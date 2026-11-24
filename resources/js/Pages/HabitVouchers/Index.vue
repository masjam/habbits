<script setup>
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useConfirm } from '@/Composables/useConfirm'
import { useToast } from '@/Composables/useToast'
import { IconVoucher } from '@/Components/Icons'

const props = defineProps({
    selectedPeriod: {
        type: String,
        required: true,
    },
    periodName: {
        type: String,
        required: true,
    },
    isCurrentMonth: {
        type: Boolean,
        default: true,
    },
    daysRemaining: {
        type: Number,
        default: 0,
    },
    totalScore: {
        type: Number,
        default: 0,
    },
    maxPossibleScore: {
        type: Number,
        default: 0,
    },
    achievedPercentage: {
        type: Number,
        default: 0,
    },
    targetPercentage: {
        type: Number,
        default: 80,
    },
    targetScoreNeeded: {
        type: Number,
        default: 0,
    },
    pointsNeeded: {
        type: Number,
        default: 0,
    },
    percentageNeeded: {
        type: Number,
        default: 0,
    },
    isTargetReached: {
        type: Boolean,
        default: false,
    },
    currentVoucher: {
        type: Object,
        default: null,
    },
    voucherHistory: {
        type: Array,
        default: () => [],
    },
    schoolSettings: {
        type: Object,
        default: () => ({}),
    },
    availablePeriods: {
        type: Array,
        default: () => [],
    },
    userCoopBalance: {
        type: Number,
        default: 0,
    },
    userRfidUid: {
        type: String,
        default: '',
    },
})

const page = usePage()
const user = computed(() => page.props.auth?.user ?? {})
const { confirm } = useConfirm()
const toast = useToast()

const selectedMonth = ref(props.selectedPeriod)
const isClaiming = ref(false)
const copiedCode = ref(false)
const selectedVoucherForModal = ref(null)
const isModalOpen = ref(false)

const onPeriodChange = () => {
    router.get(route('habit.vouchers'), { period: selectedMonth.value }, {
        preserveState: true,
        preserveScroll: true,
    })
}

// Copy voucher code
const copyVoucherCode = async (code) => {
    try {
        await navigator.clipboard.writeText(code)
        copiedCode.value = true
        toast.success(`Kode voucher ${code} berhasil disalin!`)
        setTimeout(() => {
            copiedCode.value = false
        }, 3000)
    } catch {
        toast.info(`Kode voucher: ${code}`)
    }
}

// Claim voucher
const handleClaimVoucher = async (voucher) => {
    if (!voucher || voucher.status === 'claimed') return

    const ok = await confirm({
        title: 'Klaim Voucher Habit',
        message: `Apakah Anda yakin ingin mengklaim ${voucher.reward_title || 'Voucher Penghargaan Habit'} periode ${props.periodName}?`,
        confirmText: 'Ya, Klaim Sekarang',
        type: 'primary',
    })

    if (!ok) return

    isClaiming.value = true
    router.post(route('habit.vouchers.claim', voucher.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            isClaiming.value = false
        },
    })
}

// Transfer voucher ke Saldo Kartu RFID Presensi Guru
const isTransferring = ref(false)
const transferToRfid = async (voucher) => {
    const isConfirmed = await confirm({
        title: 'Transfer ke Saldo Kartu RFID Presensi?',
        message: `Voucher senilai ${voucher.reward_value} akan dimasukkan ke Saldo Kartu Presensi Anda. Saldo dapat langsung digunakan belanja di Koperasi Sekolah cukup dengan tap kartu RFID Anda. Lanjutkan?`,
        confirmText: 'Ya, Masukkan ke Saldo Kartu',
        cancelText: 'Batal',
    })

    if (!isConfirmed) return

    isTransferring.value = true
    router.post(route('habit.vouchers.convert-to-rfid', voucher.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            isTransferring.value = false
        },
    })
}

