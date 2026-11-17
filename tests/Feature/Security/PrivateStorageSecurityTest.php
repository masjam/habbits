<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PrivateStorageSecurityTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $userA;
    private $userB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Storage::fake('public');

        Role::firstOrCreate(['name' => 'superadmin']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'pegawai']);

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->userA = User::factory()->create(['name' => 'User A']);
        $this->userA->assignRole('pegawai');

        $this->userB = User::factory()->create(['name' => 'User B']);
        $this->userB->assignRole('pegawai');
    }

    public function test_store_leave_stores_attachment_to_private_disk()
    {
        $file = UploadedFile::fake()->create('surat_dokter.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(route('admin.attendance.store-leave'), [
            'user_id' => $this->userA->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'sakit',
            'notes' => 'Demam tinggi',
            'attachment' => $file,
        ]);

        $response->assertSessionHasNoErrors();

        $att = Attendance::where('user_id', $this->userA->id)->first();
        $this->assertNotNull($att);
        $this->assertNotNull($att->attachment);

        // Must be stored on private disk
        Storage::disk('private')->assertExists($att->attachment);
    }

    public function test_unauthenticated_user_cannot_access_private_attachment()
    {
        Storage::disk('private')->put('attendance/attachments/secret_doc.pdf', 'Secret Medical Info');

        Attendance::create([
            'user_id' => $this->userA->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'sakit',
            'attachment' => 'attendance/attachments/secret_doc.pdf',
        ]);

        $response = $this->get('/storage/attendance/attachments/secret_doc.pdf');
        // Unauthenticated access must be rejected (302 redirect to login or 401)
        $this->assertTrue(in_array($response->status(), [302, 401]));
    }

    public function test_unauthorized_user_cannot_view_other_users_attachment()
    {
        Storage::disk('private')->put('attendance/attachments/secret_doc.pdf', 'Secret Medical Info');

        Attendance::create([
            'user_id' => $this->userA->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'sakit',
            'attachment' => 'attendance/attachments/secret_doc.pdf',
        ]);

        // User B attempts to access User A's medical certificate
        $response = $this->actingAs($this->userB)->get('/storage/attendance/attachments/secret_doc.pdf');
        $response->assertStatus(403);
    }

    public function test_authorized_user_and_admin_can_access_attachment()
    {
        Storage::disk('private')->put('attendance/attachments/secret_doc.pdf', 'Secret Medical Info');

        Attendance::create([
            'user_id' => $this->userA->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'sakit',
            'attachment' => 'attendance/attachments/secret_doc.pdf',
        ]);

        // User A (owner) can access
        $resOwner = $this->actingAs($this->userA)->get('/storage/attendance/attachments/secret_doc.pdf');
        $resOwner->assertStatus(200);

        // Admin can access
        $resAdmin = $this->actingAs($this->admin)->get('/storage/attendance/attachments/secret_doc.pdf');
        $resAdmin->assertStatus(200);
    }
}
