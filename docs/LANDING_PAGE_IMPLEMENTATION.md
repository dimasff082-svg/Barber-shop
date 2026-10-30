# Implementasi Landing Page BARBERFLOW

## Lingkup dan integrasi

Implementasi ini berfokus pada landing page `/` melalui halaman Inertia `welcome`. Acuannya adalah `PRD.md`, `AGENTS.MD`, `DESIGN_GUIDELINES.md`, dan screenshot landing dalam `design-references/`. Screenshot menjadi inspirasi komposisi, bukan gambar yang ditempel sebagai halaman produksi.

Stack pada manifest proyek tetap digunakan: Laravel `^13.17`, React `^19.2`, Inertia `^3.0`, TypeScript `^5.7`, Tailwind CSS `^4`, Vite+ `1.4`, serta komponen shadcn/ui berbasis Radix dan ikon Lucide. Test backend yang tersedia memakai PHPUnit 12. Tidak ada proyek frontend, Next.js, dependency, atau font baru yang ditambahkan.

Tidak ada perubahan controller, route, model, database, aturan antrean, autentikasi starter kit, atau `.env`. Halaman publik lain dan dashboard berada di luar lingkup tahap ini.

## Keputusan design system

- Token semantik shadcn dipakai kembali melalui `.public-theme`: background `#101010`, card `#1C1C1C`, surface raised `#262626`, border `#383838`, primary `#D4AF37`, hover `#E2C15B`, foreground putih, dan teks sekunder `#A3A3A3`. Token success, warning, danger, dan info mengikuti pedoman.
- `@theme inline` membuat utility Tailwind membaca token pada elemen yang menggunakannya. Tema publik dibatasi pada wrapper landing, termasuk `.public-theme` pada konten portal Sheet dan Dialog; pilihan appearance staff tetap memakai sistem starter kit.
- Display menggunakan font sistem condensed `Impact`, `Arial Narrow`, lalu fallback sans-serif sebagai padanan yang diizinkan pedoman. Body mempertahankan Instrument Sans yang sudah dikonfigurasi starter kit. Tidak ada unduhan atau ketergantungan font tambahan.
- Konten memakai lebar maksimal 1280 px, padding 24 px pada mobile dan 32–48 px pada layar lebih lebar. Spacing mengikuti skala 4 px; section memakai ruang vertikal 64–96 px. Kontrol membulat sekitar 8 px dan card 12 px.
- Grid beradaptasi dari satu kolom ke dua atau tiga kolom. Menu desktop berubah menjadi drawer pada mobile, dengan CTA tetap di bagian bawah layar. Target sentuh utama minimal 44 px.
- Skip link, focus ring, alt foto, judul/deskripsi dialog, navigasi keyboard, Escape, scrolling drawer, dan reduced motion disediakan. Navigasi dari drawer memindahkan fokus ke section tujuan tanpa mengembalikan scroll ke tombol menu. Label tutup menu dan galeri berbahasa Indonesia.

## File implementasi

File yang diperbarui:

- `resources/css/app.css` — token tema, font display, container, spacing section, focus, overlay hero, dan reduced motion.
- `resources/js/pages/welcome.tsx` — komposisi landing: hero, tentang, layanan, barber, galeri, cara antre, informasi antrean, dan kontak.
- `resources/js/components/ui/sheet.tsx` dan `resources/js/components/ui/dialog.tsx` — opsi `closeLabel` agar antarmuka publik dapat memberi nama tombol tutup dalam bahasa Indonesia; nilai default existing dipertahankan untuk pemakai lain.

File reusable baru:

- `resources/js/layouts/public-layout.tsx` — wrapper publik, skip link, footer, dan CTA mobile.
- `resources/js/components/public/brand.tsx` — identitas BARBERFLOW.
- `resources/js/components/public/public-navbar.tsx` — navigasi anchor dan drawer mobile.
- `resources/js/components/public/public-footer.tsx` — navigasi footer dan akses staff existing.
- `resources/js/components/public/primary-cta.tsx` — CTA antrean yang konsisten.
- `resources/js/components/public/section-heading.tsx` — hierarki judul section.
- `resources/js/components/public/content-image.tsx` — foto dengan lazy loading, decoding asinkron, dan fallback berlabel.
- `resources/js/components/public/service-card.tsx` — kartu contoh layanan.
- `resources/js/components/public/barber-card.tsx` — kartu profil demo.
- `resources/js/components/public/gallery-grid.tsx` — grid galeri dan lightbox shadcn.

