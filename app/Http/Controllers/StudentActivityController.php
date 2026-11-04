<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\LegacyFeederService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class StudentActivityController extends Controller
{
    private const ROLES = ['admin', 'userprodi', 'usertu'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $filters = $request->validate(['program' => ['nullable', 'string', 'max:100']]);
        $programs = $this->programs($user);
        $programId = in_array($user->role, ['userprodi', 'usertu'], true)
            ? trim((string) data_get($user, 'id_prodi'))
            : (string) ($filters['program'] ?? '');
        if (in_array($user->role, ['userprodi', 'usertu'], true)) {
            abort_unless($programId !== '', 403);
        }
        if ($programId !== '') {
            abort_unless($programs->contains('id_prodi', $programId), 404);
        }

        $calendar = $programId !== '' ? $this->activeCalendar($programId) : null;
        $activities = collect();
        if ($calendar !== null) {
            $activities = DB::connection('simak_main')->table('s1m4k_kegiatanmhs as activity')
                ->leftJoin('s1m4k_masterprodi as program', 'activity.id_prodi', '=', 'program.id_prodi')
                ->leftJoin('s1m4k_kodereferensi as kind', function ($join): void {
                    $join->on('activity.jenis_kegiatan', '=', 'kind.kode_referensi')
                        ->where('kind.keterangan', 'GetJenisAktivitasMahasiswa');
                })
                ->where('activity.id_prodi', $programId)
                ->where('activity.id_kalender', $calendar->id_kalender)
                ->orderBy('activity.judul')
                ->get([
                    'activity.id_kegiatan', 'activity.id_prodi', 'activity.id_kalender',
                    'activity.jenis_kegiatan', 'activity.program_mbkm', 'activity.judul', 'activity.lokasi',
                    'activity.no_sk', 'activity.tgl_sk', 'activity.jenis_anggota',
                    'activity.ket', 'program.nama_program_studi', 'kind.ket_referensi as jenis_label',
                ]);
        }

        return view('student-activities.index', compact(
            'user', 'programs', 'programId', 'calendar', 'activities'
        ));
    }

    public function show(Request $request, string $activityId): View
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $activity = $this->findActivity($user, $activityId);
        $connection = DB::connection('simak_main');
        $participants = $connection->table('s1m4k_peran_kegiatan as role')
            ->leftJoin('s1m4k_mastermhs as student', 'role.id_registrasi_mahasiswa', '=', 'student.id_registrasi_mahasiswa')
            ->where('role.id_aktivitas', $activityId)
            ->where('role.status', 'peserta')
            ->orderBy('role.jenis_peran')
            ->orderBy('student.nama_mahasiswa')
            ->get(['role.id_peran', 'role.id_anggota', 'role.jenis_peran', 'student.nim', 'student.nama_mahasiswa']);
        $advisors = $this->activityLecturers($activityId, 'pembimbing');
        $examiners = $this->activityLecturers($activityId, 'penguji');
        $students = $connection->table('s1m4k_mastermhs')
            ->where('id_prodi', $activity->id_prodi)
            ->whereIn(DB::raw("LOWER(TRIM(COALESCE(nama_status_mahasiswa, '')))"), ['aktif', 'cuti'])
            ->orderBy('nama_mahasiswa')
            ->get(['id_registrasi_mahasiswa', 'nim', 'nama_mahasiswa']);
        $lecturers = $connection->table('s1m4k_masterdosen')
            ->where('id_status_aktif', '1')
            ->orderBy('nama_dosen')
            ->get(['id_dosen', 'nidn', 'nama_dosen', 'gelar_depan', 'gelar_belakang']);
        $categories = $connection->table('s1m4k_kodereferensi')
            ->where('keterangan', 'getkategorikegiatan')
            ->orderBy('ket_referensi')
            ->get(['kode_referensi', 'ket_referensi']);

        return view('student-activities.show', compact(
            'user', 'activity', 'participants', 'advisors', 'examiners',
            'students', 'lecturers', 'categories'
        ));
    }

    public function store(Request $request, LegacyFeederService $feeder, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $validated = $request->validate([
            'program' => ['required', 'string', 'max:100'],
            'jenis_kegiatan' => ['required', 'string', 'max:10'],
            'program_mbkm' => ['required', Rule::in(['0', '1'])],
            'judul' => ['required', 'string', 'max:2000'],
            'lokasi' => ['nullable', 'string', 'max:100'],
            'no_sk' => ['nullable', 'string', 'max:50'],
            'tgl_sk' => ['nullable', 'date_format:Y-m-d'],
            'jenis_anggota' => ['required', Rule::in(['0', '1'])],
            'ket' => ['nullable', 'string', 'max:2000'],
        ]);
        $programId = $this->programIdForUser($user, $validated['program']);
        $calendar = $this->activeCalendar($programId);
        abort_if($calendar === null, 422, 'Kalender aktif program studi belum tersedia.');
        $kindExists = DB::connection('simak_main')->table('s1m4k_kodereferensi')
            ->where('keterangan', 'GetJenisAktivitasMahasiswa')
            ->where('kode_referensi', $validated['jenis_kegiatan'])
            ->exists();
        abort_unless($kindExists, 422, 'Jenis kegiatan tidak tersedia pada referensi SIMAK.');

        $feederRecord = [
            'program_mbkm' => $validated['program_mbkm'],
            'jenis_anggota' => $validated['jenis_anggota'],
            'id_jenis_aktivitas' => $validated['jenis_kegiatan'],
            'id_prodi' => $programId,
            'id_semester' => $calendar->tahun,
            'judul' => trim($validated['judul']),
            'keterangan' => $validated['ket'] ?? '',
            'lokasi' => $validated['lokasi'] ?? '',
            'sk_tugas' => $validated['no_sk'] ?? '',
        ];
        if ($user->role === 'admin') {
            $feederRecord['tanggal_sk_tugas'] = $validated['tgl_sk'] ?? '';
        }

        try {
            $response = $feeder->studentActivityAction('InsertAktivitasMahasiswa', [
                'record' => $feederRecord,
            ]);
            $activityId = trim((string) ($response[0]['id_aktivitas'] ?? ''));
            if ($activityId === '') {
                throw new RuntimeException('Feeder tidak mengembalikan ID aktivitas mahasiswa.');
            }
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withInput()->withErrors(['feeder' => $exception->getMessage()]);
        }

        $values = [
            'id_kegiatan' => $activityId,
            'id_kalender' => $calendar->id_kalender,
            'id_prodi' => $programId,
            'jenis_kegiatan' => $validated['jenis_kegiatan'],
            'program_mbkm' => $validated['program_mbkm'],
            'judul' => trim($validated['judul']),
            'lokasi' => $validated['lokasi'] ?? '',
            'no_sk' => $validated['no_sk'] ?? '',
            'tgl_sk' => $validated['tgl_sk'] ?? null,
            'jenis_anggota' => $validated['jenis_anggota'],
            'ket' => $validated['ket'] ?? '',
        ];
        DB::connection('simak_main')->transaction(function () use ($values, $user, $activityLogger): void {
            DB::connection('simak_main')->table('s1m4k_kegiatanmhs')->insert($values);
            $activityLogger->record($user, 'Aktivitas Mahasiswa', 'Membuat aktivitas mahasiswa', [
                'data_baru' => $values,
            ], $values['id_kegiatan'], [
                'nama' => $values['judul'],
                'program_studi' => $values['id_prodi'],
            ]);
        });

        return redirect()->route('student-activities.index', ['program' => $programId])
            ->with('status', 'Aktivitas mahasiswa berhasil disimpan ke Feeder dan SIMAK.');
    }

    public function update(Request $request, string $activityId, LegacyFeederService $feeder, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $activity = $this->findActivity($user, $activityId);
        $validated = $request->validate([
            'jenis_kegiatan' => ['required', 'string', 'max:10'],
            'program_mbkm' => ['required', Rule::in(['0', '1'])],
            'judul' => ['required', 'string', 'max:2000'],
            'lokasi' => ['nullable', 'string', 'max:100'],
            'no_sk' => ['nullable', 'string', 'max:50'],
            'tgl_sk' => ['nullable', 'date_format:Y-m-d'],
            'jenis_anggota' => ['required', Rule::in(['0', '1'])],
            'ket' => ['nullable', 'string', 'max:2000'],
        ]);
        $calendar = $this->activeCalendar((string) $activity->id_prodi);
        abort_unless($calendar !== null && (string) $calendar->id_kalender === (string) $activity->id_kalender, 404);

        $values = [
            'jenis_kegiatan' => $validated['jenis_kegiatan'],
            'program_mbkm' => $validated['program_mbkm'],
            'judul' => trim($validated['judul']),
            'lokasi' => $validated['lokasi'] ?? '',
            'no_sk' => $validated['no_sk'] ?? '',
            'tgl_sk' => $validated['tgl_sk'] ?? null,
            'jenis_anggota' => $validated['jenis_anggota'],
            'ket' => $validated['ket'] ?? '',
        ];
        $feederRecord = [
            'program_mbkm' => $values['program_mbkm'],
            'jenis_anggota' => $values['jenis_anggota'],
            'id_jenis_aktivitas' => $values['jenis_kegiatan'],
            'id_semester' => $calendar->tahun,
            'id_prodi' => $activity->id_prodi,
            'judul' => $values['judul'],
            'keterangan' => $values['ket'],
            'lokasi' => $values['lokasi'],
            'sk_tugas' => $values['no_sk'],
            'tanggal_sk_tugas' => $values['tgl_sk'] ?? '',
        ];
        try {
            $feeder->studentActivityAction('UpdateAktivitasMahasiswa', [
                'key' => ['id_aktivitas' => $activityId],
                'record' => $feederRecord,
            ]);
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withInput()->withErrors(['feeder' => $exception->getMessage()]);
        }

        DB::connection('simak_main')->transaction(function () use ($activity, $values, $activityId, $user, $activityLogger): void {
            DB::connection('simak_main')->table('s1m4k_kegiatanmhs')->where('id_kegiatan', $activityId)->update($values);
            $activityLogger->record($user, 'Aktivitas Mahasiswa', 'Memperbarui aktivitas mahasiswa', [
                'data_sebelumnya' => (array) $activity,
                'data_baru' => $values,
            ], $activityId, ['nama' => $values['judul'], 'program_studi' => $activity->id_prodi]);
        });

        return redirect()->route('student-activities.show', $activityId)
            ->with('status', 'Aktivitas mahasiswa berhasil diperbarui.');
    }

    public function destroy(Request $request, string $activityId, LegacyFeederService $feeder, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $activity = $this->findActivity($user, $activityId);
        $calendar = $this->activeCalendar((string) $activity->id_prodi);
        abort_unless($calendar !== null && (string) $calendar->id_kalender === (string) $activity->id_kalender, 404);

        try {
            $feeder->studentActivityAction('DeleteAktivitasMahasiswa', [
                'key' => ['id_aktivitas' => $activityId],
            ]);
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors(['feeder' => $exception->getMessage()]);
        }

        DB::connection('simak_main')->transaction(function () use ($activity, $activityId, $user, $activityLogger): void {
            DB::connection('simak_main')->table('s1m4k_kegiatanmhs')->where('id_kegiatan', $activityId)->delete();
            $activityLogger->record($user, 'Aktivitas Mahasiswa', 'Menghapus aktivitas mahasiswa', [
                'data_dihapus' => (array) $activity,
            ], $activityId, ['nama' => $activity->judul, 'program_studi' => $activity->id_prodi]);
        });

        return redirect()->route('student-activities.index', ['program' => $activity->id_prodi])
            ->with('status', 'Aktivitas mahasiswa berhasil dihapus dari Feeder dan SIMAK.');
    }

    public function addParticipant(Request $request, string $activityId, LegacyFeederService $feeder, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $activity = $this->findActivity($user, $activityId);
        $validated = $request->validate([
            'id_registrasi_mahasiswa' => ['required', 'string', 'max:100'],
            'jenis_peran' => ['required', Rule::in($activity->jenis_anggota === '0' ? ['3'] : ['1', '2'])],
        ]);
        $student = DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_registrasi_mahasiswa', $validated['id_registrasi_mahasiswa'])
            ->where('id_prodi', $activity->id_prodi)
            ->whereIn(DB::raw("LOWER(TRIM(COALESCE(nama_status_mahasiswa, '')))"), ['aktif', 'cuti'])
            ->first(['id_mahasiswa', 'id_registrasi_mahasiswa', 'nim', 'nama_mahasiswa']);
        abort_if($student === null, 404);
        $duplicate = DB::connection('simak_main')->table('s1m4k_peran_kegiatan')
            ->where('id_aktivitas', $activityId)
            ->where('id_registrasi_mahasiswa', $student->id_registrasi_mahasiswa)
            ->where('status', 'peserta')
            ->exists();
        abort_if($duplicate, 409, 'Mahasiswa sudah menjadi peserta kegiatan ini.');

        try {
            $response = $feeder->studentActivityAction('InsertAnggotaAktivitasMahasiswa', [
                'record' => [
                    'id_aktivitas' => $activityId,
                    'id_registrasi_mahasiswa' => $student->id_registrasi_mahasiswa,
                    'jenis_peran' => $validated['jenis_peran'],
                ],
            ]);
            $feederId = trim((string) ($response[0]['id_anggota'] ?? ''));
            if ($feederId === '') {
                throw new RuntimeException('Feeder tidak mengembalikan ID peserta aktivitas.');
            }
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withInput()->withErrors(['feeder' => $exception->getMessage()]);
        }

        DB::connection('simak_main')->transaction(function () use ($activityId, $student, $validated, $feederId, $user, $activityLogger, $activity): void {
            DB::connection('simak_main')->table('s1m4k_peran_kegiatan')->insert([
                'id_peran' => (string) Str::uuid(),
                'id_anggota' => $feederId,
                'id_bimbing_mahasiswa' => '',
                'id_uji' => '',
                'id_aktivitas' => $activityId,
                'id_registrasi_mahasiswa' => $student->id_registrasi_mahasiswa,
                'id_kategori' => '',
                'id_dosen' => '',
                'jenis_peran' => $validated['jenis_peran'],
                'pembimbing_ke' => '',
                'penguji_ke' => '',
                'status' => 'peserta',
            ]);
            if (in_array((string) $activity->jenis_kegiatan, ['1', '2', '3', '4'], true)) {
                DB::connection('simak_main')->table('s1m4k_kegiatanmhs')->where('id_kegiatan', $activityId)
                    ->update(['id_mahasiswa' => $student->id_mahasiswa]);
            }
            $activityLogger->record($user, 'Aktivitas Mahasiswa', 'Menambahkan peserta kegiatan', [
                'id_anggota_feeder' => $feederId,
                'id_registrasi_mahasiswa' => $student->id_registrasi_mahasiswa,
                'jenis_peran' => $validated['jenis_peran'],
            ], $activityId, ['nama' => $activity->judul, 'nomor_identitas' => $student->nim]);
        });

        return redirect()->route('student-activities.show', $activityId)
            ->with('status', 'Peserta kegiatan berhasil ditambahkan.');
    }

    public function addAdvisor(Request $request, string $activityId, LegacyFeederService $feeder, ActivityLogger $activityLogger): RedirectResponse
    {
        return $this->addLecturerRole($request, $activityId, 'pembimbing', $feeder, $activityLogger);
    }

    public function addExaminer(Request $request, string $activityId, LegacyFeederService $feeder, ActivityLogger $activityLogger): RedirectResponse
    {
        return $this->addLecturerRole($request, $activityId, 'penguji', $feeder, $activityLogger);
    }

    public function removeRole(Request $request, string $activityId, string $roleId, string $roleType, LegacyFeederService $feeder, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        abort_unless(in_array($roleType, ['peserta', 'pembimbing', 'penguji'], true), 404);
        $activity = $this->findActivity($user, $activityId);
        $role = DB::connection('simak_main')->table('s1m4k_peran_kegiatan')
            ->where('id_peran', $roleId)
            ->where('id_aktivitas', $activityId)
            ->where('status', $roleType)
            ->first();
        abort_if($role === null, 404);
        [$action, $keyName, $feederId] = match ($roleType) {
            'peserta' => ['DeleteAnggotaAktivitasMahasiswa', 'id_anggota', $role->id_anggota],
            'pembimbing' => ['DeleteBimbingMahasiswa', 'id_bimbing_mahasiswa', $role->id_bimbing_mahasiswa],
            default => ['DeleteUjiMahasiswa', 'id_uji', $role->id_uji],
        };

        try {
            $feeder->studentActivityAction($action, ['key' => [$keyName => $feederId]]);
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors(['feeder' => $exception->getMessage()]);
        }

        DB::connection('simak_main')->transaction(function () use ($role, $activity, $activityId, $user, $activityLogger): void {
            DB::connection('simak_main')->table('s1m4k_peran_kegiatan')->where('id_peran', $role->id_peran)->delete();
            $activityLogger->record($user, 'Aktivitas Mahasiswa', 'Menghapus peran kegiatan', [
                'peran_dihapus' => (array) $role,
            ], $activityId, ['nama' => $activity->judul]);
        });

        return redirect()->route('student-activities.show', $activityId)
            ->with('status', 'Peran kegiatan berhasil dihapus.');
    }

    private function addLecturerRole(Request $request, string $activityId, string $roleType, LegacyFeederService $feeder, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $activity = $this->findActivity($user, $activityId);
        $field = $roleType === 'pembimbing' ? 'pembimbing_ke' : 'penguji_ke';
        $validated = $request->validate([
            'id_dosen' => ['required', 'string', 'max:100'],
            'id_kategori' => ['required', 'string', 'max:10'],
            $field => ['required', 'integer', 'between:1,9'],
        ]);
        $connection = DB::connection('simak_main');
        $lecturer = $connection->table('s1m4k_masterdosen')
            ->where('id_dosen', $validated['id_dosen'])
            ->where('id_status_aktif', '1')
            ->first(['id_dosen', 'nama_dosen', 'nidn']);
        abort_if($lecturer === null, 404);
        $categoryExists = $connection->table('s1m4k_kodereferensi')
            ->where('keterangan', 'getkategorikegiatan')
            ->where('kode_referensi', $validated['id_kategori'])
            ->exists();
        abort_unless($categoryExists, 422, 'Kategori kegiatan tidak valid.');
        abort_if($connection->table('s1m4k_peran_kegiatan')
            ->where('id_aktivitas', $activityId)
            ->where('id_dosen', $lecturer->id_dosen)
            ->where('status', $roleType)
            ->exists(), 409, 'Dosen sudah terdaftar pada peran kegiatan ini.');

        $action = $roleType === 'pembimbing' ? 'InsertBimbingMahasiswa' : 'InsertUjiMahasiswa';
        $record = [
            'id_aktivitas' => $activityId,
            'id_dosen' => $lecturer->id_dosen,
            'id_kategori_kegiatan' => $validated['id_kategori'],
            $field => (string) $validated[$field],
        ];
        try {
            $response = $feeder->studentActivityAction($action, ['record' => $record]);
            $idKey = $roleType === 'pembimbing' ? 'id_bimbing_mahasiswa' : 'id_uji';
            $feederId = trim((string) ($response[0][$idKey] ?? ''));
            if ($feederId === '') {
                throw new RuntimeException('Feeder tidak mengembalikan ID peran dosen.');
            }
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withInput()->withErrors(['feeder' => $exception->getMessage()]);
        }

        $roleRow = [
            'id_peran' => (string) Str::uuid(),
            'id_anggota' => '',
            'id_bimbing_mahasiswa' => $roleType === 'pembimbing' ? $feederId : '',
            'id_uji' => $roleType === 'penguji' ? $feederId : '',
            'id_aktivitas' => $activityId,
            'id_registrasi_mahasiswa' => '',
            'id_kategori' => $validated['id_kategori'],
            'id_dosen' => $lecturer->id_dosen,
            'jenis_peran' => '',
            'pembimbing_ke' => $roleType === 'pembimbing' ? (string) $validated[$field] : '',
            'penguji_ke' => $roleType === 'penguji' ? (string) $validated[$field] : '',
            'status' => $roleType,
        ];
        $connection->transaction(function () use ($connection, $roleRow, $activity, $lecturer, $user, $activityLogger, $activityId, $roleType): void {
            $connection->table('s1m4k_peran_kegiatan')->insert($roleRow);
            $activityLogger->record($user, 'Aktivitas Mahasiswa', 'Menambahkan '.$roleType.' kegiatan', [
                'peran_baru' => $roleRow,
            ], $activityId, ['nama' => $activity->judul, 'dosen' => $lecturer->nama_dosen]);
        });

        return redirect()->route('student-activities.show', $activityId)
            ->with('status', ucfirst($roleType).' kegiatan berhasil ditambahkan.');
    }

    private function activityLecturers(string $activityId, string $roleType)
    {
        return DB::connection('simak_main')->table('s1m4k_peran_kegiatan as role')
            ->leftJoin('s1m4k_masterdosen as lecturer', 'role.id_dosen', '=', 'lecturer.id_dosen')
            ->leftJoin('s1m4k_kodereferensi as category', function ($join): void {
                $join->on('role.id_kategori', '=', 'category.kode_referensi')
                    ->where('category.keterangan', 'getkategorikegiatan');
            })
            ->where('role.id_aktivitas', $activityId)
            ->where('role.status', $roleType)
            ->orderBy($roleType === 'pembimbing' ? 'role.pembimbing_ke' : 'role.penguji_ke')
            ->get([
                'role.id_peran', 'role.id_dosen', 'role.id_bimbing_mahasiswa', 'role.id_uji',
                'role.pembimbing_ke', 'role.penguji_ke', 'role.id_kategori',
                'lecturer.nidn', 'lecturer.nama_dosen', 'lecturer.gelar_depan', 'lecturer.gelar_belakang',
                'category.ket_referensi as category_name',
            ]);
    }

    private function findActivity(object $user, string $activityId): object
    {
        $query = DB::connection('simak_main')->table('s1m4k_kegiatanmhs as activity')
            ->leftJoin('s1m4k_kalender as calendar', 'activity.id_kalender', '=', 'calendar.id_kalender')
            ->leftJoin('s1m4k_masterprodi as program', 'activity.id_prodi', '=', 'program.id_prodi')
            ->where('activity.id_kegiatan', $activityId);
        if ($user->role !== 'admin') {
            $programId = trim((string) data_get($user, 'id_prodi'));
            abort_unless($programId !== '', 403);
            $query->where('activity.id_prodi', $programId);
        }
        $activity = $query->first(['activity.*', 'calendar.tahun', 'calendar.namatahun', 'program.nama_program_studi']);
        abort_if($activity === null, 404);

        return $activity;
    }

    private function programs(object $user)
    {
        $query = DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->orderBy('nama_program_studi')
            ->select(['id_prodi', 'id_fakultas', 'nama_program_studi', 'nama_jenjang_pendidikan']);
        if ($user->role !== 'admin') {
            $programId = trim((string) data_get($user, 'id_prodi'));
            abort_unless($programId !== '', 403);
            $query->where('id_prodi', $programId);
        }

        return $query->get();
    }

    private function programIdForUser(object $user, string $requestedProgram): string
    {
        $programId = $user->role === 'admin'
            ? $requestedProgram
            : trim((string) data_get($user, 'id_prodi'));
        abort_unless($programId !== '', 403);
        abort_unless($this->programs($user)->contains('id_prodi', $programId), 404);

        return $programId;
    }

    private function activeCalendar(string $programId): ?object
    {
        return DB::connection('simak_main')->table('s1m4k_kalender')
            ->where('id_prodi', $programId)
            ->where('status', 'Y')
            ->orderByDesc('tahun')
            ->first();
    }

    private function authorizeRole(object $user): void
    {
        abort_unless(in_array($user->role, self::ROLES, true), 403);
    }
}
