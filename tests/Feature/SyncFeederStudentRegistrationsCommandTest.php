<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SyncFeederStudentRegistrationsCommandTest extends TestCase
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
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim')->nullable();
            $table->string('nama_mahasiswa')->nullable();
            $table->string('id_status_mahasiswa')->nullable();
            $table->string('nama_status_mahasiswa')->nullable();
            $table->string('id_registrasi_mahasiswa')->nullable();
        });

        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'pt-test',
            'alamat_feeder' => 'https://feeder.example.test',
            'user_feeder' => 'feeder-user',
            'password_feeder' => $this->encrypt('feeder-secret'),
        ]);
    }

    public function test_command_updates_only_registration_id_from_feeder_per_student(): void
    {
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-a', 'nim' => 'NIM-A', 'nama_mahasiswa' => 'Mahasiswa A', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'id_registrasi_mahasiswa' => 'old-a'],
            ['id_mahasiswa' => 'student-c', 'nim' => 'NIM-C', 'nama_mahasiswa' => 'Mahasiswa C', 'id_status_mahasiswa' => 'C', 'nama_status_mahasiswa' => 'Cuti', 'id_registrasi_mahasiswa' => 'reg-c'],
            ['id_mahasiswa' => 'student-m', 'nim' => 'NIM-M', 'nama_mahasiswa' => 'Mahasiswa M', 'id_status_mahasiswa' => 'M', 'nama_status_mahasiswa' => 'Kampus Merdeka', 'id_registrasi_mahasiswa' => null],
            ['id_mahasiswa' => 'student-n', 'nim' => 'NIM-N', 'nama_mahasiswa' => 'Mahasiswa N', 'id_status_mahasiswa' => 'N', 'nama_status_mahasiswa' => 'Non-Aktif', 'id_registrasi_mahasiswa' => null],
            ['id_mahasiswa' => 'student-empty-registration', 'nim' => 'NIM-EMPTY-REG', 'nama_mahasiswa' => 'Mahasiswa Tanpa Registrasi', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'id_registrasi_mahasiswa' => 'stale-id'],
            ['id_mahasiswa' => 'student-empty-name', 'nim' => 'NIM-EMPTY-NAME', 'nama_mahasiswa' => '', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'id_registrasi_mahasiswa' => null],
            ['id_mahasiswa' => 'student-null-name', 'nim' => 'NIM-NULL-NAME', 'nama_mahasiswa' => null, 'id_status_mahasiswa' => 'N', 'nama_status_mahasiswa' => 'Non-Aktif', 'id_registrasi_mahasiswa' => null],
            ['id_mahasiswa' => 'student-empty-nim', 'nim' => '', 'nama_mahasiswa' => 'Tanpa NIM', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'id_registrasi_mahasiswa' => null],
            ['id_mahasiswa' => 'student-null-nim', 'nim' => null, 'nama_mahasiswa' => 'NIM NULL', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'id_registrasi_mahasiswa' => null],
            ['id_mahasiswa' => 'student-x', 'nim' => 'NIM-X', 'nama_mahasiswa' => 'Mahasiswa X', 'id_status_mahasiswa' => 'X', 'nama_status_mahasiswa' => 'Lulus', 'id_registrasi_mahasiswa' => null],
        ]);

        Http::fake(function ($request) {
            return match ($request['act'] ?? '') {
                'GetToken' => Http::response(['error_code' => '0', 'data' => ['token' => 'student-token']]),
                'GetListMahasiswa' => match ($request['filter'] ?? '') {
                    "nim='NIM-A'" => Http::response(['error_code' => '0', 'data' => [[
                        'nim' => 'NIM-A',
                        'id_mahasiswa' => 'student-a',
                        'id_registrasi_mahasiswa' => 'reg-a',
                        'id_status_mahasiswa' => 'N',
                        'nama_status_mahasiswa' => 'Non-Aktif',
                    ]]]),
                    "nim='NIM-C'" => Http::response(['error_code' => '0', 'data' => [[
                        'nim' => 'NIM-C',
                        'id_mahasiswa' => 'student-c',
                        'id_registrasi_mahasiswa' => 'reg-c',
                        'id_status_mahasiswa' => 'C',
                        'nama_status_mahasiswa' => 'Cuti',
                    ]]]),
                    "nim='NIM-M'" => Http::response(['error_code' => '0', 'data' => [[
                        'nim' => 'NIM-M',
                        'id_mahasiswa' => 'student-m',
                        'id_registrasi_mahasiswa' => 'reg-m',
                        'id_status_mahasiswa' => 'G',
                        'nama_status_mahasiswa' => 'Lulus',
                    ]]]),
                    "nim='NIM-EMPTY-REG'" => Http::response(['error_code' => '0', 'data' => [[
                        'nim' => 'NIM-EMPTY-REG',
                        'id_mahasiswa' => 'student-empty-registration',
                        'id_registrasi_mahasiswa' => '',
                        'id_status_mahasiswa' => 'A',
                        'nama_status_mahasiswa' => 'Aktif',
                    ]]]),
                    default => Http::response(['error_code' => '0', 'data' => []]),
                },
                default => Http::response(['error_code' => '1', 'error_desc' => 'Unexpected action']),
            };
        });

        $this->artisan('siakad:sync-feeder-student-registrations')
            ->expectsOutputToContain('Memulai sinkronisasi 5 mahasiswa.')
            ->expectsOutputToContain('Progres: 5/5 selesai; 3 diperbarui, 1 tidak berubah, 1 gagal.')
            ->expectsOutputToContain('5 diperiksa, 3 diperbarui, 1 tidak berubah, 1 gagal.')
            ->expectsOutputToContain('NIM NIM-N gagal:')
            ->assertExitCode(1);

        $this->assertDatabaseHas('s1m4k_mastermhs', [
            'id_mahasiswa' => 'student-a',
            'id_registrasi_mahasiswa' => 'reg-a',
            'id_status_mahasiswa' => 'A',
            'nama_status_mahasiswa' => 'Aktif',
        ], 'simak_main');
        $this->assertDatabaseHas('s1m4k_mastermhs', [
            'id_mahasiswa' => 'student-m',
            'id_registrasi_mahasiswa' => 'reg-m',
            'id_status_mahasiswa' => 'M',
            'nama_status_mahasiswa' => 'Kampus Merdeka',
        ], 'simak_main');
        $this->assertDatabaseHas('s1m4k_mastermhs', [
            'id_mahasiswa' => 'student-x',
            'id_registrasi_mahasiswa' => null,
            'id_status_mahasiswa' => 'X',
        ], 'simak_main');
        $this->assertDatabaseHas('s1m4k_mastermhs', [
            'id_mahasiswa' => 'student-empty-registration',
            'id_registrasi_mahasiswa' => null,
            'id_status_mahasiswa' => 'A',
            'nama_status_mahasiswa' => 'Aktif',
        ], 'simak_main');

        Http::assertSentCount(6);
        Http::assertSent(fn ($request): bool => ($request['act'] ?? '') === 'GetListMahasiswa'
            && ($request['filter'] ?? '') === "nim='NIM-A'"
            && ! array_key_exists('order', $request->data())
            && ($request['limit'] ?? null) === '1'
            && ($request['offset'] ?? null) === 0
            && ($request['token'] ?? null) === 'student-token');
    }

    public function test_student_registration_command_is_scheduled_after_the_lecturer_sync(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains($event->command, 'siakad:sync-feeder-student-registrations'));

        $this->assertNotNull($event);
        $this->assertSame('30 0 * * *', $event->expression);
        $this->assertSame('Asia/Jayapura', $event->timezone);
    }

    public function test_command_continues_after_a_failed_nim_lookup_and_reports_the_failure(): void
    {
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-rejected',
            'nim' => 'NIM-REJECTED',
            'nama_mahasiswa' => 'Mahasiswa ditolak',
            'id_status_mahasiswa' => 'A',
            'nama_status_mahasiswa' => 'Aktif',
            'id_registrasi_mahasiswa' => null,
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-after',
            'nim' => 'NIM-AFTER',
            'nama_mahasiswa' => 'Mahasiswa setelah error',
            'id_status_mahasiswa' => 'A',
            'nama_status_mahasiswa' => 'Aktif',
            'id_registrasi_mahasiswa' => null,
        ]);
        Http::fake(function ($request) {
            return match ($request['act'] ?? '') {
                'GetToken' => Http::response(['error_code' => '0', 'data' => ['token' => 'student-token']]),
                'GetListMahasiswa' => match ($request['filter'] ?? '') {
                    "nim='NIM-REJECTED'" => Http::response([
                        'error_code' => '121',
                        'error_desc' => 'Error database. Periksa kembali parameter yang dikirim',
                    ]),
                    "nim='NIM-AFTER'" => Http::response(['error_code' => '0', 'data' => [[
                        'nim' => 'NIM-AFTER',
                        'id_mahasiswa' => 'student-after',
                        'id_registrasi_mahasiswa' => 'registration-after',
                        'id_status_mahasiswa' => 'A',
                        'nama_status_mahasiswa' => 'Aktif',
                    ]]]),
                    default => Http::response(['error_code' => '0', 'data' => []]),
                },
                default => Http::response(['error_code' => '1', 'error_desc' => 'Unexpected action']),
            };
        });

        $this->artisan('siakad:sync-feeder-student-registrations')
            ->expectsOutputToContain('2 diperiksa, 1 diperbarui, 0 tidak berubah, 1 gagal.')
            ->expectsOutputToContain('NIM NIM-REJECTED gagal:')
            ->assertExitCode(1);
        $this->assertDatabaseHas('s1m4k_mastermhs', [
            'id_mahasiswa' => 'student-after',
            'id_registrasi_mahasiswa' => 'registration-after',
        ], 'simak_main');
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
