<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse; // Usamos JsonResponse para la API
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Maneja la solicitud de inicio de sesión.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        // 1. Valida las credenciales (Email y Password)
        $request->authenticate();

        // 2. Obtenemos el usuario autenticado
        $user = $request->user();

        // 3. Generamos el token de acceso para el frontend
        $token = $user->createToken('auth_token')->plainTextToken;

        // 4. Devolvemos la respuesta con el token y datos básicos
        return response()->json([
            'status' => 'success',
            'message' => 'Login exitoso',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token, // Ahora coincide con lo que busca tu compañero
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Elimina la sesión y los tokens del usuario.
     */
    public function destroy(Request $request): JsonResponse
    {
        // 1. Revocamos el token actual que se está usando
        $request->user()->currentAccessToken()->delete();

        // 2. Logout tradicional de la guardia web (opcional en APIs puras)
        Auth::guard('web')->logout();

        return response()->json([
            'status' => 'success',
            'message' => 'Sesión cerrada y token eliminado'
        ]);
    }
}