<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse; // Cambiamos Response por JsonResponse
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Maneja la solicitud de registro entrante.
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validación de datos
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', Rules\Password::defaults()], // <--- Sin 'confirmed'
        ]);

        // 2. Creación del usuario en Aiven
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->string('password')),
            // Aquí podrías añadir valores por defecto para tus nuevos campos:
            'theme' => 'light',
            'notifications_enable' => true,
        ]);

        event(new Registered($user));

        // 3. Autenticación inmediata
        Auth::login($user);

        // 4. Generación del token para el Frontend (Sanctum)
        $token = $user->createToken('auth_token')->plainTextToken;

        // 5. Respuesta JSON completa
        return response()->json([
            'status' => 'success',
            'message' => 'Usuario registrado correctamente',
            'user' => $user,
            'token' => $token,
            'type' => 'bearer',
        ], 201);
    }
}