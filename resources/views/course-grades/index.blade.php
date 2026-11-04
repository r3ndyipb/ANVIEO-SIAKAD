@extends('layouts.simak')

@php
    $shortSemester = $shortSemester ?? false;
    $gradeRoutePrefix = $gradeRoutePrefix ?? 'grade-management';
    $gradeTitle = $shortSemester ? 'Nilai SP' : 'Nilai Mata Kuliah';
@endphp
@section('title', $gradeTitle.' | SIAKAD')
@section('topbar_title', $gradeTitle)

@push('styles')
    <style>
        .grade-filter { display: grid; grid-template-columns: minmax(240px, 520px) auto; align-items: end; gap: 10px; margin: 16px 0 20px; }
        .grade-filter label { display: block; margin-bottom: 7px; color: #43543c; font-size: 11px; font-weight: 800; }
        .grade-course-cell { min-width: 240px; }
        .grade-status { min-width: 150px; }
        .grade-status .grade-status-chip { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: 800; }
        .grade-status .is-entered { background: #e5f3e9; color: #24643a; }
        .grade-status .is-pending { background: #fde9e7; color: #9d352d; }
        .grade-status .is-partial { background: #fff3d7; color: #805d10; }
        .grade-process { white-space: nowrap; }
        .grade-action-cell { width: 44px; text-align: center; }
        @media (max-width: 640px) { .grade-filter { grid-template-columns: 1fr; } }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1>{{ $gradeTitle }}</h1>
            <div class="module-alert" role="note"><p class="subtitle">{{ $shortSemester ? 'Input nilai mahasiswa pada kelas yang terdaftar di kalender SP aktif.' : 'Input nilai mahasiswa pada kelas yang terdaftar di kalender akademik aktif.' }}</p></div>
        </div>
    </div>

    @if ($errors->any()) <div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div> @endif
    @if (session('status')) <div class="alert alert-success" role="status">{{ session('status') }}</div> @endif

    @if ($showProgramFilter || ($shortSemester && $calendars->isNotEmpty()))
        <form class="grade-filter" method="GET" action="{{ route($gradeRoutePrefix.'.index') }}">
            @if ($showProgramFilter)
                <div>
                    <label for="grade-program">Program studi</label>
                    <select id="grade-program" name="program" required data-searchable-select="filter" onchange="if (this.form.elements.calendar) this.form.elements.calendar.value = ''; this.form.submit()">
                        <option value="">Pilih program studi</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->id_prodi }}" @selected((string) $programId === (string) $program->id_prodi)>
                                {{ $program->nama_program_studi }} {{ $program->nama_jenjang_pendidikan ? '('.$program->nama_jenjang_pendidikan.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($shortSemester && $programId !== '')
                <div>
                    <label for="grade-calendar">Kalender SP</label>
                    <select id="grade-calendar" name="calendar" required data-searchable-select="filter" onchange="this.form.submit()">
                        @foreach ($calendars as $gradeCalendar)
                            <option value="{{ data_get($gradeCalendar, 'id_kalender') }}" @selected((string) data_get($calendar, 'id_kalender', '') === (string) data_get($gradeCalendar, 'id_kalender'))>
                                {{ data_get($gradeCalendar, 'namatahun') ?: data_get($gradeCalendar, 'tahun') }}{{ data_get($gradeCalendar, 'status_sp') === 'Y' ? ' · Aktif' : ' · Nonaktif' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($calendar)<span class="status-chip {{ $calendarIsActive ? 'active' : '' }}">{{ data_get($calendar, 'namatahun') ?: data_get($calendar, 'tahun') }}{{ $shortSemester && ! $calendarIsActive ? ' · Nonaktif · Baca saja' : '' }}</span>@endif
        </form>
    @elseif ($calendar)
        <p class="subtitle">{{ data_get($programs->firstWhere('id_prodi', $programId), 'nama_program_studi', '') }} · {{ data_get($calendar, 'namatahun') ?: data_get($calendar, 'tahun') }}</p>
    @endif

    @if ($showProgramFilter && $programId === '')
        <div class="master-empty-state"><strong>Pilih program studi</strong>Pilih program studi untuk menampilkan jadwal dan nilai mahasiswa.</div>
    @elseif ($programId !== '' && ! $calendar)
        <div class="master-empty-state"><strong>{{ $shortSemester ? 'Kalender SP aktif belum tersedia' : 'Kalender aktif belum tersedia' }}</strong>{{ $shortSemester ? 'Program studi ini belum memiliki kalender SP aktif.' : 'Program studi ini belum memiliki kalender akademik aktif.' }}</div>
    @elseif ($schedules->isEmpty())
        <div class="master-empty-state"><strong>Jadwal belum tersedia</strong>{{ $shortSemester ? 'Tidak ada jadwal SP pada kalender aktif yang dapat ditampilkan.' : 'Tidak ada jadwal kuliah pada kalender aktif yang dapat ditampilkan.' }}</div>
    @else
        @if ($shortSemester && ! $calendarIsActive)
            <div class="calendar-confirm-detail" role="note">Kalender SP tidak aktif. Nilai hanya dapat dilihat.</div>
        @elseif (in_array($user->role, ['dosen', 'asdos'], true))
            <div class="calendar-confirm-detail" role="note">
                Waktu input nilai: {{ data_get($calendar, 'tgl_mulai_isinilai') ?: 'belum ditetapkan' }} s.d. {{ data_get($calendar, 'tgl_akhir_isinilai') ?: 'belum ditetapkan' }}.
                @unless ($inputWindowOpen) Input nilai belum dapat dilakukan pada hari ini.@endunless
            </div>
        @endif
        <div class="table-wrap">
            <table data-datatable="course-grades">
                <thead><tr><th>No</th><th>Mata Kuliah</th><th>Program Studi</th><th>Dosen Pengampu</th><th>Kelas</th><th>Status Input Nilai</th>@if ($user->role === 'userprodi')<th class="no-export" colspan="3">Proses</th>@elseif (in_array($user->role, ['dosen', 'asdos', 'admin', 'usertu'], true))<th class="no-export">Proses</th>@endif</tr></thead>
                <tbody>
                    @foreach ($schedules as $schedule)
                        @php
                            $lecturerName = \App\Support\LecturerName::format(
                                $schedule->nama_dosen ? ucwords(mb_strtolower($schedule->nama_dosen)) : null,
                                $schedule->gelar_depan,
                                $schedule->gelar_belakang,
                            );
                            if ($schedule->dosen2) {
                                $lecturerName .= ' dan tim dosen';
                            }
                        @endphp
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td><div class="grade-course-cell">{{ $schedule->kode_mata_kuliah }} · {{ $schedule->nama_mata_kuliah }}</div></td>
                            <td>{{ $schedule->nama_program_studi }} {{ $schedule->nama_jenjang_pendidikan ? '('.$schedule->nama_jenjang_pendidikan.')' : '' }}</td>
                            <td>{{ $lecturerName ?: '-' }}</td>
                            <td>{{ $schedule->nama_kelas }}</td>
                            <td class="grade-status">
                                <span class="grade-status-chip {{ $schedule->is_locked ? 'is-entered' : ($schedule->entered_count > 0 ? 'is-partial' : 'is-pending') }}">{{ $schedule->is_locked ? 'Telah Diinput' : ($schedule->entered_count > 0 ? 'Sebagian Diinput' : 'Belum Diinput') }}</span>
                                <small class="subtext">{{ $schedule->entered_count }} dari {{ $schedule->enrolled_count }} mahasiswa</small>
                            </td>
                            @if (in_array($user->role, ['dosen', 'asdos'], true))
                                <td class="grade-process">
                                    @if ($schedule->can_open_entry)
                                            <a class="table-icon-button" href="{{ route($gradeRoutePrefix.'.edit', ['schedule' => $schedule->id_jadwal, 'program' => $programId]) }}" title="Input atau lihat nilai" aria-label="Input atau lihat nilai {{ $schedule->nama_mata_kuliah }}"><span data-lucide="pencil"></span></a>
                                    @endif
                                    <a class="table-icon-button" href="{{ route($gradeRoutePrefix.'.print', ['schedule' => $schedule->id_jadwal, 'program' => $programId]) }}" target="_blank" rel="noopener" title="Cetak daftar nilai" aria-label="Cetak daftar nilai {{ $schedule->nama_mata_kuliah }}"><span data-lucide="printer"></span></a>
                                </td>
                            @elseif (in_array($user->role, ['admin', 'userprodi', 'usertu'], true))
                                @if ($user->role === 'userprodi')
                                    <td class="grade-process grade-action-cell">
                                        @if ($schedule->can_open_entry)
                                            <a class="table-icon-button" href="{{ route($gradeRoutePrefix.'.edit', ['schedule' => $schedule->id_jadwal, 'program' => $programId]) }}" title="Input nilai" aria-label="Input nilai {{ $schedule->nama_mata_kuliah }}"><span data-lucide="pencil"></span></a>
                                        @elseif ($shortSemester)
                                            <span class="status-chip">Baca saja</span>
                                        @endif
                                    </td>
                                    <td class="grade-process grade-action-cell">
                                        @if ($schedule->can_unlock)
                                            <a class="table-icon-button reset" href="{{ route($gradeRoutePrefix.'.edit', ['schedule' => $schedule->id_jadwal, 'program' => $programId, 'focus' => 'unlock']) }}" title="Buka kunci input nilai dosen" aria-label="Buka kunci input nilai dosen untuk {{ $schedule->nama_mata_kuliah }}"><span data-lucide="lock-open"></span></a>
                                        @elseif ($shortSemester && ! $schedule->calendar_is_active)
                                            <span class="status-chip">Baca saja</span>
                                        @endif
                                    </td>
                                    <td class="grade-process grade-action-cell">
                                        <a class="table-icon-button" href="{{ route($gradeRoutePrefix.'.print', ['schedule' => $schedule->id_jadwal, 'program' => $programId]) }}" target="_blank" rel="noopener" title="Cetak nilai" aria-label="Cetak nilai {{ $schedule->nama_mata_kuliah }}"><span data-lucide="printer"></span></a>
                                    </td>
                                @else
                                    <td class="grade-process grade-action-cell">
                                        <a class="table-icon-button" href="{{ route($gradeRoutePrefix.'.print', ['schedule' => $schedule->id_jadwal, 'program' => $programId]) }}" target="_blank" rel="noopener" title="Cetak nilai" aria-label="Cetak nilai {{ $schedule->nama_mata_kuliah }}"><span data-lucide="printer"></span></a>
                                    </td>
                                @endif
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection