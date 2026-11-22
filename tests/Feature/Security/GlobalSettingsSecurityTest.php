<?php

namespace Tests\Feature\Security;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GlobalSettingsSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        Setting::updateOrCreate(['key' => 'kiosk_api_token'], ['value' => 'SUPER-SECRET-KIOSK-TOKEN-1234567890']);
        Setting::updateOrCreate(['key' => 'presensi_whatsapp_target'], ['value' => '081234567890']);
        Setting::updateOrCreate(['key' => 'kepsek_phone'], ['value' => '089876543210']);
        Setting::updateOrCreate(['key' => 'feature_habit'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'app_name'], ['value' => 'SD MUH AL MUJAHIDIN']);
    }

    public function test_global_settings_does_not_leak_secret_tokens_or_private_numbers()
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);

        // global_settings should only contain allowlisted public feature flags/settings
        $response->assertInertia(fn (Assert $page) => $page
            ->has('global_settings')
            ->missing('global_settings.kiosk_api_token')
            ->missing('global_settings.presensi_whatsapp_target')
            ->missing('global_settings.kepsek_phone')
            ->where('global_settings.feature_habit', '1')
        );
    }

    public function test_admin_attendance_receives_kiosk_token_specifically_for_admin()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.attendance.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->has('kiosk_token')
            ->where('kiosk_token', 'SUPER-SECRET-KIOSK-TOKEN-1234567890')
        );
    }

    public function test_settings_update_sanitizes_running_text_and_popup_text()
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $response = $this->actingAs($superadmin)->post(route('admin.settings.update'), [
            'running_text' => 'Halo <b>Semua</b><script>alert("xss")</script>',
            'popup_text' => '<p>Pengumuman Penting</p><img src=x onerror=alert("xss")>',
        ]);

        $response->assertSessionHasNoErrors();

        $runningText = Setting::where('key', 'running_text')->value('value');
        $this->assertStringNotContainsString('<script', $runningText);
        $this->assertStringNotContainsString('alert(', $runningText);
        $this->assertStringContainsString('Halo Semua', $runningText);

        $popupText = Setting::where('key', 'popup_text')->value('value');
        $this->assertStringNotContainsString('onerror', $popupText);
        $this->assertStringNotContainsString('alert(', $popupText);
        $this->assertStringContainsString('Pengumuman Penting', $popupText);
    }
}
