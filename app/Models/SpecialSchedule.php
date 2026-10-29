<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

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
}
