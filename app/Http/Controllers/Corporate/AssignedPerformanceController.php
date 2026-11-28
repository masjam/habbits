<?php

namespace App\Http\Controllers\Corporate;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveAssignedEvaluationRequest;
use App\Models\PerformanceAssignment;
use App\Models\PerformanceEvaluation;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PerformanceEvaluationNotification;
use App\Services\PerformanceEvaluationWorkflow;
use App\Services\PerformanceInsightService;
use App\Services\PerformanceRubricService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AssignedPerformanceController extends Controller
{
    public function __construct(
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
     * Halaman Tugas Penilaian Saya (khusus penilai delegasi aktif).
     */
    public function index(Request $request)
    {
        $this->ensureFeatureEnabled();
        $user = Auth::user();

        $assignments = PerformanceAssignment::active()
            ->where('assessor_id', $user->id)
            ->with(['employee:id,name,nip,divisi,evaluation_category', 'assignedBy:id,name'])
            ->orderByDesc('academic_year')
            ->orderByDesc('period_label')
            ->get();

        // Ambil evaluasi terkait dalam 1 query (hindari N+1)
        $employeeIds = $assignments->pluck('employee_id')->unique()->values();
        $evaluations = PerformanceEvaluation::query()
            ->whereIn('user_id', $employeeIds->isEmpty() ? [0] : $employeeIds)
            ->with(['approver:id,name', 'logs.actor:id,name'])
            ->get()
            ->keyBy(fn (PerformanceEvaluation $e) => "{$e->user_id}|{$e->academic_year}|{$e->period_type}|{$e->period_label}");

        $tasks = $assignments->map(function (PerformanceAssignment $a) use ($evaluations) {
            $key = "{$a->employee_id}|{$a->academic_year}|{$a->period_type}|{$a->period_label}";
            /** @var PerformanceEvaluation|null $eval */
            $eval = $evaluations->get($key);

            return [
                'assignment_id' => $a->id,
                'academic_year' => $a->academic_year,
                'period_type'   => $a->period_type,
                'period_label'  => $a->period_label,
                'note'          => $a->note,
                'assigned_by'   => $a->assignedBy?->name ?? 'Kepala Sekolah',
                'employee'      => [
                    'id'       => $a->employee->id,
                    'name'     => $a->employee->name,
                    'nip'      => $a->employee->nip,
                    'divisi'   => $a->employee->divisi,
                    'category' => $eval?->category ?? $a->employee->evaluationCategory(),
                ],
                'evaluation'    => $eval ? [
                    'id'               => $eval->id,
                    'category'         => $eval->category,
                    'indicator_scores' => $eval->indicator_scores ?? [],
                    'aspect_scores'    => $eval->aspect_scores ?? [],
                    'final_score'      => (float) $eval->final_score,
                    'predicate'        => $eval->predicate,
                    'notes'            => $eval->notes,
                    'revision_note'    => $eval->revision_note,
                    'status'           => $eval->status,
                    'submitted_at'     => $eval->submitted_at?->timezone('Asia/Jakarta')->format('d M Y H:i'),
                    'approver_name'    => $eval->approver?->name,
                    'approved_at'      => $eval->approved_at?->timezone('Asia/Jakarta')->format('d M Y H:i'),
                ] : null,
            ];
        })->values();

        return Inertia::render('Corporate/AssignedPerformance/Index', [
            'tasks'  => $tasks,
            'rubric' => $this->rubricService->getRubric(),
        ]);
    }

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

    public function saveDraft(SaveAssignedEvaluationRequest $request)
    {
        $this->ensureFeatureEnabled();
        $user = Auth::user();
        $target = User::findOrFail($request->integer('user_id'));

        Gate::authorize('evaluateAssigned', [
            PerformanceEvaluation::class,
            $target,
            $request->string('academic_year')->toString(),
            $request->string('period_type')->toString(),
            $request->string('period_label')->toString(),
        ]);

        $assignment = PerformanceAssignment::active()
            ->where('assessor_id', $user->id)
            ->where('employee_id', $target->id)
            ->forPeriod(
                $request->string('academic_year')->toString(),
                $request->string('period_type')->toString(),
                $request->string('period_label')->toString()
            )
            ->firstOrFail();

        $this->workflow->saveDraft($user, $target, $request->validated(), $assignment);

        return redirect()->back()->with('success', "Draft penilaian {$target->name} berhasil disimpan.");
    }

    public function submit(SaveAssignedEvaluationRequest $request)
    {
        $this->ensureFeatureEnabled();
        $user = Auth::user();
        $target = User::findOrFail($request->integer('user_id'));

        Gate::authorize('evaluateAssigned', [
            PerformanceEvaluation::class,
            $target,
            $request->string('academic_year')->toString(),
            $request->string('period_type')->toString(),
            $request->string('period_label')->toString(),
        ]);

        $assignment = PerformanceAssignment::active()
            ->where('assessor_id', $user->id)
            ->where('employee_id', $target->id)
            ->forPeriod(
                $request->string('academic_year')->toString(),
                $request->string('period_type')->toString(),
                $request->string('period_label')->toString()
            )
            ->firstOrFail();

        $draft = $this->workflow->saveDraft($user, $target, $request->validated(), $assignment);
        $submitted = $this->workflow->submit($draft, $user, $request->validated());

        // Notifikasi ke Kepala Sekolah (atau superadmin fallback bila KS kosong)
        $approvers = User::role('kepala_sekolah')->get();
        if ($approvers->isEmpty() && config('performance_indicators.allow_superadmin_fallback', true)) {
            $approvers = User::role('superadmin')->get();
        }

        foreach ($approvers as $approver) {
            $approver->notify(new PerformanceEvaluationNotification(
                'evaluation_submitted',
                'Pengajuan Penilaian KPI Baru',
                "{$user->name} mengajukan penilaian KPI untuk {$target->name}.",
                route('admin.performance.approvals.index'),
                ['evaluation_id' => $submitted->id]
            ));
        }

        return redirect()->back()->with('success', "Penilaian {$target->name} berhasil diajukan ke Kepala Sekolah.");
    }
}
