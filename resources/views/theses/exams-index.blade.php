@extends('layouts.simak')

@section('title', 'Ujian Skripsi | SIAKAD')
@section('topbar_title', 'Ujian Skripsi')

@section('content')
    <style>
        .exam-grade-status { display: inline-flex; padding: 5px 9px; border-radius: 999px; font-size: 10px; font-weight: 800; white-space: nowrap; }
        .exam-grade-status.is-missing { background: #f5cfcc; color: #8b302b; }
        .exam-grade-status.is-entered { background: #c8ecd3; color: #205a35; }
    </style>
    <div class="page-heading"><div><p class="eyebrow">E-Skripsi</p><h1>Jadwal & Penilaian Ujian</h1><p class="subtitle">Jadwal, nilai, dan catatan revisi mengikuti penugasan dosen dan cakupan program.</p></div><a class="button" href="{{ route('theses.dashboard') }}">Dashboard E-Skripsi</a></div>
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @if ($showProgramFilter)
        <form class="master-data-filters" method="GET" action="{{ route('theses.exams.index') }}">
            <div><label for="exam-program">Program studi</label><select id="exam-program" name="program" data-searchable-select="filter"><option value="">Semua prodi</option>@foreach ($programs as $program)<option value="{{ $program->id_prodi }}" @selected($programId === (string) $program->id_prodi)>{{ $program->nama_program_studi }} {{ $program->nama_jenjang_pendidikan }}</option>@endforeach</select></div>
            <div><label for="exam-search">Cari data ujian</label><input id="exam-search" type="search" name="search" value="{{ $search }}" placeholder="NPM, nama, judul, jenis, ruang, atau program studi"></div>
            <button class="button button-primary" type="submit">Tampilkan</button>
        </form>
    @else
        <form class="master-data-filters" method="GET" action="{{ route('theses.exams.index') }}">
            <div><label for="exam-search">Cari data ujian</label><input id="exam-search" type="search" name="search" value="{{ $search }}" placeholder="NPM, nama, judul, jenis, ruang, atau program studi"></div>
            <button class="button button-primary" type="submit">Cari</button>
        </form>
    @endif
    @if ($exams->isEmpty())
        <div class="master-empty-state">{{ $search !== '' ? 'Tidak ada data ujian yang sesuai dengan pencarian.' : 'Belum ada jadwal ujian skripsi pada cakupan ini.' }}</div>
    @else
        <div class="table-wrap"><table data-datatable="thesis-list"><thead><tr><th>Mahasiswa</th><th>Judul</th><th>Pembimbing</th><th>Penguji</th><th>Jenis ujian</th><th>Jadwal & ruang</th><th>{{ $user->role === 'dosen' ? 'Keterangan' : 'Nilai' }}</th><th class="no-export">Aksi</th></tr></thead><tbody>
            @foreach ($exams as $exam)
                @php $scores = array_filter([$exam->nilaipembimbing1, $exam->nilaipembimbing2, $exam->nilaipenguji1, $exam->nilaipenguji2, $exam->nilaipenguji3], fn ($score) => $score !== null && trim((string) $score) !== ''); @endphp
                <tr><td>{{ $exam->nim }}<small class="subtext">{{ $exam->nama_mahasiswa }}</small></td><td>{{ $exam->judul }}</td><td>{{ $exam->pembimbing_names }}</td><td>{{ $exam->penguji_names }}</td><td>{{ strtoupper($exam->jenis_ujian) }}</td><td>{{ $exam->waktu_ujian ?: 'Belum dijadwalkan' }}<small class="subtext">{{ $exam->ruang_ujian ?: 'Ruang belum ditentukan' }}</small></td><td>@if ($user->role === 'dosen')<span class="exam-grade-status {{ $exam->lecturer_has_score ? 'is-entered' : 'is-missing' }}">{{ $exam->lecturer_has_score ? 'Telah mengisi nilai' : 'Belum menginput nilai' }}</span>@else{{ $scores === [] ? 'Belum dinilai' : round(array_sum($scores) / count($scores), 2).' ('.count($scores).'/5)' }}@endif</td><td><div class="row-actions"><a class="button button-small" href="{{ route('theses.exams.show', $exam->id_ujian) }}">{{ $user->role === 'dosen' ? 'Proses Ujian' : 'Lihat' }}</a>@if ($user->role === 'dosen' && ! $exam->lecturer_has_score)<button class="button button-small" type="button" disabled aria-disabled="true">Cetak Berita Acara</button>@else<a class="button button-small" href="{{ route('theses.exams.minutes', $exam->id_ujian) }}" target="_blank" rel="noopener">Cetak Berita Acara</a>@endif @if ($user->role === 'dosen' && ! $exam->lecturer_has_score)<button class="button button-small" type="button" disabled aria-disabled="true">Cetak Nilai & Revisi</button>@elseif ($exam->has_scores)<a class="button button-small" href="{{ route('theses.exams.print', $exam->id_ujian) }}" target="_blank" rel="noopener">Cetak Nilai & Revisi</a>@endif</div></td></tr>
            @endforeach
        </tbody></table></div>
    @endif
@endsection