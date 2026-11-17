<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import axios from 'axios'

const props = defineProps({
    initialMetrics: {
        type: Object,
        default: () => ({})
    }
})

// ─── Reactive State ──────────────────────────────────────────────────────────
const metrics = ref(props.initialMetrics || {})
const isRefreshing = ref(false)
const autoRefresh = ref(false)
const autoRefreshInterval = ref(5) // detik
const lastUpdatedTime = ref(props.initialMetrics?.checked_at || '')
const errorMessage = ref('')
let timer = null

// ─── Fetch / Refresh Function ────────────────────────────────────────────────
const refreshMetrics = async () => {
    if (isRefreshing.value) return
    isRefreshing.value = true
    errorMessage.value = ''

    try {
        const response = await axios.get(route('admin.system.monitor.api'))
        if (response.data && response.data.success) {
            metrics.value = response.data.metrics
            lastUpdatedTime.value = response.data.metrics?.checked_at || new Date().toLocaleTimeString('id-ID')
        }
    } catch (err) {
        errorMessage.value = 'Gagal memperbarui metrik: ' + (err.response?.data?.message || err.message)
    } finally {
        isRefreshing.value = false
    }
}

// ─── Auto-Refresh Polling Handler ───────────────────────────────────────────
const toggleAutoRefresh = () => {
    autoRefresh.value = !autoRefresh.value
    if (autoRefresh.value) {
        startPolling()
    } else {
        stopPolling()
    }
}

const startPolling = () => {
    stopPolling()
    timer = setInterval(() => {
        refreshMetrics()
    }, autoRefreshInterval.value * 1000)
}

const stopPolling = () => {
    if (timer) {
        clearInterval(timer)
        timer = null
    }
}

onMounted(() => {
    // If props was empty, refresh once
    if (!props.initialMetrics || Object.keys(props.initialMetrics).length === 0) {
        refreshMetrics()
    }
})

onUnmounted(() => {
    stopPolling()
})

// ─── Helpers for Color Coding ───────────────────────────────────────────────
const getUsageColor = (percent) => {
    if (percent >= 85) return {
        bar: 'bg-rose-500',
        text: 'text-rose-600 dark:text-rose-400',
        bg: 'bg-rose-50 dark:bg-rose-950/40',
        border: 'border-rose-200 dark:border-rose-800'
    }
    if (percent >= 70) return {
        bar: 'bg-amber-500',
        text: 'text-amber-600 dark:text-amber-400',
        bg: 'bg-amber-50 dark:bg-amber-950/40',
        border: 'border-amber-200 dark:border-amber-800'
    }
    return {
        bar: 'bg-emerald-500',
        text: 'text-emerald-600 dark:text-emerald-400',
        bg: 'bg-emerald-50 dark:bg-emerald-950/40',
        border: 'border-emerald-200 dark:border-emerald-800'
    }
}

// Computed Shortcut Data
const cpu = computed(() => metrics.value.cpu || {})
const memory = computed(() => metrics.value.memory || {})
const storage = computed(() => metrics.value.storage || {})
const database = computed(() => metrics.value.database || {})
const server = computed(() => metrics.value.server || {})
const application = computed(() => metrics.value.application || {})

const cpuColor = computed(() => getUsageColor(cpu.value.usage_percent || 0))
const ramColor = computed(() => getUsageColor(memory.value.system?.used_percent || 0))
const diskColor = computed(() => getUsageColor(storage.value.disk?.used_percent || 0))
</script>

