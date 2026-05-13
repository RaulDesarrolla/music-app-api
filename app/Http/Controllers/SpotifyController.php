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
     * MÉTODO AUXILIAR (Privado)
     */
    private function getSpotifyProfileData()
    {
        $user = auth()->user();

        // Intentamos obtener el token a través de la relación hasOne
        // El operador ?-> (nullsafe) evita errores si el usuario no tiene registro en spotify_tokens
        $token = $user?->spotifyToken?->access_token;

        if (!$token) {
            return null;
        }

        // Petición a Spotify usando el token almacenado en Aiven
        $response = Http::withToken($token)
            ->get('https://api.spotify.com/v1/me');

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }
    /**
     * Conecta con Spotify
     */
    public function connect()
    {
        $url = 'https://accounts.spotify.com/authorize?' . http_build_query([
            'client_id' => config('services.spotify.client_id'),
            'redirect_uri' => config('services.spotify.redirect'),
            'response_type' => 'code',
            'scope' => 'user-read-private user-read-email user-top-read',
            'state' => auth()->id(), // <--- AÑADIMOS ESTO: Enviamos el ID del usuario actual
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

        // 1. Buscamos al usuario
        $user = auth()->user() ?? \App\Models\User::find($userId);

        if (!$user) {
            return response()->json(['error' => 'Usuario no identificado'], 401);
        }

        if (!$code) {
            return response()->json(['error' => 'No se recibió el código'], 400);
        }

        // 2. Petición a Spotify para obtener los tokens
        $response = Http::asForm()->post('https://accounts.spotify.com/api/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('services.spotify.redirect'),
            'client_id' => config('services.spotify.client_id'),
            'client_secret' => config('services.spotify.client_secret'),
        ]);

        $data = $response->json();

        if ($response->failed()) {
            return response()->json(['error' => 'Fallo al obtener tokens', 'detalle' => $data], 400);
        }

        // Usamos la relación definida en el modelo User
        $user->spotifyToken()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'access_token' => $data['access_token'],
                // Spotify solo envía el refresh_token la primera vez o si se pide offline
                'refresh_token' => $data['refresh_token'] ?? ($user->spotifyToken->refresh_token ?? null),
                'expires_in' => $data['expires_in'],
                'expires_at' => now()->addSeconds($data['expires_in']),
            ]
        );

        return redirect('http://localhost:5180/dashboard?spotify=connected');
    }

    /**
     * Obtener perfil de usuario filtrado (Nombre y Foto)
     */
    public function getProfile()
    {
        $profileData = $this->getSpotifyProfileData();

        // Si no hay datos, significa que el token no existe o ha expirado
        if (!$profileData) {
            return response()->json([
                'status' => 'error',
                'message' => 'No hay conexión activa con Spotify'
            ], 401);
        }

        // Spotify devuelve las fotos en un array llamado 'images'
        $photoUrl = null;
        if (!empty($profileData['images']) && isset($profileData['images'][0]['url'])) {
            $photoUrl = $profileData['images'][0]['url'];
        }

        return response()->json([
            'status' => 'success',
            'user' => [
                'name' => $profileData['display_name'] ?? 'Usuario',
                'photo' => $photoUrl,
                'spotify_url' => $profileData['external_urls']['spotify'] ?? null
            ]
        ]);
    }

    /**
     * Follow Toggle
     */
    public function toggleFollow($id)
    {
        $user = auth()->user();

        if ($user->id == $id) {
            return response()->json(['status' => 'error', 'message' => 'No puedes seguirte a ti mismo.'], 400);
        }

        $result = $user->follows()->toggle($id);
        $attached = count($result['attached']) > 0;

        return response()->json([
            'status' => 'success',
            'is_following' => $attached,
            'message' => $attached ? 'Ahora sigues a este usuario' : 'Has dejado de seguir a este usuario'
        ]);
    }

    /**
     * Get Feed
     */
    public function getFeed()
    {
        $user = auth()->user();
        $followedIds = $user->follows()->pluck('followed_id')->toArray();
        $followedIds[] = $user->id;

        $feed = Post::whereIn('user_id', $followedIds)
            ->with('user:id,name')
            ->withCount('likes')
            ->withExists([
                'likes as is_liked' => function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                }
            ])
            ->latest()
            ->paginate(15);

        return response()->json($feed);
    }

    /**
     * Search Spotify
     */
    public function search(Request $request)
    {
        $query = $request->input('query');
        if (!$query)
            return response()->json(['error' => 'Escribe algo'], 400);

        $user = auth()->user();
        $response = Http::withToken($user->access_token)
            ->get('https://api.spotify.com/v1/search', [
                'q' => $query,
                'type' => 'track,album',
                'limit' => 10
            ]);

        if ($response->failed())
            return response()->json(['error' => 'Error Spotify'], 500);

        $data = $response->json();
        $results = [];

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

        return response()->json(['status' => 'success', 'results' => $results]);
    }

    /**
     * Store Post
     */
    public function storePost(Request $request)
    {
        $validated = $request->validate([
            'track_name' => 'required|string',
            'artist_name' => 'required|string',
            'image_url' => 'required|url',
        ]);

        $post = Post::create([
            'user_id' => auth()->id(),
            'track_name' => $request->track_name,
            'artist_name' => $request->artist_name,
            'album_name' => $request->album_name,
            'image_url' => $request->image_url,
            'comment' => $request->comment,
        ]);

        return response()->json(['status' => 'success', 'post' => $post], 201);
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

            // Creamos el array de datos fuera del método add para evitar errores de sintaxis
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