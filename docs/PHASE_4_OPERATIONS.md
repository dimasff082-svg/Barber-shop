# Fase 4 — Dashboard barber dan admin

Pembaruan: 29 Oktober 2026. **Fase 4 — Dashboard barber dan admin selesai**, termasuk validasi backend, frontend dan QA browser. Implementasi tetap berada dalam aplikasi Laravel existing, menggunakan React/TypeScript di resources/js, Inertia, Tailwind dan shadcn/ui. **Tersisa satu fase: Fase 5 — Realtime dan stabilisasi**, belum dimulai. Reverb/Echo belum diimplementasikan pada fase ini.

## Halaman dan hak akses

| Halaman           | Peran  | Perilaku                                                                                                       |
| ----------------- | ------ | -------------------------------------------------------------------------------------------------------------- |
| /admin/dashboard  | Admin  | KPI tanggal bisnis, tiket sedang diproses, status barber dan kontrol ketersediaan.                             |
| /admin/antrean    | Admin  | Daftar seluruh antrean dengan filter tanggal, barber, sumber dan status; pagination dan detail audit tiket.    |
| /admin/barbers    | Admin  | Buat/edit barber beserta akun login; foto, prefix, kapasitas, status aktif dan penerimaan antrean; arsip.      |
| /admin/layanan    | Admin  | Buat/edit nama, deskripsi, harga, durasi dan foto; arsip layanan.                                              |
| /admin/galeri     | Admin  | Foto, judul, alt text, urutan dan status publikasi; arsip.                                                     |
| /admin/jadwal     | Admin  | Jam buka tujuh hari, jadwal barber dan tanggal libur khusus.                                                   |
| /admin/qr         | Admin  | QR lokasi acak, label dan aktivasi; tampilan cetak A4 dan unduh SVG.                                           |
| /admin/pengaturan | Admin  | Profil usaha, kontak/peta, buka/tutup pendaftaran, kapasitas dan durasi toleransi.                             |
| /admin/laporan    | Admin  | Rentang tanggal maksimal 366 hari, ringkasan harian/sumber/hasil serta layanan selesai per barber dan layanan. |
| /admin/audit      | Admin  | Audit administrasi dengan filter tindakan/jenis objek dan pagination.                                          |
| /barber/dashboard | Barber | Ringkasan hari ini, antrean berikutnya, layanan berjalan dan status kerja.                                     |
| /barber/antrean   | Barber | Hanya antrean miliknya, filter tanggal/sumber/status, detail dan aksi yang sah.                                |
| /barber/riwayat   | Barber | Tiket terminal miliknya: selesai, batal dan terlewat.                                                          |
| /barber/profil    | Barber | Informasi akun/profil/jadwal sendiri. Pengelolaan profil barber tetap oleh admin.                              |

Login/logout tetap memakai starter kit/Fortify. Tidak ada pendaftaran staff publik. Route web dilindungi session, staff aktif, role dan pemeriksaan kepemilikan di backend. Menu mengikuti role, tetapi menyembunyikan menu bukan satu-satunya perlindungan. Akun barber tidak dapat membuka pengaturan admin atau memproses tiket barber lain.

## Alur operasional dan integritas

Tombol **Panggil berikutnya**, **Mulai layanan** dan **Selesaikan** menggunakan QueueWorkflow existing; nomor, FIFO, status resmi, locking serta satu tiket diproses per barber tetap diputuskan backend. Form staff memakai Inertia, mempertahankan kontrak JSON endpoint antrean existing, menampilkan validasi/flash dan mencegah submit berulang selama proses.

**Tandai terlewat** hanya tersedia untuk tiket online waiting_checkin ketika toleransi benar-benar telah berakhir, atau tiket telah melewati tanggal bisnis. Backend memeriksa ulang syarat di dalam transaksi. Tindakan ini tidak memakai estimasi sebagai pemicu. Pembatalan administratif memerlukan alasan dan dicatat sebagai event tiket. Transisi terminal tidak bisa diulang. Mengubah status kerja tidak boleh memutus tiket yang masih dipanggil/dilayani.

