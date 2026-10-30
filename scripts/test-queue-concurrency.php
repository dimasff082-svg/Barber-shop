<?php

declare(strict_types=1);

// Invoked by the isolated PostgreSQL runner, never against the application schema.
use App\Enums\BarberAvailability;
use App\Enums\QueueSource;
use App\Models\Barber;
use App\Models\BarberSchedule;
use App\Models\BusinessHour;
use App\Models\BusinessSetting;
use App\Models\QrCode;
use App\Models\QueueCounter;
use App\Models\QueueEvent;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Services\Queue\CreateQueueTicket;
use App\Services\Queue\QueueWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$schema = getenv('DB_SCHEMA');
if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 5 || ! $app->environment('testing')
    || ! is_string($schema) || ! preg_match('/\Abarberflow_test_[a-f0-9]{24}\z/', $schema)
    || $app->configurationIsCached() || DB::getDriverName() !== 'pgsql'
    || DB::connection()->getSchemaBuilder()->getCurrentSchemaListing() !== [$schema]) {
    fwrite(STDERR, "Concurrency test refused: isolated PostgreSQL schema and PHP 8.5 required.\n");
    exit(1);
}
CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-26 10:00:00', 'Asia/Makassar'));

if (($argv[1] ?? null) === '--worker') {
    try {
        $job = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
        $wait = (float) $job['at'] - microtime(true);
        if ($wait > 0) {
            usleep((int) min($wait * 1_000_000, 10_000_000));
        }
        if ($job['action'] === 'create') {
            $result = app(CreateQueueTicket::class)->create($job['input'], $job['owner']);
            echo json_encode(['ok' => true, 'id' => $result->ticket->id, 'number' => $result->ticket->sequence_number, 'created' => $result->created]);
        } else {
            $barber = Barber::query()->findOrFail($job['barber']);
            $ticket = app(QueueWorkflow::class)->callNext($barber->user, $barber->id);
            echo json_encode(['ok' => true, 'id' => $ticket?->id]);
        }
    } catch (ValidationException) {
        echo json_encode(['ok' => false, 'validation' => true]);
    } catch (Throwable $exception) {
        // No raw payload, token, exception message, or credentials in worker output.
        fwrite(STDERR, get_class($exception));
        exit(1);
    }
    exit(0);
}

/** @param list<array<string, mixed>> $jobs @return list<array<string, mixed>> */
function parallelQueueJobs(array $jobs): array
{
    $processes = [];
    $at = microtime(true) + 3;
    try {
        foreach ($jobs as $job) {
            $process = new Process([PHP_BINARY, __FILE__, '--worker'], dirname(__DIR__), timeout: 90);
            $process->setInput(json_encode([...$job, 'at' => $at], JSON_THROW_ON_ERROR));
            $process->start();
            $processes[] = $process;
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Concurrency worker failed: '.$process->getErrorOutput());
            }
            $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
    }
}

