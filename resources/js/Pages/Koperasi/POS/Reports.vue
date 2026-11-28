<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

const props = defineProps({
    activeTab: { type: String, default: 'profit_loss' },
    profitLoss: { type: Object, default: () => ({ rows: [], totals: {} }) },
    stockHistory: { type: Object, default: () => ({ summary: {}, logs: { data: [], links: [] } }) },
    memberReport: { type: Object, default: () => ({ summary_rows: [], detail_rows: [] }) },
    categories: { type: Array, default: () => [] },
    productsList: { type: Array, default: () => [] },
    customerOptions: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const tab = ref(props.activeTab || 'profit_loss')
const form = ref({
    year: props.filters.year || new Date().getFullYear(),
    month: props.filters.month || 'all',
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
    category: props.filters.category || 'all',
    product_id: props.filters.product_id || 'all',
    log_type: props.filters.log_type || 'all',
    customer_key: props.filters.customer_key || 'all',
})

const applyFilters = (nextTab = tab.value) => {
    tab.value = nextTab
    router.get(route('koperasi.reports'), {
        tab: nextTab,
        ...form.value,
    }, { preserveState: true, preserveScroll: true })
}

const formatRupiah = (val) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
}).format(Number(val) || 0)

const exportUrl = () => {
    if (tab.value === 'profit_loss') {
        return route('koperasi.reports.export.profit-loss', { year: form.value.year, month: form.value.month })
    }
    if (tab.value === 'stock_history') {
        return route('koperasi.reports.export.stock-history', {
            start_date: form.value.start_date,
            end_date: form.value.end_date,
            category: form.value.category,
            product_id: form.value.product_id,
            log_type: form.value.log_type,
        })
    }
    return route('koperasi.reports.export.member-transactions', {
        start_date: form.value.start_date,
        end_date: form.value.end_date,
        customer_key: form.value.customer_key,
    })
}

const months = [
    { val: 'all', label: 'Semua Bulan (Tahunan)' },
    { val: '01', label: 'Januari' }, { val: '02', label: 'Februari' }, { val: '03', label: 'Maret' },
    { val: '04', label: 'April' }, { val: '05', label: 'Mei' }, { val: '06', label: 'Juni' },
    { val: '07', label: 'Juli' }, { val: '08', label: 'Agustus' }, { val: '09', label: 'September' },
    { val: '10', label: 'Oktober' }, { val: '11', label: 'November' }, { val: '12', label: 'Desember' },
]
</script>

