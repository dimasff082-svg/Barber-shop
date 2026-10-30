# BARBERFLOW — Posisi pengembangan

Pembaruan: 30 Oktober 2026.

**Tahap saat ini: Fase 5 — Realtime dan stabilisasi selesai pada lingkup implementasi PRD. Seluruh Fase 0–5 sudah diimplementasikan; tersisa 0 fase implementasi.** Reverb/Echo, channel privat, publish setelah commit, reconnect/fallback, stabilisasi serta dokumentasi deployment tersedia. Server produksi belum dipublikasikan; prasyarat operasional tetap perlu dipenuhi di lingkungan tujuan.

| Fase PRD                     | Posisi                                                                                                                         |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| 0 — Audit proyek             | Selesai; Laravel React Starter Kit existing dipertahankan.                                                                     |
| 1 — Pondasi dan autentikasi  | Selesai; PostgreSQL, roles/policies, autentikasi, kerangka staff, dan akun admin aktif terverifikasi.                          |
| 2 — Engine antrean           | Selesai; schema, transaksi, token guest, QR check-in, state machine, scheduler, endpoint aman, serta concurrency PostgreSQL.   |
| 3 — UI pelanggan             | Selesai: halaman publik, form, portal QR, tiket digital, pemulihan akses, polling privat, pengujian dan QA responsif.          |
| 4 — Dashboard operasional    | Selesai; implementasi, PostgreSQL/SQLite, concurrency, analisis statis, build dan QA responsif lulus.                          |
| 5 — Realtime dan stabilisasi | Implementasi selesai; realtime privat, after-commit, reconnect/fallback, pengujian, build dan dokumentasi deployment tersedia. |

## Hasil Fase 2

- Counter per barber/tanggal bisnis dipakai bersama online/on-site, dengan transaksi dan row lock. Nomor batal tidak digunakan kembali.
- Idempotency UUID diikat pada hash pemilik sesi dan payload kanonis. Retry tidak membuat tiket atau mengeluarkan ulang token.
- Normalisasi HP dan partial unique index mencegah satu HP memiliki dua tiket aktif pada hari yang sama, termasuk pada barber berbeda.
- Token acak 256 bit hanya disimpan sebagai hash. Detail/check-in/cancel memerlukan sesi tiket atau Bearer; ID dan nomor HP tidak cukup.
- QR aktif diperlukan untuk on-site dan check-in online. Check-in mempertahankan nomor yang sama.
- FIFO checked-in, panggil/mulai/selesai, toleransi default 5 menit, no-show, cancellation guest/admin, serta penjagaan availability.
- Jam buka, libur, jadwal, kapasitas, layanan aktif dan toggle penerimaan diperiksa backend. Konfigurasi belum tersedia menutup pendaftaran.
- Snapshot harga/durasi, estimasi rentang, audit append-only dan larangan hard delete tiket historis.
- Endpoint web dengan session/CSRF, rate limit, session lock, whitelist respons, no-store dan no-referrer termasuk pada error.
- Expiry setiap menit dengan withoutOverlapping dan pemeriksaan idempoten; layanan melewati tengah malam tetap dapat diselesaikan.

## Hasil Fase 3

- Landing terhubung data backend; halaman layanan, barber, galeri dan kontak memakai katalog aktif serta informasi usaha yang tersedia.
- Navbar/CTA menghubungkan halaman nyata; komponen publik reusable, placeholder netral, galeri ilustrasi berlabel dan empty/closed state.
- Form Inertia useForm dengan progress, pilihan layanan/barber, data pelanggan, ringkasan, persetujuan dan pengiriman idempoten.
- Portal QR menyediakan Ambil Antrean Baru dan Check-in Antrean Saya; check-in tidak mengubah nomor.
- Tiket digital memakai pembungkus generik tanpa data privat pada props, polling terotorisasi 12 detik, status berikon/label, informasi estimasi dan konfirmasi cancel.
- Pemulihan akses memakai fragment yang dibersihkan sebelum router aktif. Handoff token terenkripsi dalam sesi maksimal 5 menit, read/ack, dan token hanya berada di memori klien; tidak disimpan pada props/history/penyimpanan browser persisten.
- Polling berhenti ketika hidden/offline, terminal atau akses ditolak, menggunakan backoff serta menampilkan waktu pembaruan terakhir.
- Migrasi/model galeri ditambahkan sebagai sumber baca; pengelolaan CRUD tetap Fase 4. Tidak ada perubahan .env, seed usaha nyata atau aturan engine Fase 2.