## Konten, aset, dan batas integrasi

Foto menggunakan aset lokal yang disediakan pengguna dalam `public/images/`; tidak ada hotlink atau pengunduhan foto baru. Foto hero, interior, layanan, alat, portrait, dan galeri dipakai sesuai konteks. Sumber asal, izin subjek, dan lisensi aset belum disertakan dalam proyek; implementasi ini tidak mengklaim status lisensinya. Sebelum publikasi, pemilik proyek perlu melengkapi informasi tersebut.

Layanan diberi label contoh, portrait diberi label **Profil demo**, dan galeri diberi penjelasan **Inspirasi gaya · foto ilustrasi**. Tidak ada nama karyawan, harga, durasi, jadwal, alamat, kontak, atau jam operasional yang dikarang. Data yang belum tersedia dijelaskan sebagai informasi yang akan dilengkapi. Kegagalan memuat gambar menampilkan **Foto belum tersedia** beserta deskripsi gambarnya.

CTA **Ambil Antrean** menuju section `#antrean`. Section ini menjelaskan bahwa antrean online belum tersedia karena route dan layanan backend antrean belum diimplementasikan. Tidak ada link `/antrean` yang menghasilkan 404, formulir palsu, nomor tiket, estimasi, atau status operasional yang dibuat di frontend. Integrasi berikutnya harus memakai data dan validasi resmi backend sesuai PRD.

## Hasil validasi

| Pemeriksaan                                  | Hasil                                                                                                                                                                                                                             |
| -------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `npm run types:check`                        | Lulus.                                                                                                                                                                                                                            |
| `npm run build`                              | Lulus; terdapat peringatan optional dependency `fontaine` pada konfigurasi font existing.                                                                                                                                         |
| `vp check` terarah pada 12 file implementasi | Lulus.                                                                                                                                                                                                                            |
| `npm run check` seluruh proyek               | Belum lulus karena format dokumen existing `PRD.md` dan `DESIGN_GUIDELINES.md`.                                                                                                                                                   |
| `php artisan test` dengan PHP 8.5            | Lulus: 24 tes terdaftar, 21 lulus, 3 dilewati, 53 assertion.                                                                                                                                                                      |
| QA browser desktop/mobile                    | Lebar 320, 375, 768, 1024, dan 1440 px diperiksa tanpa overflow horizontal.                                                                                                                                                       |
| Interaksi browser                            | Navigasi anchor drawer, CTA menuju informasi antrean, lightbox galeri, Escape dan pengembalian fokus, serta skip link lulus. Drawer pada viewport 768 × 320 px tetap dapat digulir. Tidak ada error/warning konsol yang teramati. |

Runtime PHP pada PATH terminal adalah 8.2, sedangkan dependency terpasang membutuhkan minimal 8.4.1. Validasi memakai runtime yang sudah tersedia, `C:\laragon\bin\php\php-8.5.6-Win32-vs17-x64\php.exe`, tanpa mengganti konfigurasi global. Test PHP dan server preview berjalan di luar sandbox karena sandbox mengembalikan `is_readable=false` untuk bootstrap PHPUnit dan memblokir koneksi localhost. Pengujian memakai database SQLite di memori dari `phpunit.xml`, tanpa migrasi atau penghapusan data aplikasi.

Build produksi memperbarui output terbangun dalam `public/build/`. Peringatan `fontaine` bersifat opsional; build tetap selesai. Dokumen spesifikasi asli dibiarkan utuh sehingga format kedua dokumen tersebut tetap menjadi penghambat pemeriksaan seluruh proyek.

Pratinjau hasil build:

- [Desktop 1440 × 1000](previews/landing-desktop.jpg)
- [Mobile 375 × 812](previews/landing-mobile.jpg)
- [Halaman desktop lengkap](previews/landing-desktop-full.jpg)

Pekerjaan tersisa adalah mengisi data usaha dan menghubungkan antrean saat backend tersedia. Pola komponen belum diterapkan ke halaman lain. Asal/lisensi aset masih perlu dicatat oleh pemilik proyek; tidak ada klaim sumber yang direkayasa.
