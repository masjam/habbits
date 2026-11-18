<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;

// Hapus notifikasi yang berumur lebih dari 3 bulan (90 hari)
Schedule::call(function () {
    DB::table('notifications')
        ->where('created_at', '<', now()->subMonths(3))
        ->delete();
})->daily();

// Bersihkan job gagal yang berumur lebih dari 30 hari
Schedule::command('queue:prune-failed --hours=720')->daily();

// Hapus file hasil ekspor (xlsx/pdf) yang berumur lebih dari 7 hari.
// Mencakup disk lama ('public') maupun disk privat.
Schedule::call(function () {
    $cutoff = now()->subDays(7)->timestamp;
    foreach (['public', 'private'] as $disk) {
        $storage = \Illuminate\Support\Facades\Storage::disk($disk);
        foreach ($storage->files('exports') as $file) {
            if ($storage->lastModified($file) < $cutoff) {
                $storage->delete($file);
            }
        }
    }
})->dailyAt('02:30')->name('prune-old-exports');

// Pangkas log login yang berumur lebih dari 6 bulan
Schedule::call(function () {
    DB::table('login_logs')->where('created_at', '<', now()->subMonths(6))->delete();
})->weekly()->name('prune-login-logs');
