<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class SpotifyController extends Controller
{
    /**
     * Redirige al usuario a la página de autorización de Spotify.
     */
    public function connect()
    {
        // config('services.spotify.redirect') debe apuntar a la URL con :8000
        $url = 'https://accounts.spotify.com/authorize?' . http_build_query([
            'client_id' => config('services.spotify.client_id'),
            'redirect_uri' => config('services.spotify.redirect'),
            'response_type' => 'code',
            'scope' => 'user-read-private user-read-email',
        ]);

        return redirect()->away($url);
    }

    /**
     * Maneja la respuesta de Spotify (callback).
     */
    public function callback(Request $request)
    {
        // 1. Verificar inmediatamente si el usuario está autenticado
        if (!auth()->check()) {
            return redirect()->route('login')->with('error', 'Tu sesión ha expirado. Por favor, inicia sesión de nuevo.');
        }

        $user = auth()->user();
        $code = $request->query('code');

        // 2. Si no hay código, redirigir al inicio para evitar el error de "code must be supplied"
        if (!$code) {
            return redirect()->route('spotify.connect')
                ->with('error', 'No se recibió el código de autorización. Inténtalo de nuevo.');
        }

        // 3. Petición POST a Spotify para obtener los tokens
        $response = Http::asForm()->post('https://accounts.spotify.com/api/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('services.spotify.redirect'),
            'client_id' => config('services.spotify.client_id'),
            'client_secret' => config('services.spotify.client_secret'),
        ]);

        $data = $response->json();

        // 4. Validar si la respuesta de Spotify fue exitosa
        if ($response->failed()) {
            return response()->json([
                'mensaje' => 'Error en la comunicación con Spotify',
                'detalle' => $data
            ], 400);
        }

        $user->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        return redirect('/dashboard');
    }
}
