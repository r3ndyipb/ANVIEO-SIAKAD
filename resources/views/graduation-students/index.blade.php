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


