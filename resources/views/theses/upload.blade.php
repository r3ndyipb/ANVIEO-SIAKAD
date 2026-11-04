@extends('layouts.simak')

@section('title', 'Upload Data Skripsi | SIAKAD')
@section('topbar_title', 'Upload Data Skripsi')

@push('styles')
    <style>
        .thesis-upload-dropzone { position: relative; display: grid; min-height: 220px; align-content: center; justify-items: center; gap: 10px; padding: 24px; border: 2px dashed #b8cdb6; border-radius: 14px; background: #f7fbf6; color: #526b56; cursor: pointer; text-align: center; transition: border-color .18s ease, background .18s ease, transform .18s ease; }
        .thesis-upload-dropzone:hover, .thesis-upload-dropzone.is-dragover { transform: translateY(-1px); border-color: #438052; background: #edf6eb; }
        .thesis-upload-dropzone:focus-within { outline: 3px solid rgba(67, 128, 82, .2); outline-offset: 3px; }
        .thesis-upload-dropzone input[type="file"] { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); clip-path: inset(50%); white-space: nowrap; }
        .thesis-upload-dropzone svg { width: 30px; height: 30px; color: #438052; }
        .thesis-upload-dropzone strong { color: #28543a; font-size: 15px; }
        .thesis-upload-dropzone span { color: #718073; font-size: 12px; }
        .thesis-upload-dropzone small { max-width: 100%; overflow-wrap: anywhere; color: #315e3c; font-size: 11px; font-weight: 700; }
        .thesis-upload-error { margin: 10px 0 0; color: #a43d32; font-size: 12px; font-weight: 700; }
    </style>
@endpush

@section('content')
    <div class="page-heading"><div><p class="eyebrow">Administrasi E-Skripsi</p><h1>Upload Data Skripsi</h1><p class="subtitle">Import NPM, judul, penugasan dosen berdasarkan NIDN, dan tahap skripsi sesuai template.</p></div><a class="button" href="{{ route('theses.upload.template') }}" data-page-loader-skip><span data-lucide="file-down"></span> Unduh Template</a></div>
    @if ($errors->any())<div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>@endif
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    <section class="thesis-section">
        <form method="POST" action="{{ route('theses.upload.store') }}" enctype="multipart/form-data">
            @csrf
            <label class="thesis-upload-dropzone" for="thesis-file" data-thesis-upload-dropzone>
                <span data-lucide="upload" aria-hidden="true"></span>
                <strong>Seret file Excel ke area ini</strong>
                <span id="thesis-upload-help">atau klik untuk memilih file .xlsx (maks. 5 MB)</span>
                <small data-thesis-upload-filename>Belum ada file dipilih</small>
                <input id="thesis-file" type="file" name="file" accept=".xlsx" aria-describedby="thesis-upload-help thesis-upload-error" required>
            </label>
            <p class="thesis-upload-error" id="thesis-upload-error" data-thesis-upload-error role="alert" hidden></p>
            <div class="form-actions"><button class="button button-primary" type="submit">Import Data Skripsi</button></div>
        </form>
    </section>
    <script>
        (() => {
            const zone = document.querySelector('[data-thesis-upload-dropzone]');
            const input = zone?.querySelector('input[type="file"]');
            const filename = zone?.querySelector('[data-thesis-upload-filename]');
            const error = document.querySelector('[data-thesis-upload-error]');
            if (!zone || !input || !filename || !error) return;

            const setFile = (file, assignToInput = false) => {
                if (!file) return;
                if (!file.name.toLowerCase().endsWith('.xlsx') || file.size > 5 * 1024 * 1024) {
                    input.value = '';
                    filename.textContent = 'Belum ada file dipilih';
                    error.textContent = file.size > 5 * 1024 * 1024
                        ? 'Ukuran file tidak boleh melebihi 5 MB.'
                        : 'Pilih file Excel dengan format .xlsx.';
                    error.hidden = false;
                    return;
                }
                if (assignToInput) {
                    if (typeof DataTransfer === 'undefined') {
                        error.textContent = 'Browser ini tidak mendukung unggah dengan seret file. Klik area unggah untuk memilih file.';
                        error.hidden = false;
                        return;
                    }
                    const transfer = new DataTransfer();
                    transfer.items.add(file);
                    input.files = transfer.files;
                }
                filename.textContent = file.name;
                error.hidden = true;
                error.textContent = '';
            };

            input.addEventListener('change', () => setFile(input.files?.[0]));
            ['dragenter', 'dragover'].forEach((type) => zone.addEventListener(type, (event) => {
                event.preventDefault();
                zone.classList.add('is-dragover');
            }));
            ['dragleave', 'drop'].forEach((type) => zone.addEventListener(type, (event) => {
                event.preventDefault();
                zone.classList.remove('is-dragover');
            }));
            zone.addEventListener('drop', (event) => setFile(event.dataTransfer?.files?.[0], true));
            input.form?.addEventListener('submit', (event) => {
                if (input.files?.length) return;
                event.preventDefault();
                error.textContent = 'Pilih file Excel yang akan diunggah.';
                error.hidden = false;
            });
        })();
    </script>
@endsection