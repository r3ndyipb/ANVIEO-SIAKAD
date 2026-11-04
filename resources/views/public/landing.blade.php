<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="description" content="SIAKAD Salkin Lutfi — Sistem Informasi Akademik {{ $siteConfiguration['institution_name'] }} untuk layanan akademik, fakultas, dan program studi.">
    <meta name="keywords" content="SIAKAD Salkin Lutfi, Siakad Salkin, Sistem Informasi Akademik, {{ $siteConfiguration['institution_name'] }}">
    <meta name="application-name" content="SIAKAD Salkin Lutfi">
    <meta name="robots" content="index, follow">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="SIAKAD Salkin Lutfi">
    <meta property="og:title" content="{{ $landingData['pageTitle'] }} | SIAKAD Salkin Lutfi">
    <meta property="og:description" content="SIAKAD Salkin Lutfi — Sistem Informasi Akademik {{ $siteConfiguration['institution_name'] }}.">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary">
    <meta name="theme-color" content="{{ $siteConfiguration['primary_color'] }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" type="image/png" href="{{ $siteConfiguration['logo_url'] }}">
    <title>{{ $landingData['pageTitle'] }} | SIAKAD Salkin Lutfi</title>
    <style>:root { --brand-primary: {{ $siteConfiguration['primary_color'] }}; --brand-primary-foreground: {{ $siteConfiguration['primary_foreground'] }}; }</style>
    @vite('resources/js/public-landing.jsx')
</head>
<body class="public-site">
    @include('partials.flash-notice')
    <div class="simak-page-loader" data-page-loader role="status" aria-live="polite" aria-label="loading..." aria-hidden="true" hidden>
        <span class="simak-page-loader-mark"><img src="{{ $siteConfiguration['preloader_logo_url'] }}" alt=""><span class="simak-page-loader-orbit" aria-hidden="true"></span></span>
        <span class="simak-page-loader-label">loading<span class="simak-page-loader-dots"></span></span>
    </div>
    <div id="public-landing" data-page-type="{{ $pageType }}">
        <div class="public-page" data-landing-fallback>
            <header class="public-header">
                <a class="public-brand" href="{{ route('public.home') }}"><img src="{{ $siteConfiguration['logo_url'] }}" alt=""><span><strong>{{ $siteConfiguration['app_name'] }}</strong><small>{{ $siteConfiguration['app_full_name'] }}</small></span></a>
                <nav class="public-nav" aria-label="Navigasi utama"><a href="{{ route('public.home') }}">Dashboard</a><a href="{{ route('public.faculties.index') }}">Fakultas</a><a href="{{ route('public.programs.index') }}">Program Studi</a></nav>
                <div class="public-header-actions">
                    <details class="public-fallback-menu">
                        <summary aria-label="Buka menu"><span></span><span></span><span></span></summary>
                        <nav aria-label="Navigasi utama"><a href="{{ route('public.home') }}">Dashboard</a><a href="{{ route('public.faculties.index') }}">Fakultas</a><a href="{{ route('public.programs.index') }}">Program Studi</a></nav>
                    </details>
                    <a class="login-button" href="{{ route('login') }}">Login SIAKAD <span aria-hidden="true">↗</span></a>
                </div>
            </header>
            @if ($pageType === 'home')
                <section class="hero" aria-label="SIAKAD - Sistem Informasi Akademik">
                    <div class="hero-slide is-active" style="background-image: url('{{ $siteConfiguration['hero_images'][0] }}')"></div><div class="hero-shade"></div>
                    <div class="hero-copy"><p class="hero-eyebrow"><span></span>{{ $siteConfiguration['app_full_name'] }}</p><h1>{{ $siteConfiguration['motto'] }}</h1><div class="hero-actions"><a class="hero-primary" href="#dashboard">Jelajahi dashboard <span aria-hidden="true">↓</span></a><a class="hero-secondary" href="{{ route('public.faculties.index') }}">Lihat fakultas</a></div></div>
                </section>
            @else
                <div class="inner-hero"><div class="inner-hero-backdrop" style="--inner-hero-image: url('{{ $siteConfiguration['hero_images'][0] }}')"></div><div class="inner-hero-copy"><span>{{ $siteConfiguration['app_full_name'] }}</span><h1>{{ $landingData['pageTitle'] }}</h1><p>{{ $landingData['scopeName'] }}</p></div></div>
            @endif
            <main id="dashboard" class="landing-main"><div class="dashboard-loading" role="status" aria-live="polite"><span class="dashboard-loading-flash" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span><strong>Memuat ringkasan akademik…</strong></div></main>
                    <footer class="public-footer"><div class="public-footer-inner"><span class="footer-copyright">© {{ date('Y') }} {{ $siteConfiguration['institution_name'] }} · SIAKAD · Created by <a href="https://www.facebook.com/Salkin.Luthfi" target="_blank" rel="noopener noreferrer">Salkin</a></span></div></footer>
                </div>
    </div>
    <script id="public-landing-data" type="application/json">{!! json_encode($landingData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
</body>
</html>
