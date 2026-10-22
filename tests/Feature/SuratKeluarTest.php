<?php

namespace Tests\Feature;

use App\Models\SuratKeluar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuratKeluarTest extends TestCase
{
    use RefreshDatabase;

    private User $tuUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'tata_usaha', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web']);

        $this->tuUser = User::factory()->create();
        $this->tuUser->assignRole('tata_usaha');

        Storage::fake('public');
    }

    public function test_tu_can_view_surat_keluar_index_with_next_nomor()
    {
        SuratKeluar::create([
            'nomor_surat' => '12/ III.4.AU/F/2026',
            'tanggal_surat' => now()->format('Y-m-d'),
            'tujuan' => 'Dinas Pendidikan',
            'perihal' => 'Laporan Bulanan',
            'user_id' => $this->tuUser->id,
        ]);

        $response = $this->actingAs($this->tuUser)->get(route('tata-usaha.surat-keluar.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('TataUsaha/SuratKeluar/Index')
            ->has('suratKeluar.data', 1)
            ->where('next_nomor', '13/ III.4.AU/F/' . date('Y'))
        );
    }

    public function test_tu_can_store_surat_keluar_manually()
    {
        $payload = [
            'nomor_surat' => '05/Pan.Milad/2026',
            'tanggal_surat' => now()->format('Y-m-d'),
            'tujuan' => 'Wali Murid',
            'perihal' => 'Undangan Milad',
        ];

        $response = $this->actingAs($this->tuUser)->post(route('tata-usaha.surat-keluar.store'), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('surat_keluar', [
            'nomor_surat' => '05/Pan.Milad/2026',
            'perihal' => 'Undangan Milad',
        ]);
    }

    public function test_store_surat_keluar_fails_if_nomor_surat_duplicate()
    {
        SuratKeluar::create([
            'nomor_surat' => '10/ III.4.AU/F/2026',
            'tanggal_surat' => now()->format('Y-m-d'),
            'tujuan' => 'Dinas Pendidikan',
            'perihal' => 'Perihal A',
            'user_id' => $this->tuUser->id,
        ]);

        $payload = [
            'nomor_surat' => '10/ III.4.AU/F/2026',
            'tanggal_surat' => now()->format('Y-m-d'),
            'tujuan' => 'Dinas Pendidikan B',
            'perihal' => 'Perihal B',
        ];

        $response = $this->actingAs($this->tuUser)->post(route('tata-usaha.surat-keluar.store'), $payload);

        $response->assertSessionHasErrors(['nomor_surat']);
    }

    public function test_tu_can_view_builder_with_next_nomor()
    {
        SuratKeluar::create([
            'nomor_surat' => '25/ III.4.AU/F/2026',
            'tanggal_surat' => now()->format('Y-m-d'),
            'tujuan' => 'Kantor Wilayah',
            'perihal' => 'Koordinasi',
            'user_id' => $this->tuUser->id,
        ]);

        $response = $this->actingAs($this->tuUser)->get(route('tata-usaha.surat-keluar.builder'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('TataUsaha/SuratKeluar/Builder')
            ->where('next_nomor', '26/ III.4.AU/F/' . date('Y'))
        );
    }

    public function test_tu_can_generate_surat_with_custom_manual_number()
    {
        $payload = [
            'jenis_surat' => 'Umum',
            'nomor_mode' => 'manual',
            'nomor_surat' => '99/Pan.Ujian/2026',
            'lampiran' => '1 Berkas',
            'perihal' => 'Jadwal Pengawasan Ujian',
            'kepada' => 'Bapak/Ibu Guru',
            'di' => 'Tempat',
            'tanggal_masehi' => '21 Oktober 2026 M',
            'tanggal_hijriah' => '11 Rabi\'ul Akhir 1447 H',
            'isi_surat' => '<p>Berikut ini adalah jadwal ujian semester ganjil.</p>',
        ];

        $response = $this->actingAs($this->tuUser)->post(route('tata-usaha.surat-keluar.generate'), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('surat_keluar', [
            'nomor_surat' => '99/Pan.Ujian/2026',
            'perihal' => 'Jadwal Pengawasan Ujian',
            'status' => 'draft',
        ]);
    }

    public function test_generate_automatic_nomor_recalculates_if_conflict()
    {
        // Existing letter with next automatic number
        $year = date('Y');
        SuratKeluar::create([
            'nomor_surat' => "1/ III.4.AU/F/{$year}",
            'tanggal_surat' => now()->format('Y-m-d'),
            'tujuan' => 'Existing',
            'perihal' => 'Existing',
            'user_id' => $this->tuUser->id,
        ]);

        // Client still sent nomor_surat "1/ III.4.AU/F/2026" with mode 'otomatis'
        $payload = [
            'jenis_surat' => 'Umum',
            'nomor_mode' => 'otomatis',
            'nomor_surat' => "1/ III.4.AU/F/{$year}",
            'lampiran' => '-',
            'perihal' => 'Surat Baru Otomatis',
            'kepada' => 'Wali Siswa',
            'di' => 'Tempat',
            'tanggal_masehi' => '21 Oktober 2026 M',
            'tanggal_hijriah' => '11 Rabi\'ul Akhir 1447 H',
            'isi_surat' => '<p>Isi surat otomatis.</p>',
        ];

        $response = $this->actingAs($this->tuUser)->post(route('tata-usaha.surat-keluar.generate'), $payload);

        $response->assertSessionHasNoErrors();
        // It should have recalculated to 2/ III.4.AU/F/{year}
        $this->assertDatabaseHas('surat_keluar', [
            'nomor_surat' => "2/ III.4.AU/F/{$year}",
            'perihal' => 'Surat Baru Otomatis',
        ]);
    }

    public function test_generate_manual_nomor_fails_if_duplicate()
    {
        SuratKeluar::create([
            'nomor_surat' => '01/Panitia/2026',
            'tanggal_surat' => now()->format('Y-m-d'),
            'tujuan' => 'Tujuan A',
            'perihal' => 'Perihal A',
            'user_id' => $this->tuUser->id,
        ]);

        $payload = [
            'jenis_surat' => 'Umum',
            'nomor_mode' => 'manual',
            'nomor_surat' => '01/Panitia/2026',
            'lampiran' => '-',
            'perihal' => 'Perihal B',
            'kepada' => 'Tujuan B',
            'di' => 'Tempat',
            'tanggal_masehi' => '21 Oktober 2026 M',
            'tanggal_hijriah' => '11 Rabi\'ul Akhir 1447 H',
            'isi_surat' => '<p>Isi surat duplikat manual.</p>',
        ];

        $response = $this->actingAs($this->tuUser)->post(route('tata-usaha.surat-keluar.generate'), $payload);

        $response->assertSessionHasErrors(['nomor_surat']);
    }

    public function test_tu_can_update_builder_surat()
    {
        $surat = SuratKeluar::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'document_hash' => 'dummy_hash',
            'nomor_surat' => '05/Panitia/2026',
            'tanggal_surat' => now()->format('Y-m-d'),
            'tujuan' => 'Tujuan Lama',
            'perihal' => 'Perihal Lama',
            'builder_data' => [
                'jenis_surat' => 'Umum',
                'nomor_mode' => 'manual',
                'nomor_surat' => '05/Panitia/2026',
                'lampiran' => '-',
                'perihal' => 'Perihal Lama',
                'kepada' => 'Tujuan Lama',
                'di' => 'Tempat',
                'tanggal_masehi' => '21 Oktober 2026 M',
                'tanggal_hijriah' => '11 Rabi\'ul Akhir 1447 H',
                'isi_surat' => '<p>Isi lama</p>',
            ],
            'status' => 'draft',
            'user_id' => $this->tuUser->id,
        ]);

        $response = $this->actingAs($this->tuUser)->get(route('tata-usaha.surat-keluar.edit-builder', $surat));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('TataUsaha/SuratKeluar/Builder')
            ->where('current_nomor', '05/Panitia/2026')
        );

        $updatePayload = [
            'jenis_surat' => 'Umum',
            'nomor_mode' => 'manual',
            'nomor_surat' => '05/Panitia/2026', // same number is allowed on update
            'lampiran' => '-',
            'perihal' => 'Perihal Baru yang Direvisi',
            'kepada' => 'Tujuan Baru',
            'di' => 'Tempat',
            'tanggal_masehi' => '21 Oktober 2026 M',
            'tanggal_hijriah' => '11 Rabi\'ul Akhir 1447 H',
            'isi_surat' => '<p>Isi surat yang telah diperbarui.</p>',
        ];

        $updateResponse = $this->actingAs($this->tuUser)->post(
            route('tata-usaha.surat-keluar.update-builder', $surat),
            $updatePayload
        );

        $updateResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('surat_keluar', [
            'id' => $surat->id,
            'perihal' => 'Perihal Baru yang Direvisi',
        ]);
    }

    public function test_tu_can_preview_surat_pdf()
    {
        $payload = [
            'jenis_surat' => 'Umum',
            'nomor_mode' => 'otomatis',
            'nomor_surat' => '10/ III.4.AU/F/' . date('Y'),
            'lampiran' => '-',
            'perihal' => 'Preview Test Surat',
            'kepada' => 'Kepala Sekolah',
            'di' => 'Tempat',
            'tanggal_masehi' => '21 Oktober 2026 M',
            'tanggal_hijriah' => '11 Rabi\'ul Akhir 1447 H',
            'isi_surat' => '<p>Konten surat untuk preview PDF.</p>',
        ];

        $response = $this->actingAs($this->tuUser)->post(route('tata-usaha.surat-keluar.preview'), $payload);

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }
}

