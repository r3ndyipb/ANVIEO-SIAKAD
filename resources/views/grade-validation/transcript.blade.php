@extends('layouts.simak')

@section('title', 'Transkrip Nilai Mahasiswa | SIAKAD')
@section('topbar_title', 'Validasi Nilai · Transkrip Mahasiswa')

@section('content')
    <div class="page-heading">
        <div><p class="eyebrow">Validasi Nilai Mahasiswa</p><h1>Transkrip Nilai</h1><p class="subtitle">{{ $record->nama_mahasiswa }} · {{ $record->nim }} · {{ $record->nama_program_studi ?: '-' }}</p></div>
        <a class="button button-secondary" href="{{ route('grade-validation.index') }}"><span data-lucide="arrow-left"></span> Kembali ke daftar</a>
    </div>
    <section class="grade-validation-summary">
        <div><strong>Fakultas</strong><span>{{ $record->nama_fakultas ?: '-' }}</span></div>
        <div><strong>Program Studi</strong><span>{{ $record->nama_program_studi ?: '-' }}</span></div>
        <div><strong>Total SKS</strong><span>{{ number_format($report['total_sks'], 0) }} SKS</span></div>
        <div><strong>Total Mutu</strong><span>{{ number_format($report['total_quality'], 2) }}</span></div>
        <div><strong>IPK</strong><span>{{ number_format($report['ipk'], 2) }}</span></div>
        <div><strong>Jumlah Mata Kuliah</strong><span>{{ $report['courses']->count() }}</span></div>
    </section>
    @if ($report['courses']->isEmpty())
        <div class="master-empty-state">Belum ada riwayat nilai pada transkrip mahasiswa ini.</div>
    @else
        <div class="table-wrap">
            <table data-simple-search-table data-search-placeholder="Cari kode atau nama mata kuliah">
                <thead><tr><th>No.</th><th>Kode MK</th><th>Nama Mata Kuliah</th><th>Semester</th><th>Nilai Huruf</th><th>Bobot</th><th>SKS</th><th>Mutu</th></tr></thead>
                <tbody>
                    @foreach ($report['courses'] as $course)
                        <tr><td>{{ $loop->iteration }}</td><td>{{ $course->kode_mata_kuliah ?: '-' }}</td><td>{{ $course->nama_mata_kuliah ?: 'Mata kuliah tidak ditemukan' }}</td><td>{{ $course->semester ?: '-' }}</td><td>{{ $course->nilai_huruf ?: '-' }}</td><td>{{ number_format($course->nilai_bobot, 2) }}</td><td>{{ number_format($course->sks_mata_kuliah, 2) }}</td><td>{{ number_format($course->mutu, 2) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

@push('styles')
<style>
    .grade-validation-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(155px, 1fr)); gap: 12px; margin: 16px 0; padding: 16px; border: 1px solid #dce5e0; border-radius: 6px; background: #fff; }
    .grade-validation-summary div { display: grid; gap: 4px; }
    .grade-validation-summary strong { color: #66756d; font-size: 10px; text-transform: uppercase; }
    .grade-validation-summary span { color: #294438; font-size: 12px; font-weight: 700; }
</style>
@endpush
