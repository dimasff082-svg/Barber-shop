# PRODUCT REQUIREMENTS DOCUMENT (PRD)

## BARBERFLOW — Website Portofolio Barbershop & Sistem Antrean Hybrid

**Versi:** 2.0  
**Status:** Siap untuk implementasi bertahap  
**Bahasa aplikasi:** Bahasa Indonesia  
**Lokasi proyek saat ini:** `C:\kerjaan\aplikasi\barber-shop` (Windows)  
**Pendekatan:** Satu proyek Laravel (monolith) dengan Inertia.js + React, bukan Next.js terpisah  
**Prioritas:** Sistem antrean yang benar dan aman, lalu pengalaman pengguna dan visual premium

> **Instruksi utama untuk Codex:** Proyek Laravel React Starter Kit sudah dibuat pada folder `barber-shop`. INSPEKSI repository dan versi dependency yang benar-benar terpasang sebelum mengubah apa pun. Jangan menjalankan `laravel new`, membuat aplikasi Next.js, membuat folder `/frontend` baru, atau menimpa autentikasi starter kit. Patuhi aturan antrean dan kriteria penerimaan pada dokumen ini. Jika ada perbedaan struktur pada starter kit, ikuti struktur aktualnya.

---

## 1. Ringkasan dan tujuan produk

BARBERFLOW adalah website barbershop yang berfungsi sebagai portofolio/profil bisnis sekaligus aplikasi antrean digital. Pelanggan dapat mengambil antrean dari rumah (**online**) atau datang dan memindai QR di lokasi (**on-site**). Keduanya menggunakan **satu antrean terintegrasi per barber per tanggal bisnis**. Pelanggan dari rumah harus check-in ketika tiba; pelanggan yang membuat tiket melalui QR di lokasi dicatat telah check-in, sesuai batasan verifikasi kehadiran yang dijelaskan di bagian keamanan.

Tujuan:
1. Mengurangi waktu tunggu di tempat cukur.
2. Menunjukkan layanan, harga, galeri, barber, jadwal, lokasi, dan profil usaha.
3. Mencegah duplikasi atau benturan nomor antrean.
4. Membantu barber memanggil, memulai, dan menyelesaikan layanan.
5. Membantu admin mengelola usaha dan memantau laporan operasional.
6. Membuat proyek portofolio full-stack yang benar-benar berfungsi.

### 1.1 Lingkup MVP
- Satu cabang barbershop, beberapa barber.
- Satu tiket = satu pelanggan + satu barber + satu layanan + satu tanggal bisnis.
- Antrean FIFO **per barber**, dengan aturan kehadiran dan toleransi pelanggan yang belum check-in.
- Pengunjung/pelanggan tidak wajib login. Admin dan barber wajib login.
- Tiket hanya berlaku pada hari bisnis yang ditentukan.
- Tidak ada pemilihan slot reservasi berdasarkan jam pada MVP; sistem memberi **estimasi**, bukan janji slot.
- Tidak ada transaksi pembayaran, POS, multi-cabang, membership, atau notifikasi WhatsApp otomatis pada MVP.

### 1.2 Keberhasilan produk
- Pembuatan tiket online dan QR berjalan tanpa benturan nomor, termasuk akses bersamaan.
- Tiket online dapat check-in **tanpa membuat nomor baru**.
- Barber dapat memproses antrean dengan transisi status yang valid.
- Pelanggan dapat melihat status tiketnya dan estimasi yang diperbarui.
- Hak akses, audit trail, dan privasi terjaga.
- UI pelanggan nyaman di ponsel; dashboard jelas di desktop maupun ponsel.

---

## 2. Stack teknologi dan prinsip arsitektur

| Lapisan | Teknologi |
|---|---|
| Backend dan server-rendered navigation | **Laravel 13** (ikuti versi aktual di `composer.json`) |
| Frontend | **React + TypeScript**, di `resources/js` |
| Penghubung halaman | **Inertia.js** |
| Styling dan komponen | **Tailwind CSS + shadcn/ui + Lucide** |
| Asset tooling | **Vite / Vite+ sesuai starter kit terpasang**, jangan ganti tanpa alasan |
| Database produksi | **PostgreSQL** |
| ORM dan migration | **Eloquent + Laravel Migration** |
| Autentikasi staff | **Autentikasi starter kit/Fortify + session + CSRF** |
| Realtime staff | **Laravel Reverb + Laravel Echo** |
| Proses asinkron / scheduler | **Laravel Queue + Scheduler** |
| Pengujian | **Pest** bila sudah terpasang, jika tidak konfirmasi lalu pasang |

