@extends('layouts.simak')

@section('title', 'Kartu Hasil Studi | SIAKAD')
@section('topbar_title', 'Kartu Hasil Studi')

@push('styles')
    <style>
        .khs-filters { display: grid; grid-template-columns: minmax(220px, 520px) max-content; align-items: end; gap: 12px; margin: 16px 0 20px; padding: 15px; border: 1px solid #e3e9e2; border-radius: 7px; background: #fbfdfb; }
        .khs-filters > button { width: auto; white-space: nowrap; }
        .khs-filters label { display: block; margin-bottom: 7px; color: #43543c; font-size: 11px; font-weight: 800; }
        .khs-profile { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin: 18px 0; padding: 15px; border: 1px solid #e3e9e2; border-radius: 7px; background: #fbfdfb; }
        .khs-profile strong { display: block; margin-bottom: 4px; color: #526546; font-size: 10px; text-transform: uppercase; }
        .khs-profile span { color: #243b2a; font-weight: 700; }
        .khs-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 16px 0; }
        .khs-survey { min-width: 210px; }
        .khs-survey summary { color: #356b45; cursor: pointer; font-weight: 700; }
        .khs-survey-form { margin-top: 12px; padding: 12px; border: 1px solid #dce5d8; border-radius: 6px; background: #fbfdfb; }
        .khs-question { padding: 10px 0; border-bottom: 1px solid #e5e9e2; }
        .khs-options { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 7px; }
        .khs-options label { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; }
        .khs-options input { width: 15px; height: 15px; }
        .khs-grade-hidden { color: #7b8076; font-style: italic; }
        .khs-results-pagination { margin-top: 14px; }
        @media (max-width: 820px) { .khs-filters { grid-template-columns: 1fr; } }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1>Kartu Hasil Studi (KHS)</h1>
            <div class="module-alert" role="note">
                <p class="subtitle">Lihat hasil studi, IPS, IPK, dan rekap SKS mahasiswa berdasarkan tahun akademik.</p>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($isStudent)
        <form class="khs-filters" method="GET" action="{{ route('khs.index') }}">
            <div>
                <label for="khs-calendar">Pilih tahun akademik</label>
                <select id="khs-calendar" name="calendar" data-searchable-select="filter" required>
                    <option value="">Pilih tahun akademik</option>
                    @foreach ($calendars as $calendar)
                        <option value="{{ $calendar->id_kalender }}" @selected((string) ($selectedCalendar?->id_kalender ?? '') === (string) $calendar->id_kalender)>
                            {{ $calendar->namatahun ?: $calendar->tahun }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button class="button button-primary" type="submit"><span data-lucide="search"></span> Tampilkan KHS</button>
        </form>
        @if ($calendars->isEmpty())
            <div class="master-empty-state"><strong>Riwayat KHS belum tersedia</strong>Belum ada KRS mahasiswa yang dapat ditampilkan.</div>
        @endif
    @else
        @if (in_array($user->role, ['admin', 'userprodi', 'usertu', 'wisuda'], true))
            <form class="khs-filters" method="GET" action="{{ route('khs.index') }}">
                <div>
                    <label for="khs-npm">NPM mahasiswa</label>
                    <input id="khs-npm" name="npm" type="search" value="{{ old('npm', $filters['npm'] ?? '') }}" placeholder="Masukkan NPM mahasiswa" required>
                </div>
                <button class="button button-primary" type="submit"><span data-lucide="search"></span> Tampilkan KHS</button>
            </form>
        @elseif (! $student)
            <form class="khs-filters" method="GET" action="{{ route('khs.index') }}">
                @if ($user->role === 'validasi')<input type="hidden" name="kkn_year" value="{{ $filters['kkn_year'] }}">@endif
                <div>
                    <label for="khs-search">NPM atau nama mahasiswa</label>
                    <input id="khs-search" name="search" type="search" value="{{ old('search', $filters['search'] ?? '') }}" placeholder="Masukkan NPM atau nama" required>
                </div>
                <button class="button button-primary" type="submit"><span data-lucide="search"></span> Cari</button>
            </form>
            @if (filled($filters['search'] ?? null) && $students->isNotEmpty())
                <div class="table-wrap">
                    <table data-simple-search-table>
                        <thead><tr><th>Mahasiswa</th><th>Program Studi</th><th>Fakultas</th><th class="no-export">Aksi</th></tr></thead>
                        <tbody>
                            @foreach ($students as $listedStudent)
                                <tr>
                                    <td>{{ $listedStudent->nama_mahasiswa }}<small class="subtitle" style="display:block">{{ $listedStudent->nim }}</small></td>
                                    <td>{{ $listedStudent->nama_program_studi ?: '-' }}</td>
                                    <td>{{ $listedStudent->nama_fakultas ?: '-' }}</td>
                                    <td><a class="table-icon-button" href="{{ route('khs.index', ['search' => $filters['search'], 'student' => $listedStudent->id_mahasiswa, 'kkn_year' => $filters['kkn_year'] ?? null]) }}" title="Lihat KHS {{ $listedStudent->nama_mahasiswa }}" aria-label="Lihat KHS {{ $listedStudent->nama_mahasiswa }}"><span data-lucide="eye"></span></a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="khs-results-pagination">{{ $students->links('vendor.pagination.datatables') }}</div>
            @elseif (filled($filters['search'] ?? null))
                <div class="master-empty-state"><strong>Mahasiswa tidak ditemukan</strong>Tidak ada mahasiswa yang cocok dengan pencarian dan cakupan akun ini.</div>
            @endif
        @endif
    @endif

    @if ($student)
        <section class="panel">
            @if (! $isStudent && $user->role !== 'admin')
                <div class="khs-toolbar">
                    <a class="button button-primary" href="{{ route('khs.index', in_array($user->role, ['admin', 'userprodi', 'usertu', 'wisuda'], true) && filled($filters['npm'] ?? null) ? ['npm' => $filters['npm']] : ['search' => $filters['search'] ?? '', 'kkn_year' => $filters['kkn_year'] ?? null]) }}"><span data-lucide="arrow-left"></span> Kembali ke pencarian</a>
                </div>
            @endif
            <div class="page-heading">
                <div>
                    <h2>{{ $student->nama_mahasiswa }}</h2>
                    <p class="subtitle">{{ $student->nim }} · {{ $student->nama_program_studi ?: '-' }}</p>
                </div>
            </div>
            @if (! $isStudent)
                @if ($calendars->isNotEmpty())
                    <form class="khs-filters" method="GET" action="{{ route('khs.index') }}">
                        @if (in_array($user->role, ['admin', 'userprodi', 'usertu', 'wisuda'], true) && filled($filters['npm'] ?? null))
                            <input type="hidden" name="npm" value="{{ $filters['npm'] }}">
                        @else
                            <input type="hidden" name="search" value="{{ $filters['search'] }}">
                        @endif
                        <input type="hidden" name="student" value="{{ $student->id_mahasiswa }}">
                        @if ($user->role === 'validasi')<input type="hidden" name="kkn_year" value="{{ $filters['kkn_year'] }}">@endif
                        <div>
                            <label for="khs-staff-calendar">Pilih tahun akademik</label>
                            <select id="khs-staff-calendar" name="calendar" data-searchable-select="filter" required>
                                <option value="">Pilih tahun akademik</option>
                                @foreach ($calendars as $calendar)
                                    <option value="{{ $calendar->id_kalender }}" @selected((string) ($selectedCalendar?->id_kalender ?? '') === (string) $calendar->id_kalender)>
                                        {{ $calendar->namatahun ?: $calendar->tahun }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button class="button button-primary" type="submit"><span data-lucide="search"></span> Tampilkan KHS</button>
                    </form>
                @else
                    <div class="master-empty-state"><strong>Riwayat KHS belum tersedia</strong>Mahasiswa belum memiliki KRS pada kalender akademik mana pun.</div>
                @endif
            @endif
            @if ($selectedCalendar)
                <div class="khs-profile">
                    <div><strong>Fakultas</strong><span>{{ $student->nama_fakultas ?: '-' }}</span></div>
                    <div><strong>Tahun Akademik</strong><span>{{ $selectedCalendar->namatahun ?: $selectedCalendar->tahun }}</span></div>
                    <div><strong>IPS</strong><span>{{ number_format($report['ips'], 2) }}</span></div>
                    <div><strong>IPK</strong><span>{{ number_format($report['ipk'], 2) }}</span></div>
                    <div><strong>SKS Semester Ini</strong><span>{{ number_format($report['sks_semester'], 0) }} SKS</span></div>
                    <div><strong>Total SKS Diprogram</strong><span>{{ number_format($report['sks_program'], 0) }} SKS</span></div>
                    <div><strong>Total SKS Lulus</strong><span>{{ number_format($report['sks_lulus'], 0) }} SKS</span></div>
                    <div><strong>Maksimum SKS Berikutnya</strong><span>{{ $report['maximum_credits'] }} SKS</span></div>
                    <div><strong>Penasehat Akademik</strong><span>{{ $report['pa_name'] ?: '-' }}</span></div>
                </div>
                <div class="khs-toolbar">
                    <a class="button button-primary" href="{{ route('khs.print', ['student' => $student->id_mahasiswa, 'calendar' => $selectedCalendar->id_kalender, 'kkn_year' => $filters['kkn_year'] ?? null]) }}" target="_blank" rel="noopener"><span data-lucide="printer"></span> Cetak KHS</a>
                    @if ($isStudent)
                        <span class="subtitle">Untuk melihat nilai mata kuliah, isi survey EDOM setiap mata kuliah terlebih dahulu.</span>
                    @endif
                </div>
                @if ($report['courses']->isNotEmpty())
                    <div class="table-wrap">
                        <table data-simple-search-table>
                            <thead>
                                <tr><th>No</th><th>Kode MK</th><th>Nama Mata Kuliah</th><th>Nilai Huruf</th><th>Bobot</th><th>SKS</th><th>Mutu</th>@if ($isStudent)<th>Survey EDOM</th>@endif</tr>
                            </thead>
                            <tbody>
                                @foreach ($report['courses'] as $course)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $course->kode_mata_kuliah ?: '-' }}</td>
                                        <td>{{ $course->nama_mata_kuliah ?: 'Mata kuliah tidak ditemukan' }}</td>
                                        @if ($course->show_grade)
                                            <td>{{ $course->nilai_huruf ?: '-' }}</td>
                                            <td>{{ number_format($course->nilai_bobot, 2) }}</td>
                                            <td>{{ number_format($course->sks_mata_kuliah, 2) }}</td>
                                            <td>{{ number_format($course->mutu, 2) }}</td>
                                        @else
                                            <td class="khs-grade-hidden">Tersembunyi</td><td class="khs-grade-hidden">Tersembunyi</td><td class="khs-grade-hidden">Tersembunyi</td><td class="khs-grade-hidden">Tersembunyi</td>
                                        @endif
                                        @if ($isStudent)
                                            <td>
                                                @if ($course->edom_complete)
                                                    <span class="status-badge success">Survey telah diisi</span>
                                                @else
                                                    <details class="khs-survey">
                                                        <summary>Isi survey EDOM</summary>
                                                        @if ($report['questions']->isNotEmpty())
                                                            <form class="khs-survey-form" method="POST" action="{{ route('khs.survey.save', $course->id_krs) }}">
                                                                @csrf
                                                                <input type="hidden" name="calendar" value="{{ $selectedCalendar->id_kalender }}">
                                                                @foreach ($report['questions'] as $question)
                                                                    <fieldset class="khs-question">
                                                                        <legend>{{ $question->pertanyaan }}</legend>
                                                                        <div class="khs-options">
                                                                            @foreach ([1 => 'Kurang', 2 => 'Cukup', 3 => 'Cukup baik', 4 => 'Baik', 5 => 'Sangat baik'] as $value => $label)
                                                                                <label><input type="radio" name="answers[{{ $question->id_survey }}]" value="{{ $value }}" required> {{ $label }}</label>
                                                                            @endforeach
                                                                        </div>
                                                                    </fieldset>
                                                                @endforeach
                                                                <button class="button button-primary" type="submit" style="margin-top:12px">Kirim Survey</button>
                                                            </form>
                                                        @else
                                                            <p class="subtitle">Instrumen survey EDOM belum tersedia.</p>
                                                        @endif
                                                    </details>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr><th colspan="{{ $isStudent ? 5 : 5 }}" style="text-align:right">Jumlah</th><th>{{ number_format($report['sks_semester'], 0) }} SKS</th><th>{{ number_format($report['quality_points'], 2) }}</th>@if ($isStudent)<th></th>@endif</tr>
                                <tr><th colspan="{{ $isStudent ? 7 : 6 }}" style="text-align:right">IPS Mahasiswa</th><th>{{ number_format($report['ips'], 2) }}</th>@if ($isStudent)<th></th>@endif</tr>
                                <tr><th colspan="{{ $isStudent ? 7 : 6 }}" style="text-align:right">IPK Mahasiswa</th><th>{{ number_format($report['ipk'], 2) }}</th>@if ($isStudent)<th></th>@endif</tr>
                                <tr><th colspan="{{ $isStudent ? 7 : 6 }}" style="text-align:right">Maksimum SKS Berikutnya</th><th>{{ $report['maximum_credits'] }} SKS</th>@if ($isStudent)<th></th>@endif</tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="master-empty-state"><strong>Belum ada mata kuliah pada tahun akademik ini</strong>Data KRS untuk kalender yang dipilih belum tersedia.</div>
                @endif
            @endif
        </section>
    @endif
@endsection
