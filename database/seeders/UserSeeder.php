<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Superadmin
        $superadmin = User::firstOrCreate(
            ['email' => 'superadmin@perusahaan.com'],
            [
                'name'     => 'Budi Superadmin',
                'password' => Hash::make('password'),
                'gender'   => 'L',
            ]
        );
        if (!$superadmin->hasRole('superadmin')) {
            $superadmin->assignRole('superadmin');
        }

        // 2. Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@perusahaan.com'],
            [
                'name'     => 'Siti Admin',
                'password' => Hash::make('password'),
                'gender'   => 'P',
            ]
        );
        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        // 3. User Biasa
        $user = User::firstOrCreate(
            ['email' => 'user@perusahaan.com'],
            [
                'name'     => 'Ahmad Pegawai',
                'password' => Hash::make('password'),
                'gender'   => 'L',
            ]
        );
        if (!$user->hasRole('user')) {
            $user->assignRole('user');
        }

        // 4. User Biasa (Perempuan - Untuk uji Mode Haid)
        $userFemale = User::firstOrCreate(
            ['email' => 'user_p@perusahaan.com'],
            [
                'name'     => 'Aisyah Pegawai',
                'password' => Hash::make('password'),
                'gender'   => 'P',
            ]
        );
        if (!$userFemale->hasRole('user')) {
            $userFemale->assignRole('user');
        }

        $this->command->info('✅ 4 sample users created (Superadmin, Admin, 2x User).');
    }
}
