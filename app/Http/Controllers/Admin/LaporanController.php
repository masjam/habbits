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
        $perPage = $request->input('per_page', 10);
        $kategori_skor = $request->input('kategori_skor');
        $divisi = $request->input('divisi');

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
        $usersQuery = User::role('user')->with('badges')
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($divisi, function ($query, $divisi) {
                $query->where('divisi', $divisi);
            })
            ->withSum(
                ['habitLogs' => fn($q) => $q->whereBetween('tanggal', [$startOfMonth, $endOfMonth])],
                'skor_diperoleh'
            )
            ->when($kategori_skor, function ($query, $kategori) use ($skorMaksimalSebulan, $targetBulanan, $startOfMonth, $endOfMonth) {
                
                // Build CASE statement for division targets
                $divisions = \App\Models\Division::whereNotNull('target_divisi')->get();
                $divisionCases = "";
                foreach($divisions as $div) {
                    $name = addslashes($div->name);
                    $divisionCases .= " WHEN users.divisi = '{$name}' THEN {$div->target_divisi} ";
                }
                $divisionCaseSql = $divisionCases ? "CASE $divisionCases ELSE NULL END" : "NULL";

                // Gunakan target dinamis admin: target khusus inaktif > target divisi > target global
                $userTargetSql = "COALESCE(
                    CASE WHEN status_kehadiran != 'Aktif' THEN target_tidak_aktif ELSE NULL END,
                    $divisionCaseSql,
                    $targetBulanan
                )";

                $scoreSql = "(SELECT COALESCE(SUM(skor_diperoleh), 0) FROM habit_logs WHERE habit_logs.user_id = users.id AND tanggal BETWEEN ? AND ?)";
                
                if ($kategori === '0-30') {
                    $query->whereRaw("$scoreSql <= (0.30 * $skorMaksimalSebulan)", [$startOfMonth, $endOfMonth]);
                } elseif ($kategori === '30-50') {
                    $query->whereRaw("$scoreSql > (0.30 * $skorMaksimalSebulan)", [$startOfMonth, $endOfMonth])
                          ->whereRaw("$scoreSql <= (0.50 * $skorMaksimalSebulan)", [$startOfMonth, $endOfMonth]);
                } elseif ($kategori === '50-target') {
                    $query->whereRaw("$scoreSql > (0.50 * $skorMaksimalSebulan)", [$startOfMonth, $endOfMonth])
                          ->whereRaw("$scoreSql < (($userTargetSql) / 100 * $skorMaksimalSebulan)", [$startOfMonth, $endOfMonth]);
                } elseif ($kategori === 'tercapai') {
                    $query->whereRaw("$scoreSql >= (($userTargetSql) / 100 * $skorMaksimalSebulan)", [$startOfMonth, $endOfMonth]);
                }
            })
            ->orderByRaw('COALESCE(habit_logs_sum_skor_diperoleh, 0) DESC');

        $paginatedUsers = $usersQuery->paginate($perPage)->withQueryString();

        $divisionsLookup = \App\Models\Division::pluck('target_divisi', 'name')->toArray();

        $paginatedUsers->getCollection()->transform(function ($user) use ($skorMaksimalSebulan, $targetBulanan, $divisionsLookup) {
            $skorDiperoleh = (int) $user->habit_logs_sum_skor_diperoleh;
            $persentase = ($skorMaksimalSebulan > 0) ? ($skorDiperoleh / $skorMaksimalSebulan) * 100 : 0;
            
            $userTarget = $targetBulanan;
            
            $divisionTarget = null;
            if (!empty($user->divisi) && isset($divisionsLookup[$user->divisi])) {
                $divisionTarget = $divisionsLookup[$user->divisi];
            }

            if ($user->status_kehadiran !== 'Aktif' && !is_null($user->target_tidak_aktif)) {
                $userTarget = (float) $user->target_tidak_aktif;
            } elseif (!is_null($divisionTarget)) {
                $userTarget = (float) $divisionTarget;
            }

            return [
                'id'               => $user->id,
                'name'             => $user->name,
                'gender'           => $user->gender,
                'divisi'           => $user->divisi,
                'status'           => $user->status_kehadiran,
                'badges'           => $user->badges,
                'skor'             => $skorDiperoleh,
                'persentase'       => round($persentase, 1),
                'target'           => $userTarget,
            ];
        });

        $divisis = \App\Models\Division::orderBy('name')->pluck('name');

        return Inertia::render('Admin/Laporan/Index', [
            'leaderboard'         => $paginatedUsers,
            'targetBulanan'       => $targetBulanan,
            'skorMaksimalSebulan' => $skorMaksimalSebulan,
            'namaBulan'           => $date->translatedFormat('F Y'),
            'divisis'             => $divisis,
            'filters'             => [
                'month'  => (int) $month,
                'year'   => (int) $year,
                'search' => $search,
                'per_page' => (int) $perPage,
                'kategori_skor' => $kategori_skor,
                'divisi' => $divisi,
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
        $search = $request->input('search');
        $kategori_skor = $request->input('kategori_skor');

        $date = Carbon::create($year, $month, 1);
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth   = $date->copy()->endOfMonth();
        $daysInMonth  = $date->daysInMonth;
        
        $dailyMaxScore = \App\Models\Habit::where('status_aktif', true)
            ->where('is_pengganti_haid', false)
            ->sum('skor_maksimal') ?: 100;
        $skorMaksimalSebulan = $dailyMaxScore * $daysInMonth;

        $settingTarget = Setting::where('key', 'monthly_target_score')->first();
        $targetBulanan = $settingTarget ? (float) $settingTarget->value : 80.0;

        $users = User::role('user')
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($request->input('divisi'), function ($query, $divisi) {
                $query->where('divisi', $divisi);
            })
            ->withSum(
                ['habitLogs' => fn($q) => $q->whereBetween('tanggal', [$startOfMonth, $endOfMonth])],
                'skor_diperoleh'
            )
            ->when($kategori_skor, function ($query, $kategori) use ($skorMaksimalSebulan, $targetBulanan, $startOfMonth, $endOfMonth) {
                // Build CASE statement for division targets
                $divisions = \App\Models\Division::whereNotNull('target_divisi')->get();
                $divisionCases = "";
                foreach($divisions as $div) {
                    $name = addslashes($div->name);
                    $divisionCases .= " WHEN users.divisi = '{$name}' THEN {$div->target_divisi} ";
                }
                $divisionCaseSql = $divisionCases ? "CASE $divisionCases ELSE NULL END" : "NULL";

                // Gunakan target dinamis admin: target khusus inaktif > target divisi > target global
                $userTargetSql = "COALESCE(
                    CASE WHEN status_kehadiran != 'Aktif' THEN target_tidak_aktif ELSE NULL END,
                    $divisionCaseSql,
                    $targetBulanan
                )";

                $scoreSql = "(SELECT COALESCE(SUM(skor_diperoleh), 0) FROM habit_logs WHERE habit_logs.user_id = users.id AND tanggal BETWEEN ? AND ?)";
                
                if ($kategori === '0-30') {
                    $query->whereRaw("$scoreSql <= (0.30 * $skorMaksimalSebulan)", [$startOfMonth, $endOfMonth]);
                } elseif ($kategori === '30-50') {
                    $query->whereRaw("$scoreSql > (0.30 * $skorMaksimalSebulan)", [$startOfMonth, $endOfMonth])
                          ->whereRaw("$scoreSql <= (0.50 * $skorMaksimalSebulan)", [$startOfMonth, $endOfMonth]);
                } elseif ($kategori === '50-target') {
                    $query->whereRaw("$scoreSql > (0.50 * $skorMaksimalSebulan)", [$startOfMonth, $endOfMonth])
                          ->whereRaw("$scoreSql < (($userTargetSql) / 100 * $skorMaksimalSebulan)", [$startOfMonth, $endOfMonth]);
                } elseif ($kategori === 'tercapai') {
                    $query->whereRaw("$scoreSql >= (($userTargetSql) / 100 * $skorMaksimalSebulan)", [$startOfMonth, $endOfMonth]);
                }
            })
            ->get();

        $divisionsLookup = \App\Models\Division::pluck('target_divisi', 'name')->toArray();

        $leaderboard = $users->map(function ($user) use ($skorMaksimalSebulan, $targetBulanan, $divisionsLookup) {
            $skorDiperoleh = (int) $user->habit_logs_sum_skor_diperoleh;
            $persentase = ($skorMaksimalSebulan > 0) ? ($skorDiperoleh / $skorMaksimalSebulan) * 100 : 0;
            
            $userTarget = $targetBulanan;
            
            $divisionTarget = null;
            if (!empty($user->divisi) && isset($divisionsLookup[$user->divisi])) {
                $divisionTarget = $divisionsLookup[$user->divisi];
            }

            if ($user->status_kehadiran !== 'Aktif' && !is_null($user->target_tidak_aktif)) {
                $userTarget = (float) $user->target_tidak_aktif;
            } elseif (!is_null($divisionTarget)) {
                $userTarget = (float) $divisionTarget;
            }

            return [
                'id'         => $user->id,
                'name'       => $user->name,
                'gender'     => $user->gender,
                'skor'       => $skorDiperoleh,
                'persentase' => round($persentase, 1),
                'target'     => $userTarget,
                'status'     => $user->status_kehadiran,
            ];
        })->sortByDesc('persentase')->values();

        $namaBulan = $date->translatedFormat('F Y');
        $type = $request->input('type', 'excel');

        if ($type === 'pdf') {
            $fileName = 'Laporan_Ibadah_Pegawai_' . $date->format('Y_m') . '.pdf';
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.laporan_pdf', [
                'leaderboard' => $leaderboard,
                'skorMaksimal' => $skorMaksimalSebulan,
                'namaBulan' => $namaBulan,
                'targetBulanan' => $targetBulanan
            ]);
            return $pdf->download($fileName);
        }

        $fileName = 'Laporan_Ibadah_Pegawai_' . $date->format('Y_m') . '.xlsx';
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\LaporanExport($leaderboard, $skorMaksimalSebulan, $namaBulan, $targetBulanan),
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

        // Ambil semua log habit bulan ini
        $habitLogs = HabitLog::select('habit_id', 'tanggal', 'skor_diperoleh')
            ->where('user_id', $user->id)
            ->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
            ->get();

        $dailyScores = [];
        $totalDailyScores = array_fill(1, $daysInMonth, 0);

        foreach ($habits as $habit) {
            $dailyScores[$habit->id] = array_fill(1, $daysInMonth, 0);
        }

        foreach ($habitLogs as $log) {
            $day = Carbon::parse($log->tanggal)->day;
            if (isset($dailyScores[$log->habit_id])) {
                $dailyScores[$log->habit_id][$day] += $log->skor_diperoleh;
                $totalDailyScores[$day] += $log->skor_diperoleh;
            }
        }

        $dailyHabitProgress = [];
        foreach ($habits as $habit) {
            $dailyHabitProgress[] = [
                'id' => $habit->id,
                'nama' => $habit->nama_habit,
                'data' => array_values($dailyScores[$habit->id]),
            ];
        }

        return Inertia::render('Admin/Laporan/Detail', [
            'pegawai'            => ['id' => $user->id, 'name' => $user->name, 'gender' => $user->gender],
            'persentaseBulanIni' => round($persentaseBulanIni, 1),
            'persentaseSemester' => round($persentaseSemesterIni, 1),
            'semesterName'       => $isSemester1 ? 'Semester 1' : 'Semester 2',
            'dailyHabitProgress' => $dailyHabitProgress,
            'totalDailyScores'   => array_values($totalDailyScores),
            'daysInMonth'        => $daysInMonth,
            'namaBulan'          => $date->translatedFormat('F Y'),
            'filters'            => [
                'month' => (int) $month,
                'year'  => (int) $year,
            ]
        ]);
    }
}
