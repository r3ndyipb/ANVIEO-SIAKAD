@extends('layouts.simak')

@php
    $titles = [
        'materi' => 'Materi Kuliah',
        'pertemuan' => 'Pertemuan Kuliah',
        'tugas' => 'Tugas',
        'bank-soal' => 'Bank Soal',
        'diskusi' => 'Diskusi',
    ];
    $title = $titles[$module];
    $studentActivityFilterParameters = $isStudent && in_array($module, ['pertemuan', 'tugas', 'diskusi'], true)
        ? ['id_kalender' => $selectedCalendarId, 'id_matkul' => $selectedCourseId, 'id_jadwal' => $selectedClassId]
        : [];
    $activityFilterParameters = $activityFilterModule
        ? array_filter([
            'tahun' => $selectedAcademicYearCode,
            'id_matkul' => $selectedCourseId,
            'id_jadwal' => in_array($module, ['pertemuan', 'tugas', 'diskusi'], true) ? $selectedClassId : '',
            'jenis_soal' => $selectedQuestionType,
        ], fn ($value) => $value !== '')
        : [];
    $detailFilterParameters = array_merge($studentActivityFilterParameters, $activityFilterParameters);
@endphp

@section('title', $title.' | LMS Virtual Class')
@section('topbar_title', 'LMS Virtual Class')

