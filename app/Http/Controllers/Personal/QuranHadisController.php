<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Models\Habit;
use App\Models\HabitLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class QuranHadisController extends Controller
{
    /**
     * Tampilkan halaman Al-Qur'an & Hadis beserta sesi tilawah hari ini
     */
    public function index()
    {
        $user = Auth::user();
        $today = Carbon::today()->toDateString();

        $habit = Habit::where('template', 'quran')
            ->where('status_aktif', true)
            ->where('is_pengganti_haid', false)
            ->first() ?? Habit::where('template', 'quran')->first();

        $todaySessions = [];
        $habitLog = null;

        if ($habit && $user) {
            $habitLog = HabitLog::where('user_id', $user->id)
                ->where('habit_id', $habit->id)
                ->where('tanggal', $today)
                ->first();

            if ($habitLog && !empty($habitLog->details)) {
                $details = $habitLog->details;
                $todaySessions = $details['sesi'] ?? [];

                // Kompatibilitas data jika belum ada key 'sesi'
                if (empty($todaySessions) && (!empty($details['surat_awal']) || !empty($details['surat_akhir']))) {
                    $todaySessions[] = [
                        'id' => 'sesi_1',
                        'surat_awal' => $details['surat_awal'] ?? null,
                        'ayat_awal' => $details['ayat_awal'] ?? null,
                        'surat_akhir' => $details['surat_akhir'] ?? null,
                        'ayat_akhir' => $details['ayat_akhir'] ?? null,
                        'jam_mulai' => null,
                        'jam_selesai' => null,
                    ];
                }
            }
        }

        return Inertia::render('QuranHadis/Index', [
            'initialTodaySessions' => $todaySessions,
            'todayDate' => $today,
        ]);
    }

    /**
     * Endpoint untuk mendapatkan daftar sesi hari ini secara JSON
     */
    public function getTodaySessions()
    {
        $user = Auth::user();
        $today = Carbon::today()->toDateString();

        $habit = Habit::where('template', 'quran')
            ->where('status_aktif', true)
            ->where('is_pengganti_haid', false)
            ->first() ?? Habit::where('template', 'quran')->first();

        $todaySessions = [];
        if ($habit && $user) {
            $habitLog = HabitLog::where('user_id', $user->id)
                ->where('habit_id', $habit->id)
                ->where('tanggal', $today)
                ->first();

            if ($habitLog && !empty($habitLog->details)) {
                $details = $habitLog->details;
                $todaySessions = $details['sesi'] ?? [];
                if (empty($todaySessions) && (!empty($details['surat_awal']) || !empty($details['surat_akhir']))) {
                    $todaySessions[] = [
                        'id' => 'sesi_1',
                        'surat_awal' => $details['surat_awal'] ?? null,
                        'ayat_awal' => $details['ayat_awal'] ?? null,
                        'surat_akhir' => $details['surat_akhir'] ?? null,
                        'ayat_akhir' => $details['ayat_akhir'] ?? null,
                        'jam_mulai' => null,
                        'jam_selesai' => null,
                    ];
                }
            }
        }

        return response()->json([
            'sessions' => $todaySessions,
            'today' => $today,
        ]);
    }

    /**
     * Tandai ayat Al-Qur'an sebagai Mulai Baca atau Selesai Baca
     * Mendukung multi-sesi per hari
     */
    public function tandaiBaca(Request $request)
    {
        $request->validate([
            'action' => ['required', 'in:mulai,selesai'],
            'surah_nomor' => ['required', 'integer'],
            'surah_nama' => ['required', 'string'],
            'ayat_nomor' => ['required', 'integer'],
            'mode_baru' => ['nullable', 'boolean'],
            'sesi_id' => ['nullable', 'string'],
        ]);

        $user = Auth::user();
        $today = Carbon::today()->toDateString();
        $nowTime = Carbon::now()->format('H:i');

        $habit = Habit::where('template', 'quran')
            ->where('status_aktif', true)
            ->where('is_pengganti_haid', false)
            ->first() ?? Habit::where('template', 'quran')->first();

        if (!$habit) {
            return response()->json([
                'success' => false,
                'message' => 'Habit Al-Qur\'an tidak ditemukan pada sistem.'
            ], 404);
        }

        $habitLog = HabitLog::firstOrNew([
            'user_id' => $user->id,
            'habit_id' => $habit->id,
            'tanggal' => $today,
        ]);

        $details = $habitLog->details ?? [];
        $sesiList = $details['sesi'] ?? [];

        // Konversi format legacy jika belum ada 'sesi'
        if (empty($sesiList) && (!empty($details['surat_awal']) || !empty($details['surat_akhir']))) {
            $sesiList[] = [
                'id' => 'sesi_legacy_' . time(),
                'surat_awal' => $details['surat_awal'] ?? null,
                'ayat_awal' => $details['ayat_awal'] ?? null,
                'surat_akhir' => $details['surat_akhir'] ?? null,
                'ayat_akhir' => $details['ayat_akhir'] ?? null,
                'jam_mulai' => null,
                'jam_selesai' => null,
            ];
        }

        $action = $request->action;
        $surahNama = $request->surah_nama;
        $ayatNomor = (int) $request->ayat_nomor;
        $forceNew = (bool) $request->mode_baru;
        $targetSesiId = $request->sesi_id;

        if ($action === 'mulai') {
            // Cek apakah ada sesi terbuka yang belum selesai
            $openSessionKey = null;
            if (!$forceNew) {
                if ($targetSesiId) {
                    foreach ($sesiList as $idx => $s) {
                        if ($s['id'] === $targetSesiId) {
                            $openSessionKey = $idx;
                            break;
                        }
                    }
                } else {
                    // Cari sesi terakhir yang belum ada surat_akhir
                    foreach (array_reverse($sesiList, true) as $idx => $s) {
                        if (empty($s['surat_akhir'])) {
                            $openSessionKey = $idx;
                            break;
                        }
                    }
                }
            }

            if ($openSessionKey !== null && !$forceNew) {
                // Perbarui titik mulai pada sesi terbuka tersebut
                $sesiList[$openSessionKey]['surat_awal'] = $surahNama;
                $sesiList[$openSessionKey]['ayat_awal'] = $ayatNomor;
                $sesiList[$openSessionKey]['jam_mulai'] = $nowTime;
            } else {
                // Buat sesi baru
                $sesiNumber = count($sesiList) + 1;
                $sesiList[] = [
                    'id' => 'sesi_' . $sesiNumber . '_' . Str::random(6),
                    'surat_awal' => $surahNama,
                    'ayat_awal' => $ayatNomor,
                    'surat_akhir' => null,
                    'ayat_akhir' => null,
                    'jam_mulai' => $nowTime,
                    'jam_selesai' => null,
                ];
            }
        } elseif ($action === 'selesai') {
            // Cek sesi yang ditargetkan atau sesi terakhir yang belum selesai
            $openSessionKey = null;
            if ($targetSesiId) {
                foreach ($sesiList as $idx => $s) {
                    if ($s['id'] === $targetSesiId) {
                        $openSessionKey = $idx;
                        break;
                    }
                }
            } else {
                foreach (array_reverse($sesiList, true) as $idx => $s) {
                    if (empty($s['surat_akhir'])) {
                        $openSessionKey = $idx;
                        break;
                    }
                }
            }

            if ($openSessionKey !== null) {
                // Tutup sesi terbuka
                $sesiList[$openSessionKey]['surat_akhir'] = $surahNama;
                $sesiList[$openSessionKey]['ayat_akhir'] = $ayatNomor;
                $sesiList[$openSessionKey]['jam_selesai'] = $nowTime;

                // Hitung durasi sesi ini
                $manualDurasi = $request->input('durasi');
                if ($manualDurasi !== null && is_numeric($manualDurasi) && (int) $manualDurasi > 0) {
                    $sesiList[$openSessionKey]['durasi'] = (int) $manualDurasi;
                } else {
                    $sesiList[$openSessionKey]['durasi'] = $this->calculateSessionDuration(
                        $sesiList[$openSessionKey]['jam_mulai'] ?? $nowTime,
                        $nowTime
                    );
                }
            } else {
                // Tidak ada sesi yang terbuka, buat sesi baru langsung dengan selesai
                $prevSession = !empty($sesiList) ? end($sesiList) : null;
                $suratAwalDefault = ($prevSession && !empty($prevSession['surat_akhir']))
                    ? $prevSession['surat_akhir']
                    : $surahNama;
                $ayatAwalDefault = ($prevSession && !empty($prevSession['ayat_akhir']))
                    ? $prevSession['ayat_akhir']
                    : 1;

                $sesiNumber = count($sesiList) + 1;
                $manualDurasi = $request->input('durasi');
                $durasiItem = ($manualDurasi !== null && is_numeric($manualDurasi) && (int) $manualDurasi > 0)
                    ? (int) $manualDurasi
                    : 1;

                $sesiList[] = [
                    'id' => 'sesi_' . $sesiNumber . '_' . Str::random(6),
                    'surat_awal' => $suratAwalDefault,
                    'ayat_awal' => $ayatAwalDefault,
                    'surat_akhir' => $surahNama,
                    'ayat_akhir' => $ayatNomor,
                    'jam_mulai' => $nowTime,
                    'jam_selesai' => $nowTime,
                    'durasi' => $durasiItem,
                ];
            }
        }

        // Sinkronisasi data top-level details agar kompatibel dengan tabel rekap & form habit
        $details['sesi'] = array_values($sesiList);
        if (!empty($sesiList)) {
            $details['surat_awal'] = $sesiList[0]['surat_awal'] ?? null;
            $details['ayat_awal'] = $sesiList[0]['ayat_awal'] ?? null;

            $lastWithEnd = collect($sesiList)->reverse()->first(fn($s) => !empty($s['surat_akhir']));
            if ($lastWithEnd) {
                $details['surat_akhir'] = $lastWithEnd['surat_akhir'];
                $details['ayat_akhir'] = $lastWithEnd['ayat_akhir'];
            } else {
                $lastS = end($sesiList);
                $details['surat_akhir'] = $lastS['surat_awal'] ?? null;
                $details['ayat_akhir'] = $lastS['ayat_awal'] ?? null;
            }
        }

        // Hitung total durasi akumulatif dari semua sesi yang sudah selesai secara presisi
        $totalDurasi = 0;
        foreach ($details['sesi'] as &$s) {
            if (!empty($s['jam_selesai'])) {
                if (empty($s['durasi'])) {
                    $s['durasi'] = $this->calculateSessionDuration($s['jam_mulai'] ?? null, $s['jam_selesai']);
                }
                $totalDurasi += (int) $s['durasi'];
            }
        }
        unset($s);

        // Jika ada sesi yang selesai, gunakan total durasi tersebut. Jika baru mulai, minimal 1.
        $details['durasi'] = $totalDurasi > 0 ? $totalDurasi : (count($details['sesi']) > 0 ? 1 : 0);

        $habitLog->nilai_input = max((int) $details['durasi'], 1);
        $habitLog->skor_diperoleh = $habit->skor_maksimal;
        $habitLog->details = $details;
        $habitLog->save();

        $sessionCount = count($details['sesi']);
        $message = $action === 'mulai'
            ? "Tanda MULAI berhasil disimpan (Surat {$surahNama} ayat {$ayatNomor}). Sesi {$sessionCount} sedang berjalan."
            : "Tanda SELESAI berhasil disimpan (Surat {$surahNama} ayat {$ayatNomor}). Rekap Al-Qur'an telah diperbarui.";

        return response()->json([
            'success' => true,
            'message' => $message,
            'sessions' => $details['sesi'],
            'details' => $details,
        ]);
    }

    /**
     * Hapus satu sesi bacaan hari ini
     */
    public function hapusSesi($sesiId)
    {
        $user = Auth::user();
        $today = Carbon::today()->toDateString();

        $habit = Habit::where('template', 'quran')
            ->where('status_aktif', true)
            ->where('is_pengganti_haid', false)
            ->first() ?? Habit::where('template', 'quran')->first();

        if (!$habit) {
            return response()->json(['success' => false, 'message' => 'Habit tidak ditemukan'], 404);
        }

        $habitLog = HabitLog::where('user_id', $user->id)
            ->where('habit_id', $habit->id)
            ->where('tanggal', $today)
            ->first();

        if (!$habitLog || empty($habitLog->details['sesi'])) {
            return response()->json(['success' => false, 'message' => 'Belum ada sesi hari ini'], 400);
        }

        $details = $habitLog->details;
        $sesiList = array_values(array_filter($details['sesi'], fn($s) => $s['id'] !== $sesiId));

        $details['sesi'] = $sesiList;
        if (!empty($sesiList)) {
            $details['surat_awal'] = $sesiList[0]['surat_awal'] ?? null;
            $details['ayat_awal'] = $sesiList[0]['ayat_awal'] ?? null;
            $lastWithEnd = collect($sesiList)->reverse()->first(fn($s) => !empty($s['surat_akhir']));
            if ($lastWithEnd) {
                $details['surat_akhir'] = $lastWithEnd['surat_akhir'];
                $details['ayat_akhir'] = $lastWithEnd['ayat_akhir'];
            } else {
                $details['surat_akhir'] = null;
                $details['ayat_akhir'] = null;
            }
        } else {
            $details['surat_awal'] = null;
            $details['ayat_awal'] = null;
            $details['surat_akhir'] = null;
            $details['ayat_akhir'] = null;
            $habitLog->nilai_input = 0;
            $habitLog->skor_diperoleh = 0;
        }

        // Hitung ulang total durasi setelah sesi dihapus
        $totalDurasi = 0;
        foreach ($details['sesi'] as &$s) {
            if (!empty($s['jam_selesai'])) {
                if (empty($s['durasi'])) {
                    $s['durasi'] = $this->calculateSessionDuration($s['jam_mulai'] ?? null, $s['jam_selesai']);
                }
                $totalDurasi += (int) $s['durasi'];
            }
        }
        unset($s);

        if (!empty($details['sesi'])) {
            $details['durasi'] = $totalDurasi > 0 ? $totalDurasi : 1;
            $habitLog->nilai_input = max((int) $details['durasi'], 1);
        } else {
            $details['durasi'] = 0;
            $habitLog->nilai_input = 0;
        }

        $habitLog->details = $details;
        $habitLog->save();

        return response()->json([
            'success' => true,
            'message' => 'Sesi berhasil dihapus dari rekap.',
            'sessions' => $details['sesi'],
        ]);
    }

    /**
     * Hitung durasi sesi dalam menit dari jam mulai dan selesai
     */
    private function calculateSessionDuration(?string $jamMulai, ?string $jamSelesai): int
    {
        if (empty($jamMulai) || empty($jamSelesai)) {
            return 0;
        }

        try {
            $start = Carbon::createFromFormat('H:i', $jamMulai);
            $end = Carbon::createFromFormat('H:i', $jamSelesai);

            if ($end->lessThan($start)) {
                // Melewati pergantian hari / tengah malam
                $end->addDay();
            }

            $diff = $start->diffInMinutes($end);
            return max(1, (int) $diff); // Minimal 1 menit jika ditandai selesai
        } catch (\Exception $e) {
            return 1;
        }
    }
}
