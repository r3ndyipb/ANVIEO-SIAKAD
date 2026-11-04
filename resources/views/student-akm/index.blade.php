@extends('layouts.simak')

@section('title', 'Aktivitas Kuliah Mahasiswa | SIAKAD')
@section('topbar_title', 'Aktivitas Kuliah Mahasiswa')

@push('styles')
    <style>
        .akm-filter { display: grid; grid-template-columns: minmax(220px, 1fr) max-content; align-items: end; gap: 12px; margin: 16px 0 20px; }
        .akm-filter.with-program { grid-template-columns: minmax(220px, 1fr) minmax(220px, 1fr) max-content; }
        .akm-filter > button { width: auto; white-space: nowrap; }
        .akm-filter label { display: block; margin-bottom: 7px; color: #43543c; font-size: 11px; font-weight: 800; }
        .akm-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 14px 0; }
        .akm-actions form { margin: 0; }
        .akm-status-form { display: flex; align-items: center; gap: 6px; }
        .akm-status-form select { min-width: 190px; }
        .akm-alert { margin: 14px 0; padding: 12px 15px; border: 1px solid #d8e4d3; border-radius: 7px; background: #f3f8f0; color: #395c3c; }
        @media (max-width: 820px) { .akm-filter, .akm-filter.with-program { grid-template-columns: 1fr; } }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1>Aktivitas Kuliah Mahasiswa</h1>
            <div class="module-alert" role="note"><p class="subtitle">Generate dan verifikasi status aktivitas mahasiswa per kalender akademik. Status ditentukan otomatis: mahasiswa yang sudah registrasi ulang berstatus Aktif, sedangkan yang belum registrasi ulang berstatus Non-Aktif.</p></div>
        </div>
    </div>

    @if ($errors->any()) <div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div> @endif
    @if (session('status')) <div class="alert alert-success" role="status">{{ session('status') }}</div> @endif

    <form class="akm-filter {{ $user->role === 'admin' ? 'with-program' : 'without-program' }}" method="GET" action="{{ route('student-akm.index') }}">
        @if ($user->role === 'admin')
            <div>
                <label for="akm-program">Program studi</label>
                <select id="akm-program" name="program" required data-searchable-select="filter" onchange="this.form.elements.calendar.value = ''; this.form.submit()">
                    <option value="">Pilih program studi</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id_prodi }}" @selected((string) $programId === (string) $program->id_prodi)>{{ $program->nama_program_studi }} {{ $program->nama_jenjang_pendidikan ? '('.$program->nama_jenjang_pendidikan.')' : '' }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div>
            <label for="akm-calendar">Tahun akademik</label>
            <select id="akm-calendar" name="calendar" required data-searchable-select="filter" @disabled($programId === '' || $calendars->isEmpty()) onchange="this.form.submit()">
                <option value="">Pilih tahun akademik</option>
                @foreach ($calendars as $calendarOption)
                    <option value="{{ data_get($calendarOption, 'id_kalender') }}" @selected((string) data_get($calendar, 'id_kalender', '') === (string) data_get($calendarOption, 'id_kalender'))>
                        {{ data_get($calendarOption, 'namatahun') ?: data_get($calendarOption, 'tahun') }}{{ data_get($calendarOption, 'status') === 'Y' ? ' (Aktif)' : '' }}
                    </option>
                @endforeach
            </select>
        </div>
        <button class="button button-primary" type="submit" @disabled($programId === '' || $calendars->isEmpty())><span data-lucide="search"></span> Tampilkan</button>
    </form>

    @if ($user->role === 'admin' && $programId === '')
        <div class="master-empty-state"><strong>Pilih program studi</strong>Pilih program studi untuk memuat kalender akademik.</div>
    @elseif ($programId !== '' && $calendars->isEmpty())
        <div class="master-empty-state"><strong>Kalender akademik belum tersedia</strong>Program studi ini belum memiliki kalender akademik.</div>
    @elseif ($programId !== '' && ! $calendar)
        <div class="master-empty-state"><strong>Pilih tahun akademik</strong>Pilih kalender untuk menampilkan aktivitas kuliah mahasiswa.</div>
    @else
        @if ($user->role !== 'admin')
            <p class="subtitle">{{ $programs->firstWhere('id_prodi', $programId)->nama_program_studi ?? '' }}</p>
        @endif
        @if ($calendar)
            <div class="akm-actions">
                <a class="button button-secondary" href="{{ route('student-akm.export.excel', ['program' => $programId, 'calendar' => data_get($calendar, 'id_kalender')]) }}" data-page-loader-skip><span data-lucide="sheet"></span> Unduh Excel</a>
            </div>
        @endif
        @if ($calendar && $locked)
            <div class="akm-alert" role="status">Data aktivitas kuliah {{ data_get($calendar, 'namatahun') ?: data_get($calendar, 'tahun') }} sudah disimpan permanen dan bersifat baca saja.</div>
        @elseif ($calendar && $rows->isEmpty())
            <div class="akm-actions">
                <form method="POST" action="{{ route('student-akm.generate') }}" onsubmit="return window.confirm('Generate data aktivitas kuliah mahasiswa untuk kalender ini? Data AKM yang belum disimpan permanen akan diganti.')">
                    @csrf
                    <input type="hidden" name="program" value="{{ $programId }}">
                    <input type="hidden" name="calendar" value="{{ data_get($calendar, 'id_kalender') }}">
                    <button class="button button-primary" type="submit"><span data-lucide="refresh-cw"></span> Generate Data</button>
                </form>
            </div>
        @else
            <div class="akm-actions">
                @if (! $locked)
                    <form method="POST" action="{{ route('student-akm.generate') }}" onsubmit="return window.confirm('Generate ulang AKM untuk kalender ini? Perubahan status yang belum disimpan permanen akan diganti.')">
                        @csrf
                        <input type="hidden" name="program" value="{{ $programId }}">
                        <input type="hidden" name="calendar" value="{{ data_get($calendar, 'id_kalender') }}">
                        <button class="button" type="submit"><span data-lucide="refresh-cw"></span> Generate Ulang</button>
                    </form>
                    <form method="POST" action="{{ route('student-akm.lock') }}" onsubmit="return window.confirm('Simpan data aktivitas kuliah ini secara permanen? Status mahasiswa tidak dapat diubah setelahnya.')">
                        @csrf
                        <input type="hidden" name="program" value="{{ $programId }}">
                        <input type="hidden" name="calendar" value="{{ data_get($calendar, 'id_kalender') }}">
                        <button class="button button-primary" type="submit"><span data-lucide="lock-keyhole"></span> Simpan Permanen</button>
                    </form>
                @endif
            </div>
            <div class="table-wrap">
                <table data-simple-search-table>
                    <thead><tr><th>No</th><th>NPM</th><th>Nama Mahasiswa</th><th>Tahun Akademik</th><th>IPS</th><th>IPK</th><th>SKS Semester</th><th>SKS Program</th><th>SKS Lulus</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>{{ $rows->firstItem() + $loop->index }}</td>
                                <td>{{ $row->nim }}</td>
                                <td>{{ $row->nama_mahasiswa }}</td>
                                <td>{{ data_get($calendar, 'namatahun') ?: data_get($calendar, 'tahun') }}</td>
                                <td>{{ number_format((float) $row->ips, 2) }}</td>
                                <td>{{ number_format((float) $row->ipk, 2) }}</td>
                                <td>{{ number_format((float) $row->sks_semester, 2) }}</td>
                                <td>{{ number_format((float) $row->sks_program, 2) }}</td>
                                <td>{{ number_format((float) $row->sks_lulus, 2) }}</td>
                                <td>
                                    @if ($locked)
                                        {{ $statusOptions->firstWhere('kode_referensi', $row->status_mahasiswa)->ket_referensi ?? $row->status_mahasiswa }}
                                    @else
                                        <form class="akm-status-form" method="POST" action="{{ route('student-akm.status.update', $row->id_ipmhs) }}" data-autosave-form>
                                            @csrf
                                            <input type="hidden" name="program" value="{{ $programId }}">
                                            <input type="hidden" name="calendar" value="{{ data_get($calendar, 'id_kalender') }}">
                                            <select name="status" required data-searchable-select="filter" data-autosave-select onchange="this.form.requestSubmit()" aria-label="Status {{ $row->nama_mahasiswa }}">
                                                @foreach ($statusOptions as $status)
                                                    <option value="{{ $status->kode_referensi }}" @selected((string) $row->status_mahasiswa === (string) $status->kode_referensi)>{{ $status->kode_referensi }} · {{ $status->ket_referensi }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="student-status-pagination" data-akm-pagination>{{ $rows->links('vendor.pagination.datatables') }}</div>
        @endif
    @endif
@endsection