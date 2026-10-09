<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessLaporanExport implements ShouldQueue
{
    use Queueable;

    public $month;
    public $year;
    public $search;
    public $kategori_skor;
    public $divisi;
    public $type;
    public $userToNotify;

    /**
     * Create a new job instance.
     */
    public function __construct($month, $year, $search, $kategori_skor, $divisi, $type, \App\Models\User $userToNotify)
    {
        $this->month = $month;
        $this->year = $year;
        $this->search = $search;
        $this->kategori_skor = $kategori_skor;
        $this->divisi = $divisi;
        $this->type = $type;
        $this->userToNotify = $userToNotify;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $month = $this->month;
        $year = $this->year;
        $search = $this->search;
        $kategori_skor = $this->kategori_skor;
        $divisi = $this->divisi;
        $type = $this->type;

        $date = \Carbon\Carbon::create($year, $month, 1);
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth   = $date->copy()->endOfMonth();
        $daysInMonth  = $date->daysInMonth;
        
        $dailyMaxScore = \App\Models\Habit::where('status_aktif', true)
            ->where('is_pengganti_haid', false)
            ->sum('skor_maksimal') ?: 100;
        $skorMaksimalSebulan = $dailyMaxScore * $daysInMonth;

        $settingTarget = \App\Models\Setting::where('key', 'monthly_target_score')->first();
        $targetBulanan = $settingTarget ? (float) $settingTarget->value : 80.0;

        $users = \App\Models\User::whereDoesntHave('roles', fn($q) => $q->whereIn('name', ['admin', 'superadmin']))
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($divisi, function ($query, $div) {
                $query->where('divisi', $div);
            })
            ->withSum(
                ['habitLogs' => fn($q) => $q->whereBetween('tanggal', [$startOfMonth, $endOfMonth])],
                'skor_diperoleh'
            )
            ->when($kategori_skor, function ($query, $kategori) use ($skorMaksimalSebulan, $targetBulanan, $startOfMonth, $endOfMonth) {
                // Build CASE statement for division targets
                $divisions = \App\Models\Division::whereNotNull('target_divisi')->get();
                $divisionCases = "";
                foreach($divisions as $div) {
                    $name = addslashes($div->name);
                    $divisionCases .= " WHEN users.divisi = '{$name}' THEN {$div->target_divisi} ";
                }
                $divisionCaseSql = $divisionCases ? "CASE $divisionCases ELSE NULL END" : "NULL";

                // Gunakan target dinamis admin: target khusus inaktif > target divisi > target global
                $userTargetSql = "COALESCE(
                    CASE WHEN status_kehadiran != 'Aktif' THEN target_tidak_aktif ELSE NULL END,
                    $divisionCaseSql,
                    $targetBulanan
                )";

                $scoreSql = "(SELECT COALESCE(SUM(skor_diperoleh), 0) FROM habit_logs WHERE habit_logs.user_id = users.id AND tanggal BETWEEN ? AND ?)";
                
                if ($kategori === '0-30') {
                    $query->whereRaw("$scoreSql <= (0.30 * $skorMaksimalSebulan)", [$startOfMonth, $endOfMonth]);
                } elseif ($kategori === '30-50') {
                    $query->whereRaw("$scoreSql > (0.30 * $skorMaksimalSebulan)", [$startOfMonth, $endOfMonth])
                          ->whereRaw("$scoreSql <= (0.50 * $skorMaksimalSebulan)", [$startOfMonth, $endOfMonth]);
                } elseif ($kategori === '50-target') {
                    $query->whereRaw("$scoreSql > (0.50 * $skorMaksimalSebulan)", [$startOfMonth, $endOfMonth])
                          ->whereRaw("$scoreSql < (($userTargetSql) / 100 * $skorMaksimalSebulan)", [$startOfMonth, $endOfMonth]);
                } elseif ($kategori === 'tercapai') {
                    $query->whereRaw("$scoreSql >= (($userTargetSql) / 100 * $skorMaksimalSebulan)", [$startOfMonth, $endOfMonth]);
                }
            })
            ->get();

        $divisionsLookup = \App\Models\Division::pluck('target_divisi', 'name')->toArray();

        $leaderboard = $users->map(function ($user) use ($skorMaksimalSebulan, $targetBulanan, $divisionsLookup) {
            $skorDiperoleh = (int) $user->habit_logs_sum_skor_diperoleh;
            $persentase = ($skorMaksimalSebulan > 0) ? ($skorDiperoleh / $skorMaksimalSebulan) * 100 : 0;
            
            $userTarget = $targetBulanan;
            
            $divisionTarget = null;
            if (!empty($user->divisi) && isset($divisionsLookup[$user->divisi])) {
                $divisionTarget = $divisionsLookup[$user->divisi];
            }

            if ($user->status_kehadiran !== 'Aktif' && !is_null($user->target_tidak_aktif)) {
                $userTarget = (float) $user->target_tidak_aktif;
            } elseif (!is_null($divisionTarget)) {
                $userTarget = (float) $divisionTarget;
            }

            return [
                'id'         => $user->id,
                'name'       => $user->name,
                'gender'     => $user->gender,
                'skor'       => $skorDiperoleh,
                'persentase' => round($persentase, 1),
                'target'     => $userTarget,
                'status'     => $user->status_kehadiran,
            ];
        })->sortByDesc('persentase')->values();

        $namaBulan = $date->translatedFormat('F Y');
        
        $uuid = \Illuminate\Support\Str::uuid();
        if ($type === 'pdf') {
            $fileName = 'Laporan_Ibadah_Pegawai_' . $date->format('Y_m') . '_' . $uuid . '.pdf';
            $filePath = 'exports/' . $fileName;
            
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.laporan_pdf', [
                'leaderboard' => $leaderboard,
                'skorMaksimal' => $skorMaksimalSebulan,
                'namaBulan' => $namaBulan,
                'targetBulanan' => $targetBulanan
            ]);
            
            \Illuminate\Support\Facades\Storage::disk('public')->put($filePath, $pdf->output());
            $fileUrl = asset("storage/" . $filePath);
            $this->userToNotify->notify(new \App\Notifications\ExportReadyNotification($fileName, $fileUrl, 'Laporan PDF'));
            return;
        }

        $fileName = 'Laporan_Ibadah_Pegawai_' . $date->format('Y_m') . '_' . $uuid . '.xlsx';
        $filePath = 'exports/' . $fileName;

        \Maatwebsite\Excel\Facades\Excel::store(
            new \App\Exports\LaporanExport($leaderboard, $skorMaksimalSebulan, $namaBulan, $targetBulanan),
            $filePath,
            'public'
        );

        $fileUrl = asset("storage/" . $filePath);
        $this->userToNotify->notify(new \App\Notifications\ExportReadyNotification($fileName, $fileUrl, 'Laporan Excel'));
    }
}
