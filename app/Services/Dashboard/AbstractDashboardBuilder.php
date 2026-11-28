<?php

namespace App\Services\Dashboard;

use App\Models\Attendance;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\HabitVoucher;
use App\Models\MenstruationLog;
use App\Models\PerformanceEvaluation;
use App\Models\TeacherAdministration;
use App\Models\TeacherJournal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

abstract class AbstractDashboardBuilder implements DashboardBuilderInterface
{
    /**
     * Ringkasan pegawai pribadi (SQL aggregation, tanpa N+1).
     */
    protected function personalSummary(User $user, DashboardContext $ctx): array
    {
        $presensiActive = $ctx->feature('feature_presensi');
        $habitActive = $ctx->feature('feature_habit');
        $jurnalActive = $ctx->feature('feature_jurnal');
        $adminGuruActive = $ctx->feature('feature_administrasi_guru');
        $kpiActive = $ctx->feature('feature_kpi');

        $attendanceToday = null;
        $monthlyAttendance = ['hadir' => 0, 'terlambat' => 0, 'izin' => 0];

        if ($presensiActive) {
            $att = Attendance::where('user_id', $user->id)
                ->where('date', $ctx->todayStr)
                ->select('id', 'time_in', 'time_out', 'status', 'approval_status')
                ->first();

            $attendanceToday = [
                'checked_in' => (bool) ($att && $att->time_in),
                'checked_out' => (bool) ($att && $att->time_out),
                'time_in' => $att?->time_in ? substr((string) $att->time_in, 0, 5) : null,
                'time_out' => $att?->time_out ? substr((string) $att->time_out, 0, 5) : null,
                'status' => $att?->status ?? 'belum_check_in',
            ];

            $attStats = Attendance::where('user_id', $user->id)
                ->whereBetween('date', [$ctx->monthStartStr, $ctx->monthEndStr])
                ->selectRaw("
                    SUM(CASE WHEN status IN ('hadir','tepat_waktu') THEN 1 ELSE 0 END) as hadir,
                    SUM(CASE WHEN status = 'terlambat' THEN 1 ELSE 0 END) as terlambat,
                    SUM(CASE WHEN status IN ('izin','sakit','cuti','dinas_luar') THEN 1 ELSE 0 END) as izin
                ")
                ->first();

            $monthlyAttendance = [
                'hadir' => (int) ($attStats->hadir ?? 0),
                'terlambat' => (int) ($attStats->terlambat ?? 0),
                'izin' => (int) ($attStats->izin ?? 0),
            ];
        }

        $habitToday = ['score' => 0, 'max' => 0, 'percent' => 0, 'streak' => (int) ($user->current_streak ?? 0), 'vouchers' => 0];
        if ($habitActive) {
            $maxDaily = $ctx->maxDailyHabitScore();
            $todayScores = $this->computeCappedDailyScores($user, $ctx->todayStr, $ctx->todayStr, $maxDaily);
            $todayScore = (int) ($todayScores[$ctx->todayStr] ?? 0);
            $vouchers = (int) HabitVoucher::where('user_id', $user->id)
                ->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $ctx->todayStr))
                ->count();

            $habitToday = [
                'score' => $todayScore,
                'max' => $maxDaily,
                'percent' => $maxDaily > 0 ? round(min(100, ($todayScore / $maxDaily) * 100), 1) : 0,
                'streak' => (int) ($user->current_streak ?? 0),
                'vouchers' => $vouchers,
            ];
        }

        $teacherSummary = null;
        if ($user->evaluationCategory() === 'guru' && ($jurnalActive || $adminGuruActive)) {
            $journalToday = $jurnalActive
                ? TeacherJournal::where('user_id', $user->id)->where('date', $ctx->todayStr)->exists()
                : null;

            $adminVerified = 0;
            $adminTotal = 0;
            if ($adminGuruActive) {
                $adm = TeacherAdministration::where('user_id', $user->id)
                    ->where('academic_year', $ctx->academicYear)
                    ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified")
                    ->first();
                $adminTotal = (int) ($adm->total ?? 0);
                $adminVerified = (int) ($adm->verified ?? 0);
            }

            $teacherSummary = [
                'journal_today_filled' => $journalToday,
                'admin_verified' => $adminVerified,
                'admin_total' => $adminTotal,
            ];
        }

        $latestKpi = null;
        if ($kpiActive) {
            $eval = PerformanceEvaluation::where('user_id', $user->id)
                ->where('status', 'published')
                ->orderByDesc('published_at')
                ->select('id', 'final_score', 'predicate', 'period_label', 'academic_year')
                ->first();
            if ($eval) {
                $latestKpi = [
                    'score' => (float) $eval->final_score,
                    'predicate' => $eval->predicate,
                    'period' => $eval->period_label,
                    'academic_year' => $eval->academic_year,
                ];
            }
        }

