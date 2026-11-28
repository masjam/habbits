<?php

namespace App\Services\Dashboard;

use App\Models\User;

class EmployeeDashboardBuilder extends AbstractDashboardBuilder
{
    public function roleKey(): string
    {
        return 'user';
    }

    public function build(User $user, DashboardContext $ctx): array
    {
        return [
            'roleKey' => $this->roleKey(),
            'personal' => $this->personalSummary($user, $ctx),
            'announcement' => $ctx->setting('announcement_text'),
        ];
    }

    public function buildDeferred(User $user, DashboardContext $ctx): array
    {
        return [
            'habit_trend' => $this->personalHabitTrend($user, $ctx),
            'habit_monthly_trend' => $this->personalHabitMonthlyTrend($user, $ctx),
            'attendance_monthly_trend' => $this->personalAttendanceTrend($user, $ctx),
        ];
    }
}
