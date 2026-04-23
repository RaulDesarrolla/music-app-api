<?php

use Illuminate\Support\Facades\Route;

Route::get('/status', function () {
    return response()->json([
        'message' => '¡API conectada con éxito!',
        'database' => 'Aiven MySQL está vivo',
        'status' => 'ready'
    ]);
});
