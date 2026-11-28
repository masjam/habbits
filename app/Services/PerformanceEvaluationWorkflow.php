<?php

namespace App\Services;

use App\Models\PerformanceAssignment;
use App\Models\PerformanceEvaluation;
use App\Models\PerformanceEvaluationLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PerformanceEvaluationWorkflow
{
    public function __construct(
        private readonly PerformanceScoreCalculator $calculator
    ) {}

    /**
     * Simpan atau perbarui draft penilaian (oleh penilai delegasi atau Kepala Sekolah).
     */
    public function saveDraft(
        User $actor,
        User $employee,
        array $payload,
        ?PerformanceAssignment $assignment = null
    ): PerformanceEvaluation {
        return DB::transaction(function () use ($actor, $employee, $payload, $assignment) {
            $academicYear = (string) $payload['academic_year'];
            $periodType = (string) $payload['period_type'];
            $periodLabel = (string) $payload['period_label'];
            $category = (string) ($payload['category'] ?? $employee->evaluationCategory());
            $indicatorScores = (array) ($payload['indicator_scores'] ?? []);
            $notes = $payload['notes'] ?? null;

            $existing = PerformanceEvaluation::withTrashed()
                ->where('user_id', $employee->id)
                ->where('academic_year', $academicYear)
                ->where('period_type', $periodType)
                ->where('period_label', $periodLabel)
                ->lockForUpdate()
                ->first();

            if ($existing && !$existing->trashed() && in_array($existing->status, [
                PerformanceEvaluation::STATUS_SUBMITTED,
                PerformanceEvaluation::STATUS_PUBLISHED,
            ], true)) {
                throw new InvalidArgumentException('Evaluasi yang sudah diajukan atau disetujui tidak dapat diubah sebagai draft.');
            }

            $calculated = $this->calculator->calculate($category, $indicatorScores, false);
            [$evalMonth, $evalYear] = $this->extractMonthYear($periodType, $periodLabel, $academicYear);
            $aspectValues = array_values($calculated['aspect_scores']);

            $fromStatus = ($existing && !$existing->trashed()) ? $existing->status : null;
            $nextStatus = $fromStatus === PerformanceEvaluation::STATUS_REVISION_REQUESTED
                ? PerformanceEvaluation::STATUS_REVISION_REQUESTED
                : PerformanceEvaluation::STATUS_DRAFT;

            $data = [
                'evaluator_id'       => $actor->id,
                'evaluation_month'   => $evalMonth,
                'evaluation_year'    => $evalYear,
                'category'           => $category,
                'rubric_version'     => $calculated['rubric_version'],
                'indicator_scores'   => $indicatorScores,
                'aspect_scores'      => $calculated['aspect_scores'],
                'score_pedagogic'    => $aspectValues[0] ?? 0,
                'score_professional' => $aspectValues[1] ?? 0,
                'score_personality'  => $aspectValues[2] ?? 0,
                'score_social'       => $aspectValues[3] ?? 0,
                'average_score'      => $calculated['final_score'],
                'final_score'        => $calculated['final_score'],
                'predicate'          => $calculated['predicate'],
                'notes'              => $notes,
                'assignment_id'      => $assignment?->id ?? $existing?->assignment_id,
                'status'             => $nextStatus,
                'published_at'       => null,
            ];

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->update($data);
                $evaluation = $existing->fresh();
                $action = 'scored';
            } else {
                $evaluation = PerformanceEvaluation::create(array_merge([
                    'user_id'       => $employee->id,
                    'academic_year' => $academicYear,
                    'period_type'   => $periodType,
                    'period_label'  => $periodLabel,
                ], $data));
                $action = 'created';
            }

            $this->recordLog($evaluation, $actor, $action, $fromStatus, $nextStatus, null, $this->snapshotOf($evaluation));

            return $evaluation;
        });
    }

    /**
     * Ajukan evaluasi ke antrean persetujuan Kepala Sekolah (draft/revision_requested -> submitted).
     */
    public function submit(PerformanceEvaluation $evaluation, User $actor, ?array $payload = null): PerformanceEvaluation
    {
        return DB::transaction(function () use ($evaluation, $actor, $payload) {
            $locked = PerformanceEvaluation::whereKey($evaluation->id)->lockForUpdate()->firstOrFail();

            if (!in_array($locked->status, [
                PerformanceEvaluation::STATUS_DRAFT,
                PerformanceEvaluation::STATUS_REVISION_REQUESTED,
            ], true)) {
                throw new InvalidArgumentException('Hanya evaluasi berstatus draft atau perlu revisi yang dapat diajukan.');
            }

            $category = (string) ($payload['category'] ?? $locked->category);
            $indicatorScores = (array) ($payload['indicator_scores'] ?? $locked->indicator_scores ?? []);
            $notes = array_key_exists('notes', $payload ?? []) ? $payload['notes'] : $locked->notes;

            $calculated = $this->calculator->calculate($category, $indicatorScores, true);
            $aspectValues = array_values($calculated['aspect_scores']);
            $fromStatus = $locked->status;

            $locked->update([
                'evaluator_id'       => $actor->id,
                'submitted_by'       => $actor->id,
                'submitted_at'       => now(),
                'category'           => $category,
                'rubric_version'     => $calculated['rubric_version'],
                'indicator_scores'   => $indicatorScores,
                'aspect_scores'      => $calculated['aspect_scores'],
                'score_pedagogic'    => $aspectValues[0] ?? 0,
                'score_professional' => $aspectValues[1] ?? 0,
                'score_personality'  => $aspectValues[2] ?? 0,
                'score_social'       => $aspectValues[3] ?? 0,
                'average_score'      => $calculated['final_score'],
                'final_score'        => $calculated['final_score'],
                'predicate'          => $calculated['predicate'],
                'notes'              => $notes,
                'status'             => PerformanceEvaluation::STATUS_SUBMITTED,
            ]);

            $fresh = $locked->fresh();
            $this->recordLog(
                $fresh,
                $actor,
                'submitted',
                $fromStatus,
                PerformanceEvaluation::STATUS_SUBMITTED,
                null,
                $this->snapshotOf($fresh)
            );

            return $fresh;
        });
    }

    /**
     * Penilaian langsung oleh Kepala Sekolah tanpa delegasi (langsung published).
     */
    public function directPublish(User $approver, User $employee, array $payload): PerformanceEvaluation
    {
        return DB::transaction(function () use ($approver, $employee, $payload) {
            $academicYear = (string) $payload['academic_year'];
            $periodType = (string) $payload['period_type'];
            $periodLabel = (string) $payload['period_label'];
            $category = (string) ($payload['category'] ?? $employee->evaluationCategory());
            $indicatorScores = (array) ($payload['indicator_scores'] ?? []);
            $notes = $payload['notes'] ?? null;

            $calculated = $this->calculator->calculate($category, $indicatorScores, true);
            [$evalMonth, $evalYear] = $this->extractMonthYear($periodType, $periodLabel, $academicYear);
            $aspectValues = array_values($calculated['aspect_scores']);

            $existing = PerformanceEvaluation::withTrashed()
                ->where('user_id', $employee->id)
                ->where('academic_year', $academicYear)
                ->where('period_type', $periodType)
                ->where('period_label', $periodLabel)
                ->lockForUpdate()
                ->first();

            $fromStatus = ($existing && !$existing->trashed()) ? $existing->status : null;
            $now = now();

            $data = [
                'evaluator_id'       => $approver->id,
                'submitted_by'       => $existing?->submitted_by ?? $approver->id,
                'submitted_at'       => $existing?->submitted_at ?? $now,
                'approved_by'        => $approver->id,
                'approved_at'        => $now,
                'evaluation_month'   => $evalMonth,
                'evaluation_year'    => $evalYear,
                'category'           => $category,
                'rubric_version'     => $calculated['rubric_version'],
                'indicator_scores'   => $indicatorScores,
                'aspect_scores'      => $calculated['aspect_scores'],
                'score_pedagogic'    => $aspectValues[0] ?? 0,
                'score_professional' => $aspectValues[1] ?? 0,
                'score_personality'  => $aspectValues[2] ?? 0,
                'score_social'       => $aspectValues[3] ?? 0,
                'average_score'      => $calculated['final_score'],
                'final_score'        => $calculated['final_score'],
                'predicate'          => $calculated['predicate'],
                'notes'              => $notes,
                'revision_note'      => null,
                'status'             => PerformanceEvaluation::STATUS_PUBLISHED,
                'published_at'       => $existing?->published_at ?? $now,
            ];

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->update($data);
                $evaluation = $existing->fresh();
            } else {
                $evaluation = PerformanceEvaluation::create(array_merge([
                    'user_id'       => $employee->id,
                    'academic_year' => $academicYear,
                    'period_type'   => $periodType,
                    'period_label'  => $periodLabel,
                ], $data));
            }

            $this->recordLog(
                $evaluation,
                $approver,
                'approved',
                $fromStatus,
                PerformanceEvaluation::STATUS_PUBLISHED,
                $this->fallbackNote($approver, 'Penilaian langsung dan persetujuan'),
                $this->snapshotOf($evaluation)
            );

            return $evaluation;
        });
    }

    /**
     * Setujui (atau Edit & Setujui) evaluasi berstatus submitted -> published.
     */
    public function approve(
        PerformanceEvaluation $evaluation,
        User $approver,
        ?array $payload = null,
        ?string $note = null
    ): PerformanceEvaluation {
        return DB::transaction(function () use ($evaluation, $approver, $payload, $note) {
            $locked = PerformanceEvaluation::whereKey($evaluation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PerformanceEvaluation::STATUS_SUBMITTED) {
                throw new InvalidArgumentException('Hanya evaluasi berstatus diajukan (submitted) yang dapat disetujui.');
            }

            $beforeSnapshot = $this->snapshotOf($locked);
            $edited = false;

            $category = (string) ($payload['category'] ?? $locked->category);
            $indicatorScores = (array) ($payload['indicator_scores'] ?? $locked->indicator_scores ?? []);
            $notes = array_key_exists('notes', $payload ?? []) ? $payload['notes'] : $locked->notes;

            if ($payload !== null) {
                $edited = ($indicatorScores !== ($locked->indicator_scores ?? []))
                    || ($notes !== $locked->notes);
            }

            $calculated = $this->calculator->calculate($category, $indicatorScores, true);
            $aspectValues = array_values($calculated['aspect_scores']);
            $now = now();

            $locked->update([
                'approved_by'        => $approver->id,
                'approved_at'        => $now,
                'category'           => $category,
                'rubric_version'     => $calculated['rubric_version'],
                'indicator_scores'   => $indicatorScores,
                'aspect_scores'      => $calculated['aspect_scores'],
                'score_pedagogic'    => $aspectValues[0] ?? 0,
                'score_professional' => $aspectValues[1] ?? 0,
                'score_personality'  => $aspectValues[2] ?? 0,
                'score_social'       => $aspectValues[3] ?? 0,
                'average_score'      => $calculated['final_score'],
                'final_score'        => $calculated['final_score'],
                'predicate'          => $calculated['predicate'],
                'notes'              => $notes,
                'revision_note'      => null,
                'status'             => PerformanceEvaluation::STATUS_PUBLISHED,
                'published_at'       => $locked->published_at ?? $now,
            ]);

            $fresh = $locked->fresh();
            $afterSnapshot = $this->snapshotOf($fresh);

            if ($edited) {
                $this->recordLog(
                    $fresh,
                    $approver,
                    'edited_by_approver',
                    PerformanceEvaluation::STATUS_SUBMITTED,
                    PerformanceEvaluation::STATUS_PUBLISHED,
                    $this->fallbackNote($approver, $note ?: 'Skor/catatan disesuaikan oleh penyetuju'),
                    ['before' => $beforeSnapshot, 'after' => $afterSnapshot]
                );
            }

            $this->recordLog(
                $fresh,
                $approver,
                'approved',
                PerformanceEvaluation::STATUS_SUBMITTED,
                PerformanceEvaluation::STATUS_PUBLISHED,
                $this->fallbackNote($approver, $note),
                $afterSnapshot
            );

            return $fresh;
        });
    }

    /**
     * Minta revisi dari penilai delegasi (submitted -> revision_requested).
     */
    public function requestRevision(PerformanceEvaluation $evaluation, User $approver, string $note): PerformanceEvaluation
    {
        $trimmed = trim($note);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Catatan permintaan revisi wajib diisi.');
        }

        return DB::transaction(function () use ($evaluation, $approver, $trimmed) {
            $locked = PerformanceEvaluation::whereKey($evaluation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PerformanceEvaluation::STATUS_SUBMITTED) {
                throw new InvalidArgumentException('Hanya evaluasi berstatus diajukan (submitted) yang dapat diminta revisi.');
            }

            $locked->update([
                'status'        => PerformanceEvaluation::STATUS_REVISION_REQUESTED,
                'revision_note' => $trimmed,
            ]);

            $fresh = $locked->fresh();
            $this->recordLog(
                $fresh,
                $approver,
                'revision_requested',
                PerformanceEvaluation::STATUS_SUBMITTED,
                PerformanceEvaluation::STATUS_REVISION_REQUESTED,
                $this->fallbackNote($approver, $trimmed),
                $this->snapshotOf($fresh)
            );

            return $fresh;
        });
    }

    /**
     * Buka kembali evaluasi yang sudah published (published -> draft/submitted).
     */
    public function reopen(
        PerformanceEvaluation $evaluation,
        User $approver,
        string $note,
        string $targetStatus = PerformanceEvaluation::STATUS_DRAFT
    ): PerformanceEvaluation {
        $trimmed = trim($note);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Catatan pembukaan kembali wajib diisi.');
        }

        if (!in_array($targetStatus, [PerformanceEvaluation::STATUS_DRAFT, PerformanceEvaluation::STATUS_SUBMITTED], true)) {
            throw new InvalidArgumentException('Status tujuan buka kembali tidak valid.');
        }

        return DB::transaction(function () use ($evaluation, $approver, $trimmed, $targetStatus) {
            $locked = PerformanceEvaluation::whereKey($evaluation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PerformanceEvaluation::STATUS_PUBLISHED) {
                throw new InvalidArgumentException('Hanya evaluasi berstatus published yang dapat dibuka kembali.');
            }

            $locked->update([
                'status'        => $targetStatus,
                'published_at'  => null,
                'approved_by'   => null,
                'approved_at'   => null,
                'revision_note' => $trimmed,
            ]);

            $fresh = $locked->fresh();
            $this->recordLog(
                $fresh,
                $approver,
                'reopened',
                PerformanceEvaluation::STATUS_PUBLISHED,
                $targetStatus,
                $this->fallbackNote($approver, $trimmed),
                $this->snapshotOf($fresh)
            );

            return $fresh;
        });
    }

    /**
     * Cabut penugasan penilai delegasi secara aman.
     */
    public function revokeAssignment(PerformanceAssignment $assignment, User $actor, ?string $note = null): PerformanceAssignment
    {
        return DB::transaction(function () use ($assignment, $actor, $note) {
            $locked = PerformanceAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PerformanceAssignment::STATUS_REVOKED) {
                return $locked;
            }

            $locked->update([
                'status'     => PerformanceAssignment::STATUS_REVOKED,
                'revoked_by' => $actor->id,
                'revoked_at' => now(),
            ]);

            $eval = PerformanceEvaluation::where('assignment_id', $locked->id)->first();
            if ($eval) {
                $this->recordLog(
                    $eval,
                    $actor,
                    'assignment_revoked',
                    $eval->status,
                    $eval->status,
                    $this->fallbackNote($actor, $note ?: 'Delegasi penilai dicabut'),
                    $this->snapshotOf($eval)
                );
            }

            return $locked->fresh();
        });
    }

    private function recordLog(
        PerformanceEvaluation $evaluation,
        User $actor,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $note,
        array $snapshot
    ): void {
        PerformanceEvaluationLog::create([
            'performance_evaluation_id' => $evaluation->id,
            'actor_id'                  => $actor->id,
            'action'                    => $action,
            'from_status'               => $fromStatus,
            'to_status'                 => $toStatus,
            'note'                      => $note,
            'snapshot'                  => $snapshot,
            'created_at'                => now(),
        ]);
    }

    private function snapshotOf(PerformanceEvaluation $evaluation): array
    {
        return [
            'indicator_scores' => $evaluation->indicator_scores ?? [],
            'aspect_scores'    => $evaluation->aspect_scores ?? [],
            'final_score'      => (float) $evaluation->final_score,
            'predicate'        => $evaluation->predicate,
            'notes'            => $evaluation->notes,
            'revision_note'    => $evaluation->revision_note,
        ];
    }

    private function fallbackNote(User $actor, ?string $note): ?string
    {
        if (!$actor->hasRole('kepala_sekolah') && $actor->hasRole('superadmin')) {
            $prefix = '[Fallback Superadmin]';
            return $note ? "{$prefix} {$note}" : $prefix;
        }

        return $note;
    }

    private function extractMonthYear(string $periodType, string $periodLabel, string $academicYear): array
    {
        if ($periodType === 'bulanan' && preg_match('/^(\d{4})-(\d{2})$/', $periodLabel, $m)) {
            return [(int) $m[2], (int) $m[1]];
        }

        $parts = explode('/', $academicYear);
        $startYear = (int) ($parts[0] ?? date('Y'));
        $endYear = (int) ($parts[1] ?? ($startYear + 1));

        return $periodType === 'semester_1' ? [12, $startYear] : [6, $endYear];
    }
}
