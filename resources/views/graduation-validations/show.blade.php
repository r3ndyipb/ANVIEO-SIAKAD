@extends('layouts.simak')

@section('title', 'Verifikasi Peserta Wisuda | SIAKAD')
@section('topbar_title', 'Verifikasi Peserta Wisuda')

@section('content')
    @php
        /** @var object $record */
    @endphp
    <div class="page-heading">
        <div><p class="eyebrow">{{ $period->nama_periode }}</p><h1>{{ $canVerify ? 'Verifikasi Peserta Wisuda' : 'Detail Peserta Wisuda' }}</h1><p class="subtitle">{{ $canVerify ? 'Periksa identitas, kelengkapan dokumen, lalu catat keputusan dan komentar.' : 'Informasi dan dokumen peserta yang telah lolos verifikasi.' }}</p></div>
        <a class="button button-secondary" href="{{ route('graduation-validations.index', ['periode' => $period->id_periode]) }}"><span data-lucide="arrow-left"></span> Kembali ke daftar</a>
    </div>
    @if ($errors->any())<div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>@endif
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif

    <section class="graduation-review-identity" aria-label="Identitas peserta">
        <dl>
            <div><dt>NPM</dt><dd>{{ $record->nim ?: '-' }}</dd></div>
            <div><dt>Nama</dt><dd>{{ $record->nama_mahasiswa ?: '-' }}</dd></div>
            <div><dt>Fakultas</dt><dd>{{ $record->nama_fakultas ?: '-' }}</dd></div>
            <div><dt>Program Studi</dt><dd>{{ $record->nama_program_studi ?: '-' }}</dd></div>
            <div><dt>Periode Masuk</dt><dd>{{ $record->id_periode_masuk ?: '-' }}</dd></div>
            <div><dt>Status Mahasiswa</dt><dd>{{ $record->nama_status_mahasiswa ?: $record->id_status_mahasiswa ?: '-' }}</dd></div>
            <div><dt>Email</dt><dd>{{ $record->email ?: '-' }}</dd></div>
            <div><dt>No. HP</dt><dd>{{ $record->handphone ?: '-' }}</dd></div>
            <div><dt>Nama Ibu</dt><dd>{{ $record->nama_ibu ?: '-' }}</dd></div>
            <div><dt>Jenis Kelamin</dt><dd>{{ ['L' => 'Laki-Laki', 'P' => 'Perempuan'][$record->jenis_kelamin] ?? '-' }}</dd></div>
            <div><dt>Alamat</dt><dd>{{ trim(($record->jalan ?: '').' '.($record->kelurahan ?: '')) ?: '-' }}</dd></div>
            <div><dt>IPK</dt><dd>{{ $ipk !== null ? number_format((float) $ipk, 2) : '-' }}</dd></div>
            <div><dt>Judul Skripsi</dt><dd>{{ $record->judul_skripsi ?: '-' }}</dd></div>
        </dl>
    </section>

    <section class="graduation-review-section">
        <div class="graduation-review-section-head"><h2>Persyaratan Wisuda</h2><span class="graduation-validation-status {{ $record->lolos === '1' ? 'is-pass' : ($record->lolos === '0' ? 'is-fail' : 'is-pending') }}">{{ $verificationLabel }}</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>No.</th><th>Persyaratan</th><th>Keterangan</th><th>Dokumen</th></tr></thead>
                <tbody>
                    @foreach ($documents as $document)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $document['label'] }}</td>
                            <td>@if ($document['available'])<span class="graduation-validation-status is-pass">Telah Upload</span>@else<span class="graduation-validation-status is-fail">Belum Upload</span>@endif</td>
                            <td>@if ($document['downloadUrl'])<a class="button button-secondary" href="{{ $document['downloadUrl'] }}" target="graduation-document-preview-frame" data-dialog-open="graduation-document-preview-dialog" data-page-loader-skip><span data-lucide="eye"></span> Lihat Dokumen</a>@else<span class="graduation-review-muted">Dokumen belum tersedia</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <dialog class="graduation-document-dialog" id="graduation-document-preview-dialog" aria-labelledby="graduation-document-preview-title">
        <header class="graduation-document-dialog-head">
            <h2 id="graduation-document-preview-title">Pratinjau Dokumen</h2>
            <button class="calendar-modal-close" type="button" data-dialog-close aria-label="Tutup pratinjau"><span data-lucide="x"></span></button>
        </header>
        <iframe name="graduation-document-preview-frame" title="Pratinjau dokumen peserta wisuda"></iframe>
    </dialog>

    @if ($canVerify)
    <section class="graduation-review-decision">
        <h2>Hasil Verifikasi</h2>
        <form method="POST" action="{{ route('graduation-validations.update', [$period->id_periode, $record->id_peserta]) }}">
            @csrf
            @method('PUT')
            <fieldset>
                <legend>Keputusan</legend>
                <label><input type="radio" name="lolos" value="1" @checked(old('lolos', $record->lolos) === '1') required> Lolos Verifikasi</label>
                <label><input type="radio" name="lolos" value="0" @checked(old('lolos', $record->lolos) === '0') required> Tidak Lolos Verifikasi</label>
            </fieldset>
            <label class="graduation-review-comment">Komentar wajib<textarea name="komentar" rows="4" maxlength="2000" required>{{ old('komentar', $record->komentar) }}</textarea></label>
            <p class="graduation-review-muted">Keputusan sebelumnya oleh: {{ $record->petugas ?: 'Belum ada' }}</p>
            <button class="button button-primary" type="submit"><span data-lucide="save"></span> Simpan Verifikasi</button>
        </form>
    </section>
    @endif
@endsection


