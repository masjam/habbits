<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UserTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['Nama Lengkap', 'Email', 'Password', 'Jenis Kelamin', 'Role'];
    }

    public function array(): array
    {
        return [
            ['Budi Santoso', 'budi@sdam.sch.id', 'rahasia123', 'L', 'Pegawai'],
            ['Siti Aminah', 'siti@sdam.sch.id', 'rahasia123', 'P', 'Pegawai'],
        ];
    }
}
