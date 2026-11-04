<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\LegacyFeederService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class FeederSettingsController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request->user());
        $settings = DB::connection('simak_main')->table('s1m4k_masterpt')->first([
            'id_perguruan_tinggi', 'alamat_feeder', 'user_feeder', 'password_feeder',
        ]);
        abort_if($settings === null, 404, 'Data perguruan tinggi belum tersedia.');
        $passwordConfigured = filled($settings->password_feeder);
        unset($settings->password_feeder);

        return view('feeder-settings.index', compact('settings', 'passwordConfigured'));
    }

    public function update(Request $request, LegacyFeederService $feeder, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeAdmin($user);
        $validated = $request->validate([
            'user_feeder' => ['required', 'string', 'max:100'],
            'password_feeder' => ['nullable', 'string', 'max:100'],
        ]);
        $connection = DB::connection('simak_main');
        $settings = $connection->table('s1m4k_masterpt')->first(['id_perguruan_tinggi', 'user_feeder']);
        abort_if($settings === null, 404, 'Data perguruan tinggi belum tersedia.');

        try {
            $feeder->updateFeederCredentials($validated['user_feeder'], $validated['password_feeder'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['feeder' => $exception->getMessage()]);
        }

        $activityLogger->record($user, 'Pengaturan Feeder', 'Memperbarui kredensial Feeder', [
            'user_feeder' => ['sebelum' => $settings->user_feeder, 'sesudah' => trim($validated['user_feeder'])],
            'password_feeder_diubah' => filled($validated['password_feeder'] ?? null),
        ], (string) $settings->id_perguruan_tinggi);

        return redirect()->route('feeder-settings.index')
            ->with('status', 'Pengaturan kredensial Feeder berhasil disimpan.');
    }

    public function testConnection(Request $request, LegacyFeederService $feeder): RedirectResponse
    {
        $this->authorizeAdmin($request->user());
        try {
            $feeder->testConnection();
        } catch (RuntimeException $exception) {
            return back()->withErrors(['feeder' => $exception->getMessage()]);
        }

        return back()->with('status', 'Koneksi Feeder berhasil diuji.');
    }

    private function authorizeAdmin(?object $user): void
    {
        abort_unless($user?->role === 'admin', 403);
    }
}