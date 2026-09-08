<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::first();
Auth::login($user);

$habitQuran = App\Models\Habit::where('template', 'quran')->first();

$log = App\Models\HabitLog::where('user_id', $user->id)
    ->where('habit_id', $habitQuran->id)
    ->whereDate('tanggal', date('Y-m-d'))
    ->first();

echo "Before update:\n";
print_r($log ? $log->details : 'null');
echo "\n";

$request = new Illuminate\Http\Request();
$request->merge([
    'tanggal' => date('Y-m-d'),
    'logs' => [
        [
            'habit_id' => $habitQuran->id,
            'nilai_input' => 0,
            'details' => ['durasi' => 50, 'surat_awal' => 'Al-Baqarah']
        ]
    ]
]);

$controller = new App\Http\Controllers\FormHabitController();
$controller->store($request);

$log = App\Models\HabitLog::where('user_id', $user->id)
    ->where('habit_id', $habitQuran->id)
    ->whereDate('tanggal', date('Y-m-d'))
    ->first();

echo "After update:\n";
print_r($log->details);
echo "\n";
