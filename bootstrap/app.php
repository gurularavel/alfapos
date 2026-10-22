<?php

use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\RequireScope;
use App\Http\Middleware\TerminalGuard;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', [IdentifyTenant::class, TerminalGuard::class]);
        // Tenant scope route model binding-dən əvvəl aktiv olmalıdır, yoxsa başqa tenant-ın ID-si ilə model açılar.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: IdentifyTenant::class,
        );
        $middleware->alias([
            'subscribed' => EnsureSubscriptionActive::class,
            'scope' => RequireScope::class,
        ]);
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()->homeRoute());
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
