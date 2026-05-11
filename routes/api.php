<?php

use Illuminate\Http\Request;
use App\Http\Controllers\SpotifyController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use Illuminate\Support\Facades\Route;

/* --- RUTAS PÚBLICAS --- */
Route::post('/login', [AuthenticatedSessionController::class, 'store']);
Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
Route::post('/reset-password', [NewPasswordController::class, 'store']);
// Esta es la ruta a la que Spotify envía al usuario. 
Route::get('/spotify/callback', [SpotifyController::class, 'callback'])->name('spotify.callback');

/* --- RUTAS PROTEGIDAS (Sanctum) --- */
Route::middleware('auth:sanctum')->group(function () {

    // --- Spotify Auth ---
    // React llamará aquí para obtener la URL de conexión
    Route::get('/spotify/connect', [SpotifyController::class, 'connect'])->name('spotify.connect');

    // --- Perfil y Búsqueda ---
    Route::get('/profile', [SpotifyController::class, 'getProfile']);
    Route::get('/feed', [SpotifyController::class, 'index']);
    
    // Rutas que requieren que Spotify esté vinculado
    Route::middleware(['spotify.check'])->group(function () {
        Route::get('/search', [SpotifyController::class, 'search']);
        Route::post('/posts', [SpotifyController::class, 'storePost']);
        Route::get('/dashboard', [SpotifyController::class, 'getProfile']);
    });

    // --- Social & Otros ---
    Route::delete('/posts/{id}', [SpotifyController::class, 'destroyPost']);
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});