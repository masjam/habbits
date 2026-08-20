<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BadgeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $badges = [
            [
                'name' => 'Pemula Istiqomah',
                'description' => 'Berhasil mencapai target skor 100 poin selama 3 hari berturut-turut.',
                'icon' => 'academic-cap', 
                'criteria_type' => 'streak_days',
                'criteria_value' => 3,
                'color_theme' => 'amber',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pejuang 7 Hari',
                'description' => 'Mencapai target sempurna tanpa bolong selama 1 minggu berturut-turut.',
                'icon' => 'fire', 
                'criteria_type' => 'streak_days',
                'criteria_value' => 7,
                'color_theme' => 'orange',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Ksatria Sebulan',
                'description' => 'Sangat konsisten! Anda berhasil meraih skor sempurna 30 hari tanpa jeda.',
                'icon' => 'star', 
                'criteria_type' => 'streak_days',
                'criteria_value' => 30,
                'color_theme' => 'yellow',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pengumpul 1000 Poin',
                'description' => 'Luar biasa! Telah mengumpulkan total 1.000 poin kebaikan selama menggunakan aplikasi.',
                'icon' => 'trending-up',
                'criteria_type' => 'total_points',
                'criteria_value' => 1000,
                'color_theme' => 'emerald',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Veteran Kebaikan',
                'description' => 'Mengumpulkan total 5.000 poin kebaikan. Anda adalah inspirasi bagi yang lain.',
                'icon' => 'shield-check', 
                'criteria_type' => 'total_points',
                'criteria_value' => 5000,
                'color_theme' => 'indigo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('badges')->insert($badges);
    }
}
