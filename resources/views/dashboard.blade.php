@extends('layouts.simak')

@section('title', ($hasMasterDashboard ? 'Dashboard Master | ' : 'Dashboard | ').$siteConfiguration['app_name'])
@section('topbar_title', $hasMasterDashboard ? 'Dashboard Master' : 'Dashboard')

@push('styles')
    <style>
        .welcome-band { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(190px, .6fr); align-items: center; gap: 28px; width: min(100%, 900px); min-height: 240px; margin: clamp(28px, 8vh, 72px) auto; padding: clamp(26px, 5vw, 48px); border: 1px solid #dce8dc; border-radius: 12px; background: linear-gradient(120deg, #f7fbf2, #fff9df); box-shadow: 0 14px 36px rgba(31, 69, 44, .09); }
        .welcome-band h1 { max-width: 650px; margin: 0; color: #214830; font: 400 clamp(32px, 4vw, 45px)/1.08 Georgia, "Times New Roman", serif; }
        .welcome-band p { max-width: 580px; margin: 13px 0 0; color: #596a52; font-size: 13px; line-height: 1.7; }
        .welcome-kicker { margin: 0 0 11px !important; color: #4e7139 !important; font-size: 10px !important; font-weight: 900; text-transform: uppercase; }
        .welcome-role { padding: 17px 0 17px 20px; border-left: 1px solid #d6e3d4; }
        .welcome-role span { display: block; color: #687742; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .welcome-role strong { display: block; margin-top: 8px; color: #244b32; font: 400 24px/1.2 Georgia, serif; }
        .welcome-role small { display: block; margin-top: 9px; color: #596a42; font-size: 11px; line-height: 1.5; }
        .dashboard-intro { display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; margin-bottom: 26px; padding: clamp(24px, 4vw, 40px); border: 1px solid #dce8dc; border-radius: 10px; background: linear-gradient(120deg, #f7fbf2, #fff9df); }
        .dashboard-intro h1 { max-width: 700px; font-size: clamp(32px, 4vw, 45px); }
        .dashboard-intro p { margin: 11px 0 0; color: #68745e; font-size: 12px; }
        .dashboard-scope { flex: 0 0 auto; max-width: 300px; padding: 12px 15px; border: 1px solid #d8e4d4; border-radius: 7px; background: rgba(255,255,255,.76); }
        .dashboard-scope span { display: block; color: #72815c; font-size: 9px; font-weight: 800; text-transform: uppercase; }
        .dashboard-scope strong { display: block; margin-top: 5px; color: #315b3b; font-size: 12px; }
        .dashboard-cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 13px; margin-bottom: 28px; }
        .dashboard-card { min-height: 116px; padding: 18px; border: 1px solid #e3e9e0; border-radius: 8px; background: #fff; box-shadow: 0 5px 18px rgba(31,69,44,.05); }
        .dashboard-card-label { color: #69745c; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .dashboard-card-value { display: block; margin-top: 9px; color: #275239; font: 400 31px/1 Georgia, serif; }
        .dashboard-card-detail { display: block; margin-top: 7px; color: #889078; font-size: 10px; }
        .dashboard-panels { display: grid; grid-template-columns: minmax(260px, .75fr) minmax(0, 1.25fr); gap: 18px; }
        .dashboard-panel { min-width: 0; padding: 20px; border: 1px solid #e3e9e0; border-radius: 8px; background: #fff; }
        .dashboard-panel h2 { margin: 0; font: 400 22px/1.2 Georgia, serif; }
        .dashboard-panel > p { margin: 7px 0 18px; color: #77806b; font-size: 11px; }
        .status-list { display: grid; gap: 15px; }
        .status-row { display: grid; gap: 7px; }
        .status-label { display: flex; justify-content: space-between; gap: 14px; color: #445a44; font-size: 11px; }
        .status-track { position: relative; height: 8px; border-radius: 99px; background: #edf1e8; }
        .status-bar { height: 100%; min-width: 2px; border-radius: inherit; background: linear-gradient(90deg, #7eaa6b, #e4c54b); }
        .dashboard-table-wrap { overflow-x: auto; }
        .dashboard-table { width: 100%; min-width: 660px; border-collapse: collapse; }
        .dashboard-table th, .dashboard-table td { padding: 10px 9px; border-bottom: 1px solid #edf0e8; text-align: left; font-size: 10px; }
        .dashboard-table th { color: #71805e; font-size: 9px; font-weight: 800; text-transform: uppercase; }
        .dashboard-table td:not(:first-child), .dashboard-table th:not(:first-child) { text-align: right; }
        .dashboard-table td:first-child strong, .dashboard-table td:first-child small { display: block; }
        .dashboard-table td:first-child strong { color: #31533b; font-size: 11px; }
        .dashboard-table td:first-child small { margin-top: 4px; color: #89917c; font-size: 9px; }
        .dtps-detail-trigger { padding: 2px 4px; border: 0; border-radius: 3px; background: transparent; color: #2d6841; font: inherit; font-weight: 800; text-decoration: underline; text-underline-offset: 2px; cursor: pointer; }
        .dtps-detail-trigger:hover, .dtps-detail-trigger:focus-visible { background: #edf5e9; color: #1d4c30; }
        .dtps-details-dialog { width: min(900px, calc(100% - 32px)); max-height: min(82vh, 760px); margin: auto; padding: 22px; border: 1px solid #dce7d7; border-radius: 8px; color: #405340; box-shadow: 0 18px 55px #203c2638; }
        .dtps-details-dialog::backdrop { background: #18271db3; }
        .dtps-details-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
        .dtps-details-heading p { margin: 0 0 5px; color: #71805e; font-size: 9px; font-weight: 800; text-transform: uppercase; }
        .dtps-details-heading h2 { margin: 0; color: #294c35; font: 400 22px/1.25 Georgia, serif; }
        .dtps-details-heading button { width: 34px; height: 34px; border: 1px solid #dce7d7; border-radius: 50%; background: #fff; color: #405340; font-size: 21px; cursor: pointer; }
        .dtps-details-content { overflow: auto; max-height: calc(82vh - 100px); }
        .dtps-details-table { width: 100%; border-collapse: collapse; }
        .dtps-details-table th, .dtps-details-table td { padding: 10px; border-bottom: 1px solid #e8eee5; text-align: left; vertical-align: top; font-size: 11px; }
        .dtps-details-table th { color: #71805e; font-size: 9px; text-transform: uppercase; }
        .dtps-details-table ul { display: grid; gap: 4px; margin: 0; padding-left: 18px; }
        .dtps-details-message, .dtps-details-error { margin: 0; padding: 16px; border-radius: 5px; background: #f5f8f2; color: #52634d; font-size: 11px; }
        .dtps-details-error { background: #fff1ee; color: #9b3e32; }
        .dashboard-visual-list { display: grid; gap: 11px; margin: 0 0 16px; }
        .dashboard-visual-row { display: grid; grid-template-columns: minmax(115px, 220px) minmax(65px, 1fr) auto; align-items: center; gap: 10px; color: #4d604d; font-size: 10px; }
        .dashboard-visual-label { overflow-wrap: anywhere; }
        .dashboard-visual-track { position: relative; display: flex; height: 10px; border-radius: 99px; background: #edf1e8; }
        .dashboard-visual-track::after, .status-track::after { position: absolute; z-index: 4; bottom: calc(100% + 8px); left: 50%; max-width: min(280px, 80vw); padding: 7px 9px; transform: translate(-50%, 4px); border: 1px solid #dce7d7; border-radius: 6px; background: #fffef6; color: #405340; box-shadow: 0 5px 16px rgba(28,61,38,.16); content: attr(data-tooltip); font-size: 10px; font-weight: 700; line-height: 1.4; opacity: 0; pointer-events: none; transition: opacity .12s ease, transform .12s ease; visibility: hidden; white-space: nowrap; }
        .dashboard-visual-track:hover::after, .dashboard-visual-track:focus-within::after, .status-track:hover::after, .status-track:focus-within::after { transform: translate(-50%, 0); opacity: 1; visibility: visible; }
        .dashboard-visual-bar { display: block; height: 100%; min-width: 0; background: #4b7ca8; }
        .dashboard-visual-bar.is-green { background: #4f9467; }
        .dashboard-visual-bar.is-gold { background: #c9a13b; }
        .dashboard-visual-bar.is-rust { background: #b86c56; }
        .dashboard-visual-value { color: #354f3a; font-weight: 800; white-space: nowrap; }
        .dashboard-visual-legend { display: flex; flex-wrap: wrap; gap: 8px 13px; margin: 0 0 13px; color: #6c7865; font-size: 9px; }
        .dashboard-visual-legend span { display: inline-flex; align-items: center; gap: 5px; }
        .dashboard-visual-legend i { width: 8px; height: 8px; border-radius: 50%; background: #4b7ca8; }
        .dashboard-visual-legend .is-green { background: #4f9467; }
        .dashboard-visual-legend .is-gold { background: #c9a13b; }
        .dashboard-visual-legend .is-rust { background: #b86c56; }
        .dashboard-empty { padding: 22px 0; color: #6d775e; font-size: 12px; }
        .dashboard-chart { min-width: 0; height: 345px; }
        .dashboard-chart-scroll { overflow-x: auto; }
        .dashboard-chart-plot { position: relative; min-width: 720px; height: 417px; }
        .dashboard-chart-plot svg { display: block; width: 100%; height: 100%; }
        .dashboard-chart-grid { stroke: #e7ede4; stroke-width: 1; }
        .dashboard-chart-label { fill: #71805e; font: 11px "Avenir Next", Avenir, "Segoe UI", sans-serif; }
        .dashboard-chart-line { fill: none; stroke-width: 2.6; stroke-linecap: round; stroke-linejoin: round; }
        .dashboard-chart-point { stroke: #fff; stroke-width: 2; }
        .dashboard-chart-point { cursor: crosshair; transition: r .12s ease, stroke-width .12s ease; }
        .dashboard-chart-point:hover, .dashboard-chart-point:focus { r: 7; stroke-width: 3; outline: none; }
        .dashboard-chart-tooltip { position: absolute; z-index: 2; min-width: 190px; max-width: 280px; padding: 10px 12px; border: 1px solid #dce7d7; border-radius: 8px; background: rgba(255, 254, 246, .98); color: #405340; box-shadow: 0 8px 24px rgba(28, 61, 38, .18); font-size: 11px; line-height: 1.5; pointer-events: none; }
        .dashboard-chart-tooltip[hidden] { display: none; }
        .dashboard-chart-tooltip strong, .dashboard-chart-tooltip span { display: block; }
        .dashboard-chart-tooltip strong { color: #2e6d4a; font-size: 12px; }
        .dashboard-chart-tooltip span + span { margin-top: 2px; }
        .dashboard-chart-legend { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr)); align-content: start; gap: 6px 10px; max-height: 180px; overflow-y: auto; margin-top: 14px; padding-right: 4px; }
        .dashboard-chart-legend button { display: flex; width: 100%; min-width: 0; align-items: center; gap: 8px; padding: 7px 9px; border: 1px solid #e3e9e0; border-radius: 6px; background: #fff; color: #455a43; font-size: 10px; line-height: 1.4; text-align: left; white-space: normal; overflow-wrap: anywhere; cursor: pointer; }
        .dashboard-chart-legend button[aria-pressed="false"] { opacity: .45; text-decoration: line-through; }
        .dashboard-chart-swatch { width: 9px; height: 9px; border-radius: 50%; }
        .dashboard-chart-legend button > span:last-child { min-width: 0; }
        .dashboard-map-shell { overflow: hidden; border: 1px solid #e1e9df; border-radius: 8px; background: #eaf2eb; }
        #student-distribution-map { width: 100%; height: 500px; }
        .leaflet-container { font: 11px "Avenir Next", Avenir, "Segoe UI", sans-serif; }
        .leaflet-popup-content-wrapper, .leaflet-popup-tip { background: #fffef6; }
        .map-popup { min-width: 190px; color: #405340; }
        .map-popup strong { color: #2e6d4a; font-size: 13px; }
        .map-popup ul { margin: 8px 0; padding-left: 16px; }
        .map-popup-total { padding-top: 7px; border-top: 1px solid #e4eadf; font-weight: 800; }
        .distribution-table { min-width: 700px; }
        .distribution-table tfoot th { border-top: 2px solid #cedbc9; background: #f5f8f1; color: #34553a; }
        .map-empty { display: grid; min-height: 260px; place-items: center; padding: 28px; color: #6d775e; text-align: center; }
        .personal-activity { margin-top: 26px; }
        .personal-activity-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 14px; }
        .personal-activity-heading h2 { margin: 0; color: #254b32; font: 400 25px/1.2 Georgia, serif; }
        .personal-activity-heading p { margin: 6px 0 0; color: #69745c; font-size: 11px; }
        .personal-activity-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(175px, 1fr)); gap: 12px; margin-bottom: 16px; }
        .personal-activity-panel { min-width: 0; margin-top: 16px; padding: 18px; border: 1px solid #e3e9e0; border-radius: 8px; background: #fff; }
        .personal-activity-panel h3 { margin: 0 0 12px; color: #31533b; font: 400 19px/1.2 Georgia, serif; }
        .personal-activity-table-wrap { overflow-x: auto; }
        .personal-activity-table { width: 100%; min-width: 560px; border-collapse: collapse; }
        .personal-activity-table th, .personal-activity-table td { padding: 9px; border-bottom: 1px solid #edf0e8; text-align: left; font-size: 11px; }
        .personal-activity-table th { color: #71805e; font-size: 9px; text-transform: uppercase; }
        .personal-activity-empty { padding: 16px 0; color: #6d775e; font-size: 12px; }
        .personal-activity-links { display: flex; flex-wrap: wrap; gap: 9px; margin-top: 15px; }
        @media (max-width: 860px) { .dashboard-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); } .dashboard-panels { grid-template-columns: 1fr; } }
        @media (max-width: 620px) { .welcome-band { grid-template-columns: 1fr; gap: 18px; min-height: auto; margin: 24px auto; padding: 25px 22px; } .welcome-role { padding: 14px 0 0; border-top: 1px solid #d6e3d4; border-left: 0; } .dashboard-intro { align-items: stretch; flex-direction: column; } .dashboard-scope { max-width: none; } .dashboard-cards { gap: 9px; } .dashboard-card { min-height: 105px; padding: 14px; } .dashboard-card-value { font-size: 27px; } #student-distribution-map { height: 390px; } }
    </style>
@endpush

@section('content')
    @if (! $hasMasterDashboard)
        <section class="welcome-band">
            <div>
                <p class="welcome-kicker">Sistem Informasi Akademik</p>
                <h1>Selamat datang, {{ auth()->user()->name ?: auth()->user()->login }}.</h1>
                <p>Ruang kerja akademik {{ $siteConfiguration['institution_name'] }} untuk mendukung layanan sesuai tanggung jawab akun Anda.</p>
            </div>
            <div class="welcome-role">
                <span>Role aktif</span>
                <strong>{{ $roleLabel }}</strong>
                <small>Hak akses mengikuti pengaturan SIAKAD.</small>
            </div>
        </section>
        @if (in_array(auth()->user()->role, ['mahasiswa', 'dosen'], true))
            <section class="personal-activity" aria-labelledby="personal-activity-heading">
                @if (! $personalActivity['found'])
                    <div class="dashboard-empty">Data aktivitas akademik untuk akun ini belum tersedia.</div>
                @else
                    <div class="personal-activity-heading">
                        <div>
                            <h2 id="personal-activity-heading">Aktivitas Akademik Saya</h2>
                            <p>
                                @if ($personalActivity['type'] === 'student')
                                    {{ $personalActivity['identifier'] }} · {{ $personalActivity['name'] }} · {{ $personalActivity['calendar'] ?: 'Kalender aktif belum tersedia' }}
                                @else
                                    Ringkasan mata kuliah yang Anda ampu pada kalender aktif.
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="personal-activity-cards" aria-label="Ringkasan aktivitas akademik pribadi">
                        @foreach ($personalActivity['cards'] as $card)
                            <article class="dashboard-card">
                                <span class="dashboard-card-label">{{ $card['label'] }}</span>
                                <strong class="dashboard-card-value">{{ $card['value'] }}</strong>
                                <small class="dashboard-card-detail">{{ $card['detail'] }}</small>
                            </article>
                        @endforeach
                    </div>

                    @if ($personalActivity['type'] === 'student')
                        <section class="personal-activity-panel">
                            <h3>KRS Semester Aktif</h3>
                            @if ($personalActivity['courses']->isEmpty())
                                <div class="personal-activity-empty">Belum ada mata kuliah pada kalender aktif.</div>
                            @else
                                @php($maxCourseCredits = max(1, (int) $personalActivity['courses']->max(fn ($course): int => (int) $course->sks_mata_kuliah)))
                                <div class="dashboard-visual-list" role="img" aria-label="Grafik SKS mata kuliah semester aktif">
                                    @foreach ($personalActivity['courses'] as $course)
                                        <div class="dashboard-visual-row">
                                            <span class="dashboard-visual-label">{{ $course->nama_mata_kuliah }}</span>
                                            <span class="dashboard-visual-track" data-tooltip="{{ $course->nama_mata_kuliah }} · {{ (int) $course->sks_mata_kuliah }} SKS"><i class="dashboard-visual-bar" style="width: {{ (int) $course->sks_mata_kuliah / $maxCourseCredits * 100 }}%"></i></span>
                                            <strong class="dashboard-visual-value">{{ (int) $course->sks_mata_kuliah }} SKS</strong>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="personal-activity-table-wrap"><table class="personal-activity-table">
                                    <thead><tr><th>Kode</th><th>Mata Kuliah</th><th>SKS</th><th>Nilai</th></tr></thead>
                                    <tbody>@foreach ($personalActivity['courses'] as $course)
                                        <tr><td>{{ $course->kode_mata_kuliah ?: '-' }}</td><td>{{ $course->nama_mata_kuliah }}</td><td>{{ $course->sks_mata_kuliah }}</td><td>{{ $course->nilai_huruf ?: 'Belum ada' }}</td></tr>
                                    @endforeach</tbody>
                                </table></div>
                            @endif
                            <div class="personal-activity-links">
                                <a class="button button-small" href="{{ route('krs.index') }}">Buka KRS</a>
                                <a class="button button-small" href="{{ route('transcripts.index') }}">Lihat Transkrip</a>
                            </div>
                        </section>
                        <section class="personal-activity-panel">
                            <h3>Riwayat Indeks Prestasi</h3>
                            @if ($personalActivity['history']->isEmpty())
                                <div class="personal-activity-empty">Riwayat AKM belum tersedia.</div>
                            @else
                                <div class="dashboard-visual-legend" aria-label="Legenda grafik indeks prestasi">
                                    <span><i></i>IPS</span><span><i class="is-green"></i>IPK</span>
                                </div>
                                <div class="dashboard-visual-list" role="img" aria-label="Grafik riwayat IPS dan IPK maksimal 4">
                                    @foreach ($personalActivity['history'] as $record)
                                        @php($termLabel = $record->namatahun ?: $record->calendar_year ?: $record->tahun)
                                        <div class="dashboard-visual-row">
                                            <span class="dashboard-visual-label">{{ $termLabel }}</span>
                                            <span class="dashboard-visual-track" data-tooltip="IPS {{ $record->ips }} · {{ $record->namatahun ?: $record->calendar_year }}"><i class="dashboard-visual-bar" style="width: {{ min(100, max(0, (float) $record->ips / 4 * 100)) }}%"></i></span>
                                            <strong class="dashboard-visual-value">{{ $record->ips ?: '-' }} / {{ $record->ipk ?: '-' }}</strong>
                                        </div>
                                        <div class="dashboard-visual-row">
                                            <span class="dashboard-visual-label">{{ $termLabel }} · IPK</span>
                                            <span class="dashboard-visual-track" data-tooltip="IPK {{ $record->ipk }} · {{ $record->namatahun ?: $record->calendar_year }}"><i class="dashboard-visual-bar is-green" style="width: {{ min(100, max(0, (float) $record->ipk / 4 * 100)) }}%"></i></span>
                                            <strong class="dashboard-visual-value">{{ $record->ipk ?: '-' }}</strong>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="personal-activity-table-wrap"><table class="personal-activity-table">
                                    <thead><tr><th>Semester</th><th>IPS</th><th>IPK</th><th>SKS Semester</th><th>SKS Lulus</th></tr></thead>
                                    <tbody>@foreach ($personalActivity['history'] as $record)
                                        <tr><td>{{ $record->namatahun ?: $record->calendar_year ?: $record->tahun }}</td><td>{{ $record->ips ?: '-' }}</td><td>{{ $record->ipk ?: '-' }}</td><td>{{ $record->sks_semester ?: '-' }}</td><td>{{ $record->sks_lulus ?: '-' }}</td></tr>
                                    @endforeach</tbody>
                                </table></div>
                            @endif
                        </section>
                    @else
                        <section class="personal-activity-panel">
                            <h3>Jadwal Mengajar Kalender Aktif</h3>
                            @if ($personalActivity['schedules']->isEmpty())
                                <div class="personal-activity-empty">Belum ada jadwal mengajar yang ditugaskan pada kalender aktif.</div>
                            @else
                                @php($maxPlannedSessions = max(1, (int) $personalActivity['schedules']->max(fn ($schedule): int => (int) $schedule->rencana)))
                                <div class="dashboard-visual-legend" aria-label="Legenda grafik aktivitas mengajar">
                                    <span><i></i>Rencana</span><span><i class="is-green"></i>Realisasi</span>
                                </div>
                                <div class="dashboard-visual-list" role="img" aria-label="Grafik perbandingan rencana dan realisasi tatap muka dosen">
                                    @foreach ($personalActivity['schedules'] as $schedule)
                                        @php($courseLabel = $schedule->nama_mata_kuliah.' · '.$schedule->nama_kelas)
                                        <div class="dashboard-visual-row">
                                            <span class="dashboard-visual-label">{{ $courseLabel }}</span>
                                            <span class="dashboard-visual-track" data-tooltip="Tatap muka direncanakan · {{ $schedule->nama_mata_kuliah }} · {{ (int) $schedule->rencana }}"><i class="dashboard-visual-bar" style="width: {{ (int) $schedule->rencana / $maxPlannedSessions * 100 }}%"></i></span>
                                            <strong class="dashboard-visual-value">{{ (int) $schedule->realisasi }} / {{ (int) $schedule->rencana }}</strong>
                                        </div>
                                        <div class="dashboard-visual-row">
                                            <span class="dashboard-visual-label">{{ $courseLabel }} · Realisasi</span>
                                            <span class="dashboard-visual-track" data-tooltip="Tatap muka terealisasi · {{ $schedule->nama_mata_kuliah }} · {{ (int) $schedule->realisasi }}"><i class="dashboard-visual-bar is-green" style="width: {{ min(100, (int) $schedule->realisasi / $maxPlannedSessions * 100) }}%"></i></span>
                                            <strong class="dashboard-visual-value">{{ (int) $schedule->realisasi }}</strong>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="personal-activity-table-wrap"><table class="personal-activity-table">
                                    <thead><tr><th>Semester</th><th>Program Studi</th><th>Mata Kuliah</th><th>Kelas</th><th>Rencana</th><th>Realisasi</th></tr></thead>
                                    <tbody>@foreach ($personalActivity['schedules'] as $schedule)
                                        <tr><td>{{ $schedule->namatahun ?: $schedule->tahun }}</td><td>{{ $schedule->nama_program_studi }}</td><td>{{ $schedule->nama_mata_kuliah }}</td><td>{{ $schedule->nama_kelas }}</td><td>{{ $schedule->rencana ?: 0 }}</td><td>{{ $schedule->realisasi ?: 0 }}</td></tr>
                                    @endforeach</tbody>
                                </table></div>
                            @endif
                            <div class="personal-activity-links"><a class="button button-small" href="{{ route('class-schedules.index') }}">Lihat Jadwal Kuliah</a></div>
                        </section>
                    @endif
                @endif
            </section>
        @endif
    @else
        <section class="dashboard-intro">
            <div>
                <p class="eyebrow">Pemantauan Data Master</p>
                <h1>Dashboard Master</h1>
                <p>{{ $siteConfiguration['institution_name'] }} · Ringkasan data akademik untuk {{ auth()->user()->name ?: auth()->user()->login }}.</p>
            </div>
            <div class="dashboard-scope">
                <span>Cakupan data</span>
                <strong>{{ $scopeLabel }}</strong>
            </div>
        </section>

        <section class="dashboard-cards" aria-label="Ringkasan data master">
            @foreach ($cards as $card)
                <article class="dashboard-card">
                    <span class="dashboard-card-label">{{ $card['label'] }}</span>
                    <strong class="dashboard-card-value">{{ number_format($card['value']) }}</strong>
                    <small class="dashboard-card-detail">{{ $card['detail'] }}</small>
                </article>
            @endforeach
        </section>

        <section class="dashboard-panel" aria-labelledby="student-intake-heading">
            <h2 id="student-intake-heading">Tren Mahasiswa Baru per Program Studi</h2>
            <p>Jumlah mahasiswa berdasarkan tahun angkatan dari periode masuk, {{ min($intakeYears) }}–{{ max($intakeYears) }}.</p>
            <div id="dashboard-visualization-data"
                data-years='@json($intakeYears)'
                data-series='@json($intakeSeries)'
                data-faculties='@json($faculties)'
                data-distribution='@json($studentDistribution)'
                data-distribution-total="{{ $distributionTotal }}"
                data-faculty-totals='@json($distributionFacultyTotals)'></div>
            <div id="student-intake-chart" class="dashboard-chart-scroll" aria-label="Grafik tren mahasiswa baru"></div>
            @if (collect($intakeSeries)->every(fn (array $series): bool => array_sum($series['values']) === 0))
                <div class="dashboard-empty">Belum ada data angkatan mahasiswa dalam enam tahun terakhir.</div>
            @endif
        </section>

        <section class="dashboard-panel" aria-labelledby="student-distribution-heading" style="margin-top:18px">
            <h2 id="student-distribution-heading">Sebaran Mahasiswa</h2>
            <p>Total mahasiswa status A, N, C, dan M: {{ number_format($studentTotal) }}. Peta menampilkan {{ number_format($distributionTotal) }} mahasiswa dengan data provinsi; {{ number_format(max(0, $studentTotal - $distributionTotal)) }} belum terpetakan.</p>
            <div id="student-distribution-map" class="dashboard-map-shell" aria-label="Peta sebaran mahasiswa"></div>
            <div class="dashboard-table-wrap" style="margin-top:18px">
                @if ($studentDistribution !== [])
                    <table class="dashboard-table distribution-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Provinsi</th>
                                @foreach ($faculties as $faculty)
                                    <th>{{ str_replace('FAKULTAS ', '', mb_strtoupper($faculty)) }}</th>
                                @endforeach
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($studentDistribution as $province)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td><strong>{{ $province['province'] }}</strong></td>
                                    @foreach ($faculties as $faculty)
                                        <td>{{ number_format($province['faculties'][$faculty] ?? 0) }}</td>
                                    @endforeach
                                    <td><strong>{{ number_format($province['total']) }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2">TOTAL TERPETAKAN</th>
                                @foreach ($faculties as $faculty)
                                    <th>{{ number_format($distributionFacultyTotals[$faculty] ?? 0) }}</th>
                                @endforeach
                                <th>{{ number_format($distributionTotal) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                @else
                    <div class="map-empty">Belum ada data mahasiswa aktif dengan informasi wilayah pada cakupan akun ini.</div>
                @endif
            </div>
        </section>

        <div class="dashboard-panels" style="margin-top:18px">
            <section class="dashboard-panel" aria-labelledby="student-status-heading">
                <h2 id="student-status-heading">Status mahasiswa</h2>
                <p>{{ number_format($studentTotal) }} mahasiswa dalam cakupan data.</p>
                @if ($studentStatuses->isNotEmpty())
                    <div class="status-list">
                        @foreach ($studentStatuses as $status)
                            @php($percentage = $studentTotal > 0 ? round(($status->total / $studentTotal) * 100) : 0)
                            <div class="status-row">
                                <div class="status-label">
                                    <span>{{ $status->label }}</span>
                                    <strong>{{ number_format($status->total) }} · {{ $percentage }}%</strong>
                                </div>
                                <div class="status-track" role="img" aria-label="{{ $status->label }}: {{ $percentage }}%" data-tooltip="{{ $status->label }} · {{ number_format($status->total) }} · {{ $percentage }}%">
                                    <div class="status-bar" style="width: {{ $percentage }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="dashboard-empty">Belum ada data mahasiswa pada cakupan ini.</div>
                @endif
            </section>

            <section class="dashboard-panel" aria-labelledby="program-summary-heading">
                <h2 id="program-summary-heading">Ringkasan per program studi</h2>
                <p>DTPS dihitung sebagai dosen unik yang tercantum pada jadwal seluruh semester; homebase mengikuti program studi pada data dosen.</p>
                @if ($programSummary->isNotEmpty())
                    @php($summaryMetrics = [['Mahasiswa', 'students', ''], ['Dosen', 'lecturers', 'is-green'], ['DTPS homebase', 'dtpsHomebase', 'is-gold'], ['DTPS non-homebase', 'dtpsNonHomebase', 'is-rust'], ['Asisten', 'assistants', 'is-gold'], ['Mata kuliah', 'courses', 'is-rust'], ['Ruang', 'rooms', '']])
                    @php($summaryMaxima = collect($summaryMetrics)->mapWithKeys(fn (array $metric): array => [$metric[1] => max(1, (int) $programSummary->max($metric[1]))]))
                    <div class="dashboard-visual-legend" aria-label="Legenda grafik ringkasan per program studi">
                        @foreach ($summaryMetrics as [$label, $field, $color])
                            <span><i class="{{ $color }}"></i>{{ $label }}</span>
                        @endforeach
                    </div>
                    <div class="dashboard-visual-list" role="img" aria-label="Grafik jumlah mahasiswa, dosen, DTPS homebase dan non-homebase, asisten, mata kuliah, dan ruang per program studi">
                        @foreach ($programSummary as $program)
                            @foreach ($summaryMetrics as [$label, $field, $color])
                                <div class="dashboard-visual-row">
                                    <span class="dashboard-visual-label">{{ $program['name'] }} · {{ $label }}</span>
                                    <span class="dashboard-visual-track" data-tooltip="{{ $program['name'] }} · {{ $label }} · {{ number_format($program[$field]) }}"><i class="dashboard-visual-bar {{ $color }}" style="width: {{ $program[$field] / $summaryMaxima[$field] * 100 }}%"></i></span>
                                    @if (in_array($field, ['dtpsHomebase', 'dtpsNonHomebase'], true))
                                        <button class="dashboard-visual-value dtps-detail-trigger" type="button"
                                            data-dtps-url="{{ route('dashboard.dtps-details', $program['id']) }}"
                                            data-dtps-program="{{ $program['name'] }}"
                                            data-dtps-category="{{ $field === 'dtpsHomebase' ? 'homebase' : 'non_homebase' }}"
                                            aria-label="{{ $program['name'] }} · {{ $label }} · {{ number_format($program[$field]) }}"
                                            aria-haspopup="dialog">{{ number_format($program[$field]) }}</button>
                                    @else
                                        <strong class="dashboard-visual-value">{{ number_format($program[$field]) }}</strong>
                                    @endif
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                    <div class="dashboard-table-wrap">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Program studi</th>
                                    <th>Mahasiswa</th>
                                    <th>Dosen</th>
                                    <th>DTPS</th>
                                    <th>Homebase</th>
                                    <th>Non-homebase</th>
                                    <th>Asisten</th>
                                    <th>Mata kuliah</th>
                                    <th>Ruang</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($programSummary as $program)
                                    <tr>
                                        <td><strong>{{ $program['name'] }}</strong><small>{{ $program['faculty'] }}</small></td>
                                        <td>{{ number_format($program['students']) }}</td>
                                        <td>{{ number_format($program['lecturers']) }}</td>
                                        <td><button class="dtps-detail-trigger" type="button" data-dtps-url="{{ route('dashboard.dtps-details', $program['id']) }}" data-dtps-program="{{ $program['name'] }}" data-dtps-category="all" aria-haspopup="dialog">{{ number_format($program['dtps']) }}</button></td>
                                        <td><button class="dtps-detail-trigger" type="button" data-dtps-url="{{ route('dashboard.dtps-details', $program['id']) }}" data-dtps-program="{{ $program['name'] }}" data-dtps-category="homebase" aria-haspopup="dialog">{{ number_format($program['dtpsHomebase']) }}</button></td>
                                        <td><button class="dtps-detail-trigger" type="button" data-dtps-url="{{ route('dashboard.dtps-details', $program['id']) }}" data-dtps-program="{{ $program['name'] }}" data-dtps-category="non_homebase" aria-haspopup="dialog">{{ number_format($program['dtpsNonHomebase']) }}</button></td>
                                        <td>{{ number_format($program['assistants']) }}</td>
                                        <td>{{ number_format($program['courses']) }}</td>
                                        <td>{{ number_format($program['rooms']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="dashboard-empty">Cakupan program studi akun ini belum ditetapkan atau datanya belum tersedia.</div>
                @endif
            </section>
        </div>
        <dialog class="dtps-details-dialog" id="dtps-details-dialog" aria-labelledby="dtps-details-title">
            <div class="dtps-details-heading">
                <div><p>Rincian DTPS</p><h2 id="dtps-details-title"></h2></div>
                <button type="button" data-dtps-close aria-label="Tutup rincian DTPS">×</button>
            </div>
            <div class="dtps-details-content" aria-live="polite"></div>
        </dialog>
    @endif
@endsection
