@extends('layouts.simak')

@php
    $titles = ['materi' => 'Materi Kuliah', 'pertemuan' => 'Pertemuan Kuliah', 'tugas' => 'Tugas', 'bank-soal' => 'Bank Soal', 'diskusi' => 'Diskusi'];
    $title = $titles[$module];
    $recordId = match ($module) {
        'materi' => $record->id_materi,
        'pertemuan' => $record->id_pertemuan,
        'tugas' => $record->id_tugas,
        'bank-soal' => $record->id_soal,
        'diskusi' => $record->id_diskusi,
    };
    $indexRoute = match ($module) {
        'materi' => 'lms.materials.index',
        'pertemuan' => 'lms.meetings.index',
        'tugas' => 'lms.tasks.index',
        'bank-soal' => 'lms.questions.index',
        'diskusi' => 'lms.discussions.index',
    };
@endphp

@section('title', $title.' | LMS Virtual Class')
@section('topbar_title', 'LMS Virtual Class')

@section('content')
    @include('lms._notifications')
    <div class="lms-page">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ $title }}</p>
                <h1>{{ $record->judul ?? \Illuminate\Support\Str::limit(strip_tags($record->pertanyaan), 110) }}</h1>
                @if (isset($record->nama_mata_kuliah))
                    <p class="subtitle">{{ $record->kode_mata_kuliah }} — {{ $record->nama_mata_kuliah }} · {{ $record->nama_kelas ?? $record->nama_program_studi }}</p>
                @endif
            </div>
            <a class="button button-secondary" href="{{ route($indexRoute, array_merge($studentActivityFilters, $activityFilters)) }}">Kembali ke {{ $title }}</a>
        </div>

        @if ($isShortSemester)
            @include('components.short-semester-feeder-alert')
        @endif

        @if ($user->role === 'dosen' && in_array($module, ['pertemuan', 'tugas', 'bank-soal', 'diskusi'], true) && ! $canManage)
            <div class="student-activity-readonly" role="status">
                <strong>Tampilan baca-saja</strong>
                <span>Pengelolaan LMS hanya tersedia pada kalender akademik atau SP yang aktif.</span>
            </div>
        @endif

        @if ($isStudent && in_array($module, ['pertemuan', 'tugas', 'diskusi'], true) && ! $studentActivityWritable)
            <div class="student-activity-readonly" role="status">
                <strong>Tampilan baca-saja</strong>
                <span>Kalender akademik atau SP ini sudah tidak aktif. Aktivitas hanya dapat dilihat dan tidak dapat diubah.</span>
            </div>
        @endif

        @if ($module === 'materi')
            <section class="lms-panel">
                <div class="lms-meta"><span>Status: {{ ucfirst($record->status_publikasi) }}</span></div>
                <div class="lms-prose">{!! nl2br(e($record->deskripsi ?: 'Tidak ada deskripsi.')) !!}</div>
                <div class="lms-actions">
                    @if ($record->path_berkas)
                        <a class="table-icon-button" href="{{ route('lms.materials.download', $record->id_materi) }}" data-page-loader-skip aria-label="Unduh {{ $record->nama_berkas }}" title="Unduh {{ $record->nama_berkas }}"><span data-lucide="file-down" aria-hidden="true"></span></a>
                    @endif
                    @if ($canManage)
                        <button class="table-icon-button" type="button" data-lms-open="lms-edit-modal" aria-label="Ubah materi" title="Ubah materi"><span data-lucide="pencil" aria-hidden="true"></span></button>
                        @if ($record->used_in_meeting)
                            <span class="subtext">Materi ini sudah digunakan pada pertemuan mata kuliah, hanya bisa dilakukan edit.</span>
                        @else
                            <button class="table-icon-button reset" type="button" data-lms-open="lms-delete-modal" aria-label="Hapus materi" title="Hapus materi"><span data-lucide="trash-2" aria-hidden="true"></span></button>
                        @endif
                    @endif
                </div>
            </section>
        @elseif ($module === 'pertemuan' && $isStudent)
            @php
                $meetingDate = \Illuminate\Support\Carbon::parse($record->tanggal_kuliah)->locale('id');
            @endphp
            <section class="lms-student-meeting-detail">
                <header class="student-meeting-hero">
                    <div class="student-meeting-date-block" aria-label="{{ $meetingDate->translatedFormat('l, d F Y') }}">
                        <span>{{ $meetingDate->translatedFormat('D') }}</span>
                        <strong>{{ $meetingDate->format('d') }}</strong>
                        <small>{{ $meetingDate->translatedFormat('M Y') }}</small>
                    </div>
                    <div class="student-meeting-hero-content">
                        <div class="student-meeting-hero-label">
                            <span>Pertemuan {{ $record->pertemuan_ke }}</span>
                            <span class="status-chip active">{{ ucfirst($record->status_pertemuan) }}</span>
                        </div>
                        <h2>{{ $record->kode_mata_kuliah }} · {{ $record->nama_mata_kuliah }}</h2>
                        <p>{{ $record->nama_kelas }} · {{ $record->nama_program_studi }} · {{ $record->namatahun }}</p>
                    </div>
                </header>

                <div class="student-meeting-layout">
                    <div class="student-meeting-main">
                        <section class="student-meeting-section" aria-labelledby="meeting-schedule-heading">
                            <h2 id="meeting-schedule-heading">Jadwal perkuliahan</h2>
                            <div class="student-meeting-facts">
                                <div><span>Tanggal</span><strong>{{ $meetingDate->translatedFormat('l, d F Y') }}</strong></div>
                                <div><span>Waktu</span><strong>{{ substr($record->waktu_mulai, 0, 5) }}–{{ substr($record->waktu_selesai, 0, 5) }} WIT</strong></div>
                                <div><span>Kelas</span><strong>{{ $record->nama_kelas }} · {{ $record->nama_program_studi }}</strong></div>
                            </div>
                        </section>

                        <section class="student-meeting-section" aria-labelledby="meeting-materials-heading">
                            <div class="student-meeting-section-heading">
                                <h2 id="meeting-materials-heading">Materi pertemuan</h2>
                                <span>{{ $record->materials->count() }} materi</span>
                            </div>
                            @if ($record->materials->isNotEmpty())
                                <ul class="student-meeting-material-list">
                                    @foreach ($record->materials as $material)
                                        <li>
                                            <span class="student-meeting-material-mark" aria-hidden="true">FILE</span>
                                            <span class="student-meeting-material-name">{{ $material->judul }}</span>
                                            <a href="{{ route('lms.show', ['module' => 'materi', 'record' => $material->id_materi]) }}" aria-label="Buka materi {{ $material->judul }}">Buka <span aria-hidden="true">→</span></a>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="student-meeting-muted">Belum ada materi pendukung untuk pertemuan ini.</p>
                            @endif
                        </section>

                        @if ($record->tautan_video)
                            <section class="student-meeting-link-panel">
                                <div><span class="student-meeting-kicker">Ruang kelas daring</span><strong>Video conference</strong></div>
                                <a class="button button-secondary" href="{{ $record->tautan_video }}" target="_blank" rel="noopener noreferrer">Buka ruang kelas</a>
                            </section>
                        @endif

                        @if ($record->exam && ($studentActivityWritable || ! $isStudent))
                            <section class="student-meeting-link-panel student-meeting-exam-panel">
                                <div><span class="student-meeting-kicker">Evaluasi kelas</span><strong>{{ $record->exam->judul }}</strong></div>
                                <a class="button button-primary" href="{{ route('lms.exams.show', $record->exam->id_ujian) }}">Lihat ujian</a>
                            </section>
                        @elseif ($record->exam)
                            <section class="student-meeting-link-panel student-meeting-exam-panel"><div><span class="student-meeting-kicker">Evaluasi kelas · Baca-saja</span><strong>{{ $record->exam->judul }}</strong></div></section>
                        @endif
                    </div>

                    <aside class="student-meeting-attendance-panel" aria-labelledby="meeting-attendance-heading">
                        <span class="student-meeting-kicker">Kehadiran</span>
                        <h2 id="meeting-attendance-heading">Absensi pertemuan</h2>
                        @if ($record->studentAttendance && $record->studentAttendance->status_kehadiran)
                            <div class="student-meeting-attendance-confirmation" role="status" aria-live="polite">
                                <strong>Anda sudah melakukan absen</strong>
                                <span>Status kehadiran tercatat: {{ ucfirst($record->studentAttendance->status_kehadiran) }}</span>
                                @if ($record->studentAttendance->waktu_absen)
                                    <small>{{ \Illuminate\Support\Carbon::parse($record->studentAttendance->waktu_absen, 'Asia/Jayapura')->format('d/m/Y H:i') }} WIT · {{ $record->studentAttendance->metode_absen === 'mandiri' ? 'Absensi mandiri' : 'Dicatat oleh dosen' }}</small>
                                @endif
                            </div>
                        @elseif ($record->absen_dibuka && $studentActivityWritable)
                            <p>Tombol hadir hanya aktif selama jadwal kuliah berlangsung.</p>
                            @if ($attendanceOpenNow)
                                <form method="post" action="{{ route('lms.attendance.self', $record->id_pertemuan) }}">
                                    @csrf
                                    <button class="button button-primary" type="submit">Saya hadir</button>
                                </form>
                            @else
                                <span class="student-meeting-attendance-state">Di luar waktu absensi</span>
                            @endif
                        @else
                            @if ($studentActivityWritable)
                                <p>Dosen akan mencatat kehadiran pada pertemuan ini.</p>
                                <span class="student-meeting-attendance-state">Absensi mandiri ditutup</span>
                            @else
                                <p>Kalender akademik atau SP ini sudah tidak aktif.</p>
                                <span class="student-meeting-attendance-state">Tampilan baca-saja</span>
                            @endif
                        @endif
                    </aside>
                </div>
            </section>
        @elseif ($module === 'pertemuan')
            <section class="lms-panel">
                <div class="lms-meta"><span>Pertemuan {{ $record->pertemuan_ke }} · {{ $record->tanggal_kuliah }}</span><span>{{ substr($record->waktu_mulai, 0, 5) }}–{{ substr($record->waktu_selesai, 0, 5) }} WIT</span><span>Absensi mandiri {{ $record->absen_dibuka ? 'dibuka' : 'ditutup' }}</span></div>
                @if ($record->tautan_video)<p><a href="{{ $record->tautan_video }}" target="_blank" rel="noopener noreferrer">Buka video conference</a></p>@endif
                @if ($record->materials->isNotEmpty())
                    <div><strong>Materi terkait</strong><ul>@foreach ($record->materials as $material)<li><a href="{{ route('lms.show', ['module' => 'materi', 'record' => $material->id_materi]) }}">{{ $material->judul }}</a></li>@endforeach</ul></div>
                @endif
                @if ($record->exam)
                    <p><a class="button button-primary" href="{{ route('lms.exams.show', $record->exam->id_ujian) }}">{{ $isStudent ? 'Ikuti ujian' : 'Lihat ujian' }}: {{ $record->exam->judul }}</a></p>
                @elseif ($canManage)
                    <a class="button button-primary" href="{{ route('lms.exams.create', $record->id_pertemuan) }}">Buat ujian</a>
                @endif
                @if ($attendanceOpenNow)
                    <form method="post" action="{{ route('lms.attendance.self', $record->id_pertemuan) }}">@csrf<button class="button button-primary" type="submit">Saya hadir</button></form>
                @endif
                @if ($canViewRoster && $record->students->isNotEmpty())
                    <h2>Daftar absensi kelas</h2>
                    <div class="table-wrap"><table data-datatable="lms-attendance"><thead><tr><th>NPM</th><th>Nama</th><th>Kehadiran</th><th>Proses</th></tr></thead><tbody>
                        @foreach ($record->students as $student)
                            <tr><td>{{ $student->nim }}</td><td>{{ $student->nama_mahasiswa }}</td><td>{{ $student->status_kehadiran ?? 'Belum absen' }}</td><td>
                                @if ($canManage)
                                    <form method="post" action="{{ route('lms.attendance.mark', ['record' => $record->id_pertemuan, 'student' => $student->id_mahasiswa]) }}" class="lms-inline-form">@csrf @method('PUT')
                                        <select name="status_kehadiran" data-searchable-select required><option value="hadir">Hadir</option><option value="izin">Izin</option><option value="sakit">Sakit</option><option value="alpa">Alpa</option></select>
                                        <button class="table-icon-button" type="submit" aria-label="Simpan absensi" title="Simpan absensi"><span data-lucide="save" aria-hidden="true"></span></button>
                                    </form>
                                @else
                                    —
                                @endif
                            </td></tr>
                        @endforeach
                    </tbody></table></div>
                @endif
                @if ($canManage)
                    <div class="lms-actions"><button class="table-icon-button" type="button" data-lms-open="lms-edit-modal" aria-label="Ubah pertemuan" title="Ubah pertemuan"><span data-lucide="pencil" aria-hidden="true"></span></button><button class="table-icon-button reset" type="button" data-lms-open="lms-delete-modal" aria-label="Hapus pertemuan" title="Hapus pertemuan"><span data-lucide="trash-2" aria-hidden="true"></span></button></div>
                @endif
            </section>
        @elseif ($module === 'tugas' && $canManage)
            @php
                $taskSubmissionCount = $record->submissions->count();
                $taskNeedsGradingCount = $record->submissions->where('status_pengumpulan', 'terkumpul')->count();
                $taskGradedCount = $record->submissions->where('status_pengumpulan', 'dinilai')->count();
            @endphp
            <section class="lms-teacher-task-detail">
                <header class="lms-teacher-task-overview">
                    <div class="lms-teacher-task-overview-heading">
                        <span class="lms-teacher-task-eyebrow">Ringkasan tugas</span>
                        <span class="status-chip {{ $record->status_tugas === 'terbit' ? 'active' : '' }}">{{ ucfirst($record->status_tugas) }}</span>
                    </div>
                    <p class="lms-teacher-task-course">{{ $record->kode_mata_kuliah }} — {{ $record->nama_mata_kuliah }}<span>{{ $record->nama_kelas }} · {{ $record->nama_program_studi }}</span></p>
                    <div class="lms-teacher-task-deadline"><span>Batas pengumpulan</span><strong>{{ \Illuminate\Support\Carbon::parse($record->batas_pengumpulan, 'Asia/Jayapura')->format('l, d F Y') }}</strong><small>{{ \Illuminate\Support\Carbon::parse($record->batas_pengumpulan, 'Asia/Jayapura')->format('H:i') }} WIT</small></div>
                    <div class="lms-teacher-task-description"><h2>Deskripsi tugas</h2><div class="lms-prose">{!! nl2br(e($record->deskripsi)) !!}</div></div>
                </header>

                <section class="lms-teacher-task-metrics" aria-label="Ringkasan pengumpulan tugas">
                    <div><span>Total pengumpulan</span><strong>{{ $taskSubmissionCount }}</strong></div>
                    <div><span>Perlu dinilai</span><strong>{{ $taskNeedsGradingCount }}</strong></div>
                    <div><span>Sudah dinilai</span><strong>{{ $taskGradedCount }}</strong></div>
                </section>

                <section class="lms-teacher-task-submissions">
                    <div class="lms-teacher-task-submissions-heading">
                        <div><p class="lms-teacher-task-eyebrow">Aktivitas mahasiswa</p><h2>Pengumpulan mahasiswa</h2></div>
                        <span>{{ $taskSubmissionCount }} pengumpulan</span>
                    </div>
                    @if ($record->submissions->isNotEmpty())
                        <div class="table-wrap"><table data-datatable="lms-submissions"><thead><tr><th>NPM</th><th>Nama</th><th>Jawaban</th><th>Tautan</th><th>Penilaian</th></tr></thead><tbody>
                            @foreach ($record->submissions as $submission)
                                <tr><td>{{ $submission->nim }}</td><td>{{ $submission->nama_mahasiswa }}</td><td>{{ \Illuminate\Support\Str::limit($submission->jawaban_teks, 160) }}</td><td>@if ($submission->tautan_berkas)<div class="lms-task-link-alert" role="note"><strong>Link tugas</strong><a href="{{ $submission->tautan_berkas }}" target="_blank" rel="noopener noreferrer">{{ $submission->tautan_berkas }}</a></div>@endif</td><td>
                                    <form method="post" action="{{ route('lms.tasks.grade', ['record' => $record->id_tugas, 'submission' => $submission->id_pengumpulan]) }}" class="lms-inline-form">@csrf @method('PUT')
                                        <input name="nilai" type="number" min="0" max="100" step="0.01" value="{{ $submission->nilai }}" required aria-label="Nilai">
                                        <input name="komentar_dosen" value="{{ $submission->komentar_dosen }}" placeholder="Komentar" aria-label="Komentar">
                                        <button class="table-icon-button" type="submit" aria-label="Simpan nilai tugas" title="Simpan nilai tugas"><span data-lucide="save" aria-hidden="true"></span></button>
                                    </form>
                                </td></tr>
                            @endforeach
                        </tbody></table></div>
                    @else
                        <p class="lms-teacher-task-empty">Belum ada mahasiswa yang mengumpulkan tugas ini.</p>
                    @endif
                </section>
            </section>
        @elseif ($module === 'tugas')
            <section class="lms-panel">
                <div class="lms-meta"><span>Batas pengumpulan: {{ $record->batas_pengumpulan }} WIT</span><span>Status: {{ ucfirst($record->status_tugas) }}</span></div>
                <div class="lms-prose">{!! nl2br(e($record->deskripsi)) !!}</div>
                @if ($isStudent)
                    <div class="lms-task-student-actions">
                        @if ($record->submission)
                            @php($submissionStatusLabel = $record->submission->status_pengumpulan === 'dinilai' ? 'Sudah dinilai' : 'Sudah dikumpulkan')
                            <div class="lms-task-submission-summary">
                                <div class="lms-task-submission-heading">
                                    <strong>Pengumpulan tugas</strong>
                                    <span class="status-chip active">{{ $submissionStatusLabel }}</span>
                                </div>
                                <p>Dikumpulkan {{ \Illuminate\Support\Carbon::parse($record->submission->dikumpulkan_pada, 'Asia/Jayapura')->format('d/m/Y H:i') }} WIT</p>
                                @if ($record->submission->nilai !== null)
                                    <p>Nilai: <strong>{{ $record->submission->nilai }}</strong></p>
                                @endif
                                @if ($record->submission->komentar_dosen)
                                    <p class="lms-task-submission-feedback">Catatan dosen: {{ $record->submission->komentar_dosen }}</p>
                                @endif
                                @if ($record->submission->tautan_berkas)
                                    <div class="lms-task-submission-link-alert" role="note">
                                        <strong>Tautan pengumpulan tugas</strong>
                                        <a href="{{ $record->submission->tautan_berkas }}" target="_blank" rel="noopener noreferrer">{{ $record->submission->tautan_berkas }}</a>
                                    </div>
                                @endif
                            </div>
                        @else
                            <p class="lms-task-submission-prompt">Belum ada jawaban yang dikumpulkan.</p>
                        @endif

                        @if ($studentActivityWritable && (! $record->submission || $record->submission->status_pengumpulan !== 'dinilai'))
                            <button class="button button-primary" type="button" data-lms-open="lms-task-submission-modal">
                                {{ $record->submission ? 'Ubah pengumpulan' : 'Kumpulkan tugas' }}
                            </button>
                            <dialog class="lms-dialog lms-task-submission-dialog" id="lms-task-submission-modal" aria-labelledby="lms-task-submission-title">
                                <form method="post" action="{{ route('lms.tasks.submit', $record->id_tugas) }}" class="lms-dialog-form">
                                    @csrf
                                    <div class="lms-dialog-heading lms-task-dialog-heading">
                                        <div><p class="lms-task-dialog-eyebrow">Pengumpulan tugas</p><h2 id="lms-task-submission-title">{{ $record->submission ? 'Perbarui jawaban' : 'Kumpulkan tugas' }}</h2></div>
                                        <button class="table-icon-button" type="button" data-lms-close aria-label="Tutup formulir"><span data-lucide="x" aria-hidden="true"></span></button>
                                    </div>
                                    <div class="lms-dialog-body lms-task-dialog-body">
                                        <div class="lms-task-deadline"><span>Batas pengumpulan</span><strong>{{ \Illuminate\Support\Carbon::parse($record->batas_pengumpulan, 'Asia/Jayapura')->format('d/m/Y H:i') }} WIT</strong></div>
                                        <label>Jawaban Text langsung<textarea name="jawaban_teks" rows="6" placeholder="Tuliskan jawaban tugas Anda">{{ $record->submission->jawaban_teks ?? '' }}</textarea></label>
                                        <label>Link URL Tugas<input type="url" name="tautan_berkas" maxlength="2048" placeholder="https://" value="{{ $record->submission->tautan_berkas ?? '' }}"></label>
                                    </div>
                                    <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close>Batal</button><button class="button button-primary" type="submit">{{ $record->submission ? 'Simpan perubahan' : 'Kumpulkan tugas' }}</button></div>
                                </form>
                            </dialog>
                        @elseif (! $studentActivityWritable)
                            <p class="lms-task-submission-prompt">Kalender akademik atau SP ini sudah tidak aktif. Pengumpulan tugas hanya dapat dilihat.</p>
                        @endif
                    </div>
                @endif
            </section>
        @elseif ($module === 'bank-soal')
            <section class="lms-panel">
                <div class="lms-meta"><span>{{ $record->kode_mata_kuliah }} — {{ $record->nama_mata_kuliah }}</span><span>{{ $record->jenis_soal === 'pilihan_ganda' ? 'Pilihan ganda' : 'Essay' }}</span></div>
                <div class="lms-prose">{!! nl2br(e($record->pertanyaan)) !!}</div>
                @if ($record->jenis_soal === 'pilihan_ganda')
                    <ol type="A">@foreach (['opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e'] as $option) @if ($record->{$option})<li>{{ $record->{$option} }}</li>@endif @endforeach</ol>
                    @if ($canManage)<p><strong>Kunci jawaban:</strong> {{ $record->kunci_jawaban }}</p>@endif
                @endif
                @if ($canManage)
                    <div class="lms-actions"><button class="table-icon-button" type="button" data-lms-open="lms-edit-modal" aria-label="Ubah soal" title="Ubah soal"><span data-lucide="pencil" aria-hidden="true"></span></button><button class="table-icon-button reset" type="button" data-lms-open="lms-delete-modal" aria-label="Hapus soal" title="Hapus soal"><span data-lucide="trash-2" aria-hidden="true"></span></button></div>
                @endif
            </section>
        @else
            <section class="lms-panel">
                <div class="lms-meta"><span>Status: {{ ucfirst($record->status_diskusi) }}</span><span>{{ $record->nama_kelas }} · {{ $record->nama_program_studi }}</span></div>
                <div class="lms-discussion-opening"><strong>Topik pembuka</strong><div class="lms-prose">{!! nl2br(e($record->isi_diskusi)) !!}</div></div>
                <h2>Percakapan</h2>
                <div class="lms-chat" data-lms-chat>
                    @forelse ($record->messages as $message)
                        <article class="lms-chat-row {{ $message->is_mine ? 'is-mine' : 'is-other' }}" id="pesan-{{ $message->id_pesan }}">
                            <div class="lms-chat-bubble">
                                <div class="lms-chat-message-heading"><strong>{{ ucfirst($message->jenis_pengguna) }}</strong><time>{{ \Illuminate\Support\Carbon::parse($message->dibuat_pada, 'Asia/Jayapura')->format('d/m/Y H:i') }}</time></div>
                                @if ($message->parent_message)
                                    <blockquote class="lms-chat-quote"><strong>{{ ucfirst($message->parent_message->jenis_pengguna) }}</strong><span>{!! \App\Support\ChatMessageText::toSafeHtml(\Illuminate\Support\Str::limit($message->parent_message->isi_pesan, 180)) !!}</span></blockquote>
                                @endif
                                <p>{!! \App\Support\ChatMessageText::toSafeHtml($message->isi_pesan) !!}</p>
                                @if ($canParticipate && $studentActivityWritable && $record->status_diskusi === 'terbuka')
                                    <div class="lms-chat-actions">
                                        <button class="button button-secondary button-small" type="button" data-lms-reply data-reply-id="{{ $message->id_pesan }}" data-reply-author="{{ ucfirst($message->jenis_pengguna) }}" data-reply-content="{{ $message->isi_pesan }}">Balas</button>
                                        @if ($message->can_delete)
                                            <form method="post" action="{{ route('lms.discussions.messages.destroy', ['record' => $record->id_diskusi, 'message' => $message->id_pesan]) }}" data-lms-confirm>@csrf @method('DELETE')<button class="button button-danger button-small" type="submit">Hapus</button></form>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="lms-chat-empty">Belum ada pesan. Mulai percakapan di bawah.</p>
                    @endforelse
                </div>
                @if ($canParticipate && $studentActivityWritable && $record->status_diskusi === 'terbuka')
                    <form method="post" action="{{ route('lms.discussions.messages.store', $record->id_diskusi) }}" class="lms-chat-composer">@csrf
                        <div class="lms-reply-context" data-lms-reply-context hidden><span data-lms-reply-label></span><button class="table-icon-button" type="button" data-lms-reply-cancel aria-label="Batalkan balasan" title="Batalkan balasan"><span data-lucide="x" aria-hidden="true"></span></button></div>
                        <input type="hidden" name="id_pesan_induk" value="">
                        <label class="sr-only" for="lms-chat-message">Tulis pesan</label>
                        <textarea id="lms-chat-message" name="isi_pesan" rows="2" required maxlength="20000" placeholder="Tulis pesan..."></textarea>
                        <button class="button button-primary" type="submit">Kirim</button>
                    </form>
                @elseif ($record->status_diskusi !== 'terbuka')
                    <p class="lms-chat-closed">Diskusi telah ditutup. Pesan baru tidak dapat dikirim.</p>
                @elseif ($isStudent && ! $studentActivityWritable)
                    <p class="lms-chat-closed">Kalender akademik atau SP ini sudah tidak aktif. Diskusi hanya dapat dibaca.</p>
                @endif
            </section>
        @endif

    </div>

    @if ($canManage && in_array($module, ['materi', 'pertemuan', 'bank-soal'], true))
        <dialog class="lms-dialog" id="lms-edit-modal"><form method="post" class="lms-dialog-form" enctype="multipart/form-data"
            action="{{ match ($module) {'materi' => route('lms.materials.update', $recordId), 'pertemuan' => route('lms.meetings.update', $recordId), 'bank-soal' => route('lms.questions.update', $recordId)} }}">
            @csrf @method('PUT')
            <div class="lms-dialog-heading"><h2>Ubah {{ $title }}</h2><button class="table-icon-button" type="button" data-lms-close aria-label="Tutup">×</button></div>
            <div class="lms-dialog-body">
                @if ($module === 'materi')
                    <label>Judul<input name="judul" value="{{ $record->judul }}" required maxlength="255"></label>
                    <label>Deskripsi<textarea name="deskripsi" rows="4">{{ $record->deskripsi }}</textarea></label>
                    <label class="lms-dropzone" data-lms-dropzone>
                        <input class="lms-dropzone-input" type="file" name="berkas" accept=".doc,.docx,.odt,.rtf,.ppt,.pptx,.odp,.pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.oasis.opendocument.text,application/rtf,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/vnd.oasis.opendocument.presentation,application/pdf">
                        <span>Seret file pengganti ke sini atau klik untuk memilih</span>
                        <small data-lms-file-name>{{ $record->nama_berkas ?: 'Belum ada file dipilih' }}</small>
                    </label>
                    <label>Status publikasi<select name="status_publikasi" data-searchable-select><option value="terbit" @selected($record->status_publikasi === 'terbit')>Terbit</option><option value="draf" @selected($record->status_publikasi === 'draf')>Draf</option></select></label>
                @elseif ($module === 'pertemuan')
                    <label>Pertemuan Ke<select name="pertemuan_ke" data-searchable-select required>@for ($meeting = 1; $meeting <= 16; $meeting++)<option value="{{ $meeting }}" @selected((int) $record->pertemuan_ke === $meeting)>Pertemuan {{ $meeting }}</option>@endfor</select></label>
                    <label>Materi terkait (dapat memilih lebih dari satu)<select name="id_materi[]" data-searchable-select multiple>@foreach ($related as $material)<option value="{{ $material->id_materi }}" @selected($record->materials->contains('id_materi', $material->id_materi))>{{ $material->judul }}</option>@endforeach</select></label>
                    <label>Judul<input name="judul" value="{{ $record->judul }}" required maxlength="255"></label>
                    <label>Tanggal kuliah<input type="date" name="tanggal_kuliah" value="{{ $record->tanggal_kuliah }}" required></label>
                    <div class="lms-inline-fields"><label>Waktu mulai<input type="time" name="waktu_mulai" value="{{ substr($record->waktu_mulai, 0, 5) }}" required></label><label>Waktu selesai<input type="time" name="waktu_selesai" value="{{ substr($record->waktu_selesai, 0, 5) }}" required></label></div>
                    <label>Absensi mandiri<select name="absen_dibuka" data-searchable-select><option value="0" @selected(! $record->absen_dibuka)>Ditutup</option><option value="1" @selected($record->absen_dibuka)>Dibuka</option></select></label>
                    <label>Tautan video conference<input type="url" name="tautan_video" value="{{ $record->tautan_video }}" maxlength="2048"></label>
                    <label>Status<select name="status_pertemuan" data-searchable-select><option value="draf" @selected($record->status_pertemuan === 'draf')>Draf</option><option value="terbit" @selected($record->status_pertemuan === 'terbit')>Terbit</option><option value="selesai" @selected($record->status_pertemuan === 'selesai')>Selesai</option></select></label>
                @elseif ($module === 'bank-soal')
                    <label>Jenis soal<select name="jenis_soal" data-searchable-select required><option value="pilihan_ganda" @selected($record->jenis_soal === 'pilihan_ganda')>Pilihan ganda</option><option value="essay" @selected($record->jenis_soal === 'essay')>Essay</option></select></label>
                    <label>Pertanyaan<textarea name="pertanyaan" rows="5" required>{{ $record->pertanyaan }}</textarea></label>
                    <fieldset data-question-options @if ($record->jenis_soal === 'essay') hidden @endif><legend>Pilihan jawaban</legend>@foreach (['a', 'b', 'c', 'd', 'e'] as $option)<label>Opsi {{ strtoupper($option) }}<textarea name="opsi_{{ $option }}" rows="2">{{ $record->{'opsi_'.$option} }}</textarea></label>@endforeach
                        <label>Kunci jawaban<select name="kunci_jawaban" data-searchable-select><option value="">Pilih jawaban benar</option>@foreach (['A', 'B', 'C', 'D', 'E'] as $key)<option value="{{ $key }}" @selected($record->kunci_jawaban === $key)>{{ $key }}</option>@endforeach</select></label></fieldset>
                    <label>Status<select name="aktif" data-searchable-select><option value="1" @selected($record->aktif)>Aktif</option><option value="0" @selected(! $record->aktif)>Nonaktif</option></select></label>
                @else
                    <label>Topik<input name="judul" value="{{ $record->judul }}" required maxlength="255"></label>
                    <label>Uraian pembuka<textarea name="isi_diskusi" rows="5" required>{{ $record->isi_diskusi }}</textarea></label>
                    <label>Status<select name="status_diskusi" data-searchable-select><option value="terbuka" @selected($record->status_diskusi === 'terbuka')>Terbuka</option><option value="ditutup" @selected($record->status_diskusi === 'ditutup')>Ditutup</option></select></label>
                @endif
            </div>
            <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close>Batal</button><button class="button button-primary" type="submit">Simpan perubahan</button></div>
        </form></dialog>
        @if ($module !== 'materi' || ! $record->used_in_meeting)
            <dialog class="lms-dialog lms-confirm-dialog" id="lms-delete-modal"><div class="lms-dialog-heading"><h2>Konfirmasi penghapusan</h2></div><p>Yakin ingin menghapus {{ strtolower($title) }} ini?</p>
                <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close>Batal</button>
                    <form method="post" action="{{ match ($module) {'materi' => route('lms.materials.destroy', $recordId), 'pertemuan' => route('lms.meetings.destroy', $recordId), 'bank-soal' => route('lms.questions.destroy', $recordId)} }}">@csrf @method('DELETE')<button class="button button-danger" type="submit">Hapus</button></form>
                </div>
            </dialog>
        @endif
    @endif

    @if ($module === 'diskusi')
        <dialog class="lms-dialog lms-confirm-dialog" id="lms-message-delete-modal">
            <div class="lms-dialog-heading"><h2>Konfirmasi penghapusan pesan</h2></div>
            <p>Yakin ingin menghapus pesan diskusi ini?</p>
            <div class="lms-dialog-actions"><button class="button button-secondary" type="button" data-lms-close>Batal</button><button class="button button-danger" type="button" data-lms-confirm-submit>Hapus pesan</button></div>
        </dialog>
    @endif
