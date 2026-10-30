# BARBERFLOW — Fase 1: Pondasi dan autentikasi

**Tahap aktif:** Fase 1 menurut `PRD.md`. Landing page publik telah dikerjakan sebelumnya. Kode pondasi, koneksi PostgreSQL, enam migrasi, dan suite pada PostgreSQL asli telah lulus pemeriksaan. Akun admin aplikasi masih menunggu pengguna menetapkan password melalui terminal interaktif.

## Lingkup tahap ini

Tahap ini mempertahankan aplikasi Laravel, React, Inertia, dan autentikasi Fortify dari starter kit. Implementasinya mencakup role `admin` dan `barber`, status aktif akun, pembatasan akses, fondasi model barber/layanan, perintah pembuatan admin, serta halaman dasar dashboard masing-masing role.

Engine antrean belum dibuat. Penomoran tiket, FIFO, check-in QR, token akses guest, idempotensi, status layanan, toleransi kehadiran, scheduler, dan realtime tetap menjadi pekerjaan Fase 2 dan fase setelahnya. Halaman dashboard belum menyediakan CRUD operasional atau angka statistik antrean.

## Akun, login, dan sesi

`UserRole` membatasi role menjadi `admin` atau `barber`. `User` menggunakan enum cast untuk role, boolean cast untuk `is_active`, dan relasi satu-ke-satu `barber()`.

Login tetap melalui Fortify dengan session, CSRF, dan rate limiter login existing. Callback autentikasi menerima kredensial yang valid hanya jika akun staff aktif. Password tetap diverifikasi melalui provider autentikasi Laravel, termasuk rehash bila konfigurasi mengaktifkannya. Respons login mengarahkan admin ke `admin.dashboard` dan barber ke `barber.dashboard`; tujuan `url.intended` lama dibersihkan agar tujuan dari role lain tidak dipakai setelah login.

Middleware global web `EnsureActiveStaff` memeriksa akun yang sudah memiliki sesi, termasuk saat mengakses halaman publik. Guest tetap dapat mengakses halaman publik. Akun nonaktif tidak dapat mempertahankan sesi: sistem logout, menginvalidasi sesi, dan meregenerasi token CSRF. Request JSON menerima 401, sedangkan request web diarahkan ke login dengan pesan sesi tidak valid.

`AuthenticateSession` dari Laravel juga dipasang pada kelompok web untuk memeriksa validitas sesi terhadap password akun. Middleware `EnsureRole`, melalui alias `role`, melindungi dashboard berdasarkan role di backend. Menyembunyikan menu di React tidak menggantikan pemeriksaan ini.

Registrasi publik tidak diaktifkan: daftar fitur Fortify saat ini hanya memuat reset password. Tidak ada penambahan tombol daftar akun publik atau alur autentikasi baru.

## Policy dan perubahan akun

`UserPolicy::update` membatasi perubahan profil pada akun aktif miliknya sendiri. Form profil hanya memvalidasi nama dan email; model `User` hanya mengizinkan mass assignment untuk nama, email, dan password. Payload profil tidak dapat mengubah role atau `is_active`. Email profil dinormalisasi menjadi huruf kecil dan dipangkas spasinya sebelum validasi.

`UserPolicy::delete` menolak penghapusan akun staff sendiri. Tombol penghapusan tidak ditampilkan pada halaman profil, dan request langsung ke endpoint hapus tetap ditolak oleh otorisasi `ProfileDeleteRequest`. Akun staff dipertahankan untuk relasi dan riwayat, dengan penonaktifan administratif sebagai pendekatan lifecycle akun.

`BarberPolicy` mengizinkan admin aktif melihat semua profil, membuat, serta mengubah konfigurasi barber. Barber aktif hanya boleh melihat profil miliknya sendiri. `ServicePolicy` mengizinkan staff aktif melihat layanan, sedangkan pembuatan dan perubahan konfigurasi layanan dibatasi pada admin aktif. Endpoint CRUD belum tersedia pada tahap ini.

Relasi `barbers.user_id` saat ini dijaga foreign key dan unique index. Saat CRUD barber dibuat, service/FormRequest masih perlu memvalidasi bahwa akun target mempunyai role barber, termasuk menjaga perubahan role untuk akun yang sudah mempunyai profil. Validasi lintas tabel tersebut belum dianggap tersedia hanya karena policy aktor sudah ada.

## Matriks route dan akses

