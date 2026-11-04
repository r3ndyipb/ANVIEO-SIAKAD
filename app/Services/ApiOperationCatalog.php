<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\View;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

class ApiOperationCatalog
{
    private const FEATURE_PREFIXES = [
        'academic-calendars.', 'academic-dashboard.', 'activity-logs.', 'activity-notifications.',
        'activity-teaching.', 'camaba-verification.', 'class-schedules.', 'class-transfers.',
        'course-attendance.', 'course-removals.', 'ekurikulum.', 'finance.', 'grade-validation.',
        'graduation-participants.', 'graduation-periods.', 'graduation-validations.',
        'khs.', 'kkn.', 'krs.', 'krs-transfer.', 'lms.', 'master-data.', 'mbkm-transfers.',
        'monitoring-krs.', 're-enrollment.', 'simak-users.', 'student-akm.', 'student-status.',
        'survey-edom.', 'theses.', 'transcripts.',
    ];

    private const EXCLUDED_ROUTES = [
        'camaba-verification.verify', 'camaba-verification.upload',
        'camaba-verification.template', 'master-data.*.sync', 'master-data.*.reset-password',
        'master-data.perguruan-tinggi.*', 'master-data.mahasiswa.update', 'master-data.mahasiswa.store',
        'master-data.mata-kuliah.store', 'master-data.mata-kuliah.update', 'master-data.mata-kuliah.sync',
        'master-data.mata-kuliah.evaluations*', 'finance.categories.template', 'finance.categories.upload',
        'finance.*.export', 'finance.ipi.upload',
        'student-payments.*', 'theses.titles.store', 'theses.titles.validation.update',
        'course-attendance.print', 'graduation-participants.*.create', 'graduation-validations.export.*',
        'graduation-validations.documents.*', 'graduation-student.*', 'theses.assignments.update',
        'theses.bimbingan.send', 'theses.exams.schedule', 'theses.exams.grade', 'theses.exams.print',
        'theses.exams.minutes', 'theses.upload.*', 'student-activities.*',
        'lms.attendance.self', 'lms.exams.start', 'lms.exams.answers', 'lms.exams.submit',
        'lms.tasks.submit', 'lms.discussions.messages.*',
    ];

    private const EXCLUDED_SUFFIXES = [
        '.export', '.pdf', '.excel', '.ppt', '.print', '.template', '.upload', '.download',
        '.import', '.sync', '.reset-password', '.send', '.notify',
    ];

    private const FEATURE_LABELS = [
        'academic-calendars' => 'kalender akademik',
        'academic-dashboard' => 'dashboard akademik',
        'activity-logs' => 'log aktivitas',
        'activity-notifications' => 'notifikasi aktivitas',
        'activity-teaching' => 'aktivitas mengajar',
        'camaba-verification' => 'verifikasi calon mahasiswa',
        'class-schedules' => 'jadwal kuliah',
        'class-transfers' => 'pemindahan kelas',
        'course-attendance' => 'absensi kuliah',
        'course-removals' => 'penghapusan mata kuliah',
        'ekurikulum' => 'e-kurikulum',
        'finance' => 'keuangan',
        'grade-validation' => 'validasi nilai',
        'graduation-participants' => 'usulan peserta wisuda',
        'graduation-periods' => 'periode wisuda',
        'graduation-validations' => 'validasi wisuda',
        'khs' => 'kartu hasil studi',
        'kkn' => 'kuliah kerja nyata',
        'krs' => 'kartu rencana studi',
        'lms' => 'LMS Virtual Class',
        'krs-transfer' => 'transfer KRS',
        'master-data' => 'data master',
        'mbkm-transfers' => 'transfer MBKM',
        'monitoring-krs' => 'monitoring KRS',
        're-enrollment' => 'registrasi ulang',
        'simak-users' => 'pengguna SIMAK',
        'student-akm' => 'aktivitas kuliah mahasiswa',
        'student-status' => 'status mahasiswa',
        'survey-edom' => 'survei EDOM',
        'theses' => 'e-skripsi',
        'transcripts' => 'transkrip',
    ];

