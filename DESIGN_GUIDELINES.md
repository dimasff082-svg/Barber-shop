# BARBERFLOW — DESIGN GUIDELINES

**Versi:** 1.0  
**Untuk:** `C:\kerjaan\aplikasi\barber-shop`  
**Produk:** Website profil barbershop + sistem antrean hybrid  
**Bahasa antarmuka:** Bahasa Indonesia  
**Dokumen pendamping:** `PRD.md` dan `AGENTS.md`

> **Instruksi untuk Codex:** Baca `PRD.md` terlebih dahulu untuk memahami fungsi, aturan antrean, status, hak akses, dan arsitektur. Dokumen ini mengatur **desain visual dan perilaku antarmuka**, bukan mengganti aturan bisnis pada PRD. Bangun UI menggunakan React + TypeScript + Inertia.js yang SUDAH ADA di proyek Laravel. Jangan membuat aplikasi Next.js atau frontend terpisah. Gambar dalam `design-references/` adalah inspirasi visual, **bukan layar yang ditempel sebagai gambar**. Implementasikan ulang sebagai komponen React yang responsif, aksesibel, dan terhubung ke data nyata.

---

## 1. Visi desain

BARBERFLOW harus terasa seperti **premium men's grooming studio**: modern, tegas, hangat, profesional, dan mudah dipakai. Gunakan pendekatan **dark-first** dengan warna hitam arang dan aksen emas. Jangan menjadikan halaman antrean rumit demi efek visual.

Tiga konteks berbeda, satu identitas merek:

1. **Website publik:** editorial, visual kuat, foto berkualitas, CTA **Ambil Antrean** jelas.
2. **Alur pelanggan:** mobile-first, langkah pendek, informasi nomor/status sangat jelas.
3. **Dashboard staff:** fokus ke data dan aksi; tabel, filter, konfirmasi, dan status mudah dipindai.

**Urutan prioritas:** (1) fungsi + kejelasan status, (2) aksesibilitas, (3) responsivitas, (4) konsistensi komponen, (5) dekorasi.

## 2. Design tokens

### 2.1 Warna

| Token | Hex | Penggunaan |
|---|---|---|
| `background` | `#101010` | Background utama |
| `surface` | `#1C1C1C` | Card, navbar, sidebar |
| `surface-raised` | `#262626` | Input, hover, card aktif |
| `border` | `#383838` | Garis pemisah/outline |
| `primary` | `#D4AF37` | CTA utama, nomor penting, highlight |
| `primary-hover` | `#E2C15B` | Hover CTA emas |
| `text` | `#FFFFFF` | Headline dan teks utama |
| `text-muted` | `#A3A3A3` | Deskripsi/metadata |
| `success` | `#22C55E` | Berhasil, selesai, sudah hadir |
| `warning` | `#F59E0B` | Menunggu, batas waktu |
| `danger` | `#EF4444` | Gagal, pembatalan, terlewat |
| `info` | `#60A5FA` | Dipanggil, sedang dilayani, informasi |

Pastikan kontrast minimum WCAG AA untuk teks; jangan menggunakan aksen emas tipis pada teks yang terlalu kecil. Tombol utama: background emas dengan teks gelap. Fokus keyboard harus terlihat jelas.

Implementasikan melalui CSS variables/tokens bawaan starter kit (`resources/css/app.css` atau lokasi aktual). **Sesuaikan** sistem token shadcn/ui yang sudah terpasang; jangan menduplikasi tema.

### 2.2 Tipografi

- Heading display/hero: **Oswald** atau font display sans-condensed setara, dengan fallback `sans-serif`.
- Body dan dashboard: **Inter** atau font sans UI bawaan starter kit yang sudah ada.
- Jangan memaksakan penggantian font jika bertentangan dengan konfigurasi proyek.
- Heading hero desktop: sekitar 56–72 px; mobile: 36–44 px.
- H1 halaman internal: 30–40 px desktop; 26–32 px mobile.
- Teks body: 14–16 px; label penting 14 px atau lebih.
- Nomor tiket: 52–72 px, tebal, dengan angka yang mudah dibaca (`tabular-nums`).
- Gunakan maksimal dua keluarga font, dengan hierarki ukuran konsisten.

### 2.3 Layout, ruang, radius

