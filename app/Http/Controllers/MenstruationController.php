<?php

namespace App\Http\Controllers;

use App\Models\MenstruationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MenstruationController extends Controller
{
    /**
     * Menampilkan halaman manajemen Haid.
     */
    public function index()
    {
        $user = Auth::user();

        // Keamanan: Pastikan hanya user wanita yang bisa mengakses halaman ini
        if ($user->gender !== 'P') {
            return redirect()->route('dashboard')->withErrors('Halaman ini khusus untuk pengguna wanita.');
        }

        // Cek status saat ini
        $activeLog = MenstruationLog::where('user_id', $user->id)
            ->whereNull('waktu_selesai')
            ->first();

        // Ambil riwayat sebelumnya
        $historyLogs = MenstruationLog::where('user_id', $user->id)
            ->whereNotNull('waktu_selesai')
            ->orderBy('waktu_mulai', 'desc')
            ->get();

        return Inertia::render('Haid/Index', [
            'isSedangHaid' => $activeLog !== null,
            'activeLog'    => $activeLog,
            'historyLogs'  => $historyLogs,
        ]);
    }

    /**
     * Mengubah status (Mulai / Selesai Haid).
     */
    public function toggle(Request $request)
    {
        $user = Auth::user();

        if ($user->gender !== 'P') {
            abort(403, 'Unauthorized action.');
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
        }

        return redirect()->route('haid.index')->with('success', $msg);
    }
}
