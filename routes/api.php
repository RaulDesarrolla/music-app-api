<?php

use Illuminate\Http\Request;
use App\Http\Controllers\SpotifyController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

/* --- RUTAS PÚBLICAS (Para usuarios NO logueados) --- */

Route::post('/login', [AuthenticatedSessionController::class, 'store']);

Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware('guest');

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware('guest');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('guest');


/* --- RUTAS PROTEGIDAS (Solo para usuarios con sesión activa) --- */

Route::middleware('auth:sanctum')->group(function () {

    // Perfil y Feed
    Route::get('/profile', [SpotifyController::class, 'getProfile']);
    Route::get('/feed', [SpotifyController::class, 'index']);
    Route::get('/search', [SpotifyController::class, 'search']);

    // Posts y Social
    Route::post('/posts', [SpotifyController::class, 'storePost']);
    Route::delete('/posts/{id}', [SpotifyController::class, 'destroyPost']);
    Route::post('/posts/{id}/like', [SpotifyController::class, 'toggleLike']);
    Route::post('/posts/{id}/comments', [SpotifyController::class, 'storeComment']);
    Route::post('/users/{id}/follow', [SpotifyController::class, 'toggleFollow']);

    // Verificación de email y Logout
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1');
});

// Esta ruta es especial, suele estar fuera o dentro dependiendo de si quieres que el front verifique el estado
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');