<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserBulkCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user', 'tata_usaha', 'koperasi', 'kurikulum', 'kepala_sekolah', 'admin', 'superadmin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_admin_can_bulk_update_user_evaluation_category_and_reset_to_auto(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $u1 = User::factory()->create(['divisi' => 'Tata Usaha']);
        $u1->assignRole('user');
        $u2 = User::factory()->create(['divisi' => 'Guru Kelas']);
        $u2->assignRole('user');

        // Bulk set to guru
        $this->actingAs($admin)
            ->post(route('admin.users.bulk-category'), [
                'user_ids' => [$u1->id, $u2->id],
                'evaluation_category' => 'guru',
            ])
            ->assertRedirect();

        $this->assertEquals('guru', $u1->fresh()->evaluation_category);
        $this->assertEquals('guru', $u1->fresh()->evaluationCategory());
        $this->assertEquals('guru', $u2->fresh()->evaluationCategory());

        // Bulk set to karyawan
        $this->actingAs($admin)
            ->post(route('admin.users.bulk-category'), [
                'user_ids' => [$u1->id, $u2->id],
                'evaluation_category' => 'karyawan',
            ])
            ->assertRedirect();

        $this->assertEquals('karyawan', $u1->fresh()->evaluationCategory());
        $this->assertEquals('karyawan', $u2->fresh()->evaluationCategory());

        // Reset to auto (null)
        $this->actingAs($admin)
            ->post(route('admin.users.bulk-category'), [
                'user_ids' => [$u1->id, $u2->id],
                'evaluation_category' => null,
            ])
            ->assertRedirect();

        $this->assertNull($u1->fresh()->evaluation_category);
        $this->assertEquals('karyawan', $u1->fresh()->evaluationCategory());
        $this->assertEquals('guru', $u2->fresh()->evaluationCategory());
    }

    public function test_non_superadmin_admin_cannot_bulk_update_privileged_users(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $this->actingAs($admin)
            ->post(route('admin.users.bulk-category'), [
                'user_ids' => [$superadmin->id],
                'evaluation_category' => 'guru',
            ])
            ->assertForbidden();

        $this->assertNull($superadmin->fresh()->evaluation_category);
    }

    public function test_regular_user_cannot_access_bulk_category_endpoint(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $other = User::factory()->create();
        $other->assignRole('user');

        $this->actingAs($user)
            ->post(route('admin.users.bulk-category'), [
                'user_ids' => [$other->id],
                'evaluation_category' => 'guru',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->assertNull($other->fresh()->evaluation_category);
    }
}
