<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SpecialSchedule extends Model
{
    use HasUuid;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'time_in',
        'time_out',
        'late_tolerance',
        'latitude',
        'longitude',
        'radius',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saved(function () {
            self::clearCache();
        });

        static::deleted(function () {
            self::clearCache();
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('active_special_schedules');
    }

    public static function getActiveCached()
    {
        return Cache::remember('active_special_schedules', 300, function () {
            return static::where('is_active', true)->get();
        });
    }
}
