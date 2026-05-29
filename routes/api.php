<?php


use Illuminate\Http\Request;
use App\Http\Controllers\SpotifyController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PostController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

/* --- 🌍 RUTAS TOTALMENTE PÚBLICAS --- */
Route::post('/login', [AuthenticatedSessionController::class, 'store']);
Route::post('/register', [RegisteredUserController::class, 'store']);
Route::get('/spotify/callback', [SpotifyController::class, 'callback'])->name('spotify.callback');

/* --- 🔒 RUTAS TOTALMENTE PROTEGIDAS (Solo usuarios logueados con Sanctum) --- */
Route::middleware('auth:sanctum')->group(function () {

    // --- 📱 Red Social, Muro y Feed ---
    Route::get('/feed', [PostController::class, 'index']);
    Route::post('/posts', [PostController::class, 'store']);
    Route::post('/posts/{id}/like', [PostController::class, 'toggleLike']);
    Route::post('/comments/{postId}', [PostController::class, 'storeComment']);
    Route::post('/posts/report', [AdminController::class, 'reportPost']);
   
    // --- 👥 Usuarios, Perfiles y Seguimientos (Movidos a PostController) ---
    Route::get('/users/search', [PostController::class, 'searchProfiles']);
    Route::get('/users/{id}', [PostController::class, 'getUserProfile']);
    Route::get('/users/{id}/profile', [PostController::class, 'getUserProfile']);
    Route::post('/users/{id}/follow', [PostController::class, 'toggleFollow']);
   
    // Lista general de usuarios (La mantenemos aquí si quieres, o muévela a PostController también)
    Route::get('/users', function () {
        try {
            $currentUser = auth()->user();
            $users = \App\Models\User::where('id', '!=', $currentUser->id)
                ->select('id', 'name', 'email', 'created_at')
                ->get();


            return response()->json([
                'status' => 'success',
                'results' => $users
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al listar usuarios'], 500);
        }
    });


    // --- 🎵 Spotify Perfil y Acciones de Reproductor ---
    Route::prefix('spotify')->group(function () {
        Route::get('/connect', [SpotifyController::class, 'connect']);
        Route::get('/profile', [SpotifyController::class, 'getProfile']);
        Route::get('/player-token', [SpotifyController::class, 'getPlayerToken']);
        Route::get('/search', [SpotifyController::class, 'search']);
        Route::get('/weekly-wrapped', [SpotifyController::class, 'getWeeklyWrapped']);
    });


    // --- 👑 Panel de Administración ---
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard-stats', [AdminController::class, 'getDashboardStats']);
        Route::post('/posts/approve', [AdminController::class, 'approvePost']);
        Route::post('/posts/delete', [AdminController::class, 'destroyPost']);
    });


    // --- 🔑 Cierre de Sesión y Datos de Usuario ---
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

