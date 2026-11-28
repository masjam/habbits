<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LaporanFilterRequest;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\Setting;
use App\Models\User;
use App\Queries\HabitReportQuery;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LaporanController extends Controller
{
    /**
     * Tampilkan halaman Leaderboard (semua pegawai bulan ini).
     */
    public function index(LaporanFilterRequest $request, HabitReportQuery $reportQuery)
    {
        $filters = $request->validatedFilters();
        $ctx = $reportQuery->resolveContext($filters);

        $paginatedUsers = $reportQuery->baseQuery($filters, $ctx)
            ->paginate($filters['per_page'])
            ->withQueryString();

        $divisionsLookup = \App\Models\Division::getTargetsMap();
        $skorMaksimalSebulan = $ctx['skorMaksimalSebulan'];
        $targetBulanan = $ctx['targetBulanan'];

        $paginatedUsers->getCollection()->transform(function ($user) use ($skorMaksimalSebulan, $targetBulanan, $divisionsLookup) {
            $skorDiperoleh = (int) $user->habit_logs_sum_skor_diperoleh;
            $persentase = ($skorMaksimalSebulan > 0) ? ($skorDiperoleh / $skorMaksimalSebulan) * 100 : 0;

            $userTarget = $targetBulanan;
            $divisionTarget = (!empty($user->divisi) && isset($divisionsLookup[$user->divisi]))
                ? $divisionsLookup[$user->divisi]
                : null;

            if ($user->status_kehadiran !== 'Aktif' && !is_null($user->target_tidak_aktif)) {
                $userTarget = (float) $user->target_tidak_aktif;
            } elseif (!is_null($divisionTarget)) {
                $userTarget = (float) $divisionTarget;
            }

            return [
                'id'               => $user->id,
                'name'             => $user->name,
                'nip'              => $user->nip,
                'gender'           => $user->gender,
                'divisi'           => $user->divisi,
                'status'           => $user->status_kehadiran,
                'badges'           => $user->badges,
                'skor'             => $skorDiperoleh,
                'persentase'       => round($persentase, 1),
                'target'           => $userTarget,
                'last_filled_at'   => $user->last_filled_at ? Carbon::parse($user->last_filled_at)->format('d M Y') : null,
            ];
        });

        $divisis = \App\Models\Division::orderBy('name')->pluck('name');

        return Inertia::render('Admin/Laporan/Index', [
            'leaderboard'         => $paginatedUsers,
            'summary'             => $reportQuery->summary($filters, $ctx),
            'targetBulanan'       => $targetBulanan,
            'skorMaksimalSebulan' => $skorMaksimalSebulan,
            'namaBulan'           => $ctx['namaBulan'],
            'divisis'             => $divisis,
            'filters'             => $filters,
        ]);
    }

    /**
     * Unduh Laporan Leaderboard dalam format Excel/PDF.
     */
    public function export(LaporanFilterRequest $request)
    {
        $f = $request->validatedFilters();
        $userToNotify = auth()->user();

        \App\Jobs\ProcessLaporanExport::dispatch(
            $f['month'],
            $f['year'],
            $f['search'],
            $f['kategori_skor'],
            $f['divisi'],
            $f['type'],
            $userToNotify
        );

        return redirect()->back()->with('success', 'Laporan sedang digenerate di background. Silakan cek menu Pesan/Notifikasi dalam beberapa menit untuk mengunduh.');
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
        // Pastikan yg dibuka bukan admin/superadmin
        if ($user->hasAnyRole(['admin', 'superadmin'])) {
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
            
        $dailyMaxScore = \App\Models\Habit::allActiveCached()
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

        // Ambil target skor dari pengaturan atau divisi
        $settingTargetValue = Setting::get('monthly_target_score');
        $targetBulanan = $settingTargetValue !== null ? (float) $settingTargetValue : 80.0;
        
        $userTarget = $targetBulanan;
        if ($user->status_kehadiran !== 'Aktif' && $user->target_tidak_aktif) {
            $userTarget = $user->target_tidak_aktif;
        } else {
            $targetsMap = \App\Models\Division::getTargetsMap();
            if (!empty($user->divisi) && isset($targetsMap[$user->divisi])) {
                $userTarget = $targetsMap[$user->divisi];
            }
        }

        return Inertia::render('Admin/Laporan/Detail', [
            'pegawai'            => ['id' => $user->id, 'name' => $user->name, 'gender' => $user->gender, 'target' => $userTarget],
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
    /**
     * Tampilkan data pegawai yang belum mengisi habit (skor 0) pada tanggal tertentu di bulan terpilih.
     * Hanya bisa diakses oleh superadmin.
     */
    public function unfilled(Request $request)
    {
        $now = Carbon::now();
        $month = $request->input('month', $now->month);
        $year = $request->input('year', $now->year);
        
        $date = Carbon::create($year, $month, 1);
        $startOfMonth = $date->copy()->startOfMonth();
        
        // Jangan tampilkan hari di masa depan
        $endOfMonth = $date->copy()->endOfMonth();
        if ($endOfMonth->isFuture()) {
            $endOfMonth = Carbon::today();
        }

        // Ambil semua pegawai aktif (bukan admin)
        $users = User::whereIn('id', User::getNonAdminUserIds())
            ->where('status_kehadiran', 'Aktif')
            ->orderBy('name')
            ->get();
            
        // Ambil semua log habit bulan ini
        $habitLogs = HabitLog::select('user_id', 'tanggal', 'skor_diperoleh')
            ->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
            ->get();
            
        // Kelompokkan skor harian per user
        $userDailyScores = [];
        foreach ($habitLogs as $log) {
            $day = Carbon::parse($log->tanggal)->day;
            if (!isset($userDailyScores[$log->user_id])) {
                $userDailyScores[$log->user_id] = [];
            }
            if (!isset($userDailyScores[$log->user_id][$day])) {
                $userDailyScores[$log->user_id][$day] = 0;
            }
            $userDailyScores[$log->user_id][$day] += $log->skor_diperoleh;
        }
        
        $totalDays = $startOfMonth->diffInDays($endOfMonth) + 1;
        $unfilledData = [];
        
        foreach ($users as $user) {
            $unfilledDates = [];
            for ($day = 1; $day <= $totalDays; $day++) {
                // Jika tanggal tersebut belum ada log sama sekali atau skor totalnya 0
                if (!isset($userDailyScores[$user->id][$day]) || $userDailyScores[$user->id][$day] == 0) {
                    $unfilledDates[] = $day;
                }
            }
            
            if (count($unfilledDates) > 0) {
                $unfilledData[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'divisi' => $user->divisi,
                    'unfilled_dates' => $unfilledDates,
                    'total_unfilled' => count($unfilledDates),
                ];
            }
        }
        
        // Urutkan berdasarkan yang paling banyak bolong
        usort($unfilledData, function($a, $b) {
            return $b['total_unfilled'] <=> $a['total_unfilled'];
        });

        return Inertia::render('Admin/Laporan/Unfilled', [
            'unfilledData' => $unfilledData,
            'namaBulan'    => $date->translatedFormat('F Y'),
            'filters'      => [
                'month' => (int) $month,
                'year'  => (int) $year,
            ]
        ]);
    }

    /**
     * Download data pegawai yang belum mengisi habit dalam bentuk Excel.
     */
    public function unfilledExport(Request $request)
    {
        $now = Carbon::now();
        $month = $request->input('month', $now->month);
        $year = $request->input('year', $now->year);
        
        $date = Carbon::create($year, $month, 1);
        $startOfMonth = $date->copy()->startOfMonth();
        
        $endOfMonth = $date->copy()->endOfMonth();
        if ($endOfMonth->isFuture()) {
            $endOfMonth = Carbon::today();
        }

        $users = User::whereIn('id', User::getNonAdminUserIds())
            ->where('status_kehadiran', 'Aktif')
            ->orderBy('name')
            ->get();
            
        $habitLogs = HabitLog::select('user_id', 'tanggal', 'skor_diperoleh')
            ->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
            ->get();
            
        $userDailyScores = [];
        foreach ($habitLogs as $log) {
            $day = Carbon::parse($log->tanggal)->day;
            if (!isset($userDailyScores[$log->user_id])) {
                $userDailyScores[$log->user_id] = [];
            }
            if (!isset($userDailyScores[$log->user_id][$day])) {
                $userDailyScores[$log->user_id][$day] = 0;
            }
            $userDailyScores[$log->user_id][$day] += $log->skor_diperoleh;
        }
        
        $totalDays = $startOfMonth->diffInDays($endOfMonth) + 1;
        $unfilledData = [];
        
        foreach ($users as $user) {
            $unfilledDates = [];
            for ($day = 1; $day <= $totalDays; $day++) {
                if (!isset($userDailyScores[$user->id][$day]) || $userDailyScores[$user->id][$day] == 0) {
                    $unfilledDates[] = $day;
                }
            }
            
            if (count($unfilledDates) > 0) {
                $unfilledData[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'divisi' => $user->divisi,
                    'unfilled_dates' => $unfilledDates,
                    'total_unfilled' => count($unfilledDates),
                ];
            }
        }
        
        usort($unfilledData, function($a, $b) {
            return $b['total_unfilled'] <=> $a['total_unfilled'];
        });

        $namaBulan = $date->translatedFormat('F Y');
        $now = now()->format('Ymd_His');
        $fileName = 'Laporan_Belum_Isi_Habit_' . $date->format('Y_m') . '_' . $now . '.xlsx';
        
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\UnfilledExport($unfilledData, $namaBulan),
            $fileName
        );
    }
}
