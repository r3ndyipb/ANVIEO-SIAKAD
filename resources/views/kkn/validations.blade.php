@extends('layouts.simak')

@section('title', 'Verifikasi KKN | SIAKAD')
@section('topbar_title', 'Verifikasi KKN')

@section('content')
    <div class="page-heading"><div><p class="eyebrow">Kuliah Kerja Nyata</p><h1>Verifikasi Peserta KKN</h1><p class="subtitle">Tinjau pengusulan mahasiswa dan hasil pemeriksaan administrasi.</p></div></div>
    @if ($errors->any())<div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>@endif
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    <form class="kkn-filter" method="GET" action="{{ route('kkn.validations.index') }}"><label for="kkn-validation-year">Periode KKN</label><select id="kkn-validation-year" name="tahun" data-searchable-select required><option value="">Pilih periode</option>@foreach ($periods as $item)<option value="{{ $item->tahun }}" @selected($period && (string) $period->tahun === (string) $item->tahun)>{{ $item->nama_periode }} ({{ $item->tahun }})</option>@endforeach</select><button class="button button-primary" type="submit">Tampilkan</button></form>
    @if ($rows)
        <div class="table-wrap"><table data-simple-search-table><thead><tr><th>No.</th><th>NPM</th><th>Nama mahasiswa</th><th>Program studi</th><th>Pengusul</th><th>Status</th><th>Keterangan</th><th>Aksi</th></tr></thead><tbody>
            @forelse ($rows as $row)
                @php($status = [0 => 'Menunggu verifikasi', 1 => 'Tidak lolos', 2 => 'Lolos'][$row->status] ?? 'Tidak diketahui')
                <tr><td>{{ $rows->firstItem() + $loop->index }}</td><td>{{ $row->nim ?: '-' }}</td><td>{{ $row->nama_mahasiswa ?: '-' }}</td><td>{{ $row->nama_program_studi ?: '-' }}</td><td>{{ $row->petugas ?: '-' }}</td><td><span class="kkn-status {{ (int) $row->status === 2 ? 'is-active' : '' }}">{{ $status }}</span></td><td>{{ $row->keterangan ?: '-' }}</td><td><a class="table-icon-button" href="{{ route('kkn.validations.show', $row->id_peserta) }}" title="Periksa data mahasiswa" aria-label="Periksa {{ $row->nama_mahasiswa }}"><span data-lucide="clipboard-check"></span></a></td></tr>
            @empty<tr><td colspan="8" class="kkn-empty">Belum ada calon peserta pada periode ini.</td></tr>@endforelse
        </tbody></table></div><div class="kkn-pagination">{{ $rows->links('vendor.pagination.datatables') }}</div>
    @else
        <div class="kkn-empty-state"><strong>Periode belum tersedia</strong>Pilih periode yang tercatat untuk menampilkan calon peserta.</div>
    @endif
@endsection

@push('styles')
<style>
    .kkn-filter { display: grid; grid-template-columns: max-content minmax(220px, 1fr) max-content; align-items: center; gap: 10px; margin: 16px 0; padding: 14px; border: 1px solid #dce5e0; border-radius: 6px; background: #fff; }
    .kkn-filter label { color: #52645b; font-size: 11px; font-weight: 700; }
    .kkn-filter select { min-width: min(100%, 320px); min-height: 38px; padding: 7px 9px; border: 1px solid #cbd8d1; border-radius: 5px; background: #fff; }
    .kkn-status { display: inline-flex; padding: 4px 8px; border-radius: 4px; background: #fff4d7; color: #725400; font-size: 11px; font-weight: 700; white-space: nowrap; }
    .kkn-status.is-active { background: #e5f2e8; color: #27613a; }
    .kkn-empty, .kkn-empty-state { padding: 24px; color: #69786f; text-align: center; }
    .kkn-empty-state { display: grid; gap: 5px; margin-top: 16px; border: 1px dashed #cbd8d1; border-radius: 6px; }
    .kkn-pagination { margin-top: 14px; }
    @media (max-width: 640px) { .kkn-filter { grid-template-columns: 1fr; } .kkn-filter button { width: 100%; } }
</style>
@endpush