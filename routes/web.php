<?php

use App\Http\Controllers\SpotifyController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
// Ruta base
Route::get('/', function () {
    return view('welcome');
});

// Rutas de Autenticación manual
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');

// Rutas protegidas: El usuario DEBE estar logueado para conectar Spotify
Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', [SpotifyController::class, 'getProfile'])->name('dashboard');
    
    Route::get('/spotify/connect', [SpotifyController::class, 'connect'])->name('spotify.connect');

    Route::get('/spotify/callback', [SpotifyController::class, 'callback'])->name('spotify.callback');

});

// Importación de las rutas de Breeze
require __DIR__.'/auth.php';