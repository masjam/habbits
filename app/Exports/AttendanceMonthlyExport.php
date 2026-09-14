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

class AttendanceMonthlyExport implements FromCollection, WithHeadings, WithMapping, WithEvents, ShouldAutoSize
{
    protected Collection $reportData;
    protected string $monthName;
    protected int $daysInMonth;
    protected int $rowNumber = 0;

    public function __construct(Collection $reportData, string $monthName, int $daysInMonth = 30)
    {
        $this->reportData = $reportData;
        $this->monthName = $monthName;
        $this->daysInMonth = $daysInMonth;
    }

    public function collection(): Collection
    {
        return $this->reportData;
    }

    public function headings(): array
    {
        $headers = [
            'No',
            'Nama Pegawai',
            'NIP',
            'Divisi',
            'Jadwal Kerja',
        ];

        for ($d = 1; $d <= $this->daysInMonth; $d++) {
            $headers[] = 'Tgl ' . $d;
        }

        $headers[] = 'Tepat Waktu';
        $headers[] = 'Terlambat';
        $headers[] = 'Dinas Luar';
        $headers[] = 'Pulang Cepat';
        $headers[] = 'Total Hadir';
        $headers[] = '% Kehadiran';

        return [
            ['REKAP PRESENSI PEGAWAI BULANAN (MATRIKS)'],
            ['Periode: ' . $this->monthName],
            [''],
            $headers
        ];
    }

    public function map($row): array
    {
        $this->rowNumber++;

        $data = [
            $this->rowNumber,
            $row['name'],
            $row['nip'] ?: '-',
            $row['divisi'] ?: '-',
            $row['work_schedule'] ?: 'Default',
        ];

        // Kolom harian tanggal 1..daysInMonth (jam masuk & jam pulang dalam satu cell)
        for ($d = 1; $d <= $this->daysInMonth; $d++) {
            $rec = $row['daily_records'][$d] ?? null;
            if ($rec) {
                $in = $rec['time_in'] ?: '--:--';
                $out = $rec['time_out'] ?: '--:--';
                $data[] = "{$in}\n{$out}";
            } else {
                $data[] = '-';
            }
        }

        // Ringkasan
        $data[] = $row['total_hadir'];
        $data[] = $row['total_terlambat'];
        $data[] = $row['total_dinas_luar'];
        $data[] = $row['total_pulang_cepat'];
        $data[] = $row['total_kehadiran'];
        $data[] = $row['attendance_rate'] . '%';

        return $data;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Style Judul
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('4B5563');

                $lastColumnIndex = 5 + $this->daysInMonth + 6;
                $lastColumnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColumnIndex);

                // Style Header Tabel (Baris 4)
                $headerRange = "A4:{$lastColumnLetter}4";
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF']
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '059669'] // Emerald 600
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

                    $sheet->getStyle("A5:A{$totalRows}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Kolom tanggal (wrap text agar jam masuk & keluar vertikal rapi di dalam 1 sel)
                    $startDateCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(6);
                    $endDateCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $this->daysInMonth);
                    $sheet->getStyle("{$startDateCol}5:{$endDateCol}{$totalRows}")->getAlignment()->setWrapText(true);
                    $sheet->getStyle("{$startDateCol}5:{$endDateCol}{$totalRows}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("{$startDateCol}5:{$endDateCol}{$totalRows}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                    // Pewarnaan sel sesuai status kehadiran per tanggal
                    $rowIndex = 5;
                    foreach ($this->reportData as $row) {
                        for ($d = 1; $d <= $this->daysInMonth; $d++) {
                            $rec = $row['daily_records'][$d] ?? null;
                            if ($rec) {
                                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $d);
                                $color = 'E8F5E9'; // Hadir (Hijau muda)

                                if ($rec['is_pulang_cepat']) {
                                    $color = 'F3E5F5'; // Pulang cepat (Ungu muda)
                                } elseif ($rec['status'] === 'terlambat') {
                                    $color = 'FFF8E1'; // Terlambat (Kuning/Amber)
                                } elseif ($rec['status'] === 'dinas_luar') {
                                    $color = 'E3F2FD'; // Dinas Luar (Biru muda)
                                }

                                $sheet->getStyle("{$colLetter}{$rowIndex}")->applyFromArray([
                                    'fill' => [
                                        'fillType' => Fill::FILL_SOLID,
                                        'startColor' => ['rgb' => $color]
                                    ]
                                ]);
                            }
                        }
                        $rowIndex++;
                    }

                    // Kolom ringkasan sebelah kanan
                    $summaryStartCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(6 + $this->daysInMonth);
                    $sheet->getStyle("{$summaryStartCol}5:{$lastColumnLetter}{$totalRows}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("{$summaryStartCol}5:{$lastColumnLetter}{$totalRows}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                }
            }
        ];
    }
}