    private const OBJECT_LABELS = [
        'asisten-dosen' => 'asisten dosen', 'fakultas' => 'fakultas', 'dosen' => 'dosen',
        'mahasiswa' => 'mahasiswa', 'mata-kuliah' => 'mata kuliah', 'ruang' => 'ruang',
        'program-studi' => 'program studi', 'perguruan-tinggi' => 'perguruan tinggi',
        'categories' => 'kategori uang kuliah', 'callbacks' => 'riwayat callback', 'ipi' => 'Iuran Pengembangan Institusi',
        'ukt' => 'pembayaran uang kuliah', 'billing' => 'billing',
        'materials' => 'materi kuliah', 'meetings' => 'pertemuan kuliah', 'attendance' => 'absensi',
        'questions' => 'bank soal', 'exams' => 'ujian', 'tasks' => 'tugas', 'discussions' => 'diskusi',
        'periods' => 'periode uang kuliah', 'curricula' => 'kurikulum', 'teams' => 'tim kurikulum',
        'profile' => 'profil program studi', 'proposals' => 'usulan judul', 'assignments' => 'penugasan dosen',
        'bimbingan' => 'bimbingan skripsi', 'exams' => 'ujian skripsi', 'participants' => 'peserta',
        'locations' => 'lokasi', 'validations' => 'validasi peserta', 'student' => 'mahasiswa',
    ];

    private const ACTION_LABELS = [
        'index' => 'Daftar', 'data' => 'Ambil data', 'show' => 'Lihat rincian',
        'edit-data' => 'Lihat data untuk diubah', 'store' => 'Create', 'create' => 'Create',
        'update' => 'Update data', 'destroy' => 'Delete', 'delete' => 'Delete',
        'activate' => 'Aktifkan', 'status' => 'Ubah status', 'approve-pa' => 'Setujui oleh dosen PA',
        'approve-program' => 'Setujui oleh program studi', 'approve-program-all' => 'Setujui semua program studi',
        'save' => 'Simpan', 'copy' => 'Salin', 'cancel' => 'Batalkan', 'lock' => 'Kunci',
        'package' => 'Atur paket', 'reset-approval' => 'Batalkan persetujuan',
        'register' => 'Daftarkan', 'unregister' => 'Batalkan pendaftaran',
        'location' => 'Atur lokasi', 'random-plot' => 'Atur lokasi secara acak',
        'amount' => 'Ubah nominal', 'paid' => 'Ubah status pembayaran', 'generate' => 'Buat tagihan',
        'read' => 'Tandai telah dibaca', 'read-all' => 'Tandai semua telah dibaca',
        'pending-approvals' => 'Lihat persetujuan tertunda', 'lecturers' => 'Cari dosen', 'regions' => 'Cari wilayah',
        'origins' => 'Cari asal pendidikan', 'search' => 'Cari data', 'insights' => 'Lihat analisis',
        'messages' => 'Lihat pesan', 'results' => 'Lihat hasil', 'questions' => 'Lihat pertanyaan',
        'grade' => 'Nilai', 'mark' => 'Catat', 'roster' => 'Lihat daftar',
    ];

    public function all(): array
    {
        $operations = [];
        $supportedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (! is_string($name) || ! $this->isAllowed($name, $route->uri(), $route->gatherMiddleware())) {
                continue;
            }

            foreach (array_intersect($route->methods(), $supportedMethods) as $method) {
                $responseKind = $this->responseKind($route, $method);
                if ($responseKind === null) {
                    continue;
                }
                $publicPath = self::publicPath($route->uri());
                $operations[] = [
                    'id' => $name.'.'.strtolower($method),
                    'name' => $name,
                    'method' => $method,
                    'path' => $publicPath,
                    'parameters' => $this->pathParameters($route->uri()),
                    'response_kind' => $responseKind,
                    'summary' => $this->summary($name),
                ];
            }
        }

        usort($operations, static fn (array $left, array $right): int => [$left['name'], $left['method']] <=> [$right['name'], $right['method']]);

