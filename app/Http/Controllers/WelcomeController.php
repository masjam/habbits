<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Models\Setting;
use App\Models\HabitLog;
use Carbon\Carbon;

class WelcomeController extends Controller
{
    public function index()
    {
        $settings = Setting::pluck('value', 'key')->toArray();
        
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
