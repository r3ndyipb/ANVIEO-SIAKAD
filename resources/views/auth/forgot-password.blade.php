<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ $siteConfiguration['logo_url'] }}">
    <title>Lupa Password | {{ $siteConfiguration['app_name'] }}</title>
    <style>
        :root { font-family: "Avenir Next", Avenir, "Segoe UI", sans-serif; color: #24382d; background: #e4eee4; }
        * { box-sizing: border-box; }
        body { display: grid; min-width: 320px; min-height: 100vh; margin: 0; padding: 24px; place-items: center; background: linear-gradient(125deg, #b8d8c1, #e8f1e7 48%, #c8dfcf); }
        body.has-flash { padding-top: 120px; }
        main { width: min(100%, 440px); padding: 36px; border: 1px solid #e3e9e2; border-radius: 10px; background: #fff; box-shadow: 0 24px 70px rgba(31, 69, 44, .14); animation: panel-in .45s ease-out both; }
        .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 34px; font-size: 14px; font-weight: 700; }
        .brand img { width: 48px; height: 48px; object-fit: contain; }
        h1 { margin: 0; font: 400 34px/1.15 Georgia, "Times New Roman", serif; }
        p { color: #566548; font-size: 14px; line-height: 1.65; }
        label { display: block; margin: 22px 0 8px; color: #43553b; font-size: 13px; font-weight: 800; }
        input { width: 100%; min-height: 48px; padding: 0 12px; border: 1px solid #c8c99c; border-radius: 5px; font: inherit; }
        input:focus { outline: 3px solid rgba(73, 132, 88, .18); border-color: #568263; }
        button { width: 100%; min-height: 48px; margin-top: 20px; border: 0; border-radius: 5px; background: linear-gradient(110deg, #4e914e, #39734a); color: #fff; font: inherit; font-weight: 800; cursor: pointer; transition: transform .2s ease, filter .2s ease; }
        button:hover { transform: translateY(-1px); filter: saturate(1.08); }
        a { display: inline-block; margin-top: 22px; color: #376a41; font-size: 13px; font-weight: 700; }
        .error { color: #9c452c; font-size: 13px; }
        @keyframes panel-in { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 480px) { main { padding: 28px 22px; } h1 { font-size: 30px; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; } }
        :root { --brand-primary: {{ $siteConfiguration['primary_color'] }}; --brand-primary-foreground: {{ $siteConfiguration['primary_foreground'] }}; color: #203a55; background: #d9eaff; }
        body { background: linear-gradient(125deg, color-mix(in srgb, var(--brand-primary), white 83%) 0%, #fff0bd 52%, color-mix(in srgb, var(--brand-primary), white 75%) 100%); }
        h1 { color: color-mix(in srgb, var(--brand-primary), #102f51 38%); }
        p, label { color: #526b83; }
        input { border-color: #b9cde0; color: #203a55; }
        input:focus { outline-color: color-mix(in srgb, var(--brand-primary), transparent 80%); border-color: var(--brand-primary); }
        button { background: linear-gradient(110deg, color-mix(in srgb, var(--brand-primary), #102f51 24%), var(--brand-primary)); box-shadow: inset 0 -3px 0 #e0bf49; color: var(--brand-primary-foreground); }
        a { color: var(--brand-primary); }
    </style>
</head>
<body class="{{ session()->has('status') || $errors->any() ? 'has-flash' : '' }}">
    @include('partials.flash-notice')
    <main>
        <div class="brand"><img src="{{ $siteConfiguration['logo_url'] }}" alt="Logo {{ $siteConfiguration['institution_name'] }}"><span>{{ $siteConfiguration['app_name'] }}</span></div>
        <h1>Lupa password?</h1>
        <p>Masukkan username, NIM, NIDN, atau kode asisten. Jika akun memiliki email terdaftar, instruksi reset akan dikirim.</p>
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <label for="login">Identitas akun</label>
            <input id="login" name="login" type="text" value="{{ old('login') }}" autocomplete="username" maxlength="100" required>
            @error('login') <p class="error" role="alert">{{ $message }}</p> @enderror
            <button type="submit">Kirim instruksi reset</button>
        </form>
        <a href="{{ route('login') }}">Kembali ke login</a>
    </main>
</body>
</html>