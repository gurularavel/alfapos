<?php

namespace App\Http\Controllers;

use App\Rules\Turnstile;
use App\Services\TenantProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $this->verifyHuman($request, 'login');
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email və ya parol yanlışdır.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended($request->user()->homeRoute());
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request, TenantProvisioner $provisioner)
    {
        $this->verifyHuman($request, 'register');
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()\-]{9,20}$/'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'terms' => ['accepted'],
        ], [
            'phone.regex' => 'Telefon nömrəsi yalnız rəqəmlərdən ibarət olmalıdır, məs. +994 50 123 45 67.',
            'terms.accepted' => 'Qeydiyyat üçün İstifadə şərtləri və Məxfilik siyasəti ilə razılaşmalısınız.',
        ]);

        $user = $provisioner->create($data['business_name'], $data['name'], $data['email'], $data['password'], self::normalizePhone($data['phone'] ?? null));
        $user->forceFill(['terms_accepted_at' => now()])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Xoş gəldiniz! '.TenantProvisioner::TRIAL_DAYS.' günlük sınaq müddəti başladı.');
    }

    /** "+994 50 123 45 67" / "050 123 45 67" → "+994501234567" (maskadakı boşluqlar saxlanmır). */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '' || $digits === '994') {
            return null;
        }

        return '+'.(str_starts_with($digits, '0') ? '994'.substr($digits, 1) : $digits);
    }

    /** Cloudflare Turnstile: parol / qeydiyyat yoxlanmadan əvvəl (açarlar konfiqurasiya olunmayıbsa keçir). */
    protected function verifyHuman(Request $request, string $action): void
    {
        if (Turnstile::enabled()) {
            $request->validate(['cf-turnstile-response' => ['required', new Turnstile($action)]], ['cf-turnstile-response.required' => 'Robot olmadığınızı təsdiqləyin.']);
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
