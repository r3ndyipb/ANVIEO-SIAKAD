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
                        <button class="button button-secondary" type="button" data-lms-open="lms-question-upload-modal"><span>Upload Soal</span><span data-lucide="upload" aria-hidden="true"></span></button>
                    @endif
                    <button class="button button-primary" type="button" data-lms-open="lms-create-modal"><span>Tambah {{ $title }}</span><span data-lucide="plus" aria-hidden="true"></span></button>
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
                    <div class="lms-material-filter-field">
                        <label for="material-program-select">Program studi</label>
                        <select id="material-program-select" name="id_prodi" data-searchable-select required>
                            <option value="">Pilih program studi untuk menampilkan dosen</option>
                            @foreach ($materialPrograms as $program)
                                <option value="{{ $program->id_prodi }}" @selected((string) $selectedProgramId === (string) $program->id_prodi)>{{ $program->nama_program_studi }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="button button-primary"><span>Tampilkan dosen</span><span data-lucide="users-round" aria-hidden="true"></span></button>
                </form>
            @endif
            @if ($materialProgramFilterRequired && $selectedProgramId === '')
                <div class="lms-material-empty">
                    <span data-lucide="school" aria-hidden="true"></span>
                    <strong>Pilih program studi terlebih dahulu</strong>
                    <p>Daftar dosen dan mata kuliah akan dimuat setelah Anda memilih program studi.</p>
                </div>
            @elseif ($selectedLecturerId !== '' && $selectedCourseId !== '')
                <section class="lms-material-library">
                    <div class="lms-material-library-heading">
                        <a class="button button-secondary" href="{{ route('lms.materials.index', $materialProgramFilterRequired ? ['id_prodi' => $selectedProgramId] : []) }}"><span>Kembali ke dosen</span><span data-lucide="arrow-left" aria-hidden="true"></span></a>
                        <div class="lms-material-library-heading-info">
                            <p class="lms-material-library-kicker">{{ $selectedMaterialCourse->kode_mata_kuliah }} · {{ $selectedMaterialCourse->nama_program_studi ?: 'Program studi' }}</p>
                            <h2>{{ $selectedMaterialCourse->nama_mata_kuliah }}</h2>
                            @php
                                $selectedLecturer = $materialLibrary->firstWhere('id_dosen', $selectedLecturerId);
                            @endphp
                            <p class="lms-material-library-sub">{{ \App\Support\LecturerName::format($selectedLecturer->nama_dosen, $selectedLecturer->gelar_depan, $selectedLecturer->gelar_belakang) }} · <span class="badge">{{ $records->count() }} materi tersedia</span></p>
                        </div>
                    </div>
                    @if ($records->isEmpty())
                        <div class="lms-material-empty">
                            <span data-lucide="folder-open" aria-hidden="true"></span>
                            <strong>Belum ada materi tersedia</strong>
                            <p>Dosen belum mengunggah materi untuk mata kuliah ini.</p>
                        </div>
                    @else
                        <div class="lms-material-resource-grid">
                            @foreach ($records as $item)
                                <article class="lms-material-resource-card">
                                    <div class="lms-material-resource-icon">
                                        @php
                                            $ext = strtolower(pathinfo($item->nama_berkas ?? '', PATHINFO_EXTENSION));
                                        @endphp
                                        @if (in_array($ext, ['ppt', 'pptx', 'odp']))
                                            <span data-lucide="presentation" aria-hidden="true"></span>
                                        @elseif (in_array($ext, ['doc', 'docx', 'odt', 'rtf']))
                                            <span data-lucide="file-type" aria-hidden="true"></span>
                                        @else
                                            <span data-lucide="file-text" aria-hidden="true"></span>
                                        @endif
                                    </div>
                                    <div class="lms-material-resource-content">
                                        <div class="lms-material-resource-header">
                                            <span class="lms-material-resource-label">Materi kuliah</span>
                                            @if ($ext)
                                                <span class="lms-material-type-pill">{{ strtoupper($ext) }}</span>
                                            @endif
                                        </div>
                                        <h3>{{ $item->judul }}</h3>
                                        @if ($item->deskripsi)
                                            <p class="lms-material-desc">{{ \Illuminate\Support\Str::limit($item->deskripsi, 130) }}</p>
                                        @endif
                                        <p class="lms-material-file-info">{{ $item->nama_berkas ?: 'Dokumen materi' }} · {{ \App\Support\LecturerName::format($item->nama_dosen, $item->gelar_depan, $item->gelar_belakang) }}</p>
                                        <div class="lms-material-card-footer">
                                            @if ($item->path_berkas)
                                                <a class="button button-primary lms-material-download" href="{{ route('lms.materials.download', $item->id_materi) }}" data-page-loader-skip aria-label="Unduh materi {{ $item->nama_berkas }}" title="Unduh materi"><span>Unduh</span><span data-lucide="download" aria-hidden="true"></span></a>
                                            @else
                                                <span class="lms-material-unavailable">File belum tersedia</span>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
            @elseif ($materialLibrary->isEmpty())
                <div class="lms-material-empty">
                    <span data-lucide="library" aria-hidden="true"></span>
                    <strong>Belum ada dosen untuk ditampilkan</strong>
                    <p>Daftar dosen akan muncul jika tersedia jadwal mengajar dalam cakupan akses akun Anda.</p>
                </div>
            @else
                <section class="lms-material-library">
                    <div class="lms-material-library-intro">
                        <span class="lms-material-library-intro-icon"><span data-lucide="library" aria-hidden="true"></span></span>
                        <div>
                            <p class="lms-material-library-kicker">Perpustakaan digital</p>
                            <h2>Materi dari dosen</h2>
                            <p>Pilih mata kuliah untuk melihat dan mengunduh materi yang tersedia.</p>
                        </div>
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
                                    <div class="lms-material-lecturer-info">
                                        <h3>{{ \App\Support\LecturerName::format($lecturer->nama_dosen, $lecturer->gelar_depan, $lecturer->gelar_belakang) ?: 'Dosen' }}</h3>
                                        <p>{{ $lecturer->nidn ? 'NIDN '.$lecturer->nidn : 'Dosen pengampu' }}</p>
                                    </div>
                                    <span class="lms-material-count">{{ $lecturer->material_count }} materi</span>
                                </header>
                                <div class="lms-material-lecturer-courses">
                                    <p class="lms-material-course-heading">Mata kuliah yang pernah diampu</p>
                                    @foreach ($lecturer->courses as $course)
                                        <a class="lms-material-course-link" data-material-course="{{ $course->nama_mata_kuliah }}" href="{{ route('lms.materials.index', ['id_dosen' => $lecturer->id_dosen, 'id_matkul' => $course->id_matkul, 'id_prodi' => $course->id_prodi]) }}">
                                            <span class="lms-material-course-icon"><span data-lucide="book-open" aria-hidden="true"></span></span>
                                            <span class="lms-material-course-name">
                                                <strong>{{ $course->nama_mata_kuliah }}</strong>
                                                <small>{{ $course->kode_mata_kuliah }} · {{ $course->nama_program_studi ?: 'Program studi' }}</small>
                                            </span>
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
                                <th>Materi & Mata Kuliah</th><th>Program Studi</th><th>Dosen</th><th>Status</th><th class="text-right">Aksi</th>
                            @elseif ($module === 'pertemuan')
                                <th>Pertemuan</th><th>Mata Kuliah / Kelas</th><th>Tanggal dan Waktu</th><th>Absen Mandiri</th>
                                @if ($isStudent)
                                    <th>Keterangan Kehadiran</th>
                                @endif
                                <th class="text-right">Aksi</th>
                            @elseif ($module === 'tugas')
                                <th>Nama Tugas</th><th>Mata Kuliah / Kelas</th><th>Batas Pengumpulan</th>
                                @if ($isStudent)
                                    <th>Status Pengumpulan</th><th>Nilai / Komentar Dosen</th>
                                @else
                                    <th>Status</th>
                                @endif
                                <th class="text-right">Aksi</th>
                            @elseif ($module === 'bank-soal')
                                <th>Pertanyaan</th><th>Mata Kuliah</th><th>Jenis</th><th>Status</th><th class="text-right">Aksi</th>
                            @else
                                <th>Topik Diskusi</th><th>Mata Kuliah / Kelas</th><th>Status</th><th>Tanggal Terakhir Diskusi</th><th class="text-right">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $item)
                            <tr>
                                @if ($module === 'materi')
                                    <td>
                                        <div class="lms-merged-cell">
                                            <div class="lms-merged-title-row">
                                                <strong class="lms-merged-title">{{ $item->judul }}</strong>
                                                <span class="lms-merged-icon" aria-hidden="true"><span data-lucide="file-text"></span></span>
                                            </div>
                                            <div class="lms-merged-course-row">
                                                @if ($item->kode_mata_kuliah)
                                                    <span class="lms-course-code-badge">{{ $item->kode_mata_kuliah }}</span>
                                                @endif
                                                <span class="lms-course-name">{{ $item->nama_mata_kuliah ?: 'Mata kuliah tidak ditentukan' }}</span>
                                            </div>
                                            @if ($item->nama_berkas)
                                                <div class="lms-merged-file-meta">
                                                    <span class="subtext">{{ $item->nama_berkas }} · {{ number_format(($item->ukuran_berkas ?? 0) / 1024, 0) }} KB</span>
                                                </div>
                                            @elseif ($item->deskripsi)
                                                <div class="lms-merged-file-meta">
                                                    <span class="subtext">{{ \Illuminate\Support\Str::limit($item->deskripsi, 80) }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
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
                                <td class="text-right table-actions-cell">
                                    <div class="table-actions">
                                        @if ($module === 'materi')
                                            @if ($item->path_berkas)
                                                <a class="table-icon-button" href="{{ route('lms.materials.download', $item->id_materi) }}" data-page-loader-skip aria-label="Unduh materi {{ $item->nama_berkas }}" title="Unduh materi ({{ $item->nama_berkas }})"><span data-lucide="file-down" aria-hidden="true"></span></a>
                                            @endif
                                            @if ($canManage)
                                                <button class="table-icon-button" type="button" data-lms-open="lms-material-edit-{{ $item->id_materi }}" aria-label="Ubah materi" title="Ubah materi"><span data-lucide="pencil" aria-hidden="true"></span></button>
                                                @if ($item->used_in_meeting)
                                                    <span class="table-icon-button is-disabled" title="Materi ini sudah digunakan pada pertemuan mata kuliah, hanya bisa dilakukan edit." aria-label="Materi terkunci"><span data-lucide="lock" aria-hidden="true"></span><span class="sr-only">Materi ini sudah digunakan pada pertemuan mata kuliah, hanya bisa dilakukan edit.</span></span>
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
                                                        <span>Kumpul tugas</span><span data-lucide="upload" aria-hidden="true"></span>
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
                                    </div>
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
                        <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close><span>Batal</span></button><button class="button button-primary" type="submit"><span>Simpan perubahan</span><span data-lucide="check" aria-hidden="true"></span></button></div>
                    </form>
                </dialog>
                @unless ($item->used_in_meeting)
                    <dialog class="lms-dialog lms-confirm-dialog" id="lms-material-delete-{{ $item->id_materi }}">
                        <div class="lms-dialog-heading"><h2>Hapus materi</h2></div>
                        <p>Yakin ingin menghapus materi “{{ $item->judul }}”?</p>
                        <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close><span>Batal</span></button><form method="post" action="{{ route('lms.materials.destroy', $item->id_materi) }}">@csrf @method('DELETE')<button class="button button-danger" type="submit"><span>Hapus materi</span><span data-lucide="trash-2" aria-hidden="true"></span></button></form></div>
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
                    <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close><span>Batal</span></button><button class="button button-primary" type="submit"><span>{{ $studentSubmission ? 'Simpan perubahan' : 'Kumpulkan tugas' }}</span><span data-lucide="send" aria-hidden="true"></span></button></div>
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
                    <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close><span>Batal</span></button><button class="button button-primary" type="submit"><span>Simpan perubahan</span><span data-lucide="check" aria-hidden="true"></span></button></div>
                </form>
            </dialog>
            <dialog class="lms-dialog lms-confirm-dialog" id="lms-task-delete-{{ $item->id_tugas }}">
                <div class="lms-dialog-heading"><h2>Hapus tugas</h2></div>
                <p>Yakin ingin menghapus tugas “{{ $item->judul }}”?</p>
                <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close><span>Batal</span></button><form method="post" action="{{ route('lms.tasks.destroy', $item->id_tugas) }}">@csrf @method('DELETE')<button class="button button-danger" type="submit"><span>Hapus tugas</span><span data-lucide="trash-2" aria-hidden="true"></span></button></form></div>
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
                    <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close><span>Batal</span></button><button class="button button-primary" type="submit"><span>Simpan perubahan</span><span data-lucide="check" aria-hidden="true"></span></button></div>
                </form>
            </dialog>
            <dialog class="lms-dialog lms-confirm-dialog" id="lms-meeting-delete-{{ $item->id_pertemuan }}">
                <div class="lms-dialog-heading"><h2>Hapus pertemuan</h2></div>
                <p>Yakin ingin menghapus Pertemuan {{ $item->pertemuan_ke }} — {{ $item->judul }}?</p>
                <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close><span>Batal</span></button><form method="post" action="{{ route('lms.meetings.destroy', $item->id_pertemuan) }}">@csrf @method('DELETE')<button class="button button-danger" type="submit"><span>Hapus pertemuan</span><span data-lucide="trash-2" aria-hidden="true"></span></button></form></div>
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
                    <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close><span>Batal</span></button><button class="button button-primary" type="submit"><span>Simpan perubahan</span><span data-lucide="check" aria-hidden="true"></span></button></div>
                </form>
            </dialog>
            <dialog class="lms-dialog lms-confirm-dialog" id="lms-discussion-delete-{{ $item->id_diskusi }}">
                <div class="lms-dialog-heading"><h2>Hapus diskusi</h2></div>
                <p>Yakin ingin menghapus diskusi “{{ $item->judul }}” beserta percakapannya?</p>
                <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close><span>Batal</span></button><form method="post" action="{{ route('lms.discussions.destroy', $item->id_diskusi) }}">@csrf @method('DELETE')<button class="button button-danger" type="submit"><span>Hapus diskusi</span><span data-lucide="trash-2" aria-hidden="true"></span></button></form></div>
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
                        <button class="button button-secondary" type="button" data-lms-close><span>Batal</span></button>
                        <button class="button button-primary" type="submit"><span>Simpan perubahan</span><span data-lucide="check" aria-hidden="true"></span></button>
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
                    <button class="button button-secondary" type="button" data-lms-close><span>Batal</span></button>
                    <button class="button button-primary" type="submit"><span>Upload soal</span><span data-lucide="upload" aria-hidden="true"></span></button>
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
                    <button class="button button-secondary" type="button" data-lms-close><span>Batal</span></button>
                    <button class="button button-primary" type="submit"><span>Simpan</span><span data-lucide="check" aria-hidden="true"></span></button>
                </div>
            </form>
        </dialog>
    @endif
@endsection

@push('styles')
    <style>.lms-question-edit-dialog[open]{display:flex;flex-direction:column}</style>
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
