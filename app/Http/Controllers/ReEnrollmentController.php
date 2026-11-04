<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Support\AcademicCalendarScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReEnrollmentController extends Controller
{
    private const ROLES = ['admin', 'regulang'];

    public function index(Request $request): View
    {
        $this->authorizeRole($request);

        return $this->page((string) old('npm', $request->session()->pull('re_enrollment_npm', '')));
    }

    public function search(Request $request): View
    {
        $this->authorizeRole($request);
        $validated = $request->validate([
            'npm' => ['required', 'string', 'max:24'],
        ]);

        return $this->page(trim($validated['npm']));
    }

    public function register(Request $request, string $calendar, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeRole($request);
        $validated = $request->validate([
            'id_mahasiswa' => ['required', 'string', 'max:100'],
        ]);
        $connection = DB::connection('simak_main');
        $student = $connection->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', $validated['id_mahasiswa'])
            ->first(['id_mahasiswa', 'nim', 'nama_mahasiswa', 'id_prodi', 'id_fakultas']);
        abort_if($student === null, 404);

        try {
            $calendarRecord = $connection->transaction(function () use ($connection, $student, $calendar, $request, $activityLogger): object {
                $lockedStudent = $connection->table('s1m4k_mastermhs')
                    ->where('id_mahasiswa', $student->id_mahasiswa)
                    ->lockForUpdate()
                    ->first(['id_mahasiswa', 'id_prodi', 'id_fakultas']);
                abort_if($lockedStudent === null, 404);

                $calendarRecord = $connection->table('s1m4k_kalender')
                    ->where('id_kalender', $calendar)
                    ->where('id_prodi', $lockedStudent->id_prodi)
                    ->where('status', 'Y')
                    ->lockForUpdate()
                    ->first(['id_kalender', 'tahun', 'id_prodi', 'id_fakultas']);
                abort_if($calendarRecord === null, 404);

                $alreadyRegistered = $connection->table('s1m4k_registrasiulang')
                    ->where('id_mahasiswa', $lockedStudent->id_mahasiswa)
                    ->where('id_kalender', $calendarRecord->id_kalender)
                    ->exists();
                if ($alreadyRegistered) {
                    throw ValidationException::withMessages([
                        'registration' => 'Mahasiswa sudah terdaftar pada kalender akademik ini.',
                    ]);
                }

                $universityId = $connection->table('s1m4k_masterpt')->value('id_perguruan_tinggi');
                if (! is_string($universityId) || trim($universityId) === '') {
                    throw ValidationException::withMessages([
                        'registration' => 'Identitas perguruan tinggi belum tersedia.',
                    ]);
                }

                $registration = [
                    'id_registrasi' => (string) Str::uuid(),
                    'id_perguruan_tinggi' => $universityId,
                    'id_fakultas' => $lockedStudent->id_fakultas,
                    'id_prodi' => $lockedStudent->id_prodi,
                    'id_mahasiswa' => $lockedStudent->id_mahasiswa,
                    'id_kalender' => $calendarRecord->id_kalender,
                    'tgl_registasi' => now()->toDateString(),
                    'user' => (string) $request->user()->legacy_id,
                ];
                $connection->table('s1m4k_registrasiulang')->insert($registration);
                $uktQuery = $connection->table('s1m4k_pembayaranukt')
                    ->where('id_mahasiswa', $lockedStudent->id_mahasiswa)
                    ->where('tahun', $calendarRecord->tahun);
                $uktBefore = $uktQuery->get(['id', 'lunas', 'petugas']);
                $uktQuery->update([
                    'lunas' => 1,
                    'petugas' => (string) $request->user()->legacy_id,
                ]);
                $uktAfter = $connection->table('s1m4k_pembayaranukt')
                    ->whereIn('id', $uktBefore->pluck('id'))
                    ->get(['id', 'lunas', 'petugas'])
                    ->keyBy('id');
                $activityLogger->record($request->user(), 'Registrasi Ulang', 'Registrasikan mahasiswa', [
                    'registrasi_baru' => $registration,
                    'pembayaran_ukt' => $uktBefore->map(fn (object $payment): array => [
                        'id' => $payment->id,
                        'sebelum' => ['lunas' => $payment->lunas, 'petugas' => $payment->petugas],
                        'sesudah' => [
                            'lunas' => $uktAfter->get($payment->id)?->lunas,
                            'petugas' => $uktAfter->get($payment->id)?->petugas,
                        ],
                    ])->all(),
                ], (string) $lockedStudent->id_mahasiswa, [
                    'jenis' => 'Mahasiswa',
                    'nama' => (string) $student->nama_mahasiswa,
                    'nomor_identitas' => (string) $student->nim,
                ]);

                return $calendarRecord;
            });
        } catch (ValidationException $exception) {
            return $this->redirectToStudent($student->nim)
                ->withErrors($exception->errors());
        }

        Log::info('SIMAK re-enrollment completed.', [
            'actor_id' => (string) $request->user()->legacy_id,
            'student_id' => (string) $student->id_mahasiswa,
            'calendar_id' => (string) $calendarRecord->id_kalender,
        ]);

        return $this->redirectToStudent($student->nim)
            ->with('status', 'Registrasi ulang berhasil disimpan.');
    }

    public function cancel(Request $request, string $calendar, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeRole($request);
        $validated = $request->validate([
            'id_mahasiswa' => ['required', 'string', 'max:100'],
        ]);
        $connection = DB::connection('simak_main');
        $student = $connection->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', $validated['id_mahasiswa'])
            ->first(['id_mahasiswa', 'nim', 'nama_mahasiswa', 'id_prodi']);
        abort_if($student === null, 404);

        try {
            $calendarRecord = $connection->transaction(function () use ($connection, $student, $calendar, $request, $activityLogger): object {
                $lockedStudent = $connection->table('s1m4k_mastermhs')
                    ->where('id_mahasiswa', $student->id_mahasiswa)
                    ->lockForUpdate()
                    ->first(['id_mahasiswa', 'id_prodi']);
                abort_if($lockedStudent === null, 404);

                $calendarRecord = $connection->table('s1m4k_kalender')
                    ->where('id_kalender', $calendar)
                    ->where('id_prodi', $lockedStudent->id_prodi)
                    ->first(['id_kalender', 'tahun']);
                abort_if($calendarRecord === null, 404);

                $registrationQuery = $connection->table('s1m4k_registrasiulang')
                    ->where('id_mahasiswa', $lockedStudent->id_mahasiswa)
                    ->where('id_kalender', $calendarRecord->id_kalender);
                $registration = $registrationQuery->lockForUpdate()->first();
                abort_if($registration === null, 404);

                $hasCourseRegistration = $connection->table('s1m4k_krs')
                    ->where('id_mahasiswa', $lockedStudent->id_mahasiswa)
                    ->where('id_kalender', $calendarRecord->id_kalender)
                    ->exists();
                if ($hasCourseRegistration) {
                    throw ValidationException::withMessages([
                        'registration' => 'Registrasi tidak dapat dibatalkan karena mahasiswa sudah mengambil mata kuliah pada semester ini.',
                    ]);
                }

                $uktQuery = $connection->table('s1m4k_pembayaranukt')
                    ->where('id_mahasiswa', $lockedStudent->id_mahasiswa)
                    ->where('tahun', $calendarRecord->tahun);
                $uktBefore = $uktQuery->get(['id', 'lunas', 'petugas']);
                $registrationQuery
                    ->where('id_mahasiswa', $lockedStudent->id_mahasiswa)
                    ->where('id_kalender', $calendarRecord->id_kalender)
                    ->delete();
                $uktQuery->update([
                    'lunas' => 0,
                    'petugas' => (string) $request->user()->legacy_id,
                ]);
                $uktAfter = $connection->table('s1m4k_pembayaranukt')
                    ->whereIn('id', $uktBefore->pluck('id'))
                    ->get(['id', 'lunas', 'petugas'])
                    ->keyBy('id');
                $activityLogger->record($request->user(), 'Registrasi Ulang', 'Batalkan registrasi mahasiswa', [
                    'registrasi_dihapus' => (array) $registration,
                    'pembayaran_ukt' => $uktBefore->map(fn (object $payment): array => [
                        'id' => $payment->id,
                        'sebelum' => ['lunas' => $payment->lunas, 'petugas' => $payment->petugas],
                        'sesudah' => [
                            'lunas' => $uktAfter->get($payment->id)?->lunas,
                            'petugas' => $uktAfter->get($payment->id)?->petugas,
                        ],
                    ])->all(),
                ], (string) $lockedStudent->id_mahasiswa, [
                    'jenis' => 'Mahasiswa',
                    'nama' => (string) $student->nama_mahasiswa,
                    'nomor_identitas' => (string) $student->nim,
                ]);

                return $calendarRecord;
            });
        } catch (ValidationException $exception) {
            return $this->redirectToStudent($student->nim)
                ->withErrors($exception->errors());
        }

        Log::info('SIMAK re-enrollment cancelled.', [
            'actor_id' => (string) $request->user()->legacy_id,
            'student_id' => (string) $student->id_mahasiswa,
            'calendar_id' => (string) $calendarRecord->id_kalender,
        ]);

        return $this->redirectToStudent($student->nim)
            ->with('status', 'Registrasi ulang berhasil dibatalkan.');
    }

    private function page(string $npm): View
    {
        $student = null;
        $calendars = collect();
        $notFound = false;

        if ($npm !== '') {
            $connection = DB::connection('simak_main');
            $student = $connection->table('s1m4k_mastermhs as student')
                ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
                ->where('student.nim', $npm)
                ->first([
                    'student.id_mahasiswa', 'student.nim', 'student.nama_mahasiswa',
                    'student.id_prodi', 'student.id_fakultas', 'program.nama_program_studi',
                ]);

            if ($student === null) {
                $notFound = true;
            } else {
                $calendars = AcademicCalendarScope::excludeShortSemesters(
                    $connection->table('s1m4k_kalender as calendar'),
                    'calendar.tahun'
                )
                    ->leftJoin('s1m4k_masterprodi as program', 'calendar.id_prodi', '=', 'program.id_prodi')
                    ->where('calendar.id_prodi', $student->id_prodi)
                    ->where('calendar.status', 'Y')
                    ->orderByDesc('calendar.tahun')
                    ->get([
                        'calendar.id_kalender', 'calendar.tahun', 'calendar.namatahun',
                        'calendar.id_prodi', 'calendar.id_fakultas', 'program.nama_program_studi',
                    ]);

                $registrations = $connection->table('s1m4k_registrasiulang')
                    ->where('id_mahasiswa', $student->id_mahasiswa)
                    ->whereIn('id_kalender', $calendars->pluck('id_kalender'))
                    ->get(['id_registrasi', 'id_kalender', 'user'])
                    ->keyBy('id_kalender');
                $operatorIds = $registrations->pluck('user')
                    ->filter(static fn (mixed $operator): bool => is_string($operator) && $operator !== '' && $operator !== 'Keuangan')
                    ->unique()->values();
                $operators = $operatorIds->isEmpty()
                    ? collect()
                    : $connection->table('s1m4k_admin')->whereIn('id_admin', $operatorIds)->pluck('nama_lengkap', 'id_admin');

                $calendars = $calendars->map(function (object $calendar) use ($registrations, $operators): object {
                    $registration = $registrations->get($calendar->id_kalender);
                    $calendar->registered = $registration !== null;
                    $calendar->registered_by = $registration === null
                        ? null
                        : (($registration->user === 'Keuangan')
                            ? 'Keuangan'
                            : ($operators->get($registration->user) ?: $registration->user));

                    return $calendar;
                });
            }
        }

        return view('re-enrollment.index', compact('npm', 'student', 'calendars', 'notFound'));
    }

    private function redirectToStudent(string $npm): RedirectResponse
    {
        return redirect()->route('re-enrollment.index')
            ->with('re_enrollment_npm', $npm);
    }

    private function authorizeRole(Request $request): void
    {
        abort_unless(in_array($request->user()->role, self::ROLES, true), 403);
    }
}
