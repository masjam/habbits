<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import IslamicWidget from '@/Components/IslamicWidget.vue'
import PageContainer from '@/Components/UI/PageContainer.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import Badge from '@/Components/UI/Badge.vue'
import Button from '@/Components/UI/Button.vue'
import Modal from '@/Components/UI/Modal.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { Line, Doughnut } from 'vue-chartjs'
import { useTheme } from '@/Composables/useTheme'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler,
    ArcElement
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler, ArcElement)

const { activeTheme } = useTheme()

const isLgScreen = ref(typeof window !== 'undefined' ? window.matchMedia('(min-width: 1024px)').matches : true)

onMounted(() => {
    if (typeof window !== 'undefined') {
        const mq = window.matchMedia('(min-width: 1024px)')
        const updateScreen = (e) => { isLgScreen.value = e.matches }
        mq.addEventListener('change', updateScreen)
        onUnmounted(() => mq.removeEventListener('change', updateScreen))
    }
})

const props = defineProps({
    skorHariIni: Number,
    skorMaksimalHariIni: Number,
    totalPegawai: Number,
    dailyChartData: Array,
    monthlyChartData: Array,
    semester: String,
    tahun: Number,
    targetRatio: Object,
})

const persentaseHariIni = computed(() => {
    if (props.skorMaksimalHariIni === 0) return 0
    return ((props.skorHariIni / props.skorMaksimalHariIni) * 100).toFixed(1)
})

const showMobileWidget = ref(false)

// ─── DAILY CHART (BULAN INI) ────────────────────────────────────────────────
const dailyChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                usePointStyle: true,
                boxWidth: 8,
                color: 'rgb(148, 163, 184)',
                font: { size: 11 }
            }
        },
        tooltip: {
            mode: 'index',
            intersect: false,
            backgroundColor: 'rgba(15, 23, 42, 0.9)',
            padding: 10,
            cornerRadius: 12,
            callbacks: {
                label: function(context) {
                    return ` ${context.dataset.label}: ${context.raw}%`
                }
            }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            suggestedMax: 100,
            max: 100,
            grid: { color: 'rgba(148, 163, 184, 0.15)' },
            ticks: { color: 'rgb(148, 163, 184)', font: { size: 11 } }
        },
        x: {
            grid: { display: false },
            ticks: { color: 'rgb(148, 163, 184)', font: { size: 10 } }
        }
    },
    interaction: { mode: 'nearest', axis: 'x', intersect: false }
}

// Safely format color with alpha transparency for Chart.js canvas fills
const withAlpha = (color, alphaFloat = 0.1) => {
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

const dailyChartConfig = computed(() => {
    const themeColor = activeTheme.value?.primary600 || activeTheme.value?.hex || 'rgb(0, 106, 96)'
    return {
        labels: (props.dailyChartData || []).map(d => {
            const date = new Date(d.tanggal)
            return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
        }),
        datasets: [
            {
                label: 'Skor Tercapai (Seluruh Pegawai)',
                data: (props.dailyChartData || []).map(d => d.skor),
                borderColor: themeColor,
                backgroundColor: withAlpha(themeColor, 0.1),
                borderWidth: 2,
                tension: 0.3,
                fill: true,
                pointBackgroundColor: themeColor,
                pointRadius: 3,
                pointHoverRadius: 5
            },
            {
                label: 'Batas Maksimal (100%)',
                data: (props.dailyChartData || []).map(d => d.maks),
                borderColor: 'rgb(148, 163, 184)', // Slate 400
                borderWidth: 2,
                borderDash: [5, 5],
                tension: 0,
                fill: false,
                pointRadius: 0,
                pointHoverRadius: 0
            }
        ]
    }
})

// ─── MONTHLY CHART (SEMESTER) ───────────────────────────────────────────────
const monthlyChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                usePointStyle: true,
                boxWidth: 8,
                color: 'rgb(148, 163, 184)',
                font: { size: 11 }
            }
        },
        tooltip: {
            mode: 'index',
            intersect: false,
            backgroundColor: 'rgba(15, 23, 42, 0.9)',
            padding: 10,
            cornerRadius: 12,
            callbacks: {
                label: function(context) {
                    return ` ${context.dataset.label}: ${context.raw}%`
                }
            }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            suggestedMax: 100,
            max: 100,
            grid: { color: 'rgba(148, 163, 184, 0.15)' },
            ticks: { color: 'rgb(148, 163, 184)', font: { size: 11 } }
        },
        x: {
            grid: { display: false },
            ticks: { color: 'rgb(148, 163, 184)', font: { size: 11 } }
        }
    },
    interaction: { mode: 'nearest', axis: 'x', intersect: false }
}

const monthlyChartConfig = computed(() => {
    const themeColor = activeTheme.value?.primary600 || activeTheme.value?.hex || 'rgb(0, 106, 96)'
    return {
        labels: (props.monthlyChartData || []).map(d => d.bulan),
        datasets: [
            {
                label: 'Total Skor Semester',
                data: (props.monthlyChartData || []).map(d => d.skor),
                borderColor: themeColor,
                backgroundColor: withAlpha(themeColor, 0.1),
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: themeColor,
                pointRadius: 4,
                pointHoverRadius: 6
            },
            {
                label: 'Batas Maksimal (100%)',
                data: (props.monthlyChartData || []).map(d => d.maks),
                borderColor: 'rgb(148, 163, 184)', // Slate 400
                borderWidth: 2,
                borderDash: [5, 5],
                tension: 0,
                fill: false,
                pointRadius: 0,
                pointHoverRadius: 0
            }
        ]
    }
})

