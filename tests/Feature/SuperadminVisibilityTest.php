<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperadminVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user', 'tata_usaha', 'koperasi', 'kurikulum', 'kepala_sekolah', 'admin', 'superadmin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_admin_cannot_see_superadmin_even_with_multiple_roles(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $multiRoleSuperadmin = User::factory()->create(['name' => 'Root Multi']);
        $multiRoleSuperadmin->assignRole(['superadmin', 'user', 'kurikulum']);

        $employee = User::factory()->create(['name' => 'Pegawai Biasa']);
        $employee->assignRole('user');

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['category' => 'karyawan']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->has('users.data', 1)
                ->where('users.data.0.id', $employee->id)
            );

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['category' => 'guru']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->has('users.data', 0)
            );
    }

    public function test_superadmin_only_sees_self_among_superadmins(): void
    {
        $sa1 = User::factory()->create(['name' => 'Superadmin Satu']);
        $sa1->assignRole('superadmin');

        $sa2 = User::factory()->create(['name' => 'Superadmin Dua']);
        $sa2->assignRole(['superadmin', 'user']);

        $employee = User::factory()->create(['name' => 'Pegawai Biasa']);
        $employee->assignRole('user');

        $this->actingAs($sa1)
            ->get(route('admin.users.index', ['category' => 'karyawan']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->has('users.data', 2)
                ->where('users.data', fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all() === collect([$sa1->id, $employee->id])->sort()->values()->all())
            );
    }

    public function test_users_index_separates_guru_and_tendik_tabs(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $guru = User::factory()->create(['name' => 'Guru Kelas', 'divisi' => 'Guru Kelas']);
        $guru->assignRole('user');

        $tendik = User::factory()->create(['name' => 'Staf TU', 'divisi' => 'Tata Usaha']);
        $tendik->assignRole('user');

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['category' => 'guru']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.category', 'guru')
                ->where('categoryCounts.guru', 1)
                ->where('categoryCounts.karyawan', 1)
                ->has('users.data', 1)
                ->where('users.data.0.id', $guru->id)
            );

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['category' => 'karyawan']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.category', 'karyawan')
                ->has('users.data', 1)
                ->where('users.data.0.id', $tendik->id)
            );
    }

    public function test_superadmin_passes_gate_checks_without_extra_roles(): void
    {
        Gate::define('test-custom-ability', fn (User $u) => false);

        $sa = User::factory()->create();
        $sa->assignRole('superadmin');

        $regular = User::factory()->create();
        $regular->assignRole('user');

        $this->assertTrue(Gate::forUser($sa)->allows('test-custom-ability'));
        $this->assertFalse(Gate::forUser($regular)->allows('test-custom-ability'));
    }
}
