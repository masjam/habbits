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

        $now = Carbon::now();
        $today = $now->toDateString();
        $dayOfWeek = (int) $now->dayOfWeekIso; // 1 = Senin, ..., 7 = Minggu

        // Ambil jadwal harian sekolah untuk hari ini
        $dailySchedulesRaw = $settings['presensi_daily_schedules'] ?? null;
        $decodedDaily = $dailySchedulesRaw ? json_decode($dailySchedulesRaw, true) : [];
        $todayDaily = $decodedDaily[$dayOfWeek] ?? null;

        $globalStart = $settings['presensi_work_start'] ?? '07:00';
        $globalEnd   = $settings['presensi_work_end'] ?? '15:00';

        // Cek jika ada jadwal khusus sekolah (Special Schedule)
        $specialSchedule = \App\Models\SpecialSchedule::where('is_active', true)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->first();

        if ($specialSchedule) {
            $kioskWorkStart = $specialSchedule->time_in;
            $kioskWorkEnd   = $specialSchedule->time_out;
            $kioskScheduleName = $specialSchedule->name;
        } elseif ($todayDaily && !empty($todayDaily['is_active'])) {
            $kioskWorkStart = $todayDaily['start'] ?? $globalStart;
            $kioskWorkEnd   = $todayDaily['end'] ?? $globalEnd;
            $kioskScheduleName = 'Jadwal ' . (\App\Models\DutySchedule::getDayNames()[$dayOfWeek] ?? '');
        } else {
            $kioskWorkStart = $globalStart;
            $kioskWorkEnd   = $globalEnd;
            $kioskScheduleName = 'Jadwal Standar';
        }

        // Ambil data presensi hari ini diurutkan sesuai urutan jam kedatangan (time_in ASC)
        $attendances = Attendance::with('user:id,name')
            ->where('date', $today)
            ->orderBy('time_in', 'asc')
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

        $totalPegawai = User::whereDoesntHave('roles', function ($q) {
            $q->whereIn('name', ['admin', 'superadmin']);
        })->count();

        $stats = [
            'total_hadir' => $attendances->count(),
            'tepat_waktu' => $attendances->where('status', 'hadir')->count(),
            'terlambat' => $attendances->where('status', 'terlambat')->count(),
            'total_pegawai' => $totalPegawai,
        ];

        return Inertia::render('Attendance/RFIDKiosk', [
            'initialAttendances' => $attendances,
            'initialStats' => $stats,
            'todayWorkSchedule' => [
                'work_start' => substr($kioskWorkStart, 0, 5),
                'work_end'   => substr($kioskWorkEnd, 0, 5),
                'schedule_name' => $kioskScheduleName,
            ],
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

        // Ambil jam kerja efektif pegawai (jadwal khusus/event, piket guru, harian, atau individual)
        $schedule = $user->getEffectiveWorkSchedule($date);
        $workStart = $schedule['work_start'] ?? ($settings['presensi_work_start'] ?? '07:00');
        $workEnd = $schedule['work_end'] ?? ($settings['presensi_work_end'] ?? '15:00');
        $lateTolerance = (int) ($schedule['late_tolerance'] ?? ($settings['presensi_late_tolerance'] ?? 15));

        $workStartTime = Carbon::parse($date . ' ' . $workStart);
        $toleranceEnd = $workStartTime->copy()->addMinutes($lateTolerance);

        if (!$attendance) {
            // Belum absen masuk -> CHECK IN
            $status = $now->greaterThan($toleranceEnd) ? 'terlambat' : 'hadir';

            $notePrefix = 'Presensi via Mesin RFID';
            if (!empty($schedule['is_piket']) && !empty($schedule['piket_name'])) {
                $notePrefix .= " ({$schedule['piket_name']})";
            } elseif (!empty($schedule['is_special']) && !empty($schedule['special_name'])) {
                $notePrefix .= " ({$schedule['special_name']})";
            }

            $newAtt = Attendance::create([
                'user_id' => $user->id,
                'date' => $date,
                'time_in' => $time,
                'status' => $status,
                'notes' => $notePrefix,
            ]);

            return response()->json([
                'success' => true,
                'type' => 'in',
                'user' => [
                    'name' => $user->name,
                    'status' => $status,
                    'time' => $time,
                    'work_start' => $workStart,
                    'is_piket' => !empty($schedule['is_piket']),
                    'schedule_info' => !empty($schedule['piket_name']) ? $schedule['piket_name'] : (!empty($schedule['special_name']) ? $schedule['special_name'] : null),
                ],
                'attendance' => [
                    'id' => $newAtt->id,
                    'name' => $user->name,
                    'time_in' => $time,
                    'time_out' => null,
                    'status' => $status,
                    'notes' => $notePrefix,
                ],
                'message' => "Selamat datang, {$user->name}!" . ($status === 'terlambat' ? ' (Terlambat)' : ''),
            ]);
        } else {
            // Sudah absen masuk
            // Update time_out (Check out)
            $workEndTime = Carbon::parse($date . ' ' . $workEnd);
            $isPulangCepat = $now->lessThan($workEndTime);

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
                    'work_end' => $workEnd,
                ],
                'attendance' => [
                    'id' => $attendance->id,
                    'name' => $user->name,
                    'time_in' => $attendance->time_in,
                    'time_out' => $time,
                    'status' => $attendance->status,
                    'notes' => $attendance->notes,
                ],
                'message' => "Sampai jumpa, {$user->name}!" . ($isPulangCepat ? ' (Pulang Cepat)' : ''),
            ]);
        }
    }
}
