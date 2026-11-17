<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_token_is_stored_hashed_in_database()
    {
        $user = User::factory()->create([
            'email' => 'api_test@example.com',
            'password' => bcrypt('Secret123!'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'api_test@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(200);
        $plainToken = $response->json('data.token');
        $this->assertNotEmpty($plainToken);

        $dbToken = $user->fresh()->api_token;
        // DB token MUST NOT be equal to plaintext token
        $this->assertNotEquals($plainToken, $dbToken);
        // DB token MUST match SHA-256 hash of plaintext token
        $this->assertEquals(hash('sha256', $plainToken), $dbToken);
    }

    public function test_authenticated_api_request_works_with_hashed_token()
    {
        $plainToken = 'raw-secure-api-token-value-12345';
        $user = User::factory()->create([
            'email' => 'user_hashed@example.com',
            'api_token' => hash('sha256', $plainToken),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $plainToken)
            ->getJson('/api/user');

        $response->assertStatus(200);
        $response->assertJsonPath('data.email', 'user_hashed@example.com');
    }

    public function test_api_login_is_rate_limited()
    {
        $user = User::factory()->create([
            'email' => 'throttle_test@example.com',
            'password' => bcrypt('Secret123!'),
        ]);

        // 5 requests allowed
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'throttle_test@example.com',
                'password' => 'WrongPassword',
            ]);
        }

        // 6th request must be rate limited (429)
        $response = $this->postJson('/api/login', [
            'email' => 'throttle_test@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(429);
    }

    public function test_catatan_pimpinan_is_not_exposed_in_api_responses()
    {
        $user = User::factory()->create([
            'email' => 'private_notes@example.com',
            'password' => bcrypt('Secret123!'),
            'catatan_pimpinan' => 'Sangat rahasia: evaluasi performa buruk',
        ]);

        // Login response check
        $loginRes = $this->postJson('/api/login', [
            'email' => 'private_notes@example.com',
            'password' => 'Secret123!',
        ]);

        $loginRes->assertStatus(200);
        $loginRes->assertJsonMissing(['catatan_pimpinan' => 'Sangat rahasia: evaluasi performa buruk']);

        // /api/user response check
        $plainToken = $loginRes->json('data.token');
        $userRes = $this->withHeader('Authorization', 'Bearer ' . $plainToken)
            ->getJson('/api/user');

        $userRes->assertStatus(200);
        $userRes->assertJsonMissing(['catatan_pimpinan' => 'Sangat rahasia: evaluasi performa buruk']);
    }
}
