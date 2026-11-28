<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $settings = \App\Models\Setting::allCached();
        $allowedSettingKeys = [
            'maintenance_mode',
            'maintenance_title',
            'maintenance_message',
            'maintenance_end_time',
            'dark_mode_active',
            'app_name',
            'feature_presensi',
            'feature_habit',
            'feature_jurnal',
            'feature_administrasi_guru',
            'feature_arsip',
            'feature_kpi',
            'feature_divisi',
            'feature_badges',
            'feature_cuti',
            'feature_idcard',
            'feature_notes',
            'attendance_mode_gps',
            'attendance_mode_rfid',
            'attendance_mode_fingerprint',
            'gamification_active',
            'monthly_target_score',
            'presensi_work_start',
            'presensi_work_end',
            'announcement_text',
            'popup_active',
            'popup_text',
            'youtube_link',
            'running_text',
        ];
        $publicSettings = array_intersect_key($settings, array_flip($allowedSettingKeys));

        $user = $request->user();
        if ($user) {
            $user->loadMissing('roles');
        }

        $isKpiApprover = $user && app(\App\Policies\PerformanceEvaluationPolicy::class)->isApprover($user);

        $hasKpiAssignments = false;
        $pendingKpiApprovals = 0;
        if ($user) {
            try {
                $hasKpiAssignments = \App\Models\PerformanceAssignment::active()->where('assessor_id', $user->id)->exists();
                if ($isKpiApprover) {
                    $pendingKpiApprovals = \App\Models\PerformanceEvaluation::submitted()->count();
                }
            } catch (\Throwable) {
                // Abaikan bila migrasi tabel delegasi KPI belum dijalankan
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user'  => $user,
                'roles' => $user ? $user->getRoleNames() : [],
                'is_actual_superadmin' => $user ? $user->isActualSuperadmin() : false,
                'simulated_role' => $user ? $user->getSimulatedRole() : null,
                'multi_accounts' => $request->session()->get('multi_accounts', []),
                'is_adding_account' => $request->session()->get('is_adding_account', false),
            ],
            'kpi_nav' => [
                'has_assignments'   => $hasKpiAssignments,
                'is_approver'       => $isKpiApprover,
                'pending_approvals' => $pendingKpiApprovals,
            ],
            'global_settings' => $publicSettings,
            'webpush' => [
                'vapid_public_key' => config('webpush.vapid.public_key'),
                'is_active' => ($settings['push_notifications_active'] ?? '0') === '1' || ($settings['push_notifications_active'] ?? 'false') === 'true',
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
            'notifications' => [
                'unread_count' => $user ? $user->getCachedUnreadNotificationsCount() : 0,
                'latest' => $user ? $user->getCachedLatestNotifications() : [],
            ],
        ];
    }
}
