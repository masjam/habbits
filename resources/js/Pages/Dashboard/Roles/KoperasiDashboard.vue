<script setup>
import { computed } from 'vue'
import { Link, Deferred, usePage } from '@inertiajs/vue3'
import DashboardShell from '@/Components/Dashboard/DashboardShell.vue'
import StatCard from '@/Components/Dashboard/StatCard.vue'
import ActionList from '@/Components/Dashboard/ActionList.vue'
import MiniTrendChart from '@/Components/Dashboard/MiniTrendChart.vue'
import PersonalMiniCard from '@/Pages/Dashboard/Partials/PersonalMiniCard.vue'
import { useIslamicData } from '@/Composables/useIslamicData'
import { formatNumber, formatRupiah } from '@/utils/formatters'

const props = defineProps({
    dashboard: { type: Object, required: true },
    deferred: { type: Object, default: null },
})

const page = usePage()
const { hijriDate } = useIslamicData()
const user = computed(() => page.props.auth?.user || {})
const k = computed(() => props.dashboard.koperasi || {})

const todayLabel = computed(() => {
    const m = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' })
    return hijriDate.value ? `${m} · ${hijriDate.value}` : m
})

const paymentRows = computed(() => {
    const mix = k.value.payment_mix || {}
    const total = (mix.cash || 0) + (mix.rfid || 0) + (mix.voucher || 0) + (mix.bon || 0)
    const pct = (val) => (total > 0 ? Math.round(((val || 0) / total) * 100) : 0)
    return [
        { label: 'Tunai', amount: mix.cash || 0, pct: pct(mix.cash), bar: 'bg-emerald-500' },
        { label: 'Kartu RFID', amount: mix.rfid || 0, pct: pct(mix.rfid), bar: 'bg-primary-500' },
        { label: 'Voucher Habit', amount: mix.voucher || 0, pct: pct(mix.voucher), bar: 'bg-sky-500' },
        { label: 'Piutang BON', amount: mix.bon || 0, pct: pct(mix.bon), bar: 'bg-amber-500' },
    ]
})

const lowStockItems = computed(() =>
    (props.deferred?.low_stock_items || []).map((p) => ({
        id: p.id,
        title: p.name,
        subtitle: p.category || 'Produk',
        badge: `${p.stock} ${p.unit}`,
        tone: p.stock <= 0 ? 'bad' : 'warn',
        href: route('koperasi.stock-opname'),
    }))
)
</script>

<template>
    <DashboardShell
        :greeting="`Salam, ${user.name || 'Petugas Koperasi'}`"
        :dateLabel="todayLabel"
        contextLine="Ringkasan transaksi, stok & piutang koperasi"
    >
        <template #actions>
            <Link
                :href="route('koperasi.pos')"
                class="inline-flex items-center px-3.5 py-2 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold transition-colors min-h-[44px] sm:min-h-0"
            >
                Buka Kasir POS →
            </Link>
            <Link
                :href="route('koperasi.stock-opname')"
                class="inline-flex items-center px-3.5 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold transition-colors min-h-[44px] sm:min-h-0"
            >
                Stock Opname
            </Link>
        </template>

        <template #kpis>
            <StatCard
                label="Penjualan hari ini"
                :value="formatRupiah(k.today_sales)"
                :delta="k.sales_delta"
                deltaLabel="vs kemarin"
                :subtext="`${formatNumber(k.today_count)} transaksi hari ini`"
                tone="good"
                :href="route('koperasi.history')"
            />
            <StatCard
                label="Transaksi bulan ini"
                :value="formatRupiah(k.monthly_sales)"
                :subtext="`${formatNumber(k.monthly_count)} transaksi bulan berjalan`"
                tone="primary"
                :href="route('koperasi.reports')"
            />
            <StatCard
                label="Stok menipis (≤ 5)"
                :value="formatNumber(k.low_stock_count)"
                :subtext="`Dari ${formatNumber(k.active_product_count)} produk aktif`"
                :tone="k.low_stock_count > 0 ? 'warn' : 'good'"
                :href="route('koperasi.stock-opname')"
            />
            <StatCard
                label="Piutang BON berjalan"
                :value="formatRupiah(k.outstanding_bon)"
                subtext="Belum lunas"
                :tone="k.outstanding_bon > 0 ? 'warn' : 'good'"
                :href="route('koperasi.bon')"
            />
        </template>

        <template #main>
            <div class="md:col-span-7 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-4 flex flex-col min-h-[200px]">
                <div class="flex items-center justify-between gap-2 mb-2 shrink-0">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Tren penjualan 7 hari</h2>
                    <Link :href="route('koperasi.reports')" class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline">
                        Laporan →
                    </Link>
                </div>
                <div class="flex-1 min-h-0">
                    <Deferred data="deferred">
                        <template #fallback>
                            <div class="h-full min-h-[140px] rounded-xl bg-slate-100 dark:bg-slate-800 animate-pulse" />
                        </template>
                        <MiniTrendChart
                            v-if="deferred?.sales_trend?.labels?.length"
                            type="bar"
                            :labels="deferred.sales_trend.labels"
                            :values="deferred.sales_trend.values"
                            datasetLabel="Penjualan (Rp)"
                            ariaLabel="Grafik penjualan koperasi 7 hari"
                        />
                        <div v-else class="h-full flex items-center justify-center text-xs text-slate-500 dark:text-slate-400">
                            Belum ada data penjualan.
                        </div>
                    </Deferred>
                </div>
            </div>

            <div class="md:col-span-5 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-4 flex flex-col justify-between min-h-0">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Metode bayar hari ini</h2>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Komposisi</span>
                </div>
                <div class="space-y-2.5 flex-1 flex flex-col justify-center">
                    <div v-for="row in paymentRows" :key="row.label" class="space-y-1">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-600 dark:text-slate-300 font-medium">{{ row.label }}</span>
                            <span class="font-bold text-slate-900 dark:text-slate-100 tabular-nums">{{ formatRupiah(row.amount) }}</span>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                            <div class="h-full rounded-full" :class="row.bar" :style="{ width: `${row.pct}%` }" />
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template #sidebar>
            <ActionList
                title="Stok menipis (Top 5)"
                :items="lowStockItems"
                emptyText="Semua stok produk aman."
                :viewAllHref="route('koperasi.stock-opname')"
                viewAllLabel="Opname"
                class="flex-1 min-h-[160px]"
            />
            <PersonalMiniCard :personal="dashboard.personal" />
        </template>
    </DashboardShell>
</template>
