# Pengujian PostgreSQL BARBERFLOW

`scripts/test-postgresql.php` menjalankan seluruh suite PHPUnit existing pada schema PostgreSQL baru yang khusus dibuat untuk satu proses pengujian. Runner menggunakan konfigurasi koneksi aplikasi melalui bootstrap Laravel; kredensial tidak ditulis ke source, file konfigurasi tes, argumen command, atau output runner.

## Menjalankan

Gunakan PHP 8.5 Laragon, pastikan `.env` aplikasi sudah memilih koneksi PostgreSQL yang dapat diakses, serta `DB_SCHEMA` aplikasi adalah `public`. Driver `pdo_pgsql` harus aktif pada proses PHP yang menjalankan runner.

```powershell
. .\scripts\use-php85.ps1
php scripts/test-postgresql.php
```

Jika driver belum dimuat oleh konfigurasi proses terminal:

```powershell
& 'C:\laragon\bin\php\php-8.5.6-Win32-vs17-x64\php.exe' -d extension=pdo_pgsql scripts/test-postgresql.php
```

Runner memeriksa driver pada child PHP dan meneruskan opsi `-d extension=pdo_pgsql` bila diperlukan. Pengaturan PHP global tidak diubah. Runner tidak menerima opsi filter atau bootstrap tambahan; pengujian selalu memakai seluruh suite pada `phpunit.xml`.

Cache konfigurasi Laravel harus tidak tersedia. Runner menolak cache yang masih aktif karena cache dapat mengabaikan override environment schema. Runner tidak menghapus cache secara diam-diam.

## Batas isolasi

1. Koneksi asal harus bertipe `pgsql`, mengarah ke database yang sesuai dengan konfigurasinya, dan memakai daftar `search_path` tepat `public`.
2. Runner membuat schema baru bernama `barberflow_test_` diikuti 24 karakter heksadesimal acak. Tidak ada penggunaan ulang schema dan tidak ada `CREATE SCHEMA IF NOT EXISTS`.
3. Child menerima `DB_SCHEMA` yang hanya berisi schema baru tersebut. `public` tidak disertakan dalam daftar pencarian schema child. Nama database dan kredensial disampaikan melalui environment proses, bukan argumen command.
4. Preflight child memeriksa `database.default`, `PostgresBuilder::getCurrentSchemaListing()`, identitas database, dan `current_schema()` sebelum PHPUnit diizinkan berjalan.
5. `phpunit.xml` tetap mempertahankan default SQLite di memori untuk perintah pengujian biasa. Nilai environment XML tanpa `force` menghormati override proses yang disiapkan runner. Cache, session, mail, serta queue pengujian juga diarahkan ke driver pengujian agar tidak memakai data operasional aplikasi.
6. Trait `RefreshDatabase` memang memanggil `migrate:fresh`; pada runner ini connection `search_path` membatasinya pada schema baru. Migrasi, foreign key, seed/factory, dan transaksi pengujian dibuat di schema tersebut, bukan tabel aplikasi dalam `public`.
7. Setelah suite selesai atau terjadi exception, cleanup memeriksa ulang nama schema, identitas database, role koneksi, dan ownership sebelum menghapus **hanya schema baru milik proses ini** beserta data tesnya. Runner tidak pernah menjalankan `DROP DATABASE` atau `migrate:fresh` pada schema aplikasi.

Cleanup tetap diupayakan saat suite gagal. Interupsi paksa terhadap proses PHP dapat meninggalkan schema sementara; identifikasi schema dengan nama yang dilaporkan dan periksa ownership sebelum membersihkannya. Runner menolak cleanup jika identitas atau ownership berubah.

## Bukti yang diperiksa

Suite fase pondasi mencakup role yang sah, default akun nonaktif, relasi user–barber unik, prefix barber unik dan berformat A–Z, foreign key yang menolak penghapusan user tertaut, availability yang sah, rentang harga dan durasi layanan, serta penjagaan nilai pada update. Pada PostgreSQL, pemeriksaan tersebut memakai constraint PostgreSQL sesungguhnya, bukan trigger SQLite.

Suite kini mencakup Fase 2: counter, idempotensi, QR, token guest, status, grace, scheduler, ownership, CSRF dan audit. Setelah PHPUnit lulus, runner menjalankan scripts/test-queue-concurrency.php pada schema sementara yang sama: empat batch masing-masing 20 child PHP paralel menguji counter campuran, retry UUID, HP ganda lintas barber, serta panggilan FIFO tunggal. Urutan kedatangan proses tidak diasumsikan; FIFO diperiksa terhadap sequence terbit database.

Worker menerima fixture melalui stdin dan kredensial melalui environment. Output tidak memuat token atau exception mentah. Worker menolak runtime selain PHP 8.5/testing, cache konfigurasi, schema selain pola schema uji atau search_path tidak sesuai. Fixture dibuat setelah suite selesai agar terlihat antar-proses tanpa mengganggu transaksi RefreshDatabase. Cleanup runner menghapus schema miliknya beserta fixture.

Catat hasil aktual di docs/DEVELOPMENT_PROGRESS.md. Kelulusan engine bukan penyelesaian UI, dashboard atau realtime yang mengikuti Fase 3–5.