**Aturan arsitektur:**
- Satu aplikasi Laravel, satu repository, satu domain aplikasi pada deployment standar.
- Navigasi halaman dan form utama menggunakan **Laravel web routes + Inertia**; jangan membangun Next.js, frontend terpisah, atau REST API besar yang tidak dibutuhkan.
- Endpoint JSON kecil diperbolehkan untuk polling/detail status tiket yang aman dan data real-time bila diperlukan.
- Tidak perlu Laravel Sanctum hanya untuk halaman React yang berbagi session dengan Laravel.
- Laravel memutuskan nomor tiket, status, estimasi resmi, hak akses, dan validasi. React hanya menangani tampilan/interaksi.
- Gunakan Service classes, Form Requests, Policies/Gates, Eloquent Resources/DTO seperlunya. Hindari controller yang berisi seluruh aturan bisnis.
- Jangan mengganti login bawaan, role, atau dependensi proyek tanpa pemeriksaan kondisi existing.

### 2.1 Susunan folder target (adaptasikan dengan starter kit)

```text
barber-shop/
├── app/
│   ├── Http/Controllers/
│   │   ├── Public/
│   │   ├── Admin/
│   │   └── Barber/
│   ├── Http/Requests/
│   ├── Models/
│   ├── Policies/
│   ├── Services/Queue/
│   ├── Events/
│   └── Jobs/
├── bootstrap/
├── config/
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── resources/
│   ├── css/
│   └── js/
│       ├── components/
│       ├── layouts/
│       ├── pages/
│       │   ├── public/
│       │   ├── queue/
│       │   ├── admin/
│       │   └── barber/
│       ├── hooks/
│       └── types/
├── routes/
│   ├── web.php
│   ├── console.php
│   └── channels.php    # setelah broadcasting dipasang
├── tests/
├── docs/
│   └── DESIGN_GUIDELINES.md
├── design-references/   # opsional; screenshot referensi UI dari pemilik proyek
├── composer.json
├── package.json
├── .env.example
└── PRD.md
```

Untuk development, gunakan `composer run dev` **jika** script itu tersedia dan berjalan pada versi starter kit ini. `php artisan serve` tetap alternatif dengan proses asset dev terpisah. Gunakan PostgreSQL setelah koneksi `.env` divalidasi; jangan menganggap migration sebelumnya memakai PostgreSQL hanya karena berhasil.

---

## 3. Peran pengguna dan otorisasi

### 3.1 Pelanggan (guest; tanpa login)
- Melihat beranda, layanan, profil barber, galeri, kontak/lokasi.
- Melihat informasi umum antrean (jumlah menunggu dan status ketersediaan) tanpa data pribadi orang lain.
- Membuat tiket online atau tiket melalui QR di lokasi.
- Melihat tiket miliknya melalui akses rahasia/tiket digital.
- Check-in tiket online, memantau antrean, atau membatalkan tiket selama status mengizinkan.

### 3.2 Barber (login)
- Melihat antrean miliknya saja dan ringkasan kinerja hari itu.
- Mengubah status ketersediaan dalam aturan yang berlaku.
- Memanggil pelanggan, memulai dan menyelesaikan layanan.
- Melihat pelanggan check-in, yang terlewat, dan riwayat layanan.
- Tidak dapat mengubah harga, mengelola akun lain, atau memproses antrean barber lain.

### 3.3 Admin/Pemilik (login)
- Mengelola barber, akun staff, layanan, harga, jadwal, galeri, jam operasional, lokasi, dan QR.
- Melihat seluruh antrean, statistik, laporan, status barber dan alasan pembatalan.
- Membuka/menutup pendaftaran antrean dan menetapkan batas kapasitas.
- Melakukan koreksi atau pembatalan administratif dengan alasan yang tercatat.

**Kebijakan autentikasi:** Tidak ada pendaftaran akun publik untuk staff. Fitur tambahan seperti email verification, passkeys, dan 2FA tidak diwajibkan dalam MVP jika saat setup dipilih `None`; login tetap ada. Admin awal dibuat lewat seeder/perintah Artisan yang aman; jangan hardcode password produksi. Pertimbangkan 2FA admin saat produksi.

---

