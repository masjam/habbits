<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    logs: Object,
    filters: Object,
    stats: Object,
    formattedMonth: String,
})

const search = ref(props.filters.search || '')
const month = ref(props.filters.month || '')

const currentMonthStr = new Date().toISOString().substring(0, 7)
const isCurrentMonth = computed(() => month.value === currentMonthStr)

const applyFilters = () => {
    router.get(route('admin.login-logs'), { 
        search: search.value || undefined,
        month: month.value || undefined,
    }, { 
        preserveState: true, 
        replace: true 
    })
}

// Watch search with debounce
let searchTimeout
watch(search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        applyFilters()
    }, 400)
})

// Navigasi bulan sebelumnya / sesudahnya
const changeMonth = (delta) => {
    const base = month.value || currentMonthStr
    const [year, m] = base.split('-').map(Number)
    const date = new Date(year, m - 1 + delta, 1)
    const newYear = date.getFullYear()
    const newMonth = String(date.getMonth() + 1).padStart(2, '0')
    month.value = `${newYear}-${newMonth}`
    applyFilters()
}

const resetToCurrentMonth = () => {
    month.value = currentMonthStr
    applyFilters()
}

const clearMonthFilter = () => {
    month.value = ''
    applyFilters()
}

// Fungsi memformat tanggal (contoh: 21 Agu 2026 14:30)
const formatDate = (dateStr) => {
    if (!dateStr) return '-'
    const d = new Date(dateStr)
    return new Intl.DateTimeFormat('id-ID', { 
        day: 'numeric', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    }).format(d)
}

// Fungsi memformat user agent secara ringkas
const formatUserAgent = (ua) => {
    if (!ua) return 'Tidak Diketahui'
    if (ua.includes('Windows')) return 'Windows Desktop'
    if (ua.includes('Mac OS')) return 'Mac OS'
    if (ua.includes('Linux')) return 'Linux'
    if (ua.includes('Android')) return 'Android Mobile'
    if (ua.includes('iPhone')) return 'iPhone'
    return 'Lainnya'
}

// Helper untuk parsing lokasi terstruktur (JSON) atau teks biasa
const getParsedLocation = (locationStr) => {
    if (!locationStr) return null
    if (typeof locationStr === 'object') return locationStr
    if (typeof locationStr === 'string' && locationStr.trim().startsWith('{')) {
        try {
            return JSON.parse(locationStr)
        } catch (e) {
            return null
        }
    }
    return null
}

// Helper untuk menyusun nama wilayah / kota
const formatLocationTitle = (locObj) => {
    if (!locObj) return 'Tidak Diketahui'
    const parts = []
    if (locObj.city) parts.push(locObj.city)
    if (locObj.region && locObj.region !== locObj.city) parts.push(locObj.region)
    if (locObj.country && locObj.country !== 'Indonesia' && locObj.country !== 'ID') {
        parts.push(locObj.country)
    } else if (parts.length === 0 && locObj.country) {
        parts.push(locObj.country)
    }
    return parts.length > 0 ? parts.join(', ') : 'Lokasi Terdeteksi'
}
</script>

