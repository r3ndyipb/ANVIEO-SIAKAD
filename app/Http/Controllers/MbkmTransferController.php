<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Support\AcademicCalendarScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MbkmTransferController extends Controller
{
    private const ROLES = ['admin', 'userprodi'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:24']]);
        $student = filled($filters['search'] ?? null)
            ? $this->studentQuery($user)->where('student.nim', trim($filters['search']))->first()
            : null;
        $calendar = null;
        $registered = false;
        $isMbkm = false;
        $canRegisterMbkm = false;
        $canRemoveMbkm = false;
        $regularCredits = 0.0;
        $mbkmCourses = collect();
        $courses = collect();
        $activities = collect();
        $originInstitutions = collect();
        $totalCredits = 0.0;

        if ($student !== null) {
            $calendar = $this->activeCalendar($student);
            if ($calendar !== null) {
                $connection = DB::connection('simak_main');
                $registered = $connection->table('s1m4k_registrasiulang')
                    ->where('id_mahasiswa', $student->id_mahasiswa)
                    ->where('id_kalender', $calendar->id_kalender)
                    ->exists();
                $isMbkm = $connection->table('s1m4k_akm')
                    ->where('id_mahasiswa', $student->id_mahasiswa)
                    ->where('id_kalender', $calendar->id_kalender)
                    ->where('status_mahasiswa', 'M')
                    ->exists();
                $currentAkm = $connection->table('s1m4k_akm')
                    ->where('id_mahasiswa', $student->id_mahasiswa)
                    ->where('id_kalender', $calendar->id_kalender)
                    ->first(['id_ipmhs', 'status_mahasiswa', 'simpan']);
                $canRegisterMbkm = $registered
                    && ! $isMbkm
                    && (int) ($currentAkm->simpan ?? 0) !== 1;
                $regularCredits = (float) $connection->table('s1m4k_krs as krs')
                    ->join('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
                    ->where('krs.id_mahasiswa', $student->id_mahasiswa)
                    ->where('krs.id_kalender', $calendar->id_kalender)
                    ->where('krs.krs_mbkm', 'N')
                    ->sum('course.sks_mata_kuliah');
                $mbkmCourses = $connection->table('s1m4k_krs as krs')
                    ->leftJoin('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
                    ->leftJoin('s1m4k_kegiatanmhs as activity', 'krs.id_kegiatan', '=', 'activity.id_kegiatan')
                    ->where('krs.id_mahasiswa', $student->id_mahasiswa)
                    ->where('krs.id_kalender', $calendar->id_kalender)
                    ->where('krs.krs_mbkm', 'Y')
                    ->orderBy('course.semester')
                    ->orderBy('course.nama_mata_kuliah')
                    ->get([
                        'krs.id_krs', 'krs.id_pt_asal', 'krs.id_kegiatan', 'krs.kode_mk_asal',
                        'krs.nama_mk_asal', 'krs.sks_asal', 'krs.nilai_huruf_asal',
                        'krs.nilai_angka', 'krs.nilai_huruf', 'krs.nilai_bobot',
                        'course.kode_mata_kuliah', 'course.nama_mata_kuliah', 'course.sks_mata_kuliah',
                        'activity.judul as activity_title',
                    ]);
                $canRemoveMbkm = $registered
                    && $isMbkm
                    && $mbkmCourses->isEmpty()
                    && (int) ($currentAkm->simpan ?? 0) !== 1;
                $totalCredits = $regularCredits + (float) $mbkmCourses->sum(
                    fn (object $course): float => (float) $course->sks_mata_kuliah
                );

                if ($registered && $isMbkm) {
                    $courses = $connection->table('s1m4k_mastermk')
                        ->where('id_prodi', $student->id_prodi)
                        ->where('status', 'A')
                        ->orderBy('semester')
                        ->orderBy('nama_mata_kuliah')
                        ->get(['id_matkul', 'kode_mata_kuliah', 'nama_mata_kuliah', 'semester', 'sks_mata_kuliah']);
                    $activities = $this->activities($student, $calendar);
                    $originInstitutions = $connection->table('s1m4k_masterallpt')
                        ->orderBy('nama_perguruan_tinggi')
                        ->get(['id_perguruan_tinggi', 'kode_perguruan_tinggi', 'nama_perguruan_tinggi']);
                }
            }
        }

        return view('mbkm-transfers.index', compact(
            'user', 'filters', 'student', 'calendar', 'registered', 'isMbkm', 'canRegisterMbkm', 'canRemoveMbkm',
            'regularCredits', 'mbkmCourses', 'courses', 'activities',
            'originInstitutions', 'totalCredits'
        ));
    }

    public function registerMbkmStatus(Request $request, string $studentId, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $student = $this->findStudent($user, $studentId);
        $calendar = $this->activeCalendar($student);
        abort_if($calendar === null, 422, 'Kalender akademik aktif mahasiswa tidak tersedia.');

        $connection = DB::connection('simak_main');
        $registered = $connection->table('s1m4k_registrasiulang')
            ->where('id_mahasiswa', $student->id_mahasiswa)
            ->where('id_kalender', $calendar->id_kalender)
            ->exists();
        abort_unless($registered, 422, 'Mahasiswa belum melakukan registrasi ulang pada kalender aktif.');

        $connection->transaction(function () use ($connection, $student, $calendar, $user, $activityLogger): void {
            $akm = $connection->table('s1m4k_akm')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->lockForUpdate()
                ->first();
            abort_if((int) ($akm->simpan ?? 0) === 1, 422, 'Data AKM telah disimpan permanen dan tidak dapat diubah.');
            abort_if(($akm->status_mahasiswa ?? null) === 'M', 409, 'Status mahasiswa pada kalender ini sudah MBKM.');

            $before = $akm === null ? null : (array) $akm;
            if ($akm === null) {
                $metrics = $this->academicMetrics($student, $calendar);
                $connection->table('s1m4k_akm')->insert([
                    'id_ipmhs' => (string) Str::uuid(),
                    'id_prodi' => $student->id_prodi,
                    'id_mahasiswa' => $student->id_mahasiswa,
                    'id_kalender' => $calendar->id_kalender,
                    'tahun' => $calendar->tahun,
                    ...$metrics,
                    'status_mahasiswa' => 'M',
                    'update_akm' => 0,
                    'simpan' => 0,
                    'flag_feeder' => 0,
                ]);
            } else {
                $connection->table('s1m4k_akm')
                    ->where('id_ipmhs', $akm->id_ipmhs)
                    ->update(['status_mahasiswa' => 'M']);
            }

            $connection->table('s1m4k_mastermhs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_prodi', $student->id_prodi)
                ->update([
                    'id_status_mahasiswa' => 'M',
                    'nama_status_mahasiswa' => 'Kampus Merdeka',
                ]);
            $after = $connection->table('s1m4k_akm')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->first();
            $activityLogger->record($user, 'Transfer Nilai MBKM', 'Mendaftarkan status MBKM', [
                'data_sebelumnya' => $before,
                'data_baru' => (array) $after,
                'status_mahasiswa' => ['sebelum' => $student->nama_status_mahasiswa, 'sesudah' => 'Kampus Merdeka'],
            ], (string) $student->id_mahasiswa, $this->studentIdentity($student, (object) ['nama_mata_kuliah' => 'Status MBKM']));
        });

        return redirect()->route('mbkm-transfers.index', ['search' => $student->nim])
            ->with('status', 'Status MBKM mahasiswa berhasil didaftarkan pada kalender aktif.');
    }

    public function unregisterMbkmStatus(Request $request, string $studentId, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $student = $this->findStudent($user, $studentId);
        $calendar = $this->activeCalendar($student);
        abort_if($calendar === null, 422, 'Kalender akademik aktif mahasiswa tidak tersedia.');

        $connection = DB::connection('simak_main');
        $connection->transaction(function () use ($connection, $student, $calendar, $user, $activityLogger): void {
            $akm = $connection->table('s1m4k_akm')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->lockForUpdate()
                ->first();
            abort_unless(($akm->status_mahasiswa ?? null) === 'M', 409, 'Status mahasiswa pada kalender ini bukan MBKM.');
            abort_if((int) ($akm->simpan ?? 0) === 1, 422, 'Data AKM telah disimpan permanen dan tidak dapat diubah.');

            $mbkmKrs = $connection->table('s1m4k_krs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->where('krs_mbkm', 'Y')
                ->lockForUpdate()
                ->exists();
            abort_if($mbkmKrs, 422, 'Status MBKM tidak dapat dihapus karena mahasiswa sudah mengisi KRS MBKM pada kalender ini.');

            $registered = $connection->table('s1m4k_registrasiulang')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->exists();
            $restoredStatus = $registered ? 'A' : 'N';
            $restoredLabel = $registered ? 'Aktif' : 'Non-Aktif';
            $before = (array) $akm;

            $connection->table('s1m4k_akm')->where('id_ipmhs', $akm->id_ipmhs)
                ->update(['status_mahasiswa' => $restoredStatus]);
            $connection->table('s1m4k_mastermhs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_prodi', $student->id_prodi)
                ->update([
                    'id_status_mahasiswa' => $restoredStatus,
                    'nama_status_mahasiswa' => $restoredLabel,
                ]);
            $after = $connection->table('s1m4k_akm')->where('id_ipmhs', $akm->id_ipmhs)->first();
            $activityLogger->record($user, 'Transfer Nilai MBKM', 'Menghapus status MBKM', [
                'data_sebelumnya' => $before,
                'data_baru' => (array) $after,
                'status_mahasiswa' => ['sebelum' => 'Kampus Merdeka', 'sesudah' => $restoredLabel],
            ], (string) $student->id_mahasiswa, $this->studentIdentity($student, (object) ['nama_mata_kuliah' => 'Status MBKM']));
        });

        return redirect()->route('mbkm-transfers.index', ['search' => $student->nim])
            ->with('status', 'Status MBKM berhasil dihapus dan status mahasiswa dipulihkan.');
    }

    public function store(Request $request, string $studentId, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $student = $this->findStudent($user, $studentId);
        $calendar = $this->activeCalendar($student);
        abort_if($calendar === null, 422, 'Kalender akademik aktif mahasiswa tidak tersedia.');
        $this->assertStudentEligible($student, $calendar);

        $validated = $request->validate([
            'mode' => ['required', 'in:exchange,non_exchange'],
            'id_matkul' => ['required', 'string', 'max:100'],
            'nilai_angka' => ['required', 'numeric', 'between:0,100'],
            'id_perguruan_tinggi_asal' => ['required_if:mode,exchange', 'nullable', 'string', 'max:100'],
            'kode_mk_asal' => ['required_if:mode,exchange', 'nullable', 'string', 'max:200'],
            'nama_mk_asal' => ['required_if:mode,exchange', 'nullable', 'string', 'max:200'],
            'sks_asal' => ['required_if:mode,exchange', 'nullable', 'integer', 'between:0,99'],
            'nilai_huruf_asal' => ['required_if:mode,exchange', 'nullable', 'string', 'max:4'],
            'id_kegiatan' => ['required_if:mode,non_exchange', 'nullable', 'string', 'max:100'],
        ]);

        $connection = DB::connection('simak_main');
        $course = $connection->table('s1m4k_mastermk')
            ->where('id_matkul', $validated['id_matkul'])
            ->where('id_prodi', $student->id_prodi)
            ->where('status', 'A')
            ->first(['id_matkul', 'kode_mata_kuliah', 'nama_mata_kuliah', 'sks_mata_kuliah']);
        abort_if($course === null, 404, 'Mata kuliah aktif pada program studi mahasiswa tidak ditemukan.');

        if ($validated['mode'] === 'exchange') {
            $originExists = $connection->table('s1m4k_masterallpt')
                ->where('id_perguruan_tinggi', $validated['id_perguruan_tinggi_asal'])
                ->exists();
            abort_unless($originExists, 422, 'Perguruan tinggi asal tidak ditemukan.');
        } else {
            $activityExists = $this->activities($student, $calendar)
                ->contains('id_kegiatan', $validated['id_kegiatan']);
            abort_unless($activityExists, 422, 'Kegiatan MBKM bukan milik mahasiswa pada kalender aktif.');
        }

        $duplicate = $connection->table('s1m4k_krs')
            ->where('id_mahasiswa', $student->id_mahasiswa)
            ->where('id_kalender', $calendar->id_kalender)
            ->where('id_matkul', $course->id_matkul)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'id_matkul' => 'Mata kuliah tersebut sudah diprogram pada KRS semester ini.',
            ]);
        }

        $existingCredits = (float) $connection->table('s1m4k_krs as krs')
            ->join('s1m4k_mastermk as existing_course', 'krs.id_matkul', '=', 'existing_course.id_matkul')
            ->where('krs.id_mahasiswa', $student->id_mahasiswa)
            ->where('krs.id_kalender', $calendar->id_kalender)
            ->sum('existing_course.sks_mata_kuliah');
        $newTotal = $existingCredits + (float) $course->sks_mata_kuliah;
        if ($newTotal > 24) {
            throw ValidationException::withMessages([
                'id_matkul' => "Total SKS semester ({$newTotal}) melebihi batas konversi MBKM 24 SKS.",
            ]);
        }

        $score = (float) $validated['nilai_angka'];
        $scale = $connection->table('s1m4k_masterbobotnilai')
            ->orderBy('nilai_huruf')
            ->get(['nilai_huruf', 'nilai_indeks', 'bobot_minimum', 'bobot_maksimum'])
            ->first(fn (object $grade): bool => is_numeric($grade->bobot_minimum)
                && is_numeric($grade->bobot_maksimum)
                && $score >= (float) $grade->bobot_minimum
                && $score <= (float) $grade->bobot_maksimum
            );
        if ($scale === null) {
            throw ValidationException::withMessages([
                'nilai_angka' => 'Nilai angka tidak termasuk rentang bobot nilai yang tersedia.',
            ]);
        }

        $row = [
            'id_krs' => (string) Str::uuid(),
            'id_perguruan_tinggi' => $student->id_perguruan_tinggi,
            'id_fakultas' => $student->id_fakultas,
            'id_prodi' => $student->id_prodi,
            'id_kalender' => $calendar->id_kalender,
            'id_mahasiswa' => $student->id_mahasiswa,
            'id_matkul' => $course->id_matkul,
            'tgl_update' => now()->toDateString(),
            'krs_reguler' => 'Y',
            'krs_mbkm' => 'Y',
            'id_pt_asal' => $validated['mode'] === 'exchange' ? $validated['id_perguruan_tinggi_asal'] : null,
            'kode_mk_asal' => $validated['mode'] === 'exchange' ? trim($validated['kode_mk_asal']) : null,
            'nama_mk_asal' => $validated['mode'] === 'exchange' ? trim($validated['nama_mk_asal']) : null,
            'sks_asal' => $validated['mode'] === 'exchange' ? (string) $validated['sks_asal'] : null,
            'nilai_huruf_asal' => $validated['mode'] === 'exchange' ? mb_strtoupper(trim($validated['nilai_huruf_asal'])) : null,
            'nilai_angka' => number_format($score, 2, '.', ''),
            'nilai_huruf' => $scale->nilai_huruf,
            'nilai_bobot' => $scale->nilai_indeks,
            'tahun' => $calendar->tahun,
            'user' => (string) $user->legacy_id,
            'id_kegiatan' => $validated['mode'] === 'non_exchange' ? $validated['id_kegiatan'] : null,
        ];

        $connection->transaction(function () use ($connection, $row, $student, $calendar, $course, $user, $activityLogger, $validated): void {
            $akm = $connection->table('s1m4k_akm')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->lockForUpdate()
                ->first();
            abort_unless(($akm->status_mahasiswa ?? null) === 'M', 422, 'Status mahasiswa belum ditetapkan sebagai mahasiswa MBKM pada kalender aktif.');
            abort_if((int) ($akm->simpan ?? 0) === 1, 422, 'Data AKM telah disimpan permanen dan tidak dapat diubah.');
            abort_unless($connection->table('s1m4k_registrasiulang')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->exists(), 422, 'Mahasiswa belum melakukan registrasi ulang pada kalender aktif.');

            $duplicate = $connection->table('s1m4k_krs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->where('id_matkul', $course->id_matkul)
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'id_matkul' => 'Mata kuliah tersebut sudah diprogram pada KRS semester ini.',
                ]);
            }
            $currentCredits = (float) $connection->table('s1m4k_krs as krs')
                ->join('s1m4k_mastermk as current_course', 'krs.id_matkul', '=', 'current_course.id_matkul')
                ->where('krs.id_mahasiswa', $student->id_mahasiswa)
                ->where('krs.id_kalender', $calendar->id_kalender)
                ->sum('current_course.sks_mata_kuliah');
            abort_if($currentCredits + (float) $course->sks_mata_kuliah > 24, 422,
                'Total SKS semester melebihi batas konversi MBKM 24 SKS.');

            $connection->table('s1m4k_krs')->insert($row);
            $activityLogger->record(
                $user,
                'Transfer Nilai MBKM',
                'Menambahkan konversi nilai MBKM',
                ['mode' => $validated['mode'], 'data_baru' => $row],
                $row['id_krs'],
                $this->studentIdentity($student, $course)
            );
        });

        return redirect()->route('mbkm-transfers.index', ['search' => $student->nim])
            ->with('status', 'Konversi nilai MBKM berhasil disimpan.');
    }

    public function destroy(Request $request, string $studentId, string $krsId, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $student = $this->findStudent($user, $studentId);
        $calendar = $this->activeCalendar($student);
        abort_if($calendar === null, 404);
        $this->assertStudentEligible($student, $calendar);
        $row = DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->join('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
            ->where('krs.id_krs', $krsId)
            ->where('krs.id_mahasiswa', $student->id_mahasiswa)
            ->where('krs.id_kalender', $calendar->id_kalender)
            ->where('krs.krs_mbkm', 'Y')
            ->first(['krs.*', 'course.kode_mata_kuliah', 'course.nama_mata_kuliah']);
        abort_if($row === null, 404);

        DB::connection('simak_main')->transaction(function () use ($row, $student, $user, $activityLogger): void {
            DB::connection('simak_main')->table('s1m4k_krs')->where('id_krs', $row->id_krs)->delete();
            $activityLogger->record(
                $user,
                'Transfer Nilai MBKM',
                'Menghapus konversi nilai MBKM',
                ['data_dihapus' => (array) $row],
                (string) $row->id_krs,
                $this->studentIdentity($student, $row)
            );
        });

        return redirect()->route('mbkm-transfers.index', ['search' => $student->nim])
            ->with('status', 'Mata kuliah hasil konversi berhasil dihapus.');
    }

    private function studentQuery(object $user)
    {
        $query = DB::connection('simak_main')->table('s1m4k_mastermhs as student')
            ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
            ->select([
                'student.id_mahasiswa', 'student.nim', 'student.nama_mahasiswa',
                'student.id_prodi', 'student.id_fakultas', 'student.id_perguruan_tinggi',
                'student.id_registrasi_mahasiswa', 'student.nama_status_mahasiswa',
                'program.nama_program_studi',
            ])
            ->whereRaw("TRIM(COALESCE(student.id_mahasiswa, '')) <> ''");
        if ($user->role === 'userprodi') {
            $programId = trim((string) data_get($user, 'id_prodi'));
            abort_unless($programId !== '', 403);
            $query->where('student.id_prodi', $programId);
        }

        return $query;
    }

    private function findStudent(object $user, string $studentId): object
    {
        $student = $this->studentQuery($user)->where('student.id_mahasiswa', $studentId)->first();
        abort_if($student === null, 404);

        return $student;
    }

    private function activeCalendar(object $student): ?object
    {
        return AcademicCalendarScope::excludeShortSemesters(
            DB::connection('simak_main')->table('s1m4k_kalender')
        )
            ->where('id_prodi', $student->id_prodi)
            ->where('status', 'Y')
            ->orderByDesc('tahun')
            ->first();
    }

    private function assertStudentEligible(object $student, object $calendar): void
    {
        $connection = DB::connection('simak_main');
        $registered = $connection->table('s1m4k_registrasiulang')
            ->where('id_mahasiswa', $student->id_mahasiswa)
            ->where('id_kalender', $calendar->id_kalender)
            ->exists();
        abort_unless($registered, 422, 'Mahasiswa belum melakukan registrasi ulang pada kalender aktif.');
        $isMbkm = $connection->table('s1m4k_akm')
            ->where('id_mahasiswa', $student->id_mahasiswa)
            ->where('id_kalender', $calendar->id_kalender)
            ->where('status_mahasiswa', 'M')
            ->exists();
        abort_unless($isMbkm, 422, 'Status mahasiswa belum ditetapkan sebagai mahasiswa MBKM pada kalender aktif.');
    }

    private function activities(object $student, object $calendar)
    {
        return DB::connection('simak_main')->table('s1m4k_peran_kegiatan as role')
            ->join('s1m4k_kegiatanmhs as activity', 'role.id_aktivitas', '=', 'activity.id_kegiatan')
            ->where('role.id_registrasi_mahasiswa', $student->id_registrasi_mahasiswa)
            ->where('activity.id_kalender', $calendar->id_kalender)
            ->orderBy('activity.judul')
            ->get(['activity.id_kegiatan', 'activity.judul']);
    }

    private function academicMetrics(object $student, object $calendar): array
    {
        $connection = DB::connection('simak_main');
        $termCourses = $connection->table('s1m4k_krs as krs')
            ->join('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
            ->where('krs.id_mahasiswa', $student->id_mahasiswa)
            ->where('krs.tahun', $calendar->tahun)
            ->where('krs.krs_reguler', 'Y')
            ->get(['krs.nilai_bobot', 'course.sks_mata_kuliah']);
        $termCredits = (float) $termCourses->sum(fn (object $row): float => (float) $row->sks_mata_kuliah);
        $termQuality = (float) $termCourses->sum(
            fn (object $row): float => (float) $row->nilai_bobot * (float) $row->sks_mata_kuliah
        );

        $previousCourses = $connection->table('s1m4k_krs as krs')
            ->join('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
            ->where('krs.id_mahasiswa', $student->id_mahasiswa)
            ->where('krs.tahun', '<', $calendar->tahun)
            ->select('krs.id_matkul', DB::raw('MAX(krs.nilai_bobot) as nilai_bobot'), 'course.sks_mata_kuliah')
            ->groupBy('krs.id_matkul', 'course.sks_mata_kuliah')
            ->get();
        $programCredits = (float) $previousCourses->sum(fn (object $row): float => (float) $row->sks_mata_kuliah);
        $programQuality = (float) $previousCourses->sum(
            fn (object $row): float => (float) $row->nilai_bobot * (float) $row->sks_mata_kuliah
        );
        $passedCredits = (float) $previousCourses
            ->filter(fn (object $row): bool => (float) $row->nilai_bobot > 0)
            ->sum(fn (object $row): float => (float) $row->sks_mata_kuliah);

        return [
            'ips' => number_format($termCredits > 0 ? $termQuality / $termCredits : 0, 2, '.', ''),
            'ipk' => number_format($programCredits > 0 ? $programQuality / $programCredits : 0, 2, '.', ''),
            'sks_semester' => number_format($termCredits, 2, '.', ''),
            'sks_program' => number_format($programCredits, 2, '.', ''),
            'sks_lulus' => number_format($passedCredits, 2, '.', ''),
        ];
    }

    private function studentIdentity(object $student, object $course): array
    {
        return [
            'nama' => $student->nama_mahasiswa,
            'nomor_identitas' => $student->nim,
            'program_studi' => $student->nama_program_studi,
            'mata_kuliah' => $course->nama_mata_kuliah,
        ];
    }

    private function authorizeRole(object $user): void
    {
        abort_unless(in_array($user->role, self::ROLES, true), 403);
    }
}
