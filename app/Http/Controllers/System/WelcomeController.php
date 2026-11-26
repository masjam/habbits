<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Models\Setting;
use App\Models\HabitLog;
use Carbon\Carbon;

class WelcomeController extends Controller
{
    /**
     * Pengaturan yang boleh dilihat pengunjung tanpa login.
     * Halaman ini publik: props Inertia ikut tertulis di HTML, jadi JANGAN
     * mengirim seluruh tabel settings (berisi mis. kiosk_api_token).
     */
    private const PUBLIC_SETTING_KEYS = [
        'running_text',
        'popup_active',
        'popup_text',
        'youtube_link',
    ];

    public function index()
    {
        $settings = array_intersect_key(
            Setting::allCached(),
            array_flip(self::PUBLIC_SETTING_KEYS)
        );

        // Live Stats
        $currentMonth = Carbon::now()->startOfMonth();
        $totalLogsThisMonth = HabitLog::where('tanggal', '>=', $currentMonth)->count();
        $totalSkorThisMonth = HabitLog::where('tanggal', '>=', $currentMonth)->sum('skor_diperoleh');

        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'settings' => $settings,
            'liveStats' => [
                'logs' => $totalLogsThisMonth,
                'skor' => $totalSkorThisMonth,
            ]
        ]);
    }
}