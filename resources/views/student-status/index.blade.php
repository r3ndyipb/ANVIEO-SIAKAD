@extends('layouts.simak')

@section('title', 'Lulus, Cuti, DO | SIAKAD')
@section('topbar_title', 'Lulus, Cuti, DO')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1>Lulus, Cuti, DO</h1>
            <div class="module-alert" role="note">
                <p class="subtitle">Catat dan pantau status keluar mahasiswa pada periode akademik program studi.</p>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <form class="student-status-filters {{ $user->role === 'admin' ? 'with-program' : 'without-program' }}" method="GET" action="{{ route('student-status.index') }}">
        @if ($user->role === 'admin')
            <div>
                <label for="student-status-program">Program studi</label>
                <select id="student-status-program" name="prodi" data-searchable-select="filter" required onchange="this.form.elements.tahun.value = ''; this.form.submit()">
                    <option value="">Pilih program studi</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id_prodi }}" @selected((string) $selectedProgram === (string) $program->id_prodi)>
                            {{ $program->nama_program_studi }} {{ $program->nama_jenjang_pendidikan ? '('.$program->nama_jenjang_pendidikan.')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
        <div>
            <label for="student-status-year">Tahun akademik</label>
            <select id="student-status-year" name="tahun" data-searchable-select="filter" @required($selectedProgram !== '') @disabled($selectedProgram === '' || $calendars->isEmpty()) onchange="this.form.submit()">
                <option value="">Pilih tahun akademik</option>
                @foreach ($calendars as $calendar)
                    <option value="{{ $calendar->tahun }}" @selected((string) ($filters['tahun'] ?? '') === (string) $calendar->tahun)>
                        {{ $calendar->namatahun ?: $calendar->tahun }}
                    </option>
                @endforeach
            </select>
        </div>
        <button class="button button-primary" type="submit" @disabled($selectedProgram === '' || $calendars->isEmpty())><span data-lucide="search"></span> Tampilkan</button>
    </form>

    @if ($selectedProgram !== '' && $calendars->isEmpty())
        <div class="master-empty-state"><strong>Kalender akademik belum tersedia</strong>Program studi ini belum memiliki kalender akademik.</div>
    @endif

    @if ($selectedProgram !== '' && filled($filters['tahun'] ?? null))
        <div class="student-status-actions">
            <a class="button button-primary" href="{{ route('student-status.create', ['prodi' => $selectedProgram, 'tahun' => $filters['tahun']]) }}"><span data-lucide="plus"></span> Tambah Data</a>
            <a class="button button-secondary" href="{{ route('student-status.export.excel', ['prodi' => $selectedProgram, 'tahun' => $filters['tahun']]) }}" data-page-loader-skip><span data-lucide="sheet"></span> Unduh Excel</a>
        </div>
    @endif

    @if ($records instanceof \Illuminate\Pagination\LengthAwarePaginator)
        @if ($records->isNotEmpty())
            <div class="table-wrap">
                <table data-simple-search-table>
                    <thead>
                        <tr><th>No</th><th>NPM</th><th>Nama Mahasiswa</th><th>Status</th><th>Tanggal Keluar</th><th>IPK</th><th>No SK</th><th>No Ijazah</th><th class="no-export">Proses</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($records as $record)
                            <tr>
                                <td>{{ $records->firstItem() + $loop->index }}</td>
                                <td>{{ $record->nim ?: '-' }}</td>
                                <td>{{ $record->nama_mahasiswa ?: 'Mahasiswa tidak ditemukan' }}</td>
                                <td>{{ $record->status_label }}</td>
                                <td>{{ $record->tgl_keluar ?: '-' }}</td>
                                <td>{{ number_format((float) $record->ipk, 2) }}</td>
                                <td>{{ $record->no_sk ?: '-' }}</td>
                                <td>{{ $record->no_ijazah ?: '-' }}</td>
                                <td>
                                    <div class="student-status-actions">
                                        <a class="table-icon-button" href="{{ route('student-status.edit', $record->id_ipmhs) }}" title="Ubah data status" aria-label="Ubah data status"><span data-lucide="pencil"></span></a>
                                        <form method="POST" action="{{ route('student-status.destroy', $record->id_ipmhs) }}" data-student-status-confirm data-confirm-title="Hapus Riwayat Status" data-confirm-message="Hapus riwayat {{ $record->status_label }} untuk {{ $record->nama_mahasiswa }}? Status mahasiswa akan dikembalikan ke riwayat terakhir atau Aktif.">
                                            @csrf
                                            @method('DELETE')
                                            <button class="table-icon-button reset" type="submit" title="Hapus riwayat" aria-label="Hapus riwayat"><span data-lucide="trash-2"></span></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="student-status-pagination">{{ $records->links('vendor.pagination.datatables') }}</div>
        @else
            <div class="master-empty-state"><strong>Belum ada data</strong>Tidak ada status Lulus, Cuti, atau DO pada tahun akademik ini.</div>
        @endif
    @elseif ($selectedProgram !== '' && filled($filters['tahun'] ?? null))
        <div class="master-empty-state"><strong>Tahun akademik tidak ditemukan</strong>Pilih tahun akademik yang tersedia untuk program studi ini.</div>
    @endif

    @if ($records instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div class="calendar-modal-backdrop" data-student-status-modal hidden>
            <section class="calendar-modal" role="dialog" aria-modal="true" aria-labelledby="student-status-confirm-title" aria-describedby="student-status-confirm-message" tabindex="-1">
                <div class="calendar-modal-head">
                    <div>
                        <h2 id="student-status-confirm-title" data-student-status-confirm-title>Konfirmasi Hapus Data</h2>
                        <p class="calendar-modal-copy" id="student-status-confirm-message" data-student-status-confirm-message></p>
                    </div>
                    <button class="calendar-modal-close" type="button" data-student-status-confirm-close aria-label="Tutup dialog">×</button>
                </div>
                <div class="calendar-modal-actions">
                    <button class="button" type="button" data-student-status-confirm-cancel>Batal</button>
                    <button class="button button-danger" type="button" data-student-status-confirm-submit>Ya, hapus</button>
                </div>
            </section>
        </div>
    @endif
@endsection
