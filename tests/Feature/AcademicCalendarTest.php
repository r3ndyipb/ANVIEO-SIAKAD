<?php

namespace Tests\Feature;

use App\Services\LegacyFeederService;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AcademicCalendarTest extends TestCase
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
    }

    public function test_calendar_list_obeys_role_and_organization_scope(): void
    {
        $this->seedProgram('prodi-1', 'fak-1', 'Program Studi Satu');
        $this->seedProgram('prodi-2', 'fak-2', 'Program Studi Dua');
        $this->seedProgram('prodi-3', 'fak-1', 'Program Studi Tiga');
        $this->seedCalendar('calendar-1', 'prodi-1', 'fak-1', '20251');
        $this->seedCalendar('calendar-2', 'prodi-2', 'fak-2', '20251');
        $this->seedCalendar('calendar-3', 'prodi-3', 'fak-1', '20251');

        foreach (['admin', 'pimuniv'] as $role) {
            $this->signIn($role);
            $response = $this->get(route('academic-calendars.index'))
                ->assertOk()
                ->assertSee('data-datatable="academic-calendar"', false)
                ->assertSee('Program Studi Satu')
                ->assertSee('Program Studi Dua')
                ->assertSee('Program Studi Tiga');

            if ($role === 'admin') {
                $response->assertSee('aria-label="Ubah tanggal kalender"', false);
                $response->assertSee('aria-label="Aktifkan kalender"', false);
                $response->assertSee('data-calendar-create', false)
                    ->assertSee('data-calendar-active="false"', false)
                    ->assertSee('data-period-options=', false)
                    ->assertSee('data-status-url-template="'.route('academic-calendars.status', '__CALENDAR__').'"', false);
            } else {
                $response->assertDontSee('aria-label="Ubah tanggal kalender"', false);
                $response->assertDontSee('aria-label="Aktifkan kalender"', false);
            }
        }

        $this->signIn('pimfak', ['id_fakultas' => 'fak-1']);
        $this->get(route('academic-calendars.index'))
            ->assertOk()
            ->assertSee('data-datatable="academic-calendar"', false)
            ->assertSee('Program Studi Satu')
            ->assertSee('Program Studi Tiga')
            ->assertDontSee('Program Studi Dua');

        $this->signIn('userprodi', ['id_prodi' => 'prodi-2']);
        $this->get(route('academic-calendars.index'))
            ->assertOk()
            ->assertSee('Program Studi Dua')
            ->assertDontSee('Program Studi Satu')
            ->assertDontSee('Program Studi Tiga')
            ->assertSee('aria-label="Ubah tanggal kalender"', false)
            ->assertDontSee('aria-label="Aktifkan kalender"', false)
            ->assertDontSee('data-calendar-create', false);

        $this->signIn('usertu', ['id_prodi' => 'prodi-2']);
        $this->get(route('academic-calendars.index'))
            ->assertOk()
            ->assertSee('Program Studi Dua')
            ->assertDontSee('Program Studi Satu')
            ->assertDontSee('Program Studi Tiga')
            ->assertDontSee('aria-label="Ubah tanggal kalender"', false)
            ->assertDontSee('aria-label="Aktifkan kalender"', false);

        $this->signIn('userprodi', ['id_prodi' => null]);
        $this->get(route('academic-calendars.index'))
            ->assertOk()
            ->assertDontSee('Program Studi Satu')
            ->assertDontSee('Program Studi Dua')
            ->assertDontSee('Program Studi Tiga');
    }

    public function test_admin_creates_calendars_for_active_programs_and_skips_duplicates(): void
    {
        $this->seedProgram('prodi-1', 'fak-1', 'Program Satu');
        $this->seedProgram('prodi-2', 'fak-2', 'Program Dua');
        $this->seedProgram('prodi-nonaktif', 'fak-2', 'Program Nonaktif', 'N');

        $startYear = now()->year - 1;
        $yearCode = $startYear.'1';
        $this->seedCalendar('existing-calendar', 'prodi-1', 'fak-1', $yearCode);
        $this->signIn('admin');

        $payload = [
            'periode' => $startYear.'/'.($startYear + 1).' Ganjil',
            'tgl_mulai_krs' => '2026-01-10',
            'tgl_akhir_krs' => '2026-02-10',
            'tgl_mulai_isinilai' => '2026-05-01',
            'tgl_akhir_isinilai' => '2026-06-01',
        ];

        $this->post(route('academic-calendars.store'), $payload)
            ->assertRedirect(route('academic-calendars.index'))
            ->assertSessionHas('status', '1 kalender akademik berhasil dibuat.');

        $newCalendar = DB::connection('simak_main')->table('s1m4k_kalender')
            ->where('id_prodi', 'prodi-2')
            ->where('tahun', $yearCode)
            ->first();

        $this->assertNotNull($newCalendar);
        $this->assertSame('N', $newCalendar->status);
        $this->assertSame($payload['periode'], $newCalendar->periode);
        $this->assertSame('fak-2', $newCalendar->id_fakultas);
        $this->assertSame('pt-1', $newCalendar->id_perguruan_tinggi);
        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_kalender')
            ->where('id_prodi', 'prodi-nonaktif')->count());

        $this->post(route('academic-calendars.store'), $payload)
            ->assertSessionHas('status', 'Tidak ada kalender baru. Semua prodi aktif sudah memiliki periode tersebut.');
    }

    public function test_regular_calendar_excludes_sp_periods_and_options(): void
    {
        $this->seedProgram('prodi-1', 'fak-1', 'Program Satu');
        $this->seedCalendar('regular-calendar', 'prodi-1', 'fak-1', '20261');
        $this->seedShortSemesterCalendar('sp-calendar', 'prodi-1', 'fak-1', '20263');
        $this->seedShortSemesterCalendar('sp-pendek-calendar', 'prodi-1', 'fak-1', '20253', 'N', '2024/2025 Pendek');
        $this->signIn('admin');

        $this->get(route('academic-calendars.index'))
            ->assertOk()
            ->assertSee('regular-calendar')
            ->assertDontSee('sp-calendar')
            ->assertDontSee('sp-pendek-calendar')
            ->assertDontSee('2025/2026 SP');

        $this->get(route('academic-calendars.create'))
            ->assertOk()
            ->assertDontSee('2025/2026 SP');

        $this->post(route('academic-calendars.store'), [
            'periode' => '2025/2026 SP',
            'tgl_mulai_krs' => '2025-08-01',
            'tgl_akhir_krs' => '2025-09-01',
            'tgl_mulai_isinilai' => '2025-12-01',
            'tgl_akhir_isinilai' => '2026-01-01',
        ])->assertSessionHasErrors('periode');
    }

    public function test_short_semester_curriculum_uses_its_own_rows_options_and_active_status(): void
    {
        $this->seedProgram('prodi-1', 'fak-1', 'Program Satu');
        $this->seedProgram('prodi-2', 'fak-2', 'Program Dua');
        $startYear = now()->year - 1;
        $yearCode = $startYear.'3';
        $period = $startYear.'/'.($startYear + 1).' SP';
        $periodLabel = "SP {$startYear} / ".($startYear + 1).' (Periode SP Tahun '.($startYear + 1).')';
        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'pt-1',
            'alamat_feeder' => 'https://feeder.example.test',
            'user_feeder' => 'feeder-user',
            'password_feeder' => '',
        ]);
        app(LegacyFeederService::class)->updateFeederCredentials('feeder-user', 'feeder-password');
        $this->seedCalendar('regular-calendar', 'prodi-1', 'fak-1', $yearCode, 'Y');
        $this->seedShortSemesterCalendar('existing-sp-calendar', 'prodi-1', 'fak-1', $yearCode);
        $this->seedShortSemesterCalendar('older-sp-calendar', 'prodi-1', 'fak-1', ($startYear - 1).'3');
        $feederRows = [
            [
                'id_kurikulum' => 'feeder-sp-prodi-1',
                'nama_kurikulum' => 'Kurikulum SP Program Satu',
                'id_prodi' => 'prodi-1',
                'id_semester' => $yearCode,
                'semester_mulai_berlaku' => $startYear.'/'.($startYear + 1).' Pendek',
                'jumlah_sks_lulus' => '144',
                'jumlah_sks_wajib' => '108',
                'jumlah_sks_pilihan' => '36',
            ],
            [
                'id_kurikulum' => 'feeder-sp-prodi-1-second',
                'nama_kurikulum' => 'Kurikulum SP Kedua Program Satu',
                'id_prodi' => 'prodi-1',
                'id_semester' => $yearCode,
                'semester_mulai_berlaku' => $startYear.'/'.($startYear + 1).' Pendek',
                'jumlah_sks_lulus' => '120',
                'jumlah_sks_wajib' => '100',
                'jumlah_sks_pilihan' => '20',
            ],
        ];
        Http::fake(function (ClientRequest $request) use (&$feederRows, $yearCode) {
            $action = $request['act'] ?? '';
            if ($action === 'GetToken') {
                return Http::response(['error_code' => '0', 'data' => ['token' => 'sp-create-token']]);
            }
            if ($action === 'GetListKurikulum') {
                $programId = str_contains((string) ($request['filter'] ?? ''), "id_prodi = 'prodi-1'")
                    ? 'prodi-1'
                    : 'prodi-2';
                $this->assertSame("id_semester = '{$yearCode}' AND id_prodi = '{$programId}'", $request['filter'] ?? null);
                $this->assertSame(1, $request['limit'] ?? null);
                $rows = array_values(array_filter(
                    $feederRows,
                    static fn (array $row): bool => $row['id_semester'] === $yearCode
                        && $row['id_prodi'] === $programId
                ));
                $rows = array_slice($rows, 0, 1);

                return Http::response(['error_code' => '0', 'data' => $rows]);
            }
            if ($action === 'InsertKurikulum') {
                $feederRows[] = [
                    ...$request['record'],
                    'id_kurikulum' => 'feeder-sp-prodi-2',
                ];

                return Http::response(['error_code' => '0', 'data' => ['id_kurikulum' => 'feeder-sp-prodi-2']]);
            }

            return Http::response(['error_code' => '1', 'error_desc' => 'Unexpected action']);
        });
        $this->signIn('admin');

        $this->get(route('short-semester-curricula.index'))
            ->assertOk()
            ->assertSee('Kurikulum SP')
            ->assertSee('existing-sp-calendar')
            ->assertDontSee('regular-calendar')
            ->assertSee('data-calendar-create', false)
            ->assertSee('Buat Periode SP')
            ->assertSee('Seluruh proses perubahan semester pendek secara realtime terintegrasi dengan feeder sehingga tidak perlu dilakukan export feeder maupun penginputan secara manual di feeder.')
            ->assertViewHas('periodOptions', function (array $options) use ($period, $periodLabel, $yearCode): bool {
                $this->assertSame([$period], array_keys($options));
                $this->assertSame($periodLabel, $options[$period]['label']);
                $this->assertSame($yearCode, $options[$period]['year']);

                return true;
            });

        $dates = [
            'tgl_mulai_krs' => '2026-06-01',
            'tgl_akhir_krs' => '2026-06-15',
            'tgl_mulai_isinilai' => '2026-07-01',
            'tgl_akhir_isinilai' => '2026-07-15',
        ];
        $this->postJson(route('short-semester-curricula.store'), ['periode' => $period, ...$dates, 'program_offset' => 0])
            ->assertOk()
            ->assertJsonPath('progress.processed', 1)
            ->assertJsonPath('progress.total', 2)
            ->assertJsonPath('progress.complete', false)
            ->assertJsonPath('counts.curricula_created', 1)
            ->assertJsonPath('counts.calendars_created', 1)
            ->assertJsonCount(1, 'calendars');
        $this->postJson(route('short-semester-curricula.store'), ['periode' => $period, ...$dates, 'program_offset' => 1])
            ->assertOk()
            ->assertJsonPath('progress.processed', 2)
            ->assertJsonPath('progress.total', 2)
            ->assertJsonPath('progress.complete', true)
            ->assertJsonPath('counts.curricula_created', 1)
            ->assertJsonPath('counts.calendars_created', 1)
            ->assertJsonCount(1, 'calendars');
        $this->postJson(route('short-semester-curricula.store'), ['periode' => $period, ...$dates, 'program_offset' => 0])
            ->assertOk()
            ->assertJsonPath('progress.complete', false)
            ->assertJsonPath('counts.curricula_updated', 1)
            ->assertJsonPath('counts.calendars_updated', 1)
            ->assertJsonCount(1, 'calendars');
        $this->postJson(route('short-semester-curricula.store'), ['periode' => $period, ...$dates, 'program_offset' => 1])
            ->assertOk()
            ->assertJsonPath('progress.complete', true)
            ->assertJsonPath('counts.curricula_updated', 1)
            ->assertJsonPath('counts.calendars_updated', 1)
            ->assertJsonCount(1, 'calendars');
        $this->post(route('short-semester-curricula.store'), [
            'periode' => $startYear.'/'.($startYear + 1).' Ganjil',
            ...$dates,
        ])->assertSessionHasErrors('periode');

        $newSpCalendar = DB::connection('simak_main')->table('s1m4k_kalender')
            ->where('id_kalender', 'feeder-sp-prodi-2')
            ->first();
        $this->assertNotNull($newSpCalendar);
        $this->assertSame('prodi-2', $newSpCalendar->id_prodi);
        $this->assertSame($startYear.'/'.($startYear + 1).' Pendek', $newSpCalendar->periode);
        $this->assertSame('N', $newSpCalendar->status);
        $this->assertSame('N', $newSpCalendar->status_sp);
        $this->assertSame($periodLabel, $newSpCalendar->namatahun);
        $this->assertSame($yearCode, $newSpCalendar->tahun);
        $this->assertDatabaseHas('s1m4k_kurikulum', [
            'id_kurikulum' => 'feeder-sp-prodi-2',
            'nama_kurikulum' => 'SEMESTER PENDEK '.($startYear + 1),
            'id_prodi' => 'prodi-2',
            'id_semester' => $yearCode,
            'semester_mulai_berlaku' => $startYear.'/'.($startYear + 1).' Pendek',
            'jumlah_sks_lulus' => '120',
            'jumlah_sks_wajib' => '100',
            'jumlah_sks_pilihan' => '20',
        ], 'simak_main');
        $this->assertDatabaseHas('s1m4k_kalender', [
            'id_kalender' => 'feeder-sp-prodi-1',
            'id_prodi' => 'prodi-1',
            'tahun' => $yearCode,
        ], 'simak_main');
        $this->assertDatabaseHas('s1m4k_kurikulum', [
            'id_kurikulum' => 'feeder-sp-prodi-1',
            'nama_kurikulum' => 'Kurikulum SP Program Satu',
            'id_prodi' => 'prodi-1',
            'id_semester' => $yearCode,
            'jumlah_sks_lulus' => '144',
            'jumlah_sks_wajib' => '108',
            'jumlah_sks_pilihan' => '36',
        ], 'simak_main');
        Http::assertNotSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListKurikulum'
            && ($request['filter'] ?? '') === "id_semester='{$yearCode}'");
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListKurikulum'
            && ($request['filter'] ?? '') === "id_semester = '{$yearCode}' AND id_prodi = 'prodi-1'"
            && ($request['limit'] ?? null) === 1);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListKurikulum'
            && ($request['filter'] ?? '') === "id_semester = '{$yearCode}' AND id_prodi = 'prodi-2'"
            && ($request['limit'] ?? null) === 1);
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'InsertKurikulum'
            && ($request['record']['nama_kurikulum'] ?? '') === 'SEMESTER PENDEK '.($startYear + 1)
            && ($request['record']['id_prodi'] ?? '') === 'prodi-2'
            && ($request['record']['id_semester'] ?? '') === $yearCode
            && ($request['record']['jumlah_sks_lulus'] ?? null) === 120
            && ($request['record']['jumlah_sks_wajib'] ?? null) === 100
            && ($request['record']['jumlah_sks_pilihan'] ?? null) === 20);
        $this->assertDatabaseHas('s1m4k_kalender', [
            'id_kalender' => 'regular-calendar',
            'status' => 'Y',
            'status_sp' => 'N',
        ], 'simak_main');

        $this->postJson(route('short-semester-curricula.status', 'existing-sp-calendar'), ['active' => true])
            ->assertOk()
            ->assertJsonPath('statuses.0.active', true);
        $this->assertDatabaseHas('s1m4k_kalender', [
            'id_kalender' => 'existing-sp-calendar',
            'status' => 'N',
            'status_sp' => 'Y',
        ], 'simak_main');
        $this->assertDatabaseHas('s1m4k_kalender', [
            'id_kalender' => 'older-sp-calendar',
            'status' => 'N',
            'status_sp' => 'N',
        ], 'simak_main');
        $this->assertDatabaseHas('s1m4k_kalender', [
            'id_kalender' => 'regular-calendar',
            'status' => 'Y',
            'status_sp' => 'N',
        ], 'simak_main');

        $this->post(route('short-semester-curricula.status', 'regular-calendar'), ['active' => true])
            ->assertNotFound();
        $this->post(route('academic-calendars.status', 'existing-sp-calendar'), ['active' => false])
            ->assertNotFound();
        $this->postJson(route('academic-calendars.status', 'regular-calendar'), ['active' => true])
            ->assertOk();
        $this->assertDatabaseHas('s1m4k_kalender', [
            'id_kalender' => 'regular-calendar',
            'status' => 'Y',
            'status_sp' => 'N',
        ], 'simak_main');
        $this->assertDatabaseHas('s1m4k_kalender', [
            'id_kalender' => 'existing-sp-calendar',
            'status' => 'N',
            'status_sp' => 'Y',
        ], 'simak_main');
    }

    public function test_short_semester_form_surfaces_feeder_insert_failure(): void
    {
        $this->seedProgram('prodi-1', 'fak-1', 'Program Satu');
        $this->seedFeederCredentials();
        Http::fake(function (ClientRequest $request) {
            return match ($request['act'] ?? '') {
                'GetToken' => Http::response(['error_code' => '0', 'data' => ['token' => 'sp-failure-token']]),
                'GetListKurikulum' => Http::response(['error_code' => '0', 'data' => []]),
                'InsertKurikulum' => Http::response(['error_code' => '1', 'error_desc' => 'Insert ditolak']),
                default => Http::response(['error_code' => '1', 'error_desc' => 'Unexpected action']),
            };
        });
        $this->signIn('admin');

        $year = now()->year;
        $this->postJson(route('short-semester-curricula.store'), [
            'periode' => ($year - 1).'/'.$year.' SP',
            'tgl_mulai_krs' => '2026-06-01',
            'tgl_akhir_krs' => '2026-06-15',
            'tgl_mulai_isinilai' => '2026-07-01',
            'tgl_akhir_isinilai' => '2026-07-15',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Feeder gagal (1): Insert ditolak');

        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_kurikulum')->count());
        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_kalender')->count());
    }

    public function test_admin_syncs_short_semester_curricula_and_calendars_from_feeder(): void
    {
        $this->seedProgram('prodi-1', 'fak-1', 'Program Satu');
        $this->seedProgram('prodi-2', 'fak-2', 'Program Dua');
        $this->seedShortSemesterCalendar(
            'curriculum-existing',
            'prodi-1',
            'fak-1',
            '20263',
            'Y',
            '2025/2026 Pendek'
        );
        DB::connection('simak_main')->table('s1m4k_kalender')
            ->where('id_kalender', 'curriculum-existing')
            ->update(['status' => 'Y']);
        DB::connection('simak_main')->table('s1m4k_kurikulum')->insert([
            'id_kurikulum' => 'curriculum-existing',
            'nama_kurikulum' => 'Nama Sebelum',
            'id_prodi' => 'prodi-1',
            'id_semester' => '20263',
            'semester_mulai_berlaku' => '2025/2026 SP',
            'jumlah_sks_lulus' => '120',
            'jumlah_sks_wajib' => '90',
            'jumlah_sks_pilihan' => '30',
            'jumlah_sks_mata_kuliah_wajib' => '88',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'pt-1',
            'alamat_feeder' => 'https://feeder.example.test',
            'user_feeder' => 'feeder-user',
            'password_feeder' => '',
        ]);
        app(LegacyFeederService::class)->updateFeederCredentials('feeder-user', 'feeder-password');

        $feederRows = [
            [
                'id_kurikulum' => 'curriculum-existing',
                'nama_kurikulum' => 'Kurikulum SP Baru',
                'id_prodi' => 'prodi-1',
                'id_semester' => '20263',
                'semester_mulai_berlaku' => '2025/2026 Pendek',
                'jumlah_sks_lulus' => '144',
                'jumlah_sks_wajib' => '108',
                'jumlah_sks_pilihan' => '36',
            ],
            [
                'id_kurikulum' => 'curriculum-second',
                'nama_kurikulum' => 'Kurikulum SP Kedua Prodi Satu',
                'id_prodi' => 'prodi-1',
                'id_semester' => '20263',
                'semester_mulai_berlaku' => '2025/2026 Pendek',
                'jumlah_sks_lulus' => '144',
                'jumlah_sks_wajib' => '108',
                'jumlah_sks_pilihan' => '36',
            ],
            [
                'id_kurikulum' => 'curriculum-new',
                'nama_kurikulum' => 'Kurikulum SP Prodi Dua',
                'id_prodi' => 'prodi-2',
                'id_semester' => '20263',
                'semester_mulai_berlaku' => '2025/2026 Pendek',
                'jumlah_sks_lulus' => '144',
                'jumlah_sks_wajib' => '108',
                'jumlah_sks_pilihan' => '36',
            ],
            [
                'id_kurikulum' => 'curriculum-regular',
                'nama_kurikulum' => 'Kurikulum Reguler',
                'id_prodi' => 'prodi-2',
                'id_semester' => '20261',
                'semester_mulai_berlaku' => '2025/2026 Ganjil',
                'jumlah_sks_lulus' => '144',
                'jumlah_sks_wajib' => '108',
                'jumlah_sks_pilihan' => '36',
            ],
        ];
        Http::fake(function (ClientRequest $request) use ($feederRows) {
            return match ($request['act'] ?? '') {
                'GetToken' => Http::response(['error_code' => '0', 'data' => ['token' => 'sp-test-token']]),
                'GetListKurikulum' => Http::response(['error_code' => '0', 'data' => $feederRows]),
                default => Http::response(['error_code' => '1', 'error_desc' => 'Unexpected action']),
            };
        });
        $this->signIn('admin');

        $this->get(route('short-semester-curricula.index'))
            ->assertOk()
            ->assertSee('Sinkron Kurikulum SP');

        $this->post(route('short-semester-curricula.sync'))
            ->assertRedirect(route('short-semester-curricula.index'))
            ->assertSessionHas('status', 'Sinkronisasi selesai: kurikulum 2 baru/1 diperbarui; kalender SP 2 baru/1 diperbarui; 1 data dilewati.');
        $this->get(route('short-semester-curricula.index'))
            ->assertOk()
            ->assertSee('class="module-alert calendar-sync-alert" role="status" aria-live="polite"', false)
            ->assertSee('Sinkronisasi selesai: kurikulum 2 baru/1 diperbarui; kalender SP 2 baru/1 diperbarui; 1 data dilewati.');

        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetListKurikulum'
            && ($request['filter'] ?? '') === "semester_mulai_berlaku LIKE '%Pendek'"
            && ($request['limit'] ?? null) === ''
            && ($request['offset'] ?? null) === 0);

        $this->assertSame(3, DB::connection('simak_main')->table('s1m4k_kurikulum')->count());
        $this->assertDatabaseHas('s1m4k_kurikulum', [
            'id_kurikulum' => 'curriculum-existing',
            'nama_kurikulum' => 'Kurikulum SP Baru',
            'id_semester' => '20263',
            'semester_mulai_berlaku' => '2025/2026 Pendek',
            'jumlah_sks_lulus' => '144',
        ], 'simak_main');
        $this->assertDatabaseHas('s1m4k_kurikulum', [
            'id_kurikulum' => 'curriculum-existing',
            'jumlah_sks_mata_kuliah_wajib' => '88',
        ], 'simak_main');
        $this->assertDatabaseMissing('s1m4k_kurikulum', ['id_kurikulum' => 'curriculum-regular'], 'simak_main');

        $this->assertSame(3, DB::connection('simak_main')->table('s1m4k_kalender')->count());
        $this->get(route('short-semester-curricula.index'))
            ->assertOk()
            ->assertSee('2025/2026 Pendek');
        $this->assertDatabaseHas('s1m4k_kalender', [
            'id_kalender' => 'curriculum-existing',
            'id_perguruan_tinggi' => 'pt-1',
            'id_fakultas' => 'fak-1',
            'id_prodi' => 'prodi-1',
            'periode' => '2025/2026 Pendek',
            'namatahun' => '2025/2026 Pendek - SP',
            'tahun' => '20263',
            'status' => 'Y',
            'status_sp' => 'Y',
        ], 'simak_main');
        $this->assertDatabaseHas('s1m4k_kurikulum', [
            'id_kurikulum' => 'curriculum-existing',
            'id_prodi' => 'prodi-1',
            'id_semester' => '20263',
        ], 'simak_main');
        $createdCalendar = DB::connection('simak_main')->table('s1m4k_kalender')
            ->where('id_kalender', 'curriculum-new')->first();
        $this->assertNotNull($createdCalendar);
        $this->assertSame('prodi-2', $createdCalendar->id_prodi);
        $this->assertSame('2025/2026 Pendek', $createdCalendar->periode);
        $this->assertSame('2025/2026 Pendek - SP', $createdCalendar->namatahun);
        $this->assertSame('N', $createdCalendar->status);
        $this->assertSame('N', $createdCalendar->status_sp);
        $this->assertDatabaseHas('s1m4k_kalender', [
            'id_kalender' => 'curriculum-second',
            'id_prodi' => 'prodi-1',
            'periode' => '2025/2026 Pendek',
            'tahun' => '20263',
        ], 'simak_main');

        $this->post(route('short-semester-curricula.sync'))
            ->assertSessionHas('status', 'Sinkronisasi selesai: kurikulum 0 baru/3 diperbarui; kalender SP 0 baru/3 diperbarui; 1 data dilewati.');
        $this->assertSame(3, DB::connection('simak_main')->table('s1m4k_kalender')->count());
    }

    public function test_only_admin_can_sync_short_semester_curricula(): void
    {
        $this->signIn('userprodi');

        $this->get(route('short-semester-curricula.index'))
            ->assertOk()
            ->assertDontSee('Sinkron Kurikulum SP');
        $this->post(route('short-semester-curricula.sync'))->assertForbidden();
    }

    public function test_only_admin_can_create_calendars(): void
    {
        $this->signIn('userprodi');

        $this->get(route('academic-calendars.create'))->assertForbidden();
        $this->post(route('academic-calendars.store'), [])->assertForbidden();
    }

    public function test_admin_and_program_manager_can_edit_dates_with_program_scope(): void
    {
        $this->seedProgram('prodi-1', 'fak-1', 'Program Satu');
        $this->seedProgram('prodi-2', 'fak-2', 'Program Dua');
        $this->seedCalendar('calendar-1', 'prodi-1', 'fak-1', '20251');
        $this->seedCalendar('calendar-2', 'prodi-2', 'fak-2', '20251');
        $this->signIn('admin');
        $this->get(route('academic-calendars.edit', 'calendar-1'))
            ->assertOk()
            ->assertSee('Tahun Akademik 2025/2026 Ganjil');

        $dates = [
            'tgl_mulai_krs' => '2025-08-01',
            'tgl_akhir_krs' => '2025-09-01',
            'tgl_mulai_isinilai' => '2025-12-01',
            'tgl_akhir_isinilai' => '2026-01-01',
        ];

        $this->put(route('academic-calendars.update', 'calendar-1'), $dates)
            ->assertRedirect(route('academic-calendars.index'));

        $this->assertDatabaseHas('s1m4k_kalender', array_merge(['id_kalender' => 'calendar-1'], $dates), 'simak_main');

        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        $this->get(route('academic-calendars.edit', 'calendar-1'))
            ->assertOk()
            ->assertSee('Tahun Akademik 2025/2026 Ganjil');
        $this->get(route('academic-calendars.edit', 'calendar-2'))->assertNotFound();
        $this->put(route('academic-calendars.update', 'calendar-1'), $dates)
            ->assertRedirect(route('academic-calendars.index'));
        $this->put(route('academic-calendars.update', 'calendar-2'), $dates)->assertNotFound();

        $this->signIn('usertu', ['id_prodi' => 'prodi-1']);
        $this->get(route('academic-calendars.edit', 'calendar-1'))->assertForbidden();
        $this->put(route('academic-calendars.update', 'calendar-1'), $dates)->assertForbidden();
    }

    public function test_faculty_leader_without_faculty_assignment_sees_no_calendars(): void
    {
        $this->seedProgram('prodi-1', 'fak-1', 'Program Fakultas Terikat');
        $this->seedCalendar('calendar-assigned', 'prodi-1', 'fak-1', '20251');
        $this->seedCalendar('calendar-no-faculty', 'prodi-1', '', '20252');
        $this->signIn('pimfak', ['id_fakultas' => null]);

        $this->get(route('academic-calendars.index'))
            ->assertOk()
            ->assertDontSee('Program Fakultas Terikat')
            ->assertDontSee('calendar-assigned')
            ->assertDontSee('calendar-no-faculty');

        $this->signIn('pimfak', ['id_fakultas' => '   ']);
        $this->get(route('academic-calendars.index'))
            ->assertOk()
            ->assertDontSee('Program Fakultas Terikat');
    }

    public function test_admin_activation_is_exclusive_within_a_program(): void
    {
        $this->seedProgram('prodi-1', 'fak-1', 'Program Satu');
        $this->seedProgram('prodi-2', 'fak-2', 'Program Dua');
        $this->seedCalendar('calendar-1', 'prodi-1', 'fak-1', '20251', 'Y');
        $this->seedCalendar('calendar-2', 'prodi-1', 'fak-1', '20252', 'N');
        $this->seedCalendar('calendar-3', 'prodi-2', 'fak-2', '20251', 'Y');

        $this->signIn('userprodi');
        $this->post(route('academic-calendars.activate', 'calendar-2'))->assertForbidden();

        $this->signIn('admin');
        $this->post(route('academic-calendars.activate', 'calendar-2'))
            ->assertRedirect(route('academic-calendars.index'));

        $this->assertDatabaseHas('s1m4k_kalender', ['id_kalender' => 'calendar-1', 'status' => 'N'], 'simak_main');
        $this->assertDatabaseHas('s1m4k_kalender', ['id_kalender' => 'calendar-2', 'status' => 'Y'], 'simak_main');
        $this->assertDatabaseHas('s1m4k_kalender', ['id_kalender' => 'calendar-3', 'status' => 'Y'], 'simak_main');
    }

    public function test_admin_can_confirmed_toggle_calendar_status_without_affecting_other_programs(): void
    {
        $this->seedProgram('prodi-1', 'fak-1', 'Program Satu');
        $this->seedProgram('prodi-2', 'fak-2', 'Program Dua');
        $this->seedCalendar('calendar-1', 'prodi-1', 'fak-1', '20251', 'Y');
        $this->seedCalendar('calendar-2', 'prodi-1', 'fak-1', '20252', 'N');
        $this->seedCalendar('calendar-3', 'prodi-2', 'fak-2', '20251', 'Y');
        $this->signIn('admin');

        $this->postJson(route('academic-calendars.status', 'calendar-2'), ['active' => true])
            ->assertOk()
            ->assertJsonPath('statuses.0.active', false)
            ->assertJsonPath('statuses.1.active', true);

        $this->assertDatabaseHas('s1m4k_kalender', ['id_kalender' => 'calendar-1', 'status' => 'N'], 'simak_main');
        $this->assertDatabaseHas('s1m4k_kalender', ['id_kalender' => 'calendar-2', 'status' => 'Y'], 'simak_main');
        $this->assertDatabaseHas('s1m4k_kalender', ['id_kalender' => 'calendar-3', 'status' => 'Y'], 'simak_main');

        $this->postJson(route('academic-calendars.status', 'calendar-2'), ['active' => false])
            ->assertOk()
            ->assertJsonPath('statuses.0.active', false)
            ->assertJsonPath('statuses.1.active', false);

        $this->assertDatabaseHas('s1m4k_kalender', ['id_kalender' => 'calendar-1', 'status' => 'N'], 'simak_main');
        $this->assertDatabaseHas('s1m4k_kalender', ['id_kalender' => 'calendar-2', 'status' => 'N'], 'simak_main');
        $this->assertDatabaseHas('s1m4k_kalender', ['id_kalender' => 'calendar-3', 'status' => 'Y'], 'simak_main');
    }

    public function test_calendar_status_toggle_requires_admin_and_a_valid_target_state(): void
    {
        $this->seedCalendar('calendar-1', 'prodi-1', 'fak-1', '20251');
        $this->signIn('userprodi');

        $this->postJson(route('academic-calendars.status', 'calendar-1'), ['active' => true])->assertForbidden();

        $this->signIn('admin');
        $this->postJson(route('academic-calendars.status', 'calendar-1'), ['active' => 'sometimes'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('active');

        $this->postJson(route('academic-calendars.status', 'missing-calendar'), ['active' => true])
            ->assertNotFound();
    }

    public function test_calendar_create_and_edit_json_return_rows_for_live_updates(): void
    {
        $this->seedProgram('prodi-1', 'fak-1', 'Program Studi Satu');
        $this->signIn('admin');
        $startYear = now()->year - 1;
        $period = $startYear.'/'.($startYear + 1).' Ganjil';
        $dates = [
            'tgl_mulai_krs' => '2026-01-10',
            'tgl_akhir_krs' => '2026-02-10',
            'tgl_mulai_isinilai' => '2026-05-01',
            'tgl_akhir_isinilai' => '2026-06-01',
        ];

        $created = $this->postJson(route('academic-calendars.store'), ['periode' => $period, ...$dates])
            ->assertOk()
            ->assertJsonPath('message', '1 kalender akademik berhasil dibuat.')
            ->assertJsonPath('calendars.0.nama_program_studi', 'Program Studi Satu');

        $calendarId = $created->json('calendars.0.id_kalender');
        $updatedDates = array_merge($dates, ['tgl_mulai_krs' => '2026-01-15']);

        $this->putJson(route('academic-calendars.update', $calendarId), $updatedDates)
            ->assertOk()
            ->assertJsonPath('calendar.id_kalender', $calendarId)
            ->assertJsonPath('calendar.tgl_mulai_krs', '2026-01-15');
        $this->assertSame(2, DB::connection('simak_main')->table('log_aktivitas')->count());
        $this->assertSame('Kalender Akademik', DB::connection('simak_main')->table('log_aktivitas')->first()->modul);
    }

    public function test_roles_outside_the_legacy_calendar_dispatcher_are_denied(): void
    {
        $this->signIn('mahasiswa');

        $this->get(route('academic-calendars.index'))->assertForbidden();
        $this->get(route('short-semester-curricula.index'))->assertForbidden();
    }

    public function test_calendar_list_contains_all_scoped_rows_for_client_side_search(): void
    {
        $this->seedProgram('prodi-1', 'fak-1', 'Program Studi Satu');

        foreach (range(1, 51) as $index) {
            DB::connection('simak_main')->table('s1m4k_kalender')->insert([
                'id_kalender' => 'calendar-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'id_prodi' => 'prodi-1',
                'tahun' => (string) (1900 + $index),
                'namatahun' => 'Kalender Uji '.$index,
                'status' => 'N',
            ]);
        }

        $this->signIn('admin');

        $this->get(route('academic-calendars.index'))
            ->assertOk()
            ->assertDontSee('Navigasi halaman kalender')
            ->assertSee('Kalender Uji 51')
            ->assertSee('Kalender Uji 1');
    }

    private function signIn(string $role, array $attributes = []): void
    {
        $this->actingAs(new GenericUser(array_merge([
            'id' => $role.'-test-user',
            'legacy_type' => 'admin',
            'legacy_id' => $role.'-test-user',
            'login' => $role.'-test-user',
            'role' => $role,
            'name' => 'Pengguna Uji',
            'email' => 'calendar@example.test',
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'fak-1',
            'password' => 'not-used',
            'remember_token' => null,
        ], $attributes)));
    }

    private function seedProgram(string $id, string $faculty, string $name, string $status = 'A'): void
    {
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            'id_prodi' => $id,
            'id_perguruan_tinggi' => 'pt-1',
            'id_fakultas' => $faculty,
            'nama_program_studi' => $name,
            'nama_jenjang_pendidikan' => 'S1',
            'status' => $status,
        ]);
    }

    private function seedFeederCredentials(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'pt-1',
            'alamat_feeder' => 'https://feeder.example.test',
            'user_feeder' => 'feeder-user',
            'password_feeder' => '',
        ]);
        app(LegacyFeederService::class)->updateFeederCredentials('feeder-user', 'feeder-password');
    }

    private function seedCalendar(string $id, string $program, string $faculty, string $year, string $status = 'N'): void
    {
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            'id_kalender' => $id,
            'id_perguruan_tinggi' => 'pt-1',
            'id_fakultas' => $faculty,
            'id_prodi' => $program,
            'periode' => '2025/2026 Ganjil',
            'tahun' => $year,
            'namatahun' => 'Tahun Akademik 2025/2026 Ganjil',
            'tgl_mulai_krs' => '2025-08-01',
            'tgl_akhir_krs' => '2025-09-01',
            'tgl_mulai_isinilai' => '2025-12-01',
            'tgl_akhir_isinilai' => '2026-01-01',
            'status' => $status,
        ]);
    }

    private function seedShortSemesterCalendar(
        string $id,
        string $program,
        string $faculty,
        string $year,
        string $statusSp = 'N',
        string $periode = '2025/2026 SP'
    ): void {
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            'id_kalender' => $id,
            'id_perguruan_tinggi' => 'pt-1',
            'id_fakultas' => $faculty,
            'id_prodi' => $program,
            'periode' => $periode,
            'tahun' => $year,
            'namatahun' => 'Tahun Akademik '.$periode,
            'tgl_mulai_krs' => '2025-08-01',
            'tgl_akhir_krs' => '2025-09-01',
            'tgl_mulai_isinilai' => '2025-12-01',
            'tgl_akhir_isinilai' => '2026-01-01',
            'status' => 'N',
            'status_sp' => $statusSp,
        ]);
    }

    private function createSchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table) {
            $table->string('id_prodi')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('nama_program_studi')->nullable();
            $table->string('nama_jenjang_pendidikan')->nullable();
            $table->string('status')->nullable();
        });

        Schema::connection('simak_main')->create('s1m4k_masterpt', function (Blueprint $table) {
            $table->string('id_perguruan_tinggi')->primary();
            $table->string('alamat_feeder')->nullable();
            $table->string('user_feeder')->nullable();
            $table->text('password_feeder')->nullable();
        });

        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table) {
            $table->string('id_kalender', 50)->primary();
            $table->string('periode', 20)->nullable();
            $table->string('tahun', 5)->nullable();
            $table->string('namatahun', 100)->nullable();
            $table->string('id_perguruan_tinggi', 50)->nullable();
            $table->string('id_fakultas', 50)->nullable();
            $table->string('id_prodi', 50)->nullable();
            $table->date('tgl_mulai_krs')->nullable();
            $table->date('tgl_akhir_krs')->nullable();
            $table->date('tgl_mulai_isinilai')->nullable();
            $table->date('tgl_akhir_isinilai')->nullable();
            $table->string('status', 1)->default('N')->nullable();
            $table->string('status_sp', 1)->default('N');
        });
        Schema::connection('simak_main')->create('s1m4k_kurikulum', function (Blueprint $table) {
            $table->string('id_kurikulum', 100)->primary();
            $table->string('nama_kurikulum', 100)->nullable();
            $table->string('id_prodi', 100)->nullable();
            $table->string('id_semester', 5)->nullable();
            $table->string('semester_mulai_berlaku', 50)->nullable();
            $table->string('jumlah_sks_lulus', 10)->nullable();
            $table->string('jumlah_sks_wajib', 10)->nullable();
            $table->string('jumlah_sks_pilihan', 10)->nullable();
            $table->string('jumlah_sks_mata_kuliah_wajib', 10)->nullable();
            $table->string('jumlah_sks_mata_kuliah_pilihan', 10)->nullable();
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
}
