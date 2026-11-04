<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\DatabaseMailSettings;
use App\Services\InstitutionSettings;
use App\Services\MidtransSnapService;
use App\Services\PaymentGatewaySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SystemSettingsController extends Controller
{
    public function index(Request $request, PaymentGatewaySettings $gateways): View
    {
        $this->authorizeAdmin($request);
        $this->ensureSchema();
        $connection = DB::connection('simak_main');
        $site = $connection->table('simak_site_settings')->where('id', 1)->first();
        $mail = $connection->table('simak_mail_settings')->where('id', 1)->first();
        $midtrans = $gateways->get('midtrans');

        return view('system-settings.index', [
            'site' => $site,
            'mail' => $mail,
            'mailPasswordConfigured' => filled($mail?->password_encrypted),
            'heroes' => $connection->table('simak_hero_images')->orderBy('slot')->get()->keyBy('slot'),
            'mottoSupported' => Schema::connection('simak_main')->hasColumn('simak_site_settings', 'motto'),
            'primaryColorSupported' => Schema::connection('simak_main')->hasColumn('simak_site_settings', 'primary_color'),
            'midtrans' => $midtrans,
            'midtransServerKeyConfigured' => filled($midtrans['credentials']['server_key'] ?? null),
        ]);
    }

    public function updateBranding(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $this->authorizeAdmin($request);
        $this->ensureSchema();
        $rules = [
            'ministry_name' => ['required', 'string', 'max:255'],
            'motto' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'preloader_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
        $primaryColorSupported = Schema::connection('simak_main')->hasColumn('simak_site_settings', 'primary_color');
        if ($primaryColorSupported) {
            $rules['primary_color'] = ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        }
        $validated = $request->validate($rules);
        $connection = DB::connection('simak_main');
        $current = $connection->table('simak_site_settings')->where('id', 1)->first();
        $mottoSupported = Schema::connection('simak_main')->hasColumn('simak_site_settings', 'motto');
        if (! $mottoSupported
            && isset($validated['motto'])
            && $validated['motto'] !== 'Motto Perguruan Tinggi') {
            return back()->withInput()->withErrors([
                'motto' => 'Kolom motto belum tersedia. Jalankan database/manual/simak_site_settings_motto.sql terlebih dahulu.',
            ]);
        }
        $values = [
            'ministry_name' => trim($validated['ministry_name']),
            'logo_path' => $current?->logo_path,
            'preloader_logo_path' => $current?->preloader_logo_path,
            'updated_at' => now(),
        ];
        if ($mottoSupported) {
            $values['motto'] = filled($validated['motto'] ?? null)
                ? trim($validated['motto'])
                : 'Motto Perguruan Tinggi';
        }
        if ($primaryColorSupported) {
            $values['primary_color'] = strtolower($validated['primary_color']);
        }
        foreach (['logo' => 'logo_path', 'preloader_logo' => 'preloader_logo_path'] as $input => $column) {
            if ($request->hasFile($input)) {
                $path = $request->file($input)->store('branding', 'public');
                if (! is_string($path)) {
                    throw new RuntimeException('Gagal menyimpan file '.$input.'.');
                }
                $values[$column] = $path;
            }
        }
        if ($current === null) {
            $values['id'] = 1;
            $values['created_at'] = now();
        }
        $connection->table('simak_site_settings')->updateOrInsert(['id' => 1], $values);
        foreach (['logo_path', 'preloader_logo_path'] as $column) {
            if ($current?->$column && ($current->$column !== ($values[$column] ?? null))) {
                Storage::disk('public')->delete($current->$column);
            }
        }
        $activityLogger->record($user, 'Pengaturan Sistem', 'Memperbarui identitas dan branding', [
            'ministry_name' => ['sebelum' => $current?->ministry_name, 'sesudah' => $values['ministry_name']],
            'motto' => ['sebelum' => $current?->motto, 'sesudah' => $values['motto'] ?? $current?->motto],
            'primary_color' => ['sebelum' => $current?->primary_color, 'sesudah' => $values['primary_color'] ?? $current?->primary_color],
            'logo_diubah' => isset($values['logo_path']) && $values['logo_path'] !== $current?->logo_path,
            'preloader_diubah' => isset($values['preloader_logo_path']) && $values['preloader_logo_path'] !== $current?->preloader_logo_path,
        ], 'simak_site_settings:1');

        return back()->with('status', 'Identitas dan branding berhasil disimpan.');
    }

    public function saveHero(Request $request, int $slot, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $this->authorizeAdmin($request);
        $this->ensureSchema();
        abort_unless($slot >= 1 && $slot <= 7, 404);
        $validated = $request->validate([
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove' => ['nullable', 'boolean'],
        ]);
        $connection = DB::connection('simak_main');
        $current = $connection->table('simak_hero_images')->where('slot', $slot)->first();
        $path = $current?->image_path;
        if ($request->boolean('remove')) {
            $path = null;
        } elseif ($request->hasFile('image')) {
            $path = $request->file('image')->store('branding/hero', 'public');
            if (! is_string($path)) {
                throw new RuntimeException('Gagal menyimpan gambar hero.');
            }
        }
        $connection->table('simak_hero_images')->updateOrInsert(
            ['slot' => $slot],
            ['image_path' => $path, 'created_at' => $current?->created_at ?? now(), 'updated_at' => now()],
        );
        if ($current?->image_path && $current->image_path !== $path) {
            Storage::disk('public')->delete($current->image_path);
        }
        $activityLogger->record($user, 'Pengaturan Sistem', 'Memperbarui gambar hero', [
            'slot' => $slot,
            'gambar_diperbarui' => $path !== $current?->image_path,
            'gambar_dihapus' => $path === null && $current?->image_path !== null,
        ], 'simak_hero_images:'.$slot);

        return back()->with('status', 'Gambar hero '.$slot.' berhasil diperbarui.');
    }

    public function saveMail(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $this->authorizeAdmin($request);
        $this->ensureSchema();
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'scheme' => ['required', 'in:smtp,smtps'],
            'host' => ['nullable', 'required_if:enabled,1', 'string', 'max:255'],
            'port' => ['nullable', 'required_if:enabled,1', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'required_if:enabled,1', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:4096'],
            'from_address' => ['nullable', 'required_if:enabled,1', 'email', 'max:255'],
            'from_name' => ['nullable', 'required_if:enabled,1', 'string', 'max:255'],
        ]);
        $connection = DB::connection('simak_main');
        $current = $connection->table('simak_mail_settings')->where('id', 1)->first();
        $enabled = $request->boolean('enabled');
        $password = filled($validated['password'] ?? null)
            ? Crypt::encryptString($validated['password'])
            : $current?->password_encrypted;
        if ($enabled && ! filled($password)) {
            return back()->withInput()->withErrors(['password' => 'Password SMTP wajib diisi saat mengaktifkan SMTP.']);
        }
        $values = [
            'enabled' => $enabled,
            'scheme' => $validated['scheme'],
            'host' => filled($validated['host'] ?? null) ? trim($validated['host']) : null,
            'port' => isset($validated['port']) ? (int) $validated['port'] : null,
            'username' => filled($validated['username'] ?? null) ? trim($validated['username']) : null,
            'password_encrypted' => $password,
            'from_address' => filled($validated['from_address'] ?? null) ? trim($validated['from_address']) : null,
            'from_name' => filled($validated['from_name'] ?? null) ? trim($validated['from_name']) : null,
            'updated_at' => now(),
        ];
        if ($current === null) {
            $values['id'] = 1;
            $values['created_at'] = now();
        }
        $connection->table('simak_mail_settings')->updateOrInsert(['id' => 1], $values);
        $activityLogger->record($user, 'Pengaturan Sistem', 'Memperbarui konfigurasi SMTP', [
            'enabled' => ['sebelum' => (bool) ($current?->enabled ?? false), 'sesudah' => $enabled],
            'host' => ['sebelum' => $current?->host, 'sesudah' => $values['host']],
            'username' => ['sebelum' => $current?->username, 'sesudah' => $values['username']],
            'password_diubah' => filled($validated['password'] ?? null),
        ], 'simak_mail_settings:1');

        return back()->with('status', 'Pengaturan SMTP berhasil disimpan.');
    }

    public function testMail(Request $request, DatabaseMailSettings $mailSettings): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate(['recipient' => ['required', 'email', 'max:255']]);
        try {
            $mailSettings->sendTest($validated['recipient']);
        } catch (RuntimeException|TransportExceptionInterface $exception) {
            return back()->withErrors(['smtp_test' => $exception->getMessage()]);
        }

        return back()->with('status', 'Email uji SMTP berhasil dikirim ke '.$validated['recipient'].'.');
    }

    public function saveMidtrans(Request $request, PaymentGatewaySettings $gateways, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $this->authorizeAdmin($request);
        $this->ensureSchema();
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'environment' => ['required', 'in:sandbox,production'],
            'client_key' => ['nullable', 'string', 'max:255'],
            'server_key' => ['nullable', 'string', 'max:4096'],
            'service_fee' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
        ]);
        if (! Schema::connection('simak_main')->hasColumn('simak_payment_gateways', 'service_fee')) {
            return back()->withInput()->withErrors([
                'service_fee' => 'Kolom biaya layanan belum tersedia. Jalankan database/manual/midtrans_service_fee.sql terlebih dahulu.',
            ]);
        }
        $old = $gateways->get('midtrans');
        $serverKey = filled($validated['server_key'] ?? null)
            ? $validated['server_key']
            : ($old['credentials']['server_key'] ?? '');
        if ($request->boolean('enabled') && (! filled($serverKey) || ! filled($validated['client_key'] ?? null))) {
            return back()->withInput()->withErrors(['server_key' => 'Client key dan server key wajib tersedia saat Midtrans diaktifkan.']);
        }
        $serviceFee = (int) ($validated['service_fee'] ?? 0);
        $gateways->save(
            'midtrans',
            $request->boolean('enabled'),
            $validated['environment'],
            $validated['client_key'] ?? null,
            ['server_key' => $serverKey],
            $serviceFee,
        );
        $activityLogger->record($user, 'Pengaturan Sistem', 'Memperbarui konfigurasi Midtrans', [
            'enabled' => $request->boolean('enabled'),
            'environment' => $validated['environment'],
            'service_fee' => ['sebelum' => $old['service_fee'], 'sesudah' => $serviceFee],
            'client_key_diubah' => ($validated['client_key'] ?? null) !== ($old['client_key'] ?? null),
            'server_key_diubah' => filled($validated['server_key'] ?? null),
        ], 'simak_payment_gateways:midtrans');

        return back()->with('status', 'Pengaturan Midtrans berhasil disimpan.');
    }

    public function testMidtrans(Request $request, MidtransSnapService $midtrans): RedirectResponse
    {
        $this->authorizeAdmin($request);

        try {
            $midtrans->testConnection();
        } catch (RuntimeException $exception) {
            return back()->withErrors(['midtrans_test' => $exception->getMessage()]);
        }

        $environment = app(PaymentGatewaySettings::class)->get('midtrans')['environment'];
        $environmentName = $environment === 'production' ? 'Production' : 'Sandbox';

        return back()->with('status', "Koneksi dan autentikasi Midtrans {$environmentName} berhasil diuji. Tidak ada transaksi yang dibuat.");
    }

    private function ensureSchema(): void
    {
        abort_unless(
            app(InstitutionSettings::class)->available(),
            503,
            'Tabel pengaturan Siakad belum tersedia. Jalankan database/manual/simak_configuration_settings.sql terlebih dahulu.',
        );
    }

    private function authorizeAdmin(Request $request): object
    {
        $user = $request->user();
        abort_unless($user?->role === 'admin', 403);

        return $user;
    }
}
