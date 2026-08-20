<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Collection;

class LaporanExport implements FromCollection, WithHeadings, WithMapping
{
    protected $leaderboard;
    protected $skorMaksimal;
    protected $namaBulan;
    protected $rank = 0;

    public function __construct(Collection $leaderboard, $skorMaksimal, $namaBulan)
    {
        $this->leaderboard = $leaderboard;
        $this->skorMaksimal = $skorMaksimal;
        $this->namaBulan = $namaBulan;
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
            $row['persentase'] . '%'
        ];
    }
}
