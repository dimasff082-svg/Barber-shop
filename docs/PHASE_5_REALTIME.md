# Fase 5 — Realtime dan stabilisasi

Pembaruan: 29 Oktober 2026. **Fase 5 — Realtime dan stabilisasi selesai pada lingkup implementasi PRD. Seluruh Fase 0–5 sudah diimplementasikan; tersisa 0 fase implementasi.** Realtime tersedia dalam aplikasi Laravel existing, dengan validasi backend/frontend dan QA WebSocket nyata. Deployment server publik belum dilakukan; kebutuhan host tujuan dan operasional usaha dicatat terpisah. Status pemeriksaan operasional lokal terakhir ada di [progres](DEVELOPMENT_PROGRESS.md).

## Arsitektur dan integritas

Laravel Reverb dipasang melalui Composer; Laravel Echo dan pusher-js memakai toolchain Vite existing. React/TypeScript tetap berada di resources/js dengan Inertia. Pengambilan nomor, locking, FIFO, grace, otorisasi dan transisi tiket tetap diputuskan QueueWorkflow/CreateQueueTicket di backend.

Observer membaca event audit antrean dan perubahan ketersediaan yang benar-benar terjadi. Event realtime menyimpan snapshot scalar, kemudian PublishQueueUpdate mendaftarkan callback setelah commit. Rollback transaksi tidak menerbitkan invalidasi. Enqueue atau broadcast yang gagal tidak membatalkan tiket yang sudah berhasil disimpan. Log kegagalan enqueue dibatasi pada jenis event dan class exception; tidak menyalin pesan transport atau kredensial.

Semua event merupakan sinyal untuk mengambil ulang data. Payload tidak dipercaya sebagai status tiket dan tidak berisi nama pelanggan, HP, nomor/ID tiket, alasan pembatalan, hash/token guest, idempotency key atau kredensial. Frontend hanya mengganti snapshot memakai respons HTTP backend yang terotorisasi.

| Event                     | Sumber                                                              |
| ------------------------- | ------------------------------------------------------------------- |
| QueueCreated              | Pembuatan tiket online/on-site yang berhasil                        |
| QueueCheckedIn            | Check-in tiket yang sama                                            |
| QueueCalled               | Pemanggilan FIFO yang sah                                           |
| QueueStarted              | Mulai layanan                                                       |
| QueueCompleted            | Penyelesaian layanan                                                |
| QueueCancelled            | Pembatalan sesuai kebijakan guest/admin                             |
| QueueMissed               | Toleransi habis atau aturan penutupan tanggal bisnis                |
| BarberAvailabilityChanged | Perubahan availability, termasuk busy dari layanan                  |
| QueueSummaryChanged       | Grace dimulai atau perubahan katalog/pengaturan melalui audit staff |

Payload event hanya barber_id (nullable untuk perubahan global), queue_date dan type. Channel publik queue.summary serta channel privat staff.admin menerima invalidasi; staff.barber.{id} menerima invalidasi terkait barber tersebut. Staff juga mendengarkan channel publik agar perubahan global dapat memicu refetch yang aman.

Broadcast memakai queue realtime, tiga percobaan, backoff 5/30/60 detik dan timeout job 15 detik. Transport publikasi memiliki batas koneksi 3 detik/request 10 detik agar gangguan endpoint tidak menahan worker tanpa batas. Tidak ada durable outbox; bila enqueue gagal atau retry habis, invalidasi dapat terlewat. Reconcile berkala dan refetch setelah reconnect memulihkan tampilan dari database.

## Akses dan privasi

POST /broadcasting/auth memakai session web, CSRF, akun staff aktif, rate limit dan header no-store/no-referrer. Channel admin hanya mengizinkan admin aktif; channel barber memeriksa ulang akun aktif, role barber, profil aktif dan kepemilikan ID yang diminta. Tamu serta barber lain tidak memperoleh signature channel privat. Nilai socket/channel dibatasi format dan panjangnya. Broadcaster nonaktif tidak memberikan auth yang seolah berhasil.

Tidak ada channel guest berdasarkan ID tiket atau HP. Tiket pelanggan mempertahankan polling privat sekitar 12 detik menggunakan sesi/Bearer yang sudah tersedia. Token mentah tetap tidak masuk shared props, URL request, history atau storage browser persisten. Shared props realtime hanya memuat pengenal aplikasi publik, endpoint host/port/skema, status aktivasi dan nama channel yang sesuai peran; secret server tetap privat.

Reverb membatasi origin sesuai hostname konfigurasi dan menolak client events. Listener lokal memakai loopback. Produksi perlu HTTPS/WSS dan allowlist domain usaha. Auth channel tetap menuju Laravel melalui domain session yang sama; route tersebut tidak diteruskan ke proses WebSocket.

## Pembaruan dan gangguan koneksi

Dashboard admin/barber, daftar/detail antrean dan laporan membaca ulang prop yang relevan melalui partial reload Inertia. Halaman publik ringkasan antrean/barber/form hanya membaca ulang daftar barber serta status penerimaan. Event beruntun digabung menjadi satu refetch, dan event yang masuk saat request berjalan memicu satu refetch lanjutan. Filter serta query halaman tetap dipertahankan.

