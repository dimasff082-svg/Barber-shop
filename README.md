# BARBERFLOW

Laravel 13 + React/TypeScript + Inertia, Tailwind/shadcn, PostgreSQL dan Reverb/Echo dalam satu aplikasi. **Fase 5 — Realtime dan stabilisasi selesai pada lingkup implementasi PRD. Seluruh Fase 0–5 sudah diimplementasikan; tersisa 0 fase implementasi.** Deployment publik dan konfigurasi operasional usaha belum dilakukan. Lihat [progres](docs/DEVELOPMENT_PROGRESS.md), [check-in scan QR](docs/QR_CHECK_IN.md), [realtime](docs/PHASE_5_REALTIME.md), [deployment](docs/DEPLOYMENT.md), [dashboard](docs/PHASE_4_OPERATIONS.md) dan [engine antrean](docs/PHASE_2_QUEUE_ENGINE.md).

## Development Windows

Gunakan PowerShell pada C:\kerjaan\aplikasi\barber-shop, PHP Laragon 8.5.6 dan Node/npm sesuai dependency terpasang. PostgreSQL lokal harus aktif. Jangan menimpa .env existing; .env.example berisi parameter contoh.

```powershell
. .\scripts\use-php85.ps1
php artisan barberflow:realtime-local
composer run dev
```

Aktivasi lokal membuat identitas Reverb acak dalam storage/app/private/realtime-local.json yang diabaikan version control, hanya untuk APP_ENV=local dan tanpa menimpa .env. File valid existing dipertahankan. Kredensial produksi diatur melalui environment server; .env.example hanya menyediakan key contoh kosong.

composer run dev lokal mendaftarkan lima proses: server HTTP, Vite, worker realtime,default, Reverb dan scheduler. Server biasanya http://127.0.0.1:8000 dan Reverb memakai loopback port 8080. Periksa konflik port/proses sebelum menjalankannya. Alternatif lima terminal terpisah dan prosedur restart tersedia di [panduan menjalankan aplikasi](docs/DEPLOYMENT.md#development-windows-dengan-php-85-laragon).

Helper memilih PHP 8.5 beserta driver PostgreSQL untuk sesi dan child process, termasuk Wayfinder; PHP global tidak diubah. Jangan menjalankan composer setup pada instalasi existing karena script itu menyertakan key:generate; pertahankan APP_KEY dan database usaha.

## Database dan akun

Sepuluh migrasi aplikasi sudah diterapkan tanpa reset, termasuk sumber galeri Fase 3 dan audit staff Fase 4. Migrasi tambahan berikutnya menggunakan php artisan migrate; jangan memakai migrate:fresh pada database aplikasi. Seeder tidak membuat password umum atau data usaha palsu. Admin awal sudah dibuat melalui prompt tersembunyi barberflow:create-admin.

Fase 5 tidak menambah migrasi atau fixture usaha. Event realtime dikirim setelah commit dengan payload invalidasi tanpa data pelanggan; frontend membaca ulang snapshot resmi. Channel staff privat memerlukan session/CSRF serta role/kepemilikan. Reconnect, polling fallback dan proteksi form menjaga pembaruan saat jaringan berubah. Tiket guest tetap memakai polling privat sekitar 12 detik.

Pendaftaran antrean default tertutup sampai data usaha tersedia. Admin kini dapat mengisi pengaturan usaha, jam buka/hari libur, barber beserta akun dan jadwalnya, layanan, kapasitas dan QR melalui dashboard. Pilih status barber **Tersedia**, aktifkan penerimaan antrean dan buka pendaftaran setelah seluruh data siap. Foto upload memakai disk public; public/storage sudah ditautkan melalui php artisan storage:link. Lihat [urutan konfigurasi](docs/PHASE_4_OPERATIONS.md#konfigurasi-awal-usaha).

Admin memiliki daftar/filter/detail antrean, CRUD katalog, jadwal, QR cetak/unduh SVG, audit administrasi serta laporan sederhana. Barber hanya mengakses antrean/riwayat/profilnya sendiri dan melakukan panggil → mulai → selesai sesuai aturan backend. Laporan menghitung tiket dan waktu tunggu, tanpa data pembayaran atau klaim omzet.

Tiket online kini menyediakan **Scan QR untuk Check-in**: buka kamera dan pindai QR lokasi agar tiket yang sedang dibuka langsung check-in. Alternatif foto dibaca pada perangkat tanpa upload. **Salin ID tiket** memudahkan check-in melalui portal lokasi pada sesi browser yang sama; perangkat lain memerlukan tautan pemulihan pribadi. Kamera memerlukan HTTPS/localhost dan QR harus memakai origin website yang sama. Lihat [panduan scan QR dan ID tiket](docs/QR_CHECK_IN.md).

## Validasi

```powershell
. .\scripts\use-php85.ps1
php artisan test
php scripts/test-postgresql.php
node --test scripts/test-location-qr.mjs scripts/test-ticket-client.mjs scripts/test-live-updates.mjs
php scripts/test-realtime-transport.php
php vendor/bin/pint --test
php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress
npm run types:check
npm run build
npm run build:ssr
```

Tes biasa memakai SQLite di memori. Runner PostgreSQL memakai schema sementara dengan guard isolasi/cleanup, lalu empat skenario masing-masing 20 proses paralel. Jangan mengganti target test ke schema aplikasi. Lihat [isolasi pengujian](docs/POSTGRESQL_TESTING.md).

Suite menggunakan PHPUnit existing; tes client Node memeriksa parser QR lokasi, lifecycle pemindai, parser pemulihan, pembersihan fragment sebelum router, CSRF/Authorization dan error/throttling. Akses tiket memerlukan sesi/Bearer dan mutasi web memakai CSRF. Tiket privat memakai polling sekitar 12 detik dengan state offline dan backoff. QR statis belum membuktikan lokasi fisik; HP belum memakai OTP.

Hasil historis penyelesaian Fase 5 pada PostgreSQL dan SQLite: masing-masing **224 tes, 221 lulus, 3 dilewati, 1.479 assertion**. Empat skenario concurrency masing-masing 20 proses lulus dan schema sementara dibersihkan. Pint, PHPStan (nol error), TypeScript, lint, sepuluh tes client Node, transport Reverb nyata, build produksi dan SSR lulus. Composer optimized autoload menghasilkan 8.907 class; audit Composer/npm melaporkan nol kerentanan pada pemeriksaan ini. Validasi tambahan fitur scan QR dicatat terpisah pada [progres pengembangan](docs/DEVELOPMENT_PROGRESS.md#validasi-penyempurnaan-qr).

QA browser membuktikan pembaruan admin dan barber pada sesi terpisah, fallback saat Reverb berhenti, reconnect otomatis, form dirty tetap terjaga, panggil/mulai/selesai dan responsivitas 320/375/768/1024/1440 px tanpa overflow atau error JavaScript. Detail dan batas validasi ada di [laporan Fase 5](docs/PHASE_5_REALTIME.md). npm run check masih menandai format dua spesifikasi existing PRD.md dan DESIGN_GUIDELINES.md; spesifikasi tersebut tidak diubah.

Sebelum go-live, lengkapi data usaha, domain/HTTPS/WSS, allowlist origin, process manager, backup/restore dan kebijakan retensi. Implementasi lokal selesai tidak berarti server publik telah dideploy. Kegagalan enqueue tidak memakai durable outbox; reconcile/fallback memulihkan snapshot, dan operator perlu memantau job gagal.
