<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureSpotifyIsConnected
{
    /**
     * Verifica si el usuario autenticado tiene vinculada su cuenta de Spotify.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // 1. Si está logueado pero no tiene token
        // 2. Y no está intentando ya conectar o en el callback (para evitar bucle infinito)
        if ($user && !$user->access_token && !$request->is('spotify/*', 'connect-spotify')) {
            return redirect()->route('spotify.prompt');
        }

        return $next($request);
    }
}