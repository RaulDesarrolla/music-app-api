<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Maneja una petición entrante.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificamos si el usuario está autenticado mediante Sanctum y si su rol es 'admin'
        if ($request->user() && $request->user()->role === 'admin') {
            return $next($request);
        }

        // Si no es admin, cortamos la petición inmediatamente con un 403 Forbidden
        return response()->json([
            'error' => 'Acceso denegado. Se requieren permisos de administrador.'
        ], 403);
    }
}