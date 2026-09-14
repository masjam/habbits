<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'time_in',
        'lat_in',
        'lng_in',
        'distance_in',
        'photo_in',
        'time_out',
        'lat_out',
        'lng_out',
        'distance_out',
        'photo_out',
        'status',
        'notes',
        'is_overtime',
        'overtime_minutes',
        'overtime_activity',
    ];

    protected $casts = [
        'date' => 'date',
        'lat_in' => 'float',
        'lng_in' => 'float',
        'lat_out' => 'float',
        'lng_out' => 'float',
        'distance_in' => 'integer',
        'distance_out' => 'integer',
        'is_overtime' => 'boolean',
        'overtime_minutes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
