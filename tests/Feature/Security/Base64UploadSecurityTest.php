<?php

namespace Tests\Feature\Security;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Base64UploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('public');

        // Set attendance location so check-in passes geofence
        Setting::updateOrCreate(['key' => 'presensi_latitude'], ['value' => '-6.200000']);
        Setting::updateOrCreate(['key' => 'presensi_longitude'], ['value' => '106.800000']);
        Setting::updateOrCreate(['key' => 'presensi_radius_meters'], ['value' => '1000']);
        Setting::updateOrCreate(['key' => 'feature_presensi'], ['value' => '1']);
    }

    public function test_attendance_rejects_non_image_payload_even_with_image_header()
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        // Payload has data:image/png header, but content is PHP script (not a valid image)
        $maliciousPayload = 'data:image/png;base64,' . base64_encode('<?php echo "evil"; ?>');

        $response = $this->actingAs($user)->postJson(route('attendance.check-in'), [
            'latitude' => -6.2,
            'longitude' => 106.8,
            'photo' => $maliciousPayload,
        ]);

        // Must reject with 422 Unprocessable Entity
        $response->assertStatus(422);
    }

    public function test_attendance_rejects_payload_exceeding_size_limit()
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        // 2.5 MB fake base64 data
        $largeData = str_repeat('A', (int) (2.5 * 1024 * 1024));
        $largePayload = 'data:image/png;base64,' . base64_encode($largeData);

        $response = $this->actingAs($user)->postJson(route('attendance.check-in'), [
            'latitude' => -6.2,
            'longitude' => 106.8,
            'photo' => $largePayload,
        ]);

        $response->assertStatus(422);
    }

    public function test_attendance_accepts_valid_image_and_generates_random_filename_with_verified_mime_extension()
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        // Create a 1x1 valid PNG image
        $rawPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        // Client maliciously sends image/fake in data url, but binary is PNG
        $payload = 'data:image/fake;base64,' . base64_encode($rawPng);

        $response = $this->actingAs($user)->post(route('attendance.check-in'), [
            'latitude' => -6.2,
            'longitude' => 106.8,
            'photo' => $payload,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Verify stored file has .png extension derived from verified MIME, not .fake
        $files = Storage::disk('public')->files('attendances');
        $this->assertNotEmpty($files);
        $this->assertStringEndsWith('.png', $files[0]);
        // Filename should be random and not predictable
        $this->assertMatchesRegularExpression('/attendances\/checkin_[a-zA-Z0-9_-]+\.png$/', $files[0]);
    }

    public function test_notulen_rejects_fake_signature_content()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->post(route('notulen.store'), [
            'judul_rapat' => 'Rapat Evaluasi',
            'jenis_rapat' => 'Dinas',
            'tanggal_waktu' => now()->format('Y-m-d H:i:s'),
            'lokasi' => 'Ruang Guru',
            'pimpinan_rapat' => 'Kepala Sekolah',
            'peserta_rapat' => 'Guru A, Guru B',
            'notulis' => $user->name,
            'isi_pembahasan' => '<p>Pembahasan</p>',
            'tindak_lanjut' => '<p>Tindak Lanjut</p>',
            'ttd_notulis' => 'data:image/png;base64,' . base64_encode('not an image at all'),
        ]);

        $response->assertSessionHasErrors('ttd_notulis');
    }
}
