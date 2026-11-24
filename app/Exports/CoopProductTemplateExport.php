<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class CoopProductTemplateExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function headings(): array
    {
        return [
            'Nama Produk',
            'Barcode SKU',
            'Kategori',
            'Harga Jual',
            'Harga Beli Modal',
            'Stok',
            'Satuan',
        ];
    }

    public function array(): array
    {
        return [
            ['Seragam Kemeja Putih Pendek SDAM', '899123457001', 'Seragam Siswa', 65000, 52000, 50, 'Stel'],
            ['Rinso Molto Deterjen Cair 750ml', '899123458001', 'Sabun & Cuci', 21500, 18000, 40, 'Pouch'],
            ['Buku Tulis Sinar Dunia 38 Lembar', '899123456001', 'ATK & Buku', 4500, 3500, 100, 'Pcs'],
            ['Teh Pucuk Harum 350ml', '899123456011', 'Minuman', 4000, 3000, 72, 'Botol'],
            ['Minyak Goreng SunCo 1L', '899123456030', 'Sembako', 21000, 18500, 35, 'Pouch'],
        ];
    }
}
