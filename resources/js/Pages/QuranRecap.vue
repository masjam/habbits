<script setup>
import { ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    recapData: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) }
})

const activeTab = ref('tilawah')
const filterPeriode = ref(props.filters.periode || 'bulan')
const filterBulan = ref(props.filters.bulan || new Date().getMonth() + 1)
const filterTahun = ref(props.filters.tahun || new Date().getFullYear())
const filterSemester = ref(props.filters.semester || (new Date().getMonth() + 1 <= 6 ? 1 : 2))

const showMobileFilters = ref(false)

const currentYear = new Date().getFullYear();
const years = Array.from({length: 5}, (_, i) => currentYear - i);

const debounce = (fn, delay) => {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
};

const updateData = debounce(() => {
    router.get(route('quran.recap'), {
        periode: filterPeriode.value,
        bulan: filterBulan.value,
        tahun: filterTahun.value,
        semester: filterSemester.value
    }, { preserveState: true, preserveScroll: true })
}, 300)

watch([filterPeriode, filterBulan, filterTahun, filterSemester], () => {
    updateData()
})

const formatTanggal = (dateStr) => {
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Rekap Al-Quran" />

        <div class="w-full space-y-6 pb-8">
            
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-6 relative overflow-hidden">
                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-200">Rekap Bacaan Al-Quran</h1>
                        <p class="text-slate-500 dark:text-slate-400 mt-1">Pantau hasil membaca harian (Tilawah & Murojaah) Anda.</p>
                        
                        <!-- Mobile Filter Toggle Button -->
                        <button 
                            @click="showMobileFilters = !showMobileFilters" 
                            class="md:hidden flex items-center gap-2 px-3 py-1.5 mt-3 text-xs font-bold text-slate-700 dark:text-slate-200 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                            {{ showMobileFilters ? 'Sembunyikan Filter' : 'Tampilkan Filter' }}
                        </button>
                    </div>
                    
                    <div :class="['flex-wrap items-center gap-3 bg-slate-50 dark:bg-slate-800/50 p-3 rounded-xl border border-slate-200 dark:border-slate-700', showMobileFilters ? 'flex' : 'hidden md:flex']">
                        <select v-model="filterPeriode" class="px-3 py-1.5 text-sm border-slate-300 dark:border-slate-600 rounded-lg focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100 w-full sm:w-auto">
                            <option value="bulan">1 Bulan</option>
                            <option value="semester">1 Semester</option>
                            <option value="tahun">1 Tahun</option>
                        </select>
                        
                        <select v-if="filterPeriode === 'bulan'" v-model="filterBulan" class="px-3 py-1.5 text-sm border-slate-300 dark:border-slate-600 rounded-lg focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100 w-full sm:w-auto">
                            <option value="1">Januari</option>
                            <option value="2">Februari</option>
                            <option value="3">Maret</option>
                            <option value="4">April</option>
                            <option value="5">Mei</option>
                            <option value="6">Juni</option>
                            <option value="7">Juli</option>
                            <option value="8">Agustus</option>
                            <option value="9">September</option>
                            <option value="10">Oktober</option>
                            <option value="11">November</option>
                            <option value="12">Desember</option>
                        </select>
                        
                        <select v-if="filterPeriode === 'semester'" v-model="filterSemester" class="px-3 py-1.5 text-sm border-slate-300 dark:border-slate-600 rounded-lg focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100 w-full sm:w-auto">
                            <option value="1">Semester 1 (Jan-Jun)</option>
                            <option value="2">Semester 2 (Jul-Des)</option>
                        </select>
                        
                        <select v-model="filterTahun" class="px-3 py-1.5 text-sm border-slate-300 dark:border-slate-600 rounded-lg focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100 w-full sm:w-auto">
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>
                </div>
            </div>

            
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                
                <!-- Tab Switcher -->
                <div class="flex border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
                    <button 
                        @click="activeTab = 'tilawah'" 
                        :class="['flex-1 py-3.5 px-4 text-sm font-bold text-center border-b-2 transition-colors', activeTab === 'tilawah' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400 bg-white dark:bg-slate-800' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700/50']"
                    >
                        Tilawah (Membaca)
                    </button>
                    <button 
                        @click="activeTab = 'murojaah'" 
                        :class="['flex-1 py-3.5 px-4 text-sm font-bold text-center border-b-2 transition-colors', activeTab === 'murojaah' ? 'border-blue-500 text-blue-600 dark:text-blue-400 bg-white dark:bg-slate-800' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700/50']"
                    >
                        Murojaah
                    </button>
                </div>

                <!-- Unified Table View (Desktop & Mobile) -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-[11px] sm:text-sm whitespace-nowrap">
                        <thead class="bg-slate-50 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="px-1.5 sm:px-3 py-2 sm:py-3 font-bold text-slate-700 dark:text-slate-200 border-r border-slate-200 dark:border-slate-700 w-16 sm:w-28 text-center sm:text-left">Tgl</th>
                                <th class="px-1.5 sm:px-3 py-2 sm:py-3 font-bold text-slate-700 dark:text-slate-200 border-r border-slate-200 dark:border-slate-700 w-10 sm:w-20 text-center">Dur.</th>
                                <th class="px-1.5 sm:px-3 py-2 sm:py-3 font-bold text-slate-700 dark:text-slate-200 border-r border-slate-200 dark:border-slate-700 max-w-[90px] truncate sm:max-w-none sm:truncate-none">Surat Awal</th>
                                <th class="px-1.5 sm:px-3 py-2 sm:py-3 font-bold text-slate-700 dark:text-slate-200 max-w-[90px] truncate sm:max-w-none sm:truncate-none">Surat Akhir</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            <tr v-for="item in recapData" :key="item.tanggal" class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="px-1.5 sm:px-3 py-2 sm:py-3 font-medium text-slate-700 dark:text-slate-300 border-r border-slate-100 dark:border-slate-700 text-center sm:text-left">
                                    {{ formatTanggal(item.tanggal).replace(' 2026', '') }}
                                </td>
                                
                                <template v-if="activeTab === 'tilawah'">
                                    <td class="px-1.5 sm:px-3 py-2 sm:py-3 border-r border-slate-100 dark:border-slate-700 text-emerald-600 dark:text-emerald-400 font-bold text-center">
                                        <span v-if="item.tilawah && item.tilawah.durasi">{{ item.tilawah.durasi }}m</span>
                                        <span v-else class="text-slate-300 dark:text-slate-600">-</span>
                                    </td>
                                    <td class="px-1.5 sm:px-3 py-1.5 sm:py-2 border-r border-slate-100 dark:border-slate-700 text-slate-600 dark:text-slate-400 max-w-[90px] truncate sm:max-w-none sm:truncate-none">
                                        <template v-if="item.tilawah && item.tilawah.surat_awal">
                                            <div class="font-bold text-slate-800 dark:text-slate-200 truncate">{{ item.tilawah.surat_awal }}</div> 
                                            <div class="text-[9px] sm:text-[11px] text-slate-400">Ayat {{ item.tilawah.ayat_awal || '-' }}</div>
                                        </template>
                                        <span v-else class="text-slate-300 dark:text-slate-600">-</span>
                                    </td>
                                    <td class="px-1.5 sm:px-3 py-1.5 sm:py-2 text-slate-600 dark:text-slate-400 max-w-[90px] truncate sm:max-w-none sm:truncate-none">
                                        <template v-if="item.tilawah && item.tilawah.surat_akhir">
                                            <div class="font-bold text-slate-800 dark:text-slate-200 truncate">{{ item.tilawah.surat_akhir }}</div> 
                                            <div class="text-[9px] sm:text-[11px] text-slate-400">Ayat {{ item.tilawah.ayat_akhir || '-' }}</div>
                                        </template>
                                        <span v-else class="text-slate-300 dark:text-slate-600">-</span>
                                    </td>
                                </template>
                                
                                <template v-else>
                                    <td class="px-1.5 sm:px-3 py-2 sm:py-3 border-r border-slate-100 dark:border-slate-700 text-blue-600 dark:text-blue-400 font-bold text-center">
                                        <span v-if="item.murojaah && item.murojaah.durasi">{{ item.murojaah.durasi }}m</span>
                                        <span v-else class="text-slate-300 dark:text-slate-600">-</span>
                                    </td>
                                    <td class="px-1.5 sm:px-3 py-1.5 sm:py-2 border-r border-slate-100 dark:border-slate-700 text-slate-600 dark:text-slate-400 max-w-[90px] truncate sm:max-w-none sm:truncate-none">
                                        <template v-if="item.murojaah && item.murojaah.surat_awal">
                                            <div class="font-bold text-slate-800 dark:text-slate-200 truncate">{{ item.murojaah.surat_awal }}</div> 
                                            <div class="text-[9px] sm:text-[11px] text-slate-400">Ayat {{ item.murojaah.ayat_awal || '-' }}</div>
                                        </template>
                                        <span v-else class="text-slate-300 dark:text-slate-600">-</span>
                                    </td>
                                    <td class="px-1.5 sm:px-3 py-1.5 sm:py-2 text-slate-600 dark:text-slate-400 max-w-[90px] truncate sm:max-w-none sm:truncate-none">
                                        <template v-if="item.murojaah && item.murojaah.surat_akhir">
                                            <div class="font-bold text-slate-800 dark:text-slate-200 truncate">{{ item.murojaah.surat_akhir }}</div> 
                                            <div class="text-[9px] sm:text-[11px] text-slate-400">Ayat {{ item.murojaah.ayat_akhir || '-' }}</div>
                                        </template>
                                        <span v-else class="text-slate-300 dark:text-slate-600">-</span>
                                    </td>
                                </template>
                            </tr>
                            
                            <tr v-if="recapData.length === 0">
                                <td colspan="4" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                                    Belum ada data bacaan untuk periode ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
