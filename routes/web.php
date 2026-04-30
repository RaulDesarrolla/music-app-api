<?php

use App\Http\Controllers\SpotifyController;
use Illuminate\Support\Facades\Route;

// Ruta base generada por Laravel
Route::get('/', function () {
    return view('welcome'); // Cambiado a vista para que puedas ver el botón de inicio
});

Route::get('/spotify/connect', [SpotifyController::class, 'connect'])->name('spotify.connect');

Route::middleware(['auth'])->group(function () {
    // Ruta para iniciar la conexión

    // Esta ruta DEBE coincidir con el Dashboard de Spotify
    // Hemos añadido 'api/auth/callback' para cumplir con tu configuración
    Route::get('/api/auth/callback', [SpotifyController::class, 'callback'])->name('spotify.callback');
});

// Importación de las rutas de Breeze (Login, Registro, etc.)
require __DIR__.'/auth.php';