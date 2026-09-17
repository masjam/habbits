<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class AttendanceTugasLuarExport implements FromCollection, WithHeadings, WithMapping, WithEvents, ShouldAutoSize
{
    protected Collection $reportData;
    protected string $monthName;
    protected int $rowNumber = 0;

    public function __construct(Collection $reportData, string $monthName)
    {
        $this->reportData = $reportData;
        $this->monthName = $monthName;
    }

    public function collection(): Collection
    {
        return $this->reportData;
    }

    public function headings(): array
    {
        return [
            ['REKAP PRESENSI TUGAS LUAR / DINAS LUAR'],
            ['Periode: ' . $this->monthName],
            [''],
            [
                'No',
                'Nama Pegawai',
                'Divisi',
                'Tanggal Tugas Luar',
                'Jam Masuk',
                'Koordinat Masuk (Lat, Lng)',
                'Jam Keluar',
                'Koordinat Keluar (Lat, Lng)',
                'Keterangan',
            ]
        ];
    }

    public function map($row): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            $row['name'],
            $row['divisi'] ?: '-',
            $row['date_formatted'],
            $row['time_in'] ?: '--:--',
            $row['koordinat_in'],
            $row['time_out'] ?: '--:--',
            $row['koordinat_out'],
            $row['notes'] ?: '-',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Style Judul
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('4B5563');

                $lastColumnLetter = 'I'; // Kolom 9

                // Style Header Tabel (Baris 4)
                $headerRange = "A4:{$lastColumnLetter}4";
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF']
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '0284c7'] // Sky 600
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ]
                ]);

                // Border dan alignment data
                $totalRows = 4 + count($this->reportData);
                if ($totalRows >= 5) {
                    $sheet->getStyle("A4:{$lastColumnLetter}{$totalRows}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'D1D5DB']
                            ]
                        ]
                    ]);

                    // Alignment center untuk kolom yang diperlukan
                    $sheet->getStyle("A5:A{$totalRows}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // No
                    $sheet->getStyle("D5:D{$totalRows}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Tanggal
                    $sheet->getStyle("E5:E{$totalRows}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Jam Masuk
                    $sheet->getStyle("G5:G{$totalRows}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Jam Keluar
                    
                    // Wrap text untuk keterangan
                    $sheet->getStyle("I5:I{$totalRows}")->getAlignment()->setWrapText(true);
                    
                    // Vertikal center semua sel
                    $sheet->getStyle("A5:{$lastColumnLetter}{$totalRows}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                }
            }
        ];
    }
}