## Hasil Fase 4

- Dashboard admin dan barber memakai data resmi tanggal bisnis, termasuk KPI aktif/menunggu/check-in/dipanggil/dilayani/selesai/batal/terlewat serta barber aktif.
- Barber hanya mengakses antrean, detail, riwayat dan profilnya sendiri; panggil/mulai/selesai memakai engine transaksi existing. Tandai terlewat memeriksa ulang toleransi yang sah, dan pembatalan admin memerlukan alasan.
- CRUD barber + akun, layanan dan galeri memakai Form Requests, pencarian/pagination, editor dan konfirmasi arsip. Arsip tidak menghapus histori; snapshot tiket lama tidak berubah mengikuti harga/durasi katalog.
- Pengaturan profil usaha, kontak/peta, penerimaan antrean, kapasitas dan toleransi; jam buka tujuh hari, jadwal barber serta tanggal libur khusus.
- QR acak dengan label/aktivasi, gambar/unduh SVG dan halaman cetak A4 tanpa sidebar; target portal ditentukan server.
- Laporan jumlah tiket per hari/sumber/hasil, layanan selesai per barber/layanan, dan rata-rata check-in sampai mulai hanya dari tiket selesai dengan timestamp sah. Tidak ada klaim pembayaran/omzet.
- Audit administrasi append-only dengan trigger database dan redaksi metadata sensitif. Respons staff no-store/no-referrer, history Inertia terenkripsi, props whitelist dan HP pelanggan dimasking.
- Komponen staff reusable, tema dark/emas, menu sesuai role, drawer mobile, breadcrumb Indonesia dan kontrol utama 44 px. Login starter tetap dipertahankan.
- Migrasi audit 000700 diterapkan secara normal dan tautan storage publik dibuat. Tidak ada reset database, fixture usaha atau perubahan .env.

## Hasil Fase 5

- Reverb, Echo dan pusher-js dipasang pada proyek Laravel/React existing; tidak ada frontend terpisah.
- Delapan event PRD dan QueueSummaryChanged berasal dari perubahan audit/availability nyata, memakai snapshot scalar dan publish setelah commit. Rollback tidak mengirim invalidasi; kegagalan enqueue/broadcast tidak membatalkan transaksi tiket sah.
- Payload publik maupun privat hanya barber_id, queue_date dan type; tidak membawa data pelanggan, ticket ID, token atau kredensial. Channel staff.admin/staff.barber.{id} memakai POST auth session/CSRF, akun/profil aktif, role/kepemilikan dan rate limit.
- Partial refetch terotorisasi menggabungkan event beruntun, memulihkan data saat reconnect/online/visible, fallback sekitar 15 detik dengan backoff maksimal 120 detik, serta reconcile 60 detik saat socket sehat.
- Form dirty, fokus input, dialog dan submit menunda refresh; request background dibatalkan saat editing/navigasi agar tidak menimpa pilihan pengguna. Listener/timer dibersihkan saat pergantian halaman/peran.
- Aktivasi Reverb lokal menghasilkan file kredensial privat yang diabaikan version control dan hanya dipakai APP_ENV=local. .env existing serta PHP global tidak diubah; produksi memakai environment operator.
- composer run dev lokal mendaftarkan tepat lima proses: HTTP, Vite, worker realtime,default, Reverb dan scheduler. Dokumentasi mencakup deployment TLS/proxy, service manager, monitoring, recovery dan kebijakan retensi/anonymization yang memerlukan prosedur operator.
- Fase 5 tidak menambah migrasi atau seed data usaha. Tidak ada durable outbox; invalidasi yang terlewat dipulihkan melalui reconcile/fallback dan operator memantau job gagal.

## Penyempurnaan setelah Fase 5 — Scan QR dan ID tiket

