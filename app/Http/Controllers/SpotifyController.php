<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Google\Cloud\Firestore\FirestoreClient;
use App\Models\Post;
use Carbon\Carbon;

class SpotifyController extends Controller
{
    protected $firestore;

    /**
     * Constructor blindado contra fallos de inicialización de Firebase
     */
    public function __construct(FirestoreClient $firestore = null)
    {
        try {
            $this->firestore = $firestore;
        } catch (\Exception $e) {
            // Si la librería de Google/Firebase falla al auto-instanciarse, no rompemos el controlador
            $this->firestore = null;
        }
    }

    /**
     * MÉTODO AUXILIAR (Privado) - Centraliza la obtención del token
     */
    private function getSpotifyToken()
    {
        $user = auth()->user();
        if (!$user)
            return null;

        // ✅ Leemos directamente del modelo User
        if (!$user->access_token)
            return null;

        // ✅ Comprobamos la expiración directa del User
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

        // ✅ Actualizamos los datos directamente en la tabla 'users'
        $user->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $user->refresh_token,
            'expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        return $data['access_token'];
    }

    /**
     * Conecta con Spotify - Blindado contra tokens nulos o sesiones inexistentes
     */
    public function connect(Request $request)
    {
        try {
            // Validamos de forma segura si el usuario existe en la petición
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No autorizado. El token de sesión no es válido o ha expirado.'
                ], 401);
            }

            $userId = $user->id;

            // ✅ Añadido 'user-read-recently-played' a la cadena de scopes
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

        // ✅ Guardamos los tokens directamente en las columnas del propio Usuario
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
        try {
            // 🌟 VALIDACIÓN DE SEGURIDAD: Si no hay sesión en Laravel, respondemos con 401 controlado
            if (!auth()->check()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado en el backend.'
                ], 401);
            }

            // Forzado el paso por la verificación y refresco automáticos
            $token = $this->getSpotifyToken();

            if (!$token) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No conectado a Spotify'
                ], 404);
            }

            return response()->json(['access_token' => $token], 200);

        } catch (\Exception $e) {
            // Evitamos cualquier colapso imprevisto devolviendo un JSON en vez de una pantalla de error rota
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ], 500);
        }
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
        try {
            $user = auth()->user() ?? \App\Models\User::find(1);

            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'Usuario no encontrado.'], 401);
            }

            // 1. Obtenemos los IDs de la gente a la que sigues desde la tabla pivote de Aiven
            $followingIds = \Illuminate\Support\Facades\DB::table('follows')
                ->where('follower_id', $user->id)
                ->pluck('followed_id') // Extrae solo la columna con los IDs (ej: [8, 12])
                ->toArray();

            // 2. Añadimos nuestro propio ID para ver también nuestros posts en el muro
            $userIdsForFeed = array_merge($followingIds, [$user->id]);

            // 3. Consultamos los posts filtrando SOLO por esos usuarios
            $posts = Post::whereIn('user_id', $userIdsForFeed)
                ->latest() // Ordena del más reciente al más antiguo
                ->get()
                ->map(function ($post) {
                    // Forzamos la carga del nombre del usuario de forma manual y segura por si no existe relación Eloquent
                    if (!$post->user) {
                        $owner = \App\Models\User::find($post->user_id);
                        $post->user = $owner ? ['id' => $owner->id, 'name' => $owner->name] : ['id' => null, 'name' => 'Anónimo'];
                    }

                    // Inyectamos el formato humano de la fecha para vuestro React
                    $post->created_at_human = $post->created_at ? $post->created_at->diffForHumans() : 'Ahora';

                    // --- NUEVA LÓGICA DE PUNTUACIÓN (RATING) ---
                    $rating = \Illuminate\Support\Facades\DB::table('ratings')
                        ->where('rateable_id', $post->id)
                        ->where('rateable_type', 'App\Models\Post')
                        ->value('rating');

                    // Si existe, lo casteamos a entero, si no, le ponemos 0
                    $post->rating = $rating ? (int) $rating : 0;
                    
                    return $post;
                });

            // Retornamos el feed limpio en formato JSON array directo para tu frontend
            return response()->json($posts, 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al cargar el feed filtrado: ' . $e->getMessage()
            ], 500);
        }
    }

    public function storePost(Request $request)
    {
        try {
            // 1. Extraemos los datos limpios del post
            $track = $request->input('track_name', 'Canción de Prueba');
            $artist = $request->input('artist_name', 'Artista Recomendado');
            $image = $request->input('image_url', 'https://placehold.co/150');
            $comment = $request->input('comment', '');
            $album = $request->input('album_name', $track);

            // Recogemos la puntuación que viene del Front (0 si no marcaron nada)
            $ratingValue = (int) $request->input('rating', 0);

            // ID de usuario fijo para local
            $userId = 1;

            // 2. Guardamos el Post primero para obtener su ID autogenerado por Aiven
            $post = new Post();
            $post->user_id = $userId;
            $post->track_name = (string) $track;
            $post->artist_name = (string) $artist;
            $post->album_name = (string) $album;
            $post->image_url = (string) $image;
            $post->comment = (string) $comment;
            $post->save(); // 👈 Aquí Aiven le asigna su ID (ej: 4, 5, 6...)

            // 3. LÓGICA DEL RATING: Si el usuario marcó estrellas (> 0), lo metemos en ratings
            if ($ratingValue > 0) {
                // Buscamos si existe el modelo Rating
                if (class_exists('App\Models\Rating')) {
                    $rating = new \App\Models\Rating();
                    $rating->user_id = $userId;
                    $rating->rating = $ratingValue;
                    $rating->rateable_id = $post->id; // 🔗 Vinculamos el ID del post recién creado
                    $rating->rateable_type = 'App\Models\Post'; // 🔗 Especificamos que es un Post
                    $rating->save();
                } else {
                    // Si tu compañero no creó el archivo App\Models\Rating.php, usamos Query Builder directo
                    \Illuminate\Support\Facades\DB::table('ratings')->insert([
                        'user_id' => $userId,
                        'rating' => $ratingValue,
                        'rateable_id' => $post->id,
                        'rateable_type' => 'App\Models\Post',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'post' => $post,
                'rating_saved' => $ratingValue > 0 ? true : false
            ], 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en storePost con Rating: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
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

    public function getUserProfile($id)
    {
        try {
            $user = \App\Models\User::find($id);

            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'Usuario no encontrado.'], 404);
            }

            $currentUser = auth()->user() ?? \App\Models\User::find(1);

            $followersCount = \Illuminate\Support\Facades\DB::table('follows')->where('followed_id', $id)->count();
            $followingCount = \Illuminate\Support\Facades\DB::table('follows')->where('follower_id', $id)->count();

            $isFollowing = false;
            if ($currentUser) {
                $isFollowing = \Illuminate\Support\Facades\DB::table('follows')
                    ->where('follower_id', $currentUser->id)
                    ->where('followed_id', $id)
                    ->exists();
            }

            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'followers_count' => $followersCount,
                'following_count' => $followingCount,
                'is_following' => $isFollowing
            ];

            $posts = Post::where('user_id', $id)
                ->latest()
                ->get()
                ->map(function ($post) {
                    $post->created_at_human = $post->created_at ? $post->created_at->diffForHumans() : 'Reciente';
                    return $post;
                });

            return response()->json([
                'status' => 'success',
                'user' => $userData,
                'posts' => $posts
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener el perfil de Aiven: ' . $e->getMessage()
            ], 500);
        }
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
            if (!$this->firestore) {
                return response()->json(['error' => 'Servicio de Firebase no disponible por fallo de inicialización.'], 503);
            }

            $data = [
                'post_id' => (int) $postId,
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name ?? 'Usuario',
                'content' => $request->input('content'),
                'created_at' => new \Google\Cloud\Core\Timestamp(new \DateTime()),
            ];

            $newComment = $this->firestore->collection('comments')->add($data);

            return response()->json([
                'status' => 'success',
                'comment_id' => $newComment->id()
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error en Firebase: ' . $e->getMessage()], 500);
        }
    }
}