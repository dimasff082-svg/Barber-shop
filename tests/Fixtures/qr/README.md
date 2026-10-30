# Fixture QR portal lokasi

`location-portal.png` adalah QR valid hasil encoder BaconQrCode dengan koreksi M, quiet zone empat modul dan skala delapan piksel. QR ini dipertahankan persis dari fixture QA terisolasi yang menemukan kegagalan deteksi finder pattern pada ZXing 0.23.0; default dan TRY_HARDER gagal pada orientasi awal, sementara PURE_BARCODE membaca payload yang benar. Pada rotasi 90 derajat, deteksi biasa berhasil.

Payload adalah URL portal localhost QA pada port 18955, tanpa token akses tiket, password, maupun data pelanggan. Kode QR lokasi bukan kredensial kepemilikan tiket. Fixture bukan QR operasional produksi dan tidak perlu diregenerasi untuk menjalankan tes.

`scripts/test-location-qr.mjs` membaca piksel PNG nyata, menguji decoder foto produksi pada orientasi awal serta rotasi 90 derajat, dan memastikan gambar kosong ditolak. Dengan demikian fallback tetap memverifikasi struktur/error correction QR sebelum parser origin dan validasi backend dijalankan.
