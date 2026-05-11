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

