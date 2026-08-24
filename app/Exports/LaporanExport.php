<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LaporanExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    protected $leaderboard;
    protected $skorMaksimal;
    protected $namaBulan;
    protected $targetBulanan;
    protected $rank = 0;

    public function __construct(Collection $leaderboard, $skorMaksimal, $namaBulan, $targetBulanan)
    {
        $this->leaderboard = $leaderboard;
        $this->skorMaksimal = $skorMaksimal;
        $this->namaBulan = $namaBulan;
        $this->targetBulanan = $targetBulanan;
    }

    public function collection(): \Illuminate\Support\Enumerable
    {
        return $this->leaderboard;
    }

    public function headings(): array
    {
        return [
            ['Laporan Capaian Ibadah Pegawai'],
            ['Periode: ' . $this->namaBulan],
            [''],
            [
                'Peringkat',
                'Nama Pegawai',
                'Jenis Kelamin',
                'Skor Diperoleh',
                'Skor Maksimal',
                'Target (%)',
                'Persentase (%)'
            ]
        ];
    }

    public function map($row): array
    {
        $this->rank++;
        return [
            $this->rank,
            $row['name'],
            $row['gender'] === 'L' ? 'Laki-laki' : 'Perempuan',
            $row['skor'],
            $this->skorMaksimal,
            ($row['target'] ?? $this->targetBulanan) . '%',
            $row['persentase'] . '%'
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                // Style heading table
                $event->sheet->getStyle('A4:G4')->getFont()->setBold(true);
                $event->sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                
                $rowNum = 5; // Data starts at row 5 (after headings)
                foreach ($this->leaderboard as $row) {
                    $persentase = $row['persentase'];
                    $userTarget = $row['target'] ?? $this->targetBulanan;
                    $color = 'D1FAE5'; // emerald-100 (Tercapai)
                    
                    if ($persentase >= $userTarget) {
                        $color = 'D1FAE5'; // emerald-100
                    } elseif ($persentase > 50) {
                        $color = 'ECFDF5'; // emerald-50
                    } elseif ($persentase > 30) {
                        $color = 'FFFBEB'; // amber-50
                    } else {
                        $color = 'FFF1F2'; // rose-50
                    }
                    
                    $event->sheet->getStyle("A{$rowNum}:G{$rowNum}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => $color]
                        ]
                    ]);
                    
                    $rowNum++;
                }
            }
        ];
    }
}
