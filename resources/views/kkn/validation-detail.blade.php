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

