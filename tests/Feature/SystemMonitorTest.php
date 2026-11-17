<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SystemMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;
    protected User $admin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->superadmin = User::factory()->create();
        $this->superadmin->assignRole('superadmin');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->regularUser = User::factory()->create();
        $this->regularUser->assignRole('user');
    }

    public function test_superadmin_can_access_system_monitor_page()
    {
        $response = $this->actingAs($this->superadmin)->get(route('admin.system.monitor'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/System/Monitor')
            ->has('initialMetrics')
            ->has('initialMetrics.cpu')
            ->has('initialMetrics.memory')
            ->has('initialMetrics.storage')
            ->has('initialMetrics.database')
            ->has('initialMetrics.server')
            ->has('initialMetrics.application')
        );
    }

    public function test_superadmin_can_fetch_metrics_via_json_api()
    {
        $response = $this->actingAs($this->superadmin)->getJson(route('admin.system.monitor.api'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'timestamp',
            'metrics' => [
                'cpu' => ['cores', 'usage_percent', 'model'],
                'memory' => ['system', 'php'],
                'storage' => ['disk', 'directories'],
                'database' => ['driver', 'connected', 'latency_ms', 'size_bytes', 'tables_count'],
                'server' => ['php_version', 'laravel_version', 'os_family', 'server_software'],
                'application' => ['app_name', 'app_env', 'cache_driver', 'session_driver'],
                'checked_at',
            ],
        ]);
        $this->assertTrue($response->json('success'));
    }

    public function test_admin_without_superadmin_cannot_access_system_monitor()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.system.monitor'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_admin_without_superadmin_cannot_fetch_metrics_api()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.system.monitor.api'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_regular_user_cannot_access_system_monitor()
    {
        $response = $this->actingAs($this->regularUser)->get(route('admin.system.monitor'));

        $response->assertRedirect(route('dashboard'));
    }
}
