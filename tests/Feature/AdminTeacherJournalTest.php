<?php

namespace Tests\Feature;

use App\Models\TeacherJournal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminTeacherJournalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user', 'kepala_sekolah', 'admin', 'superadmin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_kepala_sekolah_and_admin_can_view_per_teacher_summary_and_filters(): void
    {
        $kepsek = User::factory()->create();
        $kepsek->assignRole('kepala_sekolah');

        $guru1 = User::factory()->create(['name' => 'Ustadzah Ani', 'divisi' => 'Guru Kelas']);
        $guru1->assignRole('user');

        $guru2 = User::factory()->create(['name' => 'Ustadz Budi', 'divisi' => 'Guru Tahfidz']);
        $guru2->assignRole('user');

        TeacherJournal::create([
            'user_id'    => $guru1->id,
            'date'       => '2026-11-05',
            'class_name' => '3A',
            'subject'    => 'Matematika',
            'theme'      => 'Perkalian',
            'material'   => 'Latihan soal',
        ]);

        TeacherJournal::create([
            'user_id'    => $guru2->id,
            'date'       => '2026-10-10',
            'class_name' => '5B',
            'subject'    => 'Tahfidz',
            'theme'      => 'Juz 30',
            'material'   => 'Setoran hafalan',
        ]);

        // Filter bulan 2026-11 + mapel Matematika
        $this->actingAs($kepsek)
            ->get(route('admin.teacher-journals.index', [
                'period_type' => 'bulan',
                'month'       => '2026-11',
                'subject'     => 'Matematika',
                'class_name'  => '3A',
                'tab'         => 'detail',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/TeacherJournal/Index')
                ->has('journals.data', 1)
                ->where('journals.data.0.user.name', 'Ustadzah Ani')
                ->where('stats.total_journals', 1)
            );

        // Filter semester ganjil TA 2026/2027 (Juli-Desember 2026) -> keduanya masuk
        $this->actingAs($kepsek)
            ->get(route('admin.teacher-journals.index', [
                'period_type'   => 'semester',
                'academic_year' => '2026/2027',
                'semester'      => 'ganjil',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.total_journals', 2)
                ->where('stats.active_teachers', 2)
            );
    }

    public function test_regular_user_cannot_access_admin_teacher_journals(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->get(route('admin.teacher-journals.index'))
            ->assertRedirect(route('dashboard'));
    }
}
