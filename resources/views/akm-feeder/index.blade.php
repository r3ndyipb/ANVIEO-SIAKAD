@extends('layouts.simak')

@section('title', 'Cek AKM SIAKAD - Feeder | SIAKAD')
@section('topbar_title', 'Cek AKM SIAKAD - Feeder')

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