- Gunakan spacing berbasis kelipatan 4 px; jarak umum 8 / 12 / 16 / 24 / 32 / 48 / 64 px.
- Lebar konten landing page maksimal 1200–1280 px; padding horizontal 20–24 px mobile dan 32–48 px desktop.
- Lebar form pelanggan maksimal 560–640 px agar fokus.
- Radius: 8 px untuk kontrol kecil; 12–16 px untuk card; 20–24 px untuk feature card.
- Shadow halus; jangan membuat seluruh card bercahaya/gradient besar.
- Pakai grid yang rapi, generous whitespace, border tipis, dan hierarki konten yang tegas.

### 2.4 Ikon, tombol, animasi

- Ikon: **Lucide React**, ukuran konsisten 16/20/24 px.
- Gunakan **shadcn/ui** yang sudah dipasang, buat wrapper/reusable component bila diperlukan.
- Primary button: emas, label langsung seperti **Ambil Antrean**, **Konfirmasi**, **Panggil Berikutnya**.
- Secondary: surface/outline, Destructive: merah dengan dialog konfirmasi.
- Target sentuh minimal **44 × 44 px** pada mobile.
- Loading: spinner/skeleton; tombol disable selama submit untuk mencegah pengiriman ganda (backend tetap wajib idempoten).
- Transisi 150–250 ms; hormati `prefers-reduced-motion`.
- Jangan menggunakan carousel otomatis atau animasi yang mengganggu pembacaan antrean.

---

## 3. Navigasi dan informasi

### 3.1 Website publik

Navbar: `Beranda`, `Layanan`, `Barber`, `Galeri`, `Kontak`, tombol **Ambil Antrean**. Pada mobile gunakan menu ringkas, dengan CTA antrean yang tetap mudah dicapai. Logo brand BARBERFLOW orisinal; jangan menyalin identitas merek dari screenshot.

Footer: nama brand, jam buka, alamat, kontak, media sosial, copyright. Gunakan data konfigurasi bisnis bila sudah tersedia, bukan mengarang alamat atau jam buka.

### 3.2 Halaman pelanggan

Alur: **Pilih Layanan → Pilih Barber → Isi Data → Konfirmasi → Tiket Digital**. Tampilkan progress/stepper sederhana; pertahankan pilihan pengguna saat validasi gagal. Jika barber tidak tersedia, tunjukkan status dan jangan izinkan submit.

### 3.3 Dashboard admin dan barber

Desktop: sidebar + topbar; mobile: drawer/menu + konten berbentuk card. Gunakan breadcrumb/judul halaman konsisten dan tampilan identitas staff. Menu yang tidak punya izin harus disembunyikan sekaligus dilindungi oleh authorization di Laravel.

---

## 4. Spesifikasi layar

### 4.1 Beranda `/`

- Hero full-width dengan **foto nyata barbershop/aktivitas cukur**, overlay gelap untuk keterbacaan.
- Eyebrow singkat, headline tegas (mis. **Tampil Rapi. Tanpa Antre Lama.**), deskripsi maksimal dua baris, CTA **Ambil Antrean**, secondary CTA **Lihat Layanan**.
- Section: Tentang Kami, Layanan Unggulan, Barber Kami, Galeri, Cara Antre (Online / QR di lokasi), Preview Status Antrean Publik, Lokasi/Kontak, Footer.
- Preview antrean hanya menampilkan informasi publik non-pribadi, **bukan nama atau nomor HP pelanggan**.
- Pada mobile hero memfokuskan headline dan CTA; foto tetap proporsional tanpa memotong wajah secara buruk.

### 4.2 Layanan `/layanan`

- Card dengan foto opsional, nama, deskripsi singkat, harga Rupiah, durasi estimasi, CTA **Pilih Layanan**.
- Harga berasal dari database; harga dummy hanya untuk mode seed/demo.
- Card disabled jika layanan tidak aktif; jangan membingungkan pilihan yang masih bisa diklik.

### 4.3 Barber `/barbers`

- Card portrait barber dengan nama, spesialisasi, jadwal ringkas, status saat ini, dan CTA **Pilih Barber**.
- Warna status selalu disertai teks: `Tersedia`, `Sedang Melayani`, `Istirahat`, `Tidak Bertugas`.
- Jangan memakai foto stok yang memberi kesan seseorang benar-benar bekerja di usaha ini. Jika masih demo, tandai sebagai **Data Demo** atau gunakan placeholder netral.

### 4.4 Galeri `/galeri`

