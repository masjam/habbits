<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PageContainer from '@/Components/UI/PageContainer.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import Modal from '@/Components/UI/Modal.vue'
import StatCard from '@/Components/Dashboard/StatCard.vue'
import { useConfirm } from '@/Composables/useConfirm'
import { useToast } from '@/Composables/useToast'
import { formatNumber, formatPercent, formatRupiah } from '@/utils/formatters'

const props = defineProps({
    period: { type: String, required: true },
    periodName: { type: String, required: true },
    reportData: { type: Object, required: true },
    totalPegawai: { type: Number, default: 0 },
    totalQualified: { type: Number, default: 0 },
    totalGenerated: { type: Number, default: 0 },
    totalRedeemed: { type: Number, default: 0 },
    totalUnredeemed: { type: Number, default: 0 },
    totalExpired: { type: Number, default: 0 },
    totalValueGenerated: { type: Number, default: 0 },
    totalValueRedeemed: { type: Number, default: 0 },
    filters: { type: Object, default: () => ({}) },
    settings: { type: Object, default: () => ({}) },
    availablePeriods: { type: Array, default: () => [] },
    divisions: { type: Array, default: () => [] },
})

const { confirm } = useConfirm()
const toast = useToast()

const selectedPeriod = ref(props.period)
const searchQuery = ref(props.filters?.search || '')
const selectedDivision = ref(props.filters?.division || '')
const selectedStatus = ref(props.filters?.status || 'all')
const perPage = ref(props.filters?.per_page || 15)
const quickRedeemCode = ref('')
const isProcessing = ref(false)
const copiedCode = ref(null)

// Modals
const isSettingModalOpen = ref(false)
const detailItem = ref(null)
const settingForm = ref({
    monthly_target_score: props.settings?.monthly_target_score || 80,
    voucher_reward_title: props.settings?.voucher_reward_title || 'Voucher Apresiasi Target Habit',
    voucher_reward_value: props.settings?.voucher_reward_value || 'Voucher Belanja Koperasi Sekolah Rp 50.000',
    voucher_terms: props.settings?.voucher_terms || '',
})

const applyFilters = (replace = false) => {
    router.get(route('admin.vouchers.index'), {
        period: selectedPeriod.value,
        search: searchQuery.value || undefined,
        division: selectedDivision.value || undefined,
        status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
        per_page: perPage.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace,
    })
}

let searchTimeout = null
watch(searchQuery, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => applyFilters(true), 350)
})

watch([selectedPeriod, selectedDivision, selectedStatus, perPage], () => {
    applyFilters()
})

const resetFilters = () => {
    searchQuery.value = ''
    selectedDivision.value = ''
    selectedStatus.value = 'all'
    perPage.value = 15
}

const hasActiveFilter = computed(() =>
    Boolean(searchQuery.value || selectedDivision.value || selectedStatus.value !== 'all')
)

const copyCode = async (code) => {
    if (!code || typeof navigator === 'undefined' || !navigator.clipboard) return
    try {
        await navigator.clipboard.writeText(code)
        copiedCode.value = code
        toast.success(`Kode ${code} disalin.`)
        setTimeout(() => { copiedCode.value = null }, 2000)
    } catch {}
}

const handleGenerateSingle = async (item) => {
    const ok = await confirm({
        title: 'Terbitkan Voucher Habit',
        message: `Terbitkan voucher reward untuk ${item.name} periode ${props.periodName}?`,
        confirmText: 'Ya, Terbitkan',
        type: 'primary',
    })
    if (!ok) return
    isProcessing.value = true
    router.post(route('admin.vouchers.generate'), {
        period: selectedPeriod.value,
        user_id: item.user_id,
    }, {
        preserveScroll: true,
        onFinish: () => { isProcessing.value = false },
    })
}

const handleGenerateBatch = async () => {
    const ok = await confirm({
        title: 'Generate Massal Voucher',
        message: `Terbitkan voucher untuk semua pegawai yang memenuhi target pada periode ${props.periodName}?`,
        confirmText: 'Terbitkan Massal',
        type: 'primary',
    })
    if (!ok) return
    isProcessing.value = true
    router.post(route('admin.vouchers.generate'), {
        period: selectedPeriod.value,
    }, {
        preserveScroll: true,
        onFinish: () => { isProcessing.value = false },
    })
}

