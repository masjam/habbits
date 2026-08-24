<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
    leaderboard: Object,
    targetBulanan: Number,
    skorMaksimalSebulan: Number,
    namaBulan: String,
    divisis: Array,
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
const kategoriSkor = ref(props.filters.kategori_skor || '')
const selectedDivisi = ref(props.filters.divisi || '')
const showMobileFilters = ref(false)

let searchTimeout = null
watch(searchQuery, (newVal) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get(route('admin.laporan'), { month: selectedMonth.value, year: selectedYear.value, search: newVal, per_page: perPage.value, kategori_skor: kategoriSkor.value, divisi: selectedDivisi.value }, { preserveState: true, preserveScroll: true, replace: true })
    }, 300)
})

watch([selectedMonth, selectedYear, perPage, kategoriSkor, selectedDivisi], () => {
    router.get(route('admin.laporan'), { month: selectedMonth.value, year: selectedYear.value, search: searchQuery.value, per_page: perPage.value, kategori_skor: kategoriSkor.value, divisi: selectedDivisi.value }, { preserveState: true, preserveScroll: true })
})

const saveTarget = () => {
    form.post(route('admin.laporan.target'), {
        preserveScroll: true
    })
}

// Logic pewarnaan berdasarkan persentase
const getRowClass = (persentase, target) => {
    const targetBulanan = target ?? props.targetBulanan;
    if (persentase >= targetBulanan) {
        return 'bg-emerald-100/60 hover:bg-emerald-100 border-l-4 border-emerald-600' // Tercapai
    } else if (persentase > 50) {
        return 'bg-emerald-50/50 hover:bg-emerald-50 border-l-4 border-emerald-300' // 50.1 - Target
    } else if (persentase > 30) {
        return 'bg-amber-50 hover:bg-amber-100/80 border-l-4 border-amber-500' // 30.1-50%
    } else {
        return 'bg-rose-50 hover:bg-rose-100/80 border-l-4 border-rose-500' // 0-30%
    }
}

