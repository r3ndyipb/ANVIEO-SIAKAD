@extends('layouts.simak')

@section('title', 'Periksa Calon Peserta KKN | SIAKAD')
@section('topbar_title', 'Periksa Calon Peserta KKN')

@section('content')
    <div class="page-heading"><div><p class="eyebrow">Kuliah Kerja Nyata</p><h1>Periksa Calon Peserta</h1><p class="subtitle">Verifikasi informasi mahasiswa, hasil studi, dan transkrip.</p></div><a class="button" href="{{ route('kkn.validations.index', ['tahun' => $record->tahun]) }}">Kembali ke daftar</a></div>
    @if ($errors->any())<div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>@endif
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    <section class="kkn-student-summary"><dl><div><dt>NPM</dt><dd>{{ $record->nim ?: '-' }}</dd></div><div><dt>Nama</dt><dd>{{ $record->nama_mahasiswa ?: '-' }}</dd></div><div><dt>Program studi</dt><dd>{{ $record->nama_program_studi ?: '-' }}</dd></div><div><dt>Fakultas</dt><dd>{{ $record->nama_fakultas ?: '-' }}</dd></div><div><dt>Email</dt><dd>{{ $record->email ?: '-' }}</dd></div><div><dt>Telepon</dt><dd>{{ $record->handphone ?: '-' }}</dd></div><div><dt>Nama ibu</dt><dd>{{ $record->nama_ibu ?: '-' }}</dd></div><div><dt>Jenis kelamin</dt><dd>{{ $record->jenis_kelamin === 'L' ? 'Laki-laki' : ($record->jenis_kelamin === 'P' ? 'Perempuan' : '-') }}</dd></div><div class="wide"><dt>Alamat</dt><dd>{{ trim(($record->jalan ?: '').' '.($record->kelurahan ?: '')) ?: '-' }}</dd></div></dl></section>

    <section class="kkn-section"><h2>Hasil studi</h2><div class="kkn-report-links">
        @foreach ($calendars as $calendar)
            <a class="button button-small" href="{{ route('khs.index', ['search' => $record->nim, 'student' => $record->id_mahasiswa, 'calendar' => $calendar->id_kalender, 'kkn_year' => $record->tahun]) }}"><span data-lucide="file-text"></span> KHS · {{ $calendar->namatahun ?: $calendar->tahun }}</a>
        @endforeach
        <a class="button button-small" href="{{ route('transcripts.index', ['search' => $record->nim, 'student' => $record->id_mahasiswa, 'kkn_year' => $record->tahun]) }}"><span data-lucide="graduation-cap"></span> Transkrip nilai</a>
        @if ($calendars->isEmpty())<p class="kkn-muted">Belum ada KRS untuk ditampilkan sebagai KHS.</p>@endif
    </div></section>

    <section class="kkn-section"><h2>Hasil verifikasi</h2><p class="kkn-current-status">Status saat ini: <strong>{{ [0 => 'Menunggu verifikasi', 1 => 'Tidak lolos', 2 => 'Lolos'][$record->status] ?? 'Tidak diketahui' }}</strong></p>
        <form class="kkn-verification-form" method="POST" action="{{ route('kkn.validations.update', $record->id_peserta) }}">@csrf @method('PUT')
            <fieldset><legend>Keputusan</legend><label><input type="radio" name="status" value="2" @checked((int) old('status', $record->status) === 2) required> Lolos verifikasi</label><label><input type="radio" name="status" value="1" @checked((int) old('status', $record->status) === 1) required> Tidak lolos verifikasi</label></fieldset>
            <label class="kkn-comment"><span>Keterangan verifikasi</span><textarea name="keterangan" rows="4" maxlength="2000" required>{{ old('keterangan', $record->keterangan) }}</textarea></label>
            <button class="button button-primary" type="submit"><span data-lucide="save"></span> Simpan hasil verifikasi</button>
        </form>
    </section>
@endsection

@push('styles')
<style>
    .kkn-section, .kkn-student-summary { margin: 16px 0; padding: 18px; border: 1px solid #dce5e0; border-radius: 7px; background: #fff; }
    .kkn-section h2 { margin: 0 0 14px; color: #244a3d; font-size: 16px; }
    .kkn-student-summary dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 22px; margin: 0; }
    .kkn-student-summary dl > div { padding: 10px 0; border-bottom: 1px solid #edf1ee; }
    .kkn-student-summary .wide { grid-column: 1 / -1; }
    .kkn-student-summary dt, .kkn-comment span { color: #67766e; font-size: 10px; font-weight: 800; text-transform: uppercase; }
    .kkn-student-summary dd { margin: 4px 0 0; color: #273e33; font-size: 13px; overflow-wrap: anywhere; }
    .kkn-report-links { display: flex; flex-wrap: wrap; gap: 8px; }
    .kkn-verification-form { display: grid; gap: 15px; }
    .kkn-verification-form fieldset { display: flex; flex-wrap: wrap; gap: 16px; padding: 13px; border: 1px solid #dce5e0; border-radius: 6px; }
    .kkn-verification-form legend { padding: 0 5px; color: #52645b; font-size: 11px; font-weight: 800; }
    .kkn-comment { display: grid; gap: 7px; }
    .kkn-comment textarea { width: 100%; min-height: 100px; padding: 10px; border: 1px solid #cbd8d1; border-radius: 5px; resize: vertical; }
    .kkn-current-status, .kkn-muted { color: #65756c; font-size: 12px; }
    @media (max-width: 680px) { .kkn-student-summary dl { grid-template-columns: 1fr; } .kkn-student-summary .wide { grid-column: auto; } }
</style>
@endpush