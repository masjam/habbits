<?php

namespace Tests\Feature;

use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\MenstruationLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Dashboard\DashboardContext;
use App\Services\Dashboard\EmployeeDashboardBuilder;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeDailyHabitScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_habit_list_caps_score_and_excludes_non_replacing_haid_substitute(): void
    {
        $this->seed([RoleSeeder::class]);
        Cache::flush();

        Setting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => '0']);
        Setting::updateOrCreate(['key' => 'feature_habit'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'monthly_target_score'], ['value' => '80']);

        $mainHabit = Habit::create([
            'nama_habit' => 'Sholat Wajib',
            'kategori' => 'Ibadah',
            'template' => 'angka',
            'target_pencapaian' => 5,
            'skor_maksimal' => 50,
            'status_aktif' => true,
            'is_pengganti_haid' => false,
            'hide_saat_haid' => true,
        ]);

        $haidSubHabit = Habit::create([
            'nama_habit' => 'Dzikir Pengganti',
            'kategori' => 'Ibadah',
            'template' => 'angka',
            'target_pencapaian' => 1,
            'skor_maksimal' => 40,
            'status_aktif' => true,
            'is_pengganti_haid' => true,
            'hide_saat_haid' => false,
        ]);

        $user = User::factory()->create([
            'gender' => 'P',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('user');

        $now = Carbon::now('Asia/Jakarta');
        $today = $now->toDateString();
        $yesterday = $now->copy()->subDay()->toDateString();

        // Today: NOT menstruating, but has a substitute habit log + main habit log exceeding max (50)
        HabitLog::create([
            'user_id' => $user->id,
            'habit_id' => $mainHabit->id,
            'tanggal' => $today,
            'skor_diperoleh' => 60, // intentionally above max 50
        ]);
        HabitLog::create([
            'user_id' => $user->id,
            'habit_id' => $haidSubHabit->id,
            'tanggal' => $today,
            'skor_diperoleh' => 40, // must NOT count since not haid today
        ]);

        // If today is day >= 2, test yesterday WITH active menstruation log
        if ($now->day >= 2) {
            MenstruationLog::create([
                'user_id' => $user->id,
                'waktu_mulai' => Carbon::parse($yesterday . ' 06:00:00', 'Asia/Jakarta'),
                'waktu_selesai' => Carbon::parse($yesterday . ' 23:00:00', 'Asia/Jakarta'),
            ]);
            HabitLog::create([
                'user_id' => $user->id,
                'habit_id' => $haidSubHabit->id,
                'tanggal' => $yesterday,
                'skor_diperoleh' => 40, // MUST count since replacing during haid
            ]);
        }

        $ctx = new DashboardContext();
        $this->assertSame(50, $ctx->maxDailyHabitScore());

        $builder = app(EmployeeDashboardBuilder::class);
        $summary = $builder->build($user, $ctx);
        $deferred = $builder->buildDeferred($user, $ctx);

        // Today's score must be capped at 50 (not 60 or 100)
        $this->assertSame(50, $summary['personal']['habit_today']['score']);
        $this->assertSame(50, $summary['personal']['habit_today']['max']);

        $dailyByDate = collect($deferred['habit_trend']['daily'])->keyBy('date');
        $this->assertSame(50, $dailyByDate[$today]['score']);

        $expectedTotal = 50;
        if ($now->day >= 2) {
            $this->assertSame(40, $dailyByDate[$yesterday]['score']);
            $expectedTotal += 40;
        }

        $monthSummary = $deferred['habit_trend']['summary'];
        $this->assertSame($expectedTotal, $monthSummary['total_score']);
        $this->assertSame(50 * $ctx->daysInMonth, $monthSummary['max_score']);
        $this->assertSame(80.0, (float) $monthSummary['target_percentage']);
    }
}
