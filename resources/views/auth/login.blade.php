<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <link rel="icon" type="image/png" href="{{ $siteConfiguration['logo_url'] }}">
    <title>Masuk | {{ $siteConfiguration['app_name'] }}</title>
    <style>
        :root {
            color-scheme: light;
            --brand-primary: {{ $siteConfiguration['primary_color'] }};
            --brand-primary-foreground: {{ $siteConfiguration['primary_foreground'] }};
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #24382d;
            background: #e4eee4;
            font-synthesis: none;
            text-rendering: optimizeLegibility;
        }
        * { box-sizing: border-box; }
        body {
            min-width: 320px;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(125deg, #b8d8c1 0%, #e8f1e7 46%, #c8dfcf 100%);
            background-size: 150% 150%;
            animation: backdrop 18s ease-in-out infinite alternate;
        }
        .page {
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(360px, .95fr);
            min-height: 100vh;
            max-width: 1440px;
            margin: 0 auto;
            padding: clamp(24px, 6vw, 88px);
            gap: clamp(48px, 9vw, 140px);
            align-items: center;
        }
        .page.has-flash { padding-top: 145px; }
        .identity { animation: arrive .55s ease-out both; }
        .brand { display: flex; align-items: center; gap: 14px; margin-bottom: 70px; }
        .brand-mark {
            width: 48px;
            height: 48px;
            aspect-ratio: 1;
            object-fit: contain;
        }
        .brand-name { font-size: 14px; font-weight: 700; letter-spacing: 0; }
        .brand-caption { margin-top: 4px; color: #536748; font-size: 12px; }
        .eyebrow { color: #597a37; font-size: 11px; font-weight: 800; text-transform: uppercase; }
        h1 {
            max-width: 590px;
            margin: 18px 0;
            font-size: 64px;
            font-weight: 650;
            line-height: 1.08;
            letter-spacing: -.045em;
        }
        .intro { max-width: 430px; color: #435a3b; font-size: 16px; line-height: 1.7; }
        .access-note {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 54px;
            color: #435a3b;
            font-size: 13px;
        }
        .access-note::before { width: 30px; height: 1px; background: #b56d47; content: ""; }
        .login-panel {
            width: 100%;
            max-width: 440px;
            justify-self: end;
            padding: clamp(28px, 4vw, 48px);
            border: 1px solid rgba(91, 106, 47, .2);
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 24px 70px rgba(65, 78, 35, .16);
            animation: arrive .65s .08s ease-out both;
        }
        .login-panel h2 { margin: 0; font-size: 30px; font-weight: 650; letter-spacing: -.03em; }
        .panel-copy { margin: 9px 0 30px; color: #69745b; font-size: 14px; line-height: 1.6; }
        label { display: block; margin: 18px 0 8px; color: #43553b; font-size: 13px; font-weight: 800; }
        input {
            width: 100%;
            min-height: 50px;
            padding: 0 14px;
            border: 1px solid #c8c99c;
            border-radius: 5px;
            outline: none;
            background: #fff;
            color: #24382d;
            font: inherit;
            font-size: 14px;
        }
        input:focus { border-color: #568263; box-shadow: 0 0 0 3px rgba(73, 132, 88, .18); }
        .error { margin: 8px 0 0; color: #9c452c; font-size: 13px; }
        .submit {
            width: 100%;
            min-height: 50px;
            margin-top: 27px;
            border: 0;
            border-radius: 4px;
            background: linear-gradient(110deg, #4e914e, #39734a);
            color: #fff;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: background .18s ease, transform .18s ease;
        }
        .submit:hover { transform: translateY(-1px); background: linear-gradient(110deg, #5a9e53, #3d7f4c); }
        .submit:focus-visible { outline: 3px solid #4b7a42; outline-offset: 3px; }
        .panel-foot { margin: 22px 0 0; color: #788061; font-size: 11px; line-height: 1.6; text-align: center; }
        .forgot-link { display: block; margin-top: 14px; color: #376a41; font-size: 12px; font-weight: 700; text-align: center; }
        @keyframes backdrop { from { background-position: 0% 20%; } to { background-position: 100% 80%; } }
        @keyframes arrive { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 760px) {
            .page { grid-template-columns: 1fr; max-width: 560px; gap: 34px; padding: 28px 22px; }
            .page.has-flash { padding-top: 145px; }
            .brand { margin-bottom: 36px; }
            h1 { max-width: 420px; font-size: 44px; }
            .intro { font-size: 14px; }
            .access-note { display: none; }
            .login-panel { justify-self: stretch; max-width: none; padding: 30px 24px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
        }
        :root { color: #203a55; background: #d9eaff; }
        body { background: linear-gradient(125deg, color-mix(in srgb, var(--brand-primary), white 83%) 0%, #fff0bd 52%, color-mix(in srgb, var(--brand-primary), white 75%) 100%); }
        .brand-caption, .intro, .access-note, label, .panel-copy { color: #526b83; }
        .eyebrow, .forgot-link { color: var(--brand-primary); }
        .access-note::before { background: #e0bf49; }
        .login-panel { border-color: #d1dfec; box-shadow: 0 24px 70px rgba(29, 69, 105, .14); }
        input { border-color: #b9cde0; color: #203a55; }
        input:focus { border-color: var(--brand-primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand-primary), transparent 80%); }
        .submit { background: linear-gradient(110deg, color-mix(in srgb, var(--brand-primary), #102f51 24%), var(--brand-primary)); box-shadow: inset 0 -3px 0 #e0bf49; color: var(--brand-primary-foreground); }
        .submit:hover { background: linear-gradient(110deg, color-mix(in srgb, var(--brand-primary), #102f51 42%), color-mix(in srgb, var(--brand-primary), #102f51 18%)); }
        .submit:focus-visible { outline-color: #e0bf49; }
        .panel-foot { color: #687d91; }
    </style>
</head>
<body>
    @include('partials.flash-notice')
    <main class="page {{ session()->has('status') || $errors->any() ? 'has-flash' : '' }}">
        <section class="identity" aria-labelledby="page-title">
            <div class="brand">
                <img class="brand-mark" src="{{ $siteConfiguration['logo_url'] }}" alt="Logo {{ $siteConfiguration['institution_name'] }}">
                <div>
                    <div class="brand-name">{{ $siteConfiguration['app_name'] }}</div>
                    <div class="brand-caption">{{ $siteConfiguration['institution_name'] }}</div>
                </div>
            </div>
            <p class="eyebrow">Sistem Informasi Akademik</p>
            <h1 id="page-title">Selamat datang di ruang akademik.</h1>
            <p class="intro">Masuk untuk mengakses layanan akademik sesuai akun Anda.</p>
            <div class="access-note">Satu akses untuk mahasiswa, dosen, dan pengelola.</div>
        </section>

        <section class="login-panel" aria-labelledby="login-title">
            <h2 id="login-title">Masuk ke SIAKAD</h2>
            <p class="panel-copy">Gunakan username, NIM, NIDN, atau kode asisten Anda.</p>
            <form method="POST" action="{{ route('login.store') }}">
                @csrf
                <label for="login">Identitas akun</label>
                <input id="login" name="login" type="text" value="{{ old('login') }}" autocomplete="username" maxlength="100" required aria-describedby="login-error">
                @error('login')
                    <p class="error" id="login-error" role="alert">{{ $message }}</p>
                @enderror

                <label for="password">Kata sandi</label>
                <input id="password" name="password" type="password" autocomplete="current-password" maxlength="255" required>
                @error('password')
                    <p class="error" role="alert">{{ $message }}</p>
                @enderror

                <button class="submit" type="submit">Masuk</button>
            </form>
            <p class="panel-foot">Gunakan akun SIAKAD yang telah diberikan oleh pengelola.</p>
            <a class="forgot-link" href="{{ route('password.request') }}">Lupa password?</a>
        </section>
    </main>
</body>
</html>