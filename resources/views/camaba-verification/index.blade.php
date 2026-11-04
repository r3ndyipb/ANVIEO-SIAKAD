@extends('layouts.simak')

@section('title', 'Verifikasi CAMABA | SIAKAD')
@section('topbar_title', 'Verifikasi CAMABA')

@push('styles')
    <style>
        .camaba-filters { display:grid; grid-template-columns:minmax(220px, 1fr) minmax(220px, 1fr) max-content; align-items:end; gap:12px; margin:16px 0 20px; }
        .camaba-filters label { display:grid; gap:6px; color:#52634f; font-size:11px; font-weight:700; }
        .camaba-filters select { min-height:38px; padding:8px 10px; border:1px solid #d9e2d7; border-radius:5px; background:#fff; color:#354a38; }
        .camaba-actions { display:flex; flex-wrap:wrap; gap:8px; margin:14px 0; }
        .camaba-table-wrap { overflow-x:auto; border:1px solid #e2e9e0; border-radius:7px; background:#fff; }
        .camaba-table { width:100%; min-width:760px; border-collapse:collapse; font-size:11px; }
        .camaba-table th, .camaba-table td { padding:10px 11px; border-bottom:1px solid #edf1eb; text-align:left; vertical-align:middle; }
        .camaba-table th { background:#f7f9f5; color:#63725e; font-size:9px; text-transform:uppercase; white-space:nowrap; }
        .camaba-status { display:inline-flex; padding:4px 8px; border-radius:5px; background:#fff0e7; color:#8c4d2b; font-size:10px; font-weight:700; }
        .camaba-status.is-verified { background:#e6f4e8; color:#28613a; }
        .camaba-dialog { position:fixed; inset:50% auto auto 50%; width:min(540px, calc(100vw - 28px)); max-height:calc(100vh - 32px); margin:0; padding:0; overflow:auto; transform:translate(-50%, -50%); border:1px solid #dce5d9; border-radius:9px; background:#fff; box-shadow:0 24px 80px #17251a40; }
        .camaba-dialog::backdrop { background:#17251a80; }
        .camaba-dialog-head, .camaba-dialog-actions { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:14px 18px; border-bottom:1px solid #e8ede6; }
        .camaba-dialog-head h2 { margin:0; color:#284a33; font-size:17px; }
        .camaba-dialog-body { display:grid; gap:14px; padding:18px; }
        .camaba-dialog-body label { display:grid; gap:6px; color:#52634f; font-size:11px; font-weight:700; }
        .camaba-dialog-body select { min-height:38px; padding:8px 10px; border:1px solid #d9e2d7; border-radius:5px; background:#fff; }
        .camaba-dropzone { position:relative; display:grid !important; justify-items:center; gap:7px !important; padding:22px 16px; border:1px dashed #a9bca5; border-radius:7px; background:#f7faf5; color:#355b3c; text-align:center; cursor:pointer; }
        .camaba-dropzone.is-dragover { outline:2px solid #54835a; outline-offset:2px; background:#eef6eb; }
        .camaba-dropzone input[type="file"] { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0, 0, 0, 0); white-space:nowrap; }
        .camaba-dialog-actions { justify-content:flex-end; border-top:1px solid #e8ede6; border-bottom:0; }
        @media(max-width:760px) { .camaba-filters { grid-template-columns:1fr; } }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Administrasi</p>
            <h1>Verifikasi CAMABA</h1>
            <div class="module-alert" role="note"><p class="subtitle">Unggah data calon mahasiswa melalui template Excel, periksa datanya, lalu kirim biodata dan riwayat pendidikan ke Feeder.</p></div>
        </div>
    </div>
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif

    <div class="camaba-actions">
        <button class="button button-secondary" type="button" data-dialog-open="camaba-upload-dialog"><span data-lucide="upload"></span> Upload Data MABA</button>
    </div>

    <form class="camaba-filters" method="GET" action="{{ route('camaba-verification.index') }}">
        <label for="camaba-period">Periode masuk
            <select id="camaba-period" name="period" data-searchable-select="filter" required>
                <option value="">Pilih periode</option>
                @foreach ($periods as $item)
                    <option value="{{ $item }}" @selected($period === (string) $item)>{{ $item }}</option>
                @endforeach
            </select>
        </label>
        <label for="camaba-program">Program studi
            <select id="camaba-program" name="program" data-searchable-select="filter">
                <option value="">Semua program studi</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id_prodi }}" @selected($selectedProgram === (string) $program->id_prodi)>{{ $program->nama_program_studi }}{{ $program->nama_jenjang_pendidikan ? ' ('.$program->nama_jenjang_pendidikan.')' : '' }}</option>
                @endforeach
            </select>
        </label>
        <button class="button button-secondary" type="submit"><span data-lucide="search"></span> Tampilkan</button>
    </form>

    @if ($period !== '')
        <div class="camaba-table-wrap">
            <table class="camaba-table" data-camaba-table data-ajax-url="{{ route('camaba-verification.data', ['period' => $period, 'program' => $selectedProgram]) }}">
                <thead><tr><th>NPM</th><th>Nama mahasiswa</th><th>Nomor peserta</th><th>Program studi</th><th>Status</th><th class="no-export">Aksi</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    @else
        <div class="master-empty-state"><strong>Periode CAMABA belum tersedia</strong>Belum ada tahun akademik pada kalender SIAKAD.</div>
    @endif

    <dialog class="camaba-dialog" id="camaba-upload-dialog" aria-labelledby="camaba-upload-title" @if ($errors->any() && old('program')) data-open-on-load="true" @endif>
        <header class="camaba-dialog-head"><h2 id="camaba-upload-title">Upload Data MABA</h2><button class="table-icon-button" type="button" data-dialog-close aria-label="Tutup dialog"><span data-lucide="x"></span></button></header>
        <form method="POST" action="{{ route('camaba-verification.upload') }}" enctype="multipart/form-data">
            @csrf
            <div class="camaba-dialog-body">
                <p class="subtitle">Pilih program studi tujuan, isi template Excel, lalu unggah berkasnya. Baris dengan nomor peserta dan jalur yang sama akan memperbarui data CAMABA yang sudah ada.</p>
                <label>Program studi<select name="program" data-searchable-select required><option value="">Pilih program studi</option>@foreach ($programs as $program)<option value="{{ $program->id_prodi }}" @selected(old('program') === (string) $program->id_prodi)>{{ $program->nama_program_studi }}{{ $program->nama_jenjang_pendidikan ? ' ('.$program->nama_jenjang_pendidikan.')' : '' }}</option>@endforeach</select></label>
                <a class="button button-secondary" href="{{ route('camaba-verification.template') }}" data-page-loader-skip><span data-lucide="download"></span> Unduh template Excel</a>
                <label class="camaba-dropzone" data-finance-dropzone>
                    <span data-lucide="upload" aria-hidden="true"></span>
                    <strong>Seret file Excel/CSV ke sini atau klik untuk memilih</strong>
                    <span class="finance-dropzone-name" data-finance-file-name>Belum ada file dipilih</span>
                    <input type="file" name="file" accept=".xlsx,.csv" required>
                </label>
                @error('file')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
            </div>
            <footer class="camaba-dialog-actions"><button class="button button-secondary" type="button" data-dialog-close>Batal</button><button class="button button-primary" type="submit"><span data-lucide="upload"></span> Unggah MABA</button></footer>
        </form>
    </dialog>
@endsection