<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Habit extends Model
{
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
}
