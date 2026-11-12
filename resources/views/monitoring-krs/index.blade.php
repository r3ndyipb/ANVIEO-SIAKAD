@extends('layouts.simak')

@section('title', 'Monitoring KRS | SIAKAD')
@section('topbar_title', 'Monitoring KRS')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1>Monitoring KRS</h1>
            <div class="module-alert" role="note">
                <p class="subtitle">Data mahasiswa berstatus aktif, cuti, atau Kampus Merdeka. Status KRS dihitung berdasarkan kalender akademik aktif pada program studi.</p>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <form class="monitoring-krs-filters" method="GET" action="{{ route('monitoring-krs.index') }}">
        @if (in_array($user->role, ['admin', 'pimuniv', 'pimfak'], true))
            <div>
                <label for="monitoring-program">Program studi</label>
                <select id="monitoring-program" name="program" data-searchable-select="filter" required>
                    <option value="">Pilih program studi</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id_prodi }}" @selected((string) $selectedProgram === (string) $program->id_prodi)>
                            {{ $program->nama_program_studi }}{{ filled($program->nama_jenjang_pendidikan) ? ' ('.$program->nama_jenjang_pendidikan.')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
        @else
            <div>
                <label>Program studi</label>
                <input type="text" value="{{ $programs->first()?->nama_program_studi ?? 'Program studi akun tidak tersedia' }}" readonly>
            </div>
        @endif
        <div>
            <label for="monitoring-calendar">Tahun akademik</label>
            <select id="monitoring-calendar" name="calendar" data-searchable-select="filter" @disabled(blank($selectedProgram))>
                <option value="">Pilih tahun akademik</option>
                @foreach ($calendars as $calendar)
                    <option value="{{ $calendar->id_kalender }}" @selected((string) ($selectedCalendar?->id_kalender ?? '') === (string) $calendar->id_kalender)>
                        {{ $calendar->namatahun ?: $calendar->tahun }}{{ $calendar->status === 'Y' ? ' (Aktif)' : '' }}
                    </option>
                @endforeach
            </select>
        </div>
        <button class="button button-primary" type="submit"><span data-lucide="search"></span> Tampilkan</button>
    </form>

    @if (filled($selectedProgram) && $selectedCalendar)
        <p class="subtitle">Tahun akademik yang dipantau: <strong>{{ $selectedCalendar->namatahun ?: $selectedCalendar->tahun }}</strong></p>
    @elseif (filled($selectedProgram) && $calendars->isEmpty())
        <div class="monitoring-krs-empty">Program studi ini belum memiliki kalender akademik.</div>
    @elseif (filled($selectedProgram))
        <div class="monitoring-krs-empty">Pilih tahun akademik untuk melihat monitoring KRS.</div>
    @else
        <div class="monitoring-krs-empty">Pilih program studi untuk melihat monitoring KRS.</div>
    @endif

    @if (filled($selectedProgram) && $selectedCalendar)
        <div class="table-wrap">
                <table class="monitoring-krs-table" data-datatable="monitoring-krs" data-ajax-url="{{ route('monitoring-krs.data', ['program' => $selectedProgram, 'calendar' => $selectedCalendar->id_kalender]) }}">
                    <thead>
                        <tr>
                            <th>NPM</th>
                            <th>Nama Mahasiswa</th>
                            <th>Program Studi</th>
                            <th>Status Mahasiswa</th>
                            <th>Keterangan KRS</th>
                            <th>SKS Diprogram</th>
                            <th class="no-export">Proses</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
        </div>
    @endif
@endsection
