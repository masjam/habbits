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
