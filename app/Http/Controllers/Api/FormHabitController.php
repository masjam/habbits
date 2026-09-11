<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\MenstruationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FormHabitController extends Controller
{
    /**
     * Tampilkan daftar habit harian dan log yang sudah terisi.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $today = Carbon::today();
        $dateParam = $request->query('date', $today->toDateString());
        
        try {
            $selectedDate = Carbon::parse($dateParam);
        } catch (\Exception $e) {
            $selectedDate = $today;
        }

        // Deteksi Mode Haid
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

        return response()->json([
            'success' => true,
            'message' => 'Data formulir berhasil diambil',
            'data' => [
                'habits' => $habits,
                'logs' => $logsSelected,
                'isSedangHaid' => $isSedangHaid,
                'tanggal' => $selectedDate->toDateString(),
            ]
        ]);
    }

    /**
     * Simpan log habit dari mobile
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

        if ($selectedDate->isFuture()) {
            return response()->json(['success' => false, 'message' => 'Tidak bisa mengisi tanggal di masa depan.'], 400);
        }

        $isSedangHaid = false;
        if ($user->gender === 'P') {
            $isSedangHaid = MenstruationLog::where('user_id', $user->id)
                ->whereDate('waktu_mulai', '<=', $selectedDate)
                ->where(function($q) use ($selectedDate) {
                    $q->whereNull('waktu_selesai')
                      ->orWhereDate('waktu_selesai', '>=', $selectedDate);
                })->exists();
        }

        $habitIds = collect($request->logs)->pluck('habit_id')->unique();
        $habits = Habit::whereIn('id', $habitIds)->get()->keyBy('id');

        foreach ($request->logs as $logData) {
            $habit = $habits->get($logData['habit_id']);
            if (!$habit) continue; 

            $details = $logData['details'] ?? [];
            $nilaiInput = 0;
            $skorDiperoleh = 0;

            // Kalkulasi berdasarkan template persis web
            switch ($habit->template) {
                case 'sholat_wajib':
                    $countJamaah = 0;
                    foreach (['subuh', 'dhuhur', 'asar', 'maghrib', 'isya'] as $waktu) {
                        if (isset($details[$waktu]) && in_array($details[$waktu], ['JD', 'JM'])) {
                            $countJamaah++;
                        }
                    }
                    $nilaiInput = $countJamaah;
                    $skorDiperoleh = ($countJamaah >= $habit->target_pencapaian) ? $habit->skor_maksimal : 0;
                    break;
                case 'sholat_rawatib':
                    $count = 0;
                    foreach ($details as $key => $val) {
                        if ($val === true) $count++;
                    }
                    $nilaiInput = $count;
                    $skorDiperoleh = ($count >= $habit->target_pencapaian) ? $habit->skor_maksimal : 0;
                    break;
                case 'quran':
                    $durasi = (int) ($details['durasi'] ?? 0);
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
                    $nilaiInput = (int) ($logData['nilai_input'] ?? 0);
                    $skorDiperoleh = ($habit->target_pencapaian > 0 && $nilaiInput >= $habit->target_pencapaian) ? $habit->skor_maksimal : 0;
                    break;
                default:
                    $checkboxVal = (int) ($logData['nilai_input'] ?? 0);
                    $durasi = (int) ($details['durasi'] ?? 0);
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

        // Cap Skor Harian Saat Haid
        if ($isSedangHaid) {
            $skorMaksimalNormal = Habit::where('status_aktif', true)
                ->where('is_pengganti_haid', false)
                ->sum('skor_maksimal');

            $allLogsToday = HabitLog::where('user_id', $user->id)
                ->whereDate('tanggal', $selectedDate->toDateString())
                ->get();

            $totalSkorHariIni = $allLogsToday->sum('skor_diperoleh');

            if ($totalSkorHariIni > $skorMaksimalNormal) {
                $kelebihan = $totalSkorHariIni - $skorMaksimalNormal;
                $logsHabitPengganti = $allLogsToday
                    ->filter(fn($log) => Habit::find($log->habit_id)?->is_pengganti_haid)
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

        return response()->json([
            'success' => true,
            'message' => 'Log habit berhasil disimpan!'
        ]);
    }
}
