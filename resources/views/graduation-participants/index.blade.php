@extends('layouts.simak')

@section('title', 'Peserta Wisuda | SIAKAD')
@section('topbar_title', 'Peserta Wisuda')

@section('content')
    <div class="page-heading"><div><p class="eyebrow">Program &amp; Laporan</p><h1>Peserta Wisuda</h1><p class="subtitle">Pantau mahasiswa yang diusulkan untuk mengikuti wisuda.</p></div></div>
    @if ($errors->any())<div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>@endif
    @if (session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    <form class="kkn-filter" method="GET" action="{{ route('graduation-participants.index') }}">
        <div>
            <label for="graduation-participant-period">Periode Wisuda</label>
            <select id="graduation-participant-period" name="periode" data-searchable-select required>
                <option value="">Pilih periode</option>
                @foreach ($periods as $item)
                    <option value="{{ $item->id_periode }}" @selected($period && (string) $period->id_periode === (string) $item->id_periode)>{{ $item->nama_periode }}@if (filled($item->tahun)) ({{ $item->tahun }})@endif</option>
                @endforeach
            </select>
        </div>
        <button class="button button-primary" type="submit">Tampilkan</button>
    </form>
    @if ($period && $canPropose)
        <p class="kkn-actions">
            <button class="button button-primary" type="button" data-dialog-open="graduation-participant-create-dialog"><span data-lucide="plus"></span> Tambah usulan</button>
            <span class="kkn-muted">Pendaftaran dibuka {{ $period->tanggal_mulai }} sampai {{ $period->tanggal_akhir }}.</span>
        </p>
    @endif
    @if ($period && ! $canPropose)
        <div class="module-alert" role="status"><p>Masa pengusulan wisuda ditutup. Pengusulan hanya dapat dilakukan pada tanggal yang ditetapkan untuk periode aktif.</p></div>
    @endif
    @if ($rows)
        <div class="table-wrap"><table data-simple-search-table data-search-placeholder="Cari NPM, nama, prodi, atau status"><thead><tr><th>No.</th><th>NPM</th><th>Nama mahasiswa</th><th>Program studi</th><th>Tanggal usul</th><th>Petugas</th><th>Status</th><th>Keterangan</th></tr></thead><tbody>
            @foreach ($rows as $row)
                @php($status = [0 => 'Menunggu verifikasi', 1 => 'Tidak lolos', 2 => 'Lolos'][$row->status] ?? 'Tidak diketahui')
                <tr><td>{{ $rows->firstItem() + $loop->index }}</td><td>{{ $row->nim ?: '-' }}</td><td>{{ $row->nama_mahasiswa ?: '-' }}</td><td>{{ $row->nama_program_studi ?: '-' }}</td><td>{{ $row->tgl_daftar ?: '-' }}</td><td>{{ $row->petugas ?: '-' }}</td><td><span class="kkn-status {{ (int) $row->status === 2 ? 'is-active' : '' }}">{{ $status }}</span></td><td>{{ $row->komentar ?: '-' }}</td></tr>
            @endforeach
        </tbody></table></div><div class="kkn-pagination">{{ $rows->links('vendor.pagination.datatables') }}</div>
        @if ($rows->isEmpty())<p class="kkn-empty">Belum ada usulan pada periode ini.</p>@endif
    @elseif (! $period)
        <div class="kkn-empty-state"><strong>Periode belum tersedia</strong>Belum ada periode wisuda untuk ditampilkan.</div>
    @endif
    @if ($period && $canPropose)
        <dialog class="kkn-dialog kkn-proposal-dialog" id="graduation-participant-create-dialog" data-open-on-load="{{ $openCreate ? 'true' : 'false' }}" aria-labelledby="graduation-participant-create-title">
            <header class="kkn-dialog-head"><div><span class="kkn-muted">Periode {{ $period->nama_periode }}</span><h2 id="graduation-participant-create-title">Tambah usulan peserta wisuda</h2></div><button class="table-icon-button" type="button" data-dialog-close aria-label="Tutup dialog"><span data-lucide="x"></span></button></header>
            <div class="module-alert" role="note"><p>Mahasiswa yang sudah pernah diusulkan pada periode ini atau tidak memiliki nama/NPM tidak ditampilkan.</p></div>
            <form id="graduation-candidate-search-form" method="GET" action="{{ route('graduation-participants.create') }}" data-candidate-live-search hidden>
                <input type="hidden" name="periode" value="{{ $period->id_periode }}">
                <input type="hidden" name="search" value="{{ $search }}">
            </form>
            <form method="POST" action="{{ route('graduation-participants.store', ['periode' => $period->id_periode]) }}" data-kkn-proposal-form data-kkn-selection-key="wisuda:{{ $period->id_periode }}">@csrf
                <div class="kkn-form-actions"><button class="button button-primary" type="submit"><span data-lucide="send"></span> Simpan usulan terpilih</button></div>
                <div data-candidate-results>
                    <div class="table-wrap"><table data-kkn-candidate-table><thead><tr><th>Pilih</th><th>No.</th><th>NPM</th><th>Nama mahasiswa</th><th>Program studi</th><th>Periode masuk</th><th>Status</th></tr></thead><tbody>
                        @foreach ($students as $student)
                            <tr><td><input class="kkn-student-checkbox" type="checkbox" name="students[]" value="{{ $student->id_mahasiswa }}" @disabled($student->already_registered) aria-label="{{ $student->already_registered ? 'Sudah terdaftar pada periode ini: ' : 'Pilih ' }}{{ $student->nama_mahasiswa }}" title="{{ $student->already_registered ? 'Sudah terdaftar pada periode ini' : 'Pilih peserta' }}">@if ($student->already_registered)<span class="kkn-muted">Sudah terdaftar</span>@endif</td><td>{{ $students->firstItem() + $loop->index }}</td><td>{{ $student->nim }}</td><td>{{ $student->nama_mahasiswa }}</td><td>{{ $student->nama_program_studi ?: '-' }}</td><td>{{ $student->id_periode_masuk ?: '-' }}</td><td>{{ $student->nama_status_mahasiswa ?: $student->id_status_mahasiswa }}</td></tr>
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


