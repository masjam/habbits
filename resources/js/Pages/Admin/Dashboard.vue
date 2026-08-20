<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import IslamicWidget from '@/Components/IslamicWidget.vue'
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import { Line, Doughnut } from 'vue-chartjs'
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

// ─── DAILY CHART (BULAN INI) ────────────────────────────────────────────────
const dailyChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
        tooltip: {
            mode: 'index',
            intersect: false,
            callbacks: {
                label: function(context) {
                    return ` ${context.dataset.label}: ${context.raw}%`
                }
            }
        }
    },
    scales: {
        y: { beginAtZero: true, suggestedMax: 100, max: 100 }
    },
    interaction: { mode: 'nearest', axis: 'x', intersect: false }
}

const dailyChartConfig = computed(() => ({
    labels: props.dailyChartData.map(d => {
        const date = new Date(d.tanggal)
        return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
    }),
    datasets: [
        {
            label: 'Skor Tercapai (Seluruh Pegawai)',
            data: props.dailyChartData.map(d => d.skor),
            borderColor: '#059669', // Emerald 600
            backgroundColor: 'rgba(5, 150, 105, 0.1)',
            borderWidth: 2,
            tension: 0.3,
            fill: true,
            pointBackgroundColor: '#059669',
            pointRadius: 3,
            pointHoverRadius: 5
        },
        {
            label: 'Batas Maksimal (100%)',
            data: props.dailyChartData.map(d => d.maks),
            borderColor: '#94a3b8', // Slate 400
            borderWidth: 2,
            borderDash: [5, 5],
            tension: 0,
            fill: false,
            pointRadius: 0,
            pointHoverRadius: 0
        }
    ]
}))

// ─── MONTHLY CHART (SEMESTER) ───────────────────────────────────────────────
const monthlyChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
        tooltip: {
            mode: 'index',
            intersect: false,
            callbacks: {
                label: function(context) {
                    return ` ${context.dataset.label}: ${context.raw}%`
                }
            }
        }
    },
    scales: {
        y: { beginAtZero: true, suggestedMax: 100, max: 100 }
    },
    interaction: { mode: 'nearest', axis: 'x', intersect: false }
}

const monthlyChartConfig = computed(() => ({
    labels: props.monthlyChartData.map(d => d.bulan),
    datasets: [
        {
            label: 'Total Skor Semester',
            data: props.monthlyChartData.map(d => d.skor),
            borderColor: '#3b82f6', // Blue 500
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            borderWidth: 3,
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#3b82f6',
            pointRadius: 4,
            pointHoverRadius: 6
        },
        {
            label: 'Batas Maksimal (100%)',
            data: props.monthlyChartData.map(d => d.maks),
            borderColor: '#94a3b8', // Slate 400
            borderWidth: 2,
            borderDash: [5, 5],
            tension: 0,
            fill: false,
            pointRadius: 0,
            pointHoverRadius: 0
        }
    ]
}))

// ─── RATIO CHART (TARGET) ───────────────────────────────────────────────────
const ratioChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '70%',
    plugins: {
        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
        tooltip: {
            callbacks: {
                label: function(context) {
                    return ` ${context.label}: ${context.raw} Pegawai`
                }
            }
        }
    }
}

const ratioChartConfig = computed(() => ({
    labels: ['Tercapai (≥' + props.targetRatio.target + '%)', 'Belum Tercapai'],
    datasets: [
        {
            data: [props.targetRatio.hit, props.targetRatio.miss],
            backgroundColor: ['#10b981', '#f43f5e'],
            borderWidth: 0,
            hoverOffset: 4
        }
    ]
}))

</script>

<template>
    <AuthenticatedLayout>
        <Head title="Dashboard Admin" />

        <div class="space-y-6">
            <!-- Header -->
            <div>
                <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Dashboard Rekapitulasi</h2>
                <p class="text-sm text-slate-500 mt-1">Pantauan performa ibadah seluruh pegawai.</p>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                    <div class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-2">Pencapaian Hari Ini</div>
                    <div class="text-3xl font-black text-emerald-600">{{ skorHariIni }}<span class="text-lg text-slate-400 font-medium"> / {{ skorMaksimalHariIni }}</span></div>
                    <div class="mt-2 text-xs font-medium text-slate-500">
                        Total poin dari {{ totalPegawai }} pegawai
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                    <div class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-2">Persentase Hari Ini</div>
                    <div class="text-3xl font-black text-blue-600">{{ persentaseHariIni }}%</div>
                    <div class="mt-2 text-xs font-medium text-slate-500">
                        Dari maksimal 100% hari ini
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                    <div class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-2">Total Pegawai</div>
                    <div class="text-3xl font-black text-slate-800">{{ totalPegawai }} <span class="text-lg text-slate-400 font-medium">Orang</span></div>
                    <div class="mt-2 text-xs font-medium text-slate-500">
                        Pegawai yang dipantau sistem
                    </div>
                </div>
            </div>

            <!-- Charts & Widget -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column (Charts) -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Daily Chart -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
                            <h3 class="font-bold text-slate-800">Tren Performa Harian Seluruh Pegawai (30 Hari)</h3>
                        </div>
                        <div class="p-6">
                            <div class="h-80 w-full">
                                <Line :data="dailyChartConfig" :options="dailyChartOptions" />
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Chart -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
                            <h3 class="font-bold text-slate-800">Capaian Bulanan (Semester Ini)</h3>
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
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
                            <h3 class="font-bold text-slate-800">Rasio Capaian Target (Bulan Ini)</h3>
                        </div>
                        <div class="p-6">
                            <div class="h-64 w-full relative">
                                <Doughnut :data="ratioChartConfig" :options="ratioChartOptions" />
                                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none mt-[-30px]">
                                    <span class="text-3xl font-black text-slate-800">{{ props.targetRatio.hit }}</span>
                                    <span class="text-xs text-slate-500 font-bold uppercase">Tercapai</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <IslamicWidget class="h-full" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