| Route                                                                          | Guest                      | Admin aktif                        | Barber aktif                       | Perilaku                                                                                     |
| ------------------------------------------------------------------------------ | -------------------------- | ---------------------------------- | ---------------------------------- | -------------------------------------------------------------------------------------------- |
| `GET /`                                                                        | Diizinkan                  | Diizinkan                          | Diizinkan                          | Landing publik existing.                                                                     |
| `GET /login`, `POST /login`                                                    | Alur login Fortify         | Mengikuti middleware guest Fortify | Mengikuti middleware guest Fortify | Login hanya menerima akun staff aktif.                                                       |
| `POST /logout`                                                                 | Tidak mempunyai sesi staff | Diizinkan                          | Diizinkan                          | Logout Fortify existing.                                                                     |
| `GET /dashboard`                                                               | Redirect login             | Redirect `/admin/dashboard`        | Redirect `/barber/dashboard`       | Route kompatibilitas bernama `dashboard`.                                                    |
| `GET /admin/dashboard`                                                         | Redirect login             | Diizinkan                          | 403                                | Route `admin.dashboard`, middleware `role:admin`.                                            |
| `GET /barber/dashboard`                                                        | Redirect login             | 403                                | Diizinkan                          | Route `barber.dashboard`, middleware `role:barber`.                                          |
| `GET /settings`                                                                | Redirect login             | Redirect profil sendiri            | Redirect profil sendiri            | Menu pengaturan existing.                                                                    |
| `GET /settings/profile`, `PATCH /settings/profile`                             | Redirect login             | Profil sendiri                     | Profil sendiri                     | Policy dan validasi profil berlaku pada update.                                              |
| `DELETE /settings/profile`                                                     | Redirect login             | Ditolak policy                     | Ditolak policy                     | Self-delete staff dinonaktifkan di backend.                                                  |
| `GET /settings/security`, `PUT /settings/password`, `GET /settings/appearance` | Redirect login             | Pengaturan sendiri                 | Pengaturan sendiri                 | Middleware `verified` starter kit tetap ada; perubahan password juga memakai throttle `6,1`. |

User saat ini tidak mengimplementasikan kontrak `MustVerifyEmail`; fitur verifikasi email dan 2FA tidak diaktifkan pada konfigurasi Fortify yang tersedia. Kehadiran middleware `verified` pada kelompok route settings tidak berarti tahap ini menambahkan kewajiban verifikasi email baru.

Semua sesi staff nonaktif lebih dahulu ditangani oleh `EnsureActiveStaff`, sehingga tidak memperoleh akses hanya dengan mengetahui URL dashboard atau settings.

## Migrasi dan integritas database

Tiga migrasi baru menambah pondasi tanpa mengganti tabel autentikasi starter kit:

1. `2026_10_28_000100_add_staff_access_to_users_table.php` menambah `role` dan `is_active`. Default database adalah `barber` dan `false`. Akun legacy yang belum mempunyai penetapan role tidak otomatis dipromosikan menjadi admin atau diaktifkan.
2. `2026_10_28_000200_create_barbers_table.php` membuat profil barber. Satu user hanya dapat mempunyai satu profil; slug dan prefix antrean unik. Penghapusan user yang masih direferensikan profil ditolak oleh foreign key `restrictOnDelete`. Default profil adalah nonaktif dengan availability `off_duty`.
3. `2026_10_28_000300_create_services_table.php` membuat layanan dengan harga `decimal(12,2)`, durasi perkiraan, dan default nonaktif.

`BarberAvailability` memuat `available`, `busy`, `break`, dan `off_duty`. Enum schema database juga membatasi role akun dan availability. Prefix antrean harus terdiri dari satu sampai tiga huruf besar A–Z.

Harga layanan berada pada rentang `0` sampai `9999999999.99`; durasi berada pada rentang `1` sampai `32767` menit. Nilai harga dicast sebagai string desimal dua tempat agar tidak diolah sebagai float uang oleh model. Durasi dicast sebagai integer.

PostgreSQL menggunakan `CHECK` untuk format prefix serta rentang harga/durasi. SQLite menggunakan trigger `BEFORE INSERT` dan `BEFORE UPDATE` untuk penjagaan nilai yang sama, karena penambahan `CHECK` setelah pembuatan tabel tidak didukung melalui alur SQLite tersebut. Unique index, foreign key, dan pembatasan enum tetap menggunakan schema Laravel. Dukungan SQLite ini dipakai untuk pengujian di memori dan belum menjadi bukti migrasi pada PostgreSQL nyata.

