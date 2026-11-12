<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="description" content="Masuk ke akun portal belajar mahasiswa dan civitas akademika {{ $siteConfiguration['institution_name'] ?? 'ASEAN University International (AUI)' }} untuk mengakses silabus materi, jadwal kuliah, KRS, KHS, dan asisten AI.">
    <link rel="icon" type="image/png" href="{{ $siteConfiguration['logo_url'] }}">
    <link rel="shortcut icon" href="{{ $siteConfiguration['logo_url'] }}">
    <link rel="apple-touch-icon" href="{{ $siteConfiguration['logo_url'] }}">
    <title>Masuk ke Portal Belajar Mahasiswa — {{ $siteConfiguration['institution_name'] ?? 'ASEAN University International (AUI)' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,400;0,9..144,600;0,9..144,700;0,9..144,800;1,9..144,400&family=Karla:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand-primary: {{ $siteConfiguration['primary_color'] ?? '#0A2E52' }};
            --brand-primary-foreground: {{ $siteConfiguration['primary_foreground'] ?? '#FFFFFF' }};
        }
    </style>

    @vite('resources/css/portal-auth.css')

    <script>
        (function() {
            try {
                var initialized = localStorage.getItem('aui_portal_theme_init_v2');
                if (!initialized) {
                    localStorage.setItem('anvieo_theme', 'light');
                    localStorage.setItem('simak_theme', 'light');
                    localStorage.setItem('aui_portal_theme_init_v2', '1');
                }
                var savedTheme = localStorage.getItem('anvieo_theme') || localStorage.getItem('simak_theme');
                var active = savedTheme === 'dark' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', active);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
</head>
<body class="portal-body">
    @include('partials.flash-notice')

    <div class="portal-page-wrap">
        <!-- Top Auth Header -->
        <header class="portal-auth-header">
            <div class="blackbox-frame">
                <div class="portal-auth-header-inner">
                    <!-- Brand Logo & Badge Group -->
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <a href="{{ route('public.home') }}" class="portal-auth-brand-group brand-header-logo" aria-label="ASEAN University International — Home">
                            <img id="portal-brand-logo" src="{{ $siteConfiguration['preloader_logo_url'] ?? $siteConfiguration['logo_url'] }}" alt="Logo ASEAN University International" class="brand-logo-img portal-brand-logo" width="40" height="40" style="height: 40px; width: auto; max-height: 44px; object-fit: contain; display: block; flex-shrink: 0;" onerror="this.onerror=null; this.src='{{ asset('images/landing/icon.png') }}';">
                            <div class="brand-text portal-brand-text">
                                <span class="brand-title portal-brand-title">
                                    <span class="sr-only">ASEAN UNIVERSITU </span>
                                    ASEAN UNIVERSITY
                                </span>
                                <div class="brand-subtitle portal-brand-subtitle" aria-label="INTERNATIONAL">
                                    <span>I</span><span>N</span><span>T</span><span>E</span><span>R</span><span>N</span><span>A</span><span>T</span><span>I</span><span>O</span><span>N</span><span>A</span><span>L</span>
                                </div>
                            </div>
                        </a>

                        <span class="portal-brand-divider" aria-hidden="true"></span>

                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="portal-badge-portal">PORTAL SIAKAD</span>
                        </div>
                    </div>

                    <!-- Right Action Links & Theme Toggle -->
                    <div class="portal-header-actions">
                        <a href="{{ route('public.home') }}" class="portal-back-link" style="text-decoration: none;" title="Kembali ke Halaman Utama">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="15 18 9 12 15 6" />
                            </svg>
                            <span>Halaman Utama</span>
                        </a>

                        <button type="button" id="portal-theme-toggle-btn" class="portal-theme-btn" aria-label="Ganti Tema Warna" title="Beralih Tema">
                            <span id="theme-icon-moon" style="display: inline-flex;">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                                </svg>
                            </span>
                            <span id="theme-icon-sun" style="display: none;">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="12" cy="12" r="5" />
                                    <line x1="12" y1="1" x2="12" y2="3" />
                                    <line x1="12" y1="21" x2="12" y2="23" />
                                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
                                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
                                    <line x1="1" y1="12" x2="3" y2="12" />
                                    <line x1="21" y1="12" x2="23" y2="12" />
                                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
                                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
                                </svg>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Dual-Panel Auth Container -->
        <main class="portal-auth-container">
            <!-- Architectural Blackbox Rails -->
            <div class="blackbox-rails blackbox-rail" aria-hidden="true">
                <div style="position: relative; width: 100%; height: 100%; max-width: var(--bx-frame);">
                    <span class="blackbox-rail-hatch" style="position: absolute; left: 0; top: 0; bottom: 0; width: var(--bx-gutter);"></span>
                    <span class="blackbox-rail-hatch" style="position: absolute; right: 0; top: 0; bottom: 0; width: var(--bx-gutter);"></span>
                    <span class="blackbox-rail-v" style="position: absolute; left: 0; top: 0; bottom: 0; width: 1px;"></span>
                    <span class="blackbox-rail-v" style="position: absolute; left: var(--bx-gutter); top: 0; bottom: 0; width: 1px;"></span>
                    <span class="blackbox-rail-v" style="position: absolute; right: var(--bx-gutter); top: 0; bottom: 0; width: 1px;"></span>
                    <span class="blackbox-rail-v" style="position: absolute; right: 0; top: 0; bottom: 0; width: 1px;"></span>
                </div>
                <span class="blackbox-rail-h" style="position: absolute; left: 0; right: 0; bottom: 0; height: 1px;"></span>
                <span class="blackbox-rail-h" style="position: absolute; left: 0; right: 0; top: 0; height: 1px;"></span>
                <span style="position: absolute; inset: 0; display: flex; justify-content: center; pointer-events: none;">
                    <span style="position: relative; width: 100%; max-width: var(--bx-frame);">
                        <span class="blackbox-rail-cross" style="position: absolute; bottom: 0; left: 0;"></span>
                        <span class="blackbox-rail-cross" style="position: absolute; bottom: 0; left: var(--bx-gutter);"></span>
                        <span class="blackbox-rail-cross" style="position: absolute; bottom: 0; right: var(--bx-gutter);"></span>
                        <span class="blackbox-rail-cross" style="position: absolute; bottom: 0; right: 0;"></span>
                        <span class="blackbox-rail-cross" style="position: absolute; top: 0; left: 0;"></span>
                        <span class="blackbox-rail-cross" style="position: absolute; top: 0; left: var(--bx-gutter);"></span>
                        <span class="blackbox-rail-cross" style="position: absolute; top: 0; right: var(--bx-gutter);"></span>
                        <span class="blackbox-rail-cross" style="position: absolute; top: 0; right: 0;"></span>
                    </span>
                </span>
            </div>

            <div class="blackbox-frame">
                <div class="portal-auth-grid">
                    <!-- Left Column: Unboxed Editorial Showcase (AuthShowcase) -->
                    <div class="portal-showcase-clean">
                        <div>
                            <!-- Line Model Eyebrow (Model Garis AUI) -->
                            <div class="portal-showcase-line-label">
                                <span class="portal-gold-line" aria-hidden="true"></span>
                                <span>PORTAL AKADEMIK &amp; SISWA TERAKREDITASI</span>
                            </div>

                            <!-- Main Display Heading (Fraunces Serif) -->
                            <h1 class="portal-showcase-heading">
                                Eksplorasi Akademik, Event &amp; Prestasi Siswa
                            </h1>

                            <p class="portal-showcase-subtext">
                                Portal terpadu mahasiswa untuk mengakses silabus materi, jadwal perkuliahan, pengisian KRS, bimbingan dosen DPA, evaluasi kode 1-on-1, dan portofolio kompetensi berbobot internasional.
                            </p>

                            <!-- High-Impact Feature Highlights -->
                            <div class="portal-feature-list">
                                <div class="portal-feature-item">
                                    <div class="portal-feature-icon-box">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <rect x="4" y="4" width="16" height="16" rx="2" />
                                            <rect x="9" y="9" width="6" height="6" />
                                            <line x1="9" y1="1" x2="9" y2="4" />
                                            <line x1="15" y1="1" x2="15" y2="4" />
                                            <line x1="9" y1="20" x2="9" y2="23" />
                                            <line x1="15" y1="20" x2="15" y2="23" />
                                            <line x1="20" y1="9" x2="23" y2="9" />
                                            <line x1="20" y1="15" x2="23" y2="15" />
                                            <line x1="1" y1="9" x2="4" y2="9" />
                                            <line x1="1" y1="15" x2="4" y2="15" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="portal-feature-title">Perjalanan Akademik &amp; Socratic AI</div>
                                        <div class="portal-feature-desc">
                                            Bimbingan logika komputasi 24/7, bedah error interaktif, dan tracking progres belajar mandiri.
                                        </div>
                                    </div>
                                </div>

                                <div class="portal-feature-item">
                                    <div class="portal-feature-icon-box">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <circle cx="12" cy="8" r="7" />
                                            <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="portal-feature-title">Event, Workshop &amp; Evaluasi Kode 1x24 Jam</div>
                                        <div class="portal-feature-desc">
                                            Akses hackathon akademik berkala, webinar pakar industri, serta telaah tugas akhir per baris.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Trust & Social Proof Strip -->
                        <div class="portal-trust-card">
                            <div class="portal-testimonial-clean" id="portal-testimonial-box">
                                <img
                                    id="testi-avatar"
                                    src="{{ asset('alumni/alumni_1.jpg') }}"
                                    alt="Ahmad Fauzi"
                                    class="portal-testimonial-avatar"
                                >
                                <div style="flex: 1; min-width: 0;">
                                    <p class="portal-testimonial-quote" id="testi-quote">
                                        &ldquo;Review submission kode dari praktisi beneran detail per baris. Standar clean architecture terpakai langsung di kantor.&rdquo;
                                    </p>
                                    <div class="portal-testimonial-author" id="testi-author">
                                        <strong>Ahmad Fauzi</strong> · <span>Fullstack Engineer di Bukalapak</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Live Metrics Strip -->
                            <div class="portal-stats-row">
                                <div class="portal-stat-col">
                                    <span class="portal-stat-num">128,400+</span>
                                    <span class="portal-stat-label">Siswa Aktif</span>
                                </div>
                                <div class="portal-stat-col">
                                    <span class="portal-stat-num">350+</span>
                                    <span class="portal-stat-label">Event &amp; Workshop</span>
                                </div>
                                <div class="portal-stat-col">
                                    <span class="portal-stat-num">98.4%</span>
                                    <span class="portal-stat-label">Tingkat Lulus</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Constrained Login Card (LoginForm) -->
                    <div class="portal-card-wrap">
                        <!-- Navigation Tab (Masuk) -->
                        <div class="portal-nav-tabs">
                            <span class="portal-nav-tab active">
                                Masuk Portal
                            </span>
                        </div>

                        <!-- Error Message Banner -->
                        @if ($errors->any())
                            <div class="portal-error-banner" role="alert">
                                <span>⚠️</span>
                                <span>{{ $errors->first('login') ?: $errors->first() }}</span>
                            </div>
                        @endif

                        <!-- Main Login Form -->
                        <form method="POST" action="{{ route('login.store') }}" class="portal-form" id="portalLoginForm">
                            @csrf

                            <!-- Email / NIM Field -->
                            <div class="portal-form-group">
                                <label for="login" class="portal-label">
                                    <span>Email Akun atau NIM <span style="color: var(--gold)">*</span></span>
                                </label>
                                <div class="portal-input-wrap">
                                    <span class="portal-input-icon">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                            <polyline points="22,6 12,13 2,6" />
                                        </svg>
                                    </span>
                                    <input
                                        type="text"
                                        id="login"
                                        name="login"
                                        autoComplete="username"
                                        required
                                        placeholder="nama@student.aui.ac.id atau 2026110001"
                                        value="{{ (string) old('login') }}"
                                        class="portal-input"
                                        aria-describedby="login-error"
                                    >
                                </div>
                                @error('login')
                                    <span style="color: #DC2626; font-size: 0.75rem; margin-top: 2px;" id="login-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Password Field -->
                            <div class="portal-form-group">
                                <label for="password" class="portal-label">
                                    <span>Kata Sandi <span style="color: var(--gold)">*</span></span>
                                    <button
                                        type="button"
                                        id="open-forgot-modal-btn"
                                        class="portal-link-gold"
                                        style="background: none; border: none; cursor: pointer; padding: 0;"
                                    >
                                        Lupa Sandi?
                                    </button>
                                </label>
                                <div class="portal-input-wrap">
                                    <span class="portal-input-icon">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                        </svg>
                                    </span>
                                    <input
                                        type="password"
                                        id="password"
                                        name="password"
                                        autoComplete="current-password"
                                        required
                                        placeholder="••••••••••••"
                                        class="portal-input"
                                        aria-describedby="password-error"
                                    >
                                    <span class="portal-input-addon">
                                        <button
                                            type="button"
                                            id="portal-pw-toggle-btn"
                                            class="portal-pw-toggle"
                                            aria-label="Tampilkan kata sandi"
                                            title="Tampilkan kata sandi"
                                        >
                                            <span id="pw-icon-eye">
                                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                    <circle cx="12" cy="12" r="3" />
                                                </svg>
                                            </span>
                                            <span id="pw-icon-eye-off" style="display: none;">
                                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
                                                    <line x1="1" y1="1" x2="23" y2="23" />
                                                </svg>
                                            </span>
                                        </button>
                                    </span>
                                </div>
                                @error('password')
                                    <span style="color: #DC2626; font-size: 0.75rem; margin-top: 2px;" id="password-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Options Row (Remember Me) -->
                            <div class="portal-form-options">
                                <label class="portal-checkbox-label">
                                    <input
                                        type="checkbox"
                                        name="remember"
                                        id="remember"
                                        value="1"
                                        checked
                                    >
                                    <span>Ingat saya di perangkat ini</span>
                                </label>
                                <span style="font-size: 0.8125rem; color: var(--bx-muted-soft)">
                                    Sesi 30 Hari
                                </span>
                            </div>

                            <!-- Submit Button (Black) -->
                            <button
                                type="submit"
                                id="portal-submit-button"
                                class="portal-submit-btn portal-submit-btn-black"
                            >
                                <span id="submit-spinner" style="display: none; width: 16px; height: 16px; border: 2px solid #FFFFFF; border-top-color: transparent; border-radius: 50%; animation: spin 0.6s linear infinite;" aria-hidden="true"></span>
                                <span id="submit-text">MASUK KE PORTAL BELAJAR</span>
                                <svg id="submit-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <line x1="5" y1="12" x2="19" y2="12" />
                                    <polyline points="12 5 19 12 12 19" />
                                </svg>
                            </button>
                        </form>

                        <!-- SSO Divider -->
                        <div class="portal-divider">
                            <span>atau masuk dengan</span>
                        </div>

                        <!-- SSO Social Logins (Google) -->
                        <div class="portal-sso-single">
                            <button
                                type="button"
                                id="google-sso-btn"
                                class="portal-sso-btn portal-sso-btn-full"
                                title="Masuk dengan akun Google / Email Kampus"
                            >
                                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
                                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
                                </svg>
                                <span>Masuk dengan Akun Kampus / Google</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Minimal Bottom Bar (Copyright left, Terms/Privacy/Help right) -->
        <footer class="portal-bottom-bar" role="contentinfo">
            <div class="blackbox-frame">
                <div class="portal-bottom-bar-inner">
                    <!-- Left Corner: Copyright -->
                    <div class="portal-bottom-left">
                        <span>© 2026 {{ $siteConfiguration['institution_name'] ?? 'ASEAN University International (AUI)' }}. All rights reserved.</span>
                    </div>

                    <!-- Right Corner: Terms & Conditions, Privacy, Help -->
                    <div class="portal-bottom-right">
                        <button
                            type="button"
                            class="portal-bottom-link"
                            data-open-modal="terms"
                            title="Lihat Ketentuan Layanan"
                        >
                            Ketentuan Layanan
                        </button>
                        <span class="portal-bottom-dot" aria-hidden="true">•</span>
                        <button
                            type="button"
                            class="portal-bottom-link"
                            data-open-modal="privacy"
                            title="Lihat Kebijakan Privasi"
                        >
                            Kebijakan Privasi
                        </button>
                        <span class="portal-bottom-dot" aria-hidden="true">•</span>
                        <button
                            type="button"
                            class="portal-bottom-link"
                            data-open-modal="help"
                            title="Pusat Bantuan & Kontak"
                        >
                            Bantuan &amp; FAQ
                        </button>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- Modals Backdrop & Cards -->
    <!-- 1. Forgot Password Modal -->
    <div id="forgot-modal" class="blackbox-modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="forgot-modal-title">
        <div class="blackbox-modal-card" style="max-width: 440px;">
            <button type="button" class="blackbox-modal-close" data-close-modal="forgot-modal" aria-label="Tutup">×</button>

            <div style="text-align: center; margin-bottom: 18px;">
                <div style="width: 46px; height: 46px; margin: 0 auto 12px; background: rgba(201,150,46,0.12); border: 1px solid var(--gold); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 2l-2 2m-1.5 1.5L12 11l-4-4-6 6 8 8 6-6-4-4 5.5-5.5z" />
                        <circle cx="16.5" cy="7.5" r="1.5" />
                    </svg>
                </div>
                <h3 id="forgot-modal-title" style="font-family: var(--font-heading); font-size: 1.25rem; margin: 0 0 6px; color: var(--bx-ink-bright);">
                    Pemulihan Kata Sandi
                </h3>
                <p style="font-size: 0.875rem; color: var(--bx-muted); margin: 0;">
                    Masukkan alamat email atau identitas akun terdaftar untuk menerima tautan instruksi reset.
                </p>
            </div>

            <div id="forgot-form-view">
                <form method="POST" action="{{ route('password.email') }}" id="forgot-password-form" style="display: flex; flex-direction: column; gap: 14px;">
                    @csrf
                    <div>
                        <label for="forgot-login" style="display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 6px; color: var(--bx-ink);">
                            Email atau NIM Terdaftar
                        </label>
                        <input
                            type="text"
                            id="forgot-login"
                            name="login"
                            required
                            placeholder="nama@student.aui.ac.id atau 2026110001"
                            class="portal-input"
                            style="width: 100%;"
                        >
                    </div>

                    <button type="submit" class="portal-submit-btn" style="color: #0A2E52; font-weight: 700;">
                        KIRIM INSTRUKSI RESET →
                    </button>
                </form>

                <div style="text-align: center; margin-top: 14px;">
                    <a href="{{ route('password.request') }}" class="portal-link-gold" style="font-size: 0.8125rem;">
                        Buka Halaman Reset Mandiri SIAKAD →
                    </a>
                </div>
            </div>

            <div id="forgot-success-view" style="display: none; text-align: center; padding: 14px 0;">
                <div style="color: #10B981; margin-bottom: 8px; display: flex; justify-content: center;">
                    <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                        <polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                </div>
                <h4 style="font-size: 1.05rem; color: var(--bx-ink-bright); margin-bottom: 6px;">
                    Tautan Reset Berhasil Dikirim!
                </h4>
                <p style="font-size: 0.875rem; color: var(--bx-muted); margin-bottom: 16px;">
                    Silakan periksa folder kotak masuk atau spam pada alamat email yang terdaftar.
                </p>
                <button type="button" class="portal-submit-btn" data-close-modal="forgot-modal">
                    KEMBALI KE LOGIN
                </button>
            </div>
        </div>
    </div>

    <!-- 2. Terms of Service Modal -->
    <div id="terms-modal" class="blackbox-modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="terms-modal-title">
        <div class="blackbox-modal-card" style="max-width: 520px;">
            <button type="button" class="blackbox-modal-close" data-close-modal="terms-modal" aria-label="Tutup">×</button>

            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                </svg>
                <h3 id="terms-modal-title" style="font-family: var(--font-heading); font-size: 1.15rem; margin: 0; color: var(--bx-ink-bright);">
                    Ketentuan Layanan &amp; Etika Pembelajaran
                </h3>
            </div>
            <div style="max-height: 280px; overflow-y: auto; font-size: 0.8125rem; color: var(--bx-prose-ink); line-height: 1.6; padding-right: 8px;">
                <p style="margin: 0 0 10px 0;">
                    Selamat datang di <strong>Portal Terpadu {{ $siteConfiguration['institution_name'] ?? 'ASEAN University International (AUI)' }}</strong>. Dengan mengakses dan menggunakan platform ini, Anda menyetujui ketentuan berikut:
                </p>
                <ul style="padding-left: 18px; margin: 10px 0; display: flex; flex-direction: column; gap: 8px;">
                    <li><strong>Integritas Akademik &amp; Kode:</strong> Setiap tugas akhir, KRS, dan submission praktikum harus merupakan hasil pengerjaan orisinal.</li>
                    <li><strong>SLA Evaluasi Reviewer:</strong> Proyek dan tugas submission Anda dievaluasi oleh tim instruktur berlisensi dalam tenggat 1x24 jam kerja.</li>
                    <li><strong>Kerahasiaan Akun:</strong> Akun portal belajar ini bersifat personal dan tidak boleh dialihkan kepada pihak lain.</li>
                    <li><strong>Hak Cipta Modul:</strong> Materi silabus, modul RPS, dan aset ajar dilindungi hak kekayaan intelektual universitas.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- 3. Privacy Policy Modal -->
    <div id="privacy-modal" class="blackbox-modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="privacy-modal-title">
        <div class="blackbox-modal-card" style="max-width: 520px;">
            <button type="button" class="blackbox-modal-close" data-close-modal="privacy-modal" aria-label="Tutup">×</button>

            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
                <h3 id="privacy-modal-title" style="font-family: var(--font-heading); font-size: 1.15rem; margin: 0; color: var(--bx-ink-bright);">
                    Kebijakan Privasi &amp; Perlindungan Data
                </h3>
            </div>
            <div style="max-height: 280px; overflow-y: auto; font-size: 0.8125rem; color: var(--bx-prose-ink); line-height: 1.6; padding-right: 8px;">
                <p style="margin: 0 0 10px 0;">
                    Kami berkomitmen melindungi privasi data mahasiswa dan civitas akademika sesuai standar UU Pelindungan Data Pribadi (UU PDP):
                </p>
                <ul style="padding-left: 18px; margin: 10px 0; display: flex; flex-direction: column; gap: 8px;">
                    <li><strong>Data yang Diproses:</strong> Nama lengkap, NIK, NIM/NIDN, riwayat akademik, log kehadiran, dan rekaman evaluasi.</li>
                    <li><strong>Keamanan Transmisi:</strong> Seluruh komunikasi client-server dienkripsi menggunakan protokol TLS 1.3 dan hashing argon2id / bcrypt.</li>
                    <li><strong>Integrasi PDDIKTI:</strong> Data pelaporan aktivitas perkuliahan disinkronisasi langsung ke sistem resmi Kemendikbudristek Neo Feeder.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- 4. Help & FAQ Modal -->
    <div id="help-modal" class="blackbox-modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="help-modal-title">
        <div class="blackbox-modal-card" style="max-width: 520px;">
            <button type="button" class="blackbox-modal-close" data-close-modal="help-modal" aria-label="Tutup">×</button>

            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" />
                    <line x1="12" y1="17" x2="12.01" y2="17" />
                </svg>
                <h3 id="help-modal-title" style="font-family: var(--font-heading); font-size: 1.15rem; margin: 0; color: var(--bx-ink-bright);">
                    Pusat Bantuan &amp; Layanan BAAK
                </h3>
            </div>
            <div style="max-height: 280px; overflow-y: auto; font-size: 0.8125rem; color: var(--bx-prose-ink); line-height: 1.6; padding-right: 8px;">
                <p style="margin: 0 0 10px 0;">
                    Mengalami kendala saat login atau membutuhkan reset akun? Hubungi unit layanan kami:
                </p>
                <div style="background: var(--bx-bg); border: 1px solid var(--bx-border); border-radius: 6px; padding: 12px; margin-bottom: 12px;">
                    <div style="font-weight: 700; color: var(--bx-ink-bright); margin-bottom: 4px;">Biro Administrasi Akademik (BAAK)</div>
                    <div>Email: <a href="mailto:baak@aui.ac.id" class="portal-link-gold">baak@aui.ac.id</a></div>
                    <div>WhatsApp Helpdesk: <a href="https://wa.me/60165680906" class="portal-link-gold">+60 165 680 906</a></div>
                    <div style="font-size: 0.75rem; color: var(--bx-muted); margin-top: 4px;">Jam Layanan: Senin – Jumat, 08.00 – 17.00 WIB</div>
                </div>
                <div style="background: var(--bx-bg); border: 1px solid var(--bx-border); border-radius: 6px; padding: 12px;">
                    <div style="font-weight: 700; color: var(--bx-ink-bright); margin-bottom: 4px;">Pusat Data &amp; IT Support SIAKAD</div>
                    <div>Email: <a href="mailto:admin@aui.university" class="portal-link-gold">admin@aui.university</a></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Client-side Interactive JavaScript -->
    <script>
        (function() {
            // Testimonials Rotation
            var TESTIMONIALS = [
                {
                    avatar: "{{ asset('alumni/alumni_1.jpg') }}",
                    name: "Ahmad Fauzi",
                    role: "Fullstack Engineer di Bukalapak",
                    quote: "Review submission kode dari praktisi beneran detail per baris. Standar clean architecture terpakai langsung di kantor."
                },
                {
                    avatar: "{{ asset('alumni/alumni_2.jpg') }}",
                    name: "Siti Rahmawati",
                    role: "Cloud Architect di Tokopedia",
                    quote: "Modul industri dan Socratic AI membantuku memecahkan error arsitektur sistem tanpa harus macet berhari-hari."
                },
                {
                    avatar: "{{ asset('alumni/alumni_3.jpg') }}",
                    name: "Budi Santoso",
                    role: "AI Specialist di Telkom",
                    quote: "Sertifikasi dan portofolio proyek di Anvieo diakui langsung saat interview teknis. Transisi karir sangat mulus."
                }
            ];

            var activeTestiIdx = 0;
            var testiAvatar = document.getElementById('testi-avatar');
            var testiQuote = document.getElementById('testi-quote');
            var testiAuthor = document.getElementById('testi-author');
            var testiBox = document.getElementById('portal-testimonial-box');

            if (testiBox && testiAvatar && testiQuote && testiAuthor) {
                setInterval(function() {
                    activeTestiIdx = (activeTestiIdx + 1) % TESTIMONIALS.length;
                    var t = TESTIMONIALS[activeTestiIdx];
                    testiBox.style.opacity = '0';
                    testiBox.style.transition = 'opacity 0.25s ease';
                    setTimeout(function() {
                        testiAvatar.src = t.avatar;
                        testiAvatar.alt = t.name;
                        testiQuote.innerHTML = '&ldquo;' + t.quote + '&rdquo;';
                        testiAuthor.innerHTML = '<strong>' + t.name + '</strong> · <span>' + t.role + '</span>';
                        testiBox.style.opacity = '1';
                    }, 250);
                }, 6000);
            }

            // Theme Toggle
            var themeBtn = document.getElementById('portal-theme-toggle-btn');
            var iconMoon = document.getElementById('theme-icon-moon');
            var iconSun = document.getElementById('theme-icon-sun');

            function applyTheme(theme) {
                document.documentElement.setAttribute('data-theme', theme);
                localStorage.setItem('anvieo_theme', theme);
                localStorage.setItem('simak_theme', theme);
                if (theme === 'dark') {
                    if (iconMoon) iconMoon.style.display = 'none';
                    if (iconSun) iconSun.style.display = 'inline-flex';
                } else {
                    if (iconMoon) iconMoon.style.display = 'inline-flex';
                    if (iconSun) iconSun.style.display = 'none';
                }
            }

            var currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            applyTheme(currentTheme);

            if (themeBtn) {
                themeBtn.addEventListener('click', function() {
                    var cur = document.documentElement.getAttribute('data-theme') || 'light';
                    var next = cur === 'dark' ? 'light' : 'dark';
                    applyTheme(next);
                });
            }

            // Password Visibility Toggle
            var pwToggleBtn = document.getElementById('portal-pw-toggle-btn');
            var pwInput = document.getElementById('password');
            var pwEye = document.getElementById('pw-icon-eye');
            var pwEyeOff = document.getElementById('pw-icon-eye-off');

            if (pwToggleBtn && pwInput) {
                pwToggleBtn.addEventListener('click', function() {
                    if (pwInput.type === 'password') {
                        pwInput.type = 'text';
                        if (pwEye) pwEye.style.display = 'none';
                        if (pwEyeOff) pwEyeOff.style.display = 'inline-flex';
                        pwToggleBtn.setAttribute('aria-label', 'Sembunyikan kata sandi');
                    } else {
                        pwInput.type = 'password';
                        if (pwEye) pwEye.style.display = 'inline-flex';
                        if (pwEyeOff) pwEyeOff.style.display = 'none';
                        pwToggleBtn.setAttribute('aria-label', 'Tampilkan kata sandi');
                    }
                });
            }

            // Form Submit Loading Feedback
            var form = document.getElementById('portalLoginForm');
            var submitBtn = document.getElementById('portal-submit-button');
            var submitText = document.getElementById('submit-text');
            var submitSpinner = document.getElementById('submit-spinner');
            var submitArrow = document.getElementById('submit-arrow');

            if (form && submitBtn) {
                form.addEventListener('submit', function() {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.85';
                    submitBtn.style.cursor = 'wait';
                    if (submitSpinner) submitSpinner.style.display = 'inline-block';
                    if (submitArrow) submitArrow.style.display = 'none';
                    if (submitText) submitText.textContent = 'MEMVERIFIKASI KREDENSIAL...';
                });
            }

            var loginInput = document.getElementById('login');

            var googleSsoBtn = document.getElementById('google-sso-btn');
            if (googleSsoBtn) {
                googleSsoBtn.addEventListener('click', function() {
                    alert('Layanan Single Sign-On (SSO) Akun Kampus / Google sedang disiapkan oleh administrator.');
                });
            }

            // Modal Handlers
            function openModal(id) {
                var el = document.getElementById(id);
                if (el) {
                    el.style.display = 'flex';
                }
            }

            function closeModal(id) {
                var el = document.getElementById(id);
                if (el) {
                    el.style.display = 'none';
                }
            }

            // Open Forgot Modal
            var openForgotBtn = document.getElementById('open-forgot-modal-btn');
            if (openForgotBtn) {
                openForgotBtn.addEventListener('click', function() {
                    openModal('forgot-modal');
                    var forgotLoginInput = document.getElementById('forgot-login');
                    if (forgotLoginInput && loginInput && loginInput.value) {
                        forgotLoginInput.value = loginInput.value;
                    }
                });
            }

            // Open other modals via data attributes
            document.querySelectorAll('[data-open-modal]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var mType = btn.getAttribute('data-open-modal');
                    openModal(mType + '-modal');
                });
            });

            // Close modals via data attributes
            document.querySelectorAll('[data-close-modal]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var mId = btn.getAttribute('data-close-modal');
                    closeModal(mId);
                });
            });

            // Close modal on backdrop click
            document.querySelectorAll('.blackbox-modal-backdrop').forEach(function(bd) {
                bd.addEventListener('click', function(e) {
                    if (e.target === bd) {
                        bd.style.display = 'none';
                    }
                });
            });

            // Close on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.blackbox-modal-backdrop').forEach(function(bd) {
                        bd.style.display = 'none';
                    });
                }
            });
        })();
    </script>
</body>
</html>