// ─── RATIO CHART (TARGET) ───────────────────────────────────────────────────
const ratioChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '70%',
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                usePointStyle: true,
                boxWidth: 8,
                color: 'rgb(148, 163, 184)',
                font: { size: 11 }
            }
        },
        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.9)',
            padding: 10,
            cornerRadius: 12,
            callbacks: {
                label: function(context) {
                    return ` ${context.label}: ${context.raw} Pegawai`
                }
            }
        }
    }
}

const ratioChartConfig = computed(() => ({
    labels: ['Tercapai (≥' + (props.targetRatio?.target || 0) + '%)', 'Belum Tercapai'],
    datasets: [
        {
            data: [props.targetRatio?.hit || 0, props.targetRatio?.miss || 0],
            backgroundColor: ['rgb(16, 185, 129)', 'rgb(244, 63, 94)'], // Emerald 500, Rose 500
            borderWidth: 0,
            hoverOffset: 4
        }
    ]
}))

</script>

<template>
    <AuthenticatedLayout>
        <Head title="Dashboard Admin" />

        <PageContainer size="xl">
            <!-- Header -->
            <PageHeader
                title="Dashboard Admin"
                subtitle="Pantauan performa ibadah seluruh pegawai secara komprehensif."
            >
                <template #badge>
                    <Badge variant="primary">Administrator</Badge>
                </template>
                <template #actions>
                    <Button variant="outline" size="sm" @click="router.reload()">
                        <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Segarkan
                    </Button>
                    <Button variant="primary" size="sm" @click="router.visit(route('admin.laporan'))">
                        <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Rekap Laporan
                    </Button>
                </template>
            </PageHeader>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="card p-5 sm:p-6 transition-colors">
                    <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Pencapaian Hari Ini</div>
                    <div class="text-3xl font-black text-primary-600 dark:text-primary-400">
                        {{ skorHariIni }}<span class="text-lg text-slate-400 dark:text-slate-500 font-medium"> / {{ skorMaksimalHariIni }}</span>
                    </div>
                    <div class="mt-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                        Total poin dari {{ totalPegawai }} pegawai
                    </div>
                </div>

                <div class="card p-5 sm:p-6 transition-colors">
                    <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Persentase Hari Ini</div>
                    <div class="text-3xl font-black text-primary-600 dark:text-primary-400">
                        {{ persentaseHariIni }}%
                    </div>
                    <div class="mt-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                        Dari maksimal 100% hari ini
                    </div>
                </div>

                <div class="card p-5 sm:p-6 transition-colors">
                    <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Total Pegawai</div>
                    <div class="text-3xl font-black text-slate-900 dark:text-slate-100">
                        {{ totalPegawai }} <span class="text-lg text-slate-400 dark:text-slate-500 font-medium">Orang</span>
                    </div>
                    <div class="mt-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                        Pegawai yang dipantau sistem
                    </div>
                </div>
            </div>

            <!-- Charts & Widget -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column (Charts) -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Daily Chart -->
                    <div class="card overflow-hidden transition-colors">
                        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60">
                            <h3 class="font-bold text-slate-900 dark:text-slate-100 text-sm sm:text-base">Tren Performa Harian Seluruh Pegawai (30 Hari)</h3>
                        </div>
                        <div class="p-6">
                            <div class="h-80 w-full">
                                <Line :data="dailyChartConfig" :options="dailyChartOptions" />
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Chart -->
                    <div class="card overflow-hidden transition-colors">
                        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60">
                            <h3 class="font-bold text-slate-900 dark:text-slate-100 text-sm sm:text-base">Capaian Bulanan (Semester Ini)</h3>
                        </div>
                        <div class="p-6">
                            <div class="h-80 w-full">
                                <Line :data="monthlyChartConfig" :options="monthlyChartOptions" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column (Widget & Pie) -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- Target Ratio Pie -->
                    <div class="card overflow-hidden transition-colors">
                        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60">
                            <h3 class="font-bold text-slate-900 dark:text-slate-100 text-sm sm:text-base">Rasio Capaian Target (Bulan Ini)</h3>
                        </div>
                        <div class="p-6">
                            <div class="h-64 w-full relative">
                                <Doughnut :data="ratioChartConfig" :options="ratioChartOptions" />
                                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none mt-[-30px]">
                                    <span class="text-3xl font-black text-slate-900 dark:text-slate-100">{{ props.targetRatio?.hit || 0 }}</span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400 font-bold uppercase">Tercapai</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Islamic Widget — hanya tampil di desktop (lg ke atas) -->
                    <div v-if="isLgScreen" class="hidden lg:block card p-0 overflow-hidden">
                        <IslamicWidget class="h-full border-none shadow-none" />
                    </div>
                </div>
            </div>
        </PageContainer>

        <!-- Floating Action Button — hanya tampil di mobile/tablet (< lg) -->
        <button
            type="button"
            @click="showMobileWidget = true"
            class="lg:hidden fixed bottom-6 right-6 z-40 bg-primary-600 hover:bg-primary-700 text-white p-3.5 rounded-full shadow-lg transition-transform hover:scale-105 active:scale-95 cursor-pointer"
            title="Jadwal Sholat &amp; Waktu"
            aria-label="Buka jadwal sholat"
        >
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </button>

        <!-- Mobile Islamic Widget Modal (Centralized Modal.vue) -->
        <Modal :show="showMobileWidget" maxWidth="sm" @close="showMobileWidget = false">
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60">
                <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Waktu &amp; Jadwal Sholat</span>
                </h3>
                <button @click="showMobileWidget = false" type="button" class="icon-btn" aria-label="Tutup jadwal sholat">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="overflow-y-auto max-h-96">
                <IslamicWidget class="border-none shadow-none" />
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
