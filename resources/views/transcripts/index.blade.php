@extends('layouts.simak')

@section('title', 'Transkrip Nilai | SIAKAD')
@section('topbar_title', 'Transkrip Nilai')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1>{{ $user->role === 'dosen' ? 'Transkrip dan KHS Mahasiswa' : 'Transkrip Nilai' }}</h1>
            <div class="module-alert" role="note"><p class="subtitle">Nilai terbaik setiap mata kuliah, total SKS, dan IPK kumulatif.</p></div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($student)
        <section class="panel">
            @unless (in_array($user->role, ['mahasiswa'], true))
                <div class="transcript-toolbar">
                    <a class="button button-primary" href="{{ route('transcripts.index', ['search' => $filters['search'] ?? '', 'kkn_year' => $filters['kkn_year'] ?? null]) }}"><span data-lucide="arrow-left"></span> Kembali ke pencarian</a>
                </div>
            @endunless
            <div class="page-heading">
                <div>
                    <h2>{{ $student->nama_mahasiswa }}</h2>
                    <p class="subtitle">{{ $student->nim }} · {{ $student->nama_program_studi ?: '-' }} · {{ $student->nama_fakultas ?: '-' }}</p>
                </div>
            </div>
            <div class="transcript-toolbar">
                <a class="button button-primary" href="{{ route('transcripts.print', ['student' => $student->id_mahasiswa, 'kkn_year' => $filters['kkn_year'] ?? null]) }}" target="_blank" rel="noopener"><span data-lucide="printer"></span> Cetak Transkrip</a>
                <a class="button button-primary" href="{{ route('transcripts.curriculum', ['student' => $student->id_mahasiswa, 'kkn_year' => $filters['kkn_year'] ?? null]) }}" target="_blank" rel="noopener"><span data-lucide="list-checks"></span> Cek Mata Kuliah Diprogram</a>
            </div>
            @if ($report['courses']->isNotEmpty())
                <div class="table-wrap">
                    <table data-simple-search-table>
                        <thead><tr><th>No</th><th>Kode MK</th><th>Nama Mata Kuliah</th><th>Semester</th><th>Nilai</th><th>Bobot</th><th>SKS</th><th>Mutu</th></tr></thead>
                        <tbody>
                            @foreach ($report['courses'] as $course)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $course->kode_mata_kuliah ?: '-' }}</td>
                                    <td>{{ $course->nama_mata_kuliah ?: 'Mata kuliah tidak ditemukan' }}</td>
                                    <td>{{ $course->semester ?: '-' }}</td>
                                    <td>{{ $course->nilai_huruf }}</td>
                                    <td>{{ number_format($course->nilai_bobot, 2) }}</td>
                                    <td>{{ number_format($course->sks_mata_kuliah, 0) }}</td>
                                    <td>{{ number_format($course->mutu, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr><th colspan="6" style="text-align:right">Jumlah</th><th>{{ number_format($report['total_sks'], 0) }} SKS</th><th>{{ number_format($report['total_quality'], 2) }}</th></tr>
                            <tr><th colspan="6" style="text-align:right">IPK Mahasiswa</th><th colspan="2">{{ number_format($report['ipk'], 2) }}</th></tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="master-empty-state"><strong>Transkrip belum tersedia</strong>Mahasiswa belum memiliki mata kuliah yang diprogramkan.</div>
            @endif
        </section>
    @else
        @if ($user->role !== 'dosen')
            <form class="transcript-search" method="GET" action="{{ route('transcripts.index') }}">
                @if ($user->role === 'validasi')<input type="hidden" name="kkn_year" value="{{ $filters['kkn_year'] }}">@endif
                <div>
                    <label for="transcript-search">NPM atau nama mahasiswa</label>
                    <input id="transcript-search" name="search" type="search" value="{{ old('search', $filters['search'] ?? '') }}" placeholder="Masukkan NPM atau nama" required>
                </div>
                <button class="button button-primary" type="submit"><span data-lucide="search"></span> Cari</button>
            </form>
        @else
            <form class="transcript-search" method="GET" action="{{ route('transcripts.index') }}">
                <div>
                    <label for="transcript-search">Cari mahasiswa bimbingan PA</label>
                    <input id="transcript-search" name="search" type="search" value="{{ old('search', $filters['search'] ?? '') }}" placeholder="Masukkan NPM atau nama">
                </div>
                <button class="button button-primary" type="submit"><span data-lucide="search"></span> Cari</button>
            </form>
        @endif
        @if ($students->isNotEmpty())
            <div class="table-wrap">
                <table data-simple-search-table>
                    <thead><tr><th>Mahasiswa</th><th>Program Studi</th><th>Fakultas</th><th class="no-export">Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($students as $listedStudent)
                            <tr>
                                <td>{{ $listedStudent->nama_mahasiswa }}<small class="subtitle" style="display:block">{{ $listedStudent->nim }}</small></td>
                                <td>{{ $listedStudent->nama_program_studi ?: '-' }}</td>
                                <td>{{ $listedStudent->nama_fakultas ?: '-' }}</td>
                                <td>
                                    <a class="table-icon-button" href="{{ route('transcripts.index', ['search' => $filters['search'] ?? '', 'student' => $listedStudent->id_mahasiswa, 'kkn_year' => $filters['kkn_year'] ?? null]) }}" title="Lihat transkrip {{ $listedStudent->nama_mahasiswa }}" aria-label="Lihat transkrip {{ $listedStudent->nama_mahasiswa }}"><span data-lucide="eye"></span></a>
                                    @if ($user->role === 'dosen')
                                        <a class="table-icon-button" href="{{ route('khs.index', ['search' => $listedStudent->nim, 'student' => $listedStudent->id_mahasiswa]) }}" title="Lihat KHS {{ $listedStudent->nama_mahasiswa }}" aria-label="Lihat KHS {{ $listedStudent->nama_mahasiswa }}"><span data-lucide="file-spreadsheet"></span></a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="transcript-pagination">{{ $students->links('vendor.pagination.datatables') }}</div>
        @elseif (filled($filters['search'] ?? null))
            <div class="master-empty-state"><strong>Mahasiswa tidak ditemukan</strong>Tidak ada mahasiswa dalam hasil pencarian atau cakupan akun ini.</div>
        @elseif ($user->role === 'dosen')
            <div class="master-empty-state"><strong>Mahasiswa bimbingan tidak ditemukan</strong>Belum ada mahasiswa PA dalam cakupan akun dosen ini.</div>
        @endif
    @endif
@endsection