@section('content')
    @include('lms._notifications')
    <div class="lms-page">
        <div class="page-heading">
            <div>
                <p class="eyebrow">LMS Virtual Class</p>
                <h1>{{ $title }}</h1>
                <p class="subtitle">
                    @if ($module === 'materi' && $materialViewer)
                        Jelajahi mata kuliah berdasarkan dosen yang mengajar dalam cakupan akses Anda.
                    @elseif ($isStudent)
                        Aktivitas kelas hanya tersedia bagi mahasiswa dengan KRS yang telah disahkan.
                    @elseif ($canManage)
                        Kelola {{ strtolower($title) }} pada mata kuliah yang Anda ampu.
                    @else
                        Tampilan baca-saja sesuai cakupan akses akun.
                    @endif
                </p>
            </div>
            @if ($canManage && in_array($module, ['materi', 'pertemuan', 'tugas', 'bank-soal', 'diskusi'], true))
                <div class="lms-page-actions">
                    @if ($module === 'bank-soal')
                        <button class="button button-secondary" type="button" data-lms-open="lms-question-upload-modal">Upload Soal</button>
                    @endif
                    <button class="button button-primary" type="button" data-lms-open="lms-create-modal">Tambah {{ $title }}</button>
                </div>
            @endif
        </div>

        @if ($isShortSemester)
            @include('components.short-semester-feeder-alert')
        @endif

        @if ($activityFilterModule && ! $isStudent)
            <form method="GET" action="{{ match ($module) {'pertemuan' => route('lms.meetings.index'), 'tugas' => route('lms.tasks.index'), 'bank-soal' => route('lms.questions.index'), 'diskusi' => route('lms.discussions.index')} }}" class="lms-filter-bar" data-lms-activity-filters>
                <div class="lms-filter-field">
                    <label for="lms-academic-year">Tahun akademik</label>
                    <select id="lms-academic-year" name="tahun" data-searchable-select required>
                        @foreach ($activityAcademicYears as $academicYear)
                            <option value="{{ $academicYear['year'] }}" @selected($selectedAcademicYearCode === $academicYear['year'])>{{ $academicYear['label'] }}{{ $academicYear['active'] ? ' · Aktif' : ' · Tidak aktif · Baca-saja' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lms-filter-field">
                    <label for="lms-activity-course">Mata kuliah</label>
                    <select id="lms-activity-course" name="id_matkul" data-searchable-select @disabled($activityCourses->isEmpty())>
                        <option value="">Semua mata kuliah</option>
                        @foreach ($activityCourses as $course)
                            <option value="{{ $course->id_matkul }}" @selected((string) $selectedCourseId === (string) $course->id_matkul)>{{ $course->kode_mata_kuliah }} — {{ $course->nama_mata_kuliah }}</option>
                        @endforeach
                    </select>
                </div>
                @if (in_array($module, ['pertemuan', 'tugas', 'diskusi'], true))
                    <div class="lms-filter-field">
                        <label for="lms-activity-class">Kelas</label>
                        <select id="lms-activity-class" name="id_jadwal" data-searchable-select @disabled($activityClasses->isEmpty())>
                            <option value="">Semua kelas</option>
                            @foreach ($activityClasses as $class)
                                <option value="{{ $class->id_jadwal }}" @selected((string) $selectedClassId === (string) $class->id_jadwal)>{{ $class->kode_mata_kuliah }} — {{ $class->nama_mata_kuliah }}@if (filled(data_get($class, 'nama_program_studi'))) · {{ data_get($class, 'nama_program_studi') }}@endif (Kelas {{ $class->nama_kelas }})</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if ($module === 'bank-soal')
                    <div class="lms-filter-field">
                        <label for="question-bank-type">Jenis soal</label>
                        <select id="question-bank-type" name="jenis_soal" data-searchable-select>
                            <option value="">Semua jenis soal</option>
                            <option value="pilihan_ganda" @selected($selectedQuestionType === 'pilihan_ganda')>Pilihan ganda</option>
                            <option value="essay" @selected($selectedQuestionType === 'essay')>Essay</option>
                        </select>
                    </div>
                @endif
                <button class="button button-secondary" type="submit">Terapkan filter</button>
            </form>
            <script>
                (() => {
                    const form = document.querySelector('[data-lms-activity-filters]');
                    const year = form?.querySelector('[name="tahun"]');
                    const course = form?.querySelector('[name="id_matkul"]');
                    const classSelect = form?.querySelector('[name="id_jadwal"]');
                    if (!form || !year || !course) return;
                    year.addEventListener('change', () => {
                        if (course.tomselect) course.tomselect.clear(true);
                        else course.value = '';
                        if (classSelect?.tomselect) classSelect.tomselect.clear(true);
                        else if (classSelect) classSelect.value = '';
                        form.requestSubmit();
                    });
                    course.addEventListener('change', () => {
                        if (classSelect?.tomselect) classSelect.tomselect.clear(true);
                        else if (classSelect) classSelect.value = '';
                        form.requestSubmit();
                    });
                    classSelect?.addEventListener('change', () => form.requestSubmit());
                    form.querySelector('[name="jenis_soal"]')?.addEventListener('change', () => form.requestSubmit());
                })();
            </script>
        @endif

        @if ($activityFilterModule && $activityReadOnly)
            <div class="student-activity-readonly" role="status"><span data-lucide="eye" aria-hidden="true"></span><span><strong>Tampilan baca-saja</strong><small>Kalender akademik atau SP ini tidak aktif. Data LMS hanya dapat dilihat, tidak dapat diubah.</small></span></div>
        @endif

        @if ($isStudent && in_array($module, ['pertemuan', 'tugas', 'diskusi'], true))
            <form method="GET" action="{{ match ($module) {'pertemuan' => route('lms.meetings.index'), 'tugas' => route('lms.tasks.index'), 'diskusi' => route('lms.discussions.index')} }}" class="lms-filter-bar" data-student-activity-filters>
                <div class="lms-filter-field">
                    <label for="student-academic-calendar">Tahun akademik</label>
                    <select id="student-academic-calendar" name="id_kalender" data-searchable-select required>
                        <option value="">Pilih tahun akademik</option>
                        @foreach ($studentAcademicCalendars as $calendar)
                            <option value="{{ $calendar->id_kalender }}" @selected((string) $selectedCalendarId === (string) $calendar->id_kalender)>{{ $calendar->namatahun ?: 'Tahun akademik tidak diketahui' }}{{ $calendar->writable ? ' · Aktif' : ' · Tidak aktif · Baca-saja' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lms-filter-field">
                    <label for="student-activity-course">Mata kuliah</label>
                    <select id="student-activity-course" name="id_matkul" data-searchable-select required @disabled($selectedCalendarId === '' || $studentCourses->isEmpty())>
                        <option value="">Pilih mata kuliah</option>
                        @foreach ($studentCourses as $course)
                            <option value="{{ $course->id_matkul }}" @selected((string) $selectedCourseId === (string) $course->id_matkul)>{{ $course->kode_mata_kuliah }} — {{ $course->nama_mata_kuliah }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lms-filter-field">
                    <label for="student-activity-class">Kelas</label>
                    <select id="student-activity-class" name="id_jadwal" data-searchable-select @disabled($selectedCalendarId === '' || $selectedCourseId === '' || $activityClasses->isEmpty())>
                        <option value="">Semua kelas yang diikuti</option>
                        @foreach ($activityClasses as $class)
                            <option value="{{ $class->id_jadwal }}" @selected((string) $selectedClassId === (string) $class->id_jadwal)>Kelas {{ $class->nama_kelas }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
            <script>
                (() => {
                    const form = document.querySelector('[data-student-activity-filters]');
                    const calendar = form?.querySelector('[name="id_kalender"]');
                    const course = form?.querySelector('[name="id_matkul"]');
                    const classSelect = form?.querySelector('[name="id_jadwal"]');
                    if (!form || !calendar || !course || !classSelect) return;
                    calendar.addEventListener('change', () => {
                        if (course.tomselect) course.tomselect.clear(true);
                        else course.value = '';
                        if (classSelect.tomselect) classSelect.tomselect.clear(true);
                        else classSelect.value = '';
                        course.required = false;
                        form.requestSubmit();
                    });
                    course.addEventListener('change', () => {
                        if (classSelect.tomselect) classSelect.tomselect.clear(true);
                        else classSelect.value = '';
                        form.requestSubmit();
                    });
                    classSelect.addEventListener('change', () => form.requestSubmit());
                })();
            </script>
            @if ($selectedAcademicCalendar && ! $studentActivityWritable)
                <div class="student-activity-readonly" role="status"><span data-lucide="eye" aria-hidden="true"></span><span><strong>Tampilan baca-saja</strong><small>Kalender akademik atau SP ini sudah tidak aktif. Anda dapat melihat aktivitas, tetapi tidak dapat mengubah atau mengirim data.</small></span></div>
            @endif
        @endif

        @if ($module === 'materi' && $materialViewer)
            @if ($materialProgramFilterRequired)
                <form method="GET" action="{{ route('lms.materials.index') }}" class="lms-material-program-filter">
                    <label for="material-program-select">Program studi</label>
                    <select id="material-program-select" name="id_prodi" data-searchable-select required>
                        <option value="">Pilih program studi untuk menampilkan dosen</option>
                        @foreach ($materialPrograms as $program)
                            <option value="{{ $program->id_prodi }}" @selected((string) $selectedProgramId === (string) $program->id_prodi)>{{ $program->nama_program_studi }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="button button-primary"><span data-lucide="users-round" aria-hidden="true"></span>Tampilkan dosen</button>
                </form>
            @endif
            @if ($materialProgramFilterRequired && $selectedProgramId === '')
                <div class="lms-material-empty"><span data-lucide="school" aria-hidden="true"></span><strong>Pilih program studi terlebih dahulu</strong><p>Daftar dosen dan mata kuliah akan dimuat setelah Anda memilih program studi.</p></div>
            @elseif ($selectedLecturerId !== '' && $selectedCourseId !== '')
                <section class="lms-material-library">
                    <div class="lms-material-library-heading">
                        <a class="button button-secondary" href="{{ route('lms.materials.index', $materialProgramFilterRequired ? ['id_prodi' => $selectedProgramId] : []) }}"><span data-lucide="arrow-left" aria-hidden="true"></span>Kembali ke dosen</a>
                        <div>
                            <p class="lms-material-library-kicker">{{ $selectedMaterialCourse->kode_mata_kuliah }} · {{ $selectedMaterialCourse->nama_program_studi ?: 'Program studi' }}</p>
                            <h2>{{ $selectedMaterialCourse->nama_mata_kuliah }}</h2>
                            @php
                                $selectedLecturer = $materialLibrary->firstWhere('id_dosen', $selectedLecturerId);
                            @endphp
                            <p>{{ \App\Support\LecturerName::format($selectedLecturer->nama_dosen, $selectedLecturer->gelar_depan, $selectedLecturer->gelar_belakang) }} · {{ $records->count() }} materi tersedia</p>
                        </div>
                    </div>
                    @if ($records->isEmpty())
                        <div class="lms-material-empty"><span data-lucide="folder-open" aria-hidden="true"></span><strong>Belum ada materi tersedia</strong><p>Dosen belum mengunggah materi untuk mata kuliah ini.</p></div>
                    @else
                        <div class="lms-material-resource-grid">
                            @foreach ($records as $item)
                                <article class="lms-material-resource-card">
                                    <div class="lms-material-resource-icon"><span data-lucide="file-text" aria-hidden="true"></span></div>
                                    <div class="lms-material-resource-content">
                                        <span class="lms-material-resource-label">Materi kuliah</span>
                                        <h3>{{ $item->judul }}</h3>
                                        <p>{{ $item->nama_berkas ?: 'Dokumen materi' }} · {{ \App\Support\LecturerName::format($item->nama_dosen, $item->gelar_depan, $item->gelar_belakang) }}</p>
                                    </div>
                                    @if ($item->path_berkas)
                                        <a class="button button-primary lms-material-download" href="{{ route('lms.materials.download', $item->id_materi) }}" data-page-loader-skip aria-label="Unduh materi {{ $item->nama_berkas }}" title="Unduh materi"><span data-lucide="download" aria-hidden="true"></span><span>Unduh</span></a>
                                    @else
                                        <span class="lms-material-unavailable">File belum tersedia</span>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
            @elseif ($materialLibrary->isEmpty())
                <div class="lms-material-empty"><span data-lucide="library" aria-hidden="true"></span><strong>Belum ada dosen untuk ditampilkan</strong><p>Daftar dosen akan muncul jika tersedia jadwal mengajar dalam cakupan akses akun Anda.</p></div>
            @else
                <section class="lms-material-library">
                    <div class="lms-material-library-intro">
                        <span class="lms-material-library-intro-icon"><span data-lucide="library" aria-hidden="true"></span></span>
                        <div><p class="lms-material-library-kicker">Perpustakaan digital</p><h2>Materi dari dosen</h2><p>Pilih mata kuliah untuk melihat dan mengunduh materi yang tersedia.</p></div>
                    </div>
                    <label class="lms-material-search">
                        <span data-lucide="search" aria-hidden="true"></span>
                        <input type="search" data-material-library-search placeholder="Cari nama dosen atau mata kuliah..." aria-label="Cari nama dosen atau mata kuliah">
                    </label>
                    <p class="lms-material-search-empty" data-material-library-empty role="status" hidden>Tidak ada dosen atau mata kuliah yang cocok dengan pencarian.</p>
                    <div class="lms-material-lecturer-grid" data-material-library-grid>
                        @foreach ($materialLibrary as $lecturer)
                            <article class="lms-material-lecturer-card" data-material-lecturer="{{ \App\Support\LecturerName::format($lecturer->nama_dosen, $lecturer->gelar_depan, $lecturer->gelar_belakang) }}">
                                <header class="lms-material-lecturer-profile">
                                    <span class="lms-material-avatar">{{ collect(explode(' ', trim($lecturer->nama_dosen ?: 'Dosen')))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('') }}</span>
                                    <div><h3>{{ \App\Support\LecturerName::format($lecturer->nama_dosen, $lecturer->gelar_depan, $lecturer->gelar_belakang) ?: 'Dosen' }}</h3><p>{{ $lecturer->nidn ? 'NIDN '.$lecturer->nidn : 'Dosen pengampu' }}</p></div>
                                    <span class="lms-material-count">{{ $lecturer->material_count }} materi</span>
                                </header>
                                <div class="lms-material-lecturer-courses">
                                    <p class="lms-material-course-heading">Mata kuliah yang pernah diampu</p>
                                    @foreach ($lecturer->courses as $course)
                                        <a class="lms-material-course-link" data-material-course="{{ $course->nama_mata_kuliah }}" href="{{ route('lms.materials.index', ['id_dosen' => $lecturer->id_dosen, 'id_matkul' => $course->id_matkul, 'id_prodi' => $course->id_prodi]) }}">
                                            <span class="lms-material-course-icon"><span data-lucide="book-open" aria-hidden="true"></span></span>
                                            <span class="lms-material-course-name"><strong>{{ $course->nama_mata_kuliah }}</strong><small>{{ $course->kode_mata_kuliah }} · {{ $course->nama_program_studi ?: 'Program studi' }}</small></span>
                                            <span class="lms-material-course-count">{{ $course->material_count }} <span>materi</span></span>
                                            <span class="lms-material-course-arrow" data-lucide="arrow-right" aria-hidden="true"></span>
                                        </a>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        @elseif ($isStudent && in_array($module, ['pertemuan', 'tugas', 'diskusi'], true) && ! $studentActivityFiltersReady)
            <div class="empty-state">Pilih tahun akademik dan mata kuliah untuk menampilkan aktivitas mahasiswa.</div>
        @elseif ($isStudent && in_array($module, ['pertemuan', 'tugas', 'diskusi'], true) && $studentActivityFiltersReady && $records->isEmpty())
            <div class="empty-state">Tidak ada aktivitas untuk mata kuliah dan tahun akademik yang dipilih.</div>
        @else
            <div class="table-wrap">
                <table data-datatable="lms-{{ $module }}">
                    <thead>
                        <tr>
                            @if ($module === 'materi')
                                <th>Judul Materi</th><th>Mata Kuliah</th><th>Program Studi</th><th>Dosen</th><th>Status</th><th>Aksi</th>
                            @elseif ($module === 'pertemuan')
                                <th>Pertemuan</th><th>Mata Kuliah / Kelas</th><th>Tanggal dan Waktu</th><th>Absen Mandiri</th>
                                @if ($isStudent)
                                    <th>Keterangan Kehadiran</th>
                                @endif
                                <th>Aksi</th>
                            @elseif ($module === 'tugas')
                                <th>Nama Tugas</th><th>Mata Kuliah / Kelas</th><th>Batas Pengumpulan</th>
                                @if ($isStudent)
                                    <th>Status Pengumpulan</th><th>Nilai / Komentar Dosen</th>
                                @else
                                    <th>Status</th>
                                @endif
                                <th>Aksi</th>
                            @elseif ($module === 'bank-soal')
                                <th>Pertanyaan</th><th>Mata Kuliah</th><th>Jenis</th><th>Status</th><th>Aksi</th>
                            @else
                                <th>Topik Diskusi</th><th>Mata Kuliah / Kelas</th><th>Status</th><th>Tanggal Terakhir Diskusi</th><th>Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $item)
                            <tr>
                                @if ($module === 'materi')
                                    <td>{{ $item->judul }}</td>
                                    <td>{{ $item->kode_mata_kuliah ? $item->kode_mata_kuliah.' — '.$item->nama_mata_kuliah : '—' }}</td>
                                    <td>{{ $item->nama_program_studi ?: '—' }}</td>
                                    <td>{{ \App\Support\LecturerName::format($item->nama_dosen, $item->gelar_depan, $item->gelar_belakang) ?: '—' }}</td>
                                    <td><span class="status-chip {{ $item->status_publikasi === 'terbit' ? 'active' : '' }}">{{ ucfirst($item->status_publikasi) }}</span></td>
                                @elseif ($module === 'pertemuan')
                                    <td>Pertemuan {{ $item->pertemuan_ke }}<span class="subtext">{{ $item->judul }}</span></td>
                                    <td>{{ $item->kode_mata_kuliah }} — {{ $item->nama_mata_kuliah }}<span class="subtext">{{ $item->nama_kelas }} · {{ $item->nama_program_studi }}</span></td>
                                    <td>{{ $item->tanggal_kuliah }}<span class="subtext">{{ substr($item->waktu_mulai, 0, 5) }}–{{ substr($item->waktu_selesai, 0, 5) }}</span></td>
                                    <td>{{ $item->absen_dibuka ? 'Dibuka' : 'Ditutup' }}</td>
                                    @if ($isStudent)
                                        <td>
                                            @if ($item->student_attendance_status)
                                                <span class="status-chip attendance-recorded">Sudah absen</span>
                                            @else
                                                <span class="status-chip attendance-missing">Belum absen</span>
                                            @endif
                                        </td>
                                    @endif
                                @elseif ($module === 'tugas')
                                    <td>{{ $item->judul }}</td>
                                    <td>{{ $item->kode_mata_kuliah }} — {{ $item->nama_mata_kuliah }}<span class="subtext">{{ $item->nama_kelas }} · {{ $item->nama_program_studi }}</span></td>
                                    <td>{{ $item->batas_pengumpulan }}</td>
                                    <td>
                                        @if ($isStudent)
                                            @if ($item->student_submission)
                                                <span class="status-chip task-submitted">Sudah kumpul tugas</span>
                                            @else
                                                <span class="status-chip task-unsubmitted">Belum kumpul tugas</span>
                                            @endif
                                        @else
                                            <span class="status-chip {{ $item->status_tugas === 'terbit' ? 'active' : '' }}">{{ ucfirst($item->status_tugas) }}</span>
                                        @endif
                                    </td>
                                    @if ($isStudent)
                                        <td>
                                            @if ($item->student_submission && ($item->student_submission->nilai !== null || filled($item->student_submission->komentar_dosen)))
                                                @if ($item->student_submission->nilai !== null)
                                                    <strong>Nilai: {{ number_format((float) $item->student_submission->nilai, 2, ',', '.') }}</strong>
                                                @endif
                                                @if (filled($item->student_submission->komentar_dosen))
                                                    <span class="subtext">Komentar dosen: {{ $item->student_submission->komentar_dosen }}</span>
                                                @endif
                                            @else
                                                <span class="subtext">Nilai tugas belum dinilai oleh dosen</span>
                                            @endif
                                        </td>
                                    @endif
                                @elseif ($module === 'bank-soal')
                                    <td>{{ \Illuminate\Support\Str::limit(strip_tags($item->pertanyaan), 100) }}</td>
                                    <td>{{ $item->kode_mata_kuliah }} — {{ $item->nama_mata_kuliah }}<span class="subtext">{{ \App\Support\LecturerName::format($item->nama_dosen, $item->gelar_depan, $item->gelar_belakang) }}</span></td>
                                    <td>{{ $item->jenis_soal === 'pilihan_ganda' ? 'Pilihan ganda' : 'Essay' }}</td>
                                    <td>{{ $item->aktif ? 'Aktif' : 'Nonaktif' }}</td>
                                @else
                                    <td>{{ $item->judul }}</td>
                                    <td>{{ $item->kode_mata_kuliah }} — {{ $item->nama_mata_kuliah }}<span class="subtext">{{ $item->nama_kelas }} · {{ $item->nama_program_studi }}</span></td>
                                    <td><span class="status-chip {{ $item->status_diskusi === 'terbuka' ? 'active' : '' }}">{{ ucfirst($item->status_diskusi) }}</span></td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($item->tanggal_terakhir_diskusi, 'Asia/Jayapura')->format('d/m/Y H:i') }}</td>
                                @endif
                                <td>
                                    @if ($module === 'materi')
                                        @if ($item->path_berkas)
                                            <a class="table-icon-button" href="{{ route('lms.materials.download', $item->id_materi) }}" data-page-loader-skip aria-label="Unduh materi {{ $item->nama_berkas }}" title="Unduh materi"><span data-lucide="file-down" aria-hidden="true"></span></a>
                                        @endif
                                        @if ($canManage)
                                            <button class="table-icon-button" type="button" data-lms-open="lms-material-edit-{{ $item->id_materi }}" aria-label="Ubah materi" title="Ubah materi"><span data-lucide="pencil" aria-hidden="true"></span></button>
                                            @if ($item->used_in_meeting)
                                                <span class="subtext">Materi ini sudah digunakan pada pertemuan mata kuliah, hanya bisa dilakukan edit.</span>
                                            @else
                                                <button class="table-icon-button reset" type="button" data-lms-open="lms-material-delete-{{ $item->id_materi }}" aria-label="Hapus materi" title="Hapus materi"><span data-lucide="trash-2" aria-hidden="true"></span></button>
                                            @endif
                                        @endif
                                    @elseif ($module === 'pertemuan')
                                        @if ($canManage)
                                            <button class="table-icon-button" type="button" data-lms-open="lms-meeting-edit-{{ $item->id_pertemuan }}" aria-label="Ubah pertemuan" title="Ubah pertemuan"><span data-lucide="pencil" aria-hidden="true"></span></button>
                                            <button class="table-icon-button reset" type="button" data-lms-open="lms-meeting-delete-{{ $item->id_pertemuan }}" aria-label="Hapus pertemuan" title="Hapus pertemuan"><span data-lucide="trash-2" aria-hidden="true"></span></button>
                                            <a class="table-icon-button" href="{{ route('lms.attendance.roster', $item->id_pertemuan) }}" aria-label="Kelola absensi" title="Kelola absensi"><span data-lucide="clipboard-check" aria-hidden="true"></span></a>
                                        @endif
                                        @unless ($isStudent)
                                            <a class="table-icon-button" href="{{ route('lms.meetings.attendance.print', $item->id_pertemuan) }}" target="_blank" rel="noopener noreferrer" aria-label="Cetak absensi dan berita acara" title="Cetak absensi dan berita acara"><span data-lucide="printer" aria-hidden="true"></span></a>
                                        @endunless
                                        @if ($canManage)
                                            @if ($item->exam)
                                                <a class="table-icon-button" href="{{ route('lms.exams.show', $item->exam->id_ujian) }}" aria-label="Lihat ujian" title="Lihat ujian"><span data-lucide="file-text" aria-hidden="true"></span></a>
                                            @else
                                                <a class="table-icon-button" href="{{ route('lms.exams.create', $item->id_pertemuan) }}" aria-label="Buat ujian" title="Buat ujian"><span data-lucide="clipboard-plus" aria-hidden="true"></span></a>
                                            @endif
                                        @else
                                                @if ($isStudent && $studentActivityWritable && $item->student_exam)
                                                    <a class="table-icon-button" href="{{ route('lms.exams.show', $item->student_exam->id_ujian) }}" aria-label="Ikut ujian" title="Ikut ujian"><span data-lucide="clipboard-check" aria-hidden="true"></span></a>
                                                @endif
                                            <a class="table-icon-button" href="{{ route('lms.show', array_merge(['module' => $module, 'record' => $item->id_pertemuan], $detailFilterParameters)) }}" aria-label="Lihat detail" title="Lihat detail"><span data-lucide="eye" aria-hidden="true"></span></a>
                                        @endif
                                    @elseif ($module === 'bank-soal' && $canManage)
                                        <button class="table-icon-button" type="button" data-lms-open="lms-question-edit-{{ $item->id_soal }}" aria-label="Ubah soal" title="Ubah soal"><span data-lucide="pencil" aria-hidden="true"></span></button>
                                    @else
                                        @if ($module === 'tugas' && $isStudent)
                                            @if ($studentActivityWritable)
                                                <button class="button button-secondary lms-task-submit-action" type="button" data-lms-open="lms-task-submit-{{ $item->id_tugas }}">
                                                    <span data-lucide="upload" aria-hidden="true"></span><span>Kumpul tugas</span>
                                                </button>
                                            @else
                                                <a class="table-icon-button" href="{{ route('lms.show', array_merge(['module' => 'tugas', 'record' => $item->id_tugas], $detailFilterParameters)) }}" aria-label="Lihat tugas" title="Lihat tugas"><span data-lucide="eye" aria-hidden="true"></span></a>
                                            @endif
                                        @elseif ($module === 'tugas' && $canManage)
                                            <a class="table-icon-button" href="{{ route('lms.show', array_merge(['module' => 'tugas', 'record' => $item->id_tugas], $detailFilterParameters)) }}" aria-label="Lihat detail tugas" title="Lihat tugas"><span data-lucide="eye" aria-hidden="true"></span></a>
                                            <button class="table-icon-button" type="button" data-lms-open="lms-task-edit-{{ $item->id_tugas }}" aria-label="Ubah tugas" title="Ubah tugas"><span data-lucide="pencil" aria-hidden="true"></span></button>
                                            <button class="table-icon-button reset" type="button" data-lms-open="lms-task-delete-{{ $item->id_tugas }}" aria-label="Hapus tugas" title="Hapus tugas"><span data-lucide="trash-2" aria-hidden="true"></span></button>
                                        @elseif ($module === 'diskusi' && $canManage)
                                            <a class="table-icon-button" href="{{ route('lms.show', array_merge(['module' => 'diskusi', 'record' => $item->id_diskusi], $detailFilterParameters)) }}" aria-label="Lihat diskusi" title="Lihat diskusi"><span data-lucide="eye" aria-hidden="true"></span></a>
                                            <button class="table-icon-button" type="button" data-lms-open="lms-discussion-edit-{{ $item->id_diskusi }}" aria-label="Ubah diskusi" title="Ubah diskusi"><span data-lucide="pencil" aria-hidden="true"></span></button>
                                            <button class="table-icon-button reset" type="button" data-lms-open="lms-discussion-delete-{{ $item->id_diskusi }}" aria-label="Hapus diskusi" title="Hapus diskusi"><span data-lucide="trash-2" aria-hidden="true"></span></button>
                                        @else
                                            <a class="table-icon-button" href="{{ route('lms.show', array_merge(['module' => $module, 'record' => $item->{'id_'.str_replace('-', '_', $module)} ?? $item->id_materi ?? $item->id_pertemuan ?? $item->id_tugas ?? $item->id_soal ?? $item->id_diskusi], $detailFilterParameters)) }}" aria-label="Lihat detail" title="Lihat detail"><span data-lucide="eye" aria-hidden="true"></span></a>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($module === 'materi')
        @foreach ($records as $item)
            @if ($canManage)
                <dialog class="lms-dialog" id="lms-material-edit-{{ $item->id_materi }}">
                    <form method="post" class="lms-dialog-form" enctype="multipart/form-data" action="{{ route('lms.materials.update', $item->id_materi) }}">
                        @csrf
                        @method('PUT')
                        <div class="lms-dialog-heading"><h2>Ubah materi</h2><button class="table-icon-button" type="button" data-lms-close aria-label="Tutup form"><span data-lucide="x" aria-hidden="true"></span></button></div>
                        <div class="lms-dialog-body">
                            <label>Mata kuliah<select name="id_matkul" data-searchable-select required><option value="">Pilih mata kuliah yang pernah diampu</option>@foreach ($courses as $course)<option value="{{ $course->id_matkul }}" @selected($item->id_matkul === $course->id_matkul)>{{ $course->kode_mata_kuliah }} — {{ $course->nama_mata_kuliah }} ({{ $course->namatahun ?: 'Tahun akademik tidak diketahui' }})</option>@endforeach</select></label>
                            <label>Judul materi<input name="judul" maxlength="255" value="{{ $item->judul }}" required></label>
                            <label>Deskripsi<textarea name="deskripsi" rows="4">{{ $item->deskripsi }}</textarea></label>
                            <label class="lms-dropzone" data-lms-dropzone>
                                <input class="lms-dropzone-input" type="file" name="berkas" accept=".doc,.docx,.odt,.rtf,.ppt,.pptx,.odp,.pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.oasis.opendocument.text,application/rtf,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/vnd.oasis.opendocument.presentation,application/pdf">
                                <span>Seret file pengganti ke sini atau klik untuk memilih</span>
                                <small data-lms-file-name>{{ $item->nama_berkas ?: 'Belum ada file dipilih' }}</small>
                            </label>
                            <label>Status publikasi<select name="status_publikasi" data-searchable-select required><option value="terbit" @selected($item->status_publikasi === 'terbit')>Terbit</option><option value="draf" @selected($item->status_publikasi === 'draf')>Draf (belum ditayangkan)</option></select></label>
                        </div>
                        <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close>Batal</button><button class="button button-primary" type="submit">Simpan perubahan</button></div>
                    </form>
                </dialog>
                @unless ($item->used_in_meeting)
                    <dialog class="lms-dialog lms-confirm-dialog" id="lms-material-delete-{{ $item->id_materi }}">
                        <div class="lms-dialog-heading"><h2>Hapus materi</h2></div>
                        <p>Yakin ingin menghapus materi “{{ $item->judul }}”?</p>
                        <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close>Batal</button><form method="post" action="{{ route('lms.materials.destroy', $item->id_materi) }}">@csrf @method('DELETE')<button class="button button-danger" type="submit">Hapus materi</button></form></div>
                    </dialog>
                @endunless
            @endif
        @endforeach
    @endif

    @if ($module === 'tugas' && $isStudent && $studentActivityWritable)
        @foreach ($records as $item)
            @php
                $studentSubmission = $item->student_submission;
                $restoreTaskSubmission = (string) old('_task_id', '') === (string) $item->id_tugas;
            @endphp
            <dialog class="lms-dialog lms-task-submit-dialog" id="lms-task-submit-{{ $item->id_tugas }}" data-lms-task-modal data-reopen="{{ $errors->any() && $restoreTaskSubmission ? 'true' : 'false' }}" aria-labelledby="lms-task-submit-title-{{ $item->id_tugas }}">
                <form method="post" class="lms-dialog-form" action="{{ route('lms.tasks.submit', $item->id_tugas) }}">
                    @csrf
                    <input type="hidden" name="_task_id" value="{{ $item->id_tugas }}">
                    <div class="lms-dialog-heading lms-task-submit-heading">
                        <div><p class="lms-task-submit-eyebrow">Pengumpulan tugas</p><h2 id="lms-task-submit-title-{{ $item->id_tugas }}">{{ $item->judul }}</h2></div>
                        <button class="table-icon-button" type="button" data-lms-close aria-label="Tutup form"><span data-lucide="x" aria-hidden="true"></span></button>
                    </div>
                    <div class="lms-dialog-body lms-task-submit-body">
                        <div class="lms-task-submit-deadline"><span>Batas pengumpulan</span><strong>{{ \Illuminate\Support\Carbon::parse($item->batas_pengumpulan, 'Asia/Jayapura')->format('d/m/Y H:i') }} WIT</strong></div>
                        @if ($studentSubmission)
                            <p class="lms-task-submit-status">Status: <strong>{{ $studentSubmission->status_pengumpulan === 'dinilai' ? 'Sudah dinilai' : 'Sudah dikumpulkan' }}</strong></p>
                        @endif
                        <label>Jawaban Text langsung
                            <textarea name="jawaban_teks" rows="6" placeholder="Tuliskan jawaban tugas Anda">{{ $restoreTaskSubmission ? old('jawaban_teks') : ($studentSubmission->jawaban_teks ?? '') }}</textarea>
                        </label>
                        <label>Link URL Tugas
                            <input type="url" name="tautan_berkas" maxlength="2048" placeholder="https://" value="{{ $restoreTaskSubmission ? old('tautan_berkas') : ($studentSubmission->tautan_berkas ?? '') }}">
                        </label>
                    </div>
                    <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close>Batal</button><button class="button button-primary" type="submit">{{ $studentSubmission ? 'Simpan perubahan' : 'Kumpulkan tugas' }}</button></div>
                </form>
            </dialog>
        @endforeach
    @endif

    @if ($module === 'tugas' && $canManage)
        @foreach ($records as $item)
            <dialog class="lms-dialog" id="lms-task-edit-{{ $item->id_tugas }}">
                <form method="post" class="lms-dialog-form" action="{{ route('lms.tasks.update', $item->id_tugas) }}">
                    @csrf
                    @method('PUT')
                    <div class="lms-dialog-heading"><h2>Ubah tugas</h2><button class="table-icon-button" type="button" data-lms-close aria-label="Tutup form"><span data-lucide="x" aria-hidden="true"></span></button></div>
                    <div class="lms-dialog-body">
                        <label>Nama tugas<input name="judul" value="{{ $item->judul }}" required maxlength="255"></label>
                        <label>Deskripsi tugas<textarea name="deskripsi" rows="5" required>{{ $item->deskripsi }}</textarea></label>
                        <label>Batas pengumpulan<input type="datetime-local" name="batas_pengumpulan" value="{{ str_replace(' ', 'T', substr($item->batas_pengumpulan, 0, 16)) }}" required></label>
                        <label>Status<select name="status_tugas" data-searchable-select required><option value="terbit" @selected($item->status_tugas === 'terbit')>Terbit</option><option value="draf" @selected($item->status_tugas === 'draf')>Draf</option><option value="ditutup" @selected($item->status_tugas === 'ditutup')>Ditutup</option></select></label>
                    </div>
                    <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close>Batal</button><button class="button button-primary" type="submit">Simpan perubahan</button></div>
                </form>
            </dialog>
            <dialog class="lms-dialog lms-confirm-dialog" id="lms-task-delete-{{ $item->id_tugas }}">
                <div class="lms-dialog-heading"><h2>Hapus tugas</h2></div>
                <p>Yakin ingin menghapus tugas “{{ $item->judul }}”?</p>
                <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close>Batal</button><form method="post" action="{{ route('lms.tasks.destroy', $item->id_tugas) }}">@csrf @method('DELETE')<button class="button button-danger" type="submit">Hapus tugas</button></form></div>
            </dialog>
        @endforeach
    @endif

    @if ($module === 'pertemuan' && $canManage)
        @foreach ($records as $item)
            <dialog class="lms-dialog" id="lms-meeting-edit-{{ $item->id_pertemuan }}">
                <form method="post" class="lms-dialog-form" action="{{ route('lms.meetings.update', $item->id_pertemuan) }}">
                    @csrf
                    @method('PUT')
                    <div class="lms-dialog-heading"><h2>Ubah pertemuan {{ $item->pertemuan_ke }}</h2><button class="table-icon-button" type="button" data-lms-close aria-label="Tutup form"><span data-lucide="x" aria-hidden="true"></span></button></div>
                    <div class="lms-dialog-body">
                        <label>Pertemuan Ke<select name="pertemuan_ke" data-searchable-select required>@for ($meeting = 1; $meeting <= 16; $meeting++)<option value="{{ $meeting }}" @selected((int) $item->pertemuan_ke === $meeting)>Pertemuan {{ $meeting }}</option>@endfor</select></label>
                        <label>Materi terkait (dapat memilih lebih dari satu)<select name="id_materi[]" data-searchable-select multiple>@foreach ($materials as $material)<option value="{{ $material->id_materi }}" @selected($item->materials->contains('id_materi', $material->id_materi))>{{ $material->judul }}</option>@endforeach</select></label>
                        <label>Judul pertemuan<input name="judul" maxlength="255" value="{{ $item->judul }}" required></label>
                        <label>Tanggal kuliah<input name="tanggal_kuliah" type="date" value="{{ $item->tanggal_kuliah }}" required></label>
                        <div class="lms-inline-fields"><label>Waktu mulai<input name="waktu_mulai" type="time" value="{{ substr($item->waktu_mulai, 0, 5) }}" required></label><label>Waktu selesai<input name="waktu_selesai" type="time" value="{{ substr($item->waktu_selesai, 0, 5) }}" required></label></div>
                        <label>Absensi mandiri<select name="absen_dibuka" data-searchable-select required><option value="0" @selected(! $item->absen_dibuka)>Ditutup — dosen mengabsenkan</option><option value="1" @selected($item->absen_dibuka)>Dibuka sesuai jadwal</option></select></label>
                        <label>Tautan video conference (opsional)<input type="url" name="tautan_video" maxlength="2048" value="{{ $item->tautan_video }}"></label>
                        <label>Status pertemuan<select name="status_pertemuan" data-searchable-select required>@foreach (['draf' => 'Draf', 'terbit' => 'Terbit', 'selesai' => 'Selesai'] as $value => $label)<option value="{{ $value }}" @selected($item->status_pertemuan === $value)>{{ $label }}</option>@endforeach</select></label>
                    </div>
                    <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close>Batal</button><button class="button button-primary" type="submit">Simpan perubahan</button></div>
                </form>
            </dialog>
            <dialog class="lms-dialog lms-confirm-dialog" id="lms-meeting-delete-{{ $item->id_pertemuan }}">
                <div class="lms-dialog-heading"><h2>Hapus pertemuan</h2></div>
                <p>Yakin ingin menghapus Pertemuan {{ $item->pertemuan_ke }} — {{ $item->judul }}?</p>
                <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close>Batal</button><form method="post" action="{{ route('lms.meetings.destroy', $item->id_pertemuan) }}">@csrf @method('DELETE')<button class="button button-danger" type="submit">Hapus pertemuan</button></form></div>
            </dialog>
        @endforeach
    @endif

    @if ($module === 'diskusi' && $canManage)
        @foreach ($records as $item)
            <dialog class="lms-dialog" id="lms-discussion-edit-{{ $item->id_diskusi }}">
                <form method="post" class="lms-dialog-form" action="{{ route('lms.discussions.update', $item->id_diskusi) }}">
                    @csrf
                    @method('PUT')
                    <div class="lms-dialog-heading"><h2>Ubah diskusi</h2><button class="table-icon-button" type="button" data-lms-close aria-label="Tutup form"><span data-lucide="x" aria-hidden="true"></span></button></div>
                    <div class="lms-dialog-body">
                        <label>Topik<input name="judul" maxlength="255" value="{{ $item->judul }}" required></label>
                        <label>Uraian pembuka<textarea name="isi_diskusi" rows="5" required>{{ $item->isi_diskusi }}</textarea></label>
                        <label>Status<select name="status_diskusi" data-searchable-select required><option value="terbuka" @selected($item->status_diskusi === 'terbuka')>Terbuka</option><option value="ditutup" @selected($item->status_diskusi === 'ditutup')>Ditutup</option></select></label>
                    </div>
                    <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close>Batal</button><button class="button button-primary" type="submit">Simpan perubahan</button></div>
                </form>
            </dialog>
            <dialog class="lms-dialog lms-confirm-dialog" id="lms-discussion-delete-{{ $item->id_diskusi }}">
                <div class="lms-dialog-heading"><h2>Hapus diskusi</h2></div>
                <p>Yakin ingin menghapus diskusi “{{ $item->judul }}” beserta percakapannya?</p>
                <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close>Batal</button><form method="post" action="{{ route('lms.discussions.destroy', $item->id_diskusi) }}">@csrf @method('DELETE')<button class="button button-danger" type="submit">Hapus diskusi</button></form></div>
            </dialog>
        @endforeach
    @endif

    @if ($canManage && $module === 'bank-soal')
        @foreach ($records as $item)
            <dialog class="lms-dialog lms-question-edit-dialog" id="lms-question-edit-{{ $item->id_soal }}" aria-labelledby="lms-question-edit-title-{{ $item->id_soal }}">
                <form method="post" class="lms-dialog-form" action="{{ route('lms.questions.update', $item->id_soal) }}" data-lms-question-form>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="tahun" value="{{ $selectedAcademicYearCode }}">
                    <div class="lms-dialog-heading">
                        <div><p class="eyebrow">Bank Soal · {{ $item->kode_mata_kuliah }} — {{ $item->nama_mata_kuliah }}</p><h2 id="lms-question-edit-title-{{ $item->id_soal }}">Ubah soal</h2></div>
                        <button class="table-icon-button" type="button" data-lms-close aria-label="Tutup form"><span data-lucide="x" aria-hidden="true"></span></button>
                    </div>
                    <div class="lms-dialog-body">
                        <label>Jenis soal
                            <select name="jenis_soal" data-searchable-select required>
                                <option value="pilihan_ganda" @selected($item->jenis_soal === 'pilihan_ganda')>Pilihan ganda</option>
                                <option value="essay" @selected($item->jenis_soal === 'essay')>Essay</option>
                            </select>
                        </label>
                        <label>Pertanyaan<textarea name="pertanyaan" rows="5" required>{{ $item->pertanyaan }}</textarea></label>
                        <fieldset data-question-options @if ($item->jenis_soal === 'essay') hidden @endif>
                            <legend>Pilihan jawaban</legend>
                            @foreach (['a', 'b', 'c', 'd', 'e'] as $option)
                                <label>Opsi {{ strtoupper($option) }}<textarea name="opsi_{{ $option }}" rows="2">{{ $item->{'opsi_'.$option} }}</textarea></label>
                            @endforeach
                            <label>Kunci jawaban
                                <select name="kunci_jawaban" data-searchable-select>
                                    @foreach (['A', 'B', 'C', 'D', 'E'] as $key)
                                        <option value="{{ $key }}" @selected($item->kunci_jawaban === $key)>{{ $key }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </fieldset>
                        <label>Status
                            <select name="aktif" data-searchable-select required>
                                <option value="1" @selected($item->aktif)>Aktif</option>
                                <option value="0" @selected(! $item->aktif)>Nonaktif</option>
                            </select>
                        </label>
                    </div>
                    <div class="lms-dialog-actions">
                        <button class="button button-secondary" type="button" data-lms-close>Batal</button>
                        <button class="button button-primary" type="submit">Simpan perubahan</button>
                    </div>
                </form>
            </dialog>
        @endforeach
        <dialog class="lms-dialog" id="lms-question-upload-modal">
            <form method="post" class="lms-dialog-form" enctype="multipart/form-data" action="{{ route('lms.questions.import') }}">
                @csrf
                <div class="lms-dialog-heading">
                    <div><p class="eyebrow">LMS Virtual Class</p><h2>Upload soal bank soal</h2></div>
                    <button class="table-icon-button" type="button" data-lms-close aria-label="Tutup dialog">×</button>
                </div>
                <div class="lms-dialog-body">
                    <input type="hidden" name="tahun" value="{{ $selectedAcademicYearCode }}">
                    <label>Mata kuliah<select name="id_matkul" data-searchable-select required><option value="">Pilih mata kuliah yang pernah diampu</option>@foreach ($courses as $course)<option value="{{ $course->id_matkul }}">{{ $course->kode_mata_kuliah }} — {{ $course->nama_mata_kuliah }} ({{ $course->namatahun ?: 'Tahun akademik tidak diketahui' }})</option>@endforeach</select></label>
                    <div class="lms-upload-callout">
                        Download template Excel bank soal untuk diisi lalu upload kembali. <a href="{{ route('lms.questions.template') }}" target="_blank" rel="noopener noreferrer">Unduh format Excel</a>
                    </div>
                    <label class="lms-dropzone" data-lms-dropzone>
                        <input class="lms-dropzone-input" type="file" name="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                        <span>Seret file bank soal Excel (.xlsx) ke sini atau klik untuk memilih</span>
                        <small data-lms-file-name>Belum ada file dipilih</small>
                    </label>
                </div>
                <div class="lms-dialog-actions">
                    <button class="button button-secondary" type="button" data-lms-close>Batal</button>
                    <button class="button button-primary" type="submit">Upload soal</button>
                </div>
            </form>
        </dialog>
    @endif

    @if ($canManage)
        <dialog class="lms-dialog" id="lms-create-modal">
            <form method="post" class="lms-dialog-form" enctype="multipart/form-data"
                action="{{ match ($module) {
                    'materi' => route('lms.materials.store'),
                    'pertemuan' => route('lms.meetings.store'),
                    'tugas' => route('lms.tasks.store'),
                    'bank-soal' => route('lms.questions.store'),
                    'diskusi' => route('lms.discussions.store'),
                } }}">
                @csrf
                <div class="lms-dialog-heading">
                    <div><p class="eyebrow">LMS Virtual Class</p><h2>Tambah {{ $title }}</h2></div>
                    <button class="table-icon-button" type="button" data-lms-close aria-label="Tutup dialog">×</button>
                </div>
                <div class="lms-dialog-body">
                    @if (in_array($module, ['pertemuan', 'tugas', 'diskusi'], true))
                        <label>Mata kuliah / kelas
                            <select name="id_jadwal" data-searchable-select required>
                                <option value="">Pilih jadwal mengajar</option>
                                @foreach ($schedules as $schedule)
                                    <option value="{{ $schedule->id_jadwal }}">{{ $schedule->kode_mata_kuliah }} — {{ $schedule->nama_mata_kuliah }} ({{ $schedule->nama_kelas }}) · {{ $schedule->namatahun }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    @if ($module === 'materi')
                        <label>Mata kuliah<select name="id_matkul" data-searchable-select required><option value="">Pilih mata kuliah yang pernah diampu</option>@foreach ($courses as $course)<option value="{{ $course->id_matkul }}">{{ $course->kode_mata_kuliah }} — {{ $course->nama_mata_kuliah }} ({{ $course->namatahun ?: 'Tahun akademik tidak diketahui' }})</option>@endforeach</select></label>
                        <label>Judul materi<input name="judul" maxlength="255" required></label>
                        <label>Deskripsi<textarea name="deskripsi" rows="4"></textarea></label>
                        <label class="lms-dropzone" data-lms-dropzone>
                            <input class="lms-dropzone-input" type="file" name="berkas" data-lms-required-upload required accept=".doc,.docx,.odt,.rtf,.ppt,.pptx,.odp,.pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.oasis.opendocument.text,application/rtf,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/vnd.oasis.opendocument.presentation,application/pdf">
                            <span>Seret file materi ke sini atau klik untuk memilih</span>
                            <small data-lms-file-name>Belum ada file dipilih</small>
                        </label>
                        <label>Status publikasi<select name="status_publikasi" data-searchable-select required><option value="terbit" selected>Terbit</option><option value="draf">Draf (belum ditayangkan)</option></select></label>
                    @elseif ($module === 'pertemuan')
                        <label>Pertemuan Ke<select name="pertemuan_ke" data-searchable-select required><option value="">Pilih pertemuan</option>@for ($meeting = 1; $meeting <= 16; $meeting++)<option value="{{ $meeting }}">Pertemuan {{ $meeting }}</option>@endfor</select></label>
                        <label>Materi terkait (dapat memilih lebih dari satu)<select name="id_materi[]" data-searchable-select multiple>@foreach ($materials as $material)<option value="{{ $material->id_materi }}">{{ $material->judul }}</option>@endforeach</select></label>
                        <label>Judul pertemuan<input name="judul" maxlength="255" required></label>
                        <label>Tanggal kuliah<input name="tanggal_kuliah" type="date" required></label>
                        <div class="lms-inline-fields"><label>Waktu mulai<input name="waktu_mulai" type="time" required></label><label>Waktu selesai<input name="waktu_selesai" type="time" required></label></div>
                        <label>Absensi mandiri<select name="absen_dibuka" data-searchable-select required><option value="0">Ditutup — dosen mengabsenkan</option><option value="1">Dibuka sesuai jadwal</option></select></label>
                        <label>Tautan video conference (opsional)<input name="tautan_video" type="url" maxlength="2048"></label>
                    @elseif ($module === 'tugas')
                        <label>Nama tugas<input name="judul" maxlength="255" required></label>
                        <label>Deskripsi tugas<textarea name="deskripsi" rows="5" required></textarea></label>
                        <label>Batas pengumpulan<input name="batas_pengumpulan" type="datetime-local" required></label>
                        <label>Status tugas<select name="status_tugas" data-searchable-select required><option value="terbit">Terbit</option><option value="draf">Draf</option></select></label>
                    @elseif ($module === 'bank-soal')
                        <label>Mata kuliah<select name="id_matkul" data-searchable-select required><option value="">Pilih mata kuliah yang pernah diampu</option>@foreach ($courses as $course)<option value="{{ $course->id_matkul }}">{{ $course->kode_mata_kuliah }} — {{ $course->nama_mata_kuliah }} ({{ $course->namatahun ?: 'Tahun akademik tidak diketahui' }})</option>@endforeach</select></label>
                        <label>Jenis soal<select name="jenis_soal" data-searchable-select required><option value="pilihan_ganda">Pilihan ganda</option><option value="essay">Essay</option></select></label>
                        <label>Pertanyaan<textarea name="pertanyaan" rows="5" required></textarea></label>
                        <fieldset data-question-options><legend>Pilihan jawaban (untuk pilihan ganda)</legend>
                            @foreach (['a', 'b', 'c', 'd', 'e'] as $option)<label>Opsi {{ strtoupper($option) }}<textarea name="opsi_{{ $option }}" rows="2"></textarea></label>@endforeach
                            <label>Kunci jawaban<select name="kunci_jawaban" data-searchable-select><option value="">Pilih jawaban benar</option>@foreach (['A', 'B', 'C', 'D', 'E'] as $key)<option value="{{ $key }}">{{ $key }}</option>@endforeach</select></label>
                        </fieldset>
                    @else
                        <label>Topik diskusi<input name="judul" maxlength="255" required></label>
                        <label>Uraian pembuka<textarea name="isi_diskusi" rows="5" required></textarea></label>
                    @endif
                </div>
                <div class="lms-dialog-actions">
                    <button class="button button-secondary" type="button" data-lms-close>Batal</button>
                    <button class="button button-primary" type="submit">Simpan</button>
                </div>
            </form>
        </dialog>
    @endif
@endsection

@push('styles')
    <style>
        .lms-page .subtitle{margin-top:.5rem;color:var(--muted,#64748b)}
        .lms-filter-bar{display:flex;align-items:end;gap:1rem;flex-wrap:wrap;margin-bottom:1rem}
        .lms-filter-field{display:grid;flex:1 1 280px;gap:.4rem;min-width:0;max-width:520px}
        .lms-filter-field label{font-weight:600}
        .lms-filter-field select,.lms-filter-field .ts-wrapper{width:100%}
        .student-activity-readonly{display:flex;align-items:flex-start;gap:.7rem;padding:.8rem 1rem;border:1px solid #fde68a;border-left:4px solid #d97706;border-radius:6px;background:#fffbeb;color:#854d0e}
        .student-activity-readonly svg{width:1.1rem;height:1.1rem;flex:0 0 1.1rem;margin-top:.12rem}
        .student-activity-readonly span:last-child{display:grid;gap:.2rem}
        .student-activity-readonly small{color:#8b6a31;line-height:1.45}
        .lms-page-actions{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;justify-content:flex-end}
        .lms-upload-callout{padding:.75rem .9rem;border:1px solid #dfeae4;border-radius:10px;background:#f5faf7;color:#28523d;font-size:.9rem}
        .lms-upload-callout a{color:#185c4d;font-weight:700;text-decoration:underline}
        .lms-dialog{position:fixed;inset:50% auto auto 50%;transform:translate(-50%,-50%);margin:0;width:min(720px,calc(100vw - 2rem));max-height:min(90vh,900px);overflow:visible;padding:0;border:1px solid #dbe3ee;border-radius:16px;box-shadow:0 24px 80px #0f172a40}
        .lms-dialog::backdrop{background:#0f172a80;backdrop-filter:blur(2px)}
        .lms-dialog-form{display:flex;max-height:min(90vh,900px);flex-direction:column}
        .lms-dialog-heading,.lms-dialog-actions{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.25rem;border-bottom:1px solid #e2e8f0}
        .lms-dialog-heading h2{margin:0}
        .lms-dialog-body{display:grid;gap:1rem;overflow:auto;padding:1.25rem}
        .lms-dialog-body.lms-dropdown-open{overflow:visible}
        .lms-dialog-body label{display:grid;gap:.4rem;font-weight:600}
        .lms-dialog-body input:not([type=checkbox]):not([type=radio]):not([type=file]),.lms-dialog-body textarea,.lms-dialog-body select{width:100%;padding:.65rem .75rem;border:1px solid #cbd5e1;border-radius:8px;background:#fff}
        .lms-dialog-body fieldset{display:grid;gap:.75rem;border:1px solid #cbd5e1;border-radius:10px;padding:1rem}
        .lms-dialog-body legend{padding:0 .35rem;font-weight:700}
        .lms-inline-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
        .lms-dialog-actions{justify-content:flex-end;border-top:1px solid #e2e8f0;border-bottom:0}
        .lms-dialog-body input[type=checkbox],.lms-dialog-body input[type=radio]{width:1rem;height:1rem;flex:0 0 1rem;margin:0;accent-color:#2563eb}
        .lms-dialog [hidden]{display:none!important}
        .lms-dialog .ts-dropdown{z-index:2}
        .lms-dialog .ts-dropdown-content{max-height:min(240px,40vh);overflow-y:auto}
        .lms-question-edit-dialog{width:min(760px,calc(100vw - 2rem));max-height:min(90vh,900px);overflow:hidden;background:#fff;color:#243447}
        .lms-question-edit-dialog[open]{display:flex;flex-direction:column}
        .lms-question-edit-dialog .lms-dialog-form{min-height:0}
        .lms-question-edit-dialog .lms-dialog-body{min-height:0}
        .lms-question-edit-dialog .lms-dialog-body label{min-width:0}
        .lms-question-edit-dialog .lms-dialog-body textarea{box-sizing:border-box;resize:vertical}
        .lms-page .table-wrap .dt-container{width:100%}
        .lms-page .dt-container .dt-search input{min-width:200px}
        .lms-page table.dataTable td:last-child{text-align:center;white-space:nowrap}
        .lms-page .status-chip.attendance-recorded{border-color:#b8dfc1;background:#e8f5eb;color:#246238}
        .lms-page .status-chip.attendance-missing{border-color:#f0c1c1;background:#fff0f0;color:#a32d2d}
        .lms-page .status-chip.task-submitted{border-color:#b8dfc1;background:#e8f5eb;color:#246238}
        .lms-page .status-chip.task-unsubmitted{border-color:#f0c1c1;background:#fff0f0;color:#a32d2d}
        .lms-material-library{display:grid;gap:1.4rem}
        .lms-material-library-intro{display:flex;align-items:center;gap:1rem;padding:1.35rem 1.5rem;border:1px solid #dce8e0;border-radius:16px;background:linear-gradient(120deg,#f2f8f3,#fff 76%);box-shadow:0 8px 24px #17452b0a}
        .lms-material-library-intro-icon{display:grid;width:54px;height:54px;flex:0 0 54px;place-items:center;border-radius:15px;background:#e1f1e5;color:#287348}
        .lms-material-library-intro-icon svg{width:25px;height:25px}
        .lms-material-library-kicker{margin:0 0 .25rem;color:#3a8a5c;font-size:.72rem;font-weight:800;letter-spacing:.09em;text-transform:uppercase}
        .lms-material-library-intro h2,.lms-material-library-heading h2{margin:0;color:#183b29;font-size:1.3rem}
        .lms-material-library-intro p:last-child,.lms-material-library-heading>div>p:last-child{margin:.3rem 0 0;color:#718077;font-size:.88rem}
        .lms-material-search{display:flex;align-items:center;gap:.7rem;width:min(100%,560px);padding:.7rem .9rem;border:1px solid #dce8e0;border-radius:11px;background:#fff;color:#63806c;box-shadow:0 5px 18px #173d2708}
        .lms-material-search:focus-within{border-color:#5b9a6b;box-shadow:0 0 0 3px #5b9a6b20}
        .lms-material-search svg{width:19px;height:19px;flex:0 0 19px}
        .lms-material-search input{width:100%;min-width:0;border:0;outline:0;background:transparent;color:#263e2f;font:inherit;font-size:.88rem}
        .lms-material-search input::placeholder{color:#9aa79e}
        .lms-material-search-empty{margin:0;padding:.85rem 1rem;border:1px dashed #d2dfd5;border-radius:10px;color:#718077;font-size:.86rem}
        .lms-material-program-filter{display:flex;align-items:end;gap:.75rem;flex-wrap:wrap;padding:1rem 1.1rem;border:1px solid #dce8e0;border-radius:12px;background:#f8fbf8}
        .lms-material-program-filter label{display:grid;flex:1 1 300px;gap:.4rem;color:#355642;font-size:.82rem;font-weight:700}
        .lms-material-program-filter select,.lms-material-program-filter .ts-wrapper{width:100%}
        .lms-material-program-filter .button{flex:0 0 auto;gap:.45rem}
        .lms-material-program-filter .button svg{width:17px;height:17px}
        .lms-material-lecturer-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.85rem}
        .lms-material-lecturer-card{min-width:0;overflow:hidden;border:1px solid #e0e9e2;border-radius:13px;background:#fff;box-shadow:0 8px 22px #173d270b;transition:transform .18s ease,box-shadow .18s ease}
        .lms-material-lecturer-card:hover{transform:translateY(-2px);box-shadow:0 16px 34px #173d2717}
        .lms-material-lecturer-profile{display:flex;align-items:center;gap:.55rem;padding:.8rem .85rem;border-bottom:1px solid #edf2ee;background:linear-gradient(110deg,#f7faf7,#fff)}
        .lms-material-avatar{display:grid;width:38px;height:38px;flex:0 0 38px;place-items:center;border-radius:12px;background:linear-gradient(145deg,#2f8052,#63a77a);color:#fff;font-size:.86rem;font-weight:800;letter-spacing:.04em}
        .lms-material-lecturer-profile h3{margin:0;color:#203d2c;font-size:.86rem;line-height:1.3}
        .lms-material-lecturer-profile p{margin:.15rem 0 0;color:#7b887e;font-size:.68rem}
        .lms-material-count{margin-left:auto;padding:.3rem .45rem;border:1px solid #d9ecde;border-radius:999px;background:#eff8f0;color:#32734a;font-size:.64rem;font-weight:700;white-space:nowrap}
        .lms-material-lecturer-courses{padding:.65rem .8rem .8rem}
        .lms-material-course-heading{margin:0 0 .35rem;color:#829087;font-size:.62rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase}
        .lms-material-course-link{display:flex;align-items:center;gap:.5rem;padding:.58rem .35rem;border-top:1px solid #f0f3f0;color:inherit;text-decoration:none;transition:background .15s ease}
        .lms-material-course-link:hover{border-radius:9px;background:#f5faf6}
        .lms-material-course-icon{display:grid;width:30px;height:30px;flex:0 0 30px;place-items:center;border-radius:9px;background:#eff5f0;color:#4b815e}
        .lms-material-course-icon svg,.lms-material-course-arrow{width:16px;height:16px}
        .lms-material-course-name{display:grid;min-width:0;flex:1;gap:.18rem}
        .lms-material-course-name strong{color:#2e4636;font-size:.75rem;line-height:1.35}
        .lms-material-course-name small{color:#879289;font-size:.64rem;line-height:1.3}
        .lms-material-course-count{color:#38724c;font-size:.66rem;font-weight:800;text-align:right;white-space:nowrap}
        .lms-material-course-count span{font-weight:500}
        .lms-material-course-arrow{flex:0 0 16px;color:#9ba9a0;transition:transform .15s ease}
        .lms-material-course-link:hover .lms-material-course-arrow{transform:translateX(3px);color:#32734a}
        .lms-material-library-heading{display:grid;grid-template-columns:auto 1fr;align-items:center;gap:1rem;padding:1.1rem 1.25rem;border:1px solid #dce8e0;border-radius:14px;background:#f7faf7}
        .lms-material-library-heading .button{width:max-content}
        .lms-material-library-heading h2{margin-top:.1rem}
        .lms-material-library-heading .lms-material-library-kicker{margin:0}
        .lms-material-resource-grid{display:grid;gap:.8rem}
        .lms-material-resource-card{display:flex;align-items:center;gap:1rem;padding:1rem 1.1rem;border:1px solid #e2eae3;border-radius:13px;background:#fff;box-shadow:0 5px 18px #173d2709}
        .lms-material-resource-icon{display:grid;width:44px;height:44px;flex:0 0 44px;place-items:center;border-radius:12px;background:#edf5ef;color:#35794e}
        .lms-material-resource-icon svg{width:21px;height:21px}
        .lms-material-resource-content{min-width:0;flex:1}
        .lms-material-resource-label{color:#4c8a60;font-size:.66rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
        .lms-material-resource-content h3{margin:.2rem 0;color:#263e2f;font-size:.94rem}
        .lms-material-resource-content p{margin:0;color:#849087;font-size:.74rem;overflow-wrap:anywhere}
        .lms-material-download{flex:0 0 auto;gap:.4rem}
        .lms-material-download svg{width:16px;height:16px}
        .lms-material-unavailable{color:#879289;font-size:.75rem}
        .lms-material-empty{display:grid;justify-items:center;gap:.5rem;padding:3rem 1.5rem;border:1px dashed #cddbd0;border-radius:15px;background:#f9fbf9;color:#64756a;text-align:center}
        .lms-material-empty svg{width:32px;height:32px;margin-bottom:.25rem;color:#76a483}
        .lms-material-empty strong{color:#355642;font-size:.95rem}
        .lms-material-empty p{max-width:440px;margin:0;color:#849087;font-size:.82rem;line-height:1.5}
        .lms-task-submit-action{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .65rem;font-size:.8rem;white-space:nowrap}
        .lms-task-submit-action [data-lucide]{width:16px;height:16px}
        .lms-task-submit-dialog{width:min(620px,calc(100vw - 2rem))}
        .lms-task-submit-heading{align-items:flex-start}
        .lms-task-submit-heading h2{margin:0;color:#263b2d;font-size:1.15rem;overflow-wrap:anywhere}
        .lms-task-submit-eyebrow{margin:0 0 .3rem;color:#477d65;font-size:.7rem;font-weight:800;text-transform:uppercase}
        .lms-task-submit-body{gap:1rem}
        .lms-task-submit-body label{display:grid;gap:.45rem;color:#34483a;font-weight:700}
        .lms-task-submit-body textarea,.lms-task-submit-body input{width:100%;padding:.75rem .85rem;border:1px solid #cbd8ce;border-radius:6px;background:#fff;color:#26382d;font:inherit;font-weight:400}
        .lms-task-submit-body textarea{min-height:145px;resize:vertical;line-height:1.55}
        .lms-task-submit-body textarea:focus,.lms-task-submit-body input:focus{border-color:#477d65;outline:3px solid #477d6526}
        .lms-task-submit-deadline{display:flex;justify-content:space-between;gap:.75rem;padding:.75rem .85rem;border:1px solid #dce5df;border-radius:5px;background:#f4f8f4;color:#617065;font-size:.8rem}
        .lms-task-submit-deadline strong{color:#33483a;text-align:right}
        .lms-task-submit-status{margin:0;color:#5b6d60;font-size:.85rem}
        .lms-page .table-wrap{overflow-x:auto}
        .lms-dropzone{position:relative;min-height:105px;align-content:center;justify-items:center;padding:1rem;border:2px dashed #94a3b8;border-radius:10px;background:#f8fafc;text-align:center;cursor:pointer}
        .lms-dropzone.is-dragging{border-color:#2563eb;background:#eff6ff}
        .lms-dropzone-input{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer}
        .lms-dropzone:focus-within{outline:2px solid #2563eb;outline-offset:2px}
        @media(max-width:600px){.lms-inline-fields{grid-template-columns:1fr}.lms-filter-bar{align-items:stretch;gap:.75rem}.lms-filter-field{flex-basis:100%;max-width:none}}
        @media(max-width:1000px){.lms-material-lecturer-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:600px){.lms-material-lecturer-grid{grid-template-columns:1fr}.lms-material-library-intro{align-items:flex-start;padding:1rem}.lms-material-library-heading{grid-template-columns:1fr;align-items:start}.lms-material-library-heading .button{width:100%;justify-content:center}.lms-material-resource-card{align-items:flex-start;gap:.7rem;padding:.8rem}.lms-material-resource-icon{width:38px;height:38px;flex-basis:38px}.lms-material-download{align-self:center;padding:.55rem}.lms-material-download>span:last-child{display:none}.lms-material-course-link{gap:.45rem;padding:.7rem .25rem}.lms-material-course-count{font-size:.67rem}.lms-material-program-filter{align-items:stretch}.lms-material-program-filter .button{width:100%;justify-content:center}}
    </style>
@endpush

@push('scripts')
    <script>
        const materialLibrarySearch = document.querySelector('[data-material-library-search]');
        const materialLibraryCards = [...document.querySelectorAll('[data-material-lecturer]')];
        const materialLibraryEmpty = document.querySelector('[data-material-library-empty]');
        materialLibrarySearch?.addEventListener('input', () => {
            const searchTerm = materialLibrarySearch.value.trim().toLocaleLowerCase('id');
            let visibleCardCount = 0;
            materialLibraryCards.forEach((card) => {
                const matchesLecturer = card.dataset.materialLecturer.toLocaleLowerCase('id').includes(searchTerm);
                const courses = [...card.querySelectorAll('[data-material-course]')];
                let visibleCourseCount = 0;
                courses.forEach((course) => {
                    const isVisible = !searchTerm || matchesLecturer
                        || course.dataset.materialCourse.toLocaleLowerCase('id').includes(searchTerm);
                    course.hidden = !isVisible;
                    if (isVisible) visibleCourseCount += 1;
                });
                card.hidden = Boolean(searchTerm) && !matchesLecturer && visibleCourseCount === 0;
                if (!card.hidden) visibleCardCount += 1;
            });
            if (materialLibraryEmpty) materialLibraryEmpty.hidden = visibleCardCount > 0;
        });
        document.querySelector('[data-lms-task-modal][data-reopen="true"]')?.showModal();
        document.querySelectorAll('[data-lms-task-modal] form').forEach((form) => {
            form.addEventListener('submit', () => form.closest('dialog')?.close());
        });
        document.querySelectorAll('[data-lms-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const dialog = document.getElementById(button.dataset.lmsOpen);
                if (!dialog) return;
                dialog.showModal();
                document.dispatchEvent(new CustomEvent('simak:initialize-searchable-selects', {detail: {root: dialog}}));
            });
        });
        document.querySelectorAll('[data-lms-close]').forEach((button) => {
            button.addEventListener('click', () => button.closest('dialog')?.close());
        });
        const bindQuestionOptions = (root = document) => {
            root.querySelectorAll('#lms-create-modal [name="jenis_soal"], [data-lms-question-form] [name="jenis_soal"]').forEach((select) => {
                const options = select.closest('form')?.querySelector('[data-question-options]');
                if (!options) return;
                const toggleOptions = () => { options.hidden = select.value !== 'pilihan_ganda'; };
                if (select.dataset.questionOptionsBound !== 'true') {
                    select.addEventListener('change', toggleOptions);
                    select.dataset.questionOptionsBound = 'true';
                }
                toggleOptions();
            });
        };
        bindQuestionOptions();
        document.addEventListener('simak:initialize-searchable-selects', (event) => {
            bindQuestionOptions(event.detail?.root || document);
        });
        document.querySelectorAll('[data-lms-dropzone]').forEach((dropzone) => {
            const input = dropzone.querySelector('input[type=file]');
            const fileName = dropzone.querySelector('[data-lms-file-name]');
            input?.addEventListener('change', () => {
                fileName.textContent = input.files?.[0]?.name || 'Belum ada file dipilih';
            });
            dropzone.addEventListener('dragover', (event) => {
                event.preventDefault();
                dropzone.classList.add('is-dragging');
            });
            dropzone.addEventListener('dragleave', () => dropzone.classList.remove('is-dragging'));
            dropzone.addEventListener('drop', (event) => {
                event.preventDefault();
                dropzone.classList.remove('is-dragging');
                if (!input || !event.dataTransfer?.files.length) return;
                input.files = event.dataTransfer.files;
                input.dispatchEvent(new Event('change', {bubbles: true}));
            });
        });
    </script>
@endpush
