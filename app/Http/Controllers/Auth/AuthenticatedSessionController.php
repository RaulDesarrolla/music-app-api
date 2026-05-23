<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse; 
use Illuminate\Support\Facades\Auth;
use Throwable; // 👈 Importamos la clase global de errores de PHP

class AuthenticatedSessionController extends Controller
{
    /**
     * Maneja la solicitud de inicio de sesión con captura de diagnóstico.
     */
   public function store(LoginRequest $request): JsonResponse
    {
        // 1. Valida las credenciales (Email y Password)
        // Si fallan, Laravel enviará un JSON 422 controlado automáticamente gracias a bootstrap/app.php
        $request->authenticate();

        // 2. Recuperamos el usuario validado desde el núcleo de autenticación
        $user = Auth::user();

        // 3. Generamos el token plano para tu aplicación de React
        $token = $user->createToken('auth_token')->plainTextToken;

        // 4. Respuesta estructurada de éxito
        return response()->json([
            'status' => 'success',
            'message' => 'Login exitoso',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token, 
            'token_type' => 'Bearer',
        ], 200);
    }
}