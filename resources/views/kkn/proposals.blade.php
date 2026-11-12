@extends('layouts.simak')

@section('title', 'Usulan Peserta KKN | SIAKAD')
@section('topbar_title', 'Usulan Peserta KKN')

@section('content')
    <div class="page-heading"><div><p class="eyebrow">Kuliah Kerja Nyata</p><h1>Usulan Peserta KKN</h1><p class="subtitle">Pantau mahasiswa yang diusulkan untuk mengikuti KKN.</p></div></div>
    @if ($errors->any())<div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>@endif
    @if (session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    <form class="kkn-filter" method="GET" action="{{ route('kkn.proposals.index') }}"><label for="kkn-proposal-year">Periode KKN</label><select id="kkn-proposal-year" name="tahun" data-searchable-select required><option value="">Pilih periode</option>@foreach ($periods as $item)<option value="{{ $item->tahun }}" @selected($period && (string) $period->tahun === (string) $item->tahun)>{{ $item->nama_periode }} ({{ $item->tahun }})</option>@endforeach</select><button class="button button-primary" type="submit">Tampilkan</button></form>
    @if ($period && $canPropose)<p class="kkn-actions"><button class="button button-primary" type="button" data-dialog-open="kkn-proposal-create-dialog"><span data-lucide="plus"></span> Tambah usulan</button><span class="kkn-muted">Pendaftaran dibuka {{ $period->tanggal_mulai }} sampai {{ $period->tanggal_akhir }}.</span></p>@endif
    @if ($period && ! $canPropose)<div class="module-alert" role="status"><p>Masa pengusulan ditutup. Pengusulan hanya dapat dilakukan pada tanggal yang ditetapkan untuk periode aktif.</p></div>@endif
    @if ($rows)
        <div class="table-wrap"><table @if ($rows->isNotEmpty()) data-simple-search-table @endif><thead><tr><th>No.</th><th>NPM</th><th>Nama mahasiswa</th><th>Program studi</th><th>Tanggal usul</th><th>Pengusul</th><th>Status verifikasi</th><th>Keterangan</th></tr></thead><tbody>
            @forelse ($rows as $row)
                @php($status = [0 => 'Menunggu verifikasi', 1 => 'Tidak lolos', 2 => 'Lolos'][$row->status] ?? 'Tidak diketahui')
                <tr><td>{{ $rows->firstItem() + $loop->index }}</td><td>{{ $row->nim ?: '-' }}</td><td>{{ $row->nama_mahasiswa ?: '-' }}</td><td>{{ $row->nama_program_studi ?: '-' }}</td><td>{{ $row->tgl_daftar }}</td><td>{{ $row->petugas_usul ?: '-' }}</td><td><span class="kkn-status {{ (int) $row->status === 2 ? 'is-active' : '' }}">{{ $status }}</span></td><td>{{ $row->keterangan ?: '-' }}</td></tr>
            @empty<tr><td colspan="8" class="kkn-empty">Belum ada usulan pada periode ini.</td></tr>@endforelse
        </tbody></table></div><div class="kkn-pagination">{{ $rows->links('vendor.pagination.datatables') }}</div>
    @elseif (! $period)
        <div class="kkn-empty-state"><strong>Periode belum tersedia</strong>Belum ada periode KKN untuk ditampilkan.</div>
    @endif
    @if ($period && $canPropose)
        <dialog class="kkn-dialog kkn-proposal-dialog" id="kkn-proposal-create-dialog" data-open-on-load="{{ $openCreate ? 'true' : 'false' }}" aria-labelledby="kkn-proposal-create-title">
            <header class="kkn-dialog-head"><div><span class="kkn-muted">Periode {{ $period->nama_periode }}</span><h2 id="kkn-proposal-create-title">Tambah usulan peserta</h2></div><button class="table-icon-button" type="button" data-dialog-close aria-label="Tutup dialog"><span data-lucide="x"></span></button></header>
            <div class="module-alert" role="note"><p>Mahasiswa yang sudah pernah diusulkan pada periode ini atau tidak memiliki nama/NPM tidak ditampilkan.</p></div>
            <form id="kkn-candidate-search-form" method="GET" action="{{ route('kkn.proposals.create') }}" data-candidate-live-search hidden>
                <input type="hidden" name="tahun" value="{{ $period->tahun }}">
                <input type="hidden" name="search" value="{{ $search }}">
            </form>
            <form method="POST" action="{{ route('kkn.proposals.store') }}" data-kkn-proposal-form data-kkn-year="{{ $period->tahun }}">@csrf
                <div class="kkn-form-actions"><button class="button button-primary" type="submit"><span data-lucide="send"></span> Simpan usulan terpilih</button></div>
                <div data-candidate-results>
                    <div class="table-wrap"><table data-kkn-candidate-table><thead><tr><th>Pilih</th><th>No.</th><th>NPM</th><th>Nama mahasiswa</th><th>Program studi</th><th>Periode masuk</th><th>Status</th></tr></thead><tbody>
                        @foreach ($students as $student)
                            <tr><td><input class="kkn-student-checkbox" type="checkbox" name="students[]" value="{{ $student->id_mahasiswa }}" aria-label="Pilih {{ $student->nama_mahasiswa }}"></td><td>{{ $students->firstItem() + $loop->index }}</td><td>{{ $student->nim }}</td><td>{{ $student->nama_mahasiswa }}</td><td>{{ $student->nama_program_studi ?: '-' }}</td><td>{{ $student->id_periode_masuk ?: '-' }}</td><td>{{ $student->nama_status_mahasiswa ?: $student->id_status_mahasiswa }}</td></tr>
                        @endforeach
                    </tbody></table></div>
                    @if ($students->hasPages())<div class="kkn-pagination">{{ $students->links('vendor.pagination.datatables') }}</div>@endif
                    @if ($students->isEmpty())<div class="kkn-empty-state"><strong>Tidak ada calon untuk diusulkan</strong>Seluruh mahasiswa yang sesuai sudah diusulkan atau belum memenuhi status daftar legacy.</div>@endif
                </div>
            </form>
            <footer class="kkn-dialog-actions"><button class="button button-secondary" type="button" data-dialog-close>Tutup</button></footer>
        </dialog>
    @endif
@endsection