        return [
            'attendance_today' => $attendanceToday,
            'monthly_attendance' => $monthlyAttendance,
            'habit_today' => $habitToday,
            'teacher' => $teacherSummary,
            'latest_kpi' => $latestKpi,
            'work_hours' => [
                'start' => $user->work_start ? substr((string) $user->work_start, 0, 5) : (string) $ctx->setting('presensi_work_start', '07:00'),
                'end' => $user->work_end ? substr((string) $user->work_end, 0, 5) : (string) $ctx->setting('presensi_work_end', '15:00'),
            ],
        ];
    }

    /**
     * Hitung skor harian bersih (habit pengganti haid hanya dihitung saat tanggal haid,
     * dan total skor harian dibatasi maksimal $maxDaily).
     *
     * @return array<string, int>
     */
    protected function computeCappedDailyScores(User $user, string $startStr, string $endStr, int $maxDaily): array
    {
        $logs = HabitLog::where('user_id', $user->id)
            ->whereDate('tanggal', '>=', $startStr)
            ->whereDate('tanggal', '<=', $endStr)
            ->get(['tanggal', 'habit_id', 'skor_diperoleh']);

        if ($logs->isEmpty()) {
            return [];
        }

        $habitsById = Habit::allCachedById();
        $haidIntervals = [];
        if ($user->gender === 'P') {
            $haidLogs = MenstruationLog::where('user_id', $user->id)->get(['waktu_mulai', 'waktu_selesai']);
            foreach ($haidLogs as $hl) {
                $start = $hl->waktu_mulai ? Carbon::parse($hl->waktu_mulai)->timezone('Asia/Jakarta')->toDateString() : null;
                $end = $hl->waktu_selesai ? Carbon::parse($hl->waktu_selesai)->timezone('Asia/Jakarta')->toDateString() : null;
                if ($start) {
                    $haidIntervals[] = [$start, $end];
                }
            }
        }

        $isHaidOnDate = static function (string $dateStr) use ($haidIntervals): bool {
            foreach ($haidIntervals as [$start, $end]) {
                if ($dateStr >= $start && ($end === null || $dateStr <= $end)) {
                    return true;
                }
            }
            return false;
        };

        $rawTotals = [];
        foreach ($logs as $log) {
            $dateStr = $log->tanggal instanceof Carbon
                ? $log->tanggal->toDateString()
                : substr((string) $log->tanggal, 0, 10);

            $habit = $habitsById->get($log->habit_id);
            if ($habit && $habit->is_pengganti_haid && !$isHaidOnDate($dateStr)) {
                continue;
            }

            $rawTotals[$dateStr] = ($rawTotals[$dateStr] ?? 0) + (int) $log->skor_diperoleh;
        }

        $capped = [];
        foreach ($rawTotals as $dateStr => $sum) {
            $capped[$dateStr] = $maxDaily > 0 ? min($maxDaily, $sum) : $sum;
        }

        return $capped;
    }

    /**
     * Daftar skor harian habit dalam bulan berjalan + ringkasan total & target bulanan.
     */
    protected function personalHabitTrend(User $user, DashboardContext $ctx): array
    {
        if (!$ctx->feature('feature_habit')) {
            return [
                'daily' => [],
                'summary' => null,
            ];
        }

        $maxDaily = $ctx->maxDailyHabitScore();
        $dailyScores = $this->computeCappedDailyScores($user, $ctx->monthStartStr, $ctx->todayStr, $maxDaily);

        $daily = [];
        $totalScore = 0;
        for ($day = $ctx->now->day; $day >= 1; $day--) {
            $d = $ctx->now->copy()->setDate($ctx->now->year, $ctx->now->month, $day);
            $dateStr = $d->toDateString();
            $score = (int) ($dailyScores[$dateStr] ?? 0);
            $totalScore += $score;

            $daily[] = [
                'date' => $dateStr,
                'label' => $d->locale('id')->isoFormat('ddd, D MMM'),
                'score' => $score,
                'max' => $maxDaily,
                'percent' => $maxDaily > 0 ? round(min(100, ($score / $maxDaily) * 100), 1) : 0,
                'is_today' => $dateStr === $ctx->todayStr,
            ];
        }

        $maxMonthScore = $maxDaily * $ctx->daysInMonth;
        $achievedPct = $maxMonthScore > 0 ? round(min(100, ($totalScore / $maxMonthScore) * 100), 1) : 0.0;
        $targetPct = HabitVoucher::getEffectiveTargetForUser($user);
        $targetScore = (int) ceil(($targetPct / 100) * $maxMonthScore);

        return [
            'daily' => $daily,
            'summary' => [
                'month_label' => $ctx->now->locale('id')->isoFormat('MMMM Y'),
                'total_score' => $totalScore,
                'max_score' => $maxMonthScore,
                'achieved_percentage' => $achievedPct,
                'target_percentage' => $targetPct,
                'target_score' => $targetScore,
                'points_needed' => max(0, $targetScore - $totalScore),
                'is_target_reached' => $achievedPct >= $targetPct && $totalScore > 0,
            ],
        ];
    }

    /**
     * Tren skor habit per bulan (semester berjalan) untuk user.
     */
    protected function personalHabitMonthlyTrend(User $user, DashboardContext $ctx): array
    {
        if (!$ctx->feature('feature_habit')) {
            return ['labels' => [], 'values' => []];
        }

        $year = $ctx->now->year;
        $startMonth = $ctx->now->month <= 6 ? 1 : 7;
        $endMonth = $startMonth + 5;
        $startStr = $ctx->now->copy()->setDate($year, $startMonth, 1)->startOfMonth()->toDateString();
        $endStr = $ctx->now->copy()->setDate($year, $endMonth, 1)->endOfMonth()->toDateString();

        $maxDaily = $ctx->maxDailyHabitScore();
        $dailyScores = $this->computeCappedDailyScores($user, $startStr, $endStr, $maxDaily);

        $monthlyTotals = [];
        foreach ($dailyScores as $dateStr => $score) {
            $m = (int) substr($dateStr, 5, 2);
            $monthlyTotals[$m] = ($monthlyTotals[$m] ?? 0) + $score;
        }

        $monthNames = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Ags', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
        $labels = [];
        $values = [];
        for ($m = $startMonth; $m <= $endMonth; $m++) {
            $daysInMonth = $ctx->now->copy()->setDate($year, $m, 1)->daysInMonth;
            $maxMonth = $maxDaily * $daysInMonth;
            $score = (float) ($monthlyTotals[$m] ?? 0);
            $labels[] = $monthNames[$m];
            $values[] = $maxMonth > 0 ? round(min(100, ($score / $maxMonth) * 100), 1) : 0;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Rekap presensi per bulan (semester berjalan) untuk user.
     */
    protected function personalAttendanceTrend(User $user, DashboardContext $ctx): array
    {
        if (!$ctx->feature('feature_presensi')) {
            return ['labels' => [], 'values' => [], 'summary' => ['hadir' => 0, 'terlambat' => 0, 'izin' => 0]];
        }

        $year = $ctx->now->year;
        $startMonth = $ctx->now->month <= 6 ? 1 : 7;
        $endMonth = $startMonth + 5;
        $startStr = $ctx->now->copy()->setDate($year, $startMonth, 1)->startOfMonth()->toDateString();
        $endStr = $ctx->now->copy()->setDate($year, $endMonth, 1)->endOfMonth()->toDateString();

        $monthExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', date) AS INTEGER)"
            : 'MONTH(date)';

        $rows = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$startStr, $endStr])
            ->selectRaw("
                {$monthExpr} as m,
                SUM(CASE WHEN status IN ('hadir','tepat_waktu','terlambat') THEN 1 ELSE 0 END) as hadir_total,
                SUM(CASE WHEN status = 'terlambat' THEN 1 ELSE 0 END) as terlambat,
                SUM(CASE WHEN status IN ('izin','sakit','cuti','dinas_luar') THEN 1 ELSE 0 END) as izin
            ")
            ->groupBy('m')
            ->get()
            ->keyBy('m');

        $monthNames = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Ags', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
        $labels = [];
        $values = [];
        $sumHadir = 0;
        $sumTerlambat = 0;
        $sumIzin = 0;

        for ($m = $startMonth; $m <= $endMonth; $m++) {
            $r = $rows->get($m);
            $hadir = (int) ($r->hadir_total ?? 0);
            $terlambat = (int) ($r->terlambat ?? 0);
            $izin = (int) ($r->izin ?? 0);
            $labels[] = $monthNames[$m];
            $values[] = $hadir;
            $sumHadir += $hadir;
            $sumTerlambat += $terlambat;
            $sumIzin += $izin;
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'summary' => [
                'hadir' => $sumHadir,
                'terlambat' => $sumTerlambat,
                'izin' => $sumIzin,
            ],
        ];
    }
}
