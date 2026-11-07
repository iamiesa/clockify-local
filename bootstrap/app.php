<?php

use App\Http\Middleware\EnsureCurrentWorkspace;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render (and most hosts) terminate HTTPS at a proxy in front of the app.
        $middleware->trustProxies(at: '*');
        $middleware->alias(['workspace' => EnsureCurrentWorkspace::class]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/tracker');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
