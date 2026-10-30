# Panduan Hosting BARBERFLOW di Vercel

Dokumen ini menjelaskan langkah-langkah dan arsitektur untuk menjalankan aplikasi **BARBERFLOW** (Laravel 13 + React/TypeScript + Inertia + Tailwind CSS + PostgreSQL) di platform serverless **Vercel**.

---

## 1. Arsitektur BARBERFLOW di Vercel

Vercel menggunakan arsitektur serverless (AWS Lambda). Karena Vercel secara default tidak menyediakan runtime PHP bawaan, aplikasi ini dikonfigurasikan menggunakan runtime komunitas resmi **`vercel-php@0.9.0`** (PHP 8.5).

### Penyesuaian Penting yang Telah Diterapkan:
1. **Entry Point Serverless (`api/index.php`)**:
   - Menormalkan `$_SERVER['SCRIPT_NAME'] = '/index.php'` agar routing Laravel tidak terpotong oleh prefix `/api`.
   - Secara otomatis membuat folder direktori yang dapat ditulis di `/tmp/storage` (karena sistem file Lambda bersifat *read-only*).
   - Mengarahkan `LARAVEL_STORAGE_PATH` dan `VIEW_COMPILED_PATH` ke `/tmp`.

2. **Konfigurasi Routing Vercel (`vercel.json`)**:
   - Asset frontend Vite (`/build/*`) disajikan langsung melalui CDN Vercel.
   - Asset publik (`/images/*`, `/favicon.ico`, dll.) disajikan langsung dari folder `public/`.
   - Gambar katalog usaha yang sudah ada di repo disajikan dari `storage/app/public/`.
   - Seluruh request dinamis lainnya diteruskan ke `api/index.php`.

3. **Trusted Proxies (`bootstrap/app.php`)**:
   - Diaktifkan `$middleware->trustProxies(at: '*')` agar deteksi client IP (untuk rate limiter tiket) dan HTTPS dari edge network Vercel terdeteksi dengan tepat.

