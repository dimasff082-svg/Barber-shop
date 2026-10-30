# Fase 3 — UI pelanggan

Pembaruan: 29 Oktober 2026. Implementasi Fase 3 selesai dalam proyek Laravel yang sama, memakai React/TypeScript, Inertia, Tailwind dan komponen shadcn existing. **Fase berikutnya: Fase 4 — Dashboard operasional**, belum dimulai.

Validasi otomatis dan pemeriksaan browser yang tercatat di bawah telah selesai. QA memakai server lokal dan database SQLite khusus pengujian yang terpisah dari PostgreSQL aplikasi; tidak ada data pelanggan uji yang dimasukkan ke database usaha.

## Halaman dan pengalaman pelanggan

| Halaman                  | Hasil                                                                                                            |
| ------------------------ | ---------------------------------------------------------------------------------------------------------------- |
| `/`                      | Landing dark premium dengan katalog, ringkasan antrean dan informasi usaha dari backend.                         |
| `/layanan`               | Layanan aktif, harga Rupiah, durasi dan pilihan layanan menuju form antrean.                                     |
| `/barber`                | Profil aktif, availability, jadwal, jumlah menunggu serta penerimaan antrean resmi.                              |
| `/galeri`                | Foto aktif database atau aset ilustrasi berlabel, dengan lightbox yang dapat ditutup melalui keyboard.           |
| `/kontak`                | Alamat, jam buka, tautan peta dan WhatsApp yang tersedia dari pengaturan usaha.                                  |
| `/antrean`               | Alur pilih layanan → barber → data pelanggan → ringkasan dan persetujuan → tiket.                                |
| `/antrean/lokasi/{code}` | Dua pilihan Ambil Antrean Baru dan Check-in Antrean Saya; QR tidak aktif memiliki halaman error.                 |
| `/antrean/saya/{ticket}` | Tiket privat, nomor/status, estimasi backend, petunjuk check-in, pembatalan dengan konfirmasi dan akses kembali. |

Navbar/footer kini menghubungkan halaman nyata. CTA Ambil Antrean tetap mudah dicapai di mobile; halaman form/tiket menghindari CTA tetap yang menutupi tindakan utama. PublicLayout dan shell halaman menjaga tema publik terpisah dari layout staff. ServiceCard, BarberCard, GalleryGrid, BusinessHours serta komponen antrean dipakai ulang. Bootstrap fragment hanya memasang browser listener ketika window tersedia, sehingga pemanasan modul SSR development tetap berhasil.

Foto memakai aset lokal public/images, tidak memakai screenshot sebagai UI. Profil barber tanpa foto memakai placeholder netral; tidak ada orang stok yang diklaim sebagai pegawai. Katalog tidak mengarang nama, harga, status atau jadwal. Galeri fallback menyatakan dengan jelas bahwa foto adalah ilustrasi inspirasi gaya. Empty state tetap informatif ketika database usaha belum diisi.

Desain mengikuti palet dark/emas, typography dan spacing existing. Referensi landing, form mobile, tiket dan QR digunakan sebagai arah visual. Kontrol penting memiliki target sentuh minimal 44 px, label/error terkait input, ikon bersama label status, dan dialog fokus-terkelola. Form mempertahankan data pada kegagalan validasi, menonaktifkan submit saat proses, serta menggunakan UUID idempotensi. UI tidak menambah pemilihan slot jam.

## Data dan integrasi backend

PublicCatalog mengirim whitelist layanan/barber aktif, agregat jumlah antrean tanpa nama/HP pelanggan, jadwal dan informasi usaha yang tersedia. Kelayakan barber ditampilkan berdasarkan QueueRules yang sama dengan pembuatan tiket; backend memeriksa ulang ketika submit. Informasi publik merupakan snapshot saat halaman dibuka, bukan klaim realtime.

Model/migrasi GalleryImage ditambahkan untuk membaca galeri resmi. Migrasi `2026_10_29_000600_create_gallery_images_table.php` telah diterapkan; total **9 migrasi Ran**. Tidak ada perubahan .env, seed data usaha pada database aplikasi, penggantian stack atau perubahan aturan status/penomoran engine Fase 2. CRUD galeri/pengaturan tetap Fase 4.