<template>
    <Head title="Log Akses Login" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="font-bold text-xl text-slate-800 dark:text-slate-200 leading-tight">
                        Log Akses Login
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Rekam jejak riwayat masuk pengguna ke dalam aplikasi.
                    </p>
                </div>

                <!-- Kartu Ringkasan Statistik Singkat -->
                <div class="flex items-center gap-2 flex-wrap">
                    <div class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-2xs text-center min-w-[70px]">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Login</p>
                        <p class="text-sm font-black text-emerald-600 dark:text-emerald-400 leading-none mt-0.5">
                            {{ stats?.total_logins || 0 }}
                        </p>
                    </div>
                    <div class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-2xs text-center min-w-[70px]">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">User Unik</p>
                        <p class="text-sm font-black text-blue-600 dark:text-blue-400 leading-none mt-0.5">
                            {{ stats?.unique_users || 0 }}
                        </p>
                    </div>
                </div>
            </div>
        </template>

        <div class="space-y-6 w-full">
            <div>
                <!-- Wrapper Card -->
                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-2xl border border-slate-100 dark:border-slate-700 overflow-hidden">
                    
                    <!-- Toolbar (Search & Filter Bulanan) -->
                    <div class="p-4 sm:p-5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
                        
                        <!-- Pencarian -->
                        <div class="relative w-full md:max-w-xs">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Cari nama, email, IP, lokasi..."
                                class="pl-9 pr-3 py-2 block w-full rounded-xl border border-slate-300 dark:border-slate-600 shadow-2xs focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-xs sm:text-sm bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                            >
                        </div>

                        <!-- Kontrol Filter Bulanan -->
                        <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap">
                            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 whitespace-nowrap hidden sm:inline">
                                Periode:
                            </span>

                            <!-- Tombol Bulan Sebelumnya -->
                            <button
                                type="button"
                                @click="changeMonth(-1)"
                                title="Bulan Sebelumnya"
                                class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition active:scale-95 cursor-pointer"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>

                            <!-- Input Bulan (Month Picker) -->
                            <input
                                type="month"
                                v-model="month"
                                @change="applyFilters"
                                class="px-3 py-1.5 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-emerald-500 shadow-2xs cursor-pointer"
                            />

                            <!-- Tombol Bulan Berikutnya -->
                            <button
                                type="button"
                                @click="changeMonth(1)"
                                title="Bulan Berikutnya"
                                class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition active:scale-95 cursor-pointer"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>

                            <!-- Tombol Shortcut Bulan Ini -->
                            <button
                                type="button"
                                @click="resetToCurrentMonth"
                                :class="[
                                    'px-2.5 py-1.5 text-xs font-bold rounded-xl transition cursor-pointer',
                                    isCurrentMonth 
                                        ? 'bg-emerald-600 text-white shadow-xs' 
                                        : 'border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                                ]"
                            >
                                Bulan Ini
                            </button>

                            <!-- Tombol Semua Periode -->
                            <button
                                type="button"
                                @click="clearMonthFilter"
                                :class="[
                                    'px-2.5 py-1.5 text-xs font-bold rounded-xl transition cursor-pointer',
                                    !month 
                                        ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900 shadow-xs' 
                                        : 'border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
                                ]"
                                title="Tampilkan log semua periode"
                            >
                                Semua
                            </button>
                        </div>
                    </div>

                    <!-- Content: Table (Desktop) & Cards (Mobile) -->
                    <div class="overflow-x-auto">
                        <!-- Desktop View (Table) -->
                        <table class="hidden md:table min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead class="bg-slate-50 dark:bg-slate-900/50">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        User
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        Waktu Login
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        IP Address
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        Lokasi & Jaringan
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        Perangkat
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700">
                                <tr v-for="log in logs.data" :key="log.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-9 w-9 rounded-full bg-emerald-100 flex items-center justify-center border border-emerald-200">
                                                <span class="text-emerald-700 font-bold text-sm">{{ log.user?.name?.charAt(0) || '?' }}</span>
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-semibold text-slate-900 dark:text-white">
                                                    {{ log.user?.name || 'User Dihapus' }}
                                                </div>
                                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                                    {{ log.user?.email || '-' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-slate-700 dark:text-slate-300">
                                            {{ formatDate(log.created_at) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-mono text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-700 px-2 py-1 rounded inline-block">
                                            {{ log.ip_address || 'N/A' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <template v-if="getParsedLocation(log.location)">
                                            <div class="space-y-1 max-w-xs">
                                                <!-- Baris 1: Kota, Wilayah / Negara & Badge Sumber Akurasi -->
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.242-4.243a8 8 0 1111.314 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    </svg>
                                                    <span class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                                                        {{ formatLocationTitle(getParsedLocation(log.location)) }}
                                                    </span>
                                                    
                                                    <!-- Badge Sumber -->
                                                    <span 
                                                        v-if="getParsedLocation(log.location).source === 'gps'"
                                                        class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800"
                                                        title="Koordinat akurat dari GPS perangkat/browser"
                                                    >
                                                        GPS
                                                    </span>
                                                    <span 
                                                        v-else-if="getParsedLocation(log.location).source === 'ip'"
                                                        class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800"
                                                        title="Estimasi lokasi berdasarkan IP provider"
                                                    >
                                                        IP
                                                    </span>
                                                    <span 
                                                        v-else-if="getParsedLocation(log.location).source === 'local'"
                                                        class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600"
                                                    >
                                                        LAN
                                                    </span>
                                                </div>

                                                <!-- Baris 2: ISP Provider & Link Peta Google Maps -->
                                                <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 flex-wrap">
                                                    <!-- ISP Provider Badge -->
                                                    <span 
                                                        v-if="getParsedLocation(log.location).isp"
                                                        class="inline-flex items-center gap-1 font-medium text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700/60 px-2 py-0.5 rounded-md"
                                                    >
                                                        <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                                                        </svg>
                                                        {{ getParsedLocation(log.location).isp }}
                                                    </span>

                                                    <!-- Link Google Maps jika koordinat lat/lon tersedia -->
                                                    <a 
                                                        v-if="getParsedLocation(log.location).lat && getParsedLocation(log.location).lon"
                                                        :href="`https://www.google.com/maps?q=${getParsedLocation(log.location).lat},${getParsedLocation(log.location).lon}`"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="inline-flex items-center gap-1 font-semibold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 hover:underline transition-colors"
                                                        title="Buka titik koordinat di Google Maps"
                                                    >
                                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                        </svg>
                                                        Peta
                                                    </a>
                                                </div>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div class="flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300">
                                                <svg v-if="log.location" class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.242-4.243a8 8 0 1111.314 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                <span>{{ log.location || 'Tidak Diketahui' }}</span>
                                            </div>
                                        </template>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-slate-700 dark:text-slate-300 flex items-center gap-1.5" :title="log.user_agent">
                                            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            {{ formatUserAgent(log.user_agent) }}
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="logs.data.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                        <div class="flex flex-col items-center justify-center space-y-1">
                                            <span class="text-3xl mb-1">📋</span>
                                            <p class="font-bold text-slate-700 dark:text-slate-300">Tidak ada data log login</p>
                                            <p class="text-xs text-slate-400">Belum ada riwayat aktivitas login pada periode {{ formattedMonth }}.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <!-- Mobile View (Cards) -->
                        <div class="md:hidden divide-y divide-slate-100 dark:divide-slate-700">
                            <div v-for="log in logs.data" :key="`mobile-${log.id}`" class="p-4 space-y-4 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                <!-- Header: User & Time -->
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-emerald-100 flex items-center justify-center border border-emerald-200">
                                            <span class="text-emerald-700 font-bold text-sm">{{ log.user?.name?.charAt(0) || '?' }}</span>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-semibold text-slate-900 dark:text-white">
                                                {{ log.user?.name || 'User Dihapus' }}
                                            </div>
                                            <div class="text-xs text-slate-500 dark:text-slate-400 truncate w-32 sm:w-48">
                                                {{ log.user?.email || '-' }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-0.5">Waktu</div>
                                        <div class="text-xs text-slate-700 dark:text-slate-300">{{ formatDate(log.created_at) }}</div>
                                    </div>
                                </div>
                                
                                <!-- Body: Details -->
                                <div class="grid grid-cols-2 gap-3 bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border border-slate-100 dark:border-slate-700 text-xs">
                                    <div class="flex flex-col gap-1 col-span-2 sm:col-span-1">
                                        <span class="text-slate-400 font-medium uppercase tracking-wider text-[10px]">IP Address</span>
                                        <span class="font-mono text-slate-700 dark:text-slate-300 font-medium">{{ log.ip_address || 'N/A' }}</span>
                                    </div>
                                    
                                    <!-- Lokasi Section -->
                                    <div class="flex flex-col gap-1 col-span-2">
                                        <div class="flex items-center justify-between">
                                            <span class="text-slate-400 font-medium uppercase tracking-wider text-[10px]">Lokasi & Jaringan</span>
                                            <template v-if="getParsedLocation(log.location)">
                                                <span 
                                                    v-if="getParsedLocation(log.location).source === 'gps'"
                                                    class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200"
                                                >
                                                    GPS
                                                </span>
                                                <span 
                                                    v-else-if="getParsedLocation(log.location).source === 'ip'"
                                                    class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200"
                                                >
                                                    IP
                                                </span>
                                            </template>
                                        </div>

                                        <template v-if="getParsedLocation(log.location)">
                                            <div class="text-xs font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                                <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.242-4.243a8 8 0 1111.314 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                <span>{{ formatLocationTitle(getParsedLocation(log.location)) }}</span>
                                            </div>

                                            <div class="flex items-center gap-2 flex-wrap pt-0.5 text-[11px]">
                                                <span v-if="getParsedLocation(log.location).isp" class="bg-slate-200/60 dark:bg-slate-700 px-1.5 py-0.5 rounded text-slate-600 dark:text-slate-300 font-medium">
                                                    {{ getParsedLocation(log.location).isp }}
                                                </span>
                                                <a 
                                                    v-if="getParsedLocation(log.location).lat && getParsedLocation(log.location).lon"
                                                    :href="`https://www.google.com/maps?q=${getParsedLocation(log.location).lat},${getParsedLocation(log.location).lon}`"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-emerald-600 dark:text-emerald-400 font-semibold underline inline-flex items-center gap-0.5"
                                                >
                                                    Peta Google ↗
                                                </a>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <span class="text-slate-700 dark:text-slate-300 flex items-center gap-1">
                                                <svg v-if="log.location" class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.242-4.243a8 8 0 1111.314 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                <span class="truncate">{{ log.location || 'Tidak Diketahui' }}</span>
                                            </span>
                                        </template>
                                    </div>

                                    <div class="flex flex-col gap-1 col-span-2 pt-1 border-t border-slate-200 dark:border-slate-700">
                                        <span class="text-slate-400 font-medium uppercase tracking-wider text-[10px]">Perangkat / Browser</span>
                                        <span class="text-slate-700 dark:text-slate-300 flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            {{ formatUserAgent(log.user_agent) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-if="logs.data.length === 0" class="p-8 text-center text-slate-500 dark:text-slate-400">
                                <span class="text-3xl block mb-1">📋</span>
                                <p class="font-bold text-slate-700 dark:text-slate-300 text-sm">Tidak ada data log login</p>
                                <p class="text-xs text-slate-400 mt-0.5">Belum ada riwayat aktivitas login pada periode {{ formattedMonth }}.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700" v-if="logs.links && logs.data.length > 0">
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                            <span class="text-sm text-slate-500 dark:text-slate-400 text-center sm:text-left">
                                Menampilkan <span class="font-bold text-slate-700 dark:text-slate-300">{{ logs.from }}</span> sampai <span class="font-bold text-slate-700 dark:text-slate-300">{{ logs.to }}</span> dari <span class="font-bold text-slate-700 dark:text-slate-300">{{ logs.total }}</span> log
                            </span>
                            <div class="flex flex-wrap justify-center sm:justify-end gap-1.5">
                                <template v-for="(link, i) in logs.links" :key="i">
                                    <component
                                        :is="link.url ? 'a' : 'span'"
                                        :href="link.url"
                                        @click.prevent="link.url ? router.get(link.url) : null"
                                        v-html="link.label"
                                        class="px-3 py-1.5 rounded-lg text-sm transition-colors cursor-pointer border"
                                        :class="[
                                            link.active ? 'bg-emerald-600 text-white font-medium border-emerald-600 shadow-sm' : 
                                            !link.url ? 'text-slate-400 bg-slate-50 border-slate-200 dark:border-slate-700 dark:bg-slate-800/50 cursor-not-allowed' : 
                                            'text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700'
                                        ]"
                                    ></component>
                                </template>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
