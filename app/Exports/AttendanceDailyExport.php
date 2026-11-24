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

class AttendanceDailyExport implements FromCollection, WithHeadings, WithMapping, WithEvents, ShouldAutoSize
{
    protected Collection $reportData;
    protected string $formattedDate;
    protected ?string $specialScheduleInfo;
    protected int $rowNumber = 0;

    public function __construct(Collection $reportData, string $formattedDate, ?string $specialScheduleInfo = null)
    {
        $this->reportData = $reportData;
        $this->formattedDate = $formattedDate;
        $this->specialScheduleInfo = $specialScheduleInfo;
    }

    public function collection(): Collection
    {
        return $this->reportData;
    }

    public function headings(): array
    {
        $dateText = 'Tanggal: ' . $this->formattedDate;
        if ($this->specialScheduleInfo) {
            $dateText .= ' (⭐ JADWAL KHUSUS: ' . $this->specialScheduleInfo . ')';
        }

        return [
            ['REKAP PRESENSI HARIAN PEGAWAI'],
            [$dateText],
            [''],
            [
                'No',
                'Nama Pegawai',
                'NIP',
                'Divisi',
                'Jadwal Kerja',
                'Jam Masuk',
                'Jarak Masuk (m)',
                'Jam Pulang',
                'Jarak Pulang (m)',
                'Status',
                'Keterangan'
            ]
        ];
    }

    public function map($row): array
    {
        $this->rowNumber++;
        return [
            $this->rowNumber,
            $row['name'],
            $row['nip'] ?: '-',
            $row['divisi'] ?: '-',
            $row['work_schedule'] ?: 'Default',
            $row['time_in'] ?: '-',
            $row['distance_in'] !== null ? $row['distance_in'] . ' m' : '-',
            $row['time_out'] ?: '-',
            $row['distance_out'] !== null ? $row['distance_out'] . ' m' : '-',
            strtoupper(str_replace('_', ' ', $row['status'])),
            $row['notes'] ?: '-'
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Style Judul
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('4B5563');

                // Style Header Tabel (Baris 4)
                $headerRange = 'A4:K4';
                $headerColor = $this->specialScheduleInfo ? '7C3AED' : '059669'; // Violet 600 jika Jadwal Khusus, Emerald 600 default
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF']
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $headerColor]
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ]
                ]);

                if ($this->specialScheduleInfo) {
                    $sheet->getStyle('A2')->getFont()->setBold(true)->getColor()->setRGB('7C3AED');
                }

                // Border dan alignment baris data
                $totalRows = 4 + count($this->reportData);
                if ($totalRows >= 5) {
                    $sheet->getStyle("A4:K{$totalRows}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'D1D5DB']
                            ]
                        ]
                    ]);

                    $sheet->getStyle("A5:A{$totalRows}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E5:J{$totalRows}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            }
        ];
    }
}
