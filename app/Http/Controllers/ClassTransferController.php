<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Support\AcademicCalendarScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClassTransferController extends Controller
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
        $calendar = $student ? $this->activeCalendar($student) : null;
        $registered = false;
        $rows = collect();

        if ($student !== null && $calendar !== null) {
            $registered = DB::connection('simak_main')->table('s1m4k_registrasiulang')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_kalender', $calendar->id_kalender)
                ->exists();
            $rows = DB::connection('simak_main')->table('s1m4k_krs as krs')
                ->join('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
                ->leftJoin('s1m4k_jadwal as current_schedule', 'krs.id_jadwal', '=', 'current_schedule.id_jadwal')
                ->where('krs.id_mahasiswa', $student->id_mahasiswa)
                ->where('krs.id_prodi', $student->id_prodi)
                ->where('krs.id_kalender', $calendar->id_kalender)
                ->orderBy('course.semester')
                ->orderBy('course.nama_mata_kuliah')
                ->get([
                    'krs.id_krs', 'krs.id_matkul', 'krs.id_jadwal',
                    'course.kode_mata_kuliah', 'course.nama_mata_kuliah', 'course.semester',
                    'current_schedule.nama_kelas as current_class',
                ]);
            $targetClasses = DB::connection('simak_main')->table('s1m4k_jadwal')
                ->where('id_prodi', $student->id_prodi)
                ->where('id_kalender', $calendar->id_kalender)
                ->orderBy('nama_kelas')
                ->get(['id_jadwal', 'id_matkul', 'nama_kelas'])
                ->groupBy('id_matkul');
            foreach ($rows as $row) {
                $row->target_classes = $targetClasses->get($row->id_matkul, collect());
            }
        }

        return view('class-transfers.index', compact(
            'user', 'filters', 'student', 'calendar', 'registered', 'rows'
        ));
    }

    public function update(Request $request, string $studentId, string $krsId, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $student = $this->findStudent($user, $studentId);
        $calendar = $this->activeCalendar($student);
        abort_if($calendar === null, 422, 'Kalender akademik aktif mahasiswa tidak tersedia.');
        $registered = DB::connection('simak_main')->table('s1m4k_registrasiulang')
            ->where('id_mahasiswa', $student->id_mahasiswa)
            ->where('id_kalender', $calendar->id_kalender)
            ->exists();
        abort_unless($registered, 422, 'Mahasiswa belum melakukan registrasi ulang pada kalender aktif.');

        $validated = $request->validate([
            'id_jadwal' => ['required', 'string', 'max:50'],
        ]);
        $connection = DB::connection('simak_main');
        $krs = $connection->table('s1m4k_krs')
            ->where('id_krs', $krsId)
            ->where('id_mahasiswa', $student->id_mahasiswa)
            ->where('id_prodi', $student->id_prodi)
            ->where('id_kalender', $calendar->id_kalender)
            ->first();
        abort_if($krs === null, 404);

        $target = $connection->table('s1m4k_jadwal')
            ->where('id_jadwal', $validated['id_jadwal'])
            ->where('id_matkul', $krs->id_matkul)
            ->where('id_prodi', $student->id_prodi)
            ->where('id_kalender', $calendar->id_kalender)
            ->first(['id_jadwal', 'nama_kelas']);
        if ($target === null) {
            throw ValidationException::withMessages([
                'id_jadwal' => 'Pilih kelas mata kuliah yang sama pada kalender aktif dan program studi mahasiswa.',
            ]);
        }

        if ((string) $krs->id_jadwal !== (string) $target->id_jadwal) {
            $connection->transaction(function () use ($connection, $krs, $target, $student, $user, $activityLogger): void {
                $connection->table('s1m4k_krs')
                    ->where('id_krs', $krs->id_krs)
                    ->where('id_mahasiswa', $student->id_mahasiswa)
                    ->where('id_kalender', $krs->id_kalender)
                    ->update(['id_jadwal' => $target->id_jadwal]);
                $activityLogger->record(
                    $user,
                    'Transfer Kelas',
                    'Memindahkan kelas KRS',
                    [
                        'id_matkul' => $krs->id_matkul,
                        'id_jadwal_lama' => $krs->id_jadwal,
                        'id_jadwal_baru' => $target->id_jadwal,
                        'kelas_baru' => $target->nama_kelas,
                    ],
                    (string) $krs->id_krs,
                    $this->studentIdentity($student)
                );
            });
        }

        return redirect()->route('class-transfers.index', ['search' => $student->nim])
            ->with('status', 'Kelas KRS mahasiswa berhasil dipindahkan.');
    }

    private function studentQuery(object $user)
    {
        $query = DB::connection('simak_main')->table('s1m4k_mastermhs as student')
            ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
            ->select([
                'student.id_mahasiswa', 'student.nim', 'student.nama_mahasiswa', 'student.id_prodi',
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

    private function studentIdentity(object $student): array
    {
        return [
            'nama' => $student->nama_mahasiswa,
            'nomor_identitas' => $student->nim,
            'program_studi' => $student->nama_program_studi,
        ];
    }

    private function authorizeRole(object $user): void
    {
        abort_unless(in_array($user->role, self::ROLES, true), 403);
    }
}