## 4. Struktur halaman dan navigasi

**Publik (Inertia pages):**
- `/` — landing page: hero CTA "Ambil Antrean", branding, layanan unggulan, barber, galeri, lokasi, jam buka, ringkasan antrean.
- `/layanan` — katalog aktif: nama, deskripsi, harga rupiah, durasi, foto, CTA.
- `/barber` — daftar barber, spesialisasi, foto, jadwal, status tersedia, antrean saat ini.
- `/galeri` — galeri karya/foto barbershop.
- `/kontak` — alamat, tautan peta, WhatsApp, jam operasional.
- `/antrean` — pilih asal antrean/penjelasan aturan, pilih layanan dan barber, nama/telepon, persetujuan.
- `/antrean/lokasi/{qrCode}` — portal QR lokasi: **Ambil Antrean Baru** atau **Check-in Antrean Saya**.
- `/antrean/saya/{ticketId}` — halaman tiket; seluruh detail privat tetap memerlukan autentikasi tiket, bukan cukup tahu `ticketId`.

**Staff:**
- `/login` — gunakan autentikasi starter kit.
- `/admin/dashboard`, `/admin/barbers`, `/admin/layanan`, `/admin/antrean`, `/admin/galeri`, `/admin/jadwal`, `/admin/qr`, `/admin/laporan`, `/admin/pengaturan`.
- `/barber/dashboard`, `/barber/antrean`, `/barber/riwayat`, `/barber/profil`.

Gunakan nama route Laravel (named routes), redirect setelah login sesuai role, dan middleware/policy agar path staff tidak dapat diakses role yang salah.

---

## 5. Alur antrean hybrid

### 5.1 Online — dari rumah
1. Pelanggan buka `/antrean`, memilih **satu layanan** dan **satu barber** yang menerima antrean.
2. Pelanggan mengisi nama dan nomor HP, membaca perkiraan tunggu dan aturan check-in, lalu konfirmasi.
3. Backend memeriksa kapasitas, jam, layanan, barber, pencegahan tiket ganda, dan idempotensi.
4. Backend membuat nomor berurutan untuk barber/tanggal bisnis, sumber `online`, status `waiting_checkin`.
5. Halaman menampilkan tiket privat: nomor, barber, layanan, sumber, estimasi, status, aturan dan tautan akses kembali.
6. Pelanggan datang ke lokasi, memindai QR, memilih check-in, lalu memverifikasi akses tiketnya.
7. Status menjadi `checked_in` **tanpa nomor baru**.

### 5.2 On-site — melalui QR
1. Pengunjung memindai QR lokasi dan memilih **Ambil Antrean Baru**.
2. Mengisi data seperti pelanggan online.
3. Tiket baru masuk **counter barber yang sama**, sumber `onsite`, status awal `checked_in`.
4. Tiket menampilkan nomor dan posisi antrean.

### 5.3 Check-in online melalui QR
1. Di halaman QR pilih **Check-in Antrean Saya**.
2. Gunakan tiket yang sudah dimiliki (akses rahasia + kode tiket/identitas tiket).
3. Validasi: tiket aktif, tanggal berlaku, sumber online, belum check-in, QR aktif, akses tiket sah.
4. Update `checked_in_at` dan status; tidak boleh membuat antrean kedua.
5. Tampilkan konfirmasi dan refresh status di panel barber.

**Penting:** QR **statis** hanya memudahkan akses ke halaman lokasi; QR statis yang difoto atau dibagikan **tidak membuktikan keberadaan di lokasi**. Untuk MVP, tampilkan batasan ini dalam dokumentasi dan lakukan rate limit; siapkan pengembangan QR dinamis/OTP lokasi untuk verifikasi kehadiran yang lebih kuat. Jangan mengklaim QR statis anti-penipuan.

---

## 6. Aturan bisnis antrean (wajib)

### 6.1 Penomoran, FIFO dan data historis
- Antrean terpisah **per barber + tanggal bisnis**, tetapi sumber online/on-site menggunakan **counter yang sama**.
- Format contoh `A-001` untuk barber berkode A, `B-001` untuk B; penomoran mulai dari 1 setiap tanggal bisnis.
- `queue_date` dihitung menurut timezone usaha; default `Asia/Makassar`.
- Setelah nomor diterbitkan, nomor tidak didaur ulang walaupun tiket batal.
- Gunakan transaksi PostgreSQL + row lock / atomic upsert untuk counter; unique index `(barber_id, queue_date, sequence_number)`.
- Semua perubahan status harus dicatat dalam `queue_events`.

