<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PageContainer from '@/Components/UI/PageContainer.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import StatCard from '@/Components/Dashboard/StatCard.vue'
import { formatNumber, formatPercent } from '@/utils/formatters'

const props = defineProps({
    leaderboard: Object,
    summary: { type: Object, default: () => ({}) },
    targetBulanan: Number,
    skorMaksimalSebulan: Number,
    namaBulan: String,
    divisis: Array,
    filters: Object,
})

const now = new Date()
const currentYear = now.getFullYear()
const years = Array.from({ length: Math.max(1, currentYear - 2024 + 1) }, (_, i) => 2024 + i)
const months = [
    { value: 1, label: 'Januari' }, { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' }, { value: 4, label: 'April' },
    { value: 5, label: 'Mei' }, { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' }, { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' }, { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' }, { value: 12, label: 'Desember' },
]

const selectedMonth = ref(props.filters?.month || now.getMonth() + 1)
const selectedYear = ref(props.filters?.year || currentYear)
const searchQuery = ref(props.filters?.search || '')
const perPage = ref(props.filters?.per_page || 10)
const kategoriSkor = ref(props.filters?.kategori_skor || '')
const selectedDivisi = ref(props.filters?.divisi || '')
const sortField = ref(props.filters?.sort || 'skor')
const sortDir = ref(props.filters?.dir || 'desc')
const showMobileFilters = ref(false)

const targetForm = useForm({ target: props.targetBulanan })
const saveTarget = () => {
    targetForm.post(route('admin.laporan.target'), { preserveScroll: true })
}

const activeParams = (overrides = {}) => ({
    month: selectedMonth.value,
    year: selectedYear.value,
    search: searchQuery.value || undefined,
    per_page: perPage.value,
    kategori_skor: kategoriSkor.value || undefined,
    divisi: selectedDivisi.value || undefined,
    sort: sortField.value,
    dir: sortDir.value,
    ...overrides,
})

const applyFilters = (overrides = {}, replace = false) => {
    router.get(route('admin.laporan'), activeParams(overrides), {
        preserveState: true,
        preserveScroll: true,
        replace,
    })
}

let searchTimeout = null
watch(searchQuery, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => applyFilters({}, true), 350)
})

watch([selectedMonth, selectedYear, perPage, kategoriSkor, selectedDivisi], () => {
    applyFilters()
})

const setPreset = (preset) => {
    const d = new Date()
    if (preset === 'last_month') {
        d.setMonth(d.getMonth() - 1)
    }
    selectedMonth.value = d.getMonth() + 1
    selectedYear.value = d.getFullYear()
}

const resetFilters = () => {
    const d = new Date()
    selectedMonth.value = d.getMonth() + 1
    selectedYear.value = d.getFullYear()
    searchQuery.value = ''
    kategoriSkor.value = ''
    selectedDivisi.value = ''
    perPage.value = 10
    sortField.value = 'skor'
    sortDir.value = 'desc'
}

const toggleSort = (field) => {
    if (sortField.value === field) {
        sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc'
    } else {
        sortField.value = field
        sortDir.value = field === 'name' ? 'asc' : 'desc'
    }
    applyFilters()
}

const hasActiveFilter = computed(() =>
    Boolean(searchQuery.value || kategoriSkor.value || selectedDivisi.value)
)