Factory barber dan layanan tersedia untuk tes; akun factory tidak dibuat oleh proses seeding aplikasi. `DatabaseSeeder` tidak lagi membuat akun demo dengan password yang diketahui.

## Pembuatan admin awal

Perintah `barberflow:create-admin` membuat akun admin baru secara eksplisit. Nama dan email dapat diberikan sebagai opsi atau dimasukkan lewat prompt. Password dan konfirmasinya selalu dimasukkan melalui input tersembunyi; tidak ada opsi password pada command line, password default, atau penulisan kredensial ke `.env`.

Perintah memerlukan terminal interaktif yang mendukung input tersembunyi. Email dinormalisasi dan diperiksa duplikasinya tanpa membedakan huruf besar/kecil sebelum prompt password serta di dalam transaksi. Akun yang sudah memakai email tersebut tidak ditimpa atau dipromosikan. Password memerlukan sedikitnya 12 karakter dengan huruf besar, huruf kecil, angka, simbol, dan konfirmasi yang sama; penyimpanan tetap memakai hashed cast Laravel.

Belum ada akun admin aplikasi nyata yang dibuat. Database dan migrasi sudah siap; jalankan perintah berikut di terminal pengguna, lalu masukkan password baru beserta konfirmasinya melalui prompt tersembunyi:

```powershell
. .\scripts\use-php85.ps1
php artisan barberflow:create-admin
```

## PHP 8.5 Laragon dan PostgreSQL

Runtime yang dipilih untuk seluruh pekerjaan PHP adalah:

```text
C:\laragon\bin\php\php-8.5.6-Win32-vs17-x64\php.exe
```

`scripts/use-php85.ps1` memeriksa keberadaan executable dan menambahkan direktori tersebut di awal PATH terminal saat ini. Bila `pdo_pgsql` belum aktif, helper menambahkan direktori `scripts/php/` ke `PHP_INI_SCAN_DIR` pada proses terminal. `scripts/php/pgsql.ini` memuat driver tersebut sehingga child PHP dari Artisan, Composer, dan server juga mewarisinya. Script tidak mengganti PATH global Windows atau menulis `php.ini` Laragon.

```powershell
. .\scripts\use-php85.ps1
php --version
```

Executable juga dapat dipanggil langsung sehingga tidak bergantung pada urutan PATH:

```powershell
& 'C:\laragon\bin\php\php-8.5.6-Win32-vs17-x64\php.exe' artisan test
```

DLL `ext/php_pdo_pgsql.dll` tersedia pada instalasi Laragon tersebut. Jika ekstensi belum diaktifkan melalui konfigurasi CLI, opsi `-d extension=pdo_pgsql` memuatnya hanya untuk proses yang dijalankan:

```powershell
& 'C:\laragon\bin\php\php-8.5.6-Win32-vs17-x64\php.exe' -d extension=pdo_pgsql --ri pdo_pgsql
```

Kunci database `.env` telah diubah sesuai detail koneksi yang diberikan pengguna: PostgreSQL lokal, database `barber_shop`, schema `public`. `DB_URL` dikosongkan agar tidak mengganti target melalui URL koneksi lain. Konfigurasi sebelumnya disimpan dalam `.env.backup` yang diabaikan Git. Kunci aplikasi lainnya dipertahankan. Password tidak disalin ke source, template, atau dokumentasi. `.env.example` tetap memakai parameter contoh.

Koneksi PostgreSQL 18.6 telah divalidasi. Schema `public` kosong sebelum aktivasi; keenam migrasi diterapkan dengan `artisan migrate --no-interaction` dan semuanya berstatus Ran. Tidak ada `migrate:fresh` pada schema aplikasi. File SQLite lama tetap ada dan datanya tidak diimpor otomatis. Constraint PostgreSQL prefix, harga, dan durasi telah diverifikasi terpasang.

Suite PostgreSQL dijalankan melalui `scripts/test-postgresql.php` pada schema baru yang terisolasi, dengan pemeriksaan identitas database dan `search_path` sebelum PHPUnit berjalan. Schema tes dibersihkan setelah suite selesai; pemeriksaan akhir menunjukkan tidak ada schema tes tersisa. Lihat [POSTGRESQL_TESTING.md](POSTGRESQL_TESTING.md) untuk batas isolasi dan perintahnya.