### 6.2 Tiket aktif ganda
- Default MVP: satu nomor HP yang sudah dinormalisasi hanya dapat mempunyai satu tiket **aktif** untuk tanggal bisnis yang sama di cabang tersebut.
- Status aktif: `waiting_checkin`, `checked_in`, `called`, `in_service`.
- Status terminal: `completed`, `cancelled`, `missed`.
- Cegah race condition dengan unique partial index pada field/relasi yang memungkinkan (misalnya simpan `normalized_phone` di tiket) dan validasi dalam transaksi.
- Gunakan `idempotency_key` pada request pembuatan agar retry/double-click tidak menghasilkan dua tiket.
- **Risiko MVP:** tanpa verifikasi kepemilikan nomor HP, orang dapat memasukkan nomor orang lain. Rate limiting dan validasi format wajib; OTP adalah peningkatan untuk produksi, bukan asumsi perlindungan sudah sempurna.

### 6.3 Status dan transisi resmi

| Status | Arti |
|---|---|
| `waiting_checkin` | Sudah mendaftar online, belum melakukan check-in |
| `checked_in` | Siap menunggu dipanggil di lokasi |
| `called` | Nomor sedang dipanggil oleh barber |
| `in_service` | Sedang dilayani |
| `completed` | Layanan selesai |
| `missed` | Tidak hadir hingga melewati batas toleransi |
| `cancelled` | Dibatalkan dengan alasan/aktor bila relevan |

Transisi:
- Online: `waiting_checkin` → `checked_in` → `called` → `in_service` → `completed`.
- On-site: `checked_in` → `called` → `in_service` → `completed`.
- `waiting_checkin` → `missed` setelah aturan toleransi berlaku.
- `waiting_checkin` atau `checked_in` → `cancelled` oleh pemilik tiket; status lain hanya bisa dibatalkan melalui kebijakan staff/admin yang dicatat.
- Transisi ilegal harus ditolak oleh backend; record terminal tidak boleh diproses lagi.

### 6.4 Urutan panggil dan toleransi (aturan deterministik)
- Barber hanya dapat memanggil berikutnya jika **tidak ada tiket `called` atau `in_service`** pada barber tersebut.
- Pilih tiket `checked_in` dengan sequence terkecil yang belum diproses. Tiket `waiting_checkin` **tidak dapat dipanggil**.
- Saat barber siap dan terdapat tiket `waiting_checkin` bernomor lebih kecil yang belum hadir dibanding pelanggan eligible berikutnya, mulai waktu toleransi untuk tiket yang terlewati (`grace_started_at`, `grace_expires_at`) jika belum dimulai.
- Barber boleh langsung memanggil pelanggan `checked_in` berikutnya; tidak perlu diam menunggu pelanggan yang belum hadir.
- Jika pelanggan yang terlewati check-in saat masa toleransi masih berlaku, ia menjadi `checked_in` dan ikut dipertimbangkan menurut nomor pada **panggilan berikutnya**, tanpa menginterupsi pelanggan yang sudah `called`/`in_service`.
- Jika melewati akhir toleransi tanpa check-in, sistem mengubah tiket menjadi `missed`; default toleransi **5 menit**, dapat diubah admin.
- Bila tidak ada pelanggan `checked_in`, barber dapat menunggu. Penetapan toleransi harus terjadi ketika barber benar-benar siap untuk panggilan baru; **jangan** menjadikan estimasi waktu sebagai pemicu otomatis missed.
- Scheduler/job memproses batas toleransi secara idempoten; periksa ulang state dalam transaksi saat menandai missed.
- Operasi panggil/pindah status harus menggunakan locking untuk menghindari dua tiket dipanggil atau dilayani bersamaan.

### 6.5 Kapasitas, jam, dan ketersediaan
- Admin mengelola jam buka, hari libur, jadwal barber, status penerimaan antrean, dan batas antrean.
- Barber berstatus `available`, `busy`, `break`, `off_duty`; transisi yang berisiko memutus layanan aktif harus ditolak.
- Pendaftaran baru ditolak jika sedang tutup, barber tidak menerima antrean, layanan tidak aktif, atau kapasitas habis.
- Tiket yang sudah dibuat tidak otomatis hilang saat pendaftaran ditutup; admin perlu alur penanganan yang tercatat.

