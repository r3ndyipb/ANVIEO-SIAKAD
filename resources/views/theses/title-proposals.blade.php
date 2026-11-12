@extends('layouts.simak')

@section('title', 'Usul Judul Skripsi | SIAKAD')
@section('topbar_title', 'Usul Judul Skripsi')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">E-Skripsi</p>
            <h1>Usul Judul Skripsi</h1>
            <p class="subtitle">Ajukan judul proposal dan pantau proses verifikasi program studi.</p>
        </div>
        <a class="button" href="{{ route('theses.bimbingan.index') }}">Bimbingan Skripsi</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="thesis-title-alert" role="alert">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="thesis-section">
        <h2>Identitas Mahasiswa</h2>
        <div class="table-wrap">
            <table>
                <tbody>
                    <tr><th>NPM</th><td>{{ $student->nim }}</td><th>Program Studi</th><td>{{ $student->nama_program_studi ?: '—' }}</td></tr>
                    <tr><th>Nama</th><td>{{ $student->nama_mahasiswa }}</td><th>Fakultas</th><td>{{ $student->nama_fakultas ?: '—' }}</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="thesis-section">
        @if ($canPropose)
            <div class="module-alert thesis-title-guidance" role="{{ blank($student->email) ? 'alert' : 'note' }}">
                <ol>
                    <li>
                        @if (blank($student->email))
                            Perbarui email aktif pada profil sebelum mengajukan judul. Notifikasi proses usulan dikirim melalui email tersebut.
                        @else
                            Email notifikasi: {{ $student->email }}
                        @endif
                    </li>
                    <li>Buka formulir untuk mengisi judul dan deskripsi proposal.</li>
                </ol>
            </div>
            <button class="button button-primary thesis-title-submit" type="button" data-dialog-open="thesis-title-dialog">Usul Judul Proposal</button>
            <dialog class="thesis-title-dialog" id="thesis-title-dialog" aria-labelledby="thesis-title-dialog-heading" data-thesis-title-dialog @if ($errors->any()) data-open-on-load="true" @endif>
                <div class="thesis-title-dialog-header">
                    <div><h2 id="thesis-title-dialog-heading">Usul Judul Proposal</h2><p>Lengkapi judul dan deskripsi penelitian yang diajukan.</p></div>
                    <button class="button button-small" type="button" data-dialog-close aria-label="Tutup formulir">Tutup</button>
                </div>
                <form class="thesis-title-form" method="POST" action="{{ route('theses.titles.store') }}">
                    @csrf
                    <div class="field-grid">
                        <div style="grid-column:1 / -1">
                            <label for="proposal-title">Judul proposal</label>
                            <input id="proposal-title" name="judul" value="{{ old('judul') }}" maxlength="2000" required>
                        </div>
                        <div style="grid-column:1 / -1">
                            <label for="proposal-description">Deskripsi proposal</label>
                            <textarea id="proposal-description" name="deskripsi" rows="6" maxlength="20000" required>{{ old('deskripsi') }}</textarea>
                        </div>
                    </div>
                    <div class="form-actions"><button class="button button-primary" type="submit">Ajukan Usulan</button></div>
                </form>
            </dialog>
        @else
            <div class="master-empty-state">
                <strong>Usulan belum dapat diajukan</strong>
                @if ($proposals->contains(fn (object $proposal): bool => (int) $proposal->status !== 2))
                    Masih ada usulan yang sedang diverifikasi atau sudah disetujui. Pengajuan baru dapat dilakukan setelah usulan ditolak/revisi.
                @else
                    Pastikan email aktif telah diperbarui pada profil mahasiswa.
                @endif
            </div>
        @endif
    </section>

    <section class="thesis-section">
        <div class="module-alert" role="note"><h2>Riwayat Usulan Judul</h2></div>
        @if ($proposals->isEmpty())
            <div class="master-empty-state">Belum ada riwayat usulan judul.</div>
        @else
            <div class="table-wrap">
                <table data-datatable="thesis-list">
                    <thead><tr><th>Judul</th><th>Deskripsi</th><th>Komentar Verifikasi</th><th>Waktu Ajuan</th><th>Status</th></tr></thead>
                    <tbody>@foreach ($proposals as $proposal)
                        <tr>
                            <td>{{ $proposal->judul }}</td>
                            <td>{{ $proposal->deskripsi }}</td>
                            <td>{{ $proposal->komentar ?: '—' }}</td>
                            <td>
                                {{ $proposal->elapsed_days }} hari
                                <small class="subtext">{{ (int) $proposal->status === 0 ? 'Berjalan' : 'Sampai verifikasi' }}</small>
                            </td>
                            <td>{{ $proposal->status_label }}</td>
                        </tr>
                    @endforeach</tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
