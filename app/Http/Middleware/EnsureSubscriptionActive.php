<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    public function __construct(protected Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenancy->tenant();

        if (! $tenant) {
            abort_unless($request->user()?->isSuperAdmin(), 403);

            return redirect()->route('admin.tenants.index');
        }

        if (! $tenant->hasActiveSubscription()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Abunəlik bitib.'], 402)
                : redirect()->route('subscription');
        }

        return $next($request);
    }
}