### 6.6 Estimasi
- Estimasi tunggu dihitung dari durasi perkiraan sisa layanan berjalan dan tiket aktif yang diperkirakan mendahului pelanggan, disesuaikan dengan check-in, pembatalan dan no-show.
- Perkiraan awal dapat menggunakan durasi layanan dari `services` dan snapshot tiket; jika data tidak cukup, tampilkan rentang/label "Estimasi dapat berubah" alih-alih waktu pasti.
- Hanya status real dari DB yang boleh menentukan urutan dan kelayakan panggilan.

---

## 7. UI/UX dan design system

**Tema:** Premium modern barbershop, dark-first, navigasi sederhana, terasa profesional. Desain **landing page lebih ekspresif**, halaman antrean **mobile-first dan informatif**, dashboard **fokus operasional**.

Palet awal:
- Background utama `#101010`
- Surface `#1C1C1C` / `#262626`
- Aksen emas `#D4AF37`
- Teks utama `#FFFFFF`
- Teks sekunder `#A3A3A3`
- Success `#22C55E`, warning `#F59E0B`, danger `#EF4444`

**Persyaratan UX:**
- Tombol CTA **Ambil Antrean** terlihat jelas di beranda dan halaman layanan.
- Form antrean satu alur singkat dengan validasi jelas, state loading, cegah double submit, dan ringkasan sebelum konfirmasi.
- Tiket digital menonjolkan nomor, status, jumlah antrean di depan, barber, layanan, dan estimasi.
- Status tidak dibedakan hanya melalui warna; gunakan label teks dan ikon.
- Dashboard barber menyediakan tombol aksi yang besar, dengan konfirmasi pada tindakan berisiko.
- Mobile responsive, fokus keyboard, label input, kontras teks, empty state, loading state, error state.
- Foto dan icon bisa placeholder demo saat implementasi; **jangan** menyalin logo, merek, atau foto berhak cipta dari referensi.
- Jika folder `design-references/` diisi screenshot oleh pemilik proyek, **ikuti arah visualnya** dan tulis spesifikasi rinci dalam `docs/DESIGN_GUIDELINES.md`. Jika belum tersedia, lanjut dengan design system di atas; jangan menghentikan backend.

---

## 8. Dashboard dan fitur admin/barber

**Dashboard barber:** antrean siap, antrean menunggu check-in, nomor dipanggil, layanan berlangsung, jumlah selesai, riwayat; tombol `Panggil`, `Mulai`, `Selesaikan`, `Tandai Terlewat` hanya jika syarat bisnis terpenuhi; status availability.

**Dashboard admin:** kartu ringkasan harian (daftar/selesai/batal/missed, sedang menunggu, barber aktif), CRUD barber + akun, CRUD layanan + durasi/harga, jadwal, galeri, profil usaha, pengaturan jam dan kapasitas, generate/unduh QR, daftar/filter/detail antrean, audit trail, ringkasan laporan.

**Laporan MVP:** jumlah tiket per hari dan sumber, jumlah layanan selesai per barber dan layanan, durasi tunggu aktual rata-rata bila data tersedia, pembatalan dan missed. Tidak boleh mengklaim omzet/pendapatan pembayaran karena tidak ada data pembayaran pada MVP.

---

## 9. Data model minimal dan constraints

Gunakan migration Laravel, foreign key, index yang relevan, casts, factories, dan seeder aman. Jangan menghapus tabel starter kit yang digunakan untuk autentikasi.

