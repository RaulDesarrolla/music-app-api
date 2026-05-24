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

        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);

        // 2. Definimos los alias que ya tenías
        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'spotify.check' => \App\Http\Middleware\EnsureSpotifyIsConnected::class,
        ]);

        // ! 'api/admin/posts/*' -> Ruta de aprobación y eliminación de posts 
        // ! 'api/posts' -> Ruta de creación de posts (feed)
        $middleware->preventRequestForgery(except: [
            'api/login',
            'api/register',
            'api/admin/posts/*',
            'api/posts',
            'sanctum/csrf-cookie',
            'api/users/*/follow',
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }

            return $request->expectsJson();
        });
    })
    ->create();
