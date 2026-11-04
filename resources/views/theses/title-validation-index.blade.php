@extends('layouts.simak')

@section('title', 'Validasi Usulan Judul | SIAKAD')
@section('topbar_title', 'Validasi Usulan Judul')

@push('styles')
    <style>
        tr.title-proposal-status-0 > td { background: #fff8d8 !important; }
        tr.title-proposal-status-1 > td { background: #e8f7ed !important; }
        tr.title-proposal-status-2 > td { background: #fff0ef !important; }
        .title-proposal-status-0 td { color: #664f0a; }
        .title-proposal-status-1 td { color: #205a35; }
        .title-proposal-status-2 td { color: #8b302b; }
        .title-proposal-status-label { display: inline-flex; align-items: center; padding: 5px 9px; border-radius: 999px; font-size: 10px; font-weight: 800; white-space: nowrap; }
        .title-proposal-status-0 .title-proposal-status-label { background: #f7e7a3; color: #664f0a; }
        .title-proposal-status-1 .title-proposal-status-label { background: #c8ecd3; color: #205a35; }
        .title-proposal-status-2 .title-proposal-status-label { background: #f5cfcc; color: #8b302b; }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">E-Skripsi</p>
            <h1>Validasi Usulan Judul</h1>
            <p class="subtitle">Periksa usulan mahasiswa dan catat hasil verifikasi sesuai cakupan program studi.</p>
        </div>
        <a class="button" href="{{ route('theses.dashboard') }}">Dashboard E-Skripsi</a>
    </div>

    @if ($showProgramFilter)
        <form class="master-data-filters" method="GET" action="{{ route('theses.titles.validation.index') }}">
            <div>
                <label for="title-validation-program">Program studi</label>
                <select id="title-validation-program" name="program" data-searchable-select="filter">
                    <option value="">Pilih program studi</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id_prodi }}" @selected($programId === (string) $program->id_prodi)>
                            {{ $program->nama_program_studi }}{{ $program->nama_jenjang_pendidikan ? ' ('.$program->nama_jenjang_pendidikan.')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button class="button button-primary" type="submit">Tampilkan</button>
        </form>
    @endif

    @if ($showProgramFilter && $programId === '')
        <div class="master-empty-state"><strong>Pilih program studi</strong>Pilih prodi untuk menampilkan usulan judul pada cakupan tersebut.</div>
    @elseif ($proposals->isEmpty())
        <div class="master-empty-state">Belum ada usulan judul pada program studi ini.</div>
    @else
        <section class="thesis-section">
            <h2>Daftar Usulan Judul</h2>
            <div class="table-wrap">
                <table data-datatable="thesis-list">
                    <thead><tr><th>Mahasiswa</th><th>Judul</th><th>Deskripsi</th><th>Komentar Verifikasi</th><th>Tanggal Usulan</th><th>Status</th><th class="no-export">Aksi</th></tr></thead>
                    <tbody>@foreach ($proposals as $proposal)
                        <tr class="title-proposal-status-{{ (int) $proposal->status }}">
                            <td>{{ $proposal->nim }}<small class="subtext">{{ $proposal->nama_mahasiswa }}</small></td>
                            <td>{{ $proposal->judul }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($proposal->deskripsi, 180) }}</td>
                            <td>{{ $proposal->komentar ?: '—' }}</td>
                            <td>{{ $proposal->tanggal_usul }}</td>
                            <td><span class="title-proposal-status-label">{{ $proposal->status_label }}</span></td>
                            <td>
                                <a class="button button-small" href="{{ route('theses.titles.validation.show', $proposal->id) }}">
                                    {{ $canVerify ? 'Verifikasi Data' : 'Lihat' }}
                                </a>
                            </td>
                        </tr>
                    @endforeach</tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
