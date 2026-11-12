@extends('layouts.simak')

@section('title', 'Tambah Usulan Peserta KKN | SIAKAD')
@section('topbar_title', 'Tambah Usulan Peserta KKN')

@section('content')
    <div class="page-heading"><div><p class="eyebrow">Kuliah Kerja Nyata</p><h1>Tambah Usulan Peserta</h1><p class="subtitle">Pilih mahasiswa aktif yang akan diajukan untuk verifikasi.</p></div><a class="button" href="{{ route('kkn.proposals.index') }}">Kembali</a></div>
    @if ($errors->any())<div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>@endif
    @if ($isOpen && $period)
        <div class="module-alert" role="note"><p>Periode {{ $period->nama_periode }} dibuka {{ $period->tanggal_mulai }} sampai {{ $period->tanggal_akhir }}. Mahasiswa yang sudah pernah diusulkan pada periode ini tidak ditampilkan kembali.</p></div>
        @if ($students->isNotEmpty())
            <form method="GET" action="{{ route('kkn.proposals.create') }}" class="kkn-search-form">
                <label for="kkn-student-search">Cari nama atau NPM</label>
                <input id="kkn-student-search" type="search" name="search" value="{{ $search }}" placeholder="Ketik nama atau NPM">
                <button class="button" type="submit"><span data-lucide="search"></span> Cari</button>
            </form>
            <form method="POST" action="{{ route('kkn.proposals.store') }}" data-kkn-proposal-form data-kkn-year="{{ $period->tahun }}">@csrf
                <div class="kkn-form-actions"><button class="button button-primary" type="submit"><span data-lucide="send"></span> Simpan usulan terpilih</button></div>
                <div class="table-wrap"><table data-kkn-candidate-table><thead><tr><th>Pilih</th><th>No.</th><th>NPM</th><th>Nama mahasiswa</th><th>Program studi</th><th>Periode masuk</th><th>Status</th></tr></thead><tbody>
                    @foreach ($students as $student)
                        <tr><td><input class="kkn-student-checkbox" type="checkbox" name="students[]" value="{{ $student->id_mahasiswa }}" aria-label="Pilih {{ $student->nama_mahasiswa }}"></td><td>{{ $students->firstItem() + $loop->index }}</td><td>{{ $student->nim }}</td><td>{{ $student->nama_mahasiswa }}</td><td>{{ $student->nama_program_studi ?: '-' }}</td><td>{{ $student->id_periode_masuk ?: '-' }}</td><td>{{ $student->nama_status_mahasiswa ?: $student->id_status_mahasiswa }}</td></tr>
                    @endforeach
                </tbody></table></div>
                <div class="kkn-pagination">{{ $students->links('vendor.pagination.datatables') }}</div>
            </form>
        @else
            <div class="kkn-empty-state"><strong>Tidak ada calon untuk diusulkan</strong>Seluruh mahasiswa yang sesuai sudah diusulkan atau belum memenuhi status daftar legacy.</div>
        @endif
    @else
        <div class="kkn-empty-state"><strong>Masa pengusulan ditutup</strong>Pengusulan hanya dapat dilakukan pada tanggal yang ditetapkan untuk periode aktif.</div>
    @endif
@endsection


