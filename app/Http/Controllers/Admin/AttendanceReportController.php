<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AttendanceDailyExport;
use App\Exports\AttendanceMonthlyExport;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Division;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceReportController extends Controller
{
    public function index(Request $request)
    {
        $currentMonth = $request->input('month', Carbon::today()->format('Y-m'));
        
        // Validasi format YYYY-MM
        try {
            $monthCarbon = Carbon::parse($currentMonth . '-01');
        } catch (\Exception $e) {
            $currentMonth = Carbon::today()->format('Y-m');
            $monthCarbon = Carbon::parse($currentMonth . '-01');
        }

        $startOfMonth = $monthCarbon->copy()->startOfMonth()->format('Y-m-d');
        $endOfMonth = $monthCarbon->copy()->endOfMonth()->format('Y-m-d');
        $daysInMonth = $monthCarbon->daysInMonth;

        // Tanggal terpilih untuk mode detail harian
        $defaultDate = (Carbon::today()->format('Y-m') === $currentMonth) 
            ? Carbon::today()->format('Y-m-d') 
            : $startOfMonth;
        $selectedDate = $request->input('date', $defaultDate);
        
        // Mode tampilan: 'monthly' (default) atau 'daily'
        $viewMode = $request->input('view_mode', 'monthly');

        $divisionFilter = $request->input('division');
        $statusFilter = $request->input('status');
        $search = $request->input('search');

        // Helper format tanggal Y-m-d untuk mencocokkan Carbon object dan string
        $formatDate = function ($date) {
            if ($date instanceof \Carbon\Carbon) {
                return $date->format('Y-m-d');
            }
            return substr((string) $date, 0, 10);
        };

        // Query seluruh user pegawai (kecuali admin & superadmin)
        $userQuery = User::whereDoesntHave('roles', function ($q) {
            $q->whereIn('name', ['admin', 'superadmin']);
        });

        if ($divisionFilter) {
            $userQuery->where('divisi', $divisionFilter);
        }

        if ($search) {
            $userQuery->where('name', 'like', "%{$search}%");
        }

        $allUsers = $userQuery->orderBy('name')->get();

        // Ambil semua data presensi di bulan ini
        $monthlyAttendances = Attendance::with('approver')->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->whereIn('user_id', $allUsers->pluck('id'))
            ->get();

        // Hitung hari kerja efektif yang sudah berlalu di bulan ini (Senin - Jumat)
        $today = Carbon::today();
        $isCurrentMonth = ($today->format('Y-m') === $currentMonth);
        $effectiveEndDay = $isCurrentMonth ? $today->day : $daysInMonth;

        $workdaysCount = 0;
        $daysList = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateObj = Carbon::parse($currentMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT));
            $isWeekend = $dateObj->isWeekend();
            $isPastOrToday = ($d <= $effectiveEndDay);
            $targetDateStr = $dateObj->format('Y-m-d');
            
            if (!$isWeekend && $isPastOrToday) {
                $workdaysCount++;
            }

            // Hitung total hadir di tanggal tersebut
            $dayAtts = $monthlyAttendances->filter(fn($a) => $formatDate($a->date) === $targetDateStr);
            $daysList[] = [
                'day'         => $d,
                'date'        => $targetDateStr,
                'day_name'    => $dateObj->locale('id')->isoFormat('dd'),
                'is_weekend'  => $isWeekend,
                'is_today'    => $dateObj->isToday(),
                'is_future'   => $isCurrentMonth && ($d > $today->day),
                'total_hadir' => $dayAtts->count(),
            ];
        }

        $effectiveWorkdays = max(1, $workdaysCount);

        // Rekap Matriks Bulanan per User
        $monthlyReportData = $allUsers->map(function ($u) use ($monthlyAttendances, $effectiveWorkdays, $daysInMonth, $currentMonth, $formatDate) {
            $userAtts = $monthlyAttendances->where('user_id', $u->id)->keyBy(fn($a) => $formatDate($a->date));
            $schedule = $u->getEffectiveWorkSchedule();

            $hadirCount = 0;
            $terlambatCount = 0;
            $dinasLuarCount = 0;
            $pulangCepatCount = 0;

            $dailyRecords = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dateKey = $currentMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                $att = $userAtts->get($dateKey);

                if ($att) {
                    if ($att->status === 'hadir') $hadirCount++;
                    elseif ($att->status === 'terlambat') $terlambatCount++;
                    elseif ($att->status === 'dinas_luar') $dinasLuarCount++;

                    // Cek pulang mendahului / pulang cepat jika ada time_out dan jadwal work_end
                    $daySchedule = $u->getEffectiveWorkSchedule($dateKey);
                    $isPulangCepat = false;
                    if ($att->time_out && !empty($daySchedule['work_end'])) {
                        $outTime = substr($att->time_out, 0, 5);
                        $endTime = substr($daySchedule['work_end'], 0, 5);
                        if ($outTime < $endTime) {
                            $isPulangCepat = true;
                            $pulangCepatCount++;
                        }
                    }

                    $dailyRecords[$d] = [
                        'id'                     => $att->id,
                        'date'                   => $dateKey,
                        'time_in'                => $att->time_in ? substr($att->time_in, 0, 5) : null,
                        'time_out'               => $att->time_out ? substr($att->time_out, 0, 5) : null,
                        'status'                 => $att->status,
                        'is_pulang_cepat'        => $isPulangCepat,
                        'is_early_departure'     => (bool) ($att->is_early_departure || $isPulangCepat),
                        'early_departure_reason' => $att->early_departure_reason,
                        'approval_status'        => $att->approval_status ?? 'approved',
                        'approval_type'          => $att->approval_type ?? 'none',
                        'rejection_note'         => $att->rejection_note,
                        'attachment'             => $att->attachment,
                        'is_overtime'            => (bool) $att->is_overtime,
                        'overtime_minutes'       => $att->overtime_minutes,
                        'overtime_activity'      => $att->overtime_activity,
                        'distance_in'            => $att->distance_in,
                        'distance_out'           => $att->distance_out,
                        'photo_in'               => $att->photo_in,
                        'notes'                  => $att->notes,
                        'late_reason'            => $att->late_reason,
                        'late_photo'             => $att->late_photo,
                    ];
                } else {
                    $dailyRecords[$d] = null;
                }
            }

            $totalKehadiran = $hadirCount + $terlambatCount + $dinasLuarCount;
            $belumHadirCount = max(0, $effectiveWorkdays - $totalKehadiran);
            $rate = round(($totalKehadiran / $effectiveWorkdays) * 100);

            return [
                'user_id'            => $u->id,
                'name'               => $u->name,
                'nip'                => $u->nip,
                'divisi'             => $u->divisi,
                'avatar'             => $u->avatar,
                'work_schedule'      => $schedule['work_start'] . ' - ' . $schedule['work_end'],
                'work_start'         => $schedule['work_start'],
                'work_end'           => $schedule['work_end'],
                'is_custom_schedule' => $schedule['is_custom'],
                'total_hadir'        => $hadirCount,
                'total_terlambat'    => $terlambatCount,
                'total_dinas_luar'   => $dinasLuarCount,
                'total_pulang_cepat' => $pulangCepatCount,
                'total_kehadiran'    => $totalKehadiran,
                'total_belum_hadir'  => $belumHadirCount,
                'total_hari_kerja'   => $effectiveWorkdays,
                'attendance_rate'    => min(100, $rate),
                'daily_records'      => $dailyRecords,
            ];
        });

        // Detail Harian pada Tanggal Terpilih ($selectedDate)
        $dailyAttendances = $monthlyAttendances->filter(fn($a) => $formatDate($a->date) === $selectedDate)->keyBy('user_id');
        $dailyReportData = $allUsers->map(function ($u) use ($dailyAttendances, $selectedDate) {
            $att = $dailyAttendances->get($u->id);
            $schedule = $u->getEffectiveWorkSchedule($selectedDate);

            $isPulangCepat = false;
            if ($att?->time_out && !empty($schedule['work_end'])) {
                $outTime = substr($att->time_out, 0, 5);
                $endTime = substr($schedule['work_end'], 0, 5);
                if ($outTime < $endTime) {
                    $isPulangCepat = true;
                }
            }

            return [
                'user_id'                => $u->id,
                'name'                   => $u->name,
                'nip'                    => $u->nip,
                'divisi'                 => $u->divisi,
                'avatar'                 => $u->avatar,
                'work_schedule'          => $schedule['work_start'] . ' - ' . $schedule['work_end'],
                'work_start'             => $schedule['work_start'],
                'work_end'               => $schedule['work_end'],
                'late_tolerance'         => $schedule['late_tolerance'],
                'is_custom_schedule'     => $schedule['is_custom'],
                'attendance_id'          => $att?->id,
                'time_in'                => $att?->time_in ? substr($att->time_in, 0, 5) : null,
                'time_out'               => $att?->time_out ? substr($att->time_out, 0, 5) : null,
                'distance_in'            => $att?->distance_in,
                'distance_out'           => $att?->distance_out,
                'status'                 => $att ? $att->status : 'belum_hadir',
                'is_pulang_cepat'        => $isPulangCepat,
                'is_early_departure'     => (bool) ($att?->is_early_departure || $isPulangCepat),
                'early_departure_reason' => $att?->early_departure_reason,
                'approval_status'        => $att?->approval_status ?? 'approved',
                'approval_type'          => $att?->approval_type ?? 'none',
                'rejection_note'         => $att?->rejection_note,
                'attachment'             => $att?->attachment,
                'approved_by_name'       => $att?->approver?->name,
                'is_overtime'            => (bool) $att?->is_overtime,
                'overtime_minutes'       => $att?->overtime_minutes,
                'overtime_activity'      => $att?->overtime_activity,
                'photo_in'               => $att?->photo_in,
                'photo_out'              => $att?->photo_out,
                'notes'                  => $att?->notes,
                'late_reason'            => $att?->late_reason,
                'late_photo'             => $att?->late_photo,
            ];
        });

        if ($statusFilter) {
            if ($statusFilter === 'pending') {
                $dailyReportData = $dailyReportData->where('approval_status', 'pending')->values();
            } else {
                $dailyReportData = $dailyReportData->where('status', $statusFilter)->values();
            }
        }

        // Statistik Bulanan
        $totalUsers = $allUsers->count();
        $avgAttendanceRate = $totalUsers > 0 ? round($monthlyReportData->avg('attendance_rate')) : 0;
        $monthlyStats = [
            'total_pegawai'              => $totalUsers,
            'avg_attendance_rate'        => $avgAttendanceRate,
            'total_hadir_sebulan'        => $monthlyAttendances->where('status', 'hadir')->count(),
            'total_terlambat_sebulan'    => $monthlyAttendances->where('status', 'terlambat')->count(),
            'total_dinas_luar_sebulan'   => $monthlyAttendances->where('status', 'dinas_luar')->count(),
            'total_izin_sakit_sebulan'   => $monthlyAttendances->whereIn('status', ['izin', 'sakit'])->count(),
            'total_pulang_cepat_sebulan' => $monthlyReportData->sum('total_pulang_cepat'),
            'total_pending_approval'     => $monthlyAttendances->where('approval_status', 'pending')->count(),
            'effective_workdays'         => $effectiveWorkdays,
        ];

        // Statistik Harian pada Tanggal Terpilih
        $dailyStats = [
            'total_pegawai'    => $totalUsers,
            'hadir'            => $dailyAttendances->where('status', 'hadir')->count(),
            'terlambat'        => $dailyAttendances->where('status', 'terlambat')->count(),
            'dinas_luar'       => $dailyAttendances->where('status', 'dinas_luar')->count(),
            'izin_sakit'       => $dailyAttendances->whereIn('status', ['izin', 'sakit'])->count(),
            'pulang_cepat'     => $dailyReportData->where('is_pulang_cepat', true)->count(),
            'pending_approval' => $dailyAttendances->where('approval_status', 'pending')->count(),
            'belum_hadir'      => max(0, $totalUsers - $dailyAttendances->count()),
        ];

        $divisions = Division::orderBy('name')->get();

        $settings = Setting::all()->pluck('value', 'key')->toArray();
        $officeLocation = [
            'latitude'       => (float) ($settings['presensi_latitude'] ?? -7.7956),
            'longitude'      => (float) ($settings['presensi_longitude'] ?? 110.3695),
            'radius_meters'  => (int) ($settings['presensi_radius_meters'] ?? 100),
            'work_start'     => $settings['presensi_work_start'] ?? '07:00',
            'work_end'       => $settings['presensi_work_end'] ?? '15:00',
            'late_tolerance' => (int) ($settings['presensi_late_tolerance'] ?? 15),
        ];

        return Inertia::render('Admin/Attendance/Index', [
            'monthlyReportData' => $monthlyReportData,
            'dailyReportData'   => $dailyReportData,
            'monthlyStats'      => $monthlyStats,
            'dailyStats'        => $dailyStats,
            'daysList'          => $daysList,
            'daysInMonth'       => $daysInMonth,
            'officeLocation'    => $officeLocation,
            'filters'           => [
                'month'     => $currentMonth,
                'date'      => $selectedDate,
                'view_mode' => $viewMode,
                'division'  => $divisionFilter,
                'status'    => $statusFilter,
                'search'    => $search,
            ],
            'formattedMonth'    => $monthCarbon->locale('id')->isoFormat('MMMM Y'),
            'formattedDate'     => Carbon::parse($selectedDate)->locale('id')->isoFormat('dddd, D MMMM Y'),
            'divisions'         => $divisions,
            'isSuperadmin'      => (bool) (auth()->user()?->isActualSuperadmin() || auth()->user()?->hasRole('superadmin')),
        ]);
    }

    /**
     * Ekspor rekap presensi ke file Excel (.xlsx)
     */
    public function export(Request $request)
    {
        $type = $request->input('type', 'monthly'); // 'monthly' | 'daily'
        $currentMonth = $request->input('month', Carbon::today()->format('Y-m'));
        $date = $request->input('date', Carbon::today()->format('Y-m-d'));
        $divisionFilter = $request->input('division');

        $userQuery = User::whereDoesntHave('roles', function ($q) {
            $q->whereIn('name', ['admin', 'superadmin']);
        });

        if ($divisionFilter) {
            $userQuery->where('divisi', $divisionFilter);
        }

        $allUsers = $userQuery->orderBy('name')->get();

        if ($type === 'daily') {
            $attendances = Attendance::whereDate('date', $date)
                ->whereIn('user_id', $allUsers->pluck('id'))
                ->get()
                ->keyBy('user_id');

            $dailyReportData = $allUsers->map(function ($u) use ($attendances, $date) {
                $att = $attendances->get($u->id);
                $schedule = $u->getEffectiveWorkSchedule($date);

                $notes = $att?->notes;
                if ($att?->is_early_departure && $att?->early_departure_reason) {
                    $edText = "Izin Pulang Mendahului: {$att->early_departure_reason}";
                    $notes = $notes ? "{$notes} | {$edText}" : $edText;
                }
                if ($att?->is_overtime) {
                    $otText = "Lembur {$att->overtime_minutes}m: {$att->overtime_activity}";
                    $notes = $notes ? "{$notes} | {$otText}" : $otText;
                }
                if ($att?->approval_status === 'pending') {
                    $notes = $notes ? "{$notes} (Menunggu Persetujuan)" : "(Menunggu Persetujuan)";
                } elseif ($att?->approval_status === 'rejected') {
                    $notes = $notes ? "{$notes} (Ditolak: {$att->rejection_note})" : "(Ditolak: {$att->rejection_note})";
                }

                return [
                    'name'          => $u->name,
                    'nip'           => $u->nip,
                    'divisi'        => $u->divisi,
                    'work_schedule' => $schedule['work_start'] . ' - ' . $schedule['work_end'],
                    'time_in'       => $att?->time_in ? substr($att->time_in, 0, 5) : null,
                    'distance_in'   => $att?->distance_in,
                    'time_out'      => $att?->time_out ? substr($att->time_out, 0, 5) : null,
                    'distance_out'  => $att?->distance_out,
                    'status'        => $att ? $att->status : 'belum_hadir',
                    'notes'         => $notes,
                ];
            });

            $formattedDate = Carbon::parse($date)->locale('id')->isoFormat('dddd, D MMMM Y');
            $fileName = "Rekap_Presensi_Harian_{$date}.xlsx";

            return Excel::download(
                new AttendanceDailyExport($dailyReportData, $formattedDate),
                $fileName
            );
        }

        // Mode Rekap Bulanan Matriks (Default)
        $monthCarbon = Carbon::parse($currentMonth . '-01');
        $startOfMonth = $monthCarbon->copy()->startOfMonth()->format('Y-m-d');
        $endOfMonth = $monthCarbon->copy()->endOfMonth()->format('Y-m-d');
        $daysInMonth = $monthCarbon->daysInMonth;

        $monthlyAttendances = Attendance::whereBetween('date', [$startOfMonth, $endOfMonth])
            ->whereIn('user_id', $allUsers->pluck('id'))
            ->get();

        $today = Carbon::today();
        $isCurrentMonth = ($today->format('Y-m') === $currentMonth);
        $effectiveEndDay = $isCurrentMonth ? $today->day : $daysInMonth;

        $workdaysCount = 0;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateObj = Carbon::parse($currentMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT));
            if (!$dateObj->isWeekend() && $d <= $effectiveEndDay) {
                $workdaysCount++;
            }
        }
        $effectiveWorkdays = max(1, $workdaysCount);

        $monthlyReportData = $allUsers->map(function ($u) use ($monthlyAttendances, $effectiveWorkdays, $daysInMonth, $currentMonth, $formatDate) {
            $userAtts = $monthlyAttendances->where('user_id', $u->id)->keyBy(fn($a) => $formatDate($a->date));
            $schedule = $u->getEffectiveWorkSchedule();

            $hadirCount = 0;
            $terlambatCount = 0;
            $dinasLuarCount = 0;
            $pulangCepatCount = 0;

            $dailyRecords = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dateKey = $currentMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                $att = $userAtts->get($dateKey);

                if ($att) {
                    if ($att->status === 'hadir') $hadirCount++;
                    elseif ($att->status === 'terlambat') $terlambatCount++;
                    elseif ($att->status === 'dinas_luar') $dinasLuarCount++;

                    $daySchedule = $u->getEffectiveWorkSchedule($dateKey);
                    $isPulangCepat = false;
                    if ($att->time_out && !empty($daySchedule['work_end'])) {
                        $outTime = substr($att->time_out, 0, 5);
                        $endTime = substr($daySchedule['work_end'], 0, 5);
                        if ($outTime < $endTime) {
                            $isPulangCepat = true;
                            $pulangCepatCount++;
                        }
                    }

                    $dailyRecords[$d] = [
                        'time_in'         => $att->time_in ? substr($att->time_in, 0, 5) : null,
                        'time_out'        => $att->time_out ? substr($att->time_out, 0, 5) : null,
                        'status'          => $att->status,
                        'is_pulang_cepat' => $isPulangCepat,
                    ];
                } else {
                    $dailyRecords[$d] = null;
                }
            }

            $totalKehadiran = $hadirCount + $terlambatCount + $dinasLuarCount;
            $rate = round(($totalKehadiran / $effectiveWorkdays) * 100);

            return [
                'name'               => $u->name,
                'nip'                => $u->nip,
                'divisi'             => $u->divisi,
                'work_schedule'      => $schedule['work_start'] . ' - ' . $schedule['work_end'],
                'total_hadir'        => $hadirCount,
                'total_terlambat'    => $terlambatCount,
                'total_dinas_luar'   => $dinasLuarCount,
                'total_pulang_cepat' => $pulangCepatCount,
                'total_kehadiran'    => $totalKehadiran,
                'total_hari_kerja'   => $effectiveWorkdays,
                'attendance_rate'    => min(100, $rate),
                'daily_records'      => $dailyRecords,
            ];
        });

        $formattedMonth = $monthCarbon->locale('id')->isoFormat('MMMM Y');
        $fileName = "Rekap_Presensi_Bulanan_{$currentMonth}.xlsx";

        return Excel::download(
            new AttendanceMonthlyExport($monthlyReportData, $formattedMonth, $daysInMonth),
            $fileName
        );
    }

    /**
     * Update titik koordinat GPS & radius sekolah oleh Admin
     */
    public function updateLocation(Request $request)
    {
        $data = $request->validate([
            'latitude'       => 'required|numeric|between:-90,90',
            'longitude'      => 'required|numeric|between:-180,180',
            'radius_meters'  => 'required|numeric|min:10|max:10000',
            'work_start'     => 'nullable|string|max:10',
            'work_end'       => 'nullable|string|max:10',
            'late_tolerance' => 'nullable|numeric|min:0|max:120',
        ]);

        Setting::updateOrCreate(['key' => 'presensi_latitude'], ['value' => (string) $data['latitude']]);
        Setting::updateOrCreate(['key' => 'presensi_longitude'], ['value' => (string) $data['longitude']]);
        Setting::updateOrCreate(['key' => 'presensi_radius_meters'], ['value' => (string) $data['radius_meters']]);

        if (isset($data['work_start'])) {
            Setting::updateOrCreate(['key' => 'presensi_work_start'], ['value' => (string) $data['work_start']]);
        }
        if (isset($data['work_end'])) {
            Setting::updateOrCreate(['key' => 'presensi_work_end'], ['value' => (string) $data['work_end']]);
        }
        if (isset($data['late_tolerance'])) {
            Setting::updateOrCreate(['key' => 'presensi_late_tolerance'], ['value' => (string) $data['late_tolerance']]);
        }

        return redirect()->back()->with('success', 'Titik koordinat dan radius sekolah berhasil disimpan.');
    }

    /**
     * Edit / Simpan Presensi Masuk & Pulang User (Khusus Superadmin)
     */
    public function updateRecord(Request $request)
    {
        $currentUser = auth()->user();
        if (!$currentUser->isActualSuperadmin() && !$currentUser->hasRole('superadmin')) {
            abort(403, 'Hanya Superadmin yang memiliki hak akses mengedit presensi pegawai.');
        }

        $data = $request->validate([
            'user_id'   => 'required|exists:users,id',
            'date'      => 'required|date',
            'time_in'   => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
            'time_out'  => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
            'status'    => 'required|in:hadir,terlambat,dinas_luar,izin,sakit,alpa',
            'notes'     => 'nullable|string|max:500',
        ]);

        $user = User::findOrFail($data['user_id']);

        $timeIn = !empty($data['time_in']) ? Carbon::parse($data['time_in'])->format('H:i:s') : null;
        $timeOut = !empty($data['time_out']) ? Carbon::parse($data['time_out'])->format('H:i:s') : null;

        $attendance = Attendance::updateOrCreate(
            [
                'user_id' => $data['user_id'],
                'date'    => $data['date'],
            ],
            [
                'time_in'  => $timeIn,
                'time_out' => $timeOut,
                'status'   => $data['status'],
                'notes'    => $data['notes'],
            ]
        );

        $formattedDate = Carbon::parse($data['date'])->locale('id')->isoFormat('D MMMM Y');
        return redirect()->back()->with('success', "Presensi {$user->name} tanggal {$formattedDate} berhasil disimpan oleh Superadmin.");
    }

    /**
     * Hapus Record Presensi User (Khusus Superadmin)
     */
    public function deleteRecord($id)
    {
        $currentUser = auth()->user();
        if (!$currentUser->isActualSuperadmin() && !$currentUser->hasRole('superadmin')) {
            abort(403, 'Hanya Superadmin yang memiliki hak akses menghapus presensi pegawai.');
        }

        $attendance = Attendance::findOrFail($id);
        $userName = $attendance->user?->name ?? 'Pegawai';
        $formattedDate = Carbon::parse($attendance->date)->locale('id')->isoFormat('D MMMM Y');
        
        $attendance->delete();

        return redirect()->back()->with('success', "Data presensi {$userName} pada {$formattedDate} berhasil dihapus.");
    }

    /**
     * Menyetujui atau Menolak Permohonan Izin / Sakit / Pulang Mendahului
     */
    public function updateApproval(Request $request)
    {
        $data = $request->validate([
            'attendance_id'   => 'required|exists:attendances,id',
            'approval_status' => 'required|in:approved,rejected',
            'rejection_note'  => 'required_if:approval_status,rejected|nullable|string|max:1000',
        ], [
            'rejection_note.required_if' => 'Keterangan alasan penolakan wajib diisi jika izin ditolak.',
        ]);

        $attendance = Attendance::findOrFail($data['attendance_id']);
        $attendance->update([
            'approval_status' => $data['approval_status'],
            'rejection_note'  => $data['approval_status'] === 'rejected' ? $data['rejection_note'] : null,
            'approved_by'     => auth()->id(),
            'approved_at'     => Carbon::now(),
        ]);

        $statusText = $data['approval_status'] === 'approved' ? 'disetujui' : 'ditolak';
        $userName = $attendance->user?->name ?? 'Pegawai';
        return redirect()->back()->with('success', "Permohonan izin {$userName} berhasil {$statusText}.");
    }
}

