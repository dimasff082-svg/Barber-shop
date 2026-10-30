<?php

namespace App\Console\Commands;

use App\Support\LocalRealtimeConfiguration;
use Illuminate\Console\Command;

class ConfigureLocalRealtimeCommand extends Command
{
    protected $signature = 'barberflow:realtime-local {--port=8080 : Port loopback Reverb (1024-65535)}';

    protected $description = 'Aktifkan Reverb lokal dengan kredensial privat acak tanpa mengubah .env';

    public function handle(): int
    {
        if (! app()->isLocal() || app()->configurationIsCached()) {
            $this->error('Hanya untuk APP_ENV=local tanpa cache konfigurasi.');

            return self::FAILURE;
        }
        $port = filter_var($this->option('port'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1024, 'max_range' => 65535]]);
        if ($port === false) {
            $this->error('Port harus berupa bilangan 1024-65535.');

            return self::FAILURE;
        }
        $path = storage_path('app/private/realtime-local.json');
        if (file_exists($path)) {
            if (LocalRealtimeConfiguration::read($path) === null) {
                $this->error('Konfigurasi lokal existing tidak valid; file dipertahankan untuk diperiksa.');

                return self::FAILURE;
            }
            $this->info('Konfigurasi realtime lokal sudah tersedia; kredensial dipertahankan.');

            return self::SUCCESS;
        }
        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0700, true)) {
            $this->error('Folder privat tidak dapat dibuat.');

            return self::FAILURE;
        }
        $stream = fopen($path, 'x');
        if ($stream === false) {
            $this->error('Konfigurasi tidak dapat dibuat; file existing tidak ditimpa.');

            return self::FAILURE;
        }
        $contents = json_encode(['id' => bin2hex(random_bytes(16)), 'key' => bin2hex(random_bytes(16)),
            'secret' => bin2hex(random_bytes(32)), 'port' => $port], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        $written = fwrite($stream, $contents);
        fclose($stream);
        chmod($path, 0600);
        if ($written !== strlen($contents)) {
            $this->error('Penulisan konfigurasi tidak lengkap; periksa file privat sebelum melanjutkan.');

            return self::FAILURE;
        }
        $this->info('Realtime lokal aktif. Restart server aplikasi, worker dan Reverb; .env tidak diubah.');

        return self::SUCCESS;
    }
}
