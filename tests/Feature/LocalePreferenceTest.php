<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class LocalePreferenceTest extends TestCase
{
    public function test_indonesian_is_the_default_application_language(): void
    {
        $this->assertSame('id', config('app.locale'));
    }

    public function test_authenticated_user_can_save_either_supported_language(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 1,
            'name' => 'Pengguna Uji',
            'login' => 'useruji',
            'role' => 'admin',
        ]));

        $this->from('/dashboard')
            ->post(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('locale', 'en');

        $this->assertSame('en', app()->getLocale());
    }

    public function test_unsupported_language_is_rejected_without_changing_preference(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 1,
            'name' => 'Pengguna Uji',
            'login' => 'useruji',
            'role' => 'admin',
        ]));

        $this->from('/dashboard')
            ->post(route('locale.update'), ['locale' => 'fr'])
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrors('locale')
            ->assertSessionMissing('locale');
    }

    public function test_locale_middleware_uses_saved_language_and_falls_back_safely(): void
    {
        $session = app('session.store');
        $request = Request::create('/dashboard', 'GET');
        $request->setLaravelSession($session);
        $session->put('locale', 'en');

        $middleware = new SetLocale;
        $middleware->handle($request, fn () => new Response);

        $this->assertSame('en', app()->getLocale());

        $session->put('locale', 'fr');
        $middleware->handle($request, fn () => new Response);

        $this->assertSame('id', app()->getLocale());
    }

    public function test_authenticated_users_can_load_shared_english_translations(): void
    {
        $notificationError = 'Penyimpanan notifikasi belum tersedia. Buat tabel notifikasi E-Skripsi atau E-Tesis & Disertasi menggunakan SQL yang disediakan.';
        $this->actingAs(new GenericUser([
            'id' => 1,
            'name' => 'Pengguna Uji',
            'login' => 'useruji',
            'role' => 'admin',
        ]));

        $this->getJson(route('locale.translations'))
            ->assertOk()
            ->assertJsonPath('Dashboard', 'Dashboard')
            ->assertJsonPath('Kurikulum OBE', 'OBE Curriculum')
            ->assertJsonPath('Layanan Prodi', 'Study Program Services')
            ->assertJsonPath('Kelola Kurikulum | SIMAK Unkhair', 'Manage Curriculum | SIMAK Unkhair')
            ->assertJsonPath('Kuliah Kerja Nyata (KKN)', 'Community Service Program (KKN)')
            ->assertJsonPath('Verifikasi KKN', 'KKN Verification')
            ->assertJsonPath('Export ke Feeder', 'Export to Feeder')
            ->assertJsonPath('Sebaran Mahasiswa', 'Student Distribution')
            ->assertJsonPath('Dashboard E-Tesis & Disertasi', 'Thesis & Dissertation Dashboard')
            ->assertJsonPath('Dashboard LMS', 'LMS Dashboard')
            ->assertJsonPath('Tandai sudah dibaca', 'Mark as read')
            ->assertJsonPath('Judul tesis & disertasi:', 'Thesis & dissertation title:')
            ->assertJsonPath('Inisialisasi Data dari Feeder', 'Initialize Data from Feeder')
            ->assertJsonPath('Profil Master PT dan konfigurasi Feeder', 'University Profile and Feeder Configuration')
            ->assertJsonPath('IPK Mahasiswa', 'Student GPA')
            ->assertJsonPath('Instrumen Survey EDOM', 'EDOM Survey Instrument')
            ->assertJsonFragment([
                'Tahap wizard Feeder tidak memiliki langkah berikutnya.' => 'The Feeder wizard has no next stage.',
                $notificationError => 'Notification storage is not available. Create the E-Skripsi or E-Tesis & Disertasi notification table using the provided SQL.',
            ])
            ->assertJsonPath('Batal', 'Cancel');
    }
}
