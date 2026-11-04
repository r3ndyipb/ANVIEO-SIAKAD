<?php

namespace Tests\Feature;

use App\Mail\LegacyPasswordResetMail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
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
        $this->createLegacySchema();
    }

    public function test_login_page_is_available_and_dashboard_requires_authentication(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('target="_blank"', false)
            ->assertViewHas('landingData', fn (array $data): bool => $data['openLogin']);
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_login_error_uses_a_non_overlapping_accessible_toast(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), ['login' => 'unknown-user', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('login');

        $response = $this->get(route('login'))->assertOk();
        $landingData = $response->viewData('landingData');
        $this->assertTrue($landingData['openLogin']);
        $this->assertNotEmpty($landingData['loginErrors']['login']);
        $response->assertSee('data-auth-notification', false)
            ->assertDontSee('data-flash-toast', false);
        $response->assertSee('"icon":"error"', false);
    }

    public function test_login_validation_errors_also_use_the_authentication_alert(): void
    {
        $this->post(route('login.store'), ['login' => '', 'password' => ''])
            ->assertSessionHasErrors(['login', 'password'])
            ->assertSessionHas('auth_notification', fn (array $notification): bool => $notification['icon'] === 'error'
                && $notification['title'] === 'Login gagal'
                && str_contains($notification['text'], 'diisi'));
    }

    public function test_all_roles_share_a_two_hour_idle_session_and_requests_are_serialized(): void
    {
        $this->assertSame('web', config('auth.defaults.guard'));
        $this->assertSame(120, config('session.lifetime'));
        $this->assertFalse(config('session.expire_on_close'));
        $this->assertTrue(config('session.block'));
        $this->assertSame(60, config('session.block_lock_seconds'));
        $this->assertSame(60, config('session.block_wait_seconds'));
    }

    public function test_each_legacy_account_type_can_sign_in_and_sign_out(): void
    {
        $accounts = [
            ['admin', 's1m4k_admin', ['id_admin' => 1, 'username' => 'operator', 'level' => 'userprodi', 'blokir' => 'N', 'nama_lengkap' => 'Operator SIMAK']],
            ['dosen', 's1m4k_masterdosen', ['id_dosen' => 'dsn-1', 'nidn' => '1001', 'nama_dosen' => 'Dosen SIMAK', 'gelar_depan' => 'Dr.', 'gelar_belakang' => 'M.Kom.']],
            ['asdos', 's1m4k_masterasdos', ['id_asdos' => 'as-1', 'kdasisten' => '2001', 'nama' => 'Asisten SIMAK']],
            ['mahasiswa', 's1m4k_mastermhs', ['id_mahasiswa' => 'mhs-1', 'nim' => '3001', 'nama_mahasiswa' => 'Mahasiswa SIMAK']],
        ];

        foreach ($accounts as [$role, $table, $account]) {
            DB::connection('simak_main')->table($table)->insert(array_merge([
                'password' => Hash::make('secret-password'),
                'email' => 'user@example.test',
                'id_prodi' => 'prodi-1',
                'id_fakultas' => 'fak-1',
            ], $account));

            $login = $account['username'] ?? $account['nidn'] ?? $account['kdasisten'] ?? $account['nim'];

            $this->post(route('login.store'), [
                'login' => $login,
                'password' => 'secret-password',
            ])->assertRedirect(route('dashboard'))
                ->assertSessionHas('status', 'Berhasil masuk ke SIAKAD.')
                ->assertSessionHas('auth_notification', [
                    'icon' => 'success',
                    'title' => 'Login berhasil',
                    'text' => 'Selamat datang di SIAKAD.',
                ])
                ->assertSessionHas('auth_changed', true);

            $this->assertAuthenticated();
            $this->get(route('dashboard'))
                ->assertOk()
                ->assertSee('simak-auth-changed')
                ->assertSee('data-auth-notification', false)
                ->assertSee('Selamat datang di SIAKAD.')
                ->assertDontSee('Selamat datang di SIMAK.')
                ->assertSee($account['nama_lengkap'] ?? $account['nama_dosen'] ?? $account['nama'] ?? $account['nama_mahasiswa']);
            if ($role === 'dosen') {
                $this->get(route('dashboard'))->assertSee('Dr. Dosen SIMAK, M.Kom.');
            }
            $this->post(route('logout'))
                ->assertRedirect(route('public.home'))
                ->assertSessionHas('status', 'Anda berhasil keluar dari SIAKAD.')
                ->assertSessionHas('auth_notification', [
                    'icon' => 'success',
                    'title' => 'Logout berhasil',
                    'text' => 'Anda berhasil keluar dari SIAKAD.',
                ])
                ->assertSessionHas('auth_changed', true);
            $this->assertGuest();
            $this->get(route('public.home'))
                ->assertOk()
                ->assertSee('data-auth-notification', false)
                ->assertSee('Anda berhasil keluar dari SIAKAD.')
                ->assertDontSee('Anda berhasil keluar dari SIMAK.')
                ->assertDontSee('data-flash-toast', false)
                ->assertViewHas('landingData', fn (array $data): bool => $data['authChanged'] && ! $data['authenticated']);
        }
    }

    public function test_finance_account_login_redirects_to_the_finance_dashboard(): void
    {
        DB::connection('simak_main')->table('s1m4k_admin')->insert([
            'id_admin' => 15,
            'username' => 'finance-login',
            'password' => Hash::make('secret-password'),
            'level' => 'keuangan',
            'blokir' => 'N',
            'nama_lengkap' => 'Petugas Keuangan Uji',
        ]);

        $this->post(route('login.store'), [
            'login' => 'finance-login',
            'password' => 'secret-password',
        ])->assertRedirect(route('finance.dashboard'))
            ->assertSessionHas('status', 'Berhasil masuk ke SIAKAD.')
            ->assertSessionHas('auth_notification.title', 'Login berhasil')
            ->assertSessionHas('auth_changed', true);

        $this->assertAuthenticated();
    }

    public function test_blocked_admin_and_invalid_password_are_rejected(): void
    {
        DB::connection('simak_main')->table('s1m4k_admin')->insert([
            'id_admin' => 2,
            'username' => 'blocked',
            'password' => Hash::make('secret-password'),
            'level' => 'admin',
            'blokir' => 'Y',
        ]);

        $this->post(route('login.store'), [
            'login' => 'blocked',
            'password' => 'secret-password',
        ])->assertSessionHasErrors('login');
        $this->assertGuest();

        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'mhs-2',
            'nim' => '3002',
            'nama_mahasiswa' => 'Mahasiswa Uji',
            'password' => Hash::make('secret-password'),
        ]);

        $this->post(route('login.store'), [
            'login' => '3002',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_login_fails_closed_when_legacy_database_is_not_configured(): void
    {
        config(['database.connections.simak_main' => [
            'driver' => 'mysql',
            'host' => null,
            'database' => null,
            'url' => null,
            'unix_socket' => '',
        ]]);

        DB::purge('simak_main');

        $this->post(route('login.store'), [
            'login' => 'unknown-user',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), [
                'login' => 'unknown-user',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('login');
        }

        $this->post(route('login.store'), [
            'login' => 'unknown-user',
            'password' => 'wrong-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors('login')
            ->assertSessionHas('auth_notification.title', 'Login ditunda');
    }

    public function test_login_attempts_for_different_users_do_not_share_an_ip_limit(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), [
                'login' => 'first-user',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('login');
        }

        $this->post(route('login.store'), [
            'login' => 'second-user',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('login')
            ->assertSessionHas('auth_notification.title', 'Login gagal');
    }

    public function test_password_reset_sends_temporary_password_and_activates_once(): void
    {
        Mail::fake();

        DB::connection('simak_main')->table('s1m4k_admin')->insert([
            'id_admin' => 10,
            'username' => 'reset-dummy',
            'password' => Hash::make('old-password'),
            'level' => 'admin',
            'blokir' => 'N',
            'nama_lengkap' => 'Reset Dummy',
            'email' => 'reset@example.test',
        ]);

        $this->from(route('public.home'))
            ->post(route('password.email'), ['login' => 'reset-dummy'])
            ->assertRedirect(route('public.home'))
            ->assertSessionHas('reset_password_status')
            ->assertSessionHas('open_reset_password', true);

        $activationUrl = null;
        $temporaryPassword = null;
        Mail::assertSent(LegacyPasswordResetMail::class, function (LegacyPasswordResetMail $mail) use (&$activationUrl, &$temporaryPassword) {
            $activationUrl = $mail->activationUrl;
            $temporaryPassword = $mail->temporaryPassword;

            return $mail->hasTo('reset@example.test');
        });

        $pendingPassword = DB::connection('simak_main')->table('s1m4k_admin')
            ->where('username', 'reset-dummy')->value('passwordb');
        $this->assertTrue(Hash::check($temporaryPassword, $pendingPassword));

        $this->get($activationUrl)->assertOk()->assertSee('Aktifkan password');
        $this->post($activationUrl)->assertRedirect(route('login'));

        $activePassword = DB::connection('simak_main')->table('s1m4k_admin')
            ->where('username', 'reset-dummy')->value('password');
        $clearedPendingPassword = DB::connection('simak_main')->table('s1m4k_admin')
            ->where('username', 'reset-dummy')->value('passwordb');

        $this->assertTrue(Hash::check($temporaryPassword, $activePassword));
        $this->assertSame('', $clearedPendingPassword);
        $this->get($activationUrl)->assertStatus(410);
    }

    public function test_password_reset_response_does_not_reveal_unknown_accounts(): void
    {
        Mail::fake();

        $this->from(route('public.home'))
            ->post(route('password.email'), ['login' => 'not-a-real-account'])
            ->assertRedirect(route('public.home'))
            ->assertSessionHas('reset_password_status', 'Jika akun terdaftar dan memiliki email, instruksi reset akan dikirim.')
            ->assertSessionHas('open_reset_password', true);

        Mail::assertNothingSent();
    }

    public function test_password_reset_activates_accounts_from_all_legacy_tables(): void
    {
        Mail::fake();
        $this->withoutMiddleware(ThrottleRequests::class);

        $accounts = [
            ['admin', 's1m4k_admin', 'passwordb', 'username', ['id_admin' => 20, 'username' => 'reset-admin', 'level' => 'admin', 'blokir' => 'N', 'nama_lengkap' => 'Reset Admin']],
            ['dosen', 's1m4k_masterdosen', 'password_baru', 'nidn', ['id_dosen' => 'reset-dsn', 'nidn' => 'reset-dosen', 'nama_dosen' => 'Reset Dosen']],
            ['asdos', 's1m4k_masterasdos', 'passwordb', 'kdasisten', ['id_asdos' => 'reset-asd', 'kdasisten' => 'reset-asdos', 'nama' => 'Reset Asdos']],
            ['mahasiswa', 's1m4k_mastermhs', 'password_baru', 'nim', ['id_mahasiswa' => 'reset-mhs', 'nim' => 'reset-mhs', 'nama_mahasiswa' => 'Reset Mahasiswa']],
        ];

        foreach ($accounts as [$type, $table, $pendingColumn, $loginColumn, $account]) {
            $email = 'reset-'.$type.'@example.test';
            DB::connection('simak_main')->table($table)->insert(array_merge([
                'password' => Hash::make('old-password'),
                'email' => $email,
            ], $account));

            $this->from(route('public.home'))
                ->post(route('password.email'), ['login' => $account[$loginColumn]])
                ->assertRedirect(route('public.home'));

            $activationUrl = null;
            $temporaryPassword = null;
            Mail::assertSent(LegacyPasswordResetMail::class, function (LegacyPasswordResetMail $mail) use ($email, &$activationUrl, &$temporaryPassword) {
                if (! $mail->hasTo($email)) {
                    return false;
                }

                $activationUrl = $mail->activationUrl;
                $temporaryPassword = $mail->temporaryPassword;

                return true;
            });

            $this->assertNotEmpty(DB::connection('simak_main')->table($table)
                ->where($loginColumn, $account[$loginColumn])->value($pendingColumn));
            $this->post($activationUrl)->assertRedirect(route('login'));

            $activePassword = DB::connection('simak_main')->table($table)
                ->where($loginColumn, $account[$loginColumn])->value('password');
            $this->assertTrue(Hash::check($temporaryPassword, $activePassword));
            $recordId = (string) array_values($account)[0];
            $activities = DB::connection('simak_main')->table('log_aktivitas')
                ->where('id_user', $recordId)->get();
            $this->assertCount(2, $activities);
            $this->assertEqualsCanonicalizing(
                ['Permintaan reset kata sandi', 'Aktivasi kata sandi'],
                $activities->map(function (object $activity): string {
                    $decoded = json_decode($activity->aktifitas, true, flags: JSON_THROW_ON_ERROR);
                    $this->assertTrue(array_values($decoded['perubahan'])[0]);

                    return $decoded['aksi'];
                })->all()
            );
        }
    }

    public function test_expired_password_activation_link_is_rejected(): void
    {
        $expiredUrl = URL::temporarySignedRoute(
            'password.activate',
            now()->subMinute(),
            ['type' => 'admin', 'identifier' => 'not-real'],
        );

        $this->get($expiredUrl)->assertForbidden();
    }

    private function createLegacySchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_admin', function (Blueprint $table) {
            $table->bigIncrements('id_admin');
            $table->string('username');
            $table->string('password');
            $table->string('passwordb')->nullable();
            $table->string('level')->nullable();
            $table->string('blokir', 1);
            $table->string('nama_lengkap')->nullable();
            $table->string('email')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_fakultas')->nullable();
        });

        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table) {
            $table->string('id_dosen')->primary();
            $table->string('nidn');
            $table->string('password');
            $table->string('password_baru')->nullable();
            $table->string('nama_dosen')->nullable();
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
            $table->string('email')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_fakultas')->nullable();
        });

        Schema::connection('simak_main')->create('s1m4k_masterasdos', function (Blueprint $table) {
            $table->string('id_asdos')->primary();
            $table->string('kdasisten');
            $table->string('password');
            $table->string('passwordb')->nullable();
            $table->string('nama')->nullable();
            $table->string('email')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_fakultas')->nullable();
        });

        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table) {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim');
            $table->string('password');
            $table->string('password_baru')->nullable();
            $table->string('nama_mahasiswa')->nullable();
            $table->string('email')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('id_status_mahasiswa')->nullable();
            $table->string('nama_status_mahasiswa')->nullable();
            $table->string('id_periode_masuk')->nullable();
            $table->string('id_wilayah')->nullable();
        });

        Schema::connection('simak_main')->create('s1m4k_masterfakultas', function (Blueprint $table) {
            $table->string('id_fakultas')->primary();
            $table->string('nama_fakultas')->nullable();
        });

        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table) {
            $table->string('id_prodi')->primary();
            $table->string('id_fakultas')->nullable();
            $table->string('nama_program_studi')->nullable();
            $table->string('nama_jenjang_pendidikan')->nullable();
            $table->string('status')->nullable();
        });

        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table) {
            $table->string('id_matkul')->primary();
            $table->string('kode_mata_kuliah')->nullable();
            $table->string('nama_mata_kuliah')->nullable();
            $table->string('sks_mata_kuliah')->nullable();
            $table->string('id_prodi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table) {
            $table->string('id_kalender')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('tahun')->nullable();
            $table->string('namatahun')->nullable();
            $table->string('status')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_akm', function (Blueprint $table) {
            $table->string('id_ipmhs')->primary();
            $table->string('id_mahasiswa')->nullable();
            $table->string('id_kalender')->nullable();
            $table->string('tahun')->nullable();
            $table->string('ips')->nullable();
            $table->string('ipk')->nullable();
            $table->string('sks_semester')->nullable();
            $table->string('sks_lulus')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table) {
            $table->string('id_krs')->primary();
            $table->string('id_mahasiswa')->nullable();
            $table->string('id_kalender')->nullable();
            $table->string('id_matkul')->nullable();
            $table->string('nilai_huruf')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_jadwal', function (Blueprint $table) {
            $table->string('id_jadwal')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('id_kalender')->nullable();
            $table->string('id_matkul')->nullable();
            $table->string('nama_kelas')->nullable();
            for ($slot = 1; $slot <= 14; $slot++) {
                $table->string('dosen'.$slot)->nullable();
            }
        });
        Schema::connection('simak_main')->create('s1m4k_akd', function (Blueprint $table) {
            $table->string('id_akd')->primary();
            $table->string('id_jadwal')->nullable();
            $table->string('id_dosen')->nullable();
            $table->string('rencana')->nullable();
            $table->string('realisasi')->nullable();
        });

        Schema::connection('simak_main')->create('s1m4k_masterruang', function (Blueprint $table) {
            $table->string('id_ruang')->primary();
            $table->string('id_prodi')->nullable();
        });

        Schema::connection('simak_main')->create('s1m4k_masterpt', function (Blueprint $table) {
            $table->string('id_perguruan_tinggi')->primary();
            $table->string('nama_perguruan_tinggi')->nullable();
        });

        Schema::connection('simak_main')->create('s1m4k_kodereferensi', function (Blueprint $table) {
            $table->string('keterangan')->nullable();
            $table->string('kode_referensi')->nullable();
            $table->string('nama_provinsi')->nullable();
            $table->decimal('lat_provinsi', 10, 7)->nullable();
            $table->decimal('lng_provinsi', 10, 7)->nullable();
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
