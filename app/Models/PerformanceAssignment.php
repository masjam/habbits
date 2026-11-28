<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceAssignment extends Model
{
    use HasFactory;

    protected $table = 'performance_assessor_assignments';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'academic_year',
        'period_type',
        'period_label',
        'assessor_id',
        'employee_id',
        'assigned_by',
        'status',
        'revoked_by',
        'revoked_at',
        'note',
    ];

    protected $casts = [
        'revoked_at' => 'datetime',
    ];

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(PerformanceEvaluation::class, 'assignment_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForPeriod(Builder $query, string $academicYear, string $periodType, string $periodLabel): Builder
    {
        return $query->where('academic_year', $academicYear)
            ->where('period_type', $periodType)
            ->where('period_label', $periodLabel);
    }
}
