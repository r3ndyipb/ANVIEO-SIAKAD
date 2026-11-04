@extends('layouts.simak')

@section('title', $title.' | SIAKAD')
@section('topbar_title', $title)

@push('styles')
    <style>
        .midtrans-history-filters { display:flex; flex-wrap:wrap; align-items:end; gap:10px; margin:0 0 16px; }
        .midtrans-history-filters label { display:grid; gap:6px; color:#52634f; font-size:11px; font-weight:700; }
        .midtrans-history-filters input { min-height:38px; padding:8px 10px; border:1px solid #d9e2d7; border-radius:5px; background:#fff; color:#354a38; }
        .midtrans-history-table { width:100%; min-width:980px; border-collapse:collapse; font-size:11px; }
        .midtrans-history-table th, .midtrans-history-table td { padding:10px 11px; border-bottom:1px solid #edf1eb; text-align:left; vertical-align:middle; }
        .midtrans-history-table th { background:#f7f9f5; color:#63725e; font-size:9px; text-transform:uppercase; white-space:nowrap; }
        .midtrans-history-table tr:last-child td { border-bottom:0; }
        .midtrans-history-status { display:inline-flex; padding:4px 8px; border-radius:99px; font-size:10px; font-weight:800; white-space:nowrap; }
        .midtrans-history-status.is-paid { background:#e9f7ec; color:#176b2c; }
        .midtrans-history-status.is-unpaid { background:#fff0f0; color:#b42318; }
        .midtrans-history-note { margin:0 0 16px; }
        .midtrans-history-empty { padding:22px; color:#69786f; text-align:center; }
        .midtrans-history-pagination { padding:14px 0 0; }
        @media (max-width:640px) { .midtrans-history-filters > label { flex:1 1 100%; } .midtrans-history-filters input, .midtrans-history-filters button { width:100%; } }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div><p class="eyebrow">Keuangan</p><h1>{{ $title }}</h1><p class="subtitle">Riwayat transaksi Midtrans yang dicatat SIAKAD pada rentang tanggal pilihan.</p></div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    <div class="module-alert finance-alert" role="note">
        <p>Data diambil dari catatan transaksi SIAKAD, bukan permintaan langsung ke dashboard Midtrans. Riwayat mencakup transaksi yang tercatat saat konfigurasi menggunakan Sandbox maupun Production.</p>
    </div>

    <form class="midtrans-history-filters" method="GET" action="{{ route('finance.midtrans-transactions.index') }}">
        <label>Tanggal mulai<input type="date" name="tanggal_mulai" value="{{ $filters['tanggal_mulai'] }}" required></label>
        <label>Tanggal akhir<input type="date" name="tanggal_akhir" value="{{ $filters['tanggal_akhir'] }}" required></label>
        <label>Cari transaksi<input type="search" name="cari" value="{{ $filters['cari'] }}" maxlength="100" placeholder="NPM, pembayar, deskripsi, order ID, atau ID transaksi"></label>
        <button class="button button-primary" type="submit">Tampilkan</button>
    </form>

    <div class="table-wrap">
        <table class="midtrans-history-table">
            <thead>
                <tr><th>Waktu transaksi</th><th>Mahasiswa / pembayar</th><th>Jenis tagihan / deskripsi</th><th>Order ID</th><th>ID transaksi Midtrans</th><th>Total transaksi</th><th>Status pembayaran</th><th>Status Midtrans</th></tr>
            </thead>
            <tbody>
                @forelse ($transactions as $transaction)
                    <tr>
                        <td>{{ $transaction->created_at ?: '-' }}</td>
                        <td>
                            @if ($transaction->payment_type === 'manual')
                                <strong>{{ $transaction->payer_name ?: 'Pembayar umum' }}</strong><br>{{ $transaction->payer_email ?: '-' }}
                            @else
                                <strong>{{ $transaction->nim ?: $transaction->student_id }}</strong><br>{{ $transaction->nama_mahasiswa ?: 'Data mahasiswa tidak ditemukan' }}
                            @endif
                        </td>
                        <td>{{ $transaction->payment_type === 'manual' ? 'Billing manual: '.($transaction->manual_description ?: '-') : match ($transaction->payment_type) { 'ukt' => 'UKT', 'ipi' => 'IPI', default => strtoupper($transaction->payment_type) } }}</td>
                        <td>{{ $transaction->order_id }}</td>
                        <td>{{ $transaction->transaction_id ?: '-' }}</td>
                        <td>Rp {{ number_format((int) $transaction->gross_amount, 0, ',', '.') }}</td>
                        <td><span class="midtrans-history-status {{ (int) $transaction->is_paid === 1 ? 'is-paid' : 'is-unpaid' }}" data-payment-status="{{ (int) $transaction->is_paid === 1 ? 'paid' : 'unpaid' }}">{{ (int) $transaction->is_paid === 1 ? 'Lunas' : 'Belum lunas' }}</span></td>
                        <td>{{ ucfirst(str_replace('_', ' ', $transaction->transaction_status)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="midtrans-history-empty">Tidak ada transaksi Midtrans pada rentang tanggal ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($transactions->hasPages())
        <div class="midtrans-history-pagination">{{ $transactions->links('vendor.pagination.datatables') }}</div>
    @endif
@endsection
