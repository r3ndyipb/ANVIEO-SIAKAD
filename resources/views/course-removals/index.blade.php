@extends('layouts.simak')

@section('title', 'Penghapusan Mata Kuliah | SIAKAD')
@section('topbar_title', 'Penghapusan Mata Kuliah')

@push('styles')
    <style>
        .course-removal-search { display: grid; grid-template-columns: minmax(240px, 1fr) auto; align-items: end; gap: 12px; margin: 16px 0 20px; padding: 15px; border: 1px solid #e3e9e2; border-radius: 7px; background: #fbfdfb; }
        .course-removal-search label { display: block; margin-bottom: 7px; color: #43543c; font-size: 11px; font-weight: 800; }
        .course-removal-pagination { margin-top: 14px; }
        .course-removal-back { margin-bottom: 26px; }
        .course-removal-student { margin-top: 0; padding-top: 20px; border-top: 1px solid #e3e9e2; }
        .course-removal-action { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border: 1px solid #e1c2bd; border-radius: 5px; background: #fff8f6; color: #a74637; cursor: pointer; }
        .course-removal-action svg { width: 14px; height: 14px; }
        [data-course-removal-modal][hidden] { display: none; }
        @media (max-width: 640px) { .course-removal-search { grid-template-columns: 1fr; } }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1>Penghapusan Mata Kuliah</h1>
            <div class="module-alert" role="note"><p class="subtitle">Hapus mata kuliah mahasiswa dari KRS. Semua pengambilan mata kuliah yang sama akan dihapus.</p></div>
        </div>
    </div>

    @if ($errors->any())
        <div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($student)
        <section class="panel">
            <div class="transcript-toolbar course-removal-back">
                <a class="button button-primary" href="{{ route('course-removals.index', ['search' => $filters['search']]) }}"><span data-lucide="arrow-left"></span> Kembali ke pencarian</a>
            </div>
            <div class="page-heading course-removal-student">
                <div>
                    <h2>{{ $student->nama_mahasiswa }}</h2>
                    <p class="subtitle">{{ $student->nim }} · {{ $student->nama_program_studi ?: '-' }} · {{ $student->nama_fakultas ?: '-' }}</p>
                </div>
            </div>
            @if ($courses->isNotEmpty())
                <div class="table-wrap">
                    <table data-simple-search-table>
                        <thead><tr><th>No</th><th>Kode MK</th><th>Nama Mata Kuliah</th><th>Semester</th><th>Nilai</th><th>Bobot</th><th>SKS</th><th>Mutu</th><th class="no-export">Proses</th></tr></thead>
                        <tbody>
                            @foreach ($courses as $course)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $course->kode_mata_kuliah ?: '-' }}</td>
                                    <td>{{ $course->nama_mata_kuliah ?: 'Mata kuliah tidak ditemukan' }}</td>
                                    <td>{{ $course->semester ?: '-' }}</td>
                                    <td>{{ $course->nilai_huruf ?: '-' }}</td>
                                    <td>{{ number_format($course->nilai_bobot, 2) }}</td>
                                    <td>{{ number_format($course->sks_mata_kuliah, 2) }}</td>
                                    <td>{{ number_format($course->mutu, 2) }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('course-removals.destroy', ['student' => $student->id_mahasiswa, 'course' => $course->id_matkul, 'search' => $filters['search']]) }}" data-course-removal-confirm data-confirm-title="Konfirmasi Hapus Mata Kuliah" data-confirm-message="Hapus seluruh pengambilan {{ $course->nama_mata_kuliah }} dari KRS {{ $student->nama_mahasiswa }}? Tindakan ini tidak dapat dibatalkan.">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="search" value="{{ $filters['search'] }}">
                                            <button class="course-removal-action" type="submit" aria-label="Hapus {{ $course->nama_mata_kuliah }}" title="Hapus mata kuliah"><span data-lucide="trash-2"></span></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr><th colspan="6" style="text-align:right">Jumlah</th><th>{{ number_format($totalSks, 0) }} SKS</th><th>{{ number_format($totalQuality, 2) }}</th><th></th></tr>
                            <tr><th colspan="6" style="text-align:right">IPK Mahasiswa</th><th colspan="3">{{ number_format($ipk, 2) }}</th></tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="master-empty-state"><strong>Tidak ada mata kuliah dalam KRS</strong>Mahasiswa belum memprogramkan mata kuliah.</div>
            @endif
            <div class="calendar-confirm-detail" role="status">
                Transkrip saat ini: {{ number_format($totalSks, 0) }} SKS, mutu {{ number_format($totalQuality, 2) }}, IPK {{ number_format($ipk, 2) }}. Nilai terbaik tiap mata kuliah digunakan; penghapusan menghilangkan semua percobaan mata kuliah terpilih.
            </div>
        </section>
    @else
        <form class="course-removal-search" method="GET" action="{{ route('course-removals.index') }}">
            <div>
                <label for="course-removal-search">NPM atau nama mahasiswa</label>
                <input id="course-removal-search" name="search" type="search" value="{{ old('search', $filters['search'] ?? '') }}" placeholder="Masukkan NPM atau nama" required>
            </div>
            <button class="button button-primary" type="submit"><span data-lucide="search"></span> Cari</button>
        </form>
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
                                <td><a class="table-icon-button" href="{{ route('course-removals.index', ['search' => $filters['search'], 'student' => $listedStudent->id_mahasiswa]) }}" title="Kelola KRS {{ $listedStudent->nama_mahasiswa }}" aria-label="Kelola KRS {{ $listedStudent->nama_mahasiswa }}"><span data-lucide="eye"></span></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="course-removal-pagination">{{ $students->links('vendor.pagination.datatables') }}</div>
        @elseif (filled($filters['search'] ?? null))
            <div class="master-empty-state"><strong>Mahasiswa tidak ditemukan</strong>Tidak ada mahasiswa dalam hasil pencarian atau cakupan akun ini.</div>
        @endif
    @endif
    @if ($student && $courses->isNotEmpty())
        <div class="calendar-modal-backdrop" data-course-removal-modal hidden>
            <section class="calendar-modal" role="dialog" aria-modal="true" aria-labelledby="course-removal-confirm-title" aria-describedby="course-removal-confirm-message" tabindex="-1">
                <div class="calendar-modal-head">
                    <div>
                        <h2 id="course-removal-confirm-title" data-course-removal-confirm-title>Konfirmasi Penghapusan</h2>
                        <p class="calendar-modal-copy" id="course-removal-confirm-message" data-course-removal-confirm-message></p>
                    </div>
                    <button class="calendar-modal-close" type="button" data-course-removal-confirm-close aria-label="Tutup dialog">×</button>
                </div>
                <div class="calendar-modal-actions">
                    <button class="button" type="button" data-course-removal-confirm-cancel>Batal</button>
                    <button class="button button-danger" type="button" data-course-removal-confirm-submit>Ya, hapus</button>
                </div>
            </section>
        </div>
    @endif
@endsection
