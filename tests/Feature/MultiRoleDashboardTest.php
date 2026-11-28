<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MultiRoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class]);
        Setting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => '0']);
    }

    public function test_single_role_tata_usaha_uses_tata_usaha_dashboard_as_main(): void
    {
        $tuOnly = User::factory()->create(['password' => Hash::make('password')]);
        $tuOnly->assignRole('tata_usaha');

        $this->actingAs($tuOnly)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('dashboard.roleKey', 'tata_usaha')
            );
    }

    public function test_multi_role_users_keep_employee_dashboard_and_can_open_role_dashboards(): void
    {
        $tuMulti = User::factory()->create(['password' => Hash::make('password')]);
        $tuMulti->assignRole(['user', 'tata_usaha']);

        $this->actingAs($tuMulti)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('dashboard.roleKey', 'user')
            );

        $this->actingAs($tuMulti)
            ->get(route('tata-usaha.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('dashboard.roleKey', 'tata_usaha')
            );

        $kurMulti = User::factory()->create(['password' => Hash::make('password')]);
        $kurMulti->assignRole(['user', 'kurikulum']);

        $this->actingAs($kurMulti)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('dashboard.roleKey', 'user'));

        $this->actingAs($kurMulti)
            ->get(route('kurikulum.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('dashboard.roleKey', 'kurikulum')
            );

        $ksMulti = User::factory()->create(['password' => Hash::make('password')]);
        $ksMulti->assignRole(['user', 'kepala_sekolah']);

        $this->actingAs($ksMulti)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('dashboard.roleKey', 'user'));

        $this->actingAs($ksMulti)
            ->get(route('kepala-sekolah.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('dashboard.roleKey', 'kepala_sekolah')
            );
    }

    public function test_plain_employee_cannot_access_role_dashboards(): void
    {
        $plainUser = User::factory()->create(['password' => Hash::make('password')]);
        $plainUser->assignRole('user');

        foreach (['tata-usaha.dashboard', 'kurikulum.dashboard', 'kepala-sekolah.dashboard', 'admin.dashboard'] as $routeName) {
            $this->actingAs($plainUser)
                ->getJson(route($routeName))
                ->assertStatus(403);
        }
    }
}
