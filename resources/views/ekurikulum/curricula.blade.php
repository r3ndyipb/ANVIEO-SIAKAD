@extends('layouts.simak')

@section('title', 'Kurikulum OBE | SIAKAD')
@section('topbar_title', 'Kurikulum OBE')

@section('content')
    <div class="page-heading">
        <div><p class="eyebrow">E-Kurikulum OBE</p><h1>Daftar kurikulum</h1><div class="module-alert" role="note"><p class="subtitle">Kurikulum, pemetaan capaian pembelajaran, sebaran mata kuliah, dan RPS.</p></div></div>
        @if ($canEdit) <button class="button button-primary" type="button" data-dialog-open="create-curriculum-dialog"><span data-lucide="plus" aria-hidden="true"></span> Buat kurikulum</button> @endif
    </div>
    @if (session('status')) <div class="alert alert-success" role="status">{{ session('status') }}</div> @endif
    @if ($canEdit)
        <dialog class="ekur-dialog" id="create-curriculum-dialog" data-open-on-load="{{ request()->query('modal') === 'create' ? 'true' : 'false' }}">
            <div class="ekur-dialog-header">
                <div><p class="eyebrow">E-Kurikulum OBE</p><h2>Buat kurikulum</h2><p class="subtext">Visi dan misi aktif program studi akan disimpan sebagai snapshot.</p></div>
                <button class="ekur-dialog-close" type="button" data-dialog-close aria-label="Tutup dialog">Tutup</button>
            </div>
            <form method="post" action="{{ route('ekurikulum.curricula.store') }}" class="row g-3">
                @csrf
                <div class="col-md-8"><label class="form-label" for="curriculum-name">Nama kurikulum</label><input class="form-control" id="curriculum-name" name="nama_kur" maxlength="100" placeholder="Contoh: Kurikulum OBE Informatika" value="{{ request()->query('modal') === 'create' ? old('nama_kur') : '' }}" required></div>
                <div class="col-md-4"><label class="form-label" for="curriculum-year">Tahun berlaku</label><input class="form-control" id="curriculum-year" type="number" name="tahun" min="2000" max="2099" value="{{ request()->query('modal') === 'create' ? old('tahun', now()->year) : now()->year }}" required></div>
                <div class="col-12"><label class="form-label" for="curriculum-program-name">Program studi</label><input class="form-control" id="curriculum-program-name" value="{{ $programs->first()?->nama_program_studi ?? '' }}" readonly></div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button class="button button-secondary" type="button" data-dialog-close>Batal</button>
                    <button class="button button-primary" type="submit"><span data-lucide="save" aria-hidden="true"></span> Simpan draf</button>
                </div>
            </form>
        </dialog>
    @endif
    @if (count($programs) > 1)
        <form method="get" action="{{ route('ekurikulum.curricula.index') }}" class="table-wrap mb-4">
            <label class="form-label" for="curriculum-program">Filter program studi</label>
            <select class="form-control" id="curriculum-program" name="prodi" onchange="this.form.submit()">
                <option value="">Semua program studi dalam cakupan</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id_prodi }}" @selected((string) $program->id_prodi === $selectedProgram)>{{ $program->nama_program_studi }} ({{ $program->nama_jenjang_pendidikan }})</option>
                @endforeach
            </select>
        </form>
    @endif
    <div class="table-wrap">
        <table id="ekur-curricula-table" data-datatable="ekur-curricula">
            <thead><tr><th>Tahun</th><th>Nama kurikulum</th><th>Program studi</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse ($curricula as $curriculum)
                    <tr>
                        <td>{{ $curriculum->tahun }}</td>
                        <td>{{ $curriculum->nama_kur }}</td>
                        <td>{{ $curriculum->nama_program_studi }}<span class="subtext">{{ $curriculum->nama_jenjang_pendidikan }}</span></td>
                        <td>{{ $curriculum->status_kur === 'Final' ? 'Final / Disahkan' : 'Draf / Penyusunan' }}</td>
                        <td class="row-actions">
                            <a class="button button-secondary" href="{{ route('ekurikulum.curricula.show', $curriculum->id_kur) }}"><span data-lucide="list-checks" aria-hidden="true"></span> Kelola</a>
                            <a class="button button-secondary" href="{{ route('ekurikulum.rps.show', $curriculum->id_kur) }}"><span data-lucide="file-spreadsheet" aria-hidden="true"></span> Susun RPS</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">Belum ada kurikulum pada program studi ini.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-3">{{ $curricula->links() }}</div>
    </div>
@endsection
