<?php

use App\Http\Controllers\SpotifyController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;

// Ruta base
Route::get('/', function () {
    return view('welcome');
});

// Rutas de Autenticación manual
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);

// Rutas protegidas: El usuario DEBE estar logueado para conectar Spotify
Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', function () {
        return view('welcome'); // Reutilizamos welcome o creamos una nueva
    })->name('dashboard');
    
    Route::get('/spotify/connect', [SpotifyController::class, 'connect'])->name('spotify.connect');

    // IMPORTANTE: Hemos quitado ->withoutMiddleware(['web'])
    // Ahora esta ruta reconocerá al usuario autenticado
    Route::get('/spotify/callback', [SpotifyController::class, 'callback'])->name('spotify.callback');

});

// Importación de las rutas de Breeze
require __DIR__.'/auth.php';