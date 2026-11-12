<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="application-name" content="{{ config('app.name', 'siakad AUI') }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Early Theme Setter: Default Light Theme (FOUC Prevention) -->
    <script>
        (function() {
            try {
                if (!localStorage.getItem('aui_simak_theme_default_light_v1')) {
                    localStorage.setItem('simak_theme', 'light');
                    localStorage.setItem('anvieo_theme', 'light');
                    localStorage.setItem('aui_simak_theme_default_light_v1', '1');
                }
                var savedTheme = localStorage.getItem('simak_theme') || localStorage.getItem('anvieo_theme');
                if (savedTheme === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    document.documentElement.setAttribute('data-theme', 'light');
                    localStorage.setItem('simak_theme', 'light');
                    localStorage.setItem('anvieo_theme', 'light');
                }
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
        window.simakLocale = @json(app()->getLocale());
        window.simakTranslationsUrl = @json(route('locale.translations', ['v' => md5_file(lang_path('en/ui.php'))]));
    </script>

    <link rel="icon" type="image/png" href="{{ $siteConfiguration['logo_url'] }}">
    <link rel="shortcut icon" href="{{ $siteConfiguration['logo_url'] }}">
    <link rel="apple-touch-icon" href="{{ $siteConfiguration['logo_url'] }}">
    <title>@yield('title', $siteConfiguration['app_name'])</title>

    <!-- Google Fonts: Fraunces, Karla, JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;0,9..144,800;1,9..144,400;1,9..144,600;1,9..144,700&family=Karla:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400;1,600&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    @stack('styles')
    @vite(['resources/css/app.css', 'resources/css/simak-theme.css', 'resources/js/app.js'])

    <style>
        :root {
            --brand-primary: {{ $siteConfiguration['primary_color'] }};
            --brand-primary-foreground: {{ $siteConfiguration['primary_foreground'] }};
        }
        /* Specific page components styling in line with AUI design system */
        .dt-container { color: var(--bx-ink); font: inherit; font-size: 11px; }
        .dt-layout-row { display: flex; align-items: center; gap: 12px; margin: 0 0 12px !important; }
        .dt-layout-cell { min-width: 0; }
        .dt-container .dt-search, .dt-container .dt-length { display: flex; align-items: center; gap: 8px; color: var(--bx-muted); font-size: 10px; }
        .dt-container .dt-search input, .dt-container .dt-length select { min-height: 34px; margin: 0; padding: 0 10px; border: 1px solid var(--bx-border); border-radius: 6px; background: var(--bx-surface); color: var(--bx-ink); font: inherit; font-size: 11px; outline: none; }
        .dt-container .dt-search input:focus, .dt-container .dt-length select:focus { border-color: var(--gold); }
        .dt-container .dt-search input { width: min(270px, 42vw); }
        .dt-buttons { display: flex; flex-wrap: wrap; gap: 7px; }
        .dt-container .dt-button.dt-export-button { display: inline-flex; min-height: 34px; align-items: center; gap: 7px; padding: 0 11px; border: 1px solid var(--bx-border); border-radius: 6px; background: var(--bx-surface); color: var(--brand); font: inherit; font-size: 10px; font-weight: 700; box-shadow: none; cursor: pointer; transition: all .15s ease; }
        .dt-container .dt-button.dt-export-button:hover { border-color: var(--gold); color: var(--gold); background: var(--gold-soft); }
        [data-theme="dark"] .dt-container .dt-button.dt-export-button { color: var(--gold); }
        .dt-export-button svg { width: 14px; height: 14px; }
        .dt-container .dt-info { padding-top: 0; color: var(--bx-muted); font-size: 10px; }
        .dt-container .dt-paging .dt-paging-button { min-width: 30px; min-height: 28px; margin-left: 4px; padding: 0 8px; border: 1px solid var(--bx-border); border-radius: 5px; background: var(--bx-surface); color: var(--bx-ink) !important; font-size: 10px; box-shadow: none; cursor: pointer; }
        .dt-container .dt-paging .dt-paging-button.current { border-color: var(--gold); background: var(--gold-soft); color: var(--gold-2) !important; font-weight: 700; }
        [data-theme="dark"] .dt-container .dt-paging .dt-paging-button.current { color: var(--gold) !important; }
        .table-wrap table.dataTable thead th { border-bottom: 1px solid var(--bx-border); background: var(--bx-bg); color: var(--bx-muted); font-size: 10px; text-transform: uppercase; }
        .table-wrap table.dataTable tbody td { border-bottom: 1px solid var(--bx-border); color: var(--bx-ink); }
        .table-icon-button { display: inline-grid; width: 32px; height: 32px; place-items: center; border: 1px solid var(--bx-border); border-radius: 6px; background: var(--bx-surface); color: var(--bx-ink); text-decoration: none; cursor: pointer; transition: all .15s ease; }
        .table-icon-button:hover { border-color: var(--gold); color: var(--gold); transform: translateY(-1px); }
        .table-icon-button.activate { border-color: var(--success); color: var(--success); background: var(--success-bg); }
        .table-icon-button.reset { border-color: var(--danger); color: var(--danger); background: var(--danger-bg); }
        .table-icon-button svg { width: 15px; height: 15px; }
        .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; }

        /* Profile Modal */
        .account-profile-modal-backdrop { position: fixed; inset: 0; z-index: 120; display: grid; overflow-y: auto; padding: clamp(20px, 6vh, 60px) 20px 20px; place-items: start center; background: rgba(11, 15, 23, .65); backdrop-filter: blur(3px); }
        .account-profile-modal { width: min(100%, 600px); max-height: calc(100dvh - 80px); overflow-y: auto; padding: 24px; border: 1px solid var(--bx-border); border-radius: 12px; background: var(--bx-surface); color: var(--bx-ink); box-shadow: var(--shadow-lg); }
        .account-profile-modal-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 18px; border-bottom: 1px solid var(--bx-border); padding-bottom: 14px; }
        .account-profile-modal-head h2 { margin: 0; font-family: var(--font-heading); font-size: 1.25rem; font-weight: 700; color: var(--brand); }
        [data-theme="dark"] .account-profile-modal-head h2 { color: #FFFFFF; }
        .account-profile-modal-head p { margin: 4px 0 0; font-size: 0.78rem; color: var(--bx-muted); }
        .account-profile-modal-close { width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--bx-border); background: var(--bx-bg); color: var(--bx-muted); display: grid; place-items: center; cursor: pointer; transition: all 0.15s ease; }
        .account-profile-modal-close:hover { border-color: var(--gold); color: var(--gold); }
    </style>
</head>
<body data-route-name="{{ request()->route()?->getName() }}" data-user-id="{{ auth()->id() }}">
    <div class="simak-page-loader" data-page-loader role="status" aria-live="polite" aria-label="loading..." aria-hidden="true" hidden>
        <span class="simak-page-loader-mark"><img src="{{ $siteConfiguration['preloader_logo_url'] }}" alt="" onerror="this.onerror=null; this.src='{{ asset('images/landing/icon.png') }}';"><span class="simak-page-loader-orbit" aria-hidden="true"></span></span>
        <span class="simak-page-loader-label">loading<span class="simak-page-loader-dots"></span></span>
    </div>

    @php
        $currentUser = auth()->user();
        $currentRole = (string) $currentUser->role;
        $accountType = isset($currentUser->legacy_type) ? (string) $currentUser->legacy_type : 'admin';
        $accountLogin = (string) (data_get($currentUser, 'login') ?? data_get($currentUser, 'legacy_id') ?? '');
        $degreeTrack = in_array($currentRole, ['mahasiswa', 'userprodi', 'usertu'], true) ? '' : null;
        if ($currentRole === 'mahasiswa') {
            try {
                $degreeTrack = \Illuminate\Support\Facades\DB::connection('simak_main')
                    ->table('s1m4k_mastermhs as student')
                    ->join('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
                    ->where('student.id_mahasiswa', (string) data_get($currentUser, 'legacy_id'))
                    ->value('program.nama_jenjang_pendidikan');
            } catch (\Throwable) {
                $degreeTrack = null;
            }
        } elseif (in_array($currentRole, ['userprodi', 'usertu'], true)) {
            try {
                $degreeTrack = \Illuminate\Support\Facades\DB::connection('simak_main')
                    ->table('s1m4k_masterprodi')
                    ->where('id_prodi', (string) data_get($currentUser, 'id_prodi'))
                    ->value('nama_jenjang_pendidikan');
            } catch (\Throwable) {
                $degreeTrack = null;
            }
        }
        $degreeTrack = $degreeTrack === null ? null : strtoupper(trim((string) $degreeTrack));
        $roleLabel = app()->getLocale() === 'en'
            ? trans('ui.roles.'.$currentRole, [], 'en')
            : config('simak.role_labels.'.$currentRole, ucfirst($currentRole));

        // Helper to recursively filter items based on degree track
        $filterDegreeItems = static function (array $items) use ($degreeTrack, &$filterDegreeItems): array {
            $filtered = [];
            foreach ($items as $item) {
                if (!is_array($item)) continue;
                $route = (string) ($item['route'] ?? '');
                if (str_starts_with($route, 'theses.') && !($degreeTrack === null || in_array($degreeTrack, ['S1', 'D3'], true))) {
                    continue;
                }
                if (str_starts_with($route, 'graduate_theses.') && !($degreeTrack === null || in_array($degreeTrack, ['S2', 'S3'], true))) {
                    continue;
                }
                if (!empty($item['items']) && is_array($item['items'])) {
                    $item['items'] = $filterDegreeItems($item['items']);
                }
                if (!empty($item['children']) && is_array($item['children'])) {
                    $item['children'] = $filterDegreeItems($item['children']);
                }
                $filtered[] = $item;
            }
            return array_values($filtered);
        };

        // Group navigation items
        $menuGroups = collect(config('simak.navigation.'.$currentRole, []))
            ->map(function (array $group) use ($filterDegreeItems): array {
                $group['items'] = $filterDegreeItems($group['items'] ?? []);
                return $group;
            })
            ->filter(static fn (array $group): bool => !empty($group['items']))
            ->values()
            ->all();

        // Helper to check if an item or any of its descendants is currently active
        $isItemActive = static function (array $item) use (&$isItemActive): bool {
            $route = (string) ($item['route'] ?? '');
            if ($route !== '') {
                if (request()->routeIs($route)) {
                    return true;
                }
                if (str_ends_with($route, '.index')) {
                    $prefix = substr($route, 0, -6);
                    if (request()->routeIs($prefix . '.*')) {
                        return true;
                    }
                }
            }
            $children = !empty($item['items']) && is_array($item['items'])
                ? $item['items']
                : (!empty($item['children']) && is_array($item['children']) ? $item['children'] : []);
            foreach ($children as $child) {
                if (is_array($child) && $isItemActive($child)) {
                    return true;
                }
            }
            return false;
        };

        $accountEmail = isset($currentUser->email) ? (string) $currentUser->email : '';
        $accountIdentifierLabel = match ($accountType) {
            'dosen' => 'NIDN',
            'asdos' => 'Kode asisten',
            'mahasiswa' => 'NIM',
            default => 'Username',
        };
        $accountName = (string) (data_get($currentUser, 'name') ?: $accountLogin ?: 'Pengguna SIAKAD');
        $splitNames = preg_split('/\s+/', trim((string) $accountName));
        $accountInitials = collect(is_array($splitNames) ? $splitNames : [])
            ->filter()->take(2)->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');

        // Domain category metadata helper
        $getCategoryMeta = function(string $label) {
            return match($label) {
                'Dashboard' => ['badge' => 'Core', 'icon' => 'layout-dashboard', 'roles' => 'ALL,ADMIN,BAAK,BAUK,PRODI,DOSEN,REVIEWER'],
                'Data Master' => ['badge' => 'PDDIKTI', 'icon' => 'database', 'roles' => 'ALL,ADMIN,BAAK,PRODI'],
                'Akademik' => ['badge' => 'KRS', 'icon' => 'graduation-cap', 'roles' => 'ALL,ADMIN,BAAK,DOSEN,PRODI'],
                'Semester Pendek' => ['badge' => 'SP', 'icon' => 'calendar-days', 'roles' => 'ALL,ADMIN,BAAK,DOSEN,PRODI'],
                'LMS Virtual Class' => ['badge' => 'LMS', 'icon' => 'book-open-check', 'roles' => 'ALL,ADMIN,DOSEN,REVIEWER,PRODI'],
                'E-Skripsi' => ['badge' => 'Skripsi', 'icon' => 'scroll-text', 'roles' => 'ALL,ADMIN,BAAK,DOSEN,REVIEWER,PRODI'],
                'E-Tesis & Disertasi' => ['badge' => 'Tesis', 'icon' => 'award', 'roles' => 'ALL,ADMIN,BAAK,DOSEN,REVIEWER,PRODI'],
                'Keuangan' => ['badge' => 'Snap', 'icon' => 'wallet-cards', 'roles' => 'ALL,ADMIN,BAUK'],
                'Kuliah Kerja Nyata (KKN)' => ['badge' => 'LPPM', 'icon' => 'map-pinned', 'roles' => 'ALL,ADMIN,BAAK'],
                'Validasi Nilai' => ['badge' => 'Review', 'icon' => 'clipboard-check', 'roles' => 'ALL,ADMIN,REVIEWER,BAAK'],
                'Data Wisuda', 'Wisuda' => ['badge' => 'Wisuda', 'icon' => 'award', 'roles' => 'ALL,ADMIN,BAAK'],
                'Administrasi & Tools' => ['badge' => 'SecOps', 'icon' => 'settings', 'roles' => 'ALL,ADMIN'],
                'Evaluasi' => ['badge' => 'EDOM', 'icon' => 'chart-no-axes-column-increasing', 'roles' => 'ALL,ADMIN,DOSEN,PRODI'],
                'Layanan Mahasiswa' => ['badge' => 'Mhs', 'icon' => 'heart-handshake', 'roles' => 'ALL,BAAK,PRODI'],
                'Layanan Prodi' => ['badge' => 'Prodi', 'icon' => 'building-2', 'roles' => 'ALL,ADMIN,PRODI'],
                default => ['badge' => 'Modul', 'icon' => 'folder', 'roles' => 'ALL,ADMIN,BAAK'],
            };
        };

        try {
            $sidebarFaculties = \Illuminate\Support\Facades\DB::connection('simak_main')
                ->table('s1m4k_masterprodi as p')
                ->leftJoin('s1m4k_masterfakultas as f', 'p.id_fakultas', '=', 'f.id_fakultas')
                ->select([
                    'p.id_prodi',
                    'p.nama_program_studi',
                    'p.nama_jenjang_pendidikan',
                    'f.id_fakultas',
                    'f.nama_fakultas',
                ])
                ->where('p.status', 'A')
                ->whereNotNull('p.nama_program_studi')
                ->whereRaw("TRIM(p.nama_program_studi) <> ''")
                ->orderBy('f.nama_fakultas')
                ->orderBy('p.nama_program_studi')
                ->get()
                ->groupBy(function ($item) {
                    return $item->nama_fakultas ?: 'Fakultas Lainnya';
                });
        } catch (\Throwable $e) {
            $sidebarFaculties = collect();
        }
    @endphp

    <div class="portal-app-layout" id="simak-shell">
        <!-- ========================================================= -->
        <!-- TOP AUTHENTICATED STICKY HEADER (58px, GLASS BLUR)        -->
        <!-- ========================================================= -->
        <header class="portal-auth-header">
            <div class="portal-header-fluid">
                <!-- Left: Sidebar Toggle & Brand Group -->
                <div class="portal-header-left">
                    <button type="button" class="portal-header-sidebar-toggle" id="sidebar-toggle-btn" aria-label="Toggle Side Menu" title="Perkecil / Perluas Menu">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="3" y1="12" x2="21" y2="12"></line>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <line x1="3" y1="18" x2="21" y2="18"></line>
                        </svg>
                    </button>

                    <a href="{{ route('dashboard') }}" class="portal-auth-brand-group">
                        <img src="{{ $siteConfiguration['preloader_logo_url'] }}" class="portal-brand-logo" alt="{{ $siteConfiguration['institution_name'] }}" width="34" height="34" onerror="this.onerror=null; this.src='{{ asset('images/landing/icon.png') }}';">
                        <div class="portal-brand-text">
                            <span class="portal-brand-title">ASEAN UNIVERSITY</span>
                            <div class="portal-brand-subtitle" aria-label="INTERNATIONAL">
                                <span>I</span><span>N</span><span>T</span><span>E</span><span>R</span><span>N</span><span>A</span><span>T</span><span>I</span><span>O</span><span>N</span><span>A</span><span>L</span>
                            </div>
                        </div>
                        <span class="portal-brand-divider" aria-hidden="true"></span>
                        <span class="portal-header-plain-title">PORTAL</span>
                    </a>
                </div>

                <!-- Right: Header Actions (Notifs, Theme, User) -->
                <div class="portal-header-actions">

                    <!-- Notification Center -->
                    <div class="notification-center"
                        data-notification-center
                        data-notification-label="Notifikasi SIMAK"
                        data-empty-message="Belum ada notifikasi SIMAK"
                        data-notifications-url="{{ route('theses.notifications.index') }}"
                        data-mark-all-read-url="{{ route('theses.notifications.read-all') }}"
                        data-mark-read-url-template="{{ route('theses.notifications.read', ['notification' => '__NOTIFICATION__']) }}"
                        style="position: relative;">
                        <button class="portal-header-icon-btn notification-trigger" type="button" data-notification-trigger aria-expanded="false" aria-controls="simak-notification-panel" aria-label="Notifikasi SIMAK" title="Notifikasi SIMAK">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                            <span class="portal-header-badge-count notification-count" data-notification-count hidden></span>
                        </button>
                        <section class="notification-panel" id="simak-notification-panel" data-notification-panel hidden aria-label="Notifikasi aplikasi" style="right: 0;">
                            <header class="notification-panel-head">
                                <h2>Notifikasi SIMAK</h2>
                                <button class="notification-mark-button" type="button" data-notification-mark-all>Semua dibaca</button>
                            </header>
                            <div class="notification-list" data-notification-list aria-live="polite"><p class="notification-error">Memuat notifikasi…</p></div>
                            <footer class="notification-panel-foot"><button class="notification-mark-button" type="button" data-notification-mark-all>Tandai Semua Dibaca</button></footer>
                        </section>
                    </div>

                    <!-- Activity Log Center -->
                    <div class="notification-center"
                        data-notification-center
                        data-notification-label="Log Aktivitas"
                        data-empty-message="Belum ada aktivitas yang tercatat"
                        data-notifications-url="{{ route('activity-notifications.index') }}"
                        data-mark-all-read-url="{{ route('activity-notifications.read-all') }}"
                        data-mark-read-url-template="{{ route('activity-notifications.read', ['activity' => '__NOTIFICATION__']) }}"
                        style="position: relative;">
                        <button class="portal-header-icon-btn notification-trigger" type="button" data-notification-trigger aria-expanded="false" aria-controls="simak-activity-log-panel" aria-label="Log Aktivitas" title="Log Aktivitas">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            <span class="portal-header-badge-count notification-count" data-notification-count hidden></span>
                        </button>
                        <section class="notification-panel" id="simak-activity-log-panel" data-notification-panel hidden aria-label="Log aktivitas pengguna" style="right: 0;">
                            <header class="notification-panel-head">
                                <h2>Log Aktivitas Saya</h2>
                                <button class="notification-mark-button" type="button" data-notification-mark-all>Semua dibaca</button>
                            </header>
                            <div class="notification-list" data-notification-list aria-live="polite"><p class="notification-error">Memuat log aktivitas…</p></div>
                            <footer class="notification-panel-foot"><button class="notification-mark-button" type="button" data-notification-mark-all>Tandai Semua Dibaca</button></footer>
                        </section>
                    </div>

                    <!-- Theme Toggle (Light/Dark Mode) -->
                    <button type="button" class="portal-theme-btn" id="theme-toggle-btn" aria-label="Ganti Tema Warna" title="Beralih Tema Warna (Dark/Light)">
                        <svg class="sun-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                        <svg class="moon-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: block;"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                    </button>

                    <!-- User Account Profile Dropdown -->
                    <div style="position: relative;" id="user-profile-container">
                        <button type="button" class="portal-header-user-btn" id="user-profile-trigger" aria-expanded="false" aria-label="Menu akun {{ $accountName }}" @if($currentUser->role !== 'admin') data-account-menu @endif>
                            <div class="portal-header-avatar-wrap">
                                <span class="portal-header-avatar">{{ $accountInitials }}</span>
                                <span class="portal-avatar-status-dot" title="Status: Online"></span>
                            </div>
                            <div class="portal-header-user-text">
                                <span class="portal-header-user-name">{{ $accountName }}</span>
                                <div class="portal-header-user-id-email">
                                    <span class="portal-user-badge-id">{{ $accountLogin ?: '-' }}</span>
                                    <span>·</span>
                                    <span>{{ $roleLabel }}</span>
                                </div>
                            </div>
                            <span class="portal-header-chevron" aria-hidden="true">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                            </span>
                        </button>

                        <div class="portal-user-dropdown-card" id="user-profile-dropdown" hidden>
                            <div class="portal-dropdown-profile-header">
                                <span class="portal-dropdown-avatar">{{ $accountInitials }}</span>
                                <div class="portal-dropdown-profile-meta">
                                    <h4 class="portal-dropdown-name">{{ $accountName }}</h4>
                                    <p class="portal-dropdown-email">{{ $accountEmail ?: ($accountLogin.'@aui.ac.id') }}</p>
                                    <div class="portal-dropdown-badges">
                                        <span class="portal-dropdown-id-chip">{{ $accountIdentifierLabel }}: {{ $accountLogin ?: '-' }}</span>
                                        <span class="portal-dropdown-status-chip">{{ $roleLabel }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="portal-dropdown-divider"></div>

                            <div class="portal-dropdown-menu-links">
                                @if ($currentUser->role !== 'admin')
                                    <a class="portal-dropdown-link" href="{{ route('user-profile.edit') }}" data-account-edit>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                        <span>Edit Profil</span>
                                    </a>
                                @endif
                                <a class="portal-dropdown-link" href="{{ route('dashboard') }}">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                                    <span>Dashboard Utama</span>
                                </a>
                            </div>

                            <div class="portal-dropdown-divider"></div>

                            <div class="portal-dropdown-footer">
                                <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                                    @csrf
                                    <button class="portal-dropdown-logout-btn" type="submit">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                        <span>Keluar dari SIMAK</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- ========================================================= -->
        <!-- MAIN APP BODY: SIDEBAR + MAIN STAGE                       -->
        <!-- ========================================================= -->
        <div class="portal-app-body">
            <!-- COLLAPSIBLE LEFT SIDEBAR -->
            <aside class="portal-sidebar expanded" id="portal-sidebar" aria-label="Navigasi SIAKAD">
                <!-- Collapsed Search Trigger Header -->
                <div class="tree-collapsed-filter-header">
                    <button type="button" class="tree-collapsed-search-trigger" id="collapsed-search-btn" title="Cari modul / menu...">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    </button>
                </div>

                <!-- Expanded Top Filter Container -->
                <div class="tree-filter-container">
                    <!-- Search Bar with Live Filter -->
                    <div class="tree-search-bar">
                        <span class="tree-search-icon">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        </span>
                        <input type="text" class="tree-search-input" id="tree-search-input" placeholder="Cari modul / menu..." autocomplete="off">
                        <button type="button" class="tree-search-clear-btn" id="tree-search-clear" title="Bersihkan pencarian" style="display: none;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </button>
                    </div>

                    <!-- Program Studi Selector Filter (Connected to DB & Persistent) -->
                    <div class="tree-prodi-selector-wrap">
                        <div class="tree-filter-label">
                            <span>Program Studi</span>
                            <span class="tree-prodi-active-pill" id="prodi-filter-pill">SEMUA PRODI</span>
                        </div>
                        <div class="tree-select-box">
                            <select class="tree-prodi-select" id="tree-prodi-select" aria-label="Filter berdasarkan Program Studi">
                                <option value="ALL">Semua Program Studi (Universitas)</option>
                                @if (isset($sidebarFaculties) && $sidebarFaculties->isNotEmpty())
                                    @foreach ($sidebarFaculties as $facultyName => $prodis)
                                        <optgroup label="{{ $facultyName }}">
                                            @foreach ($prodis as $p)
                                                <option value="{{ $p->id_prodi }}">
                                                    {{ $p->nama_jenjang_pendidikan ? $p->nama_jenjang_pendidikan.' - ' : '' }}{{ $p->nama_program_studi }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                @else
                                    <optgroup label="Faculty of Engineering & Technology (FET)">
                                        <option value="PRODI-ET-S1">S1 - Bachelor of Computer Science</option>
                                        <option value="PRODI-ET-S2">S2 - Master of Artificial Intelligence & CS</option>
                                    </optgroup>
                                    <optgroup label="Faculty of Business & Economics (FBE)">
                                        <option value="PRODI-BE-S1">S1 - Bachelor of Management</option>
                                        <option value="PRODI-BE-S2">S2 - Master of Business Administration (MBA)</option>
                                    </optgroup>
                                @endif
                            </select>
                            <span class="tree-select-chevron">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                            </span>
                        </div>
                    </div>

                    <!-- Actions Toolbar: Expand / Collapse All & Reset -->
                    <div class="tree-actions-toolbar">
                        <div style="display: flex; gap: 4px;">
                            <button type="button" class="tree-toolbar-btn" id="btn-expand-all" title="Buka seluruh sub-menu">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="7 13 12 18 17 13"></polyline><polyline points="7 6 12 11 17 6"></polyline></svg>
                                <span>Buka Semua</span>
                            </button>
                            <button type="button" class="tree-toolbar-btn" id="btn-collapse-all" title="Tutup seluruh sub-menu">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 11 12 6 7 11"></polyline><polyline points="17 18 12 13 7 18"></polyline></svg>
                                <span>Tutup Semua</span>
                            </button>
                        </div>
                        <button type="button" class="tree-toolbar-btn reset" id="btn-reset-filters" title="Reset pencarian & filter">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
                            <span>Reset</span>
                        </button>
                    </div>
                </div>

                <!-- Scrollable Tree Navigation Wrapper -->
                <div class="portal-sidebar-nav-wrap">
                    <nav class="tree-menu-root" id="tree-menu-root">
                        @php
                            $isDashboardPage = request()->routeIs('dashboard');
                        @endphp
                        @foreach ($menuGroups as $groupIndex => $group)
                            @php
                                $groupLabel = (string) $group['label'];
                                $meta = $getCategoryMeta($groupLabel);
                                $hasActiveDescendant = false;
                                foreach ($group['items'] as $it) {
                                    if (is_array($it) && $isItemActive($it)) {
                                        $hasActiveDescendant = true;
                                        break;
                                    }
                                }
                                // "ketika menu aktif di hirarki yang sedang di buka menu nya harus nya terbuka"
                                // Hirarki yang memiliki menu aktif otomatis terbuka; hirarki lain tetap tertutup secara default.
                                $isGroupOpen = $hasActiveDescendant;
                                $groupId = 'tree-group-'.$groupIndex;
                            @endphp

                            <div class="tree-domain-group" data-group-title="{{ strtolower($groupLabel) }}" data-badge="{{ $meta['badge'] }}" id="{{ $groupId }}">
                                <div class="tree-node-row level-1 {{ $hasActiveDescendant ? 'active' : '' }}" data-group-row="{{ $groupId }}">
                                    <!-- Collapsed Indicator Bar -->
                                    @if ($hasActiveDescendant)
                                        <span class="tree-collapsed-active-bar" aria-hidden="true"></span>
                                    @endif

                                    <!-- Chevron Toggle (expanded mode) -->
                                    <button type="button" class="tree-chevron-toggle {{ $isGroupOpen ? 'open' : '' }}" aria-expanded="{{ $isGroupOpen ? 'true' : 'false' }}" aria-controls="sub-{{ $groupId }}" title="Buka / Tutup {{ $groupLabel }}">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                                    </button>

                                    <!-- Category Header / Button Tile (NOT a link! It toggles the accordion) -->
                                    <button type="button" class="tree-node-button" data-group-index="{{ $groupIndex }}" aria-expanded="{{ $isGroupOpen ? 'true' : 'false' }}" aria-controls="sub-{{ $groupId }}" title="{{ $groupLabel }}">
                                        <span class="tree-node-icon {{ $hasActiveDescendant ? 'active' : '' }}">
                                            @if ($meta['icon'] === 'layout-dashboard')
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                                            @elseif ($meta['icon'] === 'database')
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                                            @elseif ($meta['icon'] === 'graduation-cap')
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
                                            @elseif ($meta['icon'] === 'calendar-days')
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                            @elseif ($meta['icon'] === 'book-open-check')
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path><polyline points="16 10 18 12 22 8"></polyline></svg>
                                            @elseif ($meta['icon'] === 'scroll-text')
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 21h12a2 2 0 0 0 2-2v-2H10v2a2 2 0 1 1-4 0V5a2 2 0 1 0-4 0v3h4"></path><path d="M19 17V5a2 2 0 0 0-2-2H4"></path></svg>
                                            @elseif ($meta['icon'] === 'wallet-cards')
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                                            @elseif ($meta['icon'] === 'award')
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                                            @elseif ($meta['icon'] === 'map-pinned')
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8c0 4.5-6 9-6 9s-6-4.5-6-9a6 6 0 0 1 12 0z"></path><circle cx="12" cy="8" r="2"></circle></svg>
                                            @elseif ($meta['icon'] === 'clipboard-check')
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="2" width="6" height="4" rx="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><polyline points="9 14 11 16 15 12"></polyline></svg>
                                            @elseif ($meta['icon'] === 'settings')
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                                            @elseif ($meta['icon'] === 'chart-no-axes-column-increasing')
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line></svg>
                                            @else
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                                            @endif
                                        </span>
                                        <span class="tree-node-label root-title">{{ $groupLabel }}</span>
                                    </button>
                                </div>

                                <!-- Submenu list (Level 2) - Open if has active descendant, otherwise closed -->
                                <div class="tree-sub-list {{ $isGroupOpen ? '' : 'is-collapsed' }}" id="sub-{{ $groupId }}" {!! $isGroupOpen ? '' : 'inert' !!}>
                                    @foreach ($group['items'] as $itemIndex => $item)
                                        @php
                                            $itemLabel = (string) ($item['label'] ?? '');
                                            $itemRoute = (string) ($item['route'] ?? '');
                                            $hasChildren = (!empty($item['items']) && is_array($item['items'])) || (!empty($item['children']) && is_array($item['children']));
                                            $childList = !empty($item['items']) && is_array($item['items']) ? $item['items'] : (!empty($item['children']) && is_array($item['children']) ? $item['children'] : []);
                                            $isItemActiveCurrent = false;
                                            if ($itemRoute !== '') {
                                                $isItemActiveCurrent = request()->routeIs($itemRoute);
                                                if (!$isItemActiveCurrent && str_ends_with($itemRoute, '.index')) {
                                                    $isItemActiveCurrent = request()->routeIs(substr($itemRoute, 0, -6) . '.*');
                                                }
                                            }
                                            $isSubBranchActive = $isItemActive($item);
                                            $subItemId = $groupId.'-sub-'.$itemIndex;
                                        @endphp

                                        @if ($hasChildren)
                                            <!-- Level 2 Sub-Group (has children -> toggles child hierarchy, does NOT navigate) -->
                                            <div class="tree-sub-group" data-item-title="{{ strtolower($itemLabel) }}" id="{{ $subItemId }}">
                                                <div class="tree-node-row level-2 {{ $isSubBranchActive ? 'active' : '' }}" data-sub-row="{{ $subItemId }}">
                                                    <span class="tree-connector-elbow" aria-hidden="true"></span>
                                                    <button type="button" class="tree-sub-chevron-toggle {{ $isSubBranchActive ? 'open' : '' }}" aria-expanded="{{ $isSubBranchActive ? 'true' : 'false' }}" aria-controls="leaf-{{ $subItemId }}" title="Buka / Tutup {{ $itemLabel }}">
                                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                                                    </button>
                                                    <button type="button" class="tree-sub-node-button" aria-expanded="{{ $isSubBranchActive ? 'true' : 'false' }}" aria-controls="leaf-{{ $subItemId }}" title="{{ $itemLabel }}">
                                                        <span class="tree-sub-folder {{ $isSubBranchActive ? 'active' : '' }}" aria-hidden="true">
                                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                                                        </span>
                                                        <span class="tree-node-label sub-title">{{ $itemLabel }}</span>
                                                    </button>
                                                </div>

                                                <!-- Level 3 Leaf List - Open if has active descendant, otherwise closed -->
                                                <div class="tree-leaf-list {{ $isSubBranchActive ? '' : 'is-collapsed' }}" id="leaf-{{ $subItemId }}" {!! $isSubBranchActive ? '' : 'inert' !!}>
                                                    @foreach ($childList as $leafIndex => $leaf)
                                                        @php
                                                            $leafLabel = (string) ($leaf['label'] ?? '');
                                                            $leafRoute = (string) ($leaf['route'] ?? '');
                                                            $hasLeafChildren = (!empty($leaf['items']) && is_array($leaf['items'])) || (!empty($leaf['children']) && is_array($leaf['children']));
                                                            $leafChildList = !empty($leaf['items']) && is_array($leaf['items']) ? $leaf['items'] : (!empty($leaf['children']) && is_array($leaf['children']) ? $leaf['children'] : []);
                                                            $isLeafActive = false;
                                                            if ($leafRoute !== '') {
                                                                $isLeafActive = request()->routeIs($leafRoute);
                                                                if (!$isLeafActive && str_ends_with($leafRoute, '.index')) {
                                                                    $isLeafActive = request()->routeIs(substr($leafRoute, 0, -6) . '.*');
                                                                }
                                                            }
                                                            $isLeafBranchActive = $isItemActive($leaf);
                                                            $leafItemId = $subItemId.'-leaf-'.$leafIndex;
                                                        @endphp

                                                        @if ($hasLeafChildren)
                                                            <!-- Level 3 Sub-Group (has children -> toggles child hierarchy, does NOT navigate) -->
                                                            <div class="tree-sub-group level-3-group" data-item-title="{{ strtolower($leafLabel) }}" id="{{ $leafItemId }}">
                                                                <div class="tree-node-row level-3 {{ $isLeafBranchActive ? 'active' : '' }}" data-sub-row="{{ $leafItemId }}">
                                                                    <span class="tree-connector-elbow" aria-hidden="true"></span>
                                                                    <button type="button" class="tree-sub-chevron-toggle {{ $isLeafBranchActive ? 'open' : '' }}" aria-expanded="{{ $isLeafBranchActive ? 'true' : 'false' }}" aria-controls="subleaf-{{ $leafItemId }}" title="Buka / Tutup {{ $leafLabel }}">
                                                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                                                                    </button>
                                                                    <button type="button" class="tree-sub-node-button" aria-expanded="{{ $isLeafBranchActive ? 'true' : 'false' }}" aria-controls="subleaf-{{ $leafItemId }}" title="{{ $leafLabel }}">
                                                                        <span class="tree-sub-folder {{ $isLeafBranchActive ? 'active' : '' }}" aria-hidden="true">
                                                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                                                                        </span>
                                                                        <span class="tree-node-label sub-title">{{ $leafLabel }}</span>
                                                                    </button>
                                                                </div>

                                                                <!-- Level 4 Leaf List - Open if has active descendant, otherwise closed -->
                                                                <div class="tree-leaf-list {{ $isLeafBranchActive ? '' : 'is-collapsed' }}" id="subleaf-{{ $leafItemId }}" {!! $isLeafBranchActive ? '' : 'inert' !!}>
                                                                    @foreach ($leafChildList as $deepLeaf)
                                                                        @php
                                                                            $deepRoute = (string) ($deepLeaf['route'] ?? '');
                                                                            $deepLabel = (string) ($deepLeaf['label'] ?? '');
                                                                            $isDeepActive = false;
                                                                            if ($deepRoute !== '') {
                                                                                $isDeepActive = request()->routeIs($deepRoute);
                                                                                if (!$isDeepActive && str_ends_with($deepRoute, '.index')) {
                                                                                    $isDeepActive = request()->routeIs(substr($deepRoute, 0, -6) . '.*');
                                                                                }
                                                                            }
                                                                        @endphp
                                                                        <!-- Level 4 Final Leaf (NO children -> navigates to page!) -->
                                                                        <a href="{{ $deepRoute !== '' ? route($deepRoute) : '#' }}" class="tree-leaf-link {{ $isDeepActive ? 'active' : '' }}" {!! $isDeepActive ? 'aria-current="page"' : '' !!} data-item-title="{{ strtolower($deepLabel) }}">
                                                                            <span class="tree-sub-dot" aria-hidden="true"></span>
                                                                            <span class="tree-node-label leaf-title">{{ $deepLabel }}</span>
                                                                        </a>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @else
                                                            <!-- Level 3 Final Leaf (NO children -> navigates to page!) -->
                                                            <a href="{{ $leafRoute !== '' ? route($leafRoute) : '#' }}" class="tree-leaf-link {{ $isLeafActive ? 'active' : '' }}" {!! $isLeafActive ? 'aria-current="page"' : '' !!} data-item-title="{{ strtolower($leafLabel) }}">
                                                                <span class="tree-sub-dot" aria-hidden="true"></span>
                                                                <span class="tree-node-label leaf-title">{{ $leafLabel }}</span>
                                                            </a>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                        @else
                                            <!-- Level 2 Final Leaf (NO children -> navigates to page!) -->
                                            <a href="{{ $itemRoute !== '' ? route($itemRoute) : '#' }}" class="tree-sub-link {{ $isItemActiveCurrent ? 'active' : '' }}" {!! $isItemActiveCurrent ? 'aria-current="page"' : '' !!} data-item-title="{{ strtolower($itemLabel) }}">
                                                <span class="tree-sub-dot" aria-hidden="true"></span>
                                                <span class="tree-node-label sub-title">{{ $itemLabel }}</span>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </nav>
                </div>
            </aside>

            <!-- Floating Hover Flyout Card for Collapsed Mode (Generated dynamically via JS) -->
            <div class="tree-collapsed-flyout-card" id="collapsed-hover-flyout" hidden>
                <div class="flyout-header">
                    <span class="flyout-title" id="flyout-title">Modul</span>
                    <span class="flyout-badge" id="flyout-badge">AUI</span>
                </div>
                <div class="flyout-items-list" id="flyout-items"></div>
            </div>

            <!-- Mobile Scrim -->
            <button class="sidebar-scrim" id="sidebar-scrim" type="button" aria-label="Tutup navigasi mobile"></button>

            <!-- MAIN STAGE -->
            <div class="portal-main-stage" id="portal-main-stage">
                <main class="portal-content-fluid">
                    @include('partials.flash-notice')
                    @yield('content')
                </main>
            </div>
        </div>

        <!-- APP FOOTER (FULL WIDTH / FIT WIDTH UNDER SIDEBAR & STAGE) -->
        <footer class="portal-app-footer">
            <div class="portal-footer-fluid">
                <div>&copy; {{ date('Y') }} ASEAN University International. All Rights Reserved. • Sistem Informasi Akademik & LMS Enterprise</div>
                <div class="portal-footer-live-clock">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <span id="portal-live-clock">{{ \Carbon\Carbon::now()->locale('id')->translatedFormat('l, d F Y') }} • {{ \Carbon\Carbon::now()->format('H:i:s') }}</span>
                </div>
            </div>
        </footer>
    </div>

    <!-- Edit Profile Modal Dialog -->
    @if ($currentUser->role !== 'admin')
        <div class="account-profile-modal-backdrop" data-profile-modal hidden>
            <section class="account-profile-modal" role="dialog" aria-modal="true" aria-labelledby="account-profile-modal-title" tabindex="-1">
                <header class="account-profile-modal-head">
                    <div>
                        <h2 id="account-profile-modal-title">Edit Profil</h2>
                        <p>Perbarui informasi akun Anda.</p>
                    </div>
                    <button class="account-profile-modal-close" type="button" data-profile-close aria-label="Tutup modal">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </header>
                <div class="account-profile-modal-content" data-profile-modal-content></div>
            </section>
        </div>
    @endif

    <!-- Client Script: Sidebar Toggle, Filter, Search, Theme & Role Simulator -->
    <script>
        (() => {
            // Synchronize cross-tab authentication
            window.addEventListener('storage', (event) => {
                if (event.key === 'simak-auth-changed') window.location.reload();
            });
            @if (session('auth_changed'))
                try {
                    window.localStorage.setItem('simak-auth-changed', String(Date.now()));
                } catch (e) {}
            @endif

            // DOM Elements
            const shell = document.getElementById('simak-shell');
            const sidebar = document.getElementById('portal-sidebar');
            const mainStage = document.getElementById('portal-main-stage');
            const toggleTopBtn = document.getElementById('sidebar-toggle-btn');
            const scrim = document.getElementById('sidebar-scrim');
            const themeToggleBtn = document.getElementById('theme-toggle-btn');
            const sunIcon = themeToggleBtn?.querySelector('.sun-icon');
            const moonIcon = themeToggleBtn?.querySelector('.moon-icon');
            const searchInput = document.getElementById('tree-search-input');
            const searchClearBtn = document.getElementById('tree-search-clear');
            const prodiSelect = document.getElementById('tree-prodi-select');
            const prodiPill = document.getElementById('prodi-filter-pill');
            const btnExpandAll = document.getElementById('btn-expand-all');
            const btnCollapseAll = document.getElementById('btn-collapse-all');
            const btnResetFilters = document.getElementById('btn-reset-filters');
            const treeGroups = document.querySelectorAll('.tree-domain-group');
            const flyoutCard = document.getElementById('collapsed-hover-flyout');
            const flyoutTitle = document.getElementById('flyout-title');
            const flyoutBadge = document.getElementById('flyout-badge');
            const flyoutItems = document.getElementById('flyout-items');
            const userProfileTrigger = document.getElementById('user-profile-trigger');
            const userProfileDropdown = document.getElementById('user-profile-dropdown');
            const collapsedSearchBtn = document.getElementById('collapsed-search-btn');

            // 1. Sidebar Collapse / Expand State
            const updateSidebarState = (collapsed) => {
                if (collapsed) {
                    sidebar.classList.remove('expanded');
                    sidebar.classList.add('collapsed');
                    mainStage.classList.add('sidebar-collapsed');
                } else {
                    sidebar.classList.add('expanded');
                    sidebar.classList.remove('collapsed');
                    mainStage.classList.remove('sidebar-collapsed');
                }
                localStorage.setItem('simak_sidebar_collapsed', String(collapsed));
            };

            const storedCollapse = localStorage.getItem('simak_sidebar_collapsed');
            if (storedCollapse === 'true') {
                updateSidebarState(true);
            }

            const toggleSidebar = () => {
                if (window.innerWidth <= 1024) {
                    sidebar.classList.toggle('mobile-open');
                } else {
                    const isNowCollapsed = !sidebar.classList.contains('collapsed');
                    updateSidebarState(isNowCollapsed);
                }
            };

            toggleTopBtn?.addEventListener('click', toggleSidebar);
            scrim?.addEventListener('click', () => sidebar.classList.remove('mobile-open'));

            // 2. Multi-Level Hierarchical Accordion System
            // Rule:
            // - "menu samping default nya tutup semua" -> all start closed
            // - "menu parent ketika di klik terbuka hanya di hirarki tersebut jangan semua nya"
            //   -> Clicking Level 1 toggles only that hierarchy; closes other Level 1 groups!
            //   -> Inside that Level 1 group, nested sub-groups stay collapsed until clicked!
            // - "jika menu di bawah nya masih ada child buka hirarki di bawah nya lagi smapai child terakhir"
            //   -> Clicking Level 2 with children toggles Level 3; closes sibling Level 2 groups!
            //   -> Clicking Level 3 with children toggles Level 4; closes sibling Level 3 groups!
            // - "ketika di klik child terakhir nya baru buka halaman"
            //   -> Final leaf nodes (a.tree-sub-link, a.tree-leaf-link) are standard links; clicking navigates to the page!

            const treeRoot = document.getElementById('tree-menu-root');

            const collapseDomainGroup = (group) => {
                const subList = group?.querySelector(':scope > .tree-sub-list');
                const chevron = group?.querySelector(':scope > .tree-node-row.level-1 .tree-chevron-toggle');
                const btn = group?.querySelector(':scope > .tree-node-row.level-1 .tree-node-button');
                subList?.classList.add('is-collapsed');
                subList?.setAttribute('inert', '');
                chevron?.classList.remove('open');
                chevron?.setAttribute('aria-expanded', 'false');
                btn?.setAttribute('aria-expanded', 'false');

                // Collapse all nested child groups as well
                group?.querySelectorAll('.tree-leaf-list').forEach((ll) => {
                    ll.classList.add('is-collapsed');
                    ll.setAttribute('inert', '');
                });
                group?.querySelectorAll('.tree-sub-chevron-toggle').forEach((sc) => {
                    sc.classList.remove('open');
                    sc.setAttribute('aria-expanded', 'false');
                });
                group?.querySelectorAll('.tree-sub-node-button').forEach((sb) => {
                    sb.setAttribute('aria-expanded', 'false');
                });
            };

            const expandDomainGroup = (group) => {
                const subList = group?.querySelector(':scope > .tree-sub-list');
                const chevron = group?.querySelector(':scope > .tree-node-row.level-1 .tree-chevron-toggle');
                const btn = group?.querySelector(':scope > .tree-node-row.level-1 .tree-node-button');
                subList?.classList.remove('is-collapsed');
                subList?.removeAttribute('inert');
                chevron?.classList.add('open');
                chevron?.setAttribute('aria-expanded', 'true');
                btn?.setAttribute('aria-expanded', 'true');
            };

            const collapseSubGroup = (subGroup) => {
                const leafList = subGroup?.querySelector(':scope > .tree-leaf-list');
                const chevron = subGroup?.querySelector(':scope > .tree-node-row .tree-sub-chevron-toggle');
                const btn = subGroup?.querySelector(':scope > .tree-node-row .tree-sub-node-button');
                leafList?.classList.add('is-collapsed');
                leafList?.setAttribute('inert', '');
                chevron?.classList.remove('open');
                chevron?.setAttribute('aria-expanded', 'false');
                btn?.setAttribute('aria-expanded', 'false');

                // Collapse deeper nested lists
                leafList?.querySelectorAll('.tree-leaf-list').forEach((ll) => {
                    ll.classList.add('is-collapsed');
                    ll.setAttribute('inert', '');
                });
                leafList?.querySelectorAll('.tree-sub-chevron-toggle').forEach((sc) => {
                    sc.classList.remove('open');
                    sc.setAttribute('aria-expanded', 'false');
                });
                leafList?.querySelectorAll('.tree-sub-node-button').forEach((sb) => {
                    sb.setAttribute('aria-expanded', 'false');
                });
            };

            const expandSubGroup = (subGroup) => {
                const leafList = subGroup?.querySelector(':scope > .tree-leaf-list');
                const chevron = subGroup?.querySelector(':scope > .tree-node-row .tree-sub-chevron-toggle');
                const btn = subGroup?.querySelector(':scope > .tree-node-row .tree-sub-node-button');
                leafList?.classList.remove('is-collapsed');
                leafList?.removeAttribute('inert');
                chevron?.classList.add('open');
                chevron?.setAttribute('aria-expanded', 'true');
                btn?.setAttribute('aria-expanded', 'true');
            };

            // Auto-expand full hierarchy leading to active menu item
            const autoExpandActiveHierarchy = () => {
                const activeLeaf = treeRoot?.querySelector('a.tree-sub-link.active, a.tree-leaf-link.active, a[aria-current="page"]');
                if (!activeLeaf) return;

                let current = activeLeaf.parentElement;
                while (current && current !== treeRoot) {
                    if (current.classList.contains('tree-sub-group')) {
                        expandSubGroup(current);
                    } else if (current.classList.contains('tree-domain-group')) {
                        expandDomainGroup(current);
                    }
                    current = current.parentElement;
                }

                // Ensure other non-active Level 1 domain groups are collapsed
                const activeDomainGroup = activeLeaf.closest('.tree-domain-group');
                if (activeDomainGroup) {
                    treeGroups.forEach((g) => {
                        if (g !== activeDomainGroup) {
                            collapseDomainGroup(g);
                        }
                    });
                }

                // Smooth scroll active item into view
                try {
                    setTimeout(() => {
                        activeLeaf.scrollIntoView({ block: 'nearest' });
                    }, 60);
                } catch (e) {}
            };

            autoExpandActiveHierarchy();

            treeRoot?.addEventListener('click', (e) => {
                const target = e.target;
                if (!target) return;

                // 1. Leaf link click (child terakhir) -> open page (standard link navigation)
                const leafLink = target.closest('a.tree-sub-link, a.tree-leaf-link');
                if (leafLink) {
                    if (window.innerWidth <= 1024) {
                        sidebar.classList.remove('mobile-open');
                    }
                    return; // Let browser open the route / page
                }

                // 2. Level 3 Sub-Group with children -> toggle Level 4
                const level3Row = target.closest('.tree-node-row.level-3');
                if (level3Row) {
                    e.preventDefault();
                    e.stopPropagation();
                    const subGroup = level3Row.closest('.tree-sub-group.level-3-group');
                    const leafList = subGroup?.querySelector(':scope > .tree-leaf-list');
                    if (leafList) {
                        const isOpen = !leafList.classList.contains('is-collapsed');
                        if (isOpen) {
                            collapseSubGroup(subGroup);
                        } else {
                            // Close sibling Level 3 groups
                            const parentContainer = subGroup.parentElement;
                            parentContainer?.querySelectorAll(':scope > .tree-sub-group.level-3-group').forEach((sib) => {
                                if (sib !== subGroup) collapseSubGroup(sib);
                            });
                            expandSubGroup(subGroup);
                        }
                    }
                    return;
                }

                // 3. Level 2 Sub-Group with children -> toggle Level 3
                const level2Row = target.closest('.tree-node-row.level-2');
                if (level2Row) {
                    e.preventDefault();
                    e.stopPropagation();
                    const subGroup = level2Row.closest('.tree-sub-group');
                    const leafList = subGroup?.querySelector(':scope > .tree-leaf-list');
                    if (leafList) {
                        const isOpen = !leafList.classList.contains('is-collapsed');
                        if (isOpen) {
                            collapseSubGroup(subGroup);
                        } else {
                            // Close sibling Level 2 groups in this domain group
                            const domainGroup = subGroup.closest('.tree-domain-group');
                            domainGroup?.querySelectorAll(':scope > .tree-sub-list > .tree-sub-group').forEach((sib) => {
                                if (sib !== subGroup) collapseSubGroup(sib);
                            });
                            expandSubGroup(subGroup);
                        }
                    }
                    return;
                }

                // 4. Level 1 Domain Group -> toggle Level 2
                const level1Row = target.closest('.tree-node-row.level-1');
                if (level1Row) {
                    if (sidebar.classList.contains('collapsed')) return;
                    e.preventDefault();
                    e.stopPropagation();
                    const group = level1Row.closest('.tree-domain-group');
                    const subList = group?.querySelector(':scope > .tree-sub-list');
                    if (subList) {
                        const isOpen = !subList.classList.contains('is-collapsed');
                        if (isOpen) {
                            collapseDomainGroup(group);
                        } else {
                            // "terbuka hanya di hirarki tersebut jangan semua nya":
                            // Close ALL other Level 1 domain groups
                            treeGroups.forEach((otherGroup) => {
                                if (otherGroup !== group) {
                                    collapseDomainGroup(otherGroup);
                                }
                            });
                            // If this group contains an active link, restore its active hierarchy
                            const activeLink = group.querySelector('a.active, .tree-leaf-link.active, .tree-sub-link.active');
                            if (activeLink) {
                                let ancestorSub = activeLink.closest('.tree-sub-group');
                                while (ancestorSub && group.contains(ancestorSub)) {
                                    expandSubGroup(ancestorSub);
                                    ancestorSub = ancestorSub.parentElement?.closest('.tree-sub-group');
                                }
                            } else {
                                // Ensure nested sub-groups inside this newly opened group start closed
                                group.querySelectorAll('.tree-leaf-list').forEach((ll) => {
                                    ll.classList.add('is-collapsed');
                                    ll.setAttribute('inert', '');
                                });
                                group.querySelectorAll('.tree-sub-chevron-toggle').forEach((sc) => {
                                    sc.classList.remove('open');
                                    sc.setAttribute('aria-expanded', 'false');
                                });
                                group.querySelectorAll('.tree-sub-node-button').forEach((sb) => {
                                    sb.setAttribute('aria-expanded', 'false');
                                });
                            }

                            expandDomainGroup(group);
                        }
                    }
                    return;
                }
            });

            // 3. Real-Time Search Filtering
            const filterTree = () => {
                const query = (searchInput?.value || '').trim().toLowerCase();
                let visibleCount = 0;

                if (searchClearBtn) {
                    searchClearBtn.style.display = query.length > 0 ? 'flex' : 'none';
                }

                treeGroups.forEach((group) => {
                    const groupTitle = group.dataset.groupTitle || '';
                    const allLinks = group.querySelectorAll('.tree-sub-link, .tree-leaf-link');
                    let hasMatchingLink = false;

                    allLinks.forEach((link) => {
                        const linkTitle = link.dataset.itemTitle || '';
                        const matchesText = !query || linkTitle.includes(query) || groupTitle.includes(query);
                        if (matchesText) {
                            link.style.display = 'flex';
                            hasMatchingLink = true;
                            // Ensure all ancestor containers are visible and expanded during search
                            if (query) {
                                let ancestorSub = link.closest('.tree-sub-group');
                                while (ancestorSub && group.contains(ancestorSub)) {
                                    ancestorSub.style.display = 'flex';
                                    const leafList = ancestorSub.querySelector(':scope > .tree-leaf-list');
                                    const subChevron = ancestorSub.querySelector(':scope > .tree-node-row .tree-sub-chevron-toggle');
                                    leafList?.classList.remove('is-collapsed');
                                    leafList?.removeAttribute('inert');
                                    subChevron?.classList.add('open');
                                    ancestorSub = ancestorSub.parentElement?.closest('.tree-sub-group');
                                }
                            }
                        } else {
                            link.style.display = 'none';
                        }
                    });

                    // If empty query, reset all link displays to default
                    if (!query) {
                        allLinks.forEach((link) => { link.style.display = ''; });
                        group.querySelectorAll('.tree-sub-group').forEach((sg) => { sg.style.display = ''; });
                    }

                    if (!query || hasMatchingLink || groupTitle.includes(query)) {
                        group.style.display = 'block';
                        visibleCount++;
                        if (query) {
                            const subList = group.querySelector(':scope > .tree-sub-list');
                            const chevron = group.querySelector(':scope > .tree-node-row.level-1 .tree-chevron-toggle');
                            subList?.classList.remove('is-collapsed');
                            subList?.removeAttribute('inert');
                            chevron?.classList.add('open');
                        }
                    } else {
                        group.style.display = 'none';
                    }
                });

                if (!query) {
                    // When query is cleared, restore active hierarchy to open, keep other non-active groups collapsed
                    treeGroups.forEach((g) => collapseDomainGroup(g));
                    autoExpandActiveHierarchy();
                }

                const badgeCount = document.getElementById('visible-modules-count');
                if (badgeCount) badgeCount.textContent = `${visibleCount} Modul`;
            };

            searchInput?.addEventListener('input', filterTree);
            searchClearBtn?.addEventListener('click', () => {
                if (searchInput) searchInput.value = '';
                filterTree();
                searchInput?.focus();
            });

            // 4. Program Studi Selector Interactivity & Persistence
            const updateProdiUI = (selectedVal) => {
                if (!prodiSelect) return;
                prodiSelect.value = selectedVal;
                const opt = prodiSelect.options[prodiSelect.selectedIndex];
                const text = opt ? opt.text.trim() : '';

                if (prodiPill) {
                    if (selectedVal === 'ALL' || !selectedVal) {
                        prodiPill.textContent = 'SEMUA PRODI';
                    } else {
                        const cleanText = text.replace(/^(S\d)\s*-\s*/, '$1: ');
                        prodiPill.textContent = cleanText.length > 18 ? cleanText.substring(0, 18) + '…' : cleanText;
                    }
                }

                // Filter dashboard DTPS table rows if present on page
                const dtpsRows = document.querySelectorAll('tbody tr[data-prodi-id]');
                if (dtpsRows.length > 0) {
                    dtpsRows.forEach((row) => {
                        const rowProdiId = row.getAttribute('data-prodi-id');
                        if (selectedVal === 'ALL' || !selectedVal || rowProdiId === selectedVal) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                }

                // Dispatch event so any page script can listen to prodi change
                window.dispatchEvent(new CustomEvent('simak:prodi-changed', {
                    detail: { prodiId: selectedVal, prodiName: text }
                }));
            };

            // Restore stored prodi selection on load
            const storedProdi = localStorage.getItem('simak_selected_prodi') || 'ALL';
            if (storedProdi && prodiSelect) {
                const exists = Array.from(prodiSelect.options).some(o => o.value === storedProdi);
                if (exists) {
                    updateProdiUI(storedProdi);
                }
            }

            prodiSelect?.addEventListener('change', () => {
                const val = prodiSelect.value;
                localStorage.setItem('simak_selected_prodi', val);
                updateProdiUI(val);
            });

            // 5. Expand / Collapse All & Reset
            btnExpandAll?.addEventListener('click', () => {
                treeGroups.forEach((group) => {
                    expandDomainGroup(group);
                    group.querySelectorAll('.tree-sub-group').forEach((sg) => {
                        expandSubGroup(sg);
                    });
                });
            });

            btnCollapseAll?.addEventListener('click', () => {
                treeGroups.forEach((group) => {
                    collapseDomainGroup(group);
                });
            });

            btnResetFilters?.addEventListener('click', () => {
                if (searchInput) searchInput.value = '';
                if (prodiSelect) prodiSelect.value = 'ALL';
                localStorage.setItem('simak_selected_prodi', 'ALL');
                updateProdiUI('ALL');
                filterTree();
            });

            // 7. Hover Flyout Modals on Collapsed Rail
            let flyoutTimeout = null;
            document.querySelectorAll('.tree-node-button').forEach((btn) => {
                const group = btn.closest('.tree-domain-group');
                btn.addEventListener('mouseenter', () => {
                    if (!sidebar.classList.contains('collapsed')) return;
                    clearTimeout(flyoutTimeout);

                    const title = group?.querySelector('.tree-node-label')?.textContent || '';
                    const badge = group?.dataset?.badge || 'AUI';
                    const links = group?.querySelectorAll('.tree-sub-link, .tree-leaf-link') || [];

                    if (flyoutTitle) flyoutTitle.textContent = title;
                    if (flyoutBadge) flyoutBadge.textContent = badge;
                    if (flyoutItems) {
                        flyoutItems.innerHTML = '';
                        links.forEach((l) => {
                            const a = document.createElement('a');
                            a.className = 'flyout-item-link' + (l.classList.contains('active') ? ' active' : '');
                            a.href = l.href;
                            a.textContent = l.textContent.trim();
                            flyoutItems.appendChild(a);
                        });
                    }

                    const rect = btn.getBoundingClientRect();
                    if (flyoutCard) {
                        flyoutCard.style.top = `${rect.top + rect.height / 2}px`;
                        flyoutCard.style.transform = 'translateY(-50%)';
                        flyoutCard.hidden = false;
                    }
                });

                btn.addEventListener('mouseleave', () => {
                    flyoutTimeout = setTimeout(() => {
                        if (flyoutCard && !flyoutCard.matches(':hover')) {
                            flyoutCard.hidden = true;
                        }
                    }, 120);
                });
            });

            flyoutCard?.addEventListener('mouseleave', () => {
                flyoutCard.hidden = true;
            });

            // 8. Theme Toggle (Light / Dark)
            const syncThemeIcons = (theme) => {
                if (theme === 'dark') {
                    if (sunIcon) sunIcon.style.display = 'block';
                    if (moonIcon) moonIcon.style.display = 'none';
                    themeToggleBtn?.setAttribute('title', 'Beralih ke Tema Terang');
                } else {
                    if (sunIcon) sunIcon.style.display = 'none';
                    if (moonIcon) moonIcon.style.display = 'block';
                    themeToggleBtn?.setAttribute('title', 'Beralih ke Tema Gelap');
                }
            };

            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            syncThemeIcons(currentTheme);

            themeToggleBtn?.addEventListener('click', () => {
                const now = document.documentElement.getAttribute('data-theme') || 'light';
                const next = now === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem('simak_theme', next);
                localStorage.setItem('anvieo_theme', next);
                syncThemeIcons(next);
            });

            // 7. Dropdown toggle (User profile)
            userProfileTrigger?.addEventListener('click', (e) => {
                e.stopPropagation();
                const isOpen = !userProfileDropdown.hidden;
                userProfileDropdown.hidden = isOpen;
                userProfileTrigger.classList.toggle('active', !isOpen);
            });

            document.addEventListener('click', (e) => {
                if (userProfileDropdown && !userProfileDropdown.contains(e.target) && !userProfileTrigger?.contains(e.target)) {
                    userProfileDropdown.hidden = true;
                    userProfileTrigger?.classList.remove('active');
                }
            });

            // 10. Shortcut Keys (⌘K / Ctrl+K)
            document.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                    e.preventDefault();
                    if (sidebar.classList.contains('collapsed')) {
                        updateSidebarState(false);
                    }
                    searchInput?.focus();
                }
                if (e.key === 'Escape') {
                    if (userProfileDropdown) userProfileDropdown.hidden = true;
                    if (flyoutCard) flyoutCard.hidden = true;
                }
            });

            collapsedSearchBtn?.addEventListener('click', () => {
                updateSidebarState(false);
                searchInput?.focus();
            });

            // 11. Live Running Clock (Hari, Tanggal Bulan Tahun & Jam)
            const liveClockEl = document.getElementById('portal-live-clock');
            if (liveClockEl) {
                const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                const months = [
                    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
                ];
                const updateRunningClock = () => {
                    const now = new Date();
                    const day = days[now.getDay()];
                    const date = String(now.getDate()).padStart(2, '0');
                    const month = months[now.getMonth()];
                    const year = now.getFullYear();
                    const hours = String(now.getHours()).padStart(2, '0');
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    const seconds = String(now.getSeconds()).padStart(2, '0');
                    liveClockEl.textContent = `${day}, ${date} ${month} ${year} • ${hours}:${minutes}:${seconds}`;
                };
                updateRunningClock();
                setInterval(updateRunningClock, 1000);
            }
        })();
    </script>

    @stack('scripts')
</body>
</html>
