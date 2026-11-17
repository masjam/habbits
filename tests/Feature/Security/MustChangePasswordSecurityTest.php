<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use App\Imports\UserImport;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MustChangePasswordSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'user']);
        Role::firstOrCreate(['name' => 'superadmin']);
    }

    public function test_user_import_sets_must_change_password_flag()
    {
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');

        $rows = new Collection([
            [
                'nama_lengkap' => 'Imported User One',
                'email' => 'imported1@example.com',
                'jenis_kelamin' => 'L',
                'role' => 'user',
            ]
        ]);

        $import = new UserImport($admin);
        $import->collection($rows);

        $user = User::where('email', 'imported1@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue((bool) $user->must_change_password);
    }

    public function test_user_with_must_change_password_is_redirected_to_profile()
    {
        $user = User::factory()->create([
            'must_change_password' => true,
        ]);
        $user->assignRole('user');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('warning');
    }

    public function test_user_can_access_profile_edit_and_change_password_to_clear_flag()
    {
        $user = User::factory()->create([
            'password' => bcrypt('Password123!'),
            'must_change_password' => true,
        ]);
        $user->assignRole('user');

        // Can access profile.edit
        $editRes = $this->actingAs($user)->get(route('profile.edit'));
        $editRes->assertStatus(200);

        // Update password
        $updateRes = $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => 'Password123!',
            'password' => 'NewStrongPass123!',
            'password_confirmation' => 'NewStrongPass123!',
        ]);

        $updateRes->assertSessionHas('status', 'password-updated');
        $this->assertFalse((bool) $user->fresh()->must_change_password);

        // Now can access dashboard
        $dashRes = $this->actingAs($user)->get(route('dashboard'));
        $dashRes->assertStatus(200);
    }
}
