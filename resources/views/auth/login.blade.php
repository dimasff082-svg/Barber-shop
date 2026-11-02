<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk ke akun staff - {{ config('app.name', 'BARBERFLOW') }}</title>
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

        /* Ambient background lighting */
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

        .ambient-bottom-left {
            position: absolute;
            bottom: -100px;
            left: -80px;
            width: 400px;
            height: 300px;
            border-radius: 9999px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.1) 0%, transparent 70%);
            filter: blur(80px);
            pointer-events: none;
        }

        .ambient-bottom-right {
            position: absolute;
            bottom: -100px;
            right: -80px;
            width: 400px;
            height: 300px;
            border-radius: 9999px;
            background: radial-gradient(circle, rgba(229, 192, 123, 0.1) 0%, transparent 70%);
            filter: blur(80px);
            pointer-events: none;
        }

        .grid-pattern {
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(229, 192, 123, 0.08) 1px, transparent 1px);
            background-size: 28px 28px;
            pointer-events: none;
        }

        /* Card Container - Soft Gold Box */
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

        /* Brand Header */
        .brand-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .brand-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: inherit;
            margin-bottom: 0.5rem;
            transition: transform 0.2s ease;
        }

        .brand-link:hover {
            transform: scale(1.05);
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
            box-shadow: inset 0 1px 3px rgba(229, 192, 123, 0.3);
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
            letter-spacing: -0.01em;
            margin-top: 0.25rem;
            margin-bottom: 0.35rem;
        }

        .login-desc {
            font-size: 0.8125rem;
            color: #c2baa6;
            line-height: 1.4;
            max-width: 320px;
        }

        /* Form styling */
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            margin-bottom: 1.15rem;
        }

        .form-label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .form-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #e8e0d0;
        }

        .forgot-link {
            font-size: 0.75rem;
            color: #e5c07b;
            text-decoration: none;
            transition: color 0.15s ease;
        }

        .forgot-link:hover {
            color: #f3e5ab;
            text-decoration: underline;
        }

        .input-wrapper {
            position: relative;
            width: 100%;
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
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            outline: none;
        }

        .form-input.has-toggle {
            padding-right: 2.75rem;
        }

        .form-input::placeholder {
            color: #6b6357;
        }

        .form-input:focus {
            border-color: #f3e5ab;
            box-shadow: 0 0 0 3px rgba(229, 192, 123, 0.25);
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 0.75rem;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #a09786;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.25rem;
            transition: color 0.15s ease;
        }

        .password-toggle:hover {
            color: #f3e5ab;
        }

        .password-toggle svg {
            width: 18px;
            height: 18px;
        }

        /* Remember me - Compact Checkbox */
        .remember-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }

        .remember-checkbox {
            width: 16px;
            height: 16px;
            min-width: 16px;
            min-height: 16px;
            accent-color: #e5c07b;
            cursor: pointer;
            margin: 0;
            border-radius: 4px;
        }

        .remember-label {
            font-size: 0.75rem;
            color: #d8cfbe;
            cursor: pointer;
            user-select: none;
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            min-height: 44px;
            background: linear-gradient(to right, #d4af37, #e5c07b, #d4af37);
            border: none;
            border-radius: 0.5rem;
            color: #14120c;
            font-weight: 700;
            font-size: 0.875rem;
            letter-spacing: 0.01em;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.25);
            transition: transform 0.15s ease, filter 0.15s ease, box-shadow 0.15s ease;
        }

        .btn-submit:hover {
            filter: brightness(1.08);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.35);
        }

        .btn-submit:active {
            transform: scale(0.99);
        }

        /* Back to Home Link */
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
            transition: color 0.15s ease;
        }

        .back-link:hover {
            color: #f3e5ab;
        }

        .back-link svg {
            width: 14px;
            height: 14px;
        }

        /* Alert notifications */
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
            font-size: 0.75rem;
            padding: 0.65rem 0.85rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            line-height: 1.4;
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
    <div class="ambient-bottom-left"></div>
    <div class="ambient-bottom-right"></div>
    <div class="grid-pattern"></div>

    <div class="card-wrapper">
        <div class="card-glow"></div>

        <div class="login-card">
            <div class="card-top-line"></div>

            <div class="brand-header">
                <a href="{{ route('home') }}" class="brand-link" title="Beranda Barberflow">
                    <div class="logo-badge">
                        <!-- Scissors Icon -->
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

                <h1 class="login-title">Masuk ke akun staff</h1>
                <p class="login-desc">Gunakan email dan kata sandi akun admin atau barber Anda.</p>
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

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <!-- Email Input -->
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-wrapper">
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            tabindex="1"
                            autocomplete="email"
                            placeholder="admin@barberflow.com"
                            class="form-input"
                        >
                    </div>
                </div>

                <!-- Password Input -->
                <div class="form-group">
                    <div class="form-label-row">
                        <label for="password" class="form-label">Kata sandi</label>
                        @if ($canResetPassword ?? true)
                            <a href="{{ route('password.request') }}" class="forgot-link" tabindex="5">
                                Lupa kata sandi?
                            </a>
                        @endif
                    </div>
                    <div class="input-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            tabindex="2"
                            autocomplete="current-password"
                            placeholder="Kata sandi"
                            class="form-input has-toggle"
                        >
                        <button
                            type="button"
                            class="password-toggle"
                            onclick="togglePassword()"
                            aria-label="Tampilkan / sembunyikan kata sandi"
                            tabindex="-1"
                        >
                            <svg id="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg id="eye-off-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path>
                                <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path>
                                <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path>
                                <line x1="2" y1="2" x2="22" y2="22"></line>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Remember Me (Compact size) -->
                <div class="remember-row">
                    <input
                        type="checkbox"
                        id="remember"
                        name="remember"
                        tabindex="3"
                        class="remember-checkbox"
                        {{ old('remember') ? 'checked' : '' }}
                    >
                    <label for="remember" class="remember-label">Ingat saya</label>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-submit" tabindex="4" data-test="login-button">
                    Masuk
                </button>
            </form>

            <div class="card-footer">
                <a href="{{ route('home') }}" class="back-link">
                    <!-- Arrow Left Icon -->
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    <span>Kembali ke Halaman Utama</span>
                </a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            var pwd = document.getElementById('password');
            var eyeIcon = document.getElementById('eye-icon');
            var eyeOffIcon = document.getElementById('eye-off-icon');

            if (pwd.type === 'password') {
                pwd.type = 'text';
                eyeIcon.style.display = 'none';
                eyeOffIcon.style.display = 'block';
            } else {
                pwd.type = 'password';
                eyeIcon.style.display = 'block';
                eyeOffIcon.style.display = 'none';
            }
        }
    </script>
</body>
</html>

