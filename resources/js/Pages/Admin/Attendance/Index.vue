<script setup>
import { ref, computed, watch } from 'vue'
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const page = usePage()

const props = defineProps({
    monthlyReportData: Array,
    dailyReportData: Array,
    monthlyStats: Object,
    dailyStats: Object,
    daysList: Array,
    daysInMonth: Number,
    officeLocation: Object,
    filters: Object,
    formattedMonth: String,
    formattedDate: String,
    divisions: Array,
    isSuperadmin: Boolean,
})

// Hak akses edit presensi: Superadmin
const canEditAttendance = computed(() => {
    return !!(
        props.isSuperadmin ||
        page.props.auth?.roles?.includes('superadmin') ||
        page.props.auth?.is_actual_superadmin
    )
})

// State Filter & Mode Tampilan
const activeTab = ref(props.filters?.view_mode || 'monthly') // 'monthly' | 'daily'
const selectedMonth = ref(props.filters?.month || new Date().toISOString().substring(0, 7))
const selectedDate = ref(props.filters?.date || new Date().toISOString().split('T')[0])
const selectedDivision = ref(props.filters?.division || '')
const selectedStatus = ref(props.filters?.status || '')
const searchQuery = ref(props.filters?.search || '')

let searchTimeout = null
watch(searchQuery, (val) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        applyFilters()
    }, 300)
})

