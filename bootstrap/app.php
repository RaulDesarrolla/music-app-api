<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 1. Esto añade automáticamente EnsureFrontendRequestsAreStateful
        $middleware->statefulApi();

        // 2. Definimos los alias que ya tenías
        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'spotify.check' => \App\Http\Middleware\EnsureSpotifyIsConnected::class,
        ]);

        // 3. LA SOLUCIÓN AL 419: Excluir las rutas de login/register del CSRF
        $middleware->preventRequestForgery(except: [
            'api/login',
            'api/register',
            'sanctum/csrf-cookie',
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            if ($request->is('api/*') || $request->is('sanctum/*')) {
                return true;
            }
            return $request->expectsJson();
        });
    })
    ->create();
