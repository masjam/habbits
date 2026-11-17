<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Division extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'name',
        'target_divisi',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saved(function () {
            Cache::forget('division_targets_map');
            Cache::forget('all_divisions_list');
        });

        static::deleted(function () {
            Cache::forget('division_targets_map');
            Cache::forget('all_divisions_list');
        });
    }

    public static function getTargetsMap(): array
    {
        return Cache::rememberForever('division_targets_map', function () {
            return static::pluck('target_divisi', 'name')->toArray();
        });
    }

    public static function allCached()
    {
        return Cache::rememberForever('all_divisions_list', function () {
            return static::orderBy('name')->get();
        });
    }
}