function requireQueueAssertion(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

try {
    // These fixtures exist only in the newly created test schema and need no deletes.
    BusinessSetting::query()->updateOrCreate(['key' => 'queue_registration_open'], ['value' => true]);
    BusinessHour::query()->updateOrCreate(['weekday' => 1], ['starts_at' => '08:00:00', 'ends_at' => '21:00:00', 'is_closed' => false]);
    $barber = Barber::factory()->create(['queue_prefix' => 'CNA', 'availability_status' => BarberAvailability::Available, 'accepts_queue' => true, 'daily_capacity' => 100]);
    $other = Barber::factory()->create(['queue_prefix' => 'CNB', 'availability_status' => BarberAvailability::Available, 'accepts_queue' => true, 'daily_capacity' => 100]);
    foreach ([$barber, $other] as $model) {
        BarberSchedule::query()->create(['barber_id' => $model->id, 'weekday' => 1, 'starts_at' => '08:00:00', 'ends_at' => '21:00:00', 'is_active' => true]);
    }
    $service = Service::factory()->create();
    $qr = QrCode::query()->create(['code' => bin2hex(random_bytes(32)), 'is_active' => true]);
    $owner = bin2hex(random_bytes(32));
    $input = fn (int $i): array => [
        'name' => 'Concurrency Fixture', 'phone' => '+62812'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
        'barber_id' => $barber->id, 'service_id' => $service->id,
        'idempotency_key' => (string) Str::uuid(),
        'source' => $i % 2 === 0 ? QueueSource::Online->value : QueueSource::Onsite->value, 'qr_code' => $qr->code,
    ];
    $jobs = [];
    for ($i = 1; $i <= 20; $i++) {
        $jobs[] = ['action' => 'create', 'input' => $input($i), 'owner' => $owner];
    }
    $results = parallelQueueJobs($jobs);
    requireQueueAssertion(count(array_filter($results, fn ($r) => $r['ok'])) === 20, '20 parallel creates did not all succeed.');
    $numbers = array_column($results, 'number');
    sort($numbers);
    requireQueueAssertion($numbers === range(1, 20), 'Parallel online/onsite counter is not contiguous or unique.');
    requireQueueAssertion(QueueTicket::query()->where('barber_id', $barber->id)->count() === 20, 'Unexpected ticket count.');
    echo "PASS: 20 parallel mixed online/on-site creates, unique shared counter 1–20.\n";

    $same = $input(21);
    $results = parallelQueueJobs(array_fill(0, 20, ['action' => 'create', 'input' => $same, 'owner' => $owner]));
    requireQueueAssertion(count(array_unique(array_column($results, 'id'))) === 1 && count(array_filter($results, fn ($r) => $r['created'] ?? false)) === 1, 'Concurrent idempotency failed.');
    requireQueueAssertion(QueueCounter::query()->where('barber_id', $barber->id)->firstOrFail()->last_number === 21, 'Idempotent retry consumed counter.');
    echo "PASS: 20 concurrent retries return one ticket and increment once.\n";

    $jobs = [];
    for ($i = 0; $i < 20; $i++) {
        $data = $input(22);
        $data['barber_id'] = $i % 2 === 0 ? $barber->id : $other->id;
        $jobs[] = ['action' => 'create', 'input' => $data, 'owner' => $owner];
    }
    $results = parallelQueueJobs($jobs);
    requireQueueAssertion(count(array_filter($results, fn ($r) => $r['ok'])) === 1, 'Concurrent active phone exclusion across barbers failed.');
    requireQueueAssertion(count(array_filter($results, fn ($r) => $r['validation'] ?? false)) === 19, 'Duplicate phone failures were not safe validation responses.');
    echo "PASS: 20 concurrent duplicate-phone creates across two barbers yield one active ticket.\n";

    // Parallel arrival order is deliberately unspecified: FIFO follows the issued DB sequence.
    $firstEligible = QueueTicket::query()->where('barber_id', $barber->id)->where('status', 'checked_in')->min('sequence_number');
    $results = parallelQueueJobs(array_fill(0, 20, ['action' => 'call', 'barber' => $barber->id]));
    requireQueueAssertion(count(array_filter($results, fn ($r) => $r['ok'] && ($r['id'] ?? null) !== null)) === 1, 'Concurrent call selected multiple tickets.');
    $called = QueueTicket::query()->where('barber_id', $barber->id)->where('status', 'called')->get();
    requireQueueAssertion($called->count() === 1 && $called->first()->sequence_number === $firstEligible, 'Concurrent call violated checked-in FIFO.');
    requireQueueAssertion(QueueEvent::query()->where('queue_ticket_id', $called->first()->id)->where('event_type', 'called')->count() === 1, 'Concurrent call duplicated audit event.');
    echo "PASS: 20 concurrent calls select exactly one eligible FIFO ticket and one audit event.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Concurrency assertion failed: '.$exception->getMessage().PHP_EOL);
    exit(1);
}
