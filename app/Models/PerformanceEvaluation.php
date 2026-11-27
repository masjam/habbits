<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerformanceEvaluation extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'evaluator_id',
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
        'status',
        'published_at',
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
        'published_at'     => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
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
