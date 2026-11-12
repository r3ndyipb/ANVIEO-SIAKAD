@php
    /** @var array<string, mixed> $siteConfiguration */
    $siteConfiguration = $siteConfiguration ?? [];
    /** @var array<string, mixed> $landingData */
    $landingData = $landingData ?? [];
    /** @var string $pageType */
    $pageType = $pageType ?? 'home';
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="description" content="siakad smartkampus.id — Sistem Informasi Akademik {{ $siteConfiguration['institution_name'] }} untuk layanan akademik, fakultas, dan program studi.">
    <meta name="keywords" content="siakad smartkampus.id, salkin siakad, Sistem Informasi Akademik, {{ $siteConfiguration['institution_name'] }}">
    <meta name="application-name" content="siakad smartkampus.id">
    <meta name="robots" content="index, follow">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="siakad smartkampus.id">
    <meta property="og:title" content="{{ $landingData['pageTitle'] }} | siakad smartkampus.id">
    <meta property="og:description" content="siakad smartkampus.id — Sistem Informasi Akademik {{ $siteConfiguration['institution_name'] }}.">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary">
    <meta name="theme-color" content="{{ $siteConfiguration['primary_color'] }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" type="image/png" href="{{ $siteConfiguration['logo_url'] }}">
    <link rel="shortcut icon" href="{{ $siteConfiguration['logo_url'] }}">
    <link rel="apple-touch-icon" href="{{ $siteConfiguration['logo_url'] }}">
    <title>{{ $landingData['pageTitle'] }} | siakad smartkampus.id</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,400&family=Karla:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: #0A2E52;
            --brand-2: #12467A;
            --brand-3: #071E38;
            --gold: #C9962E;
            --gold-2: #AC7F22;
            --gold-soft: #F0DFB4;
            --cream: #FBF8F1;
            --paper: #FFFFFF;
            --ink: #152232;
            --muted: #5C6B7D;
            --line: rgba(10, 46, 82, .14);
            --line-soft: rgba(10, 46, 82, .08);
            --radius: 6px;
            --maxw: 1240px;
            --serif: "Fraunces", Georgia, serif;
            --sans: "Karla", Helvetica, Arial, sans-serif;
            --brand-primary: {{ $siteConfiguration['primary_color'] }};
            --brand-primary-foreground: {{ $siteConfiguration['primary_foreground'] }};
        }
    </style>
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
            <div class="util">
                <div class="wrap">
                    <ul>
                        <li><a href="mailto:admin@aui.university">&#9993; admin@aui.university</a></li>
                        <li class="sep">|</li>
                        <li><a href="tel:+60165680906">&#9742; +60 165 680 906</a></li>
                        <li class="sep">|</li>
                        <li><a href="{{ route('login') }}">Portal AUI (Students)</a></li>
                    </ul>
                    <ul>
                        <li><a href="{{ route('public.faculties.index') }}">Fakultas</a></li>
                        <li><a href="{{ route('public.programs.index') }}">Program Studi</a></li>
                        <li><a href="{{ route('login') }}">LOGIN PORTAL</a></li>
                    </ul>
                </div>
            </div>
            <header class="public-header hdr">
                <div class="wrap">
                    <a class="public-brand logo brand-header-logo" href="{{ route('public.home') }}" aria-label="ASEAN University International — Home">
                        <img src="{{ $siteConfiguration['logo_url'] }}" alt="Logo ASEAN University International" class="brand-logo-img">
                        <div class="brand-text">
                            <span class="brand-title">
                                <span class="sr-only">ASEAN UNIVERSITU </span>
                                ASEAN UNIVERSITY
                            </span>
                            <div class="brand-subtitle" aria-label="INTERNATIONAL">
                                <span>I</span><span>N</span><span>T</span><span>E</span><span>R</span><span>N</span><span>A</span><span>T</span><span>I</span><span>O</span><span>N</span><span>A</span><span>L</span>
                            </div>
                        </div>
                    </a>
                    <nav class="public-nav nav" aria-label="Navigasi utama">
                        <a href="{{ route('public.home') }}" class="is-active">Home</a>
                        <a href="{{ route('public.about') }}">About</a>
                        <a href="{{ route('public.structure') }}">Structure</a>
                        <a href="{{ route('public.admissions') }}">Admissions</a>
                        <a href="{{ route('public.academic') }}">Academic</a>
                        <a href="{{ route('public.accreditation') }}">Accreditation</a>
                    </nav>
                    <div class="public-header-actions hdr-cta">
                        <details class="public-fallback-menu">
                            <summary aria-label="Buka menu"><span></span><span></span><span></span></summary>
                            <nav aria-label="Navigasi utama">
                                <a href="{{ route('public.home') }}">Home</a>
                                <a href="{{ route('public.about') }}">About</a>
                                <a href="{{ route('public.structure') }}">Structure</a>
                                <a href="{{ route('public.admissions') }}">Admissions</a>
                                <a href="{{ route('public.academic') }}">Academic</a>
                                <a href="{{ route('public.accreditation') }}">Accreditation</a>
                            </nav>
                        </details>
                        <a class="login-button btn btn-gold" href="{{ route('login') }}">LOGIN PORTAL <span aria-hidden="true">&#8599;</span></a>
                    </div>
                </div>
            </header>
            @if ($pageType === 'home')
                <section class="hero" aria-label="SIAKAD - Sistem Informasi Akademik">
                    <div class="hero-slide is-active" style="background-image: url('{{ $siteConfiguration['hero_images'][0] ?? '/img/Perayaan%20Wisuda%20di%20Bawah%20Menara%20Kota.png' }}')"></div>
                    <div class="hero-shade"></div>
                    <div class="wrap">
                        <div class="hero-copy">
                            <p class="hero-eyebrow eyebrow"><span></span>{{ $siteConfiguration['app_full_name'] }}</p>
                            <h1>{{ $siteConfiguration['motto'] }}</h1>
                            <div class="hero-actions hero-btns">
                                <a class="hero-primary btn btn-gold" href="#dashboard">Jelajahi dashboard <span aria-hidden="true">&#8595;</span></a>
                                <a class="hero-secondary btn btn-ghost" href="{{ route('public.faculties.index') }}">Lihat fakultas</a>
                            </div>
                        </div>
                    </div>
                </section>
            @else
                <div class="inner-hero"><div class="inner-hero-backdrop" style="--inner-hero-image: url('{{ $siteConfiguration['hero_images'][0] ?? '/img/Perayaan%20Wisuda%20di%20Bawah%20Menara%20Kota.png' }}')"></div><div class="inner-hero-copy"><span>{{ $siteConfiguration['app_full_name'] }}</span><h1>{{ $landingData['pageTitle'] }}</h1><p>{{ $landingData['scopeName'] }}</p></div></div>
            @endif
            <main id="dashboard" class="landing-main">
                <div class="dashboard-loading" role="status" aria-live="polite">
                    <span class="dashboard-loading-flash" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
                    <strong>Memuat ringkasan akademik&hellip;</strong>
                </div>
            </main>
            <footer class="public-footer ftr">
                <div class="wrap">
                    <div class="public-footer-inner ftr-btm">
                        <span class="footer-copyright">&copy; {{ date('Y') }} ASEAN University International. All Rights Reserved.</span>
                        <ul>
                            <li><a href="{{ route('public.privacy') }}">Privacy Policy</a></li>
                            <li><a href="{{ route('public.terms') }}">Terms of Use</a></li>
                            <li><a href="{{ route('public.accessibility') }}">Accessibility Statement</a></li>
                        </ul>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    <script id="public-landing-data" type="application/json">{!! json_encode($landingData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
</body>
</html>