@endsection

@push('styles')
<style>
    .lms-page{display:grid;gap:1.25rem}.lms-page .subtitle{color:var(--muted,#64748b);margin-top:.45rem}
    .student-activity-readonly{display:flex;flex-wrap:wrap;gap:.35rem .7rem;padding:.8rem 1rem;border:1px solid #fde68a;border-left:4px solid #d97706;border-radius:8px;background:#fffbeb;color:#854d0e}
    .student-activity-readonly span{color:#8b6a31}
    .lms-panel{display:grid;gap:1rem;padding:1.25rem;background:#fff;border:1px solid #dbe3ee;border-radius:14px}
    .lms-meta{display:flex;flex-wrap:wrap;gap:.75rem 1.5rem;color:#475569;font-size:.92rem}
    .lms-prose{line-height:1.7}.lms-stack{display:grid;gap:.85rem;max-width:760px}.lms-stack label{display:grid;gap:.4rem;font-weight:600}.lms-stack input,.lms-stack textarea,.lms-inline-form input,.lms-inline-form select{padding:.55rem .65rem;border:1px solid #cbd5e1;border-radius:7px}
    .lms-inline-form{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}.lms-inline-form input{max-width:190px}
    .lms-teacher-task-detail{display:grid;gap:1.1rem}
    .lms-teacher-task-overview{display:grid;gap:1rem;padding:1.25rem 1.4rem;border:1px solid #d8e5dc;border-left:5px solid #477d65;border-radius:7px;background:linear-gradient(110deg,#f2f7f3,#fbfcfa)}
    .lms-teacher-task-overview-heading{display:flex;align-items:center;justify-content:space-between;gap:1rem}
    .lms-teacher-task-eyebrow{margin:0;color:#477d65;font-size:.7rem;font-weight:800;text-transform:uppercase}
    .lms-teacher-task-course{display:grid;gap:.2rem;margin:0;color:#2e4535;font-size:1rem;font-weight:700}
    .lms-teacher-task-course span{color:#67766b;font-size:.82rem;font-weight:400}
    .lms-teacher-task-deadline{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.5rem 1rem;padding:.7rem .85rem;border:1px solid #dce5df;border-radius:5px;background:#fff}
    .lms-teacher-task-deadline span{color:#657368;font-size:.78rem}
    .lms-teacher-task-deadline strong{color:#2d4935;font-size:.87rem}
    .lms-teacher-task-deadline small{color:#477d65;font-size:.78rem;font-weight:700}
    .lms-teacher-task-description h2,.lms-teacher-task-submissions-heading h2{margin:0 0 .35rem;color:#263b2d;font-size:1rem}
    .lms-teacher-task-description .lms-prose{color:#536358;font-size:.9rem;line-height:1.7}
    .lms-teacher-task-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}
    .lms-teacher-task-metrics>div{display:grid;gap:.25rem;padding:.85rem 1rem;border:1px solid #e0e7e1;border-radius:5px;background:#fff}
    .lms-teacher-task-metrics span{color:#68766c;font-size:.76rem}
    .lms-teacher-task-metrics strong{color:#2e5237;font-size:1.4rem;line-height:1.2}
    .lms-teacher-task-submissions{display:grid;gap:.85rem;padding-top:.2rem}
    .lms-teacher-task-submissions-heading{display:flex;align-items:center;justify-content:space-between;gap:1rem}
    .lms-teacher-task-submissions-heading h2{margin:.2rem 0 0}
    .lms-teacher-task-submissions-heading>span{color:#67766b;font-size:.8rem}
    .lms-teacher-task-empty{margin:0;padding:1.5rem;border:1px dashed #ccd8cf;border-radius:5px;color:#647268;text-align:center}
    .lms-task-link-alert{display:grid;max-width:22rem;gap:.3rem;padding:.55rem .7rem;border:1px solid #bae6fd;border-left:4px solid #0284c7;border-radius:6px;background:#f0f9ff;color:#0c4a6e;font-size:.8rem}
    .lms-task-link-alert a{overflow-wrap:anywhere;color:#0369a1}
    .lms-task-student-actions{display:grid;justify-items:start;gap:.85rem;margin-top:.25rem}
    .lms-task-submission-summary{display:grid;width:min(100%,680px);gap:.4rem;padding:.9rem 1rem;border:1px solid #dce5df;border-left:4px solid #477d65;border-radius:6px;background:#f7faf7}
    .lms-task-submission-heading{display:flex;align-items:center;justify-content:space-between;gap:1rem;color:#33483a}
    .lms-task-submission-summary p{margin:0;color:#647268;font-size:.85rem}
    .lms-task-submission-feedback{padding-top:.5rem;border-top:1px solid #e3eae4}
    .lms-task-submission-link-alert{display:grid;gap:.3rem;padding:.75rem .85rem;border:1px solid #b9d9f2;border-left:4px solid #3283bc;border-radius:5px;background:#eff7fd}
    .lms-task-submission-link-alert strong{color:#285f86;font-size:.78rem}
    .lms-task-submission-link-alert a{color:#176fa9;font-size:.83rem;overflow-wrap:anywhere;text-decoration:underline;text-underline-offset:2px}
    .lms-task-submission-prompt{margin:0;color:#68766c;font-size:.9rem}
    .lms-task-submission-dialog{width:min(620px,calc(100vw - 2rem))}
    .lms-task-dialog-heading{align-items:flex-start}
    .lms-task-dialog-heading h2{color:#253b2d;font-size:1.2rem}
    .lms-task-dialog-eyebrow{margin:0 0 .25rem;color:#477d65;font-size:.7rem;font-weight:800;text-transform:uppercase}
    .lms-task-dialog-body{gap:1rem}
    .lms-task-dialog-body label{display:grid;gap:.45rem;color:#34483a;font-weight:700}
    .lms-task-dialog-body textarea,.lms-task-dialog-body input{width:100%;padding:.75rem .85rem;border:1px solid #cbd8ce;border-radius:6px;background:#fff;color:#26382d;font:inherit;font-weight:400}
    .lms-task-dialog-body textarea{min-height:150px;resize:vertical;line-height:1.55}
    .lms-task-dialog-body textarea:focus,.lms-task-dialog-body input:focus{border-color:#477d65;outline:3px solid #477d6526}
    .lms-task-deadline{display:flex;justify-content:space-between;gap:.75rem;padding:.75rem .85rem;border:1px solid #dce5df;border-radius:5px;background:#f4f8f4;color:#617065;font-size:.8rem}
    .lms-task-deadline strong{color:#33483a;text-align:right}
    .lms-student-meeting-detail{display:grid;gap:1.25rem}
    .student-meeting-hero{display:grid;grid-template-columns:106px minmax(0,1fr);align-items:center;gap:1.4rem;padding:1.5rem;border:1px solid #d8e5dc;border-left:5px solid #477d65;border-radius:8px;background:linear-gradient(110deg,#f0f7f2,#fbfcfa)}
    .student-meeting-date-block{display:grid;justify-items:center;gap:.1rem;padding:.65rem .4rem;border-radius:5px;background:#e2efe5;color:#356b45;text-align:center}
    .student-meeting-date-block span{font-size:.75rem;font-weight:700;text-transform:uppercase}
    .student-meeting-date-block strong{font-size:2rem;line-height:1.15}
    .student-meeting-date-block small{font-size:.75rem}
    .student-meeting-hero-content{min-width:0}
    .student-meeting-hero-label{display:flex;align-items:center;gap:.75rem;color:#477d65;font-size:.75rem;font-weight:800;text-transform:uppercase}
    .student-meeting-hero-content h2{margin:.55rem 0 .2rem;color:#203b2d;font-size:1.35rem;line-height:1.35}
    .student-meeting-hero-content p{margin:0;color:#637468}
    .student-meeting-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(250px,310px);align-items:start;gap:1.25rem}
    .student-meeting-main{display:grid;gap:1.25rem;min-width:0}
    .student-meeting-section{padding:1.1rem 0;border-bottom:1px solid #e3eae4}
    .student-meeting-section h2,.student-meeting-attendance-panel h2{margin:0;color:#253b2d;font-size:1.05rem}
    .student-meeting-facts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.8rem;margin-top:.9rem}
    .student-meeting-facts div{display:grid;gap:.25rem;min-width:0}
    .student-meeting-facts span,.student-meeting-section-heading>span{color:#738077;font-size:.74rem}
    .student-meeting-facts strong{color:#33483a;font-size:.86rem;overflow-wrap:anywhere}
    .student-meeting-section-heading{display:flex;align-items:center;justify-content:space-between;gap:.75rem}
    .student-meeting-material-list{margin:.65rem 0 0;padding:0;list-style:none}
    .student-meeting-material-list li{display:flex;align-items:center;gap:.75rem;padding:.7rem 0;border-top:1px solid #edf1ed}
    .student-meeting-material-mark{display:grid;width:36px;height:36px;flex:0 0 36px;place-items:center;border-radius:4px;background:#eef5ef;color:#3e704e;font-size:.63rem;font-weight:800}
    .student-meeting-material-name{min-width:0;flex:1;color:#34483a;font-size:.88rem;font-weight:600;overflow-wrap:anywhere}
    .student-meeting-material-list a{flex:0 0 auto;color:#356b45;font-size:.8rem;font-weight:700;text-decoration:none}
    .student-meeting-muted{margin:.75rem 0 0;color:#748078;font-size:.88rem}
    .student-meeting-link-panel{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem;border:1px solid #dce5df;border-radius:6px;background:#f8faf8}
    .student-meeting-link-panel>div{display:grid;min-width:0;gap:.25rem}
    .student-meeting-link-panel strong{color:#33483a;overflow-wrap:anywhere}
    .student-meeting-kicker{color:#477d65;font-size:.7rem;font-weight:800;text-transform:uppercase}
    .student-meeting-exam-panel{border-color:#d9e2ec;background:#f5f8fb}
    .student-meeting-attendance-panel{position:sticky;top:1rem;display:grid;gap:.75rem;padding:1.15rem;border:1px solid #d8e5dc;border-top:4px solid #477d65;border-radius:6px;background:#fff}
    .student-meeting-attendance-panel p{margin:0;color:#66756a;font-size:.86rem;line-height:1.55}
    .student-meeting-attendance-panel .button{width:100%;justify-content:center}
    .student-meeting-attendance-state{display:block;padding:.6rem .7rem;border-radius:4px;background:#f0f4f1;color:#57675b;font-size:.8rem;font-weight:600}
    .student-meeting-attendance-confirmation{display:grid;gap:.45rem;padding:.85rem;border:1px solid #cfe3d3;border-radius:5px;background:#f0f8f1;color:#285d38}
    .student-meeting-attendance-confirmation strong{font-size:.9rem}
    .student-meeting-attendance-confirmation span{font-size:.83rem;font-weight:600}
    .student-meeting-attendance-confirmation small{color:#5d7563;font-size:.73rem}
    .lms-discussion-opening{display:grid;gap:.5rem;padding:1rem 1.15rem;background:#f8fafc;border-left:4px solid #64748b;border-radius:8px}.lms-chat{display:flex;box-sizing:border-box;width:100%;height:min(60vh,620px);min-height:180px;max-height:620px;flex:0 0 auto;flex-direction:column;gap:.8rem;overflow-x:hidden;overflow-y:auto;overscroll-behavior:contain;padding:1rem;background:#e9f0e8;border:1px solid #d2ddd1;border-radius:12px}.lms-chat-row{display:flex;width:100%;flex:0 0 auto}.lms-chat-row.is-mine{justify-content:flex-end}.lms-chat-row.is-other{justify-content:flex-start}.lms-chat-bubble{width:fit-content;max-width:min(78%,680px);padding:.65rem .8rem;background:#fff;border:1px solid #dce5dc;border-radius:12px 12px 12px 3px;box-shadow:0 1px 2px #0f172a12}.lms-chat-row.is-mine .lms-chat-bubble{background:#d9fdd3;border-color:#c4e8bd;border-radius:12px 12px 3px 12px}.lms-chat-message-heading{display:flex;justify-content:space-between;gap:1.5rem;color:#475569;font-size:.8rem}.lms-chat-message-heading strong{color:#166534}.lms-chat-message-heading time{white-space:nowrap}.lms-chat-bubble p{margin:.45rem 0 0;line-height:1.55;overflow-wrap:anywhere}.lms-chat-quote{display:grid;gap:.2rem;margin:.45rem 0 0;padding:.45rem .6rem;background:#ffffffa8;border-left:3px solid #16a34a;border-radius:4px;font-size:.85rem}.lms-chat-quote span{white-space:pre-line;overflow-wrap:anywhere}.lms-chat-actions{display:flex;justify-content:flex-end;gap:.4rem;margin-top:.45rem}.lms-chat-empty,.lms-chat-closed{margin:0;padding:.75rem;color:#64748b;text-align:center}.lms-chat-composer{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:end;gap:.65rem;padding:.75rem;background:#f8fafc;border:1px solid #dbe3ee;border-radius:10px}.lms-chat-composer textarea{width:100%;resize:vertical;padding:.7rem .8rem;border:1px solid #cbd5e1;border-radius:8px;font:inherit}.lms-reply-context{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.45rem .65rem;background:#e2e8f0;border-left:3px solid #16a34a;border-radius:5px;font-size:.9rem}.lms-chat-composer [hidden]{display:none!important}.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
    .lms-actions{display:flex;align-items:center;gap:.5rem}.lms-dialog{position:fixed;inset:50% auto auto 50%;transform:translate(-50%,-50%);margin:0;width:min(720px,calc(100vw - 2rem));max-height:min(90vh,900px);overflow:visible;padding:0;border:1px solid #dbe3ee;border-radius:16px;box-shadow:0 24px 80px #0f172a40}.lms-dialog::backdrop{background:#0f172a80;backdrop-filter:blur(2px)}
    .lms-dialog-form{display:flex;max-height:min(90vh,900px);flex-direction:column}.lms-dialog-heading,.lms-dialog-actions{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.25rem;border-bottom:1px solid #e2e8f0}.lms-dialog-heading h2{margin:0}
    .lms-dialog-body{display:grid;gap:1rem;overflow:auto;padding:1.25rem}.lms-dialog-body label{display:grid;gap:.4rem;font-weight:600}.lms-dialog-body input:not([type=checkbox]):not([type=radio]):not([type=file]),.lms-dialog-body textarea,.lms-dialog-body select{width:100%;padding:.65rem .75rem;border:1px solid #cbd5e1;border-radius:8px;background:#fff}
    .lms-dialog-body fieldset{display:grid;gap:.75rem;border:1px solid #cbd5e1;border-radius:10px;padding:1rem}.lms-dialog-body legend{padding:0 .35rem;font-weight:700}.lms-dialog-body input[type=checkbox],.lms-dialog-body input[type=radio]{width:1rem;height:1rem;flex:0 0 1rem;margin:0;accent-color:#2563eb}.lms-question-choice{display:flex!important;align-items:flex-start;gap:.5rem;font-weight:400!important}
    .lms-dialog [hidden]{display:none!important}.lms-dialog .ts-dropdown{z-index:2}.lms-dialog .ts-dropdown-content{max-height:min(240px,40vh);overflow-y:auto}
    .lms-dropzone{position:relative;min-height:105px;align-content:center;justify-items:center;padding:1rem;border:2px dashed #94a3b8;border-radius:10px;background:#f8fafc;text-align:center;cursor:pointer}.lms-dropzone.is-dragging{border-color:#2563eb;background:#eff6ff}.lms-dropzone-input{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer}.lms-dropzone:focus-within{outline:2px solid #2563eb;outline-offset:2px}
    .lms-inline-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.lms-dialog-actions{justify-content:flex-end;border-top:1px solid #e2e8f0;border-bottom:0}
    @media(max-width:600px){.lms-inline-fields{grid-template-columns:1fr}}
    @media(max-width:600px){.lms-teacher-task-overview{padding:1rem}.lms-teacher-task-deadline{grid-template-columns:1fr;gap:.25rem}.lms-teacher-task-metrics{gap:.45rem}.lms-teacher-task-metrics>div{padding:.7rem}.lms-teacher-task-metrics span{font-size:.68rem}.lms-teacher-task-submissions-heading{align-items:flex-start;flex-direction:column}}
    @media(max-width:760px){.student-meeting-layout{grid-template-columns:1fr}.student-meeting-attendance-panel{position:static;order:-1}}
    @media(max-width:600px){.student-meeting-hero{grid-template-columns:72px minmax(0,1fr);gap:.85rem;padding:1rem}.student-meeting-date-block strong{font-size:1.65rem}.student-meeting-hero-content h2{font-size:1.1rem}.student-meeting-facts{grid-template-columns:1fr 1fr}.student-meeting-link-panel{align-items:flex-start;flex-direction:column}.student-meeting-link-panel .button{width:100%;justify-content:center}}
    @media(max-width:600px){.lms-chat-bubble{max-width:92%}.lms-chat{padding:.65rem}}
</style>
@endpush

@push('scripts')
<script>
    document.querySelectorAll('[data-lms-open]').forEach((button) => button.addEventListener('click', () => {
        const dialog = document.getElementById(button.dataset.lmsOpen);
        dialog?.showModal();
        if (dialog) document.dispatchEvent(new CustomEvent('simak:initialize-searchable-selects', {detail: {root: dialog}}));
    }));
    document.querySelectorAll('[data-lms-close]').forEach((button) => button.addEventListener('click', () => button.closest('dialog')?.close()));
    document.querySelectorAll('[data-lms-dropzone]').forEach((dropzone) => {
        const input = dropzone.querySelector('input[type=file]');
        const fileName = dropzone.querySelector('[data-lms-file-name]');
        input?.addEventListener('change', () => { fileName.textContent = input.files?.[0]?.name || 'Belum ada file dipilih'; });
        dropzone.addEventListener('dragover', (event) => { event.preventDefault(); dropzone.classList.add('is-dragging'); });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('is-dragging'));
        dropzone.addEventListener('drop', (event) => {
            event.preventDefault();
            dropzone.classList.remove('is-dragging');
            if (!input || !event.dataTransfer?.files.length) return;
            input.files = event.dataTransfer.files;
            input.dispatchEvent(new Event('change', {bubbles: true}));
        });
    });
    document.querySelectorAll('.lms-dialog [name="jenis_soal"]').forEach((select) => {
        const options = select.closest('form').querySelector('[data-question-options]');
        const toggleOptions = () => { options.hidden = select.value !== 'pilihan_ganda'; };
        select.addEventListener('change', toggleOptions);
        toggleOptions();
    });
    const chat = document.querySelector('[data-lms-chat]');
    if (chat) {
        chat.scrollTop = chat.scrollHeight;
    }
    const replyContext = document.querySelector('[data-lms-reply-context]');
    const replyInput = document.querySelector('.lms-chat-composer [name="id_pesan_induk"]');
    const replyLabel = document.querySelector('[data-lms-reply-label]');
    const chatInput = document.getElementById('lms-chat-message');
    const clearReply = () => {
        if (!replyContext || !replyInput || !replyLabel) return;
        replyInput.value = '';
        replyLabel.textContent = '';
        replyContext.hidden = true;
    };
    document.querySelectorAll('[data-lms-reply]').forEach((button) => button.addEventListener('click', () => {
        if (!replyContext || !replyInput || !replyLabel) return;
        replyInput.value = button.dataset.replyId;
        replyLabel.textContent = `Membalas ${button.dataset.replyAuthor}: ${button.dataset.replyContent}`;
        replyContext.hidden = false;
        chatInput?.focus();
    }));
    document.querySelector('[data-lms-reply-cancel]')?.addEventListener('click', clearReply);
    const messageDeleteDialog = document.getElementById('lms-message-delete-modal');
    let pendingDeleteForm;
    if (messageDeleteDialog) {
        document.querySelectorAll('form[data-lms-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === 'true') {
                delete form.dataset.confirmed;
                return;
            }
            event.preventDefault();
            pendingDeleteForm = form;
            messageDeleteDialog.showModal();
        }));
        messageDeleteDialog.querySelector('[data-lms-confirm-submit]').addEventListener('click', () => {
            if (!pendingDeleteForm) return;
            const form = pendingDeleteForm;
            pendingDeleteForm = null;
            form.dataset.confirmed = 'true';
            form.requestSubmit();
            messageDeleteDialog.close();
        });
    }
</script>
@endpush