const handleRedeem = async (voucher) => {
    const ok = await confirm({
        title: 'Tandai Voucher Ditebus',
        message: `Tandai voucher ${voucher.code} (${formatRupiah(voucher.nominal)}) sebagai SUDAH DITUKARKAN?`,
        confirmText: 'Ya, Tebus',
        type: 'primary',
    })
    if (!ok) return
    isProcessing.value = true
    router.post(route('admin.vouchers.redeem'), {
        voucher_id: voucher.id,
    }, {
        preserveScroll: true,
        onSuccess: () => { detailItem.value = null },
        onFinish: () => { isProcessing.value = false },
    })
}

const handleQuickRedeem = () => {
    const code = quickRedeemCode.value.trim()
    if (!code || isProcessing.value) return
    isProcessing.value = true
    router.post(route('admin.vouchers.redeem'), {
        voucher_code: code,
    }, {
        preserveScroll: true,
        onSuccess: () => { quickRedeemCode.value = '' },
        onFinish: () => { isProcessing.value = false },
    })
}

const handleUnredeem = async (voucher) => {
    const ok = await confirm({
        title: 'Batalkan Penukaran Voucher',
        message: `Kembalikan status voucher ${voucher.code} menjadi BELUM DITUKARKAN?`,
        confirmText: 'Ya, Batalkan',
        type: 'danger',
    })
    if (!ok) return
    isProcessing.value = true
    router.post(route('admin.vouchers.unredeem', voucher.id), {}, {
        preserveScroll: true,
        onSuccess: () => { detailItem.value = null },
        onFinish: () => { isProcessing.value = false },
    })
}

const saveSettings = () => {
    isProcessing.value = true
    router.post(route('admin.vouchers.settings'), settingForm.value, {
        preserveScroll: true,
        onSuccess: () => { isSettingModalOpen.value = false },
        onFinish: () => { isProcessing.value = false },
    })
}

