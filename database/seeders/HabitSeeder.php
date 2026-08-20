<?php

namespace Database\Seeders;

use App\Models\Habit;
use Illuminate\Database\Seeder;

class HabitSeeder extends Seeder
{
    /**
     * Seed 12 habit default.
     *
     * Total Poin Normal:
     *   30+15+15+15+10+5+5+5 = 100 ✅
     *
     * Total Poin saat Haid:
     *   Non-hidden normal (is_pengganti_haid=false, hide_saat_haid=false):
     *     Sedekah(5) + Membaca Hadist(5) + Berwudhu(5) = 15
     *   Habit pengganti haid:
     *     Membaca Buku(25) + Dzikir Pagi(20) + Dzikir Petang(20) + Murojaah(20) = 85
     *   Total = 15 + 85 = 100 ✅
     */
    public function run(): void
    {
        $habits = [
            // ──────────────────────────────────────────────────────────────────
            // HABIT NORMAL (Muncul saat TIDAK haid, disembunyikan saat haid)
            // ──────────────────────────────────────────────────────────────────
            [
                'nama_habit'        => 'Sholat Wajib Berjamaah',
                'deskripsi'         => 'Sholat 5 waktu berjamaah (Subuh, Dzuhur, Ashar, Maghrib, Isya). Skor penuh dicapai jika minimal 3 waktu berjamaah.',
                'tipe_input'        => 'integer',
                'template'          => 'sholat_wajib',
                'satuan'            => 'Waktu',
                'target_pencapaian' => 3,
                'skor_maksimal'     => 2,
                'hide_saat_haid'    => true,
                'is_pengganti_haid' => false,
                'status_aktif'      => true,
            ],
            [
                'nama_habit'        => 'Sholat Rawatib',
                'deskripsi'         => 'Sholat sunnah rawatib (Qabliyah & Bakdiyah). Skor penuh dicapai jika minimal 3 waktu rawatib.',
                'tipe_input'        => 'integer',
                'template'          => 'sholat_rawatib',
                'satuan'            => 'Waktu',
                'target_pencapaian' => 3,
                'skor_maksimal'     => 1,
                'hide_saat_haid'    => true,
                'is_pengganti_haid' => false,
                'status_aktif'      => true,
            ],
            [
                'nama_habit'        => 'Sholat Lail Min. 3 Rakaat',
                'deskripsi'         => 'Sholat malam (Tahajud / Witir) minimal 3 rakaat sebelum Subuh.',
                'tipe_input'        => 'integer',
                'template'          => 'tahajud',
                'satuan'            => 'Rakaat',
                'target_pencapaian' => 3,
                'skor_maksimal'     => 1,
                'hide_saat_haid'    => true,
                'is_pengganti_haid' => false,
                'status_aktif'      => true,
            ],
            [
                'nama_habit'        => 'Membaca Al Quran',
                'deskripsi'         => 'Membaca Al Quran dengan tartil.',
                'tipe_input'        => 'integer',
                'template'          => 'quran',
                'satuan'            => null,
                'target_pencapaian' => 10,
                'skor_maksimal'     => 1,
                'hide_saat_haid'    => true,
                'is_pengganti_haid' => false,
                'status_aktif'      => true,
            ],
            [
                'nama_habit'        => 'Sholat Dhuha Min. 4 Rakaat',
                'deskripsi'         => 'Sholat Dhuha minimal 4 rakaat setelah matahari naik sepenggalah.',
                'tipe_input'        => 'integer',
                'template'          => 'dhuha',
                'satuan'            => 'Rakaat',
                'target_pencapaian' => 4,
                'skor_maksimal'     => 1,
                'hide_saat_haid'    => true,
                'is_pengganti_haid' => false,
                'status_aktif'      => true,
            ],
            // ──────────────────────────────────────────────────────────────────
            // HABIT NORMAL (Tetap muncul saat haid, bukan pengganti)
            // ──────────────────────────────────────────────────────────────────
            [
                'nama_habit'        => 'Sedekah Harian',
                'deskripsi'         => 'Bersedekah per hari dalam bentuk apapun.',
                'tipe_input'        => 'boolean',
                'template'          => 'default',
                'satuan'            => null,
                'target_pencapaian' => 1,
                'skor_maksimal'     => 1,
                'hide_saat_haid'    => false,
                'is_pengganti_haid' => false,
                'status_aktif'      => true,
            ],
            [
                'nama_habit'        => 'Membaca Hadist',
                'deskripsi'         => 'Membaca dan mempelajari minimal 1 hadist beserta penjelasannya.',
                'tipe_input'        => 'boolean',
                'template'          => 'hadist',
                'satuan'            => null,
                'target_pencapaian' => 1,
                'skor_maksimal'     => 1,
                'hide_saat_haid'    => false,
                'is_pengganti_haid' => false,
                'status_aktif'      => true,
            ],
            [
                'nama_habit'        => 'Berwudhu Sebelum Tidur',
                'deskripsi'         => 'Menjaga wudhu sebelum tidur malam.',
                'tipe_input'        => 'boolean',
                'template'          => 'default',
                'satuan'            => null,
                'target_pencapaian' => 1,
                'skor_maksimal'     => 1,
                'hide_saat_haid'    => false,
                'is_pengganti_haid' => false,
                'status_aktif'      => true,
            ],
            [
                'nama_habit'        => 'Menjaga Aqidah',
                'deskripsi'         => 'Tidak Melakukan Perbuatan yang merusak aqidah',
                'tipe_input'        => 'boolean',
                'template'          => 'default',
                'satuan'            => null,
                'target_pencapaian' => 1,
                'skor_maksimal'     => 1,
                'hide_saat_haid'    => false,
                'is_pengganti_haid' => false,
                'status_aktif'      => true,
            ],
            // ──────────────────────────────────────────────────────────────────
            // HABIT PENGGANTI HAID (Hanya muncul saat Mode Haid aktif)
            // ──────────────────────────────────────────────────────────────────
            [
                'nama_habit'        => '[Pengganti Haid] Membaca Artikel',
                'deskripsi'         => 'Membaca buku atau artikel seputar agama Islam selama masa haid.',
                'tipe_input'        => 'boolean',
                'template'          => 'buku',
                'satuan'            => null,
                'target_pencapaian' => 1,
                'skor_maksimal'     => 1,
                'hide_saat_haid'    => false,
                'is_pengganti_haid' => true,
                'status_aktif'      => true,
            ],
            [
                'nama_habit'        => '[Pengganti Haid] Dzikir Pagi',
                'deskripsi'         => 'Membaca dzikir pagi (Dzikir Shalat Subuh / Dzikirnya berurutan setelah waktu Subuh).',
                'tipe_input'        => 'boolean',
                'template'          => 'default',
                'satuan'            => null,
                'target_pencapaian' => 1,
                'skor_maksimal'     => 2,
                'hide_saat_haid'    => false,
                'is_pengganti_haid' => true,
                'status_aktif'      => true,
            ],
            [
                'nama_habit'        => '[Pengganti Haid] Dzikir Petang',
                'deskripsi'         => 'Membaca dzikir petang setelah waktu Ashar atau menjelang Maghrib.',
                'tipe_input'        => 'boolean',
                'template'          => 'default',
                'satuan'            => null,
                'target_pencapaian' => 1,
                'skor_maksimal'     => 2,
                'hide_saat_haid'    => false,
                'is_pengganti_haid' => true,
                'status_aktif'      => true,
            ],
            [
                'nama_habit'        => '[Pengganti Haid] Murojaah Surat',
                'deskripsi'         => 'Mengulang (murojaah) hafalan surat-surat Al Quran yang sudah dihafal.',
                'tipe_input'        => 'boolean',
                'template'          => 'quran',
                'satuan'            => null,
                'target_pencapaian' => 1,
                'skor_maksimal'     => 1,
                'hide_saat_haid'    => false,
                'is_pengganti_haid' => true,
                'status_aktif'      => true,
            ],
        ];

        foreach ($habits as $habit) {
            Habit::updateOrCreate(
                ['nama_habit' => $habit['nama_habit']],
                $habit
            );
        }

        $this->command->info('✅ ' . count($habits) . ' habits seeded successfully.');
        $this->command->info('   Mode Normal: Total Skor Maks = ' .
            collect($habits)->where('is_pengganti_haid', false)->sum('skor_maksimal') . ' poin');
        $this->command->info('   Mode Haid: Total Skor Maks = ' .
            (collect($habits)->where('is_pengganti_haid', false)->where('hide_saat_haid', false)->sum('skor_maksimal') +
             collect($habits)->where('is_pengganti_haid', true)->sum('skor_maksimal')) . ' poin');
    }
}
