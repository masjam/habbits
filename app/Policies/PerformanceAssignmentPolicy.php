<?php

namespace App\Policies;

use App\Models\PerformanceAssignment;
use App\Models\User;

class PerformanceAssignmentPolicy
{
    private function isApprover(User $user): bool
    {
        if ($user->hasRole('kepala_sekolah')) {
            return true;
        }

        return $user->hasRole('superadmin')
            && (bool) config('performance_indicators.allow_superadmin_fallback', true);
    }

    public function viewAny(User $user): bool
    {
        return $this->isApprover($user);
    }

    public function create(User $user): bool
    {
        return $this->isApprover($user);
    }

    public function revoke(User $user, PerformanceAssignment $assignment): bool
    {
        return $this->isApprover($user);
    }
}
