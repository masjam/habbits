<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserProfileSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'pegawai']);
    }

    public function test_catatan_pimpinan_is_never_shared_in_auth_user_inertia_props()
    {
        $user = User::factory()->create([
            'email' => 'pegawai_secret@example.com',
            'catatan_pimpinan' => 'Catatan evaluasi internal rahasia pimpinan.',
        ]);
        $user->assignRole('pegawai');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->has('auth.user')
            ->where('auth.user.id', $user->id)
            ->where('auth.user.email', $user->email)
            ->missing('auth.user.catatan_pimpinan')
        );
    }

    public function test_catatan_pimpinan_is_never_exposed_on_profile_edit_page()
    {
        $user = User::factory()->create([
            'email' => 'pegawai_profile@example.com',
            'catatan_pimpinan' => 'Catatan evaluasi internal rahasia pimpinan.',
        ]);
        $user->assignRole('pegawai');

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->has('user')
            ->where('user.id', $user->id)
            ->missing('user.catatan_pimpinan')
        );
    }
}