Barber baru berstatus Tidak Bertugas. Akun dibuat dengan role barber yang ditentukan server, email unik dan password minimal 12 karakter dengan huruf besar/kecil, angka, simbol serta konfirmasi. Admin dapat mengganti password melalui editor; password tidak dikirim kembali ke halaman atau audit. Menonaktifkan barber turut menonaktifkan akun terkait dan ditolak saat masih ada tiket dipanggil/dilayani. Prefix nomor tidak boleh berubah setelah counter/tiket pernah diterbitkan.

Arsip barber, layanan dan galeri mempertahankan record; tidak melakukan hard delete data historis. Mengubah harga/durasi katalog tidak mengubah snapshot tiket lama. Penutupan pendaftaran tidak menghapus tiket yang sudah diterbitkan.

## Pengaturan, jadwal dan QR

Pengaturan hanya menerima whitelist profil usaha dan parameter antrean. Kapasitas 1–32.767 dan toleransi 1–120 menit divalidasi. WhatsApp dinormalisasi sebagai nomor Indonesia dan tautan peta harus HTTPS. Kontak opsional dapat dikosongkan; nilai kosong disimpan secara sah pada kolom JSON dan katalog publik mengartikannya sebagai informasi yang belum tersedia. Timezone berasal dari konfigurasi server dan tidak dapat diubah dari payload form.

Jam buka/jadwal memakai tujuh weekday yang berbeda, waktu mulai sebelum selesai dan rentang dalam hari yang sama; hari tutup menyimpan waktu null. Jadwal hanya disimpan untuk barber dengan akun dan profil aktif. Tanggal libur unik dengan penanganan konflik aman.

QR memakai kode acak 256 bit dan portal lokasi milik aplikasi. Target/kode tidak dapat diganti oleh form; admin hanya mengubah label/aktivasi. Barcode SVG dibuat dengan dependency BaconQrCode yang sudah tersedia. Halaman cetak putih A4 berdiri sendiri tanpa sidebar dan tetap memerlukan login admin. URL cetak/gambar/unduh dilindungi role dan header privasi. QR statis yang dibagikan tetap dapat dipakai; fitur ini belum membuktikan keberadaan fisik pelanggan.

## Audit dan privasi

Migrasi 2026_10_29_000700_create_staff_audits_table.php menambah staff_audits tanpa reset tabel aplikasi. Tabel menyimpan aktor, tindakan, objek, metadata yang diizinkan dan waktu. Model serta trigger PostgreSQL/SQLite menolak update/delete, sehingga log append-only. Perubahan katalog, pengaturan, jadwal, QR dan status kerja tercatat dalam transaksi yang sama. Perubahan status tiket tetap memakai queue_events.

StaffAudit menyaring key sensitif seperti password, token, secret, cookie, authorization, credential dan idempotency secara rekursif; metadata hanya tipe sederhana dan string dibatasi. Halaman staff mengirim whitelist props, nomor HP pelanggan dimasking, history Inertia dienkripsi, serta respons memakai no-store/private dan no-referrer, termasuk error staff yang relevan. Token guest dan hash akses tidak dimasukkan dalam props dashboard.

Konfigurasi bawaan Inertia 3 store_previous_url diaktifkan agar halaman asal request Inertia tersimpan untuk redirect kembali dan validasi ketika browser tidak mengirim Referer. Request partial tidak mengganti URL halaman asal. Perbaikan ini menjaga aksi staff tetap kembali ke halaman operasional tanpa melonggarkan no-referrer; perilakunya dicakup tes regresi.

Upload dibatasi gambar JPG/JPEG/PNG/WebP/AVIF maksimal 2 MB, dengan nama file yang dibuat storage pada disk public. Path mentah dari form ditolak. Upload baru yang gagal disimpan ke database dibersihkan; aset lama tidak dihapus otomatis untuk menjaga referensi historis. public/storage telah ditautkan menggunakan storage:link. Profil tanpa foto tetap menggunakan placeholder netral.

## Definisi angka laporan

KPI dashboard dan laporan dibaca dari tiket yang tersimpan, berdasarkan queue_date dalam timezone bisnis. Barber aktif berarti profil aktif dengan akun barber aktif. Daftar antrean dipaginasi 20 per halaman. Laporan rentang tanggal diproses bertahap dengan lazyById(500), lalu agregat harian diurutkan menurut tanggal, sehingga tidak memuat seluruh Eloquent collection sekaligus.

