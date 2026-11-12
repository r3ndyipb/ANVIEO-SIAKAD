@extends('layouts.simak')

@section('title', ($hasMasterDashboard ? 'Dashboard Master | ' : 'Dashboard | ').$siteConfiguration['app_name'])
@section('topbar_title', $hasMasterDashboard ? 'Dashboard Master' : 'Dashboard')

@section('content')
    @if (! $hasMasterDashboard)
        <!-- ========================================================= -->
        <!-- PERSONAL LEARNER / LECTURER DASHBOARD                     -->
        <!-- ========================================================= -->
        <section class="portal-dash-hero">
            <div>
                <div class="portal-dash-hero-title">
                    <h1>Selamat datang, {{ auth()->user()->name ?: auth()->user()->login }}.</h1>
                    <span class="portal-badge-portal">{{ strtoupper($roleLabel) }}</span>
                </div>
                <p>Ruang kerja akademik {{ $siteConfiguration['institution_name'] }} untuk mendukung layanan sesuai tanggung jawab akun Anda.</p>
            </div>
            <div class="portal-dash-scope-tag">
                <span>Peran Aktif:</span>
                <strong>{{ $roleLabel }}</strong>
            </div>
        </section>

        @if (in_array(auth()->user()->role, ['mahasiswa', 'dosen'], true))
            <section style="margin-top: 18px;">
                @if (! $personalActivity['found'])
                    <div class="dashboard-empty">Data aktivitas akademik untuk akun ini belum tersedia.</div>
                @else
                    <div style="margin-bottom: 14px;">
                        <h2 style="font-family: var(--font-heading); font-size: 1.25rem; font-weight: 700; color: var(--brand); margin: 0;">Aktivitas Akademik Saya</h2>
                        <p style="margin: 4px 0 0; color: var(--bx-muted); font-size: 0.78rem;">
                            @if ($personalActivity['type'] === 'student')
                                {{ $personalActivity['identifier'] }} · {{ $personalActivity['name'] }} · {{ $personalActivity['calendar'] ?: 'Kalender aktif belum tersedia' }}
                            @else
                                Ringkasan mata kuliah yang Anda ampu pada kalender aktif.
                            @endif
                        </p>
                    </div>

                    <div class="portal-kpi-grid">
                        @foreach ($personalActivity['cards'] as $card)
                            <article class="portal-kpi-card">
                                <span class="portal-kpi-label">{{ $card['label'] }}</span>
                                <strong class="portal-kpi-value">{{ $card['value'] }}</strong>
                                <small class="portal-kpi-detail">{{ $card['detail'] }}</small>
                            </article>
                        @endforeach
                    </div>

                    @if ($personalActivity['type'] === 'student')
                        <section class="portal-panel" style="margin-top: 18px;">
                            <h2>KRS Semester Aktif</h2>
                            @if ($personalActivity['courses']->isEmpty())
                                <div class="dashboard-empty">Belum ada mata kuliah pada kalender aktif.</div>
                            @else
                                @php
                                    $maxCourseCredits = max(1, (int) $personalActivity['courses']->max(fn ($course): int => (int) $course->sks_mata_kuliah));
                                @endphp
                                <div class="dashboard-visual-list" role="img" aria-label="Grafik SKS mata kuliah semester aktif">
                                    @foreach ($personalActivity['courses'] as $course)
                                        <div class="dashboard-visual-row">
                                            <span class="dashboard-visual-label">{{ $course->nama_mata_kuliah }}</span>
                                            <span class="dashboard-visual-track" data-tooltip="{{ $course->nama_mata_kuliah }} · {{ (int) $course->sks_mata_kuliah }} SKS"><i class="dashboard-visual-bar" style="width: {{ (int) $course->sks_mata_kuliah / $maxCourseCredits * 100 }}%"></i></span>
                                            <strong class="dashboard-visual-value">{{ (int) $course->sks_mata_kuliah }} SKS</strong>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="portal-table-wrap">
                                    <table class="portal-table">
                                        <thead><tr><th>Kode</th><th>Mata Kuliah</th><th>SKS</th><th>Dosen Pengampu</th></tr></thead>
                                        <tbody>@foreach ($personalActivity['courses'] as $course)
                                            <tr><td><code>{{ $course->kode_mata_kuliah }}</code></td><td><strong>{{ $course->nama_mata_kuliah }}</strong></td><td>{{ (int) $course->sks_mata_kuliah }}</td><td>{{ $course->nama_dosen ?: '—' }}</td></tr>
                                        @endforeach</tbody>
                                    </table>
                                </div>
                            @endif
                        </section>
                    @endif
                @endif
            </section>
        @endif
    @else
        <!-- ========================================================= -->
        <!-- EXECUTIVE ADMINISTRATOR MASTER DASHBOARD                  -->
        <!-- ========================================================= -->
        <section class="portal-dash-hero">
            <div>
                <div class="portal-dash-hero-title">
                    <h1>Portal Eksekutif SIAKAD</h1>
                    <span class="portal-badge-portal">ENTERPRISE DASHBOARD</span>
                </div>
                <p>Selamat datang kembali, <strong>{{ auth()->user()->name ?: auth()->user()->login }}</strong> • {{ $siteConfiguration['institution_name'] }}. Ikhtisar civitas akademika, perkuliahan, registrasi KRS, dan operasional institusi.</p>
            </div>

            <div class="portal-dash-actions">
                <div class="portal-dash-scope-tag">
                    <span>Cakupan:</span>
                    <strong>{{ $scopeLabel }}</strong>
                </div>
                <a href="{{ route('academic-dashboard.export.excel') }}" class="portal-btn" title="Export Excel / CSV">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    <span>Export Laporan</span>
                </a>
                <a href="{{ route('akm-feeder.index') }}" class="portal-btn portal-btn-primary" title="Cek Status Sinkronisasi PDDIKTI Feeder">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                    <span>Status Feeder</span>
                </a>
            </div>
        </section>

        <!-- KPI SUMMARY CARDS -->
        <section class="portal-kpi-grid" aria-label="Ringkasan data master">
            @foreach ($cards as $card)
                <article class="portal-kpi-card">
                    <div class="portal-kpi-header">
                        <span class="portal-kpi-label">{{ $card['label'] }}</span>
                        <span class="portal-kpi-icon">
                            @if (str_contains(strtolower($card['label']), 'mahasiswa'))
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            @elseif (str_contains(strtolower($card['label']), 'dosen'))
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><polyline points="16 11 18 13 22 9"></polyline></svg>
                            @elseif (str_contains(strtolower($card['label']), 'dtps'))
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                            @elseif (str_contains(strtolower($card['label']), 'mata kuliah'))
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                            @elseif (str_contains(strtolower($card['label']), 'ruang'))
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"></rect><line x1="9" y1="6" x2="9" y2="6.01"></line><line x1="15" y1="6" x2="15" y2="6.01"></line></svg>
                            @else
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                            @endif
                        </span>
                    </div>
                    <strong class="portal-kpi-value">{{ number_format($card['value']) }}</strong>
                    <small class="portal-kpi-detail">{{ $card['detail'] }}</small>
                </article>
            @endforeach
        </section>

        <!-- INTAKE TREND & DISTRIBUTION CHARTS -->
        <section class="portal-panel" aria-labelledby="student-intake-heading" style="margin-bottom: 18px;">
            <div class="portal-panel-heading">
                <h2 id="student-intake-heading">Tren Mahasiswa Baru per Program Studi</h2>
                <span class="portal-badge-portal" style="font-size: 0.6rem;">HISTORIS</span>
            </div>
            <p class="portal-panel-sub">Jumlah mahasiswa berdasarkan tahun angkatan dari periode masuk, {{ min($intakeYears) }}–{{ max($intakeYears) }}.</p>
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

        <!-- SEBARAN WILAYAH MAHASISWA -->
        <section class="portal-panel" aria-labelledby="student-distribution-heading" style="margin-bottom: 18px;">
            <div class="portal-panel-heading">
                <h2 id="student-distribution-heading">Sebaran Mahasiswa Berdasarkan Wilayah Asal</h2>
                <span class="portal-badge-portal" style="font-size: 0.6rem;">PETA GEOGRAFIS</span>
            </div>
            <p class="portal-panel-sub">Total mahasiswa status A, N, C, dan M: {{ number_format($studentTotal) }}. Peta menampilkan {{ number_format($distributionTotal) }} mahasiswa dengan koordinat provinsi; {{ number_format(max(0, $studentTotal - $distributionTotal)) }} belum terpetakan.</p>
            <div id="student-distribution-map" class="dashboard-map-shell" aria-label="Peta sebaran mahasiswa"></div>
            <div class="portal-table-wrap" style="margin-top:16px">
                @if ($studentDistribution !== [])
                    <table class="portal-table distribution-table">
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
                            @foreach ($studentDistribution as $index => $row)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td><strong>{{ $row['province'] }}</strong></td>
                                    @foreach ($faculties as $faculty)
                                        <td>{{ number_format($row['faculties'][$faculty] ?? 0) }}</td>
                                    @endforeach
                                    <td><strong>{{ number_format($row['total']) }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2">Total Terpetakan</th>
                                @foreach ($faculties as $faculty)
                                    <th>{{ number_format($distributionFacultyTotals[$faculty] ?? 0) }}</th>
                                @endforeach
                                <th>{{ number_format($distributionTotal) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                @endif
            </div>
        </section>

        <!-- STATUS BREAKDOWN & PROGRAM DTPS SUMMARY -->
        <div class="portal-dash-panels">
            <section class="portal-panel" aria-labelledby="student-status-heading">
                <div class="portal-panel-heading">
                    <h2 id="student-status-heading">Status Mahasiswa</h2>
                    <span class="portal-badge-portal" style="font-size: 0.6rem;">AKTIF / CUTI / LULUS</span>
                </div>
                <p class="portal-panel-sub">Persentase distribusi status akademik mahasiswa terdaftar.</p>
                <div class="portal-status-list">
                    @foreach ($studentStatuses as $row)
                        @php
                            $statusCount = (int) $row->total;
                            $pct = $studentTotal > 0 ? round(($statusCount / $studentTotal) * 100, 1) : 0;
                        @endphp
                        <div class="portal-status-row">
                            <div class="portal-status-meta">
                                <span>{{ $row->label }}</span>
                                <strong>{{ number_format($statusCount) }} ({{ $pct }}%)</strong>
                            </div>
                            <div class="portal-status-track">
                                <div class="portal-status-bar" style="width: {{ $pct }}%;"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="portal-panel" aria-labelledby="program-summary-heading">
                <div class="portal-panel-heading">
                    <h2 id="program-summary-heading">Ringkasan Program Studi & DTPS</h2>
                    <span class="portal-badge-portal" style="font-size: 0.6rem;">AKREDITASI</span>
                </div>
                <p class="portal-panel-sub">Statistik mahasiswa, dosen pengampu, dan rasio dosen tetap (DTPS).</p>
                @if ($programSummary !== [])
                    <div class="portal-table-wrap">
                        <table class="portal-table">
                            <thead>
                                <tr>
                                    <th>Program studi</th>
                                    <th>Mahasiswa</th>
                                    <th>Dosen</th>
                                    <th>DTPS</th>
                                    <th>Homebase</th>
                                    <th>Non-Homebase</th>
                                    <th>Mata kuliah</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($programSummary as $program)
                                    <tr data-prodi-id="{{ $program['id'] }}">
                                        <td>
                                            <strong>{{ $program['name'] }}</strong>
                                            <small>{{ $program['faculty'] }}</small>
                                        </td>
                                        <td>{{ number_format($program['students']) }}</td>
                                        <td>{{ number_format($program['lecturers']) }}</td>
                                        <td>
                                            <button class="dtps-detail-trigger" type="button" data-dtps-url="{{ route('dashboard.dtps-details', $program['id']) }}" data-dtps-program="{{ $program['name'] }}" data-dtps-category="all" aria-haspopup="dialog">{{ number_format($program['dtps']) }}</button>
                                        </td>
                                        <td>
                                            <button class="dtps-detail-trigger" type="button" data-dtps-url="{{ route('dashboard.dtps-details', $program['id']) }}" data-dtps-program="{{ $program['name'] }}" data-dtps-category="homebase" aria-haspopup="dialog">{{ number_format($program['dtpsHomebase']) }}</button>
                                        </td>
                                        <td>
                                            <button class="dtps-detail-trigger" type="button" data-dtps-url="{{ route('dashboard.dtps-details', $program['id']) }}" data-dtps-program="{{ $program['name'] }}" data-dtps-category="non_homebase" aria-haspopup="dialog">{{ number_format($program['dtpsNonHomebase']) }}</button>
                                        </td>
                                        <td>{{ number_format($program['courses']) }}</td>
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

        <!-- DTPS Details Dialog -->
        <dialog class="dtps-details-dialog" id="dtps-details-dialog" aria-labelledby="dtps-details-title">
            <div class="dtps-details-heading">
                <div>
                    <p>Rincian DTPS</p>
                    <h2 id="dtps-details-title"></h2>
                </div>
                <button type="button" data-dtps-close aria-label="Tutup rincian DTPS">×</button>
            </div>
            <div class="dtps-details-content" aria-live="polite"></div>
        </dialog>
    @endif
@endsection
