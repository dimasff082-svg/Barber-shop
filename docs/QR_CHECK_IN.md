# Check-in tiket dengan scan QR

Penyempurnaan UI pelanggan setelah Fase 5, 30 Oktober 2026. Fitur ini memakai endpoint dan aturan antrean yang sudah tersedia; tidak menambah fase PRD, migrasi, maupun perubahan `.env`.

## Dari tiket digital

1. Buka tiket digital pada browser yang digunakan ketika mengambil antrean, atau pulihkan akses dengan tautan pribadi yang telah disimpan.
2. Untuk tiket online berstatus **Menunggu check-in**, tekan **Scan QR untuk Check-in**.
3. Tekan **Buka Kamera**, izinkan kamera di browser, lalu arahkan ke QR lokasi BARBERFLOW.
4. QR yang berhasil dibaca langsung diperiksa dan dikirim untuk check-in tiket yang sedang dibuka. Tidak perlu mengetik kode QR atau mengambil nomor baru.
5. Setelah berhasil, status tiket diperbarui dan nomor antrean tetap sama.

Pemindai tidak membuka alamat yang terbaca. Hanya QR portal `/antrean/lokasi/{code}` pada origin website yang sama yang diterima. Origin mencakup protokol, host dan port; misalnya QR `127.0.0.1:8000` tidak cocok ketika tiket dibuka melalui alamat IP LAN atau domain lain. Gunakan alamat website yang konsisten dan buat/cetak QR dari lingkungan tujuan.

Kamera memerlukan HTTPS atau alamat lokal yang dipercaya browser seperti localhost/127.0.0.1. Akses lewat HTTP pada alamat IP LAN umumnya tidak menyediakan izin kamera. Kamera dihentikan ketika pemindai ditutup atau halaman tidak aktif; tekan **Buka Kamera** untuk melanjutkan.

Jika kamera tidak tersedia, gunakan **Baca QR dari Foto** dan pilih PNG, JPG, atau WebP maksimal 5 MB. Foto dibaca pada perangkat dan tidak diunggah ke server. Foto harus menampilkan QR secara utuh dan jelas. QR salah, QR nonaktif, akses tiket tidak sah, atau tiket yang sudah kedaluwarsa tidak dapat digunakan untuk check-in.

## Dari portal QR lokasi

1. Pindai QR lokasi dengan pemindai bawaan perangkat untuk membuka portal.
2. Pilih **Check-in Antrean Saya**.
3. Pada tiket digital, gunakan **Salin ID tiket**, lalu tempel ID 26 karakter ke kolom **ID tiket** pada portal. Tiket pada sesi browser yang sama juga dapat dipilih dari daftar yang tersedia.
4. Tekan **Check-in dengan tiket ini**. Portal membuka tiket setelah check-in berhasil.

Jika menggunakan browser atau perangkat lain, buka **Menggunakan perangkat lain?**, lalu tempel tautan pemulihan pribadi pada kolom bertipe password. ID akan diisi dari tautan dan harus cocok dengan tiket yang dipulihkan. Tautan pemulihan memberi akses pribadi ke tiket; simpan seperti kredensial dan jangan membagikannya.

## Integritas dan batasan

- ID memilih tiket, sedangkan sesi tiket atau token rahasia tetap membuktikan hak akses. Mengetahui ID, nomor antrean, atau nomor HP saja tidak memberi izin membaca atau check-in tiket orang lain.
- Endpoint check-in tetap menggunakan ID pada URL tiket. QR aktif, tanggal bisnis, status, dan batas toleransi diperiksa backend; payload tidak menentukan status maupun nomor resmi.
- Retry check-in yang sah tidak menerbitkan tiket/nomor baru atau menggandakan event check-in.
- Pemindaian QR statis memudahkan check-in, tetapi foto atau tautannya dapat dibagikan sehingga belum membuktikan kehadiran fisik secara kuat. QR dinamis/OTP lokasi berada di luar implementasi ini.
- Tidak ada perubahan engine antrean, FIFO, toleransi, otorisasi backend, data usaha, maupun konfigurasi PHP global.

## Pengujian

Gunakan PHP 8.5 Laragon melalui helper proyek pada PowerShell:

```powershell
. .\scripts\use-php85.ps1
php artisan test --filter='ScannedTicketCheckInTest|QueueHttpTest|QueueWorkflowTest|CustomerPagesTest'
node --test scripts/test-location-qr.mjs scripts/test-ticket-client.mjs scripts/test-live-updates.mjs
npm run types:check
npm run build:ssr
```

Tes backend terkait mencakup check-in dengan sesi, ID tiket tidak dapat digunakan untuk mengubah tiket sesi lain, QR nonaktif/tidak dikenal, retry tanpa nomor/event ganda, dan kedatangan tepat pada batas toleransi. Tes Node mencakup parser QR pada origin yang sama, penolakan URL asing/manipulasi URL, serta pembersihan resource termasuk hasil izin kamera yang terlambat.

Decoder foto mencoba deteksi biasa kemudian pembacaan QR ekspor yang bersih jika deteksi awal gagal. Regresi memakai piksel PNG asli yang menemukan masalah tersebut, rotasi 90 derajat dan gambar kosong. Parser origin serta otorisasi backend tetap dijalankan setelah QR terbaca.

## File implementasi

- `resources/js/pages/queue/ticket.tsx` dan `location.tsx`: integrasi scan otomatis dan check-in portal berdasarkan ID tiket.
- `resources/js/components/queue/qr-scanner-dialog.tsx`, `ticket-identifier.tsx` dan `queue-ticket-card.tsx`: pemindai reusable, ID yang dapat disalin dan kartu tiket.
- `resources/js/lib/location-qr.ts` dan `qr-photo-reader.ts`: parser origin, lifecycle kamera dan decoder foto.
- `tests/Feature/ScannedTicketCheckInTest.php`, `scripts/test-location-qr.mjs` dan `tests/Fixtures/qr/`: regresi otorisasi, integritas antrean serta decoder.
- `package.json` / `package-lock.json`: pembaca ZXing dan dependency pengujian PNG; library pembaca dimuat saat diperlukan.
- `README.md`, `docs/DEVELOPMENT_PROGRESS.md` dan panduan ini: penggunaan, status fase dan hasil validasi.

Pengujian kamera fisik tetap perlu dilakukan pada perangkat pelanggan dengan izin kamera dan alamat HTTPS deployment yang sebenarnya. Hasil validasi akhir dicatat pada [progres pengembangan](DEVELOPMENT_PROGRESS.md).
