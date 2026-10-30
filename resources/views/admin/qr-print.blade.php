<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Cetak QR — BARBERFLOW</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eee; color: #111; font: 16px/1.6 Arial, sans-serif; }
        main { width: min(100%, 760px); margin: 32px auto; padding: 40px 24px; text-align: center; background: #fff; }
        h1 { margin: 0; letter-spacing: .08em; font-size: 28px; }
        h2 { font-size: 24px; }
        img { width: min(100%, 400px); height: auto; }
        .url { overflow-wrap: anywhere; font-size: 12px; }
        button, a { display: inline-block; min-height: 44px; padding: 12px 20px; color: #111; }
        button { border: 0; border-radius: 8px; background: #d4af37; cursor: pointer; font: inherit; }
        .note { max-width: 480px; margin: 24px auto; font-size: 13px; }
        @page { size: A4; margin: 15mm; }
        @media print { body { background: white; } main { width: 100%; margin: 0; padding: 0; } .actions { display: none; } }
    </style>
</head>
<body>
<main>
    <h1>BARBERFLOW</h1>
    <h2>Pindai untuk antre atau check-in</h2>
    <p>{{ $label }}</p>
    @unless($isActive)<p><strong>QR NONAKTIF — aktifkan sebelum dipasang di lokasi.</strong></p>@endunless
    <img src="{{ $imageUrl }}" alt="QR untuk membuka portal antrean lokasi" width="400" height="400">
    <p class="url">{{ $portalUrl }}</p>
    <p class="note">Pilih Ambil Antrean Baru jika belum memiliki tiket. Sudah mendaftar online? Pilih Check-in Antrean Saya menggunakan tiket Anda.</p>
    <p class="note">QR statis dapat dibagikan dan belum menjadi bukti kehadiran fisik yang kuat.</p>
    <div class="actions"><button type="button" onclick="window.print()">Cetak / Simpan PDF</button><a href="{{ route('admin.qr.index') }}">Kembali ke daftar QR</a></div>
</main>
</body>
</html>
