<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\InstitutionSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): View
    {
        $siteConfiguration = app(InstitutionSettings::class)->forDisplay();
        $sessionErrors = $request->session()->get('errors');
        $loginErrors = $sessionErrors ? $sessionErrors->getBag('default')->toArray() : [];

        return view('auth.login', [
            'siteConfiguration' => $siteConfiguration,
            'landingData' => [
                'openLogin' => true,
                'pageTitle' => 'Masuk Portal SIAKAD',
                'loginErrors' => $loginErrors,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'login' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput($request->only('login'))
                ->with('auth_notification', [
                    'icon' => 'error',
                    'title' => 'Login gagal',
                    'text' => $validator->errors()->first(),
                ]);
        }
        $credentials = $validator->validated();

        if (! Auth::attempt($credentials)) {
            return back()
                ->withErrors(['login' => 'Identitas atau kata sandi tidak valid.'])
                ->withInput($request->only('login'))
                ->with('auth_notification', [
                    'icon' => 'error',
                    'title' => 'Login gagal',
                    'text' => 'Identitas atau kata sandi tidak valid.',
                ]);
        }

        $request->session()->regenerate();
        $redirect = $request->user()->role === 'keuangan'
            ? redirect()->route('finance.dashboard')
            : redirect()->intended(route('dashboard'));

        return $redirect
            ->with('status', 'Berhasil masuk ke SIAKAD.')
            ->with('auth_notification', [
                'icon' => 'success',
                'title' => 'Login berhasil',
                'text' => 'Selamat datang di SIAKAD.',
            ])
            ->with('auth_changed', true);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('public.home')
            ->with('status', 'Anda berhasil keluar dari SIAKAD.')
            ->with('auth_notification', [
                'icon' => 'success',
                'title' => 'Logout berhasil',
                'text' => 'Anda berhasil keluar dari SIAKAD.',
            ])
            ->with('auth_changed', true);
    }
}
