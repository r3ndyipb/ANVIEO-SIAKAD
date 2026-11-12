@extends('layouts.simak')

@php
    $shortSemester = $shortSemester ?? false;
    $gradeRoutePrefix = $gradeRoutePrefix ?? 'grade-management';
    $gradeTitle = $shortSemester ? 'Nilai SP' : 'Nilai Mata Kuliah';
@endphp
@section('title', 'Input '.$gradeTitle.' | SIAKAD')
@section('topbar_title', 'Input '.$gradeTitle)

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