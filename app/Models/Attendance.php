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
        'late_reason',
        'late_photo',
        'is_overtime',
        'overtime_minutes',
        'overtime_activity',
        'approval_status',
        'approval_type',
        'rejection_note',
        'attachment',
        'is_early_departure',
        'early_departure_reason',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'lat_in' => 'float',
        'lng_in' => 'float',
        'lat_out' => 'float',
        'lng_out' => 'float',
        'distance_in' => 'integer',
        'distance_out' => 'integer',
        'is_overtime' => 'boolean',
        'overtime_minutes' => 'integer',
        'is_early_departure' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function getDateStringAttribute(): string
    {
        if ($this->date instanceof \Carbon\Carbon) {
            return $this->date->format('Y-m-d');
        }
        return substr((string) $this->date, 0, 10);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
