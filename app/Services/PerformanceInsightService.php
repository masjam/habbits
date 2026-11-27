<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\HabitLog;
use App\Models\TeacherAdministration;
use App\Models\TeacherJournal;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class PerformanceInsightService
{
    /**
     * Hitung rentang tanggal dan semester dari parameter periode evaluasi.
     *
     * @return array{start: Carbon, end: Carbon, semester: ?string}
     */
    public function resolvePeriodDates(string $academicYear, string $periodType, string $periodLabel): array
    {
        $parts = explode('/', $academicYear);
        $startYear = (int) ($parts[0] ?? date('Y'));
        $endYear = (int) ($parts[1] ?? ($startYear + 1));

        if ($periodType === 'semester_1') {
            return [
                'start'    => Carbon::createFromDate($startYear, 7, 1)->startOfDay(),
                'end'      => Carbon::createFromDate($startYear, 12, 31)->endOfDay(),
                'semester' => 'ganjil',
            ];
        }

        if ($periodType === 'semester_2') {
            return [
                'start'    => Carbon::createFromDate($endYear, 1, 1)->startOfDay(),
                'end'      => Carbon::createFromDate($endYear, 6, 30)->endOfDay(),
                'semester' => 'genap',
            ];
        }

        // Default: bulanan (YYYY-MM)
        if (preg_match('/^(\d{4})-(\d{2})$/', $periodLabel, $m)) {
            $start = Carbon::createFromDate((int) $m[1], (int) $m[2], 1)->startOfMonth();
            return [
                'start'    => $start,
                'end'      => $start->copy()->endOfMonth(),
                'semester' => ((int) $m[2]) >= 7 ? 'ganjil' : 'genap',
            ];
        }

        $start = Carbon::now()->startOfMonth();
        return [
            'start'    => $start,
            'end'      => $start->copy()->endOfMonth(),
            'semester' => $start->month >= 7 ? 'ganjil' : 'genap',
        ];
    }

    /**
     * Ambil ringkasan Auto-Insight (read-only) dengan agregasi SQL untuk 1 pegawai pada 1 periode.
     */
    public function forUser(
        User $user,
        string $academicYear,
        string $periodType,
        string $periodLabel,
        ?string $categoryOverride = null
    ): array {
        $range = $this->resolvePeriodDates($academicYear, $periodType, $periodLabel);
        $startDate = $range['start']->toDateString();
        $endDate = $range['end']->toDateString();
        $effectiveEnd = $range['end']->isFuture() ? Carbon::today() : $range['end'];
        $workDays = $effectiveEnd->lt($range['start'])
            ? 0
            : $this->countWorkDays($range['start'], $effectiveEnd);

        // 1. Attendance aggregation (SQL groupBy)
        $attCounts = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $terlambat = (int) ($attCounts['terlambat'] ?? 0);
        $hadir = (int) (($attCounts['hadir'] ?? 0) + ($attCounts['dinas_luar'] ?? 0) + ($attCounts['pulang_cepat'] ?? 0) + $terlambat);
        $izinSakit = (int) (($attCounts['izin'] ?? 0) + ($attCounts['sakit'] ?? 0));
        $alfa = (int) ($attCounts['alfa'] ?? 0);
        $totalAtt = (int) $attCounts->sum();

        $attendance = [
            'has_data'        => $totalAtt > 0,
            'hadir'           => $hadir,
            'terlambat'       => $terlambat,
            'izin_sakit'      => $izinSakit,
            'alfa'            => $alfa,
            'work_days'       => $workDays,
            'attendance_rate' => $totalAtt > 0 && $workDays > 0
                ? round(min(100, ($hadir / $workDays) * 100), 1)
                : null,
        ];

        // 2. HabitLog aggregation (SQL COUNT DISTINCT tanggal)
        $habitDays = (int) HabitLog::where('user_id', $user->id)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->distinct()
            ->count('tanggal');

        $habit = [
            'has_data'         => $habitDays > 0,
            'filled_days'      => $habitDays,
            'work_days'        => $workDays,
            'consistency_rate' => $habitDays > 0 && $workDays > 0
                ? round(min(100, ($habitDays / $workDays) * 100), 1)
                : null,
        ];

        $category = $categoryOverride ?: $user->evaluationCategory();

        // 3. Khusus Guru: TeacherAdministration & TeacherJournal
        $teacherAdmin = null;
        $teacherJournal = null;

        if ($category === 'guru') {
            $adminQuery = TeacherAdministration::where('user_id', $user->id)
                ->where('academic_year', $academicYear);
            if ($periodType !== 'bulanan' && $range['semester']) {
                $adminQuery->where('semester', $range['semester']);
            }
            $adminAgg = $adminQuery
                ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'disetujui' THEN 1 ELSE 0 END) as approved")
                ->first();

            $totalDocs = (int) ($adminAgg->total ?? 0);
            $approvedDocs = (int) ($adminAgg->approved ?? 0);

            $teacherAdmin = [
                'has_data'      => $totalDocs > 0,
                'uploaded'      => $totalDocs,
                'approved'      => $approvedDocs,
                'approval_rate' => $totalDocs > 0 ? round(($approvedDocs / $totalDocs) * 100, 1) : null,
            ];

            $journalDays = (int) TeacherJournal::where('user_id', $user->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->distinct()
                ->count('date');
            $journalCount = (int) TeacherJournal::where('user_id', $user->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->count();

            $teacherJournal = [
                'has_data'      => $journalCount > 0,
                'total_entries' => $journalCount,
                'filled_days'   => $journalDays,
                'work_days'     => $workDays,
                'journal_rate'  => $journalCount > 0 && $workDays > 0
                    ? round(min(100, ($journalDays / $workDays) * 100), 1)
                    : null,
            ];
        }

        return [
            'period_start'           => $startDate,
            'period_end'             => $endDate,
            'category'               => $category,
            'attendance'             => $attendance,
            'habit'                  => $habit,
            'teacher_administration' => $teacherAdmin,
            'teacher_journal'        => $teacherJournal,
        ];
    }

    private function countWorkDays(Carbon $start, Carbon $end): int
    {
        if ($end->lt($start)) {
            return 0;
        }

        $days = 0;
        foreach (CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay()) as $day) {
            if (!$day->isWeekend()) {
                $days++;
            }
        }

        return $days;
    }
}
