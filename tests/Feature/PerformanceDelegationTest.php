<?php

namespace Tests\Feature;

use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\MenstruationLog;
use App\Models\PerformanceAssignment;
use App\Models\PerformanceEvaluation;
use App\Models\PerformanceEvaluationLog;
use App\Models\TeacherJournal;
use App\Models\User;
use App\Services\PerformanceRubricService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PerformanceDelegationTest extends TestCase
{
    use RefreshDatabase;

    private User $ks;
    private User $admin;
    private User $superadmin;
    private User $assessor;
    private User $emp1;
    private User $emp2;
    private array $fullScores;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user', 'tata_usaha', 'koperasi', 'kurikulum', 'kepala_sekolah', 'admin', 'superadmin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->ks = User::factory()->create();
        $this->ks->assignRole('kepala_sekolah');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->superadmin = User::factory()->create();
        $this->superadmin->assignRole('superadmin');

        $this->assessor = User::factory()->create(['divisi' => 'Kurikulum']);
        $this->assessor->assignRole(['user', 'kurikulum']);

        $this->emp1 = User::factory()->create(['divisi' => 'Guru Kelas', 'evaluation_category' => 'guru']);
        $this->emp1->assignRole('user');

        $this->emp2 = User::factory()->create(['divisi' => 'Guru Kelas', 'evaluation_category' => 'guru']);
        $this->emp2->assignRole('user');

        $rubric = app(PerformanceRubricService::class)->getRubric()['categories']['guru']['aspects'];
        $this->fullScores = [];
        foreach ($rubric as $aspectKey => $meta) {
            foreach ($meta['indicators'] as $ind) {
                $this->fullScores[$aspectKey][$ind['code']] = 4;
            }
        }
    }

    public function test_delegate_can_only_access_and_evaluate_assigned_employee(): void
    {
        PerformanceAssignment::create([
            'academic_year' => '2026/2027',
            'period_type'   => 'bulanan',
            'period_label'  => '2026-11',
            'assessor_id'   => $this->assessor->id,
            'employee_id'   => $this->emp1->id,
            'assigned_by'   => $this->ks->id,
            'status'        => PerformanceAssignment::STATUS_ACTIVE,
        ]);

        // Boleh akses insight emp1
        $this->actingAs($this->assessor)
            ->getJson(route('performance.assigned.insight', [
                'user_id'       => $this->emp1->id,
                'academic_year' => '2026/2027',
                'period_type'   => 'bulanan',
                'period_label'  => '2026-11',
            ]))
            ->assertOk();

        // Ditolak akses insight, draft, submit untuk emp2 yang tidak ditugaskan
        $this->actingAs($this->assessor)
            ->getJson(route('performance.assigned.insight', [
                'user_id'       => $this->emp2->id,
                'academic_year' => '2026/2027',
                'period_type'   => 'bulanan',
                'period_label'  => '2026-11',
            ]))
            ->assertForbidden();

        $this->actingAs($this->assessor)
            ->post(route('performance.assigned.save-draft'), [
                'user_id'          => $this->emp2->id,
                'academic_year'    => '2026/2027',
                'period_type'      => 'bulanan',
                'period_label'     => '2026-11',
                'category'         => 'guru',
                'indicator_scores' => $this->fullScores,
            ])
            ->assertForbidden();

        $this->actingAs($this->assessor)
            ->post(route('performance.assigned.submit'), [
                'user_id'          => $this->emp2->id,
                'academic_year'    => '2026/2027',
                'period_type'      => 'bulanan',
                'period_label'     => '2026-11',
                'category'         => 'guru',
                'indicator_scores' => $this->fullScores,
            ])
            ->assertForbidden();
    }

    public function test_self_assignment_and_duplicate_active_assignment_are_blocked(): void
    {
        // Self-assignment ditolak
        $this->actingAs($this->ks)
            ->post(route('admin.performance.assignments.store'), [
                'academic_year' => '2026/2027',
                'period_type'   => 'bulanan',
                'period_label'  => '2026-11',
                'assessor_id'   => $this->assessor->id,
                'employee_id'   => $this->assessor->id,
            ])
            ->assertSessionHasErrors('employee_id');

        // Assignment pertama berhasil
        $this->actingAs($this->ks)
            ->post(route('admin.performance.assignments.store'), [
                'academic_year' => '2026/2027',
                'period_type'   => 'bulanan',
                'period_label'  => '2026-11',
                'assessor_id'   => $this->assessor->id,
                'employee_id'   => $this->emp1->id,
            ])
            ->assertRedirect();

        // Penilai lain tidak boleh ditugaskan ke emp1 pada periode yang sama (maks 1 penilai aktif)
        $this->actingAs($this->ks)
            ->post(route('admin.performance.assignments.store'), [
                'academic_year' => '2026/2027',
                'period_type'   => 'bulanan',
                'period_label'  => '2026-11',
                'assessor_id'   => $this->emp2->id,
                'employee_id'   => $this->emp1->id,
            ])
            ->assertSessionHasErrors('employee_id');
    }

    public function test_full_approval_revision_and_reopen_visibility_lifecycle(): void
    {
        PerformanceAssignment::create([
            'academic_year' => '2026/2027',
            'period_type'   => 'bulanan',
            'period_label'  => '2026-11',
            'assessor_id'   => $this->assessor->id,
            'employee_id'   => $this->emp1->id,
            'assigned_by'   => $this->ks->id,
            'status'        => PerformanceAssignment::STATUS_ACTIVE,
        ]);

        // 1. Draft
        $this->actingAs($this->assessor)
            ->post(route('performance.assigned.save-draft'), [
                'user_id'          => $this->emp1->id,
                'academic_year'    => '2026/2027',
                'period_type'      => 'bulanan',
                'period_label'     => '2026-11',
                'category'         => 'guru',
                'indicator_scores' => ['pedagogik' => ['ped_perencanaan' => 3]],
                'notes'            => 'Draft awal',
            ])
            ->assertRedirect();

        // Pegawai belum melihat apa pun saat draft
        $this->actingAs($this->emp1)
            ->get(route('performance.index'))
            ->assertInertia(fn (Assert $page) => $page->has('evaluations', 0));

        // 2. Submit
        $this->actingAs($this->assessor)
            ->post(route('performance.assigned.submit'), [
                'user_id'          => $this->emp1->id,
                'academic_year'    => '2026/2027',
                'period_type'      => 'bulanan',
                'period_label'     => '2026-11',
                'category'         => 'guru',
                'indicator_scores' => $this->fullScores,
                'notes'            => 'Pengajuan pertama',
            ])
            ->assertRedirect();

        $eval = PerformanceEvaluation::firstOrFail();
        $this->assertSame('submitted', $eval->status);

        // Penilai tidak boleh mengubah setelah submitted
        $this->actingAs($this->assessor)
            ->post(route('performance.assigned.save-draft'), [
                'user_id'          => $this->emp1->id,
                'academic_year'    => '2026/2027',
                'period_type'      => 'bulanan',
                'period_label'     => '2026-11',
                'category'         => 'guru',
                'indicator_scores' => $this->fullScores,
            ])
            ->assertStatus(500); // InvalidArgumentException from workflow guard

        // Pegawai masih belum melihat saat submitted
        $this->actingAs($this->emp1)
            ->get(route('performance.index'))
            ->assertInertia(fn (Assert $page) => $page->has('evaluations', 0));

        // 3. KS Minta Revisi
        $this->actingAs($this->ks)
            ->post(route('admin.performance.approvals.request-revision', $eval->id), [
                'note' => 'Tolong perjelas catatan pembinaan',
            ])
            ->assertRedirect();

        $eval->refresh();
        $this->assertSame('revision_requested', $eval->status);

        // 4. Penilai perbaiki & ajukan ulang
        $this->actingAs($this->assessor)
            ->post(route('performance.assigned.submit'), [
                'user_id'          => $this->emp1->id,
                'academic_year'    => '2026/2027',
                'period_type'      => 'bulanan',
                'period_label'     => '2026-11',
                'category'         => 'guru',
                'indicator_scores' => $this->fullScores,
                'notes'            => 'Catatan sudah diperjelas',
            ])
            ->assertRedirect();

        // 5. KS Edit & Setujui
        $modified = $this->fullScores;
        $firstAspect = array_key_first($modified);
        $firstInd = array_key_first($modified[$firstAspect]);
        $modified[$firstAspect][$firstInd] = 5;

        $this->actingAs($this->ks)
            ->post(route('admin.performance.approvals.update-and-approve', $eval->id), [
                'category'         => 'guru',
                'indicator_scores' => $modified,
                'notes'            => 'Disetujui KS',
            ])
            ->assertRedirect();

        $eval->refresh();
        $this->assertSame('published', $eval->status);

        $editedLog = PerformanceEvaluationLog::where('performance_evaluation_id', $eval->id)
            ->where('action', 'edited_by_approver')
            ->first();
        $this->assertNotNull($editedLog);
        $this->assertArrayHasKey('before', $editedLog->snapshot);
        $this->assertArrayHasKey('after', $editedLog->snapshot);

        // Pegawai sekarang melihat hasil published
        $this->actingAs($this->emp1)
            ->get(route('performance.index'))
            ->assertInertia(fn (Assert $page) => $page->has('evaluations', 1));

        // 6. Reopen menyembunyikan kembali dari pegawai
        $this->actingAs($this->ks)
            ->post(route('admin.performance.reopen', $eval->id), [
                'note'          => 'Perlu koreksi data',
                'target_status' => 'draft',
            ])
            ->assertRedirect();

        $this->actingAs($this->emp1)
            ->get(route('performance.index'))
            ->assertInertia(fn (Assert $page) => $page->has('evaluations', 0));
    }

    public function test_revoke_cuts_access_immediately_while_preserving_draft_and_submitted(): void
    {
        $assignment = PerformanceAssignment::create([
            'academic_year' => '2026/2027',
            'period_type'   => 'bulanan',
            'period_label'  => '2026-11',
            'assessor_id'   => $this->assessor->id,
            'employee_id'   => $this->emp1->id,
            'assigned_by'   => $this->ks->id,
            'status'        => PerformanceAssignment::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->assessor)
            ->post(route('performance.assigned.save-draft'), [
                'user_id'          => $this->emp1->id,
                'academic_year'    => '2026/2027',
                'period_type'      => 'bulanan',
                'period_label'     => '2026-11',
                'category'         => 'guru',
                'indicator_scores' => ['pedagogik' => ['ped_perencanaan' => 4]],
            ])
            ->assertRedirect();

        $this->actingAs($this->ks)
            ->delete(route('admin.performance.assignments.revoke', $assignment->id))
            ->assertRedirect();

        // Akses langsung terputus
        $this->actingAs($this->assessor)
            ->post(route('performance.assigned.save-draft'), [
                'user_id'          => $this->emp1->id,
                'academic_year'    => '2026/2027',
                'period_type'      => 'bulanan',
                'period_label'     => '2026-11',
                'category'         => 'guru',
                'indicator_scores' => ['pedagogik' => ['ped_perencanaan' => 5]],
            ])
            ->assertForbidden();

        // Draft tetap ada di database
        $this->assertDatabaseHas('performance_evaluations', [
            'user_id' => $this->emp1->id,
            'status'  => 'draft',
        ]);
    }

    public function test_admin_cannot_approve_or_manage_assignments_and_insight_excludes_sensitive_data(): void
    {
        $assignment = PerformanceAssignment::create([
            'academic_year' => '2026/2027',
            'period_type'   => 'bulanan',
            'period_label'  => '2026-11',
            'assessor_id'   => $this->assessor->id,
            'employee_id'   => $this->emp1->id,
            'assigned_by'   => $this->ks->id,
            'status'        => PerformanceAssignment::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->assessor)
            ->post(route('performance.assigned.submit'), [
                'user_id'          => $this->emp1->id,
                'academic_year'    => '2026/2027',
                'period_type'      => 'bulanan',
                'period_label'     => '2026-11',
                'category'         => 'guru',
                'indicator_scores' => $this->fullScores,
            ])
            ->assertRedirect();

        $eval = PerformanceEvaluation::firstOrFail();

        // Admin biasa tidak boleh approve maupun buat delegasi
        $this->actingAs($this->admin)
            ->post(route('admin.performance.approvals.approve', $eval->id))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('admin.performance.assignments.store'), [
                'academic_year' => '2026/2027',
                'period_type'   => 'bulanan',
                'period_label'  => '2026-11',
                'assessor_id'   => $this->assessor->id,
                'employee_id'   => $this->emp2->id,
            ])
            ->assertForbidden();

        // Seed data sensitif (haid, jurnal, habit) untuk emp1
        MenstruationLog::create([
            'user_id'     => $this->emp1->id,
            'waktu_mulai' => '2026-11-05 08:00:00',
            'notes'       => 'Catatan sensitif haid',
        ]);
        TeacherJournal::create([
            'user_id'    => $this->emp1->id,
            'date'       => '2026-11-10',
            'class_name' => '1A',
            'subject'    => 'Matematika',
            'theme'      => 'Tema Rahasia',
            'material'   => 'Isi Jurnal Pribadi',
        ]);
        $habit = Habit::first() ?? Habit::create([
            'nama_habit'    => 'Sholat Subuh',
            'poin_maksimal' => 10,
            'is_active'     => true,
        ]);
        HabitLog::create([
            'user_id'        => $this->emp1->id,
            'habit_id'       => $habit->id,
            'tanggal'        => '2026-11-10',
            'skor_diperoleh' => 10,
            'details'        => ['subuh' => 'JM'],
        ]);

        $res = $this->actingAs($this->assessor)
            ->getJson(route('performance.assigned.insight', [
                'user_id'       => $this->emp1->id,
                'academic_year' => '2026/2027',
                'period_type'   => 'bulanan',
                'period_label'  => '2026-11',
            ]))
            ->assertOk();

        $json = $res->getContent();
        $this->assertStringNotContainsString('Catatan sensitif haid', $json);
        $this->assertStringNotContainsString('Isi Jurnal Pribadi', $json);
        $this->assertStringNotContainsString('subuh', $json);
    }

    public function test_reminder_command_runs_cleanly(): void
    {
        $this->artisan('kpi:remind-pending')->assertSuccessful();
    }

    public function test_delegation_approvals_and_assigned_pages_render_without_error(): void
    {
        $this->actingAs($this->ks)
            ->get(route('admin.performance.assignments.index'))
            ->assertOk();

        $this->actingAs($this->ks)
            ->get(route('admin.performance.approvals.index'))
            ->assertOk();

        $this->actingAs($this->assessor)
            ->get(route('performance.assigned.index'))
            ->assertOk();
    }
}
