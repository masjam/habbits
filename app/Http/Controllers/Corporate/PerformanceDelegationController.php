<?php

namespace App\Http\Controllers\Corporate;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkAssignmentRequest;
use App\Http\Requests\ReopenEvaluationRequest;
use App\Http\Requests\RequestRevisionRequest;
use App\Http\Requests\StoreAssignmentRequest;
use App\Models\Division;
use App\Models\PerformanceAssignment;
use App\Models\PerformanceEvaluation;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PerformanceEvaluationNotification;
use App\Services\AcademicYearService;
use App\Services\PerformanceEvaluationWorkflow;
use App\Services\PerformanceInsightService;
use App\Services\PerformanceRubricService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use InvalidArgumentException;

class PerformanceDelegationController extends Controller
{
    public function __construct(
        private readonly AcademicYearService $academicYearService,
        private readonly PerformanceEvaluationWorkflow $workflow,
        private readonly PerformanceInsightService $insightService,
        private readonly PerformanceRubricService $rubricService
    ) {}

    private function ensureFeatureEnabled(): void
    {
        $val = Setting::get('feature_kpi', '1');
        if ($val === '0' || $val === 'false' || $val === false) {
            abort(403, 'Fitur Penilaian Kinerja (KPI) sedang dinonaktifkan.');
        }
    }

