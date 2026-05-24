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

/* --- 🌍 RUTAS TOTALMENTE PÚBLICAS --- */
// Autenticación interna de tu App (Para entrar o registrarse por primera vez)
Route::post('/login', [AuthenticatedSessionController::class, 'store']);
Route::post('/register', [RegisteredUserController::class, 'store']);

// 🔗 URL de Retorno de Spotify (Es pública porque la llama la API de Spotify desde sus servidores)
Route::get('/spotify/callback', [SpotifyController::class, 'callback'])->name('spotify.callback');


/* --- 🔒 RUTAS TOTALMENTE PROTEGIDAS (Solo usuarios logueados con Sanctum) --- */
Route::middleware('auth:sanctum')->group(function () {

    // --- 📱 Red Social, Muro y Feed ---
    Route::get('/feed', [SpotifyController::class, 'getFeed']);
    Route::post('/posts', [SpotifyController::class, 'storePost']);
    Route::post('/comments/{postId}', [SpotifyController::class, 'storeComment']);
    
    // --- 👥 Usuarios, Perfiles y Seguimientos ---
    Route::get('/users/search', [SpotifyController::class, 'searchProfiles']);
    Route::get('/users/{id}', [SpotifyController::class, 'getUserProfile']);
    Route::get('/users/{id}', [SpotifyController::class, 'getUserProfile']);
    Route::get('/users/{id}/profile', [SpotifyController::class, 'getUserProfile']);
    Route::post('/users/{id}/follow', [SpotifyController::class, 'toggleFollow']);
    
    // Lista general de usuarios (La que te daba error en Social.jsx, ahora protegida y segura)
    Route::get('/users', function () {
        try {
            $currentUser = auth()->user(); // Aquí sabemos 100% quién eres de forma segura

            // Traemos todos los usuarios menos tú mismo
            $users = \App\Models\User::where('id', '!=', $currentUser->id)
                ->select('id', 'name', 'email', 'created_at')
                ->get();

            return response()->json([
                'status' => 'success',
                'results' => $users
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al listar usuarios: ' . $e->getMessage()], 500);
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