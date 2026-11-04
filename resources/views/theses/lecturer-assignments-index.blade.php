@extends('layouts.simak')

@section('title', 'Penugasan Dosen | SIAKAD')
@section('topbar_title', 'Penugasan Dosen')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">E-Skripsi</p>
            <h1>Penugasan Dosen Pembimbing dan Penguji</h1>
            <p class="subtitle">Kelola penugasan dosen dan revisi judul skripsi pada program studi yang menjadi cakupan akun.</p>
        </div>
        <a class="button" href="{{ route('theses.dashboard') }}">Dashboard E-Skripsi</a>
    </div>

    @if (session('status'))
        <div class="module-alert" role="status">{{ session('status') }}</div>
    @endif

    @if ($showProgramFilter)
        <form class="master-data-filters" method="GET" action="{{ route('theses.assignments.index') }}">
            <div>
                <label for="assignment-program">Program studi</label>
                <select id="assignment-program" name="program" data-searchable-select="filter">
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
        <div class="master-empty-state"><strong>Pilih program studi</strong>Pilih prodi untuk memuat daftar penugasan.</div>
    @elseif ($theses->isEmpty())
        <div class="master-empty-state">Belum ada data skripsi pada program studi ini.</div>
    @else
        <section class="thesis-section">
            <div class="table-wrap">
                <table data-datatable="thesis-list">
                    <thead><tr><th>Mahasiswa</th><th>Judul</th><th>Pembimbing</th><th>Penguji</th><th>Status</th><th class="no-export">Proses</th></tr></thead>
                    <tbody>@foreach ($theses as $thesis)
                        <tr>
                            <td>{{ $thesis->nim }}<small class="subtext">{{ $thesis->nama_mahasiswa }}</small></td>
                            <td>{{ $thesis->judul }}</td>
                            <td>{{ $thesis->pembimbing_names }}</td>
                            <td>{{ $thesis->penguji_names }}</td>
                            <td>{{ $thesis->status_label }}</td>
                            <td>
                                <a class="button button-small" href="{{ route('theses.assignments.show', ['skripsi' => $thesis->id, 'program' => $programId]) }}">
                                    {{ $canEdit ? 'Penugasan Dosen' : 'Lihat' }}
                                </a>
                            </td>
                        </tr>
                    @endforeach</tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