- Tiket digital menampilkan ID 26 karakter dengan tombol Salin ID tiket. ID dipakai untuk memilih tiket pada portal QR; sesi atau token rahasia tetap diperlukan untuk otorisasi.
- Tombol Scan QR untuk Check-in menggantikan input kode QR manual pada tiket online yang menunggu check-in. QR lokasi yang terbaca langsung dikirim ke endpoint check-in tiket tersebut; nomor antrean tetap sama.
- Komponen pemindai reusable memakai kamera belakang bila tersedia, penghentian stream/timer saat ditutup atau halaman tidak aktif, serta pencegahan submit ganda. Alternatif baca foto PNG/JPG/WebP memproses foto pada perangkat tanpa upload.
- Parser hanya menerima portal QR pada origin yang sama, menolak URL asing/credentials/query/fragment atau path yang tidak sesuai, dan tidak membuka alamat hasil scan.
- Portal lokasi memakai kolom ID tiket biasa dan kolom pemulihan terpisah bertipe password untuk browser/perangkat lain. ID dari tautan pemulihan harus cocok dengan tiket pilihan.
- Engine, FIFO, toleransi, endpoint dan otorisasi backend dipertahankan. Tidak ada migrasi, perubahan `.env`, reset data, atau perubahan PHP global. Dependency pembaca QR dimuat saat dibutuhkan.
- Posisi tetap **Fase 5 selesai**, penyempurnaan UI pelanggan pasca-Fase 5; **tersisa 0 fase implementasi PRD**. Panduan penggunaan dan batas kamera/QR statis tersedia di [check-in scan QR](QR_CHECK_IN.md).

### Validasi penyempurnaan QR

- PHP 8.5 Laragon: **38 tes terkait lulus, 441 assertion** (ScannedTicketCheckInTest, QueueHttpTest, QueueWorkflowTest, CustomerPagesTest). Pint untuk tes baru lulus.
- **15 tes Node lulus**: lima tes parser/lifecycle/decoder pemindai QR, empat tes akses/fragment tiket, dan enam tes pembaruan realtime. Regresi memakai PNG QR valid asli, rotasi 90 derajat, dan gambar kosong; pembacaan foto menambahkan fallback untuk QR ekspor yang gagal dideteksi dengan cara biasa.
- TypeScript, lint frontend, format file yang diubah, serta build produksi dan SSR lulus. Warning fontaine opsional existing tidak menghalangi build. Pengulangan tes PHP setelah build selesai lulus 38/38; percobaan bersamaan dengan build sempat kehilangan CSS font sementara saat direktori aset diganti.
- QA browser dengan SQLite terisolasi: salin ID berhasil, QR asing ditolak, QR nonaktif ditolak backend, dan foto QR aktif langsung check-in tiket QR-003 dengan ID/nomor tetap. Portal lokasi menerima ID tiket pada sesi yang sama dan check-in QR-004 tanpa nomor baru. Tidak ada error JavaScript pada pemeriksaan akhir.
- Tiket dan dialog pemindai diuji pada 320/375/768/1024/1440 px tanpa overflow horizontal. Bukti tanpa token/password: docs/previews/ticket-qr-scanner-mobile.png, ticket-qr-checkin-success-mobile.png dan ticket-id-checkin-mobile.png. Kamera fisik belum diuji; validasi pada ponsel pelanggan dengan izin kamera dan HTTPS deployment masih diperlukan. Hasil historis Fase 5 di bawah dipertahankan sebagai catatan terpisah.
- Tab/server QA ditutup, viewport dipulihkan dan sembilan file database/helper/gambar sementara QA dibersihkan. Fixture regresi QR yang diperlukan tes dipertahankan pada tests/Fixtures/qr; database usaha tidak diubah.

## Catatan historis validasi — Fase 5