const voucherBadge = (item) => {
    const v = item.voucher
    if (!v) {
        return item.is_qualified
            ? { label: 'Siap Terbit', cls: 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-800/60' }
            : { label: 'Belum Target', cls: 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700' }
    }
    if (v.is_redeemed) return { label: 'Sudah Ditebus', cls: 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700' }
    if (v.is_expired) return { label: 'Kedaluwarsa', cls: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800/60' }
    return { label: 'Tersedia', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800/60' }
}

const inputClass = 'w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-colors'
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Voucher Habit Koperasi" />

        <PageContainer size="xl">
            <PageHeader
                title="Voucher Habit Koperasi"
                :subtitle="`Periode ${periodName} · Kelola penerbitan & penukaran voucher reward`"
            >
                <template #actions>
                    <button
                        type="button"
                        @click="isSettingModalOpen = true"
                        class="inline-flex items-center px-3.5 py-2 min-h-[44px] sm:min-h-0 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold transition-colors cursor-pointer"
                    >
                        Pengaturan Reward
                    </button>
                    <button
                        type="button"
                        :disabled="isProcessing"
                        @click="handleGenerateBatch"
                        class="inline-flex items-center px-3.5 py-2 min-h-[44px] sm:min-h-0 rounded-xl bg-primary-600 hover:bg-primary-700 disabled:opacity-50 text-white text-xs font-bold transition-colors cursor-pointer"
                    >
                        Generate Massal
                    </button>
                </template>
            </PageHeader>

            <!-- Ringkasan -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <StatCard
                    label="Memenuhi target"
                    :value="formatNumber(totalQualified)"
                    :subtext="`Dari ${formatNumber(totalPegawai)} pegawai`"
                    tone="primary"
                />
                <StatCard
                    label="Voucher terbit"
                    :value="formatNumber(totalGenerated)"
                    :subtext="`Total ${formatRupiah(totalValueGenerated)}`"
                    tone="good"
                />
                <StatCard
                    label="Sudah ditebus"
                    :value="formatNumber(totalRedeemed)"
                    :subtext="`Nilai ${formatRupiah(totalValueRedeemed)}`"
                    tone="neutral"
                />
                <StatCard
                    label="Belum ditebus"
                    :value="formatNumber(totalUnredeemed)"
                    :subtext="totalExpired > 0 ? `${totalExpired} kedaluwarsa` : 'Siap ditukarkan'"
                    :tone="totalUnredeemed > 0 ? 'warn' : 'good'"
                />
            </div>

            <!-- Bar Quick Redeem & Filter -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-4 space-y-3">
                <form @submit.prevent="handleQuickRedeem" class="flex flex-col sm:flex-row gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <input
                        v-model="quickRedeemCode"
                        type="text"
                        placeholder="Scan atau ketik kode voucher untuk penukaran cepat (contoh: SDAM-VCR-...)"
                        class="flex-1 px-3 py-2 text-xs font-mono rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                    />
                    <button
                        type="submit"
                        :disabled="!quickRedeemCode.trim() || isProcessing"
                        class="px-4 py-2 min-h-[44px] sm:min-h-0 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-xs font-bold transition-colors cursor-pointer shrink-0"
                    >
                        Tandai Ditebus
                    </button>
                </form>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
                    <div class="lg:col-span-3">
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Cari pegawai / kode</label>
                        <input v-model="searchQuery" type="text" placeholder="Nama, NIP, atau kode voucher..." :class="inputClass" />
                    </div>
                    <div class="lg:col-span-2">
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Periode</label>
                        <select v-model="selectedPeriod" :class="inputClass">
                            <option v-for="p in availablePeriods" :key="p.period" :value="p.period">{{ p.label }}</option>
                        </select>
                    </div>
                    <div class="lg:col-span-2">
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Divisi</label>
                        <select v-model="selectedDivision" :class="inputClass">
                            <option value="">Semua Divisi</option>
                            <option v-for="d in divisions" :key="d.id" :value="d.name">{{ d.name }}</option>
                        </select>
                    </div>
                    <div class="lg:col-span-3">
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Status</label>
                        <select v-model="selectedStatus" :class="inputClass">
                            <option value="all">Semua Status</option>
                            <option value="qualified">Memenuhi Target</option>
                            <option value="not_generated">Siap Terbit (Belum Digenerate)</option>
                            <option value="unredeemed">Tersedia (Belum Ditebus)</option>
                            <option value="redeemed">Sudah Ditebus</option>
                            <option value="expired">Kedaluwarsa</option>
                        </select>
                    </div>
                    <div class="lg:col-span-2 flex items-center gap-2">
                        <div class="flex-1">
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Baris</label>
                            <select v-model="perPage" :class="inputClass">
                                <option :value="10">10</option>
                                <option :value="15">15</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                            </select>
                        </div>
                        <button
                            v-if="hasActiveFilter"
                            type="button"
                            @click="resetFilters"
                            class="mt-5 px-2.5 py-2 rounded-xl text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40 cursor-pointer"
                        >
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabel Desktop & Kartu Mobile -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 overflow-hidden">
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-800/70 text-slate-500 dark:text-slate-400 font-bold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-4 py-3">Pegawai</th>
                                <th class="px-4 py-3">Capaian Habit</th>
                                <th class="px-4 py-3">Kode Voucher</th>
                                <th class="px-4 py-3 text-right">Nominal</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!reportData?.data?.length">
                                <td colspan="6" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">
                                    Tidak ada data voucher untuk filter ini.
                                </td>
                            </tr>
                            <tr v-for="item in reportData.data" :key="item.user_id" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900 dark:text-slate-100">{{ item.name }}</div>
                                    <div class="text-[11px] text-slate-400">{{ item.divisi }} · {{ item.nip }}</div>
                                </td>
                                <td class="px-4 py-3 tabular-nums">
                                    <div class="font-bold text-slate-800 dark:text-slate-200">
                                        {{ formatPercent(item.achieved_percentage, 1) }}
                                        <span class="font-normal text-slate-400">/ target {{ item.target_percentage }}%</span>
                                    </div>
                                    <div class="text-[11px] text-slate-400">{{ formatNumber(item.total_score) }} / {{ formatNumber(item.max_score) }} poin</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="item.voucher" class="inline-flex items-center gap-1.5">
                                        <code class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-mono font-bold text-slate-800 dark:text-slate-200">
                                            {{ item.voucher.code }}
                                        </code>
                                        <button
                                            type="button"
                                            @click="copyCode(item.voucher.code)"
                                            class="px-2 py-0.5 rounded bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-[11px] font-semibold cursor-pointer"
                                            :aria-label="`Salin kode ${item.voucher.code}`"
                                        >
                                            {{ copiedCode === item.voucher.code ? 'Disalin' : 'Salin' }}
                                        </button>
                                    </div>
                                    <span v-else class="text-slate-400">-</span>
                                </td>
                                <td class="px-4 py-3 text-right font-bold tabular-nums text-slate-900 dark:text-slate-100">
                                    {{ item.voucher ? formatRupiah(item.voucher.nominal) : '-' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border" :class="voucherBadge(item).cls">
                                        {{ voucherBadge(item).label }}
                                    </span>
                                    <div v-if="item.voucher?.claimed_at" class="text-[11px] text-slate-400 mt-0.5">
                                        {{ item.voucher.claimed_at }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-1.5 justify-end">
                                        <button
                                            v-if="!item.voucher && item.is_qualified"
                                            type="button"
                                            :disabled="isProcessing"
                                            @click="handleGenerateSingle(item)"
                                            class="px-3 py-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white font-bold cursor-pointer"
                                        >
                                            Terbitkan
                                        </button>
                                        <button
                                            v-if="item.voucher && !item.voucher.is_redeemed && !item.voucher.is_expired"
                                            type="button"
                                            :disabled="isProcessing"
                                            @click="handleRedeem(item.voucher)"
                                            class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold cursor-pointer"
                                        >
                                            Redeem
                                        </button>
                                        <button
                                            v-if="item.voucher"
                                            type="button"
                                            @click="detailItem = item"
                                            class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold cursor-pointer"
                                        >
                                            Detail
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="md:hidden divide-y divide-slate-100 dark:divide-slate-800">
                    <div v-if="!reportData?.data?.length" class="p-6 text-center text-xs text-slate-500 dark:text-slate-400">
                        Tidak ada data voucher untuk filter ini.
                    </div>
                    <div v-for="item in reportData.data" :key="`mv-${item.user_id}`" class="p-4 space-y-2.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="font-bold text-sm text-slate-900 dark:text-slate-100 truncate">{{ item.name }}</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ item.divisi }} · {{ formatPercent(item.achieved_percentage, 1) }}</div>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold border shrink-0" :class="voucherBadge(item).cls">
                                {{ voucherBadge(item).label }}
                            </span>
                        </div>
                        <div v-if="item.voucher" class="flex items-center justify-between gap-2 text-xs bg-slate-50 dark:bg-slate-800/60 p-2.5 rounded-xl">
                            <code class="font-mono font-bold text-slate-800 dark:text-slate-200 truncate">{{ item.voucher.code }}</code>
                            <span class="font-bold text-emerald-700 dark:text-emerald-400 tabular-nums shrink-0">{{ formatRupiah(item.voucher.nominal) }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                v-if="!item.voucher && item.is_qualified"
                                type="button"
                                @click="handleGenerateSingle(item)"
                                class="flex-1 min-h-[44px] rounded-xl bg-primary-600 text-white text-xs font-bold"
                            >
                                Terbitkan
                            </button>
                            <button
                                v-if="item.voucher && !item.voucher.is_redeemed && !item.voucher.is_expired"
                                type="button"
                                @click="handleRedeem(item.voucher)"
                                class="flex-1 min-h-[44px] rounded-xl bg-emerald-600 text-white text-xs font-bold"
                            >
                                Redeem
                            </button>
                            <button
                                v-if="item.voucher"
                                type="button"
                                @click="detailItem = item"
                                class="flex-1 min-h-[44px] rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold"
                            >
                                Detail &amp; Riwayat
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="reportData?.links?.length > 3" class="px-4 py-3 border-t border-slate-100 dark:border-slate-800">
                    <Pagination :links="reportData.links" />
                </div>
            </div>
        </PageContainer>

        <!-- Modal Detail & Audit Trail -->
        <Modal :show="!!detailItem" maxWidth="md" @close="detailItem = null">
            <div v-if="detailItem?.voucher" class="p-5 space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Detail Voucher</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ detailItem.name }} · {{ detailItem.divisi }}</p>
                    </div>
                    <button type="button" @click="detailItem = null" class="text-xs font-semibold text-slate-400 hover:text-slate-600 cursor-pointer">Tutup</button>
                </div>

                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/70 space-y-1.5 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Kode</span>
                        <code class="font-mono font-bold text-slate-900 dark:text-slate-100">{{ detailItem.voucher.code }}</code>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Nominal</span>
                        <span class="font-bold text-slate-900 dark:text-slate-100 tabular-nums">{{ formatRupiah(detailItem.voucher.nominal) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Berlaku s/d</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-200">{{ detailItem.voucher.valid_until || '-' }}</span>
                    </div>
                    <div v-if="detailItem.voucher.transaction_code" class="flex justify-between">
                        <span class="text-slate-500">Transaksi POS</span>
                        <span class="font-mono font-bold text-emerald-700 dark:text-emerald-400">{{ detailItem.voucher.transaction_code }}</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="text-xs font-bold text-slate-700 dark:text-slate-300">Riwayat Audit</div>
                    <div v-if="detailItem.voucher.logs?.length" class="divide-y divide-slate-100 dark:divide-slate-800 max-h-48 overflow-y-auto">
                        <div v-for="log in detailItem.voucher.logs" :key="log.id" class="py-2 text-xs">
                            <div class="flex justify-between font-semibold text-slate-800 dark:text-slate-200">
                                <span class="capitalize">{{ log.action }} · {{ log.actor }}</span>
                                <span class="text-slate-400 tabular-nums">{{ log.at }}</span>
                            </div>
                            <p v-if="log.notes" class="text-slate-500 dark:text-slate-400 mt-0.5">{{ log.notes }}</p>
                        </div>
                    </div>
                    <p v-else class="text-xs text-slate-400">Belum ada riwayat audit tercatat.</p>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button
                        v-if="detailItem.voucher.is_redeemed && !detailItem.voucher.transaction_code"
                        type="button"
                        :disabled="isProcessing"
                        @click="handleUnredeem(detailItem.voucher)"
                        class="px-4 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold cursor-pointer"
                    >
                        Batalkan Penebusan
                    </button>
                    <button
                        v-if="!detailItem.voucher.is_redeemed && !detailItem.voucher.is_expired"
                        type="button"
                        :disabled="isProcessing"
                        @click="handleRedeem(detailItem.voucher)"
                        class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold cursor-pointer"
                    >
                        Tandai Ditebus
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Modal Pengaturan Reward -->
        <Modal :show="isSettingModalOpen" maxWidth="md" @close="isSettingModalOpen = false">
            <form @submit.prevent="saveSettings" class="p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Pengaturan Reward Voucher</h3>
                    <button type="button" @click="isSettingModalOpen = false" class="text-xs font-semibold text-slate-400 hover:text-slate-600 cursor-pointer">Tutup</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Target Minimal Bulanan (%)</label>
                        <input v-model="settingForm.monthly_target_score" type="number" step="0.1" min="0" max="100" required :class="inputClass" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Judul Reward</label>
                        <input v-model="settingForm.voucher_reward_title" type="text" required :class="inputClass" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Nilai / Keterangan Reward</label>
                        <input v-model="settingForm.voucher_reward_value" type="text" required :class="inputClass" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Syarat &amp; Ketentuan</label>
                        <textarea v-model="settingForm.voucher_terms" rows="3" :class="inputClass" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" @click="isSettingModalOpen = false" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold cursor-pointer">Batal</button>
                    <button type="submit" :disabled="isProcessing" class="px-4 py-2 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold cursor-pointer">
                        Simpan Pengaturan
                    </button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
