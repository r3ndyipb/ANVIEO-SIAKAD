@extends('layouts.simak')

@section('title', $title.' | SIAKAD')
@section('topbar_title', $title)

@push('styles')
    <style>
        .manual-bill-dialog { position:fixed; inset:50% auto auto 50%; width:min(680px, calc(100vw - 28px)); max-height:calc(100vh - 32px); margin:0; padding:0; overflow:auto; transform:translate(-50%, -50%); border:1px solid #dce5d9; border-radius:9px; box-shadow:0 24px 80px #17251a40; }
        .manual-bill-dialog::backdrop { background:#17251a80; }
        .manual-bill-dialog-head, .manual-bill-dialog-actions { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:14px 18px; border-bottom:1px solid #e8ede6; }
        .manual-bill-dialog-head h2 { margin:0; color:#284a33; font-size:17px; }
        .manual-bill-form { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; padding:18px; }
        .manual-bill-form label { display:grid; gap:6px; color:#52634f; font-size:11px; font-weight:700; }
        .manual-bill-form input, .manual-bill-form textarea { width:100%; min-height:40px; padding:9px 10px; border:1px solid #d9e2d7; border-radius:5px; background:#fff; color:#354a38; font:inherit; }
        .manual-bill-form textarea { min-height:76px; resize:vertical; }
        .manual-bill-form .wide { grid-column:1 / -1; }
        .manual-bill-dialog-actions { justify-content:flex-end; border-top:1px solid #e8ede6; border-bottom:0; }
        .manual-bill-note { margin:0 0 16px; }
        .manual-bill-actions { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
        .manual-bill-url { width:min(520px,100%); min-height:36px; padding:7px 9px; border:1px solid #d9e2d7; border-radius:5px; }
        .manual-bill-table { width:100%; min-width:940px; border-collapse:collapse; font-size:11px; }
        .manual-bill-table th, .manual-bill-table td { padding:10px 11px; border-bottom:1px solid #edf1eb; text-align:left; vertical-align:top; }
        .manual-bill-table th { background:#f7f9f5; color:#63725e; font-size:9px; text-transform:uppercase; white-space:nowrap; }
        .manual-bill-table tr:last-child td { border-bottom:0; }
        .manual-bill-payment-status { display:inline-flex; padding:4px 8px; border-radius:99px; font-size:10px; font-weight:800; white-space:nowrap; }
        .manual-bill-payment-status.is-paid { background:#e9f7ec; color:#176b2c; }
        .manual-bill-payment-status.is-unpaid { background:#fff0f0; color:#b42318; }
        .manual-bill-process { display:flex; align-items:center; gap:6px; }
        .manual-bill-process form { margin:0; }
        .manual-bill-empty { padding:22px; color:#69786f; text-align:center; }
        .manual-bill-pagination { padding:14px 0 0; }
        @media (max-width:640px) { .manual-bill-form { grid-template-columns:1fr; } .manual-bill-form .wide { grid-column:auto; } }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div><p class="eyebrow">Keuangan</p><h1>{{ $title }}</h1><p class="subtitle">Buat tautan pembayaran Midtrans untuk uraian transaksi di luar UKT dan IPI.</p></div>
        @if (auth()->user()?->role === 'keuangan')
            <button class="button button-primary" type="button" data-dialog-open="manual-bill-create"><span data-lucide="plus" aria-hidden="true"></span> Buat billing</button>
        @endif
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    @if (session('checkout_url'))
        <div class="module-alert finance-alert" role="status">
            <p>Tautan pembayaran sudah dibuat. Bagikan tautan ini kepada pembayar:</p>
            <div class="manual-bill-actions">
                <input class="manual-bill-url" id="manual-bill-checkout-url" type="url" readonly value="{{ session('checkout_url') }}" aria-label="Tautan pembayaran">
                <button class="button button-secondary" type="button" data-copy-billing-link="manual-bill-checkout-url">Salin tautan</button>
                <a class="button button-primary" href="{{ session('checkout_url') }}" target="_blank" rel="noopener noreferrer">Buka checkout</a>
            </div>
            <p class="subtitle" data-copy-feedback aria-live="polite"></p>
        </div>
    @endif

    <div class="module-card">
        <h2>Daftar billing manual</h2>
        <div class="table-wrap">
            <table class="manual-bill-table">
                <thead>
                    <tr><th>Dibuat</th><th>Pembayar</th><th>Deskripsi</th><th>Nominal</th><th>Total Midtrans</th><th>Berlaku sampai</th><th>Status pembayaran</th><th>Status Midtrans</th><th>Proses</th></tr>
                </thead>
                <tbody>
                    @forelse ($bills as $bill)
                        <tr>
                            <td>{{ $bill->created_at }}</td>
                            <td><strong>{{ $bill->payer_name }}</strong><br>{{ $bill->payer_email ?: '-' }}<br>{{ $bill->payer_phone ?: '-' }}</td>
                            <td>{{ $bill->description }}</td>
                            <td>Rp {{ number_format((int) $bill->amount, 0, ',', '.') }}</td>
                            <td>{{ $bill->gross_amount !== null ? 'Rp '.number_format((int) $bill->gross_amount, 0, ',', '.') : '-' }}</td>
                            <td>{{ $bill->expires_at }}</td>
                            <td><span class="manual-bill-payment-status {{ (int) $bill->is_paid === 1 ? 'is-paid' : 'is-unpaid' }}" data-payment-status="{{ (int) $bill->is_paid === 1 ? 'paid' : 'unpaid' }}">{{ (int) $bill->is_paid === 1 ? 'Lunas' : 'Belum lunas' }}</span></td>
                            <td>{{ ucfirst(str_replace('_', ' ', $bill->transaction_status ?: $bill->checkout_status)) }}</td>
                            <td>
                                <div class="manual-bill-process">
                                    <a class="table-icon-button" href="{{ route('finance.manual-bills.print', $bill->id) }}" target="_blank" rel="noopener noreferrer" title="Cetak billing" aria-label="Cetak billing {{ $bill->payer_name }}"><span data-lucide="printer" aria-hidden="true"></span></a>
                                    @if (auth()->user()?->role === 'keuangan' && (int) $bill->is_paid !== 1 && $bill->order_id)
                                        <form method="POST" action="{{ route('finance.manual-bills.status', $bill->id) }}">
                                            @csrf
                                            <button class="table-icon-button" type="submit" title="Cek status bayar" aria-label="Cek status bayar {{ $bill->payer_name }}"><span data-lucide="refresh-cw" aria-hidden="true"></span></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="manual-bill-empty">Belum ada billing manual.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($bills->hasPages())
            <div class="manual-bill-pagination">{{ $bills->links('vendor.pagination.datatables') }}</div>
        @endif
    </div>

    @if (auth()->user()?->role === 'keuangan')
        <dialog class="manual-bill-dialog" id="manual-bill-create" aria-labelledby="manual-bill-create-title" @if ($errors->any()) data-open-on-load="true" @endif>
            <header class="manual-bill-dialog-head">
                <div><h2 id="manual-bill-create-title">Buat billing manual</h2><p class="manual-bill-note">Biaya layanan Midtrans yang aktif ditambahkan ke nominal tagihan. Nama wajib diisi; email dan telepon opsional. Masa berlaku dapat diatur dari 1 sampai 720 jam.</p></div>
                <button class="table-icon-button" type="button" data-dialog-close aria-label="Tutup dialog"><span data-lucide="x" aria-hidden="true"></span></button>
            </header>
            <form method="POST" action="{{ route('finance.manual-bills.store') }}">
                @csrf
                <div class="manual-bill-form">
                    <label>Nama pembayar<input name="payer_name" value="{{ old('payer_name') }}" maxlength="100" required></label>
                    <label>Email pembayar (opsional)<input type="email" name="payer_email" value="{{ old('payer_email') }}" maxlength="255"></label>
                    <label>Telepon pembayar (opsional)<input type="tel" name="payer_phone" value="{{ old('payer_phone') }}" maxlength="30"></label>
                    <label>Nominal tagihan (Rp)<input type="number" name="amount" value="{{ old('amount') }}" min="1" max="999999999999" step="1" required></label>
                    <label class="wide">Deskripsi transaksi<textarea name="description" maxlength="255" required>{{ old('description') }}</textarea></label>
                    <label>Masa berlaku tautan (jam)<input type="number" name="expires_in_hours" value="{{ old('expires_in_hours', 24) }}" min="1" max="720" step="1" required></label>
                </div>
                <footer class="manual-bill-dialog-actions">
                    <button class="button button-secondary" type="button" data-dialog-close>Batal</button>
                    <button class="button button-primary" type="submit">Buat billing</button>
                </footer>
            </form>
        </dialog>
    @endif

    <script>
        document.querySelectorAll('[data-copy-billing-link]').forEach((button) => {
            button.addEventListener('click', async () => {
                const input = document.getElementById(button.dataset.copyBillingLink);
                const feedback = button.closest('.module-alert')?.querySelector('[data-copy-feedback]');
                if (!input) return;
                input.select();
                try {
                    await navigator.clipboard.writeText(input.value);
                    if (feedback) feedback.textContent = 'Tautan berhasil disalin.';
                } catch {
                    const copied = document.execCommand('copy');
                    if (feedback) feedback.textContent = copied
                        ? 'Tautan berhasil disalin.'
                        : 'Salin otomatis tidak tersedia; tautan sudah dipilih untuk disalin manual.';
                }
            });
        });
    </script>
@endsection
