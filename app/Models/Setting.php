<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasUuid;

    protected $fillable = ['key', 'value'];

    public const CACHE_KEY = 'global_app_settings';

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

    /**
     * Hapus cache setting global
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Dapatkan semua setting dalam format key-value ter-cache selamanya.
     *
     * @return array<string, mixed>
     */
    public static function allCached(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::pluck('value', 'key')->toArray();
        });
    }

    /**
     * Ambil satu nilai setting dari cache.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = static::allCached();
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    /**
     * Ambil nilai boolean dari cache setting ('1' / 'true').
     */
    public static function getBoolean(string $key, bool $default = false): bool
    {
        $val = static::get($key, null);
        if ($val === null) {
            return $default;
        }
        return $val === '1' || $val === 'true' || $val === true;
    }

    /**
     * Hapus setting berdasarkan key dan bersihkan cache.
     */
    public static function remove(string $key): void
    {
        static::where('key', $key)->delete();
        self::clearCache();
    }
}
