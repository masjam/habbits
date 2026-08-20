<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [\App\Http\Controllers\WelcomeController::class, 'index'])->name('welcome');

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [\App\Http\Controllers\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [\App\Http\Controllers\AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'logout'])->name('logout');
    
    // ─── Route Pegawai (semua role auth) ───────────────────────────────────────
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile/password', [\App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/habit/form', [\App\Http\Controllers\FormHabitController::class, 'index'])->name('habit.form');
    Route::post('/habit/form', [\App\Http\Controllers\FormHabitController::class, 'store'])->name('habit.form.store');
    
    Route::get('/haid', [\App\Http\Controllers\MenstruationController::class, 'index'])->name('haid.index');
    Route::post('/haid/toggle', [\App\Http\Controllers\MenstruationController::class, 'toggle'])->name('haid.toggle');
    
    Route::get('/kajian', [\App\Http\Controllers\KajianController::class, 'index'])->name('kajian.index');

    Route::post('/push-subscriptions', [\App\Http\Controllers\PushSubscriptionController::class, 'store'])->name('push.store');

    // ─── Admin & Superadmin Routes ─────────────────────────────────────────────
    Route::middleware('role:admin|superadmin')->prefix('admin')->group(function () {

        Route::get('/laporan', [\App\Http\Controllers\Admin\LaporanController::class, 'index'])->name('admin.laporan');
        Route::get('/laporan/export', [\App\Http\Controllers\Admin\LaporanController::class, 'export'])->name('admin.laporan.export');
        Route::post('/laporan/target', [\App\Http\Controllers\Admin\LaporanController::class, 'updateTarget'])->name('admin.laporan.target');
        Route::get('/laporan/{user}', [\App\Http\Controllers\Admin\LaporanController::class, 'detail'])->name('admin.laporan.detail');

        Route::get('/habits', [\App\Http\Controllers\Admin\HabitController::class, 'index'])->name('admin.habits.index');
        Route::post('/habits/reorder', [\App\Http\Controllers\Admin\HabitController::class, 'reorder'])->name('admin.habits.reorder');
        Route::post('/habits', [\App\Http\Controllers\Admin\HabitController::class, 'store'])->name('admin.habits.store');
        Route::put('/habits/{habit}', [\App\Http\Controllers\Admin\HabitController::class, 'update'])->name('admin.habits.update');

        Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('admin.users.index');
        Route::post('/users', [\App\Http\Controllers\Admin\UserController::class, 'store'])->name('admin.users.store');
        Route::put('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('admin.users.update');
        Route::get('/users/template', [\App\Http\Controllers\Admin\UserController::class, 'downloadTemplate'])->name('admin.users.template');
        Route::post('/users/import', [\App\Http\Controllers\Admin\UserController::class, 'import'])->name('admin.users.import');
        Route::post('/users/{user}/reset-password', [\App\Http\Controllers\Admin\UserController::class, 'resetPassword'])->name('admin.users.reset-password');
        Route::delete('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('admin.users.destroy');

        Route::get('/settings', [\App\Http\Controllers\Admin\SettingController::class, 'index'])->name('admin.settings');
        Route::post('/settings', [\App\Http\Controllers\Admin\SettingController::class, 'update'])->name('admin.settings.update');
    });
});
