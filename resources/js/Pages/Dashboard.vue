<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import IslamicWidget from '@/Components/IslamicWidget.vue'
import PageContainer from '@/Components/UI/PageContainer.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import Badge from '@/Components/UI/Badge.vue'
import Button from '@/Components/UI/Button.vue'
import Modal from '@/Components/UI/Modal.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref, onMounted, onUnmounted } from 'vue'

// Import Chart.js components
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    RadialLinearScale,
    RadarController,
    Title,
    Tooltip,
    Legend,
    Filler
} from 'chart.js'
import { Line as LineChart, Bar as BarChart, Radar as RadarChart } from 'vue-chartjs'
import { useTheme } from '@/Composables/useTheme'
import { useIslamicData } from '@/Composables/useIslamicData'

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    RadialLinearScale,
    RadarController,
    Title,
    Tooltip,
    Legend,
    Filler
)

const { activeTheme } = useTheme()
const page = usePage()
const { dailyHadith } = useIslamicData()

const featureHabitActive = computed(() => {
    const val = page.props.global_settings?.feature_habit
    return val === undefined || val === '1' || val === 'true' || val === true
})

const props = defineProps({
    skorHariIni:              { type: Number,  default: 0 },
    skorMaksimalHariIni:      { type: Number,  default: 100 },
    skorBulanIni:             { type: Number,  default: 0 },
    skorMaksimalBulanIni:     { type: Number,  default: 0 },
    skorBulanDipilih:         { type: Number,  default: null },
    skorMaksimalBulanDipilih: { type: Number,  default: null },
    targetSkorMinimal:        { type: Number,  default: 0 },
    targetBulanan:            { type: Number,  default: 0 },
    adminTargetBulanan:       { type: Number,  default: 0 },
    adminTargetSkorMinimal:   { type: Number,  default: 0 },
    isPersonalTarget:         { type: Boolean, default: false },
    isSedangHaid:             { type: Boolean, default: false },
    dailyChartData:           { type: Array,   default: () => [] },
    monthlyChartData:         { type: Array,   default: () => [] },
    semester:                 { type: String,  default: '' },
    tahun:                    { type: Number,  default: new Date().getFullYear() },
    leaderboard:              { type: Array,   default: () => [] },
    isTrackingOther:          { type: Boolean, default: false },
    targetUser:               { type: Object,  default: () => ({}) },
    userBadges:               { type: Array,   default: () => [] },
    insightMessage:           { type: String,  default: null },
    pegawaiList:              { type: Array,   default: () => [] },
    missedDates:              { type: Array,   default: () => [] },
    announcement:             { type: String,  default: null },
    habitAnalytics:           { type: Object,  default: () => ({}) },
    radarChartDataBackend:    { type: Object,  default: () => ({}) },
    hasClockedInToday:        { type: Boolean, default: true },
    shouldShowPresensiNotif:  { type: Boolean, default: false },
    featurePresensiActive:    { type: Boolean, default: false },
    selectedMonth:            { type: Number,  default: new Date().getMonth() + 1 },
    selectedYear:             { type: Number,  default: new Date().getFullYear() },
    presensiBulanIni:         { type: Array,   default: () => [] },
    semesterPresensiSummary:  { type: Object,  default: () => ({ tepat_waktu: 0, terlambat: 0, izin: 0 }) },
})

// --- Modal State ---
const showMissedDatesModal = ref(false)
const showPresensiModal = ref(false)
const showMobileWidget = ref(false)
const showDailyChart = ref(false)

const currentMonth = ref(props.selectedMonth)
const currentYear = ref(props.selectedYear)

const changeMonth = () => {
    router.get(route('dashboard'), { month: currentMonth.value, year: currentYear.value }, {
        preserveState: true,
        preserveScroll: true
    })
}

