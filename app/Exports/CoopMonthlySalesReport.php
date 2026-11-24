<?php

namespace App\Exports;

use App\Models\CoopTransaction;
use Carbon\Carbon;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CoopMonthlySalesReport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    protected string $period;

    public function __construct(?string $period = null)
    {
        $this->period = $period ?: Carbon::today()->format('Y-m');
    }

    public function headings(): array
    {
        return [
            'No',
            'No. Transaksi',
            'Tanggal & Jam',
            'Kasir Bertugas',
            'Nama Pembeli / Guru',
            'NIP',
            'Divisi',
            'Metode Pembayaran',
            'Rincian Barang',
            'Total Belanja (Rp)',
            'Potongan Voucher (Rp)',
            'Bayar Saldo RFID (Rp)',
            'Bayar Tunai (Rp)',
            'Kembalian (Rp)',
            'Catatan',
        ];
    }

    public function collection(): Enumerable
    {
        try {
            $date = Carbon::parse($this->period . '-01');
        } catch (\Exception $e) {
            $date = Carbon::today()->startOfMonth();
        }

        $transactions = CoopTransaction::with(['customer', 'cashier'])
            ->whereBetween('created_at', [
                $date->copy()->startOfMonth(),
                $date->copy()->endOfMonth(),
            ])
            ->orderBy('created_at')
            ->get();

        $rows = collect();
        $no = 1;

        foreach ($transactions as $t) {
            $itemsSummary = '';
            if (is_array($t->items)) {
                $itemsSummary = collect($t->items)->map(fn($it) => ($it['name'] ?? 'Item') . ' (x' . ($it['qty'] ?? 1) . ')')->implode(', ');
            }

            $rows->push([
                'no'               => $no++,
                'code'             => $t->transaction_code,
                'datetime'         => $t->created_at->format('d/m/Y H:i:s'),
                'cashier'          => $t->cashier?->name ?: 'Kasir',
                'customer'         => $t->customer?->name ?: 'Pelanggan Umum',
                'nip'              => $t->customer?->nip ?: '-',
                'divisi'           => $t->customer?->divisi ?: '-',
                'payment'          => strtoupper($t->payment_method),
                'items'            => $itemsSummary,
                'total_gross'      => (float) $t->total_amount,
                'voucher_discount' => (float) $t->voucher_amount,
                'rfid_paid'        => (float) $t->rfid_paid_amount,
                'cash_paid'        => (float) $t->cash_paid_amount,
                'change'           => (float) $t->change_amount,
                'notes'            => $t->notes ?: '-',
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
