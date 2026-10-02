<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class UnfilledExport implements FromArray, WithHeadings, WithMapping, WithEvents
{
    protected $unfilledData;
    protected $namaBulan;
    protected $rowNumber = 0;

    public function __construct(array $unfilledData, string $namaBulan)
    {
        $this->unfilledData = $unfilledData;
        $this->namaBulan = $namaBulan;
    }

    public function array(): array
    {
        return $this->unfilledData;
    }

    public function headings(): array
    {
        return [
            ['Laporan Cek Pegawai Belum Isi Habit'],
            ['Periode: ' . $this->namaBulan],
            ['Waktu Download: ' . now()->translatedFormat('d F Y H:i:s')],
            [''],
            [
                'No',
                'Nama Pegawai',
                'Divisi',
                'Total Bolong (Hari)',
                'Tanggal Belum Diisi (Skor = 0)'
            ]
        ];
    }

    public function map($row): array
    {
        $this->rowNumber++;
        $tanggal = is_array($row['unfilled_dates']) ? implode(', ', $row['unfilled_dates']) : '';
        
        return [
            $this->rowNumber,
            $row['name'],
            $row['divisi'] ?? '-',
            $row['total_unfilled'],
            $tanggal
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                // Judul
                $event->sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                
                // Table header styling
                $event->sheet->getStyle('A5:E5')->getFont()->setBold(true);
                $event->sheet->getStyle('A5:E5')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'E2E8F0'] // slate-200
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ]);
                
                // Table auto sizing columns except Dates
                $event->sheet->getColumnDimension('A')->setAutoSize(true);
                $event->sheet->getColumnDimension('B')->setAutoSize(true);
                $event->sheet->getColumnDimension('C')->setAutoSize(true);
                $event->sheet->getColumnDimension('D')->setAutoSize(true);
                $event->sheet->getColumnDimension('E')->setWidth(40);
                
                // Data body styling
                $totalRows = count($this->unfilledData) + 5;
                if ($totalRows > 5) {
                    $event->sheet->getStyle('A6:E' . $totalRows)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                            ],
                        ],
                    ]);
                }
            }
        ];
    }
}
