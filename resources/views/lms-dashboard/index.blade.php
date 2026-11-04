@extends('layouts.simak')

@section('title', 'Dashboard LMS | SIAKAD')
@section('topbar_title', 'Dashboard LMS')

@push('styles')
    <style>
        .lms-dashboard-head { display:flex; align-items:flex-end; justify-content:space-between; gap:22px; margin-bottom:18px; padding:28px clamp(18px,4vw,34px); border:1px solid #d7e3dd; border-radius:8px; background:linear-gradient(115deg,#edf5ef,#fbf5df); }
        .lms-dashboard-head h1 { margin:0; color:#294c35; font:400 34px/1.15 Georgia,serif; }
        .lms-dashboard-head p { max-width:700px; margin:9px 0 0; color:#60715f; font-size:12px; line-height:1.6; }
        .lms-dashboard-scope { flex:0 0 auto; min-width:190px; padding-left:16px; border-left:1px solid #d0ded4; }
        .lms-dashboard-scope span { display:block; color:#75815e; font-size:9px; font-weight:800; text-transform:uppercase; }
        .lms-dashboard-scope strong { display:block; margin-top:5px; color:#315b3b; font-size:12px; line-height:1.5; }
        .lms-dashboard-filter { display:grid; grid-template-columns:minmax(240px,430px) auto; align-items:end; gap:12px; margin:16px 0 20px; padding:16px; border:1px solid #e1e8e1; border-radius:7px; background:#fbfcfa; }
        .lms-dashboard-filter label { display:block; margin-bottom:7px; color:#43543c; font-size:11px; font-weight:800; }
        .lms-dashboard-filter select { width:100%; min-height:39px; }
        .lms-dashboard-filter button { min-height:39px; padding:0 15px; border:0; border-radius:5px; background:#315b3b; color:#fff; font-size:11px; font-weight:700; cursor:pointer; }
        .lms-dashboard-note { grid-column:1/-1; margin:0; color:#75806d; font-size:10px; line-height:1.5; }
        .lms-dashboard-term { display:inline-flex; align-items:center; gap:7px; margin-bottom:14px; padding:7px 10px; border:1px solid #d8e5d5; border-radius:5px; background:#f1f7ee; color:#376347; font-size:10px; font-weight:800; }
        .lms-dashboard-term.is-inactive { border-color:#f0dfb5; background:#fff8e7; color:#8b641e; }
        .lms-dashboard-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr)); gap:10px; }
        .lms-dashboard-card { min-width:0; padding:16px; border:1px solid #e0e8e0; border-top:3px solid #689070; border-radius:6px; background:#fff; }
        .lms-dashboard-card:nth-child(3n+2) { border-top-color:#d0a83d; }
        .lms-dashboard-card:nth-child(3n) { border-top-color:#4c7eae; }
        .lms-dashboard-card-label { color:#69745c; font-size:10px; font-weight:800; text-transform:uppercase; }
        .lms-dashboard-card-value { display:block; margin-top:9px; color:#244a32; font:400 29px/1 Georgia,serif; }
        .lms-dashboard-track { height:7px; overflow:hidden; margin-top:12px; border-radius:8px; background:#edf1e9; }
        .lms-dashboard-bar { display:block; width:var(--bar-width); height:100%; border-radius:inherit; background:#5c9670; }
        .lms-dashboard-card:nth-child(3n+2) .lms-dashboard-bar { background:#c9a13b; }
        .lms-dashboard-card:nth-child(3n) .lms-dashboard-bar { background:#537da8; }
        .lms-dashboard-card small { display:block; margin-top:7px; color:#7a8475; font-size:9px; }
        .lms-dashboard-empty { padding:26px 18px; border:1px dashed #d5dfd3; border-radius:6px; background:#fbfdfb; color:#68745f; font-size:12px; line-height:1.6; text-align:center; }
        .lms-attendance-section { margin-top:24px; }
        .lms-attendance-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-bottom:12px; }
        .lms-attendance-heading h2 { margin:0; color:#294c35; font:400 24px/1.2 Georgia,serif; }
        .lms-attendance-heading p { margin:6px 0 0; color:#69745c; font-size:11px; line-height:1.5; }
        .lms-attendance-legend { display:flex; flex-wrap:wrap; gap:12px; color:#56634f; font-size:10px; }
        .lms-attendance-legend span { display:inline-flex; align-items:center; gap:5px; }
        .lms-attendance-legend i { width:9px; height:9px; border-radius:2px; }
        .lms-attendance-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,360px),1fr)); gap:12px; }
        .lms-attendance-program { margin-top:14px; padding:15px; border:1px solid #dfe8dc; border-radius:7px; background:#fbfdfb; }
        .lms-attendance-program > h3 { margin:0 0 12px; color:#294c35; font:400 18px/1.3 Georgia,serif; }
        .lms-attendance-card { min-width:0; padding:14px; border:1px solid #e0e8e0; border-radius:6px; background:#fff; }
        .lms-attendance-course-heading { display:flex; flex-wrap:wrap; align-items:baseline; justify-content:space-between; gap:7px 12px; margin-bottom:12px; }
        .lms-attendance-course-heading h4 { margin:0; color:#334d36; font-size:11px; }
        .lms-attendance-course-heading span { color:#71805e; font-size:9px; font-weight:700; }
        .lms-attendance-chart { display:grid; grid-template-columns:repeat(auto-fit,minmax(50px,1fr)); align-items:end; gap:7px; min-height:126px; }
        .lms-attendance-month { display:grid; grid-template-rows:92px auto auto; align-items:end; gap:4px; min-width:0; text-align:center; }
        .lms-attendance-bars { display:flex; align-items:end; justify-content:center; gap:4px; height:92px; padding:0 5px; border-bottom:1px solid #e4eadf; background:repeating-linear-gradient(to top,transparent 0,transparent 29px,#edf1e9 30px); }
        .lms-attendance-bar { display:block; width:min(34%,18px); min-height:2px; border-radius:3px 3px 0 0; }
        .lms-attendance-bar.student { background:#4d8b67; }
        .lms-attendance-bar.lecturer { background:#537da8; }
        .lms-attendance-month small { color:#707b6b; font-size:9px; line-height:1.2; }
        .lms-attendance-month strong { color:#4b5947; font-size:9px; font-weight:600; }
        @media (max-width:680px) {
            .lms-dashboard-head { align-items:stretch; flex-direction:column; gap:15px; }
            .lms-dashboard-head h1 { font-size:30px; }
            .lms-dashboard-scope { padding:12px 0 0; border-top:1px solid #d0ded4; border-left:0; }
            .lms-dashboard-filter { grid-template-columns:1fr; }
            .lms-dashboard-note { grid-column:auto; }
        }
    </style>
@endpush

@section('content')
    <section class="lms-dashboard-head">
        <div>
            <h1>Dashboard LMS</h1>
            <p>Ringkasan materi, pertemuan, tugas, bank soal, diskusi, ujian, dan partisipasi LMS berdasarkan tahun akademik.</p>
        </div>
        <div class="lms-dashboard-scope">
            <span>Cakupan data</span>
            <strong>{{ $scopeLabel }}</strong>
        </div>
    </section>

    <form class="lms-dashboard-filter" method="GET" action="{{ route('lms-dashboard.index') }}">
        <div>
            <label for="lms-dashboard-calendar">Tahun akademik</label>
            <select id="lms-dashboard-calendar" name="calendar" data-searchable-select>
                @foreach ($calendarOptions as $option)
                    <option value="{{ $option['year'] }}" @selected($selectedYear === $option['year'])>
                        {{ $option['label'] }}{{ $option['active'] ? ' (Aktif)' : '' }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit">Tampilkan</button>
        <p class="lms-dashboard-note">Data dibatasi sesuai peran. Materi dan bank soal dihitung dari mata kuliah yang dijadwalkan pada tahun akademik terpilih.</p>
    </form>

    @if ($selectedTerm !== null)
        <div class="lms-dashboard-term {{ $selectedTerm['active'] ? '' : 'is-inactive' }}">
            <span>{{ $selectedTerm['label'] }}</span>
            <span>{{ $selectedTerm['active'] ? 'Tahun akademik aktif' : 'Tahun akademik lampau · hanya baca' }}</span>
        </div>
    @endif

    @if ($calendarOptions->isEmpty())
        <div class="lms-dashboard-empty">Belum ada tahun akademik pada cakupan akun ini.</div>
    @else
        <section class="lms-dashboard-grid" aria-label="Ringkasan aktivitas LMS">
            @foreach ($activities as $activity)
                <article class="lms-dashboard-card" data-activity-label="{{ $activity['label'] }}" data-activity-count="{{ $activity['value'] }}">
                    <span class="lms-dashboard-card-label">{{ $activity['label'] }}</span>
                    <strong class="lms-dashboard-card-value">{{ number_format($activity['value']) }}</strong>
                    <div class="lms-dashboard-track" role="img" aria-label="{{ $activity['label'] }}: {{ $activity['value'] }}">
                        <span class="lms-dashboard-bar" style="--bar-width: {{ $activity['width'] }}%"></span>
                    </div>
                    <small>Jumlah pada cakupan terpilih</small>
                </article>
            @endforeach
        </section>
    @endif

    <section class="lms-attendance-section" aria-labelledby="lms-attendance-title">
        <div class="lms-attendance-heading">
            <div>
                <h2 id="lms-attendance-title">Tren Kehadiran LMS</h2>
                <p>Tren ditampilkan per program studi dan mata kuliah. Kehadiran mahasiswa dihitung setelah waktu selesai sesi lewat; persentase sesi dosen membandingkan sesi yang sudah lewat waktu selesai dengan seluruh sesi terjadwal.</p>
            </div>
            <div class="lms-attendance-legend" aria-label="Keterangan grafik">
                <span><i style="background:#4d8b67"></i>Kehadiran mahasiswa</span>
                <span><i style="background:#537da8"></i>Sesi lewat waktu selesai</span>
            </div>
        </div>
        @if ($attendanceTrends->isEmpty())
            <div class="lms-dashboard-empty">Belum ada data pertemuan untuk menampilkan tren kehadiran pada tahun akademik ini.</div>
        @else
            <div>
                @foreach ($attendanceTrends as $programTrend)
                    <section class="lms-attendance-program" aria-label="Tren mata kuliah {{ $programTrend['label'] }}">
                        <h3>{{ $programTrend['label'] }}</h3>
                        <div class="lms-attendance-grid">
                            @foreach ($programTrend['courses'] as $courseTrend)
                                <article class="lms-attendance-card" data-attendance-course="{{ $courseTrend['label'] }}" data-meeting-count="{{ $courseTrend['meeting_count'] }}">
                                    <div class="lms-attendance-course-heading">
                                        <h4>{{ $courseTrend['label'] }}</h4>
                                        <span>{{ number_format($courseTrend['meeting_count']) }} pertemuan</span>
                                    </div>
                                    <div class="lms-attendance-chart" role="img" aria-label="Grafik kehadiran {{ $programTrend['label'] }} · {{ $courseTrend['label'] }}">
                                        @foreach ($courseTrend['points'] as $point)
                                            <div class="lms-attendance-month">
                                                <div class="lms-attendance-bars">
                                                    <span class="lms-attendance-bar student" style="height:{{ max(2, $point['student_attendance'] ?? 0) }}%" title="Mahasiswa: {{ $point['student_attendance'] === null ? '—' : $point['student_attendance'].'%' }}"></span>
                                                    <span class="lms-attendance-bar lecturer" style="height:{{ max(2, $point['lecturer_attendance'] ?? 0) }}%" title="Sesi lewat waktu selesai: {{ $point['lecturer_attendance'] }}%"></span>
                                                </div>
                                                <small>{{ $point['month'] }}</small>
                                                <strong>{{ $point['student_attendance'] === null ? '—' : $point['student_attendance'].'%' }} / {{ $point['lecturer_attendance'] }}%</strong>
                                                <small>{{ number_format($point['meeting_count']) }} pertemuan</small>
                                            </div>
                                        @endforeach
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </section>
@endsection
