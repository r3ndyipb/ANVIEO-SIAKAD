@extends('layouts.simak')

@section('title', 'Kuliah Kerja Nyata (KKN) | SIAKAD')
@section('topbar_title', 'Kuliah Kerja Nyata (KKN)')

@section('content')
    <div class="page-heading"><div><p class="eyebrow">Layanan Mahasiswa</p><h1>Kuliah Kerja Nyata (KKN)</h1><p class="subtitle">Periksa persyaratan dan status pendaftaran KKN Anda.</p></div></div>
    @if ($errors->any())<div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>@endif
    @if (session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    <section class="kkn-student-summary"><dl><div><dt>NPM</dt><dd>{{ $student->nim }}</dd></div><div><dt>Nama</dt><dd>{{ $student->nama_mahasiswa }}</dd></div><div><dt>Program studi</dt><dd>{{ $student->nama_program_studi ?: '-' }}</dd></div><div><dt>Fakultas</dt><dd>{{ $student->nama_fakultas ?: '-' }}</dd></div></dl></section>
    @if (! $period)
        <div class="kkn-empty-state"><strong>Pendaftaran belum dibuka</strong>Belum ada periode KKN aktif saat ini.</div>
    @elseif (! $eligibility)
        <div class="kkn-empty-state"><strong>Periode tidak tersedia</strong>Periode KKN aktif belum memiliki kalender akademik aktif pada program studi Anda.</div>
    @else
        <section class="kkn-section"><h2>Periode {{ $period->nama_periode }}</h2><p class="kkn-period-dates">Pendaftaran dibuka {{ $period->tanggal_mulai }} sampai {{ $period->tanggal_akhir }}.</p>
            @if ($participant)
                <p class="kkn-current-status">Status pendaftaran: <strong>{{ [0 => 'Menunggu verifikasi', 1 => 'Tidak lolos verifikasi', 2 => 'Lolos verifikasi'][$participant->status] ?? 'Tidak diketahui' }}</strong></p>
                @if ($participant->keterangan)<p class="kkn-comment">Keterangan: {{ $participant->keterangan }}</p>@endif
                @if ($participant->nama_lokasi)<p class="kkn-comment">Lokasi penempatan: {{ $participant->nama_lokasi }}</p>@endif
            @else
                <h3>Persyaratan pendaftaran</h3>
                <ul class="kkn-requirements">
                    <li><span>Telah lulus minimal 70% dari kebulatan studi (100 SKS)</span><strong class="{{ $eligibility['eligible'] ? 'is-met' : 'is-missing' }}">{{ number_format($eligibility['passed_credits'], 0) }} SKS · {{ $eligibility['eligible'] ? 'Memenuhi syarat' : 'Belum memenuhi syarat' }}</strong></li>
                </ul>
                @if ($eligibility['eligible'] && $period->tanggal_mulai <= today()->toDateString() && $period->tanggal_akhir >= today()->toDateString())
                    <form method="POST" action="{{ route('kkn.student.register') }}" data-confirm-action>@csrf<button class="button button-primary" type="submit"><span data-lucide="send"></span> Daftar KKN</button></form>
                @elseif ($period->tanggal_mulai > today()->toDateString() || $period->tanggal_akhir < today()->toDateString())
                    <p class="kkn-current-status">Pendaftaran berada di luar tanggal yang ditetapkan.</p>
                @else
                    <p class="kkn-current-status">Pendaftaran dapat dilakukan setelah syarat SKS lulus terpenuhi.</p>
                @endif
            @endif
        </section>
    @endif
@endsection

