<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\PerformanceEvaluation;
use App\Models\TeacherJournal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PerformanceEvaluationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user', 'tata_usaha', 'koperasi', 'kurikulum', 'kepala_sekolah', 'admin', 'superadmin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function completeScores(string $category, int $value = 4): array
    {
        $out = [];
        foreach (config("performance_indicators.categories.{$category}.aspects") as $aspect => $meta) {
            foreach ($meta['indicators'] as $ind) {
                $out[$aspect][$ind['code']] = $value;
            }
        }
        return $out;
    }

    public function test_detects_guru_and_karyawan_categories(): void
    {
        $guruDiv = User::factory()->create(['divisi' => 'Guru Kelas 1']);
        $guruDiv->assignRole('user');
        $this->assertEquals('guru', $guruDiv->evaluationCategory());

        $tu = User::factory()->create(['divisi' => 'Tata Usaha']);
        $tu->assignRole('tata_usaha');
        $this->assertEquals('karyawan', $tu->evaluationCategory());

        $koperasi = User::factory()->create(['divisi' => 'Unit Usaha']);
        $koperasi->assignRole('koperasi');
        $this->assertEquals('karyawan', $koperasi->evaluationCategory());

        $teacherByActivity = User::factory()->create(['divisi' => null]);
        $teacherByActivity->assignRole('user');
        TeacherJournal::create([
            'user_id'    => $teacherByActivity->id,
            'date'       => '2026-11-10',
            'class_name' => '2B',
            'subject'    => 'IPA',
            'theme'      => 'Air',
            'material'   => 'Siklus Air',
        ]);
        $this->assertEquals('guru', $teacherByActivity->evaluationCategory());
    }

    public function test_kepala_sekolah_can_store_draft_and_publish_while_admin_cannot(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $ks = User::factory()->create();
        $ks->assignRole('kepala_sekolah');

        $teacher = User::factory()->create(['divisi' => 'Guru Kelas']);
        $teacher->assignRole('user');

        // Admin tidak boleh menilai/mempublikasikan langsung
        $this->actingAs($admin)
            ->post(route('admin.performance.store'), [
                'user_id'          => $teacher->id,
                'academic_year'    => '2026/2027',
                'period_type'      => 'bulanan',
                'period_label'     => '2026-11',
                'category'         => 'guru',
                'indicator_scores' => ['pedagogik' => ['ped_perencanaan' => 5]],
                'status'           => 'draft',
            ])
            ->assertForbidden();

        // 1. Store draft (partial) oleh Kepala Sekolah
        $this->actingAs($ks)
            ->post(route('admin.performance.store'), [
                'user_id'          => $teacher->id,
                'academic_year'    => '2026/2027',
                'period_type'      => 'bulanan',
                'period_label'     => '2026-11',
                'category'         => 'guru',
                'indicator_scores' => ['pedagogik' => ['ped_perencanaan' => 5]],
                'notes'            => 'Draft awal',
                'status'           => 'draft',
            ])
            ->assertRedirect();

        $eval = PerformanceEvaluation::firstOrFail();
        $this->assertEquals('draft', $eval->status);
        $this->assertNull($eval->published_at);

        // 2. Publish incomplete harus gagal validasi
        $this->actingAs($ks)
            ->post(route('admin.performance.store'), [
                'user_id'          => $teacher->id,
                'academic_year'    => '2026/2027',
                'period_type'      => 'bulanan',
                'period_label'     => '2026-11',
                'category'         => 'guru',
                'indicator_scores' => ['pedagogik' => ['ped_perencanaan' => 5]],
                'status'           => 'published',
            ])
            ->assertSessionHasErrors('indicator_scores');

        // 3. Publish lengkap -> update record yang sama (unique per periode) & hitung ulang di server
        $this->actingAs($ks)
            ->post(route('admin.performance.store'), [
                'user_id'          => $teacher->id,
                'academic_year'    => '2026/2027',
                'period_type'      => 'bulanan',
                'period_label'     => '2026-11',
                'category'         => 'guru',
                'indicator_scores' => $this->completeScores('guru', 5),
                'notes'            => 'Sangat baik sekali',
                'status'           => 'published',
            ])
            ->assertRedirect();

        $this->assertEquals(1, PerformanceEvaluation::count());
        $eval->refresh();
        $this->assertEquals('published', $eval->status);
        $this->assertEquals(100.0, (float) $eval->final_score);
        $this->assertEquals('Sangat Baik', $eval->predicate);
        $this->assertNotNull($eval->published_at);
    }

    public function test_employee_cannot_view_draft_other_evaluations_or_admin_routes_or_self_evaluate(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $empA = User::factory()->create(['divisi' => 'Guru Kelas']);
        $empA->assignRole('user');

        $empB = User::factory()->create(['divisi' => 'Tata Usaha']);
        $empB->assignRole('user');

        // Draft milik empA
        PerformanceEvaluation::create([
            'user_id'          => $empA->id,
            'evaluator_id'     => $admin->id,
            'academic_year'    => '2026/2027',
            'period_type'      => 'bulanan',
            'period_label'     => '2026-11',
            'category'         => 'guru',
            'rubric_version'   => '2026.1',
            'indicator_scores' => $this->completeScores('guru', 4),
            'aspect_scores'    => ['pedagogik' => 80, 'profesional' => 80, 'kepribadian' => 80, 'sosial' => 80],
            'final_score'      => 80,
            'average_score'    => 80,
            'predicate'        => 'Baik',
            'status'           => 'draft',
        ]);

        // Published milik empB
        PerformanceEvaluation::create([
            'user_id'          => $empB->id,
            'evaluator_id'     => $admin->id,
            'academic_year'    => '2026/2027',
            'period_type'      => 'bulanan',
            'period_label'     => '2026-11',
            'category'         => 'karyawan',
            'rubric_version'   => '2026.1',
            'indicator_scores' => $this->completeScores('karyawan', 5),
            'aspect_scores'    => ['kualitas_kuantitas_kerja' => 100, 'kedisiplinan' => 100, 'inisiatif_tanggung_jawab' => 100, 'pelayanan_kerja_sama' => 100],
            'final_score'      => 100,
            'average_score'    => 100,
            'predicate'        => 'Sangat Baik',
            'status'           => 'published',
            'published_at'     => now(),
        ]);

        // empA tidak melihat draft miliknya maupun published milik empB
        $this->actingAs($empA)
            ->get(route('performance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Employee/Performance/Index')
                ->has('evaluations', 0)
            );

        // empA diblokir dari route admin
        $this->actingAs($empA)
            ->get(route('admin.performance.index'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($empA)
            ->get(route('admin.performance.export'))
            ->assertRedirect(route('dashboard'));

        // Admin tidak bisa menilai dirinya sendiri
        $this->actingAs($admin)
            ->post(route('admin.performance.store'), [
                'user_id'          => $admin->id,
                'academic_year'    => '2026/2027',
                'period_type'      => 'bulanan',
                'period_label'     => '2026-11',
                'category'         => 'karyawan',
                'indicator_scores' => $this->completeScores('karyawan', 5),
                'status'           => 'published',
            ])
            ->assertForbidden();
    }

    public function test_admin_index_filters_and_exports_work(): void
    {
        $kepsek = User::factory()->create();
        $kepsek->assignRole('kepala_sekolah');

        Division::create(['name' => 'Guru Kelas', 'target_divisi' => 80]);
        Division::create(['name' => 'Tata Usaha', 'target_divisi' => 80]);

        $guru = User::factory()->create(['name' => 'Ustadz Ahmad', 'divisi' => 'Guru Kelas']);
        $guru->assignRole('user');

        $tendik = User::factory()->create(['name' => 'Pak Budi', 'divisi' => 'Tata Usaha']);
        $tendik->assignRole('tata_usaha');

        $this->actingAs($kepsek)
            ->get(route('admin.performance.index', [
                'academic_year' => '2026/2027',
                'period_type'   => 'bulanan',
                'period_label'  => '2026-11',
                'category'      => 'guru',
                'division'      => 'Guru Kelas',
                'status'        => 'belum',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Performance/Index')
                ->has('employees.data', 1)
                ->where('employees.data.0.name', 'Ustadz Ahmad')
            );

        $this->actingAs($kepsek)
            ->get(route('admin.performance.export', ['format' => 'xlsx']))
            ->assertOk();

        $this->actingAs($kepsek)
            ->get(route('admin.performance.export', ['format' => 'pdf']))
            ->assertOk();
    }

    public function test_admin_and_kepala_sekolah_can_customize_and_reset_rubric_indicators(): void
    {
        $kepsek = User::factory()->create();
        $kepsek->assignRole('kepala_sekolah');

        $pegawai = User::factory()->create();
        $pegawai->assignRole('user');

        // Pegawai biasa tidak boleh mengakses endpoint rubrik
        $this->actingAs($pegawai)
            ->put(route('admin.performance.rubric.update'), ['categories' => []])
            ->assertRedirect(route('dashboard'));

        // Kepala sekolah menambah & mengubah komponen pada aspek pedagogik
        $categories = [
            'guru' => [
                'pedagogik' => [
                    ['code' => 'ped_perencanaan', 'label' => 'Perencanaan Pembelajaran Revisi', 'description' => 'Diubah KS'],
                    ['code' => '', 'label' => 'Pendampingan Siswa', 'description' => 'Komponen baru'],
                ],
            ],
        ];

        $this->actingAs($kepsek)
            ->put(route('admin.performance.rubric.update'), ['categories' => $categories])
            ->assertRedirect();

        $rubric = app(\App\Services\PerformanceRubricService::class)->getRubric();
        $pedagogik = $rubric['categories']['guru']['aspects']['pedagogik']['indicators'];
        $this->assertCount(2, $pedagogik);
        $this->assertEquals('Perencanaan Pembelajaran Revisi', $pedagogik[0]['label']);
        $this->assertEquals('Pendampingan Siswa', $pedagogik[1]['label']);

        // Reset ke default
        $this->actingAs($kepsek)
            ->post(route('admin.performance.rubric.reset'))
            ->assertRedirect();

        $resetRubric = app(\App\Services\PerformanceRubricService::class)->getRubric();
        $this->assertCount(5, $resetRubric['categories']['guru']['aspects']['pedagogik']['indicators']);
    }
}
