<?php

use Illuminate\Http\Request;
use App\Http\Controllers\SpotifyController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

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
Route::get('/spotify/connect', [SpotifyController::class, 'connect']);
Route::get('/spotify/callback', [SpotifyController::class, 'callback'])->name('spotify.callback');


/* --- RUTAS PROTEGIDAS (Sanctum) --- */
Route::middleware('auth:sanctum')->group(function () {

    // --- Spotify Perfil y Acciones ---
    Route::prefix('spotify')->group(function () {
        // Nota: El /connect ya no está aquí dentro para evitar bloqueos de Sanctum
        Route::get('/profile', [SpotifyController::class, 'getProfile']);
        Route::get('/player-token', [SpotifyController::class, 'getPlayerToken']); 
        Route::get('/search', [SpotifyController::class, 'search']); 
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