<?php

namespace App\Services\Dashboard;

use App\Models\Attendance;
use App\Models\Disposisi;
use App\Models\Notulen;
use App\Models\TeacherAdministration;
use App\Models\TeacherJournal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class KepalaSekolahDashboardBuilder extends AdminDashboardBuilder
{
    public function roleKey(): string
    {
        return 'kepala_sekolah';
    }

    public function build(User $user, DashboardContext $ctx): array
    {
        $snapshot = $this->institutionalSnapshot($ctx);

        $adm = TeacherAdministration::where('academic_year', $ctx->academicYear)
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified")
            ->first();
        $admTotal = (int) ($adm->total ?? 0);
        $admVerified = (int) ($adm->verified ?? 0);

        $journalToday = $ctx->feature('feature_jurnal')
            ? (int) TeacherJournal::where('date', $ctx->todayStr)->distinct('user_id')->count('user_id')
            : 0;

        $disposisiPending = (int) Disposisi::where('status', '!=', 'selesai')->count();
        $notulenPending = (int) Notulen::where('is_approved', false)->count();

        return [
            'roleKey' => $this->roleKey(),
            'personal' => $this->personalSummary($user, $ctx),
            'kepala_sekolah' => [
                'total_employees' => $snapshot['total_employees'],
                'attendance_today' => $snapshot['attendance_today'],
                'habit_today' => $snapshot['habit_today'],
                'kpi_published' => $snapshot['kpi_published'],
                'teacher_admin_percent' => $admTotal > 0 ? round(($admVerified / $admTotal) * 100, 1) : 0,
                'teacher_journal_today' => $journalToday,
                'pending_approvals' => [
                    'permits' => $snapshot['attendance_today']['pending_permits'],
                    'disposisi' => $disposisiPending,
                    'notulen' => $notulenPending,
                    'total' => $snapshot['attendance_today']['pending_permits'] + $disposisiPending + $notulenPending,
                ],
            ],
        ];
    }

    public function buildDeferred(User $user, DashboardContext $ctx): array
    {
        $empIds = $ctx->activeEmployeeIds();
        $totalEmployees = count($empIds);

        $attRows = ($ctx->feature('feature_presensi') && $totalEmployees > 0)
            ? Attendance::whereIn('user_id', $empIds)
                ->whereBetween('date', [$ctx->weekStartStr, $ctx->todayStr])
                ->select('date', DB::raw('COUNT(DISTINCT user_id) as total'))
                ->groupBy('date')
                ->pluck('total', 'date')
            : collect();

        $labels = [];
        $values = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $ctx->now->copy()->subDays($i);
            $count = (int) ($attRows[$d->toDateString()] ?? 0);
            $labels[] = $d->locale('id')->isoFormat('ddd, D');
            $values[] = $totalEmployees > 0 ? round(($count / $totalEmployees) * 100, 1) : 0;
        }

        return [
            'attendance_trend' => ['labels' => $labels, 'values' => $values],
            'habit_trend' => $this->institutionalHabitTrend($ctx),
        ];
    }
}
