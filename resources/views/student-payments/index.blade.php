@extends('layouts.simak')

@section('title', $title.' | SIAKAD')
@section('topbar_title', $title)

@push('styles')
    <style>
        .student-payments-note { margin:0 0 18px; padding:12px 15px; border:1px solid #e1e8dc; border-radius:6px; background:#f8faf6; color:#61705d; font-size:11px; line-height:1.6; }
        .student-payments-table-wrap { overflow-x:auto; border:1px solid #e2e9e0; border-radius:7px; background:#fff; }
        .student-payments-table { width:100%; min-width:0; table-layout:fixed; border-collapse:collapse; font-size:11px; }
        .student-payments-table colgroup { display:none; }
        .student-payments-table th:nth-child(1), .student-payments-table td:nth-child(1) { width:15%; }
        .student-payments-table th:nth-child(2), .student-payments-table td:nth-child(2) { width:14%; }
        .student-payments-table th:nth-child(3), .student-payments-table td:nth-child(3) { width:11%; }
        .student-payments-table th:nth-child(4), .student-payments-table td:nth-child(4) { width:13%; }
        .student-payments-table th:nth-child(5), .student-payments-table td:nth-child(5) { width:14%; }
        .student-payments-table th:nth-child(6), .student-payments-table td:nth-child(6) { width:8%; }
        .student-payments-table th:nth-child(7), .student-payments-table td:nth-child(7) { width:11%; }
        .student-payments-table th:nth-child(8), .student-payments-table td:nth-child(8) { width:14%; }
        .student-payments-table th, .student-payments-table td { padding:10px 11px; border-bottom:1px solid #edf1eb; text-align:left; vertical-align:middle; overflow-wrap:anywhere; }
        .student-payments-table th { background:#f7f9f5; color:#63725e; font-size:9px; text-transform:uppercase; white-space:nowrap; }
        .student-payments-table tr:last-child td { border-bottom:0; }
        .student-payments-muted { color:#71806e; font-size:10px; line-height:1.5; }
        .student-payments-amounts { display:grid; gap:3px; }
        .student-payments-amounts span { display:block; }
        .student-payments-amounts .student-payments-muted { font-size:9px; }
        .student-payments-amounts strong { padding-top:3px; border-top:1px solid #edf1eb; }
        .student-payments-status { display:inline-flex; padding:4px 8px; border-radius:99px; background:#fff1e9; color:#8d4c27; font-size:10px; font-weight:800; }
        .student-payments-status.is-paid { background:#e8f4e9; color:#2a693c; }
        .student-payments-actions { display:flex; flex-direction:column; align-items:stretch; gap:6px; }
        .student-payments-actions form, .student-payments-actions .button { width:100%; }
        .student-payments-actions form .button { width:100%; white-space:normal; }
        .student-payments-pagination { padding:14px 0 0; }
        .student-payments-dialog { position:fixed; inset:auto; top:50%; left:50%; width:min(480px, calc(100vw - 28px)); margin:0; padding:0; transform:translate(-50%, -50%); border:1px solid #dce5d9; border-radius:9px; box-shadow:0 24px 80px #17251a40; }
        .student-payments-dialog::backdrop { background:#17251a80; }
        .student-payments-dialog header, .student-payments-dialog footer { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:14px 18px; border-bottom:1px solid #e8ede6; }
        .student-payments-dialog header h2 { margin:0; color:#284a33; font-size:17px; }
        .student-payments-dialog p { margin:0; padding:18px; color:#63725e; font-size:11px; line-height:1.6; }
        .student-payments-dialog footer { justify-content:flex-end; border-top:1px solid #e8ede6; border-bottom:0; }
        @media (max-width:1100px) {
            .student-payments-table-wrap { overflow:visible; border:0; background:transparent; }
            .student-payments-table, .student-payments-table tbody { display:block; width:100%; min-width:0; }
            .student-payments-table thead { display:none; }
            .student-payments-table tr { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); margin-bottom:12px; overflow:hidden; border:1px solid #e2e9e0; border-radius:7px; background:#fff; }
            .student-payments-table td { display:block; width:auto !important; min-width:0; padding:10px 12px; border-bottom:1px solid #edf1eb; }
            .student-payments-table td::before { display:block; margin-bottom:4px; color:#63725e; content:attr(data-label); font-size:9px; font-weight:800; text-transform:uppercase; }
            .student-payments-table td:last-child { grid-column:1 / -1; border-bottom:0; background:#f7f9f5; }
            .student-payments-table td[colspan] { grid-column:1 / -1; }
        }
        @media (max-width:640px) {
            .student-payments-table tr { grid-template-columns:minmax(0, 1fr); }
            .student-payments-table td:last-child { grid-column:auto; }
        }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div><p class="eyebrow">Layanan Mahasiswa</p><h1>{{ $title }}</h1></div>
    </div>

    @include('partials.flash-notice')
    <div class="student-payments-note" role="note">
        Tagihan yang ditampilkan hanya milik akun mahasiswa yang sedang masuk. Pembayaran online diproses melalui Midtrans; jika berlaku, biaya layanan dan total pembayaran ditampilkan terpisah dari tagihan UKT/IPI. Pembayaran beasiswa, bantuan pemerintah daerah, atau metode manual dicatat oleh petugas Keuangan.
    </div>

    <div class="student-payments-table-wrap">
        <table class="student-payments-table" data-simple-search-table>
            <thead>
                <tr>
                    <th>Mahasiswa / NPM</th>
                    <th>Jenis tagihan / Tahun Akademik</th>
                    <th>Periode bayar</th>
                    <th>Transaksi / metode pembayaran</th>
                    <th>Nominal</th>
                    <th>Status tagihan</th>
                    <th>Status Midtrans</th>
                    <th>Proses</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $payment)
                    <tr>
                        <td data-label="Mahasiswa / NPM">
                            <strong>{{ $payment->nama_mahasiswa }}</strong><br>
                            <span class="student-payments-muted">{{ $payment->nim }}</span><br>
                            <span class="student-payments-muted">{{ $payment->nama_program_studi ?: '-' }} · {{ $payment->nama_fakultas ?: '-' }}</span>
                        </td>
                        <td data-label="Jenis tagihan / Tahun Akademik">{{ $payment->nama_periode ?: ($payment->jenis === 'ipi' ? 'Iuran Pengembangan Institusi' : $payment->tahun) }}</td>
                        <td data-label="Periode bayar">
                            @if ($payment->tanggal_mulai && $payment->tanggal_akhir)
                                {{ $payment->tanggal_mulai }} s.d. {{ $payment->tanggal_akhir }}
                            @else
                                -
                            @endif
                        </td>
                        <td data-label="Transaksi / metode pembayaran">
                            @if ($payment->payment_method)
                                {{ $payment->payment_method }}
                                @if ($payment->payment_reference)
                                    <br><span class="student-payments-muted">{{ $payment->payment_reference }}</span>
                                @endif
                            @else
                                Belum checkout
                            @endif
                        </td>
                        <td data-label="Nominal">
                            <div class="student-payments-amounts">
                                <span>Tagihan: Rp {{ number_format((int) $payment->nominal, 0, ',', '.') }}</span>
                                @if ($payment->service_fee > 0)
                                    <span class="student-payments-muted">Biaya layanan Midtrans: Rp {{ number_format((int) $payment->service_fee, 0, ',', '.') }}</span>
                                @endif
                                @if ($payment->service_fee > 0)
                                    <strong>Total Midtrans: Rp {{ number_format((int) $payment->total_amount, 0, ',', '.') }}</strong>
                                @endif
                            </div>
                        </td>
                        <td data-label="Status tagihan"><span class="student-payments-status {{ (int) $payment->lunas === 1 ? 'is-paid' : '' }}">{{ (int) $payment->lunas === 1 ? 'Y' : 'T' }}</span></td>
                        <td data-label="Status Midtrans">{{ $payment->midtrans_status ?: '-' }}</td>
                        <td data-label="Proses">
                            <div class="student-payments-actions">
                            @if ((int) $payment->lunas === 1)
                                <a class="table-icon-button" href="{{ route('student-payments.billing', ['type' => $payment->jenis, 'payment' => $payment->payment_id]) }}" target="_blank" rel="noopener" title="Cetak billing" aria-label="Cetak billing {{ strtoupper($payment->jenis) }}" data-page-loader-skip><span data-lucide="printer"></span></a>
                            @else
                                @if ($payment->can_pay_midtrans)
                                    <form method="POST" action="{{ route('student-payments.midtrans', $payment->payment_id) }}">
                                        @csrf
                                        <input type="hidden" name="jenis" value="{{ $payment->jenis }}">
                                        <button class="button button-primary" type="submit" aria-label="Bayar tagihan {{ strtoupper($payment->jenis) }} dengan Midtrans">Bayar</button>
                                    </form>
                                @elseif ($payment->can_resume_midtrans)
                                    <a class="button button-secondary" href="{{ $payment->midtrans_redirect_url }}" target="_blank" rel="noopener">Lanjutkan pembayaran</a>
                                @endif
                                @if ($payment->can_check_midtrans)
                                    <form method="POST" action="{{ route('student-payments.midtrans.status', $payment->payment_id) }}">
                                        @csrf
                                        <input type="hidden" name="jenis" value="{{ $payment->jenis }}">
                                        <button class="button button-secondary" type="submit">Cek status</button>
                                    </form>
                                @elseif (! $payment->can_pay_midtrans && ! $payment->can_resume_midtrans)
                                    <span class="student-payments-muted">Pembayaran belum tersedia</span>
                                @endif
                            @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="student-payments-muted">Belum ada tagihan uang kuliah atau Iuran Pengembangan Institusi untuk akun Anda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="student-payments-pagination">{{ $records->links('vendor.pagination.datatables') }}</div>

    <dialog class="student-payments-dialog" data-finance-confirm-dialog aria-labelledby="finance-confirm-title" aria-describedby="finance-confirm-message">
        <header><h2 id="finance-confirm-title">Konfirmasi tindakan</h2><button class="table-icon-button" type="button" data-finance-confirm-cancel aria-label="Tutup dialog"><span data-lucide="x"></span></button></header>
        <p id="finance-confirm-message"></p>
        <footer><button class="button button-secondary" type="button" data-finance-confirm-cancel>Batal</button><button class="button button-primary" type="button" data-finance-confirm-submit>Ya, lanjutkan</button></footer>
    </dialog>
@endsection
