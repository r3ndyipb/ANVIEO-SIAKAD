<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SimakUserManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.simak_main' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        DB::purge('simak_main');
        $this->createSchema();
        $this->seedOrganizations();
    }

    public function test_admin_can_open_all_legacy_user_categories_and_their_forms(): void
    {
        $this->actingAs($this->admin());
        foreach ([
            'useradmin', 'userpim', 'userprodi', 'usertu', 'userregulang',
            'userkeu', 'userwisuda', 'uservalidasi', 'userlppm',
        ] as $type) {
            $this->get(route('simak-users.index', ['type' => $type]))->assertOk();
            $this->get(route('simak-users.create', $type))->assertOk();
            $this->get(route('simak-users.create', ['type' => $type, 'modal' => 1]))
                ->assertOk()->assertSee('data-simak-user-form', false);
        }
        $this->get(route('simak-users.create', 'userpim'))->assertSee('Jenis Pimpinan');
        $this->get(route('simak-users.create', 'userprodi'))->assertSee('Program Studi');
        $this->get(route('simak-users.create', 'userwisuda'))->assertSee('Wisuda');
        $this->get(route('simak-users.index', ['type' => 'usertu']))
            ->assertOk()->assertSee('Operator Prodi')->assertSee('data-simak-user-modal', false);
    }

    public function test_program_user_categories_render_program_scope_with_degree_label(): void
    {
        DB::connection('simak_main')->table('s1m4k_admin')->insert([
            ['username' => 'program-manager', 'password' => 'hash', 'nama_lengkap' => 'Pengelola', 'email' => 'pengelola@example.test', 'level' => 'userprodi', 'id_prodi' => 'program-1', 'id_fakultas' => 'faculty-1'],
            ['username' => 'program-operator', 'password' => 'hash', 'nama_lengkap' => 'Operator', 'email' => 'operator@example.test', 'level' => 'usertu', 'id_prodi' => 'program-1', 'id_fakultas' => 'faculty-1'],
        ]);
        $this->actingAs($this->admin());

        foreach (['userprodi', 'usertu'] as $type) {
            $this->get(route('simak-users.index', ['type' => $type]))
                ->assertOk()
                ->assertSee(route('simak-users.data', ['type' => $type]))
                ->assertSee('Operator Prodi');
            $this->getJson(route('simak-users.data', ['type' => $type]))
                ->assertOk()
                ->assertJsonPath('data.0.scope', 'Program Satu (S1)');
        }
    }

    public function test_user_table_searches_all_accounts_server_side(): void
    {
        DB::connection('simak_main')->table('s1m4k_admin')->insert([
            ['username' => 'admin-first', 'password' => 'hash', 'nama_lengkap' => 'Admin Pertama', 'email' => 'first@example.test', 'level' => 'admin', 'blokir' => 'N'],
            ['username' => 'admin-second', 'password' => 'hash', 'nama_lengkap' => 'Admin Dicari', 'email' => 'second@example.test', 'level' => 'admin', 'blokir' => 'N'],
        ]);
        $this->actingAs($this->admin());

        $this->getJson(route('simak-users.data', [
            'type' => 'useradmin',
            'draw' => 2,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => 'Dicari'],
        ]))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.username', 'admin-second');
    }

    public function test_program_account_creation_derives_faculty_and_audits_without_password(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('simak-users.store', 'userprodi'), [
            'username' => 'operator-prodi', 'password' => 'strong-pass-1',
            'nama_lengkap' => 'Operator Program', 'email' => 'operator@example.test',
            'no_telp' => '0812345678', 'id_prodi' => 'program-1',
        ])->assertRedirect(route('simak-users.index', ['type' => 'userprodi']))
            ->assertSessionHas('status');

        $account = DB::connection('simak_main')->table('s1m4k_admin')->where('username', 'operator-prodi')->first();
        $this->assertSame('userprodi', $account->level);
        $this->assertSame('program-1', $account->id_prodi);
        $this->assertSame('faculty-1', $account->id_fakultas);
        $this->assertSame('N', $account->blokir);
        $this->assertTrue(Hash::check('strong-pass-1', $account->password));

        $activity = DB::connection('simak_main')->table('log_aktivitas')->value('aktifitas');
        $this->assertStringNotContainsString('strong-pass-1', $activity);
        $this->assertStringNotContainsString($account->password, $activity);
    }

    public function test_userprodi_and_operator_prodi_accounts_can_be_created_and_edited_from_modal_json(): void
    {
        $this->actingAs($this->admin());

        foreach ([['userprodi', 'program-operator'], ['usertu', 'operator-prodi']] as [$type, $username]) {
            $this->get(route('simak-users.index', ['type' => $type]))->assertOk();
            $this->postJson(route('simak-users.store', $type), [
                'username' => $username,
                'password' => 'modal-password-1',
                'nama_lengkap' => 'Operator Tes',
                'email' => $username.'@example.test',
                'no_telp' => '0800',
                'id_prodi' => 'program-1',
            ])->assertOk()->assertJsonPath('message', 'Akun '.($type === 'usertu' ? 'Operator Prodi' : 'Pengelola Program Studi').' berhasil dibuat.');

            $account = DB::connection('simak_main')->table('s1m4k_admin')->where('username', $username)->first();
            $this->get(route('simak-users.edit', [
                'type' => $type,
                'account' => $account->id_admin,
                'modal' => 1,
            ]))->assertOk()->assertSee('data-simak-user-form', false)->assertSee($username === 'program-operator' ? 'modal' : 'Operator');

            $this->putJson(route('simak-users.update', ['type' => $type, 'account' => $account->id_admin]), [
                'nama_lengkap' => 'Operator Diperbarui',
                'email' => 'updated-'.$username.'@example.test',
                'no_telp' => '0811',
                'blokir' => 'N',
                'password' => '',
                'id_prodi' => 'program-2',
            ])->assertOk()->assertJsonPath('message', 'Data akun berhasil diperbarui.');
        }
    }

    public function test_pimpinan_and_wisuda_accounts_use_expected_legacy_levels_and_scope(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('simak-users.store', 'userpim'), [
            'username' => 'faculty-leader', 'password' => 'strong-pass-2',
            'nama_lengkap' => 'Pimpinan Fakultas', 'email' => 'leader@example.test',
            'level' => 'pimfak', 'id_fakultas' => 'faculty-1',
        ])->assertRedirect();
        $facultyLeader = DB::connection('simak_main')->table('s1m4k_admin')->where('username', 'faculty-leader')->first();
        $this->assertSame('pimfak', $facultyLeader->level);
        $this->assertSame('faculty-1', $facultyLeader->id_fakultas);
        $this->assertNull($facultyLeader->id_prodi);

        $this->post(route('simak-users.store', 'userwisuda'), [
            'username' => 'wisuda-operator', 'password' => 'strong-pass-3',
            'nama_lengkap' => 'Operator Wisuda', 'email' => 'wisuda@example.test',
        ])->assertRedirect();
        $wisuda = DB::connection('simak_main')->table('s1m4k_admin')->where('username', 'wisuda-operator')->first();
        $this->assertSame('wisuda', $wisuda->level);
    }

    public function test_edit_preserves_username_hash_when_password_is_blank_and_updates_block_status(): void
    {
        $accountId = DB::connection('simak_main')->table('s1m4k_admin')->insertGetId([
            'username' => 'admin-edit', 'password' => Hash::make('original-pass'), 'nama_lengkap' => 'Admin Edit',
            'email' => 'admin-edit@example.test', 'no_telp' => '', 'level' => 'admin', 'blokir' => 'N',
        ]);
        $this->actingAs($this->admin());
        $this->put(route('simak-users.update', ['type' => 'useradmin', 'account' => $accountId]), [
            'nama_lengkap' => 'Admin Updated', 'email' => 'updated@example.test', 'no_telp' => '0800',
            'blokir' => 'Y', 'password' => '',
        ])->assertRedirect(route('simak-users.index', ['type' => 'useradmin']));

        $account = DB::connection('simak_main')->table('s1m4k_admin')->where('id_admin', $accountId)->first();
        $this->assertSame('admin-edit', $account->username);
        $this->assertSame('Admin Updated', $account->nama_lengkap);
        $this->assertSame('Y', $account->blokir);
        $this->assertTrue(Hash::check('original-pass', $account->password));
    }

    public function test_non_admin_cannot_manage_accounts_and_invalid_scopes_are_rejected(): void
    {
        $this->actingAs(new GenericUser(['id' => 'staff-1', 'role' => 'userprodi', 'name' => 'Staff']));
        $this->get(route('simak-users.index'))->assertForbidden();
        $this->post(route('simak-users.store', 'userwisuda'), [])->assertForbidden();

        $this->actingAs($this->admin());
        $this->post(route('simak-users.store', 'userprodi'), [
            'username' => 'outside-program', 'password' => 'strong-pass-4',
            'nama_lengkap' => 'Outside Program', 'email' => 'outside@example.test', 'id_prodi' => 'missing',
        ])->assertStatus(422);
        $this->post(route('simak-users.store', 'userpim'), [
            'username' => 'missing-faculty', 'password' => 'strong-pass-5',
            'nama_lengkap' => 'Missing Faculty', 'email' => 'missing@example.test',
            'level' => 'pimfak', 'id_fakultas' => 'missing',
        ])->assertStatus(422);
        $this->post(route('simak-users.store', 'userwisuda'), [
            'username' => 'long-email', 'password' => 'strong-pass-6',
            'nama_lengkap' => 'Long Email', 'email' => str_repeat('a', 41).'@example.test',
        ])->assertSessionHasErrors('email');
    }

    private function createSchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterfakultas', function (Blueprint $table): void {
            $table->string('id_fakultas')->primary();
            $table->string('nama_fakultas');
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_fakultas')->nullable();
            $table->string('nama_program_studi');
            $table->string('nama_jenjang_pendidikan')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_admin', function (Blueprint $table): void {
            $table->bigIncrements('id_admin');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('nama_lengkap');
            $table->string('email')->nullable();
            $table->string('no_telp')->nullable();
            $table->string('level');
            $table->string('id_prodi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('blokir')->default('N');
            $table->timestamps();
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
    }

    private function seedOrganizations(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterfakultas')->insert([
            ['id_fakultas' => 'faculty-1', 'nama_fakultas' => 'Fakultas Satu'],
            ['id_fakultas' => 'faculty-2', 'nama_fakultas' => 'Fakultas Dua'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'program-1', 'id_fakultas' => 'faculty-1', 'nama_program_studi' => 'Program Satu', 'nama_jenjang_pendidikan' => 'S1'],
            ['id_prodi' => 'program-2', 'id_fakultas' => 'faculty-2', 'nama_program_studi' => 'Program Dua', 'nama_jenjang_pendidikan' => 'S1'],
        ]);
    }

    private function admin(): GenericUser
    {
        return new GenericUser(['id' => 'admin-1', 'legacy_id' => 'admin-1', 'role' => 'admin', 'name' => 'Admin SIMAK']);
    }
}
