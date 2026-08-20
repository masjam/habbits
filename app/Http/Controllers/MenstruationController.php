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

            // Set skor menjadi 0 untuk habit yang disembunyikan saat haid pada hari ini,
            // agar skor total tidak melebihi skor maksimal harian saat mode haid aktif.
            $hiddenHabits = \App\Models\Habit::where('hide_saat_haid', true)->pluck('id');
            \App\Models\HabitLog::where('user_id', $user->id)
                ->whereDate('tanggal', Carbon::today())
                ->whereIn('habit_id', $hiddenHabits)
                ->update(['skor_diperoleh' => 0]);

            $msg = 'Mode Haid berhasil diaktifkan.';
        }

        return redirect()->route('haid.index')->with('success', $msg);
    }
}
