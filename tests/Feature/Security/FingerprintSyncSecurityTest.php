<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Setting;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class FingerprintSyncSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['key' => 'attendance_mode_fingerprint'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'fingerprint_api_token'], ['value' => 'secret-fingerprint-token-1234567890']);
    }

    public function test_rejects_unauthorized_token_when_strict_auth_is_enabled()
    {
        config(['services.fingerprint.strict_auth' => true]);

        $response = $this->postJson('/api/fingerprint/sync', [
            ['fingerprint_id' => 'FP001', 'timestamp' => now()->format('Y-m-d H:i:s')]
        ], [
            'X-Device-Token' => 'wrong-token'
        ]);

        $response->assertStatus(401);
    }

    public function test_rejects_missing_token_when_strict_auth_is_enabled()
    {
        config(['services.fingerprint.strict_auth' => true]);

        $response = $this->postJson('/api/fingerprint/sync', [
            ['fingerprint_id' => 'FP001', 'timestamp' => now()->format('Y-m-d H:i:s')]
        ]);

        $response->assertStatus(401);
    }

    public function test_logs_warning_on_unauthorized_token_in_transition_mode()
    {
        config(['services.fingerprint.strict_auth' => false]);
        Log::spy();

        $user = User::factory()->create(['fingerprint_id' => 'FP001']);

        $response = $this->postJson('/api/fingerprint/sync', [
            ['fingerprint_id' => 'FP001', 'timestamp' => now()->format('Y-m-d H:i:s')]
        ]);

        $response->assertStatus(200);
        Log::shouldHaveReceived('warning');
    }

    public function test_rejects_payload_exceeding_500_items()
    {
        config(['services.fingerprint.strict_auth' => true]);

        $payload = [];
        for ($i = 0; $i < 501; $i++) {
            $payload[] = ['fingerprint_id' => 'FP' . $i, 'timestamp' => now()->format('Y-m-d H:i:s')];
        }

        $response = $this->postJson('/api/fingerprint/sync', $payload, [
            'X-Device-Token' => 'secret-fingerprint-token-1234567890'
        ]);

        $response->assertStatus(422);
    }

    public function test_rejects_records_with_unreasonable_timestamp_window()
    {
        config(['services.fingerprint.strict_auth' => true]);
        $user = User::factory()->create(['fingerprint_id' => 'FP001']);

        $response = $this->postJson('/api/fingerprint/sync', [
            // 60 days in past (too old)
            ['fingerprint_id' => 'FP001', 'timestamp' => now()->subDays(60)->format('Y-m-d H:i:s')],
            // 10 days in future (impossible)
            ['fingerprint_id' => 'FP001', 'timestamp' => now()->addDays(10)->format('Y-m-d H:i:s')],
            // Valid timestamp (today)
            ['fingerprint_id' => 'FP001', 'timestamp' => now()->format('Y-m-d H:i:s')],
        ], [
            'X-Device-Token' => 'secret-fingerprint-token-1234567890'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'processed' => 1,
            'failed' => 2,
        ]);
    }
}
