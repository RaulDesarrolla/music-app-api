<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SpotifyController;

Route::get('/status', function () {
    return response()->json([
        'message' => '¡API conectada con éxito!',
        'database' => 'Aiven MySQL está vivo',
        'status' => 'ready'
    ]);
});

Route::get('/search', [SpotifyController::class, 'search']);
