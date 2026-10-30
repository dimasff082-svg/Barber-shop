# Fase 2 — Engine antrean

Selesai 29 Oktober 2026, menggunakan PHP 8.5.6 Laragon dan PostgreSQL. Backend antrean tersedia; halaman pelanggan tetap Fase 3, dashboard operasional Fase 4, dan broadcasting Fase 5.

## Arsitektur dan integritas

`CreateQueueTicket` menormalisasi HP dan memakai transaksi dengan retry deadlock. Barber dikunci lebih dahulu, disusul layanan untuk snapshot, pelanggan dan counter. Counter unik per barber/tanggal; partial index menjaga satu HP aktif lintas barber per hari dan satu tiket called/in_service per barber lintas tanggal. Nomor batal tidak digunakan ulang.

Tanggal bisnis memakai timezone usaha (default Asia/Makassar); timestamps UTC. Jam/jadwal memakai interval mulai inklusif, akhir eksklusif. Jadwal melewati tengah malam belum didukung MVP. Kapasitas menghitung semua nomor diterbitkan termasuk pembatalan.

UUID idempotensi diikat pada hash pemilik sesi dan fingerprint payload kanonis. Retry yang sama mengembalikan tiket tanpa token baru; payload berbeda 409, pemilik berbeda 403. Konflik unique dipulihkan setelah rollback agar tidak melakukan query pada transaksi PostgreSQL yang abort.

`QueueWorkflow` memeriksa akun aktif dan kepemilikan pada service. Panggilan memilih checked-in bernomor terkecil; grace untuk waiting_checkin lebih kecil dimulai sekali ketika ada panggilan eligible. Check-in dalam grace ikut panggilan berikutnya tanpa menginterupsi layanan berjalan. Estimasi tidak mengubah status. Check-in pada/selepas deadline menyimpan missed sebelum menolak permintaan.

Expiry mengunci dan memeriksa ulang state; pending/called hari sebelumnya serta no-show menjadi missed sekali saja. In_service tidak dihentikan saat berganti tanggal. Guest hanya membatalkan sebelum dipanggil; admin wajib memberi alasan. Availability tidak boleh memutus tiket yang diproses.

QueueEvent mencatat aktor, status sebelum/sesudah dan alasan. Trigger database serta guard ORM menolak update/delete audit dan hard delete tiket historis. UI audit administratif yang lebih luas mengikuti Fase 4.

## Akses privat dan transport

Token acak 32 byte dikirim dalam body JSON **sekali** saat pembuatan pertama. Database dan grant sesi hanya menyimpan hash; rahasia pemilik sesi juga di-hash di tiket. Respons memakai whitelist tanpa nomor HP, hash atau idempotency key.

Sesi asal atau Authorization Bearer diperlukan untuk detail/check-in/cancel. POST akses menukar Bearer sah menjadi grant sesi baru. Token tidak ditempatkan dalam query/path, shared props Inertia, atau log kode antrean. Fase 3 akan mengambil token pemulihan dari fragment URL, menghapus fragment lalu mengirimnya melalui header dengan CSRF/session yang sah. Infrastruktur produksi harus menghindari logging body/Authorization.

ID saja, token milik tiket lain, dan token query ditolak 404. Respons termasuk error memakai no-store, private, no-referrer dan nosniff. Session lock 30 detik/waktu tunggu 10 detik mencegah request antrean paralel menimpa owner/grants. Gunakan session database serta cache database/Redis dengan atomic lock; default proyek memakai database. Driver cookie tidak sesuai untuk session blocking.

Semua route berada di middleware web Laravel, termasuk session/CSRF; bukan aplikasi/API terpisah. Endpoint JSON kecil fase engine ini menjadi dasar integrasi Inertia pada Fase 3/4; form utama nanti tetap mengikuti PRD. Sumber, status, nomor, tanggal, snapshot dan timestamp ditetapkan backend. Persetujuan wajib pada request pembuatan.

