<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class SpotifyController extends Controller
{

    private function getSpotifyToken()
    {
        $user = auth()->user();
        if (!$user)
            return null;

        if (!$user->access_token)
            return null;

        if ($user->expires_at && now()->addMinutes(5)->greaterThan($user->expires_at)) {
            return $this->refreshSpotifyToken($user);
        }

        return $user->access_token;
    }

    private function refreshSpotifyToken($user)
    {
        $response = Http::asForm()->post('https://accounts.spotify.com/api/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $user->refresh_token,
            'client_id' => config('services.spotify.client_id'),
            'client_secret' => config('services.spotify.client_secret'),
        ]);

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();

        $user->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $user->refresh_token,
            'expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        return $data['access_token'];
    }

    public function connect(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No autorizado. El token de sesión no es válido o ha expirado.'
                ], 401);
            }

            $userId = $user->id;

            $url = 'https://accounts.spotify.com/authorize?' . http_build_query([
                'client_id' => config('services.spotify.client_id'),
                'redirect_uri' => config('services.spotify.redirect'),
                'response_type' => 'code',
                'scope' => 'user-read-private user-read-email user-top-read streaming user-library-read user-read-playback-state user-modify-playback-state user-read-recently-played',
                'state' => $userId,
                'show_dialog' => true
            ]);

            return response()->json(['url' => $url], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error en connect: ' . $e->getMessage()
            ], 500);
        }
    }

    public function callback(Request $request)
    {
        $code = $request->query('code');
        $userId = $request->query('state');

        $user = \App\Models\User::find($userId);

        if (!$user) {
            return response("Error: Usuario no encontrado. Cierre sesión y vuelva a intentarlo.", 400);
        }

        $response = Http::asForm()->post('https://accounts.spotify.com/api/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('services.spotify.redirect'),
            'client_id' => config('services.spotify.client_id'),
            'client_secret' => config('services.spotify.client_secret'),
        ]);

        if ($response->failed()) {
            return response("Error al conectar con Spotify", 400);
        }

        $data = $response->json();

        $profileResponse = Http::withToken($data['access_token'])->get('https://api.spotify.com/v1/me');
        $spotifyId = $profileResponse->successful() ? $profileResponse->json()['id'] : null;

        $user->update([
            'spotify_id' => $spotifyId ?? $user->spotify_id,
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $user->refresh_token,
            'expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5185');

        return response("<script>
            window.location.href = '{$frontendUrl}/dashboard/personal?spotify=connected';
        </script>", 200)->header('Content-Type', 'text/html');
    }

    public function getPlayerToken()
    {
        try {
            if (!auth()->check()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado en el backend.'
                ], 401);
            }

            $token = $this->getSpotifyToken();

            if (!$token) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No conectado a Spotify'
                ], 404);
            }

            return response()->json(['access_token' => $token], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getProfile()
    {
        $token = $this->getSpotifyToken();
        if (!$token)
            return response()->json(['status' => 'error'], 401);

        $response = Http::withToken($token)->get('https://api.spotify.com/v1/me');

        if ($response->failed())
            return response()->json(['status' => 'error'], 401);

        $data = $response->json();

        return response()->json([
            'status' => 'success',
            'user' => [
                'name' => $data['display_name'] ?? 'Usuario',
                'photo' => $data['images'][0]['url'] ?? null,
                'spotify_url' => $data['external_urls']['spotify'] ?? null
            ]
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->input('query');
        $token = $this->getSpotifyToken();

        if (!$query)
            return response()->json(['error' => 'Escribe algo'], 400);
        if (!$token)
            return response()->json(['error' => 'No tienes conexión con Spotify'], 401);

        $response = Http::withToken($token)->get('https://api.spotify.com/v1/search', [
            'q' => $query,
            'type' => 'track',
            'limit' => 10
        ]);

        if ($response->failed())
            return response()->json(['error' => 'Error Spotify'], 500);

        $data = $response->json();
        $results = collect($data['tracks']['items'] ?? [])->map(function ($track) {
            return [
                'tipo' => 'cancion',
                'nombre' => $track['name'],
                'artista' => $track['artists'][0]['name'],
                'album' => $track['album']['name'],
                'portada' => $track['album']['images'][0]['url'] ?? null,
                'uri' => $track['uri'],
            ];
        });

        return response()->json(['status' => 'success', 'results' => $results]);
    }

    public function getWeeklyWrapped(Request $request)
    {
        $user = $request->user();

        $token = $this->getSpotifyToken();
        if (!$token) {
            return response()->json(['error' => 'No conectado a Spotify'], 404);
        }

        try {
            $response = Http::withToken($token)->get('https://api.spotify.com/v1/me/player/recently-played', [
                'limit' => 50
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Error al consultar la API de Spotify'], $response->status());
            }

            $playedTracks = $response->json()['items'] ?? [];

            $startOfWeek = Carbon::now()->startOfWeek(); 
            $endOfWeek = Carbon::now()->endOfWeek();     

            $totalMs = 0;
            $artistsCounter = [];
            $artistIds = []; 

            foreach ($playedTracks as $item) {
                $playedAt = Carbon::parse($item['played_at']);

                if ($playedAt->between($startOfWeek, $endOfWeek)) {
                    $track = $item['track'];

                    $totalMs += $track['duration_ms'];

                    foreach ($track['artists'] as $artist) {
                        $artistName = $artist['name'];
                        $artistsCounter[$artistName] = ($artistsCounter[$artistName] ?? 0) + 1;

                        $artistIds[$artistName] = $artist['id'];
                    }
                }
            }

            $topArtist = 'Ninguno';
            if (!empty($artistsCounter)) {
                arsort($artistsCounter); 
                $topArtist = array_key_first($artistsCounter);
            }

            $totalHours = round($totalMs / 3600000, 1);

            $topGenre = 'Desconocido';
            if ($topArtist !== 'Ninguno' && isset($artistIds[$topArtist])) {
                $artistId = $artistIds[$topArtist];
                $artistResponse = Http::withToken($token)->get("https://api.spotify.com/v1/artists/{$artistId}");

                if ($artistResponse->successful()) {
                    $genres = $artistResponse->json()['genres'] ?? [];
                    if (!empty($genres)) {
                        $topGenre = strtoupper($genres[0]); 
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'wrapped' => [
                    'hours_played' => $totalHours,
                    'top_artist' => $topArtist,
                    'top_genre' => $topGenre,
                    'start_date' => $startOfWeek->format('d/m'),
                    'end_date' => $endOfWeek->format('d/m')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Fallo en el servidor: ' . $e->getMessage()], 500);
        }
    }

    public function searchProfiles(Request $request)
    {
        try {
            $search = $request->query('query');

            if (blank($search)) {
                return response()->json([
                    'status' => 'success',
                    'results' => []
                ], 200);
            }

            $users = \App\Models\User::where('name', 'LIKE', "%{$search}%")
                ->orWhere('email', 'LIKE', "%{$search}%")
                ->select('id', 'name', 'email', 'created_at')
                ->limit(10)
                ->get();

            return response()->json([
                'status' => 'success',
                'results' => $users
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al buscar perfiles: ' . $e->getMessage()
            ], 500);
        }
    }    
}