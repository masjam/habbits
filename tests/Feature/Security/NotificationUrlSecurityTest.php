<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NotificationUrlSecurityTest extends TestCase
{
    use RefreshDatabase;

    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'superadmin']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_send_test_rejects_malicious_javascript_and_external_open_redirect_urls()
    {
        $this->admin->updatePushSubscription(
            'https://fcm.googleapis.com/fcm/send/test-sub',
            'dummy-p256dh',
            'dummy-auth'
        );

        // Disallow javascript: URL
        $resJs = $this->actingAs($this->admin)->postJson(route('push.test'), [
            'url' => 'javascript:alert(document.cookie)',
        ]);
        $resJs->assertStatus(422);
        $resJs->assertJsonValidationErrors(['url']);

        // Disallow external open redirect URL
        $resEvil = $this->actingAs($this->admin)->postJson(route('push.test'), [
            'url' => 'https://evil-phishing.com/login',
        ]);
        $resEvil->assertStatus(422);
        $resEvil->assertJsonValidationErrors(['url']);

        // Disallow protocol-relative URL
        $resProto = $this->actingAs($this->admin)->postJson(route('push.test'), [
            'url' => '//evil-phishing.com/login',
        ]);
        $resProto->assertStatus(422);
        $resProto->assertJsonValidationErrors(['url']);
    }

    public function test_send_broadcast_rejects_external_or_malicious_urls()
    {
        // Disallow external open redirect URL
        $response = $this->actingAs($this->admin)->postJson(route('push.broadcast'), [
            'title' => 'Broadcast Test',
            'body' => 'Broadcast Body',
            'target' => 'all',
            'url' => 'https://external-site.com/fake-login',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['url']);
    }

    public function test_send_broadcast_accepts_valid_internal_relative_url()
    {
        $target = User::factory()->create();

        $response = $this->actingAs($this->admin)->postJson(route('push.broadcast'), [
            'title' => 'Valid Broadcast',
            'body' => 'Valid Body',
            'target' => 'all',
            'url' => '/dashboard',
        ]);

        $response->assertStatus(200);
    }
}
