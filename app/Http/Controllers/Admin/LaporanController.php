<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LaporanController extends Controller
{
    /**
     * Tampilkan halaman Leaderboard (semua pegawai bulan ini).
     */
    public function index(Request $request)
    {
        $now = Carbon::now();
        $month = $request->input('month', $now->month);
        $year = $request->input('year', $now->year);
        $search = $request->input('search');

        $date = Carbon::create($year, $month, 1);
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth   = $date->copy()->endOfMonth();
        $daysInMonth  = $date->daysInMonth;

        // Ambil target skor bulanan dari pengaturan, default 80
        $settingTarget = Setting::where('key', 'monthly_target_score')->first();
        $targetBulanan = $settingTarget ? (float) $settingTarget->value : 80.0;

        // Skor maksimal harian berdasarkan database
        $dailyMaxScore = \App\Models\Habit::where('status_aktif', true)
            ->where('is_pengganti_haid', false)
            ->sum('skor_maksimal') ?: 100;
        $skorMaksimalSebulan = $dailyMaxScore * $daysInMonth;

        // Ambil data semua pegawai (role user) beserta pencarian dan pagination
        $usersQuery = User::role('user')
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->withSum(
                ['habitLogs' => fn($q) => $q->whereBetween('tanggal', [$startOfMonth, $endOfMonth])],
                'skor_diperoleh'
            )
            ->orderByRaw('COALESCE(habit_logs_sum_skor_diperoleh, 0) DESC');

        $paginatedUsers = $usersQuery->paginate(10)->withQueryString();

        $paginatedUsers->getCollection()->transform(function ($user) use ($skorMaksimalSebulan) {
            $skorDiperoleh = (int) $user->habit_logs_sum_skor_diperoleh;
            $persentase = ($skorMaksimalSebulan > 0) ? ($skorDiperoleh / $skorMaksimalSebulan) * 100 : 0;

            return [
                'id'         => $user->id,
                'name'       => $user->name,
                'gender'     => $user->gender,
                'skor'       => $skorDiperoleh,
                'persentase' => round($persentase, 1),
            ];
        });

        return Inertia::render('Admin/Laporan/Index', [
            'leaderboard'         => $paginatedUsers,
            'targetBulanan'       => $targetBulanan,
            'skorMaksimalSebulan' => $skorMaksimalSebulan,
            'namaBulan'           => $date->translatedFormat('F Y'),
            'filters'             => [
                'month'  => (int) $month,
                'year'   => (int) $year,
                'search' => $search,
            ]
        ]);
    }

    /**
     * Unduh Laporan Leaderboard dalam format Excel.
     */
    public function export(Request $request)
    {
        $now = Carbon::now();
        $month = $request->input('month', $now->month);
        $year = $request->input('year', $now->year);

        $date = Carbon::create($year, $month, 1);
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth   = $date->copy()->endOfMonth();
        $daysInMonth  = $date->daysInMonth;
        
        $dailyMaxScore = \App\Models\Habit::where('status_aktif', true)
            ->where('is_pengganti_haid', false)
            ->sum('skor_maksimal') ?: 100;
        $skorMaksimalSebulan = $dailyMaxScore * $daysInMonth;

        $users = User::role('user')
            ->withSum(
                ['habitLogs' => fn($q) => $q->whereBetween('tanggal', [$startOfMonth, $endOfMonth])],
                'skor_diperoleh'
            )
            ->get();

        $leaderboard = $users->map(function ($user) use ($skorMaksimalSebulan) {
            $skorDiperoleh = (int) $user->habit_logs_sum_skor_diperoleh;
            $persentase = ($skorMaksimalSebulan > 0) ? ($skorDiperoleh / $skorMaksimalSebulan) * 100 : 0;

            return [
                'id'         => $user->id,
                'name'       => $user->name,
                'gender'     => $user->gender,
                'skor'       => $skorDiperoleh,
                'persentase' => round($persentase, 1),
            ];
        })->sortByDesc('persentase')->values();

        $namaBulan = $date->translatedFormat('F Y');
        $fileName = 'Laporan_Ibadah_Pegawai_' . $date->format('Y_m') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\LaporanExport($leaderboard, $skorMaksimalSebulan, $namaBulan),
            $fileName
        );
    }

    /**
     * Update target skor bulanan.
     */
    public function updateTarget(Request $request)
    {
        $request->validate([
            'target' => 'required|numeric|min:0|max:100',
        ]);

        Setting::updateOrCreate(
            ['key' => 'monthly_target_score'],
            ['value' => (string) $request->target]
        );

        return redirect()->back()->with('success', 'Target bulanan berhasil diperbarui.');
    }

    /**
     * Tampilkan detail progres per habit untuk seorang user.
     */
    public function detail(User $user, Request $request)
    {
        // Pastikan yg dibuka adalah user
        if (!$user->hasRole('user')) {
            abort(404);
        }

        $now = Carbon::now();
        $month = $request->input('month', $now->month);
        $year = $request->input('year', $now->year);

        $date = Carbon::create($year, $month, 1);
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth   = $date->copy()->endOfMonth();
        $daysInMonth  = $date->daysInMonth;

        // ─── Progres Bulan Berjalan ───
        $skorBulanIni = HabitLog::where('user_id', $user->id)
            ->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
            ->sum('skor_diperoleh');
            
        $dailyMaxScore = \App\Models\Habit::where('status_aktif', true)
            ->where('is_pengganti_haid', false)
            ->sum('skor_maksimal') ?: 100;
            
        $skorMaksimalSebulan = $dailyMaxScore * $daysInMonth;
        $persentaseBulanIni = ($skorMaksimalSebulan > 0) ? ($skorBulanIni / $skorMaksimalSebulan) * 100 : 0;

        // ─── Progres Semester Berjalan ───
        $currentMonth = $now->month;
        $isSemester1  = $currentMonth <= 6;
        $startMonth   = $isSemester1 ? 1 : 7;
        $endMonth     = $isSemester1 ? 6 : 12;

        $startOfSemester = Carbon::create($now->year, $startMonth, 1)->startOfMonth();
        $endOfSemester   = Carbon::create($now->year, $endMonth, 1)->endOfMonth();
        
        $skorSemesterIni = HabitLog::where('user_id', $user->id)
            ->whereBetween('tanggal', [$startOfSemester, $endOfSemester])
            ->sum('skor_diperoleh');
        
        $totalHariSemester = $startOfSemester->diffInDays($endOfSemester) + 1;
        $skorMaksimalSemester = $dailyMaxScore * $totalHariSemester;
        $persentaseSemesterIni = ($skorMaksimalSemester > 0) ? ($skorSemesterIni / $skorMaksimalSemester) * 100 : 0;

        // ─── Progres Per Habit (Bulan Ini) ───
        // Ambil habit sesuai urutan admin, filter is_pengganti_haid untuk laki-laki
        $habitsQuery = Habit::orderBy('urutan')->orderBy('id');

        // Untuk user laki-laki, sembunyikan habit pengganti haid
        if ($user->gender === 'L') {
            $habitsQuery->where('is_pengganti_haid', false);
        }

        $habits = $habitsQuery->get();
        $habitProgress = [];

        // Ambil semua log habit bulan ini lalu kelompokkan berdasarkan habit_id
        $habitLogSums = HabitLog::selectRaw('habit_id, SUM(skor_diperoleh) as total_skor')
            ->where('user_id', $user->id)
            ->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
            ->groupBy('habit_id')
            ->pluck('total_skor', 'habit_id');

        foreach ($habits as $habit) {
            $skorDiperolehHabit = $habitLogSums->get($habit->id) ?? 0;

            // Maksimal skor habit ini dalam sebulan = skor_maksimal * jumlah_hari
            $skorMaksimalHabitSebulan = $habit->skor_maksimal * $daysInMonth;
            
            $persentase = ($skorMaksimalHabitSebulan > 0) ? ($skorDiperolehHabit / $skorMaksimalHabitSebulan) * 100 : 0;

            $habitProgress[] = [
                'id'         => $habit->id,
                'nama'       => $habit->nama_habit,
                'is_haid'    => $habit->is_pengganti_haid,
                'persentase' => round($persentase, 1),
            ];
        }

        return Inertia::render('Admin/Laporan/Detail', [
            'pegawai'            => ['id' => $user->id, 'name' => $user->name, 'gender' => $user->gender],
            'persentaseBulanIni' => round($persentaseBulanIni, 1),
            'persentaseSemester' => round($persentaseSemesterIni, 1),
            'semesterName'       => $isSemester1 ? 'Semester 1' : 'Semester 2',
            'habitProgress'      => $habitProgress, // urutan sudah dijaga dari query
            'namaBulan'          => $date->translatedFormat('F Y'),
            'filters'            => [
                'month' => (int) $month,
                'year'  => (int) $year,
            ]
        ]);
    }
}
