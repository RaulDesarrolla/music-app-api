<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    /**
     * Cierra la sesión del usuario y limpia los datos de la aplicación.
     */
    public function logout(Request $request)
    {
        // 1. Cerrar sesión en el Guard de Laravel
        Auth::logout();

        // 2. Invalidar la sesión del usuario para que el ID de sesión no sea reutilizable
        $request->session()->invalidate();

        // 3. Regenerar el token CSRF para prevenir ataques de fijación de sesión
        $request->session()->regenerateToken();

        // Redirigir a la página de inicio o login
        return redirect('/')->with('status', 'Has cerrado sesión correctamente.');
    }
}