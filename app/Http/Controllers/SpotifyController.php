<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Google\Cloud\Firestore\FirestoreClient;
use App\Models\Post;
use Carbon\Carbon;

class SpotifyController extends Controller
{
    /**
     * MÉTODO AUXILIAR (Privado) - Centraliza la obtención del token
     */
    private function getSpotifyToken()
    {
        $user = auth()->user();
        if (!$user)
            return null;

        // ✅ CORREGIDO: Leemos directamente del modelo User
        if (!$user->access_token)
            return null;

        // ✅ CORREGIDO: Comprobamos la expiración directa del User
        if ($user->expires_at && now()->addMinutes(5)->greaterThan($user->expires_at)) {
            return $this->refreshSpotifyToken($user);
        }

        return $user->access_token;
    }

    private function refreshSpotifyToken($user)
    {
        // ✅ URL Oficial de Cuentas de Spotify para refrescar tokens
        $response = Http::asForm()->post('https://accounts.spotify.com/api/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $user->refresh_token, // <-- Campo directo del User
            'client_id' => config('services.spotify.client_id'),
            'client_secret' => config('services.spotify.client_secret'),
        ]);

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();

        // ✅ CORREGIDO: Actualizamos los datos directamente en la tabla 'users'
        $user->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $user->refresh_token,
            'expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        return $data['access_token'];
    }

    /**
     * Conecta con Spotify
     */
    public function connect(Request $request)
    {
        $userId = $request->user()->id;

        // ✅ CORREGIDO: Añadido 'user-read-recently-played' a la cadena de scopes
        $url = 'https://accounts.spotify.com/authorize?' . http_build_query([
            'client_id' => config('services.spotify.client_id'),
            'redirect_uri' => config('services.spotify.redirect'),
            'response_type' => 'code',
            'scope' => 'user-read-private user-read-email user-top-read streaming user-library-read user-read-playback-state user-modify-playback-state user-read-recently-played',
            'state' => $userId,
            'show_dialog' => true
        ]);

        return response()->json(['url' => $url]);
    }

    /**
     * Callback de Spotify
     */
    public function callback(Request $request)
    {
        $code = $request->query('code');
        $userId = $request->query('state');

        $user = \App\Models\User::find($userId);

        if (!$user) {
            return response("Error: Usuario no encontrado. Cierre sesión y vuelva a intentarlo.", 400);
        }

        // ✅ URL Oficial de Spotify para intercambio de código de autorización
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

        // Obtener el perfil de spotify para guardar el spotify_id (opcional pero muy útil)
        $profileResponse = Http::withToken($data['access_token'])->get('https://api.spotify.com/v1/me');
        $spotifyId = $profileResponse->successful() ? $profileResponse->json()['id'] : null;

        // ✅ CORREGIDO: Guardamos los tokens directamente en las columnas del propio Usuario
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

    /**
     * Devuelve el token específicamente para el SDK de React (Web Playback SDK)
     */
    public function getPlayerToken()
    {
        // ✅ Forzado el paso por la verificación y refresco automáticos
        $token = $this->getSpotifyToken();

        if (!$token) {
            return response()->json(['status' => 'error', 'message' => 'No conectado a Spotify'], 404);
        }

        return response()->json(['access_token' => $token]);
    }

    /**
     * Obtener perfil de usuario filtrado para la pestaña Personal
     */
    public function getProfile()
    {
        $token = $this->getSpotifyToken();
        if (!$token)
            return response()->json(['status' => 'error'], 401);

        // ✅ URL Oficial del Perfil de Usuario de la API de Spotify
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

    /**
     * Buscador de canciones para el input de Personal.jsx
     */
    public function search(Request $request)
    {
        $query = $request->input('query');
        $token = $this->getSpotifyToken();

        if (!$query)
            return response()->json(['error' => 'Escribe algo'], 400);
        if (!$token)
            return response()->json(['error' => 'No tienes conexión con Spotify'], 401);

        // ✅ URL Oficial de Búsqueda de la API de Spotify
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

    // ... tus otros métodos (getSpotifyToken, etc.)

    public function getWeeklyWrapped(Request $request)
    {
        $user = $request->user();

        // 1. Obtener el token válido de Spotify (usando tu lógica existente)
        $token = $this->getSpotifyToken();
        if (!$token) {
            return response()->json(['error' => 'No conectado a Spotify'], 404);
        }

        try {
            // 2. Consultar el historial reciente de Spotify (máximo permitido: 50 items)
            $response = Http::withToken($token)->get('https://api.spotify.com/v1/me/player/recently-played', [
                'limit' => 50
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Error al consultar la API de Spotify'], $response->status());
            }

            $playedTracks = $response->json()['items'] ?? [];

            // 3. Definir los límites de la semana en curso (Lunes a Domingo)
            $startOfWeek = Carbon::now()->startOfWeek(); // Lunes 00:00
            $endOfWeek = Carbon::now()->endOfWeek();     // Domingo 23:59

            $totalMs = 0;
            $artistsCounter = [];
            $artistIds = []; // Guardaremos los IDs para pedir los géneros después

            foreach ($playedTracks as $item) {
                $playedAt = Carbon::parse($item['played_at']);

                // Filtrar: Solo nos interesan las canciones de la semana actual
                if ($playedAt->between($startOfWeek, $endOfWeek)) {
                    $track = $item['track'];

                    // Sumar la duración de la canción en milisegundos
                    $totalMs += $track['duration_ms'];

                    // Contar artistas
                    foreach ($track['artists'] as $artist) {
                        $artistName = $artist['name'];
                        $artistsCounter[$artistName] = ($artistsCounter[$artistName] ?? 0) + 1;

                        // Guardamos el ID del primer artista para sacar su género luego
                        $artistIds[$artistName] = $artist['id'];
                    }
                }
            }

            // 4. Calcular el cantante más escuchado
            $topArtist = 'Ninguno';
            if (!empty($artistsCounter)) {
                arsort($artistsCounter); // Ordena de mayor a menor
                $topArtist = array_key_first($artistsCounter);
            }

            // 5. Calcular las horas escuchadas (Milisegundos -> Horas con 1 decimal)
            // Fórmula: ms / (1000 * 60 * 60)
            $totalHours = round($totalMs / 3600000, 1);

            // 6. Obtener el género más escuchado
            // Nota: Las canciones no traen género, hay que pedir el género del artista TOP a Spotify
            $topGenre = 'Desconocido';
            if ($topArtist !== 'Ninguno' && isset($artistIds[$topArtist])) {
                $artistId = $artistIds[$topArtist];
                $artistResponse = Http::withToken($token)->get("https://api.spotify.com/v1/artists/{$artistId}");

                if ($artistResponse->successful()) {
                    $genres = $artistResponse->json()['genres'] ?? [];
                    if (!empty($genres)) {
                        $topGenre = strtoupper($genres[0]); // Ej: "TECHNO" o "POP"
                    }
                }
            }

            // 7. Retornar la respuesta limpia para React
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


    /**
     * Feed y Posts (Lógica Social)
     */
    public function getFeed()
    {
        $user = auth()->user();
        $followedIds = $user->follows()->pluck('followed_id')->push($user->id);

        $feed = Post::whereIn('user_id', $followedIds)
            ->with('user:id,name')
            ->withCount('likes')
            ->withExists(['likes as is_liked' => fn($q) => $q->where('user_id', $user->id)])
            ->latest()
            ->paginate(15);

        return response()->json($feed);
    }

    public function storePost(Request $request)
    {
        $request->validate([
            'track_name' => 'required|string',
            'artist_name' => 'required|string',
            'image_url' => 'required|url',
        ]);

        $post = Post::create(array_merge($request->all(), ['user_id' => auth()->id()]));

        return response()->json(['status' => 'success', 'post' => $post], 201);
    }

    public function toggleFollow($id)
    {
        $user = auth()->user();
        if ($user->id == $id)
            return response()->json(['error' => 'No puedes seguirte'], 400);

        $result = $user->follows()->toggle($id);
        $attached = count($result['attached']) > 0;

        return response()->json([
            'status' => 'success',
            'is_following' => $attached,
            'message' => $attached ? 'Siguiendo' : 'Dejado de seguir'
        ]);
    }

    /**
     * Store Comment (Firebase)
     */
    public function storeComment(Request $request, $postId)
    {
        $request->validate([
            'content' => 'required|string|max:500',
        ]);

        try {
            $path = env('FIREBASE_CREDENTIALS', 'storage/app/firebase_credentials.json');
            $credentialsPath = base_path($path);

            if (!file_exists($credentialsPath)) {
                return response()->json(['error' => 'Credenciales no encontradas'], 500);
            }

            $firestore = new FirestoreClient(['keyFilePath' => $credentialsPath]);

            $data = [
                'post_id' => (int) $postId,
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name,
                'content' => $request->input('content'),
                'created_at' => new \Google\Cloud\Core\Timestamp(new \DateTime()),
            ];

            $newComment = $firestore->collection('comments')->add($data);

            return response()->json([
                'status' => 'success',
                'comment_id' => $newComment->id()
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}