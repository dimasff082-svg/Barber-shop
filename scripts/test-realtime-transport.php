<?php

declare(strict_types=1);

use App\Enums\BarberAvailability;
use App\Models\Barber;
use App\Models\BarberSchedule;
use App\Models\BusinessHour;
use App\Models\BusinessSetting;
use App\Models\Service;
use App\Services\Queue\CreateQueueTicket;
use App\Services\Queue\QueueRules;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

// Database/transport credentials are overridden before bootstrap; .env is never changed.
if (PHP_SAPI !== 'cli' || PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 5 || count($argv) !== 1) {
    fwrite(STDERR, "Gunakan PHP 8.5 dan jalankan scripts/test-realtime-transport.php tanpa argumen.\n");
    exit(1);
}
$root = dirname(__DIR__);
if (is_file($root.'/bootstrap/cache/config.php')) {
    fwrite(STDERR, "Smoke test ditolak: konfigurasi aplikasi masih di-cache.\n");
    exit(1);
}
require $root.'/vendor/autoload.php';

$directory = realpath($root.'/storage/framework/testing');
if ($directory === false || ! is_dir($directory)) {
    fwrite(STDERR, "Folder testing workspace belum tersedia.\n");
    exit(1);
}
$database = $directory.DIRECTORY_SEPARATOR.'realtime-smoke-'.bin2hex(random_bytes(12)).'.sqlite';
$owned = false;
$reverb = $subscriber = $worker = null;
$stream = null;
$app = null;
$exitCode = 1;
try {
    // Exclusive create: this invocation may migrate and remove only its own new file.
    $handle = fopen($database, 'x');
    if ($handle === false) {
        throw new RuntimeException('Cannot create isolated SQLite file.');
    }
    fclose($handle);
    $owned = true;
    $port = null;
    for ($candidate = 19010; $candidate <= 19110; $candidate++) {
        $probe = @stream_socket_server('tcp://127.0.0.1:'.$candidate, $errorNumber, $errorMessage);
        if ($probe !== false) {
            fclose($probe);
            $port = $candidate;
            break;
        }
    }
    if ($port === null) {
        throw new RuntimeException('No isolated loopback port available.');
    }
    $key = bin2hex(random_bytes(16));
    $secret = bin2hex(random_bytes(32));
    $environment = [
        'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1',
        'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'BCRYPT_ROUNDS' => '4',
        'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '',
        'QUEUE_CONNECTION' => 'database', 'DB_QUEUE_CONNECTION' => 'sqlite',
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array',
        'BROADCAST_CONNECTION' => 'reverb', 'REVERB_APP_ID' => bin2hex(random_bytes(16)),
        'REVERB_APP_KEY' => $key, 'REVERB_APP_SECRET' => $secret,
        'REVERB_HOST' => '127.0.0.1', 'REVERB_PORT' => (string) $port, 'REVERB_SCHEME' => 'http',
        'REVERB_SERVER_HOST' => '127.0.0.1', 'REVERB_SERVER_PORT' => (string) $port,
        'REVERB_ALLOWED_ORIGINS' => '127.0.0.1',
        'LOG_CHANNEL' => 'stderr', 'LOG_LEVEL' => 'emergency',
        'PULSE_ENABLED' => 'false', 'TELESCOPE_ENABLED' => 'false', 'NIGHTWATCH_ENABLED' => 'false',
    ];
    foreach ($environment as $name => $value) {
        putenv($name.'='.$value);
        $_ENV[$name] = $_SERVER[$name] = $value;
    }
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $connection = DB::connection();
    if ($app->configurationIsCached() || ! $app->environment('testing') || $connection->getDriverName() !== 'sqlite'
        || realpath((string) $connection->getConfig('database')) !== $database
        || $connection->getSchemaBuilder()->getTables() !== []) {
        throw new RuntimeException('SQLite isolation guard failed.');
    }
    $identity = $connection->selectOne('PRAGMA database_list');
    if ($identity === null || realpath($identity->file) !== $database) {
        throw new RuntimeException('SQLite file identity guard failed.');
    }
    if (Artisan::call('migrate', ['--force' => true]) !== 0) {
        throw new RuntimeException('Isolated migrations failed.');
    }

    $now = app(QueueRules::class)->businessNow();
    BusinessSetting::query()->create(['key' => 'queue_registration_open', 'value' => true]);
    BusinessHour::query()->create(['weekday' => $now->dayOfWeek, 'starts_at' => '00:00:00', 'ends_at' => '23:59:59', 'is_closed' => false]);
    $barber = Barber::factory()->create(['queue_prefix' => 'WS', 'availability_status' => BarberAvailability::Available, 'accepts_queue' => true]);
    BarberSchedule::query()->create(['barber_id' => $barber->id, 'weekday' => $now->dayOfWeek, 'starts_at' => '00:00:00', 'ends_at' => '23:59:59', 'is_active' => true]);
    $service = Service::factory()->create(['estimated_duration_minutes' => 30]);

    $reverb = new Process([PHP_BINARY, 'artisan', 'reverb:start', '--host=127.0.0.1', '--port='.$port], $root, $environment, timeout: 90);
    $reverb->start();
    $deadline = microtime(true) + 15;
    do {
        if (! $reverb->isRunning()) {
            throw new RuntimeException('Reverb exited before accepting connections.');
        }
        $socket = @fsockopen('127.0.0.1', $port, $errorNumber, $errorMessage, 0.1);
        if ($socket !== false) {
            fclose($socket);
            break;
        }
        usleep(100_000);
    } while (microtime(true) < $deadline);
    if ($socket === false) {
        throw new RuntimeException('Reverb did not become ready.');
    }
    $stream = new InputStream;
    $subscriber = new Process(['node', 'scripts/test-realtime-transport.mjs'], $root, $environment, timeout: 90);
    $subscriber->setInput($stream);
    $subscriber->start();
    $stream->write(json_encode(['key' => $key, 'secret' => $secret, 'port' => $port, 'barber_id' => $barber->id], JSON_THROW_ON_ERROR)."\n");
    $messages = [];
    $buffer = '';
    $waitFor = function (string $kind) use ($subscriber, &$messages, &$buffer): array {
        $until = microtime(true) + 20;
        do {
            $buffer .= $subscriber->getIncrementalOutput();
            while (($position = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $position);
                $buffer = substr($buffer, $position + 1);
                $message = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                if (($message['kind'] ?? null) === 'failure') {
                    throw new RuntimeException($message['message']);
                }
                $messages[$message['kind']] = $message;
            }
            if (isset($messages[$kind])) {
                return $messages[$kind];
            }
            if (! $subscriber->isRunning()) {
                throw new RuntimeException('WebSocket subscriber exited.');
            }
            usleep(50_000);
        } while (microtime(true) < $until);

        throw new RuntimeException('WebSocket result deadline: '.$kind);
    };
    $waitFor('ready');
    $waitFor('origin_rejected');
    echo "PASS: public/private subscriptions connected; foreign Origin rejected.\n";
    $input = ['name' => 'PRIVATE_SMOKE_CUSTOMER', 'phone' => '081299991234', 'barber_id' => $barber->id,
        'service_id' => $service->id, 'idempotency_key' => (string) Str::uuid(), 'source' => 'online'];
    DB::beginTransaction();
    app(CreateQueueTicket::class)->create($input, str_repeat('owner', 10));
    if (DB::table('jobs')->count() !== 0) {
        throw new RuntimeException('Broadcast enqueued before transaction commit.');
    }
    DB::rollBack();
    $stream->write("{\"action\":\"quiet\"}\n");
    $waitFor('quiet');
    echo "PASS: rolled back ticket produced no job and no WebSocket event.\n";

    DB::beginTransaction();
    app(CreateQueueTicket::class)->create($input, str_repeat('owner', 10));
    if (DB::table('jobs')->count() !== 0) {
        throw new RuntimeException('Broadcast enqueued inside a transaction.');
    }
    DB::commit();
    if (DB::table('jobs')->where('queue', 'realtime')->count() !== 1) {
        throw new RuntimeException('Committed ticket did not enqueue exactly one event.');
    }
    $worker = new Process([PHP_BINARY, 'artisan', 'queue:work', '--queue=realtime', '--stop-when-empty', '--sleep=1', '--tries=3', '--timeout=30'], $root, $environment, timeout: 30);
    $worker->run();
    if (! $worker->isSuccessful()) {
        throw new RuntimeException('Queue worker failed.');
    }
    $result = $waitFor('delivered');
    $channels = array_column($result['records'], 'channel');
    sort($channels);
    $expected = ['private-staff.admin', 'private-staff.barber.'.$barber->id, 'queue.summary'];
    sort($expected);
    if ($channels !== $expected) {
        throw new RuntimeException('Broadcast destination mismatch.');
    }
    foreach ($result['records'] as $record) {
        if ($record['data'] !== ['barber_id' => $barber->id, 'queue_date' => $now->toDateString(), 'type' => 'created']) {
            throw new RuntimeException('Broadcast privacy payload mismatch.');
        }
    }
    if (DB::table('jobs')->count() !== 0 || DB::table('failed_jobs')->count() !== 0) {
        throw new RuntimeException('Transport job did not finish cleanly.');
    }
    echo "PASS: committed ticket delivered via actual queue worker and Reverb to all three channels; payload contains only invalidation metadata.\n";
    $stream->write("{\"action\":\"spoof\"}\n");
    $waitFor('client_events_disabled');
    echo "PASS: client event spoofing is rejected by Reverb.\n";
    $stream->write("{\"action\":\"finish\"}\n");
    $exitCode = 0;
} catch (Throwable $exception) {
    // All runtime errors deliberately omit raw transport errors/credentials.
    fwrite(STDERR, 'FAIL realtime transport smoke ('.$exception::class.').'.PHP_EOL);
} finally {
    $stream?->close();
    foreach ([$subscriber, $worker, $reverb] as $process) {
        if ($process instanceof Process && $process->isRunning()) {
            $process->stop(2);
        }
    }
    if ($app !== null) {
        try {
            DB::disconnect('sqlite');
        } catch (Throwable) {
            // Bootstrap may have failed before the database facade was initialized.
        }
    }
    if ($owned && dirname($database) === $directory && preg_match('/\Arealtime-smoke-[a-f0-9]{24}\.sqlite\z/', basename($database))
        && realpath($database) === $database && ! is_link($database)) {
        foreach ([$database.'-wal', $database.'-shm', $database.'-journal', $database] as $file) {
            if (is_file($file) && ! is_link($file)) {
                unlink($file);
            }
        }
        echo "Database SQLite transport sementara telah dibersihkan.\n";
    }
}
exit($exitCode);
