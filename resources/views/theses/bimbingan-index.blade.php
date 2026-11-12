@extends('layouts.simak')

@section('title', 'Bimbingan Skripsi | SIAKAD')
@section('topbar_title', 'Bimbingan Skripsi')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">E-Skripsi</p>
            <h1>Bimbingan Skripsi</h1>
            <p class="subtitle">Daftar usulan dan skripsi sesuai cakupan akun.</p>
        </div>
        @if (in_array($user->role, ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu', 'dosen'], true))
            <a class="button" href="{{ route('theses.dashboard') }}">Dashboard E-Skripsi</a>
        @endif
    </div>

    @if ($showProgramFilter)
        <form class="master-data-filters" method="GET" action="{{ route('theses.bimbingan.index') }}">
            <div><label for="bimbingan-program">Program studi</label>
                <select id="bimbingan-program" name="program" data-searchable-select="filter">
                    <option value="">Pilih program studi</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id_prodi }}" @selected($programId === (string) $program->id_prodi)>{{ $program->nama_program_studi }}{{ $program->nama_jenjang_pendidikan ? ' ('.$program->nama_jenjang_pendidikan.')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <button class="button button-primary" type="submit">Tampilkan</button>
        </form>
    @endif

    @if ($showProgramFilter && $programId === '')
        <div class="master-empty-state"><strong>Pilih program studi</strong>Pilih prodi untuk menampilkan daftar bimbingan.</div>
    @elseif ($user->role === 'dosen')
        <div data-thesis-role-tabs>
            <div class="thesis-role-tabs" role="tablist" aria-label="Kelompok mahasiswa bimbingan">
                <button class="thesis-role-tab" id="thesis-supervisor-tab" type="button" role="tab" aria-selected="true" aria-controls="thesis-supervisor-panel" data-thesis-role-tab="supervisor">Sebagai Pembimbing ({{ $supervisorTheses->count() }})</button>
                <button class="thesis-role-tab" id="thesis-examiner-tab" type="button" role="tab" aria-selected="false" aria-controls="thesis-examiner-panel" data-thesis-role-tab="examiner" tabindex="-1">Sebagai Penguji ({{ $examinerTheses->count() }})</button>
            </div>
            @foreach ([
                ['key' => 'supervisor', 'id' => 'thesis-supervisor-panel', 'title' => 'Mahasiswa sebagai Pembimbing', 'theses' => $supervisorTheses],
                ['key' => 'examiner', 'id' => 'thesis-examiner-panel', 'title' => 'Mahasiswa sebagai Penguji', 'theses' => $examinerTheses],
            ] as $group)
                <section class="thesis-section thesis-role-panel" id="{{ $group['id'] }}" role="tabpanel" aria-labelledby="thesis-{{ $group['key'] }}-tab" data-thesis-role-panel="{{ $group['key'] }}" @if ($group['key'] === 'examiner') hidden @endif>
                    <h2>{{ $group['title'] }}</h2>
                    @if ($group['theses']->isEmpty())
                        <div class="master-empty-state">Tidak ada mahasiswa pada kelompok ini.</div>
                    @else
                        <div class="table-wrap"><table data-datatable="thesis-list">
                            <thead><tr><th>No</th><th>Mahasiswa</th><th>Judul</th><th>Pembimbing</th><th>Penguji</th><th>Terakhir aktif</th><th>Status</th><th class="no-export">Aksi</th></tr></thead>
                            <tbody>@foreach ($group['theses'] as $thesis)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $thesis->nim }}<small class="subtext">{{ $thesis->nama_mahasiswa }}</small></td>
                                    <td>{{ $thesis->judul }}</td><td>{{ $thesis->pembimbing_names }}</td><td>{{ $thesis->penguji_names }}</td>
                                    <td>{{ $thesis->last_message_at ?: 'Belum ada' }}@if ($thesis->unread)<span class="thesis-unread-badge" style="background: #dc2626;">Pesan baru</span>@endif</td>
                                    <td>{{ $statusLabels[(int) $thesis->status] ?? 'Status tidak diketahui' }}</td>
                                    <td><a class="button button-small" href="{{ route('theses.bimbingan.show', $thesis->id) }}">Bimbingan</a></td>
                                </tr>
                            @endforeach</tbody>
                        </table></div>
                    @endif
                </section>
            @endforeach
        </div>
    @elseif ($theses->isEmpty())
        <div class="master-empty-state"><strong>Belum ada data</strong>Tidak ada skripsi yang tersedia pada cakupan akun ini.</div>
    @else
        <div class="table-wrap"><table data-datatable="thesis-list">
            <thead><tr><th>No</th><th>Mahasiswa</th><th>Judul</th><th>Pembimbing</th><th>Penguji</th><th>Terakhir aktif</th><th>Status</th><th class="no-export">Aksi</th></tr></thead>
            <tbody>@foreach ($theses as $thesis)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $thesis->nim }}<small class="subtext">{{ $thesis->nama_mahasiswa }}</small></td>
                    <td>{{ $thesis->judul }}</td>
                    <td>{{ $thesis->pembimbing_names }}</td><td>{{ $thesis->penguji_names }}</td>
                    <td>{{ $thesis->last_message_at ?: 'Belum ada' }}@if ($thesis->unread)<span class="thesis-unread-badge" style="background: #dc2626;">Pesan baru</span>@endif</td>
                    <td>{{ $statusLabels[(int) $thesis->status] ?? 'Status tidak diketahui' }}</td>
                    <td><a class="button button-small" href="{{ route('theses.bimbingan.show', $thesis->id) }}">Bimbingan</a></td>
                </tr>
            @endforeach</tbody>
        </table></div>
    @endif
@endsection