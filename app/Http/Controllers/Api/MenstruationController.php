<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenstruationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MenstruationController extends Controller
{
    /**
     * Mengubah status (Mulai / Selesai Haid).
     */
    public function toggle(Request $request)
    {
        $user = Auth::user();

        if ($user->gender !== 'P') {
            return response()->json([
                'success' => false,
                'message' => 'Halaman ini khusus untuk pengguna wanita.'
            ], 403);
        }

        // Cari apakah sedang haid
        $activeLog = MenstruationLog::where('user_id', $user->id)
            ->whereNull('waktu_selesai')
            ->first();

        if ($activeLog) {
            // Berarti sekarang sedang haid, kita hentikan (Selesai Haid)
            $activeLog->update([
                'waktu_selesai' => Carbon::now(),
            ]);
            $msg = 'Mode Haid berhasil dinonaktifkan.';
            $isActive = false;
        } else {
            // Berarti sekarang tidak haid, kita mulai (Mulai Haid)
            MenstruationLog::create([
                'user_id'     => $user->id,
                'waktu_mulai' => Carbon::now(),
            ]);

            // Saat mode haid diaktifkan: skor habit utama (hide_saat_haid) TIDAK dihapus.
            // Namun, total skor harian tidak boleh melebihi skor maksimal normal (non-haid).
            // Jika ada kelebihan, kurangi dari skor habit pengganti haid (jika ada).
            $skorMaksimalNormal = \App\Models\Habit::where('status_aktif', true)
                ->where('is_pengganti_haid', false)
                ->sum('skor_maksimal');

            $allLogsToday = \App\Models\HabitLog::where('user_id', $user->id)
                ->whereDate('tanggal', Carbon::today())
                ->get();

            $totalSkorHariIni = $allLogsToday->sum('skor_diperoleh');

            if ($totalSkorHariIni > $skorMaksimalNormal) {
                $kelebihan = $totalSkorHariIni - $skorMaksimalNormal;

                // Kurangi dari habit pengganti haid (skor terbesar dulu)
                $logsHabitPengganti = $allLogsToday
                    ->filter(fn($log) => \App\Models\Habit::find($log->habit_id)?->is_pengganti_haid)
                    ->sortByDesc('skor_diperoleh');

                foreach ($logsHabitPengganti as $log) {
                    if ($kelebihan <= 0) break;
                    $potong = min($log->skor_diperoleh, $kelebihan);
                    $log->skor_diperoleh -= $potong;
                    $log->save();
                    $kelebihan -= $potong;
                }
            }

            $msg = 'Mode Haid berhasil diaktifkan.';
            $isActive = true;
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'data' => [
                'is_active' => $isActive
            ]
        ]);
    }

    /**
     * Ambil riwayat haid.
     */
    public function history(Request $request)
    {
        $user = Auth::user();
        if ($user->gender !== 'P') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $historyLogs = MenstruationLog::where('user_id', $user->id)
            ->whereNotNull('waktu_selesai')
            ->orderBy('waktu_mulai', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $historyLogs
        ]);
    }

    /**
     * Mengubah manual waktu mulai dan selesai riwayat haid.
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $log = MenstruationLog::find($id);

        if (!$log || $user->gender !== 'P' || $log->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan atau tidak diizinkan.'], 403);
        }

        $request->validate([
            'waktu_mulai' => 'required|date',
            'waktu_selesai' => 'nullable|date|after_or_equal:waktu_mulai',
        ]);

        $log->update([
            'waktu_mulai' => Carbon::parse($request->waktu_mulai),
            'waktu_selesai' => $request->waktu_selesai ? Carbon::parse($request->waktu_selesai) : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat haid berhasil diperbarui.',
            'data' => $log
        ]);
    }
}
