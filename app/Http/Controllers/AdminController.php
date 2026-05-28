<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Report; // 💡 Modelo de reportes correctamente importado
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * 📊 Obtiene todas las métricas del panel y la cola de moderación.
     */
    public function getDashboardStats()
    {
        try {
            // 1. MÉTRICAS BASE (Usuarios y Posts)
            $totalUsers = User::count();
            $totalPosts = Post::count();

            // 2. TASA DE VINCULACIÓN DE SPOTIFY
            $connectedUsers = User::whereNotNull('spotify_id')->count();
            $spotifyBindRate = $totalUsers > 0
                ? round(($connectedUsers / $totalUsers) * 100, 1)
                : 0;

            // 3. ACTIVIDAD RECIENTE (Posts en las últimas 24 horas)
            $postsLast24h = Post::where('created_at', '>=', Carbon::now()->subDay())->count();

            // 4. PROMEDIO DE POSTS POR USUARIO
            $avgPosts = $totalUsers > 0
                ? round($totalPosts / $totalUsers, 1)
                : 0;

            // 5. ARTISTA TOP (El más repetido en la tabla de posts)
            $topArtistRecord = Post::whereNotNull('artist_name')
                ->select('artist_name', DB::raw('count(*) as total'))
                ->groupBy('artist_name')
                ->orderByDesc('total')
                ->first();
            $topArtist = $topArtistRecord ? $topArtistRecord->artist_name : 'N/A';

            // 6. TASA DE INTERACCIÓN
            $totalLikes = DB::table('likes')->count();
            $engagementRate = $totalPosts > 0
                ? round(($totalLikes / $totalPosts) * 100, 1)
                : 0;

            // 7. USUARIOS ACTIVOS HOY
            $activeUsersToday = User::where('last_activity_at', '>=', Carbon::today())->count();

            // 8. GÉNERO MUSICAL PREDOMINANTE
            $topGenre = 'N/A';

            // 9. REPORTES ACTIVOS (Cuenta los registros de la tabla pivote)
            $activeReports = Report::count();

            // 10. RANKING DE CANCIONES MÁS COMPARTIDAS
            $topSongs = Post::select(
                'track_name',
                'artist_name',
                'image_url',
                DB::raw('COUNT(*) as share_count')
            )
                ->whereNotNull('track_name')
                ->groupBy('track_name', 'artist_name', 'image_url')
                ->orderByDesc('share_count')
                ->take(5)
                ->get();

            // 11. COLA DE MODERACIÓN (Filtra únicamente los posts que TIENEN reportes activos)
            $posts = Post::whereHas('reports')
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get();

            // RETORNO DE LA RESPUESTA EXITOSA EN FORMATO JSON
            return response()->json([
                'success' => true,
                'metrics' => [
                    'total_users' => $totalUsers,
                    'total_posts' => $totalPosts,
                    'spotify_bind_rate' => $spotifyBindRate,
                    'posts_last_24h' => $postsLast24h,
                    'average_posts_per_user' => $avgPosts,
                    'top_artist' => $topArtist,
                    'engagement_rate' => $engagementRate,
                    'active_users_today' => $activeUsersToday,
                    'top_genre' => $topGenre,
                    'active_reports' => $activeReports,
                ],
                'top_songs' => $topSongs,
                'posts' => $posts
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar las estadísticas del panel.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 👍 APROBAR POST
     * Desestima las denuncias asociadas a este post recibiendo el ID en el body JSON.
     */
    public function approvePost(Request $request)
    {
        try {
            // Capturamos el post_id enviado en el cuerpo de la petición por React
            $id = $request->input('post_id');

            if (!$id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se proporcionó un ID de publicación válido.'
                ], 400);
            }

            // Verificamos si existen reportes activos para este post
            $hasReports = Report::where('post_id', $id)->exists();

            if (!$hasReports) {
                return response()->json([
                    'success' => false,
                    'message' => 'El mensaje no tenía reportes activos o ya fue procesado.'
                ], 404);
            }

            // Eliminamos todas las denuncias asociadas a este post_id
            Report::where('post_id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Mensaje aprobado con éxito. Se han removido las denuncias.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error interno al intentar aprobar el post.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 👎 ELIMINAR POST
     * Remueve el contenido por violar las normas recibiendo el ID en el body JSON.
     */
    public function destroyPost(Request $request)
    {
        try {
            // Capturamos el post_id del cuerpo de la petición de la misma forma estructurada
            $id = $request->input('post_id');

            if (!$id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se proporcionó un ID de publicación válido.'
                ], 400);
            }

            $post = Post::find($id);

            if (!$post) {
                return response()->json([
                    'success' => false,
                    'message' => 'El mensaje ya no se encuentra en el sistema o ya fue eliminado.'
                ], 404);
            }

            // Eliminación física (y remoción automática de reportes por el cascade en BD)
            $post->delete();

            return response()->json([
                'success' => true,
                'message' => 'El mensaje ha sido eliminado permanentemente de la plataforma.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error interno al intentar eliminar el post.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function reportPost(Request $request)
    {
        try {
            $postId = $request->input('post_id');
            $reason = $request->input('reason', 'Contenido inapropiado'); // Razón por defecto si viene vacía

            if (!$postId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se proporcionó un ID de publicación válido.'
                ], 400);
            }

            // Validar que el post realmente exista
            $postExists = Post::where('id', $postId)->exists();
            if (!$postExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'La publicación que intentas reportar ya no existe.'
                ], 404);
            }

            // Opcional: Evitar que un usuario reporte el mismo post varias veces
            $alreadyReported = Report::where('post_id', $postId)
                ->where('user_id', auth()->id())
                ->exists();

            if ($alreadyReported) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya has reportado esta publicación anteriormente.'
                ], 400);
            }

            // Guardar el reporte en la tabla "reports"
            Report::create([
                'post_id' => $postId,
                'user_id' => auth()->id(), // Captura el ID del token de Sanctum
                'reason' => $reason
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Publicación reportada con éxito. Será revisada por moderación.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el reporte.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}