<?php

$masterDashboard = ['label' => 'Dashboard Master', 'route' => 'dashboard'];
$academicDashboard = ['label' => 'Dashboard Akademik', 'route' => 'academic-dashboard.index'];
$lmsDashboard = ['label' => 'Dashboard LMS', 'route' => 'lms-dashboard.index'];
$home = ['label' => 'Dashboard', 'route' => 'dashboard'];
$calendar = ['label' => 'Kalender Akademik', 'route' => 'academic-calendars.index'];
$master = static fn (string $module, string $label): array => [
    'label' => $label,
    'route' => 'master-data.'.$module.'.index',
];
$lmsItems = [
    ['label' => 'Materi Kuliah', 'route' => 'lms.materials.index'],
    ['label' => 'Pertemuan Kuliah', 'route' => 'lms.meetings.index'],
    ['label' => 'Tugas', 'route' => 'lms.tasks.index'],
    ['label' => 'Bank Soal', 'route' => 'lms.questions.index'],
    ['label' => 'Diskusi', 'route' => 'lms.discussions.index'],
];
$lmsStudentItems = array_values(array_filter(
    $lmsItems,
    static fn (array $item): bool => $item['route'] !== 'lms.questions.index'
));
$lmsMenu = ['label' => 'LMS Virtual Class', 'items' => $lmsItems];
$lmsStudentMenu = ['label' => 'LMS Virtual Class', 'items' => $lmsStudentItems];
$graduateThesisDashboard = ['label' => 'Dashboard E-Tesis & Disertasi', 'route' => 'graduate_theses.dashboard'];
$graduateThesisMenu = ['label' => 'E-Tesis & Disertasi', 'items' => [
    ['label' => 'Validasi Usulan Judul', 'route' => 'graduate_theses.titles.validation.index'],
    ['label' => 'Penugasan Dosen', 'route' => 'graduate_theses.assignments.index'],
    ['label' => 'Bimbingan Tesis & Disertasi', 'route' => 'graduate_theses.bimbingan.index'],
    ['label' => 'Ujian Tesis & Disertasi', 'route' => 'graduate_theses.exams.index'],
    ['label' => 'Upload Data Tesis & Disertasi', 'route' => 'graduate_theses.upload.index'],
]];
$graduateThesisLecturerMenu = ['label' => 'E-Tesis & Disertasi', 'items' => [
    ['label' => 'Bimbingan Tesis & Disertasi', 'route' => 'graduate_theses.bimbingan.index'],
    ['label' => 'Ujian Tesis & Disertasi', 'route' => 'graduate_theses.exams.index'],
]];
$graduateThesisStudentMenu = ['label' => 'E-Tesis & Disertasi', 'items' => [
    ['label' => 'Usul Judul Tesis & Disertasi', 'route' => 'graduate_theses.titles.index'],
    ['label' => 'Bimbingan Tesis & Disertasi', 'route' => 'graduate_theses.bimbingan.index'],
]];
$thesisStudentMenu = ['label' => 'E-Skripsi', 'items' => [
    ['label' => 'Usul Judul Skripsi', 'route' => 'theses.titles.index'],
    ['label' => 'Bimbingan Skripsi', 'route' => 'theses.bimbingan.index'],
]];
$shortSemesterMenu = [
    'label' => 'Semester Pendek',
    'items' => [
        ['label' => 'Kurikulum SP', 'route' => 'short-semester-curricula.index'],
        ['label' => 'Jadwal SP', 'route' => 'short-semester-schedules.index'],
        ['label' => 'Nilai SP', 'route' => 'short-semester-grades.index'],
    ],
];
$shortSemesterGradeMenu = [
    'label' => 'Semester Pendek',
    'items' => [['label' => 'Nilai SP', 'route' => 'short-semester-grades.index']],
];