- Grid responsif: 2 kolom mobile (atau 1 pada layar sangat sempit), 3 kolom tablet, 3–4 kolom desktop.
- Foto memiliki aspect ratio konsisten; gunakan `object-cover`, lazy loading, alt text deskriptif, serta modal/lightbox yang bisa ditutup via keyboard.
- Gunakan foto hasil cukuran nyata dengan izin pemilik foto; jangan menggunakan screenshot UI sebagai galeri.

### 4.5 Ambil antrean `/antrean`

**Mobile-first** dan sederhana:
1. Pilih satu layanan.
2. Pilih barber yang sedang menerima antrean.
3. Isi nama dan nomor HP.
4. Tampilkan ringkasan: layanan, barber, harga, durasi perkiraan, jumlah antrean di depan dan sumber (online/onsite).
5. Konfirmasi dengan tombol tunggal.

- State: loading, data kosong, barbershop tutup, barber penuh, form invalid, konflik tiket aktif, error jaringan.
- Jelaskan bahwa **perkiraan waktu dapat berubah** dan nomor dari rumah harus check-in di lokasi.
- Jangan menambah pemilihan jam reservasi karena **di luar MVP**.

### 4.6 Portal QR `/antrean/lokasi/{code}`

- Header `Selamat Datang` dan penjelasan singkat.
- Dua aksi setara yang mudah dipahami: **Ambil Antrean Baru** dan **Check-in Antrean Saya**.
- Untuk check-in tampilkan input/konteks tiket dan penegasan jika berhasil.
- Tunjukkan error `QR tidak valid / sudah tidak aktif` dan opsi kembali ke halaman utama.
- QR statis **bukan bukti kehadiran kuat**; jangan menampilkan klaim validasi lokasi otomatis.

### 4.7 Tiket digital `/antrean/saya/{ticketId}`

**Prioritas visual:** nomor antrean > status > barber/layanan > sisa antrean/estimasi > petunjuk check-in.

- Nomor besar `A-015` (contoh), label status dengan ikon dan teks.
- Status yang perlu divisualisasikan: `Menunggu Check-in`, `Sudah Hadir`, `Dipanggil`, `Sedang Dilayani`, `Selesai`, `Terlewat`, `Dibatalkan`.
- Tampilkan tanggal tiket, barber, layanan, jumlah antrean di depan, estimasi waktu jika dapat dihitung.
- Penjelasan batas check-in; tindakan **Batalkan Antrean** hanya bila status mengizinkan; dialog konfirmasi wajib.
- Tiket privat hanya boleh terbuka untuk pemegang akses yang valid; **jangan** membocorkan token akses melalui UI atau log.
- Jika tidak ada koneksi, tampilkan `Terakhir diperbarui ...`, jangan menggambarkan data lama sebagai real-time.

### 4.8 Login staff `/login`

- Gunakan login bawaan starter kit; hanya ubah styling/branding secara aman.
- Card sederhana, field email/password, show/hide password, validation message, loading.
- **Jangan buat tombol Daftar Akun publik**, karena admin/barber dibuat melalui sistem.

### 4.9 Dashboard Barber `/barber/*`

- Header identitas barber, status availability, statistik singkat hari ini.
- Card fokus **Sedang Dilayani** dan **Antrean Berikutnya**.
- Daftar antrean disortir sesuai data resmi backend, tiap item menunjukkan nomor, layanan, waktu, status check-in.
- Tombol aksi besar: `Panggil`, `Mulai Layanan`, `Selesaikan`, `Tandai Terlewat` **hanya** jika diizinkan oleh status/aturan PRD.
- Tindakan berisiko memakai dialog konfirmasi. Ketika menunggu check-in tampilkan pesan informatif, bukan mengarang antrean eligible.
- State kosong: `Belum ada pelanggan dalam antrean`.

### 4.10 Dashboard Admin `/admin/*`

- Kartu KPI: total tiket, menunggu, check-in, sedang dilayani, selesai, terlewat, dibatalkan, barber aktif.
- Tabel antrean dengan filter tanggal, barber, sumber dan status; pagination.
- CRUD Barber, Layanan, Galeri, Jadwal, QR Code, Jam Operasional, Settings dan Laporan.
- Form modal/drawer untuk edit ringan; halaman penuh untuk form panjang.
- Semua nilai dari database; **jangan hardcode angka statistik**.
- Tidak menampilkan omzet `sudah dibayar` karena tidak ada sistem pembayaran pada MVP.

---

## 5. Komponen reusable wajib