const getBadgeClass = (persentase, target) => {
    const targetBulanan = target ?? props.targetBulanan;
    if (persentase >= targetBulanan) return 'bg-emerald-600 text-white shadow-sm'
    if (persentase > 50) return 'bg-emerald-200 text-emerald-800'
    if (persentase > 30) return 'bg-amber-200 text-amber-800'
    return 'bg-rose-200 text-rose-800'
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
            </div>
                
            <!-- Controls Card -->
            
            <!-- Mobile Filter Toggle Button -->
            <div class="lg:hidden">
                <button @click="showMobileFilters = !showMobileFilters" class="w-full flex items-center justify-between px-5 py-3.5 bg-white border border-slate-200 rounded-2xl text-sm font-bold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    <span class="flex items-center gap-2.5">
                        <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        Filter & Pencarian
                    </span>
                    <svg class="w-5 h-5 text-slate-400 transform transition-transform" :class="{ 'rotate-180': showMobileFilters }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </div>

            <div class="bg-white rounded-3xl lg:px-6 lg:pt-6 lg:pb-2 shadow-sm lg:border border-slate-100" :class="{ 'hidden lg:block': !showMobileFilters, 'p-5 border': showMobileFilters }">
                <div class="flex flex-col lg:flex-row gap-6 items-start lg:items-end justify-between">
                    
                    <!-- Search & Filters -->
                    <div class="flex-1 w-full grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
                        <div class="col-span-1 md:col-span-2 xl:col-span-1">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Pencarian</label>
                            <div class="relative">
                                <input type="text" v-model="searchQuery" placeholder="Cari nama pegawai..." class="bg-slate-50 border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block w-full pl-10 pr-4 py-2.5 transition-colors" />
                                <svg class="w-5 h-5 absolute left-3 top-2.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Bulan</label>
                            <select v-model="selectedMonth" class="bg-slate-50 border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block w-full py-2.5 px-4 cursor-pointer transition-colors">
                                <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Tahun</label>
                            <select v-model="selectedYear" class="bg-slate-50 border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block w-full py-2.5 px-4 cursor-pointer transition-colors">
                                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Kategori</label>
                            <select v-model="kategoriSkor" class="bg-slate-50 border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block w-full py-2.5 px-4 cursor-pointer transition-colors">
                                <option value="">Semua Kategori</option>
                                <option value="0-30">0% - 30%</option>
                                <option value="30-50">30.1% - 50%</option>
                                <option value="50-target">50.1% - &lt; Target</option>
                                <option value="tercapai">&ge; Target</option>
                            </select>
                        </div>
                        
                        <div v-if="$page.props.global_settings?.feature_divisi === '1' || $page.props.global_settings?.feature_divisi === 'true'">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Divisi</label>
                            <select v-model="selectedDivisi" class="bg-slate-50 border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block w-full py-2.5 px-4 cursor-pointer transition-colors">
                                <option value="">Semua Divisi</option>
                                <option v-for="divisi in divisis" :key="divisi" :value="divisi">{{ divisi }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Tampil</label>
                            <select v-model="perPage" class="bg-slate-50 border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block w-full py-2.5 px-4 cursor-pointer transition-colors">
                                <option :value="5">5 Baris</option>
                                <option :value="10">10 Baris</option>
                                <option :value="25">25 Baris</option>
                                <option :value="50">50 Baris</option>
                            </select>
                        </div>
                    </div>

                    <!-- Target & Export Actions -->
                    <div class="flex flex-col sm:flex-row gap-4 w-full lg:w-auto mt-4 lg:mt-0 border-t lg:border-t-0 pt-4 lg:pt-0 border-slate-100">
                        <form @submit.prevent="saveTarget" class="flex items-end gap-2 bg-emerald-50 p-2 rounded-xl border border-emerald-100">
                            <div>
                                <label class="block text-[10px] font-bold text-emerald-600 uppercase tracking-wider mb-1 px-1">Target (%)</label>
                                <input type="number" step="0.1" min="0" max="100" v-model="form.target" class="w-20 sm:w-24 px-3 py-2 text-sm font-black text-emerald-800 bg-white border-none rounded-lg focus:ring-2 focus:ring-emerald-500 text-center shadow-sm" />
                            </div>
                            <button type="submit" :disabled="form.processing" class="h-[36px] px-4 bg-emerald-600 text-white text-xs font-bold rounded-lg hover:bg-emerald-700 transition-colors shadow-sm whitespace-nowrap">
                                Simpan
                            </button>
                        </form>
                        
                        <div class="flex gap-2 self-end">
                            <a :href="route('admin.laporan.export', { month: selectedMonth, year: selectedYear, search: searchQuery, kategori_skor: kategoriSkor, type: 'excel' })" class="h-[52px] px-4 bg-emerald-700 text-white text-sm font-bold rounded-xl hover:bg-emerald-800 transition-all flex items-center justify-center gap-2 shadow-sm" title="Unduh Rekap Excel">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                <span class="hidden sm:inline">Excel</span>
                            </a>
                            <a :href="route('admin.laporan.export', { month: selectedMonth, year: selectedYear, search: searchQuery, kategori_skor: kategoriSkor, type: 'pdf' })" class="h-[52px] px-4 bg-rose-600 text-white text-sm font-bold rounded-xl hover:bg-rose-700 transition-all flex items-center justify-center gap-2 shadow-sm" title="Unduh Rekap PDF">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                <span class="hidden sm:inline">PDF</span>
                            </a>
                        </div>
                    </div>
                    
                    
                </div>
                <div class="flex flex-wrap gap-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider p-3">
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
            </div>

            <!-- Legend -->

            <!-- Leaderboard List -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <!-- Header Laporan -->
                <div class="flex items-center px-4 py-2.5 gap-3 bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                    <div class="w-8 flex-shrink-0 text-center">No</div>
                    <div class="w-9 flex-shrink-0 hidden sm:block"></div>
                    <div class="flex-1 min-w-0 ml-1">Nama Pegawai & Skor</div>
                    <div class="flex-shrink-0 text-right w-24">Pencapaian</div>
                </div>

                <ul class="divide-y divide-slate-100">
                    <li v-if="leaderboard.data.length === 0" class="p-8 text-center text-slate-500">Belum ada data pegawai.</li>
                    
                    <li v-for="(user, index) in leaderboard.data" :key="user.id" 
                        class="transition-colors group"
                        :class="getRowClass(user.persentase, user.target)"
                    >
                        <Link :href="route('admin.laporan.detail', user.id)" class="flex items-center px-4 py-2.5 gap-3">
                            <!-- Rank -->
                            <div class="w-8 flex-shrink-0 text-center">
                                <span v-if="leaderboard.current_page === 1 && index === 0" class="text-xl">🥇</span>
                                <span v-else-if="leaderboard.current_page === 1 && index === 1" class="text-xl">🥈</span>
                                <span v-else-if="leaderboard.current_page === 1 && index === 2" class="text-xl">🥉</span>
                                <span v-else class="text-base font-black text-slate-400">{{ (leaderboard.current_page - 1) * leaderboard.per_page + index + 1 }}</span>
                            </div>

                            <!-- Avatar & Name -->
                            <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-slate-600 flex-shrink-0 overflow-hidden border-2 border-white shadow-sm" :class="!user.avatar ? 'bg-slate-200' : 'bg-transparent'">
                                <img v-if="user.avatar" :src="user.avatar.startsWith('http') ? user.avatar : `/storage/${user.avatar}`" class="w-full h-full object-cover" />
                                <span v-else class="text-xs">{{ user.name.charAt(0).toUpperCase() }}</span>
                            </div>
                            <div class="flex-1 min-w-0 ml-1">
                                <div class="flex items-center gap-2">
                                    <div class="font-bold text-slate-800 text-sm truncate group-hover:text-emerald-700 transition-colors">
                                        {{ user.name }}
                                    </div>
                                    <div v-if="$page.props.global_settings?.feature_badges === '1' || $page.props.global_settings?.feature_badges === 'true'" class="flex -space-x-1">
                                        <span v-for="badge in (user.badges || [])" :key="badge.id" class="text-sm bg-white rounded-full border border-slate-200 shadow-sm z-10" :title="badge.name" v-html="badge.icon"></span>
                                    </div>
                                </div>
                                <div class="text-[9px] text-slate-500 uppercase tracking-widest mt-0.5 flex flex-wrap gap-2 items-center">
                                    <span>Skor: {{ user.skor }} / {{ skorMaksimalSebulan }}</span>
                                    <span v-if="$page.props.global_settings?.feature_divisi === '1' || $page.props.global_settings?.feature_divisi === 'true'" class="text-emerald-600 font-bold">
                                        • {{ user.divisi || 'Tanpa Divisi' }}
                                    </span>
                                    <span v-if="user.status && user.status !== 'Aktif' && ($page.props.global_settings?.feature_cuti === '1' || $page.props.global_settings?.feature_cuti === 'true')" class="bg-rose-100 text-rose-600 px-1.5 py-0.5 rounded font-bold uppercase">
                                        Sedang {{ user.status }}
                                    </span>
                                </div>
                            </div>

                            <!-- Percentage & Target -->
                            <div class="flex-shrink-0 text-right flex items-center gap-2">
                                <div class="flex flex-col items-end">
                                    <div class="px-2.5 py-1 rounded-full font-black text-xs" :class="getBadgeClass(user.persentase, user.target)">
                                        {{ user.persentase }}%
                                    </div>
                                    <div class="text-[9px] text-slate-400 font-bold mt-1 uppercase tracking-wider" title="Target skor untuk pegawai ini">
                                        Trg: {{ user.target ?? targetBulanan }}%
                                    </div>
                                </div>
                                <svg class="w-4 h-4 text-slate-300 group-hover:text-emerald-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
