<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Post;

class SpotifyController extends Controller
{
    /**
     * MÉTODO AUXILIAR (Privado)
     */
    private function getSpotifyProfileData()
    {
        $user = auth()->user();

        if (!$user || !$user->access_token)
            return null;

        $response = Http::withToken($user->access_token)
            ->get('https://api.spotify.com/v1/me');

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Conecta con Spotify (Redirección externa necesaria)
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
     * Callback de Spotify
     */
    public function callback(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $code = $request->query('code');

        if (!$code) {
            return response()->json(['error' => 'No se recibió el código de Spotify'], 400);
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
            return response()->json(['error' => 'Fallo al obtener tokens', 'detalle' => $data], 400);
        }

        auth()->user()->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        // Nota: Aquí podrías redirigir a la URL del frontend de tu compañero
        return response()->json(['status' => 'success', 'message' => 'Spotify conectado correctamente']);
    }

    /**
     * Obtener perfil (JSON en lugar de view)
     */
    public function getProfile()
    {
        $profileData = $this->getSpotifyProfileData();

        if (!$profileData) {
            auth()->user()->update(['access_token' => null]);
            return response()->json(['error' => 'Sesión de Spotify expirada'], 401);
        }

        // Devolvemos los datos directamente
        return response()->json([
            'status' => 'success',
            'profile' => $profileData
        ]);
    }

    /**
     * Alterna el seguimiento entre el usuario autenticado y otro usuario.
     */
    public function toggleFollow($id)
    {
        // 1. Obtenemos al usuario autenticado (el que hace la acción)
        $user = auth()->user();

        // 2. Verificamos que no intente seguirse a sí mismo
        if ($user->id == $id) {
            return response()->json([
                'status' => 'error',
                'message' => 'No puedes seguirte a ti mismo.'
            ], 400);
        }

        // 3. Usamos la función toggle() sobre la relación definida en el modelo User
        // Esto añadirá o quitará el ID de la tabla 'follows' automáticamente
        $result = $user->follows()->toggle($id);

        // 4. Determinamos qué acción se realizó para avisar al frontend
        $attached = count($result['attached']) > 0;

        return response()->json([
            'status' => 'success',
            'is_following' => $attached,
            'message' => $attached ? 'Ahora sigues a este usuario' : 'Has dejado de seguir a este usuario'
        ]);
    }

    /**
     * Obtiene las publicaciones de los usuarios seguidos y las propias.
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
     * Búsqueda (JSON en lugar de view)
     */
    public function search(Request $request)
    {
        $query = $request->input('query');

        if (!$query) {
            return response()->json(['error' => 'Escribe algo para buscar'], 400);
        }

        $user = auth()->user();
        $response = Http::withToken($user->access_token)
            ->get('https://api.spotify.com/v1/search', [
                'q' => $query,
                'type' => 'track,album',
                'limit' => 10
            ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Error en la búsqueda de Spotify'], 500);
        }

        $data = $response->json();
        $results = [];

        // Formateo de tracks (mantenemos tu lógica pero para el JSON)
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

        // Formateo de álbumes
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

        return response()->json([
            'status' => 'success',
            'results' => $results
        ]);
    }

    /**
     * Guardar Post (JSON con código 201)
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

        return response()->json([
            'status' => 'success',
            'message' => '¡Canción publicada con éxito!',
            'post' => $post
        ], 201); // 201 significa "Creado"
    }
}