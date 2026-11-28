<?php

namespace App\Services\Dashboard;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SuperadminDashboardBuilder extends AdminDashboardBuilder
{
    public function roleKey(): string
    {
        return 'superadmin';
    }

    public function build(User $user, DashboardContext $ctx): array
    {
        $snapshot = $this->institutionalSnapshot($ctx);

        $queuePending = 0;
        $failedJobs = 0;
        $dbOk = true;
        try {
            DB::select('SELECT 1');
            $queuePending = (int) DB::table('jobs')->count();
            $failedJobs = (int) DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            $dbOk = false;
        }

        $maintenanceActive = $ctx->feature('maintenance_mode', false);
        $reverbConfigured = !empty(config('reverb.apps.apps.0.key', env('REVERB_APP_KEY')));

        return [
            'roleKey' => $this->roleKey(),
            'personal' => $this->personalSummary($user, $ctx),
            'admin' => $snapshot,
            'system' => [
                'db_ok' => $dbOk,
                'queue_pending' => $queuePending,
                'failed_jobs' => $failedJobs,
                'maintenance_mode' => $maintenanceActive,
                'reverb_configured' => $reverbConfigured,
                'simulated_role' => $user->getSimulatedRole(),
                'unfilled_habit_today' => max(0, $snapshot['total_employees'] - $snapshot['habit_today']['filled_users']),
            ],
        ];
    }

    public function buildDeferred(User $user, DashboardContext $ctx): array
    {
        $recentLogins = LoginLog::with('user:id,name')
            ->latest('created_at')
            ->limit(5)
            ->get(['id', 'user_id', 'ip_address', 'location', 'created_at'])
            ->map(fn ($l) => [
                'id' => $l->id,
                'user_name' => $l->user?->name ?? 'User',
                'ip' => $l->ip_address,
                'location' => $l->location,
                'time' => $l->created_at?->timezone('Asia/Jakarta')->format('d M H:i'),
            ])
            ->all();

        return [
            'habit_trend' => $this->institutionalHabitTrend($ctx),
            'not_checked_in' => $this->notCheckedInTop5($ctx),
            'recent_logins' => $recentLogins,
        ];
    }
}
