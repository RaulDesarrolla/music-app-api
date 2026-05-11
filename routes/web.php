<?php

use App\Http\Controllers\SpotifyController;
use Illuminate\Support\Facades\Route;

// Ruta base - Solo para confirmar que el servidor está vivo
Route::get('/', function () {
    return response()->json(['status' => 'Music App API is running']);
});

/**
 * RUTAS DE REDIRECCIÓN (No devuelven JSON, redirigen el navegador)
 */

// Esta es la ruta a la que Spotify envía al usuario. 
// Es WEB porque Spotify hace una redirección en el navegador.
Route::get('/spotify/callback', [SpotifyController::class, 'callback'])->name('spotify.callback');