const monthNamesOptions = [
    { value: 1, label: 'Januari' }, { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' }, { value: 4, label: 'April' },
    { value: 5, label: 'Mei' }, { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' }, { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' }, { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' }, { value: 12, label: 'Desember' }
]

const selectedMonthLabel = computed(() => {
    const found = monthNamesOptions.find(m => m.value === Number(currentMonth.value))
    return found ? found.label : ''
})

const totalSkorBulanDipilih = computed(() => {
    if (props.skorBulanDipilih !== undefined && props.skorBulanDipilih !== null) {
        return props.skorBulanDipilih
    }
    return (props.dailyChartData || []).reduce((acc, curr) => acc + (Number(curr.skor) || 0), 0)
})

const skorMaksimalBulanDipilihComputed = computed(() => {
    if (props.skorMaksimalBulanDipilih) {
        return props.skorMaksimalBulanDipilih
    }
    const days = (props.dailyChartData || []).length || 30
    return (props.skorMaksimalHariIni || 0) * days
})

const persentaseBulanDipilih = computed(() => {
    const max = skorMaksimalBulanDipilihComputed.value
    if (!max || max <= 0) return 0
    return Math.round((totalSkorBulanDipilih.value / max) * 100 * 10) / 10
})

const persentaseHariIni = computed(() => {
    const max = props.skorMaksimalHariIni
    if (!max || max <= 0) return 0
    return Math.min(100, Math.round((props.skorHariIni / max) * 100))
})

const yearOptions = computed(() => {
    const current = new Date().getFullYear()
    const result = []
    for (let i = current - 5; i <= current + 1; i++) {
        result.push(i)
    }
    return result
})

const isXlScreen = ref(typeof window !== 'undefined' ? window.matchMedia('(min-width: 1280px)').matches : true)

onMounted(() => {
    if (typeof window !== 'undefined') {
        const mq = window.matchMedia('(min-width: 1280px)')
        const updateScreen = (e) => { isXlScreen.value = e.matches }
        mq.addEventListener('change', updateScreen)
        onUnmounted(() => mq.removeEventListener('change', updateScreen))
    }

    if (!props.isTrackingOther) {
        const todayDay = new Date().getDay()
        const isWeekend = todayDay === 0 || todayDay === 6

        if (props.shouldShowPresensiNotif && !isWeekend) {
            showPresensiModal.value = true
        } else if (props.missedDates && props.missedDates.length > 0) {
            showMissedDatesModal.value = true
        }
    }
})

const closePresensiModal = () => {
    showPresensiModal.value = false
    if (props.missedDates && props.missedDates.length > 0) {
        showMissedDatesModal.value = true
    }
}

// Days in month for max scale
const daysInCurrentMonth = new Date(props.tahun, new Date().getMonth() + 1, 0).getDate()

// Theme color anchor
const themeColor = computed(() => {
    return activeTheme.value?.primary600 || activeTheme.value?.hex || 'rgb(0, 106, 96)'
})

// Safely format color with alpha transparency for Chart.js canvas fills
const withAlpha = (color, alphaFloat = 0.15) => {
    if (!color) return `rgba(0, 106, 96, ${alphaFloat})`
    if (color.startsWith('#')) {
        let hex = color.slice(1)
        if (hex.length === 3) {
            hex = hex.split('').map(c => c + c).join('')
        }
        const num = parseInt(hex.substring(0, 6), 16)
        const r = (num >> 16) & 255
        const g = (num >> 8) & 255
        const b = num & 255
        return `rgba(${r}, ${g}, ${b}, ${alphaFloat})`
    }
    if (color.startsWith('rgb(')) {
        return color.replace('rgb(', 'rgba(').replace(')', `, ${alphaFloat})`)
    }
    if (color.startsWith('rgba(')) {
        return color.replace(/,\s*[\d.]+\)$/, `, ${alphaFloat})`)
    }
    return color
}

// --- Chart Configuration ---
const dailyChartData = computed(() => {
    return {
        labels: props.dailyChartData.map(d => {
            const dateStr = d.tanggal
            const dateObj = new Date(dateStr)
            const day = dateObj.getDate()
            const monthNames = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"]
            const month = monthNames[dateObj.getMonth()]
            return `${day} ${month}`
        }),
        datasets: [
            {
                label: 'Skor Harian',
                data: props.dailyChartData.map(d => d.skor),
                borderColor: themeColor.value,
                backgroundColor: withAlpha(themeColor.value, 0.13),
                borderWidth: 2.5,
                pointBackgroundColor: themeColor.value,
                pointBorderColor: 'rgb(255, 255, 255)',
                pointHoverBackgroundColor: 'rgb(255, 255, 255)',
                pointHoverBorderColor: themeColor.value,
                pointRadius: 3,
                pointHoverRadius: 5,
                fill: true,
                tension: 0.35
            }
        ]
    }
})

const dailyChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.9)',
            padding: 10,
            cornerRadius: 12,
            titleFont: { size: 12, weight: 'bold' },
            bodyFont: { size: 12 }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            grid: { color: 'rgba(148, 163, 184, 0.15)' },
            ticks: { font: { size: 11 }, color: 'rgb(148, 163, 184)' }
        },
        x: { 
            grid: { display: false },
            ticks: { maxRotation: 45, minRotation: 0, font: { size: 10 }, color: 'rgb(148, 163, 184)' }
        }
    },
}

const barChartData = computed(() => {
    return {
        labels: props.monthlyChartData.map(d => d.bulan),
        datasets: [
            {
                label: 'Poin Bulanan',
                data: props.monthlyChartData.map(d => d.skor),
                backgroundColor: themeColor.value,
                borderRadius: 8,
                barPercentage: 0.55,
            }
        ]
    }
})

const barChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.9)',
            padding: 10,
            cornerRadius: 12
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            grid: { color: 'rgba(148, 163, 184, 0.15)' },
            ticks: { font: { size: 11 }, color: 'rgb(148, 163, 184)' }
        },
        x: {
            grid: { display: false },
            ticks: { font: { size: 11 }, color: 'rgb(148, 163, 184)' }
        }
    },
}

