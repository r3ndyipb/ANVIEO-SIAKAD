<?php

namespace Tests\Feature;

use App\Services\ActivityLogger;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ActivityLogTest extends TestCase
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
        Schema::connection('simak_main')->create('log_aktivitas', function (Blueprint $table): void {
            $table->string('id_log', 36)->primary();
            $table->string('id_user', 100)->nullable();
            $table->string('level_user', 50)->nullable();
            $table->string('nama_user', 255);
            $table->string('modul', 100);
            $table->longText('aktifitas');
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::connection('simak_main')->create('simak_activity_log_reads', function (Blueprint $table): void {
            $table->string('recipient_role', 30);
            $table->string('recipient_id', 100);
            $table->string('activity_id', 36);
            $table->timestamp('read_at');
            $table->primary(['recipient_role', 'recipient_id', 'activity_id']);
        });
    }

    public function test_lms_filter_includes_historical_mobile_lms_activity(): void
    {
        DB::connection('simak_main')->table('log_aktivitas')->insert([
            [
                'id_log' => 'web-lms-log',
                'id_user' => 'dosen-1',
                'level_user' => 'dosen',
                'nama_user' => 'Dosen Web',
                'modul' => 'LMS',
                'aktifitas' => '{"aksi":"Membuat tugas"}',
                'created_at' => '2026-10-30 10:00:00',
            ],
            [
                'id_log' => 'mobile-lms-log',
                'id_user' => 'dosen-2',
                'level_user' => 'dosen',
                'nama_user' => 'Dosen Mobile LMS',
                'modul' => 'SIMAK Mobile',
                'aktifitas' => '{"aksi":"Mobile POST lms.tasks.submit","perubahan":{"route":"lms.tasks.submit","method":"POST","channel":"mobile"}}',
                'created_at' => '2026-10-30 11:00:00',
            ],
            [
                'id_log' => 'mobile-other-log',
                'id_user' => 'dosen-3',
                'level_user' => 'dosen',
                'nama_user' => 'Dosen Mobile Non-LMS',
                'modul' => 'SIMAK Mobile',
                'aktifitas' => '{"aksi":"Mobile GET khs.index","perubahan":{"route":"khs.index","method":"GET"}}',
                'created_at' => '2026-10-30 12:00:00',
            ],
        ]);
        $this->actingAs($this->user('admin'));

        $this->get(route('activity-logs.index', ['module' => 'LMS']))
            ->assertOk()
            ->assertSee('Dosen Web')
            ->assertSee('Dosen Mobile LMS')
            ->assertSee('LMS (Mobile)')
            ->assertDontSee('Dosen Mobile Non-LMS');
    }

    public function test_admin_can_review_full_activity_details_and_filter_logs(): void
    {
        DB::connection('simak_main')->table('log_aktivitas')->insert([
            [
                'id_log' => 'log-new',
                'id_user' => 'admin-1',
                'level_user' => 'admin',
                'nama_user' => 'Admin Audit',
                'modul' => 'Data Mahasiswa',
                'aktifitas' => json_encode([
                    'aksi' => 'Ubah data',
                    'id_data' => 'mhs-1',
                    'identitas_data' => [
                        'jenis' => 'Mahasiswa',
                        'nama' => 'Nama Baru',
                        'nomor_identitas' => '0123456789',
                    ],
                    'perubahan' => [
                        'nama_mahasiswa' => [
                            'sebelum' => 'Nama Lama',
                            'sesudah' => 'Nama Baru',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
                'created_at' => '2026-10-20 10:30:45',
            ],
            [
                'id_log' => 'log-old',
                'id_user' => 'user-2',
                'level_user' => 'userprodi',
                'nama_user' => 'Petugas Prodi',
                'modul' => 'Jadwal Kuliah',
                'aktifitas' => '{"aksi":"Tambah jadwal"}',
                'created_at' => '2026-10-19 09:00:00',
            ],
        ]);
        $this->actingAs($this->user('admin'));

        $this->get(route('activity-logs.index'))
            ->assertOk()
            ->assertSee('Tanggal & waktu (WIT)')
            ->assertSee('Admin Audit')
            ->assertSee('20/10/2026 10:30:45')
            ->assertSee('Nama Lama')
            ->assertSee('Nama Baru')
            ->assertSee('0123456789')
            ->assertSee('Petugas Prodi');

        $this->get(route('activity-logs.index', [
            'module' => 'Data Mahasiswa',
            'search' => 'Admin Audit',
            'from' => '2026-10-20',
            'to' => '2026-10-20',
        ]))
            ->assertOk()
            ->assertSee('Admin Audit')
            ->assertDontSee('Petugas Prodi');
    }

    public function test_activity_log_pagination_uses_datatables_style_and_keeps_filters(): void
    {
        $rows = [];
        for ($index = 1; $index <= 51; $index++) {
            $rows[] = [
                'id_log' => 'pagination-'.$index,
                'id_user' => 'admin-1',
                'level_user' => 'admin',
                'nama_user' => 'Admin Audit',
                'modul' => 'Audit',
                'aktifitas' => '{"aksi":"Perubahan"}',
                'created_at' => sprintf('2026-10-%02d 10:00:00', (($index - 1) % 28) + 1),
            ];
        }
        DB::connection('simak_main')->table('log_aktivitas')->insert($rows);
        $this->actingAs($this->user('admin'));

        $this->get(route('activity-logs.index', ['module' => 'Audit', 'search' => 'Admin Audit']))
            ->assertOk()
            ->assertSee('dt-paging')
            ->assertSee('module=Audit&amp;search=Admin%20Audit&amp;page=2', false);
    }

    public function test_activity_logs_are_not_available_to_non_admin_roles(): void
    {
        $this->actingAs($this->user('regulang'));

        $this->get(route('activity-logs.index'))->assertForbidden();
    }

    public function test_activity_notifications_are_private_and_have_independent_read_state(): void
    {
        DB::connection('simak_main')->table('log_aktivitas')->insert([
            [
                'id_log' => 'lecturer-log-new',
                'id_user' => 'dosen-audit-test',
                'level_user' => 'dosen',
                'nama_user' => 'Dosen Uji',
                'modul' => 'Bimbingan Skripsi',
                'aktifitas' => '{"aksi":"Membalas pesan"}',
                'created_at' => '2026-10-20 10:30:45',
            ],
            [
                'id_log' => 'lecturer-log-old',
                'id_user' => 'dosen-audit-test',
                'level_user' => 'dosen',
                'nama_user' => 'Dosen Uji',
                'modul' => 'Ujian Skripsi',
                'aktifitas' => '{"aksi":"Menyimpan nilai"}',
                'created_at' => '2026-10-19 09:00:00',
            ],
            [
                'id_log' => 'other-user-log',
                'id_user' => 'other-lecturer',
                'level_user' => 'dosen',
                'nama_user' => 'Dosen Lain',
                'modul' => 'Bimbingan Skripsi',
                'aktifitas' => '{"aksi":"Aktivitas pribadi"}',
                'created_at' => '2026-10-20 11:00:00',
            ],
        ]);
        $this->actingAs($this->user('dosen'));

        $this->getJson(route('activity-notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonCount(2, 'notifications')
            ->assertJsonPath('notifications.0.title', 'Bimbingan Skripsi')
            ->assertJsonPath('notifications.0.message', 'Membalas pesan');

        $this->patchJson(route('activity-notifications.read', 'lecturer-log-new'))
            ->assertOk();
        $this->getJson(route('activity-notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.is_read', true);
        $this->patchJson(route('activity-notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
        $this->getJson(route('activity-notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
        $this->patchJson(route('activity-notifications.read', 'other-user-log'))
            ->assertNotFound();
    }

    public function test_activity_history_remains_available_without_optional_read_state_table(): void
    {
        DB::connection('simak_main')->table('log_aktivitas')->insert([
            'id_log' => 'activity-without-read-table',
            'id_user' => 'dosen-audit-test',
            'level_user' => 'dosen',
            'nama_user' => 'Dosen Uji',
            'modul' => 'Ujian Skripsi',
            'aktifitas' => '{"aksi":"Menyimpan nilai"}',
            'created_at' => '2026-10-20 10:30:45',
        ]);
        Schema::connection('simak_main')->drop('simak_activity_log_reads');
        $this->actingAs($this->user('dosen'));

        $this->getJson(route('activity-notifications.index'))
            ->assertOk()
            ->assertJsonPath('can_mark_read', false)
            ->assertJsonPath('unread_count', 0)
            ->assertJsonPath('notifications.0.title', 'Ujian Skripsi')
            ->assertJsonPath('notifications.0.is_read', true);
    }

    public function test_successful_module_mutation_without_explicit_logging_is_recorded_once(): void
    {
        Route::middleware(['web', 'auth'])
            ->post('/test/untracked-activity', static fn () => response()->json(['saved' => true]))
            ->name('test.untracked-activity');
        $this->actingAs($this->user('userprodi'));

        $this->postJson('/test/untracked-activity', [
            'private_field' => 'must not be logged',
        ])->assertOk()->assertJsonPath('saved', true);

        $this->assertDatabaseCount('log_aktivitas', 1, 'simak_main');
        $activity = DB::connection('simak_main')->table('log_aktivitas')->first();
        $this->assertSame('userprodi', $activity->level_user);
        $this->assertSame('userprodi-audit-test', $activity->id_user);
        $this->assertSame('Test', $activity->modul);
        $this->assertStringContainsString('test.untracked-activity', $activity->aktifitas);
        $this->assertStringNotContainsString('must not be logged', $activity->aktifitas);
    }

    public function test_lms_activity_logs_specific_action_record_and_submitted_data(): void
    {
        Route::middleware(['web', 'auth'])
            ->post('/test/lms-audit/{record}', static fn () => response()->json(['saved' => true]))
            ->name('lms.meetings.update');
        $this->actingAs($this->user('dosen'));

        $this->postJson('/test/lms-audit/meeting-audit-1', [
            'judul' => 'Pertemuan audit',
            'status_pertemuan' => 'terbit',
            'jawaban' => ['detail-a' => 'jawaban privat'],
            'isi_pesan' => 'pesan privat',
            'kunci_jawaban' => 'A',
            '_token' => 'must-not-be-logged',
        ])->assertOk();

        $this->assertDatabaseCount('log_aktivitas', 1, 'simak_main');
        $activity = DB::connection('simak_main')->table('log_aktivitas')->first();
        $details = json_decode($activity->aktifitas, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('LMS', $activity->modul);
        $this->assertSame('Memperbarui pertemuan kuliah', $details['aksi']);
        $this->assertSame('meeting-audit-1', $details['id_data']);
        $this->assertSame('lms.meetings.update', $details['perubahan']['route']);
        $this->assertSame('terbit', $details['perubahan']['input']['status_pertemuan']);
        $this->assertTrue($details['perubahan']['input']['kunci_jawaban']);
        $this->assertSame(1, $details['perubahan']['input']['jawaban']['jumlah_item']);
        $this->assertSame(14, $details['perubahan']['input']['jawaban']['jumlah_karakter']);
        $this->assertSame(12, $details['perubahan']['input']['isi_pesan']['jumlah_karakter']);
        $this->assertSame('Pertemuan kuliah', $details['perubahan']['entitas']);
        $this->assertArrayNotHasKey('_token', $details['perubahan']['input']);
        $this->assertStringNotContainsString('jawaban privat', $activity->aktifitas);
        $this->assertStringNotContainsString('pesan privat', $activity->aktifitas);
        $this->assertStringNotContainsString('must-not-be-logged', $activity->aktifitas);
    }

    public function test_lms_material_download_is_logged_without_logging_page_views(): void
    {
        Route::middleware(['web', 'auth'])
            ->get('/test/lms-material-download/{record}', static fn () => response()->streamDownload(
                static fn () => print 'materi',
                'materi.pdf',
            ))
            ->name('lms.materials.download');
        Route::middleware(['web', 'auth'])
            ->get('/test/lms-page-view', static fn () => response('halaman'))
            ->name('lms.materials.index');
        $this->actingAs($this->user('mahasiswa'));

        $this->get('/test/lms-material-download/material-1')->assertOk();
        $this->get('/test/lms-page-view')->assertOk();

        $this->assertDatabaseCount('log_aktivitas', 1, 'simak_main');
        $activity = DB::connection('simak_main')->table('log_aktivitas')->first();
        $details = json_decode($activity->aktifitas, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Mengunduh materi LMS', $details['aksi']);
        $this->assertSame('material-1', $details['id_data']);
        $this->assertSame('lms.materials.download', $details['perubahan']['route']);
    }

    public function test_logger_keeps_business_values_and_only_records_credential_change_status(): void
    {
        app(ActivityLogger::class)->record($this->user('admin'), 'Data Mahasiswa', 'Ubah data', [
            'nama_mahasiswa' => ['sebelum' => 'Nama Lama', 'sesudah' => 'Nama Baru'],
            'password' => ['sebelum' => 'plain-text-password', 'sesudah' => 'hashed-password'],
        ], 'mhs-1', [
            'jenis' => 'Mahasiswa',
            'nama' => 'Nama Baru',
            'nomor_identitas' => '0123456789',
        ]);

        $activity = DB::connection('simak_main')->table('log_aktivitas')->first();
        $details = json_decode($activity->aktifitas, true, flags: JSON_THROW_ON_ERROR);
        $changes = $details['perubahan'];

        $this->assertSame(['sebelum' => 'Nama Lama', 'sesudah' => 'Nama Baru'], $changes['nama_mahasiswa']);
        $this->assertSame('Nama Baru', $details['identitas_data']['nama']);
        $this->assertSame('0123456789', $details['identitas_data']['nomor_identitas']);
        $this->assertTrue($changes['password']);
        $this->assertStringNotContainsString('plain-text-password', $activity->aktifitas);
        $this->assertStringNotContainsString('hashed-password', $activity->aktifitas);
    }

    private function user(string $role): GenericUser
    {
        return new GenericUser([
            'id' => $role.'-audit-test',
            'legacy_id' => $role.'-audit-test',
            'legacy_type' => $role,
            'role' => $role,
            'login' => $role.'-audit-test',
            'name' => 'Pengguna Uji',
        ]);
    }
}
