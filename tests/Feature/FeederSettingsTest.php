<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FeederSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.simak_main' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        DB::purge('simak_main');
        Schema::connection('simak_main')->create('s1m4k_masterpt', function (Blueprint $table): void {
            $table->string('id_perguruan_tinggi')->primary();
            $table->string('alamat_feeder')->nullable();
            $table->string('user_feeder')->nullable();
            $table->string('password_feeder')->nullable();
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
        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'pt-1',
            'alamat_feeder' => 'https://feeder.example.test',
            'user_feeder' => 'feeder-user',
            'password_feeder' => $this->encrypt('old-secret'),
        ]);
        Http::fake(['*' => Http::response(['error_code' => '0', 'data' => ['token' => 'fake-token']])]);
    }

    public function test_feeder_password_is_never_rendered_and_blank_update_preserves_secret(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('feeder-settings.index'))
            ->assertOk()
            ->assertSee('https://feeder.example.test')
            ->assertSee('Password Feeder')
            ->assertDontSee('old-secret')
            ->assertDontSee($this->encrypt('old-secret'));
        Http::assertNothingSent();

        $this->put(route('feeder-settings.update'), [
            'user_feeder' => 'new-user',
            'password_feeder' => '',
        ])->assertRedirect(route('feeder-settings.index'))->assertSessionHas('status');
        $settings = DB::connection('simak_main')->table('s1m4k_masterpt')->first();
        $this->assertSame('new-user', $settings->user_feeder);
        $this->assertSame($this->encrypt('old-secret'), $settings->password_feeder);
        $this->assertSame('https://feeder.example.test', $settings->alamat_feeder);
    }

    public function test_saving_new_feeder_secret_uses_legacy_encryption_and_test_connection_is_explicit(): void
    {
        $this->actingAs($this->admin());
        $this->put(route('feeder-settings.update'), [
            'user_feeder' => 'new-user',
            'password_feeder' => 'new-secret',
        ])->assertRedirect(route('feeder-settings.index'));
        $this->assertSame($this->encrypt('new-secret'), DB::connection('simak_main')
            ->table('s1m4k_masterpt')->value('password_feeder'));
        Http::assertNothingSent();

        $this->post(route('feeder-settings.test'))
            ->assertRedirect()->assertSessionHas('status');
        Http::assertSent(fn (ClientRequest $request): bool => ($request['act'] ?? '') === 'GetToken'
            && ($request['username'] ?? '') === 'new-user'
            && ($request['password'] ?? '') === 'new-secret');
        $this->assertSame(1, DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'Pengaturan Feeder')->count());
    }

    public function test_only_admin_can_manage_feeder_settings(): void
    {
        $this->actingAs(new GenericUser(['id' => 'staff-1', 'role' => 'userprodi', 'name' => 'Staff']));
        $this->get(route('feeder-settings.index'))->assertForbidden();
        $this->put(route('feeder-settings.update'), [
            'user_feeder' => 'changed', 'password_feeder' => 'secret',
        ])->assertForbidden();
        $this->post(route('feeder-settings.test'))->assertForbidden();
    }

    private function admin(): GenericUser
    {
        return new GenericUser([
            'id' => 'admin-1', 'legacy_id' => 'admin-1', 'role' => 'admin',
            'name' => 'Admin SIMAK', 'login' => 'admin-user',
        ]);
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