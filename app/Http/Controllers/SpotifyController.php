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

        $spotifyToken = $user->spotifyToken;
        if (!$spotifyToken)
            return null;

        if (now()->addMinutes(5)->greaterThan($spotifyToken->expires_at)) {
            return $this->refreshSpotifyToken($user);
        }

        return $spotifyToken->access_token;
    }

    private function refreshSpotifyToken($user)
    {
        $spotifyToken = $user->spotifyToken;

        // ✅ URL Oficial de Cuentas de Spotify para refrescar tokens
        $response = Http::asForm()->post('https://accounts.spotify.com/api/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $spotifyToken->refresh_token,
            'client_id' => config('services.spotify.client_id'),
            'client_secret' => config('services.spotify.client_secret'),
        ]);

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();

        // Actualizamos los datos en la base de datos
        $spotifyToken->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $spotifyToken->refresh_token,
            'expires_in' => $data['expires_in'],
            'expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        return $data['access_token'];
    }

    /**
     * Conecta con Spotify
     */
    public function connect(Request $request)
    {
        $token = $request->query('token');
        $userId = null;

        if ($token) {
            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if ($accessToken) {
                $userId = $accessToken->tokenable_id;
            }
        }

        if (!$userId) {
            return response()->json(['error' => 'No autorizado. Token de usuario inválido o ausente.'], 401);
        }

        // ✅ URL Oficial del Diálogo de Autenticación de Spotify
        $url = 'https://accounts.spotify.com/authorize?' . http_build_query([
            'client_id' => config('services.spotify.client_id'),
            'redirect_uri' => config('services.spotify.redirect'),
            'response_type' => 'code',
            'scope' => 'user-read-private user-read-email user-top-read streaming user-library-read user-read-playback-state user-modify-playback-state',
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

        // Guardamos el token de Spotify
        $user->spotifyToken()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? ($user->spotifyToken->refresh_token ?? null),
                'expires_in' => $data['expires_in'],
                'expires_at' => now()->addSeconds($data['expires_in']),
            ]
        );

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