<?php

namespace Tests\Unit;

use App\Models\PerformanceAssignment;
use App\Models\PerformanceEvaluation;
use App\Models\PerformanceEvaluationLog;
use App\Models\User;
use App\Services\PerformanceEvaluationWorkflow;
use App\Services\PerformanceRubricService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PerformanceEvaluationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private PerformanceEvaluationWorkflow $workflow;
    private User $ks;
    private User $assessor;
    private User $employee;
    private array $fullScores;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user', 'kepala_sekolah', 'admin', 'superadmin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->workflow = app(PerformanceEvaluationWorkflow::class);
        $this->ks = User::factory()->create();
        $this->ks->assignRole('kepala_sekolah');

        $this->assessor = User::factory()->create();
        $this->assessor->assignRole('user');

        $this->employee = User::factory()->create(['evaluation_category' => 'guru']);
        $this->employee->assignRole('user');

        $rubric = app(PerformanceRubricService::class)->getRubric()['categories']['guru']['aspects'];
        $this->fullScores = [];
        foreach ($rubric as $aspectKey => $aspect) {
            foreach ($aspect['indicators'] as $ind) {
                $this->fullScores[$aspectKey][$ind['code']] = 4;
            }
        }
    }

    public function test_full_delegation_lifecycle_and_audit_logs(): void
    {
        $assignment = PerformanceAssignment::create([
            'academic_year' => '2026/2027',
            'period_type'   => 'bulanan',
            'period_label'  => '2026-11',
            'assessor_id'   => $this->assessor->id,
            'employee_id'   => $this->employee->id,
            'assigned_by'   => $this->ks->id,
            'status'        => PerformanceAssignment::STATUS_ACTIVE,
        ]);

        // 1. Draft
        $eval = $this->workflow->saveDraft($this->assessor, $this->employee, [
            'academic_year'    => '2026/2027',
            'period_type'      => 'bulanan',
            'period_label'     => '2026-11',
            'category'         => 'guru',
            'indicator_scores' => array_slice($this->fullScores, 0, 2, true),
            'notes'            => 'Draft awal',
        ], $assignment);

        $this->assertSame(PerformanceEvaluation::STATUS_DRAFT, $eval->status);

        // 2. Submit
        $eval = $this->workflow->submit($eval, $this->assessor, [
            'indicator_scores' => $this->fullScores,
            'notes'            => 'Siap ditinjau',
        ]);
        $this->assertSame(PerformanceEvaluation::STATUS_SUBMITTED, $eval->status);

        // Cannot save draft once submitted
        $this->expectException(InvalidArgumentException::class);
        try {
            $this->workflow->saveDraft($this->assessor, $this->employee, [
                'academic_year'    => '2026/2027',
                'period_type'      => 'bulanan',
                'period_label'     => '2026-11',
                'category'         => 'guru',
                'indicator_scores' => $this->fullScores,
            ], $assignment);
        } finally {
            // 3. Request revision
            $eval = $this->workflow->requestRevision($eval, $this->ks, 'Mohon lengkapi catatan');
            $this->assertSame(PerformanceEvaluation::STATUS_REVISION_REQUESTED, $eval->status);
            $this->assertSame('Mohon lengkapi catatan', $eval->revision_note);

            // 4. Resubmit
            $eval = $this->workflow->submit($eval, $this->assessor, [
                'indicator_scores' => $this->fullScores,
                'notes'            => 'Sudah dilengkapi',
            ]);
            $this->assertSame(PerformanceEvaluation::STATUS_SUBMITTED, $eval->status);

            // 5. Edit & Approve
            $modified = $this->fullScores;
            $firstAspect = array_key_first($modified);
            $firstInd = array_key_first($modified[$firstAspect]);
            $modified[$firstAspect][$firstInd] = 5;

            $eval = $this->workflow->approve($eval, $this->ks, [
                'indicator_scores' => $modified,
                'notes'            => 'Disetujui dengan penyesuaian',
            ]);
            $this->assertSame(PerformanceEvaluation::STATUS_PUBLISHED, $eval->status);
            $this->assertSame($this->ks->id, $eval->approved_by);

            // 6. Reopen
            $eval = $this->workflow->reopen($eval, $this->ks, 'Perlu koreksi ulang', PerformanceEvaluation::STATUS_DRAFT);
            $this->assertSame(PerformanceEvaluation::STATUS_DRAFT, $eval->status);
            $this->assertNull($eval->published_at);

            $actions = PerformanceEvaluationLog::where('performance_evaluation_id', $eval->id)
                ->orderBy('id')
                ->pluck('action')
                ->all();

            $this->assertSame([
                'created',
                'submitted',
                'revision_requested',
                'submitted',
                'edited_by_approver',
                'approved',
                'reopened',
            ], $actions);
        }
    }

    public function test_double_approve_throws_exception_and_logs_are_append_only(): void
    {
        $eval = $this->workflow->saveDraft($this->assessor, $this->employee, [
            'academic_year'    => '2026/2027',
            'period_type'      => 'bulanan',
            'period_label'     => '2026-11',
            'category'         => 'guru',
            'indicator_scores' => $this->fullScores,
        ]);
        $eval = $this->workflow->submit($eval, $this->assessor);
        $eval = $this->workflow->approve($eval, $this->ks);

        $log = PerformanceEvaluationLog::where('performance_evaluation_id', $eval->id)->firstOrFail();

        $this->expectException(LogicException::class);
        try {
            $log->update(['note' => 'tamper']);
        } finally {
            $this->expectException(InvalidArgumentException::class);
            $this->workflow->approve($eval, $this->ks);
        }
    }
}
