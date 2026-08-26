<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\MenstruationLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Jika user adalah admin/superadmin, tampilkan Dashboard Admin (Aggregate)
        if ($user->hasAnyRole(['superadmin', 'admin'])) {
            return $this->adminDashboard($request);
        }

        return $this->userDashboard($request, $user);
    }

    /**
     * Dashboard khusus pengguna (Pegawai).
     */
    private function userDashboard(Request $request, User $user)
    {
        $targetUserId  = $user->id;

        // ─── 1. Identifikasi Mode Haid ─────────────────────────────────────────
        $isSedangHaid = false;
        if ($user->gender === 'P') {
            $isSedangHaid = MenstruationLog::where('user_id', $targetUserId)
                ->whereNull('waktu_selesai')
                ->exists();
        }

        // ─── 2. Statistik Skor ──────────────────────────────────────────────────
        $today        = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth   = Carbon::now()->endOfMonth();

        $skorHariIni = HabitLog::where('user_id', $targetUserId)
            ->whereDate('tanggal', $today)
            ->sum('skor_diperoleh');

        $skorBulanIni = HabitLog::where('user_id', $targetUserId)
            ->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
            ->sum('skor_diperoleh');

        // ─── 3. Grafik Harian (30 Hari Terakhir) ───────────────────────────────
        $startDate30Days = Carbon::today()->subDays(29);

        $dailyRaw = HabitLog::where('user_id', $targetUserId)
            ->where('tanggal', '>=', $startDate30Days)
            ->select('tanggal', DB::raw('SUM(skor_diperoleh) as total_skor'))
            ->groupBy('tanggal')
            ->orderBy('tanggal', 'asc')
            ->get()
            ->keyBy(fn($row) => Carbon::parse($row->tanggal)->format('Y-m-d'));

        $dailyChartData = collect();
        for ($i = 0; $i < 30; $i++) {
            $date = $startDate30Days->copy()->addDays($i)->format('Y-m-d');
            $dailyChartData->push([
                'tanggal' => $date,
                'skor'    => isset($dailyRaw[$date]) ? (int) $dailyRaw[$date]->total_skor : 0,
            ]);
        }

        // ─── 4. Grafik 6 Bulanan (Semester) ────────────────────────────────────
        $currentMonth = Carbon::now()->month;
        $currentYear  = Carbon::now()->year;
        $isSemester1  = $currentMonth <= 6;
        $startMonth   = $isSemester1 ? 1 : 7;
        $endMonth     = $isSemester1 ? 6 : 12;

        $namaBulanId = [
            1  => 'Januari',   2  => 'Februari',  3  => 'Maret',
            4  => 'April',     5  => 'Mei',        6  => 'Juni',
            7  => 'Juli',      8  => 'Agustus',    9  => 'September',
            10 => 'Oktober',   11 => 'November',   12 => 'Desember',
        ];

        $monthlyRaw = HabitLog::where('user_id', $targetUserId)
            ->whereYear('tanggal', $currentYear)
            ->whereMonth('tanggal', '>=', $startMonth)
            ->whereMonth('tanggal', '<=', $endMonth)
            ->select(
                DB::raw('MONTH(tanggal) as bulan'),
                DB::raw('SUM(skor_diperoleh) as total_skor')
            )
            ->groupBy('bulan')
            ->get()
            ->keyBy('bulan');

        $monthlyChartData = collect();
        for ($m = $startMonth; $m <= $endMonth; $m++) {
            $monthlyChartData->push([
                'bulan' => $namaBulanId[$m],
                'skor'  => isset($monthlyRaw[$m]) ? (int) $monthlyRaw[$m]->total_skor : 0,
            ]);
        }



        // ─── 5. Gamification (Badges & Streak) ───────────────────────────────────
        $userBadges = \Illuminate\Support\Facades\DB::table('user_badges')
            ->join('badges', 'user_badges.badge_id', '=', 'badges.id')
            ->where('user_badges.user_id', $targetUserId)
            ->select('badges.*', 'user_badges.unlocked_at')
            ->orderBy('user_badges.unlocked_at', 'desc')
            ->get();
            
        // ─── 6. Analytical Insight (Habit Paling Sering Kosong Bulan Ini) ──────
        // Cari habit mana (yang non-haid) yang sering 0 di bulan ini
        $insightMessage = null;
        if ($endOfMonth->isFuture() || $endOfMonth->isToday()) {
            $worstHabit = HabitLog::where('user_id', $targetUserId)
                ->whereBetween('tanggal', [$startOfMonth, Carbon::today()])
                ->where('skor_diperoleh', 0)
                ->selectRaw('habit_id, count(*) as missed_count')
                ->groupBy('habit_id')
                ->orderBy('missed_count', 'desc')
                ->first();

            if ($worstHabit && $worstHabit->missed_count >= 3) {
                $h = Habit::find($worstHabit->habit_id);
                if ($h && !$h->is_pengganti_haid) {
                    $insightMessage = "Anda telah melewatkan '{$h->nama_habit}' sebanyak {$worstHabit->missed_count} kali bulan ini. Yuk, tingkatkan lagi besok!";
                }
            }
        }

        // ─── 7. Cek Tanggal Kosong / Terlewat ────────────────────────────────────
        $todayStr = Carbon::today()->format('Y-m-d');
        $yesterday = Carbon::yesterday();
        $userCreatedAt = Carbon::parse($user->created_at)->startOfDay();
        
        $startAllowed = Carbon::today()->day <= 4 
            ? Carbon::today()->subMonth()->startOfMonth() 
            : Carbon::today()->startOfMonth();

        if ($startAllowed->lt($userCreatedAt)) {
            $startAllowed = $userCreatedAt->copy();
        }

        $missedDates = [];
        if ($yesterday->gte($startAllowed)) {
            $filledDates = HabitLog::where('user_id', $targetUserId)
                ->whereBetween('tanggal', [$startAllowed->format('Y-m-d'), $yesterday->format('Y-m-d')])
                ->pluck('tanggal')
                ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
                ->unique()
                ->toArray();

            $currentDate = $startAllowed->copy();
            while ($currentDate->lte($yesterday)) {
                $dateStr = $currentDate->format('Y-m-d');
                if (!in_array($dateStr, $filledDates)) {
                    $missedDates[] = $dateStr;
                }
                $currentDate->addDay();
            }
        }
        
        rsort($missedDates);

        $announcement = \App\Models\Setting::where('key', 'announcement_text')->value('value');

        $skorMaksimalHariIni = Habit::where('status_aktif', true)
            ->when($isSedangHaid, function ($query) {
                $query->where('hide_saat_haid', false);
            }, function ($query) {
                $query->where('is_pengganti_haid', false);
            })
            ->sum('skor_maksimal');

        $daysInMonth = Carbon::now()->daysInMonth;
        $skorMaksimalBulanIni = $skorMaksimalHariIni * $daysInMonth;

        $settingTarget = \App\Models\Setting::where('key', 'monthly_target_score')->first();
        $adminTargetBulanan = $settingTarget ? (float) $settingTarget->value : 80.0;
        $adminTargetSkorMinimal = (int) round($skorMaksimalBulanIni * ($adminTargetBulanan / 100));
        
        $targetBulanan = $adminTargetBulanan;
        $divisionTarget = null;
        if (!empty($user->divisi)) {
            $divisionTarget = \App\Models\Division::where('name', $user->divisi)->value('target_divisi');
        }

        if ($user->status_kehadiran !== 'Aktif' && !is_null($user->target_tidak_aktif)) {
            $targetBulanan = (float) $user->target_tidak_aktif;
        } elseif (!is_null($user->personal_target)) {
            $targetBulanan = (float) $user->personal_target;
        } elseif (!is_null($divisionTarget)) {
            $targetBulanan = (float) $divisionTarget;
        }

        $targetSkorMinimal = (int) round($skorMaksimalBulanIni * ($targetBulanan / 100));

        // ─── 8. Habit Analytics (Sholat Wajib, Rawatib, Quran) ───────────────────
        $habitAnalytics = [
            'sholat_wajib' => ['JM' => 0, 'JR' => 0, 'M' => 0, 'frekuensi' => 0],
            'sholat_rawatib' => ['Qobliyah' => 0, 'Badiyah' => 0, 'frekuensi' => 0],
            'quran' => ['durasi' => 0, 'frekuensi' => 0],
            'others' => []
        ];

        $monthlyLogs = HabitLog::with('habit')
            ->where('user_id', $targetUserId)
            ->whereBetween('tanggal', [$startOfMonth, Carbon::today()])
            ->get();
        
        foreach ($monthlyLogs as $log) {
            $details = $log->details;
            if (!is_array($details)) {
                $details = [];
            }

            if ($log->habit && $log->habit->template === 'sholat_wajib') {
                if (!empty($details)) {
                    $waktus = ['subuh', 'dhuhur', 'asar', 'maghrib', 'isya'];
                    foreach ($waktus as $w) {
                        if (isset($details[$w])) {
                            $val = $details[$w];
                            if ($val === 'JM') $habitAnalytics['sholat_wajib']['JM']++;
                            elseif ($val === 'JD' || $val === 'JR') $habitAnalytics['sholat_wajib']['JR']++;
                            elseif ($val === 'M') $habitAnalytics['sholat_wajib']['M']++;
                            
                            $habitAnalytics['sholat_wajib']['frekuensi']++;
                        }
                    }
                }
            } elseif ($log->habit && $log->habit->template === 'sholat_rawatib') {
                if (!empty($details)) {
                    $qKeys = ['subuh_q', 'dhuhur_q', 'asar_q', 'maghrib_q', 'isya_q'];
                    $bKeys = ['dhuhur_b', 'maghrib_b', 'isya_b'];
                    foreach ($qKeys as $k) {
                        if (!empty($details[$k])) {
                            $habitAnalytics['sholat_rawatib']['Qobliyah']++;
                            $habitAnalytics['sholat_rawatib']['frekuensi']++;
                        }
                    }
                    foreach ($bKeys as $k) {
                        if (!empty($details[$k])) {
                            $habitAnalytics['sholat_rawatib']['Badiyah']++;
                            $habitAnalytics['sholat_rawatib']['frekuensi']++;
                        }
                    }
                }
            } elseif ($log->habit && $log->habit->template === 'quran') {
                if ($log->nilai_input > 0) {
                    $habitAnalytics['quran']['frekuensi']++;
                }
                if (!empty($details) && isset($details['durasi']) && is_numeric($details['durasi'])) {
                    $habitAnalytics['quran']['durasi'] += (int) $details['durasi'];
                }
            } elseif ($log->habit) {
                if ($log->nilai_input > 0) {
                    $namaHabit = $log->habit->nama_habit;
                    if (!isset($habitAnalytics['others'][$namaHabit])) {
                        $habitAnalytics['others'][$namaHabit] = 0;
                    }
                    $habitAnalytics['others'][$namaHabit]++;
                }
            }
        }

        // --- Calculate Radar Chart Data (Days Target Achieved) ---
        $radarChartDataBackend = [];
        $dailySums = [];
        foreach ($monthlyLogs as $log) {
            if ($log->habit) {
                $dateKey = Carbon::parse($log->tanggal)->format('Y-m-d');
                $hid = $log->habit_id;
                
                if (!isset($dailySums[$dateKey])) $dailySums[$dateKey] = [];
                if (!isset($dailySums[$dateKey][$hid])) $dailySums[$dateKey][$hid] = 0;
                
                $dailySums[$dateKey][$hid] += (float) $log->nilai_input;
            }
        }

        $allHabits = \App\Models\Habit::all()->keyBy('id');
        foreach ($dailySums as $date => $habitsLog) {
            foreach ($habitsLog as $hid => $sum) {
                if (!isset($allHabits[$hid])) continue;
                $target = (float) $allHabits[$hid]->target_pencapaian;
                $habitName = $allHabits[$hid]->nama_habit;
                
                if (!isset($radarChartDataBackend[$habitName])) {
                    $radarChartDataBackend[$habitName] = 0;
                }
                
                if ($target > 0 && $sum >= $target) {
                    $radarChartDataBackend[$habitName]++;
                }
            }
        }

        return Inertia::render('Dashboard', [
            'skorHariIni'      => (int) $skorHariIni,
            'skorMaksimalHariIni'=> (int) $skorMaksimalHariIni,
            'skorBulanIni'     => (int) $skorBulanIni,
            'skorMaksimalBulanIni'=> (int) $skorMaksimalBulanIni,
            'targetSkorMinimal'=> $targetSkorMinimal,
            'targetBulanan'    => $targetBulanan,
            'adminTargetBulanan' => $adminTargetBulanan,
            'adminTargetSkorMinimal' => $adminTargetSkorMinimal,
            'isPersonalTarget' => !is_null($user->personal_target),
            'isSedangHaid'     => $isSedangHaid,
            'dailyChartData'   => $dailyChartData,
            'monthlyChartData' => $monthlyChartData,
            'semester'         => $isSemester1 ? 'Semester 1' : 'Semester 2',
            'tahun'            => $currentYear,
            'leaderboard'      => [], // Pindah ke Laporan
            'isTrackingOther'  => false,
            'targetUser'       => [
                'id' => $user->id, 
                'name' => $user->name, 
                'gender' => $user->gender,
                'current_streak' => $user->current_streak ?? 0,
                'longest_streak' => $user->longest_streak ?? 0
            ],
            'userBadges'       => $userBadges,
            'insightMessage'   => $insightMessage,
            'pegawaiList'      => [],
            'missedDates'      => $missedDates,
            'announcement'     => $announcement,
            'habitAnalytics'   => $habitAnalytics,
            'radarChartDataBackend' => $radarChartDataBackend,
        ]);
    }

    /**
     * Dashboard khusus Admin (Agregat Seluruh Pegawai).
     */
    private function adminDashboard(Request $request)
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth   = Carbon::now()->endOfMonth();
        
        $totalPegawai = User::role('user')->count();
        if ($totalPegawai === 0) $totalPegawai = 1; // Prevent div by 0 just in case

        // Hitung Maksimal Skor (sehari) dinamis berdasarkan jumlah skor maksimal semua habit aktif (standar non-haid)
        $skorMaksimalHarianPerUser = Habit::where('status_aktif', true)
            ->where('is_pengganti_haid', false)
            ->sum('skor_maksimal');
        if ($skorMaksimalHarianPerUser == 0) $skorMaksimalHarianPerUser = 100; // Fallback

        $skorMaksimalHarianSeluruhPegawai = $totalPegawai * $skorMaksimalHarianPerUser;

        // ─── GRAFIK HARIAN (Bulan Berjalan) ───────────────────────────────
        $dailyRaw = HabitLog::whereHas('user', function($q) {
                $q->role('user');
            })
            ->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
            ->select('tanggal', DB::raw('SUM(skor_diperoleh) as total_skor'))
            ->groupBy('tanggal')
            ->orderBy('tanggal', 'asc')
            ->get()
            ->keyBy(fn($row) => Carbon::parse($row->tanggal)->format('Y-m-d'));

        $dailyChartData = collect();
        $daysInMonth = $today->daysInMonth;
        for ($i = 0; $i < $daysInMonth; $i++) {
            $date = $startOfMonth->copy()->addDays($i)->format('Y-m-d');
            $rawSkor = isset($dailyRaw[$date]) ? (float) $dailyRaw[$date]->total_skor : 0;
            $persentase = ($skorMaksimalHarianSeluruhPegawai > 0) ? ($rawSkor / $skorMaksimalHarianSeluruhPegawai) * 100 : 0;

            $dailyChartData->push([
                'tanggal' => $date,
                'skor'    => round($persentase, 1),
                'maks'    => 100
            ]);
        }

        // ─── GRAFIK 6 BULANAN (Semester) ────────────────────────────────────
        $currentMonth = Carbon::now()->month;
        $currentYear  = Carbon::now()->year;
        $isSemester1  = $currentMonth <= 6;
        $startMonth   = $isSemester1 ? 1 : 7;
        $endMonth     = $isSemester1 ? 6 : 12;

        $namaBulanId = [
            1  => 'Jan.', 2  => 'Feb.', 3  => 'Mar.',
            4  => 'Apr.', 5  => 'Mei',   6  => 'Jun.',
            7  => 'Jul.', 8  => 'Ags.', 9  => 'Sep.',
            10 => 'Okt.', 11 => 'Nov.', 12 => 'Des.',
        ];

        $monthlyRaw = HabitLog::whereHas('user', function($q) {
                $q->role('user');
            })
            ->whereYear('tanggal', $currentYear)
            ->whereMonth('tanggal', '>=', $startMonth)
            ->whereMonth('tanggal', '<=', $endMonth)
            ->select(
                DB::raw('MONTH(tanggal) as bulan'),
                DB::raw('SUM(skor_diperoleh) as total_skor')
            )
            ->groupBy('bulan')
            ->get()
            ->keyBy('bulan');

        $monthlyChartData = collect();
        for ($m = $startMonth; $m <= $endMonth; $m++) {
            // Hari dalam bulan M
            $daysInThisMonth = Carbon::createFromDate($currentYear, $m, 1)->daysInMonth;
            $skorMaksBulanIni = $totalPegawai * $skorMaksimalHarianPerUser * $daysInThisMonth;
            
            $rawSkor = isset($monthlyRaw[$m]) ? (float) $monthlyRaw[$m]->total_skor : 0;
            $persentase = ($skorMaksBulanIni > 0) ? ($rawSkor / $skorMaksBulanIni) * 100 : 0;

            $monthlyChartData->push([
                'bulan' => $namaBulanId[$m],
                'skor'  => round($persentase, 1),
                'maks'  => 100
            ]);
        }

        $skorHariIni = $dailyRaw[$today->format('Y-m-d')]->total_skor ?? 0;

        // ─── TARGET RATIO BULAN INI ─────────────────────────────────────────
        $settingTarget = \App\Models\Setting::where('key', 'monthly_target_score')->first();
        $targetBulanan = $settingTarget ? (float) $settingTarget->value : 80.0;
        
        $usersSkor = User::role('user')
            ->withSum(
                ['habitLogs' => fn($q) => $q->whereBetween('tanggal', [$startOfMonth, $endOfMonth])],
                'skor_diperoleh'
            )
            ->get();
            
        $skorMaksimalSebulanUser = $skorMaksimalHarianPerUser * $daysInMonth;
        $targetHitCount = 0;
        $targetMissCount = 0;
        
        foreach($usersSkor as $u) {
            $skorUser = (float) $u->habit_logs_sum_skor_diperoleh;
            $persentase = ($skorMaksimalSebulanUser > 0) ? ($skorUser / $skorMaksimalSebulanUser) * 100 : 0;
            if ($persentase >= $targetBulanan) {
                $targetHitCount++;
            } else {
                $targetMissCount++;
            }
        }

        return Inertia::render('Admin/Dashboard', [
            'skorHariIni'          => (int) $skorHariIni,
            'skorMaksimalHariIni'  => $skorMaksimalHarianSeluruhPegawai,
            'totalPegawai'         => $totalPegawai,
            'dailyChartData'       => $dailyChartData,
            'monthlyChartData'     => $monthlyChartData,
            'semester'             => $isSemester1 ? 'Semester 1' : 'Semester 2',
            'tahun'                => $currentYear,
            'targetRatio'          => [
                'hit' => $targetHitCount,
                'miss' => $targetMissCount,
                'target' => $targetBulanan
            ]
        ]);
    }
}
