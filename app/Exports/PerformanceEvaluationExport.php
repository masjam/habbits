<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class PerformanceEvaluationExport extends DefaultValueBinder implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithCustomValueBinder
{
    public function __construct(
        private readonly Collection $rows
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Nama Pegawai',
            'NIP',
            'Divisi',
            'Kategori',
            'Periode',
            'Aspek 1',
            'Aspek 2',
            'Aspek 3',
            'Aspek 4',
            'Nilai Akhir',
            'Predikat',
            'Status',
            'Penilai',
        ];
    }

    public function map($row): array
    {
        return [
            $row['name'],
            $row['nip'] ?: '-',
            $row['divisi'] ?: '-',
            strtoupper($row['category']),
            $row['period_display'],
            $row['aspect_1'],
            $row['aspect_2'],
            $row['aspect_3'],
            $row['aspect_4'],
            $row['final_score'],
            $row['predicate'],
            $row['status_label'],
            $row['evaluator_name'] ?: '-',
        ];
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
            $cell->setValueExplicit("'" . $value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