// --- Radar Chart Configuration ---
const radarChartData = computed(() => {
    const labels = []
    const data = []

    if (props.radarChartDataBackend) {
        for (const [name, count] of Object.entries(props.radarChartDataBackend)) {
            const shortName = name.length > 15 ? name.substring(0, 15) + '...' : name
            labels.push(shortName)
            data.push(count)
        }
    }

    return {
        labels,
        datasets: [
            {
                label: 'Hari Target Tercapai',
                data,
                backgroundColor: withAlpha(themeColor.value, 0.2),
                borderColor: themeColor.value,
                borderWidth: 2,
                pointBackgroundColor: themeColor.value,
                pointBorderColor: 'rgb(255, 255, 255)',
                pointHoverBackgroundColor: 'rgb(255, 255, 255)',
                pointHoverBorderColor: themeColor.value,
                pointRadius: 3
            }
        ]
    }
})

const radarChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { 
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.9)',
            padding: 10,
            cornerRadius: 12,
            callbacks: {
                label: function(context) {
                    return context.raw + ' Hari'
                }
            }
        }
    },
    scales: {
        r: {
            angleLines: { display: true, color: 'rgba(148, 163, 184, 0.15)' },
            grid: { color: 'rgba(148, 163, 184, 0.15)' },
            pointLabels: {
                font: { size: 10, family: "'Instrument Sans', sans-serif", weight: '600' },
                color: 'rgb(100, 116, 139)'
            },
            ticks: { display: false, stepSize: 5 },
            suggestedMin: 0,
            suggestedMax: daysInCurrentMonth
        }
    }
}

