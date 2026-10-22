<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** İstifadə: ->middleware('scope:POS') və ya 'scope:ORDERS,KITCHEN' (hər hansı biri kifayətdir). Admin hər şeyə daxil olur. */
class RequireScope
{
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $user = $request->user();

        if ($scopes === ['ADMIN']) {
            abort_unless($user?->isAdmin(), 403, 'Bu bölmə yalnız admin üçündür.');

            return $next($request);
        }

        foreach ($scopes as $scope) {
            if ($user?->hasScope($scope)) {
                return $next($request);
            }
        }

        abort(403, 'Bu bölməyə icazəniz yoxdur.');
    }
}
