<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Buat 3 role sistem sesuai spesifikasi (huruf kecil semua).
     */
    public function run(): void
    {
        $roles = ['superadmin', 'admin', 'user'];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->command->info('✅ Roles created: ' . implode(', ', $roles));
    }
}
