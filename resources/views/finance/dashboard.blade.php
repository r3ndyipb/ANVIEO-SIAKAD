@extends('layouts.simak')

@section('title', $title.' | SIAKAD')
@section('topbar_title', $title)

@push('styles')
    <style>
        .finance-dashboard { display:grid; gap:22px; }
        .finance-dashboard-filter { display:flex; flex-wrap:wrap; align-items:end; gap:10px; }
        .finance-dashboard-filter label { display:grid; gap:6px; color:#52634f; font-size:11px; font-weight:700; }
        .finance-dashboard-filter select { min-height:38px; min-width:240px; padding:8px 10px; border:1px solid #d9e2d7; border-radius:5px; background:#fff; }
        .finance-dashboard-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px; }
        .finance-dashboard-card { padding:18px; border:1px solid #e2e9e0; border-radius:9px; background:#fff; box-shadow:0 5px 18px #284a3309; }
        .finance-dashboard-card h2 { margin:0; color:#63725e; font-size:12px; }
        .finance-dashboard-card strong { display:block; margin-top:12px; color:#284a33; font-size:23px; }
        .finance-dashboard-card p { margin:8px 0 0; color:#71806e; font-size:11px; }
        .finance-dashboard-section h2 { margin:0 0 12px; color:#284a33; font-size:17px; }
        .finance-dashboard-table { width:100%; border-collapse:collapse; background:#fff; font-size:11px; }
        .finance-dashboard-table th,.finance-dashboard-table td { padding:10px; border-bottom:1px solid #edf1eb; text-align:left; }
        .finance-dashboard-table th { background:#f7f9f5; color:#63725e; font-size:9px; text-transform:uppercase; }
        .finance-dashboard-welcome { margin-bottom:22px; padding:22px 26px; border:1px solid #dce8dc; border-radius:10px; background:linear-gradient(120deg,#f7fbf2,#fff9df); }
        .finance-dashboard-welcome p { margin:0; color:#597a37; font-size:10px; font-weight:800; text-transform:uppercase; }
        .finance-dashboard-welcome h2 { margin:8px 0 0; color:#214830; font:400 clamp(25px,3vw,34px)/1.15 Georgia,"Times New Roman",serif; }
        .finance-dashboard-welcome span { display:block; margin-top:7px; color:#596a52; font-size:12px; }
        @media (max-width:640px) { .finance-dashboard-table th,.finance-dashboard-table td { padding:8px; } }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Keuangan · {{ $scopeLabel }}</p>
            <h1>{{ $title }}</h1>
        </div>
    </div>
    @if (auth()->user()?->role === 'keuangan')
        <section class="finance-dashboard-welcome" aria-labelledby="finance-dashboard-welcome-title">
            <p>Dashboard Keuangan</p>
            <h2 id="finance-dashboard-welcome-title">Selamat datang, {{ auth()->user()->name ?: auth()->user()->login }}.</h2>
            <span>Ringkasan pembayaran dan layanan keuangan {{ $siteConfiguration['institution_name'] }}.</span>
        </section>
    @endif
    <div class="finance-dashboard">
        @if ($periods->isNotEmpty())
            <form class="finance-dashboard-filter" method="GET" action="{{ route('finance.dashboard') }}">
                <label>Periode uang kuliah
                    <select name="tahun" data-searchable-select="filter">
                        @foreach ($periods as $period)
                            <option value="{{ $period->tahun }}" @selected($selectedYear === (string) $period->tahun)>{{ $period->nama_periode }} ({{ $period->tahun }})</option>
                        @endforeach
                    </select>
                </label>
                <button class="button button-primary" type="submit">Tampilkan</button>
            </form>
        @endif
        <div class="finance-dashboard-grid">
            <article class="finance-dashboard-card">
                <h2>Tagihan Uang Kuliah</h2>
                <strong>{{ number_format($ukt['jumlah_tagihan'], 0, ',', '.') }}</strong>
                <p>{{ number_format($ukt['jumlah_lunas'], 0, ',', '.') }} lunas · {{ number_format($ukt['jumlah_belum_lunas'], 0, ',', '.') }} belum lunas</p>
            </article>
            <article class="finance-dashboard-card">
                <h2>Nominal uang kuliah lunas</h2>
                <strong>Rp {{ number_format($ukt['nominal_lunas'], 0, ',', '.') }}</strong>
                <p>{{ $selectedYear !== '' ? 'Periode '.$selectedYear : 'Pilih periode untuk melihat rincian uang kuliah' }}</p>
            </article>
            <article class="finance-dashboard-card">
                <h2>Nominal uang kuliah belum lunas</h2>
                <strong>Rp {{ number_format($ukt['nominal_belum_lunas'], 0, ',', '.') }}</strong>
                <p>{{ number_format($ukt['jumlah_belum_lunas'], 0, ',', '.') }} tagihan belum dibayar</p>
            </article>
            <article class="finance-dashboard-card">
                <h2>Tagihan Iuran Pengembangan Institusi</h2>
                <strong>{{ number_format($ipi['jumlah_tagihan'], 0, ',', '.') }}</strong>
                <p>{{ number_format($ipi['jumlah_lunas'], 0, ',', '.') }} lunas · {{ number_format($ipi['jumlah_belum_lunas'], 0, ',', '.') }} belum lunas</p>
            </article>
            <article class="finance-dashboard-card">
                <h2>Nominal Iuran Pengembangan Institusi lunas</h2>
                <strong>Rp {{ number_format($ipi['nominal_lunas'], 0, ',', '.') }}</strong>
                <p>Seluruh data Iuran Pengembangan Institusi dalam cakupan akun</p>
            </article>
            <article class="finance-dashboard-card">
                <h2>Nominal Iuran Pengembangan Institusi belum lunas</h2>
                <strong>Rp {{ number_format($ipi['nominal_belum_lunas'], 0, ',', '.') }}</strong>
                <p>{{ number_format($ipi['jumlah_belum_lunas'], 0, ',', '.') }} tagihan belum dibayar</p>
            </article>
        </div>
        @if ($facultySummary->isNotEmpty())
            <section class="finance-dashboard-section">
                <h2>Ringkasan per fakultas</h2>
                <div class="finance-table-wrap">
                    <table class="finance-dashboard-table">
                        <thead><tr><th>Fakultas</th><th>Jumlah tagihan</th><th>Uang kuliah lunas</th><th>Uang kuliah belum lunas</th><th>Iuran Pengembangan Institusi lunas</th><th>Iuran Pengembangan Institusi belum lunas</th></tr></thead>
                        <tbody>
                            @foreach ($facultySummary as $faculty)
                                <tr>
                                    <td>{{ $faculty['nama_fakultas'] }}</td>
                                    <td>{{ number_format($faculty['jumlah_tagihan'], 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($faculty['ukt_nominal_lunas'], 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($faculty['ukt_nominal_belum_lunas'], 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($faculty['ipi_nominal_lunas'], 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($faculty['ipi_nominal_belum_lunas'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
@endsection
