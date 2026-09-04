<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaintenanceController extends Controller
{
    /**
     * Tampilkan Halaman Pemeliharaan (Maintenance Page).
     */
    public function index(Request $request)
    {
        $settings = Setting::whereIn('key', [
            'maintenance_mode',
            'maintenance_title',
            'maintenance_message',
            'maintenance_end_time',
        ])->pluck('value', 'key')->toArray();

        $isActive = ($settings['maintenance_mode'] ?? '0') === '1' || ($settings['maintenance_mode'] ?? '0') === 'true';

        $user = $request->user();
        $isActualSuperadmin = $user && method_exists($user, 'isActualSuperadmin') && $user->isActualSuperadmin();

        // Jika maintenance TIDAK aktif dan pengunjung bukan superadmin, arahkan ke halaman utama
        if (!$isActive && !$isActualSuperadmin) {
            return redirect()->route('welcome');
        }

        return Inertia::render('Maintenance', [
            'title' => $settings['maintenance_title'] ?? 'Sistem Sedang Dalam Pemeliharaan',
            'message' => $settings['maintenance_message'] ?? 'Kami sedang melakukan pemeliharaan rutin dan peningkatan performa sistem habit tracker. Mohon maaf atas ketidaknyamanan Anda. Sistem akan segera kembali normal.',
            'endTime' => $settings['maintenance_end_time'] ?? null,
            'isActive' => $isActive,
            'isActualSuperadmin' => $isActualSuperadmin,
            'simulatedRole' => $user ? $user->getSimulatedRole() : null,
        ]);
    }

    /**
     * Beralih peran simulasi untuk ujicoba Super Admin di mode maintenance.
     */
    public function switchRole(Request $request)
    {
        $user = $request->user();
        if (!$user || !method_exists($user, 'isActualSuperadmin') || !$user->isActualSuperadmin()) {
            abort(403, 'Hanya Super Admin yang dapat mengganti peran simulasi.');
        }

        $validated = $request->validate([
            'role' => ['required', 'in:superadmin,admin,user'],
        ]);

        $role = $validated['role'];
        $previousUrl = url()->previous();

        if ($role === 'superadmin') {
            $request->session()->forget('maintenance_simulated_role');
            return redirect()->back()->with('success', 'Kembali ke peran asli Super Admin.');
        }

        $request->session()->put('maintenance_simulated_role', $role);

        $roleLabels = [
            'admin' => 'Admin',
            'user'  => 'Pegawai (User Biasa)',
        ];

        // Jika beralih ke peran 'user' dan sebelumnya berada di halaman admin,
        // arahkan dengan aman ke dashboard agar tidak terkena larangan akses (403)
        $isAdminUrl = str_contains($previousUrl, '/admin') && !str_contains($previousUrl, '/admin/maintenance');
        if ($role === 'user' && $isAdminUrl) {
            return redirect()->route('dashboard')->with('success', 'Simulasi peran aktif: Pegawai (User). Dialihkan ke Dashboard.');
        }

        // Jika beralih ke peran 'admin' dan sebelumnya berada di halaman superadmin-only (login-logs)
        if ($role === 'admin' && str_contains($previousUrl, '/admin/login-logs')) {
            return redirect()->route('admin.laporan')->with('success', 'Simulasi peran aktif: Admin.');
        }

        return redirect()->back()->with('success', 'Simulasi peran aktif: ' . ($roleLabels[$role] ?? $role));
    }

    /**
     * Toggle cepat mode maintenance oleh Super Admin.
     */
    public function toggle(Request $request)
    {
        $user = $request->user();
        if (!$user || !method_exists($user, 'isActualSuperadmin') || !$user->isActualSuperadmin()) {
            abort(403, 'Hanya Super Admin yang dapat mengubah status pemeliharaan.');
        }

        $current = Setting::where('key', 'maintenance_mode')->value('value');
        $targetMode = $request->has('target_mode') 
            ? ($request->boolean('target_mode') ? '1' : '0')
            : (($current === '1' || $current === 'true') ? '0' : '1');

        Setting::updateOrCreate(
            ['key' => 'maintenance_mode'],
            ['value' => $targetMode]
        );

        if ($request->has('maintenance_title')) {
            Setting::updateOrCreate(['key' => 'maintenance_title'], ['value' => $request->input('maintenance_title')]);
        }
        if ($request->has('maintenance_message')) {
            Setting::updateOrCreate(['key' => 'maintenance_message'], ['value' => $request->input('maintenance_message')]);
        }
        if ($request->has('maintenance_end_time')) {
            Setting::updateOrCreate(['key' => 'maintenance_end_time'], ['value' => $request->input('maintenance_end_time')]);
        }

        $statusMsg = $targetMode === '1' 
            ? 'Mode Maintenance berhasil DIAKTIFKAN. Seluruh user non-superadmin sekarang dialihkan ke halaman pemeliharaan.' 
            : 'Mode Maintenance berhasil DINONAKTIFKAN. Seluruh pengguna dapat mengakses sistem normal kembali.';

        return redirect()->back()->with('success', $statusMsg);
    }
}
