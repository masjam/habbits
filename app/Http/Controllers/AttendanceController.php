<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    /**
     * Tampilan utama presensi pegawai.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $today = Carbon::today()->format('Y-m-d');

        // Presensi hari ini
        $todayAttendance = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        // Bulan yang dipilih (default bulan berjalan)
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        $startOfMonth = Carbon::parse($month . '-01')->startOfMonth()->format('Y-m-d');
        $endOfMonth = Carbon::parse($month . '-01')->endOfMonth()->format('Y-m-d');

        $monthlyAttendances = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->orderBy('date', 'desc')
            ->get();

        // Statistik bulanan
        $stats = [
            'total_hadir' => $monthlyAttendances->where('status', 'hadir')->count(),
            'total_terlambat' => $monthlyAttendances->where('status', 'terlambat')->count(),
            'total_dinas_luar' => $monthlyAttendances->where('status', 'dinas_luar')->count(),
            'total_izin_sakit' => $monthlyAttendances->whereIn('status', ['izin', 'sakit'])->count(),
        ];

        // Konfigurasi Lokasi Sekolah & Jadwal
        $settings = Setting::all()->pluck('value', 'key')->toArray();
        $schedule = $user->getEffectiveWorkSchedule($today);

        $officeLat = (float) ($settings['presensi_latitude'] ?? -7.7956);
        $officeLng = (float) ($settings['presensi_longitude'] ?? 110.3695);
        $officeRadius = (int) ($settings['presensi_radius_meters'] ?? 100);

        return Inertia::render('Attendance/Index', [
            'todayAttendance'    => $todayAttendance,
            'monthlyAttendances' => $monthlyAttendances,
            'stats'              => $stats,
            'currentMonth'       => $month,
            'schedule'           => $schedule,
            'officeLocation'     => [
                'latitude' => $officeLat,
                'longitude' => $officeLng,
                'radius' => $officeRadius,
            ],
            'serverTime'         => Carbon::now()->format('H:i:s'),
            'serverDate'         => Carbon::now()->locale('id')->isoFormat('dddd, D MMMM Y'),
        ]);
    }

    /**
     * Presensi Masuk (Check-in)
     */
    public function checkIn(Request $request)
    {
        $user = Auth::user();
        $today = Carbon::today()->format('Y-m-d');

        // Cek apakah sudah check-in hari ini
        $existing = Attendance::where('user_id', $user->id)->where('date', $today)->first();
        if ($existing && $existing->time_in) {
            return redirect()->back()->with('error', 'Anda sudah melakukan presensi masuk hari ini.');
        }

        $request->validate([
            'latitude'    => 'required|numeric',
            'longitude'   => 'required|numeric',
            'photo'       => 'nullable|string', // Base64 data URI
            'notes'       => 'nullable|string|max:500',
            'late_reason' => 'nullable|string|max:500',
            'late_photo'  => 'nullable|string',
        ]);

        $settings = Setting::all()->pluck('value', 'key')->toArray();
        $officeLat = (float) ($settings['presensi_latitude'] ?? -7.7956);
        $officeLng = (float) ($settings['presensi_longitude'] ?? 110.3695);
        $officeRadius = (int) ($settings['presensi_radius_meters'] ?? 100);

        $distance = $this->calculateDistance(
            (float) $request->latitude,
            (float) $request->longitude,
            $officeLat,
            $officeLng
        );

        $isInsideRadius = $distance <= $officeRadius;
        $photoPath = null;
        $latePhotoPath = null;
        $lateReason = null;
        $status = 'hadir';

        // Aturan facecapture sesuai instruksi:
        // Jika di luar radius: WAJIB foto selfie & keterangan dinas luar
        // Jika di dalam radius: tidak wajib foto selfie
        if (!$isInsideRadius) {
            if (!$request->photo) {
                return redirect()->back()->with('error', "Anda berada di luar area sekolah ({$distance} meter). Presensi di luar area wajib menyertakan foto selfie kamera.");
            }

            $photoPath = $this->storeBase64Photo($request->photo, 'checkin');
            $status = 'dinas_luar';
        } else {
            // Cek keterlambatan berdasarkan jam kerja efektif user
            $schedule = $user->getEffectiveWorkSchedule($today);
            $workStartTime = Carbon::parse($today . ' ' . $schedule['work_start']);
            $toleranceLimit = $workStartTime->copy()->addMinutes($schedule['late_tolerance']);
            $now = Carbon::now();

            if ($now->greaterThan($toleranceLimit)) {
                $status = 'terlambat';
                $lateReason = trim($request->late_reason ?? $request->notes ?? '');
                if (empty($lateReason)) {
                    return redirect()->back()->with('error', 'Presensi terlambat wajib menyertakan catatan alasan keterlambatan.');
                }

                // Opsional upload foto bukti keterlambatan
                if ($request->late_photo) {
                    $latePhotoPath = $this->storeBase64Photo($request->late_photo, 'late');
                }
            } else {
                $status = 'hadir';
            }

            // Jika user opsional melampirkan foto selfie masuk
            if ($request->photo) {
                $photoPath = $this->storeBase64Photo($request->photo, 'checkin');
            }
        }

        Attendance::updateOrCreate(
            ['user_id' => $user->id, 'date' => $today],
            [
                'time_in'     => Carbon::now()->format('H:i:s'),
                'lat_in'      => $request->latitude,
                'lng_in'      => $request->longitude,
                'distance_in' => $distance,
                'photo_in'    => $photoPath,
                'status'      => $status,
                'notes'       => $request->notes,
                'late_reason' => $lateReason,
                'late_photo'  => $latePhotoPath,
            ]
        );

        $msg = $isInsideRadius 
            ? ($status === 'terlambat' 
                ? "Presensi masuk terlambat berhasil dicatat dengan alasan: {$lateReason}." 
                : "Presensi masuk berhasil (hadir tepat waktu, jarak {$distance}m).") 
            : "Presensi dinas luar berhasil dicatat dengan verifikasi foto selfie (jarak {$distance}m).";

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Presensi Pulang (Check-out)
     */
    public function checkOut(Request $request)
    {
        $user = Auth::user();
        $today = Carbon::today()->format('Y-m-d');

        $attendance = Attendance::where('user_id', $user->id)->where('date', $today)->first();
        if (!$attendance || !$attendance->time_in) {
            return redirect()->back()->with('error', 'Anda belum melakukan presensi masuk hari ini.');
        }

        if ($attendance->time_out) {
            return redirect()->back()->with('error', 'Anda sudah melakukan presensi pulang hari ini.');
        }

        $request->validate([
            'latitude'               => 'required|numeric',
            'longitude'              => 'required|numeric',
            'photo'                  => 'nullable|string',
            'is_overtime'            => 'nullable|boolean',
            'overtime_activity'      => 'nullable|string|max:1000',
            'early_departure_reason' => 'nullable|string|max:1000',
        ]);

        $settings = Setting::all()->pluck('value', 'key')->toArray();
        $officeLat = (float) ($settings['presensi_latitude'] ?? -7.7956);
        $officeLng = (float) ($settings['presensi_longitude'] ?? 110.3695);
        $officeRadius = (int) ($settings['presensi_radius_meters'] ?? 100);

        $distance = $this->calculateDistance(
            (float) $request->latitude,
            (float) $request->longitude,
            $officeLat,
            $officeLng
        );

        $photoPath = null;
        if ($distance > $officeRadius && !$request->photo) {
            return redirect()->back()->with('error', "Anda berada di luar area sekolah ({$distance} meter). Presensi pulang di luar area wajib menyertakan foto selfie kamera.");
        }

        if ($request->photo) {
            $photoPath = $this->storeBase64Photo($request->photo, 'checkout');
        }

        $schedule = $user->getEffectiveWorkSchedule($today);
        $now = Carbon::now();
        $isOvertime = false;
        $overtimeMinutes = null;
        $overtimeActivity = null;

        $isEarlyDeparture = false;
        $earlyDepartureReason = null;
        $approvalStatus = $attendance->approval_status ?? 'approved';
        $approvalType = $attendance->approval_type ?? 'none';

        if (!empty($schedule['work_end'])) {
            $workEndTime = Carbon::parse($today . ' ' . $schedule['work_end']);
            
            // 1. Cek jika pulang lebih cepat dari jadwal kerja seharusnya (Ijin Pulang Mendahului)
            if ($now->lessThan($workEndTime)) {
                $earlyReason = trim((string) $request->input('early_departure_reason'));
                if (empty($earlyReason)) {
                    return redirect()->back()->with('error', "Anda pulang mendahului jadwal ({$schedule['work_end']}). Wajib menuliskan keterangan alasan izin pulang mendahului.");
                }
                $isEarlyDeparture = true;
                $earlyDepartureReason = $earlyReason;
                $approvalType = 'pulang_mendahului';
                $approvalStatus = 'pending';
            }

            // 2. Cek apakah presensi pulang melebihi 60 menit setelah jam kerja (Lembur)
            if ($now->greaterThan($workEndTime)) {
                $minutesPast = (int) $workEndTime->diffInMinutes($now);
                if ($minutesPast > 60 && $request->boolean('is_overtime')) {
                    $isOvertime = true;
                    $overtimeMinutes = $minutesPast;
                    $overtimeActivity = $request->input('overtime_activity');
                }
            }
        }

        $attendance->update([
            'time_out'               => $now->format('H:i:s'),
            'lat_out'                => $request->latitude,
            'lng_out'                => $request->longitude,
            'distance_out'           => $distance,
            'photo_out'              => $photoPath,
            'is_overtime'            => $isOvertime,
            'overtime_minutes'       => $overtimeMinutes,
            'overtime_activity'      => $overtimeActivity,
            'is_early_departure'     => $isEarlyDeparture,
            'early_departure_reason' => $earlyDepartureReason,
            'approval_status'        => $approvalStatus,
            'approval_type'          => $approvalType,
        ]);

        $msg = "Presensi pulang berhasil dicatat pada " . $now->format('H:i');
        if ($isEarlyDeparture) {
            $msg .= " (Izin pulang mendahului tercatat dan menunggu persetujuan).";
        } elseif ($isOvertime) {
            $msg .= " (Lembur {$overtimeMinutes} menit).";
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Pengajuan Izin / Sakit Pegawai
     */
    public function storePermit(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'date'       => 'required|date',
            'status'     => 'required|in:izin,sakit',
            'notes'      => 'required|string|max:1000',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
        ]);

        $existing = Attendance::where('user_id', $user->id)->where('date', $request->date)->first();
        if ($existing && ($existing->time_in || $existing->time_out) && !in_array($existing->status, ['izin', 'sakit'])) {
            return redirect()->back()->with('error', 'Anda sudah tercatat melakukan presensi kehadiran pada tanggal tersebut.');
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('attachments/permits', 'public');
        } elseif ($existing?->attachment) {
            $attachmentPath = $existing->attachment;
        }

        Attendance::updateOrCreate(
            ['user_id' => $user->id, 'date' => $request->date],
            [
                'status'          => $request->status,
                'approval_status' => 'pending',
                'approval_type'   => $request->status,
                'notes'           => $request->notes,
                'attachment'      => $attachmentPath,
                'rejection_note'  => null,
            ]
        );

        $formattedDate = Carbon::parse($request->date)->locale('id')->isoFormat('D MMMM Y');
        $label = $request->status === 'izin' ? 'Izin' : 'Sakit';

        return redirect()->back()->with('success', "Permohonan {$label} untuk tanggal {$formattedDate} berhasil diajukan dan menunggu persetujuan Admin.");
    }

    /**
     * Hitung jarak dua titik koordinat bumi menggunakan Haversine Formula (hasil dalam meter).
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $earthRadius = 6371000; // Meter
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($earthRadius * $c);
    }

    /**
     * Simpan foto selfie base64 ke disk public storage.
     */
    private function storeBase64Photo(string $base64Data, string $prefix): string
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
            $data = substr($base64Data, strpos($base64Data, ',') + 1);
            $type = strtolower($type[1]);
            $data = base64_decode($data);

            if ($data === false) {
                throw new \Exception('Gagal memproses gambar base64.');
            }

            $fileName = 'attendances/' . $prefix . '_' . Auth::id() . '_' . time() . '.' . $type;
            Storage::disk('public')->put($fileName, $data);

            return $fileName;
        }

        throw new \Exception('Format gambar tidak valid.');
    }
}
