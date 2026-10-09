<?php

namespace App\Http\Controllers\Corporate;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class AttendanceRFIDController extends Controller
{
    /**
     * Tampilkan Kiosk RFID.
     */
    public function kiosk(Request $request)
    {
        $settings = \App\Models\Setting::pluck('value', 'key')->toArray();
        
        // Pastikan fitur RFID aktif
        if (($settings['attendance_mode_rfid'] ?? 'false') !== '1' && ($settings['attendance_mode_rfid'] ?? 'false') !== 'true') {
            abort(403, 'Mode Presensi RFID saat ini dinonaktifkan oleh Superadmin.');
        }

        // Kiosk Security: Validate Token
        $validToken = $settings['kiosk_api_token'] ?? 'SDAM-KIOSK-SECRET';
        if ($request->token !== $validToken) {
            abort(403, 'Akses Ditolak: Token Keamanan Kiosk tidak valid. Pastikan Anda membuka URL dengan ?token= yang benar.');
        }

        $today = Carbon::now()->toDateString();
        $attendances = Attendance::with('user:id,name')
            ->where('date', $today)
            ->orderBy('updated_at', 'desc')
            ->take(15)
            ->get()
            ->map(function ($att) {
                return [
                    'id' => $att->id,
                    'name' => $att->user->name ?? 'Unknown',
                    'time_in' => $att->time_in,
                    'time_out' => $att->time_out,
                    'status' => $att->status,
                    'notes' => $att->notes,
                ];
            });

        return Inertia::render('Attendance/RFIDKiosk', [
            'initialAttendances' => $attendances,
            'kioskToken' => $validToken
        ]);
    }

    /**
     * Proses scan RFID (Check-in atau Check-out otomatis)
     */
    public function processScan(Request $request)
    {
        $request->validate([
            'rfid_uid' => 'required|string',
            'token' => 'required|string',
        ]);

        $settings = \App\Models\Setting::pluck('value', 'key')->toArray();
        if (($settings['attendance_mode_rfid'] ?? 'false') !== '1' && ($settings['attendance_mode_rfid'] ?? 'false') !== 'true') {
            return response()->json([
                'success' => false,
                'message' => 'Fitur Presensi RFID sedang dinonaktifkan.',
            ], 403);
        }

        $validToken = $settings['kiosk_api_token'] ?? 'SDAM-KIOSK-SECRET';
        if ($request->token !== $validToken) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak: Token Keamanan tidak valid.',
            ], 403);
        }

        $user = User::where('rfid_uid', $request->rfid_uid)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Kartu RFID tidak terdaftar. Silakan hubungi admin.',
            ], 404);
        }

        $now = Carbon::now();
        $date = $now->toDateString();
        $time = $now->toTimeString();

        // Cari absensi hari ini
        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $date)
            ->first();

        // Ambil jam kerja dari user atau global
        $workStart = $user->work_start ?: ($settings['presensi_work_start'] ?? '07:00');
        $workEnd = $user->work_end ?: ($settings['presensi_work_end'] ?? '15:00');
        $lateTolerance = $user->late_tolerance !== null ? $user->late_tolerance : ($settings['presensi_late_tolerance'] ?? 15);
        $toleranceEnd = Carbon::parse($workStart)->addMinutes((int)$lateTolerance)->toTimeString();

        if (!$attendance) {
            // Belum absen masuk -> CHECK IN
            $status = ($time > $toleranceEnd) ? 'terlambat' : 'hadir';

            $newAtt = Attendance::create([
                'user_id' => $user->id,
                'date' => $date,
                'time_in' => $time,
                'status' => $status,
                'notes' => 'Presensi via Mesin RFID',
            ]);

            return response()->json([
                'success' => true,
                'type' => 'in',
                'user' => [
                    'name' => $user->name,
                    'status' => $status,
                    'time' => $time,
                ],
                'attendance' => [
                    'id' => $newAtt->id,
                    'name' => $user->name,
                    'time_in' => $time,
                    'time_out' => null,
                    'status' => $status,
                    'notes' => 'Presensi via Mesin RFID',
                ],
                'message' => "Selamat datang, {$user->name}!",
            ]);
        } else {
            // Sudah absen masuk
            // Update time_out (Check out)
            $isPulangCepat = $time < $workEnd;

            $attendance->update([
                'time_out' => $time,
                'is_pulang_cepat' => $isPulangCepat,
            ]);

            return response()->json([
                'success' => true,
                'type' => 'out',
                'user' => [
                    'name' => $user->name,
                    'time' => $time,
                    'is_pulang_cepat' => $isPulangCepat,
                ],
                'attendance' => [
                    'id' => $attendance->id,
                    'name' => $user->name,
                    'time_in' => $attendance->time_in,
                    'time_out' => $time,
                    'status' => $attendance->status,
                ],
                'message' => "Sampai jumpa, {$user->name}!",
            ]);

        }
    }
}
