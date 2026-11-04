<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CourseGradeTest extends TestCase
{
    private bool $emptyFeederEvaluations = false;

    private bool $rejectSpFeederUpdate = false;

    private array $evaluationWeights = [100, 100, 0, 0, 0, 0];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.simak_main' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        DB::purge('simak_main');
        $this->createSchema();
        $this->seedData();
        Http::fake(function ($request) {
            if (($request['act'] ?? '') === 'GetToken') {
                return Http::response(['data' => ['token' => 'fake-token']]);
            }
            if (($request['act'] ?? '') === 'GetListRencanaEvaluasi') {
                if ($this->emptyFeederEvaluations) {
                    return Http::response(['error_code' => '0', 'data' => []]);
                }

                return Http::response(['error_code' => '0', 'data' => [
                    ['jenis_evaluasi' => 'Aktivitas Partisipatif', 'nama_evaluasi' => 'AKTIVITAS', 'bobot_evaluasi' => $this->evaluationWeights[0]],
                    ['jenis_evaluasi' => 'Hasil Proyek', 'nama_evaluasi' => 'PROYEK', 'bobot_evaluasi' => $this->evaluationWeights[1]],
                    ['jenis_evaluasi' => 'Kognitif/ Pengetahuan', 'nama_evaluasi' => 'QIZ', 'bobot_evaluasi' => $this->evaluationWeights[2]],
                    ['jenis_evaluasi' => 'Kognitif/ Pengetahuan', 'nama_evaluasi' => 'TGS', 'bobot_evaluasi' => $this->evaluationWeights[3]],
                    ['jenis_evaluasi' => 'Kognitif/ Pengetahuan', 'nama_evaluasi' => 'UTS', 'bobot_evaluasi' => $this->evaluationWeights[4]],
                    ['jenis_evaluasi' => 'Kognitif/ Pengetahuan', 'nama_evaluasi' => 'UAS', 'bobot_evaluasi' => $this->evaluationWeights[5]],
                ]]);
            }
            if (($request['act'] ?? '') === 'UpdateNilaiPerkuliahanKelas' && $this->rejectSpFeederUpdate) {
                return Http::response([
                    'error_code' => '1217',
                    'error_desc' => 'Nilai mahasiswa tidak dapat diperbarui.',
                ]);
            }

            return Http::response(['error_code' => '0', 'data' => []]);
        });
    }

    public function test_admin_is_read_only_and_userprodi_saves_component_values_with_rounded_totals(): void
    {
        $this->actingAs($this->user('admin', 'admin-1'));
        $this->get(route('grade-management.index', ['program' => 'program-1']))
            ->assertOk()->assertSee('Mata Kuliah Satu')->assertSee('Belum Diinput')
            ->assertSee('<span class="grade-status-chip is-pending">Belum Diinput</span>', false)
            ->assertSee('<th class="no-export">Proses</th>', false)
            ->assertSee('aria-label="Cetak nilai Mata Kuliah Satu"', false)
            ->assertDontSee('aria-label="Input nilai Mata Kuliah Satu"', false)
            ->assertDontSee('aria-label="Buka kunci input nilai dosen untuk Mata Kuliah Satu"', false);
        $this->get(route('grade-management.edit', ['schedule' => 'schedule-1', 'program' => 'program-1']))
            ->assertForbidden();
        $this->put(route('grade-management.update', 'schedule-1'), [
            'program' => 'program-1',
            'grades' => [
                'krs-1' => ['nilai_partisipatif' => '79,5'],
                'krs-2' => ['nilai_partisipatif' => '39,5'],
            ],
        ])->assertForbidden();

        DB::connection('simak_main')->table('s1m4k_krs')->where('id_krs', 'krs-1')->update(['input_nilai' => 'Y']);
        $this->post(route('grade-management.unlock', ['schedule' => 'schedule-1']), [
            'program' => 'program-1',
        ])->assertForbidden();
        $this->assertSame('Y', DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_krs', 'krs-1')->value('input_nilai'));

        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));
        $this->put(route('grade-management.update', 'schedule-1'), [
            'program' => 'program-1',
            'grades' => [
                'krs-1' => ['nilai_partisipatif' => '79,5'],
                'krs-2' => ['nilai_partisipatif' => '39,5'],
            ],
        ])->assertRedirect(route('grade-management.index', ['program' => 'program-1']))
            ->assertSessionHas('status');

        $grade = DB::connection('simak_main')->table('s1m4k_krs')->where('id_krs', 'krs-1')->first();
        $this->assertSame('80', $grade->nilai_angka);
        $this->assertSame('A', $grade->nilai_huruf);
        $this->assertSame('4.00', $grade->nilai_bobot);
        $this->assertSame('79.50', $grade->nilai_partisipatif);
        $this->assertSame('Y', $grade->input_nilai);
        $this->assertSame('operator-1', $grade->user);
        $this->assertSame(2, DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'Nilai Mata Kuliah')->count());

        $this->put(route('grade-management.update', 'schedule-2'), [
            'program' => 'program-2', 'grades' => ['krs-3' => ['nilai_partisipatif' => '90']],
        ])->assertNotFound();
        Http::assertSent(fn ($request): bool => ($request['act'] ?? '') === 'GetListRencanaEvaluasi');
    }

    public function test_initial_grade_entry_notifies_students_but_edits_do_not_include_grade_values(): void
    {
        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));
        $grades = [
            'krs-1' => ['nilai_partisipatif' => '79,5'],
            'krs-2' => ['nilai_partisipatif' => '39,5'],
        ];
        $this->put(route('grade-management.update', 'schedule-1'), [
            'program' => 'program-1',
            'grades' => $grades,
        ])->assertRedirect();

        $notifications = DB::connection('simak_main')->table('eskripsi_notifikasi')->get();
        $this->assertCount(2, $notifications);
        $this->assertEqualsCanonicalizing(['student-1', 'student-3'], $notifications->pluck('recipient_id')->all());
        foreach ($notifications as $notification) {
            $this->assertSame('course-grade-entered', $notification->category);
            $this->assertStringNotContainsString('79.5', $notification->message);
            $this->assertStringNotContainsString('39.5', $notification->message);
        }

        $this->put(route('grade-management.update', 'schedule-1'), [
            'program' => 'program-1',
            'grades' => [
                'krs-1' => ['nilai_partisipatif' => '80'],
                'krs-2' => ['nilai_partisipatif' => '40'],
            ],
        ])->assertRedirect();
        $this->assertSame(2, DB::connection('simak_main')->table('eskripsi_notifikasi')->count());
    }

    public function test_edit_page_requests_custom_save_confirmation_for_userprodi(): void
    {
        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));

        $this->get(route('grade-management.edit', ['schedule' => 'schedule-1', 'program' => 'program-1']))
            ->assertOk()
            ->assertSee('data-can-edit="true"', false)
            ->assertSee('data-confirm-save="true"', false)
            ->assertSee('data-confirm-once="false"', false);
    }

    public function test_userprodi_can_open_class_unlock_confirmation_without_feeder_requests(): void
    {
        DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_krs', 'krs-1')->update(['input_nilai' => 'Y']);
        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));

        $response = $this->get(route('grade-management.edit', [
            'schedule' => 'schedule-1', 'program' => 'program-1', 'focus' => 'unlock',
        ]))->assertOk()
            ->assertSee('data-can-edit="false"', false)
            ->assertSee('data-unlock-only="true"', false)
            ->assertSee('data-grade-rows', false)
            ->assertSee('data-class-label="Mata Kuliah Satu', false)
            ->assertDontSee('<div class="grade-grid-shell"', false)
            ->assertDontSee('data-component-weights', false)
            ->assertDontSee('grade-unlock-student')
            ->assertDontSee('Pilih mahasiswa')
            ->assertDontSee('nilai_partisipatif');
        preg_match('/data-grade-rows>(.*?)<\\/script>/s', $response->getContent(), $rowScript);
        $unlockRows = json_decode($rowScript[1] ?? '', true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([], $unlockRows);
        $this->assertStringContainsString('data-unlock-url=', $response->getContent());
        Http::assertNothingSent();

        $this->post(route('grade-management.unlock', [
            'schedule' => 'schedule-1',
        ]), ['program' => 'program-1'])
            ->assertRedirect(route('grade-management.index', ['program' => 'program-1']))
            ->assertSessionHas('status', 'Input nilai untuk 1 mahasiswa pada kelas berhasil dibuka kembali.');
        Http::assertNothingSent();
        $this->assertSame('N', DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_krs', 'krs-1')->value('input_nilai'));
    }

    public function test_admin_grade_list_uses_paginated_table_and_green_status_after_values_are_entered(): void
    {
        DB::connection('simak_main')->table('s1m4k_krs')->where('id_krs', 'krs-1')->update(['input_nilai' => 'Y']);
        $this->actingAs($this->user('admin', 'admin-1'));

        $this->get(route('grade-management.index', ['program' => 'program-1']))
            ->assertOk()
            ->assertSee('data-datatable="course-grades"', false)
            ->assertSee('<th>Status Input Nilai</th>', false)
            ->assertSee('<th class="no-export">Proses</th>', false)
            ->assertSee('aria-label="Cetak nilai Mata Kuliah Satu"', false)
            ->assertDontSee('aria-label="Input nilai Mata Kuliah Satu"', false)
            ->assertDontSee('aria-label="Buka kunci input nilai dosen untuk Mata Kuliah Satu"', false)
            ->assertSee('<span class="grade-status-chip is-partial">Sebagian Diinput</span>', false)
            ->assertSee('1 dari 2 mahasiswa');

        DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_jadwal', 'schedule-1')->update(['input_nilai' => 'Y']);
        $this->get(route('grade-management.index', ['program' => 'program-1']))
            ->assertOk()
            ->assertSee('<span class="grade-status-chip is-entered">Telah Diinput</span>', false);
    }

    public function test_userprodi_grade_list_has_process_column_with_input_print_and_unlock_actions(): void
    {
        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));
        DB::connection('simak_main')->table('s1m4k_krs')->where('id_krs', 'krs-1')->update(['input_nilai' => 'Y']);

        $response = $this->get(route('grade-management.index'))
            ->assertOk()
            ->assertSee('<th>Status Input Nilai</th>', false)
            ->assertSee('<th class="no-export" colspan="3">Proses</th>', false)
            ->assertSee('aria-label="Input nilai Mata Kuliah Satu"', false)
            ->assertSee('aria-label="Cetak nilai Mata Kuliah Satu"', false)
            ->assertSee('aria-label="Buka kunci input nilai dosen untuk Mata Kuliah Satu"', false);
        $this->assertSame(3, substr_count($response->getContent(), 'class="grade-process grade-action-cell"'));
    }

    public function test_nilaimk2_rejects_component_total_over_100_without_partial_writes(): void
    {
        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));
        $this->put(route('grade-management.update', 'schedule-1'), [
            'program' => 'program-1',
            'grades' => [
                'krs-1' => ['nilai_partisipatif' => '80', 'nilai_proyek' => '30'],
                'krs-2' => ['nilai_partisipatif' => '50'],
            ],
        ])->assertSessionHasErrors('grades.krs-1');
        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_jadwal', 'schedule-1')->where('input_nilai', 'Y')->count());
    }

    public function test_lecturer_can_submit_once_during_window_and_increments_akm_update_counter(): void
    {
        DB::connection('simak_main')->table('s1m4k_akm')->insert([
            ['id_ipmhs' => 'akm-1', 'id_mahasiswa' => 'student-1', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'update_akm' => 0],
            ['id_ipmhs' => 'akm-3', 'id_mahasiswa' => 'student-3', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'update_akm' => 0],
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->where('id_krs', 'krs-1')
            ->update(['input_nilai' => 'Y']);
        $this->actingAs($this->user('dosen', 'lecturer-1', ['login' => 'NIDN-1']));
        $this->get(route('grade-management.edit', ['schedule' => 'schedule-1', 'program' => 'program-1']))
            ->assertOk()->assertSee('Mahasiswa Satu')
            ->assertSee('data-grade-spreadsheet', false)
            ->assertSee('data-grade-rows', false)
            ->assertSee('data-enabled-fields', false)
            ->assertSee('data-enabled-fields>["nilai_partisipatif","nilai_proyek"]</script>', false);
        $payload = ['program' => 'program-1', 'grades' => [
            'krs-1' => ['nilai_partisipatif' => '87.4'],
            'krs-2' => ['nilai_partisipatif' => '75'],
        ]];
        $this->put(route('grade-management.update', 'schedule-1'), $payload)->assertRedirect();
        $this->assertSame(1, DB::connection('simak_main')->table('s1m4k_akm')
            ->where('id_mahasiswa', 'student-1')->value('update_akm'));
        $this->assertSame(1, DB::connection('simak_main')->table('s1m4k_akm')
            ->where('id_mahasiswa', 'student-3')->value('update_akm'));
        $this->put(route('grade-management.update', 'schedule-1'), $payload)->assertStatus(422);
    }

    public function test_teacher_scope_and_calendar_grade_window_are_enforced_server_side(): void
    {
        $this->actingAs($this->user('dosen', 'lecturer-1', ['login' => 'NIDN-1']));
        $gradeList = $this->get(route('grade-management.index', ['program' => 'program-1']))
            ->assertOk()
            ->assertSee('<th class="no-export">Proses</th>', false)
            ->assertSee('aria-label="Input atau lihat nilai Mata Kuliah Satu"', false)
            ->assertSee('aria-label="Cetak daftar nilai Mata Kuliah Satu"', false);
        $gradeHtml = (string) $gradeList->getContent();
        $coursePosition = strpos($gradeHtml, 'Mata Kuliah Satu');
        $courseCellEnd = strpos($gradeHtml, '</td>', $coursePosition);
        $entryActionPosition = strpos($gradeHtml, 'aria-label="Input atau lihat nilai Mata Kuliah Satu"');
        $this->assertNotFalse($coursePosition);
        $this->assertNotFalse($courseCellEnd);
        $this->assertNotFalse($entryActionPosition);
        $this->assertGreaterThan($courseCellEnd, $entryActionPosition);

        $this->get(route('grade-management.edit', ['schedule' => 'schedule-2', 'program' => 'program-2']))
            ->assertNotFound();
        $this->put(route('grade-management.update', 'schedule-2'), [
            'program' => 'program-1', 'grades' => [
                'krs-1' => ['nilai_partisipatif' => '85'],
                'krs-2' => ['nilai_partisipatif' => '85'],
            ],
        ])->assertNotFound();

        DB::connection('simak_main')->table('s1m4k_kalender')->where('id_kalender', 'calendar-1')
            ->update([
                'tgl_mulai_isinilai' => now()->addDay()->toDateString(),
                'tgl_akhir_isinilai' => now()->addWeek()->toDateString(),
            ]);
        $this->actingAs($this->user('asdos', 'assistant-1', ['login' => 'ASDOS-1']));
        $this->put(route('grade-management.update', 'schedule-1'), [
            'program' => 'program-1', 'grades' => [
                'krs-1' => ['nilai_partisipatif' => '85'],
                'krs-2' => ['nilai_partisipatif' => '85'],
            ],
        ])->assertStatus(422);
        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_jadwal', 'schedule-1')->where('input_nilai', 'Y')->count());
    }

    public function test_components_with_nonpositive_evaluation_weight_cannot_be_submitted(): void
    {
        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));
        $this->put(route('grade-management.update', 'schedule-1'), [
            'program' => 'program-1',
            'grades' => [
                'krs-1' => ['nilai_partisipatif' => '70', 'nilai_quiz' => '20'],
                'krs-2' => ['nilai_partisipatif' => '60'],
            ],
        ])->assertSessionHasErrors('grades.krs-1.nilai_quiz');
        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_jadwal', 'schedule-1')->where('input_nilai', 'Y')->count());
    }

    public function test_component_scores_are_weighted_before_total_and_grade_conversion(): void
    {
        $this->evaluationWeights = [30, 40, 0, 0, 0, 0];
        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));
        $this->put(route('grade-management.update', 'schedule-1'), [
            'program' => 'program-1',
            'grades' => [
                'krs-1' => ['nilai_partisipatif' => '50', 'nilai_proyek' => '50'],
                'krs-2' => ['nilai_partisipatif' => '40', 'nilai_proyek' => '40'],
            ],
        ])->assertRedirect()->assertSessionHas('status');

        $grade = DB::connection('simak_main')->table('s1m4k_krs')->where('id_krs', 'krs-1')->first();
        $this->assertSame('35', $grade->nilai_angka);
        $this->assertSame('E', $grade->nilai_huruf);
        $this->assertSame('0.00', $grade->nilai_bobot);
        $this->assertSame('50.00', $grade->nilai_partisipatif);
        $this->assertSame('50.00', $grade->nilai_proyek);
    }

    public function test_component_values_are_restored_after_total_validation_fails(): void
    {
        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));
        $editUrl = route('grade-management.edit', ['schedule' => 'schedule-1', 'program' => 'program-1']);
        $this->from($editUrl)->put(route('grade-management.update', 'schedule-1'), [
            'program' => 'program-1',
            'grades' => [
                'krs-1' => ['nilai_partisipatif' => '60', 'nilai_proyek' => '50'],
                'krs-2' => ['nilai_partisipatif' => '60'],
            ],
        ])->assertRedirect($editUrl)->assertSessionHasErrors('grades.krs-1');

        $this->get($editUrl)
            ->assertOk()
            ->assertSee('"nilai_partisipatif":"60"', false)
            ->assertSee('"nilai_proyek":"50"', false);
    }

    public function test_usertu_is_read_only_and_managers_unlock_all_locked_grades_for_the_class(): void
    {
        DB::connection('simak_main')->table('s1m4k_krs')
            ->whereIn('id_krs', ['krs-1', 'krs-2'])->update(['input_nilai' => 'Y']);
        $this->actingAs($this->user('usertu', 'operator-tu', ['id_prodi' => 'program-1']));
        $this->get(route('grade-management.index'))
            ->assertOk()
            ->assertSee('Mata Kuliah Satu')
            ->assertSee('<th class="no-export">Proses</th>', false)
            ->assertSee('aria-label="Cetak nilai Mata Kuliah Satu"', false)
            ->assertDontSee('aria-label="Input nilai Mata Kuliah Satu"', false)
            ->assertDontSee('aria-label="Buka kunci input nilai dosen untuk Mata Kuliah Satu"', false);
        $this->get(route('grade-management.edit', ['schedule' => 'schedule-1', 'program' => 'program-1']))
            ->assertForbidden();
        $this->put(route('grade-management.update', 'schedule-1'), [
            'program' => 'program-1', 'grades' => [
                'krs-1' => ['nilai_partisipatif' => '85'],
                'krs-2' => ['nilai_partisipatif' => '85'],
            ],
        ])->assertForbidden();
        $this->post(route('grade-management.unlock', ['schedule' => 'schedule-1']), [
            'program' => 'program-1',
        ])->assertForbidden();

        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));
        $this->post(route('grade-management.unlock', ['schedule' => 'schedule-1']), [
            'program' => 'program-1',
        ])->assertRedirect(route('grade-management.index', ['program' => 'program-1']))
            ->assertSessionHas('status', 'Input nilai untuk 2 mahasiswa pada kelas berhasil dibuka kembali.');
        $this->assertSame('N', DB::connection('simak_main')->table('s1m4k_krs')->where('id_krs', 'krs-1')->value('input_nilai'));
        $this->assertSame('N', DB::connection('simak_main')->table('s1m4k_krs')->where('id_krs', 'krs-2')->value('input_nilai'));
        $this->post(route('grade-management.unlock', ['schedule' => 'schedule-2']), [
            'program' => 'program-2',
        ])->assertNotFound();
    }

    public function test_unlocking_a_course_class_does_not_unlock_parallel_class_or_other_course(): void
    {
        $connection = DB::connection('simak_main');
        $emptySlots = array_fill_keys(array_map(static fn (int $slot): string => 'dosen'.$slot, range(1, 9)), null);
        $connection->table('s1m4k_jadwal')->insert([
            [
                'id_jadwal' => 'schedule-1-parallel',
                'id_prodi' => 'program-1',
                'id_kalender' => 'calendar-1',
                'id_matkul' => 'course-1',
                'nama_kelas' => 'Kelas Paralel',
                ...$emptySlots,
            ],
            [
                'id_jadwal' => 'schedule-1-other-course',
                'id_prodi' => 'program-1',
                'id_kalender' => 'calendar-1',
                'id_matkul' => 'course-2',
                'nama_kelas' => 'Kelas Satu',
                ...$emptySlots,
            ],
        ]);
        $connection->table('s1m4k_krs')->insert([
            [
                'id_krs' => 'krs-parallel',
                'id_mahasiswa' => 'student-1',
                'id_jadwal' => 'schedule-1-parallel',
                'id_kalender' => 'calendar-1',
                'id_matkul' => 'course-1',
                'input_nilai' => 'Y',
            ],
            [
                'id_krs' => 'krs-other-course',
                'id_mahasiswa' => 'student-2',
                'id_jadwal' => 'schedule-1-other-course',
                'id_kalender' => 'calendar-1',
                'id_matkul' => 'course-2',
                'input_nilai' => 'Y',
            ],
            [
                'id_krs' => 'krs-mismatched-course',
                'id_mahasiswa' => 'student-2',
                'id_jadwal' => 'schedule-1',
                'id_kalender' => 'calendar-1',
                'id_matkul' => 'course-2',
                'input_nilai' => 'Y',
            ],
        ]);
        $connection->table('s1m4k_krs')->whereIn('id_krs', [
            'krs-1', 'krs-2', 'krs-parallel', 'krs-other-course', 'krs-mismatched-course',
        ])->update(['input_nilai' => 'Y']);
        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));

        $modalResponse = $this->get(route('grade-management.edit', [
            'schedule' => 'schedule-1', 'program' => 'program-1', 'focus' => 'unlock',
        ]))->assertOk()
            ->assertSee('data-unlock-only="true"', false)
            ->assertSee('Mata Kuliah Satu · Kelas Satu')
            ->assertDontSee('grade-unlock-student')
            ->assertDontSee('Pilih mahasiswa')
            ->assertDontSee('nilai_partisipatif');
        preg_match('/data-grade-rows>(.*?)<\\/script>/s', $modalResponse->getContent(), $rowScript);
        $this->assertSame([], json_decode($rowScript[1] ?? '', true, flags: JSON_THROW_ON_ERROR));
        Http::assertNothingSent();

        $this->post(route('grade-management.unlock', ['schedule' => 'schedule-1']), [
            'program' => 'program-1',
        ])->assertRedirect(route('grade-management.index', ['program' => 'program-1']))
            ->assertSessionHas('status', 'Input nilai untuk 2 mahasiswa pada kelas berhasil dibuka kembali.');

        $this->assertSame([
            'krs-1' => 'N',
            'krs-2' => 'N',
            'krs-mismatched-course' => 'Y',
            'krs-other-course' => 'Y',
            'krs-parallel' => 'Y',
        ], $connection->table('s1m4k_krs')
            ->whereIn('id_krs', ['krs-1', 'krs-2', 'krs-parallel', 'krs-other-course', 'krs-mismatched-course'])
            ->orderBy('id_krs')->pluck('input_nilai', 'id_krs')->all());
    }

    public function test_grade_list_print_is_available_to_allowed_roles_and_scoped(): void
    {
        $this->actingAs($this->user('usertu', 'operator-tu', ['id_prodi' => 'program-1']));
        $this->get(route('grade-management.print', ['schedule' => 'schedule-1', 'program' => 'program-1']))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('grade-management.print', ['schedule' => 'schedule-2', 'program' => 'program-2']))
            ->assertNotFound();
        $this->actingAs($this->user('mahasiswa', 'student-1'));
        $this->get(route('grade-management.index'))->assertForbidden();
    }

    public function test_pimpinan_roles_can_read_with_fakultas_scope_but_cannot_enter_grades(): void
    {
        $this->actingAs($this->user('pimfak', 'faculty-leader', ['id_fakultas' => 'faculty-1']));
        $this->get(route('grade-management.index', ['program' => 'program-1']))
            ->assertOk()->assertSee('Mata Kuliah Satu')->assertDontSee('Input atau lihat nilai');
        $this->get(route('grade-management.index', ['program' => 'program-2']))->assertNotFound();
        $this->get(route('grade-management.print', ['schedule' => 'schedule-2', 'program' => 'program-2']))
            ->assertNotFound();
        $this->get(route('grade-management.edit', ['schedule' => 'schedule-1', 'program' => 'program-1']))
            ->assertForbidden();

        $this->actingAs($this->user('pimuniv', 'university-leader'));
        $this->get(route('grade-management.index', ['program' => 'program-1']))
            ->assertOk()->assertSee('Mata Kuliah Satu');
        $this->put(route('grade-management.update', 'schedule-1'), [
            'program' => 'program-1',
            'grades' => ['krs-1' => ['nilai_partisipatif' => '90']],
        ])->assertForbidden();
    }

    public function test_pimpinan_fakultas_cannot_read_other_faculties_and_feeder_evaluations_are_required(): void
    {
        $this->actingAs($this->user('pimfak', 'faculty-leader', ['id_fakultas' => 'faculty-1']));
        $this->get(route('grade-management.index', ['program' => 'program-1']))
            ->assertOk()->assertSee('Mata Kuliah Satu');
        $this->get(route('grade-management.index', ['program' => 'program-2']))->assertNotFound();

        $this->emptyFeederEvaluations = true;
        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));
        $this->get(route('grade-management.edit', ['schedule' => 'schedule-1', 'program' => 'program-1']))
            ->assertRedirect(route('grade-management.index', ['program' => 'program-1']))
            ->assertSessionHasErrors('feeder');
    }

    public function test_short_semester_grade_list_defaults_to_active_and_keeps_inactive_read_only(): void
    {
        $this->seedShortSemester();
        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));

        $this->get(route('short-semester-grades.index'))
            ->assertOk()
            ->assertSee('Nilai SP')
            ->assertSee('Mata Kuliah SP')
            ->assertSee('SP A')
            ->assertSee(route('short-semester-grades.edit', ['schedule' => 'schedule-sp', 'program' => 'program-1']))
            ->assertDontSee('Mata Kuliah Satu')
            ->assertDontSee('Mata Kuliah Dua');

        DB::connection('simak_main')->table('s1m4k_kalender')
            ->where('id_kalender', 'calendar-sp')->update(['status_sp' => 'N']);
        $this->get(route('short-semester-grades.index'))
            ->assertOk()
            ->assertSee('Mata Kuliah SP')
            ->assertSee('Nonaktif · Baca saja')
            ->assertSee('Baca saja')
            ->assertDontSee('aria-label="Input nilai Mata Kuliah SP"', false);
    }

    public function test_short_semester_grade_list_includes_inactive_calendars_as_read_only(): void
    {
        $this->seedShortSemester();
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            'id_kalender' => 'calendar-sp-inactive',
            'id_prodi' => 'program-1',
            'tahun' => '20243',
            'namatahun' => 'SP 2023 / 2024',
            'periode' => '2023/2024 SP',
            'status' => 'N',
            'status_sp' => 'N',
        ]);
        DB::connection('simak_main')->table('s1m4k_kurikulum')->insert([
            'id_kurikulum' => 'calendar-sp-inactive',
            'id_prodi' => 'program-1',
            'id_semester' => '20243',
            'semester_mulai_berlaku' => '2023/2024 SP',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            'id_matkul' => 'course-sp-inactive',
            'id_prodi' => 'program-1',
            'kode_mata_kuliah' => 'SP000',
            'nama_mata_kuliah' => 'Mata Kuliah SP Nonaktif',
        ]);
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
            'id_jadwal' => 'schedule-sp-inactive',
            'id_kelas_kuliah' => 'feeder-class-sp-inactive',
            'id_prodi' => 'program-1',
            'id_kalender' => 'calendar-sp-inactive',
            'id_matkul' => 'course-sp-inactive',
            'nama_kelas' => 'SP L',
        ]);
        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));

        $this->get(route('short-semester-grades.index'))
            ->assertOk()
            ->assertSee('value="calendar-sp" selected', false)
            ->assertSee('value="calendar-sp-inactive"', false);

        $this->get(route('short-semester-grades.index', [
            'program' => 'program-1',
            'calendar' => 'calendar-sp-inactive',
        ]))->assertOk()
            ->assertSee('Mata Kuliah SP Nonaktif')
            ->assertSee('Nonaktif · Baca saja')
            ->assertSee('Baca saja')
            ->assertDontSee('aria-label="Input nilai Mata Kuliah SP Nonaktif"', false);

        $this->get(route('short-semester-grades.edit', [
            'schedule' => 'schedule-sp-inactive',
            'program' => 'program-1',
        ]))->assertOk()->assertSee('data-can-edit="false"', false);

        $this->putJson(route('short-semester-grades.update', [
            'schedule' => 'schedule-sp-inactive',
            'krs' => 'krs-sp-1',
        ]), [
            'program' => 'program-1',
            'components' => ['nilai_partisipatif' => 80],
        ])->assertForbidden();
    }

    public function test_short_semester_lecturers_only_see_their_assigned_sp_classes(): void
    {
        $this->seedShortSemester();
        $this->actingAs($this->user('dosen', 'lecturer-1', ['login' => 'NIDN-1']));

        $this->get(route('short-semester-grades.index', ['program' => 'program-1']))
            ->assertOk()
            ->assertSee('Mata Kuliah SP')
            ->assertSee('aria-label="Input atau lihat nilai Mata Kuliah SP"', false)
            ->assertDontSee('Mata Kuliah Satu');

        $this->actingAs($this->user('dosen', 'lecturer-2', ['login' => 'NIDN-2']));
        $this->get(route('short-semester-grades.edit', ['schedule' => 'schedule-sp', 'program' => 'program-1']))
            ->assertNotFound();
    }

    public function test_userprodi_can_save_sp_grades_using_the_sp_roster_and_calendar(): void
    {
        $this->seedShortSemester();
        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));

        $this->get(route('short-semester-grades.edit', ['schedule' => 'schedule-sp', 'program' => 'program-1']))
            ->assertOk()
            ->assertSee('Semester Pendek')
            ->assertSee('data-update-url="'.route('short-semester-grades.update', ['schedule' => 'schedule-sp', 'krs' => '__KRS__']).'"', false)
            ->assertSee('data-sequential-save="true"', false);

        $this->putJson(route('short-semester-grades.update', ['schedule' => 'schedule-sp', 'krs' => 'krs-sp-1']), [
            'program' => 'program-1',
            'components' => ['nilai_partisipatif' => '80'],
        ])->assertOk()->assertJsonPath('status', 'updated');

        $this->assertSame('calendar-sp', DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_krs', 'krs-sp-1')->value('id_kalender'));
        $this->assertSame('80', DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_krs', 'krs-sp-1')->value('nilai_angka'));
        $this->assertSame('N', DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_krs', 'krs-sp-2')->value('input_nilai'));
        $this->assertSame(1, DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'Nilai SP')->count());
        Http::assertSent(fn ($request): bool => ($request['act'] ?? '') === 'UpdateNilaiPerkuliahanKelas'
            && ($request['key']['id_kelas_kuliah'] ?? '') === 'feeder-class-sp'
            && ($request['key']['id_registrasi_mahasiswa'] ?? '') === 'reg-student-1'
            && ($request['record']['nilai_angka'] ?? '') === '80.0'
            && ($request['record']['nilai_indeks'] ?? '') === '4.00'
            && ($request['record']['nilai_huruf'] ?? '') === 'A');
    }

    public function test_feeder_rejection_keeps_sp_grade_out_of_local_krs(): void
    {
        $this->seedShortSemester();
        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));
        $this->rejectSpFeederUpdate = true;

        $this->putJson(route('short-semester-grades.update', [
            'schedule' => 'schedule-sp',
            'krs' => 'krs-sp-1',
        ]), [
            'program' => 'program-1',
            'components' => ['nilai_partisipatif' => 80],
        ])->assertUnprocessable()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('message', 'Feeder gagal memperbarui nilai peserta kelas SP. (1217: Nilai mahasiswa tidak dapat diperbarui.)');

        $this->assertDatabaseHas('s1m4k_krs', [
            'id_krs' => 'krs-sp-1',
            'nilai_angka' => null,
            'nilai_huruf' => null,
            'input_nilai' => 'N',
        ], 'simak_main');
        $this->assertDatabaseMissing('log_aktivitas', ['modul' => 'Nilai SP'], 'simak_main');
    }

    private function seedShortSemester(): void
    {
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_kalender')->insert([
            'id_kalender' => 'calendar-sp',
            'id_prodi' => 'program-1',
            'tahun' => '20253',
            'namatahun' => 'SP 2025 / 2026',
            'periode' => '2025/2026 SP',
            'status' => 'N',
            'status_sp' => 'Y',
            'tgl_mulai_isinilai' => now()->subDay()->toDateString(),
            'tgl_akhir_isinilai' => now()->addDay()->toDateString(),
        ]);
        $connection->table('s1m4k_kurikulum')->insert([
            'id_kurikulum' => 'calendar-sp',
            'id_prodi' => 'program-1',
            'id_semester' => '20253',
            'semester_mulai_berlaku' => '2025/2026 SP',
        ]);
        $connection->table('s1m4k_mastermk')->insert([
            'id_matkul' => 'course-sp',
            'id_prodi' => 'program-1',
            'kode_mata_kuliah' => 'SP001',
            'nama_mata_kuliah' => 'Mata Kuliah SP',
        ]);
        $emptySlots = array_fill_keys(array_map(static fn (int $slot): string => 'dosen'.$slot, range(1, 9)), null);
        $connection->table('s1m4k_jadwal')->insert([
            'id_jadwal' => 'schedule-sp',
            'id_kelas_kuliah' => 'feeder-class-sp',
            'id_prodi' => 'program-1',
            'id_kalender' => 'calendar-sp',
            'id_matkul' => 'course-sp',
            'nama_kelas' => 'SP A',
            ...$emptySlots,
            'dosen1' => 'lecturer-1',
        ]);
        $connection->table('s1m4k_jadwal')->insert([
            'id_jadwal' => 'schedule-sp-other-program-course',
            'id_prodi' => 'program-1',
            'id_kalender' => 'calendar-sp',
            'id_matkul' => 'course-2',
            'nama_kelas' => 'SP B',
            ...$emptySlots,
            'dosen1' => 'lecturer-1',
        ]);
        $connection->table('s1m4k_krs')->insert([
            ['id_krs' => 'krs-sp-1', 'id_mahasiswa' => 'student-1', 'id_jadwal' => 'schedule-sp', 'id_kalender' => 'calendar-sp', 'id_matkul' => 'course-sp'],
            ['id_krs' => 'krs-sp-2', 'id_mahasiswa' => 'student-3', 'id_jadwal' => 'schedule-sp', 'id_kalender' => 'calendar-sp', 'id_matkul' => 'course-sp'],
        ]);
    }

    private function createSchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_fakultas')->nullable();
            $table->string('nama_program_studi');
            $table->string('nama_jenjang_pendidikan')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterpt', function (Blueprint $table): void {
            $table->string('id_perguruan_tinggi')->primary();
            $table->string('alamat_feeder');
            $table->string('user_feeder');
            $table->string('password_feeder');
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('id_prodi');
            $table->string('tahun');
            $table->string('namatahun');
            $table->string('periode')->nullable();
            $table->string('status');
            $table->string('status_sp')->nullable();
            $table->date('tgl_mulai_isinilai')->nullable();
            $table->date('tgl_akhir_isinilai')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kurikulum', function (Blueprint $table): void {
            $table->string('id_kurikulum')->primary();
            $table->string('id_prodi');
            $table->string('id_semester');
            $table->string('semester_mulai_berlaku')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table): void {
            $table->string('id_matkul')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('kode_mata_kuliah');
            $table->string('nama_mata_kuliah');
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('nidn');
            $table->string('nama_dosen');
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterasdos', function (Blueprint $table): void {
            $table->string('id_asdos')->primary();
            $table->string('kdasisten');
            $table->string('nama');
            $table->string('gelard')->nullable();
            $table->string('gelarb')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim');
            $table->string('nama_mahasiswa');
            $table->string('id_prodi')->nullable();
            $table->string('id_registrasi_mahasiswa')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_jadwal', function (Blueprint $table): void {
            $table->string('id_jadwal')->primary();
            $table->string('id_kelas_kuliah')->nullable();
            $table->string('id_prodi');
            $table->string('id_kalender');
            $table->string('id_matkul');
            $table->string('nama_kelas');
            for ($slot = 1; $slot <= 9; $slot++) {
                $table->string('dosen'.$slot)->nullable();
            }
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_krs')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_jadwal');
            $table->string('id_kalender');
            $table->string('id_matkul');
            $table->string('nilai_angka')->nullable();
            $table->string('nilai_huruf')->nullable();
            $table->string('nilai_bobot')->nullable();
            $table->string('nilai_partisipatif')->nullable();
            $table->string('nilai_proyek')->nullable();
            $table->string('nilai_quiz')->nullable();
            $table->string('nilai_tugas')->nullable();
            $table->string('nilai_uts')->nullable();
            $table->string('nilai_uas')->nullable();
            $table->string('input_nilai')->default('N');
            $table->string('tgl_update')->nullable();
            $table->string('user')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterbobotnilai', function (Blueprint $table): void {
            $table->string('id_bobot_nilai')->primary();
            $table->string('nilai_huruf');
            $table->string('nilai_indeks');
            $table->string('bobot_minimum');
            $table->string('bobot_maksimum');
        });
        Schema::connection('simak_main')->create('s1m4k_akm', function (Blueprint $table): void {
            $table->string('id_ipmhs')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_prodi');
            $table->string('id_kalender');
            $table->integer('update_akm')->default(0);
        });
        Schema::connection('simak_main')->create('log_aktivitas', function (Blueprint $table): void {
            $table->string('id_log', 36)->primary();
            $table->string('id_user', 100)->nullable();
            $table->string('level_user', 50)->nullable();
            $table->string('nama_user', 255);
            $table->string('modul', 100);
            $table->longText('aktifitas');
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::connection('simak_main')->create('eskripsi_notifikasi', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('recipient_role', 30);
            $table->string('recipient_id', 100);
            $table->string('category', 80);
            $table->string('title', 255);
            $table->text('message');
            $table->string('url', 500);
            $table->boolean('is_read')->default(false);
            $table->dateTime('created_at');
            $table->dateTime('read_at')->nullable();
        });
    }

    private function seedData(): void
    {
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'program-1', 'id_fakultas' => 'faculty-1', 'nama_program_studi' => 'Program Satu', 'nama_jenjang_pendidikan' => 'S1'],
            ['id_prodi' => 'program-2', 'id_fakultas' => 'faculty-2', 'nama_program_studi' => 'Program Dua', 'nama_jenjang_pendidikan' => 'S1'],
        ]);
        $connection->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'pt-1',
            'alamat_feeder' => 'https://feeder.example.test',
            'user_feeder' => 'dummy-user',
            'password_feeder' => $this->encryptedFeederPassword('fake-password'),
        ]);
        $connection->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'calendar-1', 'id_prodi' => 'program-1', 'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y', 'tgl_mulai_isinilai' => now()->subDay()->toDateString(), 'tgl_akhir_isinilai' => now()->addDay()->toDateString()],
            ['id_kalender' => 'calendar-2', 'id_prodi' => 'program-2', 'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y', 'tgl_mulai_isinilai' => now()->subDay()->toDateString(), 'tgl_akhir_isinilai' => now()->addDay()->toDateString()],
        ]);
        $connection->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'course-1', 'id_prodi' => 'program-1', 'kode_mata_kuliah' => 'MK001', 'nama_mata_kuliah' => 'Mata Kuliah Satu'],
            ['id_matkul' => 'course-2', 'id_prodi' => 'program-2', 'kode_mata_kuliah' => 'MK002', 'nama_mata_kuliah' => 'Mata Kuliah Dua'],
        ]);
        $connection->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'lecturer-1', 'nidn' => 'NIDN-1', 'nama_dosen' => 'Dosen Satu'],
            ['id_dosen' => 'lecturer-2', 'nidn' => 'NIDN-2', 'nama_dosen' => 'Dosen Dua'],
        ]);
        $connection->table('s1m4k_masterasdos')->insert([
            ['id_asdos' => 'assistant-1', 'kdasisten' => 'ASDOS-1', 'nama' => 'Asisten Satu'],
        ]);
        $connection->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'nim' => '2025001', 'nama_mahasiswa' => 'Mahasiswa Satu', 'id_prodi' => 'program-1', 'id_registrasi_mahasiswa' => 'reg-student-1'],
            ['id_mahasiswa' => 'student-2', 'nim' => '2025002', 'nama_mahasiswa' => 'Mahasiswa Dua', 'id_prodi' => 'program-2', 'id_registrasi_mahasiswa' => 'reg-student-2'],
            ['id_mahasiswa' => 'student-3', 'nim' => '2025003', 'nama_mahasiswa' => 'Mahasiswa Tiga', 'id_prodi' => 'program-1', 'id_registrasi_mahasiswa' => 'reg-student-3'],
        ]);
        $emptySlots = array_fill_keys(array_map(static fn (int $slot): string => 'dosen'.$slot, range(1, 9)), null);
        $connection->table('s1m4k_jadwal')->insert([
            ['id_jadwal' => 'schedule-1', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-1', 'nama_kelas' => 'Kelas Satu', ...$emptySlots, 'dosen1' => 'lecturer-1', 'dosen2' => 'assistant-1'],
            ['id_jadwal' => 'schedule-2', 'id_prodi' => 'program-2', 'id_kalender' => 'calendar-2', 'id_matkul' => 'course-2', 'nama_kelas' => 'Kelas Dua', ...$emptySlots, 'dosen1' => 'lecturer-2'],
        ]);
        $connection->table('s1m4k_krs')->insert([
            ['id_krs' => 'krs-1', 'id_mahasiswa' => 'student-1', 'id_jadwal' => 'schedule-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-1'],
            ['id_krs' => 'krs-2', 'id_mahasiswa' => 'student-3', 'id_jadwal' => 'schedule-1', 'id_kalender' => 'calendar-1', 'id_matkul' => 'course-1'],
            ['id_krs' => 'krs-3', 'id_mahasiswa' => 'student-2', 'id_jadwal' => 'schedule-2', 'id_kalender' => 'calendar-2', 'id_matkul' => 'course-2'],
        ]);
        $connection->table('s1m4k_masterbobotnilai')->insert([
            ['id_bobot_nilai' => 'grade-a', 'nilai_huruf' => 'A', 'nilai_indeks' => '4.00', 'bobot_minimum' => '80', 'bobot_maksimum' => '100'],
            ['id_bobot_nilai' => 'grade-b', 'nilai_huruf' => 'B', 'nilai_indeks' => '3.00', 'bobot_minimum' => '60', 'bobot_maksimum' => '79'],
            ['id_bobot_nilai' => 'grade-d', 'nilai_huruf' => 'D', 'nilai_indeks' => '1.00', 'bobot_minimum' => '40', 'bobot_maksimum' => '59'],
            ['id_bobot_nilai' => 'grade-e', 'nilai_huruf' => 'E', 'nilai_indeks' => '0.00', 'bobot_minimum' => '0', 'bobot_maksimum' => '39'],
        ]);
    }

    private function user(string $role, string $id, array $extra = []): GenericUser
    {
        return new GenericUser([
            'id' => $id, 'legacy_id' => $id, 'role' => $role,
            'login' => 'admin-login', 'name' => 'Pengguna Uji', ...$extra,
        ]);
    }

    private function encryptedFeederPassword(string $plainText): string
    {
        $key = '979a218e0632df2935317f98d47956c7';
        $encrypted = '';
        for ($index = 0, $length = strlen($plainText); $index < $length; $index++) {
            $keyCharacter = substr($key, ($index % strlen($key)) - 1, 1);
            $encrypted .= chr((ord($plainText[$index]) + ord($keyCharacter)) % 256);
        }

        return urlencode(base64_encode($encrypted));
    }
}
