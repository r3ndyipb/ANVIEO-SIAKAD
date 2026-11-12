@extends('layouts.simak')

@section('title', 'Aktivitas Mengajar | SIAKAD')
@section('topbar_title', 'Aktivitas Mengajar')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1>Aktivitas Mengajar</h1>
            <div class="module-alert" role="note"><p class="subtitle">Rencana dan realisasi tatap muka dosen pengampu pada kalender aktif.</p></div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>
    @endif

    @if (in_array($user->role, ['admin', 'pimuniv', 'pimfak'], true))
        <form class="teaching-filter" method="GET" action="{{ route('activity-teaching.index') }}">
            <div>
                <label for="teaching-program">Program studi</label>
                <select id="teaching-program" name="program" required data-searchable-select="filter" onchange="this.form.submit()">
                    <option value="">Pilih program studi</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id_prodi }}" @selected((string) $selectedProgram === (string) $program->id_prodi)>
                            {{ $program->nama_program_studi }} {{ $program->nama_jenjang_pendidikan ? '('.$program->nama_jenjang_pendidikan.')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if ($calendar)
                <span class="status-chip active">{{ $calendar->namatahun ?: $calendar->tahun }}</span>
            @endif
        </form>
    @elseif ($calendar)
        <p class="subtitle">{{ $calendar->namatahun ?: $calendar->tahun }}</p>
    @endif

    @if (in_array($user->role, ['admin', 'pimuniv', 'pimfak'], true) && $selectedProgram === '')
        <div class="master-empty-state"><strong>Pilih program studi</strong>Pilih program studi untuk menampilkan rencana dan realisasi tatap muka.</div>
    @elseif ($selectedProgram !== '' && ! $calendar)
        <div class="master-empty-state"><strong>Kalender aktif belum tersedia</strong>Program studi ini belum memiliki kalender aktif.</div>
    @elseif ($schedules->isEmpty())
        <div class="master-empty-state"><strong>Jadwal belum tersedia</strong>Tidak ada jadwal pada kalender aktif program studi ini.</div>
    @else
        <div class="table-wrap">
            <table class="teaching-table" data-simple-search-table>
                <thead><tr><th>No</th><th>Mata Kuliah</th><th>Kelas</th><th>Program Studi</th><th>Dosen Pengampu</th><th>Tatap Muka Direncanakan</th><th>Tatap Muka Realisasi</th></tr></thead>
                <tbody>
                    @php($rowNumber = 0)
                    @foreach ($schedules as $schedule)
                        @foreach ($schedule->teacher_activities as $teacherActivity)
                            @php($rowNumber++)
                            <tr class="teaching-teacher-row">
                                <td>{{ $rowNumber }}</td>
                                <td>{{ $schedule->nama_mata_kuliah }}</td>
                                <td>{{ $schedule->nama_kelas }}</td>
                                <td>{{ $schedule->nama_program_studi }} {{ $schedule->nama_jenjang_pendidikan ? '('.$schedule->nama_jenjang_pendidikan.')' : '' }}</td>
                                <td>{{ $teacherActivity->teacher_name }}</td>
                                @foreach (['rencana' => $teacherActivity->rencana, 'realisasi' => $teacherActivity->realisasi] as $field => $value)
                                    <td>
                                        @if ($canEdit)
                                            <form class="teaching-count-form" method="POST" action="{{ route('activity-teaching.update', $schedule->id_jadwal) }}" data-autosave-form>
                                                @csrf
                                                <input type="hidden" name="program" value="{{ $selectedProgram }}">
                                                <input type="hidden" name="teacher_id" value="{{ $teacherActivity->teacher_id }}">
                                                <input type="hidden" name="field" value="{{ $field }}">
                                                <select name="meeting_count" required data-searchable-select="filter" data-autosave-select onchange="this.form.requestSubmit()" aria-label="{{ ucfirst($field) }} tatap muka {{ $teacherActivity->teacher_name }}">
                                                    <option value="">Pilih</option>
                                                    @foreach (range(1, 16) as $meeting)
                                                        <option value="{{ $meeting }}" @selected((string) $value === (string) $meeting)>{{ $meeting }} Tatap Muka</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        @else
                                            {{ $value ?: '-' }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection