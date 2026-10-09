<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessAttendanceExport implements ShouldQueue
{
    use Queueable;

    public $type;
    public $currentMonth;
    public $date;
    public $divisionFilter;
    public $userToNotify;

    /**
     * Create a new job instance.
     */
    public function __construct($type, $currentMonth, $date, $divisionFilter, \App\Models\User $userToNotify)
    {
        $this->type = $type;
        $this->currentMonth = $currentMonth;
        $this->date = $date;
        $this->divisionFilter = $divisionFilter;
        $this->userToNotify = $userToNotify;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $type = $this->type;
        $currentMonth = $this->currentMonth;
        $date = $this->date;
        $divisionFilter = $this->divisionFilter;

        $userQuery = \App\Models\User::whereDoesntHave('roles', function ($q) {
            $q->whereIn('name', ['admin', 'superadmin']);
        });

        if ($divisionFilter) {
            $userQuery->where('divisi', $divisionFilter);
        }

        $allUsers = $userQuery->orderBy('name')->get();

        if ($type === 'daily') {
            $attendances = \App\Models\Attendance::whereDate('date', $date)
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

            $formattedDate = \Carbon\Carbon::parse($date)->locale('id')->isoFormat('dddd, D MMMM Y');
            $fileName = "Rekap_Presensi_Harian_" . str_replace('-', '', $date) . "_" . \Illuminate\Support\Str::uuid() . ".xlsx";
            $filePath = "exports/" . $fileName;

            \Maatwebsite\Excel\Facades\Excel::store(
                new \App\Exports\AttendanceDailyExport($dailyReportData, $formattedDate),
                $filePath,
                'public'
            );
            
            $fileUrl = asset("storage/" . $filePath);
            $this->userToNotify->notify(new \App\Notifications\ExportReadyNotification($fileName, $fileUrl, 'Presensi Harian'));
            return;
        }

        if ($type === 'tugas_luar') {
            $monthCarbon = \Carbon\Carbon::parse($currentMonth . '-01');
            $startOfMonth = $monthCarbon->copy()->startOfMonth()->format('Y-m-d');
            $endOfMonth = $monthCarbon->copy()->endOfMonth()->format('Y-m-d');

            $attendances = \App\Models\Attendance::with('user')
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->where('status', 'dinas_luar')
                ->whereIn('user_id', $allUsers->pluck('id'))
                ->orderBy('date')
                ->get();

            $reportData = collect();
            foreach ($attendances as $att) {
                $user = $att->user;
                if (!$user) continue;

                $reportData->push([
                    'name' => $user->name,
                    'divisi' => $user->divisi,
                    'date_formatted' => \Carbon\Carbon::parse($att->date)->locale('id')->isoFormat('dddd, D MMMM Y'),
                    'time_in' => $att->time_in ? substr($att->time_in, 0, 5) : null,
                    'koordinat_in' => ($att->lat_in && $att->lng_in) ? '=HYPERLINK("https://maps.google.com/?q=' . $att->lat_in . ',' . $att->lng_in . '", "Lihat Peta")' : '-',
                    'time_out' => $att->time_out ? substr($att->time_out, 0, 5) : null,
                    'koordinat_out' => ($att->lat_out && $att->lng_out) ? '=HYPERLINK("https://maps.google.com/?q=' . $att->lat_out . ',' . $att->lng_out . '", "Lihat Peta")' : '-',
                    'notes' => $att->notes ?: '-',
                ]);
            }

            $formattedMonth = $monthCarbon->locale('id')->isoFormat('MMMM Y');
            $fileName = "Rekap_Tugas_Luar_" . str_replace('-', '', $currentMonth) . "_" . \Illuminate\Support\Str::uuid() . ".xlsx";
            $filePath = "exports/" . $fileName;

            \Maatwebsite\Excel\Facades\Excel::store(
                new \App\Exports\AttendanceTugasLuarExport($reportData, $formattedMonth),
                $filePath,
                'public'
            );

            $fileUrl = asset("storage/" . $filePath);
            $this->userToNotify->notify(new \App\Notifications\ExportReadyNotification($fileName, $fileUrl, 'Tugas Luar'));
            return;
        }

        // Mode Rekap Bulanan Matriks (Default)
        $monthCarbon = \Carbon\Carbon::parse($currentMonth . '-01');
        $startOfMonth = $monthCarbon->copy()->startOfMonth()->format('Y-m-d');
        $endOfMonth = $monthCarbon->copy()->endOfMonth()->format('Y-m-d');
        $daysInMonth = $monthCarbon->daysInMonth;

        $monthlyAttendances = \App\Models\Attendance::whereBetween('date', [$startOfMonth, $endOfMonth])
            ->whereIn('user_id', $allUsers->pluck('id'))
            ->get();

        $today = \Carbon\Carbon::today();
        $isCurrentMonth = ($today->format('Y-m') === $currentMonth);
        $effectiveEndDay = $isCurrentMonth ? $today->day : $daysInMonth;

        $workdaysCount = 0;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateObj = \Carbon\Carbon::parse($currentMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT));
            if (!$dateObj->isWeekend() && $d <= $effectiveEndDay) {
                $workdaysCount++;
            }
        }
        $effectiveWorkdays = max(1, $workdaysCount);

        $monthlyReportData = $allUsers->map(function ($u) use ($monthlyAttendances, $effectiveWorkdays, $daysInMonth, $currentMonth) {
            $userAtts = $monthlyAttendances->where('user_id', $u->id)->keyBy(fn($a) => \Carbon\Carbon::parse($a->date)->format('Y-m-d'));
            $schedule = $u->getEffectiveWorkSchedule();

            $hadirCount = 0;
            $terlambatCount = 0;
            $dinasLuarCount = 0;
            $pulangCepatCount = 0;
            $pureOntimeCount = 0;

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
                    
                    $isPureOntime = false;
                    if (in_array($att->status, ['hadir', 'dinas_luar']) && $att->time_in && !empty($daySchedule['work_start'])) {
                        $inTime = substr($att->time_in, 0, 5);
                        $startTime = substr($daySchedule['work_start'], 0, 5);
                        if ($inTime <= $startTime) {
                            $isPureOntime = true;
                            $pureOntimeCount++;
                        }
                    }

                    $dailyRecords[$d] = [
                        'time_in'         => $att->time_in ? substr($att->time_in, 0, 5) : null,
                        'time_out'        => $att->time_out ? substr($att->time_out, 0, 5) : null,
                        'status'          => $att->status,
                        'is_pulang_cepat' => $isPulangCepat,
                        'is_pure_ontime'  => $isPureOntime,
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
                'total_pure_ontime'  => $pureOntimeCount,
                'total_kehadiran'    => $totalKehadiran,
                'total_hari_kerja'   => $effectiveWorkdays,
                'attendance_rate'    => min(100, $rate),
                'daily_records'      => $dailyRecords,
            ];
        });

        $formattedMonth = $monthCarbon->locale('id')->isoFormat('MMMM Y');
        $fileName = "Rekap_Presensi_Bulanan_" . str_replace('-', '', $currentMonth) . "_" . \Illuminate\Support\Str::uuid() . ".xlsx";
        $filePath = "exports/" . $fileName;

        \Maatwebsite\Excel\Facades\Excel::store(
            new \App\Exports\AttendanceMonthlyExport($monthlyReportData, $formattedMonth, $daysInMonth, $currentMonth),
            $filePath,
            'public'
        );

        $fileUrl = asset("storage/" . $filePath);
        $this->userToNotify->notify(new \App\Notifications\ExportReadyNotification($fileName, $fileUrl, 'Presensi Bulanan'));
    }
}
