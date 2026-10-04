<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceEvaluation extends Model
{
    protected $fillable = [
        'user_id',
        'evaluator_id',
        'evaluation_month',
        'evaluation_year',
        'score_pedagogic',
        'score_professional',
        'score_personality',
        'score_social',
        'average_score',
        'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}
