<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);

        // Render terminates TLS at its edge and forwards plain HTTP to the
        // container, so without this Laravel sees every request as HTTP and
        // generates http:// asset/route URLs even though the real page is
        // HTTPS — trusting the proxy's X-Forwarded-* headers fixes that at
        // the source instead of patching every URL individually. Safe to
        // trust all proxies here since the container has no other public
        // ingress — Render's edge is the only thing that can reach it.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
