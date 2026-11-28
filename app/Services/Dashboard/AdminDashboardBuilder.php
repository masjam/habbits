<?php

namespace App\Services\Dashboard;

use App\Models\Attendance;
use App\Models\EmployeeDocument;
use App\Models\HabitLog;
use App\Models\PerformanceEvaluation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminDashboardBuilder extends AbstractDashboardBuilder
{
    public function roleKey(): string
    {
        return 'admin';
    }

    protected function institutionalSnapshot(DashboardContext $ctx): array
    {
        $empIds = $ctx->activeEmployeeIds();
        $totalEmployees = count($empIds);

        $attToday = ['hadir' => 0, 'terlambat' => 0, 'izin' => 0, 'belum' => $totalEmployees, 'pending_permits' => 0];
        if ($ctx->feature('feature_presensi') && $totalEmployees > 0) {
            $row = Attendance::whereIn('user_id', $empIds)
                ->where('date', $ctx->todayStr)
                ->selectRaw("
                    COUNT(DISTINCT user_id) as checked_in,
                    SUM(CASE WHEN status IN ('hadir','tepat_waktu') THEN 1 ELSE 0 END) as hadir,
                    SUM(CASE WHEN status = 'terlambat' THEN 1 ELSE 0 END) as terlambat,
                    SUM(CASE WHEN status IN ('izin','sakit','cuti','dinas_luar') THEN 1 ELSE 0 END) as izin
                ")
                ->first();

            $pendingPermits = (int) Attendance::whereIn('user_id', $empIds)
                ->where('approval_status', 'pending')
                ->count();

            $checkedIn = (int) ($row->checked_in ?? 0);
            $attToday = [
                'hadir' => (int) ($row->hadir ?? 0),
                'terlambat' => (int) ($row->terlambat ?? 0),
                'izin' => (int) ($row->izin ?? 0),
                'belum' => max(0, $totalEmployees - $checkedIn),
                'pending_permits' => $pendingPermits,
            ];
        }

        $habitToday = ['filled_users' => 0, 'percent_users' => 0, 'score_today' => 0, 'max_score_today' => 0, 'score_percent' => 0];
        if ($ctx->feature('feature_habit') && $totalEmployees > 0) {
            $maxDaily = $ctx->maxDailyHabitScore();
            $hRow = HabitLog::whereIn('user_id', $empIds)
                ->where('tanggal', $ctx->todayStr)
                ->selectRaw('COUNT(DISTINCT user_id) as filled_users, COALESCE(SUM(skor_diperoleh), 0) as total_score')
                ->first();

            $filledUsers = (int) ($hRow->filled_users ?? 0);
            $scoreToday = (int) ($hRow->total_score ?? 0);
            $maxAll = $totalEmployees * $maxDaily;

            $habitToday = [
                'filled_users' => $filledUsers,
                'percent_users' => round(($filledUsers / $totalEmployees) * 100, 1),
                'score_today' => $scoreToday,
                'max_score_today' => $maxAll,
                'score_percent' => $maxAll > 0 ? round(($scoreToday / $maxAll) * 100, 1) : 0,
            ];
        }

        $arsipPending = $ctx->feature('feature_arsip')
            ? (int) EmployeeDocument::where('is_verified', false)->count()
            : 0;

        $kpiPublished = $ctx->feature('feature_kpi')
            ? (int) PerformanceEvaluation::where('academic_year', $ctx->academicYear)->where('status', 'published')->distinct('user_id')->count('user_id')
            : 0;

        $fingerprintRegistered = (int) User::whereIn('id', $empIds ?: [0])->whereNotNull('fingerprint_id')->count();

        return [
            'total_employees' => $totalEmployees,
            'attendance_today' => $attToday,
            'habit_today' => $habitToday,
            'arsip_pending' => $arsipPending,
            'kpi_published' => $kpiPublished,
            'fingerprint_enabled' => $ctx->feature('attendance_mode_fingerprint', false),
            'fingerprint_registered' => $fingerprintRegistered,
        ];
    }

    protected function institutionalHabitTrend(DashboardContext $ctx): array
    {
        $empIds = $ctx->activeEmployeeIds();
        $totalEmployees = count($empIds);
        if (!$ctx->feature('feature_habit') || $totalEmployees === 0) {
            return ['labels' => [], 'values' => []];
        }

        $maxAll = $totalEmployees * $ctx->maxDailyHabitScore();
        $rows = HabitLog::whereIn('user_id', $empIds)
            ->whereBetween('tanggal', [$ctx->trendStartStr, $ctx->todayStr])
            ->select('tanggal', DB::raw('SUM(skor_diperoleh) as total'))
            ->groupBy('tanggal')
            ->pluck('total', 'tanggal');

        $labels = [];
        $values = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = $ctx->now->copy()->subDays($i);
            $score = (float) ($rows[$d->toDateString()] ?? 0);
            $labels[] = $d->locale('id')->isoFormat('D MMM');
            $values[] = $maxAll > 0 ? round(min(100, ($score / $maxAll) * 100), 1) : 0;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    protected function notCheckedInTop5(DashboardContext $ctx): array
    {
        if (!$ctx->feature('feature_presensi')) {
            return [];
        }
        $empIds = $ctx->activeEmployeeIds();
        if (empty($empIds)) {
            return [];
        }

        $checkedInIds = Attendance::whereIn('user_id', $empIds)
            ->where('date', $ctx->todayStr)
            ->pluck('user_id')
            ->all();

        $missingIds = array_values(array_diff($empIds, $checkedInIds));
        if (empty($missingIds)) {
            return [];
        }

        return User::whereIn('id', array_slice($missingIds, 0, 5))
            ->orderBy('name')
            ->get(['id', 'name', 'divisi'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'divisi' => $u->divisi ?? 'Pegawai'])
            ->all();
    }

    public function build(User $user, DashboardContext $ctx): array
    {
        return [
            'roleKey' => $this->roleKey(),
            'personal' => $this->personalSummary($user, $ctx),
            'admin' => $this->institutionalSnapshot($ctx),
        ];
    }

    public function buildDeferred(User $user, DashboardContext $ctx): array
    {
        return [
            'habit_trend' => $this->institutionalHabitTrend($ctx),
            'not_checked_in' => $this->notCheckedInTop5($ctx),
        ];
    }
}
