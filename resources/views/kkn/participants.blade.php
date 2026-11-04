@extends('layouts.simak')

@section('title', 'Peserta dan Ploting KKN | SIAKAD')
@section('topbar_title', 'Peserta dan Ploting KKN')

@section('content')
    <div class="page-heading"><div><p class="eyebrow">Kuliah Kerja Nyata</p><h1>Peserta dan Ploting KKN</h1><p class="subtitle">Tempatkan peserta yang lolos verifikasi ke lokasi yang tersedia.</p></div></div>
    @if ($errors->any())<div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>@endif
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    <form class="kkn-filter" method="GET" action="{{ route('kkn.participants.index') }}"><label for="kkn-participant-year">Periode KKN</label><select id="kkn-participant-year" name="tahun" data-searchable-select required><option value="">Pilih periode</option>@foreach ($periods as $item)<option value="{{ $item->tahun }}" @selected($period && (string) $period->tahun === (string) $item->tahun)>{{ $item->nama_periode }} ({{ $item->tahun }})</option>@endforeach</select><button class="button button-primary" type="submit">Tampilkan</button></form>
    @if ($period)
        <div class="kkn-actions">
            @if ($user->role === 'lppm')<form method="POST" action="{{ route('kkn.participants.random-plot') }}" data-confirm-action>@csrf<input type="hidden" name="tahun" value="{{ $period->tahun }}"><button class="button button-primary" type="submit"><span data-lucide="shuffle"></span> Ploting lokasi acak</button></form>@endif
            <a class="button" href="{{ route('kkn.participants.pdf', ['tahun' => $period->tahun]) }}"><span data-lucide="printer"></span> Unduh PDF</a>
            <a class="button" href="{{ route('kkn.participants.excel', ['tahun' => $period->tahun]) }}"><span data-lucide="sheet"></span> Unduh Excel</a>
        </div>
        <div class="module-alert" role="note"><p>Ploting acak memproses peserta lolos yang belum memiliki lokasi. Sistem memeriksa kapasitas tersisa sebelum menyimpan dan menyelang-seling peserta laki-laki/perempuan.</p></div>
        @if ($rows && $rows->isNotEmpty())
            <div class="table-wrap"><table data-simple-search-table><thead><tr><th>No.</th><th>NPM</th><th>Nama mahasiswa</th><th>Jenis kelamin</th><th>Program studi</th><th>Lokasi penempatan</th></tr></thead><tbody>
                @foreach ($rows as $row)
                    <tr><td>{{ $rows->firstItem() + $loop->index }}</td><td>{{ $row->nim ?: '-' }}</td><td>{{ $row->nama_mahasiswa ?: '-' }}</td><td>{{ $row->jenis_kelamin === 'P' ? 'Perempuan' : ($row->jenis_kelamin === 'L' ? 'Laki-laki' : '-') }}</td><td>{{ $row->nama_program_studi ?: '-' }}</td><td>@if ($user->role === 'lppm')<form class="kkn-inline-form" method="POST" action="{{ route('kkn.participants.location', $row->id_peserta) }}" data-kkn-location-form>@csrf @method('PUT')<input type="hidden" name="tahun" value="{{ $period->tahun }}"><select name="id_lokasi" data-searchable-select data-placeholder="Pilih lokasi" required><option value="">Pilih lokasi</option>@foreach ($locations as $location)<option value="{{ $location->id_lokasi }}" @selected((string) $row->id_lokasi === (string) $location->id_lokasi)>{{ $location->nama_lokasi }}</option>@endforeach</select></form>@else{{ $row->nama_lokasi ?: '-' }}@endif</td></tr>
                @endforeach
            </tbody></table></div><div class="kkn-pagination">{{ $rows->links('vendor.pagination.datatables') }}</div>
        @elseif ($rows)
            <div class="kkn-empty-state"><strong>Belum ada peserta lolos</strong>Peserta akan muncul setelah hasil verifikasi disimpan sebagai lolos.</div>
        @endif
    @else
        <div class="kkn-empty-state"><strong>Periode belum tersedia</strong>Belum ada periode KKN untuk ditampilkan.</div>
    @endif
@endsection

@push('styles')
<style>
    .kkn-filter { display: grid; grid-template-columns: max-content minmax(220px, 1fr) max-content; align-items: center; gap: 10px; margin: 16px 0; padding: 14px; border: 1px solid #dce5e0; border-radius: 6px; background: #fff; }
    .kkn-filter label { color: #52645b; font-size: 11px; font-weight: 700; }
    .kkn-filter select { min-width: min(100%, 320px); min-height: 38px; padding: 7px 9px; border: 1px solid #cbd8d1; border-radius: 5px; background: #fff; }
    .kkn-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 14px 0; }
    .kkn-inline-form { display: flex; min-width: 190px; align-items: center; gap: 6px; }
    .kkn-inline-form select { min-width: 140px; }
    .kkn-empty-state { display: grid; gap: 5px; margin-top: 16px; padding: 24px; border: 1px dashed #cbd8d1; border-radius: 6px; color: #69786f; text-align: center; }
    .kkn-pagination { margin-top: 14px; }
    @media (max-width: 640px) { .kkn-filter { grid-template-columns: 1fr; } .kkn-filter button { width: 100%; } }
</style>
@endpush