<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Report; 
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AdminController extends Controller
{

    public function getDashboardStats()
    {
        try {
            $totalUsers = User::count();
            $totalPosts = Post::count();

            $connectedUsers = User::whereNotNull('spotify_id')->count();
            $spotifyBindRate = $totalUsers > 0
                ? round(($connectedUsers / $totalUsers) * 100, 1)
                : 0;

            $postsLast24h = Post::where('created_at', '>=', Carbon::now()->subDay())->count();

            $avgPosts = $totalUsers > 0
                ? round($totalPosts / $totalUsers, 1)
                : 0;

            $totalLikes = DB::table('likes')->count();
            $engagementRate = $totalPosts > 0
                ? round(($totalLikes / $totalPosts) * 100, 1)
                : 0;

            $startOfTodayTimestamp = Carbon::today()->timestamp;
            $activeUsersToday = DB::table('sessions')
                ->whereNotNull('user_id')
                ->where('last_activity', '>=', $startOfTodayTimestamp)
                ->distinct('user_id')
                ->count('user_id');

            if ($activeUsersToday === 0) {
                $activeUsersToday = User::whereIn('id', function ($query) {
                    $query->select('user_id')
                        ->from('posts')
                        ->where('created_at', '>=', Carbon::today());
                })->count();
            }

            $topGenre = 'N/A';
            $samplePosts = Post::select('album_name', 'track_name')
                ->whereNotNull('album_name')
                ->orderByDesc('created_at')
                ->take(50)
                ->get();

            if ($samplePosts->isNotEmpty()) {
                $genreKeywords = [
                    'Pop' => ['pop', 'hits', 'love', 'dance', 'star', 'music'],
                    'Rock' => ['rock', 'metal', 'guitar', 'live', 'stone', 'dead'],
                    'Urban/Reggaeton' => ['urban', 'reggaeton', 'remix', 'trap', 'rap', 'hip hop', 'latin', 'el', 'la', 'los'],
                    'Indie/Alternative' => ['indie', 'alternative', 'acoustic', 'folk', 'session'],
                    'Electronic' => ['electronic', 'house', 'techno', 'edm', 'dj', 'mix'],
                ];

                $genreCounts = array_fill_keys(array_keys($genreKeywords), 0);

                foreach ($samplePosts as $post) {
                    $searchString = strtolower($post->album_name . ' ' . $post->track_name);
                    foreach ($genreKeywords as $genre => $keywords) {
                        foreach ($keywords as $keyword) {
                            if (str_contains($searchString, $keyword)) {
                                $genreCounts[$genre]++;
                            }
                        }
                    }
                }

                arsort($genreCounts);
                $detectedGenre = key($genreCounts);

                if ($genreCounts[$detectedGenre] > 0) {
                    $topGenre = $detectedGenre;
                } else {
                    $topGenre = 'Variado';
                }
            }

            $activeReports = Report::count();

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

            $posts = Post::whereHas('reports')
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'metrics' => [
                    'total_users' => $totalUsers,
                    'total_posts' => $totalPosts,
                    'spotify_bind_rate' => $spotifyBindRate,
                    'posts_last_24h' => $postsLast24h,
                    'average_posts_per_user' => $avgPosts,
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

    public function approvePost(Request $request)
    {
        try {
            $id = $request->input('post_id');

            if (!$id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se proporcionó un ID de publicación válido.'
                ], 400);
            }

            $hasReports = Report::where('post_id', $id)->exists();

            if (!$hasReports) {
                return response()->json([
                    'success' => false,
                    'message' => 'El mensaje no tenía reportes activos o ya fue procesado.'
                ], 404);
            }

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

    public function destroyPost(Request $request)
    {
        try {
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
            $reason = $request->input('reason', 'Contenido inapropiado'); 

            if (!$postId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se proporcionó un ID de publicación válido.'
                ], 400);
            }

            $postExists = Post::where('id', $postId)->exists();
            if (!$postExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'La publicación que intentas reportar ya no existe.'
                ], 404);
            }

            $alreadyReported = Report::where('post_id', $postId)
                ->where('user_id', auth()->id())
                ->exists();

            if ($alreadyReported) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya has reportado esta publicación anteriormente.'
                ], 400);
            }

            Report::create([
                'post_id' => $postId,
                'user_id' => auth()->id(),
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