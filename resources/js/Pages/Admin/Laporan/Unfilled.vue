<script setup>
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { watch, ref, computed } from 'vue'

const props = defineProps({
    unfilledData: {
        type: Array,
        required: true
    },
    namaBulan: {
        type: String,
        required: true
    },
    filters: {
        type: Object,
        required: true
    }
})

const selectedMonth = ref(props.filters.month)
const selectedYear = ref(props.filters.year)
const searchQuery = ref('')

const filteredUnfilledData = computed(() => {
    if (!searchQuery.value) return props.unfilledData;
    const lowerQuery = searchQuery.value.toLowerCase();
    return props.unfilledData.filter(item => 
        item.name.toLowerCase().includes(lowerQuery)
    );
});

// Helper: Array tahun dari (sekarang - 1) sampai (sekarang + 1)
const years = Array.from({length: 3}, (_, i) => new Date().getFullYear() - 1 + i)
const months = [
    { value: 1, label: 'Januari' }, { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' }, { value: 4, label: 'April' },
    { value: 5, label: 'Mei' }, { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' }, { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' }, { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' }, { value: 12, label: 'Desember' },
]

const applyFilter = () => {
    router.get(
        route('admin.laporan.unfilled'),
        { month: selectedMonth.value, year: selectedYear.value },
        { preserveState: true, replace: true }
    )
}
</script>

<template>
    <Head title="Cek Pegawai Belum Isi Habit" />
    <AuthenticatedLayout>
        <div class="px-4 sm:px-6 lg:px-8 py-8 w-full">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">
                        Cek Belum Isi Habit
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Daftar pegawai yang bolong mengisi habit pada bulan {{ namaBulan }}
                    </p>
                </div>
                
            </div>

            <!-- Filter Section -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5 mb-6">
                <div class="flex flex-col sm:flex-row gap-4 items-end">
                    <div class="w-full sm:w-56">
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5 uppercase tracking-wide">Bulan</label>
                        <select v-model="selectedMonth" @change="applyFilter" class="w-full rounded-lg border-slate-300 dark:border-slate-600 text-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-slate-700 dark:text-white shadow-sm">
                            <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                    </div>
                    <div class="w-full sm:w-48">
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5 uppercase tracking-wide">Tahun</label>
                        <select v-model="selectedYear" @change="applyFilter" class="w-full rounded-lg border-slate-300 dark:border-slate-600 text-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-slate-700 dark:text-white shadow-sm">
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-3 mt-4 md:mt-0 ml-auto">
                        <div class="relative w-full sm:w-64">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-slate-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input type="text" v-model="searchQuery" placeholder="Cari nama pegawai..." 
                                class="w-full pl-10 rounded-lg border-slate-300 dark:border-slate-600 text-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-slate-700 dark:text-white shadow-sm">
                        </div>
                        <a :href="route('admin.laporan.unfilled.export', { month: selectedMonth, year: selectedYear })"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-lg shadow-sm transition-all focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Export Excel
                        </a>
                    </div>
                </div>

            </div>

            <!-- Table Section -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-md border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300 whitespace-nowrap lg:whitespace-normal">
                        <thead class="bg-slate-100 dark:bg-slate-900/80 text-slate-700 dark:text-slate-200 font-bold border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="px-5 py-4 w-12 text-center uppercase tracking-wider text-xs">#</th>
                                <th class="px-5 py-4 uppercase tracking-wider text-xs w-64">Nama Pegawai</th>
                                <th class="px-5 py-4 text-center uppercase tracking-wider text-xs w-36">Total Bolong</th>
                                <th class="px-5 py-4 uppercase tracking-wider text-xs">Tanggal Belum Diisi (Skor = 0)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            <tr v-for="(item, index) in filteredUnfilledData" :key="item.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors duration-150">
                                <td class="px-5 py-4 text-center text-slate-500">{{ index + 1 }}</td>
                                <td class="px-5 py-4">
                                    <div class="font-bold text-slate-800 dark:text-slate-100 text-[15px]">{{ item.name }}</div>
                                    <div v-if="item.divisi" class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">{{ item.divisi }}</div>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <span class="inline-flex items-center justify-center px-3 py-1.5 rounded-md text-[13px] font-bold bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-400 border border-rose-200 dark:border-rose-800/50 shadow-sm">
                                        {{ item.total_unfilled }} Hari
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <span v-for="date in item.unfilled_dates" :key="date" 
                                            class="flex items-center justify-center w-8 h-8 rounded-md bg-white dark:bg-slate-800 text-sm font-semibold text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-600 shadow-sm hover:border-rose-400 hover:text-rose-600 dark:hover:border-rose-500 transition-colors cursor-default">
                                            {{ date }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="unfilledData.length === 0">
                                <td colspan="4" class="px-5 py-12 text-center text-slate-500 dark:text-slate-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-12 h-12 text-emerald-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span class="font-medium text-lg text-emerald-600 dark:text-emerald-400">Alhamdulillah!</span>
                                        <span class="mt-1">Semua pegawai aktif telah mengisi habit (tidak ada yang bolong) pada bulan ini.</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else-if="filteredUnfilledData.length === 0">
                                <td colspan="4" class="px-5 py-12 text-center text-slate-500 dark:text-slate-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-12 h-12 text-slate-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                        <span class="mt-1">Tidak ada pegawai yang cocok dengan kata kunci <strong>"{{ searchQuery }}"</strong>.</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
