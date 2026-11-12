@php
    $maintenanceUntil = config('app.maintenance_until');
    $maintenanceMessage = config('app.maintenance_message', 'Proses Update dan Sinkronisasi Layanan SIAKAD');
    $maintenanceIso = null;
    $maintenanceInstitution = config('app.name', 'Siakad');
    $maintenanceLogo = asset('images/landing/default-campus-logo.png');

    try {
        $maintenanceBranding = app(\App\Services\InstitutionSettings::class)->forDisplay();
        $maintenanceInstitution = $maintenanceBranding['institution_name'];
        $maintenanceLogo = $maintenanceBranding['logo_url'];
    } catch (PDOException) {
    }

    if (filled($maintenanceUntil)) {
        try {
            $maintenanceIso = \Carbon\Carbon::parse($maintenanceUntil)->setTimezone(config('app.timezone'))->toIso8601String();
        } catch (Throwable $exception) {
            $maintenanceIso = null;
        }
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#f2f7f8">
    <title>Maintenance | {{ $maintenanceInstitution }}</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #14343b;
            --muted: #647b80;
            --teal: #087e83;
            --teal-dark: #07545f;
            --mint: #e6f5f2;
            --gold: #e6aa49;
            --line: #e4edef;
            --white: #fff;
            --shadow: 0 32px 90px rgba(27, 73, 82, .13);
        }

        * { box-sizing: border-box; }

        html { min-width: 320px; min-height: 100%; }

        body {
            display: grid;
            min-height: 100vh;
            margin: 0;
            padding: clamp(16px, 4vw, 48px);
            place-items: center;
            overflow-x: hidden;
            background:
                radial-gradient(ellipse at 12% 8%, rgba(119, 205, 190, .19), transparent 34rem),
                radial-gradient(ellipse at 92% 92%, rgba(230, 170, 73, .13), transparent 28rem),
                #f2f7f8;
            color: var(--ink);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        .maintenance-card {
            position: relative;
            width: min(100%, 1060px);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, .9);
            border-radius: 30px;
            background: rgba(255, 255, 255, .94);
            box-shadow: var(--shadow);
            isolation: isolate;
        }

        .maintenance-card::before {
            position: absolute;
            z-index: -1;
            top: -190px;
            right: -100px;
            width: 480px;
            height: 480px;
            border: 1px solid rgba(8, 126, 131, .07);
            border-radius: 50%;
            content: "";
            box-shadow: 0 0 0 42px rgba(8, 126, 131, .025), 0 0 0 84px rgba(8, 126, 131, .02);
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 22px clamp(22px, 5vw, 58px);
            border-bottom: 1px solid var(--line);
        }

        .university-brand {
            display: inline-flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .university-brand img {
            display: block;
            width: 52px;
            height: 52px;
            object-fit: contain;
        }

        .brand-copy { display: grid; gap: 3px; }

        .brand-copy strong {
            color: var(--teal-dark);
            font-size: .96rem;
            letter-spacing: .035em;
        }

        .brand-copy span {
            color: var(--muted);
            font-size: .76rem;
        }

        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 10px 14px;
            border: 1px solid #d9eee9;
            border-radius: 999px;
            background: #f0faf7;
            color: #267268;
            font-size: .74rem;
            font-weight: 750;
            letter-spacing: .08em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .live-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--gold);
            box-shadow: 0 0 0 4px rgba(230, 170, 73, .16);
            animation: pulse 2s ease-in-out infinite;
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(250px, .85fr);
            align-items: center;
            gap: clamp(24px, 5vw, 64px);
            padding: clamp(34px, 6vw, 68px) clamp(22px, 5vw, 58px) 40px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin: 0 0 18px;
            color: var(--teal);
            font-size: .73rem;
            font-weight: 800;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        .eyebrow svg { width: 17px; height: 17px; }

        h1 {
            max-width: 620px;
            margin: 0;
            color: var(--teal-dark);
            font-size: clamp(2rem, 4.5vw, 3.65rem);
            font-weight: 760;
            letter-spacing: -.055em;
            line-height: 1.08;
            text-wrap: balance;
        }

        .lead {
            max-width: 570px;
            margin: 20px 0 0;
            color: var(--muted);
            font-size: clamp(.98rem, 1.5vw, 1.08rem);
            line-height: 1.8;
        }

        .illustration {
            position: relative;
            display: grid;
            min-height: 260px;
            place-items: center;
            overflow: hidden;
            border: 1px solid rgba(8, 126, 131, .08);
            border-radius: 28px;
            background:
                radial-gradient(circle at 50% 48%, rgba(255, 255, 255, .95) 0 23%, transparent 24%),
                linear-gradient(145deg, #eff9f7, #f8fbf8 58%, #fbf5e9);
        }

        .orbit {
            position: absolute;
            width: 194px;
            height: 194px;
            border: 1px solid rgba(8, 126, 131, .16);
            border-radius: 50%;
            animation: rotate 24s linear infinite;
        }

        .orbit::before, .orbit::after {
            position: absolute;
            width: 11px;
            height: 11px;
            border: 3px solid #fff;
            border-radius: 50%;
            background: var(--teal);
            box-shadow: 0 2px 8px rgba(7, 84, 95, .18);
            content: "";
        }

        .orbit::before { top: 21px; left: 21px; }
        .orbit::after { right: 7px; bottom: 56px; width: 8px; height: 8px; background: var(--gold); }
        .orbit.orbit-inner { width: 146px; height: 146px; border-style: dashed; animation-direction: reverse; animation-duration: 32s; }
        .orbit.orbit-inner::before { top: 8px; right: 19px; left: auto; width: 7px; height: 7px; background: var(--gold); }
        .orbit.orbit-inner::after { right: auto; bottom: 14px; left: 13px; width: 6px; height: 6px; }

        .visual-center {
            position: relative;
            display: grid;
            width: 104px;
            height: 104px;
            border: 1px solid rgba(8, 126, 131, .1);
            border-radius: 30px;
            background: rgba(255, 255, 255, .9);
            box-shadow: 0 16px 40px rgba(27, 73, 82, .12);
            color: var(--teal);
            place-items: center;
            animation: float 4s ease-in-out infinite;
        }

        .visual-center svg { width: 52px; height: 52px; }

        .float-label {
            position: absolute;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border: 1px solid rgba(255, 255, 255, .9);
            border-radius: 13px;
            background: rgba(255, 255, 255, .88);
            box-shadow: 0 9px 24px rgba(27, 73, 82, .09);
            color: #45646a;
            font-size: .73rem;
            font-weight: 700;
        }

        .float-label svg { width: 16px; height: 16px; color: var(--teal); }
        .float-label.label-top { top: 21px; right: 18px; }
        .float-label.label-bottom { bottom: 23px; left: 18px; }

        .countdown-section {
            margin: 0 clamp(22px, 5vw, 58px);
            padding: 26px 0 0;
            border-top: 1px solid var(--line);
        }

        .countdown-heading {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 17px;
        }

        .countdown-heading strong { color: var(--ink); font-size: .94rem; }
        .countdown-heading span { color: var(--muted); font-size: .8rem; }

        .countdown {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .slot {
            display: grid;
            min-height: 94px;
            padding: 14px 10px;
            border: 1px solid #e6eff0;
            border-radius: 17px;
            background: linear-gradient(160deg, #f8fbfb, #f1f8f6);
            text-align: center;
            align-content: center;
            gap: 7px;
        }

        .slot strong {
            color: var(--teal-dark);
            font-size: clamp(1.7rem, 3.5vw, 2.45rem);
            font-variant-numeric: tabular-nums;
            letter-spacing: -.05em;
            line-height: 1;
        }

        .slot span {
            color: var(--muted);
            font-size: .68rem;
            font-weight: 750;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .status-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 14px;
            padding: 14px 16px;
            border-radius: 14px;
            background: #f6f9f8;
            color: var(--muted);
            font-size: .84rem;
        }

        .status-box strong { color: var(--teal-dark); text-align: right; }

        .footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-top: 28px;
            padding: 20px clamp(22px, 5vw, 58px);
            border-top: 1px solid var(--line);
            color: #829398;
            font-size: .72rem;
        }

        .footer strong { color: #58777c; font-weight: 700; }

        @keyframes pulse {
            50% { box-shadow: 0 0 0 7px rgba(230, 170, 73, .08); }
        }

        @keyframes rotate { to { transform: rotate(360deg); } }
        @keyframes float { 50% { transform: translateY(-7px); } }

        @media (max-width: 700px) {
            body { padding: 14px; }
            .maintenance-card { border-radius: 23px; }
            .topbar { padding: 18px 20px; }
            .university-brand { gap: 10px; }
            .university-brand img { width: 44px; height: 44px; }
            .brand-copy strong { font-size: .84rem; }
            .brand-copy span { font-size: .68rem; }
            .live-badge { padding: 9px 10px; font-size: .62rem; }
            .hero { grid-template-columns: 1fr; gap: 27px; padding: 34px 22px 30px; }
            .illustration { min-height: 220px; }
            .orbit { width: 170px; height: 170px; }
            .orbit.orbit-inner { width: 130px; height: 130px; }
            .countdown-section { margin: 0 22px; }
            .countdown { gap: 8px; }
            .slot { min-height: 82px; border-radius: 13px; }
            .footer { margin-top: 22px; padding: 17px 22px; }
        }

        @media (max-width: 390px) {
            .topbar { align-items: flex-start; }
            .live-badge { gap: 7px; font-size: .56rem; }
            .live-dot { width: 7px; height: 7px; }
            .countdown-heading { align-items: flex-start; flex-direction: column; gap: 5px; }
            .status-box { align-items: flex-start; flex-direction: column; }
            .status-box strong { text-align: left; }
            .footer { align-items: flex-start; flex-direction: column; gap: 7px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
            }
        }
    </style>
</head>
<body>
    <main class="maintenance-card" role="status" aria-live="polite">
        <header class="topbar">
            <div class="university-brand">
                <img src="{{ $maintenanceLogo }}" alt="Logo {{ $maintenanceInstitution }}" onerror="this.onerror=null; this.src='{{ asset('images/landing/icon.png') }}';">
                <div class="brand-copy">
                    <strong>{{ $maintenanceInstitution }}</strong>
                    <span>{{ $maintenanceBranding['app_full_name'] ?? 'SIAKAD (Sistem Informasi Akademik)' }}</span>
                </div>
            </div>
            <span class="live-badge"><span class="live-dot" aria-hidden="true"></span>Pemeliharaan sistem</span>
        </header>

        <section class="hero">
            <div>
                <p class="eyebrow">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M12 3.5 14.2 8l4.8.7-3.5 3.4.8 4.8-4.3-2.3-4.3 2.3.8-4.8L5 8.7 9.8 8 12 3.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                    </svg>
                    Layanan Akademik
                </p>
                <h1>{{ $maintenanceMessage }}</h1>
                <p class="lead">Kami sedang melakukan pemeliharaan dan peningkatan layanan SIAKAD agar pengalaman akademik Anda semakin baik. Terima kasih sudah menunggu; layanan akan kembali sesegera mungkin.</p>
            </div>

            <div class="illustration" aria-hidden="true">
                <span class="orbit"></span>
                <span class="orbit orbit-inner"></span>
                <div class="visual-center">
                    <svg viewBox="0 0 64 64" fill="none">
                        <path d="M36.9 8.8a17.5 17.5 0 0 0-21.1 22.7L8.5 38.8a5.8 5.8 0 0 0 8.2 8.2l7.3-7.3a17.5 17.5 0 0 0 22.7-21.1l-9.2 9.2-9-1.4-1.4-9 9.8-9.6Z" fill="currentColor" opacity=".14"/>
                        <path d="M37 9a17.5 17.5 0 0 0-21.2 22.6l-7.3 7.3a5.8 5.8 0 0 0 8.2 8.2l7.3-7.3A17.5 17.5 0 0 0 46.6 18l-9.2 9.2-9-1.4-1.4-9L37 9Z" stroke="currentColor" stroke-width="2.7" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="m43 43 4 4 9-10" stroke="#e6aa49" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <span class="float-label label-top">
                    <svg viewBox="0 0 24 24" fill="none"><path d="m5 12 4.5 4.5L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Sistem ditingkatkan
                </span>
                <span class="float-label label-bottom">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 3v3m0 12v3M3 12h3m12 0h3M5.6 5.6l2.1 2.1m8.6 8.6 2.1 2.1m0-12.8-2.1 2.1m-8.6 8.6-2.1 2.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    Mohon menunggu
                </span>
            </div>
        </section>

        <section class="countdown-section" aria-label="Informasi waktu pemeliharaan">
            @if ($maintenanceIso)
                <div class="countdown-heading">
                    <strong>Perkiraan layanan kembali</strong>
                    <span>Hitung mundur diperbarui otomatis</span>
                </div>
                <div class="countdown" aria-label="Hitung mundur pemeliharaan">
                    <div class="slot"><strong id="days">00</strong><span>Hari</span></div>
                    <div class="slot"><strong id="hours">00</strong><span>Jam</span></div>
                    <div class="slot"><strong id="minutes">00</strong><span>Menit</span></div>
                    <div class="slot"><strong id="seconds">00</strong><span>Detik</span></div>
                </div>
                <div class="status-box">
                    <span>Estimasi waktu selesai</span>
                    <strong id="target-time">Memuat waktu…</strong>
                </div>
            @else
                <div class="status-box">
                    <span>Proses pemeliharaan sedang berlangsung.</span>
                    <strong>Silakan coba kembali beberapa saat lagi.</strong>
                </div>
            @endif
        </section>

        <footer class="footer">
            <strong>© {{ now()->format('Y') }} {{ $maintenanceInstitution }}</strong>
            <span>SIAKAD · Sistem Informasi Akademik</span>
        </footer>
    </main>

    @if ($maintenanceIso)
        <script>
            (function () {
                const target = new Date(@json($maintenanceIso));
                const daysEl = document.getElementById('days');
                const hoursEl = document.getElementById('hours');
                const minutesEl = document.getElementById('minutes');
                const secondsEl = document.getElementById('seconds');
                const targetTimeEl = document.getElementById('target-time');

                function pad(value) {
                    return String(value).padStart(2, '0');
                }

                function updateCountdown() {
                    const totalMs = target.getTime() - Date.now();
                    if (totalMs <= 0) {
                        daysEl.textContent = '00';
                        hoursEl.textContent = '00';
                        minutesEl.textContent = '00';
                        secondsEl.textContent = '00';
                        targetTimeEl.textContent = 'Sedang berlangsung';
                        return;
                    }

                    const totalSeconds = Math.floor(totalMs / 1000);
                    daysEl.textContent = pad(Math.floor(totalSeconds / 86400));
                    hoursEl.textContent = pad(Math.floor((totalSeconds % 86400) / 3600));
                    minutesEl.textContent = pad(Math.floor((totalSeconds % 3600) / 60));
                    secondsEl.textContent = pad(totalSeconds % 60);

                    targetTimeEl.textContent = new Intl.DateTimeFormat('id-ID', {
                        weekday: 'long',
                        day: '2-digit',
                        month: 'long',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        timeZone: 'Asia/Jayapura'
                    }).format(target) + ' WIT';
                }

                updateCountdown();
                setInterval(updateCountdown, 1000);
            })();
        </script>
    @endif
</body>
</html>
