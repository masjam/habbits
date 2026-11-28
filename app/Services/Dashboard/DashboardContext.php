<?php

namespace App\Services\Dashboard;

use App\Models\Habit;
use App\Models\Setting;
use App\Models\User;
use App\Services\AcademicYearService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardContext
{
    public readonly Carbon $now;
    public readonly string $todayStr;
    public readonly string $yesterdayStr;
    public readonly string $monthStartStr;
    public readonly string $monthEndStr;
    public readonly string $weekStartStr;
    public readonly string $trendStartStr;
    public readonly int $daysInMonth;
    public readonly string $academicYear;
    public readonly array $settings;

    public function __construct()
    {
        $this->now = Carbon::now('Asia/Jakarta');
        $this->todayStr = $this->now->toDateString();
        $this->yesterdayStr = $this->now->copy()->subDay()->toDateString();
        $this->monthStartStr = $this->now->copy()->startOfMonth()->toDateString();
        $this->monthEndStr = $this->now->copy()->endOfMonth()->toDateString();
        $this->weekStartStr = $this->now->copy()->subDays(6)->toDateString();
        $this->trendStartStr = $this->now->copy()->subDays(13)->toDateString();
        $this->daysInMonth = $this->now->daysInMonth;
        $this->academicYear = app(AcademicYearService::class)->getActiveYear();
        $this->settings = Setting::allCached();
    }

    public function feature(string $key, bool $default = true): bool
    {
        if (!array_key_exists($key, $this->settings)) {
            return $default;
        }
        $val = $this->settings[$key];
        return $val === '1' || $val === 'true' || $val === true;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * ID pegawai aktif (bukan admin/superadmin dan bukan status Non Aktif).
     *
     * @return array<int>
     */
    public function activeEmployeeIds(): array
    {
        return Cache::remember("dash_emp_ids_{$this->todayStr}", 120, function () {
            return User::query()
                ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['superadmin', 'admin']))
                ->where(fn ($q) => $q->whereNull('status_kehadiran')->orWhere('status_kehadiran', '!=', 'Non Aktif'))
                ->pluck('id')
                ->all();
        });
    }

    public function maxDailyHabitScore(): int
    {
        return (int) Cache::remember('dash_max_daily_habit_v2', 300, function () {
            $sum = (int) Habit::where('status_aktif', true)
                ->where('is_pengganti_haid', false)
                ->sum('skor_maksimal');
            return $sum > 0 ? $sum : 100;
        });
    }
}
