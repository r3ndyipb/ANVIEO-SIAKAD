@extends('layouts.simak')

@section('title', $title.' | SIAKAD')
@section('topbar_title', $title)

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
