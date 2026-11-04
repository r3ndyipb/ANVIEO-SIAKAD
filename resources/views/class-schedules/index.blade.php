@extends('layouts.simak')

@php
    $shortSemester = $shortSemester ?? false;
    $scheduleRoutes = $scheduleRoutes ?? ['index' => 'class-schedules.index', 'create' => 'class-schedules.create'];
    $scheduleTitle = $shortSemester ? 'Jadwal SP' : 'Jadwal Kuliah';
@endphp
@section('title', $scheduleTitle.' | SIAKAD')
@section('topbar_title', $scheduleTitle)

@push('styles')
    <style>
        .schedule-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 320px)); align-items: end; gap: 12px; margin-bottom: 22px; }
        .schedule-filters label, .schedule-copy label { display: block; margin-bottom: 7px; color: #43543c; font-size: 11px; font-weight: 800; }
        .schedule-filters .ts-wrapper, .schedule-copy .ts-wrapper { width: 100%; }
        .schedule-copy-field { width: min(100%, 320px); }
        .schedule-toolbar { display: flex; flex-wrap: wrap; align-items: end; justify-content: space-between; gap: 14px; margin-bottom: 18px; padding-bottom: 15px; border-bottom: 1px solid #e5e9e2; }
        .schedule-krs-notice { margin: 12px 0; padding: 10px 12px; border-left: 3px solid #d1a53d; background: #fffae9; color: #695426; font-size: 11px; line-height: 1.5; }
        .schedule-day { color: #315b3b; font-weight: 700; }
        .schedule-teachers { max-width: 300px; color: #596b58; font-size: 10px; line-height: 1.5; }
        .schedule-km { display: inline-block; padding: 4px 7px; border-radius: 4px; background: #eef3e8; color: #526546; font-size: 9px; font-weight: 700; }
        .schedule-print-link { margin-left: auto; }
        .schedule-modal-copy { margin: 0; color: #4c5e4b; font-size: 12px; line-height: 1.6; }
        .schedule-feeder-status { display: grid; gap: 7px; min-width: 190px; }
        .schedule-feeder-teacher { display: grid; gap: 4px; padding: 8px; border: 1px solid #e5e9e2; border-radius: 6px; background: #fbfcfa; }
        .schedule-feeder-teacher .button { justify-self: start; min-height: 29px; padding: 5px 9px; font-size: 10px; }
        .short-semester-student-list { display: grid; gap: 12px; }
        .short-semester-student-tools { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .short-semester-student-table { width: 100%; border-collapse: collapse; }
        .short-semester-student-table th, .short-semester-student-table td { padding: 8px 10px; border-bottom: 1px solid #e8eee5; text-align: left; }
        .short-semester-student-table-wrap { max-height: min(48vh, 420px); overflow: auto; }
        .short-semester-student-table thead { position: sticky; top: 0; z-index: 1; background: #fff; }
        .short-semester-student-table tbody tr:hover { background: #f6faf4; }
        .short-semester-student-table input[type="checkbox"] { width: 16px; height: 16px; }
        .short-semester-student-empty { color: #5d6a5d; font-size: 12px; }
        .short-semester-modal-pagination { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; color: #5d6a5d; font-size: 11px; }
        .short-semester-modal-pagination[hidden] { display: none; }
        .short-semester-modal-pagination-controls { display: flex; align-items: center; gap: 6px; }
        .short-semester-modal-pagination button { min-height: 30px; padding: 4px 9px; border: 1px solid #d5dfd3; border-radius: 5px; background: #fff; color: #386345; cursor: pointer; }
        .short-semester-modal-pagination button:disabled { color: #a5afa2; cursor: default; }
        .short-semester-modal-pagination select { min-height: 30px; padding: 3px 6px; border: 1px solid #d5dfd3; border-radius: 5px; background: #fff; color: #386345; }
        .schedule-participant-count { display: inline-flex; align-items: center; gap: 5px; min-width: 34px; }
        [data-schedule-confirm-modal][hidden], [data-schedule-copy-modal][hidden] { display: none; }
        .schedule-copy-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .schedule-copy-actions { display: flex; justify-content: flex-end; gap: 9px; margin-top: 20px; }
        .schedule-pagination .dt-paging-button { min-width: 32px; min-height: 30px; margin-left: 4px; padding: 0 9px; border: 1px solid #d5dfd3; border-radius: 5px; background: #fff; color: #386345; font-size: 10px; text-decoration: none; }
        .schedule-pagination .dt-paging-button.current { border-color: #73a17b; background: #e8f2e7; color: #315b3b; font-weight: 700; }
        .schedule-pagination .dt-paging-button.disabled { color: #a5afa2; cursor: default; }
        .schedule-pagination .dt-paging { display: flex; justify-content: flex-end; align-items: center; gap: 4px; padding: 13px 0; }
        @media (max-width: 620px) {
            .schedule-toolbar { align-items: stretch; flex-direction: column; }
            .schedule-copy-fields { grid-template-columns: 1fr; }
            .schedule-copy-actions { justify-content: stretch; }
            .schedule-copy-actions .button { flex: 1; }
        }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1>{{ $scheduleTitle }}</h1>
            <div class="module-alert" role="note">
                <p class="subtitle">{{ $shortSemester ? 'Jadwal Semester Pendek berdasarkan kalender SP yang aktif.' : 'Jadwal perkuliahan berdasarkan program studi dan kalender akademik.' }}</p>
            </div>
        </div>
    </div>

    @if ($shortSemester)
        @include('components.short-semester-feeder-alert')
    @endif

    @if ($errors->any())
        <div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($canChooseProgram || $canChooseCalendar)
        <form class="schedule-filters" method="GET" action="{{ route($scheduleRoutes['index']) }}">
            @if ($canChooseProgram)
                <div>
                    <label for="schedule-program">Program studi</label>
                    <select id="schedule-program" name="id_prodi" data-searchable-select="schedule">
                        <option value="">Pilih program studi</option>
                        @foreach ($programs as $programId => $programLabel)
                            <option value="{{ $programId }}" @selected($selectedProgram === (string) $programId)>{{ $programLabel }}</option>
                        @endforeach
                    </select>
                </div>
            @elseif ($selectedProgram)
                <input type="hidden" name="id_prodi" value="{{ $selectedProgram }}">
                <div>
                    <label>Program studi</label>
                    <input value="{{ collect($programs)->get((string) $selectedProgram, 'Program studi') }}" disabled>
                </div>
            @endif

            @if ($canChooseCalendar && $selectedProgram && $calendars->isNotEmpty())
                <div>
                    <label for="schedule-calendar">Tahun akademik</label>
                    <select id="schedule-calendar" name="id_kalender" data-searchable-select="schedule">
                        @foreach ($calendars as $calendar)
                            <option value="{{ $calendar->id_kalender }}" @selected($selectedCalendar === (string) $calendar->id_kalender)>
                                {{ $calendar->namatahun ?: $calendar->tahun }}{{ $calendar->status === 'Y' ? ' · Aktif' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($canChooseProgram || ($selectedProgram && $calendars->isNotEmpty()))
                <button class="button button-primary" type="submit">Terapkan</button>
            @endif
        </form>
    @endif

    @if ($selectedProgram && $canManage)
        <div class="schedule-toolbar">
            @if ($selectedCalendarStatus === 'Y')
                <a class="button button-primary" href="{{ route($scheduleRoutes['create'], ['id_prodi' => $selectedProgram]) }}">Tambah {{ $shortSemester ? 'Jadwal SP' : 'Jadwal' }}</a>
            @elseif ($selectedCalendar)
                <span class="schedule-krs-notice">Kalender {{ $shortSemester ? 'SP' : 'akademik' }} yang dipilih tidak aktif. Jadwal hanya dapat dilihat; pilih kalender aktif untuk menambah jadwal.</span>
            @else
                <span class="schedule-krs-notice">Tidak ada kalender {{ $shortSemester ? 'SP' : 'akademik' }} aktif. Jadwal baru hanya dapat dibuat pada kalender aktif.</span>
            @endif

            @if (! $shortSemester && $calendars->where('status', 'Y')->where('id_kalender', '!=', $selectedCalendar)->isNotEmpty())
                <button class="button" type="button" data-schedule-copy-open>Salin Jadwal</button>
            @endif

            @if ($canPrint && $selectedCalendar)
                <a class="button schedule-print-link" href="{{ route('class-schedules.print', ['id_prodi' => $selectedProgram, 'id_kalender' => $selectedCalendar]) }}" target="_blank" rel="noopener">Cetak Jadwal</a>
            @endif
        </div>
    @endif

    @if ($selectedProgram === null)
        <div class="master-empty-state"><strong>Pilih program studi</strong>Jadwal tampil setelah program studi dipilih.</div>
    @elseif ($selectedCalendar === null)
        @if (in_array($role, ['dosen', 'asdos', 'mahasiswa'], true))
            <div class="master-empty-state"><strong>Belum ada {{ strtolower($scheduleTitle) }}</strong>Tidak ada kalender SP dengan jadwal yang terkait dengan akun ini.</div>
        @else
            <div class="master-empty-state"><strong>Kalender aktif tidak tersedia</strong>Tidak ada kalender {{ $shortSemester ? 'SP' : 'akademik' }} yang dapat ditampilkan untuk program studi ini.</div>
        @endif
    @elseif ($schedules->isEmpty())
        <div class="master-empty-state"><strong>Belum ada {{ strtolower($scheduleTitle) }}</strong>Belum ada jadwal pada kalender yang dipilih.</div>
    @else
        <div class="table-wrap">
            <table data-simple-search-table data-schedule-list>
                <thead>
                    <tr>
                        <th>Hari</th><th>Jam</th><th>Mata Kuliah</th>                        <th>Ruang</th>
                        <th>Tahun Akademik</th><th>Program Studi</th>
                        @if ($showTeachers)<th>Dosen Pengampu</th>@endif
                        <th>Kelas</th>
                        @if ($canManage && $selectedCalendarStatus === 'Y')<th class="no-export">Aksi</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($schedules as $schedule)
                        <tr>
                            <td class="schedule-day">{{ ucfirst($schedule->hari) }}</td>
                            <td>{{ substr((string) $schedule->jammulai, 0, 5) }}–{{ substr((string) $schedule->jamselesai, 0, 5) }}</td>
                            <td>{{ $schedule->nama_mata_kuliah ?: 'Mata kuliah tidak ditemukan' }}<span class="subtext">{{ $schedule->kode_mata_kuliah }} · SMS {{ $schedule->semester }}</span></td>
                            <td>{{ $schedule->nama_ruang ?: 'Ruang tidak ditemukan' }}</td>
                            <td>{{ $schedule->namatahun ?: $schedule->tahun }}</td>
                            <td>{{ $schedule->nama_program_studi }}<span class="subtext">{{ $schedule->nama_jenjang_pendidikan }}</span></td>
                            @if ($showTeachers)<td class="schedule-teachers">{{ $schedule->teacher_names ?: 'Belum ditentukan' }}</td>@endif
                            <td>{{ $schedule->nama_kelas }}</td>
                            @if ($canManage && $selectedCalendarStatus === 'Y')
                                <td>
                                    @if ($shortSemester)
                                        <div class="schedule-feeder-status" id="feeder-sync-{{ $schedule->id_jadwal }}">
                                            <div class="row-actions">
                                                <button class="table-icon-button schedule-participant-count" type="button" data-short-semester-view-participants data-schedule-id="{{ $schedule->id_jadwal }}" aria-label="Lihat {{ $schedule->participant_count }} peserta kelas SP" title="Lihat peserta kelas SP">{{ $schedule->participant_count }}</button>
                                                <a class="table-icon-button" href="{{ route('short-semester-schedules.edit', $schedule->id_jadwal) }}" aria-label="Ubah jadwal SP" title="Ubah jadwal SP"><span data-lucide="pencil"></span></a>
                                                <button class="table-icon-button" type="button" data-short-semester-add-participants data-schedule-id="{{ $schedule->id_jadwal }}" aria-label="Tambah peserta kelas SP" title="Tambah peserta kelas SP"><span data-lucide="user-plus"></span></button>
                                                <form method="POST" action="{{ route('short-semester-schedules.destroy', $schedule->id_jadwal) }}" data-schedule-confirm data-short-semester-delete data-confirm-title="Hapus Jadwal SP" data-confirm-message="{{ $schedule->has_krs ? 'Hapus semua peserta kelas terlebih dahulu, lalu hapus jadwal SP '.$schedule->nama_mata_kuliah.' kelas '.$schedule->nama_kelas.' beserta dosen pengampu di Feeder?' : 'Hapus jadwal SP '.$schedule->nama_mata_kuliah.' kelas '.$schedule->nama_kelas.' beserta dosen pengampu di Feeder?' }}">
                                                    @csrf @method('DELETE')
                                                    <button class="table-icon-button reset" type="submit" aria-label="Hapus jadwal SP" title="Hapus jadwal SP"><span data-lucide="trash-2"></span></button>
                                                </form>
                                            </div>
                                            @if ($schedule->has_krs)<small class="subtext">Sudah diprogram KRS</small>@endif
                                            <strong>Status Feeder</strong>
                                            @forelse ($schedule->feeder_teacher_statuses as $teacherSync)
                                                <div class="schedule-feeder-teacher">
                                                    <span>{{ \App\Support\LecturerName::format($teacherSync->nama_dosen ?? null, $teacherSync->gelar_depan ?? null, $teacherSync->gelar_belakang ?? null) ?: $teacherSync->id_dosen }}</span>
                                                    <span class="subtext">
                                                        {{ match ($teacherSync->status) {
                                                            'synced' => 'Terkirim',
                                                            'failed' => 'Gagal',
                                                            'processing' => 'Sedang diproses; jangan kirim ulang',
                                                            default => 'Menunggu pengiriman',
                                                        } }}
                                                    </span>
                                                    @if ($teacherSync->status !== 'synced')
                                                        @if ($teacherSync->error_desc)
                                                            <small class="field-error">{{ $teacherSync->error_desc }}</small>
                                                        @endif
                                                        @if ($teacherSync->status !== 'processing')
                                                            <form method="POST" action="{{ route('short-semester-schedules.teachers.sync', [$schedule->id_jadwal, $teacherSync->id_dosen]) }}">
                                                                @csrf
                                                                <button class="button" type="submit">Kirim / Coba lagi</button>
                                                            </form>
                                                        @endif
                                                    @endif
                                                </div>
                                            @empty
                                                <small class="subtext">Belum ada status pengiriman pengampu.</small>
                                            @endforelse
                                        </div>
                                    @else
                                        <div class="row-actions">
                                            <a class="table-icon-button" href="{{ route('class-schedules.edit', $schedule->id_jadwal) }}" aria-label="Ubah jadwal" title="Ubah jadwal"><span data-lucide="pencil"></span></a>
                                            @unless ($schedule->has_krs)
                                                <form method="POST" action="{{ route('class-schedules.destroy', $schedule->id_jadwal) }}" data-schedule-confirm data-confirm-title="Hapus Jadwal" data-confirm-message="Hapus jadwal {{ $schedule->nama_mata_kuliah }} kelas {{ $schedule->nama_kelas }}?">
                                                    @csrf @method('DELETE')
                                                    <button class="table-icon-button reset" type="submit" aria-label="Hapus jadwal" title="Hapus jadwal"><span data-lucide="trash-2"></span></button>
                                                </form>
                                            @endunless
                                        </div>
                                        @if ($schedule->has_krs)<small class="subtext">Sudah diprogram KRS</small>@endif
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($schedules instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="schedule-pagination">{{ $schedules->links('vendor.pagination.datatables') }}</div>
        @endif
    @endif

    @unless ($shortSemester)
    <div class="calendar-modal-backdrop" data-schedule-copy-modal hidden>
        <section class="calendar-modal" role="dialog" aria-modal="true" aria-labelledby="schedule-copy-title" tabindex="-1">
            <div class="calendar-modal-head">
                <div>
                    <h2 id="schedule-copy-title">Salin Jadwal</h2>
                    <p class="calendar-modal-copy schedule-modal-copy">Pilih tahun akademik sumber dan tujuan. Jadwal disalin untuk program studi yang dipilih.</p>
                </div>
                <button class="calendar-modal-close" type="button" data-schedule-copy-close aria-label="Tutup dialog">×</button>
            </div>
            <form method="POST" action="{{ route('class-schedules.copy') }}" data-schedule-copy-form>
                @csrf
                <input type="hidden" name="id_prodi" value="{{ $selectedProgram }}">
                <div class="schedule-copy-fields">
                    <div class="schedule-copy-field">
                        <label for="schedule-source-calendar">Tahun sumber</label>
                        <select id="schedule-source-calendar" name="source_calendar" required data-searchable-select="schedule">
                            <option value="">Pilih tahun sumber</option>
                            @foreach ($calendars as $calendar)
                                <option value="{{ $calendar->id_kalender }}">{{ $calendar->namatahun ?: $calendar->tahun }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="schedule-copy-field">
                        <label for="schedule-target-calendar">Tahun tujuan</label>
                        <select id="schedule-target-calendar" name="target_calendar" required data-searchable-select="schedule">
                            <option value="">Pilih tahun tujuan</option>
                            @foreach ($calendars->where('status', 'Y') as $calendar)
                                <option value="{{ $calendar->id_kalender }}">
                                    {{ $calendar->namatahun ?: $calendar->tahun }}{{ $calendar->status === 'Y' ? ' · Aktif' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <p class="field-error" data-schedule-copy-error role="alert" hidden>Tahun sumber dan tujuan harus berbeda.</p>
                <div class="schedule-copy-actions">
                    <button class="button" type="button" data-schedule-copy-close>Batal</button>
                    <button class="button button-primary" type="submit">Mulai Copy</button>
                </div>
            </form>
        </section>
    </div>
    @endunless

    @if ($shortSemester)
    <div class="calendar-modal-backdrop" data-short-semester-students-modal hidden>
        <section class="calendar-modal" role="dialog" aria-modal="true" aria-labelledby="short-semester-students-title">
            <div class="calendar-modal-head">
                <div>
                    <h2 id="short-semester-students-title">Tambah Peserta Kelas SP</h2>
                    <p class="calendar-modal-copy schedule-modal-copy">Pilih mahasiswa aktif dari program studi ini untuk ditambahkan ke kelas SP.</p>
                </div>
                <button class="calendar-modal-close" type="button" data-short-semester-students-close aria-label="Tutup dialog">×</button>
            </div>
            <div class="short-semester-student-list">
                <div class="short-semester-student-tools">
                    <input id="short-semester-student-search" type="search" placeholder="Cari mahasiswa..." aria-label="Cari mahasiswa">
                </div>
                <div class="table-wrap short-semester-student-table-wrap">
                    <table class="short-semester-student-table">
                        <thead>
                            <tr>
                                <th style="width: 44px;"></th>
                                <th>NIM</th>
                                <th>Nama Mahasiswa</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody data-short-semester-student-body>
                            <tr><td colspan="4" class="short-semester-student-empty">Memuat daftar mahasiswa...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="short-semester-modal-pagination" data-short-semester-student-pagination hidden>
                    <span data-short-semester-student-page-status></span>
                    <div class="short-semester-modal-pagination-controls">
                        <select data-short-semester-student-page-size aria-label="Jumlah mahasiswa per halaman">
                            <option value="10">10 / halaman</option>
                            <option value="25">25 / halaman</option>
                            <option value="50">50 / halaman</option>
                        </select>
                        <button type="button" data-short-semester-student-previous>‹ Sebelumnya</button>
                        <button type="button" data-short-semester-student-next>Selanjutnya ›</button>
                    </div>
                </div>
                <div class="calendar-modal-actions">
                    <button class="button" type="button" data-short-semester-students-close>Batal</button>
                    <button class="button button-primary" type="button" data-short-semester-students-submit>Tambah Peserta</button>
                </div>
            </div>
        </section>
    </div>

    <div class="calendar-modal-backdrop" data-short-semester-participants-modal hidden>
        <section class="calendar-modal" role="dialog" aria-modal="true" aria-labelledby="short-semester-participants-title">
            <div class="calendar-modal-head">
                <div>
                    <h2 id="short-semester-participants-title">Peserta Kelas SP</h2>
                    <p class="calendar-modal-copy schedule-modal-copy">Daftar mahasiswa yang tercatat pada KRS dan kelas Feeder ini.</p>
                </div>
                <button class="calendar-modal-close" type="button" data-short-semester-participants-close aria-label="Tutup dialog">×</button>
            </div>
            <div class="short-semester-student-list">
                <div class="short-semester-student-tools">
                    <input id="short-semester-participant-search" type="search" placeholder="Cari peserta..." aria-label="Cari peserta">
                </div>
                <div class="table-wrap short-semester-student-table-wrap">
                    <table class="short-semester-student-table">
                        <thead>
                            <tr><th>NIM</th><th>Nama Mahasiswa</th><th>Status</th><th>Aksi</th></tr>
                        </thead>
                        <tbody data-short-semester-participant-body>
                            <tr><td colspan="4" class="short-semester-student-empty">Memuat daftar peserta...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="short-semester-modal-pagination" data-short-semester-participant-pagination hidden>
                    <span data-short-semester-participant-page-status></span>
                    <div class="short-semester-modal-pagination-controls">
                        <select data-short-semester-participant-page-size aria-label="Jumlah peserta per halaman">
                            <option value="10">10 / halaman</option>
                            <option value="25">25 / halaman</option>
                            <option value="50">50 / halaman</option>
                        </select>
                        <button type="button" data-short-semester-participant-previous>‹ Sebelumnya</button>
                        <button type="button" data-short-semester-participant-next>Selanjutnya ›</button>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="calendar-modal-backdrop" data-short-semester-progress-modal hidden>
        <section class="calendar-modal schedule-progress-modal" role="dialog" aria-modal="true" aria-labelledby="short-semester-progress-title" aria-describedby="short-semester-progress-message">
            <h2 id="short-semester-progress-title">Memproses Jadwal SP</h2>
            <p id="short-semester-progress-message" aria-live="polite">Mengolah data jadwal SP dan pengampu di Feeder...</p>
            <div class="schedule-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                <span data-short-semester-progress-bar></span>
            </div>
            <strong data-short-semester-progress-value>0%</strong>
            <ul data-short-semester-progress-errors hidden></ul>
            <div class="calendar-modal-actions">
                <button class="button button-primary" type="button" data-short-semester-progress-close hidden>Tutup</button>
            </div>
        </section>
    </div>
    @endif

    <div class="calendar-modal-backdrop" data-schedule-confirm-modal hidden>
        <section class="calendar-modal" role="dialog" aria-modal="true" aria-labelledby="schedule-confirm-title" aria-describedby="schedule-confirm-message" tabindex="-1">
            <div class="calendar-modal-head">
                <div>
                    <h2 id="schedule-confirm-title" data-schedule-confirm-title>Konfirmasi Jadwal</h2>
                    <p class="calendar-modal-copy schedule-modal-copy" id="schedule-confirm-message" data-schedule-confirm-message></p>
                </div>
                <button class="calendar-modal-close" type="button" data-schedule-confirm-close aria-label="Tutup dialog">×</button>
            </div>
            <div class="calendar-modal-actions">
                <button class="button" type="button" data-schedule-confirm-cancel>Batal</button>
                <button class="button button-primary" type="button" data-schedule-confirm-submit>Ya, lanjutkan</button>
            </div>
        </section>
    </div>
@endsection