Smoke test HTTP kernel Laravel menggunakan konfigurasi aplikasi PostgreSQL juga berhasil: beranda `/` dan `/login` mengembalikan status 200. Pemeriksaan ini memvalidasi respons backend, bukan QA visual browser.

## Dashboard dasar

`admin/dashboard` dan `barber/dashboard` memakai komponen reusable `DashboardFoundation`, sidebar starter kit, dan identitas akun dari Inertia shared props. Sidebar menunjukkan dashboard sesuai role dan tautan ke halaman publik. Halaman menyediakan tautan pengaturan profil dan keterangan bahwa layanan antrean masih disiapkan.

Tidak ada KPI, status barber, nomor tiket, estimasi, atau data antrean palsu. Statistik operasional akan ditambahkan bersama sumber data backend pada tahap yang sesuai.

## Kelompok file implementasi

- **Model dan enum:** `app/Models/User.php`, `Barber.php`, `Service.php`, serta `app/Enums/UserRole.php` dan `BarberAvailability.php`.
- **Autentikasi dan akses:** `app/Providers/FortifyServiceProvider.php`, `app/Http/Responses/LoginResponse.php`, `app/Http/Middleware/EnsureActiveStaff.php`, `EnsureRole.php`, `bootstrap/app.php`, serta policy dalam `app/Policies/`.
- **Routing dan controller:** `routes/web.php`, `routes/settings.php`, `app/Http/Controllers/StaffDashboardController.php`, serta request profil dalam `app/Http/Requests/Settings/`.
- **Provisioning dan database:** `app/Console/Commands/CreateAdminCommand.php`, tiga migrasi bertanggal `2026_10_28`, factory model, `database/seeders/DatabaseSeeder.php`, serta `.env.example`.
- **Frontend staff:** `resources/js/pages/admin/dashboard.tsx`, `resources/js/pages/barber/dashboard.tsx`, `resources/js/components/staff/dashboard-foundation.tsx`, sidebar/branding staff, tipe auth, dan halaman profil settings.
- **Runtime dan konfigurasi:** `scripts/use-php85.ps1`, `scripts/php/pgsql.ini`, `scripts/test-postgresql.php`, `config/database.php`, `config/barberflow.php`, serta preferensi kerja dalam `AGENTS.MD`.
- **Pengujian:** `tests/Feature/Auth/StaffAccessTest.php`, `FoundationModelsTest.php`, `StaffPoliciesTest.php`, `CreateAdminCommandTest.php`, serta penyesuaian tes autentikasi/dashboard/settings existing.

## Validasi dan posisi tahap

| Pemeriksaan                                                               | Status dokumentasi                                                               |
| ------------------------------------------------------------------------- | -------------------------------------------------------------------------------- |
| Tes autentikasi, sesi, role, policy, perintah admin, dan integritas model | Lulus: 72 tes berhasil, 3 dilewati; 238 assertion.                               |
| Pint pada file implementasi dan Larastan                                  | Lulus; Larastan nol error.                                                       |
| TypeScript, lint frontend yang diubah, dan build                          | Lulus. Warning fontaine existing tidak memblokir build.                          |
| Koneksi PostgreSQL dan migrasi database aplikasi nyata                    | Lulus: PostgreSQL 18.6, enam migrasi Ran dan constraint terpasang.               |
| Suite pada PostgreSQL asli melalui runner terisolasi                      | Lulus: 72 tes berhasil, 3 dilewati; 238 assertion. Schema tes telah dibersihkan. |

Perintah pengujian biasa tetap memakai SQLite di memori sesuai `phpunit.xml`. Runner PostgreSQL memberikan override environment hanya untuk child proses tes sehingga kedua jalur pengujian tersedia.

Tiga tes dilewati karena fitur Fortify opsional tidak diaktifkan. Pemeriksaan format seluruh proyek sebelumnya masih terhalang `PRD.md` dan `DESIGN_GUIDELINES.md` existing. QA visual dashboard staff belum dijalankan. Pembuatan admin nyata masih menunggu password yang ditentukan pengguna melalui terminal interaktif.

Pembaruan 29 Oktober 2026: akun admin aktif terverifikasi dan **Fase 1 selesai**. Keterangan onboarding tertunda di atas merupakan catatan historis. Fase 2 juga telah diselesaikan; status dan validasi terbaru ada di [DEVELOPMENT_PROGRESS.md](DEVELOPMENT_PROGRESS.md) serta [PHASE_2_QUEUE_ENGINE.md](PHASE_2_QUEUE_ENGINE.md).
