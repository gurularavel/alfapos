<?php

namespace App\Http\Middleware;

use App\Support\PosTerminal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Terminal rejimində cihaz yalnız POS və Sifarişlər ekranlarını aça bilər,
 * kilidli ekrandan isə (PIN daxil edilməyibsə) heç bir dəyişiklik göndərilə bilməz.
 */
class TerminalGuard
{
    protected const ALLOWED = ['pos', 'pos.*', 'orders.*', 'invoices.receipt'];

    protected const ALWAYS_OPEN = ['pos.terminal.unlock', 'pos.terminal.lock', 'pos.terminal.exit'];

    public function __construct(protected PosTerminal $terminal) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $this->terminal->active()) {
            return $next($request);
        }

        $route = $request->route();
        if (! $route?->named(...self::ALLOWED)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Terminal rejimində bu bölmə bağlıdır.'], 403)
                : redirect()->route('pos');
        }

        if (! $request->isMethodSafe() && ! $route->named(...self::ALWAYS_OPEN) && $this->terminal->locked()) {
            return response()->json(['message' => 'Ekran kilidlidir. PIN daxil edin.', 'locked' => true], 423);
        }

        return $next($request);
    }
}
