<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PerformanceEvaluationLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'performance_evaluation_logs';

    protected $fillable = [
        'performance_evaluation_id',
        'actor_id',
        'action',
        'from_status',
        'to_status',
        'note',
        'snapshot',
        'created_at',
    ];

    protected $casts = [
        'snapshot'   => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Audit log KPI bersifat append-only dan tidak dapat diubah.');
        });

        static::deleting(function () {
            throw new LogicException('Audit log KPI bersifat append-only dan tidak dapat dihapus.');
        });
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(PerformanceEvaluation::class, 'performance_evaluation_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
