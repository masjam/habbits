<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeacherJournal;
use App\Models\User;
use App\Services\AcademicYearService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeacherJournalController extends Controller
{
    public function __construct(
        private readonly AcademicYearService $academicYearService
    ) {}

    public function index(Request $request)
    {
        $periodType = (string) $request->query('period_type', 'bulan');
        if (!in_array($periodType, ['semua', 'bulan', 'tanggal', 'semester'], true)) {
            $periodType = 'bulan';
        }

        $academicYear = (string) $request->query('academic_year', $this->academicYearService->getActiveYear());
        $semester = (string) $request->query('semester', $this->academicYearService->getActiveSemester());
        $month = (string) $request->query('month', Carbon::now()->format('Y-m'));
        $startDate = (string) $request->query('start_date', '');
        $endDate = (string) $request->query('end_date', '');
        $teacherId = (string) $request->query('teacher_id', 'all');
        $subject = (string) $request->query('subject', 'all');
        $className = (string) $request->query('class_name', 'all');
        $search = trim((string) $request->query('search', ''));
        $tab = (string) $request->query('tab', 'per_guru');
        if (!in_array($tab, ['per_guru', 'detail'], true)) {
            $tab = 'per_guru';
        }

        // Tentukan rentang tanggal efektif dari mode periode
        [$effectiveStart, $effectiveEnd] = $this->resolveDateRange(
            $periodType,
            $month,
            $startDate,
            $endDate,
            $academicYear,
            $semester
        );

        $baseQuery = TeacherJournal::query();

        if ($effectiveStart) {
            $baseQuery->where('date', '>=', $effectiveStart);
        }
        if ($effectiveEnd) {
            $baseQuery->where('date', '<=', $effectiveEnd);
        }
        if ($subject !== 'all' && $subject !== '') {
            $baseQuery->where('subject', $subject);
        }
        if ($className !== 'all' && $className !== '') {
            $baseQuery->where('class_name', $className);
        }
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('nip', 'like', "%{$search}%"))
                    ->orWhere('class_name', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('theme', 'like', "%{$search}%");
            });
        }

        // 1. Rekap Per Guru (agregasi berdasarkan filter aktif tanpa terkunci teacher_id)
        $summaryRows = (clone $baseQuery)
            ->selectRaw('user_id, COUNT(*) as total_journals, COUNT(DISTINCT date) as active_days, MAX(date) as last_date')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $metaRows = (clone $baseQuery)
            ->select('user_id', 'subject', 'class_name')
            ->distinct()
            ->get()
            ->groupBy('user_id');

        // Ambil daftar guru (yang relevan atau pernah mengisi jurnal)
        $teachersQuery = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['user', 'kurikulum']))
            ->withExists(['teacherJournals', 'teacherAdministrations'])
            ->orderBy('name');

        if ($search !== '') {
            $teachersQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhereIn('id', $summaryRows->keys());
            });
        }

        $teacherSummaries = $teachersQuery->get()
            ->filter(function (User $u) use ($summaryRows) {
                if ($summaryRows->has($u->id)) {
                    return true;
                }
                return $u->evaluationCategory() === 'guru';
            })
            ->map(function (User $u) use ($summaryRows, $metaRows) {
                $agg = $summaryRows->get($u->id);
                $userMeta = $metaRows->get($u->id, collect());

                return [
                    'user_id'        => $u->id,
                    'name'           => $u->name,
                    'nip'            => $u->nip,
                    'divisi'         => $u->divisi,
                    'total_journals' => (int) ($agg->total_journals ?? 0),
                    'active_days'    => (int) ($agg->active_days ?? 0),
                    'last_date'      => $agg?->last_date,
                    'subjects'       => $userMeta->pluck('subject')->filter()->unique()->sort()->values()->all(),
                    'classes'        => $userMeta->pluck('class_name')->filter()->unique()->sort()->values()->all(),
                ];
            })
            ->sortByDesc('total_journals')
            ->values();

        // 2. Detail Jurnal (dengan filter teacher_id opsional)
        $detailQuery = (clone $baseQuery)->with('user:id,name,nip,divisi')->orderByDesc('date')->orderByDesc('id');
        if ($teacherId !== 'all' && $teacherId !== '') {
            $detailQuery->where('user_id', (int) $teacherId);
        }
        $journals = $detailQuery->paginate(20)->withQueryString();

        // Opsi dropdown filter
        $subjects = TeacherJournal::distinct()->pluck('subject')->filter()->sort()->values()->all();
        $classes = TeacherJournal::distinct()->pluck('class_name')->filter()->sort()->values()->all();
        $teacherOptions = User::whereIn('id', TeacherJournal::distinct()->pluck('user_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->values();

        return Inertia::render('Admin/TeacherJournal/Index', [
            'journals'         => $journals,
            'teacherSummaries' => $teacherSummaries,
            'subjects'         => $subjects,
            'classes'          => $classes,
            'teacherOptions'   => $teacherOptions,
            'academicYears'    => $this->academicYearService->getAcademicYears(),
            'stats'            => [
                'total_journals'  => (int) $summaryRows->sum('total_journals'),
                'active_teachers' => $summaryRows->count(),
                'total_teachers'  => $teacherSummaries->count(),
                'total_subjects'  => count($subjects),
            ],
            'filters'          => [
                'tab'           => $tab,
                'period_type'   => $periodType,
                'academic_year' => $academicYear,
                'semester'      => $semester,
                'month'         => $month,
                'start_date'    => $startDate,
                'end_date'      => $endDate,
                'teacher_id'    => $teacherId,
                'subject'       => $subject,
                'class_name'    => $className,
                'search'        => $search,
            ],
        ]);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveDateRange(
        string $periodType,
        string $month,
        string $startDate,
        string $endDate,
        string $academicYear,
        string $semester
    ): array {
        if ($periodType === 'bulan' && preg_match('/^(\d{4})-(\d{2})$/', $month, $m)) {
            $start = Carbon::createFromDate((int) $m[1], (int) $m[2], 1)->startOfMonth();
            return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
        }

        if ($periodType === 'tanggal') {
            return [
                $startDate !== '' ? $startDate : null,
                $endDate !== '' ? $endDate : null,
            ];
        }

        if ($periodType === 'semester') {
            $parts = explode('/', $academicYear);
            $startYear = (int) ($parts[0] ?? date('Y'));
            $endYear = (int) ($parts[1] ?? ($startYear + 1));

            if ($semester === 'genap') {
                return ["{$endYear}-01-01", "{$endYear}-06-30"];
            }
            return ["{$startYear}-07-01", "{$startYear}-12-31"];
        }

        return [null, null];
    }
}
