@extends('layouts.simak')

@section('title', ($record ? 'Ubah' : 'Tambah').' Status Mahasiswa | SIAKAD')
@section('topbar_title', 'Lulus, Cuti, DO')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1>{{ $record ? 'Ubah' : 'Tambah' }} Data Lulus, Cuti, DO</h1>
            <div class="module-alert" role="note"><p class="subtitle">Status dan periode akademik akan diperiksa kembali sebelum disimpan.</p></div>
        </div>
    </div>

    @if ($errors->any())
        <div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>
    @endif

    @if (! $record && $user->role === 'admin')
        <form class="student-status-form" method="GET" action="{{ route('student-status.create') }}">
            <div class="student-status-form-field">
                <label for="student-status-program">Program studi</label>
                <select id="student-status-program" name="prodi" data-searchable-select="filter" required onchange="this.form.elements.tahun && (this.form.elements.tahun.value = ''); this.form.submit()">
                    <option value="">Pilih program studi</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id_prodi }}" @selected((string) $selectedProgram === (string) $program->id_prodi)>
                            {{ $program->nama_program_studi }} {{ $program->nama_jenjang_pendidikan ? '('.$program->nama_jenjang_pendidikan.')' : '' }}
                        </option>
                    @endforeach
                </select>
                <button class="button button-primary" type="submit">Pilih Program Studi</button>
            </div>
        </form>
    @endif

    @if (! $record && $selectedProgram !== '')
        <form class="student-status-form" method="GET" action="{{ route('student-status.create') }}">
            <input type="hidden" name="prodi" value="{{ $selectedProgram }}">
            <div class="student-status-form-field">
                <label for="status-calendar-year">Pilih tahun akademik</label>
                <select id="status-calendar-year" name="tahun" data-searchable-select="filter" required @disabled($calendars->isEmpty()) onchange="this.form.submit()">
                    <option value="">Pilih tahun akademik</option>
                    @foreach ($calendars as $calendar)
                        <option value="{{ $calendar->tahun }}" @selected((string) request('tahun') === (string) $calendar->tahun)>
                            {{ $calendar->namatahun ?: $calendar->tahun }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
        @if ($calendars->isEmpty())
            <div class="master-empty-state"><strong>Kalender akademik belum tersedia</strong>Program studi ini belum memiliki kalender akademik.</div>
        @endif
    @endif

    @if ($record || $selectedCalendar)
        <section class="panel student-status-form">
            <form method="POST" action="{{ $record ? route('student-status.update', $record->id_ipmhs) : route('student-status.store') }}">
                @csrf
                @if ($record)
                    @method('PUT')
                    <input type="hidden" name="id_mahasiswa" value="{{ $record->id_mahasiswa }}">
                    <input type="hidden" name="id_prodi" value="{{ $record->id_prodi }}">
                @elseif ($user->role === 'admin')
                    <input type="hidden" name="id_prodi" value="{{ $selectedProgram }}">
                @endif
                @if (! $record)
                    <input type="hidden" name="id_kalender" value="{{ $selectedCalendar->id_kalender }}">
                    <input type="hidden" name="tahun" value="{{ $selectedCalendar->tahun }}">
                @endif
                <div class="student-status-form-grid">
                    @if ($record)
                        <div class="student-status-form-field student-status-form-field-wide">
                            <label>Mahasiswa</label>
                            <div class="form-control">{{ $studentNim }} · {{ $studentName }}</div>
                        </div>
                    @else
                        <div class="student-status-form-field student-status-form-field-wide">
                            <label for="status-student">Mahasiswa aktif *</label>
                            <select id="status-student" name="id_mahasiswa" required data-searchable-select="filter">
                                <option value="">Pilih mahasiswa</option>
                                @foreach ($students as $student)
                                    <option value="{{ $student->id_mahasiswa }}" @selected(old('id_mahasiswa') === $student->id_mahasiswa)>
                                        {{ $student->nim }} — {{ $student->nama_mahasiswa }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @if ($record)
                        <div class="student-status-form-field student-status-form-field-wide">
                            <label for="status-calendar">Periode keluar *</label>
                            <select id="status-calendar" name="id_kalender" required data-searchable-select="filter" data-student-status-calendar>
                                <option value="">Pilih tahun akademik</option>
                                @foreach ($calendars as $calendar)
                                    <option value="{{ $calendar->id_kalender }}" data-year="{{ $calendar->tahun }}" @selected((string) old('id_kalender', $record->id_kalender) === (string) $calendar->id_kalender)>
                                        {{ $calendar->namatahun ?: $calendar->tahun }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="tahun" value="{{ old('tahun', $record->tahun) }}" data-student-status-year>
                        </div>
                    @endif
                    <div class="student-status-form-field">
                        <label for="status-code">Jenis keluar *</label>
                        <select id="status-code" name="status_mahasiswa" required data-searchable-select="filter">
                            <option value="">Pilih jenis keluar</option>
                            @foreach (['C' => 'Cuti', '1' => 'Lulus', '2' => 'Mutasi', '3' => 'Dikeluarkan', '4' => 'Mengundurkan Diri', '5' => 'Putus Kuliah', '6' => 'Wafat', '7' => 'Hilang', 'Z' => 'Lainnya'] as $code => $label)
                                <option value="{{ $code }}" @selected(strtoupper((string) old('status_mahasiswa', $record?->status_mahasiswa)) === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="student-status-form-field">
                        <label for="status-date">Tanggal keluar *</label>
                        <input id="status-date" type="date" name="tgl_keluar" value="{{ old('tgl_keluar', $record?->tgl_keluar ?? now()->toDateString()) }}" required>
                    </div>
                    <div class="student-status-form-field">
                        <label for="status-ipk">IPK</label>
                        <input id="status-ipk" type="number" name="ipk" min="0" max="4" step="0.01" value="{{ old('ipk', $record?->ipk) }}">
                    </div>
                    <div class="student-status-form-field">
                        <label for="status-sk-number">Nomor SK</label>
                        <input id="status-sk-number" type="text" name="no_sk" maxlength="50" value="{{ old('no_sk', $record?->no_sk) }}">
                    </div>
                    <div class="student-status-form-field">
                        <label for="status-sk-date">Tanggal SK</label>
                        <input id="status-sk-date" type="date" name="tgl_sk" value="{{ old('tgl_sk', $record?->tgl_sk ?: now()->toDateString()) }}">
                    </div>
                    <div class="student-status-form-field">
                        <label for="status-diploma-number">Nomor ijazah</label>
                        <input id="status-diploma-number" type="text" name="no_ijazah" maxlength="50" value="{{ old('no_ijazah', $record?->no_ijazah) }}">
                    </div>
                    <div class="student-status-form-field student-status-form-field-wide">
                        <label for="status-note">Keterangan</label>
                        <textarea id="status-note" name="ket" rows="3" maxlength="2000">{{ old('ket', $record?->ket) }}</textarea>
                    </div>
                </div>
                <div class="student-status-form-actions">
                    <button class="button button-primary" type="submit"><span data-lucide="save"></span> Simpan</button>
                    <a class="button" href="{{ route('student-status.index', ['prodi' => $selectedProgram, 'tahun' => $record?->tahun]) }}">Daftar Data</a>
                </div>
            </form>
        </section>
    @elseif (! $record && $user->role === 'admin' && $selectedProgram === '')
        <div class="master-empty-state"><strong>Pilih program studi</strong>Pilih program studi untuk memuat kalender dan mahasiswa aktif.</div>
    @endif
@endsection
