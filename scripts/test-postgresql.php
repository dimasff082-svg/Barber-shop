<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\PostgresBuilder;
use Symfony\Component\Process\Process;

function failPostgresqlTest(string $message): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}

if (PHP_SAPI !== 'cli' || PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 5) {
    failPostgresqlTest('Gunakan PHP 8.5 Laragon untuk menjalankan pengujian PostgreSQL.');
}

if (count($argv) !== 1) {
    failPostgresqlTest('Jalankan scripts/test-postgresql.php tanpa argumen tambahan; runner ini menjalankan seluruh suite.');
}

if (! extension_loaded('pdo_pgsql')) {
    failPostgresqlTest('Driver pdo_pgsql belum aktif. Jalankan dengan helper PHP 8.5 atau opsi -d extension=pdo_pgsql.');
}

$root = dirname(__DIR__);

if (is_file($root.'/bootstrap/cache/config.php')) {
    failPostgresqlTest('Pengujian ditolak karena cache konfigurasi aplikasi masih tersedia. Bersihkan cache secara eksplisit terlebih dahulu.');
}

require $root.'/vendor/autoload.php';

$schema = null;
$createdSchema = false;
$pdo = null;
$databaseName = null;
$ownerName = null;
$exitCode = 1;
$originalEnvironment = [];

