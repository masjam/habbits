<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user', 'admin', 'superadmin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_404_renders_inertia_error_for_web_and_json_for_api(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->get('/halaman-yang-tidak-ada-sama-sekali')
            ->assertStatus(404)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 404)
                ->where('homeLabel', 'Ke Dashboard')
            );

        $this->getJson('/api/endpoint-tidak-ada')
            ->assertStatus(404)
            ->assertJson(['status' => 404]);
    }

    public function test_403_renders_inertia_error_with_effective_role_and_json_for_api(): void
    {
        Route::middleware('web')->get('/_test/forbidden', fn () => abort(403));

        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->get('/_test/forbidden')
            ->assertStatus(403)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 403)
                ->where('effectiveRole', 'user')
            );

        $this->actingAs($user)
            ->getJson('/_test/forbidden')
            ->assertStatus(403)
            ->assertJson(['status' => 403]);
    }

    public function test_401_redirects_web_to_login_and_returns_json_for_api(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->getJson('/api/dashboard')
            ->assertStatus(401)
            ->assertJsonStructure(['message']);
    }

    public function test_419_redirects_back_with_flash_for_inertia_and_renders_blade_for_web(): void
    {
        Route::middleware('web')->post('/_test/csrf-expired', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        });

        $this->from('/login')
            ->post('/_test/csrf-expired', [], ['X-Inertia' => 'true'])
            ->assertRedirect('/login')
            ->assertSessionHas('error');

        $this->post('/_test/csrf-expired')
            ->assertStatus(419)
            ->assertSee('419')
            ->assertSee('Sesi Halaman Kedaluwarsa');
    }

    public function test_429_exposes_retry_after_in_inertia_props_and_json(): void
    {
        Route::middleware('web')->get('/_test/throttle', function () {
            throw new ThrottleRequestsException('Too Many Attempts.', null, ['Retry-After' => 45]);
        });

        $this->get('/_test/throttle')
            ->assertStatus(429)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 429)
                ->where('retryAfter', 45)
            );

        $this->getJson('/_test/throttle')
            ->assertStatus(429)
            ->assertJson([
                'status' => 429,
                'retry_after' => 45,
            ]);
    }

    public function test_500_in_production_hides_internal_details_and_includes_reference(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/_test/boom', function () {
            throw new \RuntimeException('SECRET_DB_PASSWORD_LEAK');
        });

        $response = $this->get('/_test/boom');
        $response->assertStatus(500)
            ->assertSee('500')
            ->assertSee('ID Referensi:')
            ->assertDontSee('SECRET_DB_PASSWORD_LEAK')
            ->assertDontSee('RuntimeException');
    }

    public function test_503_renders_standalone_blade_maintenance_view(): void
    {
        Route::middleware('web')->get('/_test/down', fn () => abort(503));

        $this->get('/_test/down')
            ->assertStatus(503)
            ->assertSee('503')
            ->assertSee('Sedang Pemeliharaan Sistem');
    }
}
