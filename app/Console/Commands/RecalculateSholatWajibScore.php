<?php

namespace App\Console\Commands;

use App\Models\HabitLog;
use App\Models\MenstruationLog;
use Illuminate\Console\Command;

class RecalculateSholatWajibScore extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'habit:recalculate-sholat 
                            {--dry-run : Jalankan simulasi tanpa menyimpan perubahan ke database}
                            {--strict : Mode ketat: turunkan skor menjadi 0 jika tidak mencapai target atau saat periode haid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menghitung ulang nilai frekuensi jamaah dan skor untuk seluruh log Sholat Wajib (termasuk JR & JM)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $isStrict = $this->option('strict');

        $this->info($isDryRun 
            ? '🔍 Memulai SIMULASI (Dry Run) kalkulasi ulang Sholat Wajib...' 
            : '⚡ Memulai kalkulasi ulang Sholat Wajib...');

        if ($isStrict) {
            $this->warn('⚠️  Mode STRICT aktif: skor yang tidak memenuhi syarat atau terindikasi haid akan dinolkan.');
        }

        $waktuKeys = ['subuh', 'dhuhur', 'asar', 'maghrib', 'isya'];

        // Ambil semua log sholat wajib
        $logs = HabitLog::with(['habit', 'user'])
            ->whereHas('habit', function ($q) {
                $q->where('template', 'sholat_wajib');
            })
            ->orderBy('tanggal', 'asc')
            ->get();

        $this->line("Ditemukan total {$logs->count()} data log Sholat Wajib.");

        $updatedCount = 0;
        $rows = [];

        foreach ($logs as $log) {
            $details = is_array($log->details) ? $log->details : [];

            // Lewati log dummy/seeder yang tidak memiliki detail waktu sholat
            $hasPrayerDetails = count(array_intersect_key($details, array_flip($waktuKeys))) > 0;
            if (!$hasPrayerDetails) {
                continue;
            }

            // Hitung jumlah sholat jamaah (JM = Jamaah Masjid, JR = Jamaah di Rumah, JD = Backward compatibility)
            $countJamaah = 0;
            foreach ($waktuKeys as $waktu) {
                if (isset($details[$waktu]) && in_array($details[$waktu], ['JM', 'JR', 'JD'])) {
                    $countJamaah++;
                }
            }

            $oldNilai = (int) $log->nilai_input;
            $oldSkor = (int) $log->skor_diperoleh;

            // Cek kondisi haid saat log dibuat
            $isSedangHaid = false;
            if ($log->user && $log->user->gender === 'P') {
                $tanggalStr = is_string($log->tanggal) ? $log->tanggal : $log->tanggal->format('Y-m-d');
                $isSedangHaid = MenstruationLog::where('user_id', $log->user_id)
                    ->where(function ($q) use ($tanggalStr, $log) {
                        $q->whereDate('waktu_mulai', '<', $tanggalStr)
                          ->orWhere(function ($q2) use ($tanggalStr, $log) {
                              $q2->whereDate('waktu_mulai', '=', $tanggalStr)
                                 ->where('waktu_mulai', '<=', $log->created_at);
                          });
                    })
                    ->where(function ($q) use ($tanggalStr) {
                        $q->whereNull('waktu_selesai')
                          ->orWhereDate('waktu_selesai', '>=', $tanggalStr);
                    })->exists();
            }

            // Tentukan skor baru
            $target = $log->habit->target_pencapaian;
            $skorMaks = $log->habit->skor_maksimal;

            if ($countJamaah >= $target && (!$isSedangHaid || !$log->habit->hide_saat_haid)) {
                $newSkor = $skorMaks;
            } elseif ($isStrict) {
                $newSkor = 0;
            } else {
                // Pertahankan skor lama jika tidak dalam mode strict
                $newSkor = $oldSkor;
            }

            if ($oldNilai !== $countJamaah || $oldSkor !== $newSkor) {
                $updatedCount++;
                $rows[] = [
                    $log->id,
                    $log->user ? $log->user->name : "User #{$log->user_id}",
                    is_string($log->tanggal) ? $log->tanggal : $log->tanggal->format('Y-m-d'),
                    "{$oldNilai} -> {$countJamaah}",
                    "{$oldSkor} -> {$newSkor}",
                ];

                if (!$isDryRun) {
                    $log->nilai_input = $countJamaah;
                    $log->skor_diperoleh = $newSkor;
                    $log->save();
                }
            }
        }

        if ($updatedCount > 0) {
            $this->table(
                ['ID Log', 'User', 'Tanggal', 'Nilai / Frekuensi (Lama -> Baru)', 'Skor (Lama -> Baru)'],
                $rows
            );

            if ($isDryRun) {
                $this->warn("⚠️  [Dry Run] Terdapat {$updatedCount} data log yang akan diperbarui. Tidak ada perubahan yang disimpan ke database.");
                $this->comment("Jalankan tanpa opsi --dry-run untuk menerapkan perubahan ke database:");
                $this->line("  php artisan habit:recalculate-sholat");
            } else {
                $this->info("✅ Berhasil memperbarui {$updatedCount} data log Sholat Wajib di database!");
            }
        } else {
            $this->info("✨ Semua data log Sholat Wajib sudah akurat. Tidak ada data yang perlu diperbarui.");
        }

        return Command::SUCCESS;
    }
}
