<?php

namespace App\Imports;

use App\Models\CoopProduct;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CoopProductImport implements ToCollection, WithHeadingRow
{
    public int $importedCount = 0;
    public int $updatedCount = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            // Support various heading naming styles
            $name = trim((string) ($row['nama_produk'] ?? $row['nama_barang'] ?? $row['nama'] ?? ''));
            if (!$name) {
                continue;
            }

            $barcode = trim((string) ($row['barcode_sku'] ?? $row['barcode'] ?? $row['sku'] ?? ''));
            $category = trim((string) ($row['kategori'] ?? 'Umum')) ?: 'Umum';
            $price = (float) ($row['harga_jual'] ?? $row['harga'] ?? 0);
            $costPrice = (float) ($row['harga_beli_modal'] ?? $row['harga_modal'] ?? $row['harga_beli'] ?? 0);
            $stock = (int) ($row['stok'] ?? $row['stock'] ?? 0);
            $unit = trim((string) ($row['satuan'] ?? $row['unit'] ?? 'Pcs')) ?: 'Pcs';

            // Find existing by barcode or name
            $product = null;
            if ($barcode) {
                $product = CoopProduct::where('barcode', $barcode)->first();
            }
            if (!$product) {
                $product = CoopProduct::where('name', $name)->first();
            }

            if ($product) {
                $product->update([
                    'name'       => $name,
                    'barcode'    => $barcode ?: $product->barcode,
                    'category'   => $category,
                    'price'      => $price > 0 ? $price : $product->price,
                    'cost_price' => $costPrice > 0 ? $costPrice : $product->cost_price,
                    'stock'      => $stock,
                    'unit'       => $unit,
                    'is_active'  => true,
                ]);
                $this->updatedCount++;
            } else {
                CoopProduct::create([
                    'name'       => $name,
                    'barcode'    => $barcode ?: null,
                    'category'   => $category,
                    'price'      => $price,
                    'cost_price' => $costPrice,
                    'stock'      => $stock,
                    'unit'       => $unit,
                    'is_active'  => true,
                ]);
                $this->importedCount++;
            }
        }
    }
}
