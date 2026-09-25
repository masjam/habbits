<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;

$user = User::first();
if (!$user) {
    echo "No user found.\n";
    exit;
}

$date = '2026-09-25';
$timeIn = '08:00:00';
$timeOut = '17:00:00';

$attendance = Attendance::updateOrCreate(
    [
        'user_id' => $user->id,
        'date'    => $date,
    ],
    [
        'time_in'  => $timeIn,
        'time_out' => $timeOut,
        'status'   => 'hadir',
        'notes'    => 'Test update',
    ]
);

echo "Updated attendance. ID: {$attendance->id}, Time In: {$attendance->time_in}, Time Out: {$attendance->time_out}\n";

$attendance->refresh();
echo "After refresh. Time In: {$attendance->time_in}, Time Out: {$attendance->time_out}\n";
