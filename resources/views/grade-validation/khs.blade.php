@extends('layouts.simak')

@section('title', 'KHS Mahasiswa | SIAKAD')
@section('topbar_title', 'Validasi Nilai · KHS Mahasiswa')

@section('content')
    <div class="page-heading">
        <div><p class="eyebrow">Validasi Nilai Mahasiswa</p><h1>Kartu Hasil Studi</h1><p class="subtitle">{{ $record->nama_mahasiswa }} · {{ $record->nim }} · {{ $record->nama_program_studi ?: '-' }}</p></div>
        <a class="button button-secondary" href="{{ route('grade-validation.index') }}"><span data-lucide="arrow-left"></span> Kembali ke daftar</a>
    </div>
    <form class="grade-validation-filter" method="GET" action="{{ route('grade-validation.khs', $record->id_mahasiswa) }}">
        <label for="grade-calendar">Pilih tahun akademik</label>
        <select id="grade-calendar" name="calendar" required>
            <option value="">Pilih tahun akademik</option>
            @foreach ($calendars as $item)
                <option value="{{ $item->id_kalender }}" @selected($calendar && (string) $calendar->id_kalender === (string) $item->id_kalender)>{{ $item->namatahun ?: $item->tahun }}</option>
            @endforeach
        </select>
        <button class="button button-primary" type="submit"><span data-lucide="search"></span> Tampilkan KHS</button>
    </form>
    @if ($calendars->isEmpty())
        <div class="master-empty-state"><strong>Riwayat KHS belum tersedia</strong>Mahasiswa belum memiliki KRS pada kalender akademik mana pun.</div>
    @elseif ($report)
        <section class="grade-validation-summary">
            <div><strong>Fakultas</strong><span>{{ $record->nama_fakultas ?: '-' }}</span></div>
            <div><strong>Tahun Akademik</strong><span>{{ $calendar->namatahun ?: $calendar->tahun }}</span></div>
            <div><strong>IPS</strong><span>{{ number_format($report['ips'], 2) }}</span></div>
            <div><strong>IPK</strong><span>{{ number_format($report['ipk'], 2) }}</span></div>
            <div><strong>SKS Semester</strong><span>{{ number_format($report['sks_semester'], 0) }}</span></div>
            <div><strong>Total SKS Diprogram</strong><span>{{ number_format($report['sks_program'], 0) }}</span></div>
            <div><strong>Total SKS Lulus</strong><span>{{ number_format($report['sks_lulus'], 0) }}</span></div>
            <div><strong>Maksimum SKS Berikutnya</strong><span>{{ $report['maximum_credits'] }}</span></div>
            <div><strong>Penasehat Akademik</strong><span>{{ $report['advisor'] ?: '-' }}</span></div>
        </section>
        <div class="table-wrap">
            <table @if ($report['courses']->isNotEmpty()) data-simple-search-table data-search-placeholder="Cari kode atau nama mata kuliah" @endif>
                <thead><tr><th>No.</th><th>Kode MK</th><th>Nama Mata Kuliah</th><th>Nilai Huruf</th><th>Bobot</th><th>SKS</th><th>Mutu</th></tr></thead>
                <tbody>
                    @forelse ($report['courses'] as $course)
                        <tr><td>{{ $loop->iteration }}</td><td>{{ $course->kode_mata_kuliah ?: '-' }}</td><td>{{ $course->nama_mata_kuliah ?: 'Mata kuliah tidak ditemukan' }}</td><td>{{ $course->nilai_huruf ?: '-' }}</td><td>{{ number_format($course->nilai_bobot, 2) }}</td><td>{{ number_format($course->sks_mata_kuliah, 2) }}</td><td>{{ number_format($course->mutu, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="7" class="grade-validation-empty">Belum ada mata kuliah pada kalender akademik ini.</td></tr>
                    @endforelse
                </tbody>
                @if ($report['courses']->isNotEmpty())
                    <tfoot>
                        <tr><th colspan="6" class="grade-validation-total-label">Jumlah</th><th>{{ number_format($report['sks_semester'], 0) }} SKS</th><th>{{ number_format($report['quality_points'], 2) }}</th></tr>
                        <tr><th colspan="6" class="grade-validation-total-label">IPS Mahasiswa</th><th colspan="2">{{ number_format($report['ips'], 2) }}</th></tr>
                        <tr><th colspan="6" class="grade-validation-total-label">IPK Mahasiswa</th><th colspan="2">{{ number_format($report['ipk'], 2) }}</th></tr>
                    </tfoot>
                @endif
            </table>
        </div>
    @endif
@endsection


