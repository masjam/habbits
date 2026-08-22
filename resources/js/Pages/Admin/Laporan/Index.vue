<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
    leaderboard: Object,
    targetBulanan: Number,
    skorMaksimalSebulan: Number,
    namaBulan: String,
    filters: Object,
})

import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

const form = useForm({
    target: props.targetBulanan
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
const searchQuery = ref(props.filters.search || '')
const perPage = ref(props.filters.per_page || 10)

let searchTimeout = null
watch(searchQuery, (newVal) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get(route('admin.laporan'), { month: selectedMonth.value, year: selectedYear.value, search: newVal, per_page: perPage.value }, { preserveState: true, preserveScroll: true, replace: true })
    }, 300)
})

watch([selectedMonth, selectedYear, perPage], () => {
    router.get(route('admin.laporan'), { month: selectedMonth.value, year: selectedYear.value, search: searchQuery.value, per_page: perPage.value }, { preserveState: true, preserveScroll: true })
})

const saveTarget = () => {
    form.post(route('admin.laporan.target'), {
        preserveScroll: true
    })
}

// Logic pewarnaan berdasarkan persentase
const getRowClass = (persentase) => {
    if (persentase <= 30) {
        return 'bg-rose-50 hover:bg-rose-100/80 border-l-4 border-rose-500' // 0-30%
    } else if (persentase <= 50) {
        return 'bg-amber-50 hover:bg-amber-100/80 border-l-4 border-amber-500' // 30.1-50%
    } else if (persentase < props.targetBulanan) {
        return 'bg-emerald-50/50 hover:bg-emerald-50 border-l-4 border-emerald-300' // 50.1 - Target
    } else {
        return 'bg-emerald-100/60 hover:bg-emerald-100 border-l-4 border-emerald-600' // Target - 100%
    }
}