Buat komponen secara proporsional dengan struktur starter kit yang ada, misalnya:

- `PublicNavbar`, `PublicFooter`, `SectionHeading`, `PrimaryCTA`.
- `ServiceCard`, `BarberCard`, `GalleryGrid`.
- `QueueStepIndicator`, `QueueSummary`, `QueueTicketCard`, `QueueStatusBadge`, `QueueEmptyState`.
- `ConfirmActionDialog`, `FormFieldError`, `LoadingButton`.
- `DashboardShell`, `StatCard`, `StatusFilter`, `QueueTable`.

**Satu komponen status** harus menjadi sumber tampilan untuk status antrean. Gunakan label, warna, ikon, dan deskripsi yang konsisten di semua halaman; label status mengikuti PRD.

---

## 6. Responsive dan aksesibilitas

- Rancang mulai dari viewport **375 px**; uji juga 320 px, 768 px, 1024 px, 1440 px.
- Mobile: satu kolom untuk formulir/tiket; tombol konfirmasi full-width; field tidak terlalu rapat.
- Tablet: dua kolom card; dashboard menyesuaikan tanpa horizontal scroll halaman.
- Desktop: grid 3–4 kolom untuk katalog; sidebar untuk dashboard.
- Tabel lebar boleh scroll horizontal **di dalam kontainer tabel**, bukan keseluruhan halaman.
- Semantik HTML, label pada semua input, keyboard focus, error terkait field, alt text, dialog fokus-terkelola.
- Jangan gunakan warna saja untuk menyampaikan status.
- Pastikan status loading, error, empty, disabled, success, dan offline dipikirkan untuk setiap aksi penting.

---

## 7. Aturan foto dan aset

Pisahkan **screenshot referensi desain** dan **foto yang benar-benar ditampilkan dalam website**.

### 7.1 Screenshot UI — `design-references/` (TIDAK ditampilkan sebagai komponen produksi)

| Nama file | Fungsi |
|---|---|
| `01-landing-desktop.png` | Hero desktop, navbar, komposisi section, CTA |
| `02-landing-mobile.png` | Adaptasi navbar/hero ke HP |
| `03-queue-booking-mobile.png` | Pemilihan layanan/barber, formulir, stepper |
| `04-ticket-status-mobile.png` | Nomor antrean besar, status, estimasi |
| `05-barber-dashboard.png` | Daftar antrean dan tombol aksi staff |
| `06-admin-dashboard.png` | Sidebar, KPI, tabel, filter, form CRUD |
| `07-qr-checkin-mobile.png` | Opsional: dua aksi portal QR dan check-in |

**Penting:** Nama file di atas adalah **slot yang perlu diisi pengguna** dengan screenshot desain pilihan. Jangan membuat screenshot palsu lalu menganggapnya referensi yang diberikan pengguna. Jika gambar belum tersedia, gunakan aturan tertulis dalam dokumen ini. Jangan menyalin logo, foto, ilustrasi, teks merek, atau desain pixel-perfect dari pihak lain.

### 7.2 Foto konten — `public/images/` (digunakan dalam website)

| Nama file | Lokasi penggunaan | Bentuk foto | Ukuran disarankan |
|---|---|---|---|
| `hero-barbershop.webp` | Hero beranda | Aksi barber memotong rambut dengan ruang untuk teks | 1920×1080, landscape |
| `shop-interior.webp` | Tentang Kami | Interior barbershop, kursi, cermin | 1200×800, landscape |
| `service-haircut.webp` | Layanan | Close-up proses haircut | 800×600 |
| `service-beard.webp` | Layanan | Grooming beard/trimming | 800×600 |
| `barber-01.webp` | Profil barber | Portrait barber asli, dengan izin | 600×750, portrait |
| `barber-02.webp` | Profil barber | Portrait barber asli, dengan izin | 600×750, portrait |
| `gallery-fade.webp` | Galeri | Hasil potongan fade | 800×1000 |
| `gallery-classic.webp` | Galeri | Hasil potongan classic | 800×1000 |
| `gallery-textured.webp` | Galeri | Hasil potongan textured | 800×1000 |
| `tools-detail.webp` | Section editorial | Detail gunting, clipper, alat | 1000×700 |

Untuk MVP awal, **empat foto prioritas**: `hero-barbershop.webp`, `shop-interior.webp`, `service-haircut.webp`, `tools-detail.webp`. Foto barber dan galeri bisa menggunakan placeholder demo yang jelas sampai tersedia konten asli.