const applyFilters = () => {
    router.get(route('admin.attendance.index'), {
        month: selectedMonth.value,
        date: selectedDate.value,
        view_mode: activeTab.value,
        division: selectedDivision.value,
        status: selectedStatus.value,
        search: searchQuery.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

// Beralih ke tanggal tertentu saat kolom tanggal di klik
const selectDateAndSwitch = (targetDate) => {
    selectedDate.value = targetDate
    activeTab.value = 'daily'
    applyFilters()
}

// ─── PAGINATION MATRIKS BULANAN ─────────────────────────────────────────────
const currentPage = ref(1)
const perPage = ref(15)
const perPageOptions = [10, 15, 25, 50, 100]

const totalMonthlyRows = computed(() => props.monthlyReportData?.length || 0)
const totalMonthlyPages = computed(() => Math.max(1, Math.ceil(totalMonthlyRows.value / perPage.value)))

const paginatedMonthlyData = computed(() => {
    if (!props.monthlyReportData) return []
    const start = (currentPage.value - 1) * perPage.value
    return props.monthlyReportData.slice(start, start + perPage.value)
})

const paginationStart = computed(() => {
    if (totalMonthlyRows.value === 0) return 0
    return (currentPage.value - 1) * perPage.value + 1
})

const paginationEnd = computed(() => {
    return Math.min(currentPage.value * perPage.value, totalMonthlyRows.value)
})

const goToPage = (p) => {
    const target = Math.max(1, Math.min(p, totalMonthlyPages.value))
    currentPage.value = target
}

const visiblePages = computed(() => {
    const total = totalMonthlyPages.value
    const current = currentPage.value
    const delta = 2
    const range = []
    const rangeWithDots = []
    let l

    for (let i = 1; i <= total; i++) {
        if (i === 1 || i === total || (i >= current - delta && i <= current + delta)) {
            range.push(i)
        }
    }

    for (const i of range) {
        if (l) {
            if (i - l === 2) {
                rangeWithDots.push(l + 1)
            } else if (i - l !== 1) {
                rangeWithDots.push('...')
            }
        }
        rangeWithDots.push(i)
        l = i
    }

    return rangeWithDots
})

// Reset pagination ke halaman 1 saat filter atau data berubah
watch([
    () => props.monthlyReportData,
    () => selectedMonth.value,
    () => selectedDivision.value,
    () => selectedStatus.value,
    () => searchQuery.value,
    perPage
], () => {
    currentPage.value = 1
})

// Navigasi Ganti Bulan
const prevMonth = () => {
    const [year, month] = selectedMonth.value.split('-').map(Number)
    const prevDate = new Date(year, month - 2, 1)
    selectedMonth.value = `${prevDate.getFullYear()}-${String(prevDate.getMonth() + 1).padStart(2, '0')}`
    applyFilters()
}

const nextMonth = () => {
    const [year, month] = selectedMonth.value.split('-').map(Number)
    const nextDate = new Date(year, month, 1)
    selectedMonth.value = `${nextDate.getFullYear()}-${String(nextDate.getMonth() + 1).padStart(2, '0')}`
    applyFilters()
}

// Ekspor ke file Microsoft Excel (.xlsx)
const exportExcel = () => {
    const params = new URLSearchParams({
        type: activeTab.value,
        month: selectedMonth.value,
        date: selectedDate.value,
        division: selectedDivision.value,
    })
    window.location.href = `${route('admin.attendance.export')}?${params.toString()}`
}

// ─── HELPER CEK PENGAJUAN IZIN / PULANG CEPAT ───────────────────────────────
const hasPermitOrEarlyDeparture = (record) => {
    if (!record) return false
    return ['izin', 'sakit'].includes(record.status) ||
           !!record.is_early_departure ||
           (!!record.approval_type && record.approval_type !== 'none') ||
           record.approval_status === 'pending'
}

const getPermitTypeLabel = (record) => {
    if (!record) return 'Izin'
    if (record.status === 'sakit' || record.approval_type === 'sakit') return 'Sakit'
    if (record.status === 'izin' || record.approval_type === 'izin') return 'Izin Tidak Masuk'
    if (record.is_early_departure || record.approval_type === 'pulang_cepat') return 'Pulang Mendahului'
    return 'Pengajuan Izin'
}

// ─── PERSETUJUAN & PENOLAKAN IZIN OLEH ADMIN ─────────────────────────────────
const isRejectModalOpen = ref(false)
const selectedRejectItem = ref(null)

const approvalForm = useForm({
    attendance_id: null,
    approval_status: 'approved',
    rejection_note: '',
})

const handleQuickApprove = (item) => {
    const attId = item.attendance_id || item.id || item.record?.id || item.record?.attendance_id
    if (!attId) return
    const userName = item.name || item.userName || 'pegawai ini'
    if (!confirm(`Setujui permohonan izin/kehadiran untuk ${userName}?`)) return

    router.post(route('admin.attendance.approval'), {
        attendance_id: attId,
        approval_status: 'approved',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            if (selectedDetailModal.value?.record) {
                selectedDetailModal.value.record.approval_status = 'approved'
            }
        }
    })
}

const openRejectModal = (item) => {
    selectedRejectItem.value = item
    const attId = item.attendance_id || item.id || item.record?.id || item.record?.attendance_id
    approvalForm.attendance_id = attId
    approvalForm.approval_status = 'rejected'
    approvalForm.rejection_note = item.rejection_note || item.record?.rejection_note || ''
    isRejectModalOpen.value = true
}

const submitRejectApproval = () => {
    if (!approvalForm.rejection_note.trim()) {
        alert('Keterangan alasan penolakan wajib diisi.')
        return
    }
    approvalForm.post(route('admin.attendance.approval'), {
        preserveScroll: true,
        onSuccess: () => {
            isRejectModalOpen.value = false
            if (selectedDetailModal.value?.record) {
                selectedDetailModal.value.record.approval_status = 'rejected'
                selectedDetailModal.value.record.rejection_note = approvalForm.rejection_note
            }
            approvalForm.reset()
        }
    })
}

// ─── MODAL DETAIL & EDIT PRESENSI (SUPERADMIN) ──────────────────────────────
const selectedDetailModal = ref(null)
const isDeletingAttendance = ref(false)

const editForm = useForm({
    id: null,
    user_id: null,
    date: '',
    time_in: '',
    time_out: '',
    status: 'hadir',
    notes: '',
})

const openCellDetail = (user, dayItem, record) => {
    // Jika bukan superadmin dan tidak ada data presensi, abaikan
    if (!canEditAttendance.value && !record) return

    selectedDetailModal.value = {
        userId: user.user_id,
        userName: user.name,
        userNip: user.nip,
        userDivisi: user.divisi,
        schedule: user.work_schedule,
        work_start: user.work_start,
        work_end: user.work_end,
        late_tolerance: user.late_tolerance,
        date: dayItem.date,
        dayNumber: dayItem.day,
        record: record || null,
    }

    // Populate form data
    editForm.id = record?.id || record?.attendance_id || null
    editForm.user_id = user.user_id
    editForm.date = dayItem.date
    editForm.time_in = record?.time_in ? record.time_in.substring(0, 5) : ''
    editForm.time_out = record?.time_out ? record.time_out.substring(0, 5) : ''
    editForm.status = (record?.status && record.status !== 'belum_hadir') ? record.status : 'hadir'
    editForm.notes = record?.notes || ''
}

const closeCellDetail = () => {
    selectedDetailModal.value = null
    editForm.reset()
    editForm.clearErrors()
}

const submitEditAttendance = () => {
    editForm.post(route('admin.attendance.update-record'), {
        preserveScroll: true,
        onSuccess: () => {
            closeCellDetail()
        }
    })
}

const deleteAttendanceRecord = () => {
    if (!editForm.id) return
    const name = selectedDetailModal.value?.userName || 'pegawai'
    const date = selectedDetailModal.value?.date || ''
    if (!confirm(`Hapus data presensi ${name} pada tanggal ${date}? Tindakan ini tidak dapat dibatalkan.`)) {
        return
    }
    isDeletingAttendance.value = true
    router.delete(route('admin.attendance.delete-record', editForm.id), {
        preserveScroll: true,
        onFinish: () => {
            isDeletingAttendance.value = false
            closeCellDetail()
        }
    })
}

// Helper Style Cell Matriks per Tanggal
const getCellClass = (record, isWeekend, isFuture) => {
    if (!record) {
        if (isWeekend) return 'bg-slate-100/50 dark:bg-slate-900/30 text-slate-300 dark:text-slate-600'
        if (isFuture) return 'bg-card-subtle/30 text-slate-300 dark:text-slate-600'
        return 'bg-card-subtle/40 text-slate-400 hover:bg-slate-100/60'
    }

    if (record.is_pulang_cepat) {
        return 'bg-purple-50/90 text-purple-900 border border-purple-200 dark:bg-purple-950/40 dark:text-purple-200 dark:border-purple-800/80 hover:scale-105 shadow-2xs'
    }
    if (record.status === 'dinas_luar') {
        return 'bg-blue-50/90 text-blue-900 border border-blue-200 dark:bg-blue-950/40 dark:text-blue-200 dark:border-blue-800/80 hover:scale-105 shadow-2xs'
    }
    if (record.status === 'terlambat') {
        return 'bg-amber-50/90 text-amber-900 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:border-amber-800/80 hover:scale-105 shadow-2xs'
    }
    return 'bg-emerald-50/90 text-emerald-900 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:border-emerald-800/80 hover:scale-105 shadow-2xs'
}
</script>

<template>
    <Head title="Rekap Presensi Pegawai" />

    <AuthenticatedLayout>
        <div class="space-y-6">

            <!-- ── Header & Action Bar ───────────────────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-800 dark:text-slate-100 flex items-center gap-2.5">
                        <span>Rekap Presensi Pegawai</span>
                        <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                            Matriks Bulanan GPS
                        </span>
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Matriks jam masuk &amp; jam pulang dalam satu kolom tanggal per pegawai dengan kode warna status.
                    </p>
                </div>

                <div class="flex items-center gap-2.5">
                    <!-- Link ke Pengaturan Superadmin (Titik Lokasi & Cleansing) -->
                    <Link
                        v-if="canEditAttendance"
                        :href="route('admin.settings.hr')"
                        class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-card-subtle hover:bg-emerald-50 dark:hover:bg-emerald-950/40 text-slate-700 dark:text-slate-200 border border-theme text-xs font-bold transition-all active:scale-95 cursor-pointer shadow-xs"
                        title="Atur titik lokasi default, jam kerja, dan pembersihan foto di Pengaturan HR & Pemeliharaan Sistem"
                    >
                        <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>⚙️ Pengaturan Default &amp; Cleansing</span>
                    </Link>

                    <!-- Tombol Ekspor Excel -->
                    <button
                        type="button"
                        @click="exportExcel"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black shadow-md hover:shadow-emerald-600/20 active:scale-95 transition-all cursor-pointer"
                        :title="activeTab === 'monthly' ? 'Ekspor Matriks Bulanan (.xlsx)' : 'Ekspor Detail Harian (.xlsx)'"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Ekspor Excel (.xlsx)</span>
                    </button>
                </div>
            </div>

            <!-- ── BLOK KONTROL REKAP (Tab Mode, Navigasi Periode, Statistik & Filter) ── -->
            <div class="bg-sidebar rounded-3xl border border-theme shadow-xs p-4 sm:p-5 space-y-4">
                <!-- Baris 1: Mode Tab & Navigasi Periode -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-subtle">
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="activeTab = 'monthly'; applyFilters()"
                            :class="[
                                'flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer',
                                activeTab === 'monthly'
                                    ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/30'
                                    : 'bg-card-subtle text-slate-600 dark:text-slate-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 border border-theme/60'
                            ]"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            <span>📊 Matriks Bulanan</span>
                        </button>

                        <button
                            type="button"
                            @click="activeTab = 'daily'; applyFilters()"
                            :class="[
                                'flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer',
                                activeTab === 'daily'
                                    ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/30'
                                    : 'bg-card-subtle text-slate-600 dark:text-slate-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 border border-theme/60'
                            ]"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>📅 Detail Harian ({{ formattedDate }})</span>
                        </button>
                    </div>

                    <!-- Kontrol Navigasi Periode (Bulan / Tanggal) -->
                    <div class="flex items-center gap-2 self-start sm:self-auto">
                        <template v-if="activeTab === 'monthly'">
                            <button
                                type="button"
                                @click="prevMonth"
                                class="p-1.5 rounded-lg bg-card-subtle hover:bg-emerald-100 dark:hover:bg-emerald-950 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer border border-theme"
                                title="Bulan Sebelumnya"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>

                            <input 
                                type="month" 
                                v-model="selectedMonth" 
                                @change="applyFilters"
                                class="text-xs font-bold rounded-xl border border-theme bg-card-subtle px-3 py-1.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 cursor-pointer"
                            />

                            <button
                                type="button"
                                @click="nextMonth"
                                class="p-1.5 rounded-lg bg-card-subtle hover:bg-emerald-100 dark:hover:bg-emerald-950 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer border border-theme"
                                title="Bulan Berikutnya"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </template>
                        <template v-else>
                            <input 
                                type="date" 
                                v-model="selectedDate" 
                                @change="applyFilters"
                                class="text-xs font-bold rounded-xl border border-theme bg-card-subtle px-3 py-1.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 cursor-pointer"
                            />
                        </template>
                    </div>
                </div>

                <!-- Baris 2: Statistik Ringkasan Mini di Samping Filter & Pencarian -->
                <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-3">
                    <!-- Sisi Kiri: 7 Kotak Statistik Mini -->
                    <div v-if="activeTab === 'monthly'" class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                        <div class="px-2.5 py-1.5 bg-card-subtle/80 rounded-xl border border-theme/60 shadow-2xs text-center min-w-[64px]">
                            <p class="text-[10px] font-bold text-slate-500 dark:text-slate-400 leading-none whitespace-nowrap">Total Pegawai</p>
                            <p class="text-sm font-black text-slate-800 dark:text-slate-100 leading-none mt-1">{{ monthlyStats?.total_pegawai || 0 }}</p>
                        </div>
                        <div class="px-2.5 py-1.5 bg-card-subtle/80 rounded-xl border border-theme/60 shadow-2xs text-center min-w-[64px]">
                            <p class="text-[10px] font-bold text-emerald-600 leading-none whitespace-nowrap">Rata-rata Hadir</p>
                            <p class="text-sm font-black text-emerald-600 leading-none mt-1">{{ monthlyStats?.avg_attendance_rate || 0 }}%</p>
                        </div>
                        <div class="px-2.5 py-1.5 bg-card-subtle/80 rounded-xl border border-theme/60 shadow-2xs text-center min-w-[64px]">
                            <p class="text-[10px] font-bold text-teal-600 leading-none whitespace-nowrap">Tepat Waktu</p>
                            <p class="text-sm font-black text-teal-600 leading-none mt-1">{{ monthlyStats?.total_hadir_sebulan || 0 }}</p>
                        </div>
                        <div class="px-2.5 py-1.5 bg-card-subtle/80 rounded-xl border border-theme/60 shadow-2xs text-center min-w-[64px]">
                            <p class="text-[10px] font-bold text-amber-600 leading-none whitespace-nowrap">Terlambat</p>
                            <p class="text-sm font-black text-amber-600 leading-none mt-1">{{ monthlyStats?.total_terlambat_sebulan || 0 }}</p>
                        </div>
                        <div class="px-2.5 py-1.5 bg-card-subtle/80 rounded-xl border border-theme/60 shadow-2xs text-center min-w-[64px]">
                            <p class="text-[10px] font-bold text-blue-600 leading-none whitespace-nowrap">Tugas Luar</p>
                            <p class="text-sm font-black text-blue-600 leading-none mt-1">{{ monthlyStats?.total_dinas_luar_sebulan || 0 }}</p>
                        </div>
                        <div class="px-2.5 py-1.5 bg-card-subtle/80 rounded-xl border border-theme/60 shadow-2xs text-center min-w-[64px]">
                            <p class="text-[10px] font-bold text-purple-600 leading-none whitespace-nowrap">Pulang Cepat</p>
                            <p class="text-sm font-black text-purple-600 leading-none mt-1">{{ monthlyStats?.total_pulang_cepat_sebulan || 0 }}</p>
                        </div>
                        <div 
                            @click="selectedStatus = (selectedStatus === 'pending' ? '' : 'pending'); applyFilters()"
                            class="px-2.5 py-1.5 rounded-xl border shadow-2xs cursor-pointer transition-all hover:scale-105 active:scale-95 text-center min-w-[64px]"
                            :class="[
                                selectedStatus === 'pending'
                                    ? 'border-amber-500 bg-amber-50/70 dark:bg-amber-950/50'
                                    : (monthlyStats?.total_pending_approval > 0 ? 'border-amber-400 bg-amber-50/30' : 'border-theme/60 bg-card-subtle/80')
                            ]"
                            title="Klik untuk filter yang perlu review"
                        >
                            <div class="flex items-center justify-center gap-1">
                                <p class="text-[10px] font-bold text-amber-600 leading-none whitespace-nowrap">Perlu Review</p>
                                <span v-if="monthlyStats?.total_pending_approval > 0" class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping" />
                            </div>
                            <p class="text-sm font-black text-amber-600 leading-none mt-1">{{ monthlyStats?.total_pending_approval || 0 }}</p>
                        </div>
                    </div>

                    <div v-else class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                        <div class="px-2.5 py-1.5 bg-card-subtle/80 rounded-xl border border-theme/60 shadow-2xs text-center min-w-[64px]">
                            <p class="text-[10px] font-bold text-slate-500 dark:text-slate-400 leading-none whitespace-nowrap">Total Pegawai</p>
                            <p class="text-sm font-black text-slate-800 dark:text-slate-100 leading-none mt-1">{{ dailyStats?.total_pegawai || 0 }}</p>
                        </div>
                        <div class="px-2.5 py-1.5 bg-card-subtle/80 rounded-xl border border-theme/60 shadow-2xs text-center min-w-[64px]">
                            <p class="text-[10px] font-bold text-emerald-600 leading-none whitespace-nowrap">Tepat Waktu</p>
                            <p class="text-sm font-black text-emerald-600 leading-none mt-1">{{ dailyStats?.hadir || 0 }}</p>
                        </div>
                        <div class="px-2.5 py-1.5 bg-card-subtle/80 rounded-xl border border-theme/60 shadow-2xs text-center min-w-[64px]">
                            <p class="text-[10px] font-bold text-amber-600 leading-none whitespace-nowrap">Terlambat</p>
                            <p class="text-sm font-black text-amber-600 leading-none mt-1">{{ dailyStats?.terlambat || 0 }}</p>
                        </div>
                        <div class="px-2.5 py-1.5 bg-card-subtle/80 rounded-xl border border-theme/60 shadow-2xs text-center min-w-[64px]">
                            <p class="text-[10px] font-bold text-blue-600 leading-none whitespace-nowrap">Tugas Luar</p>
                            <p class="text-sm font-black text-blue-600 leading-none mt-1">{{ dailyStats?.dinas_luar || 0 }}</p>
                        </div>
                        <div class="px-2.5 py-1.5 bg-card-subtle/80 rounded-xl border border-theme/60 shadow-2xs text-center min-w-[64px]">
                            <p class="text-[10px] font-bold text-purple-600 leading-none whitespace-nowrap">Pulang Cepat</p>
                            <p class="text-sm font-black text-purple-600 leading-none mt-1">{{ dailyStats?.pulang_cepat || 0 }}</p>
                        </div>
                        <div class="px-2.5 py-1.5 bg-card-subtle/80 rounded-xl border border-theme/60 shadow-2xs text-center min-w-[64px]">
                            <p class="text-[10px] font-bold text-indigo-600 leading-none whitespace-nowrap">Izin / Sakit</p>
                            <p class="text-sm font-black text-indigo-600 leading-none mt-1">{{ dailyStats?.izin_sakit || 0 }}</p>
                        </div>
                        <div 
                            @click="selectedStatus = (selectedStatus === 'pending' ? '' : 'pending'); applyFilters()"
                            class="px-2.5 py-1.5 rounded-xl border shadow-2xs cursor-pointer transition-all hover:scale-105 active:scale-95 text-center min-w-[64px]"
                            :class="[
                                selectedStatus === 'pending'
                                    ? 'border-amber-500 bg-amber-50/70 dark:bg-amber-950/50'
                                    : (dailyStats?.pending_approval > 0 ? 'border-amber-400 bg-amber-50/30' : 'border-theme/60 bg-card-subtle/80')
                            ]"
                            title="Klik untuk filter yang perlu review"
                        >
                            <div class="flex items-center justify-center gap-1">
                                <p class="text-[10px] font-bold text-amber-600 leading-none whitespace-nowrap">Perlu Review</p>
                                <span v-if="dailyStats?.pending_approval > 0" class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping" />
                            </div>
                            <p class="text-sm font-black text-amber-600 leading-none mt-1">{{ dailyStats?.pending_approval || 0 }}</p>
                        </div>
                    </div>

                    <!-- Sisi Kanan: Filter Divisi, Status & Pencarian di Sampingnya -->
                    <div class="flex flex-wrap items-center gap-2">
                        <div>
                            <select 
                                v-model="selectedDivision" 
                                @change="applyFilters"
                                class="text-xs rounded-xl border border-theme bg-card-subtle px-3 py-1.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500"
                            >
                                <option value="">Semua Divisi</option>
                                <option v-for="d in divisions" :key="d.id" :value="d.name">{{ d.name }}</option>
                            </select>
                        </div>

                        <div>
                            <select 
                                v-model="selectedStatus" 
                                @change="applyFilters"
                                class="text-xs rounded-xl border border-theme bg-card-subtle px-3 py-1.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 font-medium"
                            >
                                <option value="">Semua Status</option>
                                <option value="pending">⏳ Menunggu Review ({{ activeTab === 'daily' ? (dailyStats?.pending_approval || 0) : (monthlyStats?.total_pending_approval || 0) }})</option>
                                <option value="hadir">Hadir (Tepat Waktu)</option>
                                <option value="terlambat">Terlambat</option>
                                <option value="dinas_luar">Tugas Luar</option>
                                <option value="izin">Izin</option>
                                <option value="sakit">Sakit</option>
                                <option value="belum_hadir">Belum Hadir</option>
                            </select>
                        </div>

                        <div class="w-44 sm:w-56">
                            <div class="relative">
                                <input 
                                    type="text" 
                                    v-model="searchQuery" 
                                    placeholder="Cari nama pegawai..." 
                                    class="w-full text-xs rounded-xl border border-theme bg-card-subtle pl-8 pr-3 py-1.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500"
                                />
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── TABEL 1: MATRIKS PRESENSI BULANAN (GRID CELL JAM MASUK & PULANG) ── -->
            <div v-if="activeTab === 'monthly'" class="bg-sidebar rounded-3xl border border-theme shadow-xs overflow-hidden">
                <div class="p-4 border-b border-subtle flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <span>Matriks Kehadiran Bulan {{ formattedMonth }}</span>
                        </h2>
                        <p class="text-[11px] text-slate-400">
                            Format cell per tanggal: <strong>[Jam Masuk]</strong> (atas) dan <strong>[Jam Pulang]</strong> (bawah). Klik tanggal di header atau cell untuk rincian.
                        </p>
                    </div>

                    <!-- Legenda Warna Status Cell di Header Tabel Matriks -->
                    <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 text-[10px] font-bold">
                        <span class="text-slate-400 uppercase tracking-wider text-[9px] mr-0.5">Legenda:</span>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" /> Tepat Waktu
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500" /> Terlambat
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-blue-50 text-blue-800 border border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500" /> Tugas Luar
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-purple-50 text-purple-800 border border-purple-200 dark:bg-purple-950/60 dark:text-purple-300 dark:border-purple-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-purple-500" /> Pulang Mendahului
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-slate-100 text-slate-500 border border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400" /> Weekend / Libur
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-subtle bg-card-subtle text-slate-600 dark:text-slate-300">
                                <!-- Sticky Header Pegawai di Kiri -->
                                <th class="py-3 px-4 font-bold sticky left-0 bg-card-subtle z-20 min-w-[210px] border-r border-subtle shadow-xs">
                                    Pegawai
                                </th>

                                <!-- Kolom-Kolom Tanggal (1 s/d 30/31) di Bagian Atas -->
                                <th 
                                    v-for="d in daysList" 
                                    :key="d.day"
                                    @click="selectDateAndSwitch(d.date)"
                                    :class="[
                                        'py-2 px-1 text-center min-w-[54px] max-w-[62px] border-r border-subtle/70 transition-colors cursor-pointer select-none group',
                                        d.is_today ? 'bg-emerald-100/60 dark:bg-emerald-950/50' : 
                                        d.is_weekend ? 'bg-slate-100/50 dark:bg-slate-800/30' : 'hover:bg-emerald-50 dark:hover:bg-emerald-900/20'
                                    ]"
                                    :title="`Klik untuk melihat rekap detail tanggal ${d.date}`"
                                >
                                    <div class="flex flex-col items-center justify-center">
                                        <span :class="['text-[9px] uppercase font-bold tracking-tight', d.is_weekend ? 'text-rose-500' : 'text-slate-400 group-hover:text-emerald-600']">
                                            {{ d.day_name }}
                                        </span>
                                        <span :class="['text-xs font-black leading-none mt-0.5', d.is_today ? 'text-emerald-700 dark:text-emerald-400 underline decoration-2' : 'text-slate-800 dark:text-slate-100']">
                                            {{ d.day }}
                                        </span>
                                    </div>
                                </th>

                                <!-- Kolom Ringkasan Akumulasi di Kanan -->
                                <th class="py-2 px-2.5 text-center min-w-[40px] font-bold text-emerald-600 border-r border-subtle/70" title="Tepat Waktu">H</th>
                                <th class="py-2 px-2.5 text-center min-w-[40px] font-bold text-amber-600 border-r border-subtle/70" title="Terlambat">T</th>
                                <th class="py-2 px-2.5 text-center min-w-[40px] font-bold text-blue-600 border-r border-subtle/70" title="Tugas Luar">DL</th>
                                <th class="py-2 px-2.5 text-center min-w-[40px] font-bold text-purple-600 border-r border-subtle/70" title="Pulang Mendahului">PC</th>
                                <th class="py-2 px-3 text-center min-w-[50px] font-black text-slate-800 dark:text-slate-100 border-r border-subtle/70" title="Total Hadir">Total</th>
                                <th class="py-2 px-3 text-center min-w-[55px] font-black text-emerald-600" title="Persentase">%</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-subtle">
                            <tr v-for="user in paginatedMonthlyData" :key="user.user_id" class="hover:bg-card-subtle/30 transition-colors">
                                <!-- Sticky Info Pegawai -->
                                <td class="py-2.5 px-4 sticky left-0 bg-sidebar z-10 border-r border-subtle shadow-xs">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-950 flex items-center justify-center font-bold text-xs text-emerald-700 dark:text-emerald-400 flex-shrink-0 overflow-hidden border border-emerald-200 dark:border-emerald-800">
                                            <img v-if="user.avatar" :src="user.avatar.startsWith('http') ? user.avatar : `/storage/${user.avatar}`" class="w-full h-full object-cover" />
                                            <span v-else>{{ user.name.charAt(0).toUpperCase() }}</span>
                                        </div>
                                        <div class="truncate max-w-[150px]">
                                            <p class="font-bold text-slate-800 dark:text-slate-100 text-xs truncate" :title="user.name">{{ user.name }}</p>
                                            <p class="text-[10px] text-slate-400 truncate">
                                                {{ user.divisi || 'Tanpa Divisi' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Cell Presensi per Tanggal (Jam Masuk & Pulang dalam 1 cell) -->
                                <td
                                    v-for="d in daysList"
                                    :key="d.day"
                                    @click="openCellDetail(user, d, user.daily_records?.[d.day])"
                                    :class="[
                                        'py-1.5 px-0.5 text-center border-r border-subtle/60 transition-all cursor-pointer select-none',
                                        d.is_today ? 'bg-emerald-50/20' : '',
                                        canEditAttendance && !user.daily_records?.[d.day] && !d.is_future ? 'hover:bg-emerald-100/50 dark:hover:bg-emerald-950/40' : ''
                                    ]"
                                    :title="canEditAttendance ? (user.daily_records?.[d.day] ? `Klik untuk lihat/koreksi presensi (${user.name} - tgl ${d.day})` : `Klik untuk input presensi (${user.name} - tgl ${d.day})`) : (user.daily_records?.[d.day] ? 'Klik untuk melihat detail presensi' : '')"
                                >
                                    <div 
                                        :class="[
                                            'rounded-lg py-1 px-1 transition-all mx-auto max-w-[56px]',
                                            getCellClass(user.daily_records?.[d.day], d.is_weekend, d.is_future)
                                        ]"
                                    >
                                        <template v-if="user.daily_records?.[d.day]">
                                            <!-- Jam Masuk (Atas) -->
                                            <div class="text-[10px] font-black font-mono leading-none tracking-tight flex items-center justify-center gap-0.5">
                                                <span>{{ user.daily_records[d.day].time_in || '--:--' }}</span>
                                                <span 
                                                    v-if="hasPermitOrEarlyDeparture(user.daily_records[d.day])" 
                                                    class="text-[9px] cursor-help inline-block leading-none" 
                                                    :title="`🚩 ${getPermitTypeLabel(user.daily_records[d.day])}`"
                                                >🚩</span>
                                            </div>
                                            <!-- Jam Pulang (Bawah) -->
                                            <div class="text-[9px] font-mono leading-none tracking-tight mt-1 opacity-80 flex items-center justify-center gap-0.5">
                                                <span>{{ user.daily_records[d.day].time_out || '--:--' }}</span>
                                                <span v-if="user.daily_records[d.day].is_overtime" class="text-[8px] text-amber-500 font-black" :title="`Lembur: ${user.daily_records[d.day].overtime_minutes}m`">⚡</span>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <span 
                                                :class="[
                                                    'text-[10px] font-bold block py-1 transition-all',
                                                    canEditAttendance && !d.is_future ? 'text-slate-400 dark:text-slate-500 hover:text-emerald-600 hover:scale-125 font-black' : 'text-slate-300 dark:text-slate-600'
                                                ]"
                                            >
                                                {{ canEditAttendance && !d.is_future ? '+' : '-' }}
                                            </span>
                                        </template>
                                    </div>
                                </td>

                                <!-- Ringkasan Hitungan Sebulan -->
                                <td class="py-2.5 px-2.5 text-center font-bold text-emerald-600 border-r border-subtle/60 text-xs">
                                    {{ user.total_hadir }}
                                </td>
                                <td class="py-2.5 px-2.5 text-center font-bold text-amber-600 border-r border-subtle/60 text-xs">
                                    {{ user.total_terlambat }}
                                </td>
                                <td class="py-2.5 px-2.5 text-center font-bold text-blue-600 border-r border-subtle/60 text-xs">
                                    {{ user.total_dinas_luar }}
                                </td>
                                <td class="py-2.5 px-2.5 text-center font-bold text-purple-600 border-r border-subtle/60 text-xs">
                                    {{ user.total_pulang_cepat }}
                                </td>
                                <td class="py-2.5 px-3 text-center font-black text-slate-800 dark:text-slate-100 border-r border-subtle/60 text-xs">
                                    {{ user.total_kehadiran }}
                                </td>
                                <td class="py-2.5 px-3 text-center font-black text-emerald-600 text-xs">
                                    {{ user.attendance_rate }}%
                                </td>
                            </tr>

                            <tr v-if="!monthlyReportData || monthlyReportData.length === 0">
                                <td :colspan="daysList.length + 7" class="py-12 text-center text-slate-400">
                                    Tidak ada data pegawai yang sesuai dengan filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Matriks Bulanan -->
                <div v-if="totalMonthlyRows > 0" class="p-4 border-t border-subtle flex flex-col sm:flex-row items-center justify-between gap-3 bg-card-subtle/30">
                    <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                        <span>
                            Menampilkan <strong class="text-slate-800 dark:text-slate-200">{{ paginationStart }}</strong> - <strong class="text-slate-800 dark:text-slate-200">{{ paginationEnd }}</strong> dari <strong class="text-slate-800 dark:text-slate-200">{{ totalMonthlyRows }}</strong> pegawai
                        </span>
                        <div class="flex items-center gap-1.5 ml-2 pl-3 border-l border-subtle">
                            <span class="text-[11px]">Per halaman:</span>
                            <select
                                v-model="perPage"
                                class="text-xs font-semibold rounded-lg border border-theme bg-sidebar px-2 py-1 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 cursor-pointer"
                            >
                                <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="totalMonthlyPages > 1" class="flex items-center gap-1">
                        <button
                            type="button"
                            @click="goToPage(1)"
                            :disabled="currentPage === 1"
                            class="px-2.5 py-1.5 rounded-lg text-xs font-bold border border-theme bg-sidebar hover:bg-card-subtle disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer"
                            title="Halaman Pertama"
                        >
                            «
                        </button>
                        <button
                            type="button"
                            @click="goToPage(currentPage - 1)"
                            :disabled="currentPage === 1"
                            class="px-2.5 py-1.5 rounded-lg text-xs font-bold border border-theme bg-sidebar hover:bg-card-subtle disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer"
                            title="Sebelumnya"
                        >
                            ‹
                        </button>

                        <template v-for="(p, idx) in visiblePages" :key="idx">
                            <span v-if="p === '...'" class="px-2 py-1 text-xs text-slate-400 font-bold select-none">...</span>
                            <button
                                v-else
                                type="button"
                                @click="goToPage(p)"
                                :class="[
                                    'min-w-[32px] px-2.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer',
                                    currentPage === p
                                        ? 'bg-emerald-600 text-white shadow-xs'
                                        : 'border border-theme bg-sidebar hover:bg-card-subtle text-slate-700 dark:text-slate-300'
                                ]"
                            >
                                {{ p }}
                            </button>
                        </template>

                        <button
                            type="button"
                            @click="goToPage(currentPage + 1)"
                            :disabled="currentPage === totalMonthlyPages"
                            class="px-2.5 py-1.5 rounded-lg text-xs font-bold border border-theme bg-sidebar hover:bg-card-subtle disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer"
                            title="Berikutnya"
                        >
                            ›
                        </button>
                        <button
                            type="button"
                            @click="goToPage(totalMonthlyPages)"
                            :disabled="currentPage === totalMonthlyPages"
                            class="px-2.5 py-1.5 rounded-lg text-xs font-bold border border-theme bg-sidebar hover:bg-card-subtle disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer"
                            title="Halaman Terakhir"
                        >
                            »
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── TABEL 2: DETAIL HARIAN (Saat Tab Harian Aktif) ──────────────── -->
            <div v-else class="bg-sidebar rounded-3xl border border-theme shadow-xs overflow-hidden">
                <div class="p-4 border-b border-subtle flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <span>Detail Presensi Tanggal: {{ formattedDate }}</span>
                        </h2>
                        <p class="text-[11px] text-slate-400">
                            Menampilkan jam masuk, jam kepulangan, jarak GPS, dan foto selfie dinas luar.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <input 
                            type="date" 
                            v-model="selectedDate" 
                            @change="applyFilters"
                            class="text-xs rounded-xl border border-theme bg-card-subtle px-3 py-1.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 cursor-pointer"
                        />
                        <button
                            type="button"
                            @click="activeTab = 'monthly'; applyFilters()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 border border-emerald-200 dark:border-emerald-800 transition-colors cursor-pointer"
                        >
                            <span>← Kembali ke Matriks Bulanan</span>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-subtle bg-card-subtle text-slate-500 dark:text-slate-400 uppercase tracking-wider text-[10px]">
                                <th class="py-3 px-4">Pegawai</th>
                                <th class="py-3 px-3">Jadwal Kerja</th>
                                <th class="py-3 px-3">Jam Masuk</th>
                                <th class="py-3 px-3">Jam Pulang</th>
                                <th class="py-3 px-3">Status &amp; Persetujuan</th>
                                <th class="py-3 px-4">Bukti &amp; Keterangan</th>
                                <th class="py-3 px-3 text-center min-w-[140px]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-subtle">
                            <tr v-for="item in dailyReportData" :key="item.user_id" 
                                :class="[
                                    'transition-colors',
                                    item.approval_status === 'pending' ? 'bg-amber-50/40 dark:bg-amber-950/20 hover:bg-amber-50/70' : 'hover:bg-card-subtle/50'
                                ]"
                            >
                                <!-- Info Pegawai -->
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-emerald-100 dark:bg-emerald-950 flex items-center justify-center font-bold text-xs text-emerald-700 dark:text-emerald-400 flex-shrink-0 overflow-hidden border border-emerald-200 dark:border-emerald-800">
                                            <img v-if="item.avatar" :src="item.avatar.startsWith('http') ? item.avatar : `/storage/${item.avatar}`" class="w-full h-full object-cover" />
                                            <span v-else>{{ item.name.charAt(0).toUpperCase() }}</span>
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-800 dark:text-slate-100">{{ item.name }}</p>
                                            <p class="text-[11px] text-slate-400">
                                                {{ item.divisi || 'Tanpa Divisi' }} <span v-if="item.nip">· NIP: {{ item.nip }}</span>
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Jadwal Kerja -->
                                <td class="py-3.5 px-3">
                                    <span class="font-mono text-slate-700 dark:text-slate-300 font-semibold">{{ item.work_schedule }}</span>
                                    <span v-if="item.is_custom_schedule" class="ml-1.5 px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                        Khusus
                                    </span>
                                </td>

                                <!-- Jam Masuk -->
                                <td class="py-3.5 px-3 font-mono">
                                    <template v-if="item.time_in">
                                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ item.time_in }}</span>
                                        <span v-if="item.distance_in !== null" class="block text-[10px] text-slate-400">
                                            📍 {{ item.distance_in }}m
                                        </span>
                                    </template>
                                    <template v-else>
                                        <span class="text-slate-400">-</span>
                                    </template>
                                </td>

                                <!-- Jam Pulang -->
                                <td class="py-3.5 px-3 font-mono">
                                    <template v-if="item.time_out">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ item.time_out }}</span>
                                            <span v-if="item.is_overtime" class="px-1.5 py-0.5 rounded text-[9px] font-black bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200 font-sans" :title="item.overtime_activity || 'Lembur'">
                                                ⚡ {{ item.overtime_minutes }}m
                                            </span>
                                        </div>
                                        <span v-if="item.distance_out !== null" class="block text-[10px] text-slate-400">
                                            📍 {{ item.distance_out }}m
                                        </span>
                                    </template>
                                    <template v-else>
                                        <span class="text-slate-400">-</span>
                                    </template>
                                </td>

                                <!-- Status & Approval Badge -->
                                <td class="py-3.5 px-3">
                                    <div class="flex flex-col gap-1 items-start">
                                        <div class="flex flex-wrap items-center gap-1">
                                            <span 
                                                :class="[
                                                    'px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider',
                                                    item.status === 'hadir' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' :
                                                    item.status === 'terlambat' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' :
                                                    item.status === 'dinas_luar' ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300' :
                                                    item.status === 'izin' ? 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300' :
                                                    item.status === 'sakit' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' :
                                                    'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300'
                                                ]"
                                            >
                                                {{ item.status === 'belum_hadir' ? 'Belum Hadir' : item.status.replace('_', ' ') }}
                                            </span>

                                            <span 
                                                v-if="item.is_early_departure" 
                                                class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300"
                                                title="Pulang mendahului jadwal pulang kerja"
                                            >
                                                Pulang Cepat
                                            </span>

                                            <!-- Flag Khusus Permohonan Izin / Sakit / Pulang Cepat -->
                                            <span 
                                                v-if="hasPermitOrEarlyDeparture(item)"
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300 border border-purple-300 dark:border-purple-700 shadow-2xs"
                                                :title="`Pegawai mengajukan: ${getPermitTypeLabel(item)}`"
                                            >
                                                <span>🚩</span>
                                                <span>{{ getPermitTypeLabel(item) }}</span>
                                            </span>
                                        </div>

                                        <!-- Status Persetujuan Admin (Hanya jika mengajukan izin/pulang cepat) -->
                                        <div v-if="item.attendance_id && hasPermitOrEarlyDeparture(item)">
                                            <span 
                                                v-if="item.approval_status === 'pending'"
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 animate-pulse border border-amber-300"
                                            >
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500" />
                                                Menunggu Review
                                            </span>
                                            <span 
                                                v-else-if="item.approval_status === 'approved'"
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300"
                                            >
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                                                Disetujui
                                            </span>
                                            <span 
                                                v-else-if="item.approval_status === 'rejected'"
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border border-rose-300"
                                            >
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500" />
                                                Ditolak
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Foto & Keterangan -->
                                <td class="py-3.5 px-4">
                                    <div class="space-y-1.5">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <!-- Thumbnail Foto Masuk jika ada -->
                                            <button 
                                                v-if="item.photo_in" 
                                                type="button" 
                                                @click="openCellDetail(item, { date: selectedDate, day: '' }, item)"
                                                class="w-7 h-7 rounded-lg overflow-hidden border border-theme hover:scale-110 transition-transform flex-shrink-0 cursor-pointer"
                                                title="Lihat Foto Masuk (Selfie)"
                                            >
                                                <img :src="`/storage/${item.photo_in}`" class="w-full h-full object-cover" />
                                            </button>

                                            <!-- Thumbnail Dokumen Lampiran Izin/Sakit jika ada -->
                                            <a 
                                                v-if="item.attachment" 
                                                :href="`/storage/${item.attachment}`" 
                                                target="_blank"
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-purple-50 dark:bg-purple-950/60 border border-purple-200 dark:border-purple-800 text-[10px] font-bold text-purple-700 dark:text-purple-300 hover:underline"
                                                title="Buka lampiran berkas"
                                            >
                                                <span>📎 Lampiran</span>
                                            </a>
                                        </div>

                                        <!-- Keterangan Catatan Pegawai -->
                                        <div class="text-[11px] text-slate-700 dark:text-slate-300 max-w-xs">
                                            <span>{{ item.notes || '-' }}</span>
                                        </div>

                                        <!-- Catatan Alasan Keterlambatan & Foto Terlambat -->
                                        <div v-if="item.late_reason || item.status === 'terlambat'" class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-[10px] text-amber-800 dark:text-amber-200 space-y-1">
                                            <div class="flex items-center gap-1 font-bold uppercase text-[9px] text-amber-700 dark:text-amber-300">
                                                <span>⚠️</span>
                                                <span>Alasan Terlambat:</span>
                                            </div>
                                            <p class="font-medium text-amber-900 dark:text-amber-100">
                                                "{{ item.late_reason || 'Tidak ada catatan alasan terlambat' }}"
                                            </p>
                                            <div v-if="item.late_photo" class="pt-0.5">
                                                <a 
                                                    :href="`/storage/${item.late_photo}`" 
                                                    target="_blank" 
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-100 dark:bg-amber-900/60 border border-amber-300 dark:border-amber-700 text-[10px] font-bold text-amber-800 dark:text-amber-200 hover:underline"
                                                    title="Lihat foto bukti keterlambatan"
                                                >
                                                    <span>📷 Bukti Foto Terlambat</span>
                                                </a>
                                            </div>
                                        </div>

                                        <!-- Keterangan Pulang Mendahului -->
                                        <div v-if="item.is_early_departure && item.early_departure_reason" class="text-[10px] text-purple-600 dark:text-purple-400 font-medium">
                                            Alasan Pulang Cepat: "{{ item.early_departure_reason }}"
                                        </div>

                                        <!-- Alasan Penolakan jika permohonan ditolak -->
                                        <div v-if="item.approval_status === 'rejected' && item.rejection_note" class="p-1.5 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-[10px] text-rose-700 dark:text-rose-300">
                                            <span class="font-bold block uppercase text-[9px]">Alasan Penolakan:</span>
                                            "{{ item.rejection_note }}"
                                        </div>

                                        <!-- Lembur -->
                                        <div v-if="item.is_overtime && item.overtime_activity" class="text-[10px] text-amber-600 dark:text-amber-400 font-medium truncate" :title="item.overtime_activity">
                                            ⚡ Lembur: {{ item.overtime_activity }}
                                        </div>
                                    </div>
                                </td>

                                <!-- Aksi Persetujuan & Edit Superadmin -->
                                <td class="py-3.5 px-3 text-center">
                                    <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                        <!-- Tombol Cepat Setujui & Tolak HANYA jika Pegawai Mengajukan Izin/Pulang Cepat & Status Pending -->
                                        <template v-if="hasPermitOrEarlyDeparture(item) && item.approval_status === 'pending'">
                                            <button 
                                                type="button" 
                                                @click="handleQuickApprove(item)"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold shadow-2xs transition-all active:scale-95 cursor-pointer"
                                                title="Setujui permohonan ini"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                </svg>
                                                <span>Setujui</span>
                                            </button>

                                            <button 
                                                type="button" 
                                                @click="openRejectModal(item)"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-[11px] font-bold shadow-2xs transition-all active:scale-95 cursor-pointer"
                                                title="Tolak permohonan ini dengan keterangan"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                                <span>Tolak</span>
                                            </button>
                                        </template>

                                        <!-- Jika sudah Ditolak, beri opsi untuk ubah status jika diperlukan -->
                                        <template v-else-if="hasPermitOrEarlyDeparture(item) && item.approval_status === 'rejected'">
                                            <button 
                                                type="button" 
                                                @click="openRejectModal(item)"
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300 hover:bg-rose-100 text-[10px] font-bold border border-rose-200 dark:border-rose-800 cursor-pointer"
                                                title="Ubah alasan penolakan"
                                            >
                                                <span>Ubah Alasan</span>
                                            </button>
                                        </template>

                                        <!-- Tombol Edit Presensi Masuk & Pulang (Superadmin) -->
                                        <button 
                                            v-if="canEditAttendance"
                                            type="button" 
                                            @click="openCellDetail(item, { date: selectedDate, day: '' }, item)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-card-subtle hover:bg-emerald-50 dark:hover:bg-emerald-950/60 text-slate-700 dark:text-slate-300 text-[11px] font-bold border border-theme transition-all cursor-pointer shadow-2xs hover:shadow-xs active:scale-95"
                                            title="Buka rincian & koreksi jam presensi (Superadmin)"
                                        >
                                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            <span>Detail / Edit</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!dailyReportData || dailyReportData.length === 0">
                                <td :colspan="canEditAttendance ? 7 : 6" class="py-12 text-center text-slate-400">
                                    Tidak ada data pegawai yang sesuai dengan filter pada tanggal ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── MODAL 2: DETAIL & EDIT PRESENSI (KHUSUS SUPERADMIN) ────────── -->
            <div 
                v-if="selectedDetailModal" 
                class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/75 backdrop-blur-xs overflow-y-auto"
                @click="closeCellDetail"
            >
                <div class="relative bg-sidebar max-w-lg w-full rounded-3xl overflow-hidden shadow-2xl border border-theme my-8" @click.stop>
                    
                    <!-- Modal Header -->
                    <div class="p-5 border-b border-subtle flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-black text-slate-800 dark:text-slate-100">{{ selectedDetailModal.userName }}</h3>
                                <span 
                                    v-if="canEditAttendance" 
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300 border border-purple-200 dark:border-purple-800"
                                >
                                    <span>👑 Super Admin</span>
                                </span>
                            </div>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Tanggal: <strong class="text-slate-700 dark:text-slate-300">{{ selectedDetailModal.date }}</strong> 
                                <span v-if="selectedDetailModal.userDivisi">· Divisi: {{ selectedDetailModal.userDivisi }}</span>
                            </p>
                        </div>
                        <button 
                            type="button" 
                            @click="closeCellDetail"
                            class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- ══════════ TAMPILAN KHUSUS SUPERADMIN: FORM EDIT ══════════ -->
                    <form v-if="canEditAttendance" @submit.prevent="submitEditAttendance">
                        <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">

                            <!-- Flag Khusus Permohonan Izin / Sakit / Pulang Cepat -->
                            <div v-if="hasPermitOrEarlyDeparture(selectedDetailModal.record)" class="p-3.5 rounded-2xl bg-purple-50/80 dark:bg-purple-950/50 border border-purple-200 dark:border-purple-800 flex items-center justify-between gap-2 shadow-2xs">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">🚩</span>
                                    <div>
                                        <p class="text-xs font-black text-purple-900 dark:text-purple-200">
                                            Pengajuan: {{ getPermitTypeLabel(selectedDetailModal.record) }}
                                        </p>
                                        <p class="text-[10px] text-purple-600 dark:text-purple-400">
                                            Pegawai ini mengajukan permohonan izin / pulang cepat.
                                        </p>
                                    </div>
                                </div>
                                <span :class="[
                                    'px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-wider',
                                    selectedDetailModal.record?.approval_status === 'approved' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' :
                                    selectedDetailModal.record?.approval_status === 'rejected' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' :
                                    'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 animate-pulse'
                                ]">
                                    {{ selectedDetailModal.record?.approval_status === 'approved' ? 'Disetujui' : selectedDetailModal.record?.approval_status === 'rejected' ? 'Ditolak' : 'Menunggu Review' }}
                                </span>
                            </div>

                            <!-- Jadwal Kerja Pegawai -->
                            <div class="flex items-center justify-between p-3 rounded-2xl bg-card-subtle border border-theme/60 text-xs">
                                <div>
                                    <span class="text-slate-400 font-bold block text-[10px] uppercase">Jadwal Kerja Efektif</span>
                                    <span class="font-mono font-bold text-slate-700 dark:text-slate-200">{{ selectedDetailModal.schedule }}</span>
                                </div>
                                <span v-if="selectedDetailModal.record?.is_pulang_cepat" class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300">
                                    Pulang Cepat
                                </span>
                            </div>

                            <!-- Input Jam Masuk & Jam Pulang -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">
                                        Jam Masuk
                                    </label>
                                    <div class="relative">
                                        <input 
                                            type="time" 
                                            v-model="editForm.time_in"
                                            class="w-full text-sm font-mono font-bold rounded-xl border border-theme bg-card-subtle px-3 py-2 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500"
                                        />
                                    </div>
                                    <span v-if="selectedDetailModal.record?.distance_in != null" class="block text-[10px] text-emerald-600 mt-1 font-semibold">
                                        📍 Masuk: {{ selectedDetailModal.record?.distance_in }}m
                                    </span>
                                    <p v-if="editForm.errors.time_in" class="text-[10px] text-rose-500 mt-0.5">{{ editForm.errors.time_in }}</p>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">
                                        Jam Pulang
                                    </label>
                                    <div class="relative">
                                        <input 
                                            type="time" 
                                            v-model="editForm.time_out"
                                            class="w-full text-sm font-mono font-bold rounded-xl border border-theme bg-card-subtle px-3 py-2 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500"
                                        />
                                    </div>
                                    <span v-if="selectedDetailModal.record?.distance_out != null" class="block text-[10px] text-rose-600 mt-1 font-semibold">
                                        📍 Pulang: {{ selectedDetailModal.record?.distance_out }}m
                                    </span>
                                    <p v-if="editForm.errors.time_out" class="text-[10px] text-rose-500 mt-0.5">{{ editForm.errors.time_out }}</p>
                                </div>
                            </div>

                            <!-- Pilihan Status Kehadiran -->
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">
                                    Status Kehadiran
                                </label>
                                <select 
                                    v-model="editForm.status"
                                    class="w-full text-xs font-bold rounded-xl border border-theme bg-card-subtle px-3 py-2.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500"
                                    required
                                >
                                    <option value="hadir">Hadir (Tepat Waktu)</option>
                                    <option value="terlambat">Terlambat</option>
                                    <option value="dinas_luar">Dinas / Tugas Luar</option>
                                    <option value="izin">Izin</option>
                                    <option value="sakit">Sakit</option>
                                    <option value="alpa">Alpa (Tanpa Keterangan)</option>
                                </select>
                                <p v-if="editForm.errors.status" class="text-[10px] text-rose-500 mt-0.5">{{ editForm.errors.status }}</p>
                            </div>

                            <!-- Catatan / Keterangan Koreksi -->
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">
                                    Catatan / Keterangan
                                </label>
                                <textarea 
                                    v-model="editForm.notes"
                                    rows="2"
                                    class="w-full text-xs rounded-xl border border-theme bg-card-subtle px-3 py-2 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500"
                                    placeholder="Contoh: Koreksi jam masuk oleh Superadmin / Tugas lapangan..."
                                ></textarea>
                                <p v-if="editForm.errors.notes" class="text-[10px] text-rose-500 mt-0.5">{{ editForm.errors.notes }}</p>
                            </div>

                            <!-- Catatan Alasan Keterlambatan & Foto Terlambat -->
                            <div v-if="selectedDetailModal.record?.late_reason || selectedDetailModal.record?.status === 'terlambat'" class="p-3.5 rounded-2xl bg-amber-50/70 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 space-y-2">
                                <div class="flex items-center gap-1 font-bold uppercase text-[10px] text-amber-700 dark:text-amber-300">
                                    <span>⚠️</span>
                                    <span>Alasan Keterlambatan Pegawai:</span>
                                </div>
                                <p class="text-xs text-amber-900 dark:text-amber-100 font-medium">
                                    "{{ selectedDetailModal.record?.late_reason || 'Tidak ada catatan alasan terlambat' }}"
                                </p>
                                <div v-if="selectedDetailModal.record?.late_photo" class="mt-2">
                                    <p class="text-[10px] font-bold uppercase text-amber-600 dark:text-amber-400 mb-1">Bukti Foto Keterlambatan:</p>
                                    <div class="rounded-xl overflow-hidden border border-amber-300 dark:border-amber-700 bg-black/20 flex items-center justify-center p-1 max-w-[220px]">
                                        <a :href="`/storage/${selectedDetailModal.record?.late_photo}`" target="_blank" title="Lihat foto bukti keterlambatan">
                                            <img :src="`/storage/${selectedDetailModal.record?.late_photo}`" class="max-h-40 w-auto rounded-lg object-contain hover:scale-105 transition-transform" />
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Foto Selfie (jika sebelumnya ada dinas luar) -->
                            <div v-if="selectedDetailModal.record?.photo_in" class="p-3 rounded-2xl bg-card-subtle border border-theme/60 space-y-1.5">
                                <p class="text-[10px] font-bold uppercase text-slate-400">Bukti Foto Selfie Masuk:</p>
                                <div class="rounded-xl overflow-hidden border border-theme bg-black/20 flex items-center justify-center p-1">
                                    <img :src="`/storage/${selectedDetailModal.record?.photo_in}`" class="max-h-40 w-auto rounded-lg object-contain" />
                                </div>
                            </div>

                            <!-- Box Review Persetujuan Izin / Pulang Cepat (Hanya jika mengajukan) -->
                            <div 
                                v-if="hasPermitOrEarlyDeparture(selectedDetailModal.record)"
                                class="p-3.5 rounded-2xl border transition-all"
                                :class="[
                                    selectedDetailModal.record?.approval_status === 'pending'
                                        ? 'bg-amber-50/60 dark:bg-amber-950/30 border-amber-300 dark:border-amber-800'
                                        : selectedDetailModal.record?.approval_status === 'approved'
                                            ? 'bg-emerald-50/60 dark:bg-emerald-950/30 border-emerald-300 dark:border-emerald-800'
                                            : 'bg-rose-50/60 dark:bg-rose-950/30 border-rose-300 dark:border-rose-800'
                                ]"
                            >
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <span class="text-xs font-black text-slate-800 dark:text-slate-100">
                                        Persetujuan Izin / Pulang Cepat
                                    </span>
                                    <span 
                                        :class="[
                                            'px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider',
                                            selectedDetailModal.record?.approval_status === 'approved' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' :
                                            selectedDetailModal.record?.approval_status === 'rejected' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' :
                                            'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 animate-pulse'
                                        ]"
                                    >
                                        {{ selectedDetailModal.record?.approval_status === 'approved' ? 'Disetujui' : selectedDetailModal.record?.approval_status === 'rejected' ? 'Ditolak' : 'Menunggu Review' }}
                                    </span>
                                </div>

                                <div v-if="selectedDetailModal.record?.attachment" class="mb-2">
                                    <a 
                                        :href="`/storage/${selectedDetailModal.record?.attachment}`" 
                                        target="_blank"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white dark:bg-slate-800 border border-theme text-xs font-bold text-purple-600 dark:text-purple-400 hover:underline"
                                    >
                                        📎 Buka Berkas Lampiran Surat Izin / Sakit
                                    </a>
                                </div>

                                <div v-if="selectedDetailModal.record?.is_early_departure && selectedDetailModal.record?.early_departure_reason" class="text-xs text-purple-700 dark:text-purple-300 mb-2">
                                    <span class="font-bold block text-[10px] uppercase">Alasan Pulang Mendahului:</span>
                                    "{{ selectedDetailModal.record?.early_departure_reason }}"
                                </div>

                                <div v-if="selectedDetailModal.record?.approval_status === 'rejected' && selectedDetailModal.record?.rejection_note" class="p-2 rounded-xl bg-rose-100/80 dark:bg-rose-950/60 border border-rose-300 text-xs text-rose-800 dark:text-rose-200 mb-2">
                                    <span class="font-bold block uppercase text-[10px]">Alasan Penolakan Admin:</span>
                                    "{{ selectedDetailModal.record?.rejection_note }}"
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex items-center gap-2 mt-3 pt-2 border-t border-theme/40">
                                    <button 
                                        type="button" 
                                        @click="handleQuickApprove(selectedDetailModal)"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold cursor-pointer transition-all active:scale-95"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>Setujui Permohonan</span>
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="openRejectModal(selectedDetailModal)"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold cursor-pointer transition-all active:scale-95"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        <span>{{ selectedDetailModal.record?.approval_status === 'rejected' ? 'Ubah Alasan Tolak' : 'Tolak Permohonan' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer Superadmin -->
                        <div class="p-4 border-t border-subtle bg-card-subtle flex items-center justify-between gap-2">
                            <div>
                                <button 
                                    v-if="editForm.id" 
                                    type="button" 
                                    @click="deleteAttendanceRecord"
                                    :disabled="isDeletingAttendance || editForm.processing"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors cursor-pointer disabled:opacity-50"
                                    title="Hapus record presensi ini"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    <span>Hapus</span>
                                </button>
                            </div>

                            <div class="flex items-center gap-2">
                                <button 
                                    type="button" 
                                    @click="closeCellDetail"
                                    class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200/50 transition-colors cursor-pointer"
                                >
                                    Batal
                                </button>
                                <button 
                                    type="submit" 
                                    :disabled="editForm.processing"
                                    class="inline-flex items-center gap-1.5 px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-md hover:shadow-emerald-600/20 active:scale-95 transition-all cursor-pointer disabled:opacity-50"
                                >
                                    <svg v-if="editForm.processing" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Simpan Presensi</span>
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- ══════════ TAMPILAN NON-SUPERADMIN: READ ONLY ══════════ -->
                    <div v-else>
                        <div class="p-5 space-y-4">
                            <!-- Flag Khusus Permohonan Izin / Sakit / Pulang Cepat -->
                            <div v-if="hasPermitOrEarlyDeparture(selectedDetailModal.record)" class="p-3.5 rounded-2xl bg-purple-50/80 dark:bg-purple-950/50 border border-purple-200 dark:border-purple-800 flex items-center justify-between gap-2 shadow-2xs">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">🚩</span>
                                    <div>
                                        <p class="text-xs font-black text-purple-900 dark:text-purple-200">
                                            Pengajuan: {{ getPermitTypeLabel(selectedDetailModal.record) }}
                                        </p>
                                        <p class="text-[10px] text-purple-600 dark:text-purple-400">
                                            Pegawai ini mengajukan permohonan izin / pulang cepat.
                                        </p>
                                    </div>
                                </div>
                                <span :class="[
                                    'px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-wider',
                                    selectedDetailModal.record?.approval_status === 'approved' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' :
                                    selectedDetailModal.record?.approval_status === 'rejected' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' :
                                    'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 animate-pulse'
                                ]">
                                    {{ selectedDetailModal.record?.approval_status === 'approved' ? 'Disetujui' : selectedDetailModal.record?.approval_status === 'rejected' ? 'Ditolak' : 'Menunggu Review' }}
                                </span>
                            </div>

                            <!-- Status Badge -->
                            <div class="flex items-center justify-between p-3 rounded-2xl bg-card-subtle border border-theme/60">
                                <span class="text-xs text-slate-500 font-bold">Status Kehadiran</span>
                                <div class="flex items-center gap-1.5">
                                    <span 
                                        :class="[
                                            'px-3 py-1 rounded-full text-xs font-black uppercase',
                                            selectedDetailModal.record?.status === 'hadir' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' :
                                            selectedDetailModal.record?.status === 'terlambat' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' :
                                            selectedDetailModal.record?.status === 'dinas_luar' ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300' :
                                            selectedDetailModal.record?.status === 'izin' ? 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300' :
                                            selectedDetailModal.record?.status === 'sakit' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' :
                                            'bg-rose-100 text-rose-700'
                                        ]"
                                    >
                                        {{ (selectedDetailModal.record?.status || 'belum_hadir').replace('_', ' ') }}
                                    </span>
                                    <span 
                                        v-if="selectedDetailModal.record?.is_pulang_cepat || selectedDetailModal.record?.is_early_departure" 
                                        class="px-2.5 py-1 rounded-full text-xs font-black uppercase bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300"
                                    >
                                        Pulang Cepat
                                    </span>
                                </div>
                            </div>

                            <!-- Jam Masuk & Pulang -->
                            <div class="grid grid-cols-2 gap-3">
                                <div class="p-3.5 rounded-2xl bg-card-subtle border border-theme/60">
                                    <p class="text-[10px] font-bold uppercase text-slate-400">Jam Masuk</p>
                                    <p class="text-base font-black text-slate-800 dark:text-slate-100 mt-0.5">
                                        {{ selectedDetailModal.record?.time_in || '--:--' }}
                                    </p>
                                    <p v-if="selectedDetailModal.record?.distance_in != null" class="text-[10px] text-emerald-600 mt-1 font-semibold">
                                        📍 Jarak: {{ selectedDetailModal.record?.distance_in }}m
                                    </p>
                                </div>
                                <div class="p-3.5 rounded-2xl bg-card-subtle border border-theme/60">
                                    <p class="text-[10px] font-bold uppercase text-slate-400">Jam Pulang</p>
                                    <p class="text-base font-black text-slate-800 dark:text-slate-100 mt-0.5">
                                        {{ selectedDetailModal.record?.time_out || '--:--' }}
                                    </p>
                                    <p v-if="selectedDetailModal.record?.distance_out != null" class="text-[10px] text-rose-600 mt-1 font-semibold">
                                        📍 Jarak: {{ selectedDetailModal.record?.distance_out }}m
                                    </p>
                                </div>
                            </div>

                            <!-- Foto Selfie jika ada -->
                            <div v-if="selectedDetailModal.record?.photo_in" class="space-y-1.5">
                                <p class="text-xs font-bold text-slate-600 dark:text-slate-300">Foto Selfie Bukti Presensi (Luar Radius):</p>
                                <div class="rounded-2xl overflow-hidden border border-theme bg-black/30 flex items-center justify-center p-2">
                                    <img :src="`/storage/${selectedDetailModal.record?.photo_in}`" class="max-h-56 w-auto rounded-xl object-contain" />
                                </div>
                            </div>

                            <!-- Dokumen Lampiran Izin / Sakit jika ada -->
                            <div v-if="selectedDetailModal.record?.attachment" class="p-3 rounded-2xl bg-purple-50/60 dark:bg-purple-950/30 border border-purple-200 dark:border-purple-800">
                                <p class="text-[10px] font-bold uppercase text-purple-600 mb-1">Dokumen Lampiran Izin/Sakit:</p>
                                <a 
                                    :href="`/storage/${selectedDetailModal.record?.attachment}`" 
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold transition-colors"
                                >
                                    <span>📎 Unduh / Buka Dokumen Berkas</span>
                                </a>
                            </div>

                            <!-- Catatan / Alasan Pegawai -->
                            <div v-if="selectedDetailModal.record?.notes" class="p-3 rounded-2xl bg-blue-50/60 dark:bg-blue-950/30 border border-blue-100 dark:border-blue-900/40">
                                <p class="text-[10px] font-bold uppercase text-blue-600 mb-0.5">Catatan / Keterangan Pegawai:</p>
                                <p class="text-xs text-slate-700 dark:text-slate-300">{{ selectedDetailModal.record?.notes }}</p>
                            </div>

                            <!-- Catatan Alasan Keterlambatan & Foto Terlambat -->
                            <div v-if="selectedDetailModal.record?.late_reason || selectedDetailModal.record?.status === 'terlambat'" class="p-3.5 rounded-2xl bg-amber-50/70 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 space-y-2">
                                <div class="flex items-center gap-1 font-bold uppercase text-[10px] text-amber-700 dark:text-amber-300">
                                    <span>⚠️</span>
                                    <span>Alasan Keterlambatan Pegawai:</span>
                                </div>
                                <p class="text-xs text-amber-900 dark:text-amber-100 font-medium">
                                    "{{ selectedDetailModal.record?.late_reason || 'Tidak ada catatan alasan terlambat' }}"
                                </p>
                                <div v-if="selectedDetailModal.record?.late_photo" class="mt-2">
                                    <p class="text-[10px] font-bold uppercase text-amber-600 dark:text-amber-400 mb-1">Bukti Foto Keterlambatan:</p>
                                    <div class="rounded-xl overflow-hidden border border-amber-300 dark:border-amber-700 bg-black/20 flex items-center justify-center p-1 max-w-[220px]">
                                        <a :href="`/storage/${selectedDetailModal.record?.late_photo}`" target="_blank" title="Lihat foto bukti keterlambatan">
                                            <img :src="`/storage/${selectedDetailModal.record?.late_photo}`" class="max-h-40 w-auto rounded-lg object-contain hover:scale-105 transition-transform" />
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Keterangan Pulang Cepat -->
                            <div v-if="selectedDetailModal.record?.is_early_departure && selectedDetailModal.record?.early_departure_reason" class="p-3 rounded-2xl bg-purple-50/60 dark:bg-purple-950/30 border border-purple-200 dark:border-purple-800">
                                <p class="text-[10px] font-bold uppercase text-purple-600 mb-0.5">Alasan Pulang Mendahului:</p>
                                <p class="text-xs text-purple-800 dark:text-purple-200">{{ selectedDetailModal.record?.early_departure_reason }}</p>
                            </div>

                            <!-- Box Persetujuan Admin (HANYA tampil jika pegawai mengajukan izin/pulang cepat) -->
                            <div 
                                v-if="hasPermitOrEarlyDeparture(selectedDetailModal.record)"
                                class="p-3.5 rounded-2xl border transition-all"
                                :class="[
                                    selectedDetailModal.record?.approval_status === 'pending'
                                        ? 'bg-amber-50/60 dark:bg-amber-950/30 border-amber-300 dark:border-amber-800'
                                        : selectedDetailModal.record?.approval_status === 'approved'
                                            ? 'bg-emerald-50/60 dark:bg-emerald-950/30 border-emerald-300 dark:border-emerald-800'
                                            : 'bg-rose-50/60 dark:bg-rose-950/30 border-rose-300 dark:border-rose-800'
                                ]"
                            >
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <span class="text-xs font-black text-slate-800 dark:text-slate-100">
                                        Persetujuan Izin / Pulang Cepat
                                    </span>
                                    <span 
                                        :class="[
                                            'px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider',
                                            selectedDetailModal.record?.approval_status === 'approved' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' :
                                            selectedDetailModal.record?.approval_status === 'rejected' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' :
                                            'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 animate-pulse'
                                        ]"
                                    >
                                        {{ selectedDetailModal.record?.approval_status === 'approved' ? 'Disetujui' : selectedDetailModal.record?.approval_status === 'rejected' ? 'Ditolak' : 'Menunggu Review' }}
                                    </span>
                                </div>

                                <div v-if="selectedDetailModal.record?.approval_status === 'rejected' && selectedDetailModal.record?.rejection_note" class="p-2 rounded-xl bg-rose-100/80 dark:bg-rose-950/60 border border-rose-300 text-xs text-rose-800 dark:text-rose-200 mb-2">
                                    <span class="font-bold block uppercase text-[10px]">Alasan Penolakan:</span>
                                    "{{ selectedDetailModal.record?.rejection_note }}"
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex items-center gap-2 mt-3 pt-2 border-t border-theme/40">
                                    <button 
                                        type="button" 
                                        @click="handleQuickApprove(selectedDetailModal)"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold cursor-pointer transition-all active:scale-95"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>Setujui Permohonan</span>
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="openRejectModal(selectedDetailModal)"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold cursor-pointer transition-all active:scale-95"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        <span>{{ selectedDetailModal.record?.approval_status === 'rejected' ? 'Ubah Alasan Tolak' : 'Tolak Permohonan' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 border-t border-subtle bg-card-subtle text-right">
                            <button 
                                type="button" 
                                @click="closeCellDetail"
                                class="px-5 py-2 rounded-xl bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold hover:bg-slate-300 transition-colors cursor-pointer"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── MODAL 3: PENOLAKAN PERMOHONAN DENGAN KETERANGAN ALASAN WAJIB ── -->
            <div 
                v-if="isRejectModalOpen" 
                class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/75 backdrop-blur-xs overflow-y-auto"
                @click="isRejectModalOpen = false"
            >
                <div class="relative bg-sidebar max-w-lg w-full rounded-3xl overflow-hidden shadow-2xl border border-theme my-8" @click.stop>
                    <div class="p-5 border-b border-subtle flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-rose-100 dark:bg-rose-950 flex items-center justify-center text-rose-600 border border-rose-200 dark:border-rose-900">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-black text-slate-800 dark:text-slate-100">Tolak Permohonan</h3>
                                <p class="text-xs text-slate-400">Pegawai: <strong class="text-slate-700 dark:text-slate-200">{{ selectedRejectItem?.name || selectedRejectItem?.userName }}</strong></p>
                            </div>
                        </div>
                        <button 
                            type="button" 
                            @click="isRejectModalOpen = false"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitRejectApproval">
                        <div class="p-5 space-y-4">
                            <div class="p-3.5 rounded-2xl bg-rose-50/70 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 text-xs text-rose-800 dark:text-rose-200 space-y-1">
                                <p class="font-bold flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <span>Pemberitahuan Wajib:</span>
                                </p>
                                <p>Silakan cantumkan alasan mengapa permohonan izin / sakit / pulang mendahului ini ditolak agar dapat dibaca oleh pegawai terkait.</p>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1.5">
                                    Keterangan Alasan Penolakan <span class="text-rose-500">*</span>
                                </label>
                                <textarea 
                                    v-model="approvalForm.rejection_note" 
                                    rows="4"
                                    required
                                    placeholder="Contoh: Dokumen surat sakit kurang lengkap / Jadwal rapat penting belum selesai..."
                                    class="w-full text-xs rounded-xl border border-theme bg-card-subtle px-3 py-2.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-rose-500 focus:border-rose-500"
                                ></textarea>
                                <p v-if="approvalForm.errors.rejection_note" class="text-[10px] text-rose-500 mt-1 font-semibold">
                                    {{ approvalForm.errors.rejection_note }}
                                </p>
                            </div>
                        </div>

                        <div class="p-4 border-t border-subtle bg-card-subtle flex items-center justify-end gap-2">
                            <button 
                                type="button" 
                                @click="isRejectModalOpen = false"
                                class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200/50 transition-colors cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="approvalForm.processing || !approvalForm.rejection_note.trim()"
                                class="inline-flex items-center gap-1.5 px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-md hover:shadow-rose-600/20 active:scale-95 transition-all cursor-pointer disabled:opacity-50"
                            >
                                <svg v-if="approvalForm.processing" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <span>Konfirmasi Penolakan</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