Saat socket sehat, klien merekonsiliasi snapshot setiap 60 detik. Saat koneksi putus, fallback dimulai sekitar 15 detik dan kegagalan berulang memakai backoff maksimal 120 detik. Reconnect, kembali online atau kembali ke tab aktif memicu pembacaan ulang. Reconnect transport mengikuti retry Pusher 15/30/60 detik; saat server baru pulih, perpindahan dari fallback ke socket dapat memerlukan sekitar 1–2 menit, bukan seketika. Tab tersembunyi/offline menghentikan pekerjaan berkala. Otorisasi HTTP yang ditolak menghentikan pembaruan data privat.

Input yang aktif, form dirty, submit dan dialog konfirmasi menunda refresh. Request background yang masih berjalan dibatalkan ketika pengguna mulai mengedit atau navigasi/form foreground dimulai, sehingga respons lama tidak menimpa pilihan yang sedang diisi. Listener socket, timer, request auth dan event DOM dibersihkan saat halaman/peran berubah atau komponen dilepas.

Status staff menampilkan Terhubung langsung, pembaruan berkala, offline, menunggu form, gangguan atau akses yang perlu diperbarui. Data terakhir tetap dapat dibaca ketika koneksi terputus. Label fallback tidak membuktikan WebSocket bekerja; QA perlu memastikan handshake, subscribe privat, publikasi dari worker dan refetch pada sesi kedua benar-benar terjadi.

## Aktivasi dan proses development

Semua PHP/Artisan/tooling memakai PHP 8.5 Laragon melalui scripts/use-php85.ps1, termasuk child Wayfinder. Perintah barberflow:realtime-local membuat kredensial acak lokal pada storage/app/private/realtime-local.json tanpa menimpa .env. File existing yang valid dipertahankan, hanya dipakai ketika APP_ENV=local dan tidak masuk version control. Produksi mengabaikan file tersebut serta memakai environment server yang dikelola operator.

```powershell
. .\scripts\use-php85.ps1
php artisan barberflow:realtime-local
composer run dev
```

Registrasi DevCommands lokal menghasilkan lima proses: HTTP server, Vite, queue:listen untuk realtime,default, Reverb dan scheduler. Nama queue/reverb menggantikan default framework/package sehingga tidak menggandakan worker. Alternatif terminal terpisah, konfigurasi TLS/proxy, restart rilis, monitoring dan pemulihan dijelaskan pada [panduan deployment](DEPLOYMENT.md).

## File utama

Reverb 1.12 memerlukan rantai Guzzle/PSR-7 versi yang kompatibel. Resolver Composer memakai perubahan minimum: Guzzle 7, promises 2 dan PSR-7 2 menggantikan major yang sebelumnya terkunci, serta patch Symfony MIME. Suite aplikasi dan audit dependency lulus setelah perubahan; framework dan starter kit dipertahankan.

| Area                   | File/folder                                                                                        |
| ---------------------- | -------------------------------------------------------------------------------------------------- |
| Dependency             | composer.json, composer.lock, package.json, package-lock.json                                      |
| Event                  | app/Events/QueueInvalidated.php dan event antrean/availability turunannya                          |
| Publish setelah commit | app/Services/Realtime/PublishQueueUpdate.php                                                       |
| Sumber perubahan       | app/Observers/QueueEventObserver.php, BarberObserver.php, StaffAuditObserver.php                   |
| Channel dan auth       | routes/channels.php, app/Http/Controllers/Realtime/BroadcastAuthController.php, bootstrap/app.php  |
| Konfigurasi            | config/broadcasting.php, config/reverb.php, config/barberflow.php                                  |
| Aktivasi lokal         | app/Support/LocalRealtimeConfiguration.php, app/Console/Commands/ConfigureLocalRealtimeCommand.php |
| Shared props/proses    | app/Http/Middleware/HandleInertiaRequests.php, app/Providers/AppServiceProvider.php                |
| Reconnect/refetch      | resources/js/hooks/use-live-updates.ts, resources/js/lib/live-refresh.ts, broadcast-auth.ts        |
| Status UI              | resources/js/components/staff/live-update-status.tsx dan integrasi halaman/form                    |
| Development assets     | vite.config.ts; host server/HMR loopback 127.0.0.1                                                 |
| Transport uji          | scripts/test-realtime-transport.php dan helper Node; fixture proses/database terisolasi            |
| Dokumentasi            | docs/DEPLOYMENT.md, README.md, docs/DEVELOPMENT_PROGRESS.md                                        |

## Validasi akhir

Smoke transport nyata telah lulus melalui scripts/test-realtime-transport.php: subscription public/admin/barber pemilik, tidak ada job setelah rollback, satu job setelah commit, metadata minimal, penolakan origin asing dan client event palsu. QA browser membuktikan hitungan dashboard admin berubah 1→2 melalui WebSocket, lalu fallback membaca 2→3 setelah Reverb dihentikan. Setelah server dipulihkan, retry Pusher mengembalikan koneksi otomatis.

