@extends('layouts.simak')

@section('title', 'Edit Profil | SIAKAD')
@section('topbar_title', 'Edit Profil')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Akun Saya</p>
            <h1>Edit Profil</h1>
            <div class="module-alert" role="note"><p class="subtitle">Perbarui data akun Anda. Kosongkan kata sandi jika tidak ingin mengubahnya.</p></div>
        </div>
    </div>

    <form class="profile-edit-form" method="POST" action="{{ route('user-profile.update') }}">
        @csrf
        @method('PUT')
        <div class="field-grid master-data-field-grid">
            <div>
                <label>Username</label>
                <input type="text" value="{{ $profile->username ?? $profile->nidn ?? $profile->kdasisten ?? $profile->nim }}" disabled>
            </div>
            @foreach ($fields as $name => $rules)
                @php
                    $labels = [
                        'nama_lengkap' => 'Nama lengkap', 'nama_dosen' => 'Nama lengkap', 'nama' => 'Nama lengkap',
                        'email' => 'Email', 'no_telp' => 'Nomor telepon', 'nip' => 'NIP', 'notlp' => 'Nomor telepon',
                        'nik' => 'NIK', 'id_negara' => 'Kode kewarganegaraan', 'jalan' => 'Jalan', 'rt' => 'RT',
                        'rw' => 'RW', 'dusun' => 'Dusun', 'kelurahan' => 'Kelurahan', 'kode_pos' => 'Kode pos',
                        'id_wilayah' => 'Kode kecamatan / wilayah', 'id_jenis_tinggal' => 'Kode jenis tinggal',
                        'id_alat_transportasi' => 'Kode alat transportasi', 'handphone' => 'Handphone', 'telepon' => 'Telepon',
                    ];
                @endphp
                <div>
                    <label for="profile-{{ $name }}">{{ $labels[$name] ?? $name }}</label>
                    <input
                        id="profile-{{ $name }}"
                        name="{{ $name }}"
                        type="{{ $name === 'email' ? 'email' : 'text' }}"
                        value="{{ old($name, $profile->{$name} ?? '') }}"
                        @required(in_array('required', $rules, true))
                    >
                    @error($name)<p class="field-error">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <div>
                <label for="profile-password">Kata sandi baru</label>
                <input id="profile-password" name="password" type="password" minlength="8" maxlength="100" autocomplete="new-password">
                @error('password')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>
        @error('profile')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        <div class="calendar-modal-actions">
            <button class="button button-primary" type="submit">Simpan perubahan</button>
        </div>
    </form>
@endsection