Form utama menggunakan Inertia useForm dan route web Laravel. Sukses pembuatan mengalihkan pelanggan ke halaman tiket; validasi kembali ke formulir. Endpoint JSON privat Fase 2 tetap dipakai untuk status, check-in, cancel dan pertukaran akses. Session, CSRF, rate limit, session lock serta header privasi dipertahankan.

## Akses tiket dan pemulihan

Halaman tiket mengirim pembungkus generik untuk ID dengan format sah, tanpa membocorkan keberadaan atau detail tiket. Data privat diambil melalui endpoint status setelah otorisasi sesi/Bearer. Inertia history halaman tiket dienkripsi; token mentah tidak masuk page props, shared props, query URL atau penyimpanan browser persisten.

Untuk sukses form Inertia, TicketSecretHandoff menyimpan token **terenkripsi** di sesi selama maksimal **5 menit**. Endpoint secret memerlukan akses tiket sah dan mengembalikan token ke memori klien. Klien mengirim acknowledgment untuk menghapus handoff setelah menerima token. Pemisahan read/ack memungkinkan retry bila respons jaringan hilang; token yang expired/tidak dapat didekripsi juga dibersihkan. Sesi berbasis database tidak menyimpan token pemulihan mentah.

Tautan pemulihan memakai fragment access, bukan query. Fragment ditangkap dan dihapus menggunakan replaceState sebelum router Inertia diinisialisasi; hanya memori halaman yang menyimpan nilainya dan mengikatnya ke ID tiket terkait. Token dikirim melalui Authorization header ke endpoint pertukaran sesi dengan CSRF, same-origin, no-store dan no-referrer. Input tautan menolak origin lain, query token, ID/path tambahan atau token dengan format tidak sah. QR context dapat diteruskan dalam fragment untuk check-in pada tiket yang sama.

Pelanggan dapat menyalin tautan akses kembali melalui tindakan eksplisit dengan pesan hasil. Tautan itu adalah akses rahasia dan harus disimpan pribadi. Browser yang masih memiliki grant sesi tetap dapat membuka tiket; ID/nomor HP saja tidak memberikan akses pada perangkat lain. Token tidak dikeluarkan ulang oleh retry pembuatan tiket.

## Polling dan koneksi

useTicketPolling memperbarui endpoint privat sekitar **12 detik**. Request yang tumpang tindih dicegah; timer berhenti saat tab tersembunyi/offline, status terminal, atau akses ditolak. Saat koneksi/tab aktif kembali, status diambil ulang. AbortController membersihkan request ketika komponen berganti.

Error jaringan mempertahankan data terakhir dengan label waktu pembaruan, tanpa menyebutnya realtime. Retry menggunakan backoff sampai 120 detik; 429 menghormati Retry-After yang dibatasi. Kegagalan otorisasi menghapus data privat dari tampilan dan meminta pemulihan akses. Status completed/cancelled/missed menghentikan polling. Pesan error HTTP ditangani tanpa menampilkan respons debug mentah.

Check-in tetap memakai QR aktif dan nomor yang sudah diterbitkan. Pembatalan memerlukan dialog konfirmasi; menutup dialog tidak mengubah tiket. Semua transisi dan hasil estimasi tetap diputuskan engine Fase 2 di backend.

## Pengujian dan bukti yang tersedia

