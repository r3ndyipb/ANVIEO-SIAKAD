<?php

use App\Http\Controllers\AcademicCalendarController;
use App\Http\Controllers\AcademicDashboardController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ActivityNotificationController;
use App\Http\Controllers\AkmFeederCheckController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\CamabaVerificationController;
use App\Http\Controllers\ClassScheduleController;
use App\Http\Controllers\ClassTransferController;
use App\Http\Controllers\CourseAttendanceController;
use App\Http\Controllers\CourseGradeController;
use App\Http\Controllers\CourseRemovalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EkurikulumController;
use App\Http\Controllers\FeederExportController;
use App\Http\Controllers\FeederInitializationController;
use App\Http\Controllers\FeederSettingsController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\GradeValidationController;
use App\Http\Controllers\GraduateThesisController;
use App\Http\Controllers\GraduationPeriodController;
use App\Http\Controllers\GraduationStudentController;
use App\Http\Controllers\GraduationValidationController;
use App\Http\Controllers\KhsController;
use App\Http\Controllers\KknController;
use App\Http\Controllers\KrsController;
use App\Http\Controllers\KrsTransferController;
use App\Http\Controllers\LmsController;
use App\Http\Controllers\LmsDashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\MbkmTransferController;
use App\Http\Controllers\MonitoringKrsController;
use App\Http\Controllers\PublicLandingController;
use App\Http\Controllers\ReEnrollmentController;
use App\Http\Controllers\SimakUserManagementController;
use App\Http\Controllers\StudentAcademicActivityController;
use App\Http\Controllers\StudentActivityController;
use App\Http\Controllers\StudentPaymentsController;
use App\Http\Controllers\StudentStatusController;
use App\Http\Controllers\SurveyEdomController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\TeachingActivityController;
use App\Http\Controllers\ThesisController;
use App\Http\Controllers\ThesisNotificationController;
use App\Http\Controllers\TranscriptController;
use App\Http\Controllers\UiTranslationController;
use App\Http\Controllers\UserProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicLandingController::class, 'home'])->name('public.home');
Route::get('/fakultas', [PublicLandingController::class, 'faculties'])->name('public.faculties.index');
Route::get('/fakultas/{faculty}', [PublicLandingController::class, 'faculty'])->name('public.faculties.show');
Route::get('/prodi', [PublicLandingController::class, 'programs'])->name('public.programs.index');
Route::get('/prodi/{program}', [PublicLandingController::class, 'program'])->name('public.programs.show');
Route::get('/privacy', [PublicLandingController::class, 'privacy'])->name('public.privacy');
Route::get('/terms', [PublicLandingController::class, 'terms'])->name('public.terms');
Route::get('/accessibility', [PublicLandingController::class, 'accessibility'])->name('public.accessibility');
Route::get('/about', [PublicLandingController::class, 'about'])->name('public.about');
Route::get('/structure', [PublicLandingController::class, 'structure'])->name('public.structure');
Route::get('/admissions', [PublicLandingController::class, 'admissions'])->name('public.admissions');
Route::get('/academic', [PublicLandingController::class, 'academic'])->name('public.academic');
Route::get('/accreditation', [PublicLandingController::class, 'accreditation'])->name('public.accreditation');
Route::get('/payment-information', [PublicLandingController::class, 'payment'])->name('public.payment');
Route::redirect('/payment', '/payment-information');
Route::get('/contact', [PublicLandingController::class, 'contact'])->name('public.contact');
Route::get('/dashboard-data/{pageType}/{identifier?}', [PublicLandingController::class, 'data'])
    ->middleware('throttle:30,1')
    ->name('public.dashboard-data');
Route::get('/dtps-data/{program}', [PublicLandingController::class, 'dtpsDetails'])
    ->middleware('throttle:30,1')
    ->name('public.dtps-details');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:3,1')->name('password.email');
});

Route::get('/password/activate/{type}/{identifier}', [PasswordResetController::class, 'edit'])
    ->middleware('signed')
    ->name('password.activate');
Route::post('/password/activate/{type}/{identifier}', [PasswordResetController::class, 'activate'])
    ->middleware('signed')
    ->name('password.activate.store');
Route::get('/document-verification/{code}', [KrsController::class, 'verifyDocument'])
    ->name('document-verification.show');
Route::get('/khs/validasi/{code}/{student}/{calendar}', [KhsController::class, 'verifyPrint'])
    ->name('khs.documents.verify');
Route::get('/transkrip/validasi/{code}/{student}/{signatory}', [TranscriptController::class, 'verifyPrint'])
    ->whereIn('signatory', ['dekan', 'ketua-prodi'])
    ->name('transcripts.documents.verify');
Route::get('/e-skripsi/validasi/{code}', [ThesisController::class, 'verifyExamDocument'])
    ->name('theses.documents.verify');
Route::get('/e-tesis-disertasi/validasi/{code}', [GraduateThesisController::class, 'verifyExamDocument'])
    ->name('graduate_theses.documents.verify');
Route::get('/lms/validasi-berita-acara/{record}', [LmsController::class, 'verifyPrintedMeeting'])
    ->middleware('signed')
    ->name('lms.meetings.minutes.verify');