- Utamakan foto milik barbershop dengan izin; foto stok untuk demo boleh, tetapi jangan menyebut subjek stok sebagai barber/karyawan sungguhan.
- Download dari sumber yang lisensinya mengizinkan penggunaan website; periksa ketentuannya dan dokumentasikan sumber.
- Simpan lokal dalam `public/images/`, hindari hotlink pihak ketiga.
- Kompres ke WebP/AVIF jika memungkinkan. Target sekitar 100–300 KB untuk card, hero idealnya <500 KB sesuai kualitas.
- Selalu sediakan `alt` yang deskriptif, dimensi/aspect ratio agar layout tidak bergeser, lazy loading pada foto di bawah fold.
- Logo/brand harus orisinal; jangan gunakan logo dari karya desain referensi.

### 7.3 Referensi pencarian awal (opsional)

**UI** — screenshot sendiri dari tautan berikut, hanya sebagai referensi visual:
- Landing black-gold: https://dribbble.com/shots/26703773-Barber-Elite-Premium-Barbershop-Website-Design
- Booking mobile: https://dribbble.com/shots/26802701-Barbery-Barbershop-app-UI-Kit
- Tampilan tiket/estimasi: https://dribbble.com/shots/23544301-Barbershop-Booking-App
- Dashboard admin: https://dribbble.com/shots/26026064-Project-Dashboard

**Foto stok contoh** — unduh dari halaman resmi (periksa lisensi):
- Haircut: https://unsplash.com/photos/barber-cutting-mans-hair-idzUojjazCg
- Interior: https://unsplash.com/photos/an-empty-barbershop-interior-with-several-chairs-and-mirrors-AXurvQTtO3Y
- Close-up peralatan: https://www.pexels.com/photo/close-up-shot-of-barbershop-tools-4730937/
- Barber sedang bekerja: https://www.pexels.com/photo/a-barber-at-work-7697445/

---

## 8. Aturan implementasi Codex

1. Baca **`PRD.md` + `AGENTS.md` + file ini** sebelum mengubah UI.
2. Periksa komponen, routing, tailwind config, dan Vite/Vite+ pada starter kit aktual.
3. Buat tema dan komponen reusable; jangan menyebarkan hex value berulang di banyak file.
4. Kode harus React + TypeScript + Inertia dalam `resources/js` sesuai starter kit.
5. Gunakan komponen shadcn/ui bila cocok; jaga konsistensi import dan struktur existing.
6. Jangan menambahkan Next.js, frontend baru, ataupun halaman statis HTML terpisah.
7. Jangan membuat UX yang mengubah aturan bisnis antrean (misalnya booking jam atau status palsu).
8. Jangan hardcode status/nomor/estimasi real pada UI production; gunakan Inertia props/endpoint tervalidasi.
9. Jika foto atau screenshot belum ada, implementasikan placeholder berlabel demo dan tetap lanjutkan komponen.
10. Implementasikan satu halaman inti dahulu, presentasikan hasilnya, lalu terapkan pola ke halaman lain.
11. Jalankan lint, typecheck, build, dan test relevan yang didukung package scripts proyek.
12. Dokumentasikan setiap perubahan penting dan keputusan desain yang menyimpang dari referensi.

## 9. UI Acceptance Checklist

- [ ] Warna, font, jarak, radius konsisten.
- [ ] Navigasi publik jelas dan CTA antrean menonjol.
- [ ] Pengambilan antrean nyaman pada layar 375 px.
- [ ] Informasi tiket mudah dipahami tanpa mengandalkan warna.
- [ ] Check-in via QR menampilkan **Ambil Baru** dan **Check-in Saya**.
- [ ] Form menampilkan validasi, loading, error, sukses, dan disabled state.
- [ ] Status real berasal dari backend; tidak ada mock yang tampak sebagai data hidup.
- [ ] Dashboard barber bisa dipakai dengan sedikit klik dan tombol cukup besar.
- [ ] Dashboard admin memiliki filter/pagination dan layout responsive.
- [ ] Foto memiliki alt text, sumber/lisensi jelas, dan ukuran teroptimasi.
- [ ] Keyboard, focus state, kontras, serta reduced-motion diperiksa.
- [ ] Tidak ada identitas pihak ketiga atau data pelanggan sensitif yang bocor.

---

**Akhir dokumen — BARBERFLOW DESIGN GUIDELINES v1.0**
