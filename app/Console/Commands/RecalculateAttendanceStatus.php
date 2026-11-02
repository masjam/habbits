<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Support\Carbon;

class RecalculateAttendanceStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:recalculate 
                            {--from= : Tanggal mulai (format YYYY-MM-DD)}
                            {--to= : Tanggal akhir (format YYYY-MM-DD)}
                            {--user_id= : ID User tertentu (opsional)}
                            {--dry-run : Uji coba tanpa menyimpan perubahan ke database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hitung ulang status keterlambatan dan kepulangan presensi pegawai berdasarkan jadwal efektif (piket/jadwal khusus/reguler)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $userId = $this->option('user_id');
        $dryRun = $this->option('dry-run');

        $query = Attendance::with('user');

        if ($from) {
            $query->where('date', '>=', $from);
        }
        if ($to) {
            $query->where('date', '<=', $to);
        }
        if ($userId) {
            $query->where('user_id', $userId);
        }

        // Hanya hitung ulang presensi yang masuk (hadir atau terlambat)
        $query->whereIn('status', ['hadir', 'terlambat']);

        $attendances = $query->orderBy('date', 'asc')->get();

        if ($attendances->isEmpty()) {
            $this->info('Tidak ditemukan data presensi yang memenuhi kriteria.');
            return 0;
        }

        $this->info("Menemukan {$attendances->count()} data presensi untuk dianalisis...");
        if ($dryRun) {
            $this->warn('[DRY-RUN MODE] Tidak ada perubahan yang akan disimpan ke database.');
        }

        $updatedCount = 0;

        foreach ($attendances as $att) {
            $user = $att->user;
            if (!$user) {
                continue;
            }

            $dateStr = Carbon::parse($att->date)->toDateString();
            $schedule = $user->getEffectiveWorkSchedule($dateStr);

            $workStart = $schedule['work_start'] ?? '07:00';
            $lateTolerance = (int) ($schedule['late_tolerance'] ?? 0);
            $workEnd = $schedule['work_end'] ?? '15:00';

            $workStartTime = Carbon::parse($dateStr . ' ' . $workStart);
            $toleranceEnd = $workStartTime->copy()->addMinutes($lateTolerance);

            $actualIn = Carbon::parse($dateStr . ' ' . $att->time_in);
            $calculatedStatus = $actualIn->greaterThan($toleranceEnd) ? 'terlambat' : 'hadir';

            $isPulangCepat = $att->is_pulang_cepat;
            if (!empty($att->time_out) && !empty($workEnd)) {
                $actualOut = Carbon::parse($dateStr . ' ' . $att->time_out);
                $workEndTime = Carbon::parse($dateStr . ' ' . $workEnd);
                $isPulangCepat = $actualOut->lessThan($workEndTime);
            }

            $hasChanged = ($att->status !== $calculatedStatus) || ($att->is_pulang_cepat != $isPulangCepat);

            if ($hasChanged) {
                $updatedCount++;
                $scheduleType = !empty($schedule['is_piket']) ? "Piket ({$schedule['piket_name']})" : (!empty($schedule['is_special']) ? "Khusus ({$schedule['special_name']})" : 'Reguler');

                $this->line(sprintf(
                    "[%s] %s | Jam In: %s | Jadwal: %s (Tol: %sm, Batas: %s) | Status: %s -> %s | Tipe: %s",
                    $dateStr,
                    $user->name,
                    substr($att->time_in, 0, 5),
                    $workStart,
                    $lateTolerance,
                    $toleranceEnd->format('H:i'),
                    $att->status,
                    $calculatedStatus,
                    $scheduleType
                ));

                if (!$dryRun) {
                    $att->update([
                        'status' => $calculatedStatus,
                        'is_pulang_cepat' => $isPulangCepat,
                    ]);
                }
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("Simulasi selesai. Sebanyak {$updatedCount} data akan berubah.");
        } else {
            $this->info("Rekalkulasi selesai. Sebanyak {$updatedCount} data presensi telah berhasil diperbarui.");
        }

        return 0;
    }
}

