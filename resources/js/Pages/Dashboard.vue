<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import IslamicWidget from '@/Components/IslamicWidget.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed, ref, onMounted } from 'vue'

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

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, RadialLinearScale, RadarController, Title, Tooltip, Legend, Filler)

const props = defineProps({
    skorHariIni:      { type: Number,  default: 0 },
    skorMaksimalHariIni:{ type: Number, default: 100 },
    skorBulanIni:     { type: Number,  default: 0 },
    skorMaksimalBulanIni:{ type: Number, default: 0 },
    targetSkorMinimal:{ type: Number, default: 0 },
    targetBulanan:    { type: Number, default: 0 },
    adminTargetBulanan: { type: Number, default: 0 },
    adminTargetSkorMinimal: { type: Number, default: 0 },
    isPersonalTarget: { type: Boolean, default: false },
    isSedangHaid:     { type: Boolean, default: false },
    dailyChartData:   { type: Array,   default: () => [] },
    monthlyChartData: { type: Array,   default: () => [] },
    semester:         { type: String,  default: '' },
    tahun:            { type: Number,  default: new Date().getFullYear() },
    leaderboard:      { type: Array,   default: () => [] },
    isTrackingOther:  { type: Boolean, default: false },
    targetUser:       { type: Object,  default: () => ({}) },
    userBadges:       { type: Array,   default: () => [] },
    insightMessage:   { type: String,  default: null },
    pegawaiList:      { type: Array,   default: () => [] },
    missedDates:      { type: Array,   default: () => [] },
    announcement:     { type: String,  default: null },
    habitAnalytics:   { type: Object,  default: () => ({}) },
    radarChartDataBackend: { type: Object, default: () => ({}) },
})

// --- Modal State ---
const showMissedDatesModal = ref(props.missedDates && props.missedDates.length > 0)
const showMobileWidget = ref(false)

// --- Islamic Data untuk Hadis Harian (Running Text) ---
import { useIslamicData } from '@/Composables/useIslamicData'
const { dailyHadith } = useIslamicData()

onMounted(() => {
    // Tampilkan modal jika ada tanggal terlewat dan sedang melihat data sendiri
    if (props.missedDates.length > 0 && !props.isTrackingOther) {
        showMissedDatesModal.value = true;
    }
})

// Get days in current month for max scale
const daysInCurrentMonth = new Date(props.tahun, new Date().getMonth() + 1, 0).getDate();

// --- Chart Configuration ---
const dailyChartData = computed(() => {
    return {
        labels: props.dailyChartData.map(d => {
            const dateStr = d.tanggal;
            const dateObj = new Date(dateStr);
            const day = dateObj.getDate();
            const monthNames = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
            const month = monthNames[dateObj.getMonth()];
            return `${day} ${month}`;
        }),
        datasets: [
            {
                label: 'Skor Harian',
                data: props.dailyChartData.map(d => d.skor),
                borderColor: '#10b981', // emerald-500
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 2,
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '#10b981',
                fill: true,
                tension: 0.4
            }
        ]
    }
})

const dailyChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        y: { beginAtZero: true },
        x: { 
            grid: { display: false },
            ticks: { maxRotation: 45, minRotation: 45 }
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
                backgroundColor: '#14b8a6', // teal-500
                borderRadius: 4,
                barPercentage: 0.5,
            }
        ]
    }
})

const barChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        y: { beginAtZero: true },
        x: { grid: { display: false } }
    },
}

