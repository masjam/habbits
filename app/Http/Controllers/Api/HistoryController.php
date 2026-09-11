<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Fetch the sum of scores per day for the authenticated user
        // Ordered by newest date first, limited to the last 60 days
        $history = DB::table('habit_logs')
            ->select(DB::raw('DATE(tanggal) as tanggal'), DB::raw('SUM(skor_diperoleh) as total_skor'))
            ->where('user_id', $user->id)
            ->groupBy(DB::raw('DATE(tanggal)'))
            ->orderBy('tanggal', 'desc')
            ->limit(60)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Data riwayat berhasil diambil',
            'data' => $history
        ]);
    }

    public function show(Request $request, $date)
    {
        $user = Auth::user();

        // Get all active habits
        $habits = \App\Models\Habit::where('status_aktif', true)
            ->orderBy('urutan')
            ->get();

        // Get logs for the specified date
        $logs = \App\Models\HabitLog::where('user_id', $user->id)
            ->whereDate('tanggal', $date)
            ->get()
            ->keyBy('habit_id');

        $details = [];
        foreach ($habits as $habit) {
            $log = $logs->get($habit->id);
            $details[] = [
                'habit_id' => $habit->id,
                'nama_habit' => $habit->nama_habit,
                'skor_maksimal' => $habit->skor_maksimal,
                'is_done' => $log != null,
                'skor_diperoleh' => $log ? $log->skor_diperoleh : 0,
                'nilai_input' => $log ? $log->nilai_input : null,
                'satuan' => $habit->satuan,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail riwayat berhasil diambil',
            'data' => [
                'tanggal' => $date,
                'details' => $details
            ]
        ]);
    }
}
