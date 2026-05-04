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
        // 1. Verificar autenticación local
        if (!auth()->check()) {
            return redirect()->route('login')->with('error', 'Tu sesión ha expirado.');
        }

        $user = auth()->user();
        $code = $request->query('code');

        // 2. Validar que recibimos el código de Spotify
        if (!$code) {
            return redirect()->route('spotify.connect')
                ->with('error', 'No se recibió el código de autorización.');
        }

        // 3. Intercambio de código por Tokens
        $response = Http::asForm()->post('https://accounts.spotify.com/api/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('services.spotify.redirect'),
            'client_id' => config('services.spotify.client_id'),
            'client_secret' => config('services.spotify.client_secret'),
        ]);

        $data = $response->json();

        if ($response->failed()) {
            return response()->json(['error' => 'Fallo en tokens', 'detalle' => $data], 400);
        }

        // 4. Guardar tokens y redirigir al Dashboard
        // Usamos update para persistir los datos en Aiven
        $user->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        // Redirigimos a la ruta nombrada 'dashboard'
        return redirect()->route('dashboard')->with('status', 'Conectado a Spotify');
    }

    /**
     * Obtiene y muestra el perfil del usuario en el Dashboard.
     */
    public function getProfile()
    {
        $user = auth()->user();

        // Verificamos si tenemos un token antes de intentar la petición
        if (!$user->access_token) {
            return redirect()->route('spotify.connect');
        }

        $response = Http::withToken($user->access_token)
            ->get('https://api.spotify.com/v1/me');

        if ($response->failed()) {
            return redirect('/')->with('error', 'Error al consultar Spotify.');
        }

        $profileData = $response->json();

        // Opcional: Actualizar el nombre del usuario con su nombre real de Spotify
        $user->update([
            'name' => $profileData['display_name'] ?? $user->name,
            'spotify_id' => $profileData['id'] ?? null,
        ]);

        return view('dashboard', ['profile' => $profileData]);
    }
}