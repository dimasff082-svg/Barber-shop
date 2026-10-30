# Menjalankan dan merilis BARBERFLOW

Dokumen ini adalah panduan operasional Fase 5 untuk aplikasi Laravel yang sama. Konfigurasi server publik, DNS, sertifikat, backup terkelola dan uji restore produksi tetap perlu disiapkan pada lingkungan tujuan. Keberhasilan pengujian lokal tidak menyatakan aplikasi telah dipublikasikan.

## Development Windows dengan PHP 8.5 Laragon

Buka PowerShell pada C:\kerjaan\aplikasi\barber-shop. Jalankan helper pada setiap terminal sebelum perintah PHP, Composer atau Vite; child Wayfinder juga menerima PHP 8.5 dari PATH sesi.

```powershell
. .\scripts\use-php85.ps1
php --version
composer install
npm ci
php artisan migrate
php artisan storage:link
npm run build
```

Gunakan koneksi PostgreSQL yang sudah dikonfigurasi. Jangan menyalin .env.example ke atas .env existing. Jangan menjalankan composer setup pada instalasi existing karena script tersebut menyertakan key:generate; perubahan APP_KEY dapat memutus sesi dan akses terenkripsi. Jangan memakai migrate:fresh atau seed fixture pengujian pada database usaha. storage:link cukup sekali jika tautan public/storage belum tersedia.

Aktivasi realtime lokal tersedia tanpa mengubah .env:

```powershell
. .\scripts\use-php85.ps1
php artisan barberflow:realtime-local
```

Perintah membuat identitas Reverb acak dalam storage/app/private/realtime-local.json yang tidak dimasukkan ke version control. File hanya dipakai ketika APP_ENV=local dan mengaktifkan koneksi broadcast Reverb lokal. Nilai environment untuk kredensial/endpoint tetap diprioritaskan atas fallback file. Simpan file privat ini sebagai rahasia lokal; jangan buka isinya pada output atau screenshot. Untuk menjalankan dengan konfigurasi baru, restart HTTP development server serta worker/Reverb yang sudah berjalan. Pada production file ini diabaikan dan operator harus menyediakan konfigurasi environment server sendiri.

Perintah aktivasi mempertahankan file valid yang sudah ada; menjalankannya lagi tidak mengganti secret atau port existing. Untuk menonaktifkan aktivasi lokal, hentikan proses proyek, ubah nama file privat tersebut menjadi backup di folder privat yang sama, lalu restart proses. Konfigurasi kembali mengikuti environment. Jangan memindahkan file ke public atau menimpa kredensial existing untuk pemeriksaan koneksi.

Satu terminal dapat menjalankan proses development lengkap:

```powershell
. .\scripts\use-php85.ps1
composer run dev
```

AppServiceProvider mendaftarkan queue:listen untuk realtime,default dengan tiga percobaan dan timeout 30 detik, Reverb, serta scheduler. Nama proses queue/reverb menggantikan registrasi bawaan sehingga tidak membuat worker kedua. Framework tetap menjalankan HTTP server dan Vite. Registrasi tambahan aplikasi ini hanya berlaku pada APP_ENV=local dan proses console. Pilih jalur terpadu ini atau jalur terminal terpisah berikut, sesuai kebutuhan inspeksi.

Untuk menjalankan proses secara eksplisit, gunakan terminal terpisah. Ini juga memudahkan melihat proses yang berhenti:

| Terminal | Perintah setelah helper PHP                                                      | Fungsi                                     |
| -------- | -------------------------------------------------------------------------------- | ------------------------------------------ |
| 1        | php artisan serve --host=127.0.0.1 --port=8000                                   | HTTP aplikasi                              |
| 2        | npm run dev                                                                      | Vite dan Wayfinder                         |
| 3        | php artisan queue:work --queue=realtime,default --sleep=1 --tries=3 --timeout=30 | Mengirim broadcast dan job lain            |
| 4        | php artisan reverb:start --host=127.0.0.1 --port=8080                            | WebSocket lokal                            |
| 5        | php artisan schedule:work                                                        | Expiry/no-show/penutupan hari setiap menit |

Pastikan port tidak dipakai aplikasi lain. Periksa proses yang sudah berjalan sebelum menambah listener/worker; hentikan hanya proses milik proyek yang memang akan diganti. Jalur terminal terpisah memudahkan restart satu komponen saat memeriksa gangguan.

## Konfigurasi realtime

