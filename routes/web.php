<?php

use App\Http\Controllers\SpotifyController;
use Illuminate\Support\Facades\Route;

// Ruta base generada por Laravel
Route::get('/', function () {
    return view('welcome'); // Cambiado a vista para que puedas ver el botón de inicio
});

Route::get('/spotify/connect', [SpotifyController::class, 'connect'])->name('spotify.connect');

// Esta ruta DEBE coincidir con el Redirect URI: http://127.0.0.1:8000/spotify/callback
Route::get('/spotify/callback', [SpotifyController::class, 'callback'])->name('spotify.callback')->withoutMiddleware(['web']);

// Importación de las rutas de Breeze (Login, Registro, etc.)
require __DIR__.'/auth.php';
