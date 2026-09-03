<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\MenstruationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class FormHabitController extends Controller
{
    /**
     * Tampilkan form isian habit harian.
     * Menerima parameter `date` opsional.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // 1. Tentukan tanggal pengisian
        $today = Carbon::today();
        $dateParam = $request->query('date', $today->toDateString());
        
        try {
            $selectedDate = Carbon::parse($dateParam);
        } catch (\Exception $e) {
            $selectedDate = $today;
        }

        // 2. Validasi Tanggal (Tidak boleh future)
        if ($selectedDate->isFuture()) {
            return redirect()->route('habit.form')->withErrors(['date' => 'Tidak bisa mengisi tanggal di masa depan.']);
        }

        // Validasi: hanya bisa mengisi bulan lalu jika tanggal saat ini < 4 (maksimal tgl 3)
        if ($selectedDate->month !== $today->month || $selectedDate->year !== $today->year) {
            if ($today->day >= 4) {
                return redirect()->route('habit.form')->withErrors(['date' => 'Batas waktu pengisian bulan lalu telah berakhir (maksimal tgl 3).']);
            }
        }

        // Deteksi Mode Haid pada tanggal yang dipilih
        $isSedangHaid = false;
        if ($user->gender === 'P') {
            $isSedangHaid = MenstruationLog::where('user_id', $user->id)
                ->whereDate('waktu_mulai', '<=', $selectedDate)
                ->where(function($q) use ($selectedDate) {
                    $q->whereNull('waktu_selesai')
                      ->orWhereDate('waktu_selesai', '>=', $selectedDate);
                })->exists();
        }

        // Filter habit
        $habitsQuery = Habit::where('status_aktif', true);

        if (!$isSedangHaid) {
            $habitsQuery->where('is_pengganti_haid', false);
        } else {
            $habitsQuery->where(function ($q) {
                $q->where('hide_saat_haid', false)
                  ->orWhere('is_pengganti_haid', true);
            });
        }

        $habits = $habitsQuery->orderBy('urutan')->orderBy('id')->get();

        // Ambil log pada tanggal yang dipilih
        $logsSelected = HabitLog::where('user_id', $user->id)
            ->whereDate('tanggal', $selectedDate)
            ->get()
            ->keyBy('habit_id');

        return Inertia::render('FormHabit', [
            'habits'        => $habits,
            'logsHariIni'   => $logsSelected,
            'isSedangHaid'  => $isSedangHaid,
            'tanggal'       => $selectedDate->toDateString(),
            'today'         => $today->toDateString(),
        ]);
    }

    /**
     * Simpan log habit tabular
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'tanggal'         => ['required', 'date'],
            'logs'            => ['required', 'array'],
            'logs.*.habit_id' => ['required', 'exists:habits,id'],
        ]);

        $today = Carbon::today();
        $selectedDate = Carbon::parse($request->tanggal);

        // Validasi Tanggal (sama seperti index)
        if ($selectedDate->isFuture()) {
            return redirect()->back()->withErrors(['date' => 'Tidak bisa mengisi tanggal di masa depan.']);
        }
        if ($selectedDate->month !== $today->month || $selectedDate->year !== $today->year) {
            if ($today->day >= 4) {
                return redirect()->back()->withErrors(['date' => 'Batas waktu pengisian bulan lalu telah berakhir.']);
            }
        }

        // Deteksi Mode Haid pada tanggal yang dipilih
        $isSedangHaid = false;
        if ($user->gender === 'P') {
            $isSedangHaid = MenstruationLog::where('user_id', $user->id)
                ->whereDate('waktu_mulai', '<=', $selectedDate)
                ->where(function($q) use ($selectedDate) {
                    $q->whereNull('waktu_selesai')
                      ->orWhereDate('waktu_selesai', '>=', $selectedDate);
                })->exists();
        }

        // Optimasi N+1: Ambil semua data habit sekaligus dari database
        $habitIds = collect($request->logs)->pluck('habit_id')->unique();
        $habits = Habit::whereIn('id', $habitIds)->get()->keyBy('id');

        foreach ($request->logs as $logData) {
            $habit = $habits->get($logData['habit_id']);
            if (!$habit) continue; // Lewati jika habit tidak ditemukan
            $details = $logData['details'] ?? [];
            $nilaiInput = 0;
            $skorDiperoleh = 0;

            // Kalkulasi khusus berdasarkan template
            switch ($habit->template) {
                case 'sholat_wajib':
                    // Hitung jumlah sholat jamaah (JD, JM)
                    $countJamaah = 0;
                    foreach (['subuh', 'dhuhur', 'asar', 'maghrib', 'isya'] as $waktu) {
                        if (isset($details[$waktu]) && in_array($details[$waktu], ['JD', 'JM'])) {
                            $countJamaah++;
                        }
                    }
                    $nilaiInput = $countJamaah;
                    
                    // Jika sholat jamaah mencapai/melebihi target, skor maksimal. Jika kurang, skor 0.
                    if ($countJamaah >= $habit->target_pencapaian) {
                        $skorDiperoleh = $habit->skor_maksimal;
                    } else {
                        $skorDiperoleh = 0;
                    }
                    break;

                case 'sholat_rawatib':
                    // Hitung jumlah Q/B yang dicentang (bernilai true)
                    $count = 0;
                    foreach ($details as $key => $val) {
                        if ($val === true) $count++;
                    }
                    $nilaiInput = $count;
                    if ($count >= $habit->target_pencapaian) {
                        $skorDiperoleh = $habit->skor_maksimal;
                    } else {
                        $skorDiperoleh = 0;
                    }
                    break;

                case 'quran':
                    $durasi = (int) ($details['durasi'] ?? 0);
                    // Gunakan durasi jika ada, jika tidak ada durasi minimal anggap 1 (jika mengisi form)
                    if ($durasi > 0) {
                        $nilaiInput = $durasi;
                    } elseif (!empty($details['surat_awal']) || !empty($details['ayat_awal'])) {
                        $nilaiInput = 1;
                    }

                    if ($habit->target_pencapaian > 0 && $nilaiInput >= $habit->target_pencapaian) {
                        $skorDiperoleh = $habit->skor_maksimal;
                    } elseif ($habit->target_pencapaian == 0 && $nilaiInput > 0) {
                        $skorDiperoleh = $habit->skor_maksimal;
                    } else {
                        $skorDiperoleh = 0;
                    }
                    break;

                case 'tahajud':
                case 'dhuha':
                case 'integer':
                    // Gunakan nilai_input bawaan
                    $nilaiInput = (int) ($logData['nilai_input'] ?? 0);
                    if ($habit->target_pencapaian > 0 && $nilaiInput >= $habit->target_pencapaian) {
                        $skorDiperoleh = $habit->skor_maksimal;
                    } else {
                        $skorDiperoleh = 0;
                    }
                    break;

                case 'hadist':
                case 'buku':
                case 'default':
                case 'boolean':
                    $checkboxVal = (int) ($logData['nilai_input'] ?? 0);
                    $durasi = (int) ($details['durasi'] ?? 0);
                    
                    // Prioritaskan nilai durasi (menit) jika diisi. Jika tidak, gunakan nilai centang (1)
                    $nilaiInput = $durasi > 0 ? $durasi : $checkboxVal;

                    if ($habit->target_pencapaian > 0 && $nilaiInput >= $habit->target_pencapaian) {
                        $skorDiperoleh = $habit->skor_maksimal;
                    } elseif ($habit->target_pencapaian == 0 && $nilaiInput > 0) {
                        $skorDiperoleh = $habit->skor_maksimal;
                    } else {
                        $skorDiperoleh = 0;
                    }
                    break;
            }

            // Saat haid: habit yang disembunyikan (hide_saat_haid) TIDAK ditampilkan di form,
            // sehingga tidak ada input dari user. Kita biarkan nilainya 0 di sini (tidak diisi).
            // Skor habit ini diatur melalui mekanisme cap harian di bawah (setelah semua log tersimpan).
            if ($isSedangHaid && $habit->hide_saat_haid) {
                $skorDiperoleh = 0;
            }

            HabitLog::updateOrCreate(
                [
                    'user_id'  => $user->id,
                    'habit_id' => $habit->id,
                    'tanggal'  => $selectedDate->toDateString(),
                ],
                [
                    'nilai_input'    => $nilaiInput,
                    'skor_diperoleh' => $skorDiperoleh,
                    'details'        => $details,
                ]
            );
        }

        // ─── Cap Skor Harian Saat Haid ──────────────────────────────────────────────
        // Skor habit utama (hide_saat_haid) TIDAK hangus, tapi total akhir harian
        // tidak boleh melebihi skor maksimal normal (non-haid / semua habit utama).
        if ($isSedangHaid) {
            // Hitung skor maksimal harian normal (tanpa habit pengganti haid)
            $skorMaksimalNormal = Habit::where('status_aktif', true)
                ->where('is_pengganti_haid', false)
                ->sum('skor_maksimal');

            // Ambil semua log hari ini (sudah tersimpan), hitung total
            $allLogsToday = \App\Models\HabitLog::where('user_id', $user->id)
                ->whereDate('tanggal', $selectedDate->toDateString())
                ->get();

            $totalSkorHariIni = $allLogsToday->sum('skor_diperoleh');

            // Jika total melebihi skor maksimal normal, kurangi skor habit pengganti
            if ($totalSkorHariIni > $skorMaksimalNormal) {
                $kelebihan = $totalSkorHariIni - $skorMaksimalNormal;

                // Kurangi dari habit pengganti haid terlebih dahulu (skor terbesar dulu)
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
        }

        // Recalculate streak & unlock badges
        $this->recalculateAndUnlockBadges($user);

        return redirect()->back()->with('success', 'Log habit berhasil disimpan!');
    }


    private function recalculateAndUnlockBadges($user)
    {
        // 1. Hitung total skor harian user dari tanggal registrasi (atau bulan lalu)
        $dailyScores = HabitLog::where('user_id', $user->id)
            ->selectRaw('tanggal, SUM(skor_diperoleh) as total')
            ->groupBy('tanggal')
            ->orderBy('tanggal', 'desc')
            ->pluck('total', 'tanggal');

        $currentStreak = 0;
        $checkDate = Carbon::today();
        
        $settingTarget = \App\Models\Setting::where('key', 'monthly_target_score')->first();
        $targetBulanan = $settingTarget ? (float) $settingTarget->value : 80.0;
        
        $isSedangHaid = false;
        if ($user->gender === 'P') {
            $isSedangHaid = \App\Models\MenstruationLog::where('user_id', $user->id)
                ->whereNull('waktu_selesai')
                ->exists();
        }

        // Asumsikan target minimal harian adalah persentase targetBulanan dari Total Skor Maksimal Harian
        $totalMaxScore = \App\Models\Habit::where('status_aktif', true)
            ->when($isSedangHaid, function ($query) {
                $query->where('hide_saat_haid', false);
            }, function ($query) {
                $query->where('is_pengganti_haid', false);
            })
            ->sum('skor_maksimal');
        if ($totalMaxScore == 0) $totalMaxScore = 100;
        
        $dailyTarget = ($targetBulanan / 100) * $totalMaxScore;

        // Jika hari ini belum mencapai target, kita masih beri toleransi jika kemarin mencapai target (streak belum putus total)
        $todayScore = $dailyScores[$checkDate->toDateString()] ?? 0;
        
        if ($todayScore >= $dailyTarget) {
            $currentStreak++;
            $checkDate->subDay();
            // Loop ke belakang
            while (true) {
                $score = $dailyScores[$checkDate->toDateString()] ?? 0;
                if ($score >= $dailyTarget) {
                    $currentStreak++;
                    $checkDate->subDay();
                } else {
                    break;
                }
            }
        } else {
            // Cek dari kemarin
            $checkDate->subDay();
            $yesterdayScore = $dailyScores[$checkDate->toDateString()] ?? 0;
            if ($yesterdayScore >= $dailyTarget) {
                $currentStreak++;
                $checkDate->subDay();
                while (true) {
                    $score = $dailyScores[$checkDate->toDateString()] ?? 0;
                    if ($score >= $dailyTarget) {
                        $currentStreak++;
                        $checkDate->subDay();
                    } else {
                        break;
                    }
                }
            }
        }

        $user->current_streak = $currentStreak;
        if ($currentStreak > $user->longest_streak) {
            $user->longest_streak = $currentStreak;
        }
        $user->save();

        // 2. Unlock Badges
        $badges = \Illuminate\Support\Facades\DB::table('badges')->get();
        $totalPoints = $dailyScores->sum();
        $userBadgesToInsert = [];

        foreach ($badges as $badge) {
            $unlocked = false;
            
            if ($badge->criteria_type === 'streak_days' && $currentStreak >= $badge->criteria_value) {
                $unlocked = true;
            } elseif ($badge->criteria_type === 'total_points' && $totalPoints >= $badge->criteria_value) {
                $unlocked = true;
            }

            if ($unlocked) {
                $userBadgesToInsert[] = [
                    'user_id' => $user->id,
                    'badge_id' => $badge->id,
                    'unlocked_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Optimasi N+1: Bulk Insert (hanya 1 kueri ke database)
        if (!empty($userBadgesToInsert)) {
            \Illuminate\Support\Facades\DB::table('user_badges')->insertOrIgnore($userBadgesToInsert);
        }
    }
}