<template>
    <Head title="Pemantauan Sistem (System Monitor)" />

    <AuthenticatedLayout>
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- ─── HEADER BAR ───────────────────────────────────────────────────────── -->
            <div class="bg-surface rounded-2xl p-5 sm:p-6 border border-theme shadow-xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 dark:bg-emerald-950/70 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                </span>
                                Superadmin Only
                            </span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                Terakhir diperbarui: <strong class="text-slate-700 dark:text-slate-200">{{ lastUpdatedTime || 'Memuat...' }}</strong>
                            </span>
                        </div>
                        <h1 class="text-xl sm:text-2xl font-black text-slate-800 dark:text-slate-100 flex items-center gap-2.5 tracking-tight">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center text-white shadow-sm shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M3 9h2m-2 6h2m14-6h2m-2 6h2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                                </svg>
                            </div>
                            <span>Pemantauan Sistem (System Monitor)</span>
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                            Pantau utilisasi beban CPU, penggunaan RAM fisik, kapasitas penyimpanan storage, performa database, dan kesehatan runtime server secara realtime.
                        </p>
                    </div>

                    <!-- Actions & Controls -->
                    <div class="flex items-center flex-wrap gap-2.5 shrink-0">
                        <!-- Auto Refresh Switch -->
                        <button
                            type="button"
                            @click="toggleAutoRefresh"
                            :class="[
                                'inline-flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-bold transition-all border cursor-pointer',
                                autoRefresh 
                                    ? 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-300 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300' 
                                    : 'bg-card-subtle border-theme text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
                            ]"
                            title="Aktifkan pembaruan otomatis setiap 5 detik"
                        >
                            <span class="w-2 h-2 rounded-full" :class="autoRefresh ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'" />
                            <span>Auto Refresh (5s): <strong class="uppercase">{{ autoRefresh ? 'ON' : 'OFF' }}</strong></span>
                        </button>

                        <!-- Refresh Button -->
                        <button
                            type="button"
                            @click="refreshMetrics"
                            :disabled="isRefreshing"
                            class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white transition-all shadow-xs cursor-pointer disabled:opacity-50"
                        >
                            <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': isRefreshing }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span>{{ isRefreshing ? 'Menyegarkan...' : 'Segarkan Data' }}</span>
                        </button>
                    </div>
                </div>

                <!-- Error Alert If Any -->
                <div v-if="errorMessage" class="mt-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs flex items-center justify-between">
                    <span>{{ errorMessage }}</span>
                    <button @click="errorMessage = ''" class="font-bold underline text-xs">Tutup</button>
                </div>
            </div>

            <!-- ─── 4 UTAMA KPI CARDS (CPU, RAM, DISK, DB) ────────────────────────── -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                <!-- 1. CPU LOAD CARD -->
                <div class="bg-surface rounded-2xl p-5 border border-theme shadow-xs space-y-3 relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Beban CPU</span>
                        <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xs">
                            ⚡
                        </div>
                    </div>
                    <div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl font-black text-slate-800 dark:text-slate-100 tracking-tight">
                                {{ cpu.usage_percent }}%
                            </span>
                            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                ({{ cpu.cores }} Cores)
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500 truncate mt-0.5" :title="cpu.model">
                            {{ cpu.model || 'CPU Processor' }}
                        </p>
                    </div>

                    <!-- Progress Bar -->
                    <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2.5 overflow-hidden">
                        <div
                            class="h-2.5 rounded-full transition-all duration-500"
                            :class="cpuColor.bar"
                            :style="{ width: `${Math.min(100, Math.max(0, cpu.usage_percent || 0))}%` }"
                        />
                    </div>

                    <!-- Load Average (1m, 5m, 15m) -->
                    <div class="pt-2 border-t border-subtle flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                        <span>Load Avg:</span>
                        <span v-if="cpu.load_average" class="font-mono font-semibold text-slate-700 dark:text-slate-300">
                            {{ cpu.load_average['1m'] }} / {{ cpu.load_average['5m'] }} / {{ cpu.load_average['15m'] }}
                        </span>
                        <span v-else class="font-mono text-xs text-slate-400">Windows Task Rate</span>
                    </div>
                </div>

                <!-- 2. RAM / MEMORY CARD -->
                <div class="bg-surface rounded-2xl p-5 border border-theme shadow-xs space-y-3 relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">RAM Sistem</span>
                        <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-xs">
                            🧠
                        </div>
                    </div>
                    <div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl font-black text-slate-800 dark:text-slate-100 tracking-tight">
                                {{ memory.system?.used_percent || 0 }}%
                            </span>
                            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                {{ memory.system?.used_formatted }} / {{ memory.system?.total_formatted }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                            Bebas: <strong class="text-slate-700 dark:text-slate-300">{{ memory.system?.free_formatted }}</strong>
                        </p>
                    </div>

                    <!-- Progress Bar -->
                    <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2.5 overflow-hidden">
                        <div
                            class="h-2.5 rounded-full transition-all duration-500"
                            :class="ramColor.bar"
                            :style="{ width: `${Math.min(100, Math.max(0, memory.system?.used_percent || 0))}%` }"
                        />
                    </div>

                    <!-- Sub PHP Memory Usage -->
                    <div class="pt-2 border-t border-subtle flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                        <span>PHP Memory:</span>
                        <span class="font-mono font-semibold text-slate-700 dark:text-slate-300">
                            {{ memory.php?.current_formatted }} / {{ memory.php?.limit }}
                        </span>
                    </div>
                </div>

                <!-- 3. STORAGE / DISK CARD -->
                <div class="bg-surface rounded-2xl p-5 border border-theme shadow-xs space-y-3 relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Disk Storage</span>
                        <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-xs">
                            💾
                        </div>
                    </div>
                    <div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl font-black text-slate-800 dark:text-slate-100 tracking-tight">
                                {{ storage.disk?.used_percent || 0 }}%
                            </span>
                            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                {{ storage.disk?.used_formatted }} / {{ storage.disk?.total_formatted }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                            Tersisa: <strong class="text-slate-700 dark:text-slate-300">{{ storage.disk?.free_formatted }}</strong>
                        </p>
                    </div>

                    <!-- Progress Bar -->
                    <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2.5 overflow-hidden">
                        <div
                            class="h-2.5 rounded-full transition-all duration-500"
                            :class="diskColor.bar"
                            :style="{ width: `${Math.min(100, Math.max(0, storage.disk?.used_percent || 0))}%` }"
                        />
                    </div>

                    <div class="pt-2 border-t border-subtle flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                        <span>Status Ruang:</span>
                        <span class="font-semibold" :class="diskColor.text">
                            {{ (storage.disk?.used_percent || 0) > 85 ? 'Kapasitas Kritis' : 'Normal & Sehat' }}
                        </span>
                    </div>
                </div>

                <!-- 4. DATABASE METRICS CARD -->
                <div class="bg-surface rounded-2xl p-5 border border-theme shadow-xs space-y-3 relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Database Status</span>
                        <div class="w-8 h-8 rounded-xl bg-teal-50 dark:bg-teal-950/60 text-teal-600 dark:text-teal-400 flex items-center justify-center font-bold text-xs">
                            🗄️
                        </div>
                    </div>
                    <div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl font-black text-slate-800 dark:text-slate-100 tracking-tight">
                                {{ database.size_formatted || '0 B' }}
                            </span>
                            <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                {{ database.connected ? '● Terhubung' : '✕ Terputus' }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                            Driver: <strong class="uppercase text-slate-700 dark:text-slate-300">{{ database.driver }}</strong> ({{ database.tables_count }} Tabel)
                        </p>
                    </div>

                    <!-- Progress Bar (Ping Latency indicator) -->
                    <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2.5 overflow-hidden">
                        <div
                            class="h-2.5 rounded-full bg-teal-500 transition-all duration-500"
                            :style="{ width: `${Math.min(100, Math.max(10, (database.latency_ms || 1) * 10))}%` }"
                        />
                    </div>

                    <div class="pt-2 border-t border-subtle flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                        <span>Query Latency:</span>
                        <span class="font-mono font-semibold text-slate-700 dark:text-slate-300">
                            {{ database.latency_ms ? `${database.latency_ms} ms` : '< 1 ms' }}
                        </span>
                    </div>
                </div>

            </div>

            <!-- ─── DETAIL GRID ROW 1: STORAGE DIRECTORIES & TOP DATABASE TABLES ────── -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- KOTAK 1: RINCIAN PENGGUNAAN STORAGE APLIKASI -->
                <div class="bg-surface rounded-2xl p-5 sm:p-6 border border-theme shadow-xs space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-subtle">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 flex items-center justify-center text-sm font-bold">
                                📁
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Rincian Storage Direktori Aplikasi</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Ukuran file media, logs, cache, dan aset build</p>
                            </div>
                        </div>
                        <Link
                            :href="route('admin.settings.hr', { tab: 'maintenance' })"
                            class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:underline"
                        >
                            <span>Pembersihan Foto</span>
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </Link>
                    </div>

                    <div class="space-y-3">
                        <div 
                            v-for="(dir, key) in storage.directories || {}" 
                            :key="key"
                            class="p-3.5 rounded-xl bg-card-subtle border border-theme/80 flex items-center justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate">{{ dir.name }}</p>
                                <p class="text-[11px] font-mono text-slate-400 dark:text-slate-500 truncate" :title="dir.path">{{ dir.path }}</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-white dark:bg-slate-800 border border-theme text-slate-700 dark:text-slate-200 shrink-0">
                                {{ dir.formatted }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- KOTAK 2: TABEL DATABASE TERBESAR -->
                <div class="bg-surface rounded-2xl p-5 sm:p-6 border border-theme shadow-xs space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-subtle">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-teal-100 dark:bg-teal-950/60 text-teal-700 dark:text-teal-300 flex items-center justify-center text-sm font-bold">
                                📊
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Tabel Database Terbesar</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Urutan tabel dengan alokasi data & indeks tertinggi</p>
                            </div>
                        </div>
                        <span class="text-xs font-mono text-slate-500 dark:text-slate-400">
                            Total: {{ database.size_formatted }}
                        </span>
                    </div>

                    <!-- Table List -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-subtle text-slate-400 dark:text-slate-500">
                                    <th class="pb-2 font-bold uppercase tracking-wider">Nama Tabel</th>
                                    <th class="pb-2 font-bold uppercase tracking-wider text-right">Est. Baris</th>
                                    <th class="pb-2 font-bold uppercase tracking-wider text-right">Data</th>
                                    <th class="pb-2 font-bold uppercase tracking-wider text-right">Indeks</th>
                                    <th class="pb-2 font-bold uppercase tracking-wider text-right">Total Ukuran</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-subtle font-mono">
                                <tr v-for="t in database.top_tables || []" :key="t.name" class="hover:bg-slate-50 dark:hover:bg-slate-900/40">
                                    <td class="py-2.5 font-bold text-slate-800 dark:text-slate-200">{{ t.name }}</td>
                                    <td class="py-2.5 text-right text-slate-600 dark:text-slate-400">{{ Number(t.rows).toLocaleString() }}</td>
                                    <td class="py-2.5 text-right text-slate-500">{{ t.data_formatted }}</td>
                                    <td class="py-2.5 text-right text-slate-500">{{ t.index_formatted }}</td>
                                    <td class="py-2.5 text-right font-black text-slate-700 dark:text-slate-100">{{ t.size_formatted }}</td>
                                </tr>
                                <tr v-if="!database.top_tables || database.top_tables.length === 0">
                                    <td colspan="5" class="py-4 text-center text-slate-400 font-sans">
                                        Data tabel tidak tersedia untuk driver database ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Ringkasan Jumlah Record Utama -->
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 pt-2 border-t border-subtle text-center text-xs">
                        <div class="p-2 rounded-xl bg-card-subtle">
                            <span class="text-[10px] text-slate-400 block uppercase">Pegawai</span>
                            <strong class="font-mono text-sm text-slate-800 dark:text-slate-200">{{ database.row_counts?.users || 0 }}</strong>
                        </div>
                        <div class="p-2 rounded-xl bg-card-subtle">
                            <span class="text-[10px] text-slate-400 block uppercase">Habit Logs</span>
                            <strong class="font-mono text-sm text-slate-800 dark:text-slate-200">{{ database.row_counts?.habit_logs || 0 }}</strong>
                        </div>
                        <div class="p-2 rounded-xl bg-card-subtle">
                            <span class="text-[10px] text-slate-400 block uppercase">Presensi</span>
                            <strong class="font-mono text-sm text-slate-800 dark:text-slate-200">{{ database.row_counts?.attendances || 0 }}</strong>
                        </div>
                        <div class="p-2 rounded-xl bg-card-subtle">
                            <span class="text-[10px] text-slate-400 block uppercase">Sesi Aktif</span>
                            <strong class="font-mono text-sm text-emerald-600 dark:text-emerald-400">{{ database.row_counts?.active_sessions || 0 }}</strong>
                        </div>
                        <div class="p-2 rounded-xl bg-card-subtle col-span-2 sm:col-span-1">
                            <span class="text-[10px] text-slate-400 block uppercase">Failed Jobs</span>
                            <strong class="font-mono text-sm" :class="database.row_counts?.failed_jobs > 0 ? 'text-rose-500' : 'text-slate-800 dark:text-slate-200'">{{ database.row_counts?.failed_jobs || 0 }}</strong>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ─── DETAIL GRID ROW 2: SERVER ENVIRONMENT & RUNTIME CONFIG ─────────── -->
            <div class="bg-surface rounded-2xl p-5 sm:p-6 border border-theme shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-subtle">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 flex items-center justify-center text-sm font-bold">
                            ⚙️
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Informasi Server, PHP & Runtime Environment</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Konfigurasi perangkat lunak server, batas memori, dan status ekstensi</p>
                        </div>
                    </div>
                    <span class="text-xs font-mono font-bold px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                        PHP {{ server.php_version }} • Laravel {{ server.laravel_version }}
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
                    
                    <!-- Spesifikasi OS & Server -->
                    <div class="space-y-2 p-3.5 rounded-xl bg-card-subtle border border-theme/60">
                        <h4 class="font-bold text-slate-700 dark:text-slate-300 text-xs flex items-center gap-1.5">
                            <span>🖥️</span> Sistem Operasi & Uptime
                        </h4>
                        <div class="space-y-1.5 pt-1 text-slate-600 dark:text-slate-400">
                            <div class="flex justify-between">
                                <span>OS Platform:</span>
                                <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ server.os_family }}</strong>
                            </div>
                            <div class="flex justify-between">
                                <span>OS Kernel:</span>
                                <strong class="text-slate-800 dark:text-slate-200 font-mono truncate max-w-[180px]" :title="server.os_name">{{ server.os_name }}</strong>
                            </div>
                            <div class="flex justify-between">
                                <span>Web Server:</span>
                                <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ server.server_software }}</strong>
                            </div>
                            <div class="flex justify-between">
                                <span>System Uptime:</span>
                                <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ server.server_uptime }}</strong>
                            </div>
                            <div class="flex justify-between">
                                <span>Waktu Server:</span>
                                <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ server.current_time }}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Konfigurasi Limit PHP & OPcache -->
                    <div class="space-y-2 p-3.5 rounded-xl bg-card-subtle border border-theme/60">
                        <h4 class="font-bold text-slate-700 dark:text-slate-300 text-xs flex items-center gap-1.5">
                            <span>⚡</span> PHP Runtime & OPcache
                        </h4>
                        <div class="space-y-1.5 pt-1 text-slate-600 dark:text-slate-400">
                            <div class="flex justify-between">
                                <span>Memory Limit:</span>
                                <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ memory.php?.limit }}</strong>
                            </div>
                            <div class="flex justify-between">
                                <span>Max Execution Time:</span>
                                <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ server.max_execution_time }}</strong>
                            </div>
                            <div class="flex justify-between">
                                <span>Upload Max Filesize:</span>
                                <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ server.upload_max_filesize }}</strong>
                            </div>
                            <div class="flex justify-between">
                                <span>Post Max Size:</span>
                                <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ server.post_max_size }}</strong>
                            </div>
                            <div class="flex justify-between items-center">
                                <span>OPcache Status:</span>
                                <span class="font-bold px-2 py-0.5 rounded text-[11px]" :class="server.opcache?.enabled ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-400'">
                                    {{ server.opcache?.enabled ? `Aktif (${server.opcache.hit_rate || 'OK'})` : 'Nonaktif' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Status Driver & Ekstensi Kunci -->
                    <div class="space-y-2 p-3.5 rounded-xl bg-card-subtle border border-theme/60">
                        <h4 class="font-bold text-slate-700 dark:text-slate-300 text-xs flex items-center gap-1.5">
                            <span>🧩</span> Ekstensi PHP Esensial
                        </h4>
                        <div class="grid grid-cols-3 gap-1.5 pt-1">
                            <div
                                v-for="(installed, ext) in server.extensions || {}"
                                :key="ext"
                                :class="[
                                    'px-2 py-1.5 rounded-lg text-center font-mono text-[11px] font-bold border transition-colors',
                                    installed 
                                        ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800' 
                                        : 'bg-slate-100 dark:bg-slate-800 text-slate-400 border-slate-200 dark:border-slate-700'
                                ]"
                            >
                                <span class="block truncate">{{ ext }}</span>
                                <span class="text-[9px] uppercase font-sans">{{ installed ? '✓ Ada' : '✕ Tidak' }}</span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Footer System State -->
                <div class="pt-3 border-t border-subtle flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500">
                    <div class="flex items-center gap-4 flex-wrap">
                        <span>Cache: <strong class="text-slate-700 dark:text-slate-200 uppercase">{{ application.cache_driver }}</strong></span>
                        <span>Session: <strong class="text-slate-700 dark:text-slate-200 uppercase">{{ application.session_driver }}</strong></span>
                        <span>Queue: <strong class="text-slate-700 dark:text-slate-200 uppercase">{{ application.queue_connection }}</strong></span>
                        <span>WebPush VAPID: <strong :class="application.vapid_configured ? 'text-emerald-600' : 'text-slate-400'">{{ application.vapid_configured ? 'Aktif' : 'Belum' }}</strong></span>
                        <span>Maintenance: <strong :class="application.maintenance_mode ? 'text-amber-600' : 'text-slate-700 dark:text-slate-200'">{{ application.maintenance_mode ? 'AKTIF' : 'Nonaktif' }}</strong></span>
                    </div>
                    <span class="text-[11px] text-slate-400">
                        Habit Tracker • SAHABAT SDAM
                    </span>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
