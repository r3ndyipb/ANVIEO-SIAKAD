@extends('layouts.simak')

@section('title', 'Absen Kuliah | SIAKAD')
@section('topbar_title', 'Absen Kuliah')

@push('styles')
    <style>
        .attendance-filter { display: grid; grid-template-columns: minmax(220px, 1.15fr) minmax(220px, 1fr) auto; align-items: end; gap: 10px; margin: 16px 0 20px; }
        .attendance-filter.without-program { grid-template-columns: minmax(240px, 520px) auto; }
        .attendance-filter > button { width: auto; white-space: nowrap; }
        .attendance-filter label { display: block; margin-bottom: 7px; color: #43543c; font-size: 11px; font-weight: 800; }
        .attendance-actions { display: flex; flex-wrap: wrap; gap: 6px; }
        .attendance-actions a { min-width: 32px; }
        .attendance-pending-button { padding: 0; border: 0; background: transparent; color: #356b45; font: inherit; font-weight: 800; text-decoration: underline; text-decoration-thickness: 1px; text-underline-offset: 3px; cursor: pointer; }
        .attendance-pending-button:hover { color: #214f36; }
        .attendance-pending-button:focus-visible { outline: 3px solid rgba(53, 107, 69, .25); outline-offset: 3px; }
        .attendance-pending-list { display: grid; gap: 8px; max-height: 48vh; overflow-y: auto; padding-left: 24px; color: #2c4932; font-size: 12px; }
        .attendance-pending-state { color: #69745c; font-size: 12px; }
        @media (max-width: 820px) { .attendance-filter, .attendance-filter.without-program { grid-template-columns: 1fr; } }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1>Absen Mata Kuliah</h1>
            <div class="module-alert" role="note"><p class="subtitle">Daftar jadwal kalender aktif dan dokumen kehadiran perkuliahan.</p></div>
        </div>
    </div>

    <form class="attendance-filter {{ in_array($user->role, ['admin', 'dosen', 'asdos'], true) ? 'with-program' : 'without-program' }}" method="GET" action="{{ route('course-attendance.index') }}">
        @if (in_array($user->role, ['admin', 'dosen', 'asdos'], true))
            <div>
                <label for="attendance-program">Program studi</label>
                <select id="attendance-program" name="program" data-searchable-select="filter" required onchange="this.form.elements.calendar.value = ''; this.form.submit()">
                    <option value="">Pilih program studi</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id_prodi }}" @selected((string) $selectedProgram === (string) $program->id_prodi)>
                            {{ $program->nama_program_studi }} {{ $program->nama_jenjang_pendidikan ? '('.$program->nama_jenjang_pendidikan.')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
        <div>
            <label for="attendance-calendar">Tahun akademik</label>
            <select id="attendance-calendar" name="calendar" data-searchable-select="filter" @required($selectedProgram !== '') @disabled($selectedProgram === '' || $calendars->isEmpty()) onchange="this.form.submit()">
                <option value="">Pilih tahun akademik</option>
                @foreach ($calendars as $calendarOption)
                    <option value="{{ $calendarOption->id_kalender }}" @selected((string) data_get($calendar, 'id_kalender', '') === (string) $calendarOption->id_kalender)>
                        {{ $calendarOption->namatahun ?: $calendarOption->tahun }}{{ $calendarOption->status === 'Y' ? ' (Aktif)' : '' }}
                    </option>
                @endforeach
            </select>
        </div>
        <button class="button button-primary" type="submit" @disabled($selectedProgram === '' || $calendars->isEmpty())><span data-lucide="search"></span> Tampilkan</button>
    </form>

    @if (in_array($user->role, ['admin', 'dosen', 'asdos'], true) && $selectedProgram === '')
        <div class="master-empty-state"><strong>Pilih program studi</strong>Pilih program studi untuk menampilkan jadwal pada kalender aktif.</div>
    @elseif ($selectedProgram !== '' && $calendars->isEmpty())
        <div class="master-empty-state"><strong>Kalender aktif belum tersedia</strong>Program studi ini belum memiliki kalender aktif.</div>
    @elseif ($selectedProgram !== '' && ! $calendar)
        <div class="master-empty-state"><strong>Pilih tahun akademik</strong>Pilih kalender untuk menampilkan jadwal mata kuliah.</div>
    @elseif ($selectedProgram !== '' && $schedules->isEmpty())
        <div class="master-empty-state"><strong>Jadwal belum tersedia</strong>Tidak ada jadwal aktif pada cakupan program studi ini.</div>
    @elseif ($schedules->isNotEmpty())
        <div class="table-wrap">
            <table data-datatable="course-attendance">
                <thead><tr><th>No</th><th>Mata Kuliah</th><th>Program Studi</th><th>Dosen Pengampu</th><th>Kelas</th><th>Pengesahan Prodi</th><th class="no-export">Proses</th></tr></thead>
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
                            <td>{{ $schedule->kode_mata_kuliah }} · {{ $schedule->nama_mata_kuliah }}</td>
                            <td>{{ $schedule->nama_program_studi }} {{ $schedule->nama_jenjang_pendidikan ? '('.$schedule->nama_jenjang_pendidikan.')' : '' }}</td>
                            <td>{{ $lecturerName ?: '-' }}</td>
                            <td>{{ $schedule->nama_kelas }}</td>
                            <td>
                                {{ $schedule->approved_student_count }} disahkan dari {{ $schedule->student_count }} mahasiswa terdaftar
                                <small class="subtext">
                                    @if ($schedule->pending_approval_count > 0 && in_array($user->role, ['admin', 'userprodi', 'usertu'], true))
                                        <button class="attendance-pending-button" type="button" data-attendance-pending-open data-pending-url="{{ route('course-attendance.pending-approvals', $schedule->id_jadwal) }}" data-course-name="{{ $schedule->nama_mata_kuliah }}" data-course-class="{{ $schedule->nama_kelas }}">{{ $schedule->pending_approval_count }} belum disahkan prodi</button>
                                    @else
                                        {{ $schedule->pending_approval_count }} belum disahkan prodi
                                    @endif
                                </small>
                            </td>
                            <td>
                                <div class="attendance-actions">
                                    <a class="table-icon-button" href="{{ route('course-attendance.print', ['schedule' => $schedule->id_jadwal, 'document' => 'attendance']) }}" target="_blank" rel="noopener" title="Cetak Absen Kuliah" aria-label="Cetak Absen Kuliah"><span data-lucide="printer"></span></a>
                                    <a class="table-icon-button" href="{{ route('course-attendance.print', ['schedule' => $schedule->id_jadwal, 'document' => 'class-record']) }}" target="_blank" rel="noopener" title="Cetak Berita Acara Kuliah" aria-label="Cetak Berita Acara Kuliah"><span data-lucide="file-down"></span></a>
                                    <a class="table-icon-button" href="{{ route('course-attendance.print', ['schedule' => $schedule->id_jadwal, 'document' => 'roster']) }}" title="Unduh Daftar Mahasiswa Excel" aria-label="Unduh Daftar Mahasiswa Excel"><span data-lucide="file-spreadsheet"></span></a>
                                    <a class="table-icon-button" href="{{ route('course-attendance.print', ['schedule' => $schedule->id_jadwal, 'document' => 'midterm']) }}" target="_blank" rel="noopener" title="Cetak Berita Acara UTS" aria-label="Cetak Berita Acara UTS"><span data-lucide="file-down"></span></a>
                                    <a class="table-icon-button" href="{{ route('course-attendance.print', ['schedule' => $schedule->id_jadwal, 'document' => 'final']) }}" target="_blank" rel="noopener" title="Cetak Berita Acara UAS" aria-label="Cetak Berita Acara UAS"><span data-lucide="file-down"></span></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @if (in_array($user->role, ['admin', 'userprodi', 'usertu'], true) && $schedules->isNotEmpty())
        <div class="calendar-modal-backdrop" data-attendance-pending-modal hidden>
            <section class="calendar-modal" role="dialog" aria-modal="true" aria-labelledby="attendance-pending-title" aria-describedby="attendance-pending-description" tabindex="-1">
                <div class="calendar-modal-head">
                    <div>
                        <h2 id="attendance-pending-title" data-attendance-pending-title>Mahasiswa Belum Disahkan</h2>
                        <p class="calendar-modal-copy" id="attendance-pending-description" data-attendance-pending-description></p>
                    </div>
                    <button class="calendar-modal-close" type="button" data-attendance-pending-close aria-label="Tutup dialog">×</button>
                </div>
                <p class="attendance-pending-state" data-attendance-pending-state role="status"></p>
                <ol class="attendance-pending-list" data-attendance-pending-list></ol>
                <div class="calendar-modal-actions">
                    <button class="button" type="button" data-attendance-pending-close>Tutup</button>
                </div>
            </section>
        </div>
    @endif
@endsection