<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Habit extends Model
{
    use HasUuid;
    protected $fillable = [
        'nama_habit',
        'deskripsi',
        'tipe_input',
        'template',
        'json_schema',
        'satuan',
        'target_pencapaian',
        'skor_maksimal',
        'hide_saat_haid',
        'is_pengganti_haid',
        'status_aktif',
        'urutan',
    ];

    protected $casts = [
        'status_aktif'       => 'boolean',
        'hide_saat_haid'     => 'boolean',
        'is_pengganti_haid'  => 'boolean',
        'target_pencapaian'  => 'integer',
        'skor_maksimal'      => 'integer',
        'json_schema'        => 'array',
    ];

    public function habitLogs(): HasMany
    {
        return $this->hasMany(HabitLog::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($habit) {
            self::clearHabitCaches();
        });

        static::deleted(function ($habit) {
            self::clearHabitCaches();
        });
    }

    public static function allActiveCached()
    {
        return \Illuminate\Support\Facades\Cache::rememberForever('habits_active_all', function () {
            return static::where('status_aktif', true)->orderBy('urutan')->orderBy('id')->get();
        });
    }

    public static function allCachedById()
    {
        return \Illuminate\Support\Facades\Cache::rememberForever('habits_all_by_id', function () {
            return static::all()->keyBy('id');
        });
    }

    public static function clearHabitCaches()
    {
        \Illuminate\Support\Facades\Cache::forget('habits_active_all');
        \Illuminate\Support\Facades\Cache::forget('habits_all_by_id');
    }
}
