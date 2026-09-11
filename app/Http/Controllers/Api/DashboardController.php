<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\MenstruationLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // 1. Identifikasi Mode Haid
        $isSedangHaid = false;
        if ($user->gender === 'P') {
            $isSedangHaid = MenstruationLog::where('user_id', $user->id)
                ->whereNull('waktu_selesai')
                ->exists();
        }

        // 2. Statistik Skor
        $today        = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth   = Carbon::now()->endOfMonth();

        $skorHariIni = HabitLog::where('user_id', $user->id)
            ->whereDate('tanggal', $today)
            ->sum('skor_diperoleh');

        $skorBulanIni = HabitLog::where('user_id', $user->id)
            ->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
            ->sum('skor_diperoleh');

        // 3. Cap & Target (Singkat)
        $skorMaksimalHariIni = Habit::where('status_aktif', true)
            ->when($isSedangHaid, function ($query) {
                $query->where('hide_saat_haid', false);
            }, function ($query) {
                $query->where('is_pengganti_haid', false);
            })
            ->sum('skor_maksimal');

        $daysInMonth = Carbon::now()->daysInMonth;
        $skorMaksimalBulanIni = $skorMaksimalHariIni * $daysInMonth;

        // 4. Target Bulanan
        $targetBulanan = $user->personal_target;
        if (is_null($targetBulanan)) {
            $settingTarget = \App\Models\Setting::where('key', 'monthly_target_score')->first();
            $adminTargetBulanan = $settingTarget ? (float) $settingTarget->value : 80.0;
            $targetBulanan = $adminTargetBulanan;
        }
        $targetSkorMinimal = (int) round($skorMaksimalBulanIni * ($targetBulanan / 100));

        // 5. Daily Chart Data (30 Hari Terakhir)
        $startDate30Days = Carbon::now()->subDays(29);
        $dailyRaw = DB::table('habit_logs')
            ->select(DB::raw('DATE(tanggal) as tanggal'), DB::raw('SUM(skor_diperoleh) as total_skor'))
            ->where('user_id', $user->id)
            ->where('tanggal', '>=', $startDate30Days->format('Y-m-d'))
            ->groupBy(DB::raw('DATE(tanggal)'))
            ->get()
            ->keyBy(fn($row) => Carbon::parse($row->tanggal)->format('Y-m-d'));

        $dailyChartData = [];
        for ($i = 0; $i < 30; $i++) {
            $date = $startDate30Days->copy()->addDays($i)->format('Y-m-d');
            $dailyChartData[] = [
                'tanggal' => $date,
                'skor'    => isset($dailyRaw[$date]) ? (int) $dailyRaw[$date]->total_skor : 0,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Data dashboard berhasil diambil',
            'data' => [
                'skorHariIni' => (int) $skorHariIni,
                'skorMaksimalHariIni' => (int) $skorMaksimalHariIni,
                'skorBulanIni' => (int) $skorBulanIni,
                'skorMaksimalBulanIni' => (int) $skorMaksimalBulanIni,
                'targetBulanan' => (float) $targetBulanan,
                'targetSkorMinimal' => (int) $targetSkorMinimal,
                'isSedangHaid' => $isSedangHaid,
                'dailyChartData' => $dailyChartData,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'gender' => $user->gender,
                    'current_streak' => $user->current_streak ?? 0,
                ],
            ]
        ]);
    }
}
