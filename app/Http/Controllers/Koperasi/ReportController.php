<?php

namespace App\Http\Controllers\Koperasi;

use App\Exports\CoopArrayReportExport;
use App\Http\Controllers\Controller;
use App\Models\CoopMember;
use App\Models\CoopProduct;
use App\Models\CoopStockLog;
use App\Models\CoopTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'profit_loss');
        $year = (int) $request->query('year', Carbon::today()->year);
        $month = $request->query('month', 'all');
        $startDate = $request->query('start_date', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today()->endOfMonth()->format('Y-m-d'));
        $category = $request->query('category', 'all');
        $productId = $request->query('product_id', 'all');
        $logType = $request->query('log_type', 'all');
        $customerKey = $request->query('customer_key', 'all');

        $profitLoss = $this->buildProfitLossData($year, $month);
        $stockHistory = $this->buildStockHistoryData($startDate, $endDate, $category, $productId, $logType, paginate: true);
        $memberReport = $this->buildMemberReportData($startDate, $endDate, $customerKey);

        $categories = CoopProduct::distinct()->pluck('category')->filter()->sort()->values()->all();
        $productsList = CoopProduct::orderBy('name')->get(['id', 'name', 'category']);

        $userOptions = User::orderBy('name')->get(['id', 'name', 'nip'])->map(fn($u) => [
            'key'   => 'user:' . $u->id,
            'label' => $u->name . ($u->nip ? " ({$u->nip})" : ' (Pegawai)'),
        ]);
        $memberOptions = CoopMember::orderBy('name')->get(['id', 'name', 'member_code', 'type'])->map(fn($m) => [
            'key'   => 'member:' . $m->id,
            'label' => "{$m->name} ({$m->member_code} • {$m->type_label})",
        ]);

        return Inertia::render('Koperasi/POS/Reports', [
            'activeTab'       => $tab,
            'profitLoss'      => $profitLoss,
            'stockHistory'    => $stockHistory,
            'memberReport'    => $memberReport,
            'categories'      => $categories,
            'productsList'    => $productsList,
            'customerOptions' => $userOptions->concat($memberOptions)->values(),
            'filters'         => [
                'tab'          => $tab,
                'year'         => $year,
                'month'        => $month,
                'start_date'   => $startDate,
                'end_date'     => $endDate,
                'category'     => $category,
                'product_id'   => $productId,
                'log_type'     => $logType,
                'customer_key' => $customerKey,
            ],
        ]);
    }

    public function exportProfitLoss(Request $request)
    {
        $year = (int) $request->query('year', Carbon::today()->year);
        $month = $request->query('month', 'all');
        $data = $this->buildProfitLossData($year, $month);

        $headings = ['Bulan', 'Jumlah Transaksi', 'Omzet Kotor (Rp)', 'Potongan Voucher (Rp)', 'Omzet Bersih (Rp)', 'HPP / Modal (Rp)', 'Laba Kotor (Rp)', 'Selisih Opname (Rp)', 'Laba Bersih (Rp)'];
        $rows = collect($data['rows'])->map(fn($r) => [
            $r['label'],
            $r['trx_count'],
            $r['gross_sales'],
            $r['voucher_discount'],
            $r['net_sales'],
            $r['cogs'],
            $r['gross_profit'],
            $r['opname_impact'],
            $r['net_profit'],
        ]);

        return Excel::download(new CoopArrayReportExport($headings, $rows), "Laporan-Laba-Rugi-Koperasi-Rabbani-{$year}.xlsx");
    }

    public function exportStockHistory(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today()->endOfMonth()->format('Y-m-d'));
        $category = $request->query('category', 'all');
        $productId = $request->query('product_id', 'all');
        $logType = $request->query('log_type', 'all');

        $data = $this->buildStockHistoryData($startDate, $endDate, $category, $productId, $logType, paginate: false);

        $headings = ['Tanggal & Jam', 'Barang', 'Kategori', 'Jenis Mutasi', 'Stok Awal', 'Selisih', 'Stok Akhir', 'Nilai Selisih (Rp)', 'Petugas', 'Catatan'];
        $rows = collect($data['items'])->map(fn($l) => [
            $l['created_at'],
            $l['product_name'],
            $l['category'],
            strtoupper($l['type']),
            $l['stock_before'],
            $l['difference'],
            $l['stock_after'],
            $l['value_impact'],
            $l['staff_name'],
            $l['notes'] ?: '-',
        ]);

        return Excel::download(new CoopArrayReportExport($headings, $rows), "Laporan-Perubahan-Stok-{$startDate}-sd-{$endDate}.xlsx");
    }

    public function exportMemberTransactions(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today()->endOfMonth()->format('Y-m-d'));
        $customerKey = $request->query('customer_key', 'all');

        $data = $this->buildMemberReportData($startDate, $endDate, $customerKey);

        $headings = ['Pelanggan', 'Identitas / Kode', 'Kategori', 'Jumlah Transaksi', 'Total Belanja (Rp)', 'Voucher (Rp)', 'RFID (Rp)', 'Tunai (Rp)', 'BON (Rp)', 'Sisa BON (Rp)'];
        $rows = collect($data['summary_rows'])->map(fn($r) => [
            $r['customer_name'],
            $r['customer_code'],
            $r['customer_group'],
            $r['trx_count'],
            $r['total_spent'],
            $r['total_voucher'],
            $r['total_rfid'],
            $r['total_cash'],
            $r['total_bon'],
            $r['unpaid_bon'],
        ]);

        return Excel::download(new CoopArrayReportExport($headings, $rows), "Laporan-Transaksi-Member-{$startDate}-sd-{$endDate}.xlsx");
    }

    private function buildProfitLossData(int $year, string $month): array
    {
        $costMap = CoopProduct::pluck('cost_price', 'id');

        $start = $month !== 'all'
            ? Carbon::createFromDate($year, (int) $month, 1)->startOfMonth()
            : Carbon::createFromDate($year, 1, 1)->startOfYear();
        $end = $month !== 'all'
            ? $start->copy()->endOfMonth()
            : Carbon::createFromDate($year, 12, 31)->endOfYear();

        $transactions = CoopTransaction::whereBetween('created_at', [$start, $end])->get();
        $stockLogs = CoopStockLog::with('product:id,cost_price')
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('type', ['opname', 'damage', 'expired', 'correction'])
            ->get();

        $monthsToLoop = $month !== 'all' ? [(int) $month] : range(1, 12);
        $rows = [];

        foreach ($monthsToLoop as $m) {
            $mTrx = $transactions->filter(fn($t) => (int) $t->created_at->month === $m);
            $mLogs = $stockLogs->filter(fn($l) => (int) $l->created_at->month === $m);

            $grossSales = (float) $mTrx->sum('total_amount');
            $voucherDiscount = (float) $mTrx->sum(fn($t) => $t->voucher_amount + $t->discount_amount);
            $netSales = max(0, $grossSales - $voucherDiscount);

            $cogs = (float) $mTrx->sum(function ($t) use ($costMap) {
                if (!is_array($t->items)) {
                    return 0;
                }
                return collect($t->items)->sum(function ($it) use ($costMap) {
                    $qty = (int) ($it['qty'] ?? 1);
                    $unitCost = isset($it['cost_price']) && $it['cost_price'] > 0
                        ? (float) $it['cost_price']
                        : (float) ($costMap[$it['id'] ?? 0] ?? 0);
                    return $qty * $unitCost;
                });
            });

            $grossProfit = $netSales - $cogs;
            $opnameImpact = (float) $mLogs->sum(fn($l) => $l->difference * (float) ($l->product?->cost_price ?? 0));
            $netProfit = $grossProfit + $opnameImpact;

            $rows[] = [
                'month'            => $m,
                'label'            => Carbon::createFromDate($year, $m, 1)->locale('id')->isoFormat('MMMM Y'),
                'trx_count'        => $mTrx->count(),
                'gross_sales'      => $grossSales,
                'voucher_discount' => $voucherDiscount,
                'net_sales'        => $netSales,
                'cogs'             => $cogs,
                'gross_profit'     => $grossProfit,
                'opname_impact'    => $opnameImpact,
                'net_profit'       => $netProfit,
            ];
        }

        $rowsColl = collect($rows);

        return [
            'rows'    => $rows,
            'totals'  => [
                'trx_count'        => $rowsColl->sum('trx_count'),
                'gross_sales'      => $rowsColl->sum('gross_sales'),
                'voucher_discount' => $rowsColl->sum('voucher_discount'),
                'net_sales'        => $rowsColl->sum('net_sales'),
                'cogs'             => $rowsColl->sum('cogs'),
                'gross_profit'     => $rowsColl->sum('gross_profit'),
                'opname_impact'    => $rowsColl->sum('opname_impact'),
                'net_profit'       => $rowsColl->sum('net_profit'),
            ],
        ];
    }

    private function buildStockHistoryData(string $startDate, string $endDate, string $category, string $productId, string $logType, bool $paginate): array
    {
        $query = CoopStockLog::with(['product', 'staff'])
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->orderByDesc('created_at');

        if ($category !== 'all') {
            $query->whereHas('product', fn($q) => $q->where('category', $category));
        }
        if ($productId !== 'all') {
            $query->where('coop_product_id', (int) $productId);
        }
        if ($logType !== 'all') {
            $query->where('type', $logType);
        }

        $allLogs = (clone $query)->get();
        $totalPlus = (int) $allLogs->where('difference', '>', 0)->sum('difference');
        $totalMinus = (int) $allLogs->where('difference', '<', 0)->sum('difference');
        $netValueImpact = (float) $allLogs->sum(fn($l) => $l->difference * (float) ($l->product?->cost_price ?? 0));

        $transform = fn($l) => [
            'id'           => $l->id,
            'created_at'   => $l->created_at->format('d M Y, H:i'),
            'product_name' => $l->product?->name ?: 'Produk Dihapus',
            'category'     => $l->product?->category ?: '-',
            'type'         => $l->type,
            'stock_before' => $l->stock_before,
            'difference'   => $l->difference,
            'stock_after'  => $l->stock_after,
            'value_impact' => (float) ($l->difference * (float) ($l->product?->cost_price ?? 0)),
            'staff_name'   => $l->staff?->name ?: 'Sistem',
            'notes'        => $l->notes,
        ];

        $result = [
            'summary' => [
                'total_logs'       => $allLogs->count(),
                'total_plus'       => $totalPlus,
                'total_minus'      => $totalMinus,
                'net_value_impact' => $netValueImpact,
            ],
        ];

        if ($paginate) {
            $logs = $query->paginate(25, ['*'], 'stock_page')->withQueryString();
            $logs->getCollection()->transform($transform);
            $result['logs'] = $logs;
        } else {
            $result['items'] = $allLogs->map($transform)->values();
        }

        return $result;
    }

    private function buildMemberReportData(string $startDate, string $endDate, string $customerKey): array
    {
        $query = CoopTransaction::with(['customer', 'member'])
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->orderByDesc('created_at');

        if ($customerKey !== 'all' && str_contains($customerKey, ':')) {
            [$type, $id] = explode(':', $customerKey, 2);
            if ($type === 'user') {
                $query->where('user_id', (int) $id);
            } elseif ($type === 'member') {
                $query->where('member_id', (int) $id);
            }
        }

        $transactions = $query->get();

        $grouped = $transactions->groupBy(function ($t) {
            if ($t->member_id) {
                return 'member:' . $t->member_id;
            }
            if ($t->user_id) {
                return 'user:' . $t->user_id;
            }
            return 'guest';
        });

        $summaryRows = $grouped->map(function ($group, $key) {
            $first = $group->first();
            return [
                'key'            => $key,
                'customer_name'  => $first->customer?->name ?? $first->member?->name ?? 'Pelanggan Umum',
                'customer_code'  => $first->customer?->nip ?? $first->member?->member_code ?? '-',
                'customer_group' => $first->customer ? ($first->customer->divisi ?: 'Pegawai') : ($first->member?->type_label ?? 'Umum'),
                'trx_count'      => $group->count(),
                'total_spent'    => (float) $group->sum('total_amount'),
                'total_voucher'  => (float) $group->sum('voucher_amount'),
                'total_rfid'     => (float) $group->sum('rfid_paid_amount'),
                'total_cash'     => (float) $group->sum(fn($t) => max(0, $t->cash_paid_amount - $t->change_amount)),
                'total_bon'      => (float) $group->sum('bon_amount'),
                'unpaid_bon'     => (float) $group->sum(fn($t) => max(0, $t->bon_amount - $t->bon_paid_amount)),
            ];
        })->sortByDesc('total_spent')->values()->all();

        $detailRows = $customerKey !== 'all'
            ? $transactions->map(fn($t) => [
                'id'               => $t->id,
                'transaction_code' => $t->transaction_code,
                'created_at'       => $t->created_at->format('d M Y, H:i'),
                'payment_method'   => $t->payment_method,
                'total_amount'     => (float) $t->total_amount,
                'voucher_amount'   => (float) $t->voucher_amount,
                'bon_amount'       => (float) $t->bon_amount,
                'bon_status'       => $t->bon_status,
                'items'            => $t->items ?: [],
            ])->values()->all()
            : [];

        return [
            'summary_rows' => $summaryRows,
            'detail_rows'  => $detailRows,
        ];
    }
}
