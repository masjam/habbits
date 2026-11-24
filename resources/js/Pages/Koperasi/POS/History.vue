<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { IconKoperasi, IconArsip } from '@/Components/Icons'

const props = defineProps({
    transactions: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    summary: {
        type: Object,
        default: () => ({ total_transactions: 0, total_discount: 0, total_amount: 0 }),
    },
    filters: {
        type: Object,
        default: () => ({ start_date: '', end_date: '', search: '' }),
    },
})

const filterForm = ref({
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
    search: props.filters.search || '',
})

// Current month period for monthly report (YYYY-MM)
const currentPeriod = new Date().toISOString().slice(0, 7)
const selectedMonth = ref(currentPeriod)

const downloadMonthlySales = () => {
    window.location.href = route('koperasi.export.sales-monthly', { period: selectedMonth.value })
}

const applyFilter = () => {
    router.get(route('koperasi.history'), filterForm.value, {
        preserveState: true,
        preserveScroll: true,
    })
}

const resetFilter = () => {
    filterForm.value = {
        start_date: '',
        end_date: '',
        search: '',
    }
    applyFilter()
}

const formatRupiah = (val) => {
    const num = Number(val) || 0
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(num)
}

// ─── Thermal Receipt Modal ───
const activeReceipt = ref(null)

const showReceipt = (trx) => {
    activeReceipt.value = {
        transaction_code:   trx.transaction_code,
        voucher_code:       trx.voucher_code,
        date_time:          trx.claimed_at,
        cashier_name:       trx.cashier_name,
        customer_name:      trx.user_name,
        customer_nip:       trx.user_nip,
        customer_divisi:    trx.user_divisi,
        reward_title:       trx.reward_title,
        reward_value:       trx.reward_value,
        transaction_amount: trx.transaction_amount,
        discount_amount:    trx.discount_amount,
        payable_amount:     Math.max(0, trx.transaction_amount - trx.discount_amount),
        paid_amount:        trx.paid_amount,
        change_amount:      trx.change_amount,
        items_summary:      trx.items_summary,
        store_name:         'Koperasi Surya Amanah SD Muhammadiyah 1 Krian',
        store_address:      'Jl. Raden Bagus No. 1, Krian, Sidoarjo',
        store_phone:        '0812-3456-7890',
        receipt_footer:     "Terima kasih atas dedikasi ibadah Anda.\nBarang yang sudah dibeli tidak dapat ditukar/dikembalikan.",
    }
}

const printReceipt = () => {
    window.print()
}

