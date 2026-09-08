<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::first();
if (!$user) {
    echo "No user\n";
    exit;
}
$habit = App\Models\Habit::first();
if (!$habit) {
    echo "No habit\n";
    exit;
}

$date = '2026-09-08';

echo "First updateOrCreate\n";
$log1 = App\Models\HabitLog::updateOrCreate(
    ['user_id' => $user->id, 'habit_id' => $habit->id, 'tanggal' => $date],
    ['nilai_input' => 1, 'skor_diperoleh' => 10, 'details' => ['test' => 1]]
);
echo "Log1 ID: {$log1->id}, Nilai: {$log1->nilai_input}\n";

echo "Second updateOrCreate\n";
$log2 = App\Models\HabitLog::updateOrCreate(
    ['user_id' => $user->id, 'habit_id' => $habit->id, 'tanggal' => $date],
    ['nilai_input' => 5, 'skor_diperoleh' => 50, 'details' => ['test' => 2]]
);
echo "Log2 ID: {$log2->id}, Nilai: {$log2->nilai_input}\n";
