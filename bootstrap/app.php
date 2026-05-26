<?php


use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request; // <--- ESTO ES LO QUE FALTA
use Throwable;              // <--- ESTO ES RECOMENDABLE


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);


        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'spotify.check' => \App\Http\Middleware\EnsureSpotifyIsConnected::class,
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);


        $middleware->preventRequestForgery(except: [
            'api/login',
            'api/register',
            'api/admin/posts/*',
            'api/posts',
            'api/posts/*/like',
            'sanctum/csrf-cookie',
            'api/users/*/follow',
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

