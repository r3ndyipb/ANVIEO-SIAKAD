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

@push('styles')
<style>
    .graduation-review-identity, .graduation-review-section, .graduation-review-decision { margin: 16px 0; padding: 18px; border: 1px solid #dce5e0; border-radius: 6px; background: #fff; }
    .graduation-review-identity dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 22px; margin: 0; }
    .graduation-review-identity dl > div { padding: 9px 0; border-bottom: 1px solid #edf1ee; }
    .graduation-review-identity dt { color: #67766e; font-size: 10px; font-weight: 800; text-transform: uppercase; }
    .graduation-review-identity dd { margin: 4px 0 0; color: #273e33; font-size: 12px; overflow-wrap: anywhere; }
    .graduation-review-section-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .graduation-review-section h2, .graduation-review-decision h2 { margin: 0 0 12px; color: #244a3d; font-size: 16px; }
    .graduation-validation-status { display: inline-flex; padding: 4px 8px; border-radius: 4px; background: #fff4d7; color: #725400; font-size: 11px; font-weight: 700; white-space: nowrap; }
    .graduation-validation-status.is-pass { background: #e5f2e8; color: #27613a; }
    .graduation-validation-status.is-fail { background: #fce9e6; color: #963f32; }
    .graduation-validation-status.is-pending { background: #fff4d7; color: #725400; }
    .graduation-review-decision form { display: grid; justify-items: start; gap: 14px; }
    .graduation-review-decision fieldset { display: flex; flex-wrap: wrap; gap: 18px; padding: 0; border: 0; }
    .graduation-review-decision legend { margin-bottom: 8px; color: #52645b; font-size: 11px; font-weight: 800; }
    .graduation-review-decision fieldset label { display: inline-flex; align-items: center; gap: 7px; color: #334b3f; font-size: 12px; }
    .graduation-review-decision input[type="radio"] { appearance: auto; width: 13px; height: 13px; flex: 0 0 13px; margin: 0; padding: 0; accent-color: #26724b; }
    .graduation-review-comment { display: grid; width: min(100%, 720px); gap: 6px; color: #52645b; font-size: 11px; font-weight: 700; }
    .graduation-review-comment textarea { width: 100%; padding: 10px; border: 1px solid #cbd8d1; border-radius: 5px; }
    .graduation-review-muted { color: #718078; font-size: 11px; }
    .graduation-document-dialog { position: fixed; inset: 0; width: min(980px, calc(100vw - 32px)); max-width: none; height: min(86dvh, 800px); max-height: none; margin: auto; flex-direction: column; gap: 12px; padding: 16px; overflow: hidden; border: 1px solid #d5e2ee; border-radius: 8px; background: #fff; box-shadow: 0 24px 80px rgba(16, 41, 25, .28); }
    .graduation-document-dialog[open] { display: flex; }
    .graduation-document-dialog::backdrop { background: rgba(20, 43, 29, .62); backdrop-filter: blur(3px); }
    .graduation-document-dialog-head { display: flex; min-height: 38px; align-items: center; justify-content: space-between; gap: 14px; }
    .graduation-document-dialog-head h2 { margin: 0; font-size: 16px; }
    .graduation-document-dialog iframe { width: 100%; min-height: 0; flex: 1; border: 1px solid #dce5e0; border-radius: 4px; background: #f4f6f5; }
    @media (max-width: 640px) { .graduation-review-identity dl { grid-template-columns: 1fr; } .graduation-review-decision fieldset { display: grid; } }
</style>
@endpush
