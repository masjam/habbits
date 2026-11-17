<?php

namespace Tests\Feature\Security;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KioskTokenSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Setting::updateOrCreate(['key' => 'attendance_mode_rfid'], ['value' => '1']);
    }

    public function test_kiosk_rejects_default_fallback_secret_when_no_token_configured()
    {
        // No token configured in settings
        Setting::where('key', 'kiosk_api_token')->delete();
        Setting::clearCache();

        $response = $this->get(route('attendance.rfid.kiosk', ['token' => 'SDAM-KIOSK-SECRET']));
        $response->assertStatus(403);
    }

    public function test_kiosk_rejects_tokens_shorter_than_32_characters()
    {
        // Short token
        Setting::updateOrCreate(['key' => 'kiosk_api_token'], ['value' => 'short-secret-token-123']);
        Setting::clearCache();

        $response = $this->get(route('attendance.rfid.kiosk', ['token' => 'short-secret-token-123']));
        $response->assertStatus(403);
    }

    public function test_kiosk_accepts_valid_32_char_token()
    {
        $validToken = Str::random(32);
        Setting::updateOrCreate(['key' => 'kiosk_api_token'], ['value' => $validToken]);
        Setting::clearCache();

        $response = $this->get(route('attendance.rfid.kiosk', ['token' => $validToken]));
        $response->assertStatus(200);
    }

    public function test_process_scan_rejects_fallback_secret()
    {
        Setting::where('key', 'kiosk_api_token')->delete();
        Setting::clearCache();

        $response = $this->postJson(route('attendance.rfid.scan'), [
            'rfid_uid' => '12345678',
            'token' => 'SDAM-KIOSK-SECRET',
        ]);

        $response->assertStatus(403);
    }

    public function test_process_scan_returns_consistent_error_on_unknown_uid()
    {
        $validToken = Str::random(32);
        Setting::updateOrCreate(['key' => 'kiosk_api_token'], ['value' => $validToken]);
        Setting::clearCache();

        $response = $this->postJson(route('attendance.rfid.scan'), [
            'rfid_uid' => 'NON-EXISTENT-UID-999',
            'token' => $validToken,
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Kartu RFID tidak terdaftar. Silakan hubungi admin.',
        ]);
    }
}