| Route                                             | Nama                     | Perlindungan                                 |
| ------------------------------------------------- | ------------------------ | -------------------------------------------- |
| POST /antrean                                     | queue.store              | Online, persetujuan, idempotensi, rate limit |
| POST /antrean/lokasi/{code}                       | queue.onsite.store       | QR aktif, sumber ditentukan route            |
| POST /antrean/saya/{ticket}/akses                 | queue.access             | Bearer sah untuk grant sesi                  |
| GET /antrean/saya/{ticket}/status                 | queue.status             | Sesi/Bearer, polling privat                  |
| POST /antrean/saya/{ticket}/check-in              | queue.check-in           | Sesi/Bearer dan QR aktif                     |
| POST /antrean/saya/{ticket}/cancel                | queue.cancel             | Sesi/Bearer dan state valid                  |
| POST /staff/antrean/barber/{barber}/call          | queue.staff.call         | Admin/pemilik barber aktif                   |
| PATCH /staff/antrean/barber/{barber}/availability | queue.staff.availability | Admin/pemilik barber aktif                   |
| POST /staff/antrean/{ticket}/start                | queue.staff.start        | Admin/pemilik barber aktif                   |
| POST /staff/antrean/{ticket}/complete             | queue.staff.complete     | Admin/pemilik barber aktif                   |
| POST /staff/antrean/{ticket}/cancel               | queue.staff.cancel       | Admin aktif, alasan wajib                    |

Rate limit: pembuatan 5/menit/IP dan 30/jam/sesi; akses 10/menit/IP; check-in/cancel 20/menit/IP; status 60/menit/IP; staff 60/menit/akun. Cache key tidak memakai token atau HP mentah.

QR statis aktif memberikan konteks portal; QR yang dibagikan tidak membuktikan kehadiran fisik. HP dinormalisasi tetapi belum diverifikasi OTP, sesuai batas MVP.

## Konfigurasi dan scheduler

Pendaftaran tertutup bila konfigurasi belum ada. Data yang dibutuhkan:

- BusinessSetting queue_registration_open: JSON boolean true untuk membuka.
- daily_queue_capacity: JSON integer 1–32767, default 50; Barber.daily_capacity dapat mengesampingkannya.
- grace_minutes: JSON integer 1–120, default 5.
- BusinessHour per weekday (0=Ahad sampai 6=Sabtu), BusinessClosure per tanggal, BarberSchedule aktif.
- Staff dengan role barber aktif, profil aktif, accepts_queue=true, availability available/busy; layanan dan QR aktif.

Data usaha tidak diisi otomatis. CRUD operasional mengikuti Fase 4. Dua migrasi tambahan 000400/000500 sudah diterapkan memakai migrate biasa; total delapan migrasi Ran. Akun admin dipertahankan; .env tidak diubah pada fase ini.

Di PowerShell pada root proyek:

```powershell
. .\scripts\use-php85.ps1
php artisan schedule:work
```

Scheduler menjalankan barberflow:expire-queues setiap menit dengan withoutOverlapping. Eksekusi satu kali: php artisan barberflow:expire-queues. Produksi memerlukan scheduler terkelola; Reverb belum diperlukan pada fase ini.

## File dan validasi

- Dua migrasi, sembilan model baru, perluasan Barber, delapan factory serta enum QueueStatus/QueueSource.
- Domain: app/Services/Queue (creation, rules, phone normalizer, estimate, workflow, availability, access) dan app/Data/CreatedQueueTicket.php.
- HTTP: app/Http/Controllers/Queue, app/Http/Requests/Queue, PrivateQueueResponse, rate limiter AppServiceProvider, routes/queue.php, routes/web.php dan bootstrap/app.php.
- Scheduler: app/Console/Commands/ExpireQueuesCommand.php dan routes/console.php.
- Pengujian: QueueSchemaTest, QueueCreationTest, QueueWorkflowTest, QueueHttpTest dan fixture tests/Support/QueueScenario.php.
- Concurrency: scripts/test-queue-concurrency.php diorkestrasi scripts/test-postgresql.php pada schema terisolasi.

PostgreSQL: **160 tes, 157 lulus, 3 dilewati, 518 assertion**. Empat batch masing-masing 20 proses paralel lulus: counter online/on-site, idempotensi, HP ganda lintas barber, dan panggilan FIFO/event tunggal. FIFO diuji terhadap nomor terbit database, bukan urutan peluncuran proses.

SQLite juga lulus dengan 157 tes berhasil, 3 dilewati, 518 assertion. Pint, PHPStan, lint frontend, TypeScript dan build produksi lulus. Warning fontaine opsional existing tidak menghalangi build. Pemeriksaan gabungan npm run check masih terhalang format PRD.md dan DESIGN_GUIDELINES.md existing; format dokumen fase ini lulus. Pengujian memakai PHPUnit existing; dependency tidak diganti. QA alur UI pelanggan menyusul setelah halaman dibuat.