Route::middleware('auth')->group(function () {
    Route::post('/language', [LocaleController::class, 'update'])->name('locale.update');
    Route::get('/language/translations', [UiTranslationController::class, 'index'])->name('locale.translations');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/dtps-details/{program}', [DashboardController::class, 'dtpsDetails'])->name('dashboard.dtps-details');
    Route::get('/tagihan-mahasiswa', [StudentPaymentsController::class, 'index'])->name('student-payments.index');
    Route::get('/tagihan-mahasiswa/{type}/{payment}/billing', [StudentPaymentsController::class, 'printBilling'])
        ->whereIn('type', ['ukt', 'ipi'])->name('student-payments.billing');
    Route::post('/tagihan-mahasiswa/{payment}/midtrans', [StudentPaymentsController::class, 'createMidtransCheckout'])
        ->name('student-payments.midtrans');
    Route::post('/tagihan-mahasiswa/{payment}/midtrans/status', [StudentPaymentsController::class, 'checkMidtransStatus'])
        ->name('student-payments.midtrans.status');
    Route::get('/e-skripsi/notifikasi', [ThesisNotificationController::class, 'index'])
        ->name('theses.notifications.index');
    Route::patch('/e-skripsi/notifikasi/{notification}/read', [ThesisNotificationController::class, 'markRead'])
        ->name('theses.notifications.read');
    Route::patch('/e-skripsi/notifikasi/read-all', [ThesisNotificationController::class, 'markAllRead'])
        ->name('theses.notifications.read-all');
    Route::get('/e-tesis-disertasi/notifikasi', [ThesisNotificationController::class, 'index'])
        ->name('graduate_theses.notifications.index');
    Route::patch('/e-tesis-disertasi/notifikasi/{notification}/read', [ThesisNotificationController::class, 'markRead'])
        ->name('graduate_theses.notifications.read');
    Route::patch('/e-tesis-disertasi/notifikasi/read-all', [ThesisNotificationController::class, 'markAllRead'])
        ->name('graduate_theses.notifications.read-all');
    Route::get('/activity-notifications', [ActivityNotificationController::class, 'index'])
        ->name('activity-notifications.index');
    Route::patch('/activity-notifications/{activity}/read', [ActivityNotificationController::class, 'markRead'])
        ->name('activity-notifications.read');
    Route::patch('/activity-notifications/read-all', [ActivityNotificationController::class, 'markAllRead'])
        ->name('activity-notifications.read-all');
    Route::get('/dashboard-akademik', [AcademicDashboardController::class, 'index'])->name('academic-dashboard.index');
    Route::get('/dashboard-akademik/data', [AcademicDashboardController::class, 'data'])->name('academic-dashboard.data');
    Route::get('/dashboard-akademik/export/excel', [AcademicDashboardController::class, 'exportExcel'])->name('academic-dashboard.export.excel');
    Route::get('/dashboard-akademik/dtps-details', [AcademicDashboardController::class, 'dtpsDetails'])->name('academic-dashboard.dtps-details');
    Route::get('/dashboard-akademik/insights', [AcademicDashboardController::class, 'insights'])->name('academic-dashboard.insights');
    Route::get('/dashboard-lms', [LmsDashboardController::class, 'index'])->name('lms-dashboard.index');
    Route::prefix('lms')->name('lms.')->controller(LmsController::class)->group(function () {
        Route::get('/materi', 'materials')->name('materials.index');
        Route::get('/pertemuan', 'meetings')->name('meetings.index');
        Route::get('/tugas', 'tasks')->name('tasks.index');
        Route::get('/bank-soal', 'questions')->name('questions.index');
        Route::get('/bank-soal/template', 'downloadQuestionTemplate')->name('questions.template');
        Route::post('/bank-soal/upload', 'importQuestions')->name('questions.import');
        Route::get('/diskusi', 'discussions')->name('discussions.index');
        Route::get('/{module}/{record}', 'show')->whereIn('module', ['materi', 'pertemuan', 'tugas', 'bank-soal', 'diskusi'])->name('show');
        Route::post('/materi', 'storeMaterial')->name('materials.store');
        Route::put('/materi/{record}', 'updateMaterial')->name('materials.update');
        Route::delete('/materi/{record}', 'destroyMaterial')->name('materials.destroy');
        Route::get('/materi/{record}/unduh', 'downloadMaterial')->name('materials.download');
        Route::post('/pertemuan', 'storeMeeting')->name('meetings.store');
        Route::put('/pertemuan/{record}', 'updateMeeting')->name('meetings.update');
        Route::delete('/pertemuan/{record}', 'destroyMeeting')->name('meetings.destroy');
        Route::get('/pertemuan/{record}/absensi', 'attendanceRoster')->name('attendance.roster');
        Route::get('/pertemuan/{record}/cetak-absen', 'printMeetingAttendance')->name('meetings.attendance.print');
        Route::get('/pertemuan/{record}/bank-soal', 'meetingQuestions')->name('meetings.questions');
        Route::get('/pertemuan/{record}/ujian/buat', 'createExam')->name('exams.create');
        Route::post('/pertemuan/{record}/absen', 'selfAttend')->name('attendance.self');
        Route::put('/pertemuan/{record}/absensi/{student}', 'markAttendance')->name('attendance.mark');
        Route::delete('/pertemuan/{record}/absensi/{student}', 'cancelAttendance')->name('attendance.cancel');
        Route::post('/bank-soal', 'storeQuestion')->name('questions.store');
        Route::put('/bank-soal/{record}', 'updateQuestion')->name('questions.update');
        Route::delete('/bank-soal/{record}', 'destroyQuestion')->name('questions.destroy');
        Route::post('/pertemuan/{record}/ujian', 'storeExam')->name('exams.store');
        Route::get('/ujian/{record}', 'showExam')->name('exams.show');
        Route::post('/ujian/{record}/mulai', 'startExam')->name('exams.start');
        Route::put('/ujian/{record}', 'updateExam')->name('exams.update');
        Route::put('/ujian/{record}/jawaban', 'saveExamAnswers')->name('exams.answers');
        Route::post('/ujian/{record}/kumpulkan', 'submitExam')->name('exams.submit');
        Route::put('/ujian/{record}/hasil/{result}', 'gradeEssay')->name('exams.grade');
        Route::post('/tugas', 'storeTask')->name('tasks.store');
        Route::put('/tugas/{record}', 'updateTask')->name('tasks.update');
        Route::delete('/tugas/{record}', 'destroyTask')->name('tasks.destroy');
        Route::post('/tugas/{record}/kumpulkan', 'submitTask')->name('tasks.submit');
        Route::put('/tugas/{record}/pengumpulan/{submission}', 'gradeTask')->name('tasks.grade');
        Route::post('/diskusi', 'storeDiscussion')->name('discussions.store');
        Route::put('/diskusi/{record}', 'updateDiscussion')->name('discussions.update');
        Route::delete('/diskusi/{record}', 'destroyDiscussion')->name('discussions.destroy');
        Route::post('/diskusi/{record}/pesan', 'storeDiscussionMessage')->name('discussions.messages.store');
        Route::delete('/diskusi/{record}/pesan/{message}', 'destroyDiscussionMessage')->name('discussions.messages.destroy');
    });
    Route::get('/cek-akm-feeder', [AkmFeederCheckController::class, 'index'])->name('akm-feeder.index');
    Route::get('/cek-akm-feeder/data', [AkmFeederCheckController::class, 'data'])->name('akm-feeder.data');
    Route::post('/cek-akm-feeder/status', [AkmFeederCheckController::class, 'updateStatus'])->name('akm-feeder.status.update');
    Route::get('/verifikasi-camaba', [CamabaVerificationController::class, 'index'])->name('camaba-verification.index');
    Route::get('/verifikasi-camaba/data', [CamabaVerificationController::class, 'data'])->name('camaba-verification.data');
    Route::get('/verifikasi-camaba/template', [CamabaVerificationController::class, 'template'])->name('camaba-verification.template');
    Route::post('/verifikasi-camaba/upload', [CamabaVerificationController::class, 'upload'])->name('camaba-verification.upload');
    Route::get('/verifikasi-camaba/wilayah', [CamabaVerificationController::class, 'regionOptions'])->name('camaba-verification.regions');
    Route::get('/verifikasi-camaba/{candidate}', [CamabaVerificationController::class, 'show'])->name('camaba-verification.show');
    Route::post('/verifikasi-camaba/{candidate}', [CamabaVerificationController::class, 'verify'])->name('camaba-verification.verify');
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('/survey-edom/questions', [SurveyEdomController::class, 'questions'])->name('survey-edom.questions.index');
    Route::get('/survey-edom/questions/create', [SurveyEdomController::class, 'createQuestion'])->name('survey-edom.questions.create');
    Route::post('/survey-edom/questions', [SurveyEdomController::class, 'storeQuestion'])->name('survey-edom.questions.store');
    Route::get('/survey-edom/questions/{question}/edit', [SurveyEdomController::class, 'editQuestion'])->name('survey-edom.questions.edit');
    Route::put('/survey-edom/questions/{question}', [SurveyEdomController::class, 'updateQuestion'])->name('survey-edom.questions.update');
    Route::get('/survey-edom/results', [SurveyEdomController::class, 'results'])->name('survey-edom.results.index');
    Route::get('/survey-edom/results/data', [SurveyEdomController::class, 'data'])->name('survey-edom.results.data');
    Route::get('/survey-edom/results/{program}/{calendar}/{lecturer}/export', [SurveyEdomController::class, 'export'])
        ->name('survey-edom.results.export');
    Route::prefix('/e-skripsi')->name('theses.')->controller(ThesisController::class)->group(function () {
        Route::get('/dashboard', 'dashboard')->name('dashboard');
        Route::get('/usulan-judul', 'titleProposalIndex')->name('titles.index');
        Route::post('/usulan-judul', 'storeTitleProposal')->name('titles.store');
        Route::get('/validasi-judul', 'titleValidationIndex')->name('titles.validation.index');
        Route::get('/validasi-judul/{proposal}', 'titleValidationShow')->name('titles.validation.show');
        Route::put('/validasi-judul/{proposal}', 'saveTitleValidation')->name('titles.validation.update');
        Route::get('/penugasan-dosen', 'lecturerAssignmentIndex')->name('assignments.index');
        Route::get('/penugasan-dosen/{skripsi}', 'lecturerAssignmentShow')->name('assignments.show');
        Route::put('/penugasan-dosen/{skripsi}', 'saveLecturerAssignment')->name('assignments.update');
        Route::put('/penugasan-dosen/{skripsi}/judul', 'updateAssignedThesisTitle')->name('assignments.title.update');
        Route::get('/bimbingan', 'bimbinganIndex')->name('bimbingan.index');
        Route::get('/bimbingan/{skripsi}', 'bimbinganShow')->name('bimbingan.show');
        Route::get('/bimbingan/{skripsi}/messages', 'messages')->name('bimbingan.messages');
        Route::post('/bimbingan/{skripsi}/messages', 'sendMessage')->name('bimbingan.send');
        Route::get('/ujian', 'examsIndex')->name('exams.index');
        Route::get('/ujian/{exam}', 'examShow')->name('exams.show');
        Route::put('/ujian/{exam}/jadwal', 'scheduleExam')->name('exams.schedule');
        Route::put('/ujian/{exam}/nilai', 'saveGrade')->name('exams.grade');
        Route::get('/ujian/{exam}/cetak', 'printExam')->name('exams.print');
        Route::get('/ujian/{exam}/cetak-berita-acara', 'printMinutes')->name('exams.minutes');
        Route::get('/upload', 'uploadIndex')->name('upload.index');
        Route::get('/upload/template', 'downloadTemplate')->name('upload.template');
        Route::post('/upload', 'upload')->name('upload.store');
    });
    Route::prefix('/e-tesis-disertasi')->name('graduate_theses.')->controller(GraduateThesisController::class)->group(function () {
        Route::get('/dashboard', 'dashboard')->name('dashboard');
        Route::get('/usulan-judul', 'titleProposalIndex')->name('titles.index');
        Route::post('/usulan-judul', 'storeTitleProposal')->name('titles.store');
        Route::get('/validasi-judul', 'titleValidationIndex')->name('titles.validation.index');
        Route::get('/validasi-judul/{proposal}', 'titleValidationShow')->name('titles.validation.show');
        Route::put('/validasi-judul/{proposal}', 'saveTitleValidation')->name('titles.validation.update');
        Route::get('/penugasan-dosen', 'lecturerAssignmentIndex')->name('assignments.index');
        Route::get('/penugasan-dosen/{skripsi}', 'lecturerAssignmentShow')->name('assignments.show');
        Route::put('/penugasan-dosen/{skripsi}', 'saveLecturerAssignment')->name('assignments.update');
        Route::put('/penugasan-dosen/{skripsi}/judul', 'updateAssignedThesisTitle')->name('assignments.title.update');
        Route::get('/bimbingan', 'bimbinganIndex')->name('bimbingan.index');
        Route::get('/bimbingan/{skripsi}', 'bimbinganShow')->name('bimbingan.show');
        Route::get('/bimbingan/{skripsi}/messages', 'messages')->name('bimbingan.messages');
        Route::post('/bimbingan/{skripsi}/messages', 'sendMessage')->name('bimbingan.send');
        Route::get('/ujian', 'examsIndex')->name('exams.index');
        Route::get('/ujian/{exam}', 'examShow')->name('exams.show');
        Route::put('/ujian/{exam}/jadwal', 'scheduleExam')->name('exams.schedule');
        Route::put('/ujian/{exam}/nilai', 'saveGrade')->name('exams.grade');
        Route::get('/ujian/{exam}/cetak', 'printExam')->name('exams.print');
        Route::get('/ujian/{exam}/cetak-berita-acara', 'printMinutes')->name('exams.minutes');
        Route::get('/upload', 'uploadIndex')->name('upload.index');
        Route::get('/upload/template', 'downloadTemplate')->name('upload.template');
        Route::post('/upload', 'upload')->name('upload.store');
    });
    Route::prefix('/e-kurikulum')->name('ekurikulum.')->controller(EkurikulumController::class)->group(function () {
        Route::get('/timkur', 'teams')->name('teams.index');
        Route::post('/timkur', 'storeTeam')->name('teams.store');
        Route::put('/timkur/{account}', 'updateTeam')->name('teams.update');
        Route::get('/profil-prodi', 'profile')->name('profile.index');
        Route::put('/profil-prodi', 'updateProfile')->name('profile.update');
        Route::get('/kurikulum', 'curricula')->name('curricula.index');
        Route::get('/kurikulum/create', 'createCurriculum')->name('curricula.create');
        Route::post('/kurikulum', 'storeCurriculum')->name('curricula.store');
        Route::get('/kurikulum/{curriculum}/rps', 'rps')->name('rps.show');
        Route::get('/kurikulum/{curriculum}/rps/{course}/cetak', 'printRps')->name('rps.print');
        Route::get('/kurikulum/{curriculum}/rps/{course}/export', 'exportRps')->name('rps.export');
        Route::get('/kurikulum/{curriculum}/export', 'exportCurriculum')->name('curricula.export');
        Route::get('/kurikulum/{curriculum}', 'showCurriculum')->name('curricula.show');
        Route::get('/template/{kind}', 'template')->name('template');
        Route::post('/kurikulum/{curriculum}/import/{kind}', 'import')->name('import');
        Route::post('/kurikulum/{curriculum}/data/{entity}/{record?}', 'saveData')->name('data.store');
        Route::put('/kurikulum/{curriculum}/data/{entity}/{record}', 'saveData')->name('data.update');
        Route::delete('/kurikulum/{curriculum}/data/{entity}/{record}', 'deleteData')->name('data.delete');
    });
    Route::get('/settings/feeder', [FeederSettingsController::class, 'index'])->name('feeder-settings.index');
    Route::put('/settings/feeder', [FeederSettingsController::class, 'update'])->name('feeder-settings.update');
    Route::post('/settings/feeder/test', [FeederSettingsController::class, 'testConnection'])->name('feeder-settings.test');
    Route::get('/feeder-initialization', [FeederInitializationController::class, 'index'])->name('feeder-initialization.index');
    Route::post('/feeder-initialization/jobs', [FeederInitializationController::class, 'start'])->name('feeder-initialization.start');
    Route::get('/feeder-initialization/jobs/status', [FeederInitializationController::class, 'status'])->name('feeder-initialization.status');
    Route::post('/feeder-initialization/jobs/next', [FeederInitializationController::class, 'next'])->name('feeder-initialization.next');
    Route::post('/feeder-initialization/jobs/stage', [FeederInitializationController::class, 'activateStage'])->name('feeder-initialization.stage');
    Route::post('/feeder-initialization/jobs/restart', [FeederInitializationController::class, 'restartStage'])->name('feeder-initialization.restart');
    Route::post('/feeder-initialization/jobs/step', [FeederInitializationController::class, 'step'])->name('feeder-initialization.step');
    Route::get('/settings/system', [SystemSettingsController::class, 'index'])->name('system-settings.index');
    Route::put('/settings/system/branding', [SystemSettingsController::class, 'updateBranding'])->name('system-settings.branding');
    Route::post('/settings/system/hero/{slot}', [SystemSettingsController::class, 'saveHero'])->whereNumber('slot')->name('system-settings.hero');
    Route::put('/settings/system/smtp', [SystemSettingsController::class, 'saveMail'])->name('system-settings.smtp');
    Route::post('/settings/system/smtp/test', [SystemSettingsController::class, 'testMail'])->middleware('throttle:3,1')->name('system-settings.smtp.test');
    Route::put('/settings/system/midtrans', [SystemSettingsController::class, 'saveMidtrans'])->name('system-settings.midtrans');
    Route::post('/settings/system/midtrans/test', [SystemSettingsController::class, 'testMidtrans'])->middleware('throttle:3,1')->name('system-settings.midtrans.test');
    Route::get('/feeder-export', [FeederExportController::class, 'index'])->name('feeder-export.index');
    Route::post('/feeder-export/curricula', [FeederExportController::class, 'storeCurriculum'])->name('feeder-export.curricula.store');
    Route::post('/feeder-export/curricula/{curriculum}/sync', [FeederExportController::class, 'updateCurriculum'])
        ->name('feeder-export.curricula.sync');
    Route::get('/feeder-export/curricula/{curriculum}/reports', [FeederExportController::class, 'reports'])
        ->name('feeder-export.reports');
    Route::post('/feeder-export/jobs', [FeederExportController::class, 'startJob'])->name('feeder-export.jobs.start');
    Route::get('/feeder-export/jobs/{job}', [FeederExportController::class, 'status'])->name('feeder-export.jobs.status');
    Route::post('/feeder-export/jobs/{job}/step', [FeederExportController::class, 'step'])->name('feeder-export.jobs.step');
    Route::post('/feeder-export/jobs/{job}/retry', [FeederExportController::class, 'retry'])->name('feeder-export.jobs.retry');
    Route::prefix('/keuangan')->name('finance.')->controller(FinanceController::class)->group(function () {
        Route::get('/dashboard', 'dashboard')->name('dashboard');
        Route::get('/periode-ukt', 'periods')->name('periods.index');
        Route::post('/periode-ukt', 'storePeriod')->name('periods.store');
        Route::put('/periode-ukt/{period}', 'updatePeriod')->name('periods.update');
        Route::post('/periode-ukt/{period}/aktifkan', 'activatePeriod')->name('periods.activate');
        Route::get('/kategori-ukt', 'categories')->name('categories.index');
        Route::get('/kategori-ukt/data', 'categoryData')->name('categories.data');
        Route::get('/kategori-ukt/template', 'categoryUploadTemplate')->name('categories.template');
        Route::post('/kategori-ukt/upload', 'uploadCategories')->name('categories.upload');
        Route::get('/pembayaran-ukt', 'ukt')->name('ukt.index');
        Route::get('/pembayaran-ukt/data', 'paymentDataUkt')->name('ukt.data');
        Route::post('/pembayaran-ukt/generate', 'generateUkt')->name('ukt.generate');
        Route::get('/pembayaran-ukt/export', 'exportUkt')->name('ukt.export');
        Route::put('/pembayaran-ukt/{payment}/nominal', 'updateUktAmount')->name('ukt.amount');
        Route::get('/riwayat-transaksi-midtrans', 'midtransTransactions')->name('midtrans-transactions.index');
        Route::get('/billing-manual', 'manualBills')->name('manual-bills.index');
        Route::post('/billing-manual', 'createManualBill')->name('manual-bills.store');
        Route::get('/billing-manual/{bill}/cetak', 'printManualBill')->name('manual-bills.print');
        Route::post('/billing-manual/{bill}/status', 'checkManualBillStatus')->name('manual-bills.status');
        Route::post('/pembayaran/{type}/{payment}/midtrans/status', 'checkMidtransStatus')
            ->whereIn('type', ['ukt', 'ipi'])->name('payments.midtrans-status');
        Route::post('/pembayaran/{type}/{payment}/manual-paid', 'markPaymentPaidManually')
            ->whereIn('type', ['ukt', 'ipi'])->name('payments.manual-paid');
        Route::get('/pembayaran/{type}/{payment}/billing', 'printPaymentBilling')
            ->whereIn('type', ['ukt', 'ipi'])->name('payments.billing');
        Route::get('/pembayaran-ipi', 'ipi')->name('ipi.index');
        Route::get('/pembayaran-ipi/data', 'paymentDataIpi')->name('ipi.data');
        Route::get('/pembayaran-ipi/template', 'ipiUploadTemplate')->name('ipi.template');
        Route::post('/pembayaran-ipi/upload', 'uploadIpi')->name('ipi.upload');
        Route::get('/pembayaran-ipi/export', 'exportIpi')->name('ipi.export');
        Route::put('/pembayaran-ipi/{payment}/nominal', 'updateIpiAmount')->name('ipi.amount');
    });
    Route::prefix('/periode-wisuda')->name('graduation-periods.')->controller(GraduationPeriodController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/data', 'data')->name('data');
        Route::post('/', 'store')->name('store');
        Route::put('/{period}', 'update')->name('update');
        Route::post('/{period}/aktifkan', 'activate')->name('activate');
    });
    Route::prefix('/peserta-wisuda')->name('graduation-participants.')->controller(GraduationPeriodController::class)->group(function () {
        Route::get('/', 'graduationProposals')->name('index');
        Route::get('/tambah', 'createGraduationProposals')->name('create');
        Route::post('/', 'storeGraduationProposals')->name('store');
    });
    Route::prefix('/wisuda-mahasiswa')->name('graduation-student.')->controller(GraduationStudentController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/dokumen/{document}', 'upload')->whereIn('document', ['foto', 'bebas_perpus', 'bebas_spp', 'skripsi'])
            ->middleware('throttle:10,1')->name('documents.upload');
        Route::get('/dokumen/{document}/unduh', 'download')->whereIn('document', ['foto', 'bebas_perpus', 'bebas_spp', 'skripsi'])
            ->name('documents.download');
    });
    Route::prefix('/validasi-wisuda')->name('graduation-validations.')->controller(GraduationValidationController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{period}/excel', 'exportExcel')->name('export.excel');
        Route::get('/{period}/pdf', 'exportPdf')->name('export.pdf');
        Route::get('/{period}/ppt', 'exportPresentation')->name('export.ppt');
        Route::get('/{period}/{participant}', 'show')->name('show');
        Route::put('/{period}/{participant}', 'update')->middleware('throttle:20,1')->name('update');
        Route::get('/{period}/{participant}/dokumen/{document}', 'download')
            ->whereIn('document', ['foto', 'bebas_perpus', 'bebas_spp', 'skripsi'])->name('documents.download');
    });
    Route::prefix('/validasi-nilai')->name('grade-validation.')->controller(GradeValidationController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{student}/khs', 'khs')->name('khs');
        Route::get('/{student}/transkrip', 'transcript')->name('transcript');
    });
    Route::prefix('/kkn')->name('kkn.')->controller(KknController::class)->group(function () {
        Route::get('/periode', 'periods')->name('periods.index');
        Route::post('/periode', 'storePeriod')->name('periods.store');
        Route::put('/periode/{period}', 'updatePeriod')->name('periods.update');
        Route::post('/periode/{period}/aktifkan', 'activatePeriod')->name('periods.activate');
        Route::get('/lokasi', 'locations')->name('locations.index');
        Route::post('/lokasi', 'storeLocation')->name('locations.store');
        Route::put('/lokasi/{location}', 'updateLocation')->name('locations.update');
        Route::get('/usulan', 'proposals')->name('proposals.index');
        Route::get('/usulan/tambah', 'createProposals')->name('proposals.create');
        Route::post('/usulan', 'storeProposals')->name('proposals.store');
        Route::get('/verifikasi', 'validations')->name('validations.index');
        Route::get('/verifikasi/{participant}', 'validationDetail')->name('validations.show');
        Route::put('/verifikasi/{participant}', 'saveValidation')->name('validations.update');
        Route::get('/peserta', 'participants')->name('participants.index');
        Route::put('/peserta/{participant}/lokasi', 'assignLocation')->name('participants.location');
        Route::post('/peserta/ploting-acak', 'randomPlot')->name('participants.random-plot');
        Route::get('/peserta/pdf', 'exportPdf')->name('participants.pdf');
        Route::get('/peserta/excel', 'exportExcel')->name('participants.excel');
        Route::get('/mahasiswa', 'studentIndex')->name('student.index');
        Route::post('/mahasiswa', 'registerStudent')->name('student.register');
    });
    Route::get('/administration/users', [SimakUserManagementController::class, 'index'])->name('simak-users.index');
    Route::get('/administration/users/data', [SimakUserManagementController::class, 'data'])->name('simak-users.data');
    Route::get('/administration/users/{type}/create', [SimakUserManagementController::class, 'create'])->name('simak-users.create');
    Route::post('/administration/users/{type}', [SimakUserManagementController::class, 'store'])->name('simak-users.store');
    Route::get('/administration/users/{type}/{account}/edit', [SimakUserManagementController::class, 'edit'])->name('simak-users.edit');
    Route::put('/administration/users/{type}/{account}', [SimakUserManagementController::class, 'update'])->name('simak-users.update');
    Route::get('/re-enrollment', [ReEnrollmentController::class, 'index'])
        ->name('re-enrollment.index');
    Route::post('/re-enrollment/search', [ReEnrollmentController::class, 'search'])
        ->name('re-enrollment.search');
    Route::post('/re-enrollment/{calendar}/register', [ReEnrollmentController::class, 'register'])
        ->name('re-enrollment.register');
    Route::post('/re-enrollment/{calendar}/cancel', [ReEnrollmentController::class, 'cancel'])
        ->name('re-enrollment.cancel');
    Route::get('/class-schedules', [ClassScheduleController::class, 'index'])->name('class-schedules.index');
    Route::get('/absen-kuliah', [CourseAttendanceController::class, 'index'])->name('course-attendance.index');
    Route::get('/absen-kuliah/pending-approval/{schedule}', [CourseAttendanceController::class, 'pendingApprovals'])
        ->name('course-attendance.pending-approvals');
    Route::get('/absen-kuliah/{schedule}/{document}', [CourseAttendanceController::class, 'print'])->name('course-attendance.print');
    Route::get('/nilai-mk', [CourseGradeController::class, 'index'])->name('grade-management.index');
    Route::get('/nilai-mk/{schedule}/edit', [CourseGradeController::class, 'edit'])->name('grade-management.edit');
    Route::put('/nilai-mk/{schedule}', [CourseGradeController::class, 'update'])->name('grade-management.update');
    Route::post('/nilai-mk/{schedule}/unlock', [CourseGradeController::class, 'unlock'])->name('grade-management.unlock');
    Route::get('/nilai-mk/{schedule}/print', [CourseGradeController::class, 'print'])->name('grade-management.print');
    Route::get('/nilai-sp', [CourseGradeController::class, 'index'])->name('short-semester-grades.index');
    Route::get('/nilai-sp/{schedule}/edit', [CourseGradeController::class, 'edit'])->name('short-semester-grades.edit');
    Route::put('/nilai-sp/{schedule}/peserta/{krs}', [CourseGradeController::class, 'updateShortSemesterGrade'])
        ->name('short-semester-grades.update');
    Route::post('/nilai-sp/{schedule}/unlock', [CourseGradeController::class, 'unlock'])->name('short-semester-grades.unlock');
    Route::get('/nilai-sp/{schedule}/print', [CourseGradeController::class, 'print'])->name('short-semester-grades.print');
    Route::get('/transfer-kelas', [ClassTransferController::class, 'index'])->name('class-transfers.index');
    Route::put('/transfer-kelas/{student}/{krs}', [ClassTransferController::class, 'update'])->name('class-transfers.update');
    Route::get('/krs', [KrsController::class, 'index'])->name('krs.index');
    Route::get('/transfer-mbkm', [MbkmTransferController::class, 'index'])->name('mbkm-transfers.index');
    Route::post('/transfer-mbkm/{student}/register-status', [MbkmTransferController::class, 'registerMbkmStatus'])->name('mbkm-transfers.register');
    Route::delete('/transfer-mbkm/{student}/register-status', [MbkmTransferController::class, 'unregisterMbkmStatus'])->name('mbkm-transfers.unregister');
    Route::post('/transfer-mbkm/{student}', [MbkmTransferController::class, 'store'])->name('mbkm-transfers.store');
    Route::delete('/transfer-mbkm/{student}/{krs}', [MbkmTransferController::class, 'destroy'])->name('mbkm-transfers.destroy');
    Route::get('/krs-transfer', [KrsTransferController::class, 'index'])->name('krs-transfer.index');
    Route::get('/krs-transfer/{student}', [KrsTransferController::class, 'show'])->name('krs-transfer.show');
    Route::get('/krs-transfer/{student}/courses', [KrsTransferController::class, 'editCourses'])->name('krs-transfer.courses.edit');
    Route::put('/krs-transfer/{student}/courses', [KrsTransferController::class, 'saveCourses'])->name('krs-transfer.courses.save');
    Route::get('/krs-transfer/{student}/grades', [KrsTransferController::class, 'editGrades'])->name('krs-transfer.grades.edit');
    Route::put('/krs-transfer/{student}/grades', [KrsTransferController::class, 'saveGrades'])->name('krs-transfer.grades.save');
    Route::get('/krs-transfer/{student}/print', [KrsTransferController::class, 'print'])->name('krs-transfer.print');
    Route::get('/khs', [KhsController::class, 'index'])->name('khs.index');
    Route::get('/khs/{student}/{calendar}/print', [KhsController::class, 'print'])->name('khs.print');
    Route::post('/khs/survey/{krs}', [KhsController::class, 'saveSurvey'])->name('khs.survey.save');
    Route::get('/transkrip', [TranscriptController::class, 'index'])->name('transcripts.index');
    Route::get('/transkrip/{student}/print', [TranscriptController::class, 'print'])->name('transcripts.print');
    Route::get('/transkrip/{student}/program-studi', [TranscriptController::class, 'curriculum'])->name('transcripts.curriculum');
    Route::get('/penghapusan-mata-kuliah', [CourseRemovalController::class, 'index'])->name('course-removals.index');
    Route::delete('/penghapusan-mata-kuliah/{student}/{course}', [CourseRemovalController::class, 'destroy'])->name('course-removals.destroy');
    Route::get('/lulus-cuti-do/export/excel', [StudentStatusController::class, 'exportExcel'])->name('student-status.export.excel');
    Route::get('/lulus-cuti-do', [StudentStatusController::class, 'index'])->name('student-status.index');
    Route::get('/aktivitas-mahasiswa', [StudentActivityController::class, 'index'])->name('student-activities.index');
    Route::post('/aktivitas-mahasiswa', [StudentActivityController::class, 'store'])->name('student-activities.store');
    Route::get('/aktivitas-mahasiswa/{activity}', [StudentActivityController::class, 'show'])->name('student-activities.show');
    Route::put('/aktivitas-mahasiswa/{activity}', [StudentActivityController::class, 'update'])->name('student-activities.update');
    Route::delete('/aktivitas-mahasiswa/{activity}', [StudentActivityController::class, 'destroy'])->name('student-activities.destroy');
    Route::post('/aktivitas-mahasiswa/{activity}/participants', [StudentActivityController::class, 'addParticipant'])->name('student-activities.participants.store');
    Route::post('/aktivitas-mahasiswa/{activity}/advisors', [StudentActivityController::class, 'addAdvisor'])->name('student-activities.advisors.store');
    Route::post('/aktivitas-mahasiswa/{activity}/examiners', [StudentActivityController::class, 'addExaminer'])->name('student-activities.examiners.store');
    Route::delete('/aktivitas-mahasiswa/{activity}/roles/{role}/{type}', [StudentActivityController::class, 'removeRole'])->name('student-activities.roles.destroy');
    Route::get('/aktivitas-kuliah-mahasiswa/export/excel', [StudentAcademicActivityController::class, 'exportExcel'])->name('student-akm.export.excel');
    Route::get('/aktivitas-kuliah-mahasiswa', [StudentAcademicActivityController::class, 'index'])->name('student-akm.index');
    Route::post('/aktivitas-kuliah-mahasiswa/generate', [StudentAcademicActivityController::class, 'generate'])->name('student-akm.generate');
    Route::post('/aktivitas-kuliah-mahasiswa/{record}/status', [StudentAcademicActivityController::class, 'updateStatus'])->name('student-akm.status.update');
    Route::post('/aktivitas-kuliah-mahasiswa/lock', [StudentAcademicActivityController::class, 'lock'])->name('student-akm.lock');
    Route::get('/aktivitas-mengajar', [TeachingActivityController::class, 'index'])->name('activity-teaching.index');
    Route::post('/aktivitas-mengajar/{schedule}', [TeachingActivityController::class, 'update'])->name('activity-teaching.update');
    Route::get('/lulus-cuti-do/create', [StudentStatusController::class, 'create'])->name('student-status.create');
    Route::post('/lulus-cuti-do', [StudentStatusController::class, 'store'])->name('student-status.store');
    Route::get('/lulus-cuti-do/{record}/edit', [StudentStatusController::class, 'edit'])->name('student-status.edit');
    Route::put('/lulus-cuti-do/{record}', [StudentStatusController::class, 'update'])->name('student-status.update');
    Route::delete('/lulus-cuti-do/{record}', [StudentStatusController::class, 'destroy'])->name('student-status.destroy');
    Route::get('/monitoringkrs', [MonitoringKrsController::class, 'index'])->name('monitoring-krs.index');
    Route::get('/monitoringkrs/data', [MonitoringKrsController::class, 'data'])->name('monitoring-krs.data');
    Route::get('/krs/{student}/edit', [KrsController::class, 'edit'])->name('krs.edit');
    Route::post('/krs/{student}/save', [KrsController::class, 'save'])->name('krs.save');
    Route::post('/krs/{student}/package', [KrsController::class, 'savePackage'])->name('krs.package');
    Route::get('/krs/{student}/print', [KrsController::class, 'print'])->name('krs.print');
    Route::post('/krs/{student}/approve-pa', [KrsController::class, 'approvePa'])->name('krs.approve-pa');
    Route::post('/krs/{student}/approve-program', [KrsController::class, 'approveProgram'])->name('krs.approve-program');
    Route::post('/krs/{student}/reset-approval', [KrsController::class, 'resetApproval'])->name('krs.reset-approval');
    Route::post('/krs/approve-program-all', [KrsController::class, 'approveAllProgram'])->name('krs.approve-program-all');
    Route::get('/class-schedules/create', [ClassScheduleController::class, 'create'])->name('class-schedules.create');
    Route::post('/class-schedules/copy', [ClassScheduleController::class, 'copy'])->name('class-schedules.copy');
    Route::post('/class-schedules', [ClassScheduleController::class, 'store'])->name('class-schedules.store');
    Route::get('/class-schedules/print', [ClassScheduleController::class, 'print'])->name('class-schedules.print');
    Route::get('/class-schedules/{schedule}/edit', [ClassScheduleController::class, 'edit'])->name('class-schedules.edit');
    Route::put('/class-schedules/{schedule}', [ClassScheduleController::class, 'update'])->name('class-schedules.update');
    Route::delete('/class-schedules/{schedule}', [ClassScheduleController::class, 'destroy'])->name('class-schedules.destroy');
    Route::get('/jadwal-sp', [ClassScheduleController::class, 'shortSemesterIndex'])->name('short-semester-schedules.index');
    Route::get('/jadwal-sp/create', [ClassScheduleController::class, 'createShortSemester'])->name('short-semester-schedules.create');
    Route::post('/jadwal-sp', [ClassScheduleController::class, 'storeShortSemester'])->name('short-semester-schedules.store');
    Route::get('/jadwal-sp/{scheduleId}/peserta', [ClassScheduleController::class, 'shortSemesterEligibleStudents'])
        ->name('short-semester-schedules.students.index');
    Route::post('/jadwal-sp/{scheduleId}/peserta/{student}', [ClassScheduleController::class, 'storeShortSemesterStudent'])
        ->name('short-semester-schedules.students.store');
    Route::get('/jadwal-sp/{scheduleId}/peserta/terdaftar', [ClassScheduleController::class, 'shortSemesterParticipants'])
        ->name('short-semester-schedules.participants.index');
    Route::delete('/jadwal-sp/{scheduleId}/peserta/{student}', [ClassScheduleController::class, 'destroyShortSemesterStudent'])
        ->name('short-semester-schedules.participants.destroy');
    Route::get('/jadwal-sp/{scheduleId}/edit', [ClassScheduleController::class, 'editShortSemester'])
        ->name('short-semester-schedules.edit');
    Route::put('/jadwal-sp/{scheduleId}', [ClassScheduleController::class, 'updateShortSemester'])
        ->name('short-semester-schedules.update');
    Route::delete('/jadwal-sp/{scheduleId}', [ClassScheduleController::class, 'destroyShortSemester'])
        ->name('short-semester-schedules.destroy');
    Route::post('/jadwal-sp/{scheduleId}/pengampu/{teacherId}', [ClassScheduleController::class, 'syncShortSemesterTeacher'])
        ->name('short-semester-schedules.teachers.sync');
    Route::get('/profile', [UserProfileController::class, 'edit'])->name('user-profile.edit');
    Route::put('/profile', [UserProfileController::class, 'update'])->name('user-profile.update');
    Route::get('/academic-calendars', [AcademicCalendarController::class, 'index'])->name('academic-calendars.index');
    Route::get('/academic-calendars/create', [AcademicCalendarController::class, 'create'])->name('academic-calendars.create');
    Route::post('/academic-calendars', [AcademicCalendarController::class, 'store'])->name('academic-calendars.store');
    Route::get('/academic-calendars/{calendar}/edit', [AcademicCalendarController::class, 'edit'])->name('academic-calendars.edit');
    Route::put('/academic-calendars/{calendar}', [AcademicCalendarController::class, 'update'])->name('academic-calendars.update');
    Route::post('/academic-calendars/{calendar}/activate', [AcademicCalendarController::class, 'activate'])->name('academic-calendars.activate');
    Route::post('/academic-calendars/{calendar}/status', [AcademicCalendarController::class, 'setStatus'])->name('academic-calendars.status');
    Route::get('/kurikulum-sp', [AcademicCalendarController::class, 'index'])->defaults('shortSemester', true)->name('short-semester-curricula.index');
    Route::post('/kurikulum-sp', [AcademicCalendarController::class, 'store'])->defaults('shortSemester', true)->name('short-semester-curricula.store');
    Route::post('/kurikulum-sp/sinkronisasi', [AcademicCalendarController::class, 'syncShortSemester'])->name('short-semester-curricula.sync');
    Route::get('/kurikulum-sp/{calendar}/edit', [AcademicCalendarController::class, 'edit'])->defaults('shortSemester', true)->name('short-semester-curricula.edit');
    Route::put('/kurikulum-sp/{calendar}', [AcademicCalendarController::class, 'update'])->defaults('shortSemester', true)->name('short-semester-curricula.update');
    Route::post('/kurikulum-sp/{calendar}/activate', [AcademicCalendarController::class, 'activate'])->defaults('shortSemester', true)->name('short-semester-curricula.activate');
    Route::post('/kurikulum-sp/{calendar}/status', [AcademicCalendarController::class, 'setStatus'])->defaults('shortSemester', true)->name('short-semester-curricula.status');
    foreach ([
        'perguruan-tinggi', 'fakultas', 'program-studi', 'dosen', 'asisten-dosen',
        'mahasiswa', 'mata-kuliah', 'ruang', 'bobot-nilai',
    ] as $module) {
        Route::get('/master-data/'.$module, [MasterDataController::class, 'index'])
            ->defaults('module', $module)
            ->name('master-data.'.$module.'.index');
        Route::get('/master-data/'.$module.'/data', [MasterDataController::class, 'tableData'])
            ->defaults('module', $module)
            ->name('master-data.'.$module.'.data');
        Route::get('/master-data/'.$module.'/{record}/edit', [MasterDataController::class, 'editData'])
            ->defaults('module', $module)
            ->name('master-data.'.$module.'.edit-data');
        Route::post('/master-data/'.$module, [MasterDataController::class, 'store'])
            ->defaults('module', $module)
            ->name('master-data.'.$module.'.store');
        Route::put('/master-data/'.$module.'/{record}', [MasterDataController::class, 'update'])
            ->defaults('module', $module)
            ->name('master-data.'.$module.'.update');
    }
    Route::get('/master-data/mahasiswa/export', [MasterDataController::class, 'exportStudents'])
        ->name('master-data.mahasiswa.export');
    foreach (['perguruan-tinggi', 'program-studi', 'mahasiswa', 'mata-kuliah'] as $module) {
        Route::post('/master-data/'.$module.'/sync', [MasterDataController::class, 'sync'])
            ->defaults('module', $module)
            ->name('master-data.'.$module.'.sync');
    }
    Route::get('/master-data/fakultas/lecturers', [MasterDataController::class, 'lecturers'])
        ->name('master-data.fakultas.lecturers');
    Route::get('/master-data/program-studi/lecturers', [MasterDataController::class, 'lecturers'])
        ->name('master-data.program-studi.lecturers');
    Route::get('/master-data/mahasiswa/origins', [MasterDataController::class, 'studentOrigins'])
        ->name('master-data.mahasiswa.origins');
    Route::get('/master-data/mahasiswa/regions', [MasterDataController::class, 'studentRegions'])
        ->name('master-data.mahasiswa.regions');
    foreach (['dosen', 'asisten-dosen', 'mahasiswa'] as $module) {
        Route::post('/master-data/'.$module.'/{record}/reset-password', [MasterDataController::class, 'resetPassword'])
            ->defaults('module', $module)
            ->name('master-data.'.$module.'.reset-password');
    }
    Route::get('/master-data/mata-kuliah/{record}/evaluations', [MasterDataController::class, 'courseEvaluations'])
        ->name('master-data.mata-kuliah.evaluations');
    Route::delete('/master-data/mata-kuliah/{record}/evaluations/{evaluation}', [MasterDataController::class, 'deleteCourseEvaluation'])
        ->name('master-data.mata-kuliah.evaluations.delete');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
