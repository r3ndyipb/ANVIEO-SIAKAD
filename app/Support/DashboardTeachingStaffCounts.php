<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardTeachingStaffCounts
{
    public static function forPrograms(Collection $programIds, ?Collection $calendarIds = null): array
    {
        $counts = $programIds
            ->mapWithKeys(static fn (mixed $id): array => [(string) $id => [
                'total' => 0,
                'homebase' => 0,
                'non_homebase' => 0,
            ]])
            ->all();

        if ($programIds->isEmpty() || ($calendarIds !== null && $calendarIds->isEmpty())) {
            return $counts;
        }

        $connection = DB::connection('simak_main');
        $scheduleColumns = [
            's1m4k_jadwal.id_jadwal',
            's1m4k_jadwal.id_prodi',
            's1m4k_jadwal.id_kalender',
        ];
        for ($slot = 1; $slot <= 14; $slot++) {
            $scheduleColumns[] = 's1m4k_jadwal.dosen'.$slot;
        }

        $scheduleQuery = $connection->table('s1m4k_jadwal')
            ->leftJoin('s1m4k_kalender as calendar', 'calendar.id_kalender', '=', 's1m4k_jadwal.id_kalender')
            ->select($scheduleColumns)
            ->addSelect('calendar.tahun', 'calendar.namatahun')
            ->whereIn('s1m4k_jadwal.id_prodi', $programIds->all());
        if ($calendarIds !== null) {
            $scheduleQuery->whereIn('s1m4k_jadwal.id_kalender', $calendarIds->all());
        }

        $latestAssignmentsByProgram = [];
        foreach ($scheduleQuery->cursor() as $schedule) {
            $programId = (string) $schedule->id_prodi;
            foreach (range(1, 14) as $slot) {
                $lecturerId = trim((string) ($schedule->{'dosen'.$slot} ?? ''));
                if ($lecturerId === '') {
                    continue;
                }

                $assignment = (object) [
                    'id_dosen' => $lecturerId,
                    'id_jadwal' => $schedule->id_jadwal,
                    'id_kalender' => $schedule->id_kalender,
                    'tahun' => $schedule->tahun,
                    'namatahun' => $schedule->namatahun,
                ];
                $current = $latestAssignmentsByProgram[$programId][$lecturerId] ?? null;
                if (self::isLaterAssignment($assignment, $current)) {
                    $latestAssignmentsByProgram[$programId][$lecturerId] = $assignment;
                }
            }
        }

        $lecturerIds = collect($latestAssignmentsByProgram)
            ->flatMap(static fn (array $ids): array => array_keys($ids))
            ->unique()
            ->values();
        $peopleById = collect();
        foreach ($lecturerIds->chunk(500) as $chunk) {
            $lecturers = $connection->table('s1m4k_masterdosen')
                ->whereIn('id_dosen', $chunk->all())
                ->get(['id_dosen', 'nidn', 'id_prodi', 'nama_dosen', 'gelar_depan', 'gelar_belakang'])
                ->keyBy(static fn (object $lecturer): string => trim((string) $lecturer->id_dosen));

            foreach ($lecturers as $id => $lecturer) {
                $name = LecturerName::format(
                    $lecturer->nama_dosen ?? null,
                    $lecturer->gelar_depan ?? null,
                    $lecturer->gelar_belakang ?? null,
                );
                $peopleById->put($id, (object) [
                    'nidn' => $lecturer->nidn,
                    'name' => $name,
                    'homebase' => $lecturer->id_prodi,
                ]);
            }
        }

        foreach ($latestAssignmentsByProgram as $programId => $assignments) {
            $rows = collect($assignments)
                ->filter(static fn (object $assignment): bool => $peopleById->has($assignment->id_dosen))
                ->map(static function (object $assignment) use ($peopleById): object {
                    $person = $peopleById->get($assignment->id_dosen);

                    return (object) [
                        'id_dosen' => $assignment->id_dosen,
                        'id_jadwal' => $assignment->id_jadwal,
                        'id_kalender' => $assignment->id_kalender,
                        'tahun' => $assignment->tahun,
                        'namatahun' => $assignment->namatahun,
                        'nidn' => $person->nidn,
                        'nama_dosen' => $person->name,
                        'lecturer_homebase' => $person->homebase,
                    ];
                });
            $uniqueLecturers = self::uniqueLecturers($rows, (string) $programId);

            foreach ($uniqueLecturers as $lecturer) {
                $counts[$programId]['total']++;
                $counts[$programId][$lecturer['homebase'] ? 'homebase' : 'non_homebase']++;
            }
        }

        return $counts;
    }

    public static function detailsForProgram(string $programId, ?Collection $calendarIds = null, ?string $category = null): array
    {
        $lecturers = self::lecturersByProgram(collect([$programId]), $calendarIds)[$programId] ?? [];
        $details = collect($lecturers)
            ->filter(static fn (array $lecturer): bool => $category === null
                || ($category === 'homebase' && $lecturer['homebase'])
                || ($category === 'non_homebase' && ! $lecturer['homebase']))
            ->map(static function (array $lecturer): array {
                $courses = collect($lecturer['courses'])
                    ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all();

                return [
                    'id' => $lecturer['id'],
                    'name' => $lecturer['name'],
                    'latest_academic_year' => $lecturer['latest_academic_year'],
                    'courses' => $courses,
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return [
            'total' => $details->count(),
            'lecturers' => $details->all(),
        ];
    }

    private static function isLaterAssignment(object $candidate, ?object $current): bool
    {
        if ($current === null) {
            return true;
        }

        foreach (['tahun', 'id_kalender', 'id_jadwal', 'id_dosen'] as $field) {
            $comparison = strcmp(trim((string) ($candidate->{$field} ?? '')), trim((string) ($current->{$field} ?? '')));
            if ($comparison !== 0) {
                return $comparison > 0;
            }
        }

        return false;
    }

    private static function lecturersByProgram(Collection $programIds, ?Collection $calendarIds): array
    {
        if ($programIds->isEmpty() || ($calendarIds !== null && $calendarIds->isEmpty())) {
            return [];
        }

        $connection = DB::connection('simak_main');
        $assignments = null;

        for ($slot = 1; $slot <= 14; $slot++) {
            $column = 'dosen'.$slot;
            $query = $connection->table('s1m4k_jadwal')
                ->select('id_prodi', 'id_kalender', 'id_jadwal', 'id_matkul')
                ->selectRaw('TRIM('.$column.') as id_dosen')
                ->whereIn('id_prodi', $programIds->all())
                ->whereNotNull($column)
                ->whereRaw('TRIM('.$column.") <> ''");

            if ($calendarIds !== null) {
                $query->whereIn('id_kalender', $calendarIds->all());
            }

            $assignments = $assignments === null
                ? $query
                : $assignments->unionAll($query);
        }

        $rows = $connection->query()
            ->fromSub($assignments, 'assignment')
            ->join('s1m4k_masterdosen as lecturer', 'lecturer.id_dosen', '=', 'assignment.id_dosen')
            ->leftJoin('s1m4k_kalender as calendar', 'calendar.id_kalender', '=', 'assignment.id_kalender')
            ->leftJoin('s1m4k_mastermk as course', 'course.id_matkul', '=', 'assignment.id_matkul')
            ->select([
                'assignment.id_prodi',
                'assignment.id_dosen',
                'assignment.id_jadwal',
                'assignment.id_kalender',
                'assignment.id_matkul',
                'lecturer.nidn',
                'lecturer.nama_dosen',
                'lecturer.gelar_depan',
                'lecturer.gelar_belakang',
                'lecturer.id_prodi as lecturer_homebase',
                'calendar.tahun',
                'calendar.namatahun',
                'course.nama_mata_kuliah',
            ])
            ->distinct()
            ->get();

        $byProgram = [];
        foreach ($rows->groupBy(static fn (object $row): string => (string) $row->id_prodi) as $programId => $programRows) {
            $byProgram[$programId] = self::uniqueLecturers($programRows, (string) $programId);
        }

        return $byProgram;
    }

    private static function uniqueLecturers(Collection $rows, string $programId): array
    {
        $parents = [];
        $find = static function (string $node) use (&$parents, &$find): string {
            $parents[$node] ??= $node;
            if ($parents[$node] !== $node) {
                $parents[$node] = $find($parents[$node]);
            }

            return $parents[$node];
        };
        $union = static function (string $left, string $right) use (&$parents, $find): void {
            $leftRoot = $find($left);
            $rightRoot = $find($right);
            if ($leftRoot !== $rightRoot) {
                $parents[$rightRoot] = $leftRoot;
            }
        };

        foreach ($rows as $row) {
            $id = trim((string) $row->id_dosen);
            if ($id === '') {
                continue;
            }
            $nidn = trim((string) ($row->nidn ?? ''));
            if ($nidn !== '') {
                $union('id:'.$id, 'nidn:'.$nidn);
            } else {
                $find('id:'.$id);
            }
        }

        $assignmentsByPerson = [];
        foreach ($rows as $row) {
            $id = trim((string) $row->id_dosen);
            if ($id === '') {
                continue;
            }
            $identity = $find('id:'.$id);
            $assignmentsByPerson[$identity][] = $row;
        }

        $people = [];
        foreach ($assignmentsByPerson as $identity => $assignments) {
            usort($assignments, static fn (object $left, object $right): int => self::compareAssignments($right, $left));
            $latestAssignment = $assignments[0];
            $latestYear = trim((string) ($latestAssignment->tahun ?? ''));
            $latestAssignments = collect($assignments)
                ->filter(static fn (object $assignment): bool => trim((string) ($assignment->tahun ?? '')) === $latestYear);
            $name = LecturerName::format(
                $latestAssignment->nama_dosen ?? null,
                $latestAssignment->gelar_depan ?? null,
                $latestAssignment->gelar_belakang ?? null,
            );
            $homebase = trim((string) ($latestAssignment->lecturer_homebase ?? ''));

            $courses = [];
            foreach ($latestAssignments as $assignment) {
                $courseId = trim((string) ($assignment->id_matkul ?? ''));
                if ($courseId !== '') {
                    $courses[$courseId] = [
                        'name' => trim((string) ($assignment->nama_mata_kuliah ?? '')) ?: $courseId,
                        'academic_year' => trim((string) ($assignment->namatahun ?? '')) ?: $latestYear,
                    ];
                }
            }

            $people[$identity] = [
                'id' => trim((string) $latestAssignment->id_dosen),
                'name' => $name !== '' ? $name : null,
                'homebase' => $homebase === $programId,
                'courses' => $courses,
                'latest_academic_year' => $latestYear,
            ];
        }

        return $people;
    }

    private static function compareAssignments(object $left, object $right): int
    {
        foreach (['tahun', 'id_kalender', 'id_jadwal', 'id_dosen'] as $field) {
            $comparison = strcmp(trim((string) ($left->{$field} ?? '')), trim((string) ($right->{$field} ?? '')));
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    }
}