return [
    'professional_program_id' => '7eab686c-e4f4-416c-9ced-442d9723835b',
    'krs' => [
        'max_credits_low_ips' => 14,
        'max_credits_mid_ips' => 19,
        'max_credits_high_ips' => 24,
        'max_credits_new_student' => 24,
        'professional_max_credits' => 50,
    ],
    'kkn' => [
        'minimum_passed_credits' => 100,
    ],
    'role_labels' => [
        'admin' => 'Administrator',
        'dosen' => 'Dosen',
        'asdos' => 'Asisten Dosen',
        'mahasiswa' => 'Mahasiswa',
        'regulang' => 'Registrasi Ulang',
        'pimuniv' => 'Pimpinan Universitas',
        'pimfak' => 'Pimpinan Fakultas',
        'timkur' => 'Tim Kurikulum',
        'userprodi' => 'Pengelola Program Studi',
        'usertu' => 'Operator Prodi',
        'keuangan' => 'Keuangan',
        'wisuda' => 'Wisuda',
        'validasi' => 'Validasi',
        'lppm' => 'LPPM',
    ],
    'navigation_group_icons' => [
        'Dashboard' => 'layout-dashboard',
        'Data Master' => 'database',
        'Akademik' => 'graduation-cap',
        'LMS Virtual Class' => 'book-open-check',
        'Semester Pendek' => 'calendar-days',
        'E-Skripsi' => 'scroll-text',
        'E-Tesis & Disertasi' => 'scroll-text',
        'E-Kurikulum OBE' => 'book-open',
        'Keuangan' => 'wallet-cards',
        'Kuliah Kerja Nyata (KKN)' => 'map-pinned',
        'Validasi Nilai' => 'clipboard-check',
        'Data Wisuda' => 'award',
        'Administrasi & Tools' => 'settings',
        'Evaluasi' => 'chart-no-axes-column-increasing',
        'Pemantauan' => 'eye',
        'Layanan Mahasiswa' => 'heart-handshake',
        'Registrasi' => 'clipboard-list',
        'Layanan Prodi' => 'building-2',
        'Wisuda' => 'award',
        'Validasi' => 'shield-check',
    ],
    'navigation' => [
        'admin' => [
            ['label' => 'Dashboard', 'items' => [$masterDashboard, $academicDashboard, $lmsDashboard, ['label' => 'Dashboard E-Skripsi', 'route' => 'theses.dashboard'], $graduateThesisDashboard]],
            ['label' => 'Data Master', 'items' => [
                $master('perguruan-tinggi', 'Perguruan Tinggi'), $master('fakultas', 'Fakultas'),
                $master('program-studi', 'Program Studi'), $master('dosen', 'Dosen'),
                $master('asisten-dosen', 'Asisten Dosen'), $master('mahasiswa', 'Mahasiswa'),
                $master('mata-kuliah', 'Mata Kuliah'), $master('ruang', 'Ruang'),
                $master('bobot-nilai', 'Bobot Nilai'),
            ]],
            ['label' => 'Akademik', 'items' => [
                $calendar, ['label' => 'Jadwal Kuliah', 'route' => 'class-schedules.index'], ['label' => 'Registrasi Ulang', 'route' => 're-enrollment.index'],
                ['label' => 'KRS Mahasiswa', 'route' => 'krs.index'], ['label' => 'Monitoring KRS', 'route' => 'monitoring-krs.index'], ['label' => 'KRS Transfer', 'route' => 'krs-transfer.index'],
                ['label' => 'Transfer Nilai MBKM', 'route' => 'mbkm-transfers.index'], ['label' => 'Transfer Kelas', 'route' => 'class-transfers.index'], ['label' => 'Absen Kuliah', 'route' => 'course-attendance.index'],
                ['label' => 'Nilai Mata Kuliah', 'route' => 'grade-management.index'], ['label' => 'Aktivitas Mengajar', 'route' => 'activity-teaching.index'],
                ['label' => 'Aktivitas Kuliah Mahasiswa', 'route' => 'student-akm.index'], ['label' => 'Aktivitas Mahasiswa', 'route' => 'student-activities.index'], ['label' => 'Lulus, Cuti, DO', 'route' => 'student-status.index'],
                ['label' => 'KHS Mahasiswa', 'route' => 'khs.index'], ['label' => 'Transkrip', 'route' => 'transcripts.index'], ['label' => 'Penghapusan Mata Kuliah', 'route' => 'course-removals.index'], ['label' => 'Hasil Survey EDOM', 'route' => 'survey-edom.results.index'],
            ]],
            $shortSemesterMenu,
            $lmsMenu,
            ['label' => 'E-Skripsi', 'items' => [
                ['label' => 'Validasi Usulan Judul', 'route' => 'theses.titles.validation.index'],
                ['label' => 'Penugasan Dosen', 'route' => 'theses.assignments.index'],
                ['label' => 'Bimbingan Skripsi', 'route' => 'theses.bimbingan.index'],
                ['label' => 'Ujian Skripsi', 'route' => 'theses.exams.index'],
                ['label' => 'Upload Data Skripsi', 'route' => 'theses.upload.index'],
            ]],
            $graduateThesisMenu,
            ['label' => 'Keuangan', 'items' => [
                ['label' => 'Periode Uang Kuliah', 'route' => 'finance.periods.index'],
                ['label' => 'Kategori Uang Kuliah', 'route' => 'finance.categories.index'],
                ['label' => 'Pembayaran Uang Kuliah', 'route' => 'finance.ukt.index'],
                ['label' => 'Iuran Pengembangan Institusi', 'route' => 'finance.ipi.index'],
                ['label' => 'Billing Midtrans', 'route' => 'finance.manual-bills.index'],
                ['label' => 'Riwayat Transaksi Midtrans', 'route' => 'finance.midtrans-transactions.index'],
            ]],
            ['label' => 'Kuliah Kerja Nyata (KKN)', 'items' => [
                ['label' => 'Periode KKN', 'route' => 'kkn.periods.index'],
                ['label' => 'Usulan Peserta KKN', 'route' => 'kkn.proposals.index'],
                ['label' => 'Verifikasi KKN', 'route' => 'kkn.validations.index'],
                ['label' => 'Lokasi KKN', 'route' => 'kkn.locations.index'],
                ['label' => 'Peserta dan Ploting KKN', 'route' => 'kkn.participants.index'],
            ]],
            ['label' => 'Validasi Nilai', 'items' => [
                ['label' => 'Validasi Nilai Mahasiswa', 'route' => 'grade-validation.index'],
            ]],
            ['label' => 'Data Wisuda', 'items' => [
                ['label' => 'Periode Wisuda', 'route' => 'graduation-periods.index'], ['label' => 'Peserta Wisuda', 'route' => 'graduation-validations.index'],
            ]],
            ['label' => 'Administrasi & Tools', 'items' => [
                ['label' => 'Log Aktivitas', 'route' => 'activity-logs.index'],
                ['label' => 'Verifikasi CAMABA', 'route' => 'camaba-verification.index'],
                ['label' => 'Cek AKM SIMAK - Feeder', 'route' => 'akm-feeder.index'],
                ['label' => 'Pengguna SIMAK', 'route' => 'simak-users.index'], ['label' => 'Pengaturan Feeder', 'route' => 'feeder-settings.index'],
                ['label' => 'Pengaturan Sistem', 'route' => 'system-settings.index'],
                ['label' => 'Survey EDOM', 'route' => 'survey-edom.questions.index'],
                ['label' => 'Export ke Feeder', 'route' => 'feeder-export.index'],
            ]],
        ],
        'dosen' => [
            ['label' => 'Dashboard', 'items' => [$home, $lmsDashboard, ['label' => 'Dashboard E-Skripsi', 'route' => 'theses.dashboard'], $graduateThesisDashboard]],
            ['label' => 'Akademik', 'items' => [
                ['label' => 'Jadwal Mata Kuliah', 'route' => 'class-schedules.index'], ['label' => 'Absensi Kuliah', 'route' => 'course-attendance.index'],
                ['label' => 'Input Nilai', 'route' => 'grade-management.index'], ['label' => 'KRS Mahasiswa PA', 'route' => 'krs.index'], ['label' => 'Transkrip dan KHS Mahasiswa', 'route' => 'transcripts.index'],
            ]],
            $shortSemesterGradeMenu,
            $lmsMenu,
            ['label' => 'E-Skripsi', 'items' => [
                ['label' => 'Bimbingan Skripsi', 'route' => 'theses.bimbingan.index'],
                ['label' => 'Ujian Skripsi', 'route' => 'theses.exams.index'],
            ]],
            $graduateThesisLecturerMenu,
            ['label' => 'Evaluasi', 'items' => [['label' => 'Hasil Survey EDOM', 'route' => 'survey-edom.results.index']]],
        ],
        'asdos' => [
            ['label' => 'Dashboard', 'items' => [$home]],
            ['label' => 'Akademik', 'items' => [
                ['label' => 'Jadwal Mata Kuliah', 'route' => 'class-schedules.index'], ['label' => 'Absensi Kuliah', 'route' => 'course-attendance.index'], ['label' => 'Input Nilai', 'route' => 'grade-management.index'], ['label' => 'Hasil Survey EDOM', 'route' => 'survey-edom.results.index'],
            ]],
            $shortSemesterGradeMenu,
        ],
        'mahasiswa' => [
            ['label' => 'Dashboard', 'items' => [$home, $lmsDashboard, $graduateThesisDashboard]],
            ['label' => 'Akademik', 'items' => [
                ['label' => 'Kartu Rencana Studi', 'route' => 'krs.index'], ['label' => 'Jadwal Kuliah', 'route' => 'class-schedules.index'],
                ['label' => 'Kartu Hasil Studi', 'route' => 'khs.index'], ['label' => 'Transkrip Nilai', 'route' => 'transcripts.index'],
            ]],
            $lmsStudentMenu,
            $thesisStudentMenu,
            $graduateThesisStudentMenu,
            ['label' => 'Layanan Mahasiswa', 'items' => [
                ['label' => 'Tagihan Uang Kuliah & Iuran Pengembangan Institusi', 'route' => 'student-payments.index'], ['label' => 'Pendaftaran Wisuda', 'route' => 'graduation-student.index'],
                ['label' => 'Kuliah Kerja Nyata (KKN)', 'route' => 'kkn.student.index'],
            ]],
        ],
        'regulang' => [
            ['label' => 'Dashboard', 'items' => [$home]],
            ['label' => 'Registrasi', 'items' => [
                ['label' => 'Registrasi Ulang', 'route' => 're-enrollment.index'], ['label' => 'Verifikasi CAMABA', 'route' => 'camaba-verification.index'],
                $master('mahasiswa', 'Data Mahasiswa'), $master('mata-kuliah', 'Mata Kuliah'), ['label' => 'KHS Mahasiswa', 'route' => 'khs.index'],
                ['label' => 'KRS Mahasiswa', 'route' => 'krs.index'],
            ]],
        ],
        'pimuniv' => [
            ['label' => 'Dashboard', 'items' => [$masterDashboard, $academicDashboard, $lmsDashboard, ['label' => 'Dashboard E-Skripsi', 'route' => 'theses.dashboard'], $graduateThesisDashboard, ['label' => 'Dashboard Keuangan', 'route' => 'finance.dashboard']]],
            ['label' => 'Data Master', 'items' => [
                $master('perguruan-tinggi', 'Perguruan Tinggi'), $master('fakultas', 'Fakultas'),
                $master('program-studi', 'Program Studi'), $master('dosen', 'Dosen'),
                $master('asisten-dosen', 'Asisten Dosen'), $master('mahasiswa', 'Mahasiswa'),
                $master('mata-kuliah', 'Mata Kuliah'), $master('ruang', 'Ruang'),
                $master('bobot-nilai', 'Bobot Nilai'),
            ]],
            ['label' => 'Akademik', 'items' => [
                $calendar, ['label' => 'Jadwal Kuliah', 'route' => 'class-schedules.index'], ['label' => 'KRS Mahasiswa', 'route' => 'krs.index'],
                ['label' => 'Monitoring KRS', 'route' => 'monitoring-krs.index'],
                ['label' => 'Nilai Mata Kuliah', 'route' => 'grade-management.index'], ['label' => 'KHS Mahasiswa', 'route' => 'khs.index'], ['label' => 'Aktivitas Mengajar', 'route' => 'activity-teaching.index'],
            ]],
            $shortSemesterMenu,
            $lmsMenu,
            ['label' => 'Pemantauan', 'items' => [
                ['label' => 'Validasi Usulan Judul', 'route' => 'theses.titles.validation.index'],
                ['label' => 'Penugasan Dosen', 'route' => 'theses.assignments.index'],
                ['label' => 'Bimbingan Skripsi', 'route' => 'theses.bimbingan.index'],
                ['label' => 'Ujian Skripsi', 'route' => 'theses.exams.index'],
                ['label' => 'Rekapitulasi Universitas'],
                ['label' => 'Instrumen Survey EDOM', 'route' => 'survey-edom.questions.index'], ['label' => 'Hasil Survey EDOM', 'route' => 'survey-edom.results.index'],
            ]],
            $graduateThesisMenu,
        ],
        'pimfak' => [
            ['label' => 'Dashboard', 'items' => [$masterDashboard, $academicDashboard, $lmsDashboard, ['label' => 'Dashboard E-Skripsi', 'route' => 'theses.dashboard'], $graduateThesisDashboard]],
            ['label' => 'Data Master', 'items' => [
                $master('perguruan-tinggi', 'Perguruan Tinggi'), $master('fakultas', 'Fakultas'),
                $master('program-studi', 'Program Studi'), $master('dosen', 'Dosen'),
                $master('asisten-dosen', 'Asisten Dosen'), $master('mahasiswa', 'Mahasiswa'),
                $master('mata-kuliah', 'Mata Kuliah'), $master('ruang', 'Ruang'),
                $master('bobot-nilai', 'Bobot Nilai'),
            ]],
            ['label' => 'Akademik', 'items' => [
                $calendar, ['label' => 'Jadwal Kuliah', 'route' => 'class-schedules.index'], ['label' => 'KRS Mahasiswa', 'route' => 'krs.index'],
                ['label' => 'Monitoring KRS', 'route' => 'monitoring-krs.index'],
                ['label' => 'Nilai Mata Kuliah', 'route' => 'grade-management.index'], ['label' => 'KHS Mahasiswa', 'route' => 'khs.index'], ['label' => 'Aktivitas Mengajar', 'route' => 'activity-teaching.index'],
            ]],
            $shortSemesterMenu,
            $lmsMenu,
            ['label' => 'Pemantauan', 'items' => [
                ['label' => 'Validasi Usulan Judul', 'route' => 'theses.titles.validation.index'],
                ['label' => 'Penugasan Dosen', 'route' => 'theses.assignments.index'],
                ['label' => 'Bimbingan Skripsi', 'route' => 'theses.bimbingan.index'],
                ['label' => 'Ujian Skripsi', 'route' => 'theses.exams.index'],
                ['label' => 'Rekapitulasi Fakultas'],
                ['label' => 'Instrumen Survey EDOM', 'route' => 'survey-edom.questions.index'], ['label' => 'Hasil Survey EDOM', 'route' => 'survey-edom.results.index'],
            ]],
            $graduateThesisMenu,
        ],
        'userprodi' => [
            ['label' => 'Dashboard', 'items' => [$masterDashboard, $academicDashboard, $lmsDashboard, ['label' => 'Dashboard E-Skripsi', 'route' => 'theses.dashboard'], $graduateThesisDashboard]],
            ['label' => 'Data Master', 'items' => [
                $master('dosen', 'Dosen'), $master('asisten-dosen', 'Asisten Dosen'),
                $master('mahasiswa', 'Mahasiswa'), $master('mata-kuliah', 'Mata Kuliah'),
                $master('ruang', 'Ruang'), $master('bobot-nilai', 'Bobot Nilai'),
            ]],
            ['label' => 'Akademik', 'items' => [
                $calendar, ['label' => 'Jadwal Kuliah', 'route' => 'class-schedules.index'], ['label' => 'KRS Mahasiswa', 'route' => 'krs.index'],
                ['label' => 'Monitoring KRS', 'route' => 'monitoring-krs.index'], ['label' => 'KRS Transfer', 'route' => 'krs-transfer.index'], ['label' => 'Transfer Nilai MBKM', 'route' => 'mbkm-transfers.index'],
                ['label' => 'Transfer Kelas', 'route' => 'class-transfers.index'], ['label' => 'Absensi Kuliah', 'route' => 'course-attendance.index'], ['label' => 'Nilai Mata Kuliah', 'route' => 'grade-management.index'],
                ['label' => 'Aktivitas Kuliah Mahasiswa', 'route' => 'student-akm.index'], ['label' => 'Aktivitas Mengajar', 'route' => 'activity-teaching.index'], ['label' => 'Aktivitas Mahasiswa', 'route' => 'student-activities.index'],
                ['label' => 'Lulus, Cuti, DO', 'route' => 'student-status.index'], ['label' => 'KHS Mahasiswa', 'route' => 'khs.index'], ['label' => 'Transkrip', 'route' => 'transcripts.index'],
                ['label' => 'Cek AKM SIMAK - Feeder', 'route' => 'akm-feeder.index'],
                ['label' => 'Penghapusan Mata Kuliah', 'route' => 'course-removals.index'],
            ]],
            $shortSemesterMenu,
            $lmsMenu,
            ['label' => 'E-Skripsi', 'items' => [
                ['label' => 'Verifikasi Judul', 'route' => 'theses.titles.validation.index'],
                ['label' => 'Penugasan Dosen', 'route' => 'theses.assignments.index'],
                ['label' => 'Bimbingan Skripsi', 'route' => 'theses.bimbingan.index'],
                ['label' => 'Ujian Skripsi', 'route' => 'theses.exams.index'],
            ]],
            $graduateThesisMenu,
            ['label' => 'Layanan Prodi', 'items' => [
                ['label' => 'Hasil Survey EDOM', 'route' => 'survey-edom.results.index'],
            ]],
        ],
        'usertu' => [
            ['label' => 'Dashboard', 'items' => [$masterDashboard, $academicDashboard, $lmsDashboard, ['label' => 'Dashboard E-Skripsi', 'route' => 'theses.dashboard'], $graduateThesisDashboard]],
            ['label' => 'Data Master', 'items' => [
                $master('dosen', 'Dosen'), $master('asisten-dosen', 'Asisten Dosen'),
                $master('mahasiswa', 'Mahasiswa'), $master('mata-kuliah', 'Mata Kuliah'),
                $master('ruang', 'Ruang'), $master('bobot-nilai', 'Bobot Nilai'),
            ]],
            ['label' => 'Akademik', 'items' => [
                $calendar, ['label' => 'Jadwal Kuliah', 'route' => 'class-schedules.index'], ['label' => 'KRS Mahasiswa', 'route' => 'krs.index'],
                ['label' => 'Monitoring KRS', 'route' => 'monitoring-krs.index'], ['label' => 'KRS Transfer', 'route' => 'krs-transfer.index'], ['label' => 'KHS Mahasiswa', 'route' => 'khs.index'], ['label' => 'Absensi Kuliah', 'route' => 'course-attendance.index'], ['label' => 'Nilai Mata Kuliah', 'route' => 'grade-management.index'],
                ['label' => 'Aktivitas Kuliah Mahasiswa', 'route' => 'student-akm.index'], ['label' => 'Aktivitas Mengajar', 'route' => 'activity-teaching.index'], ['label' => 'Aktivitas Mahasiswa', 'route' => 'student-activities.index'],
                ['label' => 'Lulus, Cuti, DO', 'route' => 'student-status.index'], ['label' => 'Transkrip', 'route' => 'transcripts.index'],
                ['label' => 'Cek AKM SIMAK - Feeder', 'route' => 'akm-feeder.index'],
            ]],
            $shortSemesterMenu,
            $lmsMenu,
            ['label' => 'E-Skripsi', 'items' => [
                ['label' => 'Verifikasi Judul', 'route' => 'theses.titles.validation.index'],
                ['label' => 'Penugasan Dosen', 'route' => 'theses.assignments.index'],
                ['label' => 'Bimbingan Skripsi', 'route' => 'theses.bimbingan.index'],
                ['label' => 'Ujian Skripsi', 'route' => 'theses.exams.index'],
            ]],
            $graduateThesisMenu,
            ['label' => 'Layanan Prodi', 'items' => [
                ['label' => 'Hasil Survey EDOM', 'route' => 'survey-edom.results.index'],
                ['label' => 'Usulan Peserta KKN', 'route' => 'kkn.proposals.index'], ['label' => 'Peserta Wisuda', 'route' => 'graduation-participants.index'],
                ['label' => 'Export ke Feeder', 'route' => 'feeder-export.index'],
            ]],
        ],
        'keuangan' => [
            ['label' => 'Dashboard', 'items' => [
                ['label' => 'Dashboard Keuangan', 'route' => 'finance.dashboard'],
            ]],
            ['label' => 'Keuangan', 'items' => [
                ['label' => 'Periode Uang Kuliah', 'route' => 'finance.periods.index'],
                ['label' => 'Kategori Uang Kuliah', 'route' => 'finance.categories.index'],
                ['label' => 'Pembayaran Uang Kuliah', 'route' => 'finance.ukt.index'],
                ['label' => 'Iuran Pengembangan Institusi', 'route' => 'finance.ipi.index'],
                ['label' => 'Billing Midtrans', 'route' => 'finance.manual-bills.index'],
                ['label' => 'Riwayat Transaksi Midtrans', 'route' => 'finance.midtrans-transactions.index'],
            ]],
        ],
        'wisuda' => [
            ['label' => 'Dashboard', 'items' => [$home]],
            ['label' => 'Wisuda', 'items' => [
                ['label' => 'Periode Wisuda', 'route' => 'graduation-periods.index'],
                ['label' => 'Peserta Wisuda', 'route' => 'graduation-participants.index'],
                ['label' => 'Validasi Peserta Wisuda', 'route' => 'graduation-validations.index'],
                ['label' => 'KRS Mahasiswa', 'route' => 'krs.index'],
                ['label' => 'KHS Mahasiswa', 'route' => 'khs.index'],
                ['label' => 'Transkrip Nilai Mahasiswa', 'route' => 'transcripts.index'],
            ]],
        ],
        'validasi' => [
            ['label' => 'Dashboard', 'items' => [$home]],
            ['label' => 'Validasi', 'items' => [
                ['label' => 'Validasi Nilai Mahasiswa', 'route' => 'grade-validation.index'], ['label' => 'Usulan Peserta KKN', 'route' => 'kkn.proposals.index'],
                ['label' => 'Verifikasi KKN', 'route' => 'kkn.validations.index'], ['label' => 'Peserta Wisuda', 'route' => 'graduation-validations.index'],
                ['label' => 'KRS Mahasiswa', 'route' => 'krs.index'],
            ]],
        ],
        'lppm' => [
            ['label' => 'Dashboard', 'items' => [$home]],
            ['label' => 'Validasi Nilai', 'items' => [
                ['label' => 'Validasi Nilai Mahasiswa', 'route' => 'grade-validation.index'],
            ]],
            ['label' => 'Kuliah Kerja Nyata (KKN)', 'items' => [
                ['label' => 'Lokasi KKN', 'route' => 'kkn.locations.index'],
                ['label' => 'Peserta dan Ploting KKN', 'route' => 'kkn.participants.index'],
            ]],
        ],
        'timkur' => [
            ['label' => 'Dashboard', 'items' => [$home]],
        ],
    ],
];
