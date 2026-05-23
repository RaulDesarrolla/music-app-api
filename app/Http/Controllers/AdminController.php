<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
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

            // 6. TASA DE INTERACCIÓN (Corregida con tu estructura real)
            // 💡 SOLUCIÓN: Al ser 'comment' el texto del post, medimos la interacción basándonos en la tabla 'likes'
            $totalLikes = DB::table('likes')->count();

            $engagementRate = $totalPosts > 0 
                ? round(($totalLikes / $totalPosts) * 100, 1) 
                : 0;

            // 7. USUARIOS ACTIVOS HOY
            $activeUsersToday = User::where('last_activity_at', '>=', Carbon::today())->count();

            // 8. GÉNERO MUSICAL PREDOMINANTE
            // 💡 SOLUCIÓN: Como la tabla posts no tiene la columna 'music_genre', fijamos un valor seguro para evitar errores SQL
            $topGenre = 'N/A';

            // 9. REPORTES ACTIVOS
            $activeReports = 0;

            // 10. RANKING DE CANCIONES MÁS COMPARTIDAS
            // Nota: Usamos 'track_name' para agrupar ya que tu tabla no usa un 'track_id' explícito en la migración aportada
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

            // 11. LISTADO DE PUBLICACIONES
            $posts = Post::orderBy('created_at', 'desc')
                ->take(10)
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
}