const statusBadge = (persentase, target) => {
    const t = target ?? props.targetBulanan
    if (persentase >= t) return { label: 'Tercapai', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800/60', bar: 'bg-emerald-600' }
    if (persentase > 50) return { label: 'Mendekati', cls: 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-800/60', bar: 'bg-sky-500' }
    if (persentase > 30) return { label: 'Perhatian', cls: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800/60', bar: 'bg-amber-500' }
    return { label: 'Rendah', cls: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800/60', bar: 'bg-rose-500' }
}

const inputClass = 'w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-colors'
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Laporan Habit Pegawai" />

        <PageContainer size="xl">
            <PageHeader
                title="Rekap Habit Pegawai"
                :subtitle="`Periode ${namaBulan} · Skor maksimal ${formatNumber(skorMaksimalSebulan)} poin`"
            >
                <template #actions>
                    <Link
                        preserve-state
                        preserve-scroll
                        :href="route('admin.laporan.export', activeParams({ type: 'excel' }))"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 min-h-[44px] sm:min-h-0 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-colors"
                    >
                        Export Excel
                    </Link>
                    <Link
                        preserve-state
                        preserve-scroll
                        :href="route('admin.laporan.export', activeParams({ type: 'pdf' }))"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 min-h-[44px] sm:min-h-0 rounded-xl bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-bold transition-colors"
                    >
                        Export PDF
                    </Link>
                </template>
            </PageHeader>

            <!-- Ringkasan -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <StatCard
                    label="Total pegawai"
                    :value="formatNumber(summary.total_employees)"
                    subtext="Pegawai dipantau"
                    tone="neutral"
                />
                <StatCard
                    label="Rata-rata capaian"
                    :value="formatPercent(summary.average_percent, 1)"
                    :progress="summary.average_percent || 0"
                    :subtext="`Target sekolah ${targetBulanan}%`"
                    tone="primary"
                />
                <StatCard
                    label="Target tercapai"
                    :value="formatNumber(summary.target_reached_count)"
                    :subtext="`${formatNumber(summary.filled_employees)} pegawai sudah mengisi`"
                    tone="good"
                />
                <StatCard
                    label="Belum mengisi"
                    :value="formatNumber(summary.unfilled_employees)"
                    subtext="Belum ada entri bulan ini"
                    :tone="(summary.unfilled_employees || 0) > 0 ? 'bad' : 'good'"
                />
            </div>

            <!-- Bar Filter -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="setPreset('this_month')"
                            class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-primary-50 text-slate-700 hover:text-primary-700 dark:bg-slate-800 dark:text-slate-300 transition-colors cursor-pointer"
                        >
                            Bulan Ini
                        </button>
                        <button
                            type="button"
                            @click="setPreset('last_month')"
                            class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-primary-50 text-slate-700 hover:text-primary-700 dark:bg-slate-800 dark:text-slate-300 transition-colors cursor-pointer"
                        >
                            Bulan Lalu
                        </button>
                        <button
                            v-if="hasActiveFilter"
                            type="button"
                            @click="resetFilters"
                            class="px-2.5 py-1 rounded-lg text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40 transition-colors cursor-pointer"
                        >
                            Reset
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <form @submit.prevent="saveTarget" class="flex items-center gap-1.5">
                            <label for="target-input" class="text-xs font-semibold text-slate-500 dark:text-slate-400">Target (%):</label>
                            <input
                                id="target-input"
                                type="number"
                                step="0.1"
                                min="0"
                                max="100"
                                v-model="targetForm.target"
                                class="w-16 px-2 py-1 text-xs font-bold text-center rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 tabular-nums"
                            />
                            <button
                                type="submit"
                                :disabled="targetForm.processing"
                                class="px-2.5 py-1 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold transition-colors cursor-pointer"
                            >
                                Simpan
                            </button>
                        </form>
                        <button
                            type="button"
                            @click="showMobileFilters = !showMobileFilters"
                            class="lg:hidden px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300"
                        >
                            {{ showMobileFilters ? 'Tutup Filter' : 'Filter' }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3" :class="{ 'hidden lg:grid': !showMobileFilters }">
                    <div class="lg:col-span-3">
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Cari pegawai</label>
                        <input type="text" v-model="searchQuery" placeholder="Nama atau NIP..." :class="inputClass" />
                    </div>
                    <div class="lg:col-span-2">
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Bulan</label>
                        <select v-model="selectedMonth" :class="inputClass">
                            <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                    </div>
                    <div class="lg:col-span-2">
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Tahun</label>
                        <select v-model="selectedYear" :class="inputClass">
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>
                    <div class="lg:col-span-2">
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Divisi</label>
                        <select v-model="selectedDivisi" :class="inputClass">
                            <option value="">Semua Divisi</option>
                            <option v-for="d in divisis" :key="d" :value="d">{{ d }}</option>
                        </select>
                    </div>
                    <div class="lg:col-span-2">
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Kategori</label>
                        <select v-model="kategoriSkor" :class="inputClass">
                            <option value="">Semua Kategori</option>
                            <option value="tercapai">&ge; Target</option>
                            <option value="50-target">50.1% – &lt; Target</option>
                            <option value="30-50">30.1% – 50%</option>
                            <option value="0-30">0% – 30%</option>
                        </select>
                    </div>
                    <div class="lg:col-span-1">
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Baris</label>
                        <select v-model="perPage" :class="inputClass">
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Tabel Desktop & Kartu Mobile -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 overflow-hidden">
                <!-- Desktop Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-800/70 text-slate-500 dark:text-slate-400 font-bold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-4 py-3 w-12 text-center">#</th>
                                <th class="px-4 py-3">
                                    <button type="button" @click="toggleSort('name')" class="inline-flex items-center gap-1 hover:text-slate-900 dark:hover:text-slate-100 cursor-pointer">
                                        Pegawai
                                        <span v-if="sortField === 'name'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                                    </button>
                                </th>
                                <th class="px-4 py-3">Divisi</th>
                                <th class="px-4 py-3">
                                    <button type="button" @click="toggleSort('skor')" class="inline-flex items-center gap-1 hover:text-slate-900 dark:hover:text-slate-100 cursor-pointer">
                                        Capaian &amp; Skor
                                        <span v-if="sortField === 'skor'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-center">Terakhir Isi</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!leaderboard?.data?.length">
                                <td colspan="7" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">
                                    Tidak ada data untuk filter ini.
                                    <button v-if="hasActiveFilter" type="button" @click="resetFilters" class="ml-2 text-primary-600 font-semibold hover:underline cursor-pointer">
                                        Reset filter
                                    </button>
                                </td>
                            </tr>
                            <tr v-for="(u, idx) in leaderboard.data" :key="u.id" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-4 py-3 text-center font-semibold text-slate-400 tabular-nums">
                                    {{ (leaderboard.from || 1) + idx }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900 dark:text-slate-100">{{ u.name }}</div>
                                    <div class="text-[11px] text-slate-400">{{ u.nip || '-' }}</div>
                                </td>
                                <td class="px-4 py-3">{{ u.divisi || '-' }}</td>
                                <td class="px-4 py-3 min-w-[200px]">
                                    <div class="flex items-center justify-between gap-2 tabular-nums mb-1">
                                        <span class="font-bold text-slate-900 dark:text-slate-100">{{ formatPercent(u.persentase, 1) }}</span>
                                        <span class="text-slate-500 dark:text-slate-400">{{ formatNumber(u.skor) }} / {{ formatNumber(skorMaksimalSebulan) }}</span>
                                    </div>
                                    <div class="h-1.5 w-full rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                        <div class="h-full rounded-full" :class="statusBadge(u.persentase, u.target).bar" :style="{ width: `${Math.min(100, u.persentase)}%` }" />
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center tabular-nums text-slate-500 dark:text-slate-400">
                                    {{ u.last_filled_at || 'Belum ada' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border" :class="statusBadge(u.persentase, u.target).cls">
                                        {{ statusBadge(u.persentase, u.target).label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        :href="route('admin.laporan.detail', { user: u.id, month: selectedMonth, year: selectedYear })"
                                        class="inline-flex items-center px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-primary-50 text-slate-700 hover:text-primary-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-primary-950/60 font-semibold transition-colors"
                                    >
                                        Detail →
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="md:hidden divide-y divide-slate-100 dark:divide-slate-800">
                    <div v-if="!leaderboard?.data?.length" class="p-6 text-center text-xs text-slate-500 dark:text-slate-400">
                        Tidak ada data untuk filter ini.
                    </div>
                    <div v-for="u in leaderboard.data" :key="`m-${u.id}`" class="p-4 space-y-2.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="font-bold text-sm text-slate-900 dark:text-slate-100 truncate">{{ u.name }}</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ u.divisi || '-' }} · {{ u.last_filled_at || 'Belum isi' }}</div>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold border shrink-0" :class="statusBadge(u.persentase, u.target).cls">
                                {{ statusBadge(u.persentase, u.target).label }}
                            </span>
                        </div>
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs tabular-nums">
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ formatPercent(u.persentase, 1) }}</span>
                                <span class="text-slate-500 dark:text-slate-400">{{ formatNumber(u.skor) }} poin</span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                <div class="h-full rounded-full" :class="statusBadge(u.persentase, u.target).bar" :style="{ width: `${Math.min(100, u.persentase)}%` }" />
                            </div>
                        </div>
                        <Link
                            :href="route('admin.laporan.detail', { user: u.id, month: selectedMonth, year: selectedYear })"
                            class="inline-flex items-center justify-center w-full min-h-[44px] rounded-xl bg-slate-100 dark:bg-slate-800 text-xs font-bold text-slate-700 dark:text-slate-200"
                        >
                            Lihat Detail Habit →
                        </Link>
                    </div>
                </div>

                <div v-if="leaderboard?.links?.length > 3" class="px-4 py-3 border-t border-slate-100 dark:border-slate-800">
                    <Pagination :links="leaderboard.links" />
                </div>
            </div>
        </PageContainer>
    </AuthenticatedLayout>
</template>