4. **Background Worker & Realtime**:
   - **Queue**: Diatur ke `QUEUE_CONNECTION=sync` agar job dieksekusi langsung tanpa memerlukan daemon queue worker yang berjalan terus-menerus.
   - **Realtime**: Pada mode serverless tanpa daemon Reverb mandiri, `BROADCAST_CONNECTION=null` secara aman mengaktifkan mode *polling fallback* bawaan frontend (pemeriksaan berkala ~12 detik untuk pelanggan). Jika ingin WebSocket aktif, dapat menggunakan layanan cloud seperti **Pusher**.
   - **Cron (Pembersihan Antrean Kedaluwarsa)**: Tersedia endpoint `GET /api/cron/expire-queues`. Pada paket Vercel Hobby (Gratis), cron bawaan dibatasi 1x per hari. Untuk menjalankan setiap menit secara 100% gratis, Anda dapat menggunakan layanan gratis pihak ketiga seperti [cron-job.org](https://cron-job.org) untuk memanggil URL tersebut.

---

## 2. Persiapan Sebelum Deploy

### A. Siapkan Database PostgreSQL Eksternal
Vercel adalah serverless stateless, sehingga database tidak dapat berada di dalam container Vercel. Gunakan penyedia PostgreSQL cloud yang mendukung SSL, contohnya:
- **Neon.tech** (Sangat disarankan untuk serverless / gratis)
- **Supabase**
- **Railway**
- **AWS RDS / Tembo**

Catat detail koneksi: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, atau `DB_URL`.

### B. Jalankan Migrasi & Buat Akun Admin
Sebelum atau sesudah deploy, jalankan migrasi database ke PostgreSQL cloud dari terminal lokal Anda (menggunakan kredensial database cloud):

```bash
# Contoh menjalankan migrasi ke database cloud
php artisan migrate --force

# Buat akun admin pertama
php artisan barberflow:create-admin
```

---

## 3. Langkah Deploy ke Vercel

### Langkah 1: Push Kode ke Git (GitHub / GitLab / Bitbucket)
Pastikan branch `main` Anda sudah ter-update dengan konfigurasi Vercel:
- `api/index.php`
- `vercel.json`
- `.vercelignore`
- `bootstrap/app.php` & `routes/web.php`

### Langkah 2: Buat Project Baru di Vercel Dashboard
1. Buka [vercel.com](https://vercel.com) dan login.
2. Klik **Add New...** > **Project**.
3. Import repositori git `barber-shop`.
4. Pada bagian **Framework Preset**, biarkan **Other** (atau kosongkan / biarkan terdeteksi otomatis dari `vercel.json`).
5. **Root Directory**: `.` (root project).
6. **Build Command**: Biarkan default dari `vercel.json` (`npm run build`).
7. **Output Directory**: Kosongkan (jangan diisi `public`, karena routing dikendalikan oleh `vercel.json`).

### Langkah 3: Masukkan Environment Variables
Pada bagian **Environment Variables** di Vercel, tambahkan variabel berikut (lihat juga file referensi `.env.vercel.example`):

| Nama Variabel | Nilai Contoh | Keterangan |
| :--- | :--- | :--- |
| `APP_NAME` | `BARBERFLOW` | Nama aplikasi |
| `APP_ENV` | `production` | Lingkungan aplikasi |
| `APP_DEBUG` | `false` | Matikan debug di production |
| `APP_URL` | `https://nama-proyek.vercel.app` | Domain Vercel Anda |
| `APP_KEY` | *(hasil `php artisan key:generate --show`)* | Kunci enkripsi wajib 32 karakter |
| `BARBERFLOW_TIMEZONE` | `Asia/Makassar` | Zona waktu operasional usaha |
| `DB_CONNECTION` | `pgsql` | Driver database |
| `DB_HOST` | `ep-xyz.neon.tech` | Host PostgreSQL cloud |
| `DB_PORT` | `5432` | Port database |
| `DB_DATABASE` | `barberflow` | Nama database |
| `DB_USERNAME` | `neondb_owner` | Username database |
| `DB_PASSWORD` | `...` | Password database |
| `DB_SSLMODE` | `require` | SSL mode untuk cloud database |
| `SESSION_DRIVER` | `database` | Sesi disimpan di tabel database |
| `CACHE_STORE` | `database` | Cache disimpan di tabel database |
| `QUEUE_CONNECTION` | `sync` | Menjalankan job secara synchronous |
| `LOG_CHANNEL` | `stderr` | Log langsung tampil di tab Vercel Logs |
| `BROADCAST_CONNECTION`| `null` | Polling fallback aktif otomatis |
| `CRON_SECRET` | *(string acak)* | Token rahasia endpoint cron |

*(Tips: Jika menggunakan Neon atau Supabase dengan connection string tunggal, Anda dapat mengisi `DB_URL=postgres://...`)*.

### Langkah 4: Klik Deploy!
Vercel akan:
1. Mengunduh kode sumber.
2. Menginstal package Node dan menjalankan `npm run build` untuk mem-bundle asset Vite/React.
3. Mempersiapkan runtime serverless PHP via `vercel-php`.
4. Mempublikasikan aplikasi Anda dengan sertifikat SSL gratis (HTTPS).

---

## 4. Checklist Pengujian Pasca Deploy

Setelah deployment selesai:
1. **Halaman Publik**:
   - Buka `https://nama-proyek.vercel.app`.
   - Cek halaman `/layanan`, `/barber`, `/galeri`, dan `/kontak`. Pastikan styling Tailwind, font, dan gambar tampil sempurna.
2. **Pemesanan Antrean**:
   - Coba ambil nomor antrean di `/antrean`.
   - Pastikan tiket berhasil dibuat dan halaman pemantauan tiket (`/antrean/saya/{id}`) berjalan dengan polling aktif.
3. **Scan QR Check-in**:
   - Buka kamera di tiket untuk uji scan QR lokasi. Vercel secara default menyediakan HTTPS yang merupakan syarat browser untuk mengakses kamera.
4. **Login Staff & Admin**:
   - Akses `/login` menggunakan akun admin yang dibuat sebelumnya.
   - Buka `/admin/dashboard` dan `/admin/pengaturan` untuk mengisi data usaha dan membuka pendaftaran antrean.

