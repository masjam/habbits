<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::first();
Auth::login($user);

$habitQuran = App\Models\Habit::where('template', 'quran')->first();

if (!$habitQuran) {
    echo "No quran habit found";
    exit;
}

$request = new Illuminate\Http\Request();
$request->merge([
    'tanggal' => date('Y-m-d'),
    'logs' => [
        [
            'habit_id' => $habitQuran->id,
            'nilai_input' => 0,
            'details' => ['durasi' => 30, 'surat_awal' => 'Al-Fatihah']
        ]
    ]
]);

$controller = new App\Http\Controllers\FormHabitController();
$controller->store($request);

$log = App\Models\HabitLog::where('user_id', $user->id)
    ->where('habit_id', $habitQuran->id)
    ->whereDate('tanggal', date('Y-m-d'))
    ->first();

echo "Log saved:\n";
print_r($log->toArray());
