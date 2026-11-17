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

    protected static $inMemoryActive = null;
    protected static $inMemoryById = null;

    public static function allActiveCached()
    {
        if (static::$inMemoryActive !== null) {
            return static::$inMemoryActive;
        }

        $records = \Illuminate\Support\Facades\Cache::rememberForever('habits_active_all_v2', function () {
            return static::where('status_aktif', true)
                ->orderBy('urutan')
                ->orderBy('id')
                ->get()
                ->toArray();
        });

        return static::$inMemoryActive = static::hydrate($records);
    }

    public static function allCachedById()
    {
        if (static::$inMemoryById !== null) {
            return static::$inMemoryById;
        }

        $records = \Illuminate\Support\Facades\Cache::rememberForever('habits_all_by_id_v2', function () {
            return static::all()->toArray();
        });

        return static::$inMemoryById = static::hydrate($records)->keyBy('id');
    }

    public static function clearHabitCaches()
    {
        static::$inMemoryActive = null;
        static::$inMemoryById = null;
        \Illuminate\Support\Facades\Cache::forget('habits_active_all');
        \Illuminate\Support\Facades\Cache::forget('habits_all_by_id');
        \Illuminate\Support\Facades\Cache::forget('habits_active_all_v2');
        \Illuminate\Support\Facades\Cache::forget('habits_all_by_id_v2');
    }
}
