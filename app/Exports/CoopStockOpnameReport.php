<?php

namespace App\Exports;

use App\Models\CoopProduct;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CoopStockOpnameReport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    public function headings(): array
    {
        return [
            'No',
            'Barcode SKU',
            'Nama Produk',
            'Kategori',
            'Satuan',
            'Stok Sistem',
            'Cek Fisik Nyata',
            'Selisih Opname',
            'Harga Beli Modal (Rp)',
            'Harga Jual (Rp)',
            'Nilai Aset Modal (Rp)',
            'Potensi Omset (Rp)',
            'Keterangan / Kondisi Barang',
        ];
    }

    public function collection(): Enumerable
    {
        $products = CoopProduct::orderBy('category')->orderBy('name')->get();

        $rows = collect();
        $no = 1;

        foreach ($products as $p) {
            $costVal = (float) $p->cost_price * $p->stock;
            $sellVal = (float) $p->price * $p->stock;

            $rows->push([
                'no'         => $no++,
                'barcode'    => $p->barcode ?: '-',
                'name'       => $p->name,
                'category'   => $p->category,
                'unit'       => $p->unit,
                'stock'      => (int) $p->stock,
                'physical'   => '', // Kolom kosong untuk diisi petugas saat stock opname fisik
                'difference' => '', // Kolom selisih opname
                'cost_price' => (float) $p->cost_price,
                'price'      => (float) $p->price,
                'cost_val'   => $costVal,
                'sell_val'   => $sellVal,
                'notes'      => $p->is_active ? 'Aktif' : 'Non-Aktif',
            ]);
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): ?array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