Gunakan identitas aplikasi Reverb yang sama pada publisher Laravel dan listener Reverb. APP_KEY dan REVERB_APP_SECRET merupakan rahasia server; REVERB_APP_KEY hanya pengenal klien dan boleh muncul pada konfigurasi browser. Jangan menaruh secret pada variabel VITE_, shared props, URL, screenshot atau log. Reverb membedakan alamat listener REVERB_SERVER_HOST/PORT dan alamat publisher REVERB_HOST/PORT/SCHEME. [Dokumentasi Reverb](https://laravel.com/framework/docs/13.x/reverb) menjelaskan pemisahan ini dan allowlist origin.

Jika memakai konfigurasi environment eksplisit sebagai pengganti aktivasi lokal, contoh nilai berikut diterapkan operator tanpa mengganti isi .env yang sudah ada. Isi ID/key/secret dengan nilai unik yang dibuat privat; jangan menyalin placeholder sebagai kredensial:

```dotenv
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database
REVERB_APP_ID=<identitas-aplikasi-unik>
REVERB_APP_KEY=<kunci-publik-acak>
REVERB_APP_SECRET=<rahasia-server-acak>
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_ALLOWED_ORIGINS=127.0.0.1,localhost
```

REVERB_ALLOWED_ORIGINS menerima hostname yang dipisah koma, misalnya 127.0.0.1,localhost untuk development dan barberflow.example untuk contoh produksi. Default memakai hostname APP_URL atau allowlist loopback dari aktivasi lokal. Jangan mengaktifkan wildcard untuk mengatasi salah konfigurasi. Jika host/port/skema browser dipisahkan dari publisher, selaraskan konfigurasi publik klien dengan endpoint WebSocket yang benar. Restart proses berumur panjang setelah perubahan konfigurasi; cache konfigurasi produksi perlu dibangun ulang.

Event antrean dikirim ke channel publik queue.summary dan channel privat staff.admin/staff.barber.{id}. Payload hanya invalidasi minimal (barber_id, queue_date, type); browser membaca ulang snapshot terotorisasi dari backend. Channel privat memerlukan POST /broadcasting/auth dengan session dan CSRF, akun aktif serta kepemilikan profil. Token tiket guest tidak digunakan untuk subscribe channel staff; polling privat tiket pelanggan tetap berjalan.

Publisher menunggu commit transaksi dan menangkap kegagalan memasukkan job agar tiket yang sudah sah tetap berhasil. Broadcast memakai antrean realtime, maksimal tiga percobaan, timeout job 15 detik dan backoff 5/30/60 detik. Transport HTTP memiliki batas koneksi 3 detik dan request 10 detik. Tidak ada durable outbox: kegagalan enqueue atau job yang kehabisan retry dapat membuat invalidasi terlewat. Operator tetap perlu memantau job gagal.

Klien menggabungkan event beruntun menjadi satu refetch, membaca ulang setelah reconnect, dan tetap merekonsiliasi data setiap 60 detik saat socket sehat. Ketika socket putus, polling fallback dimulai sekitar 15 detik dan kegagalan berulang memakai backoff maksimal 120 detik. Pembaruan berhenti ketika tab tersembunyi/offline, serta menunggu saat form sedang diedit atau dialog aktif. Status koneksi staff menjelaskan kondisi tersebut; UI memakai snapshot database terakhir ketika jaringan belum pulih.

## Lingkungan produksi

Gunakan PHP 8.5 beserta ekstensi sesuai composer.lock dan PostgreSQL, Node saat build, serta web server dengan document root tepat folder public. storage dan bootstrap/cache harus dapat ditulis oleh akun aplikasi. Simpan .env, vendor, source dan backup di luar akses publik. [Panduan deployment Laravel](https://laravel.com/framework/docs/13.x/deployment) menyediakan konfigurasi awal server dan cache deployment.

Tetapkan APP_ENV=production, APP_DEBUG=false, APP_URL=https://domain-usaha, SESSION_SECURE_COOKIE=true, SESSION_HTTP_ONLY=true dan SESSION_SAME_SITE=lax. Pakai HTTPS untuk login, tiket dan WebSocket. Jika TLS diakhiri oleh reverse proxy/load balancer, atur trusted proxy hanya untuk infrastruktur yang dikenal dan uji URL/cookie yang dihasilkan. Batasi role database aplikasi serta akses panel staff dan backup sesuai kebutuhan operasional.

Contoh topologi satu domain:

| Komponen                 | Alamat                                                               |
| ------------------------ | -------------------------------------------------------------------- |
| Website dan auth channel | https://barberflow.example                                           |
| WebSocket publik         | wss://barberflow.example/app/{key}                                   |
| Reverb listener internal | 127.0.0.1:8080                                                       |
| Publisher Laravel        | REVERB_HOST=barberflow.example, REVERB_PORT=443, REVERB_SCHEME=https |

Ganti domain contoh dan sertifikat pada server tujuan. Proksi meneruskan /app/ untuk koneksi WebSocket serta /apps/ untuk publikasi bertanda tangan. Blok berikut dimasukkan ke server HTTPS aplikasi; konfigurasi PHP-FPM, sertifikat dan lokasi root mengikuti server tujuan. Header Upgrade/Connection diteruskan secara eksplisit karena tidak otomatis melewati reverse proxy. [Dokumentasi Nginx WebSocket](https://nginx.org/en/docs/http/websocket.html) menjelaskan mekanismenya.

```nginx
location /app/ {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_read_timeout 120s;
    proxy_send_timeout 120s;
}

location /apps/ {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_read_timeout 30s;
}
```

Port 8080 cukup di loopback dan tidak perlu dibuka ke internet. Jangan meneruskan /broadcasting/auth ke Reverb: endpoint itu milik Laravel dengan middleware session/CSRF. Jangan cache respons auth, halaman staff atau endpoint tiket privat pada CDN/proxy. Hindari access log yang menyimpan query/payload/header Authorization, cookie atau kredensial; log WebSocket dapat mencatat path key publik, tetapi tidak memerlukan secret.

## Proses terkelola dan rilis

Kelola PHP-FPM/web server, queue worker, Reverb dan scheduler dengan service manager lingkungan tujuan (misalnya systemd/Supervisor pada Linux atau service/task terkelola pada Windows). Konfigurasikan working directory dan executable PHP 8.5 secara eksplisit, restart saat crash, akses log terbatas dan rotasi log. Akun proses tidak memerlukan akses administrator. Worker harus mendengarkan realtime,default; worker yang hanya mendengarkan default tidak akan mengirim event antrean realtime.

Untuk produksi, jadwalkan satu pemanggilan php artisan schedule:run tiap menit melalui scheduler sistem; jangan menjalankan schedule:work dan cron schedule:run secara bersamaan untuk instalasi yang sama. Mekanisme withoutOverlapping existing perlu cache lock yang dapat dibagi bila ada lebih dari satu node. Satu cabang MVP dapat memakai satu scheduler dan satu Reverb; penskalaan lintas node memerlukan konfigurasi pub/sub/cache tambahan dan uji tersendiri.

Urutan rilis yang disarankan:

1. Jalankan suite otomatis di lingkungan tes yang terisolasi; simpan artifact build dan identitas rilis. Runner PostgreSQL menolak config cache dan memakai schema sementara, sehingga dijalankan sebelum cache produksi dibangun.
2. Backup database PostgreSQL dan upload; pastikan backup dapat dibaca kembali. Jaga APP_KEY existing serta rahasia environment pada penyimpanan privat yang dapat dipulihkan.
3. Pasang dependency dari lockfile dengan composer install --no-dev --optimize-autoloader pada artifact runtime. Build frontend memakai npm ci dan npm run build di tahap build; jangan menaruh Vite development server pada produksi.
4. Terapkan environment tujuan, jalankan php artisan migrate --force dan php artisan storage:link jika belum ada. Periksa dampak migrasi sebelum rilis; hindari migrasi destruktif otomatis dan jangan mengganti database aplikasi dengan fixture.
5. Bangun cache dengan php artisan config:cache, route:cache dan view:cache. Verifikasi route auth channel serta koneksi database sebelum menerima pelanggan.
6. Jalankan php artisan queue:restart dan php artisan reverb:restart; service manager menghidupkan proses kembali dengan kode/konfigurasi baru. Jalankan ulang/reload PHP-FPM sesuai mekanisme rilis host.
7. Periksa /up, login, halaman publik, auth channel, pengambilan/check-in tiket uji terkontrol, pembaruan dua sesi staff, serta putus-sambung WebSocket. Catat hasil dan bersihkan hanya fixture yang memang disepakati sebagai data uji.

Jika rilis bermasalah, arahkan ke artifact kode sebelumnya yang kompatibel dengan schema sekarang. Jangan langsung migrate:rollback atau restore backup di atas database aktif: periksa tiket yang baru diterbitkan, ambil backup keadaan terbaru dan tentukan prosedur pemulihan yang menjaga nomor/audit. Kegagalan realtime saja dapat ditangani dengan memulihkan worker/Reverb sementara UI menggunakan fallback; jangan mengulang pembuatan tiket untuk memicu broadcast.

## Pemeriksaan dan pemulihan

| Gejala                                     | Periksa dan tindakan                                                                                             |
| ------------------------------------------ | ---------------------------------------------------------------------------------------------------------------- |
| /up gagal                                  | Proses HTTP/PHP dan log aplikasi; health dasar tidak membuktikan WebSocket/database/worker sehat.                |
| UI berada pada fallback                    | Reverb listener, TLS/proxy, host/port/skema klien dan allowlist origin; setelah pulih UI membaca ulang snapshot. |
| WebSocket tersambung tetapi data terlambat | Worker realtime, ukuran/umur job dalam jobs, koneksi publisher ke Reverb dan queue:failed.                       |
| Auth channel 401/403/419                   | Session login, role/profil aktif, CSRF, domain/cookie dan origin; jangan menghapus middleware sebagai perbaikan. |
| Antrean online tidak menjadi missed        | Scheduler setiap menit, cache lock, timezone bisnis dan syarat grace sebenarnya; estimasi bukan pemicu missed.   |
| Upload tidak tampil                        | Tautan public/storage, izin storage/app/public dan konfigurasi web server.                                       |
| Perubahan konfigurasi belum terlihat       | Cache konfigurasi dan restart worker/Reverb/PHP.                                                                 |

php artisan queue:failed menampilkan job gagal dan dapat memuat exception teknis; baca di terminal privat. Setelah penyebab diperbaiki, operator dapat mencoba kembali ID job yang relevan dengan php artisan queue:retry <id>. Invalidation lama dapat memicu refetch tambahan tetapi tidak mengubah ulang tiket. Jangan menjalankan queue:flush, queue:clear atau penghapusan log/data otomatis saat pemulihan tanpa meninjau dampaknya.

Pantau HTTP error, latensi, autentikasi gagal/rate limit, jumlah koneksi Reverb, kegagalan enqueue/broadcast, backlog/umur job, durasi scheduler, kapasitas disk serta backup. Respons privat no-store/no-referrer harus tetap lolos melalui reverse proxy. Pengujian gangguan yang penting: hentikan Reverb sementara, pulihkan, pastikan status UI jelas dan tampilan kembali sesuai database; ulangi dengan worker berhenti agar kegagalan jalur publikasi juga terdeteksi.

## Kebijakan data operasional

Nama dan nomor HP hanya digunakan untuk antrean dan dukungan operasional, dengan akses staff sesuai role. Pemilik usaha perlu menetapkan periode retensi tertulis sebelum go-live berdasarkan kebutuhan nyata dan kewajiban yang berlaku; aplikasi tidak mengasumsikan masa simpan universal. Bedakan data pelanggan, sesi/token, audit, log teknis, upload dan backup. Informasikan tujuan penggunaan dan periode retensi pada kebijakan privasi usaha.

queue_events dan staff_audits bersifat append-only; perubahan langsung pada audit atau hard delete tiket historis bukan prosedur retensi. Permintaan penghapusan/anonymization harus ditinjau operator dengan lingkup data yang jelas, tiket aktif diselesaikan lebih dahulu, dan perubahan dianalisis terhadap indeks nomor HP, referensi pelanggan, histori dan backup. Mekanisme anonymization otomatis belum disediakan; implementasikan prosedur/migrasi khusus dengan persetujuan terpisah bila dibutuhkan. Jangan menonaktifkan trigger integritas sebagai jalan pintas.

Batasi akses log, fixture, dump database dan backup. Gunakan rotasi log (misalnya channel daily dengan periode yang disetujui), backup terenkripsi, akses minimum dan uji restore berkala pada database terpisah. Backup yang dipulihkan dapat mengembalikan data lama; terapkan kembali kebijakan retensi/permintaan yang sudah disetujui sebelum hasil restore dipakai untuk operasional.

QR statis dapat dibagikan dan nomor HP belum memakai OTP sesuai batas MVP. Keduanya bukan bukti kehadiran/kepemilikan yang kuat. Realisasi server produksi, kebijakan retensi usaha dan pengujian restore di host tujuan merupakan prasyarat operasional di luar verifikasi lokal Fase 5.
