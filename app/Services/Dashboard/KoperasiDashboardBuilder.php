<?php

namespace App\Services\Dashboard;

use App\Models\CoopProduct;
use App\Models\CoopTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class KoperasiDashboardBuilder extends AbstractDashboardBuilder
{
    public function roleKey(): string
    {
        return 'koperasi';
    }

    public function build(User $user, DashboardContext $ctx): array
    {
        $todayStats = CoopTransaction::whereDate('created_at', $ctx->todayStr)
            ->selectRaw('
                COUNT(*) as tx_count,
                COALESCE(SUM(total_amount), 0) as total_sales,
                COALESCE(SUM(cash_paid_amount), 0) as cash_total,
                COALESCE(SUM(rfid_paid_amount), 0) as rfid_total,
                COALESCE(SUM(voucher_amount), 0) as voucher_total,
                COALESCE(SUM(bon_amount), 0) as bon_total
            ')
            ->first();

        $yesterdaySales = (float) CoopTransaction::whereDate('created_at', $ctx->yesterdayStr)->sum('total_amount');
        $todaySales = (float) ($todayStats->total_sales ?? 0);
        $deltaPercent = $yesterdaySales > 0
            ? round((($todaySales - $yesterdaySales) / $yesterdaySales) * 100, 1)
            : ($todaySales > 0 ? 100.0 : 0.0);

        $monthlyStats = CoopTransaction::whereBetween(DB::raw('DATE(created_at)'), [$ctx->monthStartStr, $ctx->monthEndStr])
            ->selectRaw('COUNT(*) as tx_count, COALESCE(SUM(total_amount), 0) as total_sales')
            ->first();

        $lowStockCount = (int) CoopProduct::where('is_active', true)->where('stock', '<=', 5)->count();
        $activeProductCount = (int) CoopProduct::where('is_active', true)->count();

        $outstandingBon = (float) CoopTransaction::where('bon_amount', '>', 0)
            ->where(fn ($q) => $q->whereNull('bon_status')->orWhere('bon_status', '!=', 'paid'))
            ->selectRaw('COALESCE(SUM(CASE WHEN bon_amount - COALESCE(bon_paid_amount, 0) > 0 THEN bon_amount - COALESCE(bon_paid_amount, 0) ELSE 0 END), 0) as sisa')
            ->value('sisa');

        return [
            'roleKey' => $this->roleKey(),
            'personal' => $this->personalSummary($user, $ctx),
            'koperasi' => [
                'today_sales' => $todaySales,
                'today_count' => (int) ($todayStats->tx_count ?? 0),
                'sales_delta' => $deltaPercent,
                'monthly_sales' => (float) ($monthlyStats->total_sales ?? 0),
                'monthly_count' => (int) ($monthlyStats->tx_count ?? 0),
                'low_stock_count' => $lowStockCount,
                'active_product_count' => $activeProductCount,
                'outstanding_bon' => $outstandingBon,
                'payment_mix' => [
                    'cash' => (float) ($todayStats->cash_total ?? 0),
                    'rfid' => (float) ($todayStats->rfid_total ?? 0),
                    'voucher' => (float) ($todayStats->voucher_total ?? 0),
                    'bon' => (float) ($todayStats->bon_total ?? 0),
                ],
            ],
        ];
    }

    public function buildDeferred(User $user, DashboardContext $ctx): array
    {
        $lowStockItems = CoopProduct::where('is_active', true)
            ->where('stock', '<=', 5)
            ->orderBy('stock')
            ->limit(5)
            ->get(['id', 'name', 'category', 'stock', 'unit'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category,
                'stock' => (int) $p->stock,
                'unit' => $p->unit ?? 'pcs',
            ])
            ->all();

        $salesRaw = CoopTransaction::whereBetween(DB::raw('DATE(created_at)'), [$ctx->weekStartStr, $ctx->todayStr])
            ->selectRaw('DATE(created_at) as dt, COALESCE(SUM(total_amount), 0) as total')
            ->groupBy('dt')
            ->pluck('total', 'dt');

        $labels = [];
        $values = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $ctx->now->copy()->subDays($i);
            $labels[] = $d->locale('id')->isoFormat('ddd, D');
            $values[] = (float) ($salesRaw[$d->toDateString()] ?? 0);
        }

        return [
            'low_stock_items' => $lowStockItems,
            'sales_trend' => ['labels' => $labels, 'values' => $values],
        ];
    }
}
