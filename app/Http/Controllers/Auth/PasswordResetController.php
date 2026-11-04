<?php

namespace App\Http\Controllers\Auth;

use App\Auth\LegacyUserProvider;
use App\Http\Controllers\Controller;
use App\Mail\LegacyPasswordResetMail;
use App\Services\ActivityLogger;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PasswordResetController extends Controller
{
    public function create(): RedirectResponse
    {
        return redirect()->route('public.home')->with('open_reset_password', true);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'login' => ['required', 'string', 'max:100'],
        ]);
        if ($validator->fails()) {
            return redirect()->route('public.home')
                ->withErrors($validator, 'passwordReset')
                ->withInput()
                ->with('open_reset_password', true);
        }
        $validated = $validator->validated();

        if ($this->simakConnectionIsConfigured()) {
            foreach (LegacyUserProvider::ACCOUNTS as $type => $account) {
                $record = DB::connection('simak_main')
                    ->table($account['table'])
                    ->where($account['login'], $validated['login'])
                    ->first(array_values(array_unique([
                        $account['primary'],
                        $account['login'],
                        $account['name'],
                        'email',
                    ])));

                if ($record === null) {
                    continue;
                }

                if (is_string($record->email) && $record->email !== '') {
                    $this->sendResetMessage($type, $account, $record, $activityLogger);
                }

                break;
            }
        }

        return redirect()->route('public.home')
            ->with('reset_password_status', 'Jika akun terdaftar dan memiliki email, instruksi reset akan dikirim.')
            ->with('open_reset_password', true);
    }

    public function edit(string $type, string $identifier): View
    {
        $account = $this->accountFor($type);
        $pendingPassword = DB::connection('simak_main')
            ->table($account['table'])
            ->where($account['primary'], $identifier)
            ->value($account['pending_password']);

        abort_if(! is_string($pendingPassword) || $pendingPassword === '', 410);

        return view('auth.activate-password');
    }

    public function activate(string $type, string $identifier, ActivityLogger $activityLogger): RedirectResponse
    {
        $account = $this->accountFor($type);
        $activated = DB::connection('simak_main')->transaction(function () use ($account, $identifier, $activityLogger, $type) {
            $query = DB::connection('simak_main')->table($account['table']);
            $record = (clone $query)
                ->where($account['primary'], $identifier)
                ->first(array_values(array_unique([
                    $account['primary'],
                    $account['login'],
                    $account['name'],
                ])));
            $pendingPassword = (clone $query)
                ->where($account['primary'], $identifier)
                ->lockForUpdate()
                ->value($account['pending_password']);

            if (! is_string($pendingPassword) || $pendingPassword === '') {
                return false;
            }

            $updated = $query
                ->where($account['primary'], $identifier)
                ->where($account['pending_password'], $pendingPassword)
                ->update([
                    'password' => $pendingPassword,
                    $account['pending_password'] => '',
                ]) === 1;
            if ($updated) {
                $actor = new GenericUser([
                    'legacy_id' => $identifier,
                    'role' => $type,
                    'name' => $record->{$account['name']} ?? '',
                ]);
                $activityLogger->record($actor, 'Autentikasi', 'Aktivasi kata sandi', [
                    'kata_sandi_diubah' => true,
                ], $identifier, [
                    'jenis' => ucfirst($type),
                    'nama' => (string) ($record->{$account['name']} ?? ''),
                    'nomor_identitas' => (string) ($record->{$account['login']} ?? ''),
                ]);
            }

            return $updated;
        });

        abort_if(! $activated, 410);

        return redirect()->route('login')->with('status', 'Aktivasi berhasil. Silakan masuk menggunakan password yang dikirim melalui email.');
    }

    private function sendResetMessage(
        string $type,
        array $account,
        object $record,
        ActivityLogger $activityLogger
    ): void {
        $temporaryPassword = Str::password(20);
        $hashedPassword = Hash::make($temporaryPassword);
        $identifier = (string) $record->{$account['primary']};

        DB::connection('simak_main')
            ->table($account['table'])
            ->where($account['primary'], $identifier)
            ->update([$account['pending_password'] => $hashedPassword]);

        $activationUrl = URL::temporarySignedRoute(
            'password.activate',
            now()->addMinutes(30),
            ['type' => $type, 'identifier' => $identifier],
        );

        try {
            $this->assertMailTransportIsConfiguredForDelivery();
            Mail::to($record->email)->send(new LegacyPasswordResetMail(
                $record->{$account['name']} ?: $record->email,
                $temporaryPassword,
                $activationUrl,
            ));
        } catch (Throwable $exception) {
            DB::connection('simak_main')
                ->table($account['table'])
                ->where($account['primary'], $identifier)
                ->where($account['pending_password'], $hashedPassword)
                ->update([$account['pending_password'] => '']);

            Log::error('SIMAK password reset email could not be sent.', [
                'account_type' => $type,
                'mailer' => config('mail.default'),
                'exception' => $exception::class,
                'exception_message' => substr(
                    preg_replace('/[\w.%+-]+@[\w.-]+\.[A-Z]{2,}/i', '[redacted-email]', $exception->getMessage()) ?? '',
                    0,
                    500,
                ),
                'recipient_domain' => substr(strrchr((string) $record->email, '@') ?: '', 1),
            ]);

            return;
        }

        $actor = new GenericUser([
            'legacy_id' => (string) $record->{$account['primary']},
            'role' => $type,
            'name' => (string) ($record->{$account['name']} ?: $record->email),
        ]);
        $activityLogger->record($actor, 'Autentikasi', 'Permintaan reset kata sandi', [
            'permintaan_reset_kata_sandi' => true,
        ], (string) $record->{$account['primary']}, [
            'jenis' => ucfirst($type),
            'nama' => (string) ($record->{$account['name']} ?: $record->email),
            'nomor_identitas' => (string) ($record->{$account['login']} ?? ''),
        ]);
    }

    private function assertMailTransportIsConfiguredForDelivery(): void
    {
        if (app()->environment('production') && in_array(config('mail.default'), ['array', 'log'], true)) {
            throw new \RuntimeException('The configured mailer does not deliver email in production.');
        }
    }

    private function accountFor(string $type): array
    {
        abort_unless(isset(LegacyUserProvider::ACCOUNTS[$type]), 404);

        return LegacyUserProvider::ACCOUNTS[$type];
    }

    private function simakConnectionIsConfigured(): bool
    {
        $connection = config('database.connections.simak_main', []);

        if (($connection['driver'] ?? null) !== 'mysql') {
            return true;
        }

        if (! empty($connection['url'])) {
            return true;
        }

        return ! empty($connection['database'])
            && (! empty($connection['host']) || ! empty($connection['unix_socket']));
    }
}
