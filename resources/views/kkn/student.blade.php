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

@push('styles')
<style>
    .kkn-section, .kkn-student-summary { margin: 16px 0; padding: 18px; border: 1px solid #dce5e0; border-radius: 7px; background: #fff; }
    .kkn-section h2 { margin: 0 0 8px; color: #244a3d; font-size: 16px; }
    .kkn-section h3 { margin: 18px 0 8px; color: #355244; font-size: 13px; }
    .kkn-student-summary dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 22px; margin: 0; }
    .kkn-student-summary dl > div { padding: 9px 0; border-bottom: 1px solid #edf1ee; }
    .kkn-student-summary dt { color: #67766e; font-size: 10px; font-weight: 800; text-transform: uppercase; }
    .kkn-student-summary dd { margin: 4px 0 0; color: #273e33; font-size: 13px; }
    .kkn-period-dates, .kkn-current-status, .kkn-comment { color: #65756c; font-size: 12px; }
    .kkn-requirements { display: grid; gap: 0; margin: 0 0 18px; padding: 0; list-style: none; }
    .kkn-requirements li { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; padding: 11px 0; border-bottom: 1px solid #edf1ee; color: #334b3f; font-size: 12px; }
    .kkn-requirements strong { font-size: 11px; }
    .kkn-requirements .is-met { color: #267245; }
    .kkn-requirements .is-missing { color: #a34332; }
    .kkn-empty-state { display: grid; gap: 5px; margin-top: 16px; padding: 24px; border: 1px dashed #cbd8d1; border-radius: 6px; color: #69786f; text-align: center; }
    @media (max-width: 680px) { .kkn-student-summary dl { grid-template-columns: 1fr; } }
</style>
@endpush