Ketika pilihan availability diubah menjadi Istirahat tetapi belum disimpan, refresh menunggu dan pilihan tetap terjaga meskipun tiket keempat dibuat; setelah pilihan dibersihkan, snapshot membaca 4. Barber pada sesi HTTP terpisah (localhost, admin memakai 127.0.0.1) berlangganan channel privat dan melihat hitungan 4→5. Alur panggil/mulai/selesai dengan konfirmasi berhasil, availability terkunci ketika sedang dilayani, kemudian layanan selesai tercatat 1. Tidak ada error JavaScript dan tidak ada overflow pada 320/375/768/1024/1440 px. Bukti tampilan memakai fixture terisolasi: docs/previews/phase5-admin-live.jpg dan phase5-barber-live-mobile.jpg.

Landing QA memperbarui ringkasan publik 4→5 menunggu setelah tiket baru dibuat tanpa reload; nama pelanggan QA tidak muncul pada body publik. Startup terpadu aplikasi utama juga menjalankan lima proses nyata pada port HTTP 8000, Vite 5173 dan Reverb 8080 beserta worker/scheduler. Halaman utama memuat kode baru dan closed state sesuai konfigurasi usaha kosong. Vite development host/HMR dipatok ke loopback 127.0.0.1 untuk menghindari alamat hot IPv6 yang tidak cocok; hasil verifikasi operasional terakhir dicatat di progres.

| Pemeriksaan                                         | Posisi                                                                   |
| --------------------------------------------------- | ------------------------------------------------------------------------ |
| Review payload, channel, CSRF dan failure isolation | Review kode dan feature tests lulus                                      |
| PHP 8.5 dan registrasi lima DevCommands             | Terverifikasi melalui bootstrap baca-saja                                |
| SQLite terisolasi                                   | 224 tes: 221 lulus, 3 dilewati, 1.479 assertion                          |
| PostgreSQL terisolasi                               | 224 tes: 221 lulus, 3 dilewati, 1.479 assertion; sama dengan SQLite      |
| Empat skenario concurrency × 20 proses              | Semua lulus dan schema sementara dibersihkan                             |
| Tes client akses tiket dan realtime                 | Sepuluh tes Node lulus                                                   |
| Pint, PHPStan, TypeScript dan lint                  | Lulus; PHPStan/Larastan nol error                                        |
| Build produksi dan SSR                              | Keduanya lulus                                                           |
| QA dua sesi staff dan gangguan koneksi              | Update WebSocket, fallback, reconnect, proteksi form dan workflow lulus  |
| Composer optimized autoload                         | Lulus: menghasilkan 8.907 class; scan awal Windows lambat tetapi selesai |
| Audit Composer dan npm                              | Nol kerentanan pada pemeriksaan dependency ini                           |
| Deployment publik dan restore backup produksi       | Belum dilakukan; lingkungan tujuan diperlukan                            |

Tiga skip Fortify berasal dari fitur opsional existing yang tidak diaktifkan. Runner PostgreSQL memakai schema sementara dengan guard/cleanup miliknya; jangan menjalankan RefreshDatabase atau migrate:fresh terhadap schema aplikasi. Lihat [isolasi tes](POSTGRESQL_TESTING.md). Pemeriksaan gabungan npm run check masih menandai format dua spesifikasi existing PRD.md dan DESIGN_GUIDELINES.md; spesifikasi tersebut tidak diubah. Warning fontaine opsional existing tidak menghalangi build.

Smoke transport dapat dijalankan secara terpisah:

```powershell
. .\scripts\use-php85.ps1
php scripts/test-realtime-transport.php
```

Runner menggunakan SQLite baru yang terisolasi, kredensial sintetis melalui stdin, proses Reverb dan queue worker miliknya sendiri, lalu membersihkan file/proses fixture tersebut. Otorisasi HTTP session/CSRF diuji terpisah oleh feature tests; script wire transport tidak menggantikan QA browser dua sesi maupun verifikasi server produksi.

## Prasyarat operasional dan batas MVP

Usaha tetap perlu mengisi profil, layanan, barber/akun, jam buka, jadwal, QR dan kapasitas nyata, lalu membuka penerimaan ketika siap. Worker realtime, Reverb dan scheduler harus aktif saat digunakan. Jika hanya HTTP/Vite yang berjalan, pengiriman realtime atau expiry tidak berjalan lengkap.

Server tujuan perlu domain/HTTPS, konfigurasi origin dan endpoint WebSocket, process manager, izin storage, backup/restore serta pemantauan. Kebijakan retensi/anonymization ditetapkan pemilik usaha; tidak ada penghapusan data pelanggan atau audit otomatis pada fase ini. Audit dan histori tetap append-only. QR statis belum membuktikan kehadiran fisik dan nomor HP belum diverifikasi OTP, sesuai batas MVP.

Seluruh fase PRD 0–5 selesai pada lingkup implementasi; penerapan ke server publik serta konfigurasi operasional usaha tetap dilaporkan terpisah berdasarkan bukti aktual. Tidak ada migrasi baru, reset database, seed fixture usaha atau perubahan .env pada Fase 5. Konfigurasi lokal privat dipisahkan dari environment produksi.
