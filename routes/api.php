<?php


use Illuminate\Http\Request;
use App\Http\Controllers\SpotifyController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PostController;

Route::post('/login', [AuthenticatedSessionController::class, 'store']);
Route::post('/register', [RegisteredUserController::class, 'store']);
Route::get('/spotify/callback', [SpotifyController::class, 'callback'])->name('spotify.callback');

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/feed', [PostController::class, 'index']);
    Route::post('/posts', [PostController::class, 'store']);
    Route::post('/posts/{id}/like', [PostController::class, 'toggleLike']);
    Route::post('/comments/{postId}', [PostController::class, 'storeComment']);
    Route::post('/posts/report', [AdminController::class, 'reportPost']);

    Route::get('/users/search', [PostController::class, 'searchProfiles']);
    Route::get('/users/{id}', [PostController::class, 'getUserProfile']);
    Route::get('/users/{id}/profile', [PostController::class, 'getUserProfile']);
    Route::post('/users/{id}/follow', [PostController::class, 'toggleFollow']);
    Route::post('/user/update', function (Request $request) {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'password' => 'sometimes|string|min:6',
        ]);


        if ($request->filled('name')) {
            $user->name = $request->name;
        }

        if ($request->filled('password')) {
            $user->password = \Illuminate\Support\Facades\Hash::make($request->password);
        }

        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Perfil actualizado',
            'new_name' => $user->name
        ]);
    });

    Route::get('/users', function () {
        try {
            $currentUser = auth()->user();
            $users = \App\Models\User::where('id', '!=', $currentUser->id)
                ->select('id', 'name', 'email', 'created_at')
                ->get();


            return response()->json([
                'status' => 'success',
                'results' => $users
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al listar usuarios'], 500);
        }
    });

    Route::prefix('spotify')->group(function () {
        Route::get('/connect', [SpotifyController::class, 'connect']);
        Route::get('/profile', [SpotifyController::class, 'getProfile']);
        Route::get('/player-token', [SpotifyController::class, 'getPlayerToken']);
        Route::get('/search', [SpotifyController::class, 'search']);
        Route::get('/weekly-wrapped', [SpotifyController::class, 'getWeeklyWrapped']);
    });

    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/dashboard-stats', [AdminController::class, 'getDashboardStats']);
        Route::post('/posts/approve', [AdminController::class, 'approvePost']);
        Route::post('/posts/delete', [AdminController::class, 'destroyPost']);
    });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

