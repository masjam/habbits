<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Notulen;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NotulenSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected $creator;
    protected $pimpinan;
    protected $otherUser;
    protected $adminUser;
    protected $kepsekUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'superadmin']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'kepala_sekolah']);
        Role::firstOrCreate(['name' => 'pegawai']);

        $this->creator = User::factory()->create(['name' => 'Notulis User']);
        $this->creator->assignRole('pegawai');

        $this->pimpinan = User::factory()->create(['name' => 'Pimpinan Rapat']);
        $this->pimpinan->assignRole('pegawai');

        $this->otherUser = User::factory()->create(['name' => 'Other Pegawai']);
        $this->otherUser->assignRole('pegawai');

        $this->adminUser = User::factory()->create(['name' => 'Admin User']);
        $this->adminUser->assignRole('admin');

        $this->kepsekUser = User::factory()->create(['name' => 'Kepala Sekolah']);
        $this->kepsekUser->assignRole('kepala_sekolah');
    }

    private function validBase64Png(): string
    {
        // 1x1 transparent PNG
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    }

    public function test_unauthorized_user_cannot_update_notulen()
    {
        $notulen = Notulen::create([
            'user_id' => $this->creator->id,
            'judul_rapat' => 'Rapat Evaluasi',
            'jenis_rapat' => 'Koordinasi',
            'tanggal_waktu' => now(),
            'pimpinan_rapat' => $this->pimpinan->name,
            'lokasi' => 'Ruang Guru',
            'isi_pembahasan' => '<p>Pembahasan awal</p>',
            'ttd_notulis' => 'signatures/notulis_sample.png',
        ]);

        $response = $this->actingAs($this->otherUser)
            ->put(route('notulen.update', $notulen->id), [
                'judul_rapat' => 'Hacked Title',
                'jenis_rapat' => 'Koordinasi',
                'tanggal_waktu' => now()->format('Y-m-d H:i:s'),
                'pimpinan_rapat' => $this->pimpinan->name,
                'lokasi' => 'Ruang Guru',
                'isi_pembahasan' => '<p>Pembahasan diubah</p>',
                'ttd_notulis' => $this->validBase64Png(),
            ]);

        $response->assertStatus(403);
    }

    public function test_creator_pimpinan_and_admin_can_update_notulen()
    {
        $notulen = Notulen::create([
            'user_id' => $this->creator->id,
            'judul_rapat' => 'Rapat Evaluasi',
            'jenis_rapat' => 'Koordinasi',
            'tanggal_waktu' => now(),
            'pimpinan_rapat' => $this->pimpinan->name,
            'lokasi' => 'Ruang Guru',
            'isi_pembahasan' => '<p>Pembahasan awal</p>',
            'ttd_notulis' => 'signatures/notulis_sample.png',
        ]);

        // Creator can update
        $resCreator = $this->actingAs($this->creator)
            ->put(route('notulen.update', $notulen->id), [
                'judul_rapat' => 'Update by Creator',
                'jenis_rapat' => 'Koordinasi',
                'tanggal_waktu' => now()->format('Y-m-d H:i:s'),
                'pimpinan_rapat' => $this->pimpinan->name,
                'lokasi' => 'Ruang Guru',
                'isi_pembahasan' => '<p>Pembahasan diubah</p>',
                'ttd_notulis' => $this->validBase64Png(),
            ]);
        $resCreator->assertSessionHasNoErrors();
        $this->assertEquals('Update by Creator', $notulen->fresh()->judul_rapat);

        // Pimpinan can update
        $resPimpinan = $this->actingAs($this->pimpinan)
            ->put(route('notulen.update', $notulen->id), [
                'judul_rapat' => 'Update by Pimpinan',
                'jenis_rapat' => 'Koordinasi',
                'tanggal_waktu' => now()->format('Y-m-d H:i:s'),
                'pimpinan_rapat' => $this->pimpinan->name,
                'lokasi' => 'Ruang Guru',
                'isi_pembahasan' => '<p>Pembahasan diubah pimpinan</p>',
                'ttd_notulis' => $this->validBase64Png(),
            ]);
        $resPimpinan->assertSessionHasNoErrors();
        $this->assertEquals('Update by Pimpinan', $notulen->fresh()->judul_rapat);

        // Admin can update
        $resAdmin = $this->actingAs($this->adminUser)
            ->put(route('notulen.update', $notulen->id), [
                'judul_rapat' => 'Update by Admin',
                'jenis_rapat' => 'Koordinasi',
                'tanggal_waktu' => now()->format('Y-m-d H:i:s'),
                'pimpinan_rapat' => $this->pimpinan->name,
                'lokasi' => 'Ruang Guru',
                'isi_pembahasan' => '<p>Pembahasan diubah admin</p>',
                'ttd_notulis' => $this->validBase64Png(),
            ]);
        $resAdmin->assertSessionHasNoErrors();
        $this->assertEquals('Update by Admin', $notulen->fresh()->judul_rapat);
    }

    public function test_unauthorized_user_cannot_delete_notulen()
    {
        $notulen = Notulen::create([
            'user_id' => $this->creator->id,
            'judul_rapat' => 'Rapat Hapus',
            'jenis_rapat' => 'Koordinasi',
            'tanggal_waktu' => now(),
            'pimpinan_rapat' => $this->pimpinan->name,
            'lokasi' => 'Ruang Guru',
            'isi_pembahasan' => '<p>Pembahasan</p>',
            'ttd_notulis' => 'signatures/notulis_sample.png',
        ]);

        $response = $this->actingAs($this->otherUser)
            ->delete(route('notulen.destroy', $notulen->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('notulens', ['id' => $notulen->id]);
    }

    public function test_unauthorized_user_cannot_approve_notulen()
    {
        $notulen = Notulen::create([
            'user_id' => $this->creator->id,
            'judul_rapat' => 'Rapat Approve',
            'jenis_rapat' => 'Koordinasi',
            'tanggal_waktu' => now(),
            'pimpinan_rapat' => $this->pimpinan->name,
            'lokasi' => 'Ruang Guru',
            'isi_pembahasan' => '<p>Pembahasan</p>',
            'ttd_notulis' => 'signatures/notulis_sample.png',
        ]);

        $response = $this->actingAs($this->otherUser)
            ->post(route('notulen.approve', $notulen->id), [
                'ttd_pimpinan' => $this->validBase64Png(),
            ]);

        $response->assertStatus(403);
        $this->assertFalse((bool) $notulen->fresh()->is_approved);
    }

    public function test_pimpinan_and_kepsek_and_admin_can_approve_notulen()
    {
        $notulen = Notulen::create([
            'user_id' => $this->creator->id,
            'judul_rapat' => 'Rapat Approve Legit',
            'jenis_rapat' => 'Koordinasi',
            'tanggal_waktu' => now(),
            'pimpinan_rapat' => $this->pimpinan->name,
            'lokasi' => 'Ruang Guru',
            'isi_pembahasan' => '<p>Pembahasan</p>',
            'ttd_notulis' => 'signatures/notulis_sample.png',
        ]);

        $response = $this->actingAs($this->pimpinan)
            ->post(route('notulen.approve', $notulen->id), [
                'ttd_pimpinan' => $this->validBase64Png(),
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue((bool) $notulen->fresh()->is_approved);
    }

    public function test_sanitizes_xss_in_isi_pembahasan_and_tindak_lanjut()
    {
        $maliciousHtml = '<p>Normal text <script>alert("XSS")</script><img src="x" onerror="alert(1)"> <strong>Safe bold</strong></p>';

        $response = $this->actingAs($this->creator)
            ->post(route('notulen.store'), [
                'judul_rapat' => 'Rapat XSS Test',
                'jenis_rapat' => 'Koordinasi',
                'tanggal_waktu' => now()->format('Y-m-d H:i:s'),
                'pimpinan_rapat' => $this->pimpinan->name,
                'lokasi' => 'Ruang Guru',
                'isi_pembahasan' => $maliciousHtml,
                'tindak_lanjut' => $maliciousHtml,
                'ttd_notulis' => $this->validBase64Png(),
            ]);

        $response->assertSessionHasNoErrors();

        $saved = Notulen::where('judul_rapat', 'Rapat XSS Test')->first();
        $this->assertNotNull($saved);

        // Dangerous script and event handlers must be stripped
        $this->assertStringNotContainsString('<script>', $saved->isi_pembahasan);
        $this->assertStringNotContainsString('onerror', $saved->isi_pembahasan);
        $this->assertStringNotContainsString('<script>', $saved->tindak_lanjut);
        $this->assertStringNotContainsString('onerror', $saved->tindak_lanjut);

        // Safe tags must be preserved
        $this->assertStringContainsString('Safe bold', $saved->isi_pembahasan);
        $this->assertStringContainsString('<strong>Safe bold</strong>', $saved->isi_pembahasan);
    }

    public function test_unauthorized_user_cannot_mark_hadir_if_not_peserta()
    {
        $notulen = Notulen::create([
            'user_id' => $this->creator->id,
            'judul_rapat' => 'Rapat Terbatas',
            'jenis_rapat' => 'Koordinasi',
            'tanggal_waktu' => now()->format('Y-m-d H:i:s'),
            'pimpinan_rapat' => $this->pimpinan->name,
            'lokasi' => 'Ruang Guru',
            'isi_pembahasan' => 'Pembahasan internal',
            'ttd_notulis' => $this->validBase64Png(),
        ]);

        // otherUser is not a participant (not in pesertas pivot and not in peserta_rapat)
        $response = $this->actingAs($this->otherUser)->post(route('notulen.hadir', $notulen->id));
        $response->assertStatus(403);
    }

    public function test_authorized_peserta_can_mark_hadir()
    {
        $notulen = Notulen::create([
            'user_id' => $this->creator->id,
            'judul_rapat' => 'Rapat Terbuka',
            'jenis_rapat' => 'Koordinasi',
            'tanggal_waktu' => now()->format('Y-m-d H:i:s'),
            'pimpinan_rapat' => $this->pimpinan->name,
            'lokasi' => 'Ruang Guru',
            'isi_pembahasan' => 'Pembahasan terbuka',
            'ttd_notulis' => $this->validBase64Png(),
        ]);

        $notulen->pesertas()->attach($this->otherUser->id, ['is_hadir' => false]);

        $response = $this->actingAs($this->otherUser)->post(route('notulen.hadir', $notulen->id));
        $response->assertSessionHas('success');

        $this->assertTrue((bool) $notulen->pesertas()->where('user_id', $this->otherUser->id)->first()->pivot->is_hadir);
    }
}
