<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SpotifyController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rutas públicas (si necesitas alguna que no requiera login)
// Route::get('/ping', function() { return response()->json(['res' => 'pong']); });

// Rutas protegidas por autenticación
Route::middleware('auth:sanctum')->group(function () {

    // 1. Perfil del usuario (Datos de Spotify + Posts propios)
    Route::get('/profile', [SpotifyController::class, 'getProfile']);

    // 2. Feed de noticias (Posts de seguidos y propios)
    Route::get('/feed', [SpotifyController::class, 'index']);

    // 3. Búsqueda de música en Spotify
    Route::get('/search', [SpotifyController::class, 'search']);

    // 4. Crear una nueva publicación musical
    Route::post('/posts', [SpotifyController::class, 'storePost']);

    // 5. Gestión de seguidores (Seguir/Dejar de seguir)
    Route::post('/users/{id}/follow', [SpotifyController::class, 'toggleFollow']);

    Route::post('/posts/{id}/like', [SpotifyController::class, 'toggleLike']);

    // 6. Eliminar un post propio
    Route::delete('/posts/{id}', [SpotifyController::class, 'destroyPost']);
});

/**
 * Nota: Las rutas de conexión inicial (connect y callback) suelen 
 * mantenerse en web.php porque requieren redirección del navegador.
 */