Laporan memuat jumlah tiket total, online/on-site, selesai, batal dan terlewat; jumlah selesai per barber dan layanan; serta rata-rata waktu tunggu aktual. Waktu tunggu dihitung **dari checked_in_at sampai started_at, hanya tiket completed dengan timestamp sah**. Jumlah sampel ditampilkan dan rata-rata kosong bila tidak ada sampel. Tidak ada asumsi pembayaran, omzet atau pendapatan.

## Desain dan komponen

Tema staff memakai token dark/emas yang sama dengan website publik, termasuk sidebar, drawer, menu akun dan form. Navigasi mengikuti role, judul/breadcrumb berbahasa Indonesia, dan menu mobile menutup setelah navigasi. Login tetap autentikasi starter dengan label Indonesia dan kontrol password yang dapat dioperasikan keyboard.

StaffPage, StatCard, Pagination, OperationStats, QueueCards, TicketActions, AvailabilityControl dan CatalogFields dipakai ulang. Status memakai label/ikon yang konsisten; tindakan berisiko memiliki dialog konfirmasi, input memiliki label/error, dan kontrol utama memiliki target sentuh minimal 44 px. Statistik berasal dari backend, termasuk empty state ketika usaha belum dikonfigurasi.

## File utama

| Area                   | File/folder                                                                                                                                          |
| ---------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| Routing                | routes/web.php, routes/admin-catalogs.php, routes/admin-business.php, routes/operations.php                                                          |
| Controller             | app/Http/Controllers/Admin, app/Http/Controllers/Staff, app/Http/Controllers/Barber, StaffDashboardController dan Queue/StaffQueueController         |
| Validasi               | app/Http/Requests/Admin dan app/Http/Requests/Staff                                                                                                  |
| Read model dan katalog | app/Services/Staff/OperationsReadModel.php, app/Services/Admin/StaffDirectory.php, CatalogImages.php                                                 |
| Audit/privasi          | app/Services/StaffAudit.php, app/Models/StaffAuditEntry.php, app/Http/Middleware/StaffPrivacy.php, bootstrap/app.php                                 |
| Antrean                | app/Services/Queue/QueueWorkflow.php dan BarberAvailabilityService.php                                                                               |
| Navigasi Inertia       | config/inertia.php; penyimpanan halaman asal untuk redirect kembali tanpa Referer                                                                    |
| UI staff               | resources/js/pages/admin, resources/js/pages/barber, resources/js/pages/staff, resources/js/components/staff dan components/admin/catalog-fields.tsx |
| Shell/tema             | resources/js/components/app-sidebar.tsx, app-sidebar-header.tsx, app-shell.tsx, nav-user.tsx, resources/css/app.css                                  |
| QR cetak               | resources/views/admin/qr-print.blade.php                                                                                                             |
| Migrasi                | database/migrations/2026_10_29_000700_create_staff_audits_table.php                                                                                  |
| Pengujian              | tests/Feature/AdminCatalogTest.php, AdminBusinessTest.php, StaffOperationsTest.php, StaffAuditTest.php; suite engine existing                        |

## Validasi

