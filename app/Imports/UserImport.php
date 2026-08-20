<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UserImport implements ToCollection, WithHeadingRow
{
    protected $currentUser;

    public function __construct($user)
    {
        $this->currentUser = $user;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            // Skip if required fields are missing
            if (empty($row['nama_lengkap']) || empty($row['email'])) {
                continue;
            }

            // Skip if email already exists
            if (User::where('email', $row['email'])->exists()) {
                continue;
            }

            $user = User::create([
                'name'     => $row['nama_lengkap'],
                'email'    => $row['email'],
                'password' => Hash::make(!empty($row['password']) ? $row['password'] : 'Password123!'),
                'gender'   => strtoupper($row['jenis_kelamin'] ?? 'L') === 'P' ? 'P' : 'L',
            ]);

            // Determine role
            $roleName = 'user'; // Default to user (pegawai)

            if ($this->currentUser->hasRole('superadmin')) {
                $requestedRole = strtolower($row['role'] ?? 'pegawai');
                if ($requestedRole === 'superadmin') {
                    $roleName = 'superadmin';
                } elseif ($requestedRole === 'admin') {
                    $roleName = 'admin';
                }
            }

            $user->assignRole($roleName);
        }
    }
}