try {
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    if ($app->configurationIsCached() || $app['config']->get('database.default') !== 'pgsql') {
        throw new RuntimeException('Konfigurasi target bukan PostgreSQL tanpa cache.');
    }

    $connection = $app['db']->connection('pgsql');
    $configuration = $connection->getConfig();
    $schemaBuilder = $connection->getSchemaBuilder();

    if (($configuration['driver'] ?? null) !== 'pgsql'
        || ! $schemaBuilder instanceof PostgresBuilder
        || $schemaBuilder->getCurrentSchemaListing() !== ['public']) {
        throw new RuntimeException('Jalankan runner dari koneksi PostgreSQL aplikasi dengan DB_SCHEMA=public.');
    }

    $pdo = $connection->getPdo();
    $identity = $pdo->query('SELECT current_database() AS database_name, current_user AS owner_name')->fetch(PDO::FETCH_ASSOC);

    if (! is_array($identity)
        || ! is_string($configuration['database'] ?? null)
        || $identity['database_name'] !== $configuration['database']) {
        throw new RuntimeException('Identitas database tidak sesuai dengan konfigurasi target.');
    }

    $databaseName = $identity['database_name'];
    $ownerName = $identity['owner_name'];
    $schema = 'barberflow_test_'.bin2hex(random_bytes(12));

    if (! preg_match('/\Abarberflow_test_[a-f0-9]{24}\z/', $schema)) {
        throw new RuntimeException('Nama schema pengujian tidak valid.');
    }

    $childEnvironment = [
        'APP_ENV' => 'testing',
        'APP_MAINTENANCE_DRIVER' => 'file',
        'BCRYPT_ROUNDS' => '4',
        'BROADCAST_CONNECTION' => 'null',
        'CACHE_STORE' => 'array',
        'MAIL_MAILER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
        'PULSE_ENABLED' => 'false',
        'TELESCOPE_ENABLED' => 'false',
        'NIGHTWATCH_ENABLED' => 'false',
        'DB_CONNECTION' => 'pgsql',
        'DB_HOST' => (string) ($configuration['host'] ?? '127.0.0.1'),
        'DB_PORT' => (string) ($configuration['port'] ?? '5432'),
        'DB_DATABASE' => $databaseName,
        'DB_USERNAME' => (string) ($configuration['username'] ?? ''),
        'DB_PASSWORD' => (string) ($configuration['password'] ?? ''),
        'DB_CHARSET' => (string) ($configuration['charset'] ?? 'utf8'),
        'DB_SSLMODE' => (string) ($configuration['sslmode'] ?? 'prefer'),
        'DB_URL' => '',
        'DB_SCHEMA' => $schema,
    ];

    foreach ($childEnvironment as $name => $value) {
        $originalEnvironment[$name] = getenv($name);

        if (! putenv($name.'='.$value)) {
            throw new RuntimeException('Environment proses pengujian tidak dapat disiapkan.');
        }
    }

    // Pass the same explicit environment to Process: Laravel's existing $_ENV
    // values otherwise take priority over getenv() in Symfony's inherited map.
    $extensionProbe = new Process([
        PHP_BINARY, '-r', 'exit(extension_loaded("pdo_pgsql") ? 0 : 1);',
    ], $root, $childEnvironment, timeout: 30);
    $extensionProbe->run();
    $phpCommand = [PHP_BINARY];

    if ($extensionProbe->getExitCode() === 1) {
        $phpCommand = [PHP_BINARY, '-d', 'extension=pdo_pgsql'];
    } elseif (! $extensionProbe->isSuccessful()) {
        throw new RuntimeException('Runtime PHP child tidak dapat diperiksa.');
    }

    // No IF NOT EXISTS: this invocation must own a newly created schema.
    $pdo->exec('CREATE SCHEMA "'.$schema.'"');
    $createdSchema = true;

    $preflight = <<<'PHP'
    use Illuminate\Contracts\Console\Kernel;
    use Illuminate\Database\Schema\PostgresBuilder;

    try {
        require getcwd().'/vendor/autoload.php';
        $app = require getcwd().'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        $expected = getenv('DB_SCHEMA');
        if (! is_string($expected) || ! preg_match('/\Abarberflow_test_[a-f0-9]{24}\z/', $expected)
            || $app->configurationIsCached() || $app['config']->get('database.default') !== 'pgsql') {
            throw new RuntimeException();
        }
        $connection = $app['db']->connection('pgsql');
        $builder = $connection->getSchemaBuilder();
        if (! $builder instanceof PostgresBuilder || $builder->getCurrentSchemaListing() !== [$expected]) {
            throw new RuntimeException();
        }
        $identity = $connection->selectOne('SELECT current_database() AS db, current_schema() AS schema');
        if ($identity->db !== getenv('DB_DATABASE') || $identity->schema !== $expected) {
            throw new RuntimeException();
        }
        echo "Isolasi schema PostgreSQL terverifikasi.\n";
    } catch (Throwable) {
        fwrite(STDERR, "Verifikasi isolasi schema PostgreSQL gagal; suite tidak dijalankan.\n");
        exit(1);
    }
    PHP;

    $preflightProcess = new Process([...$phpCommand, '-r', $preflight], $root, $childEnvironment, timeout: 30);

    if ($preflightProcess->run() !== 0) {
        throw new RuntimeException('Verifikasi isolasi child gagal.');
    }

    fwrite(STDOUT, 'Menjalankan seluruh suite pada schema PostgreSQL sementara yang terisolasi.'.PHP_EOL);

    $suite = new Process([
        ...$phpCommand,
        $root.'/vendor/phpunit/phpunit/phpunit',
        '--configuration', $root.'/phpunit.xml',
    ], $root, $childEnvironment, timeout: 300);
    $password = $childEnvironment['DB_PASSWORD'];
    $exitCode = $suite->run(static function (string $type, string $buffer) use ($password): void {
        // The runner never prints connection parameters or raw exception objects.
        if ($password !== '') {
            $buffer = str_replace($password, '[redacted]', $buffer);
        }

        fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer);
    });
    if ($exitCode === 0) {
        $concurrency = new Process([...$phpCommand, $root.'/scripts/test-queue-concurrency.php'], $root, $childEnvironment, timeout: 180);
        $exitCode = $concurrency->run(static function (string $type, string $buffer) use ($password): void {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $password === '' ? $buffer : str_replace($password, '[redacted]', $buffer));
        });
    }
} catch (Throwable) {
    fwrite(STDERR, 'Pengujian PostgreSQL gagal disiapkan atau dijalankan. Periksa driver, izin schema, dan konfigurasi koneksi tanpa membagikan kredensial.'.PHP_EOL);
    $exitCode = 1;
} finally {
    if ($createdSchema && $pdo instanceof PDO && is_string($schema)) {
        try {
            if (! preg_match('/\Abarberflow_test_[a-f0-9]{24}\z/', $schema)) {
                throw new RuntimeException('Nama schema cleanup tidak valid.');
            }

            $identity = $pdo->query('SELECT current_database() AS database_name, current_user AS owner_name')->fetch(PDO::FETCH_ASSOC);
            $ownerQuery = $pdo->prepare('SELECT pg_get_userbyid(nspowner) FROM pg_namespace WHERE nspname = ?');
            $ownerQuery->execute([$schema]);
            $schemaOwner = $ownerQuery->fetchColumn();

            if (! is_array($identity) || $identity['database_name'] !== $databaseName
                || $identity['owner_name'] !== $ownerName || $schemaOwner !== $ownerName) {
                throw new RuntimeException('Identitas atau ownership schema cleanup berubah.');
            }

            $pdo->exec('DROP SCHEMA "'.$schema.'" CASCADE');
            fwrite(STDOUT, 'Schema PostgreSQL sementara telah dibersihkan.'.PHP_EOL);
        } catch (Throwable) {
            fwrite(STDERR, 'Cleanup ditolak atau gagal. Periksa hanya schema uji '.$schema.'; jangan menghapus schema aplikasi.'.PHP_EOL);
            $exitCode = 1;
        }
    }

    foreach ($originalEnvironment as $name => $value) {
        putenv($value === false ? $name : $name.'='.$value);
    }
}

exit($exitCode);
