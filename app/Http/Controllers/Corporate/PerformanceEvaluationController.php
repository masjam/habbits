<?php

namespace App\Http\Controllers\Corporate;

use App\Exports\PerformanceEvaluationExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePerformanceEvaluationRequest;
use App\Models\Division;
use App\Models\PerformanceEvaluation;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PerformanceEvaluationNotification;
use App\Policies\PerformanceEvaluationPolicy;
use App\Services\AcademicYearService;
use App\Services\PerformanceEvaluationWorkflow;
use App\Services\PerformanceInsightService;
use App\Services\PerformanceRubricService;
use App\Services\PerformanceScoreCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class PerformanceEvaluationController extends Controller
{
    public function __construct(
        private readonly AcademicYearService $academicYearService,
        private readonly PerformanceScoreCalculator $calculator,
        private readonly PerformanceInsightService $insightService,
        private readonly PerformanceRubricService $rubricService,
        private readonly PerformanceEvaluationWorkflow $workflow
    ) {}

    private function ensureFeatureEnabled(): void
    {
        $val = Setting::get('feature_kpi', '1');
        if ($val === '0' || $val === 'false' || $val === false) {
            abort(403, 'Fitur Penilaian Kinerja (KPI) sedang dinonaktifkan.');
        }
    }

    /**
     * Tampilan Admin / Kepala Sekolah: Daftar Pegawai + Filter + Ringkasan KPI.
     */
    public function adminIndex(Request $request)
    {
        $this->ensureFeatureEnabled();
        Gate::authorize('viewAny', PerformanceEvaluation::class);

        $isApprover = app(PerformanceEvaluationPolicy::class)->isApprover(Auth::user());

        $filters = $this->resolveFilters($request);
        if (!in_array($filters['category'], ['guru', 'karyawan'], true)) {
            $filters['category'] = 'guru';
        }

        $allMatchingCategory = $this->buildFilteredRows(array_merge($filters, ['category' => 'all']), !$isApprover);
        $categoryCounts = [
            'guru'     => $allMatchingCategory->where('category', 'guru')->count(),
            'karyawan' => $allMatchingCategory->where('category', 'karyawan')->count(),
        ];

        $rows = $allMatchingCategory
            ->filter(fn ($r) => $r['category'] === $filters['category'])
            ->values();

        $totalCount = $rows->count();
        $evaluatedRows = $rows->filter(fn ($r) => $r['evaluation'] !== null);
        $publishedRows = $rows->filter(fn ($r) => ($r['evaluation']['status'] ?? null) === 'published');
        $avgScore = $evaluatedRows->isNotEmpty()
            ? round((float) $evaluatedRows->avg(fn ($r) => $r['evaluation']['final_score']), 2)
            : 0.0;

        $perPage = 12;
        $page = max(1, (int) $request->query('page', 1));
        $paginated = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $totalCount,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return Inertia::render('Admin/Performance/Index', [
            'employees'            => $paginated,
            'filters'              => $filters,
            'categoryCounts'       => $categoryCounts,
            'academicYears'        => $this->academicYearService->getAcademicYears(),
            'divisions'            => Division::orderBy('name')->pluck('name')->values()->all(),
            'rubric'               => $this->rubricService->getRubric(),
            'canEvaluate'          => $isApprover,
            'canManageApprovals'   => $isApprover,
            'pendingApprovalCount' => $isApprover ? PerformanceEvaluation::submitted()->count() : 0,
            'summary'              => [
                'total'         => $totalCount,
                'evaluated'     => $evaluatedRows->count(),
                'not_evaluated' => $totalCount - $evaluatedRows->count(),
                'published'     => $publishedRows->count(),
                'average_score' => $avgScore,
            ],
        ]);
    }

    /**
     * Endpoint Auto-Insight (JSON) untuk modal penilaian.
     */
    public function insight(Request $request): JsonResponse
    {
        $this->ensureFeatureEnabled();

        $validated = $request->validate([
            'user_id'       => ['required', 'integer', 'exists:users,id'],
            'academic_year' => ['required', 'string', 'max:20'],
            'period_type'   => ['required', 'in:bulanan,semester_1,semester_2'],
            'period_label'  => ['required', 'string', 'max:32'],
            'category'      => ['nullable', 'in:guru,karyawan'],
        ]);

        $target = User::findOrFail($validated['user_id']);
        Gate::authorize('viewInsight', [
            PerformanceEvaluation::class,
            $target,
            $validated['academic_year'],
            $validated['period_type'],
            $validated['period_label'],
        ]);
        $insight = $this->insightService->forUser(
            $target,
            $validated['academic_year'],
            $validated['period_type'],
            $validated['period_label'],
            $validated['category'] ?? null
        );

        return response()->json($insight);
    }

    /**
     * Simpan Draft atau Publikasikan Evaluasi Kinerja (skor dihitung ulang di server).
     */
    public function store(StorePerformanceEvaluationRequest $request)
    {
        $this->ensureFeatureEnabled();

        $target = User::findOrFail($request->user_id);
        Gate::authorize('evaluate', [PerformanceEvaluation::class, $target]);

        $status = $request->input('status');
        $actor = Auth::user();

        if ($status === PerformanceEvaluation::STATUS_PUBLISHED) {
            $eval = $this->workflow->directPublish($actor, $target, $request->validated());
            $target->notify(new PerformanceEvaluationNotification(
                'evaluation_approved',
                'Hasil Evaluasi Kinerja Tersedia',
                "Hasil evaluasi KPI Anda periode {$eval->period_label} telah dipublikasikan.",
                route('performance.index'),
                ['evaluation_id' => $eval->id]
            ));
            $msg = "Penilaian kinerja {$target->name} berhasil dipublikasikan.";
        } else {
            $this->workflow->saveDraft($actor, $target, $request->validated());
            $msg = "Draft penilaian kinerja {$target->name} berhasil disimpan.";
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Transisi eksplisit status: Draft / Submitted -> Published.
     */
    public function publish(PerformanceEvaluation $evaluation)
    {
        $this->ensureFeatureEnabled();
        Gate::authorize('approve', $evaluation);

        $approver = Auth::user();
        if ($evaluation->status === PerformanceEvaluation::STATUS_SUBMITTED) {
            $published = $this->workflow->approve($evaluation, $approver);
        } else {
            $published = $this->workflow->directPublish($approver, $evaluation->user, [
                'academic_year'    => $evaluation->academic_year,
                'period_type'      => $evaluation->period_type,
                'period_label'     => $evaluation->period_label,
                'category'         => $evaluation->category,
                'indicator_scores' => $evaluation->indicator_scores ?? [],
                'notes'            => $evaluation->notes,
            ]);
        }

        $published->user?->notify(new PerformanceEvaluationNotification(
            'evaluation_approved',
            'Hasil Evaluasi Kinerja Tersedia',
            "Hasil evaluasi KPI Anda periode {$published->period_label} telah dipublikasikan.",
            route('performance.index'),
            ['evaluation_id' => $published->id]
        ));

        return redirect()->back()->with('success', 'Penilaian kinerja berhasil dipublikasikan.');
    }

    /**
     * Transisi eksplisit status: Published -> Draft.
     */
    public function unpublish(PerformanceEvaluation $evaluation)
    {
        $this->ensureFeatureEnabled();
        Gate::authorize('reopen', $evaluation);

        $this->workflow->reopen($evaluation, Auth::user(), 'Dibuka kembali ke status draft');

        return redirect()->back()->with('success', 'Penilaian kinerja dikembalikan ke status draft.');
    }

    /**
     * Ekspor Laporan KPI ke Excel atau PDF sesuai filter aktif.
     */
    public function export(Request $request)
    {
        $this->ensureFeatureEnabled();
        Gate::authorize('export', PerformanceEvaluation::class);

        $isApprover = app(PerformanceEvaluationPolicy::class)->isApprover(Auth::user());
        $filters = $this->resolveFilters($request);
        $rows = $this->buildFilteredRows($filters, !$isApprover)->map(function ($r) use ($filters) {
            $eval = $r['evaluation'];
            $scores = $eval['aspect_scores'] ?? [];
            $vals = array_values($scores);

            return [
                'name'           => $r['name'],
                'nip'            => $r['nip'],
                'divisi'         => $r['divisi'],
                'category'       => $eval['category'] ?? $r['category'],
                'period_display' => $this->formatPeriodDisplay($filters['period_type'], $filters['period_label'], $filters['academic_year']),
                'aspect_1'       => $vals[0] ?? '-',
                'aspect_2'       => $vals[1] ?? '-',
                'aspect_3'       => $vals[2] ?? '-',
                'aspect_4'       => $vals[3] ?? '-',
                'final_score'    => $eval ? $eval['final_score'] : '-',
                'predicate'      => $eval['predicate'] ?? '-',
                'status_label'   => $eval ? ($eval['status'] === 'published' ? 'Dipublikasikan' : 'Draft') : 'Belum Dinilai',
                'evaluator_name' => $eval['evaluator_name'] ?? '-',
            ];
        });

        $format = strtolower((string) $request->query('format', 'xlsx'));
        $slug = str_replace('/', '-', $filters['academic_year']) . '-' . $filters['period_label'];

        if ($format === 'pdf') {
            $pdf = Pdf::setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => false], true)
                ->loadView('pdf.performance-evaluations', [
                    'rows'         => $rows,
                    'academicYear' => $filters['academic_year'],
                    'periodLabel'  => $this->formatPeriodDisplay($filters['period_type'], $filters['period_label'], $filters['academic_year']),
                ])
                ->setPaper('a4', 'landscape');

            return $pdf->download("Laporan-KPI-{$slug}.pdf");
        }

        return Excel::download(new PerformanceEvaluationExport($rows), "Laporan-KPI-{$slug}.xlsx");
    }

    /**
     * Tampilan Pegawai: Hanya evaluasi milik sendiri yang berstatus published.
     */
    public function userIndex(Request $request)
    {
        $this->ensureFeatureEnabled();
        $user = Auth::user();

        $evaluations = PerformanceEvaluation::query()
            ->with(['evaluator:id,name', 'approver:id,name'])
            ->where('user_id', $user->id)
            ->published()
            ->orderByDesc('academic_year')
            ->orderByDesc('period_label')
            ->get()
            ->map(function (PerformanceEvaluation $e) {
                return [
                    'id'               => $e->id,
                    'academic_year'    => $e->academic_year,
                    'period_type'      => $e->period_type,
                    'period_label'     => $e->period_label,
                    'period_display'   => $this->formatPeriodDisplay($e->period_type, (string) $e->period_label, (string) $e->academic_year),
                    'category'         => $e->category,
                    'rubric_version'   => $e->rubric_version,
                    'indicator_scores' => $e->indicator_scores ?? [],
                    'aspect_scores'    => $e->aspect_scores ?? [],
                    'final_score'      => (float) $e->final_score,
                    'predicate'        => $e->predicate,
                    'notes'            => $e->notes,
                    'evaluator_name'   => $e->evaluator?->name ?? 'Pimpinan',
                    'approver_name'    => $e->approver?->name ?? 'Kepala Sekolah',
                    'approved_at'      => ($e->approved_at ?? $e->published_at)?->locale('id')->isoFormat('D MMMM Y'),
                    'published_at'     => $e->published_at?->locale('id')->isoFormat('D MMMM Y'),
                ];
            })
            ->values();

        return Inertia::render('Employee/Performance/Index', [
            'evaluations' => $evaluations,
            'rubric'      => $this->rubricService->getRubric(),
            'userCategory'=> $user->evaluationCategory(),
        ]);
    }

    /**
     * Perbarui komponen indikator rubrik (tambah, edit, hapus) oleh Admin / KS.
     */
    public function updateRubric(Request $request)
    {
        $this->ensureFeatureEnabled();
        Gate::authorize('viewAny', PerformanceEvaluation::class);

        $validated = $request->validate([
            'categories'                               => ['required', 'array'],
            'categories.*'                             => ['required', 'array'],
            'categories.*.*'                           => ['required', 'array', 'min:1'],
            'categories.*.*.*.code'                    => ['nullable', 'string', 'max:64'],
            'categories.*.*.*.label'                   => ['required', 'string', 'max:120'],
            'categories.*.*.*.description'             => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->rubricService->saveIndicators($validated['categories']);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['rubric' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', 'Komponen indikator KPI berhasil diperbarui.');
    }

    /**
     * Kembalikan rubrik indikator ke konfigurasi default bawaan.
     */
    public function resetRubric()
    {
        $this->ensureFeatureEnabled();
        Gate::authorize('viewAny', PerformanceEvaluation::class);

        $this->rubricService->resetToDefault();

        return redirect()->back()->with('success', 'Komponen indikator KPI dikembalikan ke pengaturan default.');
    }

    private function resolveFilters(Request $request): array
    {
        $academicYear = (string) $request->query('academic_year', $this->academicYearService->getActiveYear());
        $periodType = (string) $request->query('period_type', 'bulanan');
        if (!in_array($periodType, ['bulanan', 'semester_1', 'semester_2'], true)) {
            $periodType = 'bulanan';
        }

        $defaultLabel = $periodType === 'bulanan'
            ? Carbon::now()->format('Y-m')
            : $periodType;
        $periodLabel = (string) $request->query('period_label', $defaultLabel);
        if ($periodType !== 'bulanan') {
            $periodLabel = $periodType;
        }

        return [
            'academic_year' => $academicYear,
            'period_type'   => $periodType,
            'period_label'  => $periodLabel,
            'division'      => (string) $request->query('division', 'all'),
            'category'      => (string) $request->query('category', 'all'),
            'status'        => (string) $request->query('status', 'all'),
            'search'        => trim((string) $request->query('search', '')),
        ];
    }

    private function buildFilteredRows(array $filters, bool $onlyPublished = false): Collection
    {
        $query = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['user', 'kurikulum', 'tata_usaha', 'koperasi']))
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'kepala_sekolah'))
            ->with('roles:id,name')
            ->withExists(['teacherJournals', 'teacherAdministrations'])
            ->with(['performanceEvaluations' => function ($q) use ($filters, $onlyPublished) {
                $q->where('academic_year', $filters['academic_year'])
                    ->where('period_type', $filters['period_type'])
                    ->where('period_label', $filters['period_label'])
                    ->when($onlyPublished, fn ($sub) => $sub->where('status', PerformanceEvaluation::STATUS_PUBLISHED))
                    ->with(['evaluator:id,name', 'approver:id,name']);
            }])
            ->orderBy('name');

        if ($filters['division'] !== 'all' && $filters['division'] !== '') {
            $query->where('divisi', $filters['division']);
        }

        if ($filters['search'] !== '') {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('nip', 'like', "%{$s}%");
            });
        }

        $rows = $query->get()->map(function (User $u) {
            $autoCategory = $this->resolveCategoryFromLoadedUser($u);
            $eval = $u->performanceEvaluations->first();

            return [
                'user_id'       => $u->id,
                'name'          => $u->name,
                'nip'           => $u->nip,
                'divisi'        => $u->divisi,
                'auto_category' => $autoCategory,
                'category'      => $eval?->category ?? $autoCategory,
                'evaluation'    => $eval ? [
                    'id'               => $eval->id,
                    'category'         => $eval->category,
                    'rubric_version'   => $eval->rubric_version,
                    'indicator_scores' => $eval->indicator_scores ?? [],
                    'aspect_scores'    => $eval->aspect_scores ?? [],
                    'final_score'      => (float) $eval->final_score,
                    'predicate'        => $eval->predicate,
                    'notes'            => $eval->notes,
                    'revision_note'    => $eval->revision_note,
                    'status'           => $eval->status,
                    'evaluator_name'   => $eval->evaluator?->name,
                    'approver_name'    => $eval->approver?->name,
                    'submitted_at'     => $eval->submitted_at?->timezone('Asia/Jakarta')->format('d M Y H:i'),
                    'approved_at'      => $eval->approved_at?->timezone('Asia/Jakarta')->format('d M Y H:i'),
                ] : null,
            ];
        });

        if (in_array($filters['category'], ['guru', 'karyawan'], true)) {
            $rows = $rows->filter(fn ($r) => $r['category'] === $filters['category']);
        }

        if ($filters['status'] === 'belum') {
            $rows = $rows->filter(fn ($r) => $r['evaluation'] === null);
        } elseif (in_array($filters['status'], ['draft', 'submitted', 'revision_requested', 'published'], true)) {
            $rows = $rows->filter(fn ($r) => ($r['evaluation']['status'] ?? null) === $filters['status']);
        } elseif ($filters['status'] === 'sudah') {
            $rows = $rows->filter(fn ($r) => $r['evaluation'] !== null);
        }

        return $rows->values();
    }

    private function resolveCategoryFromLoadedUser(User $u): string
    {
        if (in_array($u->evaluation_category, ['guru', 'karyawan'], true)) {
            return $u->evaluation_category;
        }

        $divisi = strtolower(trim((string) $u->divisi));
        foreach (['guru', 'wali kelas', 'pengajar', 'tahfidz', 'kurikulum', 'mapel', 'kelas'] as $kw) {
            if ($divisi !== '' && str_contains($divisi, $kw)) {
                return 'guru';
            }
        }

        foreach (['tata usaha', 'tu', 'koperasi', 'keuangan', 'sarpras', 'kebersihan', 'keamanan', 'satpam', 'karyawan', 'tendik', 'staf', 'staff', 'it', 'humas', 'perpustakaan'] as $kw) {
            if ($divisi !== '' && str_contains($divisi, $kw)) {
                return 'karyawan';
            }
        }

        $roleNames = $u->roles->pluck('name')->all();
        if (in_array('kurikulum', $roleNames, true)) {
            return 'guru';
        }
        if (array_intersect(['tata_usaha', 'koperasi'], $roleNames)) {
            return 'karyawan';
        }

        if (!empty($u->teacher_journals_exists) || !empty($u->teacher_administrations_exists)) {
            return 'guru';
        }

        return 'karyawan';
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function extractMonthYear(string $periodType, string $periodLabel, string $academicYear): array
    {
        if ($periodType === 'bulanan' && preg_match('/^(\d{4})-(\d{2})$/', $periodLabel, $m)) {
            return [(int) $m[2], (int) $m[1]];
        }

        $parts = explode('/', $academicYear);
        $year = $periodType === 'semester_2'
            ? (int) ($parts[1] ?? date('Y'))
            : (int) ($parts[0] ?? date('Y'));

        return [null, $year];
    }

    private function formatPeriodDisplay(string $periodType, string $periodLabel, string $academicYear): string
    {
        if ($periodType === 'semester_1') {
            return "Semester 1 ({$academicYear})";
        }
        if ($periodType === 'semester_2') {
            return "Semester 2 ({$academicYear})";
        }
        if (preg_match('/^(\d{4})-(\d{2})$/', $periodLabel, $m)) {
            return Carbon::createFromDate((int) $m[1], (int) $m[2], 1)->locale('id')->isoFormat('MMMM Y');
        }
        return $periodLabel;
    }
}
