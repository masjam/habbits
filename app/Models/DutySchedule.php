<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DutySchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'day_of_week',
        'name',
        'time_in',
        'time_out',
        'late_tolerance',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'day_of_week'    => 'integer',
        'late_tolerance' => 'integer',
        'is_active'      => 'boolean',
    ];

    protected $appends = [
        'day_name',
    ];

    public static function getDayNames(): array
    {
        return [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
    }

    public function getDayNameAttribute(): string
    {
        $names = self::getDayNames();
        return $names[$this->day_of_week] ?? 'Hari ' . $this->day_of_week;
    }

    /**
     * Relasi ke daftar pegawai yang bertugas pada jadwal piket ini.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'duty_schedule_user')->withTimestamps();
    }
}
