@extends('layouts.simak')

@section('title', 'Pengguna SIAKAD | SIAKAD')
@section('topbar_title', 'Pengguna SIAKAD')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Administrasi</p>
            <h1>Pengguna SIAKAD</h1>
            <div class="module-alert" role="note"><p class="subtitle">Kelola akun operator pada tabel SIAKAD. Perubahan akses langsung berlaku saat akun digunakan untuk login berikutnya.</p></div>
        </div>
        <button class="button button-primary" type="button" data-simak-user-create data-create-url="{{ $createUrl }}" data-user-title="{{ $types[$type] }}"><span data-lucide="user-round-pen"></span> Tambah Pengguna</button>
    </div>

    @if ($errors->any()) <div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div> @endif
    @if (session('status')) <div class="alert alert-success" role="status">{{ session('status') }}</div> @endif

    <nav class="simak-user-types" aria-label="Jenis pengguna">
        @foreach ($types as $key => $label)
            <a href="{{ route('simak-users.index', ['type' => $key]) }}" @if ($type === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <div class="table-wrap">
        <table class="simak-user-table" data-simak-users-table data-ajax-url="{{ $dataUrl }}" data-user-title="{{ $types[$type] }}">
            <thead>
                <tr><th>Username</th><th>Nama Lengkap</th><th>Email</th><th>Telepon</th><th>Cakupan</th><th>Status</th><th class="no-export">Aksi</th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <div class="calendar-modal-backdrop simak-user-modal" data-simak-user-modal hidden>
        <section class="calendar-modal master-data-modal" role="dialog" aria-modal="true" aria-labelledby="simak-user-modal-title" tabindex="-1" data-simak-user-dialog>
            <div class="calendar-modal-head">
                <div>
                    <h2 id="simak-user-modal-title" data-simak-user-modal-title>Pengguna SIAKAD</h2>
                    <p class="calendar-modal-copy">Data akun legacy tersimpan pada tabel administrasi SIAKAD.</p>
                </div>
                <button class="calendar-modal-close" type="button" data-simak-user-close aria-label="Tutup dialog">×</button>
            </div>
            <div class="simak-user-modal-error" data-simak-user-error role="alert" hidden></div>
            <div data-simak-user-content></div>
        </section>
    </div>
@endsection