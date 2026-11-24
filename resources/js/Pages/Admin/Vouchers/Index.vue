<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useConfirm } from '@/Composables/useConfirm'
import { useToast } from '@/Composables/useToast'
import { IconVoucher } from '@/Components/Icons'

const props = defineProps({
    period: {
        type: String,
        required: true,
    },
    periodName: {
        type: String,
        required: true,
    },
    reportData: {
        type: Array,
        default: () => [],
    },
    totalPegawai: {
        type: Number,
        default: 0,
    },
    totalQualified: {
        type: Number,
        default: 0,
    },
    totalGenerated: {
        type: Number,
        default: 0,
    },
    totalRedeemed: {
        type: Number,
        default: 0,
    },
    totalUnredeemed: {
        type: Number,
        default: 0,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    settings: {
        type: Object,
        default: () => ({}),
    },
    availablePeriods: {
        type: Array,
        default: () => [],
    },
    divisions: {
        type: Array,
        default: () => [],
    },
})

const { confirm } = useConfirm()
const toast = useToast()

const selectedPeriod = ref(props.period)
const searchQuery = ref(props.filters.search || '')
const selectedDivision = ref(props.filters.division || '')
const selectedStatus = ref(props.filters.status || 'all')

const isProcessing = ref(false)
const quickRedeemCode = ref('')

// Settings Modal
const isSettingModalOpen = ref(false)
const settingForm = ref({
    monthly_target_score: props.settings.monthly_target_score || 80,
    voucher_reward_title: props.settings.voucher_reward_title || 'Voucher Apresiasi Target Habit',
    voucher_reward_value: props.settings.voucher_reward_value || 'Voucher Belanja Koperasi Sekolah Rp 50.000',
    voucher_terms: props.settings.voucher_terms || '',
})

const applyFilters = () => {
    router.get(route('admin.vouchers.index'), {
        period: selectedPeriod.value,
        search: searchQuery.value || undefined,
        division: selectedDivision.value || undefined,
        status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

// Generate voucher untuk 1 pegawai
const handleGenerateSingle = async (item) => {
    const ok = await confirm({
        title: 'Terbitkan Voucher Habit',
        message: `Terbitkan voucher reward apresiasi untuk ${item.name} periode ${props.periodName}?`,
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

// Batch generate semua voucher yang qualified
const handleBatchGenerate = async () => {
    const ok = await confirm({
        title: 'Terbitkan Semua Voucher',
        message: `Terbitkan voucher habit secara serentak untuk seluruh pegawai (${props.totalQualified} pegawai) yang memenuhi target sekolah periode ${props.periodName}?`,
        confirmText: 'Terbitkan Semua',
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

// Tandai voucher sudah ditukarkan di koperasi
const handleRedeemVoucher = async (voucherId, employeeName) => {
    const ok = await confirm({
        title: 'Konfirmasi Penukaran Koperasi',
        message: `Tandai voucher milik ${employeeName} sebagai SUDAH DITUKARKAN di Koperasi Sekolah?`,
        confirmText: 'Ya, Tandai Ditukarkan',
        type: 'primary',
    })
    if (!ok) return

    isProcessing.value = true
    router.post(route('admin.vouchers.redeem'), {
        voucher_id: voucherId,
        notes: 'Ditukarkan di Koperasi Sekolah',
    }, {
        preserveScroll: true,
        onFinish: () => { isProcessing.value = false },
    })
}

// Quick Redeem via scan/input kode
const handleQuickRedeem = () => {
    if (!quickRedeemCode.value.trim()) {
        toast.error('Masukkan kode voucher terlebih dahulu!')
        return
    }

    isProcessing.value = true
    router.post(route('admin.vouchers.redeem'), {
        voucher_code: quickRedeemCode.value.trim(),
        notes: 'Ditukarkan via Input Kasir Koperasi',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            quickRedeemCode.value = ''
        },
        onFinish: () => { isProcessing.value = false },
    })
}

// Batalkan penukaran (kembalikan ke belum ditukarkan)
const handleUnredeem = async (voucherId, employeeName) => {
    const ok = await confirm({
        title: 'Batalkan Penukaran Voucher',
        message: `Kembalikan status voucher milik ${employeeName} menjadi BELUM DITUKARKAN?`,
        confirmText: 'Ya, Batalkan Penukaran',
        type: 'warning',
    })
    if (!ok) return

    isProcessing.value = true
    router.post(route('admin.vouchers.unredeem', voucherId), {}, {
        preserveScroll: true,
        onFinish: () => { isProcessing.value = false },
    })
}

// Simpan Pengaturan
const saveSettings = () => {
    isProcessing.value = true
    router.post(route('admin.vouchers.settings'), settingForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            isSettingModalOpen.value = false
        },
        onFinish: () => { isProcessing.value = false },
    })
}
</script>

<template>
    <Head title="Kelola Voucher Habit Koperasi Sekolah" />

    <AuthenticatedLayout>
        <div class="py-6 sm:py-8 space-y-6 max-w-7xl mx-auto px-4 sm:px-6">
            
            <!-- ── HEADER HALAMAN ────────────────────────────────────────────────── -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-theme">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center shadow-xs flex-shrink-0">
                        <IconVoucher class="w-6 h-6" />
                    </div>
                    <div>
                        <h1 class="text-xl sm:text-2xl font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <span>Kelola Voucher Habit Koperasi</span>
                            <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 font-bold border border-emerald-300">
                                Koperasi Sekolah
                            </span>
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                            Penerbitan voucher bagi pegawai yang memenuhi target habit sekolah &amp; verifikasi penukaran di Koperasi Sekolah.
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- Tombol Pengaturan Voucher -->
                    <button
                        type="button"
                        @click="isSettingModalOpen = true"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-card-subtle hover:bg-slate-200 dark:hover:bg-slate-800 border border-theme text-xs font-bold text-slate-700 dark:text-slate-200 transition-all cursor-pointer shadow-2xs"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>Pengaturan Target &amp; Reward</span>
                    </button>

                    <!-- Tombol Terbitkan Semua Voucher -->
                    <button
                        type="button"
                        @click="handleBatchGenerate"
                        :disabled="isProcessing || totalQualified === 0"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-700 hover:to-amber-600 text-white text-xs font-black shadow-md shadow-amber-600/25 active:scale-95 transition-all cursor-pointer disabled:opacity-50"
                    >
                        <span>⚡</span>
                        <span>Terbitkan Semua Voucher ({{ totalQualified }})</span>
                    </button>
                </div>
            </div>

            <!-- ── RINGKASAN STATISTIK ───────────────────────────────────────────── -->
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                <div class="p-4 rounded-2xl bg-card-subtle border border-theme text-center space-y-1 shadow-2xs">
                    <p class="text-[10px] font-bold text-slate-400 uppercase">Total Pegawai</p>
                    <p class="text-xl font-black text-slate-800 dark:text-slate-100">{{ totalPegawai }}</p>
                </div>
                <div class="p-4 rounded-2xl bg-card-subtle border border-theme text-center space-y-1 shadow-2xs">
                    <p class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase">Memenuhi Target</p>
                    <p class="text-xl font-black text-emerald-600 dark:text-emerald-400">{{ totalQualified }}</p>
                </div>
                <div class="p-4 rounded-2xl bg-card-subtle border border-theme text-center space-y-1 shadow-2xs">
                    <p class="text-[10px] font-bold text-amber-600 dark:text-amber-400 uppercase">Voucher Terbit</p>
                    <p class="text-xl font-black text-amber-600 dark:text-amber-400">{{ totalGenerated }}</p>
                </div>
                <div class="p-4 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-800 text-center space-y-1 shadow-2xs">
                    <p class="text-[10px] font-black text-emerald-700 dark:text-emerald-300 uppercase">🟢 Belum Ditukarkan</p>
                    <p class="text-xl font-black text-emerald-700 dark:text-emerald-300">{{ totalUnredeemed }}</p>
                </div>
                <div class="p-4 rounded-2xl bg-blue-50/60 dark:bg-blue-950/40 border border-blue-300 dark:border-blue-800 text-center space-y-1 shadow-2xs col-span-2 sm:col-span-1">
                    <p class="text-[10px] font-black text-blue-700 dark:text-blue-300 uppercase">🔵 Sudah Ditukarkan</p>
                    <p class="text-xl font-black text-blue-700 dark:text-blue-300">{{ totalRedeemed }}</p>
                </div>
            </div>

            <!-- ── PANEL CEPAT PENUKARAN VOUCHER KASIR KOPERASI ───────────────────── -->
            <div class="p-4 sm:p-5 rounded-3xl bg-gradient-to-r from-emerald-500/10 via-teal-500/10 to-blue-500/10 border-2 border-emerald-400/40 dark:border-emerald-700/50 shadow-xs space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <span>🛒 Kasir / Petugas Koperasi: Validasi Penukaran Voucher</span>
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Masukkan atau scan kode voucher yang dibawa pegawai untuk menandainya sebagai SUDAH DITUKARKAN.
                        </p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-center gap-2 max-w-xl">
                    <input
                        type="text"
                        v-model="quickRedeemCode"
                        @keyup.enter="handleQuickRedeem"
                        placeholder="Contoh: SDAM-VCR-202611-ABC123"
                        class="w-full text-xs font-mono font-bold uppercase rounded-xl border border-theme bg-white dark:bg-slate-900 px-3.5 py-2.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                    <button
                        type="button"
                        @click="handleQuickRedeem"
                        :disabled="isProcessing"
                        class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black shadow-md shadow-emerald-600/25 active:scale-95 transition-all cursor-pointer whitespace-nowrap disabled:opacity-50"
                    >
                        ✓ Tandai Sudah Ditukarkan
                    </button>
                </div>
            </div>

            <!-- ── FILTER DAN PENCARIAN ─────────────────────────────────────────── -->
            <div class="flex flex-wrap items-center justify-between gap-3 bg-sidebar p-4 rounded-2xl border border-theme">
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Pilih Periode -->
                    <div>
                        <select
                            v-model="selectedPeriod"
                            @change="applyFilters"
                            class="text-xs font-bold rounded-xl border border-theme bg-card-subtle px-3 py-1.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-primary-500 cursor-pointer"
                        >
                            <option v-for="opt in availablePeriods" :key="opt.period" :value="opt.period">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Filter Divisi -->
                    <div>
                        <select
                            v-model="selectedDivision"
                            @change="applyFilters"
                            class="text-xs rounded-xl border border-theme bg-card-subtle px-3 py-1.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-primary-500 cursor-pointer"
                        >
                            <option value="">Semua Divisi</option>
                            <option v-for="d in divisions" :key="d.id" :value="d.name">{{ d.name }}</option>
                        </select>
                    </div>

                    <!-- Filter Status -->
                    <div>
                        <select
                            v-model="selectedStatus"
                            @change="applyFilters"
                            class="text-xs font-bold rounded-xl border border-theme bg-card-subtle px-3 py-1.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-primary-500 cursor-pointer"
                        >
                            <option value="all">Semua Status</option>
                            <option value="qualified">Memenuhi Target ({{ totalQualified }})</option>
                            <option value="not_generated">Belum Terbit Voucher</option>
                            <option value="unredeemed">🟢 Belum Ditukarkan ({{ totalUnredeemed }})</option>
                            <option value="redeemed">🔵 Sudah Ditukarkan ({{ totalRedeemed }})</option>
                        </select>
                    </div>
                </div>

                <!-- Input Pencarian -->
                <div class="w-full sm:w-64">
                    <input
                        type="text"
                        v-model="searchQuery"
                        @input="applyFilters"
                        placeholder="Cari nama atau NIP pegawai..."
                        class="w-full text-xs rounded-xl border border-theme bg-card-subtle px-3 py-1.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-primary-500"
                    />
                </div>
            </div>

            <!-- ── TABEL DAFTAR PEGAWAI & VOUCHER KOPERASI ───────────────────────── -->
            <div class="bg-sidebar rounded-3xl border border-theme shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-card-subtle text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider text-[10px] border-b border-theme">
                            <tr>
                                <th class="py-3 px-3 text-center w-12">No</th>
                                <th class="py-3 px-4">Pegawai</th>
                                <th class="py-3 px-3 text-center">Divisi</th>
                                <th class="py-3 px-3 text-center">Skor Habit</th>
                                <th class="py-3 px-3 text-center">Pencapaian</th>
                                <th class="py-3 px-3 text-center">Target Sekolah</th>
                                <th class="py-3 px-3 text-center">Status Target</th>
                                <th class="py-3 px-4 text-center">Voucher Koperasi</th>
                                <th class="py-3 px-4 text-center">Aksi Petugas</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-subtle">
                            <tr
                                v-for="(item, idx) in reportData"
                                :key="item.user_id"
                                class="hover:bg-card-subtle/50 transition-colors"
                            >
                                <td class="py-3 px-3 text-center font-bold text-slate-400">
                                    {{ idx + 1 }}
                                </td>

                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-full bg-primary-100 dark:bg-primary-950 flex items-center justify-center font-bold text-xs text-primary-700 flex-shrink-0 overflow-hidden border border-theme">
                                            <img v-if="item.avatar" :src="item.avatar.startsWith('http') ? item.avatar : `/storage/${item.avatar}`" class="w-full h-full object-cover" />
                                            <span v-else>{{ item.name.charAt(0) }}</span>
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-800 dark:text-slate-100">{{ item.name }}</p>
                                            <p class="text-[10px] text-slate-400">NIP: {{ item.nip }}</p>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3 px-3 text-center text-slate-500">
                                    {{ item.divisi }}
                                </td>

                                <td class="py-3 px-3 text-center font-bold text-slate-700 dark:text-slate-200">
                                    {{ item.total_score }} <span class="text-[10px] font-normal text-slate-400">/ {{ item.max_score }}</span>
                                </td>

                                <td class="py-3 px-3 text-center">
                                    <span
                                        :class="[
                                            'font-black',
                                            item.is_qualified ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-400'
                                        ]"
                                    >
                                        {{ item.achieved_percentage }}%
                                    </span>
                                </td>

                                <td class="py-3 px-3 text-center font-bold text-slate-600 dark:text-slate-300">
                                    {{ item.target_percentage }}%
                                </td>

                                <td class="py-3 px-3 text-center">
                                    <span
                                        :class="[
                                            'px-2 py-0.5 rounded-full text-[10px] font-black',
                                            item.is_qualified
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-300'
                                                : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                                        ]"
                                    >
                                        {{ item.is_qualified ? 'Tercapai' : 'Belum' }}
                                    </span>
                                </td>

                                <!-- Status Voucher -->
                                <td class="py-3 px-4 text-center">
                                    <template v-if="item.voucher">
                                        <div class="space-y-1">
                                            <span class="font-mono text-[11px] font-bold text-slate-800 dark:text-slate-200 block">
                                                {{ item.voucher.code }}
                                            </span>
                                            <span
                                                :class="[
                                                    'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black shadow-2xs',
                                                    item.voucher.is_redeemed
                                                        ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300 border border-blue-300'
                                                        : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300 animate-pulse'
                                                ]"
                                            >
                                                <span v-if="item.voucher.is_redeemed">🔵 Sudah Ditukarkan</span>
                                                <span v-else>🟢 Belum Ditukarkan</span>
                                            </span>
                                            <span v-if="item.voucher.is_redeemed" class="block text-[9px] text-slate-400">
                                                {{ item.voucher.claimed_at }}
                                            </span>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <span v-if="item.is_qualified" class="text-amber-600 dark:text-amber-400 font-bold text-[11px]">
                                            ⏳ Siap Diterbitkan
                                        </span>
                                        <span v-else class="text-slate-400 text-[11px]">
                                            -
                                        </span>
                                    </template>
                                </td>

                                <!-- Aksi Petugas -->
                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <!-- Tombol Terbitkan jika belum terbit -->
                                        <button
                                            v-if="item.is_qualified && !item.voucher"
                                            type="button"
                                            @click="handleGenerateSingle(item)"
                                            :disabled="isProcessing"
                                            class="px-3 py-1 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-black text-[11px] shadow-xs active:scale-95 transition-all cursor-pointer"
                                            title="Terbitkan Voucher untuk pegawai ini"
                                        >
                                            ⚡ Terbitkan
                                        </button>

                                        <!-- Tombol Tandai Sudah Ditukarkan di Koperasi -->
                                        <button
                                            v-if="item.voucher && !item.voucher.is_redeemed"
                                            type="button"
                                            @click="handleRedeemVoucher(item.voucher.id, item.name)"
                                            :disabled="isProcessing"
                                            class="px-2.5 py-1 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-[10px] shadow-xs active:scale-95 transition-all cursor-pointer whitespace-nowrap"
                                            title="Tandai voucher ini sudah ditukarkan di Koperasi Sekolah"
                                        >
                                            ✓ Tukarkan di Koperasi
                                        </button>

                                        <!-- Tombol Batalkan Penukaran jika salah klik -->
                                        <button
                                            v-if="item.voucher && item.voucher.is_redeemed"
                                            type="button"
                                            @click="handleUnredeem(item.voucher.id, item.name)"
                                            :disabled="isProcessing"
                                            class="px-2 py-1 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-rose-100 text-slate-600 dark:text-slate-300 hover:text-rose-600 text-[10px] font-bold transition-all cursor-pointer"
                                            title="Batalkan status penukaran"
                                        >
                                            Batal Tukar
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="reportData.length === 0">
                                <td colspan="9" class="py-8 text-center text-xs text-slate-400">
                                    Tidak ada data pegawai yang sesuai dengan filter yang dipilih.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── MODAL PENGATURAN TARGET & REWARD VOUCHER ─────────────────────── -->
            <div
                v-if="isSettingModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
                @click.self="isSettingModalOpen = false"
            >
                <div class="w-full max-w-lg bg-sidebar rounded-3xl border border-theme shadow-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-subtle">
                        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <span>⚙️ Pengaturan Target &amp; Reward Voucher</span>
                        </h4>
                        <button
                            type="button"
                            @click="isSettingModalOpen = false"
                            class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-4 text-xs">
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Target Skor Bulanan Sekolah (%):
                            </label>
                            <input
                                type="number"
                                min="0"
                                max="100"
                                v-model.number="settingForm.monthly_target_score"
                                class="w-full text-xs font-bold rounded-xl border border-theme bg-card-subtle px-3.5 py-2 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-primary-500"
                            />
                            <p class="text-[10px] text-slate-400 mt-1">
                                Standar target sekolah untuk pegawai mendapatkan voucher apresiasi (default: 80%).
                            </p>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Judul Reward Apresiasi:
                            </label>
                            <input
                                type="text"
                                v-model="settingForm.voucher_reward_title"
                                class="w-full text-xs rounded-xl border border-theme bg-card-subtle px-3.5 py-2 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-primary-500"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Bentuk Hadiah / Nominal Voucher Koperasi:
                            </label>
                            <input
                                type="text"
                                v-model="settingForm.voucher_reward_value"
                                class="w-full text-xs font-bold text-amber-600 dark:text-amber-400 rounded-xl border border-theme bg-card-subtle px-3.5 py-2 focus:ring-2 focus:ring-primary-500"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Syarat &amp; Ketentuan Penukaran di Koperasi:
                            </label>
                            <textarea
                                rows="3"
                                v-model="settingForm.voucher_terms"
                                class="w-full text-xs rounded-xl border border-theme bg-card-subtle px-3.5 py-2 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-primary-500"
                            ></textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-subtle">
                        <button
                            type="button"
                            @click="isSettingModalOpen = false"
                            class="px-4 py-2 rounded-xl border border-theme text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            @click="saveSettings"
                            :disabled="isProcessing"
                            class="px-4 py-2 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold shadow-xs cursor-pointer disabled:opacity-50"
                        >
                            Simpan Pengaturan
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
