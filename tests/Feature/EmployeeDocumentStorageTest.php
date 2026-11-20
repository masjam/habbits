<?php

namespace Tests\Feature;

use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeDocumentStorageTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;
    private User $admin;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->employee = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->otherUser = User::factory()->create();
    }

    public function test_employee_can_preview_document_stored_in_local_disk()
    {
        Storage::fake('local');
        $filePath = 'employee_documents/' . $this->employee->id . '/ijazah_sd.pdf';
        Storage::disk('local')->put($filePath, 'fake pdf local content');

        $doc = EmployeeDocument::create([
            'user_id' => $this->employee->id,
            'document_type' => 'ijazah-sd',
            'title' => 'Ijazah SD',
            'file_path' => $filePath,
            'file_extension' => 'pdf',
        ]);

        $response = $this->actingAs($this->employee)->get(route('employee-documents.preview', $doc));

        $response->assertStatus(200);
        $this->assertEquals('fake pdf local content', $response->streamedContent());
    }

    public function test_employee_can_preview_document_stored_in_public_disk()
    {
        Storage::fake('public');
        $filePath = 'employee_documents/' . $this->employee->id . '/ijazah_sd_public.pdf';
        Storage::disk('public')->put($filePath, 'fake pdf public content');

        $doc = EmployeeDocument::create([
            'user_id' => $this->employee->id,
            'document_type' => 'ijazah-sd',
            'title' => 'Ijazah SD',
            'file_path' => $filePath,
            'file_extension' => 'pdf',
        ]);

        $response = $this->actingAs($this->employee)->get(route('employee-documents.preview', $doc));

        $response->assertStatus(200);
        $this->assertEquals('fake pdf public content', $response->streamedContent());
    }

    public function test_employee_can_preview_document_stored_in_legacy_storage_app_path()
    {
        $relativeDir = 'employee_documents/' . $this->employee->id;
        $fullDir = storage_path('app/' . $relativeDir);
        if (!file_exists($fullDir)) {
            mkdir($fullDir, 0777, true);
        }

        $fullPath = $fullDir . '/legacy_ijazah.pdf';
        file_put_contents($fullPath, 'legacy pdf file content');

        try {
            $doc = EmployeeDocument::create([
                'user_id' => $this->employee->id,
                'document_type' => 'ijazah-sd',
                'title' => 'Ijazah SD Legacy',
                'file_path' => $relativeDir . '/legacy_ijazah.pdf',
                'file_extension' => 'pdf',
            ]);

            $response = $this->actingAs($this->employee)->get(route('employee-documents.preview', $doc));

            $response->assertStatus(200);
            $this->assertFileExists($response->getFile()->getPathname());
            $this->assertEquals('legacy pdf file content', file_get_contents($response->getFile()->getPathname()));
        } finally {
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
            if (file_exists($fullDir)) {
                @rmdir($fullDir);
            }
        }
    }

    public function test_admin_can_preview_employee_document()
    {
        Storage::fake('local');
        $filePath = 'employee_documents/' . $this->employee->id . '/ijazah_sd.pdf';
        Storage::disk('local')->put($filePath, 'fake pdf content');

        $doc = EmployeeDocument::create([
            'user_id' => $this->employee->id,
            'document_type' => 'ijazah-sd',
            'title' => 'Ijazah SD',
            'file_path' => $filePath,
            'file_extension' => 'pdf',
        ]);

        $response = $this->actingAs($this->admin)->get(route('employee-documents.preview', $doc));
        $response->assertStatus(200);
    }

    public function test_unauthorized_user_cannot_preview_other_user_document()
    {
        Storage::fake('local');
        $filePath = 'employee_documents/' . $this->employee->id . '/ijazah_sd.pdf';
        Storage::disk('local')->put($filePath, 'fake pdf content');

        $doc = EmployeeDocument::create([
            'user_id' => $this->employee->id,
            'document_type' => 'ijazah-sd',
            'title' => 'Ijazah SD',
            'file_path' => $filePath,
            'file_extension' => 'pdf',
        ]);

        $response = $this->actingAs($this->otherUser)->get(route('employee-documents.preview', $doc));
        $response->assertStatus(403);
    }

    public function test_preview_returns_404_if_file_truly_does_not_exist()
    {
        $doc = EmployeeDocument::create([
            'user_id' => $this->employee->id,
            'document_type' => 'ijazah-sd',
            'title' => 'Ijazah SD Missing',
            'file_path' => 'employee_documents/non_existent/missing.pdf',
            'file_extension' => 'pdf',
        ]);

        $response = $this->actingAs($this->employee)->get(route('employee-documents.preview', $doc));
        $response->assertStatus(404);
    }
}