- PHP/Artisan/tooling menggunakan **PHP 8.5.6 Laragon** dan helper scripts/use-php85.ps1, termasuk PHP child Wayfinder. Konfigurasi PHP global tidak diubah.
- PostgreSQL final: **210 tes; 207 lulus, 3 dilewati, 1.278 assertion**, termasuk regresi redirect Inertia. Tiga skip berasal dari fitur Fortify opsional existing.
- SQLite di memori final: **210 tes; 207 lulus, 3 dilewati, 1.278 assertion**, sama dengan PostgreSQL.
- Empat skenario PostgreSQL masing-masing **20 proses paralel** lulus: counter online/on-site bersama, retry idempoten, HP aktif ganda lintas barber, dan panggil FIFO tunggal. Schema sementara telah dibersihkan.
- PHPStan/Larastan: nol error.
- Pint, empat tes client Node, TypeScript, lint frontend dan build produksi final: lulus. Format ketiga dokumen handoff lulus.
- QA browser yang sudah lulus: login admin, simpan pengaturan termasuk kontak kosong, toggle menutup pendaftaran publik, perubahan harga layanan, QR SVG dengan cetak A4 tanpa sidebar, serta laporan 3 tiket/1 selesai yang sesuai data QA.
- Sepuluh halaman admin lolos tanpa overflow pada 320 px. Halaman layanan dan dialog juga lolos pada 375/768/1024/1440 px; menu mobile menutup setelah navigasi.
- Scroll dialog dan Escape bekerja. Harga layanan baru Rp60.000 tidak mengubah snapshot tiket lama Rp50.000.
- Alur barber panggil QA-003 → mulai → selesai dengan konfirmasi berhasil. Availability terkunci selama pemrosesan, lalu berhasil diubah menjadi Istirahat. Riwayat memuat 2 selesai/1 terlewat dan detail audit sesuai status.
- Dashboard barber lolos 375/768/1024/1440 px dan tiga halaman barber lolos 320 px tanpa overflow; tidak ada error JavaScript. QA menggunakan database khusus terisolasi dari PostgreSQL usaha.
- Screenshot tersedia di docs/previews/phase4-admin-desktop.jpg dan phase4-barber-mobile.jpg, tanpa token/password.
- Database usaha terverifikasi: **10 migrasi Ran**, termasuk audit 000700; admin tetap aktif, tiket 0 dan schema tes sementara 0. Tidak ada reset, seed fixture usaha atau perubahan .env. Tautan public/storage mengarah ke storage/app/public.
- Smoke HTTP aplikasi utama: beranda, login dan form antrean 200; halaman admin tanpa sesi dialihkan ke login. Scheduler expiry terdaftar setiap menit. Tab/server QA ditutup, viewport dipulihkan dan file database/metadata/router QA sementara dibersihkan.
- npm run check final berhenti hanya pada format existing PRD.md dan DESIGN_GUIDELINES.md; kedua spesifikasi tidak diubah pada fase ini.

## Konfigurasi awal usaha

Data usaha nyata tidak diisi otomatis. Admin perlu menuntaskan konfigurasi berikut melalui dashboard sebelum menerima pelanggan:

1. Isi nama usaha, kontak/peta yang tersedia, kapasitas dan toleransi di Pengaturan usaha; biarkan pendaftaran tertutup selama persiapan.
2. Isi jam buka tujuh hari dan libur khusus di Jadwal & jam buka.
3. Buat akun/profil barber dengan email dan password privat; aktifkan profil dan penerimaan antreannya.
4. Isi jadwal masing-masing barber, kapasitas bila memakai batas khusus, serta layanan aktif dengan harga/durasi yang benar.
5. Buat/aktifkan QR lokasi dan cetak/unduh setelah URL aplikasi yang digunakan pelanggan sudah benar.
6. Pilih status kerja barber Tersedia saat mulai bertugas, kemudian buka pendaftaran dari Pengaturan usaha.

Jalankan scheduler di terminal PowerShell tambahan dari root proyek:

```powershell
. .\scripts\use-php85.ps1
php artisan schedule:work
```

Scheduler diperlukan untuk expiry/no-show/penutupan hari otomatis. Jangan menjalankan migrate:fresh pada database usaha. Migrasi baru menggunakan php artisan migrate; setup salinan proyek yang belum memiliki tautan upload menggunakan php artisan storage:link. Gunakan PHP 8.5 melalui helper pada setiap terminal Composer/Vite/PHP.

## Tahap berikutnya

Fase 4 selesai tanpa blocker implementasi. Tersisa **Fase 5 — Realtime dan stabilisasi**: Reverb/Echo private channels, broadcast setelah commit, reconnect/fallback, optimasi, pengujian end-to-end penting serta dokumentasi deployment/proses terkelola. Halaman staff fase ini menggunakan snapshot hasil request/navigasi; pembaruan realtime staff belum tersedia. Polling tiket pelanggan Fase 3 tetap berjalan sesuai mekanisme existing.

Lihat [progres](DEVELOPMENT_PROGRESS.md), [engine antrean](PHASE_2_QUEUE_ENGINE.md), [UI pelanggan](PHASE_3_CUSTOMER_UI.md) dan [isolasi tes PostgreSQL](POSTGRESQL_TESTING.md).
