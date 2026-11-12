@extends('layouts.simak')

@section('title', 'Dashboard LMS | SIAKAD')
@section('topbar_title', 'Dashboard LMS')

@section('content')
    <section class="lms-dashboard-head">
        <div>
            <p class="eyebrow">Pembelajaran Digital · LMS</p>
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
