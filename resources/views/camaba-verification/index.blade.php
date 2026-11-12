@extends('layouts.simak')

@section('title', 'Verifikasi CAMABA | SIAKAD')
@section('topbar_title', 'Verifikasi CAMABA')



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
        <div>
            <label for="camaba-period">Periode masuk</label>
            <select id="camaba-period" name="period" data-searchable-select="filter" required>
                <option value="">Pilih periode</option>
                @foreach ($periods as $item)
                    <option value="{{ $item }}" @selected($period === (string) $item)>{{ $item }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="camaba-program">Program studi</label>
            <select id="camaba-program" name="program" data-searchable-select="filter">
                <option value="">Semua program studi</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id_prodi }}" @selected($selectedProgram === (string) $program->id_prodi)>{{ $program->nama_program_studi }}{{ $program->nama_jenjang_pendidikan ? ' ('.$program->nama_jenjang_pendidikan.')' : '' }}</option>
                @endforeach
            </select>
        </div>
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