        return $operations;
    }

    public function find(string $name, string $method): ?array
    {
        foreach ($this->all() as $operation) {
            if ($operation['name'] === $name && $operation['method'] === strtoupper($method)) {
                return $operation;
            }
        }

        return null;
    }

    private function isAllowed(string $name, string $uri, array $middleware): bool
    {
        if (str_starts_with($uri, 'api/') || ! in_array('auth', $middleware, true)) {
            return false;
        }

        $inFeature = false;
        foreach (self::FEATURE_PREFIXES as $prefix) {
            if (Str::startsWith($name, $prefix)) {
                $inFeature = true;
                break;
            }
        }
        if (! $inFeature) {
            return false;
        }

        foreach (self::EXCLUDED_ROUTES as $pattern) {
            if (Str::is($pattern, $name)) {
                return false;
            }
        }
        foreach (self::EXCLUDED_SUFFIXES as $suffix) {
            if (Str::endsWith($name, $suffix)) {
                return false;
            }
        }

        return true;
    }

    public static function publicPath(string $path): string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $segment): bool => $segment !== ''));

        $translated = array_map(static function (string $segment): string {
            $map = self::pathAliases();

            return $map[$segment] ?? $segment;
        }, $segments);

        return '/'.implode('/', $translated);
    }

    public static function internalPath(string $path): string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $segment): bool => $segment !== ''));

        $translated = array_map(static function (string $segment): string {
            $map = array_flip(self::pathAliases());

            return $map[$segment] ?? $segment;
        }, $segments);

        return '/'.implode('/', $translated);
    }

    private static function pathAliases(): array
    {
        return [
            'administration' => 'administrasi',
            'billing' => 'tagihan',
            'callbacks' => 'callback',
            'categories' => 'kategori-ukt',
            'finance' => 'keuangan',
            'ipi' => 'pembayaran-ipi',
            'master-data' => 'data-master',
            'periods' => 'periode-ukt',
            'settings' => 'pengaturan',
            'simak-users' => 'pengguna-simak',
            'ukt' => 'pembayaran-ukt',
            'users' => 'pengguna',
        ];
    }

    private function pathParameters(string $uri): array
    {
        preg_match_all('/\{([^}?]+)\??\}/', $uri, $matches);

        return $matches[1] ?? [];
    }

    private function summary(string $name): string
    {
        $parts = explode('.', $name);
        $feature = array_shift($parts) ?? '';
        $action = array_pop($parts) ?? '';
        $module = self::FEATURE_LABELS[$feature] ?? 'data SIMAK';
        $object = null;
        foreach (array_reverse($parts) as $part) {
            if (isset(self::OBJECT_LABELS[$part])) {
                $object = self::OBJECT_LABELS[$part];
                break;
            }
        }
        $actionLabel = self::ACTION_LABELS[$action] ?? 'proses data';
        if ($feature === 'course-removals' && in_array($action, ['delete', 'destroy'], true)) {
            return 'Delete mata kuliah dari KRS';
        }

        return $actionLabel.' '.($object ?? $module);
    }

    private function responseKind(\Illuminate\Routing\Route $route, string $method): ?string
    {
        $controller = $route->getControllerClass();
        $action = $route->getActionMethod();
        if (! is_string($controller) || ! class_exists($controller) || ! method_exists($controller, $action)) {
            return null;
        }

        $type = (new ReflectionMethod($controller, $action))->getReturnType();
        $names = match (true) {
            $type instanceof ReflectionNamedType => [$type->getName()],
            $type instanceof ReflectionUnionType => array_map(static fn (ReflectionNamedType $part): string => $part->getName(), $type->getTypes()),
            default => [],
        };

        $hasJsonResponse = in_array(JsonResponse::class, $names, true);
        $hasViewResponse = str_starts_with((string) $route->getName(), 'lms.')
            && $method === 'GET'
            && in_array(View::class, $names, true);
        $hasRedirectResponse = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && in_array(RedirectResponse::class, $names, true);

        return match (true) {
            $hasJsonResponse && $hasRedirectResponse => 'mixed',
            $hasJsonResponse => 'json',
            $hasViewResponse => 'json',
            $hasRedirectResponse => 'redirect',
            default => null,
        };
    }
}
