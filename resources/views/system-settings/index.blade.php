@extends('layouts.simak')

@section('title', 'Pengaturan Sistem | '.$siteConfiguration['app_name'])
@section('topbar_title', 'Pengaturan Sistem')

@push('styles')
    <style>
        .system-settings-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr)); gap:18px; }
        .system-settings-grid > *, .system-settings-card { min-width:0; }
        .system-settings-card { padding:20px; border:1px solid #e1e8df; border-radius:9px; background:#fff; }
        .system-settings-card h2 { margin:0 0 7px; color:var(--brand-primary); font-size:17px; }
        .system-settings-card > p { margin:0 0 16px; color:#69766a; font-size:11px; line-height:1.6; }
        .system-settings-field { display:grid; gap:6px; margin:12px 0; }
        .system-settings-field label { color:#405c45; font-size:11px; font-weight:700; }
        .system-settings-field input, .system-settings-field select, .system-settings-field textarea { width:100%; min-height:38px; padding:8px 10px; border:1px solid #d5dfd3; border-radius:5px; background:#fff; color:#26392d; font:inherit; font-size:12px; }
        .system-settings-field input[type="color"] { width:78px; min-height:44px; padding:4px; cursor:pointer; }
        .system-settings-field textarea { min-height:76px; resize:vertical; }
        .system-settings-row { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
        .system-settings-check { display:flex; align-items:center; gap:8px; margin:12px 0; color:#405c45; font-size:11px; font-weight:700; }
        .system-settings-check input { width:16px; height:16px; }
        .system-settings-help { display:block; color:#778276; font-size:10px; line-height:1.5; }
        .system-settings-card code { display:block; max-width:100%; padding:8px 10px; overflow-wrap:anywhere; word-break:break-word; white-space:normal; background:#f7f9f5; border-radius:5px; font-size:11px; }
        .system-settings-preview { display:block; width:100%; max-height:130px; margin:8px 0; border:1px solid #e5e9e3; border-radius:6px; object-fit:contain; background:#f6f8f5; }
        .system-settings-slots { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; margin:0 0 20px; }
        .system-settings-slot { padding:12px; border:1px solid #e5e9e3; border-radius:7px; }
        .system-settings-slot h3 { margin:0; color:#405c45; font-size:12px; }
        .system-settings-actions { display:flex; flex-wrap:wrap; align-items:center; gap:10px; }
        .system-settings-actions .button { min-height:36px; }
        @media (max-width:560px) { .system-settings-row { grid-template-columns:1fr; } }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div><p class="eyebrow">Identitas & Integrasi</p><h1>Pengaturan Sistem</h1></div>
    </div>
    @include('partials.flash-notice')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Pengaturan belum tersimpan.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="system-settings-card" style="margin-bottom:18px">
        <h2>Identitas & Branding</h2>
        <p>Nama perguruan tinggi dibaca dari data utama. Motto, warna utama aplikasi, dan nama kementerian dapat disesuaikan. File gambar disimpan pada public storage; database hanya menyimpan path file.</p>
        <form method="POST" action="{{ route('system-settings.branding') }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            @if ($mottoSupported)
                <div class="system-settings-field">
                    <label for="institution-motto">Motto perguruan tinggi</label>
                    <input id="institution-motto" name="motto" value="{{ old('motto', $site->motto ?? $siteConfiguration['motto']) }}" maxlength="255" placeholder="Contoh: Berilmu, berakhlak, dan berdaya saing">
                </div>
            @else
                <p class="system-settings-help">Motto default: {{ $siteConfiguration['motto'] }}. Untuk menyimpan motto kustom, jalankan SQL upgrade motto terlebih dahulu.</p>
            @endif
            <div class="system-settings-field">
                <label for="ministry-name">Nama kementerian pada dokumen cetak</label>
                <input id="ministry-name" name="ministry_name" value="{{ old('ministry_name', $site->ministry_name ?? $siteConfiguration['ministry_name']) }}" maxlength="255" placeholder="Contoh: Kementerian Pendidikan Tinggi, Sains, dan Teknologi" required>
            </div>
            @if ($primaryColorSupported)
                <div class="system-settings-field">
                    <label for="primary-color">Warna utama aplikasi</label>
                    <input id="primary-color" type="color" name="primary_color" value="{{ old('primary_color', $siteConfiguration['primary_color']) }}" required>
                    <small class="system-settings-help">Pilih warna identitas kampus. Warna diterapkan pada tombol utama, navigasi, aksen, dan landing page.</small>
                </div>
            @else
                <p class="system-settings-help">Color picker belum tersedia pada database ini. Jalankan database/manual/simak_site_settings_primary_color.sql, lalu muat ulang halaman.</p>
            @endif
            <div class="system-settings-row">
                <div class="system-settings-field">
                    <label for="logo">Logo aplikasi</label>
                    <img class="system-settings-preview" src="{{ $siteConfiguration['logo_url'] }}" alt="Logo saat ini">
                    <input id="logo" type="file" name="logo" accept=".jpg,.jpeg,.png,.webp">
                    <small class="system-settings-help">JPG, PNG, atau WebP; maksimal 5 MB. Jika belum diunggah, logo akademik default akan digunakan.</small>
                </div>
                <div class="system-settings-field">
                    <label for="preloader-logo">Logo preloader</label>
                    <img class="system-settings-preview" src="{{ $siteConfiguration['preloader_logo_url'] }}" alt="Logo preloader saat ini">
                    <input id="preloader-logo" type="file" name="preloader_logo" accept=".jpg,.jpeg,.png,.webp">
                    <small class="system-settings-help">Jika tidak diunggah, preloader menggunakan logo aplikasi.</small>
                </div>
            </div>
            <button class="button button-primary" type="submit">Simpan identitas</button>
        </form>
    </div>

    <div class="system-settings-card" style="margin-bottom:18px">
        <h2>Gambar Hero</h2>
        <p>Tujuh slot gambar landing page. Gambar bawaan tetap digunakan pada slot yang belum memiliki unggahan.</p>
        <div class="system-settings-slots">
            @for ($slot = 1; $slot <= 7; $slot++)
                @php $hero = $heroes->get($slot); @endphp
                <form class="system-settings-slot" method="POST" action="{{ route('system-settings.hero', $slot) }}" enctype="multipart/form-data">
                    @csrf
                    <h3>Gambar {{ $slot }}</h3>
                    <img class="system-settings-preview" src="{{ $hero?->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($hero->image_path) : $siteConfiguration['hero_images'][$slot - 1] }}" alt="Hero {{ $slot }}">
                    <div class="system-settings-field">
                        <label for="hero-{{ $slot }}">Unggah gambar</label>
                        <input id="hero-{{ $slot }}" type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
                    </div>
                    @if ($hero?->image_path)
                        <label class="system-settings-check"><input type="checkbox" name="remove" value="1"> Kembalikan ke gambar bawaan</label>
                    @endif
                    <button class="button button-secondary" type="submit">Simpan gambar {{ $slot }}</button>
                </form>
            @endfor
        </div>
    </div>

    <div class="system-settings-grid">
        <section class="system-settings-card">
            <h2>SMTP</h2>
            <p>Password disimpan terenkripsi menggunakan APP_KEY. Kosongkan password saat memperbarui jika ingin mempertahankan password tersimpan.</p>
            <form method="POST" action="{{ route('system-settings.smtp') }}">
                @csrf @method('PUT')
                <label class="system-settings-check"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $mail?->enabled))> Aktifkan pengiriman SMTP</label>
                <div class="system-settings-row">
                    <div class="system-settings-field"><label for="smtp-scheme">Keamanan</label><select id="smtp-scheme" name="scheme"><option value="smtps" @selected(old('scheme', $mail?->scheme ?? 'smtps') === 'smtps')>SMTPS</option><option value="smtp" @selected(old('scheme', $mail?->scheme) === 'smtp')>SMTP / STARTTLS</option></select></div>
                    <div class="system-settings-field"><label for="smtp-port">Port</label><input id="smtp-port" name="port" type="number" min="1" max="65535" value="{{ old('port', $mail?->port ?? 465) }}" placeholder="465 (SMTPS) atau 587 (STARTTLS)"></div>
                </div>
                <div class="system-settings-field"><label for="smtp-host">Host SMTP</label><input id="smtp-host" name="host" value="{{ old('host', $mail?->host) }}" maxlength="255" placeholder="Contoh: mail.domainkampus.ac.id"></div>
                <div class="system-settings-field"><label for="smtp-username">Username</label><input id="smtp-username" name="username" value="{{ old('username', $mail?->username) }}" maxlength="255" autocomplete="username" placeholder="Contoh: notifikasi@domainkampus.ac.id"></div>
                <div class="system-settings-field"><label for="smtp-password">Password {{ $mailPasswordConfigured ? '(sudah tersimpan)' : '' }}</label><input id="smtp-password" name="password" type="password" maxlength="4096" autocomplete="new-password" placeholder="Masukkan password SMTP"></div>
                <div class="system-settings-row">
                    <div class="system-settings-field"><label for="smtp-from-address">Alamat pengirim</label><input id="smtp-from-address" name="from_address" type="email" value="{{ old('from_address', $mail?->from_address) }}" maxlength="255" placeholder="Contoh: akademik@domainkampus.ac.id"></div>
                    <div class="system-settings-field"><label for="smtp-from-name">Nama pengirim</label><input id="smtp-from-name" name="from_name" value="{{ old('from_name', $mail?->from_name) }}" maxlength="255" placeholder="Contoh: SIAKAD Kampus"></div>
                </div>
                <button class="button button-primary" type="submit">Simpan SMTP</button>
            </form>
            <form method="POST" action="{{ route('system-settings.smtp.test') }}" style="margin-top:14px">
                @csrf
                <div class="system-settings-field"><label for="smtp-recipient">Kirim email uji ke</label><input id="smtp-recipient" name="recipient" type="email" value="{{ old('recipient', auth()->user()->email ?? '') }}" maxlength="255" placeholder="nama@domainkampus.ac.id" required></div>
                <button class="button button-secondary" type="submit">Kirim email uji</button>
            </form>
        </section>

        <section class="system-settings-card">
            <h2>Midtrans Snap</h2>
            <p>Checkout Midtrans menggunakan Snap. Mulai dari sandbox; aktifkan produksi hanya setelah kredensial dan akun merchant diverifikasi. Biaya layanan bersifat opsional, nominal tetap dalam rupiah, dan ditambahkan terpisah dari tagihan kampus saat pembayaran Midtrans.</p>
            <p>Daftarkan URL notifikasi berikut pada pengaturan notifikasi pembayaran di dashboard Midtrans:</p>
            <code>{{ route('payments.midtrans.notification') }}</code>
            <small class="system-settings-help">Gunakan domain publik dengan HTTPS. Callback settlement akan memperbarui status lunas tagihan UKT/IPI secara otomatis.</small>
            <form method="POST" action="{{ route('system-settings.midtrans') }}">
                @csrf @method('PUT')
                <label class="system-settings-check"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $midtrans['enabled']))> Aktifkan pembayaran Midtrans</label>
                <div class="system-settings-field"><label for="midtrans-environment">Lingkungan</label><select id="midtrans-environment" name="environment"><option value="sandbox" @selected(old('environment', $midtrans['environment']) === 'sandbox')>Sandbox</option><option value="production" @selected(old('environment', $midtrans['environment']) === 'production')>Production</option></select></div>
                <div class="system-settings-field"><label for="midtrans-client-key">Client key</label><input id="midtrans-client-key" name="client_key" value="{{ old('client_key', $midtrans['client_key']) }}" maxlength="255" autocomplete="off" placeholder="SB-Mid-client-... untuk sandbox"></div>
                <div class="system-settings-field"><label for="midtrans-server-key">Server key {{ $midtransServerKeyConfigured ? '(sudah tersimpan)' : '' }}</label><input id="midtrans-server-key" name="server_key" type="password" maxlength="4096" autocomplete="new-password" placeholder="Masukkan server key Midtrans"></div>
                <small class="system-settings-help">Server key dienkripsi pada database dan tidak ditampilkan kembali setelah disimpan.</small>
                <div class="system-settings-field"><label for="midtrans-service-fee">Biaya layanan per transaksi (Rp)</label><input id="midtrans-service-fee" name="service_fee" type="number" min="0" max="2147483647" step="1" value="{{ old('service_fee', $midtrans['service_fee']) }}" placeholder="Kosongkan atau isi 0 untuk menonaktifkan"><small class="system-settings-help">Nominal ini dibebankan kepada pembayar hanya untuk checkout Midtrans. Nilai tagihan UKT/IPI kampus tetap tercatat terpisah.</small></div>
                <button class="button button-primary" type="submit" style="margin-top:14px">Simpan Midtrans</button>
            </form>
            <form method="POST" action="{{ route('system-settings.midtrans.test') }}" style="margin-top:12px">
                @csrf
                <small class="system-settings-help">Simpan konfigurasi terlebih dahulu. Pemeriksaan menguji autentikasi tanpa membuat transaksi pembayaran.</small>
                <button class="button button-secondary" type="submit">Cek koneksi Midtrans</button>
            </form>
        </section>

    </div>
@endsection
