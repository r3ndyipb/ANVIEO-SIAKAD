@extends('layouts.simak')

@section('title', 'Pendaftaran Wisuda | SIAKAD')
@section('topbar_title', 'Pendaftaran Wisuda')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Layanan Mahasiswa</p>
            <h1>Pendaftaran Wisuda</h1>
            <p class="subtitle">Lengkapi dokumen persyaratan untuk periode wisuda yang sedang aktif.</p>
        </div>
    </div>

    @if ($errors->any())<div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>@endif
    @if (session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif

    <section class="graduation-student-identity" aria-label="Data mahasiswa">
        <dl>
            <div><dt>NPM</dt><dd>{{ $student->nim ?: '-' }}</dd></div>
            <div><dt>Nama</dt><dd>{{ $student->nama_mahasiswa ?: '-' }}</dd></div>
            <div><dt>Program studi</dt><dd>{{ $student->nama_program_studi ?: '-' }}</dd></div>
            <div><dt>Fakultas</dt><dd>{{ $student->nama_fakultas ?: '-' }}</dd></div>
        </dl>
    </section>

    @if (! $period)
        <div class="kkn-empty-state"><strong>Periode wisuda belum tersedia</strong>Belum ada periode wisuda aktif saat ini.</div>
    @elseif (! $participant)
        <div class="alert alert-info" role="status">
            Data usulan peserta wisuda untuk periode {{ $period->nama_periode }} belum ditemukan.
            Jika Anda sudah yudisium dan belum bisa melengkapi dokumen wisuda, hubungi operator prodi (Tuprodi) untuk diusulkan terlebih dahulu.
        </div>
    @else
        <section class="graduation-student-period">
            <h2>{{ $period->nama_periode }}</h2>
            <p class="graduation-student-dates">Pendaftaran {{ $period->tanggal_mulai }} sampai {{ $period->tanggal_akhir }}.</p>
            @if (! $isOpen)
                <div class="module-alert" role="status"><p>Periode wisuda sedang ditutup. Pengunggahan dokumen hanya dapat dilakukan pada tanggal yang ditetapkan.</p></div>
            @endif
            @if ($verified)
                <div class="alert {{ $participant->lolos === '1' ? 'alert-success' : 'alert-danger' }}" role="status">
                    Berkas wisuda sudah diverifikasi dan
                    <strong>{{ $participant->lolos === '1' ? 'dinyatakan lolos.' : 'dinyatakan belum lolos.' }}</strong>
                </div>
                @if ($participant->komentar)<p class="graduation-student-comment">Catatan verifikasi: {{ $participant->komentar }}</p>@endif
            @elseif ($isOpen)
                <div class="module-alert" role="note"><p>Batas ukuran setiap berkas 1 MB. Foto hanya menerima JPG/PNG; dokumen lain harus PDF. Berkas dapat diganti sampai diverifikasi panitia.</p></div>
            @endif
            <div class="table-wrap">
                <table class="graduation-documents-table">
                    <thead><tr><th>No.</th><th>Persyaratan</th><th>Status</th><th>Dokumen</th></tr></thead>
                    <tbody>
                        @foreach ($documents as $key => $document)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <strong>{{ $document['label'] }}</strong>
                                    @if ($key === 'foto')<small>Pas foto berlatar merah · JPG atau PNG</small>
                                    @elseif ($key === 'skripsi')<small>Bukti penyerahan jurnal dan skripsi · PDF</small>
                                    @else<small>Surat persyaratan resmi · PDF</small>@endif
                                </td>
                                <td>
                                    @if ($document['available'])<span class="graduation-document-status is-uploaded">Telah diunggah</span>
                                    @elseif ($document['uploaded'])<span class="graduation-document-status is-legacy">Berkas lama tidak ditemukan</span>
                                    @else<span class="graduation-document-status">Belum diunggah</span>@endif
                                </td>
                                <td class="graduation-document-actions">
                                    @if ($document['downloadUrl'])
                                        <a class="button button-secondary" href="{{ $document['downloadUrl'] }}"><span data-lucide="download"></span> Unduh</a>
                                    @endif
                                    @if ($isOpen && ! $verified)
                                        <button class="button button-primary" type="button" data-dialog-open="graduation-document-upload-dialog" data-graduation-document="{{ $key }}" data-upload-url="{{ route('graduation-student.documents.upload', $key) }}" data-document-label="{{ $document['label'] }}" data-document-type="{{ $document['type'] }}" data-current-file="{{ $document['available'] ? 'true' : 'false' }}"><span data-lucide="{{ $document['available'] ? 'refresh-cw' : 'upload' }}"></span> {{ $document['available'] ? 'Ganti berkas' : 'Upload' }}</button>
                                    @elseif ($verified)
                                        <span class="graduation-student-verified-note">Upload ditutup setelah verifikasi.</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </section>
        @if ($isOpen && ! $verified)
            <dialog class="graduation-upload-dialog" id="graduation-document-upload-dialog" data-open-on-load="{{ $errors->has('document_file') ? 'true' : 'false' }}" aria-labelledby="graduation-document-upload-title">
                <header class="graduation-upload-head">
                    <div><span class="eyebrow">{{ $period->nama_periode }}</span><h2 id="graduation-document-upload-title">Upload dokumen wisuda</h2></div>
                    <button class="table-icon-button" type="button" data-dialog-close aria-label="Tutup dialog"><span data-lucide="x"></span></button>
                </header>
                @if ($errors->has('document_file'))<div class="calendar-modal-error" role="alert">{{ $errors->first('document_file') }}</div>@endif
                <form method="POST" action="{{ route('graduation-student.documents.upload', old('document_type', 'foto')) }}" enctype="multipart/form-data" class="graduation-upload-form" data-graduation-upload-form>
                    @csrf
                    <input type="hidden" name="document_type" value="{{ old('document_type', 'foto') }}">
                    <p class="graduation-upload-document-label" data-graduation-document-label>Pas foto berlatar merah</p>
                    <label class="graduation-upload-dropzone" data-graduation-dropzone for="graduation-document-file">
                        <input id="graduation-document-file" type="file" name="document_file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required aria-label="Pilih dokumen wisuda">
                        <span class="graduation-upload-icon" data-lucide="cloud-upload" aria-hidden="true"></span>
                        <strong>Seret file ke sini atau pilih dari perangkat</strong>
                        <span data-graduation-file-name>Belum ada file dipilih</span>
                        <small data-graduation-file-hint>JPG/PNG · Maksimal 1 MB</small>
                    </label>
                    <div class="graduation-skripsi-fields" data-graduation-skripsi-fields hidden>
                        <label>No. HP aktif<input type="tel" name="handphone" value="{{ old('handphone', $student->handphone) }}" autocomplete="tel" required></label>
                        <label>Judul skripsi<textarea name="judul_skripsi" rows="3" maxlength="2000" required>{{ old('judul_skripsi', $participant->judul_skripsi) }}</textarea></label>
                    </div>
                    <div class="graduation-upload-actions">
                        <button class="button button-secondary" type="button" data-dialog-close>Batal</button>
                        <button class="button button-primary" type="submit"><span data-lucide="upload"></span> Unggah dokumen</button>
                    </div>
                </form>
            </dialog>
        @endif
    @endif
@endsection

@push('styles')
<style>
    .graduation-student-identity, .graduation-student-period { margin: 16px 0; padding: 18px; border: 1px solid #dce5e0; border-radius: 6px; background: #fff; }
    .graduation-student-identity dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 22px; margin: 0; }
    .graduation-student-identity dl > div { padding: 9px 0; border-bottom: 1px solid #edf1ee; }
    .graduation-student-identity dt { color: #67766e; font-size: 10px; font-weight: 800; text-transform: uppercase; }
    .graduation-student-identity dd { margin: 4px 0 0; color: #273e33; font-size: 13px; }
    .graduation-student-period h2 { margin: 0 0 7px; color: #244a3d; font-size: 17px; }
    .graduation-student-dates, .graduation-student-comment { color: #65756c; font-size: 12px; }
    .graduation-documents-table small { display: block; margin-top: 4px; color: #718078; font-size: 11px; }
    .graduation-document-status { display: inline-flex; padding: 4px 8px; border-radius: 4px; background: #fff4d7; color: #725400; font-size: 11px; font-weight: 700; white-space: nowrap; }
    .graduation-document-status.is-uploaded { background: #e5f2e8; color: #27613a; }
    .graduation-document-status.is-legacy { background: #fce9e6; color: #963f32; }
    .graduation-document-actions { min-width: 240px; }
    .graduation-student-verified-note { color: #8a4135; font-size: 11px; }
    .graduation-upload-dialog { position: fixed; inset: 0; width: min(560px, calc(100vw - 28px)); max-width: none; max-height: min(90dvh, 760px); margin: auto; overflow-y: auto; padding: 22px; border: 1px solid #dce5e0; border-radius: 8px; background: #fff; box-shadow: 0 24px 70px rgba(15, 35, 28, .25); }
    .graduation-upload-dialog::backdrop { background: rgba(18, 38, 31, .5); backdrop-filter: blur(2px); }
    .graduation-upload-dialog[open] { display: block; }
    .graduation-upload-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
    .graduation-upload-head h2 { margin: 4px 0 0; color: #244a3d; font-size: 18px; }
    .graduation-upload-document-label { margin: 0 0 12px; color: #334b3f; font-size: 13px; font-weight: 800; }
    .graduation-upload-dropzone { position: relative; display: grid; min-height: 190px; align-content: center; justify-items: center; gap: 10px; padding: 24px; border: 2px dashed #b8cdb6; border-radius: 8px; background: #f7fbf6; color: #526b56; cursor: pointer; text-align: center; transition: border-color .18s ease, background .18s ease; }
    .graduation-upload-dropzone:hover, .graduation-upload-dropzone.is-dragover { border-color: #438052; background: #edf6eb; }
    .graduation-upload-dropzone:focus-within { outline: 3px solid rgba(67, 128, 82, .2); outline-offset: 3px; }
    .graduation-upload-dropzone input[type="file"] { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); clip-path: inset(50%); white-space: nowrap; }
    .graduation-upload-icon svg { width: 30px; height: 30px; color: #438052; }
    .graduation-upload-dropzone strong { color: #28543a; font-size: 14px; }
    .graduation-upload-dropzone span { max-width: 100%; overflow-wrap: anywhere; color: #718073; font-size: 12px; }
    .graduation-upload-dropzone small { color: #315e3c; font-size: 11px; font-weight: 700; }
    .graduation-skripsi-fields { display: grid; gap: 12px; margin-top: 14px; }
    .graduation-skripsi-fields[hidden] { display: none; }
    .graduation-skripsi-fields label { display: grid; gap: 5px; color: #52645b; font-size: 11px; font-weight: 700; }
    .graduation-skripsi-fields input, .graduation-skripsi-fields textarea { width: 100%; padding: 9px; border: 1px solid #cbd8d1; border-radius: 5px; }
    .graduation-upload-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 9px; margin-top: 16px; }
    @media (max-width: 680px) { .graduation-student-identity dl { grid-template-columns: 1fr; } .graduation-document-actions { min-width: 0; } .graduation-upload-dialog { padding: 16px; } .graduation-upload-actions > button { flex: 1 1 100%; } }
</style>
@endpush
