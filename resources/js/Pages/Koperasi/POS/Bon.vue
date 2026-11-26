<script setup>
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

const props = defineProps({
    transactions: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    stats: {
        type: Object,
        default: () => ({ total_active_bon: 0, total_paid_bon: 0, active_transactions: 0 }),
    },
    filters: {
        type: Object,
        default: () => ({ status: 'unpaid', search: '' }),
    },
})

const search = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || 'unpaid')

const applyFilter = () => {
    router.get(route('koperasi.bon'), {
        search: search.value,
        status: statusFilter.value,
    }, { preserveState: true, preserveScroll: true })
}

const formatRupiah = (val) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
}).format(Number(val) || 0)

// ─── Modal Bayar / Cicil BON ───
const activeBon = ref(null)
const payForm = useForm({
    amount: 0,
    payment_method: 'cash',
    notes: '',
})

const openPayModal = (trx) => {
    activeBon.value = trx
    payForm.reset()
    payForm.amount = trx.remaining_amount
    payForm.payment_method = 'cash'
}

const submitPay = () => {
    if (!activeBon.value) return
    payForm.post(route('koperasi.bon.pay', activeBon.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            activeBon.value = null
        },
    })
}

const settleAllForCustomer = (trx) => {
    if (!confirm(`Lunasi seluruh BON aktif milik ${trx.customer_name} secara tunai?`)) return
    router.post(route('koperasi.bon.pay-bulk'), {
        customer_type: trx.customer_type,
        customer_id: trx.customer_id,
        payment_method: 'cash',
    }, { preserveScroll: true })
}
</script>

<template>
    <Head title="BON / Piutang Belanja — Koperasi Rabbani" />

    <PosLayout>
        <div class="flex-1 py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full space-y-6 overflow-y-auto bg-slate-100/60">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900">BON / Piutang Belanja</h1>
                    <p class="text-xs text-slate-500">Kelola catatan kasbon pegawai & member, cicilan, dan pelunasan</p>
                </div>
                <Link :href="route('koperasi.pos')" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md shadow-emerald-600/20">
                    Buka Mesin Kasir
                </Link>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                    <p class="text-xs font-semibold text-slate-500">Total Piutang BON Aktif</p>
                    <p class="text-2xl font-black text-amber-600 mt-1">{{ formatRupiah(stats.total_active_bon) }}</p>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                    <p class="text-xs font-semibold text-slate-500">Total BON Sudah Dibayar</p>
                    <p class="text-2xl font-black text-emerald-600 mt-1">{{ formatRupiah(stats.total_paid_bon) }}</p>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                    <p class="text-xs font-semibold text-slate-500">Nota Belum Lunas</p>
                    <p class="text-2xl font-black text-slate-900 mt-1">{{ stats.active_transactions }}</p>
                </div>
            </div>

            <!-- Filter -->
            <form @submit.prevent="applyFilter" class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs flex flex-col sm:flex-row gap-3">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Cari kode transaksi, nama pelanggan, NIP / kode member..."
                    class="flex-1 px-3 py-2 text-xs rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:border-emerald-500"
                />
                <select
                    v-model="statusFilter"
                    @change="applyFilter"
                    class="px-3 py-2 text-xs rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:border-emerald-500 font-semibold"
                >
                    <option value="unpaid">Belum Lunas</option>
                    <option value="paid">Sudah Lunas</option>
                    <option value="all">Semua</option>
                </select>
                <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs">
                    Filter
                </button>
            </form>

            <!-- Table -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50 text-slate-500 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3">Transaksi / Tanggal</th>
                                <th class="px-4 py-3">Pelanggan</th>
                                <th class="px-4 py-3 text-right">Total BON</th>
                                <th class="px-4 py-3 text-right">Sudah Dibayar</th>
                                <th class="px-4 py-3 text-right">Sisa BON</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="trx in transactions.data" :key="trx.id" class="hover:bg-slate-50/70">
                                <td class="px-4 py-3">
                                    <div class="font-mono font-bold text-slate-900">{{ trx.transaction_code }}</div>
                                    <div class="text-[11px] text-slate-400">{{ trx.created_at }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-800">{{ trx.customer_name }}</div>
                                    <div class="text-[11px] text-slate-400">{{ trx.customer_code }} • {{ trx.customer_group }}</div>
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-slate-800">{{ formatRupiah(trx.bon_amount) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-emerald-600">{{ formatRupiah(trx.bon_paid_amount) }}</td>
                                <td class="px-4 py-3 text-right font-black text-amber-600">{{ formatRupiah(trx.remaining_amount) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                                        :class="{
                                            'bg-rose-50 text-rose-700': trx.bon_status === 'unpaid',
                                            'bg-amber-50 text-amber-700': trx.bon_status === 'partial',
                                            'bg-emerald-50 text-emerald-700': trx.bon_status === 'paid',
                                        }"
                                    >
                                        {{ trx.bon_status === 'paid' ? 'Lunas' : (trx.bon_status === 'partial' ? 'Cicilan' : 'Belum Bayar') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div v-if="trx.bon_status !== 'paid'" class="flex items-center justify-center gap-1.5">
                                        <button type="button" @click="openPayModal(trx)" class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px]">
                                            Bayar
                                        </button>
                                        <button type="button" @click="settleAllForCustomer(trx)" class="px-2.5 py-1 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-800 font-bold text-[11px]" title="Lunasi seluruh BON pelanggan ini">
                                            Lunasi Semua
                                        </button>
                                    </div>
                                    <span v-else class="text-[11px] text-slate-400">-</span>
                                </td>
                            </tr>
                            <tr v-if="transactions.data.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-slate-400">Tidak ada data BON pada filter ini.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="transactions.links && transactions.links.length > 3" class="px-4 py-3 border-t border-slate-100 flex justify-center gap-1">
                    <template v-for="(link, i) in transactions.links" :key="i">
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
        </div>

        <!-- Modal Bayar BON -->
        <div v-if="activeBon" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" @click.self="activeBon = null">
            <form @submit.prevent="submitPay" class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4">
                <div>
                    <h3 class="text-base font-black text-slate-900">Pembayaran BON</h3>
                    <p class="text-xs text-slate-500">{{ activeBon.transaction_code }} • {{ activeBon.customer_name }}</p>
                    <p class="text-xs text-amber-700 font-bold mt-1">Sisa Tagihan: {{ formatRupiah(activeBon.remaining_amount) }}</p>
                </div>
                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">Nominal Bayar (Rp) *</label>
                        <input v-model.number="payForm.amount" type="number" min="1" :max="activeBon.remaining_amount" required class="w-full px-3 py-2 rounded-xl border border-slate-200 font-mono text-base font-bold" />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">Metode Pembayaran *</label>
                        <select v-model="payForm.payment_method" class="w-full px-3 py-2 rounded-xl border border-slate-200">
                            <option value="cash">Tunai</option>
                            <option value="qris">QRIS</option>
                            <option value="rfid_card">Potong Saldo RFID ({{ formatRupiah(activeBon.customer_balance) }})</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">Catatan</label>
                        <input v-model="payForm.notes" type="text" placeholder="Pelunasan BON..." class="w-full px-3 py-2 rounded-xl border border-slate-200" />
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="button" @click="activeBon = null" class="flex-1 py-2.5 rounded-xl bg-slate-100 text-slate-700 font-bold text-xs">Batal</button>
                    <button type="submit" :disabled="payForm.processing" class="flex-1 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs">
                        {{ payForm.processing ? 'Memproses...' : 'Catat Pembayaran' }}
                    </button>
                </div>
            </form>
        </div>
    </PosLayout>
</template>
