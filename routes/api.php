<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [\App\Http\Controllers\Api\AuthController::class, 'login']);

Route::middleware('api.token')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    Route::get('/habits', [\App\Http\Controllers\Api\HabitController::class, 'index']);
    Route::post('/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);
});
