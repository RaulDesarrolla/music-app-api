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
            'scope' => 'user-read-private user-read-email user-top-read',
        ]);

        // Opción A: Redirección estándar de Laravel (intentar primero)
        return redirect()->away($url);  
    }

    /**
     * Maneja la respuesta de Spotify (callback).
     */
    public function callback(Request $request)
    {
        // 1. Intercambiar el código por tokens
        $response = Http::asForm()->post('https://accounts.spotify.com/api/token', [
            'grant_type' => 'authorization_code',
            'code' => $request->code,
            'redirect_uri' => config('services.spotify.redirect'),
            'client_id' => config('services.spotify.client_id'),
            'client_secret' => config('services.spotify.client_secret'),
        ]);

        $data = $response->json();

        // 2. Obtener datos del perfil del usuario desde Spotify
        $userProfile = Http::withToken($data['access_token'])
            ->get('https://api.spotify.com/v1/me')
            ->json();

        // 3. Actualizar el usuario autenticado en nuestra DB de Aiven
        $user = Auth::user();
        $user->update([
            'spotify_id' => $userProfile['id'],
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        return redirect()->route('dashboard')->with('status', 'Spotify conectado correctamente.');
    }
}