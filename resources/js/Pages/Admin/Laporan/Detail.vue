<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import { Bar } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale } from 'chart.js'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale)

const props = defineProps({
    pegawai: Object,
    persentaseBulanIni: Number,
    persentaseSemester: Number,
    semesterName: String,
    habitProgress: Array,
    namaBulan: String,
    filters: Object,
})

const currentYear = new Date().getFullYear()
const years = Array.from({ length: currentYear - 2024 + 1 }, (_, i) => 2024 + i)
const months = [
    { value: 1, label: 'Januari' }, { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' }, { value: 4, label: 'April' },
    { value: 5, label: 'Mei' }, { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' }, { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' }, { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' }, { value: 12, label: 'Desember' },
]

const selectedMonth = ref(props.filters.month)
const selectedYear = ref(props.filters.year)

watch([selectedMonth, selectedYear], () => {
    router.get(route('admin.laporan.detail', props.pegawai.id), { month: selectedMonth.value, year: selectedYear.value }, { preserveState: true })
})

const chartData = computed(() => {
    return {
        labels: props.habitProgress.map(h => h.nama),
        datasets: [
            {
                label: 'Capaian (%)',
                backgroundColor: props.habitProgress.map(h => h.persentase >= 80 ? '#10b981' : (h.persentase >= 50 ? '#fbbf24' : '#fb7185')),
                data: props.habitProgress.map(h => h.persentase)
            }
        ]
    }
})

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        y: {
            beginAtZero: true,
            max: 100,
            ticks: {
                callback: function(value) {
                    return value + '%'
                }
            }
        }
    },
    plugins: {
        legend: {
            display: false
        },
        tooltip: {
            callbacks: {
                label: function(context) {
                    return context.parsed.y + '%'
                }
            }
        }
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Detail Progres - ${pegawai.name}`" />

        <div class="space-y-6">
            <!-- Header & Back Button -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <Link :href="route('admin.laporan')" class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-white border border-slate-200 shadow-sm text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800 tracking-tight">{{ pegawai.name }}</h2>
                        <p class="text-sm text-slate-500 mt-1">Laporan bulan <strong>{{ namaBulan }}</strong>.</p>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="flex items-center gap-2 bg-white p-2 rounded-xl border border-slate-200 shadow-sm w-full md:w-auto">
                    <select v-model="selectedMonth" class="bg-slate-50 border-slate-200 text-slate-700 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block w-full p-2">
                        <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                    <select v-model="selectedYear" class="bg-slate-50 border-slate-200 text-slate-700 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block w-full p-2">
                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>
            </div>

            <!-- Global Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 flex items-center justify-between relative overflow-hidden">
                    <div class="relative z-10">
                        <div class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-1">Bulan Berjalan</div>
                        <div class="text-4xl font-black text-blue-600">{{ persentaseBulanIni }}%</div>
                    </div>
                    <!-- Background progress bar visual -->
                    <div class="absolute inset-y-0 left-0 bg-blue-50 z-0 transition-all duration-1000" :style="`width: ${persentaseBulanIni}%`"></div>
                </div>
                
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 flex items-center justify-between relative overflow-hidden">
                    <div class="relative z-10">
                        <div class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-1">{{ semesterName }} Berjalan</div>
                        <div class="text-4xl font-black text-emerald-600">{{ persentaseSemester }}%</div>
                    </div>
                    <!-- Background progress bar visual -->
                    <div class="absolute inset-y-0 left-0 bg-emerald-50 z-0 transition-all duration-1000" :style="`width: ${persentaseSemester}%`"></div>
                </div>
            </div>

            <!-- Per Habit Stats -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
                    <h3 class="font-bold text-slate-800">Grafik Progres Per Habit ({{ namaBulan }})</h3>
                </div>
                <div class="p-6">
                    <div class="w-full h-[400px]">
                        <Bar :data="chartData" :options="chartOptions" />
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
