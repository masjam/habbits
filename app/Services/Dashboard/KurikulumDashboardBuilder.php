<?php

namespace App\Services\Dashboard;

use App\Models\TeacherAdministration;
use App\Models\TeacherJournal;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class KurikulumDashboardBuilder extends AbstractDashboardBuilder
{
    public function roleKey(): string
    {
        return 'kurikulum';
    }

    /**
     * @return array<int>
     */
    private function teacherIds(DashboardContext $ctx): array
    {
        return Cache::remember("dash_teacher_ids_{$ctx->todayStr}", 180, function () {
            return User::with(['roles'])
                ->withExists(['teacherJournals', 'teacherAdministrations'])
                ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['superadmin', 'admin']))
                ->where(fn ($q) => $q->whereNull('status_kehadiran')->orWhere('status_kehadiran', '!=', 'Non Aktif'))
                ->get()
                ->filter(fn (User $u) => $u->evaluationCategory() === 'guru')
                ->pluck('id')
                ->values()
                ->all();
        });
    }

    public function build(User $user, DashboardContext $ctx): array
    {
        $teacherIds = $this->teacherIds($ctx);
        $totalTeachers = count($teacherIds);

        $adminStats = TeacherAdministration::where('academic_year', $ctx->academicYear)
            ->when($totalTeachers > 0, fn ($q) => $q->whereIn('user_id', $teacherIds))
            ->selectRaw("
                COUNT(*) as total_docs,
                SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified_docs,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_docs,
                COUNT(DISTINCT CASE WHEN status = 'verified' THEN user_id END) as verified_teachers
            ")
            ->first();

        $journalFilledToday = $totalTeachers > 0
            ? (int) TeacherJournal::where('date', $ctx->todayStr)->whereIn('user_id', $teacherIds)->distinct('user_id')->count('user_id')
            : 0;

        $verifiedDocs = (int) ($adminStats->verified_docs ?? 0);
        $totalDocs = (int) ($adminStats->total_docs ?? 0);

        return [
            'roleKey' => $this->roleKey(),
            'personal' => $this->personalSummary($user, $ctx),
            'kurikulum' => [
                'academic_year' => $ctx->academicYear,
                'total_teachers' => $totalTeachers,
                'admin_compliance_percent' => $totalDocs > 0 ? round(($verifiedDocs / $totalDocs) * 100, 1) : 0,
                'admin_verified_docs' => $verifiedDocs,
                'admin_total_docs' => $totalDocs,
                'pending_verification' => (int) ($adminStats->pending_docs ?? 0),
                'journal_filled_today' => $journalFilledToday,
                'journal_missing_today' => max(0, $totalTeachers - $journalFilledToday),
            ],
        ];
    }

    public function buildDeferred(User $user, DashboardContext $ctx): array
    {
        $teacherIds = $this->teacherIds($ctx);
        $filledIds = TeacherJournal::where('date', $ctx->todayStr)
            ->whereIn('user_id', $teacherIds)
            ->pluck('user_id')
            ->all();

        $missingIds = array_values(array_diff($teacherIds, $filledIds));
        $missingTeachers = !empty($missingIds)
            ? User::whereIn('id', array_slice($missingIds, 0, 5))
                ->orderBy('name')
                ->get(['id', 'name', 'divisi'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'divisi' => $u->divisi ?? 'Guru'])
                ->all()
            : [];

        $journalTrendRaw = TeacherJournal::whereBetween('date', [$ctx->trendStartStr, $ctx->todayStr])
            ->select('date', DB::raw('COUNT(*) as total'))
            ->groupBy('date')
            ->pluck('total', 'date');

        $labels = [];
        $values = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = $ctx->now->copy()->subDays($i);
            $labels[] = $d->locale('id')->isoFormat('D MMM');
            $values[] = (int) ($journalTrendRaw[$d->toDateString()] ?? 0);
        }

        return [
            'missing_journal_teachers' => $missingTeachers,
            'journal_trend' => ['labels' => $labels, 'values' => $values],
        ];
    }
}