- Runtime PHP/Artisan/tooling: **PHP 8.5.6 Laragon**, termasuk child Wayfinder. Registrasi DevCommands terverifikasi menghasilkan lima proses tanpa worker default ganda.
- PostgreSQL terisolasi dan SQLite di memori: masing-masing **224 tes, 221 lulus, 3 dilewati, 1.479 assertion**. Tiga skip Fortify berasal dari fitur opsional existing.
- Empat batch concurrency PostgreSQL masing-masing **20 proses paralel** lulus: counter campuran, retry idempoten, HP aktif ganda lintas barber dan panggil FIFO. Schema sementara dibersihkan.
- Sepuluh tes client Node lulus: empat tes akses/fragment tiket dan enam tes coalescing, editing, hidden/offline, reconnect, backoff/disposal dan auth CSRF.
- Smoke transport nyata lulus dengan Reverb + queue worker + SQLite terisolasi: channel publik/admin/barber pemilik, rollback tanpa job, commit satu job, payload minimal, origin asing dan client event palsu ditolak; proses/file fixture dibersihkan oleh runner.
- Pint, PHPStan/Larastan (nol error), TypeScript dan lint frontend lulus. Build produksi serta SSR lulus; warning fontaine opsional existing tidak menghalangi build.
- Composer optimized autoload lulus dengan **8.907 class**; pemindaian awal Windows lambat tetapi selesai. Audit Composer dan npm melaporkan **nol kerentanan** pada dependency yang diperiksa.
- QA admin nyata: dashboard berubah 1→2 lewat WebSocket, Reverb dihentikan lalu fallback membaca 2→3, reconnect kembali otomatis setelah retry Pusher. Pilihan availability Istirahat yang belum disimpan tetap terjaga saat tiket keempat dibuat; setelah dibersihkan, hitungan membaca 4.
- QA barber memakai sesi HTTP terpisah (localhost untuk barber, 127.0.0.1 untuk admin): subscription privat aktif dan hitungan berubah 4→5. Panggil/mulai/selesai memakai konfirmasi; availability terkunci saat dilayani dan jumlah selesai menjadi 1.
- QA ringkasan publik: landing berubah 4→5 menunggu setelah tiket baru dibuat tanpa reload; nama pelanggan QA tidak muncul pada body halaman publik.
- Startup aplikasi utama menjalankan lima proses nyata melalui composer run dev: HTTP port 8000, Vite 5173, Reverb 8080, worker realtime,default dan scheduler, memakai PHP 8.5. Halaman utama memuat kode baru serta closed state sesuai usaha yang belum dikonfigurasi.
- Vite development host dan HMR memakai loopback 127.0.0.1 agar browser tidak menerima alamat hot IPv6 ::1 yang tidak cocok dengan koneksi lokal.
- Tidak ada error JavaScript. Halaman diuji pada 320/375/768/1024/1440 px tanpa overflow. Bukti: docs/previews/phase5-admin-live.jpg dan phase5-barber-live-mobile.jpg, memakai data QA terisolasi tanpa token/password.
- Pemeriksaan gabungan npm run check masih menandai format dua spesifikasi existing PRD.md dan DESIGN_GUIDELINES.md; spesifikasi tersebut dipertahankan.
- Pemeriksaan akhir lokal lulus: halaman utama dimuat ulang setelah Vite memakai IPv4 tanpa error baru; beranda/login/form antrean merespons 200 dan admin tanpa sesi dialihkan ke login. PostgreSQL aplikasi tetap memiliki 10 migrasi, admin aktif, 0 tiket dan 0 schema tes sementara; driver broadcast Reverb aktif dengan origin loopback. Tab, server, worker dan database/kredensial/helper QA sudah dibersihkan. Lima proses development utama tetap aktif; .env dan PHP global tidak diubah.

## Catatan historis validasi — Fase 4