const formatTanggal = (dateStr) => {
    const d = new Date(dateStr)
    return d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Dashboard" />

        <PageContainer size="xl">
            <!-- ═══ RUNNING ANNOUNCEMENT & HADITH BAR ═══════════════════ -->
            <div v-if="announcement || dailyHadith" class="w-full bg-slate-900 text-white py-2 overflow-hidden shadow-xs relative z-10 flex items-center rounded-2xl border border-slate-800">
                <div class="px-4 shrink-0 font-bold text-xs bg-slate-900 z-10 flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                    </svg>
                    <span>Pemberitahuan</span>
                </div>
                <div class="flex-1 overflow-hidden whitespace-nowrap">
                    <div class="animate-marquee inline-block text-xs sm:text-sm">
                        <span class="mx-8">
                            <template v-if="announcement">{{ announcement }}</template>
                            <template v-if="announcement && dailyHadith"> <span class="mx-3 text-primary-400">•</span> </template>
                            <template v-if="dailyHadith">
                                <component :is="dailyHadith.url ? Link : 'span'" :href="dailyHadith.url" class="hover:text-primary-300 transition-colors">
                                    “{{ dailyHadith.text }}” ({{ dailyHadith.source }})
                                </component>
                            </template>
                        </span>
                        <span class="mx-8">
                            <template v-if="announcement">{{ announcement }}</template>
                            <template v-if="announcement && dailyHadith"> <span class="mx-3 text-primary-400">•</span> </template>
                            <template v-if="dailyHadith">
                                <component :is="dailyHadith.url ? Link : 'span'" :href="dailyHadith.url" class="hover:text-primary-300 transition-colors">
                                    “{{ dailyHadith.text }}” ({{ dailyHadith.source }})
                                </component>
                            </template>
                        </span>
                    </div>
                </div>
            </div>

            <!-- ═══ STANDARDIZED PAGE HEADER & HERO ACTIONS ══════════════ -->
            <PageHeader
                :title="`Assalamu'alaikum, ${targetUser?.name || 'Sahabat SDAM'}`"
                subtitle="Awali aktivitas dengan niat ibadah dan konsistensi terbaik hari ini. Pantau progres kebiasaan serta kehadiran Anda secara terpadu."
            >
                <template #badge>
                    <Badge variant="primary">Beranda Utama</Badge>
                    <Badge v-if="isSedangHaid" variant="danger" class="inline-flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500 motion-safe:animate-pulse" />
                        Mode Haid Aktif
                    </Badge>
                </template>

                <template #actions>
                    <Button
                        v-if="featureHabitActive"
                        variant="primary"
                        size="md"
                        @click="router.visit(route('habit.form'))"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <span>Isi Habit Hari Ini</span>
                    </Button>

                    <Button
                        v-if="featurePresensiActive"
                        variant="outline"
                        size="md"
                        @click="router.visit(route('attendance.index'))"
                    >
                        <svg class="w-4 h-4 mr-1.5 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>{{ hasClockedInToday ? 'Presensiku' : 'Catat Presensi' }}</span>
                    </Button>
                </template>
            </PageHeader>

            <!-- ═══ KPI / METRIC CARDS GRID ═════════════════════════════ -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                <!-- Card 1: Skor Hari Ini -->
                <div class="card p-5 sm:p-6 flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Skor hari ini</span>
                        <div class="w-9 h-9 rounded-xl bg-primary-100 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl font-extrabold text-slate-900 dark:text-slate-100 tracking-tight">{{ skorHariIni }}</span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">/ {{ skorMaksimalHariIni }} poin</span>
                        </div>
                        <!-- Progress bar -->
                        <div class="w-full bg-slate-100 dark:bg-slate-700 h-2 rounded-full mt-3 overflow-hidden">
                            <div class="bg-primary-600 h-full rounded-full transition-transform duration-300" :style="{ width: `${persentaseHariIni}%` }" />
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-2">
                            {{ persentaseHariIni }}% capaian harian
                        </p>
                    </div>
                </div>

                <!-- Card 2: Skor Bulan Berjalan -->
                <div class="card p-5 sm:p-6 flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Skor {{ selectedMonthLabel }}</span>
                        <div class="w-9 h-9 rounded-xl bg-primary-100 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl font-extrabold text-primary-600 dark:text-primary-400 tracking-tight">{{ totalSkorBulanDipilih }}</span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">/ {{ skorMaksimalBulanDipilihComputed }} poin</span>
                        </div>
                        <!-- Progress bar -->
                        <div class="w-full bg-slate-100 dark:bg-slate-700 h-2 rounded-full mt-3 overflow-hidden">
                            <div class="bg-primary-600 h-full rounded-full transition-transform duration-300" :style="{ width: `${Math.min(100, persentaseBulanDipilih)}%` }" />
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-2">
                            {{ persentaseBulanDipilih }}% total akumulasi
                        </p>
                    </div>
                </div>

                <!-- Card 3: Target Minimal Sekolah & Pribadi -->
                <div class="card p-5 sm:p-6 flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Target minimal</span>
                        <div class="w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-600 dark:text-slate-400">Sekolah ({{ adminTargetBulanan }}%)</span>
                            <span class="font-bold text-slate-900 dark:text-slate-100">{{ adminTargetSkorMinimal }} poin</span>
                        </div>
                        <div v-if="isPersonalTarget" class="flex items-center justify-between text-xs pt-1 border-t border-slate-100 dark:border-slate-700">
                            <span class="text-slate-600 dark:text-slate-400">Pribadi ({{ targetBulanan }}%)</span>
                            <span class="font-bold text-primary-600 dark:text-primary-400">{{ targetSkorMinimal }} poin</span>
                        </div>
                        <div class="pt-1">
                            <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                                Status: {{ totalSkorBulanDipilih >= adminTargetSkorMinimal ? 'Target tercapai' : 'Sedang berproses' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Streak & Konsistensi -->
                <div class="card p-5 sm:p-6 flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Streak kebiasaan</span>
                        <div class="w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl font-extrabold text-amber-600 dark:text-amber-400 tracking-tight">
                                {{ targetUser?.current_streak || 0 }}
                            </span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">hari berturut-turut</span>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-3">
                            {{ (targetUser?.current_streak || 0) > 0 ? 'Konsistensi ibadah terjaga!' : 'Mulai streak hari ini dengan mencatat habit.' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- ═══ ANALYTICAL INSIGHT NOTICE ═══════════════════════════ -->
            <div v-if="insightMessage" class="bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 rounded-2xl p-5 flex items-start gap-4 shadow-xs">
                <div class="w-10 h-10 rounded-xl bg-amber-200/60 dark:bg-amber-900/60 text-amber-800 dark:text-amber-200 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-amber-900 dark:text-amber-200">Evaluasi otomatis</h3>
                    <p class="text-xs sm:text-sm text-amber-800 dark:text-amber-300 mt-1 leading-relaxed">{{ insightMessage }}</p>
                </div>
            </div>

            <!-- ═══ MAIN LAYOUT GRID: CONTENT & SIDEBAR ══════════════════ -->
            <div class="grid grid-cols-1 lg:grid-cols-3 xl:grid-cols-4 gap-6">

                <!-- Left 2-Column: Analysis, Charts & Presensi -->
                <div class="lg:col-span-2 xl:col-span-3 space-y-6">

                    <!-- Section: Rekap Skor Harian & Grafik -->
                    <div v-if="featureHabitActive" class="card p-5 sm:p-6">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-slate-100 dark:border-slate-700">
                            <div>
                                <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 tracking-tight">
                                    Rekap perolehan skor
                                </h2>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Pantauan harian bulan {{ selectedMonthLabel }} {{ currentYear }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2.5">
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    @click="showDailyChart = !showDailyChart"
                                >
                                    <svg v-if="!showDailyChart" class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                                    </svg>
                                    <svg v-else class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18M4 6h16a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V7a1 1 0 011-1z" />
                                    </svg>
                                    <span>{{ !showDailyChart ? 'Lihat grafik' : 'Lihat tabel' }}</span>
                                </Button>

                                <select v-model="currentMonth" @change="changeMonth" class="px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-primary-500 outline-none cursor-pointer">
                                    <option v-for="m in monthNamesOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
                                </select>
                                <select v-model="currentYear" @change="changeMonth" class="px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-primary-500 outline-none cursor-pointer">
                                    <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Table View -->
                        <div v-if="!showDailyChart" class="mt-5 max-h-[380px] overflow-y-auto custom-scrollbar">
                            <table v-if="props.dailyChartData.length" class="w-full text-left text-xs sm:text-sm text-slate-600 dark:text-slate-400 border-collapse">
                                <thead class="bg-slate-50 dark:bg-slate-800/80 sticky top-0 z-10 border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        <th class="py-3 px-4 font-bold text-slate-800 dark:text-slate-200">Tanggal</th>
                                        <th class="py-3 px-4 font-bold text-slate-800 dark:text-slate-200 text-right">Skor harian</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                    <tr v-for="(item, index) in props.dailyChartData" :key="index" class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                        <td class="py-2.5 px-4 font-medium">{{ formatTanggal(item.tanggal) }}</td>
                                        <td class="py-2.5 px-4 text-right">
                                            <span class="inline-flex items-center justify-center px-2.5 py-0.5 text-xs font-bold rounded-full" :class="item.skor > 0 ? 'bg-primary-100 text-primary-800 dark:bg-primary-900/50 dark:text-primary-200' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'">
                                                {{ item.skor }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-slate-50 dark:bg-slate-800/80 sticky bottom-0 z-10 border-t-2 border-slate-200 dark:border-slate-700">
                                    <tr>
                                        <th class="py-3 px-4 font-bold text-slate-800 dark:text-slate-200 text-xs">
                                            Total skor bulan {{ selectedMonthLabel }} {{ currentYear }}
                                        </th>
                                        <th class="py-3 px-4 text-right">
                                            <span class="inline-flex items-center justify-center px-3 py-1 text-xs font-extrabold rounded-full bg-primary-600 text-white shadow-xs">
                                                {{ totalSkorBulanDipilih }} Poin
                                            </span>
                                        </th>
                                    </tr>
                                </tfoot>
                            </table>
                            <div v-else class="text-center py-12 text-sm text-slate-400 dark:text-slate-500">
                                Belum ada data pada periode ini
                            </div>
                        </div>

                        <!-- Chart View -->
                        <div v-else class="mt-5 h-[320px] relative">
                            <LineChart v-if="dailyChartData.labels.length" :data="dailyChartData" :options="dailyChartOptions" />
                            <div v-else class="h-full flex items-center justify-center text-sm text-slate-400 dark:text-slate-500">
                                Belum ada data untuk ditampilkan
                            </div>
                        </div>
                    </div>

                    <!-- Section: Analisis Kualitas Ibadah -->
                    <div v-if="featureHabitActive && habitAnalytics && Object.keys(habitAnalytics).length > 0" class="card p-5 sm:p-6">
                        <div class="pb-4 mb-5 border-b border-slate-100 dark:border-slate-700">
                            <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 tracking-tight">
                                Analisis kualitas ibadah
                            </h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Rekapitulasi pelaksanaan ibadah pada bulan berjalan
                            </p>
                        </div>

                        <!-- Mobile Radar Chart -->
                        <div class="block md:hidden mb-6 h-64 relative">
                            <RadarChart v-if="radarChartData.labels.length > 0" :data="radarChartData" :options="radarChartOptions" />
                            <div v-else class="h-full flex items-center justify-center text-slate-400 text-xs">Belum ada data ibadah</div>
                        </div>

                        <!-- Desktop Detailed Grid -->
                        <div class="hidden md:grid md:grid-cols-2 gap-4">
                            <!-- Sholat Wajib -->
                            <div v-if="habitAnalytics.sholat_wajib" class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 space-y-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-xl bg-primary-100 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300 flex items-center justify-center">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                    </div>
                                    <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200">Sholat wajib berjamaah</h3>
                                </div>
                                <div class="grid grid-cols-3 gap-2 text-center">
                                    <div class="bg-white dark:bg-slate-800 rounded-xl p-2.5 border border-slate-200 dark:border-slate-700">
                                        <div class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-0.5">Masjid</div>
                                        <div class="text-base font-extrabold text-primary-600 dark:text-primary-400">{{ habitAnalytics.sholat_wajib.JM }}<span class="text-xs font-normal text-slate-400 ml-0.5">x</span></div>
                                    </div>
                                    <div class="bg-white dark:bg-slate-800 rounded-xl p-2.5 border border-slate-200 dark:border-slate-700">
                                        <div class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-0.5">Rumah</div>
                                        <div class="text-base font-extrabold text-primary-700 dark:text-primary-300">{{ habitAnalytics.sholat_wajib.JR }}<span class="text-xs font-normal text-slate-400 ml-0.5">x</span></div>
                                    </div>
                                    <div class="bg-white dark:bg-slate-800 rounded-xl p-2.5 border border-slate-200 dark:border-slate-700">
                                        <div class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-0.5">Sendiri</div>
                                        <div class="text-base font-extrabold text-amber-600 dark:text-amber-400">{{ habitAnalytics.sholat_wajib.M }}<span class="text-xs font-normal text-slate-400 ml-0.5">x</span></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sholat Rawatib -->
                            <div v-if="habitAnalytics.sholat_rawatib" class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 space-y-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-xl bg-primary-100 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300 flex items-center justify-center">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                    </div>
                                    <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200">Sholat sunnah rawatib</h3>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="flex justify-between items-center bg-white dark:bg-slate-800 rounded-xl px-3.5 py-2.5 border border-slate-200 dark:border-slate-700">
                                        <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">Qobliyah</span>
                                        <span class="text-sm font-extrabold text-primary-600 dark:text-primary-400">{{ habitAnalytics.sholat_rawatib.Qobliyah }}<span class="text-xs font-normal text-slate-400 ml-0.5">x</span></span>
                                    </div>
                                    <div class="flex justify-between items-center bg-white dark:bg-slate-800 rounded-xl px-3.5 py-2.5 border border-slate-200 dark:border-slate-700">
                                        <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">Ba'diyah</span>
                                        <span class="text-sm font-extrabold text-primary-600 dark:text-primary-400">{{ habitAnalytics.sholat_rawatib.Badiyah }}<span class="text-xs font-normal text-slate-400 ml-0.5">x</span></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Al-Qur'an -->
                            <div v-if="habitAnalytics.quran && habitAnalytics.quran.durasi > 0" class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 space-y-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-xl bg-primary-100 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300 flex items-center justify-center">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                                    </div>
                                    <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200">Tilawah Al-Qur'an</h3>
                                </div>
                                <div class="flex justify-between items-center bg-white dark:bg-slate-800 rounded-xl px-4 py-2.5 border border-slate-200 dark:border-slate-700">
                                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">Total durasi baca</span>
                                    <div class="text-sm font-extrabold text-primary-600 dark:text-primary-400">
                                        {{ Math.floor(habitAnalytics.quran.durasi / 60) > 0 ? Math.floor(habitAnalytics.quran.durasi / 60) + 'j ' : '' }}{{ habitAnalytics.quran.durasi % 60 }}<span class="text-xs font-normal text-slate-400 ml-0.5">m</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Kebiasaan Lainnya -->
                            <template v-if="habitAnalytics.others && Object.keys(habitAnalytics.others).length > 0">
                                <div v-for="(count, name) in habitAnalytics.others" :key="name" class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 space-y-3">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-xl bg-slate-200/60 dark:bg-slate-700 text-slate-700 dark:text-slate-300 flex items-center justify-center">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                        </div>
                                        <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate" :title="name">{{ name }}</h3>
                                    </div>
                                    <div class="flex justify-between items-center bg-white dark:bg-slate-800 rounded-xl px-4 py-2.5 border border-slate-200 dark:border-slate-700">
                                        <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">Total frekuensi</span>
                                        <div class="text-sm font-extrabold text-slate-800 dark:text-slate-200">
                                            {{ count }}<span class="text-xs font-normal text-slate-400 ml-0.5">x</span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Section: Rekap Presensi Bulan Berjalan -->
                    <div v-if="featurePresensiActive" class="card p-5 sm:p-6">
                        <div class="pb-4 mb-5 border-b border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 tracking-tight">
                                    Rekap presensi bulan berjalan
                                </h2>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Riwayat presensi harian pada periode aktif
                                </p>
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto custom-scrollbar max-h-64">
                            <table class="w-full text-left text-xs sm:text-sm text-slate-600 dark:text-slate-400 border-collapse">
                                <thead class="bg-slate-50 dark:bg-slate-800/80 sticky top-0 z-10 border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        <th class="py-3 px-4 font-bold text-slate-800 dark:text-slate-200">Tanggal</th>
                                        <th class="py-3 px-4 font-bold text-slate-800 dark:text-slate-200 text-center">Masuk</th>
                                        <th class="py-3 px-4 font-bold text-slate-800 dark:text-slate-200 text-center">Pulang</th>
                                        <th class="py-3 px-4 font-bold text-slate-800 dark:text-slate-200 text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                    <tr v-if="presensiBulanIni.length === 0">
                                        <td colspan="4" class="py-8 text-center text-slate-400 dark:text-slate-500">Belum ada data presensi bulan ini</td>
                                    </tr>
                                    <tr v-for="item in presensiBulanIni" :key="item.tanggal" class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                        <td class="py-2.5 px-4 font-medium">{{ formatTanggal(item.tanggal) }}</td>
                                        <td class="py-2.5 px-4 text-center">{{ item.check_in || '-' }}</td>
                                        <td class="py-2.5 px-4 text-center">{{ item.check_out || '-' }}</td>
                                        <td class="py-2.5 px-4 text-right">
                                            <Badge
                                                :variant="item.status === 'hadir' ? 'success' : (item.status === 'terlambat' ? 'warning' : (item.status === 'alpa' ? 'danger' : 'info'))"
                                                size="sm"
                                                class="capitalize"
                                            >
                                                {{ item.status }}
                                            </Badge>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Footer Semester Summary -->
                        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700 flex flex-wrap items-center justify-between gap-4">
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Akumulasi {{ semester }}</span>
                            <div class="flex flex-wrap items-center gap-4 text-xs font-medium">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500" />
                                    <span>Tepat Waktu: <strong class="text-slate-800 dark:text-slate-200">{{ semesterPresensiSummary.tepat_waktu }}</strong></span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-amber-500" />
                                    <span>Terlambat: <strong class="text-slate-800 dark:text-slate-200">{{ semesterPresensiSummary.terlambat }}</strong></span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-sky-500" />
                                    <span>Izin/Sakit: <strong class="text-slate-800 dark:text-slate-200">{{ semesterPresensiSummary.izin }}</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Grafik Batang Capaian Semester -->
                    <div v-if="featureHabitActive && monthlyChartData.length" class="card p-5 sm:p-6">
                        <div class="pb-3 mb-4 border-b border-slate-100 dark:border-slate-700">
                            <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 tracking-tight">
                                Capaian {{ semester }}
                            </h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Akumulasi poin tiap bulan berjalan
                            </p>
                        </div>
                        <div class="h-64 relative">
                            <BarChart v-if="barChartData.labels.length" :data="barChartData" :options="barChartOptions" />
                            <div v-else class="h-full flex items-center justify-center text-sm text-slate-400 dark:text-slate-500">
                                Tidak ada data pada semester ini
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right 1-Column: Islamic Widget & Badges -->
                <div class="space-y-6 xl:sticky xl:top-6">
                    <!-- Islamic Widget (Desktop) -->
                    <div v-if="isXlScreen" class="hidden xl:block card p-0 overflow-hidden">
                        <IslamicWidget class="h-full border-none shadow-none" />
                    </div>
                    
                    <!-- Koleksi Lencana (Gamification) -->
                    <div v-if="featureHabitActive" class="card p-5 sm:p-6">
                        <div class="mb-5 pb-3 border-b border-slate-100 dark:border-slate-700">
                            <h2 class="text-base font-bold text-slate-900 dark:text-slate-100 tracking-tight">Koleksi lencana</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Apresiasi konsistensi ibadah Anda</p>
                        </div>
                        
                        <div v-if="userBadges?.length" class="grid grid-cols-2 gap-4">
                            <div v-for="badge in userBadges" :key="badge.id" class="flex flex-col items-center text-center p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 group">
                                <div class="w-16 h-16 rounded-full flex items-center justify-center mb-2.5 bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300 ring-4 ring-primary-50 dark:ring-primary-950/40 group-hover:scale-105 transition-transform duration-200">
                                    <svg v-if="badge.icon === 'academic-cap'" class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72l5 2.73 5-2.73v3.72z"/></svg>
                                    <svg v-else-if="badge.icon === 'fire'" class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M11.64 5.93h-.04a8.21 8.21 0 00-2.3 3.37A8.32 8.32 0 009 13c0 2.22 1.34 4 3 4s3-1.78 3-4c0-1.25-.49-2.43-1.37-3.23a8.1 8.1 0 00-2.33-1.5c-.32-.14-.52-.45-.47-.8a.8.8 0 00-.73-.89L10 6.54l1.64-.61z" /><path d="M17 9.87c-.6-.73-1.38-1.33-2.22-1.76l-1.02-.53-.16-.95C13.43 5.48 12.56 4 11.5 4c-.16 0-.32.02-.48.05-.18-1.06-1.12-1.9-2.27-1.97h-.24C6.58 2.27 5 3.99 5 6.06c0 1.25.64 2.41 1.7 3.12l.98.66-.4 1.12C7.03 11.68 6 13.25 6 15c0 3.31 2.69 6 6 6s6-2.69 6-6c0-2.12-1.1-4.05-2.83-5.06l-1.07-.63.2-1.12c.16-.95.34-2.12.16-3.15l-.26-1.52 1.36.87A6.47 6.47 0 0118 10.42v1.54l-1-.59zM12 19c-2.21 0-4-1.79-4-4 0-1.4.88-2.64 2.19-3.26l1.24-.59-.3-1.34c-.15-.65-.18-1.31-.08-1.95.46.46.99.85 1.56 1.13l1.1.53-.15 1.2c-.08.63-.05 1.26.09 1.87A4.01 4.01 0 0116 15c0 2.21-1.79 4-4 4z"/></svg>
                                    <svg v-else-if="badge.icon === 'star'" class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                                    <svg v-else-if="badge.icon === 'trending-up'" class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg>
                                    <svg v-else-if="badge.icon === 'shield-check'" class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg>
                                    <svg v-else class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 9h-2V7h-2v5H6v2h2v5h2v-5h2v-2z"/></svg>
                                </div>
                                <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ badge.name }}</h3>
                                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5 leading-snug line-clamp-2">{{ badge.description }}</p>
                            </div>
                        </div>
                        
                        <div v-else class="text-center py-8 px-4 bg-slate-50 dark:bg-slate-800/40 rounded-xl border border-dashed border-slate-200 dark:border-slate-700">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-white dark:bg-slate-800 text-slate-400 mb-2 shadow-xs">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Belum ada lencana yang terbuka.<br />Pertahankan catatan habit Anda!</p>
                        </div>
                    </div>
                </div>

            </div>
        </PageContainer>

        <!-- ═══ MODAL PERINGATAN PRESENSI (Centralized Modal.vue) ═══ -->
        <Modal :show="showPresensiModal" maxWidth="md" @close="closePresensiModal">
            <div class="p-6">
                <div class="sm:flex sm:items-start gap-4">
                    <div class="mx-auto flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300 sm:mx-0">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:text-left">
                        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100" id="modal-presensi-title">Belum melakukan presensi</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                            Anda belum mencatat kehadiran hari ini. Jangan lupa presensi masuk agar rekap kehadiran tercatat rapi.
                        </p>
                    </div>
                </div>
            </div>
            <div class="bg-slate-50/70 dark:bg-slate-800/60 px-6 py-4 sm:flex sm:flex-row-reverse gap-3 border-t border-slate-100 dark:border-slate-800">
                <Button variant="primary" @click="router.visit(route('attendance.index'))">
                    Presensi sekarang
                </Button>
                <Button variant="outline" @click="closePresensiModal">
                    Nanti saja
                </Button>
            </div>
        </Modal>

        <!-- ═══ MODAL TANGGAL TERLEWAT (Centralized Modal.vue) ════════ -->
        <Modal :show="showMissedDatesModal" maxWidth="md" @close="showMissedDatesModal = false">
            <div class="p-6">
                <div class="sm:flex sm:items-start gap-4">
                    <div class="mx-auto flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 sm:mx-0">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:text-left">
                        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100" id="modal-missed-title">Ada hari yang belum terisi</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                            Terdapat <strong class="text-slate-900 dark:text-slate-100">{{ missedDates.length }} hari</strong> yang belum diisi laporan ibadahnya. Lengkapi catatan agar skor bulanan tetap maksimal.
                        </p>
                    </div>
                </div>
                
                <!-- Scrollable list of missed dates -->
                <div class="mt-4 max-h-48 overflow-y-auto pr-1 space-y-2 custom-scrollbar">
                    <Link 
                        v-for="date in missedDates" 
                        :key="date"
                        :href="`/habit/form?date=${date}`"
                        class="flex items-center justify-between px-4 py-2.5 bg-slate-50 dark:bg-slate-800/50 hover:bg-primary-50 dark:hover:bg-primary-950/40 border border-slate-200 dark:border-slate-700 hover:border-primary-300 rounded-xl transition-colors group cursor-pointer"
                    >
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 group-hover:text-primary-800 dark:group-hover:text-primary-200">
                            {{ formatTanggal(date) }}
                        </span>
                        <svg class="w-4 h-4 text-slate-400 group-hover:text-primary-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>
            </div>
            
            <div class="bg-slate-50/70 dark:bg-slate-800/60 px-6 py-4 sm:flex sm:flex-row-reverse border-t border-slate-100 dark:border-slate-800">
                <Button variant="outline" @click="showMissedDatesModal = false">
                    Tutup sementara
                </Button>
            </div>
        </Modal>

        <!-- ═══ FLOATING ACTION BUTTON (MOBILE ISLAMIC WIDGET) ══════ -->
        <button 
            type="button"
            @click="showMobileWidget = true"
            class="xl:hidden fixed bottom-6 right-6 z-40 bg-primary-600 hover:bg-primary-700 text-white p-3.5 rounded-full shadow-lg transition-transform hover:scale-105 active:scale-95 cursor-pointer"
            title="Jadwal Sholat &amp; Waktu"
            aria-label="Buka jadwal sholat"
        >
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </button>

        <!-- ═══ MOBILE ISLAMIC WIDGET MODAL (Centralized Modal.vue) ═══ -->
        <Modal :show="showMobileWidget" maxWidth="sm" @close="showMobileWidget = false">
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60">
                <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Jadwal sholat &amp; waktu</span>
                </h3>
                <button type="button" @click="showMobileWidget = false" class="icon-btn" aria-label="Tutup jadwal sholat">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="overflow-y-auto max-h-96 custom-scrollbar">
                <IslamicWidget class="border-none shadow-none" />
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style>
@keyframes marquee {
    0% { transform: translateX(0%); }
    100% { transform: translateX(-50%); }
}
.animate-marquee {
    animation: marquee 25s linear infinite;
    display: inline-flex;
}
.animate-marquee:hover {
    animation-play-state: paused;
}

.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: rgba(148, 163, 184, 0.4); border-radius: 10px; }
</style>
