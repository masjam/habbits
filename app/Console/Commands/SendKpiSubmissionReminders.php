<?php

namespace App\Console\Commands;

use App\Models\PerformanceAssignment;
use App\Models\PerformanceEvaluation;
use App\Models\Setting;
use App\Notifications\PerformanceEvaluationNotification;
use App\Services\PerformanceInsightService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendKpiSubmissionReminders extends Command
{
    protected $signature = 'kpi:remind-pending {--days=3 : Jumlah hari sebelum akhir periode untuk mengirim pengingat}';

    protected $description = 'Kirim pengingat ke penilai delegasi yang belum mengajukan penilaian KPI mendekati akhir periode';

    public function handle(PerformanceInsightService $insightService): int
    {
        if (Setting::get('feature_kpi', '1') === '0') {
            return self::SUCCESS;
        }

        $daysThreshold = max(1, (int) $this->option('days'));
        $today = Carbon::today();

        $activeAssignments = PerformanceAssignment::active()
            ->with(['assessor:id,name', 'employee:id,name'])
            ->get();

        if ($activeAssignments->isEmpty()) {
            return self::SUCCESS;
        }

        $pendingByAssessor = [];

        foreach ($activeAssignments as $assignment) {
            if (!$assignment->assessor) {
                continue;
            }

            $range = $insightService->resolvePeriodDates(
                $assignment->academic_year,
                $assignment->period_type,
                $assignment->period_label
            );

            $daysLeft = $today->diffInDays($range['end']->copy()->startOfDay(), false);
            if ($daysLeft < 0 || $daysLeft > $daysThreshold) {
                continue;
            }

            $alreadySubmitted = PerformanceEvaluation::where('user_id', $assignment->employee_id)
                ->forPeriod($assignment->academic_year, $assignment->period_type, $assignment->period_label)
                ->whereIn('status', [
                    PerformanceEvaluation::STATUS_SUBMITTED,
                    PerformanceEvaluation::STATUS_PUBLISHED,
                ])
                ->exists();

            if (!$alreadySubmitted) {
                $pendingByAssessor[$assignment->assessor_id]['user'] = $assignment->assessor;
                $pendingByAssessor[$assignment->assessor_id]['count'] = ($pendingByAssessor[$assignment->assessor_id]['count'] ?? 0) + 1;
            }
        }

        $sent = 0;
        foreach ($pendingByAssessor as $item) {
            $count = $item['count'];
            $item['user']->notify(new PerformanceEvaluationNotification(
                'submission_reminder',
                'Pengingat Penilaian KPI',
                "Anda memiliki {$count} tugas penilaian KPI yang belum diajukan dan mendekati batas periode.",
                route('performance.assigned.index')
            ));
            $sent++;
        }

        $this->info("Pengingat KPI dikirim ke {$sent} penilai.");

        return self::SUCCESS;
    }
}
