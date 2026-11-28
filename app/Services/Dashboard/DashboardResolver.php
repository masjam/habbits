<?php

namespace App\Services\Dashboard;

use App\Models\User;

class DashboardResolver
{
    /**
     * Aturan prioritas multi-role untuk /dashboard utama:
     * superadmin > admin > kepala_sekolah > kurikulum > tata_usaha > user
     * (Koperasi memiliki halaman dashboard sendiri di /koperasi/dashboard).
     */
    public function resolve(User $user): DashboardBuilderInterface
    {
        if ($user->hasRole('superadmin')) {
            return app(SuperadminDashboardBuilder::class);
        }
        if ($user->hasRole('admin')) {
            return app(AdminDashboardBuilder::class);
        }
        if ($user->hasRole('kepala_sekolah')) {
            return app(KepalaSekolahDashboardBuilder::class);
        }
        if ($user->hasRole('kurikulum')) {
            return app(KurikulumDashboardBuilder::class);
        }
        if ($user->hasRole('tata_usaha')) {
            return app(TataUsahaDashboardBuilder::class);
        }

        return app(EmployeeDashboardBuilder::class);
    }
}