| Tabel | Kolom inti | Aturan penting |
|---|---|---|
| `users` | id, name, email, password, role, is_active | Reuse tabel starter; role `admin`/`barber`, tidak ada public registration |
| `barbers` | id, user_id, name, slug, photo, bio, specialization, queue_prefix, availability_status, is_active | user_id dan prefix unik |
| `services` | id, name, slug, description, price, estimated_duration_minutes, image_path, is_active | harga `numeric`, durasi positif |
| `customers` | id, name, normalized_phone | Normalisasi telepon; batasi akses PII |
| `barber_schedules` | id, barber_id, weekday, starts_at, ends_at, is_active | Validasi jam dan benturan jadwal |
| `business_hours` | id, weekday, starts_at, ends_at, is_closed | Periode bisnis |
| `business_closures` | id, date, reason | Libur/pengecualian |
| `queue_counters` | id, barber_id, queue_date, last_number | Unique `(barber_id, queue_date)` + lock saat increment |
| `queue_tickets` | id(ULID/UUID), customer_id, normalized_phone, barber_id, service_id, queue_date, sequence_number, queue_number, source, status, token_hash, idempotency_key, price_snapshot, duration_snapshot, grace_started_at, grace_expires_at, checked_in_at, called_at, started_at, completed_at, cancelled_at, missed_at | Unique nomor per barber/hari, proteksi tiket aktif ganda, status transisi valid |
| `queue_events` | id, queue_ticket_id, actor_user_id nullable, event_type, previous_status, new_status, reason, metadata, created_at | Audit append-only |
| `qr_codes` | id, code, label, target_path, is_active | Code unik, QR menuju portal lokasi |
| `gallery_images` | id, title, image_path, category, sort_order, is_active | Storage aman |
| `business_settings` | key, value | Branding, kontak, timezone, kapasitas, grace minutes, toggle antrean |

**Konsistensi data:**
- Normalisasi nomor HP secara konsisten (misalnya ke format `+62...` untuk Indonesia).
- `queue_date` menggunakan timezone usaha, timestamps disimpan konsisten.
- Simpan snapshot harga dan durasi saat tiket dibuat agar riwayat tidak berubah ketika admin mengedit layanan.
- Buat partial unique index PostgreSQL untuk membatasi satu tiket aktif per normalized phone + tanggal bisnis; aktif = empat status aktif di bagian 6.2. Jika implementasi lain dipilih, demonstrasikan test concurrency yang setara.
- `idempotency_key` unik dalam konteks pembuatan tiket dan asosiasinya, jangan dibocorkan.
- Jangan hard delete tiket atau event historis.

---

## 10. Routing, form, dan komunikasi data

**Gunakan Inertia:** `Route::get` mengembalikan `Inertia::render`, formulir Inertia `useForm` atau form helpers memanggil Laravel web routes. Validasi melalui Form Request; error ditampilkan di halaman terkait; setelah sukses redirect + flash message. **Tidak perlu API REST penuh** untuk CRUD internal.

Kelompok route mutasi yang dibutuhkan (nama final boleh sesuai konvensi Laravel):

**Guest:**
- `GET /antrean` — form.
- `POST /antrean` — buat tiket online (dan mode on-site tervalidasi melalui QR).
- `GET /antrean/lokasi/{code}` — halaman QR, verifikasi QR aktif.
- `GET /antrean/saya/{ticketId}` — halaman pembungkus tiket; data privat hanya saat otorisasi tiket valid.
- `GET /antrean/saya/{ticketId}/status` — endpoint JSON terbatas untuk polling privat.
- `POST /antrean/saya/{ticketId}/check-in` — check-in.
- `POST /antrean/saya/{ticketId}/cancel` — batalkan.

**Staff:**
- `/login`, `/logout` — **reuse** Fortify/starter kit.
- Group `/barber/*` — dashboard, antrean, availability, panggil, mulai, selesaikan, riwayat.
- Group `/admin/*` — dashboard, CRUD barber/akun/layanan/jadwal/galeri, antrean, QR, laporan dan settings.

**Keamanan tiket guest:** Gunakan token akses rahasia high-entropy yang hash-nya tersimpan di database. `ticketId`/nomor antrean/nomor HP saja **bukan otorisasi**. Rancang mekanisme link pemulihan tiket yang tidak membocorkan token ke log dan referrer (contoh akses token di fragment URL yang diserahkan oleh frontend lewat header, atau mekanisme session+tautan penukaran token). Terapkan `Referrer-Policy: no-referrer`, `Cache-Control: no-store` pada halaman privat dan jangan log token. Jangan memasukkan token mentah ke Inertia shared props. Dokumentasikan pilihan implementasi.

Untuk on-site, nilai `source=onsite` tidak boleh dipercayai hanya dari field form biasa: wajib ada proof/QR context yang sah dan rate limit. Karena QR statis bisa dibagikan, ini bukan bukti lokasi fisik yang kuat (upgrade ke QR dinamis dapat menyelesaikannya di fase berikutnya).

---

## 11. Realtime, background jobs, dan perilaku offline

