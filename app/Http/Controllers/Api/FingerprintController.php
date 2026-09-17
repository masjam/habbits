<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class FingerprintController extends Controller
{
    /**
     * Endpoint untuk menerima data absensi dari mesin Fingerprint / ADMS / Script Sync.
     * Payload expected: Array of objects [{ fingerprint_id: "...", timestamp: "YYYY-MM-DD HH:MM:SS" }]
     */
    public function sync(Request $request)
    {
        // Pastikan fitur Fingerprint aktif
        $settings = \App\Models\Setting::pluck('value', 'key')->toArray();
        if (($settings['attendance_mode_fingerprint'] ?? 'false') !== '1' && ($settings['attendance_mode_fingerprint'] ?? 'false') !== 'true') {
            return response()->json([
                'success' => false,
                'message' => 'Fitur Presensi Fingerprint dinonaktifkan.'
            ], 403);
        }

        $payload = $request->json()->all();

        // Bisa jadi single object atau array of objects
        if (!is_array($payload) || isset($payload['fingerprint_id'])) {
            $payload = [$payload];
        }

        $processed = 0;
        $failed = 0;

        foreach ($payload as $data) {
            if (!isset($data['fingerprint_id']) || !isset($data['timestamp'])) {
                $failed++;
                continue;
            }

            $user = User::where('fingerprint_id', $data['fingerprint_id'])->first();
            if (!$user) {
                $failed++;
                continue;
            }

            try {
                $datetime = Carbon::parse($data['timestamp']);
                $date = $datetime->toDateString();
                $time = $datetime->toTimeString();

                // Cek absensi hari tersebut
                $attendance = Attendance::where('user_id', $user->id)
                    ->where('date', $date)
                    ->first();

                $workStart = $user->work_start ?: ($settings['presensi_work_start'] ?? '07:00');
                $workEnd = $user->work_end ?: ($settings['presensi_work_end'] ?? '15:00');
                $lateTolerance = $user->late_tolerance !== null ? $user->late_tolerance : ($settings['presensi_late_tolerance'] ?? 15);
                $toleranceEnd = Carbon::parse($workStart)->addMinutes((int)$lateTolerance)->toTimeString();

                if (!$attendance) {
                    // Check IN
                    $status = ($time > $toleranceEnd) ? 'terlambat' : 'hadir';

                    Attendance::create([
                        'user_id' => $user->id,
                        'date' => $date,
                        'time_in' => $time,
                        'status' => $status,
                        'notes' => 'Presensi via Mesin Fingerprint',
                    ]);
                } else {
                    // Jika belum check out, atau jika jam scan ini lebih lambat dari time_out yang ada
                    if (!$attendance->time_out || $time > $attendance->time_out) {
                        // Namun jangan overwrite jika rentang waktu scan terlalu berdekatan (misal anti-passback 5 menit)
                        $lastScan = $attendance->time_out ? Carbon::parse($date . ' ' . $attendance->time_out) : Carbon::parse($date . ' ' . $attendance->time_in);
                        
                        if ($datetime->diffInMinutes($lastScan) > 5) {
                            $isPulangCepat = $time < $workEnd;
                            $attendance->update([
                                'time_out' => $time,
                                'is_pulang_cepat' => $isPulangCepat,
                            ]);
                        }
                    }
                }

                $processed++;
            } catch (\Exception $e) {
                Log::error('Fingerprint Sync Error: ' . $e->getMessage());
                $failed++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Sync selesai.',
            'processed' => $processed,
            'failed' => $failed,
        ]);
    }
}
