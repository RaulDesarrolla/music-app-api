<?php

use Illuminate\Http\Request;
use App\Http\Controllers\SpotifyController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

/* --- RUTAS PÚBLICAS --- */
// Autenticación interna de tu App
Route::post('/login', [AuthenticatedSessionController::class, 'store']);
Route::post('/register', [RegisteredUserController::class, 'store']);

// 🔥 RUTAS DE OAUTH DE SPOTIFY (Tienen que ser públicas para que funcione el flujo)
Route::get('/spotify/callback', [SpotifyController::class, 'callback'])->name('spotify.callback');

Route::post('/admin/posts/approve', [AdminController::class, 'approvePost']);
Route::post('/admin/posts/delete', [AdminController::class, 'destroyPost']);

/* --- RUTAS PROTEGIDAS (Sanctum) --- */
Route::middleware('auth:sanctum')->group(function () {

    // --- Spotify Perfil y Acciones ---
    Route::prefix('spotify')->group(function () {
        // 🛡️ METEMOS AQUÍ EL CONNECT: Ahora Laravel sí sabrá quién es el usuario logueado
        Route::get('/connect', [SpotifyController::class, 'connect']);
        Route::get('/profile', [SpotifyController::class, 'getProfile']);
        Route::get('/player-token', [SpotifyController::class, 'getPlayerToken']);
        Route::get('/search', [SpotifyController::class, 'search']);
        Route::get('/weekly-wrapped', [SpotifyController::class, 'getWeeklyWrapped']);
    });

    Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
        Route::get('/dashboard-stats', [AdminController::class, 'getDashboardStats']);
    });

    // --- Social / Feed ---
    Route::get('/feed', [SpotifyController::class, 'getFeed']);
    Route::post('/posts', [SpotifyController::class, 'storePost']);
    Route::post('/comments/{postId}', [SpotifyController::class, 'storeComment']);
    Route::post('/users/{id}/follow', [SpotifyController::class, 'toggleFollow']);

    // --- Auth General ---
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});