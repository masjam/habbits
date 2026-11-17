<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminSettingsTabTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->superadmin = User::factory()->create();
        $this->superadmin->assignRole('superadmin');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        Setting::updateOrCreate(['key' => 'feature_presensi'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'attendance_mode_gps'], ['value' => '1']);
    }

    public function test_superadmin_can_access_settings_page_with_presensi_and_tabs()
    {
        $response = $this->actingAs($this->superadmin)->get(route('admin.settings'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Settings/Index')
            ->has('settings')
            ->has('divisions')
            ->has('employees')
            ->has('academicYearSummary')
            ->where('initialTab', 'presensi')
        );
    }

    public function test_settings_page_respects_tab_query_parameter()
    {
        $response = $this->actingAs($this->superadmin)->get(route('admin.settings', ['tab' => 'akademik']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Settings/Index')
            ->where('initialTab', 'akademik')
        );
    }

    public function test_superadmin_can_access_hr_settings_page()
    {
        $response = $this->actingAs($this->superadmin)->get(route('admin.settings.hr'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Settings/Hr')
            ->has('settings')
            ->has('photoStats')
            ->has('cleaningOptions')
            ->where('isSuperAdmin', true)
        );
    }

    public function test_admin_without_superadmin_cannot_access_hr_settings_page()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.settings.hr'));

        $response->assertStatus(403);
    }

    public function test_superadmin_can_update_settings_successfully()
    {
        $response = $this->actingAs($this->superadmin)->post(route('admin.settings.update'), [
            'feature_presensi' => true,
            'attendance_mode_gps' => true,
            'presensi_latitude' => '-7.7956000',
            'presensi_longitude' => '110.3695000',
            'presensi_radius_meters' => 150,
            'presensi_work_start' => '07:15',
            'presensi_late_tolerance' => 20,
            'presensi_work_end' => '15:30',
            'gamification_active' => true,
            'dark_mode_active' => true,
            'announcement_text' => 'Updated announcement',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('1', Setting::get('feature_presensi'));
        $this->assertEquals('150', Setting::get('presensi_radius_meters'));
        $this->assertEquals('07:15', Setting::get('presensi_work_start'));
        $this->assertEquals('Updated announcement', Setting::get('announcement_text'));
    }
}
