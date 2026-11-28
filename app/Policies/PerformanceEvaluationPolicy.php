<?php

namespace App\Policies;

use App\Models\PerformanceAssignment;
use App\Models\PerformanceEvaluation;
use App\Models\User;

class PerformanceEvaluationPolicy
{
    public function isApprover(User $user): bool
    {
        if ($user->hasRole('kepala_sekolah')) {
            return true;
        }

        return $user->hasRole('superadmin')
            && (bool) config('performance_indicators.allow_superadmin_fallback', true);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'superadmin', 'kepala_sekolah']);
    }

    public function view(User $user, PerformanceEvaluation $evaluation): bool
    {
        if ($this->isApprover($user)) {
            return true;
        }

        if ($user->hasAnyRole(['admin', 'superadmin'])) {
            return $evaluation->status === PerformanceEvaluation::STATUS_PUBLISHED;
        }

        if ((int) $evaluation->user_id === (int) $user->id) {
            return $evaluation->status === PerformanceEvaluation::STATUS_PUBLISHED;
        }

        return PerformanceAssignment::active()
            ->where('assessor_id', $user->id)
            ->where('employee_id', $evaluation->user_id)
            ->forPeriod(
                (string) $evaluation->academic_year,
                (string) $evaluation->period_type,
                (string) $evaluation->period_label
            )
            ->exists();
    }

    public function viewInsight(
        User $user,
        User $target,
        string $academicYear,
        string $periodType,
        string $periodLabel
    ): bool {
        if ((int) $user->id === (int) $target->id) {
            return false;
        }

        if ($this->isApprover($user)) {
            return true;
        }

        return PerformanceAssignment::active()
            ->where('assessor_id', $user->id)
            ->where('employee_id', $target->id)
            ->forPeriod($academicYear, $periodType, $periodLabel)
            ->exists();
    }

    public function evaluate(User $user, User $target): bool
    {
        return $this->isApprover($user)
            && (int) $user->id !== (int) $target->id
            && !$target->hasRole('kepala_sekolah');
    }

    public function evaluateAssigned(
        User $user,
        User $target,
        string $academicYear,
        string $periodType,
        string $periodLabel
    ): bool {
        if ((int) $user->id === (int) $target->id || $target->hasRole('kepala_sekolah')) {
            return false;
        }

        return PerformanceAssignment::active()
            ->where('assessor_id', $user->id)
            ->where('employee_id', $target->id)
            ->forPeriod($academicYear, $periodType, $periodLabel)
            ->exists();
    }

    public function approve(User $user, PerformanceEvaluation $evaluation): bool
    {
        if (!$this->isApprover($user)) {
            return false;
        }

        if ((int) $user->id === (int) $evaluation->user_id) {
            return false;
        }

        $target = $evaluation->relationLoaded('user') ? $evaluation->user : $evaluation->user()->first();
        if ($target && $target->hasRole('kepala_sekolah')) {
            return false;
        }

        return true;
    }

    public function publish(User $user, PerformanceEvaluation $evaluation): bool
    {
        return $this->approve($user, $evaluation);
    }

    public function reopen(User $user, PerformanceEvaluation $evaluation): bool
    {
        return $this->approve($user, $evaluation);
    }

    public function export(User $user): bool
    {
        return $this->viewAny($user);
    }
}
