<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PushSubscriptionSecurityTest extends TestCase
{
    use RefreshDatabase;

    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_rejects_non_https_endpoint()
    {
        $response = $this->actingAs($this->user)->postJson(route('push.store'), [
            'endpoint' => 'http://169.254.169.254/latest/meta-data',
            'keys' => [
                'p256dh' => 'valid-p256dh-key',
                'auth' => 'valid-auth-key',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['endpoint']);
    }

    public function test_rejects_untrusted_host_to_prevent_ssrf()
    {
        $response = $this->actingAs($this->user)->postJson(route('push.store'), [
            'endpoint' => 'https://malicious-internal-server.local/push',
            'keys' => [
                'p256dh' => 'valid-p256dh-key',
                'auth' => 'valid-auth-key',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['endpoint']);
    }

    public function test_rejects_excessively_long_endpoint()
    {
        $response = $this->actingAs($this->user)->postJson(route('push.store'), [
            'endpoint' => 'https://fcm.googleapis.com/' . str_repeat('a', 600),
            'keys' => [
                'p256dh' => 'valid-p256dh-key',
                'auth' => 'valid-auth-key',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['endpoint']);
    }

    public function test_accepts_valid_push_service_endpoints()
    {
        $validEndpoints = [
            'https://fcm.googleapis.com/fcm/send/sample-token',
            'https://updates.push.services.mozilla.com/wpush/v2/sample-token',
            'https://web.push.apple.com/sample-token',
            'https://db5.notify.windows.com/?token=sample-token',
        ];

        foreach ($validEndpoints as $endpoint) {
            $response = $this->actingAs($this->user)->postJson(route('push.store'), [
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => 'valid-p256dh-key',
                    'auth' => 'valid-auth-key',
                ],
            ]);

            $response->assertStatus(200);
        }
    }
}
