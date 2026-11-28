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
        $filters = [
            'month' => $this->month,
            'year' => $this->year,
            'search' => $this->search,
            'kategori_skor' => $this->kategori_skor,
            'divisi' => $this->divisi,
        ];

        $reportQuery = app(\App\Queries\HabitReportQuery::class);
        $ctx = $reportQuery->resolveContext($filters);
        $date = $ctx['date'];
        $skorMaksimalSebulan = $ctx['skorMaksimalSebulan'];
        $targetBulanan = $ctx['targetBulanan'];
        $type = $this->type;

        $users = $reportQuery->baseQuery($filters, $ctx)->get();

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
            
            \Illuminate\Support\Facades\Storage::disk('private')->put($filePath, $pdf->output());
            $fileUrl = asset("storage/" . $filePath);
            $this->userToNotify->notify(new \App\Notifications\ExportReadyNotification($fileName, $fileUrl, 'Laporan PDF'));
            \App\Events\ExportReady::dispatch($this->userToNotify->id, $fileName, $fileUrl, 'Laporan PDF');
            return;
        }

        $fileName = 'Laporan_Ibadah_Pegawai_' . $date->format('Y_m') . '_' . $uuid . '.xlsx';
        $filePath = 'exports/' . $fileName;

        \Maatwebsite\Excel\Facades\Excel::store(
            new \App\Exports\LaporanExport($leaderboard, $skorMaksimalSebulan, $namaBulan, $targetBulanan),
            $filePath,
            'private'
        );

        $fileUrl = asset("storage/" . $filePath);
        $this->userToNotify->notify(new \App\Notifications\ExportReadyNotification($fileName, $fileUrl, 'Laporan Excel'));
        \App\Events\ExportReady::dispatch($this->userToNotify->id, $fileName, $fileUrl, 'Laporan Excel');
    }
}