- Instal Reverb + Echo **ketika fase real-time dimulai**, mengikuti konfigurasi bawaan Laravel dan menyesuaikan `composer.json` yang ada.
- Event setelah transaksi committed: `QueueCreated`, `QueueCheckedIn`, `QueueCalled`, `QueueStarted`, `QueueCompleted`, `QueueCancelled`, `QueueMissed`, `BarberAvailabilityChanged`.
- Channel publik hanya membawa ringkasan non-pribadi. Channel staff admin/barber harus private dan memeriksa otorisasi.
- Untuk tiket pelanggan guest pada MVP, gunakan **polling privat tiap ~10–15 detik** via endpoint yang memerlukan token, agar tidak membuka channel pribadi yang tidak terotorisasi; dapat ditingkatkan menjadi channel guest yang aman pada fase berikutnya.
- Perubahan staff melalui Reverb harus muncul tanpa refresh. Jika WebSocket putus, lakukan reconnect dan refetch; polling fallback dapat digunakan.
- Kegagalan broadcast tidak boleh membatalkan transaksi tiket yang sudah sah.
- Scheduler memproses tiket yang melewati toleransi dan proses penutupan hari. Tugas harus idempoten; lock/recheck state sebelum update.
- `composer run dev` belum tentu otomatis menjalankan **Reverb server** dan scheduler, jadi cek script aktual dan dokumentasikan perintah proses yang diperlukan secara terpisah. Di produksi, jalankan queue worker, Reverb, scheduler sebagai proses terkelola.

---

## 12. Pengamanan dan privasi

- Login staff menggunakan session, CSRF, rate limiting, serta middleware dan Laravel Policies/Gates.
- Nonaktifkan fitur pendaftaran akun publik. Admin awal harus dibuat aman melalui command atau seeder dengan password di environment pada development, bukan password tetap di kode.
- Admin membuat akun barber, bukan barber mendaftarkan dirinya sendiri.
- Rate limiting pembuatan tiket, pembatalan, check-in, dan login.
- Semua input divalidasi; file upload image dibatasi tipe, ukuran, nama, dan storage.
- Jangan tampilkan nomor HP atau nama penuh pelanggan pada feed publik; channel publik hanya nomor/status agregat.
- Tambahkan audit log untuk perubahan status dan tindakan admin.
- Jangan percaya payload frontend untuk `queue_number`, `status`, `checked_in_at`, `price_snapshot` maupun `barber_id` tanpa validasi.
- Jangan menaruh credentials, APP_KEY, password, maupun token pada repository atau screenshot publik.
- Tambahkan aturan data retention dan penghapusan/anonymization data pelanggan di kebijakan operasional produksi.

---

## 13. Kriteria penerimaan dan pengujian otomatis

MVP dinyatakan lulus jika:

1. **Online ticket:** valid -> satu tiket `waiting_checkin`, counter bertambah tepat sekali.
2. **On-site ticket:** QR valid -> satu tiket `checked_in`, menggunakan urutan yang sama dengan online pada barber terkait.
3. **Check-in:** tiket online sah -> `checked_in` tanpa membuat tiket kedua; tiket tidak sah/expired ditolak.
4. **Concurrent create:** >=20 request paralel terhadap barber dan hari yang sama tidak menghasilkan nomor duplikat.
5. **Idempotency:** double submit/retry mengembalikan tiket yang sama atau respons konflik yang konsisten, tidak membuat dua tiket.
6. **Duplicate active:** permintaan kedua pada nomor HP sama di hari yang sama ditolak secara aman; perhatikan race.
7. **Call next:** hanya antrean `checked_in` eligible; barber idle dan tidak ada tiket `called` atau `in_service` lainnya.
8. **Late arrival:** tiket online belum hadir dilewati sementara, masa toleransi dimulai saat benar-benar dicapai, check-in selama toleransi tidak menginterupsi layanan berjalan.
9. **No-show:** tiket yang melewati masa toleransi menjadi `missed` sekali saja oleh scheduler.
10. **Invalid transitions:** semua transisi tak sah ditolak, tanpa mengubah database.
11. **Ownership:** barber A tidak bisa memanipulasi antrean barber B; user guest tidak bisa membaca tiket orang lain.
12. **Closed state:** tiket baru ditolak ketika pendaftaran atau barber ditutup/tidak tersedia.
13. **Realtime/polling:** perubahan status tampil tanpa reload penuh; data sensitif tidak dibroadcast ke publik.
14. **Reports:** angka dashboard konsisten dengan tiket yang benar-benar tersimpan.
15. **Responsive:** alur antrean dari HP dapat diselesaikan; error/loading/empty state tersedia.
16. **Installability:** migrasi, seed dev, lint/typecheck (jika script tersedia), build frontend, serta tes backend berjalan sesuai dokumentasi.

