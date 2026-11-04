@extends('layouts.simak')

@section('title', $calendarLabel.' | SIAKAD')
@section('topbar_title', $calendarLabel)

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Data Akademik</p>
            <h1>{{ $calendarLabel }}</h1>
            <div class="module-alert" role="note"><p class="subtitle">{{ $isShortSemester ? 'Periode dan rentang pembukaan KRS serta pengisian nilai Semester Pendek.' : 'Periode perkuliahan dan rentang pembukaan KRS serta pengisian nilai.' }}</p></div>
        </div>
        @if ($canCreate)
            @if ($isShortSemester)
                <div class="page-actions">
                    <button class="button button-primary" type="button" data-calendar-create>Buat Periode SP</button>
                    @if ($canSync)
                        <form method="POST" action="{{ route('short-semester-curricula.sync') }}">
                            @csrf
                            <button class="button" type="submit">Sinkron Kurikulum SP</button>
                        </form>
                    @endif
                </div>
            @else
                <button class="button button-primary" type="button" data-calendar-create>Buat Periode</button>
            @endif
        @endif
    </div>

    @if ($isShortSemester)
        @include('components.short-semester-feeder-alert')
    @endif

    @if (session('status'))
        <div class="module-alert calendar-sync-alert" role="status" aria-live="polite">{{ session('status') }}</div>
    @endif
    @if ($errors->has('feeder'))
        <div class="calendar-modal-error" role="alert">{{ $errors->first('feeder') }}</div>
    @endif

    <div class="table-wrap">
        <table id="academic-calendar-table" data-datatable="academic-calendar" data-calendar-label="{{ $calendarLabel }}">
            <thead>
                <tr>
                    <th>Tahun Akademik</th>
                    <th>Prodi</th>
                    <th>Periode</th>
                    <th>Rentang KRS</th>
                    <th>Rentang Input Nilai</th>
                    <th>Status</th>
                    @if ($canEdit || $canActivate) <th class="no-export">Aksi</th> @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($calendars as $calendar)
                    <tr data-calendar-row="{{ $calendar->id_kalender }}">
                        <td class="period">{{ $calendar->namatahun ?: $calendar->tahun }}</td>
                        <td>{{ $calendar->nama_program_studi ?: 'Prodi tidak ditemukan' }}<span class="subtext">{{ $calendar->nama_jenjang_pendidikan }}</span></td>
                        <td>{{ $calendar->periode }}</td>
                        <td>{{ $calendar->tgl_mulai_krs ?: '-' }}<span class="subtext">s.d. {{ $calendar->tgl_akhir_krs ?: '-' }}</span></td>
                        <td>{{ $calendar->tgl_mulai_isinilai ?: '-' }}<span class="subtext">s.d. {{ $calendar->tgl_akhir_isinilai ?: '-' }}</span></td>
                        <td data-calendar-status><span class="status-chip {{ $calendar->status === 'Y' ? 'active' : '' }}">{{ $calendar->status === 'Y' ? 'Aktif' : 'Tidak aktif' }}</span></td>
                        @if ($canEdit || $canActivate)
                            <td>
                                <div class="row-actions">
                                    @if ($canEdit)
                                        <button
                                            class="table-icon-button"
                                            type="button"
                                            data-calendar-edit
                                            data-calendar-id="{{ $calendar->id_kalender }}"
                                            data-calendar-name="{{ $calendar->namatahun ?: $calendar->tahun }}"
                                            data-tgl-mulai-krs="{{ $calendar->tgl_mulai_krs }}"
                                            data-tgl-akhir-krs="{{ $calendar->tgl_akhir_krs }}"
                                            data-tgl-mulai-isinilai="{{ $calendar->tgl_mulai_isinilai }}"
                                            data-tgl-akhir-isinilai="{{ $calendar->tgl_akhir_isinilai }}"
                                            aria-label="Ubah tanggal kalender"
                                            title="Ubah tanggal kalender"
                                        ><span data-lucide="pencil" aria-hidden="true"></span></button>
                                    @endif
                                    @if ($canActivate)
                                        <button
                                            class="table-icon-button activate"
                                            type="button"
                                            data-calendar-toggle
                                            data-calendar-id="{{ $calendar->id_kalender }}"
                                            data-calendar-active="{{ $calendar->status === 'Y' ? 'true' : 'false' }}"
                                            aria-label="{{ $calendar->status === 'Y' ? 'Nonaktifkan kalender' : 'Aktifkan kalender' }}"
                                            title="{{ $calendar->status === 'Y' ? 'Nonaktifkan kalender' : 'Aktifkan kalender' }}"
                                        ><span data-lucide="{{ $calendar->status === 'Y' ? 'circle-minus' : 'circle-check' }}" aria-hidden="true"></span></button>
                                    @endif
                                </div>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($canCreate || $canEdit || $canActivate)
        <div
            id="calendar-actions-root"
            data-create-url="{{ route($routePrefix.'.store') }}"
            data-update-url-template="{{ route($routePrefix.'.update', '__CALENDAR__') }}"
            data-status-url-template="{{ route($routePrefix.'.status', '__CALENDAR__') }}"
            data-period-options='@json($periodOptions ?? [])'
            data-calendar-label="{{ $calendarLabel }}"
            data-can-create="{{ $canCreate ? 'true' : 'false' }}"
            data-can-edit="{{ $canEdit ? 'true' : 'false' }}"
            data-can-activate="{{ $canActivate ? 'true' : 'false' }}"
        ></div>
    @endif
@endsection