// --- Radar Chart Configuration ---
const radarChartData = computed(() => {
    const labels = [];
    const data = [];

    if (props.radarChartDataBackend) {
        for (const [name, count] of Object.entries(props.radarChartDataBackend)) {
            const shortName = name.length > 15 ? name.substring(0, 15) + '...' : name;
            labels.push(shortName);
            data.push(count);
        }
    }

    return {
        labels,
        datasets: [
            {
                label: 'Hari Target Tercapai',
                data,
                backgroundColor: 'rgba(16, 185, 129, 0.2)', // emerald
                borderColor: '#10b981',
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '#10b981',
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
            callbacks: {
                label: function(context) {
                    return context.raw + ' Hari';
                }
            }
        }
    },
    scales: {
        r: {
            angleLines: { display: true, color: 'rgba(0, 0, 0, 0.1)' },
            grid: { color: 'rgba(0, 0, 0, 0.1)' },
            pointLabels: {
                font: { size: 10, family: "'Instrument Sans', sans-serif", weight: 'bold' },
                color: '#64748b' // slate-500
            },
            ticks: { display: false, stepSize: 5 },
            suggestedMin: 0,
            suggestedMax: daysInCurrentMonth
        }
    }
}

// Fungsi Helper untuk format tanggal
const formatTanggal = (dateStr) => {
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' });
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Dashboard" />

        <!-- Running Text Announcement & Hadis -->
        <div v-if="announcement || dailyHadith" class="w-full bg-slate-900 text-white py-2 overflow-hidden shadow-sm relative z-10 flex items-center">
            <div class="px-4 shrink-0 font-bold text-xs uppercase tracking-wider bg-slate-900 z-10 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                </svg>
                PENGUMUMAN
            </div>
            <div class="flex-1 overflow-hidden whitespace-nowrap">
                <div class="animate-marquee inline-block text-sm">
                    <span class="mx-8">
                        <template v-if="announcement">{{ announcement }}</template>
                        <template v-if="announcement && dailyHadith"> <span class="mx-4 text-emerald-400">◆</span> </template>
                        <template v-if="dailyHadith">
                            <component :is="dailyHadith.url ? Link : 'span'" :href="dailyHadith.url" class="hover:text-emerald-400 transition-colors">
                                "{{ dailyHadith.text }}" — {{ dailyHadith.source }}
                            </component>
                        </template>
                    </span>
                    <span class="mx-8">
                        <template v-if="announcement">{{ announcement }}</template>
                        <template v-if="announcement && dailyHadith"> <span class="mx-4 text-emerald-400">◆</span> </template>
                        <template v-if="dailyHadith">
                            <component :is="dailyHadith.url ? Link : 'span'" :href="dailyHadith.url" class="hover:text-emerald-400 transition-colors">
                                "{{ dailyHadith.text }}" — {{ dailyHadith.source }}
                            </component>
                        </template>
                    </span>
                </div>
            </div>
        </div>

        <!-- Modal Peringatan Tanggal Terlewat -->
        <div v-if="showMissedDatesModal" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>
            
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-3xl bg-white dark:bg-slate-800 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md border border-slate-100 dark:border-slate-700">
                        <div class="bg-white dark:bg-slate-800 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                    <h3 class="text-base font-bold leading-6 text-slate-900 dark:text-slate-100" id="modal-title">Ada Hari Terlewat!</h3>
                                    <div class="mt-2">
                                        <p class="text-sm text-slate-500 dark:text-slate-400 dark:text-slate-500">
                                            Anda memiliki <span class="font-bold text-slate-700 dark:text-slate-300 dark:text-slate-600">{{ missedDates.length }} hari</span> yang belum diisi laporan ibadahnya. Segera isi agar skor bulan ini tidak kosong.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Scrollable List of Missed Dates -->
                            <div class="mt-4 max-h-48 overflow-y-auto pr-2 space-y-2 custom-scrollbar">
                                <Link 
                                    v-for="date in missedDates" 
                                    :key="date"
                                    :href="`/habit/form?date=${date}`"
                                    class="block w-full text-left px-4 py-3 bg-slate-50 dark:bg-slate-800/50 hover:bg-amber-50 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 hover:border-amber-200 rounded-xl transition-colors group"
                                >
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-300 dark:text-slate-600 group-hover:text-amber-800">{{ formatTanggal(date) }}</span>
                                        <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 group-hover:text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </Link>
                            </div>
                        </div>
                        
                        <div class="bg-slate-50 dark:bg-slate-800/50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t border-slate-100 dark:border-slate-700">
                            <button @click="showMissedDatesModal = false" type="button" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white dark:bg-slate-800 px-3 py-2 text-sm font-bold text-slate-900 dark:text-slate-100 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 dark:bg-slate-800/50 sm:mt-0 sm:w-auto">
                                Tutup (Nanti Saja)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6 w-full pb-8">
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 dark:text-slate-100 tracking-tight">Dashboard</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 dark:text-slate-500 mt-1">
                        Pantauan habit &amp; ibadah
                        <span class="font-bold text-slate-700 dark:text-slate-300 dark:text-slate-600">{{ targetUser?.name }}</span>
                        <span v-if="isSedangHaid" class="ml-2 inline-flex items-center gap-1 text-pink-600 font-medium">
                            <span class="w-2 h-2 rounded-full bg-pink-500 animate-pulse" />
                            Mode Haid Aktif
                        </span>
                    </p>
                </div>
                
                <!-- Streak Pill -->
                <div v-if="targetUser?.current_streak > 0" class="inline-flex items-center gap-2 px-4 py-2 rounded-full shadow-sm bg-gradient-to-r from-orange-400 to-amber-500 text-white font-bold text-sm">
                    <span>{{ targetUser.current_streak }} Hari Beruntun</span>
                    <svg class="w-5 h-5 drop-shadow" fill="currentColor" viewBox="0 0 24 24"><path d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z" /></svg>
                </div>
            </div>

            <!-- Analytical Insight Card -->
            <div v-if="insightMessage" class="bg-[#FDE68A] rounded-3xl shadow-sm p-5 relative overflow-hidden flex items-start gap-4">
                <div class="w-10 h-10 rounded-full bg-amber-200/50 text-amber-700 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-amber-900">Evaluasi Otomatis</h3>
                    <p class="text-sm text-amber-800 mt-1 leading-snug">{{ insightMessage }}</p>
                </div>
            </div>

            <!-- Main Grid Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                
                <!-- Left Column (Badges & Charts) -->
                <div class="lg:col-span-2 xl:col-span-2 space-y-6">
                    
                    <!-- Gamification: Koleksi Lencana -->
                    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] p-6">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-200">Koleksi Lencana</h2>
                                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Penghargaan atas konsistensi ibadah Anda</p>
                            </div>
                        </div>
                        
                        <div v-if="userBadges?.length" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-6">
                            <div v-for="badge in userBadges" :key="badge.id" class="flex flex-col items-center text-center group cursor-default">
                                <div :class="`w-20 h-20 rounded-full flex items-center justify-center mb-3 bg-${badge.color_theme}-100 text-${badge.color_theme}-500 shadow-inner group-hover:scale-110 transition-transform duration-300 ring-4 ring-${badge.color_theme}-50`">
                                    <svg v-if="badge.icon === 'academic-cap'" class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72l5 2.73 5-2.73v3.72z"/></svg>
                                    <svg v-else-if="badge.icon === 'fire'" class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24"><path d="M11.64 5.93h-.04a8.21 8.21 0 00-2.3 3.37A8.32 8.32 0 009 13c0 2.22 1.34 4 3 4s3-1.78 3-4c0-1.25-.49-2.43-1.37-3.23a8.1 8.1 0 00-2.33-1.5c-.32-.14-.52-.45-.47-.8a.8.8 0 00-.73-.89L10 6.54l1.64-.61z" /><path d="M17 9.87c-.6-.73-1.38-1.33-2.22-1.76l-1.02-.53-.16-.95C13.43 5.48 12.56 4 11.5 4c-.16 0-.32.02-.48.05-.18-1.06-1.12-1.9-2.27-1.97h-.24C6.58 2.27 5 3.99 5 6.06c0 1.25.64 2.41 1.7 3.12l.98.66-.4 1.12C7.03 11.68 6 13.25 6 15c0 3.31 2.69 6 6 6s6-2.69 6-6c0-2.12-1.1-4.05-2.83-5.06l-1.07-.63.2-1.12c.16-.95.34-2.12.16-3.15l-.26-1.52 1.36.87A6.47 6.47 0 0118 10.42v1.54l-1-.59zM12 19c-2.21 0-4-1.79-4-4 0-1.4.88-2.64 2.19-3.26l1.24-.59-.3-1.34c-.15-.65-.18-1.31-.08-1.95.46.46.99.85 1.56 1.13l1.1.53-.15 1.2c-.08.63-.05 1.26.09 1.87A4.01 4.01 0 0116 15c0 2.21-1.79 4-4 4z"/></svg>
                                    <svg v-else-if="badge.icon === 'star'" class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                                    <svg v-else-if="badge.icon === 'trending-up'" class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg>
                                    <svg v-else-if="badge.icon === 'shield-check'" class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg>
                                    <svg v-else class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 9h-2V7h-2v5H6v2h2v5h2v-5h2v-2z"/></svg>
                                </div>
                                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ badge.name }}</h4>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 dark:text-slate-500 mt-1 leading-snug px-2">{{ badge.description }}</p>
                            </div>
                        </div>
                        
                        <div v-else class="text-center py-10 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-dashed border-slate-200 dark:border-slate-700">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white dark:bg-slate-800 text-slate-300 dark:text-slate-600 mb-3 shadow-sm">
                                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400 dark:text-slate-500 font-medium">Belum ada lencana yang terbuka. <br/> Terus semangat penuhi target harian Anda!</p>
                        </div>
                    </div>

                    <!-- Analisis Ibadah Widget -->
                    <div v-if="habitAnalytics && Object.keys(habitAnalytics).length > 0" class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] p-6">
                        <div class="border-b border-slate-100 dark:border-slate-700 pb-3 mb-4">
                            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Analisis Kualitas Bulan Ini</h3>
                            <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5 tracking-wide">Berdasarkan data yang Anda inputkan</p>
                        </div>
                        
                        <!-- Mobile Only: Radar Chart -->
                        <div class="block md:hidden mb-4 h-64 relative">
                            <RadarChart v-if="radarChartData.labels.length > 0" :data="radarChartData" :options="radarChartOptions" />
                            <div v-else class="h-full flex items-center justify-center text-slate-400 text-xs">Belum ada data ibadah</div>
                        </div>

                        <!-- Desktop Only: Detailed Grid -->
                        <div class="hidden md:grid md:grid-cols-2 gap-4">
                            <!-- Sholat Wajib -->
                            <div v-if="habitAnalytics.sholat_wajib">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-6 h-6 rounded-md bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                    </div>
                                    <h4 class="text-xs font-bold text-slate-700 dark:text-slate-300">Sholat Wajib</h4>
                                </div>
                                <div class="grid grid-cols-3 gap-2">
                                    <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-2 text-center border border-slate-100 dark:border-slate-700">
                                        <div class="text-[9px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">Masjid</div>
                                        <div class="text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ habitAnalytics.sholat_wajib.JM }}<span class="text-[10px] font-normal text-slate-400 ml-0.5">x</span></div>
                                    </div>
                                    <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-2 text-center border border-slate-100 dark:border-slate-700">
                                        <div class="text-[9px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">Rumah (JR)</div>
                                        <div class="text-sm font-bold text-blue-600 dark:text-blue-400">{{ habitAnalytics.sholat_wajib.JR }}<span class="text-[10px] font-normal text-slate-400 ml-0.5">x</span></div>
                                    </div>
                                    <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-2 text-center border border-slate-100 dark:border-slate-700">
                                        <div class="text-[9px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">Sendiri (M)</div>
                                        <div class="text-sm font-bold text-amber-600 dark:text-amber-400">{{ habitAnalytics.sholat_wajib.M }}<span class="text-[10px] font-normal text-slate-400 ml-0.5">x</span></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sholat Rawatib -->
                            <div v-if="habitAnalytics.sholat_rawatib">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-6 h-6 rounded-md bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                    </div>
                                    <h4 class="text-xs font-bold text-slate-700 dark:text-slate-300">Sholat Rawatib</h4>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="flex justify-between items-center bg-slate-50 dark:bg-slate-800/50 rounded-xl px-3 py-2 border border-slate-100 dark:border-slate-700">
                                        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400">Qobliyah</span>
                                        <span class="text-sm font-bold text-blue-600 dark:text-blue-400">{{ habitAnalytics.sholat_rawatib.Qobliyah }}<span class="text-[10px] font-normal text-slate-400 ml-0.5">x</span></span>
                                    </div>
                                    <div class="flex justify-between items-center bg-slate-50 dark:bg-slate-800/50 rounded-xl px-3 py-2 border border-slate-100 dark:border-slate-700">
                                        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400">Ba'diyah</span>
                                        <span class="text-sm font-bold text-blue-600 dark:text-blue-400">{{ habitAnalytics.sholat_rawatib.Badiyah }}<span class="text-[10px] font-normal text-slate-400 ml-0.5">x</span></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Al-Quran -->
                            <div v-if="habitAnalytics.quran && habitAnalytics.quran.durasi > 0">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-6 h-6 rounded-md bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                                    </div>
                                    <h4 class="text-xs font-bold text-slate-700 dark:text-slate-300">Al-Quran</h4>
                                </div>
                                <div class="flex justify-between items-center bg-slate-50 dark:bg-slate-800/50 rounded-xl px-4 py-2.5 border border-slate-100 dark:border-slate-700">
                                    <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Durasi Baca</span>
                                    <div class="text-sm font-bold text-purple-600 dark:text-purple-400">
                                        {{ Math.floor(habitAnalytics.quran.durasi / 60) > 0 ? Math.floor(habitAnalytics.quran.durasi / 60) + 'j ' : '' }}{{ habitAnalytics.quran.durasi % 60 }}<span class="text-[10px] font-normal text-slate-400 ml-0.5">m</span>
                                    </div>
                                </div>
                            </div>
                            <!-- Other Habits (Separated) -->
                            <template v-if="habitAnalytics.others && Object.keys(habitAnalytics.others).length > 0">
                                <div v-for="(count, name) in habitAnalytics.others" :key="name">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="w-6 h-6 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                        </div>
                                        <h4 class="text-xs font-bold text-slate-700 dark:text-slate-300 truncate w-full" :title="name">{{ name }}</h4>
                                    </div>
                                    <div class="flex justify-between items-center bg-slate-50 dark:bg-slate-800/50 rounded-xl px-4 py-2.5 border border-slate-100 dark:border-slate-700">
                                        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Frekuensi</span>
                                        <div class="text-sm font-bold text-slate-600 dark:text-slate-400">
                                            {{ count }}<span class="text-[10px] font-normal text-slate-400 ml-0.5">x</span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Middle Column (Stat Cards & Analysis) -->
                <div class="lg:col-span-1 xl:col-span-1 space-y-6">
                    
                    <!-- Stat Cards -->
                    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] p-6">
                        <div class="border-b border-slate-100 dark:border-slate-700 pb-4 mb-4">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500 mb-1">
                                Perolehan Skor
                            </p>
                            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 mt-1">
                                <div>
                                    <span class="text-xl font-bold text-emerald-500">{{ skorHariIni }}</span>
                                    <span class="text-[10px] text-slate-400 ml-1 tracking-wide">/ {{ skorMaksimalHariIni }} (Harian)</span>
                                </div>
                                <div>
                                    <span class="text-xl font-bold text-blue-500">{{ skorBulanIni }}</span>
                                    <span class="text-[10px] text-slate-400 ml-1 tracking-wide">/ {{ skorMaksimalBulanIni }} (Bulanan)</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500 mb-1">
                                    Target Minimal
                                </p>
                                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 mt-1">
                                    <div v-if="isPersonalTarget">
                                        <span class="text-xl font-bold text-emerald-500">{{ targetSkorMinimal }}</span>
                                        <span class="text-[10px] text-emerald-600 font-bold ml-1 tracking-wide">(Pribadi: {{ targetBulanan }}%)</span>
                                    </div>
                                    <div>
                                        <span class="text-xl font-bold text-amber-500">{{ adminTargetSkorMinimal }}</span>
                                        <span class="text-[10px] text-slate-400 ml-1 tracking-wide">(Sekolah : {{ adminTargetBulanan }}%)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Area -->
                    <div class="flex flex-col gap-6">
                        <!-- Daily Trend Line Chart -->
                        <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] p-6 h-72 flex flex-col">
                            <div class="mb-3">
                                <p class="text-sm font-bold text-slate-800 dark:text-slate-200">Tren 30 Hari</p>
                                <p class="text-[11px] text-slate-400 dark:text-slate-500 uppercase tracking-wide">Poin Harian</p>
                            </div>
                            <div class="flex-1 relative min-h-0">
                                <LineChart v-if="dailyChartData.labels.length" :data="dailyChartData" :options="dailyChartOptions" />
                                <div v-else class="h-full flex items-center justify-center text-sm text-slate-400 dark:text-slate-500">Tidak ada data</div>
                            </div>
                        </div>

                        <!-- Monthly Bar Chart -->
                        <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] p-6 h-72 flex flex-col">
                            <div class="mb-3">
                                <p class="text-sm font-bold text-slate-800 dark:text-slate-200">Capaian {{ semester }}</p>
                                <p class="text-[11px] text-slate-400 dark:text-slate-500 uppercase tracking-wide">Akumulasi Bulanan</p>
                            </div>
                            <div class="flex-1 relative min-h-0">
                                <BarChart v-if="barChartData.labels.length" :data="barChartData" :options="barChartOptions" />
                                <div v-else class="h-full flex items-center justify-center text-sm text-slate-400 dark:text-slate-500">Tidak ada data</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column (Islamic Widget) - Hidden on Mobile, shown as sidebar on Desktop -->
                <div class="hidden xl:block xl:col-span-1">
                    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] overflow-hidden xl:sticky xl:top-6">
                        <IslamicWidget class="h-full border-none shadow-none" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Floating Action Button for Mobile Islamic Widget -->
        <button 
            @click="showMobileWidget = true"
            class="xl:hidden fixed bottom-6 right-6 z-40 bg-emerald-600 hover:bg-emerald-700 text-white p-4 rounded-full shadow-[0_8px_30px_rgb(0,0,0,0.12)] hover:shadow-[0_8px_30px_rgb(52,211,153,0.3)] transition-all hover:-translate-y-1"
            title="Jadwal Sholat & Waktu"
        >
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </button>

        <!-- Mobile Islamic Widget Off-Canvas / Modal -->
        <Transition
            enter-active-class="transition-opacity ease-linear duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity ease-linear duration-300"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showMobileWidget" class="fixed inset-0 z-50 flex items-center justify-center p-4 xl:hidden">
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showMobileWidget = false"></div>
                <div class="bg-white dark:bg-slate-800 w-full max-w-sm rounded-3xl shadow-2xl relative z-10 overflow-hidden transform transition-all flex flex-col max-h-full">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900">
                        <h3 class="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                            <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Waktu & Jadwal Sholat
                        </h3>
                        <button @click="showMobileWidget = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 rounded-full p-1.5 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="overflow-y-auto flex-1 custom-scrollbar">
                        <IslamicWidget class="border-none shadow-none" />
                    </div>
                </div>
            </div>
        </Transition>
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
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
</style>
