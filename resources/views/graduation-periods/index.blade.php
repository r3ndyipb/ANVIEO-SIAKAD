@extends('layouts.simak')

@section('title', 'Periode Wisuda | SIAKAD')
@section('topbar_title', 'Periode Wisuda')

@push('styles')
    <style>
        .graduation-actions { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin:16px 0; }
        .graduation-table-wrap { overflow-x:auto; border:1px solid #e2e9e0; border-radius:7px; background:#fff; }
        .graduation-table { width:100%; min-width:760px; border-collapse:collapse; font-size:11px; }
        .graduation-table th, .graduation-table td { padding:10px 11px; border-bottom:1px solid #edf1eb; text-align:left; vertical-align:middle; }
        .graduation-table th { background:#f7f9f5; color:#63725e; font-size:9px; text-transform:uppercase; white-space:nowrap; }
        .graduation-dialog { position:fixed; inset:50% auto auto 50%; width:min(540px, calc(100vw - 28px)); max-height:calc(100vh - 32px); margin:0; padding:0; overflow:auto; transform:translate(-50%, -50%); border:1px solid #dce5d9; border-radius:9px; box-shadow:0 24px 80px #17251a40; }
        .graduation-dialog::backdrop { background:#17251a80; }
        .graduation-dialog-head, .graduation-dialog-actions { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:14px 18px; border-bottom:1px solid #e8ede6; }
        .graduation-dialog-head h2 { margin:0; color:#284a33; font-size:17px; }
        .graduation-dialog-body { display:grid; gap:13px; padding:18px; }
        .graduation-dialog-body label { display:grid; gap:6px; color:#52634f; font-size:11px; font-weight:700; }
        .graduation-dialog-body input { min-height:38px; padding:8px 10px; border:1px solid #d9e2d7; border-radius:5px; background:#fff; color:#354a38; }
        .graduation-dialog-actions { justify-content:flex-end; border-top:1px solid #e8ede6; border-bottom:0; }
        @media (max-width:640px) { .graduation-table th, .graduation-table td { padding:8px; } }
    </style>
@endpush

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