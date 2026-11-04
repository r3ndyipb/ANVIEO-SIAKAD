@extends('layouts.simak')

@php
    $shortSemester = $shortSemester ?? false;
    $gradeRoutePrefix = $gradeRoutePrefix ?? 'grade-management';
    $gradeTitle = $shortSemester ? 'Nilai SP' : 'Nilai Mata Kuliah';
@endphp
@section('title', 'Input '.$gradeTitle.' | SIAKAD')
@section('topbar_title', 'Input '.$gradeTitle)

@push('styles')
    <style>
        .grade-grid-shell { height: min(66vh, 650px); min-height: 280px; margin-top: 12px; overflow: hidden; border: 1px solid #dfe7dc; border-radius: 6px; }
        .grade-spreadsheet { width: 100%; height: 100%; font-size: 11px; --rdg-color: #24382d; --rdg-background-color: #fff; --rdg-header-background-color: #f3f7f1; --rdg-border-color: #e3e9e0; --rdg-selection-color: #3b7850; --rdg-font-size: 11px; }
        .grade-spreadsheet .rdg-header-row { font-weight: 800; }
        .grade-spreadsheet .grade-grid-editable { background: #fffef6; }
        .grade-spreadsheet .grade-grid-disabled { background: #edf0ea; color: #858b81; }
        .grade-spreadsheet .grade-grid-over { color: #a63b2c; }
        .grade-spreadsheet .rdg-cell { padding-inline: 10px; }
        .grade-grid-empty { padding: 24px; color: #69745c; }
        .grade-paste-note { margin: 12px 0; color: #687563; font-size: 11px; }
        .grade-progress-track { height: 10px; margin: 16px 0 8px; overflow: hidden; border-radius: 6px; background: #e8eee6; }
        .grade-progress-track span { display: block; height: 100%; background: #4a8a58; transition: width .2s ease; }
        .grade-progress-errors { max-height: 220px; overflow-y: auto; padding-left: 20px; color: #8c382e; font-size: 12px; line-height: 1.5; }
        .grade-confirm-backdrop { position: fixed; inset: 0; z-index: 140; display: grid; overflow-y: auto; padding: 8vh 16px 20px; place-items: start center; background: rgba(16, 42, 66, .52); backdrop-filter: blur(3px); }
        .grade-confirm-dialog { width: min(100%, 620px); padding: clamp(20px, 4vw, 34px); border: 1px solid #d5e2ef; border-radius: 9px; background: #fff; box-shadow: 0 24px 70px rgba(18, 44, 70, .25); }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ $shortSemester ? 'Semester Pendek' : 'Akademik' }} · {{ $schedule->nama_program_studi }}</p>
            <h1>{{ $schedule->nama_mata_kuliah }}</h1>
            <p class="subtitle">{{ $schedule->nama_kelas }} · {{ $schedule->namatahun ?: $schedule->tahun }}</p>
        </div>
        <a class="button" href="{{ route($gradeRoutePrefix.'.index', ['program' => $schedule->id_prodi]) }}"><span data-lucide="arrow-left"></span> Kembali</a>
    </div>

    @if ($errors->any()) <div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div> @endif
    @if (session('status')) <div class="alert alert-success" role="status">{{ session('status') }}</div> @endif
    @if ($unlockOnly)
        <p class="grade-paste-note">Konfirmasi akan membuka kembali input nilai seluruh mahasiswa pada kelas ini.</p>
    @else
        <div class="calendar-confirm-detail" role="note">
            <strong>Komponen evaluasi:</strong>
            @foreach ($evaluationComponents as $component)
                {{ $component['jenis_evaluasi'] ?? '-' }} · {{ $component['nama_evaluasi'] ?? '-' }} ({{ number_format((float) ($component['bobot_evaluasi'] ?? 0), 0) }}%)@if (! $loop->last) · @endif
            @endforeach
            <br><strong>Rentang nilai:</strong>
            @foreach ($gradeRanges as $range)
                {{ $range->nilai_huruf }} {{ $range->bobot_minimum }}–{{ $range->bobot_maksimum }}@if (! $loop->last) · @endif
            @endforeach
            <br>Total komponen dibulatkan sebelum dipetakan ke nilai huruf/bobot dan tidak boleh melebihi 100.
        </div>
    @endif
    @if (! $unlockOnly && $isLocked && ! in_array($user->role, ['admin', 'userprodi'], true))
        <div class="calendar-modal-error" role="status">Nilai sudah disimpan. Hubungi pengelola prodi jika perlu dibuka kembali.</div>
    @endif

    @if (! $unlockOnly && $canEdit)
        <p class="grade-paste-note">Skor tiap komponen dikalikan persentase evaluasi sebelum dijumlahkan. Komponen berbobot 0 dikunci; total akhir maksimal 100.</p>
    @elseif (! $unlockOnly)
        <p class="grade-paste-note">Nilai terkunci. Pengelola prodi dapat membuka kembali nilai per mahasiswa bila diperlukan.</p>
    @endif
    <div
        data-grade-spreadsheet
        data-program="{{ $schedule->id_prodi }}"
        data-update-url="{{ $shortSemester ? route($gradeRoutePrefix.'.update', ['schedule' => $schedule->id_jadwal, 'krs' => '__KRS__']) : route($gradeRoutePrefix.'.update', ['schedule' => $schedule->id_jadwal]) }}"
        data-sequential-save="{{ $shortSemester ? 'true' : 'false' }}"
        data-cancel-url="{{ route($gradeRoutePrefix.'.index', ['program' => $schedule->id_prodi]) }}"
        data-unlock-url="{{ route($gradeRoutePrefix.'.unlock', ['schedule' => $schedule->id_jadwal]) }}"
        data-class-label="{{ $schedule->nama_mata_kuliah }} · {{ $schedule->nama_kelas }}"
        data-can-edit="{{ $canEdit ? 'true' : 'false' }}"
        data-unlock-only="{{ $unlockOnly ? 'true' : 'false' }}"
        data-confirm-save="{{ $canEdit ? 'true' : 'false' }}"
        data-confirm-once="{{ in_array($user->role, ['dosen', 'asdos'], true) ? 'true' : 'false' }}"
    >
        <script type="application/json" data-grade-rows>@json($gradeRows)</script>
        @unless ($unlockOnly)
            <script type="application/json" data-grade-ranges>@json($gradeRanges->map(fn ($range) => ['nilai_huruf' => $range->nilai_huruf, 'bobot_minimum' => $range->bobot_minimum, 'bobot_maksimum' => $range->bobot_maksimum])->values())</script>
            <script type="application/json" data-enabled-fields>@json(array_keys(array_filter($enabledComponents)))</script>
            <script type="application/json" data-component-weights>@json($componentWeights)</script>
        @endunless
    </div>
@endsection