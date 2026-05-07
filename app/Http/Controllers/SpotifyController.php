<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Post;

class SpotifyController extends Controller
{
    /**
     * MÉTODO AUXILIAR (Privado)
     * Obtiene los datos del perfil de Spotify sin devolver una vista.
     */
    private function getSpotifyProfileData()
    {
        $user = auth()->user();
        $response = Http::withToken($user->access_token)
            ->get('https://api.spotify.com/v1/me');

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

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

    public function callback(Request $request)
    {
        if (!auth()->check()) {
            return redirect()->route('login')->with('error', 'Tu sesión ha expirado.');
        }

        $user = auth()->user();
        $code = $request->query('code');

        if (!$code) {
            return redirect()->route('spotify.connect')->with('error', 'No se recibió el código.');
        }

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

        $user->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        return redirect()->route('dashboard')->with('status', 'Conectado a Spotify');
    }

    /**
     * Muestra el Dashboard inicial
     */
    public function getProfile()
    {
        $profileData = $this->getSpotifyProfileData();

        if (!$profileData) {
            auth()->user()->update(['access_token' => null]);
            return redirect()->route('spotify.prompt')->with('error', 'Sesión expirada.');
        }

        return view('dashboard', ['profile' => $profileData]);
    }

    /**
     * Realiza la búsqueda y devuelve la misma vista con resultados
     */
    public function search(Request $request)
    {
        $query = $request->input('query');
        $profileData = $this->getSpotifyProfileData(); // Obtenemos el perfil para la vista

        if (!$query) {
            return redirect()->route('dashboard')->with('error', 'Escribe algo para buscar.');
        }

        $user = auth()->user();
        $response = Http::withToken($user->access_token)
            ->get('https://api.spotify.com/v1/search', [
                'q' => $query,
                'type' => 'track,album',
                'limit' => 10
            ]);

        if ($response->failed()) {
            return redirect()->route('dashboard')->with('error', 'Error en la búsqueda.');
        }

        $data = $response->json();
        $results = [];

        // Formatear canciones
        if (isset($data['tracks'])) {
            foreach ($data['tracks']['items'] as $track) {
                $results[] = [
                    'tipo' => 'cancion',
                    'nombre' => $track['name'],
                    'artista' => $track['artists'][0]['name'],
                    'album' => $track['album']['name'],
                    'portada' => $track['album']['images'][0]['url'] ?? null,
                ];
            }
        }

        // Formatear álbumes
        if (isset($data['albums'])) {
            foreach ($data['albums']['items'] as $album) {
                $results[] = [
                    'tipo' => 'album',
                    'nombre' => $album['name'],
                    'artista' => $album['artists'][0]['name'],
                    'album' => $album['name'],
                    'portada' => $album['images'][0]['url'] ?? null,
                ];
            }
        }

        return view('dashboard', [
            'profile' => $profileData,
            'results' => $results
        ]);
    }

    public function storePost(Request $request)
    {
        $request->validate([
            'track_name' => 'required|string',
            'artist_name' => 'required|string',
            'image_url' => 'required|url',
        ]);

        Post::create([
            'user_id' => auth()->id(),
            'track_name' => $request->track_name,
            'artist_name' => $request->artist_name,
            'album_name' => $request->album_name,
            'image_url' => $request->image_url,
            'comment' => $request->comment,
        ]);

        return redirect()->route('dashboard')->with('success', '¡Canción publicada con éxito!');
    }
}