- Suite backend SQLite dan PostgreSQL asli: masing-masing **168 tes, 165 lulus, 3 dilewati, 755 assertion**. Tiga skip terkait Fortify opsional existing.
- Empat skenario concurrency PostgreSQL dari Fase 2 tetap lulus, masing-masing 20 proses paralel.
- Empat tes client Node lulus: parser pemulihan, capture fragment sebelum router, request CSRF/Authorization/no-store, dan pesan error/throttling aman.
- PHP 8.5.6 Laragon dipakai untuk PHP/Artisan/tests/tooling serta child PHP Wayfinder; PHP global tidak diubah.
- Pint, PHPStan nol error, TypeScript, lint frontend dan build final lulus, termasuk perbaikan fragment dan gambar kontak mobile. Warning fontaine opsional existing tidak memblokir build.
- npm run check masih terhalang format PRD.md dan DESIGN_GUIDELINES.md existing. Dokumen spesifikasi tidak diubah; format source dan dokumen fase ini diperiksa terpisah.
- Browser 375 px: form online menghasilkan tiket UI-001 dan detailnya terbuka melalui sesi privat.
- Browser 320 px: tiket digital tampil tanpa horizontal overflow halaman.
- QR check-in mempertahankan UI-001 dan mengubahnya menjadi checked_in.
- Dialog cancel: memilih tetap antre tidak mengubah tiket; konfirmasi menjadikannya cancelled dan polling terminal berhenti.
- Tindakan salin menunjukkan pemberitahuan hasil. Unduh akses menghasilkan file dengan tautan fragment yang sah; setelah grant sesi QA dikosongkan, tautan memulihkan tiket UI-002 dan fragment langsung hilang dari URL, termasuk navigasi fragment pada halaman tiket yang sudah terbuka.
- Alur on-site: nomor HP tidak valid menampilkan error inline, mengembalikan langkah data diri dan mempertahankan input. Setelah diperbaiki, UI-002 terbit dengan sumber onsite dan status checked_in, melanjutkan counter online UI-001.
- Landing, layanan, barber, galeri, kontak, form dan tiket diperiksa pada lebar 320/375/768/1024/1440 px tanpa horizontal overflow. Temuan overflow gambar kontak pada mobile diperbaiki dengan rasio gambar responsif dan diperiksa ulang pada lima ukuran.
- Lightbox galeri terbuka, ditutup dengan Escape dan mengembalikan fokus ke pemicu. QR tidak aktif menampilkan halaman error; ID tiket tanpa akses menampilkan gerbang akses tanpa detail pelanggan.

Screenshot tersedia pada docs/previews/phase3-ticket-mobile.jpg dan phase3-gallery-desktop.jpg; keduanya tidak menyertakan tautan/token pemulihan. Pemeriksaan ini bukan audit seluruh perangkat, screen reader atau simulasi offline browser; perilaku offline/backoff diimplementasikan, tetapi simulasi koneksi terputus belum dijalankan secara manual.

Verifikasi akhir pada aplikasi utama PostgreSQL: seluruh halaman publik/form merespons 200, sembilan migrasi aktif, admin tetap aktif, jumlah tiket aplikasi nol, dan tidak ada schema tes tersisa. Server utama dijalankan dengan PHP 8.5 Laragon pada 127.0.0.1:8000 dan Vite pada 127.0.0.1:5173. Halaman antrean menampilkan closed state sesuai konfigurasi kosong, tanpa error JavaScript pada pemeriksaan browser. Bukti: docs/previews/phase3-main-closed-state.jpg. Server QA port 8001 dihentikan dan database/router QA sementara telah dibersihkan.

## Berkas utama

- Publik: resources/js/pages/welcome.tsx, pages/public, components/public dan layouts/public-layout.tsx.
- Pelanggan: resources/js/pages/queue, components/queue, hooks/use-ticket-polling.ts, lib/queue-request.ts, lib/ticket-fragment.ts dan types/customer.ts.
- Server: PublicCatalog, controller halaman publik/QueuePageController, GuestQueueController, TicketSecretHandoff, GalleryImage serta migrasi 000600.
- Routing/boot: routes/web.php, routes/queue.php dan resources/js/app.tsx.
- Pengujian: tes halaman/alur pelanggan pada tests/Feature serta scripts/test-ticket-client.mjs.

## Menjalankan dan batas fase

```powershell
. .\scripts\use-php85.ps1
php artisan test
php scripts/test-postgresql.php
node --test scripts/test-ticket-client.mjs
npm run types:check
npm run build
```

Database aplikasi masih belum berisi konfigurasi operasional yang diperlukan untuk menerima pelanggan. Halaman produksi menampilkan empty/closed state sampai layanan, barber aktif, jam buka, jadwal, QR dan toggle penerimaan tersedia. Data QA tidak menjadi seed usaha nyata.

Fase 4 berikutnya membangun dashboard operasional, CRUD/pengaturan, QR printable dan laporan. Scheduler tetap memerlukan proses aktif dengan php artisan schedule:work saat development. Reverb/Echo, private channels dan stabilisasi koneksi staff mengikuti Fase 5. QR statis belum membuktikan kehadiran fisik dan HP belum memakai OTP, sesuai batas MVP.
