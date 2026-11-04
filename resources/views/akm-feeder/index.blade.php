@extends('layouts.simak')

@section('title', 'Cek AKM SIAKAD - Feeder | SIAKAD')
@section('topbar_title', 'Cek AKM SIAKAD - Feeder')

@push('styles')
    <style>
        .akm-feeder-filters { display:grid; grid-template-columns:minmax(220px, 1fr) minmax(220px, 1fr) max-content; align-items:end; gap:12px; margin:16px 0 20px; }
        .akm-feeder-filters label { display:grid; gap:6px; color:#52634f; font-size:11px; font-weight:700; }
        .akm-feeder-filters select { min-height:38px; padding:8px 10px; border:1px solid #d9e2d7; border-radius:5px; background:#fff; color:#354a38; }
        .akm-feeder-table-wrap { overflow-x:auto; border:1px solid #e2e9e0; border-radius:7px; background:#fff; }
        .akm-feeder-table { width:100%; min-width:840px; border-collapse:collapse; font-size:11px; }
        .akm-feeder-table th, .akm-feeder-table td { padding:10px 11px; border-bottom:1px solid #edf1eb; text-align:left; vertical-align:middle; }
        .akm-feeder-table th { background:#f7f9f5; color:#63725e; font-size:9px; text-transform:uppercase; white-space:nowrap; }
        .akm-feeder-table select { min-width:190px; min-height:36px; padding:6px 8px; border:1px solid #d9e2d7; border-radius:5px; background:#fff; }
        .akm-feeder-muted { color:#71806e; }
        @media (max-width:760px) { .akm-feeder-filters { grid-template-columns:1fr; } }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Administrasi</p>
            <h1>Cek AKM SIAKAD - Feeder</h1>
            <div class="module-alert" role="note"><p class="subtitle">Bandingkan status mahasiswa pada AKM SIAKAD dengan Aktivitas Kuliah Mahasiswa di Feeder.</p></div>
        </div>
    </div>

    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif

    <form class="akm-feeder-filters" method="GET" action="{{ route('akm-feeder.index') }}">
        @if ($user->role === 'admin')
            <label for="akm-feeder-program">Program studi
                <select id="akm-feeder-program" name="program" required data-searchable-select="filter" onchange="this.form.elements.calendar.value = ''; this.form.submit()">
                    <option value="">Pilih program studi</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id_prodi }}" @selected($programId === (string) $program->id_prodi)>{{ $program->nama_program_studi }}{{ $program->nama_jenjang_pendidikan ? ' ('.$program->nama_jenjang_pendidikan.')' : '' }}</option>
                    @endforeach
                </select>
            </label>
        @else
            <input type="hidden" name="program" value="{{ $programId }}">
            <div class="module-alert" role="note"><p>{{ $programs->firstWhere('id_prodi', $programId)->nama_program_studi ?? 'Program studi sesuai akun' }}</p></div>
        @endif
        <label for="akm-feeder-calendar">Tahun akademik
            <select id="akm-feeder-calendar" name="calendar" required data-searchable-select="filter" @disabled($programId === '' || $calendars->isEmpty())>
                <option value="">Pilih tahun akademik</option>
                @foreach ($calendars as $calendarOption)
                    <option value="{{ $calendarOption->id_kalender }}" @selected((string) ($calendar?->id_kalender ?? '') === (string) $calendarOption->id_kalender)>{{ $calendarOption->namatahun ?: $calendarOption->tahun }}</option>
                @endforeach
            </select>
        </label>
        <button class="button button-primary" type="submit" @disabled($programId === '' || $calendars->isEmpty())><span data-lucide="search"></span> Tampilkan</button>
    </form>

    @if ($programId === '')
        <div class="master-empty-state"><strong>Pilih program studi</strong>Pilih program studi dan tahun akademik untuk membandingkan status AKM.</div>
    @elseif ($calendars->isEmpty())
        <div class="master-empty-state"><strong>Kalender akademik belum tersedia</strong>Program studi ini belum memiliki kalender akademik.</div>
    @elseif (! $calendar)
        <div class="master-empty-state"><strong>Pilih tahun akademik</strong>Pilih kalender untuk memuat data mahasiswa.</div>
    @else
        <div class="akm-feeder-table-wrap">
            <table class="akm-feeder-table" data-akm-feeder-table
                data-program="{{ $programId }}"
                data-calendar="{{ $calendar->id_kalender }}"
                data-ajax-url="{{ route('akm-feeder.data', ['program' => $programId, 'calendar' => $calendar->id_kalender]) }}"
                data-update-url="{{ route('akm-feeder.status.update') }}"
                data-status-options="{{ $statusOptions->map(fn (object $status): array => ['code' => $status->kode_referensi, 'label' => $status->ket_referensi])->values()->toJson() }}">
                <thead><tr><th>No.</th><th>NPM</th><th>Nama mahasiswa</th><th>Tahun akademik</th><th>Status SIAKAD</th><th>Status Feeder</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    @endif
@endsection