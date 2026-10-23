<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/** Daxil olmuş istifadəçinin tenant-ını aktiv edir; deaktiv istifadəçini çıxarır. */
class IdentifyTenant
{
    public function __construct(protected Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Hesabınız deaktiv edilib.']);
        }

        if ($user?->tenant) {
            $this->tenancy->set($user->tenant); // saat qurşağını da tətbiq edir
            View::share('tenant', $user->tenant);
        }

        return $next($request);
    }
}
