@extends('layouts.simak')

@section('title', 'Periode Wisuda | SIAKAD')
@section('topbar_title', 'Periode Wisuda')



@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Wisuda</p>
            <h1>Periode Wisuda</h1>
        </div>
        @if ($canManage)
            <button class="button button-primary" type="button" data-dialog-open="graduation-period-create"><span data-lucide="plus"></span> Tambah periode</button>
        @endif
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="module-alert" role="note"><p>Kelola jadwal pendaftaran, kuota peserta, dan periode wisuda yang aktif.</p></div>
    <div class="graduation-table-wrap">
        <table class="graduation-table" data-graduation-period-table data-ajax-url="{{ route('graduation-periods.data') }}" data-can-manage="{{ $canManage ? 'true' : 'false' }}">
            <thead><tr><th>Nama periode</th><th>Tanggal pendaftaran</th><th>Kuota peserta</th><th>Status aktif</th><th class="no-export">Aksi</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>

    @if ($canManage)
    <dialog class="graduation-dialog" id="graduation-period-create" aria-labelledby="graduation-period-create-title" @if ($errors->any() && ! old('id_periode')) data-open-on-load="true" @endif>
        <header class="graduation-dialog-head"><h2 id="graduation-period-create-title">Tambah periode wisuda</h2><button class="table-icon-button" type="button" data-dialog-close aria-label="Tutup dialog"><span data-lucide="x"></span></button></header>
        <form method="POST" action="{{ route('graduation-periods.store') }}">
            @csrf
            <div class="graduation-dialog-body">
                <label>Nama periode<input type="text" name="nama_periode" value="{{ old('nama_periode') }}" maxlength="100" placeholder="Periode Wisuda Bulan ... Tahun ..." required></label>
                <label>Kuota peserta<input type="number" name="kuota_wisuda" value="{{ old('kuota_wisuda') }}" min="1" max="9999" required></label>
                <label>Tanggal mulai pendaftaran<input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai', today()->toDateString()) }}" required></label>
                <label>Tanggal akhir pendaftaran<input type="date" name="tanggal_akhir" value="{{ old('tanggal_akhir', today()->toDateString()) }}" required></label>
            </div>
            <footer class="graduation-dialog-actions"><button class="button button-secondary" type="button" data-dialog-close>Batal</button><button class="button button-primary" type="submit">Simpan periode</button></footer>
        </form>
    </dialog>

    <dialog class="graduation-dialog" id="graduation-period-edit" aria-labelledby="graduation-period-edit-title" @if ($errors->any() && old('id_periode')) data-open-on-load="true" @endif>
        <header class="graduation-dialog-head"><h2 id="graduation-period-edit-title">Ubah periode wisuda</h2><button class="table-icon-button" type="button" data-dialog-close aria-label="Tutup dialog"><span data-lucide="x"></span></button></header>
        <form method="POST" action="{{ old('id_periode') ? route('graduation-periods.update', old('id_periode')) : '' }}" data-graduation-edit-form>
            @csrf
            @method('PUT')
            <input type="hidden" name="id_periode" value="{{ old('id_periode') }}">
            <div class="graduation-dialog-body">
                <label>Nama periode<input type="text" name="nama_periode" value="{{ old('nama_periode') }}" maxlength="100" required></label>
                <label>Kuota peserta<input type="number" name="kuota_wisuda" value="{{ old('kuota_wisuda') }}" min="1" max="9999" required></label>
                <label>Tanggal mulai pendaftaran<input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai') }}" required></label>
                <label>Tanggal akhir pendaftaran<input type="date" name="tanggal_akhir" value="{{ old('tanggal_akhir') }}" required></label>
            </div>
            <footer class="graduation-dialog-actions"><button class="button button-secondary" type="button" data-dialog-close>Batal</button><button class="button button-primary" type="submit">Simpan perubahan</button></footer>
        </form>
    </dialog>
    @endif
@endsection