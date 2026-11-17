<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\LoginLog;
use App\Jobs\ResolveLoginLocationJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProxyAndIpSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_uses_request_ip_and_does_not_trust_arbitrary_cf_connecting_ip_header()
    {
        $user = User::factory()->create([
            'email' => 'spoof_ip@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.50'])
            ->withHeaders([
                'CF-Connecting-IP' => '1.2.3.4',
                'X-Forwarded-For' => '5.6.7.8',
            ])
            ->post('/login', [
                'email' => 'spoof_ip@example.com',
                'password' => 'Password123!',
            ]);

        $response->assertRedirect('/dashboard');

        $latestLog = LoginLog::where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($latestLog);

        // Under default trusted proxies (127.0.0.1), remote addr 192.168.1.50 is NOT a trusted proxy,
        // so forged headers like 1.2.3.4 must NOT be recorded as client IP.
        $this->assertNotEquals('1.2.3.4', $latestLog->ip_address);
        $this->assertEquals('192.168.1.50', $latestLog->ip_address);
    }

    public function test_login_dispatches_resolve_location_job_and_does_not_make_synchronous_http_call()
    {
        Queue::fake();
        Http::fake();

        $user = User::factory()->create([
            'email' => 'async_ip@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.195'])
            ->post('/login', [
                'email' => 'async_ip@example.com',
                'password' => 'Password123!',
            ]);

        // Synchronous HTTP call must not have been made during login request
        Http::assertNothingSent();

        // Queue job must be dispatched
        Queue::assertPushed(ResolveLoginLocationJob::class);
    }
}
