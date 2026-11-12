@extends('layouts.simak')

@section('title', 'Lokasi KKN | SIAKAD')
@section('topbar_title', 'Lokasi KKN')

@section('content')
    <div class="page-heading"><div><p class="eyebrow">Kuliah Kerja Nyata</p><h1>Lokasi KKN</h1><p class="subtitle">Kelola lokasi penempatan, kapasitas, dan ketersediaan.</p></div></div>
    @if ($errors->any())<div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>@endif
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    <div class="kkn-list-actions"><button class="button button-primary" type="button" data-dialog-open="kkn-location-create-dialog"><span data-lucide="plus"></span> Tambah lokasi</button></div>
    <div class="table-wrap"><table data-simple-search-table><thead><tr><th>Lokasi</th><th>Kuota</th><th>Terisi</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        @forelse ($locations as $location)
            <tr><td><strong>{{ $location->nama_lokasi }}</strong></td><td>{{ number_format((int) $location->kuota) }}</td><td>{{ number_format($location->occupied_count) }}</td><td><span class="kkn-status {{ $location->aktif === 'Y' ? 'is-active' : '' }}">{{ $location->aktif === 'Y' ? 'Aktif' : 'Tidak aktif' }}</span></td><td><button class="table-icon-button" type="button" data-dialog-open="kkn-location-edit-dialog" data-kkn-location-edit data-location-id="{{ $location->id_lokasi }}" data-update-url="{{ route('kkn.locations.update', $location->id_lokasi) }}" data-name="{{ $location->nama_lokasi }}" data-quota="{{ $location->kuota }}" data-active="{{ $location->aktif }}" title="Ubah lokasi" aria-label="Ubah lokasi {{ $location->nama_lokasi }}"><span data-lucide="pencil"></span></button></td></tr>
        @empty
            <tr><td colspan="5" class="kkn-empty">Belum ada lokasi KKN.</td></tr>
        @endforelse
    </tbody></table></div>
    <div class="kkn-pagination">{{ $locations->links('vendor.pagination.datatables') }}</div>
    <dialog class="kkn-dialog" id="kkn-location-create-dialog" data-open-on-load="{{ $errors->any() && old('form') === 'location-create' ? 'true' : 'false' }}" aria-labelledby="kkn-location-create-title">
        <header class="kkn-dialog-head"><h2 id="kkn-location-create-title">Tambah lokasi KKN</h2><button class="table-icon-button" type="button" data-dialog-close aria-label="Tutup dialog"><span data-lucide="x"></span></button></header>
        <form class="kkn-form" method="POST" action="{{ route('kkn.locations.store') }}">@csrf<input type="hidden" name="form" value="location-create">
            <label><span>Nama lokasi</span><input name="nama_lokasi" maxlength="200" value="{{ old('nama_lokasi') }}" required></label>
            <label><span>Kuota peserta</span><input type="number" name="kuota" min="1" max="2147483647" value="{{ old('kuota') }}" required></label>
            <div class="kkn-dialog-actions"><button class="button button-primary" type="submit"><span data-lucide="save"></span> Simpan lokasi</button></div>
        </form>
    </dialog>
    <dialog class="kkn-dialog" id="kkn-location-edit-dialog" data-open-on-load="{{ $errors->any() && old('form') === 'location-edit' ? 'true' : 'false' }}" aria-labelledby="kkn-location-edit-title">
        <header class="kkn-dialog-head"><h2 id="kkn-location-edit-title">Ubah lokasi KKN</h2><button class="table-icon-button" type="button" data-dialog-close aria-label="Tutup dialog"><span data-lucide="x"></span></button></header>
        <form class="kkn-form" id="kkn-location-edit-form" method="POST" action="{{ route('kkn.locations.update', old('location_id', '__LOCATION__')) }}">@csrf @method('PUT')<input type="hidden" name="form" value="location-edit"><input type="hidden" name="location_id" value="{{ old('location_id') }}">
            <label><span>Nama lokasi</span><input name="nama_lokasi" maxlength="200" required></label>
            <label><span>Kuota peserta</span><input type="number" name="kuota" min="1" max="2147483647" required></label>
            <label><span>Status lokasi</span><select name="aktif" data-searchable-select required><option value="Y">Aktif</option><option value="N">Tidak aktif</option></select></label>
            <div class="kkn-dialog-actions"><button class="button button-primary" type="submit"><span data-lucide="save"></span> Simpan perubahan</button></div>
        </form>
    </dialog>
@endsection