// ─── Void Transaction ───
const cancelTransaction = (trx) => {
    if (!confirm(`Batalkan transaksi ${trx.transaction_code} milik ${trx.user_name}? Voucher akan dikembalikan aktif.`)) {
        return
    }
    router.post(route('koperasi.pos.cancel', trx.id), {}, {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Riwayat Transaksi POS — Koperasi Sekolah" />

    <PosLayout>
        <div class="flex-1 py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6 overflow-y-auto bg-slate-100/60">

            <!-- Header & Export Toolbar -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center shadow-xs">
                        <IconArsip class="w-6 h-6" />
                    </div>
                    <div>
                        <h1 class="text-xl sm:text-2xl font-black text-slate-900">Riwayat Transaksi Kasir POS</h1>
                        <p class="text-xs text-slate-500">Log lengkap penjualan, penukaran voucher, & mutasi saldo kartu RFID guru</p>
                    </div>
                </div>

                <!-- Action & Export Buttons -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Month Selector & Download Monthly Sales -->
                    <div class="flex items-center bg-white border border-slate-200 rounded-xl p-1 shadow-xs">
                        <input
                            type="month"
                            v-model="selectedMonth"
                            class="text-xs text-slate-700 border-0 bg-transparent py-1 px-2 focus:ring-0 focus:outline-none font-bold"
                        />
                        <button
                            type="button"
                            @click="downloadMonthlySales"
                            class="px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 font-bold text-xs transition-colors flex items-center gap-1.5"
                            title="Unduh Laporan Penjualan Bulanan ke format Excel"
                        >
                            <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            <span>Laporan Penjualan (Excel)</span>
                        </button>
                    </div>

                    <!-- Download Stock Opname -->
                    <a
                        :href="route('koperasi.export.stock-opname')"
                        class="px-3 py-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs transition-colors flex items-center gap-1.5 shadow-xs"
                        title="Unduh Lembar Stock Opname Fisik ke Excel"
                    >
                        <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        <span>Stock Opname (Excel)</span>
                    </a>

                    <!-- Buka Kasir POS -->
                    <Link
                        :href="route('koperasi.pos')"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/20 transition-all text-xs"
                    >
                        <IconKoperasi class="w-4 h-4" />
                        <span>Buka Mesin Kasir</span>
                    </Link>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                    <p class="text-xs font-semibold text-slate-500">Total Transaksi</p>
                    <p class="text-2xl font-black text-slate-900 mt-1">{{ summary.total_transactions }} <span class="text-xs font-normal text-slate-400">Transaksi</span></p>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                    <p class="text-xs font-semibold text-slate-500">Total Potongan Voucher</p>
                    <p class="text-2xl font-black text-emerald-600 mt-1">{{ formatRupiah(summary.total_discount) }}</p>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                    <p class="text-xs font-semibold text-slate-500">Total Nilai Belanja</p>
                    <p class="text-2xl font-black text-teal-600 mt-1">{{ formatRupiah(summary.total_amount) }}</p>
                </div>
            </div>

            <!-- Filter Panel -->
            <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-xs">
                <form @submit.prevent="applyFilter" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                    <div class="sm:col-span-4">
                        <label class="block text-xs font-bold text-slate-600 mb-1">Cari Transaksi / Pegawai / Kode</label>
                        <input
                            type="text"
                            v-model="filterForm.search"
                            placeholder="Kode TRX / Kode Voucher / Nama Pegawai..."
                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-slate-50 text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                        />
                    </div>
                    <div class="sm:col-span-3">
                        <label class="block text-xs font-bold text-slate-600 mb-1">Dari Tanggal</label>
                        <input
                            type="date"
                            v-model="filterForm.start_date"
                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:bg-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                        />
                    </div>
                    <div class="sm:col-span-3">
                        <label class="block text-xs font-bold text-slate-600 mb-1">Sampai Tanggal</label>
                        <input
                            type="date"
                            v-model="filterForm.end_date"
                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:bg-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                        />
                    </div>
                    <div class="sm:col-span-2 flex gap-2">
                        <button
                            type="submit"
                            class="flex-1 py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-colors shadow-xs"
                        >
                            Filter
                        </button>
                        <button
                            type="button"
                            @click="resetFilter"
                            class="py-2 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs transition-colors"
                        >
                            Reset
                        </button>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50 text-slate-500 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3.5">No. Transaksi / Waktu</th>
                                <th class="px-4 py-3.5">Pegawai</th>
                                <th class="px-4 py-3.5">Kode Voucher</th>
                                <th class="px-4 py-3.5 text-right">Potongan</th>
                                <th class="px-4 py-3.5 text-right">Total Belanja</th>
                                <th class="px-4 py-3.5">Kasir</th>
                                <th class="px-4 py-3.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            <tr v-for="trx in transactions.data" :key="trx.id" class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900 font-mono">{{ trx.transaction_code }}</div>
                                    <div class="text-[11px] text-slate-400">{{ trx.claimed_at }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-800">{{ trx.user_name }}</div>
                                    <div class="text-[11px] text-slate-400">NIP: {{ trx.user_nip }} • {{ trx.user_divisi }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="trx.voucher_code" class="font-mono text-[11px] font-bold px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        {{ trx.voucher_code }}
                                    </span>
                                    <span v-else class="text-slate-400 font-mono text-[11px]">-</span>
                                </td>
                                <td class="px-4 py-3 text-right font-black text-emerald-600">
                                    {{ formatRupiah(trx.discount_amount) }}
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-slate-800">
                                    {{ formatRupiah(trx.transaction_amount) }}
                                </td>
                                <td class="px-4 py-3 text-slate-500">
                                    {{ trx.cashier_name }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button
                                            type="button"
                                            @click="showReceipt(trx)"
                                            title="Cetak Struk"
                                            class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 transition-colors text-[11px] font-bold text-slate-700"
                                        >
                                            Struk
                                        </button>
                                        <button
                                            type="button"
                                            @click="cancelTransaction(trx)"
                                            title="Batalkan (Void)"
                                            class="px-2 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 transition-colors text-[11px] font-bold"
                                        >
                                            Void
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="transactions.data.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-slate-400">
                                    Tidak ada data transaksi yang sesuai dengan filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="transactions.links && transactions.links.length > 3" class="px-4 py-3 border-t border-slate-100 flex justify-center gap-1">
                    <template v-for="(link, i) in transactions.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="px-3 py-1 rounded text-xs font-bold transition-colors"
                            :class="link.active ? 'bg-emerald-600 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'"
                            v-html="link.label"
                        />
                        <span v-else class="px-3 py-1 rounded text-xs text-slate-400" v-html="link.label"></span>
                    </template>
                </div>
            </div>

            <!-- Receipt Modal -->
            <div
                v-if="activeReceipt"
                class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
                @click.self="activeReceipt = null"
            >
                <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl relative border border-slate-200">
                    <button
                        type="button"
                        @click="activeReceipt = null"
                        class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 p-1"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>

                    <div id="receipt-print-area" class="font-mono text-xs text-slate-800 space-y-3 pb-2 border-b border-dashed border-slate-300">
                        <div class="text-center space-y-1">
                            <h3 class="font-black text-sm uppercase tracking-tight">{{ activeReceipt.store_name }}</h3>
                            <p class="text-[10px] text-slate-500">{{ activeReceipt.store_address }}</p>
                            <p class="text-[10px] text-slate-500">Telp: {{ activeReceipt.store_phone }}</p>
                        </div>

                        <div class="border-t border-b border-dashed border-slate-300 py-2 space-y-1 text-[11px]">
                            <div class="flex justify-between">
                                <span>No. TRX:</span>
                                <span class="font-bold">{{ activeReceipt.transaction_code }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Tanggal:</span>
                                <span>{{ activeReceipt.date_time }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Kasir:</span>
                                <span>{{ activeReceipt.cashier_name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Pelanggan:</span>
                                <span class="font-bold">{{ activeReceipt.customer_name }}</span>
                            </div>
                            <div v-if="activeReceipt.voucher_code" class="flex justify-between text-emerald-700">
                                <span>Voucher:</span>
                                <span class="font-bold">{{ activeReceipt.voucher_code }}</span>
                            </div>
                        </div>

                        <!-- Item Summary -->
                        <div class="py-1 space-y-1 text-[11px]">
                            <div class="flex justify-between text-slate-500">
                                <span>Belanja:</span>
                                <span>{{ activeReceipt.items_summary || 'Barang Koperasi' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Subtotal:</span>
                                <span class="font-bold">{{ formatRupiah(activeReceipt.transaction_amount) }}</span>
                            </div>
                            <div v-if="activeReceipt.discount_amount > 0" class="flex justify-between text-emerald-700 font-bold">
                                <span>Potongan Voucher:</span>
                                <span>- {{ formatRupiah(activeReceipt.discount_amount) }}</span>
                            </div>
                            <div class="flex justify-between font-black text-sm border-t border-slate-400 pt-1">
                                <span>Total Bayar:</span>
                                <span>{{ formatRupiah(activeReceipt.payable_amount) }}</span>
                            </div>
                            <div v-if="activeReceipt.paid_amount > 0" class="flex justify-between text-[11px]">
                                <span>Tunai Diterima:</span>
                                <span>{{ formatRupiah(activeReceipt.paid_amount) }}</span>
                            </div>
                            <div v-if="activeReceipt.change_amount > 0" class="flex justify-between text-[11px] font-bold">
                                <span>Kembalian:</span>
                                <span>{{ formatRupiah(activeReceipt.change_amount) }}</span>
                            </div>
                        </div>

                        <div class="text-center pt-2 text-[10px] text-slate-500 whitespace-pre-line leading-relaxed">
                            {{ activeReceipt.receipt_footer }}
                        </div>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <button
                            type="button"
                            @click="printReceipt"
                            class="flex-1 py-2.5 px-4 rounded-xl font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors text-xs flex items-center justify-center gap-1 shadow-xs"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                            <span>Cetak Struk</span>
                        </button>
                        <button
                            type="button"
                            @click="activeReceipt = null"
                            class="py-2.5 px-4 rounded-xl font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition-colors text-xs"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </PosLayout>
</template>

<style scoped>
@media print {
    body * {
        visibility: hidden;
    }
    #receipt-print-area, #receipt-print-area * {
        visibility: visible;
    }
    #receipt-print-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        max-width: 80mm;
        margin: 0 auto;
        padding: 5px;
    }
}
</style>
