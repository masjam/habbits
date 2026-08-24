<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Habit;
use App\Models\HabitLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

// Ensure role exists
Role::firstOrCreate(['name' => 'user']);

$targets = [
    ['name' => 'Sample User 90', 'email' => 'sample90@example.com', 'score' => 90],
    ['name' => 'Sample User 150', 'email' => 'sample150@example.com', 'score' => 150],
    ['name' => 'Sample User 217', 'email' => 'sample217@example.com', 'score' => 217],
    ['name' => 'Sample User 248', 'email' => 'sample248@example.com', 'score' => 248],
];

// Active habits
$habits = Habit::where('status_aktif', true)->get();
if ($habits->isEmpty()) {
    echo "No active habits found to assign scores.\n";
    exit;
}

$now = Carbon::now();
$startOfMonth = $now->copy()->startOfMonth();

foreach ($targets as $target) {
    $user = User::firstOrCreate(
        ['email' => $target['email']],
        [
            'name' => $target['name'],
            'password' => Hash::make('password'),
            'gender' => 'L'
        ]
    );
    $user->assignRole('user');
    
    // Clear old logs for this month
    HabitLog::where('user_id', $user->id)
        ->whereBetween('tanggal', [$startOfMonth, $now->copy()->endOfMonth()])
        ->delete();

    $remainingScore = $target['score'];
    $currentDate = $startOfMonth->copy();
    $habitIndex = 0;

    echo "Seeding user {$target['name']} for {$target['score']} points...\n";

    while ($remainingScore > 0 && $currentDate <= $now) {
        $habit = $habits[$habitIndex % $habits->count()];
        
        $scoreToGive = min((int)$habit->skor_maksimal, $remainingScore);
        
        if ($scoreToGive > 0) {
            HabitLog::create([
                'user_id' => $user->id,
                'habit_id' => $habit->id,
                'tanggal' => $currentDate->format('Y-m-d'),
                'is_done' => true,
                'input_value' => $scoreToGive,
                'skor_diperoleh' => $scoreToGive
            ]);
            $remainingScore -= $scoreToGive;
        }

        $habitIndex++;
        // If we looped through all habits for the day, move to next day
        if ($habitIndex % $habits->count() === 0) {
            $currentDate->addDay();
        }
    }
    
    if ($remainingScore > 0) {
        echo "Warning: Not enough days/habits to reach {$target['score']} for {$target['name']}. Remaining: $remainingScore\n";
    } else {
        echo "Successfully seeded {$target['name']}.\n";
    }
}
echo "Done.\n";
