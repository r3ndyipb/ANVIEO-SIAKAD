@extends('layouts.simak')

@section('title', 'Pengaturan Sistem | '.$siteConfiguration['app_name'])
@section('topbar_title', 'Pengaturan Sistem')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Identitas & Integrasi</p>
            <h1>Pengaturan Sistem</h1>
            <p class="subtitle">Kelola identitas kampus, logo institusi, aset landing page, konfigurasi pengiriman email SMTP, dan gateway pembayaran Midtrans Snap.</p>
        </div>
    </div>

    @include('partials.flash-notice')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Pengaturan belum tersimpan.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- 1. IDENTITAS & BRANDING KAMPUS --}}
    <div class="system-settings-card">
        <div class="system-settings-card-head">
            <h2><span data-lucide="building-2"></span> Identitas & Branding Kampus</h2>
            <span class="chip chip-gold">Identitas Utama</span>
        </div>
        <p class="system-settings-card-desc">Nama perguruan tinggi dibaca otomatis dari data institusi (Master PT). Motto, warna tema aplikasi, dan nama kementerian disesuaikan di sini. File gambar tersimpan pada public storage secara aman.</p>
        
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
                    <div class="system-settings-color-row">
                        <input id="primary-color" type="color" name="primary_color" value="{{ old('primary_color', $siteConfiguration['primary_color']) }}" required>
                        <span class="system-settings-color-badge" id="primary-color-badge">{{ old('primary_color', $siteConfiguration['primary_color']) }}</span>
                    </div>
                    <small class="system-settings-help">Pilih warna identitas kampus. Warna diterapkan pada tombol utama, navigasi, aksen, dan landing page.</small>
                </div>
            @else
                <p class="system-settings-help">Color picker belum tersedia pada database ini. Jalankan database/manual/simak_site_settings_primary_color.sql, lalu muat ulang halaman.</p>
            @endif

            <div class="system-settings-row">
                <div class="system-settings-field">
                    <label for="logo">Logo aplikasi</label>
                    <div class="system-settings-preview-wrap">
                        <img class="system-settings-preview" src="{{ $siteConfiguration['logo_url'] }}" alt="Logo saat ini" onerror="this.onerror=null; this.src='{{ asset('images/landing/default-campus-logo.png') }}';">
                    </div>
                    <input id="logo" type="file" name="logo" accept=".jpg,.jpeg,.png,.webp">
                    <small class="system-settings-help">JPG, PNG, atau WebP; maksimal 5 MB. Jika belum diunggah, logo akademik default akan digunakan.</small>
                </div>

                <div class="system-settings-field">
                    <label for="preloader-logo">Logo preloader</label>
                    <div class="system-settings-preview-wrap">
                        <img class="system-settings-preview" src="{{ $siteConfiguration['preloader_logo_url'] }}" alt="Logo preloader saat ini" onerror="this.onerror=null; this.src='{{ asset('images/landing/icon.png') }}';">
                    </div>
                    <input id="preloader-logo" type="file" name="preloader_logo" accept=".jpg,.jpeg,.png,.webp">
                    <small class="system-settings-help">Jika tidak diunggah, preloader menggunakan logo aplikasi.</small>
                </div>
            </div>

            <button class="button button-primary" type="submit" style="margin-top:8px"><span data-lucide="save"></span> Simpan identitas</button>
        </form>
    </div>

    {{-- 2. GAMBAR HERO LANDING PAGE --}}
    <div class="system-settings-card">
        <div class="system-settings-card-head">
            <h2><span data-lucide="images"></span> Gambar Hero</h2>
            <span class="chip chip-gold">7 Slot Showcase</span>
        </div>
        <p class="system-settings-card-desc">Tujuh slot gambar landing page. Gambar bawaan tetap digunakan pada slot yang belum memiliki unggahan.</p>
        
        <div class="system-settings-slots">
            @for ($slot = 1; $slot <= 7; $slot++)
                @php
                    $hero = $heroes->get($slot);
                    $hasCustomHero = $hero?->image_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($hero->image_path);
                    $heroUrl = $hasCustomHero ? \Illuminate\Support\Facades\Storage::disk('public')->url($hero->image_path) : $siteConfiguration['hero_images'][$slot - 1];
                @endphp
                <form class="system-settings-slot" method="POST" action="{{ route('system-settings.hero', $slot) }}" enctype="multipart/form-data">
                    @csrf
                    <div>
                        <div class="system-settings-slot-head">
                            <h3><span data-lucide="image"></span> Gambar {{ $slot }}</h3>
                            <span class="chip {{ $hasCustomHero ? 'chip-info' : 'chip-muted' }}">{{ $hasCustomHero ? 'Kustom' : 'Bawaan' }}</span>
                        </div>
                        <img class="system-settings-preview system-settings-hero-preview" src="{{ $heroUrl }}" alt="Hero {{ $slot }}" onerror="this.onerror=null; this.src='{{ asset('images/landing/hero-0'.$slot.'.jpg') }}';">
                        <div class="system-settings-field" style="margin: 10px 0 6px;">
                            <label for="hero-{{ $slot }}">Unggah gambar</label>
                            <input id="hero-{{ $slot }}" type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
                        </div>
                        @if ($hero?->image_path)
                            <label class="system-settings-check"><input type="checkbox" name="remove" value="1"> Kembalikan ke gambar bawaan</label>
                        @endif
                    </div>
                    <button class="button button-secondary" type="submit"><span data-lucide="upload"></span> Simpan gambar {{ $slot }}</button>
                </form>
            @endfor
        </div>
    </div>

    {{-- 3. INTEGRASI SISTEM (SMTP & MIDTRANS) --}}
    <div class="system-settings-grid">
        <section class="system-settings-card">
            <div class="system-settings-card-head">
                <h2><span data-lucide="mail"></span> SMTP</h2>
                <span class="chip {{ $mail?->enabled ? 'chip-success' : 'chip-muted' }}">{{ $mail?->enabled ? 'Aktif' : 'Non-Aktif' }}</span>
            </div>
            <p class="system-settings-card-desc">Password disimpan terenkripsi menggunakan APP_KEY. Kosongkan password saat memperbarui jika ingin mempertahankan password tersimpan.</p>
            
            <form method="POST" action="{{ route('system-settings.smtp') }}">
                @csrf @method('PUT')
                <label class="system-settings-check"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $mail?->enabled))> Aktifkan pengiriman SMTP</label>
                
                <div class="system-settings-row">
                    <div class="system-settings-field">
                        <label for="smtp-scheme">Keamanan</label>
                        <select id="smtp-scheme" name="scheme">
                            <option value="smtps" @selected(old('scheme', $mail?->scheme ?? 'smtps') === 'smtps')>SMTPS</option>
                            <option value="smtp" @selected(old('scheme', $mail?->scheme) === 'smtp')>SMTP / STARTTLS</option>
                        </select>
                    </div>
                    <div class="system-settings-field">
                        <label for="smtp-port">Port</label>
                        <input id="smtp-port" name="port" type="number" min="1" max="65535" value="{{ old('port', $mail?->port ?? 465) }}" placeholder="465 (SMTPS) atau 587 (STARTTLS)">
                    </div>
                </div>

                <div class="system-settings-field">
                    <label for="smtp-host">Host SMTP</label>
                    <input id="smtp-host" name="host" value="{{ old('host', $mail?->host) }}" maxlength="255" placeholder="Contoh: mail.domainkampus.ac.id">
                </div>

                <div class="system-settings-field">
                    <label for="smtp-username">Username</label>
                    <input id="smtp-username" name="username" value="{{ old('username', $mail?->username) }}" maxlength="255" autocomplete="username" placeholder="Contoh: notifikasi@domainkampus.ac.id">
                </div>

                <div class="system-settings-field">
                    <label for="smtp-password">Password {{ $mailPasswordConfigured ? '(sudah tersimpan)' : '' }}</label>
                    <input id="smtp-password" name="password" type="password" maxlength="4096" autocomplete="new-password" placeholder="Masukkan password SMTP">
                </div>

                <div class="system-settings-row">
                    <div class="system-settings-field">
                        <label for="smtp-from-address">Alamat pengirim</label>
                        <input id="smtp-from-address" name="from_address" type="email" value="{{ old('from_address', $mail?->from_address) }}" maxlength="255" placeholder="Contoh: akademik@domainkampus.ac.id">
                    </div>
                    <div class="system-settings-field">
                        <label for="smtp-from-name">Nama pengirim</label>
                        <input id="smtp-from-name" name="from_name" value="{{ old('from_name', $mail?->from_name) }}" maxlength="255" placeholder="Contoh: SIAKAD Kampus">
                    </div>
                </div>

                <button class="button button-primary" type="submit" style="margin-top:14px"><span data-lucide="save"></span> Simpan SMTP</button>
            </form>

            <div class="system-settings-test-box">
                <form method="POST" action="{{ route('system-settings.smtp.test') }}">
                    @csrf
                    <div class="system-settings-field">
                        <label for="smtp-recipient">Kirim email uji ke</label>
                        <input id="smtp-recipient" name="recipient" type="email" value="{{ old('recipient', auth()->user()->email ?? '') }}" maxlength="255" placeholder="nama@domainkampus.ac.id" required>
                    </div>
                    <button class="button button-secondary" type="submit"><span data-lucide="send"></span> Kirim email uji</button>
                </form>
            </div>
        </section>

        <section class="system-settings-card">
            <div class="system-settings-card-head">
                <h2><span data-lucide="credit-card"></span> Midtrans Snap</h2>
                <span class="chip {{ $midtrans['enabled'] ? 'chip-success' : 'chip-muted' }}">{{ $midtrans['enabled'] ? 'Aktif' : 'Non-Aktif' }}</span>
            </div>
            <p class="system-settings-card-desc">Checkout Midtrans menggunakan Snap. Mulai dari sandbox; aktifkan produksi hanya setelah kredensial dan akun merchant diverifikasi. Biaya layanan bersifat opsional, nominal tetap dalam rupiah, dan ditambahkan terpisah dari tagihan kampus saat pembayaran Midtrans.</p>
            
            <div style="margin: 14px 0 18px;">
                <p style="margin: 0 0 6px; font-size: 0.76rem; font-weight: 700; color: var(--bx-ink);">Daftarkan URL notifikasi berikut pada pengaturan notifikasi pembayaran di dashboard Midtrans:</p>
                <code>{{ route('payments.midtrans.notification') }}</code>
                <small class="system-settings-help" style="margin-top:6px;">Gunakan domain publik dengan HTTPS. Callback settlement akan memperbarui status lunas tagihan UKT/IPI secara otomatis.</small>
            </div>

            <form method="POST" action="{{ route('system-settings.midtrans') }}">
                @csrf @method('PUT')
                <label class="system-settings-check"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $midtrans['enabled']))> Aktifkan pembayaran Midtrans</label>
                
                <div class="system-settings-row">
                    <div class="system-settings-field">
                        <label for="midtrans-environment">Lingkungan</label>
                        <select id="midtrans-environment" name="environment">
                            <option value="sandbox" @selected(old('environment', $midtrans['environment']) === 'sandbox')>Sandbox</option>
                            <option value="production" @selected(old('environment', $midtrans['environment']) === 'production')>Production</option>
                        </select>
                    </div>
                    <div class="system-settings-field">
                        <label for="midtrans-service-fee">Biaya layanan per transaksi (Rp)</label>
                        <input id="midtrans-service-fee" name="service_fee" type="number" min="0" max="2147483647" step="1" value="{{ old('service_fee', $midtrans['service_fee']) }}" placeholder="Kosongkan atau isi 0 untuk menonaktifkan">
                    </div>
                </div>

                <div class="system-settings-field">
                    <label for="midtrans-client-key">Client key</label>
                    <input id="midtrans-client-key" name="client_key" value="{{ old('client_key', $midtrans['client_key']) }}" maxlength="255" autocomplete="off" placeholder="SB-Mid-client-... untuk sandbox">
                </div>

                <div class="system-settings-field">
                    <label for="midtrans-server-key">Server key {{ $midtransServerKeyConfigured ? '(sudah tersimpan)' : '' }}</label>
                    <input id="midtrans-server-key" name="server_key" type="password" maxlength="4096" autocomplete="new-password" placeholder="Masukkan server key Midtrans">
                </div>
                <small class="system-settings-help">Server key dienkripsi pada database dan tidak ditampilkan kembali setelah disimpan.</small>
                <small class="system-settings-help" style="margin-top:4px;">Nominal ini dibebankan kepada pembayar hanya untuk checkout Midtrans. Nilai tagihan UKT/IPI kampus tetap tercatat terpisah.</small>

                <button class="button button-primary" type="submit" style="margin-top:16px"><span data-lucide="save"></span> Simpan Midtrans</button>
            </form>

            <div class="system-settings-test-box">
                <form method="POST" action="{{ route('system-settings.midtrans.test') }}">
                    @csrf
                    <small class="system-settings-help" style="margin-bottom:8px">Simpan konfigurasi terlebih dahulu. Pemeriksaan menguji autentikasi tanpa membuat transaksi pembayaran.</small>
                    <button class="button button-secondary" type="submit"><span data-lucide="shield-check"></span> Cek koneksi Midtrans</button>
                </form>
            </div>
        </section>
    </div>

    @push('scripts')
    <script>
        (function() {
            const colorInput = document.getElementById('primary-color');
            const colorBadge = document.getElementById('primary-color-badge');
            if (colorInput && colorBadge) {
                colorInput.addEventListener('input', function() {
                    colorBadge.textContent = this.value;
                });
            }
        })();
    </script>
    @endpush
@endsection
