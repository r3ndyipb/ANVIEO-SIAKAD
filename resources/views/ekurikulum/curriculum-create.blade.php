@extends('layouts.simak')

@section('title', 'Buat Kurikulum OBE | SIAKAD')
@section('topbar_title', 'Buat Kurikulum OBE')

@section('content')
    <div class="page-heading">
        <div><p class="eyebrow">E-Kurikulum OBE</p><h1>Buat kurikulum</h1><div class="module-alert" role="note"><p class="subtitle">Visi dan misi aktif program studi akan disimpan sebagai snapshot kurikulum.</p></div></div>
    </div>
    @if ($errors->any()) <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div> @endif
    <div class="table-wrap">
        <form method="post" action="{{ route('ekurikulum.curricula.store') }}" class="row g-3">
            @csrf
            <div class="col-md-8"><label class="form-label" for="curriculum-name">Nama kurikulum</label><input class="form-control" id="curriculum-name" name="nama_kur" maxlength="100" placeholder="Contoh: Kurikulum OBE Informatika" value="{{ old('nama_kur') }}" required></div>
            <div class="col-md-4"><label class="form-label" for="curriculum-year">Tahun berlaku</label><input class="form-control" id="curriculum-year" type="number" name="tahun" min="2000" max="2099" value="{{ old('tahun', now()->year) }}" required></div>
            <div class="col-12"><label class="form-label">Program studi</label><input class="form-control" value="{{ $program->nama_program_studi }}" readonly></div>
            <div class="col-12 d-flex gap-2"><a class="button button-secondary" href="{{ route('ekurikulum.curricula.index') }}">Kembali</a><button class="button button-primary" type="submit"><span data-lucide="save" aria-hidden="true"></span> Simpan draf</button></div>
        </form>
    </div>
@endsection
