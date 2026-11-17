<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;
    public function test_security_headers_are_present_on_web_requests()
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=(self)', $response->headers->get('Permissions-Policy'));
        $this->assertStringContainsString('geolocation=(self)', $response->headers->get('Permissions-Policy'));
        $this->assertNotEmpty($response->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_hsts_header_is_present_when_accessed_via_https()
    {
        $response = $this->withServerVariables(['HTTPS' => 'on', 'SERVER_PORT' => 443])
            ->get('https://localhost/login');

        $this->assertNotEmpty($response->headers->get('Strict-Transport-Security'));
        $this->assertStringContainsString('max-age=', $response->headers->get('Strict-Transport-Security'));
    }
}
