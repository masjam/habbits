<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DashboardController as ApiDashboardController;
use App\Http\Controllers\Api\FormHabitController as ApiFormHabitController;

Route::post('/login', [\App\Http\Controllers\Api\AuthController::class, 'login']);


Route::middleware('api.token')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/dashboard', [ApiDashboardController::class, 'index']);
    Route::get('/history', [\App\Http\Controllers\Api\HistoryController::class, 'index']);
    Route::get('/history/{date}', [\App\Http\Controllers\Api\HistoryController::class, 'show']);
    Route::get('/form-habit', [ApiFormHabitController::class, 'index']);
    Route::post('/form-habit', [ApiFormHabitController::class, 'store']);
    
    Route::get('/habits', [\App\Http\Controllers\Api\HabitController::class, 'index']);
    
    Route::post('/haid/toggle', [\App\Http\Controllers\Api\MenstruationController::class, 'toggle']);
    Route::get('/haid/history', [\App\Http\Controllers\Api\MenstruationController::class, 'history']);
    Route::put('/haid/{id}', [\App\Http\Controllers\Api\MenstruationController::class, 'update']);
    
    Route::post('/profile/update', [\App\Http\Controllers\Api\ProfileController::class, 'updateProfile']);
    
    Route::post('/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);
});
