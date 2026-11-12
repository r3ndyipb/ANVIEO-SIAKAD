@extends('layouts.simak')

@section('title', 'Detail KRS Transfer | SIAKAD')
@section('topbar_title', 'KRS Transfer')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1>KRS Transfer</h1>
            <p class="subtitle">Daftar mata kuliah yang diakui dan hasil konversi nilai mahasiswa.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <div class="transfer-profile">
        <div><strong>NPM</strong><span>{{ $student->nim }}</span></div>
        <div><strong>Nama</strong><span>{{ $student->nama_mahasiswa }}</span></div>
        <div><strong>Program Studi</strong><span>{{ $student->nama_program_studi ?: '-' }}</span></div>
        <div><strong>Status</strong><span>Pindahan / Transfer</span></div>
        <div><strong>Batas SKS Diakui</strong><span>{{ $student->sks_diakui ?: 0 }} SKS</span></div>
        <div><strong>SKS Diprogram</strong><span>{{ number_format($creditTotal, 0) }} SKS</span></div>
    </div>

    <div class="transfer-toolbar">
        <a class="button" href="{{ route('krs-transfer.index', request()->only('search')) }}"><span data-lucide="eye"></span> Kembali ke pencarian</a>
        @if ($canManage)
            <a class="button button-primary" href="{{ route('krs-transfer.courses.edit', $student->id_mahasiswa) }}"><span data-lucide="{{ $courses->isNotEmpty() ? 'pencil' : 'circle-check' }}"></span> {{ $courses->isNotEmpty() ? 'Edit KRS Transfer' : 'Program KRS Transfer' }}</a>
            @if ($courses->isNotEmpty())
                <a class="button" href="{{ route('krs-transfer.grades.edit', $student->id_mahasiswa) }}"><span data-lucide="pencil"></span> Input Nilai Transfer</a>
            @endif
        @endif
        @if ($courses->isNotEmpty())
            <a class="button" href="{{ route('krs-transfer.print', $student->id_mahasiswa) }}" target="_blank" rel="noopener"><span data-lucide="printer"></span> Cetak Nilai Transfer</a>
        @endif
    </div>

    @if ($courses->isNotEmpty())
        <div class="table-wrap">
            <table data-simple-search-table>
                <thead>
                    <tr><th>No</th><th>Kode MK</th><th>Nama Mata Kuliah</th><th>SKS</th><th>Nilai Diakui</th><th>SKS Asal</th><th>Nilai Asal</th></tr>
                </thead>
                <tbody>
                    @foreach ($courses as $course)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $course->kode_mata_kuliah ?: '-' }}</td>
                            <td>{{ $course->nama_mata_kuliah ?: 'Mata kuliah tidak ditemukan' }}</td>
                            <td>{{ number_format((float) $course->sks_mata_kuliah, 0) }}</td>
                            <td>{{ $course->nilai_huruf ?: 'E' }}@if ($course->nilai_angka !== null) ({{ number_format((float) $course->nilai_angka, 0) }}) @endif</td>
                            <td>{{ $course->sks_asal ?: '-' }}</td>
                            <td>{{ $course->nilai_huruf_asal ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="master-empty-state"><strong>Belum ada mata kuliah transfer</strong>@if ($canManage) Gunakan tombol Program KRS Transfer untuk memilih mata kuliah yang diakui. @else Data KRS transfer mahasiswa belum tersedia. @endif</div>
    @endif
@endsection