<template>
    <Head title="Laporan Koperasi — Koperasi Rabbani" />

    <PosLayout>
        <div class="flex-1 w-full overflow-y-auto bg-slate-50">
            <div class="w-full py-5 px-4 sm:px-6 space-y-5">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900">Pusat Laporan Koperasi Rabbani</h1>
                    <p class="text-xs text-slate-500">Laporan Laba/Rugi, Riwayat Perubahan Stok & Opname, serta Rekap Transaksi Member</p>
                </div>
                <a
                    :href="exportUrl()"
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md shadow-emerald-600/20"
                >
                    <span>Export Excel (.xlsx)</span>
                </a>
            </div>

            <!-- Tabs -->
            <div class="flex flex-wrap gap-2 bg-white p-1.5 rounded-2xl border border-slate-200 shadow-xs">
                <button
                    type="button"
                    @click="applyFilters('profit_loss')"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all"
                    :class="tab === 'profit_loss' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
                >
                    Laba / Rugi Bulanan & Tahunan
                </button>
                <button
                    type="button"
                    @click="applyFilters('stock_history')"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all"
                    :class="tab === 'stock_history' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
                >
                    Perubahan Stok & Opname
                </button>
                <button
                    type="button"
                    @click="applyFilters('member_transactions')"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all"
                    :class="tab === 'member_transactions' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
                >
                    Transaksi per Member / Pegawai
                </button>
            </div>

            <!-- ══ TAB 1: LABA / RUGI ══ -->
            <template v-if="tab === 'profit_loss'">
                <form @submit.prevent="applyFilters('profit_loss')" class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs flex flex-wrap gap-3 items-end text-xs">
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">Tahun</label>
                        <input v-model.number="form.year" type="number" min="2024" max="2035" class="w-28 px-3 py-2 rounded-xl border border-slate-200 font-bold" />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">Periode Bulan</label>
                        <select v-model="form.month" class="px-3 py-2 rounded-xl border border-slate-200 font-semibold">
                            <option v-for="m in months" :key="m.val" :value="m.val">{{ m.label }}</option>
                        </select>
                    </div>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold">Terapkan</button>
                </form>

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                        <p class="text-xs font-semibold text-slate-500">Omzet Bersih</p>
                        <p class="text-xl font-black text-slate-900 mt-1">{{ formatRupiah(profitLoss.totals.net_sales) }}</p>
                    </div>
                    <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                        <p class="text-xs font-semibold text-slate-500">HPP / Modal Barang</p>
                        <p class="text-xl font-black text-slate-700 mt-1">{{ formatRupiah(profitLoss.totals.cogs) }}</p>
                    </div>
                    <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                        <p class="text-xs font-semibold text-slate-500">Laba Kotor</p>
                        <p class="text-xl font-black text-emerald-600 mt-1">{{ formatRupiah(profitLoss.totals.gross_profit) }}</p>
                    </div>
                    <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                        <p class="text-xs font-semibold text-slate-500">Laba Bersih Operasional</p>
                        <p class="text-xl font-black mt-1" :class="profitLoss.totals.net_profit >= 0 ? 'text-teal-600' : 'text-rose-600'">
                            {{ formatRupiah(profitLoss.totals.net_profit) }}
                        </p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-600">
                            <thead class="bg-slate-50 text-slate-500 uppercase font-bold text-[10px] border-b border-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Bulan</th>
                                    <th class="px-4 py-3 text-right">TRX</th>
                                    <th class="px-4 py-3 text-right">Omzet Kotor</th>
                                    <th class="px-4 py-3 text-right">Potongan</th>
                                    <th class="px-4 py-3 text-right">HPP / Modal</th>
                                    <th class="px-4 py-3 text-right">Laba Kotor</th>
                                    <th class="px-4 py-3 text-right">Selisih Opname</th>
                                    <th class="px-4 py-3 text-right">Laba Bersih</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="r in profitLoss.rows" :key="r.month" class="hover:bg-slate-50/70">
                                    <td class="px-4 py-3 font-bold text-slate-900">{{ r.label }}</td>
                                    <td class="px-4 py-3 text-right font-mono">{{ r.trx_count }}</td>
                                    <td class="px-4 py-3 text-right">{{ formatRupiah(r.gross_sales) }}</td>
                                    <td class="px-4 py-3 text-right text-amber-600">{{ formatRupiah(r.voucher_discount) }}</td>
                                    <td class="px-4 py-3 text-right">{{ formatRupiah(r.cogs) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-emerald-600">{{ formatRupiah(r.gross_profit) }}</td>
                                    <td class="px-4 py-3 text-right" :class="r.opname_impact < 0 ? 'text-rose-600' : 'text-slate-600'">
                                        {{ formatRupiah(r.opname_impact) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-black" :class="r.net_profit >= 0 ? 'text-teal-700' : 'text-rose-600'">
                                        {{ formatRupiah(r.net_profit) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <!-- ══ TAB 2: PERUBAHAN STOK & OPNAME ══ -->
            <template v-else-if="tab === 'stock_history'">
                <form @submit.prevent="applyFilters('stock_history')" class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs grid grid-cols-1 sm:grid-cols-6 gap-3 items-end text-xs">
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">Dari Tanggal</label>
                        <input v-model="form.start_date" type="date" class="w-full px-3 py-2 rounded-xl border border-slate-200" />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">Sampai Tanggal</label>
                        <input v-model="form.end_date" type="date" class="w-full px-3 py-2 rounded-xl border border-slate-200" />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">Kategori</label>
                        <select v-model="form.category" class="w-full px-3 py-2 rounded-xl border border-slate-200">
                            <option value="all">Semua Kategori</option>
                            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">Barang</label>
                        <select v-model="form.product_id" class="w-full px-3 py-2 rounded-xl border border-slate-200">
                            <option value="all">Semua Barang</option>
                            <option v-for="p in productsList" :key="p.id" :value="String(p.id)">{{ p.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">Jenis Mutasi</label>
                        <select v-model="form.log_type" class="w-full px-3 py-2 rounded-xl border border-slate-200">
                            <option value="all">Semua Jenis</option>
                            <option value="opname">Stok Opname</option>
                            <option value="restock">Restock / Kulakan</option>
                            <option value="damage">Rusak / Hilang</option>
                            <option value="expired">Kedaluwarsa</option>
                            <option value="correction">Koreksi</option>
                        </select>
                    </div>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold">Filter</button>
                </form>

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                        <p class="text-xs font-semibold text-slate-500">Total Catatan Mutasi</p>
                        <p class="text-xl font-black text-slate-900 mt-1">{{ stockHistory.summary.total_logs }}</p>
                    </div>
                    <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                        <p class="text-xs font-semibold text-slate-500">Penambahan Stok (+)</p>
                        <p class="text-xl font-black text-emerald-600 mt-1">+{{ stockHistory.summary.total_plus }}</p>
                    </div>
                    <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                        <p class="text-xs font-semibold text-slate-500">Pengurangan Stok (-)</p>
                        <p class="text-xl font-black text-rose-600 mt-1">{{ stockHistory.summary.total_minus }}</p>
                    </div>
                    <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                        <p class="text-xs font-semibold text-slate-500">Dampak Nilai Modal</p>
                        <p class="text-xl font-black mt-1" :class="stockHistory.summary.net_value_impact >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                            {{ formatRupiah(stockHistory.summary.net_value_impact) }}
                        </p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-600">
                            <thead class="bg-slate-50 text-slate-500 uppercase font-bold text-[10px] border-b border-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Waktu</th>
                                    <th class="px-4 py-3">Barang / Kategori</th>
                                    <th class="px-4 py-3">Jenis</th>
                                    <th class="px-4 py-3 text-right">Awal</th>
                                    <th class="px-4 py-3 text-right">Selisih</th>
                                    <th class="px-4 py-3 text-right">Akhir</th>
                                    <th class="px-4 py-3 text-right">Nilai Selisih</th>
                                    <th class="px-4 py-3">Petugas / Catatan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="l in stockHistory.logs.data" :key="l.id" class="hover:bg-slate-50/70">
                                    <td class="px-4 py-3 whitespace-nowrap">{{ l.created_at }}</td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-900">{{ l.product_name }}</div>
                                        <div class="text-[11px] text-slate-400">{{ l.category }}</div>
                                    </td>
                                    <td class="px-4 py-3 uppercase font-bold text-[11px]">{{ l.type }}</td>
                                    <td class="px-4 py-3 text-right font-mono">{{ l.stock_before }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-bold" :class="l.difference >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                                        {{ l.difference > 0 ? '+' + l.difference : l.difference }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">{{ l.stock_after }}</td>
                                    <td class="px-4 py-3 text-right font-bold" :class="l.value_impact >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                                        {{ formatRupiah(l.value_impact) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-slate-800">{{ l.staff_name }}</div>
                                        <div class="text-[11px] text-slate-400">{{ l.notes || '-' }}</div>
                                    </td>
                                </tr>
                                <tr v-if="stockHistory.logs.data.length === 0">
                                    <td colspan="8" class="px-4 py-8 text-center text-slate-400">Belum ada catatan perubahan stok pada filter ini.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="stockHistory.logs.links && stockHistory.logs.links.length > 3" class="px-4 py-3 border-t border-slate-100 flex justify-center gap-1">
                        <template v-for="(link, i) in stockHistory.logs.links" :key="i">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="px-3 py-1 rounded text-xs font-bold"
                                :class="link.active ? 'bg-emerald-600 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'"
                                v-html="link.label"
                            />
                            <span v-else class="px-3 py-1 rounded text-xs text-slate-400" v-html="link.label"></span>
                        </template>
                    </div>
                </div>
            </template>

            <!-- ══ TAB 3: TRANSAKSI PER MEMBER / PEGAWAI ══ -->
            <template v-else>
                <form @submit.prevent="applyFilters('member_transactions')" class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs grid grid-cols-1 sm:grid-cols-4 gap-3 items-end text-xs">
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">Dari Tanggal</label>
                        <input v-model="form.start_date" type="date" class="w-full px-3 py-2 rounded-xl border border-slate-200" />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">Sampai Tanggal</label>
                        <input v-model="form.end_date" type="date" class="w-full px-3 py-2 rounded-xl border border-slate-200" />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">Pegawai / Member</label>
                        <select v-model="form.customer_key" class="w-full px-3 py-2 rounded-xl border border-slate-200">
                            <option value="all">Semua Pelanggan (Rekap)</option>
                            <option v-for="opt in customerOptions" :key="opt.key" :value="opt.key">{{ opt.label }}</option>
                        </select>
                    </div>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold">Tampilkan</button>
                </form>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-600">
                            <thead class="bg-slate-50 text-slate-500 uppercase font-bold text-[10px] border-b border-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Pelanggan</th>
                                    <th class="px-4 py-3">Kategori</th>
                                    <th class="px-4 py-3 text-right">TRX</th>
                                    <th class="px-4 py-3 text-right">Total Belanja</th>
                                    <th class="px-4 py-3 text-right">Voucher</th>
                                    <th class="px-4 py-3 text-right">RFID</th>
                                    <th class="px-4 py-3 text-right">Tunai</th>
                                    <th class="px-4 py-3 text-right">Sisa BON</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="r in memberReport.summary_rows" :key="r.key" class="hover:bg-slate-50/70">
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-900">{{ r.customer_name }}</div>
                                        <div class="font-mono text-[11px] text-slate-400">{{ r.customer_code }}</div>
                                    </td>
                                    <td class="px-4 py-3">{{ r.customer_group }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-bold">{{ r.trx_count }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-900">{{ formatRupiah(r.total_spent) }}</td>
                                    <td class="px-4 py-3 text-right text-emerald-600">{{ formatRupiah(r.total_voucher) }}</td>
                                    <td class="px-4 py-3 text-right">{{ formatRupiah(r.total_rfid) }}</td>
                                    <td class="px-4 py-3 text-right">{{ formatRupiah(r.total_cash) }}</td>
                                    <td class="px-4 py-3 text-right font-bold" :class="r.unpaid_bon > 0 ? 'text-amber-600' : 'text-slate-400'">
                                        {{ formatRupiah(r.unpaid_bon) }}
                                    </td>
                                </tr>
                                <tr v-if="memberReport.summary_rows.length === 0">
                                    <td colspan="8" class="px-4 py-8 text-center text-slate-400">Tidak ada transaksi pada periode ini.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Rincian Transaksi jika 1 pelanggan dipilih -->
                <div v-if="memberReport.detail_rows.length > 0" class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 text-xs font-black text-slate-700">
                        Rincian Nota Transaksi Pelanggan Terpilih
                    </div>
                    <div class="divide-y divide-slate-100 text-xs">
                        <div v-for="d in memberReport.detail_rows" :key="d.id" class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <div class="font-mono font-bold text-slate-900">{{ d.transaction_code }} <span class="text-slate-400 font-normal">• {{ d.created_at }}</span></div>
                                <div class="text-[11px] text-slate-500 mt-0.5">
                                    {{ d.items.map(it => `${it.name} x${it.qty}`).join(', ') || '-' }}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-black text-slate-900">{{ formatRupiah(d.total_amount) }}</div>
                                <div class="text-[10px] uppercase font-bold text-slate-500">{{ d.payment_method }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            </div>
        </div>
    </PosLayout>
</template>
