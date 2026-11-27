<?php

namespace App\Policies;

use App\Models\PerformanceEvaluation;
use App\Models\User;

class PerformanceEvaluationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'superadmin', 'kepala_sekolah']);
    }

    public function view(User $user, PerformanceEvaluation $evaluation): bool
    {
        if ($this->viewAny($user)) {
            return true;
        }

        return (int) $evaluation->user_id === (int) $user->id
            && $evaluation->status === 'published';
    }

    public function evaluate(User $user, User $target): bool
    {
        return $this->viewAny($user) && (int) $user->id !== (int) $target->id;
    }

    public function publish(User $user, PerformanceEvaluation $evaluation): bool
    {
        return $this->viewAny($user) && (int) $user->id !== (int) $evaluation->user_id;
    }

    public function export(User $user): bool
    {
        return $this->viewAny($user);
    }
}
