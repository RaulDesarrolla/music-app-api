<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Google\Cloud\Firestore\FirestoreClient;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;

class PostController extends Controller
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
            $this->firestore = null;
        }
    }


    /**
     * Feed (Red Social)
     */
    /**
     * Feed (Red Social) - Corregido para persistir Likes
     */
    public function index()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'No autorizado.'], 401);
        }

        // Obtener los IDs de las personas a las que sigue el usuario
        $followingIds = DB::table('follows')
            ->where('follower_id', $user->id)
            ->pluck('followed_id')
            ->toArray();

        // El feed mostrará los posts de sus seguidos y los suyos propios
        $userIdsForFeed = array_merge($followingIds, [$user->id]);

        $posts = Post::with('user:id,name')
            ->whereIn('user_id', $userIdsForFeed)
            ->latest()
            ->get()
            ->map(function ($post) use ($user) {
                // 1. Formatear la fecha
                $post->created_at_human = $post->created_at ? $post->created_at->diffForHumans() : 'Ahora';

                // 2. Obtener la calificación (Estrellas)
                $rating = DB::table('ratings')
                    ->where('rateable_id', $post->id)
                    ->where('rateable_type', 'App\Models\Post')
                    ->value('rating');
                $post->rating = $rating ? (int) $rating : 0;

                // 🌟 3. CALCULAR EL TOTAL DE LIKES (Persistencia en refresh)
                $post->likes_count = DB::table('likes')
                    ->where('post_id', $post->id)
                    ->count();

                // 🌟 4. VERIFICAR SI EL USUARIO ACTUAL YA LE DIO LIKE (Persistencia en refresh)
                $post->is_liked = DB::table('likes')
                    ->where('post_id', $post->id)
                    ->where('user_id', $user->id)
                    ->exists();

                return $post;
            });

        return response()->json($posts, 200);
    }


    /**
     * Crear un nuevo post
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        try {
            $request->validate([
                'track_name' => 'required|string',
                'comment' => 'required|string',
            ]);

            $post = Post::create([
                'user_id' => $user->id,
                'track_name' => $request->input('track_name'),
                'artist_name' => $request->input('artist_name', 'Artista Recomendado'),
                'album_name' => $request->input('album_name', $request->track_name),
                'image_url' => $request->input('image_url', 'https://placehold.co/150'),
                'comment' => $request->comment,
            ]);

            $ratingValue = 0;
            if ($request->has('rating') && (int) $request->rating > 0) {
                $ratingValue = (int) $request->rating;
                DB::table('ratings')->insert([
                    'user_id' => $user->id,
                    'rating' => $ratingValue,
                    'rateable_id' => $post->id,
                    'rateable_type' => 'App\Models\Post',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 🌟 IMPORTANTE: Cargamos la relación del usuario para que React sepa quién publicó
            $post->load('user:id,name');

            // Formateamos las propiedades iniciales para que coincidan exactamente con la estructura de tu feed
            $post->created_at_human = 'Ahora';
            $post->rating = $ratingValue;
            $post->likes_count = 0;
            $post->is_liked = false;

            // Cambiamos a 'status' => 'success' para estandarizar tus endpoints
            return response()->json(['status' => 'success', 'post' => $post], 201);

        } catch (\Exception $e) {
            \Log::error('Error en storePost: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }


    /**
     * Store Comment (Firebase)
     */
    public function storeComment(Request $request, $postId)
    {
        $request->validate(['content' => 'required|string|max:500']);


        if (!$this->firestore) {
            return response()->json(['error' => 'Firebase no disponible'], 503);
        }


        $data = [
            'post_id' => (int) $postId,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->name ?? 'Usuario',
            'content' => $request->input('content'),
            'created_at' => new \Google\Cloud\Core\Timestamp(new \DateTime()),
        ];


        $newComment = $this->firestore->collection('comments')->add($data);


        return response()->json(['status' => 'success', 'comment_id' => $newComment->id()]);
    }

    /**
     * Perfil de usuario
     */
    public function getUserProfile($id)
    {
        $user = User::find($id);
        if (!$user)
            return response()->json(['message' => 'Usuario no encontrado'], 404);


        $currentUser = auth()->user();

        $followersCount = DB::table('follows')->where('followed_id', $id)->count();
        $followingCount = DB::table('follows')->where('follower_id', $id)->count();
        $isFollowing = $currentUser ? DB::table('follows')->where('follower_id', $currentUser->id)->where('followed_id', $id)->exists() : false;


        $posts = Post::where('user_id', $id)->latest()->get();


        return response()->json([
            'status' => 'success',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'followers_count' => $followersCount,
                'following_count' => $followingCount,
                'is_following' => $isFollowing
            ],
            'posts' => $posts
        ], 200);
    }

    public function toggleFollow($id)
    {
        $user = auth()->user();
        if ($user->id == $id)
            return response()->json(['error' => 'No puedes seguirte'], 400);


        $result = $user->follows()->toggle($id);
        return response()->json([
            'status' => 'success',
            'is_following' => count($result['attached']) > 0
        ]);
    }

    public function searchProfiles(Request $request)
    {
        $search = $request->query('query');
        $users = User::where('name', 'LIKE', "%{$search}%")->select('id', 'name', 'email')->limit(10)->get();
        return response()->json(['results' => $users]);
    }

    /**
     * Alternar Me gusta (Like / Unlike) en un Post
     */
    public function toggleLike($id)
    {
        // 📝 Esto escribirá en los logs de Laravel para saber si React se comunica con el backend
        \Log::info("Intentando dar like al post ID: " . $id);
        \Log::info("Usuario autenticado ID: " . auth()->id());

        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $post = Post::find($id);
        if (!$post) {
            \Log::error("El post con ID " . $id . " no fue encontrado en la base de datos.");
            return response()->json(['message' => 'El post no existe'], 404);
        }

        try {
            $likeExist = DB::table('likes')
                ->where('user_id', $user->id)
                ->where('post_id', $id);

            if ($likeExist->exists()) {
                $likeExist->delete();
                $isLiked = false;
                $message = 'Me gusta eliminado.';
            } else {
                DB::table('likes')->insert([
                    'user_id' => $user->id,
                    'post_id' => $id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $isLiked = true;
                $message = 'Me gusta agregado.';
            }

            $likesCount = DB::table('likes')->where('post_id', $id)->count();

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'is_liked' => $isLiked,
                'likes_count' => $likesCount
            ], 200);

        } catch (\Exception $e) {
            \Log::error("Error crítico en la base de datos al dar like: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
