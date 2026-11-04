@extends('layouts.simak')

@section('title', 'Pengaturan Feeder | SIAKAD')
@section('topbar_title', 'Pengaturan Feeder')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Administrasi</p>
            <h1>Pengaturan Feeder</h1>
            <div class="module-alert" role="note"><p class="subtitle">Alamat WebService ditetapkan pada data perguruan tinggi. Perubahan kredensial hanya tersimpan pada konfigurasi SIAKAD.</p></div>
        </div>
    </div>

    @if ($errors->any())<div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>@endif
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif

    <section class="panel">
        <dl class="master-identity-grid">
            <div><dt>URL WebService Feeder</dt><dd>{{ $settings->alamat_feeder }}</dd></div>
            <div><dt>Status kredensial</dt><dd>{{ $passwordConfigured ? 'Tersimpan' : 'Belum diatur' }}</dd></div>
        </dl>
        <form method="POST" action="{{ route('feeder-settings.update') }}" style="margin-top:22px">
            @csrf
            @method('PUT')
            <div class="field-grid">
                <div>
                    <label for="feeder-username">Username Feeder</label>
                    <input id="feeder-username" name="user_feeder" value="{{ old('user_feeder', $settings->user_feeder) }}" maxlength="100" required autocomplete="off">
                </div>
                <div>
                    <label for="feeder-password">Password Feeder</label>
                    <input id="feeder-password" type="password" name="password_feeder" maxlength="100" autocomplete="new-password" placeholder="{{ $passwordConfigured ? 'Kosongkan untuk mempertahankan password saat ini' : 'Masukkan password Feeder' }}">
                </div>
            </div>
            <p class="subtitle">Password yang tersimpan tidak ditampilkan. Kosongkan kolom untuk mempertahankan kredensial saat ini.</p>
            <div class="form-actions">
                <button class="button button-primary" type="submit"><span data-lucide="save"></span> Simpan Kredensial</button>
            </div>
        </form>
        <form method="POST" action="{{ route('feeder-settings.test') }}" style="margin-top:12px" onsubmit="return window.confirm('Uji koneksi akan mengirim permintaan GetToken ke Feeder. Lanjutkan?')">
            @csrf
            <button class="button" type="submit"><span data-lucide="plug-zap"></span> Uji Koneksi Feeder</button>
        </form>
    </section>
@endsection