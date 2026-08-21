<script setup>
import { ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    recapData: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) }
})

const filterPeriode = ref(props.filters.periode || 'bulan')
const filterBulan = ref(props.filters.bulan || new Date().getMonth() + 1)
const filterTahun = ref(props.filters.tahun || new Date().getFullYear())
const filterSemester = ref(props.filters.semester || (new Date().getMonth() + 1 <= 6 ? 1 : 2))

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

        <div class="w-full max-w-[1920px] px-4 sm:px-6 lg:px-8 mx-auto space-y-6 py-8">
            
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-6 relative overflow-hidden">
                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-200">Rekap Bacaan Al-Quran</h1>
                        <p class="text-slate-500 dark:text-slate-400 mt-1">Pantau hasil membaca harian (Tilawah & Murojaah) Anda.</p>
                    </div>
                    
                    <div class="flex flex-wrap items-center gap-3 bg-slate-50 dark:bg-slate-800/50 p-3 rounded-xl border border-slate-200 dark:border-slate-700">
                        <select v-model="filterPeriode" class="px-3 py-1.5 text-sm border-slate-300 dark:border-slate-600 rounded-lg focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100">
                            <option value="bulan">1 Bulan</option>
                            <option value="semester">1 Semester</option>
                            <option value="tahun">1 Tahun</option>
                        </select>
                        
                        <select v-if="filterPeriode === 'bulan'" v-model="filterBulan" class="px-3 py-1.5 text-sm border-slate-300 dark:border-slate-600 rounded-lg focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100">
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
                        
                        <select v-if="filterPeriode === 'semester'" v-model="filterSemester" class="px-3 py-1.5 text-sm border-slate-300 dark:border-slate-600 rounded-lg focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100">
                            <option value="1">Semester 1 (Jan-Jun)</option>
                            <option value="2">Semester 2 (Jul-Des)</option>
                        </select>
                        
                        <select v-model="filterTahun" class="px-3 py-1.5 text-sm border-slate-300 dark:border-slate-600 rounded-lg focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100">
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                
                <!-- Desktop Table View -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-slate-50 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th rowspan="2" class="px-4 py-3 font-bold text-slate-700 dark:text-slate-200 border-r border-slate-200 dark:border-slate-700 w-32">Tanggal</th>
                                <th colspan="2" class="px-4 py-2 font-bold text-center text-emerald-700 dark:text-emerald-400 bg-emerald-50/50 dark:bg-emerald-900/20 border-r border-slate-200 dark:border-slate-700">Tilawah (Membaca)</th>
                                <th colspan="2" class="px-4 py-2 font-bold text-center text-blue-700 dark:text-blue-400 bg-blue-50/50 dark:bg-blue-900/20">Murojaah</th>
                            </tr>
                            <tr>
                                <th class="px-4 py-2 font-semibold text-slate-600 dark:text-slate-300 border-r border-t border-slate-200 dark:border-slate-700 bg-emerald-50/20 dark:bg-emerald-900/10">Surat Awal</th>
                                <th class="px-4 py-2 font-semibold text-slate-600 dark:text-slate-300 border-r border-t border-slate-200 dark:border-slate-700 bg-emerald-50/20 dark:bg-emerald-900/10">Surat Akhir</th>
                                <th class="px-4 py-2 font-semibold text-slate-600 dark:text-slate-300 border-r border-t border-slate-200 dark:border-slate-700 bg-blue-50/20 dark:bg-blue-900/10">Surat Awal</th>
                                <th class="px-4 py-2 font-semibold text-slate-600 dark:text-slate-300 border-t border-slate-200 dark:border-slate-700 bg-blue-50/20 dark:bg-blue-900/10">Surat Akhir</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            <tr v-for="item in recapData" :key="item.tanggal" class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-300 border-r border-slate-100 dark:border-slate-700">
                                    {{ formatTanggal(item.tanggal) }}
                                </td>
                                
                                <!-- Tilawah -->
                                <td class="px-4 py-3 border-r border-slate-100 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                                    <template v-if="item.tilawah && item.tilawah.surat_awal">
                                        <span class="font-semibold">{{ item.tilawah.surat_awal }}</span> 
                                        <span class="text-xs text-slate-400">Ayat {{ item.tilawah.ayat_awal || '-' }}</span>
                                    </template>
                                    <span v-else class="text-slate-300 dark:text-slate-600">-</span>
                                </td>
                                <td class="px-4 py-3 border-r border-slate-100 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                                    <template v-if="item.tilawah && item.tilawah.surat_akhir">
                                        <span class="font-semibold">{{ item.tilawah.surat_akhir }}</span> 
                                        <span class="text-xs text-slate-400">Ayat {{ item.tilawah.ayat_akhir || '-' }}</span>
                                    </template>
                                    <span v-else class="text-slate-300 dark:text-slate-600">-</span>
                                </td>
                                
                                <!-- Murojaah -->
                                <td class="px-4 py-3 border-r border-slate-100 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                                    <template v-if="item.murojaah && item.murojaah.surat_awal">
                                        <span class="font-semibold">{{ item.murojaah.surat_awal }}</span> 
                                        <span class="text-xs text-slate-400">Ayat {{ item.murojaah.ayat_awal || '-' }}</span>
                                    </template>
                                    <span v-else class="text-slate-300 dark:text-slate-600">-</span>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                                    <template v-if="item.murojaah && item.murojaah.surat_akhir">
                                        <span class="font-semibold">{{ item.murojaah.surat_akhir }}</span> 
                                        <span class="text-xs text-slate-400">Ayat {{ item.murojaah.ayat_akhir || '-' }}</span>
                                    </template>
                                    <span v-else class="text-slate-300 dark:text-slate-600">-</span>
                                </td>
                            </tr>
                            
                            <tr v-if="recapData.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                                    Belum ada data bacaan untuk periode ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="md:hidden divide-y divide-slate-100 dark:divide-slate-700">
                    <div v-for="item in recapData" :key="item.tanggal + '-mobile'" class="p-4 space-y-4 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-2">
                            <span class="font-bold text-slate-700 dark:text-slate-200">{{ formatTanggal(item.tanggal) }}</span>
                        </div>
                        
                        <div class="space-y-4">
                            <!-- Tilawah -->
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 mb-2">Tilawah (Membaca)</h3>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div class="bg-emerald-50/50 dark:bg-emerald-900/20 p-2.5 rounded-lg border border-emerald-100 dark:border-emerald-800/30">
                                        <div class="text-[10px] text-slate-500 mb-1 font-semibold uppercase">Surat Awal</div>
                                        <template v-if="item.tilawah && item.tilawah.surat_awal">
                                            <div class="font-bold text-slate-700 dark:text-slate-300">{{ item.tilawah.surat_awal }}</div>
                                            <div class="text-xs text-slate-500 mt-0.5">Ayat {{ item.tilawah.ayat_awal || '-' }}</div>
                                        </template>
                                        <div v-else class="text-slate-400">-</div>
                                    </div>
                                    <div class="bg-emerald-50/50 dark:bg-emerald-900/20 p-2.5 rounded-lg border border-emerald-100 dark:border-emerald-800/30">
                                        <div class="text-[10px] text-slate-500 mb-1 font-semibold uppercase">Surat Akhir</div>
                                        <template v-if="item.tilawah && item.tilawah.surat_akhir">
                                            <div class="font-bold text-slate-700 dark:text-slate-300">{{ item.tilawah.surat_akhir }}</div>
                                            <div class="text-xs text-slate-500 mt-0.5">Ayat {{ item.tilawah.ayat_akhir || '-' }}</div>
                                        </template>
                                        <div v-else class="text-slate-400">-</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Murojaah -->
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400 mb-2">Murojaah</h3>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div class="bg-blue-50/50 dark:bg-blue-900/20 p-2.5 rounded-lg border border-blue-100 dark:border-blue-800/30">
                                        <div class="text-[10px] text-slate-500 mb-1 font-semibold uppercase">Surat Awal</div>
                                        <template v-if="item.murojaah && item.murojaah.surat_awal">
                                            <div class="font-bold text-slate-700 dark:text-slate-300">{{ item.murojaah.surat_awal }}</div>
                                            <div class="text-xs text-slate-500 mt-0.5">Ayat {{ item.murojaah.ayat_awal || '-' }}</div>
                                        </template>
                                        <div v-else class="text-slate-400">-</div>
                                    </div>
                                    <div class="bg-blue-50/50 dark:bg-blue-900/20 p-2.5 rounded-lg border border-blue-100 dark:border-blue-800/30">
                                        <div class="text-[10px] text-slate-500 mb-1 font-semibold uppercase">Surat Akhir</div>
                                        <template v-if="item.murojaah && item.murojaah.surat_akhir">
                                            <div class="font-bold text-slate-700 dark:text-slate-300">{{ item.murojaah.surat_akhir }}</div>
                                            <div class="text-xs text-slate-500 mt-0.5">Ayat {{ item.murojaah.ayat_akhir || '-' }}</div>
                                        </template>
                                        <div v-else class="text-slate-400">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="recapData.length === 0" class="p-8 text-center text-slate-500 dark:text-slate-400 text-sm">
                        Belum ada data bacaan untuk periode ini.
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
