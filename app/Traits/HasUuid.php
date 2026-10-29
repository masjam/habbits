<?php

namespace App\Traits;

use Illuminate\Support\Str;

/**
 * HasUuid Trait
 *
 * Adds a companion `uuid` column to a model.
 * The integer primary key (id) and all foreign key relations remain unchanged.
 * UUIDs are generated automatically on model creation.
 */
trait HasUuid
{
    public static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Find a model by its UUID.
     */
    public static function findByUuid(string $uuid): ?static
    {
        return static::where('uuid', $uuid)->first();
    }

    /**
     * Find a model by its UUID or fail.
     */
    public static function findByUuidOrFail(string $uuid): static
    {
        return static::where('uuid', $uuid)->firstOrFail();
    }
}
