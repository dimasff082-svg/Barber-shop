<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lupa Kata Sandi - {{ config('app.name', 'BARBERFLOW') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Instrument Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #0a0908;
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
            position: relative;
            overflow-x: hidden;
        }

        .ambient-top {
            position: absolute;
            top: -140px;
            left: 50%;
            transform: translateX(-50%);
            width: 640px;
            height: 380px;
            border-radius: 9999px;
            background: radial-gradient(ellipse at center, rgba(229, 192, 123, 0.22) 0%, rgba(212, 175, 55, 0.1) 40%, transparent 70%);
            filter: blur(60px);
            pointer-events: none;
        }

        .grid-pattern {
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(229, 192, 123, 0.08) 1px, transparent 1px);
            background-size: 28px 28px;
            pointer-events: none;
        }

        .card-wrapper {
            position: relative;
            width: 100%;
            max-width: 440px;
            z-index: 10;
        }

        .card-glow {
            position: absolute;
            inset: -2px;
            border-radius: 20px;
            background: linear-gradient(to bottom, rgba(229, 192, 123, 0.35), rgba(212, 175, 55, 0.12), transparent);
            filter: blur(8px);
            opacity: 0.8;
            pointer-events: none;
        }

        .login-card {
            position: relative;
            background: rgba(22, 20, 16, 0.95);
            border: 2px solid rgba(229, 192, 123, 0.4);
            border-radius: 1rem;
            padding: 2rem 2rem 2.25rem;
            box-shadow: 0 15px 45px -10px rgba(0, 0, 0, 0.85), 0 0 35px rgba(229, 192, 123, 0.15);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            overflow: hidden;
        }

        .card-top-line {
            position: absolute;
            top: 0;
            left: 2.5rem;
            right: 2.5rem;
            height: 2px;
            background: linear-gradient(to right, transparent, #f3e5ab, transparent);
            opacity: 0.9;
        }

        .brand-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .logo-badge {
            width: 48px;
            height: 48px;
            border-radius: 1rem;
            border: 2px solid rgba(229, 192, 123, 0.4);
            background: linear-gradient(135deg, rgba(229, 192, 123, 0.25), rgba(212, 175, 55, 0.15), #1c1812);
            color: #f3e5ab;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.5rem;
        }

        .logo-badge svg {
            width: 24px;
            height: 24px;
        }

        .brand-title {
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.28em;
            color: #e5c07b;
            text-transform: uppercase;
        }

        .login-title {
            font-size: 1.375rem;
            font-weight: 700;
            color: #ffffff;
            margin-top: 0.25rem;
            margin-bottom: 0.35rem;
        }

        .login-desc {
            font-size: 0.8125rem;
            color: #c2baa6;
            line-height: 1.4;
            max-width: 320px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            margin-bottom: 1.25rem;
        }

        .form-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #e8e0d0;
        }

        .form-input {
            width: 100%;
            min-height: 44px;
            background: rgba(16, 14, 10, 0.9);
            border: 1px solid rgba(229, 192, 123, 0.35);
            border-radius: 0.5rem;
            padding: 0.6rem 0.85rem;
            font-size: 0.875rem;
            color: #ffffff;
            outline: none;
        }

        .form-input:focus {
            border-color: #f3e5ab;
            box-shadow: 0 0 0 3px rgba(229, 192, 123, 0.25);
        }

        .btn-submit {
            width: 100%;
            min-height: 44px;
            background: linear-gradient(to right, #d4af37, #e5c07b, #d4af37);
            border: none;
            border-radius: 0.5rem;
            color: #14120c;
            font-weight: 700;
            font-size: 0.875rem;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.25);
            transition: filter 0.15s ease;
        }

        .btn-submit:hover {
            filter: brightness(1.08);
        }

        .card-footer {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(229, 192, 123, 0.2);
            text-align: center;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.75rem;
            color: #c2baa6;
            text-decoration: none;
        }

        .back-link:hover {
            color: #f3e5ab;
        }

        .back-link svg {
            width: 14px;
            height: 14px;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
            font-size: 0.75rem;
            padding: 0.65rem 0.85rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .alert-status {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.4);
            color: #86efac;
            font-size: 0.75rem;
            padding: 0.65rem 0.85rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="ambient-top"></div>
    <div class="grid-pattern"></div>

    <div class="card-wrapper">
        <div class="card-glow"></div>

        <div class="login-card">
            <div class="card-top-line"></div>

            <div class="brand-header">
                <a href="{{ route('login') }}" style="text-decoration: none; color: inherit;">
                    <div class="logo-badge">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="6" cy="6" r="3"></circle>
                            <circle cx="6" cy="18" r="3"></circle>
                            <line x1="20" y1="4" x2="8.12" y2="15.88"></line>
                            <line x1="14.47" y1="14.48" x2="20" y2="20"></line>
                            <line x1="8.12" y1="8.12" x2="12" y2="12"></line>
                        </svg>
                    </div>
                    <span class="brand-title">BARBERFLOW</span>
                </a>

                <h1 class="login-title">Lupa Kata Sandi</h1>
                <p class="login-desc">Masukkan alamat email terdaftar untuk menerima tautan pemulihan kata sandi.</p>
            </div>

            @if (session('status'))
                <div class="alert-status">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert-error">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        placeholder="email@example.com"
                        class="form-input"
                    >
                </div>

                <button type="submit" class="btn-submit" data-test="email-password-reset-link-button">
                    Kirim Tautan Pemulihan
                </button>
            </form>

            <div class="card-footer">
                <a href="{{ route('login') }}" class="back-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    <span>Kembali ke Halaman Masuk</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>