Tulis feature tests untuk auth/role, validasi CRUD, antrean, QR, status, concurrency, tiket guest, scheduler, dan real-time event-after-commit. Gunakan factory serta seed yang tidak mengandung data pribadi nyata.

---

## 14. Tahapan implementasi yang wajib diikuti Codex

### Fase 0 — Audit proyek yang sudah ada
- Buka `composer.json`, `package.json`, `routes/web.php`, struktur `resources/js`, dan autentikasi Fortify yang sudah terpasang.
- Periksa versi PHP, Laravel, React, Inertia, tool assets, script dev/build/test, dan konfigurasi DB.
- **Jangan instal proyek baru.** Catat apa yang sudah siap dan yang belum.
- Buat daftar file/fitur yang akan dibuat dan matriks route + role.

### Fase 1 — Pondasi dan autentikasi
- Konfigurasi PostgreSQL, migration terencana, roles/policies, seed admin aman, model dan relasi.
- Nonaktifkan registrasi publik jika masih ada; pertahankan login/logout staff yang berfungsi.
- Bangun layout admin/barber dasar, routing, middleware dan pengujian akses.

### Fase 2 — Queue engine (prioritas)
- Implementasikan counter, tiket, source online/on-site, idempotensi, larangan tiket aktif ganda, token guest, QR check-in, state machine, grace, cancellation, scheduler.
- Buat tests concurrency dan status sebelum mempercantik seluruh UI.

### Fase 3 — UI pelanggan
- Buat landing, layanan, profil barber, galeri, kontak, pengambilan tiket, portal QR, tiket digital, pelacakan dengan polling privat.
- Implementasi desain dark premium dan responsive.

### Fase 4 — Dashboard barber dan admin
- Panggil/mulai/selesai, status kerja barber, CRUD admin, QR code printable, pengaturan bisnis, laporan sederhana.

### Fase 5 — Realtime dan stabilisasi
- Reverb/Echo private channels, after-commit, reconnect, fallback polling, optimasi, test end-to-end penting, dokumentasi dan deployment.

**Jangan melanjutkan fase apabila test inti pada fase sebelumnya gagal** tanpa mendokumentasikan masalah dan langkah perbaikannya.

---

## 15. Di luar lingkup MVP

Tidak dibuat kecuali ada instruksi baru: aplikasi Android/iOS, pembayaran online, POS/kasir, membership, loyalty points, promosi berbayar, AI rekomendasi rambut, reservasi slot tertentu, sistem multi-cabang, WhatsApp API berbayar, dan dashboard customer berakun.

---

## 16. Definition of Done dan handoff

Hasil akhir harus memiliki:
- Kode berfungsi di proyek **Laravel yang sama**, bukan dua aplikasi.
- Migration, factories, seeder aman, policies, validation, service logic, dan tests.
- Halaman guest, barber, admin sesuai PRD.
- Alur antrean hybrid dan check-in satu nomor, no-show, pembatalan, estimasi.
- Audit trail, akses privat, penyimpanan PostgreSQL dan real-time yang aman.
- README langkah setup Windows, syarat PHP/Node, setup PostgreSQL, command migration, dev/build/test, konfigurasi queue/scheduler/Reverb.
- Dokumentasi asumsi, risiko/limitasi MVP (QR statis, telepon belum OTP), daftar test yang lulus/gagal, dan pekerjaan tersisa.

### Instruksi siap pakai untuk AI Codex

> Baca PRD.md seluruhnya. Proyek Laravel React Starter Kit sudah ada pada `C:\kerjaan\aplikasi\barber-shop`. Kita memakai Laravel + Inertia.js + React/TypeScript + Tailwind/shadcn dalam **satu aplikasi**; jangan membuat Next.js atau proyek frontend baru. Audit isi repo terlebih dahulu. Setelah audit, kerjakan **Fase 1** dan laporkan struktur aktual, migration/model/role yang dibuat, hasil test, isu dan langkah berikutnya. Ikuti semua aturan antrean pada PRD ini. Fokus pada integritas transaksi, otorisasi, dan keamanan data guest; jangan langsung membangun semua fitur sekaligus.

---

**Dokumen akhir — BARBERFLOW PRD v2.0**
