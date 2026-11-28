<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerformanceEvaluation extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_REVISION_REQUESTED = 'revision_requested';
    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'uuid',
        'user_id',
        'evaluator_id',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'academic_year',
        'period_type',
        'period_label',
        'evaluation_month',
        'evaluation_year',
        'category',
        'rubric_version',
        'indicator_scores',
        'aspect_scores',
        'score_pedagogic',
        'score_professional',
        'score_personality',
        'score_social',
        'average_score',
        'final_score',
        'predicate',
        'notes',
        'revision_note',
        'assignment_id',
        'status',
        'published_at',
        'is_legacy',
    ];

    protected $casts = [
        'indicator_scores' => 'array',
        'aspect_scores'    => 'array',
        'score_pedagogic'  => 'float',
        'score_professional' => 'float',
        'score_personality' => 'float',
        'score_social'     => 'float',
        'average_score'    => 'float',
        'final_score'      => 'float',
        'submitted_at'     => 'datetime',
        'approved_at'      => 'datetime',
        'published_at'     => 'datetime',
        'is_legacy'        => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(PerformanceAssignment::class, 'assignment_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PerformanceEvaluationLog::class, 'performance_evaluation_id')->orderByDesc('created_at');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $isSuperadminFallback = $user->hasRole('superadmin')
            && (bool) config('performance_indicators.allow_superadmin_fallback', true);

        if ($user->hasRole('kepala_sekolah') || $isSuperadminFallback) {
            return $query;
        }

        if ($user->hasAnyRole(['admin', 'superadmin'])) {
            return $query->where('status', self::STATUS_PUBLISHED);
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where(function (Builder $own) use ($user) {
                $own->where('user_id', $user->id)
                    ->where('status', self::STATUS_PUBLISHED);
            })->orWhereExists(function ($sub) use ($user) {
                $sub->selectRaw('1')
                    ->from('performance_assessor_assignments as paa')
                    ->where('paa.assessor_id', $user->id)
                    ->where('paa.status', PerformanceAssignment::STATUS_ACTIVE)
                    ->whereColumn('paa.employee_id', 'performance_evaluations.user_id')
                    ->whereColumn('paa.academic_year', 'performance_evaluations.academic_year')
                    ->whereColumn('paa.period_type', 'performance_evaluations.period_type')
                    ->whereColumn('paa.period_label', 'performance_evaluations.period_label');
            });
        });
    }

    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeForPeriod(Builder $query, string $academicYear, string $periodType, string $periodLabel): Builder
    {
        return $query->where('academic_year', $academicYear)
            ->where('period_type', $periodType)
            ->where('period_label', $periodLabel);
    }
}
