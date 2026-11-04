<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CourseRemovalController extends Controller
{
    private const ROLES = ['admin', 'userprodi'];

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'student' => ['nullable', 'string', 'max:100'],
        ]);
        $students = collect();
        $student = null;
        $courses = collect();

        if (filled($filters['search'] ?? null)) {
            $search = trim($filters['search']);
            if (ctype_digit($search)) {
                $student = $this->studentQuery($user)->where('student.nim', $search)->first();
                if ($student === null) {
                    return redirect()->route('course-removals.index')
                        ->withInput(['search' => $search])
                        ->withErrors(['search' => 'Data mahasiswa dengan NPM tersebut tidak ditemukan.']);
                }
                $courses = $this->studentCourses((string) $student->id_mahasiswa);
            } else {
                $students = $this->studentQuery($user)
                    ->where(function ($query) use ($search): void {
                        $query->where('student.nim', 'like', '%'.$search.'%')
                            ->orWhere('student.nama_mahasiswa', 'like', '%'.$search.'%');
                    })
                    ->orderBy('student.nama_mahasiswa')
                    ->paginate(25)
                    ->withQueryString();
            }
        }

        if ($student === null && filled($filters['student'] ?? null)) {
            $student = $this->findStudent($user, $filters['student']);
            $courses = $this->studentCourses((string) $student->id_mahasiswa);
        }
        $totalSks = (float) $courses->sum('sks_mata_kuliah');
        $totalQuality = (float) $courses->sum('mutu');
        $ipk = $totalSks > 0 ? $totalQuality / $totalSks : 0.0;

        return view('course-removals.index', compact(
            'filters', 'students', 'student', 'courses', 'totalSks', 'totalQuality', 'ipk'
        ));
    }

    public function destroy(
        Request $request,
        string $studentId,
        string $courseId,
        ActivityLogger $activityLogger
    ): RedirectResponse {
        $user = $request->user();
        $this->authorizeRole($user);
        $student = $this->findStudent($user, $studentId);
        $search = $request->validate(['search' => ['required', 'string', 'max:120']])['search'];
        $connection = DB::connection('simak_main');
        $course = $connection->table('s1m4k_mastermk')
            ->where('id_matkul', $courseId)
            ->first(['id_matkul', 'kode_mata_kuliah', 'nama_mata_kuliah']);
        abort_if($course === null, 404);

        $connection->transaction(function () use ($connection, $activityLogger, $course, $courseId, $student, $user): void {
            $rows = $connection->table('s1m4k_krs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_matkul', $courseId)
                ->lockForUpdate()
                ->get();
            abort_if($rows->isEmpty(), 404, 'Mata kuliah tidak ditemukan pada KRS mahasiswa.');

            $krsIds = $rows->pluck('id_krs')->map(fn ($id): string => (string) $id)->all();
            $edomRows = $connection->table('s1m4k_edom')
                ->whereIn('id_krs', $krsIds)
                ->get()
                ->map(fn (object $row): array => (array) $row)
                ->all();
            if ($krsIds !== []) {
                $connection->table('s1m4k_edom')->whereIn('id_krs', $krsIds)->delete();
            }
            $connection->table('s1m4k_krs')
                ->where('id_mahasiswa', $student->id_mahasiswa)
                ->where('id_matkul', $courseId)
                ->delete();

            $activityLogger->record($user, 'Penghapusan Mata Kuliah', 'Menghapus mata kuliah dari KRS', [
                'mata_kuliah' => [
                    'id_matkul' => $course->id_matkul,
                    'kode' => $course->kode_mata_kuliah,
                    'nama' => $course->nama_mata_kuliah,
                ],
                'data_krs_dihapus' => $rows->map(fn (object $row): array => (array) $row)->all(),
                'data_edom_dihapus' => $edomRows,
            ], (string) $student->id_mahasiswa, [
                'nama' => $student->nama_mahasiswa,
                'nomor_identitas' => $student->nim,
            ]);
        });

        return redirect()->route('course-removals.index', [
            'search' => $search,
            'student' => $student->id_mahasiswa,
        ])->with('status', 'Mata kuliah berhasil dihapus dari KRS mahasiswa.');
    }

    private function studentCourses(string $studentId): Collection
    {
        return DB::connection('simak_main')->table('s1m4k_krs as krs')
            ->leftJoin('s1m4k_mastermk as course', 'krs.id_matkul', '=', 'course.id_matkul')
            ->where('krs.id_mahasiswa', $studentId)
            ->orderBy('course.semester')
            ->orderBy('course.nama_mata_kuliah')
            ->get([
                'krs.id_krs', 'krs.id_matkul', 'krs.nilai_huruf', 'krs.nilai_bobot',
                'course.kode_mata_kuliah', 'course.nama_mata_kuliah',
                'course.semester', 'course.sks_mata_kuliah',
            ])
            ->groupBy('id_matkul')
            ->map(function (Collection $records): object {
                $course = $records->sortByDesc(fn (object $row): float => (float) ($row->nilai_bobot ?? 0))->first();
                $course->nilai_bobot = (float) ($course->nilai_bobot ?? 0);
                $course->nilai_huruf = match (number_format($course->nilai_bobot, 2, '.', '')) {
                    '4.00' => 'A',
                    '3.50' => 'AB',
                    '3.00' => 'B',
                    '2.50' => 'BC',
                    '2.00' => 'C',
                    '1.00' => 'D',
                    default => $course->nilai_huruf ?: 'E',
                };
                $course->sks_mata_kuliah = (float) ($course->sks_mata_kuliah ?? 0);
                $course->mutu = $course->nilai_bobot * $course->sks_mata_kuliah;

                return $course;
            })
            ->values();
    }

    private function studentQuery(object $user)
    {
        $query = DB::connection('simak_main')->table('s1m4k_mastermhs as student')
            ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'student.id_fakultas', '=', 'faculty.id_fakultas')
            ->select([
                'student.id_mahasiswa', 'student.nim', 'student.nama_mahasiswa',
                'student.id_prodi', 'program.nama_program_studi', 'faculty.nama_fakultas',
            ])
            ->whereRaw("TRIM(COALESCE(student.id_mahasiswa, '')) <> ''");

        if ($user->role === 'userprodi') {
            abort_unless(filled(data_get($user, 'id_prodi')), 403);
            $query->where('student.id_prodi', data_get($user, 'id_prodi'));
        }

        return $query;
    }

    private function findStudent(object $user, string $studentId): object
    {
        $student = $this->studentQuery($user)->where('student.id_mahasiswa', $studentId)->first();
        abort_if($student === null, 404);

        return $student;
    }

    private function authorizeRole(object $user): void
    {
        abort_unless(in_array($user->role, self::ROLES, true), 403);
    }
}
