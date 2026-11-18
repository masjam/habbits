<?php

namespace Tests\Feature\Security;

use App\Models\TeacherJournal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OpsHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['superadmin', 'admin', 'kepala_sekolah', 'tata_usaha', 'kurikulum', 'user'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function makeUser(array $roles): User
    {
        $u = User::factory()->create();
        $u->assignRole($roles);

        return $u;
    }

    public function test_teacher_journal_material_is_sanitized_on_write(): void
    {
        $teacher = $this->makeUser(['user']);

        $journal = TeacherJournal::create([
            'user_id' => $teacher->id,
            'date' => now()->toDateString(),
            'time_start' => '07:00',
            'time_end' => '08:00',
            'material' => '<p onclick="x()">Materi</p><script>alert(1)</script><img src=x onerror=alert(1)>',
        ]);

        $stored = $journal->fresh()->material;
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onerror', $stored);
        $this->assertStringNotContainsString('onclick', $stored);
        $this->assertStringContainsString('Materi', $stored);
    }

    public function test_admin_cannot_reset_password_or_delete_kepala_sekolah(): void
    {
        $admin = $this->makeUser(['admin']);
        $kepsek = $this->makeUser(['user', 'kepala_sekolah']);

        $this->actingAs($admin)
            ->post(route('admin.users.reset-password', $kepsek), ['password' => 'Passw0rd!Baru#1'])
            ->assertStatus(403);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $kepsek))
            ->assertStatus(403);
    }

    public function test_admin_cannot_change_password_of_tata_usaha_via_update(): void
    {
        $admin = $this->makeUser(['admin']);
        $tu = $this->makeUser(['user', 'tata_usaha']);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $tu), [
                'name' => $tu->name,
                'email' => $tu->email,
                'gender' => 'L',
                'password' => 'Passw0rd!Baru#1',
            ])
            ->assertStatus(403);
    }

    public function test_admin_can_still_reset_password_of_regular_user(): void
    {
        $admin = $this->makeUser(['admin']);
        $regular = $this->makeUser(['user']);

        $this->actingAs($admin)
            ->post(route('admin.users.reset-password', $regular), ['password' => 'Passw0rd!Baru#1'])
            ->assertSessionHasNoErrors();
    }

    public function test_public_verification_does_not_leak_file_path(): void
    {
        $this->get('/verifikasi-surat/preview-only')->assertOk();
        // Halaman publik tidak boleh membawa kunci file_path ke props Inertia
        $this->assertStringNotContainsString('file_path', $this->get('/verifikasi-surat/tidak-ada')->getContent());
    }

    public function test_export_download_requires_authorized_role(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('exports/Laporan_Test.xlsx', 'dummy');

        $this->get('/storage/exports/Laporan_Test.xlsx')->assertRedirect(); // belum login

        $regular = $this->makeUser(['user']);
        $this->actingAs($regular)->get('/storage/exports/Laporan_Test.xlsx')->assertStatus(403);

        $admin = $this->makeUser(['admin']);
        $this->actingAs($admin)->get('/storage/exports/Laporan_Test.xlsx')->assertOk();
    }
}
