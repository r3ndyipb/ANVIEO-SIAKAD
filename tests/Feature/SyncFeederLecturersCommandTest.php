<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SyncFeederLecturersCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'database.connections.simak_main' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);
        DB::purge('simak_main');

        Schema::connection('simak_main')->create('s1m4k_masterpt', function (Blueprint $table): void {
            $table->string('id_perguruan_tinggi')->primary();
            $table->string('alamat_feeder');
            $table->string('user_feeder');
            $table->string('password_feeder');
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_fakultas')->nullable();
            $table->string('id_perguruan_tinggi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterfakultas', function (Blueprint $table): void {
            $table->string('id_fakultas')->primary();
            $table->string('nama_fakultas')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('id_registrasi_dosen')->nullable();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('nidn')->nullable();
            $table->string('nama_dosen')->nullable();
            $table->string('email')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('id_tahun_ajaran')->nullable();
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
            $table->string('nip')->nullable();
            $table->string('nuptk')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->string('id_agama')->nullable();
            $table->string('tanggal_lahir')->nullable();
            $table->string('id_status_aktif')->nullable();
            $table->string('nama_status_aktif')->nullable();
            $table->string('nomor_surat_tugas')->nullable();
            $table->string('tanggal_surat_tugas')->nullable();
            $table->string('mulai_surat_tugas')->nullable();
            $table->integer('flag_feeder')->nullable();
            $table->string('password')->nullable();
        });

        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'pt-dynamic',
            'alamat_feeder' => 'https://feeder.example.test',
            'user_feeder' => 'feeder-user',
            'password_feeder' => $this->encrypt('feeder-secret'),
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'prodi-1', 'id_fakultas' => 'fak-1', 'id_perguruan_tinggi' => 'pt-1'],
            ['id_prodi' => 'prodi-2', 'id_fakultas' => 'fak-2', 'id_perguruan_tinggi' => 'pt-1'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterfakultas')->insert([
            ['id_fakultas' => 'fak-1', 'nama_fakultas' => 'Fakultas 1'],
            ['id_fakultas' => 'fak-2', 'nama_fakultas' => 'Fakultas 2'],
        ]);
    }

    public function test_command_syncs_full_feeder_roster_without_resetting_existing_credentials(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            'id_dosen' => 'local-existing',
            'nidn' => 'NIDN-EXIST',
            'nama_dosen' => 'Nama Lama',
            'email' => 'local@example.test',
            'gelar_depan' => 'Dr.',
            'gelar_belakang' => 'M.Kom.',
            'password' => Hash::make('password-lama'),
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            'id_dosen' => 'not-in-feeder',
            'nidn' => 'NIDN-LOCAL',
            'nama_dosen' => 'Dosen Lokal',
            'password' => Hash::make('password-lokal'),
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            'id_dosen' => 'local-no-homebase-update',
            'nidn' => 'NIDN-EXISTING-NO-HOMEBASE',
            'id_prodi' => 'prodi-2',
            'id_fakultas' => 'fak-2',
            'nama_dosen' => 'Nama Lokal',
            'password' => Hash::make('password-preserved'),
        ]);

        Http::fake(function ($request) {
            return match ($request['act'] ?? '') {
                'GetToken' => Http::response(['error_code' => '0', 'data' => ['token' => 'nightly-token']]),
                'GetListDosen' => Http::response([
                    'error_code' => '0',
                    'data' => [
                        [
                            'id_dosen' => 'feeder-existing', 'nidn' => 'NIDN-EXIST', 'nip' => '198001',
                            'nuptk' => 'NUPTK-1', 'nama_dosen' => 'Nama Biodata', 'email' => 'feeder@example.test',
                            'jenis_kelamin' => 'P', 'id_agama' => '1', 'tanggal_lahir' => '1980-01-02',
                            'id_status_aktif' => '1', 'nama_status_aktif' => 'Aktif',
                        ],
                        [
                            'id_dosen' => 'feeder-new', 'nidn' => 'NIDN-NEW', 'nip' => '199002',
                            'nama_dosen' => 'Dosen Baru', 'id_status_aktif' => '1',
                        ],
                        [
                            'id_dosen' => 'feeder-nip-only', 'nidn' => '', 'nip' => 'NIP-ONLY',
                            'nama_dosen' => 'Dosen NIP', 'id_status_aktif' => '1',
                        ],
                        [
                            'id_dosen' => 'feeder-no-homebase', 'nidn' => 'NIDN-NO-HOMEBASE',
                            'nama_dosen' => 'Dosen Tanpa Homebase',
                        ],
                        [
                            'id_dosen' => 'feeder-existing-no-homebase', 'nidn' => 'NIDN-EXISTING-NO-HOMEBASE',
                            'nama_dosen' => 'Dosen Existing Tanpa Homebase',
                        ],
                        [
                            'id_dosen' => 'feeder-no-identifier', 'nidn' => '', 'nip' => '',
                            'nama_dosen' => 'Dosen Tanpa Identitas Login',
                        ],
                        [
                            'id_dosen' => 'feeder-fallback-homebase', 'nidn' => 'NIDN-FALLBACK',
                            'nama_dosen' => 'Dosen Homebase Fallback',
                        ],
                    ],
                ]),
                'GetListPenugasanDosen' => Http::response([
                    'error_code' => '0',
                    'data' => [
                        [
                            'id_dosen' => 'feeder-existing', 'id_registrasi_dosen' => 'reg-current',
                            'id_prodi' => 'prodi-1', 'id_perguruan_tinggi' => 'pt-1',
                            'id_tahun_ajaran' => '20261', 'nama_dosen' => 'Nama Penugasan',
                            'nomor_surat_tugas' => 'ST-1', 'tanggal_surat_tugas' => '2026-01-02',
                            'mulai_surat_tugas' => '2026-01-03',
                        ],
                        [
                            'id_dosen' => 'feeder-existing', 'id_registrasi_dosen' => 'reg-old',
                            'id_prodi' => 'prodi-2', 'id_perguruan_tinggi' => 'pt-1',
                            'id_tahun_ajaran' => '20251',
                        ],
                        [
                            'id_dosen' => 'feeder-new', 'id_registrasi_dosen' => 'reg-new',
                            'id_prodi' => 'prodi-2', 'id_perguruan_tinggi' => 'pt-1',
                            'id_tahun_ajaran' => '20261',
                        ],
                        [
                            'id_dosen' => 'feeder-nip-only', 'id_registrasi_dosen' => 'reg-nip',
                            'id_prodi' => 'prodi-1', 'id_perguruan_tinggi' => 'pt-1',
                            'id_tahun_ajaran' => '20261',
                        ],
                        [
                            'id_dosen' => 'feeder-no-homebase', 'id_registrasi_dosen' => 'reg-no-homebase',
                            'id_prodi' => 'prodi-not-in-local', 'id_perguruan_tinggi' => 'pt-1',
                            'id_tahun_ajaran' => '20261',
                        ],
                        [
                            'id_dosen' => 'feeder-no-identifier', 'id_registrasi_dosen' => 'reg-no-id',
                            'id_prodi' => 'prodi-1', 'id_perguruan_tinggi' => 'pt-1',
                            'id_tahun_ajaran' => '20261',
                        ],
                        [
                            'id_dosen' => 'feeder-fallback-homebase',
                            'id_registrasi_dosen' => 'reg-unmapped',
                            'id_prodi' => 'prodi-not-imported',
                            'id_perguruan_tinggi' => 'pt-1',
                            'id_tahun_ajaran' => '20271',
                        ],
                        [
                            'id_dosen' => 'feeder-fallback-homebase',
                            'id_registrasi_dosen' => 'reg-fallback',
                            'id_prodi' => 'prodi-2',
                            'id_perguruan_tinggi' => 'pt-1',
                            'id_tahun_ajaran' => '20261',
                        ],
                    ],
                ]),
                default => Http::response(['error_code' => '1', 'error_desc' => 'Unexpected action']),
            };
        });

        $this->artisan('siakad:sync-feeder-lecturers')
            ->expectsOutput('Sinkronisasi dosen selesai: 6 tersinkron (4 baru, 2 diperbarui), 2 tanpa homebase SIMAK, 1 dilewati (1 tanpa NIDN/NIP).')
            ->assertExitCode(0);

        $existing = DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->where('id_dosen', 'local-existing')->first();
        $this->assertSame('reg-current', $existing->id_registrasi_dosen);
        $this->assertSame('pt-dynamic', $existing->id_perguruan_tinggi);
        $this->assertSame('prodi-1', $existing->id_prodi);
        $this->assertSame('fak-1', $existing->id_fakultas);
        $this->assertSame('Nama Penugasan', $existing->nama_dosen);
        $this->assertSame('NUPTK-1', $existing->nuptk);
        $this->assertSame('local@example.test', $existing->email);
        $this->assertSame('Dr.', $existing->gelar_depan);
        $this->assertSame('M.Kom.', $existing->gelar_belakang);
        $this->assertTrue(Hash::check('password-lama', $existing->password));

        $new = DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->where('id_dosen', 'feeder-new')->first();
        $this->assertSame('NIDN-NEW', $new->nidn);
        $this->assertSame('pt-dynamic', $new->id_perguruan_tinggi);
        $this->assertTrue(Hash::check('dosenNIDN-NEW', $new->password));
        $nipOnly = DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->where('id_dosen', 'feeder-nip-only')->first();
        $this->assertSame('NIP-ONLY', $nipOnly->nidn);
        $this->assertSame('pt-dynamic', $nipOnly->id_perguruan_tinggi);
        $this->assertSame('NIP-ONLY', $nipOnly->nip);
        $this->assertTrue(Hash::check('dosenNIP-ONLY', $nipOnly->password));
        $fallbackHomebase = DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->where('id_dosen', 'feeder-fallback-homebase')->first();
        $this->assertSame('reg-fallback', $fallbackHomebase->id_registrasi_dosen);
        $this->assertSame('prodi-2', $fallbackHomebase->id_prodi);
        $this->assertSame('fak-2', $fallbackHomebase->id_fakultas);
        $this->assertSame('20261', $fallbackHomebase->id_tahun_ajaran);
        $withoutHomebase = DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->where('id_dosen', 'feeder-no-homebase')->first();
        $this->assertSame('pt-dynamic', $withoutHomebase->id_perguruan_tinggi);
        $this->assertNull($withoutHomebase->id_prodi);
        $this->assertNull($withoutHomebase->id_fakultas);
        $this->assertNull($withoutHomebase->id_tahun_ajaran);
        $this->assertTrue(Hash::check('dosenNIDN-NO-HOMEBASE', $withoutHomebase->password));
        $updatedWithoutHomebase = DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->where('id_dosen', 'local-no-homebase-update')->first();
        $this->assertSame('prodi-2', $updatedWithoutHomebase->id_prodi);
        $this->assertSame('fak-2', $updatedWithoutHomebase->id_fakultas);
        $this->assertSame('Dosen Existing Tanpa Homebase', $updatedWithoutHomebase->nama_dosen);
        $this->assertTrue(Hash::check('password-preserved', $updatedWithoutHomebase->password));
        $this->assertDatabaseHas('s1m4k_masterdosen', ['id_dosen' => 'not-in-feeder'], 'simak_main');
        $this->assertDatabaseHas('s1m4k_masterdosen', ['id_dosen' => 'feeder-no-homebase'], 'simak_main');
        $this->assertDatabaseMissing('s1m4k_masterdosen', ['id_dosen' => 'feeder-no-identifier'], 'simak_main');
        Http::assertSentCount(3);
        Http::assertSent(fn ($request): bool => ($request['act'] ?? '') === 'GetListDosen'
            && ($request['limit'] ?? null) === 0
            && ($request['token'] ?? null) === 'nightly-token');
        Http::assertSent(fn ($request): bool => ($request['act'] ?? '') === 'GetListPenugasanDosen'
            && ($request['order'] ?? '') === 'id_tahun_ajaran DESC'
            && ($request['limit'] ?? null) === 0
            && ($request['token'] ?? null) === 'nightly-token');
    }

    public function test_command_fails_instead_of_reporting_success_for_empty_feeder_roster(): void
    {
        Http::fake(fn ($request) => match ($request['act'] ?? '') {
            'GetToken' => Http::response(['error_code' => '0', 'data' => ['token' => 'nightly-token']]),
            'GetListDosen' => Http::response(['error_code' => '0', 'data' => []]),
            default => Http::response(['error_code' => '1', 'error_desc' => 'Unexpected action']),
        });

        $this->artisan('siakad:sync-feeder-lecturers')
            ->expectsOutputToContain('Feeder tidak mengembalikan data dosen')
            ->assertExitCode(1);
    }

    public function test_daily_schedule_runs_at_midnight_in_simak_timezone(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains($event->command, 'siakad:sync-feeder-lecturers'));

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
        $this->assertSame('Asia/Jayapura', $event->timezone);
    }

    public function test_command_skips_when_another_sync_holds_the_lock(): void
    {
        $lock = Cache::lock('simak:sync-feeder-lecturers', 21600);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('siakad:sync-feeder-lecturers')
                ->expectsOutput('Sinkronisasi dosen Feeder lain masih berjalan; proses ini dilewati.')
                ->assertExitCode(0);
        } finally {
            $lock->release();
        }

        Http::assertNothingSent();
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
