@extends('layouts.simak')

@section('title', 'Validasi Nilai Mahasiswa | SIAKAD')
@section('topbar_title', 'Validasi Nilai Mahasiswa')

@section('content')
    <div class="page-heading"><div><p class="eyebrow">Akademik</p><h1>Validasi Nilai Mahasiswa</h1><p class="subtitle">Cari data KHS dan transkrip mahasiswa sesuai status akademik legacy.</p></div></div>
    <form class="grade-validation-filter" method="GET" action="{{ route('grade-validation.index') }}">
        <label for="grade-validation-search">NPM atau nama mahasiswa</label>
        <input id="grade-validation-search" type="search" name="search" value="{{ $search }}" placeholder="Masukkan NPM atau nama">
        <button class="button button-primary" type="submit"><span data-lucide="search"></span> Cari</button>
    </form>
    <p class="grade-validation-status-note">Status yang ditampilkan: Aktif, Cuti, Lulus, Nonaktif, Drop Out, dan Kampus Merdeka.</p>
    @if ($students->isNotEmpty())
        <div class="table-wrap">
            <table data-simple-search-table data-search-placeholder="Cari NPM, nama, atau program studi">
                <thead><tr><th>No.</th><th>NPM</th><th>Nama Mahasiswa</th><th>Program Studi</th><th>Fakultas</th><th>Status</th><th>Proses</th></tr></thead>
                <tbody>
                    @foreach ($students as $student)
                        <tr>
                            <td>{{ $students->firstItem() + $loop->index }}</td>
                            <td>{{ $student->nim ?: '-' }}</td>
                            <td>{{ $student->nama_mahasiswa }}</td>
                            <td>{{ $student->nama_program_studi ?: '-' }}</td>
                            <td>{{ $student->nama_fakultas ?: '-' }}</td>
                            <td>{{ $student->nama_status_mahasiswa ?: $student->id_status_mahasiswa }}</td>
                            <td class="grade-validation-actions">
                                <a class="button button-secondary" href="{{ route('grade-validation.khs', $student->id_mahasiswa) }}" target="_blank" rel="noopener"><span data-lucide="file-text"></span> KHS MHS</a>
                                <a class="button button-primary" href="{{ route('grade-validation.transcript', $student->id_mahasiswa) }}" target="_blank" rel="noopener"><span data-lucide="graduation-cap"></span> Transkrip MHS</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="grade-validation-pagination">{{ $students->links('vendor.pagination.datatables') }}</div>
    @elseif ($search !== '')
        <div class="master-empty-state"><strong>Mahasiswa tidak ditemukan</strong>Tidak ada mahasiswa bernama terisi dengan status yang cocok pada pencarian ini.</div>
    @else
        <div class="master-empty-state">Tidak ada mahasiswa dengan nama terisi untuk divalidasi.</div>
    @endif
@endsection

@push('styles')
<style>
    .grade-validation-filter { display: flex; flex-wrap: wrap; align-items: end; gap: 10px; margin: 16px 0 10px; padding: 14px; border: 1px solid #dce5e0; border-radius: 6px; background: #fff; }
    .grade-validation-filter label { flex: 0 0 100%; color: #52645b; font-size: 11px; font-weight: 700; }
    .grade-validation-filter input { min-width: min(100%, 320px); min-height: 38px; flex: 1 1 320px; padding: 7px 9px; border: 1px solid #cbd8d1; border-radius: 5px; }
    .grade-validation-status-note { color: #65756c; font-size: 11px; }
    .grade-validation-actions { display: flex; flex-wrap: wrap; gap: 6px; min-width: 210px; }
    .grade-validation-pagination { margin-top: 14px; }
    @media (max-width: 640px) { .grade-validation-filter { align-items: stretch; } .grade-validation-filter > * { width: 100%; } }
</style>
@endpush
