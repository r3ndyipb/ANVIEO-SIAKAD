<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Billing Manual | {{ $siteConfiguration['app_name'] }}</title>
    <style>
        :root { color-scheme:light; font-family:Arial,Helvetica,sans-serif; color:#1d2b24; background:#edf1ed; }
        * { box-sizing:border-box; }
        body { margin:0; padding:28px 16px; }
        .billing-sheet { position:relative; isolation:isolate; width:min(100%,760px); margin:0 auto; padding:38px; border:1px solid #d6ded7; background:#fff; }
        .billing-heading { display:grid; grid-template-columns:76px minmax(0,1fr) 150px; align-items:center; gap:20px; padding-bottom:22px; border-bottom:2px solid #243b2c; }
        .billing-heading-title { text-align:center; }
        .billing-heading h1 { margin:0 0 6px; font-size:23px; }
        .billing-heading p, .billing-footer { margin:0; color:#617066; font-size:12px; line-height:1.6; }
        .billing-logo { display:block; width:auto; max-width:154px; height:auto; max-height:68px; object-fit:contain; }
        .billing-university-logo { width:68px; max-height:76px; }
        .billing-watermark { position:absolute; z-index:2; top:50%; left:50%; width:calc(100% - 24px); transform:translate(-50%,-50%) rotate(-14deg); font-size:64px; font-style:italic; font-weight:900; line-height:1; opacity:.16; text-align:center; white-space:nowrap; pointer-events:none; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
        .billing-watermark.is-paid { color:#18833e; }
        .billing-watermark.is-unpaid { color:#c62828; }
        .billing-details, .billing-footer { position:relative; z-index:1; }
        .billing-details { display:grid; grid-template-columns:1fr 1fr; gap:0 30px; margin:24px 0; }
        .billing-field { padding:12px 0; border-bottom:1px solid #e8ede8; }
        .billing-field dt { margin-bottom:5px; color:#68766d; font-size:10px; text-transform:uppercase; }
        .billing-field dd { margin:0; overflow-wrap:anywhere; font-size:13px; font-weight:700; }
        .billing-amount { grid-column:1 / -1; margin:2px 0 14px; padding:18px; background:rgb(243 247 242 / .68); }
        .billing-amount dd { color:#1d6339; font-size:24px; }
        .billing-instruction { position:relative; z-index:1; margin:18px 0; padding:14px 16px; border-left:4px solid #00a5a8; background:#f5f7f5; font-size:12px; line-height:1.65; overflow-wrap:anywhere; }
        .billing-footer { padding-top:14px; border-top:1px solid #e8ede8; }
        .billing-print-controls { display:flex; justify-content:center; gap:8px; margin:18px auto 0; }
        .billing-print-controls button { min-height:38px; padding:8px 14px; border:1px solid #294c37; border-radius:4px; background:#294c37; color:#fff; font-size:13px; cursor:pointer; }
        .billing-print-controls button:last-child { background:#fff; color:#294c37; }
        @media (max-width:560px) { body { padding:12px 8px; } .billing-sheet { padding:22px 18px; } .billing-heading { grid-template-columns:48px minmax(0,1fr) 78px; gap:8px; } .billing-heading h1 { font-size:16px; } .billing-university-logo { width:44px; } .billing-logo { max-width:78px; max-height:48px; } .billing-watermark { font-size:36px; } .billing-details { grid-template-columns:1fr; } .billing-amount { grid-column:auto; } }
        @media print { :root { background:#fff; } body { padding:0; } .billing-sheet { width:100%; padding:0; border:0; } .billing-print-controls { display:none; } }
    </style>
</head>
<body>
    <main class="billing-sheet">
        <div class="billing-watermark {{ $billing['is_paid'] ? 'is-paid' : 'is-unpaid' }}" aria-hidden="true">{{ $billing['is_paid'] ? 'LUNAS' : 'BELUM LUNAS' }}</div>
        <header class="billing-heading">
            <img class="billing-logo billing-university-logo" src="{{ $siteConfiguration['logo_url'] }}" alt="Logo {{ $siteConfiguration['institution_name'] }}">
            <div class="billing-heading-title">
                <h1>{{ $billing['is_paid'] ? 'Bukti Pelunasan' : 'Billing Manual' }}</h1>
                <p>{{ $siteConfiguration['app_name'] }}</p>
            </div>
            <img class="billing-logo" src="{{ asset('images/payments/midtrans.svg') }}" alt="Midtrans">
        </header>
        <dl class="billing-details">
            <div class="billing-field"><dt>Nama pembayar</dt><dd>{{ $billing['name'] }}</dd></div>
            <div class="billing-field"><dt>Email</dt><dd>{{ $billing['email'] ?: '-' }}</dd></div>
            <div class="billing-field"><dt>Telepon</dt><dd>{{ $billing['phone'] ?: '-' }}</dd></div>
            <div class="billing-field"><dt>Keperluan</dt><dd>{{ $billing['description'] }}</dd></div>
            <div class="billing-field billing-amount"><dt>Nominal tagihan</dt><dd>Rp {{ number_format($billing['amount'], 0, ',', '.') }}</dd></div>
            @if ($billing['service_fee'] > 0)
                <div class="billing-field"><dt>Biaya layanan Midtrans</dt><dd>Rp {{ number_format($billing['service_fee'], 0, ',', '.') }}</dd></div>
            @endif
            <div class="billing-field"><dt>Total pembayaran</dt><dd>Rp {{ number_format($billing['total_amount'], 0, ',', '.') }}</dd></div>
            <div class="billing-field"><dt>ID order Midtrans</dt><dd>{{ preg_replace('/^SIMAK-/i', 'SIAKAD-', $billing['order_id']) }}</dd></div>
            <div class="billing-field"><dt>Status pembayaran</dt><dd>{{ $billing['is_paid'] ? 'Lunas' : 'Belum lunas' }}</dd></div>
            <div class="billing-field"><dt>Berlaku sampai</dt><dd>{{ $billing['expires_at'] ?: '-' }}</dd></div>
            @if ($billing['transaction_id'])
                <div class="billing-field"><dt>ID transaksi Midtrans</dt><dd>{{ $billing['transaction_id'] }}</dd></div>
            @endif
        </dl>
        <p class="billing-instruction">
            @if ($billing['is_paid'])
                Pembayaran billing ini telah diverifikasi melalui Midtrans dan tercatat lunas pada SIAKAD.
            @else
                Silakan lakukan pembayaran melalui tautan checkout Midtrans berikut:<br>
                <a href="{{ $billing['redirect_url'] }}">{{ $billing['redirect_url'] }}</a>
            @endif
        </p>
        <p class="billing-footer">{{ $siteConfiguration['institution_name'] }} · Billing transaksi SIAKAD</p>
    </main>
    <div class="billing-print-controls">
        <button type="button" onclick="window.print()">Cetak billing</button>
        <button type="button" onclick="window.close()">Tutup</button>
    </div>
</body>
</html>