    /**
     * Halaman Kepala Sekolah: Kelola Delegasi Penilai KPI.
     */
    public function assignmentsIndex(Request $request)
    {
        $this->ensureFeatureEnabled();
        Gate::authorize('viewAny', PerformanceAssignment::class);

        $filters = $this->resolvePeriodFilters($request);

        $query = PerformanceAssignment::query()
            ->with(['assessor:id,name,divisi', 'employee:id,name,nip,divisi,evaluation_category', 'assignedBy:id,name'])
            ->forPeriod($filters['academic_year'], $filters['period_type'], $filters['period_label'])
            ->orderByDesc('created_at');

        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        if ($filters['assessor_id'] !== '') {
            $query->where('assessor_id', (int) $filters['assessor_id']);
        }
        if ($filters['division'] !== 'all' && $filters['division'] !== '') {
            $query->whereHas('employee', fn ($q) => $q->where('divisi', $filters['division']));
        }
        if ($filters['search'] !== '') {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->whereHas('employee', fn ($e) => $e->where('name', 'like', "%{$s}%")->orWhere('nip', 'like', "%{$s}%"))
                    ->orWhereHas('assessor', fn ($a) => $a->where('name', 'like', "%{$s}%"));
            });
        }

        $allAssignments = $query->get();

        $empIds = $allAssignments->pluck('employee_id')->unique()->values();
        $evaluationsByEmp = PerformanceEvaluation::query()
            ->whereIn('user_id', $empIds->isEmpty() ? [0] : $empIds)
            ->forPeriod($filters['academic_year'], $filters['period_type'], $filters['period_label'])
            ->with(['logs.actor:id,name'])
            ->get()
            ->keyBy('user_id');

        $mappedAll = $allAssignments->map(function (PerformanceAssignment $a) use ($evaluationsByEmp) {
            /** @var PerformanceEvaluation|null $eval */
            $eval = $evaluationsByEmp->get($a->employee_id);
            $empCategory = $eval?->category ?? ($a->employee?->evaluationCategory() ?? 'guru');

            return [
                'id'           => $a->id,
                'assessor_id'  => $a->assessor_id,
                'assessor'     => $a->assessor ? ['id' => $a->assessor->id, 'name' => $a->assessor->name, 'divisi' => $a->assessor->divisi] : null,
                'employee_id'  => $a->employee_id,
                'employee'     => $a->employee ? [
                    'id'       => $a->employee->id,
                    'name'     => $a->employee->name,
                    'nip'      => $a->employee->nip,
                    'divisi'   => $a->employee->divisi,
                    'category' => $empCategory,
                ] : null,
                'category'     => $empCategory,
                'assigned_by'  => $a->assignedBy?->name,
                'status'       => $a->status,
                'note'         => $a->note,
                'created_at'   => $a->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i'),
                'revoked_at'   => $a->revoked_at?->timezone('Asia/Jakarta')->format('d M Y H:i'),
                'evaluation'   => $eval ? [
                    'id'               => $eval->id,
                    'category'         => $eval->category,
                    'indicator_scores' => $eval->indicator_scores ?? [],
                    'aspect_scores'    => $eval->aspect_scores ?? [],
                    'final_score'      => (float) $eval->final_score,
                    'predicate'        => $eval->predicate,
                    'notes'            => $eval->notes,
                    'status'           => $eval->status,
                    'logs'             => $eval->logs->map(fn ($l) => [
                        'id'         => $l->id,
                        'action'     => $l->action,
                        'actor_name' => $l->actor?->name ?? 'Sistem',
                        'note'       => $l->note,
                        'created_at' => $l->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i'),
                    ])->values(),
                ] : null,
            ];
        });

        $categoryCounts = [
            'guru'     => $mappedAll->where('category', 'guru')->count(),
            'karyawan' => $mappedAll->where('category', 'karyawan')->count(),
        ];

        $filtered = $mappedAll->where('category', $filters['category'])->values();
        $perPage = 50;
        $page = max(1, (int) $request->query('page', 1));
        $assignments = new \Illuminate\Pagination\LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Pegawai aktif untuk dropdown penilai & target dinilai (kecuali kepala_sekolah)
        $activeEmployees = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['user', 'kurikulum', 'tata_usaha', 'koperasi']))
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'kepala_sekolah'))
            ->orderBy('name')
            ->get(['id', 'name', 'nip', 'divisi', 'evaluation_category'])
            ->map(fn (User $u) => [
                'id'       => $u->id,
                'name'     => $u->name,
                'nip'      => $u->nip,
                'divisi'   => $u->divisi,
                'category' => $u->evaluationCategory(),
            ])
            ->values();

        $activeAssignedEmployeeIds = PerformanceAssignment::active()
            ->forPeriod($filters['academic_year'], $filters['period_type'], $filters['period_label'])
            ->pluck('employee_id')
            ->all();

        return Inertia::render('Admin/Performance/Delegations', [
            'assignments'               => $assignments,
            'filters'                   => $filters,
            'categoryCounts'            => $categoryCounts,
            'academicYears'             => $this->academicYearService->getAcademicYears(),
            'divisions'                 => Division::orderBy('name')->pluck('name')->values()->all(),
            'employees'                 => $activeEmployees,
            'activeAssignedEmployeeIds' => $activeAssignedEmployeeIds,
            'rubric'                    => $this->rubricService->getRubric(),
        ]);
    }

    public function storeAssignment(StoreAssignmentRequest $request)
    {
        $this->ensureFeatureEnabled();
        Gate::authorize('create', PerformanceAssignment::class);

        $assignment = DB::transaction(function () use ($request) {
            $exists = PerformanceAssignment::active()
                ->where('employee_id', $request->integer('employee_id'))
                ->forPeriod(
                    $request->string('academic_year')->toString(),
                    $request->string('period_type')->toString(),
                    $request->string('period_label')->toString()
                )
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                throw new InvalidArgumentException('Pegawai ini sudah memiliki penilai delegasi aktif pada periode tersebut.');
            }

            return PerformanceAssignment::updateOrCreate(
                [
                    'academic_year' => $request->string('academic_year')->toString(),
                    'period_type'   => $request->string('period_type')->toString(),
                    'period_label'  => $request->string('period_label')->toString(),
                    'assessor_id'   => $request->integer('assessor_id'),
                    'employee_id'   => $request->integer('employee_id'),
                ],
                [
                    'assigned_by' => Auth::id(),
                    'status'      => PerformanceAssignment::STATUS_ACTIVE,
                    'revoked_by'  => null,
                    'revoked_at'  => null,
                    'note'        => $request->input('note'),
                ]
            );
        });

        $assessor = User::find($assignment->assessor_id);
        $employee = User::find($assignment->employee_id);
        if ($assessor && $employee) {
            $assessor->notify(new PerformanceEvaluationNotification(
                'assignment_created',
                'Tugas Penilaian KPI Baru',
                "Anda ditugaskan untuk menilai kinerja {$employee->name}.",
                route('performance.assigned.index'),
                ['assignment_id' => $assignment->id]
            ));
        }

        return redirect()->back()->with('success', 'Delegasi penilai KPI berhasil disimpan.');
    }

    public function bulkStoreAssignment(BulkAssignmentRequest $request)
    {
        $this->ensureFeatureEnabled();
        Gate::authorize('create', PerformanceAssignment::class);

        $academicYear = $request->string('academic_year')->toString();
        $periodType = $request->string('period_type')->toString();
        $periodLabel = $request->string('period_label')->toString();
        $assessorId = $request->integer('assessor_id');
        $employeeIds = collect($request->input('employee_ids', []))->map(fn ($id) => (int) $id)->unique()->values();
        $note = $request->input('note');

        $createdCount = DB::transaction(function () use ($academicYear, $periodType, $periodLabel, $assessorId, $employeeIds, $note) {
            $alreadyAssigned = PerformanceAssignment::active()
                ->forPeriod($academicYear, $periodType, $periodLabel)
                ->whereIn('employee_id', $employeeIds)
                ->lockForUpdate()
                ->pluck('employee_id')
                ->all();

            $validTargets = User::whereIn('id', $employeeIds)
                ->where('id', '!=', $assessorId)
                ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'kepala_sekolah'))
                ->pluck('id')
                ->diff($alreadyAssigned);

            foreach ($validTargets as $empId) {
                PerformanceAssignment::updateOrCreate(
                    [
                        'academic_year' => $academicYear,
                        'period_type'   => $periodType,
                        'period_label'  => $periodLabel,
                        'assessor_id'   => $assessorId,
                        'employee_id'   => $empId,
                    ],
                    [
                        'assigned_by' => Auth::id(),
                        'status'      => PerformanceAssignment::STATUS_ACTIVE,
                        'revoked_by'  => null,
                        'revoked_at'  => null,
                        'note'        => $note,
                    ]
                );
            }

            return $validTargets->count();
        });

        if ($createdCount > 0) {
            $assessor = User::find($assessorId);
            $assessor?->notify(new PerformanceEvaluationNotification(
                'assignment_created',
                'Tugas Penilaian KPI Baru',
                "Anda menerima {$createdCount} penugasan penilaian KPI untuk periode {$periodLabel}.",
                route('performance.assigned.index')
            ));
        }

        return redirect()->back()->with('success', "{$createdCount} penugasan penilai KPI berhasil dibuat.");
    }

    public function revokeAssignment(PerformanceAssignment $assignment)
    {
        $this->ensureFeatureEnabled();
        Gate::authorize('revoke', $assignment);

        $this->workflow->revokeAssignment($assignment, Auth::user());

        return redirect()->back()->with('success', 'Delegasi penilai berhasil dicabut.');
    }

    /**
     * Halaman Kepala Sekolah: Antrean Persetujuan KPI (status = submitted).
     */
    public function approvalsIndex(Request $request)
    {
        $this->ensureFeatureEnabled();
        Gate::authorize('viewAny', PerformanceAssignment::class);

        $queue = PerformanceEvaluation::query()
            ->submitted()
            ->with([
                'user:id,name,nip,divisi,evaluation_category',
                'evaluator:id,name',
                'submitter:id,name',
                'logs.actor:id,name',
            ])
            ->orderBy('submitted_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PerformanceEvaluation $e) => [
                'id'               => $e->id,
                'user_id'          => $e->user_id,
                'employee_name'    => $e->user?->name ?? 'Pegawai',
                'employee_nip'     => $e->user?->nip,
                'employee_divisi'  => $e->user?->divisi,
                'assessor_name'    => $e->submitter?->name ?? $e->evaluator?->name ?? '-',
                'academic_year'    => $e->academic_year,
                'period_type'      => $e->period_type,
                'period_label'     => $e->period_label,
                'category'         => $e->category,
                'indicator_scores' => $e->indicator_scores ?? [],
                'aspect_scores'    => $e->aspect_scores ?? [],
                'final_score'      => (float) $e->final_score,
                'predicate'        => $e->predicate,
                'notes'            => $e->notes,
                'status'           => $e->status,
                'submitted_at'     => $e->submitted_at?->timezone('Asia/Jakarta')->format('d M Y H:i'),
                'logs'             => $e->logs->map(fn ($l) => [
                    'id'          => $l->id,
                    'action'      => $l->action,
                    'actor_name'  => $l->actor?->name ?? 'Sistem',
                    'from_status' => $l->from_status,
                    'to_status'   => $l->to_status,
                    'note'        => $l->note,
                    'created_at'  => $l->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i'),
                ])->values(),
            ]);

        return Inertia::render('Admin/Performance/Approvals', [
            'queue'  => $queue,
            'rubric' => $this->rubricService->getRubric(),
        ]);
    }

    public function approve(PerformanceEvaluation $evaluation)
    {
        $this->ensureFeatureEnabled();
        Gate::authorize('approve', $evaluation);

        $approved = $this->workflow->approve($evaluation, Auth::user());
        $this->notifyOnApproval($approved, false);

        return redirect()->back()->with('success', 'Evaluasi KPI berhasil disetujui dan dipublikasikan.');
    }

    public function bulkApprove(Request $request)
    {
        $this->ensureFeatureEnabled();
        Gate::authorize('viewAny', PerformanceAssignment::class);

        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:performance_evaluations,id'],
        ]);

        $count = 0;
        $approver = Auth::user();
        $evaluations = PerformanceEvaluation::whereIn('id', $validated['ids'])->submitted()->get();

        foreach ($evaluations as $eval) {
            if (Gate::denies('approve', $eval)) {
                continue;
            }
            $approved = $this->workflow->approve($eval, $approver);
            $this->notifyOnApproval($approved, false);
            $count++;
        }

        return redirect()->back()->with('success', "{$count} evaluasi KPI berhasil disetujui.");
    }

    public function updateAndApprove(Request $request, PerformanceEvaluation $evaluation)
    {
        $this->ensureFeatureEnabled();
        Gate::authorize('approve', $evaluation);

        $validated = $request->validate([
            'category'         => ['required', 'in:guru,karyawan'],
            'indicator_scores' => ['required', 'array'],
            'notes'            => ['nullable', 'string', 'max:2000'],
            'approval_note'    => ['nullable', 'string', 'max:1000'],
        ]);

        $approved = $this->workflow->approve($evaluation, Auth::user(), $validated, $validated['approval_note'] ?? null);
        $this->notifyOnApproval($approved, true);

        return redirect()->back()->with('success', 'Evaluasi KPI diperbarui dan berhasil disetujui.');
    }

    public function requestRevision(RequestRevisionRequest $request, PerformanceEvaluation $evaluation)
    {
        $this->ensureFeatureEnabled();
        Gate::authorize('approve', $evaluation);

        $updated = $this->workflow->requestRevision($evaluation, Auth::user(), $request->string('note')->toString());

        if ($updated->submitted_by) {
            User::find($updated->submitted_by)?->notify(new PerformanceEvaluationNotification(
                'revision_requested',
                'Revisi Penilaian KPI Diminta',
                "Kepala Sekolah meminta revisi penilaian untuk {$updated->user?->name}: {$updated->revision_note}",
                route('performance.assigned.index'),
                ['evaluation_id' => $updated->id]
            ));
        }

        return redirect()->back()->with('success', 'Permintaan revisi berhasil dikirim ke penilai.');
    }

    public function reopen(ReopenEvaluationRequest $request, PerformanceEvaluation $evaluation)
    {
        $this->ensureFeatureEnabled();
        Gate::authorize('reopen', $evaluation);

        $targetStatus = $request->input('target_status', PerformanceEvaluation::STATUS_DRAFT);
        $this->workflow->reopen($evaluation, Auth::user(), $request->string('note')->toString(), $targetStatus);

        return redirect()->back()->with('success', 'Evaluasi KPI dibuka kembali dan disembunyikan dari pegawai.');
    }

    private function notifyOnApproval(PerformanceEvaluation $evaluation, bool $edited): void
    {
        $employee = $evaluation->user;
        if ($employee) {
            $employee->notify(new PerformanceEvaluationNotification(
                'evaluation_approved',
                'Hasil Evaluasi Kinerja Tersedia',
                "Hasil evaluasi KPI Anda periode {$evaluation->period_label} telah disetujui Kepala Sekolah.",
                route('performance.index'),
                ['evaluation_id' => $evaluation->id]
            ));
        }

        if ($evaluation->submitted_by && (int) $evaluation->submitted_by !== (int) Auth::id()) {
            User::find($evaluation->submitted_by)?->notify(new PerformanceEvaluationNotification(
                $edited ? 'evaluation_edited_by_approver' : 'evaluation_approved',
                $edited ? 'Penilaian KPI Disesuaikan & Disetujui' : 'Penilaian KPI Disetujui',
                $edited
                    ? "Penilaian Anda untuk {$employee?->name} telah disesuaikan dan disetujui Kepala Sekolah."
                    : "Penilaian Anda untuk {$employee?->name} telah disetujui Kepala Sekolah.",
                route('performance.assigned.index'),
                ['evaluation_id' => $evaluation->id]
            ));
        }
    }

    private function resolvePeriodFilters(Request $request): array
    {
        $academicYear = (string) $request->query('academic_year', $this->academicYearService->getActiveYear());
        $periodType = (string) $request->query('period_type', 'bulanan');
        if (!in_array($periodType, ['bulanan', 'semester_1', 'semester_2'], true)) {
            $periodType = 'bulanan';
        }

        $defaultLabel = $periodType === 'bulanan' ? Carbon::now()->format('Y-m') : $periodType;
        $periodLabel = $periodType !== 'bulanan'
            ? $periodType
            : (string) $request->query('period_label', $defaultLabel);

        $category = (string) $request->query('category', 'guru');
        if (!in_array($category, ['guru', 'karyawan'], true)) {
            $category = 'guru';
        }

        return [
            'academic_year' => $academicYear,
            'period_type'   => $periodType,
            'period_label'  => $periodLabel,
            'category'      => $category,
            'status'        => (string) $request->query('status', 'active'),
            'assessor_id'   => (string) $request->query('assessor_id', ''),
            'division'      => (string) $request->query('division', 'all'),
            'search'        => trim((string) $request->query('search', '')),
        ];
    }
}
