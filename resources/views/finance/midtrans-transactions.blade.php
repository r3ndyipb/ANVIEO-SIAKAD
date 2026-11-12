@extends('layouts.simak')

@section('title', $title.' | SIAKAD')
@section('topbar_title', $title)

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
