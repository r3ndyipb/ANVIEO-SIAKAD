@extends('layouts.simak')

@section('title', $title.' | SIAKAD')
@section('topbar_title', $title)

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
