<?php

namespace App\Services\Dashboard;

use App\Models\User;

class DashboardResolver
{
    /**
     * Aturan prioritas multi-role untuk /dashboard utama:
     * - Admin dan Superadmin selalu menggunakan Dashboard Admin/Superadmin sebagai dashboard utama.
     * - Role non-admin dengan lebih dari 1 role menggunakan Dashboard Pegawai sebagai dashboard utama;
     *   dashboard role tambahan diakses melalui menu role masing-masing.
     */
    public function resolve(User $user): DashboardBuilderInterface
    {
        if ($simulated = $user->getSimulatedRole()) {
            return $this->resolveForRole($user, $simulated);
        }

        $user->loadMissing('roles');

        if ($user->hasRole('superadmin')) {
            return app(SuperadminDashboardBuilder::class);
        }

        if ($user->hasRole('admin')) {
            return app(AdminDashboardBuilder::class);
        }

        if ($user->roles->count() > 1) {
            return app(EmployeeDashboardBuilder::class);
        }

        return $this->resolveForRole($user, (string) ($user->roles->first()?->name ?? 'user'));
    }

    /**
     * Resolve builder untuk dashboard role tertentu.
     */
    public function resolveForRole(User $user, string $role): DashboardBuilderInterface
    {
        return match ($role) {
            'superadmin' => app(SuperadminDashboardBuilder::class),
            'admin' => $user->hasRole('superadmin')
                ? app(SuperadminDashboardBuilder::class)
                : app(AdminDashboardBuilder::class),
            'kepala_sekolah' => app(KepalaSekolahDashboardBuilder::class),
            'kurikulum' => app(KurikulumDashboardBuilder::class),
            'tata_usaha' => app(TataUsahaDashboardBuilder::class),
            default => app(EmployeeDashboardBuilder::class),
        };
    }
}
