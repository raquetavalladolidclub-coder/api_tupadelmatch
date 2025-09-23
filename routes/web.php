<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'Padel Club API']);
});

// Rutas públicas
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('login/google', [AuthController::class, 'loginWithGoogle']);
    Route::post('login/apple', [AuthController::class, 'loginWithApple']);
    Route::post('validate-token', [AuthController::class, 'validateToken']);
});

// Rutas protegidas
Route::middleware('auth')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::get('user', [AuthController::class, 'user']);
        Route::post('logout', [AuthController::class, 'logout']);
    });

    // Aquí irán las rutas protegidas de partidos, jugadores, etc.
    Route::get('/protected', function (Request $request) {
        return response()->json([
            'message' => 'Acceso permitido',
            'user' => $request->user()
        ]);
    });
});