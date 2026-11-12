<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bukti Pelunasan | {{ $siteConfiguration['app_name'] }}</title>
    <style>
        :root { color-scheme: light; font-family: Arial, Helvetica, sans-serif; color: #1d2b24; background: #edf1ed; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 28px 16px; }
        .billing-sheet { position: relative; isolation: isolate; width: min(100%, 760px); margin: 0 auto; padding: 38px; border: 1px solid #d6ded7; background: #fff; }
        .billing-heading { position: relative; z-index: 1; display: grid; grid-template-columns: 76px minmax(0, 1fr) 150px; align-items: center; gap: 20px; padding-bottom: 22px; border-bottom: 2px solid #243b2c; }
        .billing-heading-title { text-align: center; }
        .billing-heading h1 { margin: 0 0 6px; font-size: 23px; }
        .billing-heading p, .billing-footer { margin: 0; color: #617066; font-size: 12px; line-height: 1.6; }
        .billing-logo { display: block; width: auto; max-width: 154px; height: auto; max-height: 68px; object-fit: contain; }
        .billing-university-logo { width: 68px; max-height: 76px; }
        .billing-watermark { position: absolute; z-index: 2; top: 50%; left: 50%; width: calc(100% - 24px); transform: translate(-50%, -50%) rotate(-14deg); font-size: 64px; font-style: italic; font-weight: 900; line-height: 1; opacity: .16; text-align: center; white-space: nowrap; pointer-events: none; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .billing-watermark.is-paid { color: #18833e; }
        .billing-watermark.is-unpaid { color: #c62828; }
        .billing-details, .billing-instruction, .billing-footer { position: relative; z-index: 1; }
        .billing-details { display: grid; grid-template-columns: 1fr 1fr; gap: 0 30px; margin: 24px 0; }
        .billing-field { padding: 12px 0; border-bottom: 1px solid #e8ede8; }
        .billing-field dt { margin-bottom: 5px; color: #68766d; font-size: 10px; text-transform: uppercase; }
        .billing-field dd { margin: 0; overflow-wrap: anywhere; font-size: 13px; font-weight: 700; }
        .billing-amount { grid-column: 1 / -1; margin: 2px 0 14px; padding: 18px; background: rgb(243 247 242 / .68); }
        .billing-amount dd { color: #1d6339; font-size: 24px; }
        .billing-instruction { margin: 18px 0; padding: 14px 16px; background: #f5f7f5; font-size: 12px; line-height: 1.65; }
        .billing-instruction.is-midtrans { border-left: 4px solid #00a5a8; }
        .billing-footer { padding-top: 14px; border-top: 1px solid #e8ede8; }
        .billing-print-controls { display: flex; justify-content: center; gap: 8px; margin: 18px auto 0; }
        .billing-print-controls button { min-height: 38px; padding: 8px 14px; border: 1px solid #294c37; border-radius: 4px; background: #294c37; color: #fff; font-size: 13px; cursor: pointer; }
        .billing-print-controls button:last-child { background: #fff; color: #294c37; }
        @media (max-width: 560px) { body { padding: 12px 8px; } .billing-sheet { padding: 22px 18px; } .billing-heading { grid-template-columns: 48px minmax(0, 1fr) 78px; gap: 8px; } .billing-heading h1 { font-size: 16px; } .billing-heading p { font-size: 10px; } .billing-university-logo { width: 44px; } .billing-logo { max-width: 78px; max-height: 48px; } .billing-watermark { font-size: 36px; } .billing-details { grid-template-columns: 1fr; } .billing-amount { grid-column: auto; } }
        @media print { :root { background: #fff; } body { padding: 0; } .billing-sheet { width: 100%; padding: 0; border: 0; } .billing-print-controls { display: none; } }
    </style>
</head>
<body>
    @php($paymentMethodLabel = ['midtrans' => 'Midtrans', 'beasiswa' => 'Beasiswa', 'pemda' => 'Bantuan pemerintah daerah', 'manual' => 'Pembayaran manual'][$billing['payment_method']] ?? (filled($billing['payment_method']) ? ucwords((string) $billing['payment_method']) : 'Pembayaran'))
    @php($displayOrderId = preg_replace('/^SIMAK-/i', 'SIAKAD-', (string) $billing['order_id']))
    <main class="billing-sheet">
        <div class="billing-watermark {{ $billing['is_paid'] ? 'is-paid' : 'is-unpaid' }}" aria-hidden="true">{{ $billing['is_paid'] ? 'LUNAS' : 'BELUM LUNAS' }}</div>
        <header class="billing-heading">
            <img class="billing-logo billing-university-logo"
                 src="{{ $siteConfiguration['preloader_logo_url'] ?: $siteConfiguration['logo_url'] }}"
                 alt="Logo {{ $siteConfiguration['institution_name'] }}"
                 onerror="this.onerror=null; this.src='{{ asset('images/landing/icon.png') }}';">
            <div class="billing-heading-title">
                <h1>{{ $billing['is_paid'] ? 'Bukti Pelunasan' : 'Billing' }}</h1>
                <p>{{ $siteConfiguration['app_name'] }}</p>
            </div>
            @if ($billing['payment_method'] === 'midtrans')
                <img class="billing-logo" src="{{ asset('images/payments/midtrans.svg') }}" alt="Midtrans">
            @else
                <strong>{{ $paymentMethodLabel }}</strong>
            @endif
        </header>

        <dl class="billing-details">
            <div class="billing-field"><dt>Nama pembayar</dt><dd>{{ $billing['name'] }}</dd></div>
            <div class="billing-field"><dt>NIM / NPM</dt><dd>{{ $billing['nim'] ?: '-' }}</dd></div>
            <div class="billing-field"><dt>Fakultas</dt><dd>{{ $billing['faculty'] ?: '-' }}</dd></div>
            <div class="billing-field"><dt>Program studi</dt><dd>{{ $billing['program'] ?: '-' }}</dd></div>
            <div class="billing-field"><dt>Keperluan</dt><dd>{{ $billing['description'] ?: '-' }}</dd></div>
            <div class="billing-field billing-amount"><dt>Nominal tagihan UKT / IPI</dt><dd>Rp {{ number_format($billing['amount'], 0, ',', '.') }}</dd></div>
            @if ($billing['service_fee'] > 0)
                <div class="billing-field"><dt>Biaya layanan Midtrans</dt><dd>Rp {{ number_format($billing['service_fee'], 0, ',', '.') }}</dd></div>
            @endif
            <div class="billing-field"><dt>Total pembayaran</dt><dd>Rp {{ number_format($billing['total_amount'], 0, ',', '.') }}</dd></div>
            <div class="billing-field"><dt>{{ $billing['payment_method'] === 'midtrans' ? 'ID order Midtrans' : 'Nomor referensi' }}</dt><dd>{{ $displayOrderId }}</dd></div>
            @if ($billing['payment_method'] === 'midtrans' && filled($billing['transaction_id']) && $billing['transaction_id'] !== '-')
                <div class="billing-field"><dt>ID transaksi Midtrans</dt><dd>{{ $billing['transaction_id'] }}</dd></div>
            @endif
            <div class="billing-field"><dt>Metode pembayaran</dt><dd>{{ $paymentMethodLabel }}</dd></div>
            @if ($billing['is_paid'])
                <div class="billing-field"><dt>Tanggal pembayaran</dt><dd>{{ $billing['paid_at'] ?: '-' }}</dd></div>
            @endif
        </dl>

        <p class="billing-instruction {{ $billing['payment_method'] === 'midtrans' ? 'is-midtrans' : '' }}">
            @if ($billing['payment_method'] === 'midtrans')
                Pembayaran tagihan ini telah diverifikasi melalui Midtrans dan tercatat lunas pada SIAKAD.
            @else
                Pembayaran tagihan ini telah diverifikasi dan dicatat lunas secara manual oleh petugas keuangan SIAKAD.
            @endif
        </p>
        <p class="billing-footer">Simpan bukti pelunasan ini untuk keperluan administrasi.</p>
    </main>
    <div class="billing-print-controls">
        <button type="button" onclick="window.print()">Cetak billing</button>
        <button type="button" onclick="window.close()">Tutup</button>
    </div>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>