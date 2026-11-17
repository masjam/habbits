<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HabitLog extends Model
{
    use HasUuid;
    protected $fillable = [
        'user_id',
        'habit_id',
        'tanggal',
        'nilai_input',
        'skor_diperoleh',
        'details',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'details' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($log) {
            self::clearUserDashboardCache($log->user_id);
        });

        static::deleted(function ($log) {
            self::clearUserDashboardCache($log->user_id);
        });
    }

    public static function getUserDashboardVersion(int|string $userId): int
    {
        return (int) \Illuminate\Support\Facades\Cache::get("user_dash_ver_{$userId}", 1);
    }

    public static function getAdminDashboardVersion(): int
    {
        return (int) \Illuminate\Support\Facades\Cache::get('admin_dash_ver', 1);
    }

    public static function clearUserDashboardCache(int|string $userId): void
    {
        \Illuminate\Support\Facades\Cache::add("user_dash_ver_{$userId}", 1, 86400 * 30);
        \Illuminate\Support\Facades\Cache::increment("user_dash_ver_{$userId}");

        \Illuminate\Support\Facades\Cache::add('admin_dash_ver', 1, 86400 * 30);
        \Illuminate\Support\Facades\Cache::increment('admin_dash_ver');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function habit(): BelongsTo
    {
        return $this->belongsTo(Habit::class);
    }
}
