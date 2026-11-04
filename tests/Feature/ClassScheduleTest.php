<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClassScheduleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.simak_main' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::purge('simak_main');
        $this->createSchema();
        $this->seedRecords();
    }

    public function test_dispatcher_roles_can_view_schedule_and_others_are_denied(): void
    {
        $allowed = [
            'admin' => [],
            'pimuniv' => [],
            'pimfak' => ['id_fakultas' => 'fak-1'],
            'userprodi' => ['id_prodi' => 'prodi-1'],
            'usertu' => ['id_prodi' => 'prodi-1'],
            'dosen' => ['legacy_id' => 'dosen-1'],
            'asdos' => ['legacy_id' => 'asdos-1'],
            'mahasiswa' => ['legacy_id' => 'mhs-1', 'id_prodi' => 'prodi-1'],
        ];

        foreach ($allowed as $role => $attributes) {
            $menuItems = collect(config('simak.navigation.'.$role))
                ->flatMap(static fn (array $group): array => $group['items']);
            $this->assertTrue($menuItems->contains(
                static fn (array $item): bool => ($item['route'] ?? null) === 'class-schedules.index'
            ));
            $this->signIn($role, $attributes);
            $this->get(route('class-schedules.index'))->assertOk()->assertSee('Jadwal Kuliah');
        }

        foreach (['regulang', 'keuangan', 'wisuda'] as $role) {
            $menuItems = collect(config('simak.navigation.'.$role))
                ->flatMap(static fn (array $group): array => $group['items']);
            $this->assertFalse($menuItems->contains(
                static fn (array $item): bool => ($item['route'] ?? null) === 'class-schedules.index'
            ));
            $this->signIn($role);
            $this->get(route('class-schedules.index'))->assertForbidden();
        }
    }

    public function test_schedule_data_is_scoped_to_faculty_program_teacher_and_student(): void
    {
        $this->signIn('admin');
        $this->get(route('class-schedules.index', ['id_prodi' => 'prodi-1', 'id_kalender' => 'kalender-1']))
            ->assertOk()->assertSee('Kelas Satu')->assertDontSee('Kelas Dua');

        $this->signIn('pimfak', ['id_fakultas' => 'fak-1']);
        $this->get(route('class-schedules.index', ['id_prodi' => 'prodi-1']))
            ->assertOk()->assertSee('Kelas Satu');
        $this->get(route('class-schedules.index', ['id_prodi' => 'prodi-2']))->assertNotFound();

        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        $this->get(route('class-schedules.index'))->assertOk()->assertSee('Kelas Satu');
        $this->get(route('class-schedules.index', ['id_prodi' => 'prodi-2']))
            ->assertOk()->assertSee('Kelas Satu')->assertDontSee('Kelas Dua');

        $this->signIn('usertu', ['id_prodi' => 'prodi-2']);
        $this->get(route('class-schedules.index'))->assertOk()->assertSee('Kelas Dua')->assertDontSee('Kelas Satu');

        $this->signIn('dosen', ['legacy_id' => 'dosen-1']);
        $this->get(route('class-schedules.index', ['id_prodi' => 'prodi-1']))
            ->assertOk()->assertSee('Kelas Satu')->assertDontSee('Kelas Dua');
        $this->get(route('class-schedules.index', ['id_prodi' => 'prodi-2']))->assertNotFound();

        $this->signIn('asdos', ['legacy_id' => 'asdos-1']);
        $this->get(route('class-schedules.index', ['id_prodi' => 'prodi-1']))
            ->assertOk()->assertSee('Kelas Satu');

        $this->signIn('mahasiswa', ['legacy_id' => 'mhs-1', 'id_prodi' => 'prodi-1']);
        $this->get(route('class-schedules.index'))
            ->assertOk()->assertSee('Kelas Satu')->assertDontSee('Kelas Dua')
            ->assertDontSee('Tambah Jadwal')->assertDontSee('Dosen Pengampu')->assertDontSee('Kampus Merdeka');
        $this->get(route('class-schedules.index'))
            ->assertSee('name="id_kalender"', false)
            ->assertDontSee('id="schedule-program"', false)
            ->assertDontSee('<label for="schedule-program">Program studi</label>', false);

        $this->signIn('dosen', ['legacy_id' => 'dosen-1']);
        $this->get(route('class-schedules.index', [
            'id_prodi' => 'prodi-1',
            'id_kalender' => 'kalender-lama',
        ]))->assertOk()->assertSee('Kelas Sumber')->assertDontSee('Kelas Satu');
    }

    public function test_teacher_and_student_calendar_filters_only_offer_years_with_their_schedule_data(): void
    {
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-lama',
            'id_jadwal' => 'jadwal-sumber',
            'id_kalender' => 'kalender-lama',
            'id_prodi' => 'prodi-1',
            'id_mahasiswa' => 'mhs-1',
        ]);
        $this->signIn('mahasiswa', ['legacy_id' => 'mhs-1', 'id_prodi' => 'prodi-1']);

        $this->get(route('class-schedules.index'))
            ->assertOk()
            ->assertSee('2026 Ganjil')
            ->assertSee('2025 Genap')
            ->assertSee('name="id_kalender"', false);
        $this->get(route('class-schedules.index', ['id_kalender' => 'kalender-lama']))
            ->assertOk()
            ->assertSee('Kelas Sumber')
            ->assertSee('2025 Genap')
            ->assertDontSee('Kelas Satu');

        $this->signIn('dosen', ['legacy_id' => 'dosen-1']);
        $this->get(route('class-schedules.index', ['id_prodi' => 'prodi-1']))
            ->assertOk()
            ->assertDontSee('2024 Ganjil');
        $this->get(route('class-schedules.index', [
            'id_prodi' => 'prodi-1',
            'id_kalender' => 'kalender-kosong',
        ]))->assertNotFound();
    }

    public function test_admin_and_program_users_see_paginated_schedule_rows_with_academic_year(): void
    {
        $rows = [];
        for ($index = 1; $index <= 30; $index++) {
            $rows[] = $this->scheduleRow(
                'jadwal-extra-'.$index,
                'prodi-1',
                'kalender-1',
                'mk-1',
                'ruang-1',
                'Kelas Tambahan '.$index,
                'dosen-1',
                ''
            );
        }
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert($rows);
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);

        $response = $this->get(route('class-schedules.index'));

        $response->assertOk()
            ->assertSee('Tahun Akademik')
            ->assertSee('2026 Ganjil')
            ->assertSee('Kelas Tambahan 1')
            ->assertDontSee('Kelas Tambahan 30')
            ->assertDontSee('<th>Kampus Merdeka</th>', false)
            ->assertSee('dt-paging-button', false)
            ->assertSee('data-schedule-copy-modal', false)
            ->assertSee('name="source_calendar"', false)
            ->assertSee('name="target_calendar"', false)
            ->assertSee('Mulai Copy');
        preg_match('/<table\b[^>]*data-schedule-list[^>]*>(.*?)<\/table>/s', (string) $response->getContent(), $scheduleTable);
        $this->assertNotEmpty($scheduleTable);
        $this->assertSame(26, substr_count($scheduleTable[1], '<tr'));
    }

    public function test_all_schedule_selects_are_searchable_and_krs_notice_uses_requested_copy(): void
    {
        $this->signIn('admin');
        $adminCreate = $this->get(route('class-schedules.create', ['id_prodi' => 'prodi-1']))
            ->assertOk();
        $this->assertStringNotContainsString('Pilih program studi</button>', (string) $adminCreate->getContent());

        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        $createResponse = $this->get(route('class-schedules.create', ['id_prodi' => 'prodi-1']));
        $createResponse->assertOk();
        $createHtml = (string) $createResponse->getContent();
        $this->assertSame(
            substr_count($createHtml, '<select'),
            substr_count($createHtml, 'data-searchable-select="schedule"')
        );

        $editResponse = $this->get(route('class-schedules.edit', 'jadwal-1'));
        $editResponse->assertOk()->assertSee(
            'Jadwal ini sudah dikontrak matakuliah oleh pada KRS. Mata kuliah, ruang, hari, dan jam dikunci; dosen pengampu, kelas, serta metadata Kampus Merdeka masih bisa diubah.'
        );
        $editHtml = (string) $editResponse->getContent();
        $this->assertSame(
            substr_count($editHtml, '<select'),
            substr_count($editHtml, 'data-searchable-select="schedule"')
        );
    }

    public function test_admin_and_program_users_can_create_copy_and_manage_schedule_with_krs_rules(): void
    {
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        $this->post(route('class-schedules.store'), $this->schedulePayload('KLS B'))
            ->assertRedirect(route('class-schedules.index', ['id_prodi' => 'prodi-1', 'id_kalender' => 'kalender-1']));
        $created = DB::connection('simak_main')->table('s1m4k_jadwal')
            ->where('nama_kelas', 'KLS B')->first();
        $this->assertNotNull($created);
        $this->assertSame('pt-1', $created->id_perguruan_tinggi);
        $this->assertSame('fak-1', $created->id_fakultas);
        $this->assertSame('prodi-1', $created->id_prodi);
        $activity = DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'Jadwal Kuliah')->first();
        $this->assertSame('Pengguna Jadwal', $activity->nama_user);
        $this->assertSame('userprodi', $activity->level_user);
        $this->assertSame('Tambah jadwal', json_decode($activity->aktifitas, true, flags: JSON_THROW_ON_ERROR)['aksi']);
        $this->assertSame(
            'KLS B',
            json_decode($activity->aktifitas, true, flags: JSON_THROW_ON_ERROR)['perubahan']['data_baru']['nama_kelas']
        );

        $this->post(route('class-schedules.store'), $this->schedulePayload('KLS B'))
            ->assertSessionHasErrors('nama_kelas');
        $this->post(route('class-schedules.store'), $this->schedulePayload('KELAS BARU'))
            ->assertSessionHasErrors('nama_kelas');

        $this->signIn('admin');
        DB::connection('simak_main')->table('s1m4k_jadwal')
            ->where('id_jadwal', 'jadwal-sumber')
            ->update(['jml_mahasiswa' => 7, 'input_nilai' => 'Y']);
        $this->get(route('class-schedules.index', [
            'id_prodi' => 'prodi-1',
            'id_kalender' => 'kalender-lama',
        ]))->assertOk()
            ->assertSee('Jadwal hanya dapat dilihat')
            ->assertDontSee('Tambah Jadwal')
            ->assertDontSee('aria-label="Ubah jadwal"', false)
            ->assertDontSee('aria-label="Hapus jadwal"', false);
        $this->post(route('class-schedules.copy'), [
            'id_prodi' => 'prodi-1',
            'source_calendar' => 'kalender-lama',
            'target_calendar' => 'kalender-kosong',
        ])->assertNotFound();
        DB::connection('simak_main')->table('s1m4k_kalender')
            ->where('id_kalender', 'kalender-kosong')
            ->update(['status' => 'Y']);
        $this->post(route('class-schedules.copy'), [
            'id_prodi' => 'prodi-1',
            'source_calendar' => 'kalender-lama',
            'target_calendar' => 'kalender-kosong',
        ])->assertRedirect(route('class-schedules.index', ['id_prodi' => 'prodi-1', 'id_kalender' => 'kalender-kosong']));
        $this->assertDatabaseHas('s1m4k_jadwal', [
            'id_kalender' => 'kalender-kosong',
            'id_prodi' => 'prodi-1',
            'nama_kelas' => 'Kelas Sumber',
            'jml_mahasiswa' => 0,
            'input_nilai' => 'N',
        ], 'simak_main');
        $this->post(route('class-schedules.copy'), [
            'id_prodi' => 'prodi-1',
            'source_calendar' => 'kalender-lama',
            'target_calendar' => 'kalender-lama',
        ])->assertSessionHasErrors('target_calendar');

        $this->put(route('class-schedules.update', 'jadwal-1'), $this->schedulePayload('Kelas Satu A'))
            ->assertRedirect(route('class-schedules.index', ['id_prodi' => 'prodi-1', 'id_kalender' => 'kalender-1']));
        $this->assertDatabaseHas('s1m4k_jadwal', [
            'id_jadwal' => 'jadwal-1',
            'id_matkul' => 'mk-1',
            'id_ruang' => 'ruang-1',
            'hari' => 'senin',
            'nama_kelas' => 'Kelas Satu A',
        ], 'simak_main');
        $this->delete(route('class-schedules.destroy', 'jadwal-1'))
            ->assertRedirect(route('class-schedules.index', ['id_prodi' => 'prodi-1', 'id_kalender' => 'kalender-1']))
            ->assertSessionHasErrors('schedule');
        $this->assertDatabaseHas('s1m4k_jadwal', ['id_jadwal' => 'jadwal-1'], 'simak_main');

        $this->get(route('class-schedules.edit', 'jadwal-sumber'))->assertForbidden();
        $this->delete(route('class-schedules.destroy', 'jadwal-sumber'))->assertForbidden();
        $this->assertDatabaseHas('s1m4k_jadwal', ['id_jadwal' => 'jadwal-sumber'], 'simak_main');
    }

    public function test_schedule_using_krs_only_updates_allowed_fields_and_requires_kampus_merdeka_date(): void
    {
        $this->signIn('admin');
        $this->put(route('class-schedules.update', 'jadwal-1'), [
            ...$this->schedulePayload('Kelas KRS'),
            'kampus_merdeka' => '1',
            'tgl_tutup_daftar_km' => '',
        ])->assertSessionHasErrors('tgl_tutup_daftar_km');

        $this->put(route('class-schedules.update', 'jadwal-1'), [
            ...$this->schedulePayload('Kelas KRS'),
            'id_matkul' => 'mk-2',
            'id_ruang' => 'ruang-2',
            'hari' => 'jumat',
            'jammulai' => '13:00',
            'jamselesai' => '15:00',
            'kampus_merdeka' => '1',
            'tgl_tutup_daftar_km' => '2026-12-31',
        ])->assertRedirect();

        $this->assertDatabaseHas('s1m4k_jadwal', [
            'id_jadwal' => 'jadwal-1',
            'id_matkul' => 'mk-1',
            'id_ruang' => 'ruang-1',
            'hari' => 'senin',
            'jammulai' => '08:00:00',
            'nama_kelas' => 'Kelas KRS',
            'kampus_merdeka' => '1',
            'tgl_tutup_daftar_km' => '2026-12-31',
        ], 'simak_main');
    }

    public function test_sp_calendar_does_not_offer_or_accept_regular_semester_courses(): void
    {
        DB::connection('simak_main')->table('s1m4k_kalender')
            ->where('id_kalender', 'kalender-1')
            ->update(['tahun' => '20263']);

        $this->signIn('admin');
        $this->get(route('class-schedules.create', ['id_prodi' => 'prodi-1']))
            ->assertRedirect(route('class-schedules.index', ['id_prodi' => 'prodi-1']))
            ->assertSessionHasErrors('calendar');
        $this->post(route('class-schedules.store'), $this->schedulePayload('KLS C'))
            ->assertStatus(422)
            ->assertSee('Tidak ada kalender aktif untuk program studi ini.');
    }

    public function test_short_semester_schedule_uses_matching_sp_calendar_and_sends_class_then_all_lecturers_to_feeder(): void
    {
        DB::connection('simak_main')->table('s1m4k_mastermk')
            ->where('id_matkul', 'mk-sp')
            ->update(['sks_mata_kuliah' => '4']);
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        $this->get(route('short-semester-schedules.create'))
            ->assertOk()
            ->assertSee('Jadwal SP')
            ->assertSee('Seluruh proses perubahan semester pendek secara realtime terintegrasi dengan feeder sehingga tidak perlu dilakukan export feeder maupun penginputan secara manual di feeder.')
            ->assertSee('Mata Kuliah SP')
            ->assertSee('Matematika')
            ->assertDontSee('Asisten Satu');

        $actions = [];
        $teacherRecords = [];
        Http::fake(function (ClientRequest $request) use (&$actions, &$teacherRecords) {
            $action = $request['act'] ?? '';
            $actions[] = $action;
            if ($action === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'sp-token']]);
            }
            if ($action === 'InsertKelasKuliah') {
                $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_jadwal')
                    ->where('id_kalender', 'kalender-sp')->count());

                return Http::response(['error_code' => '0', 'data' => ['id_kelas_kuliah' => 'feeder-class-sp-1']]);
            }
            if ($action === 'GetListPenugasanDosen') {
                $nidn = str_contains((string) ($request['filter'] ?? ''), 'N002') ? 'N002' : 'N001';

                return Http::response([
                    'error_code' => '0',
                    'data' => [
                        [
                            'id_registrasi_dosen' => 'registration-wrong-program',
                            'nidn' => $nidn,
                            'id_prodi' => 'prodi-2',
                        ],
                        [
                            'id_registrasi_dosen' => 'registration-'.$nidn,
                            'nidn' => $nidn,
                            'id_prodi' => 'prodi-1',
                        ],
                    ],
                ]);
            }
            if ($action === 'InsertDosenPengajarKelasKuliah') {
                $this->assertSame(1, DB::connection('simak_main')->table('s1m4k_jadwal')
                    ->where('id_kalender', 'kalender-sp')->count());
                $teacherRecords[] = $request['record'];

                return Http::response(['error_code' => '0', 'data' => ['id_aktivitas_mengajar' => 'teaching-activity']]);
            }

            return Http::response(['error_code' => '99', 'error_desc' => 'Unexpected action']);
        });

        $response = $this->postJson(route('short-semester-schedules.store'), [
            ...$this->spSchedulePayload(),
            'dosen2' => 'dosen-2',
        ]);
        $response->assertCreated()
            ->assertJsonPath('teacher_ids', ['dosen-1', 'dosen-2']);

        $scheduleId = $response->json('schedule_id');
        $schedule = DB::connection('simak_main')->table('s1m4k_jadwal')
            ->where('id_jadwal', $scheduleId)->first();
        $this->assertSame('kalender-sp', $schedule->id_kalender);
        $this->assertSame('feeder-class-sp-1', $schedule->id_kelas_kuliah);
        $this->assertSame(['pending', 'pending'], DB::connection('simak_main')
            ->table('s1m4k_jadwal_pengajar_feeder')->orderBy('id_dosen')->pluck('status')->all());

        $this->postJson(route('short-semester-schedules.teachers.sync', [$scheduleId, 'dosen-1']))
            ->assertOk()->assertJsonPath('status', 'synced');
        $this->postJson(route('short-semester-schedules.teachers.sync', [$scheduleId, 'dosen-2']))
            ->assertOk()->assertJsonPath('status', 'synced');

        $classRequest = Http::recorded()->first(
            static fn (array $entry): bool => ($entry[0]['act'] ?? '') === 'InsertKelasKuliah'
        )[0]['record'];
        $this->assertSame([
            'id_prodi' => 'prodi-1',
            'id_semester' => '20253',
            'id_matkul' => 'mk-sp',
            'nama_kelas_kuliah' => 'SP A',
            'bahasan' => '',
            'tanggal_mulai_efektif' => '2026-01-10',
            'tanggal_akhir_efektif' => '2026-05-30',
            'apa_untuk_pditt' => '0',
            'kapasitas' => 40,
            'lingkup' => null,
            'mode' => null,
        ], $classRequest);
        $this->assertCount(2, $teacherRecords);
        foreach ($teacherRecords as $index => $record) {
            $this->assertSame('feeder-class-sp-1', $record['id_kelas_kuliah']);
            $this->assertSame('registration-N00'.($index + 1), $record['id_registrasi_dosen']);
            $this->assertSame('2.00', $record['sks_substansi_total']);
            $this->assertSame(16, $record['rencana_minggu_pertemuan']);
            $this->assertSame(8, $record['realisasi_minggu_pertemuan']);
            $this->assertSame('1', $record['id_jenis_evaluasi']);
        }
        $this->assertSame(['16:8', '16:8'], DB::connection('simak_main')->table('s1m4k_akd')
            ->orderBy('id_dosen')->get(['rencana', 'realisasi'])
            ->map(static fn (object $activity): string => $activity->rencana.':'.$activity->realisasi)
            ->all());
        $this->assertSame(2, collect($actions)->filter(
            static fn (string $action): bool => $action === 'InsertDosenPengajarKelasKuliah'
        )->count());
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListPenugasanDosen'
            && ($request['filter'] ?? '') === "nidn='N001'");
    }

    public function test_short_semester_teacher_failure_is_recorded_and_can_be_retried(): void
    {
        $failLecturerInsert = true;
        Http::fake(function (ClientRequest $request) use (&$failLecturerInsert) {
            return match ($request['act'] ?? '') {
                'GetToken' => Http::response(['error_code' => '0', 'data' => ['token' => 'sp-token']]),
                'InsertKelasKuliah' => Http::response(['error_code' => '0', 'data' => ['id_kelas_kuliah' => 'feeder-class-retry']]),
                'GetListPenugasanDosen' => Http::response(['error_code' => '0', 'data' => [
                    ['id_registrasi_dosen' => 'registration-N001', 'nidn' => 'N001', 'id_prodi' => 'prodi-1'],
                ]]),
                'InsertDosenPengajarKelasKuliah' => $failLecturerInsert
                    ? Http::response(['error_code' => '9', 'error_desc' => 'Feeder sedang sibuk'])
                    : Http::response(['error_code' => '0', 'data' => ['id_aktivitas_mengajar' => 'teaching-retry']]),
                default => Http::response(['error_code' => '99', 'error_desc' => 'Unexpected action']),
            };
        });
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        $created = $this->postJson(route('short-semester-schedules.store'), $this->spSchedulePayload())
            ->assertCreated();
        $scheduleId = $created->json('schedule_id');

        $this->postJson(route('short-semester-schedules.teachers.sync', [$scheduleId, 'dosen-1']))
            ->assertUnprocessable()
            ->assertJsonPath('status', 'failed');
        $this->assertDatabaseHas('s1m4k_jadwal_pengajar_feeder', [
            'id_jadwal' => $scheduleId,
            'id_dosen' => 'dosen-1',
            'status' => 'failed',
        ], 'simak_main');
        $this->assertDatabaseHas('s1m4k_jadwal', [
            'id_jadwal' => $scheduleId,
            'id_kelas_kuliah' => 'feeder-class-retry',
        ], 'simak_main');
        $this->get(route('short-semester-schedules.index', [
            'id_prodi' => 'prodi-1',
            'id_kalender' => 'kalender-sp',
        ]))->assertOk()->assertSee('Feeder sedang sibuk')->assertSee('Kirim / Coba lagi');

        $failLecturerInsert = false;
        $this->postJson(route('short-semester-schedules.teachers.sync', [$scheduleId, 'dosen-1']))
            ->assertOk()->assertJsonPath('status', 'synced');
        $this->assertDatabaseHas('s1m4k_jadwal_pengajar_feeder', [
            'id_jadwal' => $scheduleId,
            'id_dosen' => 'dosen-1',
            'status' => 'synced',
        ], 'simak_main');
    }

    public function test_short_semester_schedule_edit_rebalances_credits_when_only_one_lecturer_changes(): void
    {
        $this->seedShortSemesterSchedule();
        DB::connection('simak_main')->table('s1m4k_mastermk')
            ->where('id_matkul', 'mk-sp')
            ->update(['sks_mata_kuliah' => '3']);
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        $this->get(route('short-semester-schedules.edit', 'jadwal-sp'))
            ->assertOk()
            ->assertSee('Ubah Jadwal SP')
            ->assertSee('Seluruh proses perubahan semester pendek secara realtime terintegrasi dengan feeder sehingga tidak perlu dilakukan export feeder maupun penginputan secara manual di feeder.')
            ->assertSee('name="_method" value="PUT"', false);

        $actions = [];
        Http::fake(function (ClientRequest $request) use (&$actions) {
            $action = $request['act'] ?? '';
            if ($action !== 'GetToken') {
                $actions[] = $action;
            }

            if ($action === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'sp-token']]);
            }
            if ($action === 'UpdateKelasKuliah') {
                $this->assertSame('feeder-class-sp-edit', $request['key']['id_kelas_kuliah']);
                $this->assertSame('SP B', $request['record']['nama_kelas_kuliah']);
                $this->assertSame('Kelas SP Lama', DB::connection('simak_main')->table('s1m4k_jadwal')
                    ->where('id_jadwal', 'jadwal-sp')->value('nama_kelas'));

                return Http::response(['error_code' => '0', 'data' => ['id_kelas_kuliah' => 'feeder-class-sp-edit']]);
            }
            if ($action === 'GetDosenPengajarKelasKuliah') {
                return Http::response(['error_code' => '0', 'data' => [
                    ['id_aktivitas_mengajar' => 'activity-sp-1', 'nidn' => 'N001'],
                    ['id_aktivitas_mengajar' => 'activity-sp-2', 'nidn' => 'N002'],
                ]]);
            }
            if ($action === 'GetListPenugasanDosen') {
                $nidn = match (true) {
                    str_contains((string) $request['filter'], 'N002') => 'N002',
                    str_contains((string) $request['filter'], 'N003') => 'N003',
                    default => 'N001',
                };

                return Http::response(['error_code' => '0', 'data' => [
                    ['id_registrasi_dosen' => 'registration-'.$nidn, 'nidn' => $nidn, 'id_prodi' => 'prodi-1'],
                ]]);
            }
            if ($action === 'UpdateDosenPengajarKelasKuliah') {
                $this->assertContains($request['key']['id_aktivitas_mengajar'], ['activity-sp-1', 'activity-sp-2']);
                $this->assertSame('feeder-class-sp-edit', $request['record']['id_kelas_kuliah']);
                $this->assertSame('1.50', $request['record']['sks_substansi_total']);
                $this->assertSame('Kelas SP Lama', DB::connection('simak_main')->table('s1m4k_jadwal')
                    ->where('id_jadwal', 'jadwal-sp')->value('nama_kelas'));

                return Http::response(['error_code' => '0', 'data' => ['id_aktivitas_mengajar' => $request['key']['id_aktivitas_mengajar']]]);
            }
            if ($action === 'DeleteDosenPengajarKelasKuliah') {
                $this->assertSame('activity-sp-1', $request['key']['id_aktivitas_mengajar']);

                return Http::response(['error_code' => '0', 'data' => []]);
            }
            if ($action === 'InsertDosenPengajarKelasKuliah') {
                $this->assertSame('registration-N003', $request['record']['id_registrasi_dosen']);
                $this->assertSame('1.50', $request['record']['sks_substansi_total']);

                return Http::response(['error_code' => '0', 'data' => ['id_aktivitas_mengajar' => 'activity-sp-3']]);
            }

            return Http::response(['error_code' => '99', 'error_desc' => 'Unexpected action']);
        });

        $this->put(route('short-semester-schedules.update', 'jadwal-sp'), [
            ...$this->spSchedulePayload(),
            'nama_kelas' => 'SP B',
            'dosen1' => 'dosen-3',
            'dosen2' => 'dosen-2',
        ])->assertRedirect(route('short-semester-schedules.index', [
            'id_prodi' => 'prodi-1',
            'id_kalender' => 'kalender-sp',
        ]))->assertSessionHas('status');
        $this->assertSame('SP B', DB::connection('simak_main')->table('s1m4k_jadwal')
            ->where('id_jadwal', 'jadwal-sp')->value('nama_kelas'));
        $this->assertSame([
            'UpdateKelasKuliah',
            'GetDosenPengajarKelasKuliah',
            'DeleteDosenPengajarKelasKuliah',
            'GetListPenugasanDosen',
            'UpdateDosenPengajarKelasKuliah',
            'GetListPenugasanDosen',
            'InsertDosenPengajarKelasKuliah',
        ], $actions);
        $this->assertSame(['synced', 'synced'], DB::connection('simak_main')
            ->table('s1m4k_jadwal_pengajar_feeder')
            ->where('id_jadwal', 'jadwal-sp')
            ->orderBy('id_dosen')
            ->pluck('status')
            ->all());
        $this->assertDatabaseMissing('s1m4k_jadwal_pengajar_feeder', [
            'id_jadwal' => 'jadwal-sp',
            'id_dosen' => 'dosen-1',
        ], 'simak_main');
        $this->assertDatabaseHas('s1m4k_jadwal_pengajar_feeder', [
            'id_jadwal' => 'jadwal-sp',
            'id_dosen' => 'dosen-3',
            'status' => 'synced',
        ], 'simak_main');
    }

    public function test_short_semester_edit_does_not_save_simak_when_feeder_lecturer_update_fails(): void
    {
        $this->seedShortSemesterSchedule();
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        Http::fake(function (ClientRequest $request) {
            return match ($request['act'] ?? '') {
                'GetToken' => Http::response(['error_code' => '0', 'data' => ['token' => 'sp-token']]),
                'UpdateKelasKuliah' => Http::response(['error_code' => '0', 'data' => []]),
                'GetDosenPengajarKelasKuliah' => Http::response(['error_code' => '0', 'data' => [
                    ['id_aktivitas_mengajar' => 'activity-sp-1', 'nidn' => 'N001'],
                ]]),
                'GetListPenugasanDosen' => Http::response(['error_code' => '0', 'data' => [
                    ['id_registrasi_dosen' => 'registration-N001', 'nidn' => 'N001', 'id_prodi' => 'prodi-1'],
                ]]),
                'UpdateDosenPengajarKelasKuliah' => Http::response(['error_code' => '9', 'error_desc' => 'Feeder sedang sibuk']),
                default => Http::response(['error_code' => '99', 'error_desc' => 'Unexpected action']),
            };
        });

        $this->put(route('short-semester-schedules.update', 'jadwal-sp'), [
            ...$this->spSchedulePayload(),
            'nama_kelas' => 'SP B',
        ])->assertRedirect()->assertSessionHasErrors('feeder');
        $this->assertSame('Kelas SP Lama', DB::connection('simak_main')->table('s1m4k_jadwal')
            ->where('id_jadwal', 'jadwal-sp')->value('nama_kelas'));
    }

    public function test_short_semester_edit_returns_json_progress_payload_for_ui(): void
    {
        $this->seedShortSemesterSchedule();
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        Http::fake(function (ClientRequest $request) {
            return match ($request['act'] ?? '') {
                'GetToken' => Http::response(['error_code' => '0', 'data' => ['token' => 'sp-token']]),
                'UpdateKelasKuliah' => Http::response(['error_code' => '0', 'data' => []]),
                'GetDosenPengajarKelasKuliah' => Http::response(['error_code' => '0', 'data' => [
                    ['id_aktivitas_mengajar' => 'activity-sp-1', 'nidn' => 'N001'],
                ]]),
                'GetListPenugasanDosen' => Http::response(['error_code' => '0', 'data' => [
                    ['id_registrasi_dosen' => 'registration-N001', 'nidn' => 'N001', 'id_prodi' => 'prodi-1'],
                    ['id_registrasi_dosen' => 'registration-N003', 'nidn' => 'N003', 'id_prodi' => 'prodi-1'],
                ]]),
                'DeleteDosenPengajarKelasKuliah' => Http::response(['error_code' => '0', 'data' => []]),
                'UpdateDosenPengajarKelasKuliah', 'InsertDosenPengajarKelasKuliah' => Http::response(['error_code' => '0', 'data' => ['id_aktivitas_mengajar' => 'activity-sp-1']]),
                default => Http::response(['error_code' => '99', 'error_desc' => 'Unexpected action']),
            };
        });

        $this->putJson(route('short-semester-schedules.update', 'jadwal-sp'), [
            ...$this->spSchedulePayload(),
            'nama_kelas' => 'SP B',
            'dosen1' => 'dosen-3',
        ])->assertOk()->assertJsonPath('status', 'updated')->assertJsonPath('redirect_url', route('short-semester-schedules.index', [
            'id_prodi' => 'prodi-1',
            'id_kalender' => 'kalender-sp',
        ]));
    }

    public function test_short_semester_delete_returns_json_progress_payload_for_ui(): void
    {
        $this->seedShortSemesterSchedule();
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        Http::fake(function (ClientRequest $request) {
            return match ($request['act'] ?? '') {
                'GetToken' => Http::response(['error_code' => '0', 'data' => ['token' => 'sp-token']]),
                'GetDosenPengajarKelasKuliah' => Http::response(['error_code' => '0', 'data' => [
                    ['id_aktivitas_mengajar' => 'activity-sp-1', 'nidn' => 'N001'],
                    ['id_aktivitas_mengajar' => 'activity-sp-2', 'nidn' => 'N002'],
                ]]),
                'DeleteDosenPengajarKelasKuliah', 'DeleteKelasKuliah' => Http::response(['error_code' => '0', 'data' => []]),
                default => Http::response(['error_code' => '99', 'error_desc' => 'Unexpected action']),
            };
        });

        $this->deleteJson(route('short-semester-schedules.destroy', 'jadwal-sp'))
            ->assertOk()->assertJsonPath('status', 'deleted')->assertJsonPath('redirect_url', route('short-semester-schedules.index', [
                'id_prodi' => 'prodi-1',
                'id_kalender' => 'kalender-sp',
            ]));
    }

    public function test_short_semester_delete_removes_feeder_lecturers_before_class_and_local_schedule(): void
    {
        $this->seedShortSemesterSchedule();
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        $actions = [];
        Http::fake(function (ClientRequest $request) use (&$actions) {
            $action = $request['act'] ?? '';
            if ($action !== 'GetToken') {
                $actions[] = $action;
            }
            if (in_array($action, ['DeleteDosenPengajarKelasKuliah', 'DeleteKelasKuliah'], true)) {
                $this->assertDatabaseHas('s1m4k_jadwal', ['id_jadwal' => 'jadwal-sp'], 'simak_main');
            }

            return match ($action) {
                'GetToken' => Http::response(['error_code' => '0', 'data' => ['token' => 'sp-token']]),
                'GetDosenPengajarKelasKuliah' => Http::response(['error_code' => '0', 'data' => [
                    ['id_aktivitas_mengajar' => 'activity-sp-1', 'nidn' => 'N001'],
                    ['id_aktivitas_mengajar' => 'activity-sp-2', 'nidn' => 'N002'],
                ]]),
                'DeleteDosenPengajarKelasKuliah', 'DeleteKelasKuliah' => Http::response(['error_code' => '0', 'data' => []]),
                default => Http::response(['error_code' => '99', 'error_desc' => 'Unexpected action']),
            };
        });

        $this->delete(route('short-semester-schedules.destroy', 'jadwal-sp'))
            ->assertRedirect(route('short-semester-schedules.index', [
                'id_prodi' => 'prodi-1',
                'id_kalender' => 'kalender-sp',
            ]))->assertSessionHas('status');
        $this->assertSame([
            'GetDosenPengajarKelasKuliah',
            'DeleteDosenPengajarKelasKuliah',
            'DeleteDosenPengajarKelasKuliah',
            'DeleteKelasKuliah',
        ], $actions);
        $this->assertDatabaseMissing('s1m4k_jadwal', ['id_jadwal' => 'jadwal-sp'], 'simak_main');
        $this->assertDatabaseMissing('s1m4k_jadwal_pengajar_feeder', ['id_jadwal' => 'jadwal-sp'], 'simak_main');
        $this->assertDatabaseMissing('s1m4k_akd', ['id_jadwal' => 'jadwal-sp'], 'simak_main');
    }

    public function test_short_semester_actions_are_only_available_for_the_active_calendar(): void
    {
        $this->seedShortSemesterSchedule();
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        $this->get(route('short-semester-schedules.index', [
            'id_prodi' => 'prodi-1',
            'id_kalender' => 'kalender-sp',
        ]))->assertOk()
            ->assertSee(route('short-semester-schedules.edit', 'jadwal-sp'))
            ->assertSee(route('short-semester-schedules.destroy', 'jadwal-sp'))
            ->assertSee('data-schedule-confirm', false)
            ->assertSee('data-lucide="user-plus"', false)
            ->assertDontSee('data-short-semester-select-all', false);

        DB::connection('simak_main')->table('s1m4k_kalender')
            ->where('id_kalender', 'kalender-sp')
            ->update(['status_sp' => 'N']);
        $this->get(route('short-semester-schedules.index', [
            'id_prodi' => 'prodi-1',
            'id_kalender' => 'kalender-sp',
        ]))->assertOk()
            ->assertSee('hanya dapat dilihat')
            ->assertDontSee(route('short-semester-schedules.edit', 'jadwal-sp'))
            ->assertDontSee(route('short-semester-schedules.destroy', 'jadwal-sp'));
        $this->get(route('short-semester-schedules.edit', 'jadwal-sp'))->assertForbidden();
        $this->delete(route('short-semester-schedules.destroy', 'jadwal-sp'))->assertForbidden();
        $this->assertDatabaseHas('s1m4k_jadwal', ['id_jadwal' => 'jadwal-sp'], 'simak_main');
    }

    public function test_short_semester_delete_is_blocked_when_schedule_has_krs(): void
    {
        $this->seedShortSemesterSchedule();
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-sp',
            'id_jadwal' => 'jadwal-sp',
            'id_kalender' => 'kalender-sp',
            'id_mahasiswa' => 'mhs-1',
        ]);
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        $this->get(route('short-semester-schedules.index', [
            'id_prodi' => 'prodi-1',
            'id_kalender' => 'kalender-sp',
        ]))->assertOk()->assertSee('aria-label="Hapus jadwal SP"', false);
        Http::fake();

        $this->delete(route('short-semester-schedules.destroy', 'jadwal-sp'))
            ->assertRedirect()->assertSessionHasErrors('schedule');
        Http::assertNothingSent();
        $this->assertDatabaseHas('s1m4k_jadwal', ['id_jadwal' => 'jadwal-sp'], 'simak_main');
    }

    public function test_short_semester_teacher_already_processing_cannot_be_submitted_again(): void
    {
        $actions = [];
        Http::fake(function (ClientRequest $request) use (&$actions) {
            $actions[] = $request['act'] ?? '';

            return match ($request['act'] ?? '') {
                'GetToken' => Http::response(['error_code' => '0', 'data' => ['token' => 'sp-token']]),
                'InsertKelasKuliah' => Http::response(['error_code' => '0', 'data' => ['id_kelas_kuliah' => 'feeder-class-processing']]),
                default => Http::response(['error_code' => '99', 'error_desc' => 'Unexpected action']),
            };
        });
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        $created = $this->postJson(route('short-semester-schedules.store'), $this->spSchedulePayload())
            ->assertCreated();
        $scheduleId = $created->json('schedule_id');
        DB::connection('simak_main')->table('s1m4k_jadwal_pengajar_feeder')
            ->where('id_jadwal', $scheduleId)->where('id_dosen', 'dosen-1')
            ->update(['status' => 'processing']);

        $this->get(route('short-semester-schedules.index', [
            'id_prodi' => 'prodi-1',
            'id_kalender' => 'kalender-sp',
        ]))->assertOk()->assertSee('Sedang diproses; jangan kirim ulang');
        $this->postJson(route('short-semester-schedules.teachers.sync', [$scheduleId, 'dosen-1']))
            ->assertStatus(409)->assertJsonPath('status', 'processing');
        $this->assertSame(0, collect($actions)->filter(
            static fn (string $action): bool => $action === 'GetListPenugasanDosen'
        )->count());
    }

    public function test_short_semester_schedule_is_not_saved_if_feeder_does_not_create_class_id(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push(['error_code' => '0', 'data' => ['token' => 'sp-token']])
                ->push(['error_code' => '0', 'data' => []]),
        ]);
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);

        $this->postJson(route('short-semester-schedules.store'), $this->spSchedulePayload())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Feeder menyatakan kelas berhasil dibuat tetapi tidak mengembalikan ID kelas kuliah. Periksa kelas di Feeder sebelum mencoba lagi.');
        $this->assertDatabaseMissing('s1m4k_jadwal', ['id_kalender' => 'kalender-sp'], 'simak_main');
    }

    public function test_short_semester_schedule_checks_local_status_table_before_creating_feeder_class(): void
    {
        Schema::connection('simak_main')->drop('s1m4k_jadwal_pengajar_feeder');
        Http::fake();
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);

        $this->postJson(route('short-semester-schedules.store'), $this->spSchedulePayload())
            ->assertStatus(503)
            ->assertJsonPath('message', 'Jadwal SP belum dapat disimpan karena tabel status pengiriman dosen belum tersedia. Jalankan SQL dari database/manual/s1m4k_jadwal_pengajar_feeder.sql terlebih dahulu, lalu coba kembali.');

        Http::assertNothingSent();
        $this->assertDatabaseMissing('s1m4k_jadwal', ['id_kalender' => 'kalender-sp'], 'simak_main');
    }

    public function test_short_semester_schedule_requires_an_active_calendar_with_matching_curriculum(): void
    {
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        DB::connection('simak_main')->table('s1m4k_kurikulum')
            ->where('id_kurikulum', 'kalender-sp')
            ->update(['id_semester' => '20243']);

        $this->get(route('short-semester-schedules.create'))
            ->assertRedirect(route('short-semester-schedules.index', ['id_prodi' => 'prodi-1']))
            ->assertSessionHasErrors('calendar');
        $this->postJson(route('short-semester-schedules.store'), $this->spSchedulePayload())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Tidak ada kalender SP aktif untuk program studi ini.');
    }

    public function test_short_semester_student_participant_list_only_includes_eligible_students_for_selected_schedule(): void
    {
        $this->seedShortSemesterSchedule();
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'mhs-eligible-a', 'nim' => '2025001', 'nama_mahasiswa' => 'Mahasiswa Aktif', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'id_registrasi_mahasiswa' => 'reg-001'],
            ['id_mahasiswa' => 'mhs-eligible-c', 'nim' => '2025002', 'nama_mahasiswa' => 'Mahasiswa Cuti', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'C', 'nama_status_mahasiswa' => 'Cuti', 'id_registrasi_mahasiswa' => 'reg-002'],
            ['id_mahasiswa' => 'mhs-eligible-n', 'nim' => '2025003', 'nama_mahasiswa' => 'Mahasiswa Nonaktif', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'N', 'nama_status_mahasiswa' => 'Nonaktif', 'id_registrasi_mahasiswa' => 'reg-003'],
            ['id_mahasiswa' => 'mhs-eligible-m', 'nim' => '2025004', 'nama_mahasiswa' => 'Mahasiswa Merdeka', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'M', 'nama_status_mahasiswa' => 'Kampus Merdeka', 'id_registrasi_mahasiswa' => 'reg-004'],
            ['id_mahasiswa' => 'mhs-ineligible-g', 'nim' => '2025005', 'nama_mahasiswa' => 'Mahasiswa Tidak Layak', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'G', 'nama_status_mahasiswa' => 'Lainnya', 'id_registrasi_mahasiswa' => 'reg-005'],
            ['id_mahasiswa' => 'mhs-other-prodi', 'nim' => '2025006', 'nama_mahasiswa' => 'Mahasiswa Prodi Lain', 'id_prodi' => 'prodi-2', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'id_registrasi_mahasiswa' => 'reg-006'],
        ]);

        $this->getJson(route('short-semester-schedules.students.index', ['scheduleId' => 'jadwal-sp']))
            ->assertOk()
            ->assertJsonCount(4, 'students')
            ->assertJsonFragment(['id_mahasiswa' => 'mhs-eligible-a'])
            ->assertJsonMissing(['id_mahasiswa' => 'mhs-ineligible-g'])
            ->assertJsonMissing(['id_mahasiswa' => 'mhs-other-prodi']);
    }

    public function test_short_semester_student_participant_insert_writes_krs_with_sp_calendar(): void
    {
        $this->seedShortSemesterSchedule();
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'mhs-eligible-sp', 'nim' => '2025007', 'nama_mahasiswa' => 'Mahasiswa SP', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'id_registrasi_mahasiswa' => 'reg-sp-001'],
        ]);

        Http::fake(function (ClientRequest $request) {
            return match ($request['act'] ?? '') {
                'GetToken' => Http::response(['error_code' => '0', 'data' => ['token' => 'sp-token']]),
                'InsertPesertaKelasKuliah' => Http::response(['error_code' => '0', 'data' => ['id_peserta_kelas' => 'participant-sp-1']]),
                default => Http::response(['error_code' => '99', 'error_desc' => 'Unexpected action']),
            };
        });

        $this->postJson(route('short-semester-schedules.students.store', ['scheduleId' => 'jadwal-sp', 'student' => 'mhs-eligible-sp']))
            ->assertOk()
            ->assertJsonPath('status', 'inserted')
            ->assertJsonPath('student_id', 'mhs-eligible-sp');

        $this->assertDatabaseHas('s1m4k_krs', ['id_jadwal' => 'jadwal-sp', 'id_kalender' => 'kalender-sp', 'id_mahasiswa' => 'mhs-eligible-sp'], 'simak_main');
    }

    public function test_short_semester_student_is_not_added_to_krs_when_feeder_rejects_participant(): void
    {
        $this->seedShortSemesterSchedule();
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'mhs-feeder-reject',
            'nim' => '2025013',
            'nama_mahasiswa' => 'Mahasiswa Ditolak Feeder',
            'id_prodi' => 'prodi-1',
            'id_status_mahasiswa' => 'A',
            'nama_status_mahasiswa' => 'Aktif',
            'id_registrasi_mahasiswa' => 'reg-feeder-reject',
        ]);
        Http::fake(function (ClientRequest $request) {
            return match ($request['act'] ?? '') {
                'GetToken' => Http::response(['error_code' => '0', 'data' => ['token' => 'sp-token']]),
                'InsertPesertaKelasKuliah' => Http::response([
                    'error_code' => '1217',
                    'error_desc' => 'Mahasiswa tidak bisa ditambah di Kelas Tahun Masuk di Bawah Semester Kelas',
                ]),
                default => Http::response(['error_code' => '99', 'error_desc' => 'Unexpected action']),
            };
        });

        $this->postJson(route('short-semester-schedules.students.store', [
            'scheduleId' => 'jadwal-sp',
            'student' => 'mhs-feeder-reject',
        ]))->assertUnprocessable()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('message', 'Feeder gagal menambahkan peserta kelas SP. (1217: Mahasiswa tidak bisa ditambah di Kelas Tahun Masuk di Bawah Semester Kelas)');

        $this->assertDatabaseMissing('s1m4k_krs', [
            'id_jadwal' => 'jadwal-sp',
            'id_mahasiswa' => 'mhs-feeder-reject',
        ], 'simak_main');
    }

    public function test_short_semester_participant_count_and_list_use_krs_records(): void
    {
        $this->seedShortSemesterSchedule();
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'mhs-participant-sp',
            'nim' => '2025010',
            'nama_mahasiswa' => 'Peserta SP',
            'id_prodi' => 'prodi-1',
            'id_status_mahasiswa' => 'A',
            'nama_status_mahasiswa' => 'Aktif',
            'id_registrasi_mahasiswa' => 'reg-participant-sp',
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-participant-sp',
            'id_jadwal' => 'jadwal-sp',
            'id_kalender' => 'kalender-sp',
            'id_prodi' => 'prodi-1',
            'id_mahasiswa' => 'mhs-participant-sp',
        ]);

        $this->getJson(route('short-semester-schedules.participants.index', ['scheduleId' => 'jadwal-sp']))
            ->assertOk()
            ->assertJsonCount(1, 'participants')
            ->assertJsonFragment(['id_mahasiswa' => 'mhs-participant-sp']);
        $this->get(route('short-semester-schedules.index', [
            'id_prodi' => 'prodi-1',
            'id_kalender' => 'kalender-sp',
        ]))->assertOk()
            ->assertSee('aria-label="Lihat 1 peserta kelas SP"', false)
            ->assertSee('data-short-semester-student-pagination', false)
            ->assertSee('data-short-semester-participant-pagination', false);
    }

    public function test_short_semester_student_delete_removes_krs_only_after_feeder_success(): void
    {
        $this->seedShortSemesterSchedule();
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'mhs-delete-sp', 'nim' => '2025011', 'nama_mahasiswa' => 'Peserta Hapus', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'id_registrasi_mahasiswa' => 'reg-delete-sp'],
            ['id_mahasiswa' => 'mhs-failed-delete-sp', 'nim' => '2025012', 'nama_mahasiswa' => 'Peserta Gagal Hapus', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'id_registrasi_mahasiswa' => 'reg-failed-delete-sp'],
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            ['id_krs' => 'krs-delete-sp', 'id_jadwal' => 'jadwal-sp', 'id_kalender' => 'kalender-sp', 'id_prodi' => 'prodi-1', 'id_mahasiswa' => 'mhs-delete-sp'],
            ['id_krs' => 'krs-failed-delete-sp', 'id_jadwal' => 'jadwal-sp', 'id_kalender' => 'kalender-sp', 'id_prodi' => 'prodi-1', 'id_mahasiswa' => 'mhs-failed-delete-sp'],
        ]);
        Http::fake(function (ClientRequest $request) {
            return match ($request['act'] ?? '') {
                'GetToken' => Http::response(['error_code' => '0', 'data' => ['token' => 'sp-token']]),
                'DeletePesertaKelasKuliah' => ($request['key']['id_registrasi_mahasiswa'] ?? '') === 'reg-delete-sp'
                    ? Http::response(['error_code' => '0', 'data' => []])
                    : Http::response(['error_code' => '17', 'error_desc' => 'Gagal hapus peserta']),
                default => Http::response(['error_code' => '99', 'error_desc' => 'Unexpected action']),
            };
        });

        $this->deleteJson(route('short-semester-schedules.participants.destroy', [
            'scheduleId' => 'jadwal-sp',
            'student' => 'mhs-delete-sp',
        ]))->assertOk()->assertJsonPath('status', 'deleted');
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'DeletePesertaKelasKuliah'
            && ($request['key']['id_kelas_kuliah'] ?? '') === 'feeder-class-sp-edit'
            && ($request['key']['id_registrasi_mahasiswa'] ?? '') === 'reg-delete-sp');
        $this->assertDatabaseMissing('s1m4k_krs', ['id_krs' => 'krs-delete-sp'], 'simak_main');

        $this->deleteJson(route('short-semester-schedules.participants.destroy', [
            'scheduleId' => 'jadwal-sp',
            'student' => 'mhs-failed-delete-sp',
        ]))->assertUnprocessable()->assertJsonPath('status', 'failed');
        $this->assertDatabaseHas('s1m4k_krs', ['id_krs' => 'krs-failed-delete-sp'], 'simak_main');
    }

    public function test_short_semester_schedule_rejects_a_course_from_another_program(): void
    {
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        $this->postJson(route('short-semester-schedules.store'), [
            ...$this->spSchedulePayload(),
            'id_matkul' => 'mk-2',
        ])->assertUnprocessable()->assertJsonValidationErrors('id_matkul');
    }

    public function test_short_semester_schedule_is_not_saved_when_feeder_rejects_class_insert(): void
    {
        Http::fake(function (ClientRequest $request) {
            return ($request['act'] ?? '') === 'GetToken'
                ? Http::response(['error_code' => '0', 'data' => ['token' => 'sp-token']])
                : Http::response(['error_code' => '17', 'error_desc' => 'Data kelas tidak valid']);
        });
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);

        $this->postJson(route('short-semester-schedules.store'), $this->spSchedulePayload())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Feeder gagal membuat kelas kuliah SP. (17: Data kelas tidak valid)');
        $this->assertDatabaseMissing('s1m4k_jadwal', ['id_kalender' => 'kalender-sp'], 'simak_main');
        $this->assertDatabaseCount('s1m4k_akd', 0, 'simak_main');
    }

    public function test_print_schedule_is_limited_to_legacy_print_roles_and_program_scope(): void
    {
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        $this->get(route('class-schedules.print', [
            'id_prodi' => 'prodi-1',
            'id_kalender' => 'kalender-1',
        ]))->assertOk()
            ->assertSee('JADWAL PERKULIAHAN')
            ->assertSee('images/landing/default-campus-logo.png')
            ->assertSee('Kelas Satu');

        $this->get(route('class-schedules.print', [
            'id_prodi' => 'prodi-2',
            'id_kalender' => 'kalender-2',
        ]))->assertNotFound();

        $this->signIn('pimuniv');
        $this->get(route('class-schedules.print', [
            'id_prodi' => 'prodi-1',
            'id_kalender' => 'kalender-1',
        ]))->assertForbidden();
    }

    private function schedulePayload(string $className): array
    {
        return [
            'id_prodi' => 'prodi-1',
            'id_matkul' => 'mk-1',
            'id_ruang' => 'ruang-1',
            'nama_kelas' => $className,
            'hari' => 'senin',
            'jammulai' => '08:00',
            'jamselesai' => '10:00',
            'dosen1' => 'dosen-1',
            'dosen2' => 'asdos-1',
            'kampus_merdeka' => '0',
            'lingkup_km' => '',
            'mode_km' => '',
            'tgl_tutup_daftar_km' => '',
        ];
    }

    private function signIn(string $role, array $attributes = []): void
    {
        $legacyId = $attributes['legacy_id'] ?? ($role === 'mahasiswa' ? 'mhs-1' : ($role === 'asdos' ? 'asdos-1' : 'actor-'.$role));
        $this->actingAs(new GenericUser(array_merge([
            'id' => $role.'-schedule-test',
            'legacy_type' => $role === 'mahasiswa' ? 'mahasiswa' : ($role === 'dosen' ? 'dosen' : ($role === 'asdos' ? 'asdos' : 'admin')),
            'legacy_id' => $legacyId,
            'login' => $role.'-schedule-test',
            'name' => 'Pengguna Jadwal',
            'role' => $role,
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'fak-1',
        ], $attributes)));
    }

    private function seedRecords(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'pt-1',
            'alamat_feeder' => 'https://feeder.example.test',
            'user_feeder' => 'feeder-user',
            'password_feeder' => $this->encrypt('feeder-secret'),
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'prodi-1', 'id_perguruan_tinggi' => 'pt-1', 'id_fakultas' => 'fak-1', 'nama_program_studi' => 'Teknik Satu', 'nama_jenjang_pendidikan' => 'S1'],
            ['id_prodi' => 'prodi-2', 'id_perguruan_tinggi' => 'pt-1', 'id_fakultas' => 'fak-2', 'nama_program_studi' => 'Teknik Dua', 'nama_jenjang_pendidikan' => 'S1'],
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'kalender-1', 'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'periode' => 'Tahun Akademik 2026/2027 Ganjil', 'id_prodi' => 'prodi-1', 'id_fakultas' => 'fak-1', 'status' => 'Y'],
            ['id_kalender' => 'kalender-lama', 'tahun' => '20252', 'namatahun' => '2025 Genap', 'periode' => 'Tahun Akademik 2025/2026 Genap', 'id_prodi' => 'prodi-1', 'id_fakultas' => 'fak-1', 'status' => 'N'],
            ['id_kalender' => 'kalender-kosong', 'tahun' => '20241', 'namatahun' => '2024 Ganjil', 'periode' => 'Tahun Akademik 2024/2025 Ganjil', 'id_prodi' => 'prodi-1', 'id_fakultas' => 'fak-1', 'status' => 'N'],
            ['id_kalender' => 'kalender-2', 'tahun' => '20261', 'namatahun' => '2026 Ganjil Prodi Dua', 'periode' => 'Tahun Akademik 2026/2027 Ganjil', 'id_prodi' => 'prodi-2', 'id_fakultas' => 'fak-2', 'status' => 'Y'],
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            'id_kalender' => 'kalender-sp',
            'tahun' => '20253',
            'namatahun' => 'SP 2025 / 2026 (Periode SP Tahun 2026)',
            'periode' => '2025/2026 SP',
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'fak-1',
            'status' => 'N',
            'status_sp' => 'Y',
            'tgl_mulai_krs' => '2026-01-10',
            'tgl_akhir_isinilai' => '2026-05-30',
        ]);
        DB::connection('simak_main')->table('s1m4k_kurikulum')->insert([
            'id_kurikulum' => 'kalender-sp',
            'id_prodi' => 'prodi-1',
            'id_semester' => '20253',
            'semester_mulai_berlaku' => '2025/2026 SP',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'mk-1', 'id_prodi' => 'prodi-1', 'kode_mata_kuliah' => 'MK001', 'nama_mata_kuliah' => 'Matematika', 'semester' => '1', 'status' => 'A'],
            ['id_matkul' => 'mk-2', 'id_prodi' => 'prodi-2', 'kode_mata_kuliah' => 'MK002', 'nama_mata_kuliah' => 'Fisika', 'semester' => '1', 'status' => 'A'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            'id_matkul' => 'mk-sp',
            'id_prodi' => 'prodi-1',
            'kode_mata_kuliah' => 'SP001',
            'nama_mata_kuliah' => 'Mata Kuliah SP',
            'semester' => '1',
            'sks_mata_kuliah' => '3',
            'status' => 'A',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterruang')->insert([
            ['id_ruang' => 'ruang-1', 'id_prodi' => 'prodi-1', 'nama_ruang' => 'Ruang Satu', 'kapasitas' => 40],
            ['id_ruang' => 'ruang-2', 'id_prodi' => 'prodi-2', 'nama_ruang' => 'Ruang Dua', 'kapasitas' => null],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'dosen-1', 'nidn' => 'N001', 'nama_dosen' => 'Dosen Satu', 'gelar_depan' => '', 'gelar_belakang' => ''],
            ['id_dosen' => 'dosen-2', 'nidn' => 'N002', 'nama_dosen' => 'Dosen Dua', 'gelar_depan' => '', 'gelar_belakang' => ''],
            ['id_dosen' => 'dosen-3', 'nidn' => 'N003', 'nama_dosen' => 'Dosen Tiga', 'gelar_depan' => '', 'gelar_belakang' => ''],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterasdos')->insert([
            ['id_asdos' => 'asdos-1', 'id_prodi' => 'prodi-1', 'nama' => 'Asisten Satu', 'gelard' => '', 'gelarb' => '', 'status' => 'Aktif'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'mhs-1', 'nim' => 'M001', 'nama_mahasiswa' => 'Mahasiswa Satu', 'id_prodi' => 'prodi-1'],
            ['id_mahasiswa' => 'mhs-2', 'nim' => 'M002', 'nama_mahasiswa' => 'Mahasiswa Dua', 'id_prodi' => 'prodi-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
            $this->scheduleRow('jadwal-1', 'prodi-1', 'kalender-1', 'mk-1', 'ruang-1', 'Kelas Satu', 'dosen-1', 'asdos-1'),
            $this->scheduleRow('jadwal-2', 'prodi-2', 'kalender-2', 'mk-2', 'ruang-2', 'Kelas Dua', 'dosen-2', ''),
            $this->scheduleRow('jadwal-sumber', 'prodi-1', 'kalender-lama', 'mk-1', 'ruang-1', 'Kelas Sumber', 'dosen-1', ''),
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            ['id_krs' => 'krs-1', 'id_jadwal' => 'jadwal-1', 'id_kalender' => 'kalender-1', 'id_prodi' => 'prodi-1', 'id_mahasiswa' => 'mhs-1'],
            ['id_krs' => 'krs-2', 'id_jadwal' => 'jadwal-2', 'id_kalender' => 'kalender-2', 'id_prodi' => 'prodi-2', 'id_mahasiswa' => 'mhs-2'],
        ]);
    }

    private function scheduleRow(string $id, string $program, string $calendar, string $course, string $room, string $class, string $teacher, string $secondTeacher): array
    {
        return [
            'id_jadwal' => $id,
            'id_perguruan_tinggi' => 'pt-1',
            'id_fakultas' => $program === 'prodi-1' ? 'fak-1' : 'fak-2',
            'id_prodi' => $program,
            'id_kalender' => $calendar,
            'id_matkul' => $course,
            'dosen1' => $teacher,
            'dosen2' => $secondTeacher,
            'id_ruang' => $room,
            'nama_kelas' => $class,
            'hari' => 'senin',
            'jammulai' => '08:00:00',
            'jamselesai' => '10:00:00',
            'kampus_merdeka' => '0',
            'lingkup_km' => '',
            'mode_km' => '',
            'tgl_tutup_daftar_km' => '',
            'jml_mahasiswa' => 0,
            'input_nilai' => 'N',
        ];
    }

    private function createSchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('nama_program_studi')->nullable();
            $table->string('nama_jenjang_pendidikan')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterpt', function (Blueprint $table): void {
            $table->string('id_perguruan_tinggi')->primary();
            $table->string('alamat_feeder')->nullable();
            $table->string('user_feeder')->nullable();
            $table->text('password_feeder')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('tahun')->nullable();
            $table->string('namatahun')->nullable();
            $table->string('periode')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('status')->nullable();
            $table->string('status_sp')->nullable();
            $table->date('tgl_mulai_krs')->nullable();
            $table->date('tgl_akhir_isinilai')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kurikulum', function (Blueprint $table): void {
            $table->string('id_kurikulum')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('id_semester')->nullable();
            $table->string('semester_mulai_berlaku')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table): void {
            $table->string('id_matkul')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('kode_mata_kuliah')->nullable();
            $table->string('nama_mata_kuliah')->nullable();
            $table->string('semester')->nullable();
            $table->string('sks_mata_kuliah')->nullable();
            $table->string('status')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterruang', function (Blueprint $table): void {
            $table->string('id_ruang')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('nama_ruang')->nullable();
            $table->integer('kapasitas')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('nidn')->nullable();
            $table->string('nama_dosen')->nullable();
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterasdos', function (Blueprint $table): void {
            $table->string('id_asdos')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('nama')->nullable();
            $table->string('gelard')->nullable();
            $table->string('gelarb')->nullable();
            $table->string('status')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim')->nullable();
            $table->string('nama_mahasiswa')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_status_mahasiswa')->nullable();
            $table->string('nama_status_mahasiswa')->nullable();
            $table->string('id_registrasi_mahasiswa')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_jadwal', function (Blueprint $table): void {
            $table->string('id_jadwal')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_kalender')->nullable();
            $table->string('id_kelas_kuliah')->nullable();
            $table->string('id_matkul')->nullable();
            foreach (range(1, 14) as $index) {
                $table->string('dosen'.$index)->nullable();
            }
            $table->string('id_ruang')->nullable();
            $table->string('nama_kelas')->nullable();
            $table->string('hari')->nullable();
            $table->time('jammulai')->nullable();
            $table->time('jamselesai')->nullable();
            $table->string('kampus_merdeka')->default('0');
            $table->string('lingkup_km')->nullable();
            $table->string('mode_km')->nullable();
            $table->string('tgl_tutup_daftar_km')->nullable();
            $table->integer('jml_mahasiswa')->default(0);
            $table->string('input_nilai')->default('N');
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_krs')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('id_jadwal')->nullable();
            $table->string('id_kalender')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('tahun')->nullable();
            $table->string('id_mahasiswa')->nullable();
            $table->string('id_matkul')->nullable();
            $table->string('status_krs')->nullable();
            $table->date('tgl_update')->nullable();
            $table->string('nilai_huruf')->nullable();
            $table->string('krs_reguler')->nullable();
            $table->string('krs_mbkm')->nullable();
            $table->string('isi_edom')->nullable();
            $table->string('user')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_akd', function (Blueprint $table): void {
            $table->string('id_akd')->primary();
            $table->string('id_jadwal')->nullable();
            $table->string('id_dosen')->nullable();
            $table->string('id_kalender')->nullable();
            $table->string('tahun')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('ket')->nullable();
            $table->integer('rencana')->nullable();
            $table->integer('realisasi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_jadwal_pengajar_feeder', function (Blueprint $table): void {
            $table->string('id_jadwal');
            $table->string('id_dosen');
            $table->string('status')->default('pending');
            $table->text('error_desc')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->primary(['id_jadwal', 'id_dosen']);
        });
        Schema::connection('simak_main')->create('log_aktivitas', function (Blueprint $table): void {
            $table->string('id_log', 36)->primary();
            $table->string('id_user', 100)->nullable();
            $table->string('level_user', 50)->nullable();
            $table->string('nama_user', 255);
            $table->string('modul', 100);
            $table->text('aktifitas');
            $table->timestamp('created_at')->nullable();
        });
    }

    private function seedShortSemesterSchedule(): void
    {
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
            ...$this->scheduleRow(
                'jadwal-sp',
                'prodi-1',
                'kalender-sp',
                'mk-sp',
                'ruang-1',
                'Kelas SP Lama',
                'dosen-1',
                'dosen-2'
            ),
            'id_kelas_kuliah' => 'feeder-class-sp-edit',
        ]);
        foreach (['dosen-1', 'dosen-2'] as $teacherId) {
            DB::connection('simak_main')->table('s1m4k_akd')->insert([
                'id_akd' => 'activity-'.$teacherId,
                'id_jadwal' => 'jadwal-sp',
                'id_dosen' => $teacherId,
                'id_kalender' => 'kalender-sp',
                'tahun' => '20253',
                'id_prodi' => 'prodi-1',
                'ket' => 'dosen',
                'rencana' => 16,
                'realisasi' => 8,
            ]);
            DB::connection('simak_main')->table('s1m4k_jadwal_pengajar_feeder')->insert([
                'id_jadwal' => 'jadwal-sp',
                'id_dosen' => $teacherId,
                'status' => 'synced',
            ]);
        }
    }

    private function spSchedulePayload(): array
    {
        return [
            'id_prodi' => 'prodi-1',
            'id_matkul' => 'mk-sp',
            'id_ruang' => 'ruang-1',
            'nama_kelas' => 'SP A',
            'hari' => 'senin',
            'jammulai' => '08:00',
            'jamselesai' => '10:00',
            'dosen1' => 'dosen-1',
            'kampus_merdeka' => '0',
            'lingkup_km' => '',
            'mode_km' => '',
            'tgl_tutup_daftar_km' => '',
        ];
    }

    private function encrypt(string $value): string
    {
        $key = '979a218e0632df2935317f98d47956c7';
        $encrypted = '';
        for ($index = 0, $length = strlen($value); $index < $length; $index++) {
            $keyCharacter = substr($key, ($index % strlen($key)) - 1, 1);
            $encrypted .= chr((ord($value[$index]) + ord($keyCharacter)) % 256);
        }

        return urlencode(base64_encode($encrypted));
    }
}
