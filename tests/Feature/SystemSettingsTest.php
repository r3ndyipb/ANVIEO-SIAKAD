<?php

namespace Tests\Feature;

use App\Services\DatabaseMailSettings;
use App\Services\InstitutionSettings;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('b', 32)),
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
            $table->string('nama_perguruan_tinggi')->nullable();
        });
        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'pt-1',
            'nama_perguruan_tinggi' => 'Universitas Contoh',
        ]);
        Schema::connection('simak_main')->create('simak_site_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('ministry_name');
            $table->string('motto')->default('Motto Perguruan Tinggi');
            $table->string('primary_color', 7)->default('#2167a5');
            $table->string('logo_path')->nullable();
            $table->string('preloader_logo_path')->nullable();
            $table->timestamps();
        });
        Schema::connection('simak_main')->create('simak_hero_images', function (Blueprint $table): void {
            $table->unsignedTinyInteger('slot')->primary();
            $table->string('image_path')->nullable();
            $table->timestamps();
        });
        Schema::connection('simak_main')->create('simak_mail_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('enabled')->default(false);
            $table->string('scheme');
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('username')->nullable();
            $table->text('password_encrypted')->nullable();
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });
        Schema::connection('simak_main')->create('simak_payment_gateways', function (Blueprint $table): void {
            $table->string('gateway')->primary();
            $table->boolean('enabled')->default(false);
            $table->string('environment')->default('sandbox');
            $table->string('client_key')->nullable();
            $table->unsignedBigInteger('service_fee')->default(0);
            $table->text('credentials_encrypted')->nullable();
            $table->timestamps();
        });
        Schema::connection('simak_main')->create('simak_payment_transactions', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->string('gateway');
            $table->string('payment_type', 10);
            $table->string('payment_id', 100);
            $table->string('student_id', 100);
            $table->string('order_id', 100)->unique();
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('service_fee')->default(0);
            $table->string('transaction_status', 40)->default('pending');
            $table->timestamps();
        });
        Schema::connection('simak_main')->create('log_aktivitas', function (Blueprint $table): void {
            $table->string('id_log')->primary();
            $table->string('id_user')->nullable();
            $table->string('level_user')->nullable();
            $table->string('nama_user');
            $table->string('modul');
            $table->text('aktifitas');
            $table->dateTime('created_at');
        });
    }

    public function test_system_settings_are_admin_only_and_show_seven_hero_slots(): void
    {
        $this->actingAs($this->user('admin'))
            ->get(route('system-settings.index'))
            ->assertOk()
            ->assertSee('Gambar 7')
            ->assertSee('name="primary_color"', false)
            ->assertSee('type="color"', false)
            ->assertSee('--brand-primary: #2167a5', false)
            ->assertSee('Midtrans Snap')
            ->assertSee('Cek koneksi Midtrans')
            ->assertSee('Simpan konfigurasi terlebih dahulu.')
            ->assertSee('name="service_fee"', false)
            ->assertSee('overflow-wrap:anywhere', false)
            ->assertSee(route('payments.midtrans.notification'))
            ->assertSee('domain publik dengan HTTPS')
            ->assertSee('placeholder="Contoh: mail.domainkampus.ac.id"', false)
            ->assertSee('placeholder="SB-Mid-client-... untuk sandbox"', false)
            ->assertSee('placeholder="Masukkan server key Midtrans"', false)
            ->assertDontSee('e-Coll')
            ->assertSee('SMTP');

        $this->actingAs($this->user('admin'))
            ->put('/settings/system/ecoll')
            ->assertNotFound();

        $this->actingAs($this->user('keuangan'))
            ->get(route('system-settings.index'))
            ->assertForbidden();
    }

    public function test_admin_can_update_dynamic_institution_branding(): void
    {
        $this->actingAs($this->user('admin'))
            ->put(route('system-settings.branding'), [
                'ministry_name' => 'Kementerian Contoh',
                'motto' => 'Motto Uji',
                'primary_color' => '#7b2cbf',
            ])
            ->assertRedirect();

        $this->assertSame(
            'Kementerian Contoh',
            DB::connection('simak_main')->table('simak_site_settings')->value('ministry_name'),
        );
        $this->assertSame('Motto Uji', DB::connection('simak_main')->table('simak_site_settings')->value('motto'));
        $this->assertSame('#7b2cbf', DB::connection('simak_main')->table('simak_site_settings')->value('primary_color'));
        $this->assertSame('#7b2cbf', app(InstitutionSettings::class)->forDisplay()['primary_color']);
        $this->assertSame('#ffffff', app(InstitutionSettings::class)->forDisplay()['primary_foreground']);
    }

    public function test_login_page_uses_saved_primary_color(): void
    {
        DB::connection('simak_main')->table('simak_site_settings')->insert([
            'id' => 1,
            'ministry_name' => 'Kementerian Contoh',
            'primary_color' => '#7b2cbf',
        ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('--brand-primary: #7b2cbf', false);
    }

    public function test_primary_color_requires_a_six_digit_hex_value(): void
    {
        DB::connection('simak_main')->table('simak_site_settings')->insert([
            'id' => 1,
            'ministry_name' => 'Kementerian Contoh',
            'primary_color' => '#2167a5',
        ]);
        $this->actingAs($this->user('admin'))
            ->from(route('system-settings.index'))
            ->put(route('system-settings.branding'), [
                'ministry_name' => 'Kementerian Contoh',
                'primary_color' => 'red; background:url(https://example.test)',
            ])
            ->assertRedirect(route('system-settings.index'))
            ->assertSessionHasErrors('primary_color');

        $this->assertSame('#2167a5', DB::connection('simak_main')->table('simak_site_settings')->value('primary_color'));
        $this->assertSame('#2167a5', app(InstitutionSettings::class)->forDisplay()['primary_color']);
    }

    public function test_uploaded_application_logo_is_available_as_display_and_pdf_asset(): void
    {
        Storage::fake('public');
        config(['app.url' => 'http://127.0.0.1:8001']);
        $image = UploadedFile::fake()->createWithContent(
            'logo-kampus.png',
            (string) file_get_contents(public_path('images/landing/default-campus-logo.png')),
        );
        $preloaderImage = UploadedFile::fake()->createWithContent(
            'preloader-kampus.png',
            (string) file_get_contents(public_path('images/landing/default-campus-logo.png')),
        );

        $this->actingAs($this->user('admin'))
            ->put(route('system-settings.branding'), [
                'ministry_name' => 'Kementerian Contoh',
                'motto' => 'Motto Uji',
                'primary_color' => '#2167a5',
                'logo' => $image,
                'preloader_logo' => $preloaderImage,
            ])
            ->assertRedirect();

        URL::forceRootUrl('https://smartkampus.test/siakad');
        $settingsRow = DB::connection('simak_main')->table('simak_site_settings')->first();
        $logoPath = $settingsRow->logo_path;
        $preloaderLogoPath = $settingsRow->preloader_logo_path;
        Storage::disk('public')->assertExists($logoPath);
        Storage::disk('public')->assertExists($preloaderLogoPath);
        $settings = app(InstitutionSettings::class);

        $this->assertSame(Storage::disk('public')->path($logoPath), $settings->logoFilePath());
        $displaySettings = $settings->forDisplay();
        $this->assertSame(url('storage/'.$logoPath), $displaySettings['logo_url']);
        $this->assertSame(url('storage/'.$preloaderLogoPath), $displaySettings['preloader_logo_url']);
        $this->assertStringNotContainsString('127.0.0.1:8001', $displaySettings['logo_url']);
    }

    public function test_webp_application_logo_is_converted_for_pdf_consumers(): void
    {
        if (! function_exists('imagecreatefromwebp') || ! function_exists('imagewebp')) {
            $this->markTestSkipped('GD WebP support is not available.');
        }

        $image = imagecreatetruecolor(2, 2);
        ob_start();
        $encoded = imagewebp($image);
        $contents = (string) ob_get_clean();
        imagedestroy($image);
        $this->assertTrue($encoded);

        Storage::fake('public');
        $logoPath = 'branding/campus-logo.webp';
        Storage::disk('public')->put($logoPath, $contents);
        DB::connection('simak_main')->table('simak_site_settings')->insert([
            'id' => 1,
            'ministry_name' => 'Kementerian Contoh',
            'logo_path' => $logoPath,
        ]);

        $convertedPath = app(InstitutionSettings::class)->logoFilePath();
        try {
            $this->assertSame('png', pathinfo($convertedPath, PATHINFO_EXTENSION));
            $this->assertFileExists($convertedPath);
            $convertedImage = imagecreatefrompng($convertedPath);
            $this->assertNotFalse($convertedImage);
            imagedestroy($convertedImage);
        } finally {
            if (is_file($convertedPath)) {
                unlink($convertedPath);
            }
        }
    }

    public function test_empty_institution_settings_use_generic_defaults_and_local_assets(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterpt')->update(['nama_perguruan_tinggi' => null]);

        $settings = app(InstitutionSettings::class)->forDisplay();

        $this->assertSame('Perguruan Tinggi', $settings['institution_name']);
        $this->assertSame('SIAKAD Perguruan Tinggi', $settings['app_name']);
        $this->assertSame('SIAKAD (Sistem Informasi Akademik)', $settings['app_full_name']);
        $this->assertSame('Motto Perguruan Tinggi', $settings['motto']);
        $this->assertStringEndsWith('/images/landing/default-campus-logo.png', $settings['logo_url']);
        $this->assertSame(7, count($settings['hero_images']));
        $this->assertStringEndsWith('/images/landing/hero-01.jpg', $settings['hero_images'][0]);
        foreach (range(1, 7) as $slot) {
            $this->assertFileExists(public_path('images/landing/hero-'.str_pad((string) $slot, 2, '0', STR_PAD_LEFT).'.jpg'));
        }
        $this->assertFileExists(app(InstitutionSettings::class)->logoFilePath());
    }

    public function test_smtp_and_midtrans_secrets_are_encrypted_and_never_logged(): void
    {
        $this->actingAs($this->user('admin'))
            ->put(route('system-settings.smtp'), [
                'enabled' => '1',
                'scheme' => 'smtps',
                'host' => 'smtp.example.test',
                'port' => 465,
                'username' => 'mailer@example.test',
                'password' => 'smtp-sensitive',
                'from_address' => 'noreply@example.test',
                'from_name' => 'SIMAK Example',
            ])
            ->assertRedirect();

        $storedMailPassword = DB::connection('simak_main')->table('simak_mail_settings')->value('password_encrypted');
        $this->assertNotSame('smtp-sensitive', $storedMailPassword);
        $this->assertSame('smtp-sensitive', Crypt::decryptString($storedMailPassword));
        $this->assertTrue(app(DatabaseMailSettings::class)->configure());
        $this->assertSame('simak_database_smtp', config('mail.default'));
        $this->assertSame('smtp.example.test', config('mail.mailers.simak_database_smtp.host'));
        $this->assertSame('smtp-sensitive', config('mail.mailers.simak_database_smtp.password'));

        $this->put(route('system-settings.midtrans'), [
            'enabled' => '1',
            'environment' => 'sandbox',
            'client_key' => 'client-sensitive',
            'server_key' => 'server-sensitive',
            'service_fee' => 2500,
        ])->assertRedirect();

        $gateway = DB::connection('simak_main')->table('simak_payment_gateways')->where('gateway', 'midtrans')->first();
        $this->assertSame(2500, (int) $gateway->service_fee);
        $this->assertNotSame('server-sensitive', $gateway->credentials_encrypted);
        $this->assertSame('server-sensitive', json_decode(Crypt::decryptString($gateway->credentials_encrypted), true)['server_key']);
        $activity = DB::connection('simak_main')->table('log_aktivitas')->pluck('aktifitas')->implode(' ');
        $this->assertStringNotContainsString('smtp-sensitive', $activity);
        $this->assertStringNotContainsString('server-sensitive', $activity);
    }

    public function test_admin_can_check_midtrans_authentication_without_creating_a_payment(): void
    {
        DB::connection('simak_main')->table('simak_payment_gateways')->insert([
            'gateway' => 'midtrans',
            'enabled' => false,
            'environment' => 'sandbox',
            'client_key' => 'SB-Mid-client-test',
            'credentials_encrypted' => Crypt::encryptString(json_encode([
                'server_key' => 'SB-Mid-server-test',
            ])),
        ]);
        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'error_messages' => ['transaction_details is required'],
            ], 400),
        ]);

        $this->actingAs($this->user('admin'))
            ->post(route('system-settings.midtrans.test'))
            ->assertRedirect()
            ->assertSessionHas('status', 'Koneksi dan autentikasi Midtrans Sandbox berhasil diuji. Tidak ada transaksi yang dibuat.');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
            && $request->method() === 'POST'
            && $request->data() === []);
        $this->assertDatabaseCount('simak_payment_transactions', 0, 'simak_main');
    }

    public function test_midtrans_connection_check_reports_invalid_credentials_and_is_admin_only(): void
    {
        DB::connection('simak_main')->table('simak_payment_gateways')->insert([
            'gateway' => 'midtrans',
            'enabled' => true,
            'environment' => 'sandbox',
            'client_key' => 'SB-Mid-client-test',
            'credentials_encrypted' => Crypt::encryptString(json_encode([
                'server_key' => 'SB-Mid-server-test',
            ])),
        ]);
        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'error' => 'Unauthorized',
            ], 401),
        ]);

        $this->actingAs($this->user('admin'))
            ->from(route('system-settings.index'))
            ->post(route('system-settings.midtrans.test'))
            ->assertRedirect(route('system-settings.index'))
            ->assertSessionHasErrors(['midtrans_test' => 'Midtrans menolak autentikasi: Unauthorized']);

        $this->actingAs($this->user('keuangan'))
            ->post(route('system-settings.midtrans.test'))
            ->assertForbidden();
        $this->assertDatabaseCount('simak_payment_transactions', 0, 'simak_main');
    }

    private function user(string $role): GenericUser
    {
        return new GenericUser([
            'id' => $role.'-1',
            'legacy_id' => $role.'-1',
            'name' => ucfirst($role).' Test',
            'role' => $role,
        ]);
    }
}