const getBadgeClass = (persentase) => {
    if (persentase <= 30) return 'bg-rose-200 text-rose-800'
    if (persentase <= 50) return 'bg-amber-200 text-amber-800'
    if (persentase < props.targetBulanan) return 'bg-emerald-200 text-emerald-800'
    return 'bg-emerald-600 text-white shadow-sm'
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Leaderboard & Laporan" />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Leaderboard Pegawai</h2>
                    <p class="text-sm text-slate-500 mt-1">
                        Rekapitulasi pencapaian ibadah seluruh pegawai bulan <strong>{{ namaBulan }}</strong>.
                    </p>
                </div>
                
                <div class="flex flex-col sm:flex-row items-center gap-4">
                    <!-- Filter Section -->
                    <div class="flex items-center gap-2 bg-white p-2 rounded-xl border border-slate-200 shadow-sm w-full sm:w-auto">
                        <div class="relative w-full sm:w-48">
                            <input type="text" v-model="searchQuery" placeholder="Cari pegawai..." class="bg-slate-50 border-slate-200 text-slate-700 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block w-full p-2 pl-8" />
                            <svg class="w-4 h-4 absolute left-2.5 top-2.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <select v-model="perPage" class="bg-slate-50 border-slate-200 text-slate-700 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block w-full sm:w-auto p-2" title="Data per halaman">
                            <option :value="5">5 Baris</option>
                            <option :value="10">10 Baris</option>
                            <option :value="25">25 Baris</option>
                            <option :value="50">50 Baris</option>
                            <option :value="100">100 Baris</option>
                        </select>
                        <select v-model="selectedMonth" class="bg-slate-50 border-slate-200 text-slate-700 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block w-full sm:w-auto p-2">
                            <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                        <select v-model="selectedYear" class="bg-slate-50 border-slate-200 text-slate-700 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block w-full sm:w-auto p-2">
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                        <a :href="route('admin.laporan.export', { month: selectedMonth, year: selectedYear, search: searchQuery })" class="px-3 py-2 bg-emerald-600 text-white text-sm font-bold rounded-lg hover:bg-emerald-700 transition-colors flex items-center gap-2" title="Unduh Rekap Excel">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Export
                        </a>
                    </div>

                    <!-- Target Settings -->
                    <form @submit.prevent="saveTarget" class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3 w-full sm:w-auto">
                        <div class="flex flex-col">
                            <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Target Bulanan (%)</label>
                            <div class="relative mt-1">
                                <input type="number" step="0.1" min="0" max="100" v-model="form.target" class="w-24 p-1.5 pr-6 text-sm font-bold text-emerald-700 bg-emerald-50 border-emerald-200 rounded focus:ring-emerald-500 focus:border-emerald-500" />
                                <span class="absolute right-2 top-1.5 text-sm text-emerald-600 font-bold">%</span>
                            </div>
                        </div>
                        <button type="submit" :disabled="form.processing" class="mt-4 px-3 py-1.5 bg-slate-800 text-white text-xs font-bold rounded-lg hover:bg-slate-900 transition-colors">
                            Simpan
                        </button>
                    </form>
                </div>
            </div>

            <!-- Legend -->
            <div class="flex flex-wrap gap-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider bg-white p-3 rounded-lg border border-slate-200 shadow-sm">
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-rose-500"></span> 0-30%
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-amber-500"></span> 30.1-50%
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-emerald-300"></span> 50.1% - &lt;{{ targetBulanan }}%
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-emerald-600"></span> &ge;{{ targetBulanan }}% (Tercapai)
                </div>
            </div>

            <!-- Leaderboard List -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <!-- Header Laporan -->
                <div class="flex items-center px-6 py-3 gap-4 bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                    <div class="w-8 flex-shrink-0 text-center">No</div>
                    <div class="w-10 flex-shrink-0 hidden sm:block"></div>
                    <div class="flex-1 min-w-0">Nama Pegawai & Skor</div>
                    <div class="flex-shrink-0 text-right w-24">Pencapaian</div>
                </div>

                <ul class="divide-y divide-slate-100">
                    <li v-if="leaderboard.data.length === 0" class="p-8 text-center text-slate-500">Belum ada data pegawai.</li>
                    
                    <li v-for="(user, index) in leaderboard.data" :key="user.id" 
                        class="transition-colors group"
                        :class="getRowClass(user.persentase)"
                    >
                        <Link :href="route('admin.laporan.detail', user.id)" class="flex items-center px-6 py-4 gap-4">
                            <!-- Rank -->
                            <div class="w-8 flex-shrink-0 text-center">
                                <span v-if="leaderboard.current_page === 1 && index === 0" class="text-2xl">🥇</span>
                                <span v-else-if="leaderboard.current_page === 1 && index === 1" class="text-2xl">🥈</span>
                                <span v-else-if="leaderboard.current_page === 1 && index === 2" class="text-2xl">🥉</span>
                                <span v-else class="text-lg font-black text-slate-400">{{ (leaderboard.current_page - 1) * leaderboard.per_page + index + 1 }}</span>
                            </div>

                            <!-- Avatar & Name -->
                            <div class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center font-bold text-slate-600 flex-shrink-0">
                                {{ user.name.charAt(0).toUpperCase() }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-bold text-slate-800 text-base truncate group-hover:text-emerald-700 transition-colors">
                                    {{ user.name }}
                                </div>
                                <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-0.5">
                                    Skor: {{ user.skor }} / {{ skorMaksimalSebulan }}
                                </div>
                            </div>

                            <!-- Percentage -->
                            <div class="flex-shrink-0 text-right flex items-center gap-3">
                                <div class="px-3 py-1 rounded-full font-black text-sm" :class="getBadgeClass(user.persentase)">
                                    {{ user.persentase }}%
                                </div>
                                <svg class="w-5 h-5 text-slate-300 group-hover:text-emerald-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </Link>
                    </li>
                </ul>
                
                <!-- Pagination -->
                <div v-if="leaderboard.links && leaderboard.links.length > 3" class="px-6 py-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-sm text-slate-500">
                        Menampilkan <span class="font-bold text-slate-700">{{ leaderboard.from || 0 }}</span> sampai <span class="font-bold text-slate-700">{{ leaderboard.to || 0 }}</span> dari <span class="font-bold text-slate-700">{{ leaderboard.total }}</span> data
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <template v-for="(link, i) in leaderboard.links" :key="i">
                            <Link 
                                v-if="link.url"
                                :href="link.url"
                                class="px-3 py-1.5 text-sm font-medium border rounded-lg transition-colors"
                                :class="link.active ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
                                v-html="link.label"
                                preserve-scroll
                            />
                            <span v-else class="px-3 py-1.5 text-sm font-medium border rounded-lg text-slate-400 border-slate-200 bg-slate-50" v-html="link.label"></span>
                        </template>
                    </div>
                </div>
            </div>
            
        </div>
    </AuthenticatedLayout>
</template>
