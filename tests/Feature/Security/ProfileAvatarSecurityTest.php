<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProfileAvatarSecurityTest extends TestCase
{
    use RefreshDatabase;

    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->user = User::factory()->create();
    }

    public function test_rejects_svg_avatar_upload_to_prevent_stored_xss()
    {
        $svgFile = UploadedFile::fake()->createWithContent('avatar.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $response = $this->actingAs($this->user)->post(route('profile.update'), [
            'name' => 'Test User',
            'avatar' => $svgFile,
        ]);

        $response->assertSessionHasErrors(['avatar']);
    }

    public function test_rejects_arbitrary_string_avatar_paths()
    {
        // Malicious arbitrary path or external non-allowlisted URL
        $maliciousStrings = [
            '../../system/secret.png',
            'signatures/notulis_secret.png',
            'https://attacker-controlled.site/payload.png',
            'javascript:alert(1)',
        ];

        foreach ($maliciousStrings as $badString) {
            $response = $this->actingAs($this->user)->post(route('profile.update'), [
                'name' => 'Test User',
                'avatar' => $badString,
            ]);

            $response->assertSessionHasErrors(['avatar']);
        }
    }

    public function test_accepts_valid_predefined_dicebear_or_img_avatar_string()
    {
        $validAvatar = 'https://api.dicebear.com/9.x/avataaars/svg?seed=Aisyah&top=hijab';

        $response = $this->actingAs($this->user)->post(route('profile.update'), [
            'name' => 'Test User',
            'avatar' => $validAvatar,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals($validAvatar, $this->user->fresh()->avatar);
    }

    public function test_deletes_old_file_only_if_it_belongs_to_avatars_directory()
    {
        // Place a sensitive file outside avatars/
        Storage::disk('public')->put('signatures/notulis_keep.png', 'sensitive signature data');
        $this->user->update(['avatar' => 'signatures/notulis_keep.png']);

        // Switch to a new avatar
        $newFile = UploadedFile::fake()->image('new_avatar.png');
        $response = $this->actingAs($this->user)->post(route('profile.update'), [
            'name' => 'Test User',
            'avatar' => $newFile,
        ]);

        $response->assertSessionHasNoErrors();
        // The signature outside avatars/ MUST NOT have been deleted!
        Storage::disk('public')->assertExists('signatures/notulis_keep.png');
    }
}