// Open modal detail voucher
const openVoucherModal = (voucher) => {
    selectedVoucherForModal.value = voucher
    isModalOpen.value = true
}

const closeVoucherModal = () => {
    isModalOpen.value = false
    selectedVoucherForModal.value = null
}

// Print voucher
const printVoucher = () => {
    window.print()
}

// Formatter tanggal
const formatDate = (dateString) => {
    if (!dateString) return '-'
    const d = new Date(dateString)
    return d.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    })
}
</script>

<template>
    <Head title="Voucher Apresiasi Habit Pegawai" />

    <AuthenticatedLayout>
        <div class="py-6 sm:py-8 space-y-6 max-w-6xl mx-auto px-4 sm:px-6 print:p-0 print:m-0 print:max-w-none">
            
            <!-- ── HEADER HALAMAN (Disembunyikan saat print) ─────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-theme print:hidden">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center shadow-xs flex-shrink-0">
                        <IconVoucher class="w-6 h-6" />
                    </div>
                    <div>
                        <h1 class="text-xl sm:text-2xl font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <span>Voucher Apresiasi Habit</span>
                            <span class="text-xs px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-700 font-bold">
                                Reward Sekolah
                            </span>
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                            Penghargaan bagi pegawai yang berhasil mencapai target habit yang ditetapkan oleh sekolah.
                        </p>
                    </div>
                </div>

                <!-- Pemilih Periode Bulan -->
                <div class="flex items-center gap-2 self-start sm:self-auto">
                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400 whitespace-nowrap">Periode:</label>
                    <select
                        v-model="selectedMonth"
                        @change="onPeriodChange"
                        class="text-xs font-bold rounded-xl border border-theme bg-card-subtle px-3 py-2 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-primary-500 cursor-pointer shadow-2xs"
                    >
                        <option v-for="opt in availablePeriods" :key="opt.period" :value="opt.period">
                            {{ opt.label }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- ── ATM RFID CARD: SALDO BELANJA KOPERASI GURU ──────────────── -->
            <div class="bg-gradient-to-r from-emerald-800 via-teal-800 to-slate-900 rounded-3xl p-5 sm:p-6 text-white shadow-xl relative overflow-hidden print:hidden border border-emerald-500/30">
                <div class="absolute -right-12 -top-12 w-48 h-48 bg-emerald-400/10 rounded-full blur-2xl pointer-events-none"></div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-400/30">
                                Kartu Presensi & Belanja Koperasi SDAM
                            </span>
                        </div>
                        <h2 class="text-xs sm:text-sm font-semibold text-emerald-200/90">Saldo Belanja Koperasi (Kartu RFID)</h2>
                        <div class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-baseline gap-1">
                            <span>Rp</span>
                            <span>{{ (userCoopBalance || 0).toLocaleString('id-ID') }}</span>
                        </div>
                    </div>

                    <div class="sm:text-right space-y-1">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block">No. Kartu RFID / NIP</span>
                        <span class="font-mono text-xs font-bold text-emerald-300 px-2.5 py-1 rounded-lg bg-black/30 border border-white/10 inline-block">
                            {{ userRfidUid || user.nip || 'Belum Terhubung' }}
                        </span>
                        <p class="text-[11px] text-emerald-200/70 max-w-xs sm:ml-auto">
                            Cukup tap kartu presensi Anda di kasir Koperasi Sekolah untuk belanja menggunakan saldo ini.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ── CARD 1: STATUS PENCAPAIAN TARGET BULAN TERPILIH ──────────────── -->
            <div class="bg-sidebar rounded-3xl border border-theme shadow-xs p-5 sm:p-6 space-y-5 print:hidden">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <span class="text-[11px] font-black uppercase tracking-wider text-primary-600 dark:text-primary-400">
                            Pencapaian Bulan {{ periodName }}
                        </span>
                        <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 mt-0.5 flex items-center gap-2">
                            <span>Status Target Sekolah:</span>
                            <span
                                :class="[
                                    'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black shadow-2xs',
                                    isTargetReached
                                        ? 'bg-emerald-100 text-emerald-800 border border-emerald-300 dark:bg-emerald-950/70 dark:text-emerald-300 dark:border-emerald-800'
                                        : 'bg-amber-100 text-amber-800 border border-amber-300 dark:bg-amber-950/70 dark:text-amber-300 dark:border-amber-800'
                                ]"
                            >
                                <span v-if="isTargetReached">🎉 Target Tercapai</span>
                                <span v-else>⏳ Belum Mencapai Target</span>
                            </span>
                        </h2>
                    </div>

                    <!-- Target vs Skor Saya -->
                    <div class="flex items-center gap-4 text-center">
                        <div class="px-3.5 py-2 rounded-2xl bg-card-subtle border border-theme shadow-2xs">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Target Sekolah</p>
                            <p class="text-base font-black text-slate-800 dark:text-slate-200">{{ targetPercentage }}%</p>
                        </div>
                        <div class="px-3.5 py-2 rounded-2xl bg-card-subtle border border-theme shadow-2xs">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Pencapaian Anda</p>
                            <p :class="['text-base font-black', isTargetReached ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400']">
                                {{ achievedPercentage }}%
                            </p>
                        </div>
                        <div class="px-3.5 py-2 rounded-2xl bg-card-subtle border border-theme shadow-2xs">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Total Skor</p>
                            <p class="text-base font-black text-slate-800 dark:text-slate-200">{{ totalScore }} <span class="text-[10px] font-normal text-slate-400">/ {{ maxPossibleScore }}</span></p>
                        </div>
                    </div>
                </div>

                <!-- Progress Bar Visual -->
                <div class="space-y-2">
                    <div class="flex justify-between text-xs font-semibold text-slate-500 dark:text-slate-400">
                        <span>Skor Diperoleh: <strong>{{ totalScore }} poin</strong></span>
                        <span>Target Minimal: <strong>{{ targetScoreNeeded }} poin ({{ targetPercentage }}%)</strong></span>
                    </div>

                    <div class="relative w-full h-4 bg-slate-100 dark:bg-slate-800/80 rounded-full overflow-hidden border border-theme">
                        <div
                            class="h-full rounded-full transition-all duration-700 ease-out"
                            :class="[
                                isTargetReached
                                    ? 'bg-gradient-to-r from-emerald-500 to-teal-400 shadow-sm shadow-emerald-500/50'
                                    : 'bg-gradient-to-r from-amber-500 to-yellow-400'
                            ]"
                            :style="{ width: `${Math.min(100, achievedPercentage)}%` }"
                        />
                        <!-- Garis target penanda threshold -->
                        <div
                            class="absolute top-0 bottom-0 w-0.5 bg-rose-500 z-10"
                            :style="{ left: `${Math.min(100, targetPercentage)}%` }"
                            :title="`Batas Target Sekolah: ${targetPercentage}%`"
                        />
                    </div>

                    <div class="flex items-center justify-between text-[11px] pt-1">
                        <p v-if="isTargetReached" class="text-emerald-700 dark:text-emerald-400 font-bold flex items-center gap-1.5">
                            <span>✨</span>
                            <span>Alhamdulillah! Anda berhak mendapatkan voucher penghargaan target habit untuk periode ini.</span>
                        </p>
                        <p v-else class="text-amber-700 dark:text-amber-400 font-medium flex items-center gap-1.5">
                            <span>💪</span>
                            <span>Kurang <strong>{{ pointsNeeded }} poin</strong> ({{ percentageNeeded }}%) lagi untuk membuka Voucher Habit periode ini.</span>
                        </p>

                        <span v-if="isCurrentMonth && daysRemaining > 0" class="text-slate-400 text-[10px]">
                            Tersisa <strong>{{ daysRemaining }} hari</strong> di bulan ini
                        </span>
                    </div>
                </div>
            </div>

            <!-- ── CARD 2: VOUCHER PENGHARGAAN (TAMPILAN TIKET PREMIUM) ─────────── -->
            <div v-if="currentVoucher" class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-400">
                        Voucher Anda untuk Periode Ini:
                    </h3>
                    <div class="flex items-center gap-2 print:hidden">
                        <button
                            type="button"
                            @click="printVoucher"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-card-subtle hover:bg-slate-200 dark:hover:bg-slate-800 border border-theme text-xs font-bold text-slate-700 dark:text-slate-200 transition-all cursor-pointer shadow-2xs"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            <span>Cetak Voucher</span>
                        </button>
                    </div>
                </div>

                <!-- TIKET VOUCHER UTAMA -->
                <div class="relative bg-gradient-to-br from-amber-500/10 via-amber-400/5 to-emerald-500/10 dark:from-amber-950/40 dark:via-slate-900 dark:to-emerald-950/30 rounded-3xl border-2 border-amber-400/60 dark:border-amber-600/50 p-6 sm:p-8 shadow-xl overflow-hidden backdrop-blur-md">
                    
                    <!-- Ornamen Background Watermark -->
                    <div class="absolute -right-8 -bottom-8 w-64 h-64 text-amber-500/5 pointer-events-none select-none">
                        <IconVoucher class="w-full h-full" />
                    </div>

                    <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                        
                        <!-- Sisi Kiri: Detail Penghargaan & Sekolah -->
                        <div class="space-y-4 flex-1">
                            <!-- Header Voucher -->
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-primary-100 dark:bg-primary-950/70 border border-primary-300 dark:border-primary-800 flex items-center justify-center p-1 shadow-2xs flex-shrink-0">
                                    <img src="/logo.png" alt="Logo SDAM" class="w-full h-full object-contain" />
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-400 leading-none">
                                        SD MUHAMMADIYAH 1 KRIAN
                                    </p>
                                    <h4 class="text-lg sm:text-xl font-black text-slate-800 dark:text-slate-100 leading-tight mt-0.5">
                                        {{ currentVoucher.title }}
                                    </h4>
                                </div>
                            </div>

                            <!-- Reward Info -->
                            <div class="p-4 rounded-2xl bg-white/70 dark:bg-slate-900/70 border border-amber-300/80 dark:border-amber-700/60 shadow-xs space-y-2">
                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Bentuk Reward Apresiasi:</span>
                                    <div class="text-base sm:text-lg font-black text-amber-600 dark:text-amber-400 flex items-center gap-2">
                                        <span>🎁</span>
                                        <span>{{ currentVoucher.reward_value }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 pt-1 border-t border-amber-200/50 dark:border-amber-800/40 text-xs text-slate-700 dark:text-slate-300">
                                    <span class="font-bold text-emerald-700 dark:text-emerald-400 flex items-center gap-1">
                                        <span>🏪</span>
                                        <span>Tempat Penukaran:</span>
                                    </span>
                                    <span class="font-black text-slate-800 dark:text-slate-100">{{ schoolSettings.redeem_location || 'Koperasi Sekolah' }}</span>
                                </div>
                                <p class="text-xs text-slate-600 dark:text-slate-300 pt-0.5">
                                    {{ currentVoucher.description }}
                                </p>
                            </div>

                            <!-- Metadata Pegawai & Validitas -->
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                                <div>
                                    <span class="text-slate-400 text-[10px] uppercase font-bold block">Penerima</span>
                                    <span class="font-bold text-slate-800 dark:text-slate-100">{{ user.name }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 text-[10px] uppercase font-bold block">Pencapaian Habit</span>
                                    <span class="font-extrabold text-emerald-600 dark:text-emerald-400">
                                        {{ currentVoucher.achieved_percentage }}% <span class="text-slate-400 font-normal">(Target {{ currentVoucher.target_percentage }}%)</span>
                                    </span>
                                </div>
                                <div class="col-span-2 sm:col-span-1">
                                    <span class="text-slate-400 text-[10px] uppercase font-bold block">Masa Berlaku</span>
                                    <span class="font-bold text-slate-700 dark:text-slate-200">
                                        s/d {{ formatDate(currentVoucher.valid_until) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Garis Pembatas Tiket Perforasi (Hanya di layar besar) -->
                        <div class="hidden lg:flex flex-col items-center justify-between self-stretch px-2 py-1">
                            <div class="w-3 h-3 rounded-full bg-slate-200 dark:bg-slate-800 -mt-8" />
                            <div class="w-0.5 h-full border-r-2 border-dashed border-amber-400/50 my-1" />
                            <div class="w-3 h-3 rounded-full bg-slate-200 dark:bg-slate-800 -mb-8" />
                        </div>

                        <!-- Sisi Kanan: Kode Voucher, Status, & Aksi Klaim -->
                        <div class="flex flex-col items-center justify-center p-5 rounded-2xl bg-white/80 dark:bg-slate-900/80 border border-theme shadow-xs space-y-4 min-w-[240px] text-center">
                            
                            <!-- Status Penukaran Koperasi -->
                            <div>
                                <span
                                    v-if="currentVoucher.is_redeemed"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-black bg-blue-100 text-blue-800 dark:bg-blue-950/70 dark:text-blue-300 border border-blue-300 dark:border-blue-800"
                                >
                                    <span>🔵</span> Sudah Ditukarkan di Koperasi
                                </span>
                                <span
                                    v-else-if="currentVoucher.is_expired"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-black bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                >
                                    Kadaluarsa
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-black bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 animate-pulse"
                                >
                                    <span>🟢</span> Belum Ditukarkan (Bawa ke Koperasi)
                                </span>

                                <span v-if="currentVoucher.is_redeemed" class="block text-[10px] text-slate-400 mt-1">
                                    Ditukarkan pada {{ currentVoucher.claimed_at }}
                                    <span v-if="currentVoucher.redeemed_by_name">oleh {{ currentVoucher.redeemed_by_name }}</span>
                                </span>
                            </div>

                            <!-- Kode Voucher Besar -->
                            <div class="w-full space-y-1">
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">KODE VOUCHER KOPERASI</span>
                                <div class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 border-2 border-dashed border-amber-500/60 font-mono text-base font-black text-slate-800 dark:text-slate-100 tracking-wider">
                                    {{ currentVoucher.voucher_code }}
                                </div>
                            </div>

                            <!-- Tombol Salin Kode -->
                            <button
                                type="button"
                                @click="copyVoucherCode(currentVoucher.voucher_code)"
                                class="inline-flex items-center gap-1.5 text-xs font-bold text-primary-600 dark:text-primary-400 hover:underline cursor-pointer"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                <span>{{ copiedCode ? 'Tersalin!' : 'Salin Kode' }}</span>
                            </button>

                            <!-- Tombol Transfer ke Saldo Kartu RFID -->
                            <button
                                v-if="!currentVoucher.is_redeemed && !currentVoucher.is_expired"
                                type="button"
                                @click="transferToRfid(currentVoucher)"
                                :disabled="isTransferring"
                                class="w-full py-2.5 px-3 rounded-xl font-bold text-xs bg-emerald-600 hover:bg-emerald-700 text-white transition-all shadow-md shadow-emerald-600/20 flex items-center justify-center gap-1.5"
                            >
                                <span>💳</span>
                                <span>{{ isTransferring ? 'Memproses...' : 'Transfer ke Saldo Kartu RFID Presensi' }}</span>
                            </button>

                            <!-- Petunjuk Penukaran -->
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                Tunjukkan kode ini kepada petugas di <strong>Koperasi Sekolah</strong> untuk ditukarkan dengan reward Anda.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pesan jika target tercapai tapi belum diterbitkan oleh Admin, ATAU belum tercapai -->
            <div v-else class="bg-card-subtle rounded-3xl border border-dashed border-theme p-8 text-center space-y-3">
                <div class="w-16 h-16 rounded-3xl flex items-center justify-center mx-auto text-2xl shadow-xs" :class="isTargetReached ? 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-600' : 'bg-amber-100 dark:bg-amber-950/40 text-amber-600'">
                    {{ isTargetReached ? '🎉' : '🎁' }}
                </div>
                
                <template v-if="isTargetReached">
                    <h3 class="text-base font-black text-slate-800 dark:text-slate-100">
                        Target Habit Bulan {{ periodName }} Berhasil Anda Capai! ({{ achievedPercentage }}%)
                    </h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-lg mx-auto">
                        Alhamdulillah, pencapaian habit Anda telah memenuhi target sekolah ({{ targetPercentage }}%). Voucher apresiasi koperasi sedang dalam proses penerbitan resmi oleh <strong>Admin / Manajemen Sekolah</strong>. Begitu diterbitkan oleh Admin, kartu voucher Anda akan langsung aktif di sini.
                    </p>
                </template>

                <template v-else>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">
                        Voucher Belum Tersedia untuk Bulan {{ periodName }}
                    </h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-md mx-auto">
                        Pencapaian habit Anda saat ini <strong>{{ achievedPercentage }}%</strong>. Anda membutuhkan <strong>{{ pointsNeeded }} poin</strong> ({{ percentageNeeded }}%) lagi untuk memenuhi target sekolah ({{ targetPercentage }}%).
                    </p>
                    <div class="pt-2">
                        <a
                            :href="route('habit.form')"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary-600 hover:bg-primary-700 text-white font-bold text-xs shadow-xs transition-all cursor-pointer"
                        >
                            <span>✍️ Isi Habit Sekarang</span>
                        </a>
                    </div>
                </template>
            </div>

            <!-- ── SECTION 3: RIWAYAT VOUCHER TERDAHULU ──────────────────────────── -->
            <div class="bg-sidebar rounded-3xl border border-theme shadow-xs p-5 sm:p-6 space-y-4 print:hidden">
                <div class="flex items-center justify-between pb-3 border-b border-subtle">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">
                            Riwayat Voucher Apresiasi Anda
                        </h3>
                        <p class="text-xs text-slate-400">
                            Daftar seluruh voucher habit yang pernah Anda dapatkan dari sekolah.
                        </p>
                    </div>
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-card-subtle text-slate-600 dark:text-slate-300 border border-theme">
                        {{ voucherHistory.length }} Voucher
                    </span>
                </div>

                <div v-if="voucherHistory.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div
                        v-for="v in voucherHistory"
                        :key="v.id"
                        class="p-4 rounded-2xl bg-card-subtle/80 hover:bg-card-subtle border border-theme transition-all space-y-2.5 shadow-2xs cursor-pointer group"
                        @click="openVoucherModal(v)"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black text-primary-600 dark:text-primary-400">
                                Periode {{ v.period }}
                            </span>
                            <span
                                :class="[
                                    'text-[10px] font-bold px-2 py-0.5 rounded-full',
                                    v.is_redeemed
                                        ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300'
                                        : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                ]"
                            >
                                {{ v.is_redeemed ? '🔵 Sudah Ditukarkan' : '🟢 Belum Ditukarkan' }}
                            </span>
                        </div>

                        <div>
                            <p class="text-xs font-bold text-slate-800 dark:text-slate-100 group-hover:text-primary-600 transition-colors">
                                {{ v.reward_title }}
                            </p>
                            <p class="text-[11px] font-black text-amber-600 dark:text-amber-400 mt-0.5">
                                {{ v.reward_value }}
                            </p>
                        </div>

                        <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1 border-t border-subtle">
                            <span class="font-mono font-bold">{{ v.voucher_code }}</span>
                            <span>Pencapaian: <strong>{{ v.achieved_percentage }}%</strong></span>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-6 text-xs text-slate-400">
                    Belum ada riwayat voucher terdahulu. Terus tingkatkan pembiasaan habit harian Anda!
                </div>
            </div>

            <!-- ── SECTION 4: SYARAT & KETENTUAN PENUKARAN ───────────────────────── -->
            <div class="bg-card-subtle rounded-2xl border border-theme p-4 sm:p-5 space-y-2 text-xs text-slate-600 dark:text-slate-400 print:hidden">
                <h4 class="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                    <span>ℹ️</span>
                    <span>Informasi &amp; Ketentuan Penukaran Voucher:</span>
                </h4>
                <div class="whitespace-pre-line leading-relaxed text-[11px]">
                    {{ schoolSettings.terms }}
                </div>
            </div>

            <!-- ── MODAL RINCIAN VOUCHER (JIKA DIKLIK DARI RIWAYAT) ──────────────── -->
            <div
                v-if="isModalOpen && selectedVoucherForModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs print:hidden"
                @click.self="closeVoucherModal"
            >
                <div class="w-full max-w-lg bg-sidebar rounded-3xl border border-theme shadow-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-subtle">
                        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <span>🎁 Rincian Voucher</span>
                            <span class="text-xs px-2 py-0.5 rounded-md bg-amber-100 text-amber-800 font-bold">
                                {{ selectedVoucherForModal.period }}
                            </span>
                        </h4>
                        <button
                            type="button"
                            @click="closeVoucherModal"
                            class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-3">
                        <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-center space-y-1">
                            <span class="text-[10px] font-bold uppercase text-slate-400">KODE VOUCHER</span>
                            <div class="font-mono text-lg font-black text-slate-800 dark:text-slate-100 tracking-wider">
                                {{ selectedVoucherForModal.voucher_code }}
                            </div>
                            <p class="text-xs font-bold text-amber-600 dark:text-amber-400 pt-1">
                                {{ selectedVoucherForModal.reward_value }}
                            </p>
                        </div>

                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between py-1 border-b border-subtle">
                                <span class="text-slate-400">Judul Penghargaan:</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ selectedVoucherForModal.title }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-subtle">
                                <span class="text-slate-400">Status Penukaran:</span>
                                <span class="font-bold" :class="selectedVoucherForModal.is_redeemed ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'">
                                    {{ selectedVoucherForModal.is_redeemed ? '🔵 Sudah Ditukarkan di Koperasi' : '🟢 Belum Ditukarkan' }}
                                </span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-subtle">
                                <span class="text-slate-400">Tempat Penukaran:</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200">Koperasi Sekolah</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-subtle">
                                <span class="text-slate-400">Pencapaian Habit:</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200">
                                    {{ selectedVoucherForModal.achieved_percentage }}% (Target: {{ selectedVoucherForModal.target_percentage }}%)
                                </span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-subtle">
                                <span class="text-slate-400">Masa Berlaku:</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200">
                                    s/d {{ formatDate(selectedVoucherForModal.valid_until) }}
                                </span>
                            </div>
                            <div v-if="selectedVoucherForModal.claimed_at" class="flex justify-between py-1 border-b border-subtle">
                                <span class="text-slate-400">Waktu Ditukarkan:</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200">
                                    {{ selectedVoucherForModal.claimed_at }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-subtle">
                        <button
                            type="button"
                            @click="copyVoucherCode(selectedVoucherForModal.voucher_code)"
                            class="px-4 py-2 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold cursor-pointer"
                        >
                            Salin Kode Voucher
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@media print {
    body {
        background: #ffffff !important;
        color: #000000 !important;
    }
}
</style>