- Runtime: **PHP 8.5.6 Laragon**, termasuk child PHP Vite/Wayfinder melalui scripts/use-php85.ps1; PHP global tidak diubah.
- PostgreSQL asli final: **207 lulus, 3 dilewati, 1.278 assertion** dari 210 tes, termasuk regresi redirect Inertia. Tiga skip terkait fitur Fortify opsional yang tidak diaktifkan.
- SQLite di memori final: **207 lulus, 3 dilewati, 1.278 assertion** dari 210 tes; sama dengan PostgreSQL.
- Empat tes client Node untuk pemulihan/fragment, CSRF/Authorization dan error/rate limit: lulus.
- Empat skenario concurrency PostgreSQL **masing-masing 20 proses paralel**: counter campuran, retry satu tiket, HP ganda lintas barber, dan panggilan FIFO tunggal. Semua lulus.
- Pengujian memakai schema PostgreSQL sementara; cleanup selesai. Tidak ada reset schema aplikasi.
- Database aplikasi terverifikasi: **10 migrasi Ran**, tabel audit tersedia, admin aktif, tiket 0 dan schema tes sementara 0. Tidak ada fixture tes di schema aplikasi. Tautan public/storage mengarah ke storage/app/public.
- Pint lulus; PHPStan/Larastan nol error; TypeScript, lint frontend dan build produksi final lulus. Format ketiga dokumen handoff lulus. Warning fontaine opsional existing tidak menghalangi build.
- Pemeriksaan gabungan npm run check final berhenti hanya pada format dua dokumen spesifikasi existing, PRD.md dan DESIGN_GUIDELINES.md; dokumen tersebut tidak diubah.
- Scheduler expiry existing tetap diperlukan sebagai proses aktif saat operasional.
- Suite menggunakan PHPUnit bawaan proyek; dependency pengujian tidak diganti pada fase ini.
- QA browser admin: sepuluh halaman tanpa overflow pada 320 px; layanan/dialog juga lolos pada 375/768/1024/1440 px, scroll dialog dan Escape bekerja, drawer mobile menutup setelah navigasi. Pengaturan berhasil disimpan termasuk kontak kosong, toggle benar-benar menutup pendaftaran publik, QR SVG dimuat/cetak A4 bersih, dan laporan 3 tiket/1 selesai sesuai data QA. Harga baru Rp60.000 tidak mengubah snapshot tiket lama Rp50.000.
- QA browser barber: panggil QA-003 → mulai → selesai dengan konfirmasi, kontrol availability terkunci saat diproses lalu berhasil diubah menjadi Istirahat. Riwayat menampilkan 2 selesai/1 terlewat dan detail audit sesuai status. Dashboard lolos 375/768/1024/1440 px; tiga halaman barber lolos 320 px. Tidak ada error JavaScript. Seluruh data QA terisolasi dari database usaha.
- Bukti tampilan: docs/previews/phase4-admin-desktop.jpg dan phase4-barber-mobile.jpg; tanpa token/password.
- Smoke HTTP aplikasi utama: beranda, login dan form antrean 200; admin tanpa sesi dialihkan ke login. Scheduler expiry terdaftar setiap menit. Tab/server QA ditutup, viewport dipulihkan dan file database/metadata/router sementara dibersihkan.
- Hasil QA pelanggan Fase 3 tetap didokumentasikan sebagai hasil historis di PHASE_3_CUSTOMER_UI.md.

## Prasyarat operasional dan berikutnya

Seluruh Fase PRD 0–5 selesai pada lingkup implementasi; **tersisa 0 fase implementasi**. Deployment server publik belum dilakukan dan perlu lingkungan tujuan serta keputusan operasional pemilik usaha.

Data usaha nyata belum diisi otomatis: admin perlu mengisi pengaturan, jam buka/libur, barber + akun, jadwal, layanan dan QR aktif, kemudian memilih barber Tersedia dan membuka queue_registration_open sebelum menerima pelanggan. Pengelolaan kini tersedia melalui UI Fase 4; pendaftaran default tertutup.

Worker realtime, Reverb dan scheduler harus aktif saat operasional; composer run dev lokal mengelola ketiganya bersama HTTP/Vite. Produksi memerlukan domain/HTTPS/WSS, allowlist origin, process manager, storage, backup/restore, monitoring serta kebijakan retensi. QR statis belum membuktikan lokasi fisik dan HP belum memakai OTP, sesuai batas MVP.

Lihat [check-in scan QR](QR_CHECK_IN.md), [realtime dan stabilisasi](PHASE_5_REALTIME.md), [deployment](DEPLOYMENT.md), [dashboard](PHASE_4_OPERATIONS.md), [UI pelanggan](PHASE_3_CUSTOMER_UI.md), [engine antrean](PHASE_2_QUEUE_ENGINE.md), [pengujian PostgreSQL](POSTGRESQL_TESTING.md), [pondasi](PHASE_1_FOUNDATION.md), dan [landing page](LANDING_PAGE_IMPLEMENTATION.md).
