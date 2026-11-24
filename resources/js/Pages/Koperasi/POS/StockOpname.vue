<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

const props = defineProps({
    metrics: {
        type: Object,
        default: () => ({
            total_products: 0,
            total_units: 0,
            total_cost_valuation: 0,
            total_potential_revenue: 0,
            potential_profit: 0,
            out_of_stock_count: 0,
            low_stock_count: 0,
            safe_stock_count: 0,
        }),
    },
    categoryBreakdown: {
        type: Array,
        default: () => [],
    },
    products: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    recentLogs: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ search: '', category: '', status: 'all', sort: 'stock_asc' }),
    },
})

// ─── Format Currency & Number ───
const formatRupiah = (val) => {
    const num = Number(val) || 0
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(num)
}

const formatNumber = (val) => {
    return new Intl.NumberFormat('id-ID').format(Number(val) || 0)
}

// ─── Filters & Search ───
const filterForm = ref({
    search: props.filters.search || '',
    category: props.filters.category || '',
    status: props.filters.status || 'all',
    sort: props.filters.sort || 'stock_asc',
})

const applyFilters = () => {
    router.get(route('koperasi.stock-opname'), filterForm.value, {
        preserveState: true,
        preserveScroll: true,
    })
}

const setStatusFilter = (status) => {
    filterForm.value.status = status
    applyFilters()
}

// ─── Quick Inline Physical Count ───
// Holds map of productId -> physical count string
const physicalCounts = ref({})

const onPhysicalCountChange = (productId, val) => {
    if (val === '' || val === null) {
        delete physicalCounts.value[productId]
    } else {
        physicalCounts.value[productId] = Number(val)
    }
}

// Check which rows have changes
const pendingChanges = computed(() => {
    const list = []
    props.products.data.forEach(p => {
        if (p.id in physicalCounts.value && physicalCounts.value[p.id] !== p.stock) {
            list.push({
                product_id: p.id,
                name: p.name,
                current_stock: p.stock,
                physical_stock: physicalCounts.value[p.id],
                diff: physicalCounts.value[p.id] - p.stock,
                unit: p.unit,
            })
        }
    })
    return list
})

const batchOpnameForm = useForm({
    items: [],
    notes: 'Audit fisik berkala (Stock Opname)',
})

const submitBatchOpname = () => {
    if (pendingChanges.value.length === 0) return

    if (!confirm(`Terapkan hasil hitung fisik untuk ${pendingChanges.value.length} barang ini? Stok sistem akan diperbarui secara otomatis.`)) {
        return
    }

    batchOpnameForm.items = pendingChanges.value.map(item => ({
        product_id: item.product_id,
        physical_stock: item.physical_stock,
    }))

    batchOpnameForm.post(route('koperasi.stock-opname.quick-batch'), {
        preserveScroll: true,
        onSuccess: () => {
            physicalCounts.value = {}
        },
    })
}

const resetPhysicalCounts = () => {
    physicalCounts.value = {}
}

// ─── Single Item Stock Adjustment Modal ───
const showAdjustModal = ref(false)
const adjustTarget = ref(null)

const adjustForm = useForm({
    product_id: null,
    actual_stock: 0,
    type: 'opname',
    notes: '',
})

const openAdjustModal = (product) => {
    adjustTarget.value = product
    adjustForm.product_id = product.id
    adjustForm.actual_stock = product.id in physicalCounts.value ? physicalCounts.value[product.id] : product.stock
    adjustForm.type = 'opname'
    adjustForm.notes = ''
    showAdjustModal.value = true
}

const submitAdjust = () => {
    adjustForm.post(route('koperasi.stock-opname.adjust'), {
        preserveScroll: true,
        onSuccess: () => {
            showAdjustModal.value = false
            delete physicalCounts.value[adjustForm.product_id]
        },
    })
}

// ─── Print Sheet ───
const printOpnameSheet = () => {
    window.print()
}
</script>

<template>
    <Head title="Dashboard Stock Opname — Koperasi Sekolah" />

    <PosLayout>
        <div class="flex-1 p-4 sm:p-6 lg:p-8 overflow-y-auto space-y-6 bg-slate-100/70">

            <!-- ══════════════════════════════════════════════════════════
                 1. HEADER & ACTION TOOLBAR (SCREEN ONLY)
                 ══════════════════════════════════════════════════════════ -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 print:hidden">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-500 text-white flex items-center justify-center shadow-md shadow-emerald-600/20 flex-shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Dashboard Stock Opname</h1>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-200">
                                Live Stock
                            </span>
                        </div>
                        <p class="text-xs text-slate-500">
                            Monitoring stok fisik, audit barang nyata, valuasi modal inventaris, dan penyesuaian stok Koperasi Sekolah SDAM
                        </p>
                    </div>
                </div>

                <!-- Export & Print Actions -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Download Excel Stock Opname -->
                    <a
                        :href="route('koperasi.export.stock-opname')"
                        class="px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs transition-colors flex items-center gap-1.5 shadow-xs"
                        title="Unduh Lembar Kerja Stock Opname format Excel"
                    >
                        <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        <span>Unduh Excel</span>
                    </a>

                    <!-- Cetak Lembar Kerja Opname Fisik -->
                    <button
                        type="button"
                        @click="printOpnameSheet"
                        class="px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs transition-colors flex items-center gap-1.5 shadow-xs"
                        title="Cetak lembar audit fisik untuk petugas checklist di gudang/etalase"
                    >
                        <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                        <span>Cetak Lembar Audit</span>
                    </button>

                    <!-- Kelola Produk Link -->
                    <Link
                        :href="route('koperasi.products')"
                        class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-colors flex items-center gap-1.5 shadow-md shadow-emerald-600/20"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        <span>Tambah / Edit Produk</span>
                    </Link>
                </div>
            </div>

            <!-- Print Header (Visible Only When Printing) -->
            <div class="hidden print:block text-center border-b-2 border-slate-900 pb-3 mb-4">
                <h2 class="text-xl font-black uppercase tracking-tight">Koperasi Surya Amanah — SD Muhammadiyah 1 Krian</h2>
                <h3 class="text-base font-bold uppercase text-slate-700">Lembar Audit Stock Opname Inventaris Barang</h3>
                <p class="text-xs text-slate-500">Tanggal Cetak: {{ new Date().toLocaleDateString('id-ID', { dateStyle: 'full' }) }}</p>
            </div>

            <!-- ══════════════════════════════════════════════════════════
                 2. EXECUTIVE KPI SUMMARY CARDS (5 METRICS)
                 ══════════════════════════════════════════════════════════ -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3.5 print:hidden">
                <!-- Total SKU -->
                <div class="bg-white rounded-2xl p-4 border border-slate-200/90 shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Produk</span>
                        <span class="p-1.5 rounded-lg bg-slate-100 text-slate-700">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                        </span>
                    </div>
                    <div class="mt-2">
                        <p class="text-2xl font-black text-slate-900">{{ formatNumber(metrics.total_products) }}</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">SKU Terdaftar Aktif</p>
                    </div>
                </div>

                <!-- Total Unit Fisik -->
                <div class="bg-white rounded-2xl p-4 border border-slate-200/90 shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Fisik Unit</span>
                        <span class="p-1.5 rounded-lg bg-teal-50 text-teal-700">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                        </span>
                    </div>
                    <div class="mt-2">
                        <p class="text-2xl font-black text-teal-600">{{ formatNumber(metrics.total_units) }}</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">Pcs / Item di Toko</p>
                    </div>
                </div>

                <!-- Nilai Aset Modal -->
                <div class="bg-white rounded-2xl p-4 border border-slate-200/90 shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Valuasi Modal</span>
                        <span class="p-1.5 rounded-lg bg-emerald-50 text-emerald-700">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </span>
                    </div>
                    <div class="mt-2">
                        <p class="text-xl font-black text-emerald-700 truncate">{{ formatRupiah(metrics.total_cost_valuation) }}</p>
                        <p class="text-[10px] text-emerald-600 mt-0.5 font-medium">Potensi Omset: {{ formatRupiah(metrics.total_potential_revenue) }}</p>
                    </div>
                </div>

                <!-- Stok Menipis -->
                <div
                    @click="setStatusFilter('low_stock')"
                    class="bg-white rounded-2xl p-4 border border-amber-200/80 shadow-xs flex flex-col justify-between cursor-pointer hover:border-amber-400 transition-all"
                    :class="{'ring-2 ring-amber-500': filterForm.status === 'low_stock'}"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-amber-800 uppercase tracking-wider">Stok Menipis</span>
                        <span class="p-1.5 rounded-lg bg-amber-50 text-amber-700">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        </span>
                    </div>
                    <div class="mt-2">
                        <p class="text-2xl font-black text-amber-600">{{ metrics.low_stock_count }}</p>
                        <p class="text-[10px] text-amber-700/80 mt-0.5 font-medium">Stok &le; 10 (Perlu Kulakan)</p>
                    </div>
                </div>

                <!-- Stok Habis -->
                <div
                    @click="setStatusFilter('out_of_stock')"
                    class="bg-white rounded-2xl p-4 border border-rose-200/80 shadow-xs flex flex-col justify-between cursor-pointer hover:border-rose-400 transition-all col-span-2 lg:col-span-1"
                    :class="{'ring-2 ring-rose-500': filterForm.status === 'out_of_stock'}"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-rose-800 uppercase tracking-wider">Stok Kosong</span>
                        <span class="p-1.5 rounded-lg bg-rose-50 text-rose-700">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                        </span>
                    </div>
                    <div class="mt-2">
                        <p class="text-2xl font-black text-rose-600">{{ metrics.out_of_stock_count }}</p>
                        <p class="text-[10px] text-rose-700/80 mt-0.5 font-medium">Stok 0 (Habis Total)</p>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════
                 3. CATEGORY DISTRIBUTION PILLS / BREAKDOWN
                 ══════════════════════════════════════════════════════════ -->
            <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs space-y-3 print:hidden">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-black uppercase text-slate-700 tracking-wider flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                        <span>Distribusi Stok per Kategori</span>
                    </h3>
                    <span class="text-[11px] text-slate-400">{{ categoryBreakdown.length }} Kategori Aktif</span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5">
                    <div
                        v-for="cat in categoryBreakdown"
                        :key="cat.category"
                        @click="filterForm.category = (filterForm.category === cat.category ? '' : cat.category); applyFilters()"
                        class="p-3 rounded-xl border text-xs cursor-pointer transition-all flex flex-col justify-between"
                        :class="filterForm.category === cat.category ? 'bg-emerald-50 border-emerald-500 shadow-xs' : 'bg-slate-50/80 border-slate-200 hover:bg-slate-50 hover:border-slate-300'"
                    >
                        <div>
                            <div class="font-bold text-slate-800 truncate">{{ cat.category }}</div>
                            <div class="text-[10px] text-slate-500 mt-0.5">{{ cat.items_count }} Produk • {{ formatNumber(cat.total_stock) }} Unit</div>
                        </div>
                        <div class="mt-2 pt-2 border-t border-slate-200/60 flex items-center justify-between text-[11px]">
                            <span class="font-mono font-bold text-emerald-700">{{ formatRupiah(cat.cost_valuation) }}</span>
                            <span v-if="cat.out_of_stock > 0" class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-rose-100 text-rose-700">
                                {{ cat.out_of_stock }} Kosong
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════
                 4. INTERACTIVE AUDIT TABLE & SEARCH FILTER
                 ══════════════════════════════════════════════════════════ -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">

                <!-- Filter Controls (Screen Only) -->
                <div class="p-4 border-b border-slate-200 bg-slate-50/60 flex flex-col sm:flex-row gap-3 items-center justify-between print:hidden">
                    <div class="flex flex-1 flex-col sm:flex-row gap-2.5 w-full">
                        <!-- Search Name / Barcode -->
                        <div class="relative flex-1">
                            <input
                                type="text"
                                v-model="filterForm.search"
                                @keyup.enter="applyFilters"
                                placeholder="Cari nama barang atau barcode..."
                                class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-slate-200 bg-white text-slate-800 placeholder-slate-400 focus:outline-none focus:border-emerald-500"
                            />
                            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>

                        <!-- Category Selector -->
                        <select
                            v-model="filterForm.category"
                            @change="applyFilters"
                            class="px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white text-slate-800 focus:outline-none focus:border-emerald-500 sm:w-44"
                        >
                            <option value="">Semua Kategori</option>
                            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                        </select>

                        <!-- Stock Status Tabs -->
                        <select
                            v-model="filterForm.status"
                            @change="applyFilters"
                            class="px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white text-slate-800 focus:outline-none focus:border-emerald-500 sm:w-40"
                        >
                            <option value="all">Semua Status</option>
                            <option value="out_of_stock">Stok Habis (0)</option>
                            <option value="low_stock">Stok Menipis (&le;10)</option>
                            <option value="safe">Stok Aman (&gt;10)</option>
                        </select>

                        <!-- Sort Selector -->
                        <select
                            v-model="filterForm.sort"
                            @change="applyFilters"
                            class="px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white text-slate-800 focus:outline-none focus:border-emerald-500 sm:w-44"
                        >
                            <option value="stock_asc">Stok: Terendah ke Tinggi</option>
                            <option value="stock_desc">Stok: Tertinggi ke Rendah</option>
                            <option value="valuation_desc">Nilai Modal Terbesar</option>
                            <option value="name_asc">Nama Produk (A-Z)</option>
                        </select>
                    </div>

                    <button
                        type="button"
                        @click="applyFilters"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-colors shadow-xs w-full sm:w-auto"
                    >
                        Filter
                    </button>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50 text-slate-500 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="px-3.5 py-3 text-center w-10">No</th>
                                <th class="px-3.5 py-3">Nama Produk & Barcode</th>
                                <th class="px-3.5 py-3">Kategori</th>
                                <th class="px-3.5 py-3 text-right">Harga Beli (Modal)</th>
                                <th class="px-3.5 py-3 text-right">Harga Jual</th>
                                <th class="px-3.5 py-3 text-center">Stok Sistem</th>
                                <th class="px-3.5 py-3 text-center bg-emerald-50/50 print:bg-transparent">
                                    Hitung Fisik Nyata
                                </th>
                                <th class="px-3.5 py-3 text-center">Selisih</th>
                                <th class="px-3.5 py-3 text-right">Valuasi Aset</th>
                                <th class="px-3.5 py-3 text-center print:hidden">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            <tr
                                v-for="(p, index) in products.data"
                                :key="p.id"
                                class="hover:bg-slate-50/70 transition-colors"
                                :class="{'bg-amber-50/40': p.id in physicalCounts && physicalCounts[p.id] !== p.stock}"
                            >
                                <td class="px-3.5 py-3 text-center font-bold text-slate-400">
                                    {{ index + 1 }}
                                </td>

                                <td class="px-3.5 py-3">
                                    <div class="font-bold text-slate-900">{{ p.name }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono">Barcode: {{ p.barcode || '-' }}</div>
                                </td>

                                <td class="px-3.5 py-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ p.category }}
                                    </span>
                                </td>

                                <td class="px-3.5 py-3 text-right font-mono text-slate-700">
                                    {{ formatRupiah(p.cost_price) }}
                                </td>

                                <td class="px-3.5 py-3 text-right font-mono font-bold text-slate-900">
                                    {{ formatRupiah(p.price) }}
                                </td>

                                <!-- Current System Stock -->
                                <td class="px-3.5 py-3 text-center">
                                    <span
                                        class="px-2.5 py-1 rounded-md text-[11px] font-black font-mono inline-flex items-center gap-1"
                                        :class="p.status === 'habis' ? 'bg-rose-100 text-rose-800 border border-rose-200' : (p.status === 'menipis' ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200')"
                                    >
                                        {{ p.stock }} {{ p.unit }}
                                    </span>
                                </td>

                                <!-- Physical Count Input -->
                                <td class="px-3.5 py-2 text-center bg-emerald-50/30 print:bg-transparent">
                                    <input
                                        type="number"
                                        min="0"
                                        :value="p.id in physicalCounts ? physicalCounts[p.id] : ''"
                                        @input="onPhysicalCountChange(p.id, $event.target.value)"
                                        :placeholder="String(p.stock)"
                                        class="w-20 px-2 py-1 text-center font-mono font-black text-xs rounded-lg border border-slate-300 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 bg-white"
                                    />
                                </td>

                                <!-- Difference Calculation -->
                                <td class="px-3.5 py-3 text-center font-mono font-bold text-xs">
                                    <template v-if="p.id in physicalCounts">
                                        <span
                                            v-if="physicalCounts[p.id] - p.stock === 0"
                                            class="text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded text-[10px]"
                                        >
                                            Cocok ✓
                                        </span>
                                        <span
                                            v-else-if="physicalCounts[p.id] - p.stock < 0"
                                            class="text-rose-700 bg-rose-100 px-2 py-0.5 rounded text-[10px]"
                                        >
                                            {{ physicalCounts[p.id] - p.stock }}
                                        </span>
                                        <span
                                            v-else
                                            class="text-teal-700 bg-teal-100 px-2 py-0.5 rounded text-[10px]"
                                        >
                                            +{{ physicalCounts[p.id] - p.stock }}
                                        </span>
                                    </template>
                                    <span v-else class="text-slate-300">-</span>
                                </td>

                                <!-- Valuation -->
                                <td class="px-3.5 py-3 text-right font-mono font-bold text-slate-800">
                                    {{ formatRupiah(p.cost_valuation) }}
                                </td>

                                <!-- Action -->
                                <td class="px-3.5 py-3 text-center print:hidden">
                                    <button
                                        type="button"
                                        @click="openAdjustModal(p)"
                                        class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 hover:text-slate-900 font-bold text-[11px] transition-colors"
                                    >
                                        Sesuaikan
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="products.data.length === 0">
                                <td colspan="10" class="px-4 py-8 text-center text-slate-400">
                                    Tidak ada produk yang cocok dengan kriteria filter stok.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="products.links && products.links.length > 3" class="px-4 py-3 border-t border-slate-100 flex justify-center gap-1 print:hidden">
                    <template v-for="(link, i) in products.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="px-3 py-1 rounded text-xs font-bold transition-colors"
                            :class="link.active ? 'bg-emerald-600 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'"
                            v-html="link.label"
                        />
                        <span v-else class="px-3 py-1 rounded text-xs text-slate-400" v-html="link.label"></span>
                    </template>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════
                 5. RECENT STOCK ADJUSTMENT LOGS (AUDIT TRAIL)
                 ══════════════════════════════════════════════════════════ -->
            <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-xs space-y-3 print:hidden">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-black uppercase text-slate-800 tracking-wider flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span>Riwayat Penyesuaian Stok Terakhir (Audit Trail)</span>
                        </h3>
                        <p class="text-[11px] text-slate-400">Catatan perubahan stok fisik, barang rusak/hilang, dan hasil opname kasir</p>
                    </div>
                </div>

                <div v-if="recentLogs.length > 0" class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50 text-slate-500 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="px-3.5 py-2.5">Waktu</th>
                                <th class="px-3.5 py-2.5">Nama Produk</th>
                                <th class="px-3.5 py-2.5">Jenis Penyesuaian</th>
                                <th class="px-3.5 py-2.5 text-center">Stok Sebelum</th>
                                <th class="px-3.5 py-2.5 text-center">Stok Baru</th>
                                <th class="px-3.5 py-2.5 text-center">Selisih</th>
                                <th class="px-3.5 py-2.5">Petugas</th>
                                <th class="px-3.5 py-2.5">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            <tr v-for="log in recentLogs" :key="log.id" class="hover:bg-slate-50/70">
                                <td class="px-3.5 py-2 font-mono text-[11px] text-slate-400">{{ log.created_at }}</td>
                                <td class="px-3.5 py-2 font-bold text-slate-800">{{ log.product_name }}</td>
                                <td class="px-3.5 py-2">
                                    <span
                                        class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                                        :class="{
                                            'bg-emerald-50 text-emerald-700 border border-emerald-200': log.type === 'opname',
                                            'bg-rose-50 text-rose-700 border border-rose-200': log.type === 'damage' || log.type === 'expired',
                                            'bg-teal-50 text-teal-700 border border-teal-200': log.type === 'restock',
                                            'bg-slate-100 text-slate-700 border border-slate-200': log.type === 'correction' || log.type === 'other',
                                        }"
                                    >
                                        {{ log.type }}
                                    </span>
                                </td>
                                <td class="px-3.5 py-2 text-center font-mono">{{ log.stock_before }}</td>
                                <td class="px-3.5 py-2 text-center font-mono font-bold text-slate-900">{{ log.stock_after }}</td>
                                <td class="px-3.5 py-2 text-center font-mono font-bold" :class="log.difference < 0 ? 'text-rose-600' : 'text-emerald-600'">
                                    {{ log.difference > 0 ? `+${log.difference}` : log.difference }}
                                </td>
                                <td class="px-3.5 py-2 text-slate-700">{{ log.staff_name }}</td>
                                <td class="px-3.5 py-2 text-slate-500 text-[11px] italic">{{ log.notes || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="text-center py-6 text-slate-400 text-xs">
                    Belum ada riwayat penyesuaian stok.
                </div>
            </div>

        </div>

        <!-- ══════════════════════════════════════════════════════════
             6. FLOATING BATCH SAVE ACTION BAR
             ══════════════════════════════════════════════════════════ -->
        <div
            v-if="pendingChanges.length > 0"
            class="fixed bottom-6 inset-x-4 sm:inset-x-auto sm:right-8 z-40 bg-slate-900 text-white p-4 rounded-2xl shadow-2xl border border-slate-800 flex flex-col sm:flex-row items-center gap-3 animate-in slide-in-from-bottom duration-200 print:hidden"
        >
            <div class="flex items-center gap-2">
                <span class="w-8 h-8 rounded-full bg-emerald-500 text-slate-900 font-black text-xs flex items-center justify-center">
                    {{ pendingChanges.length }}
                </span>
                <div class="text-xs">
                    <p class="font-bold">Perubahan Hitung Fisik Siap Disimpan</p>
                    <p class="text-[10px] text-slate-400">Stok sistem akan disesuaikan dengan hasil opname</p>
                </div>
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto">
                <button
                    type="button"
                    @click="resetPhysicalCounts"
                    class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs transition-colors flex-1 sm:flex-none"
                >
                    Batal
                </button>
                <button
                    type="button"
                    @click="submitBatchOpname"
                    :disabled="batchOpnameForm.processing"
                    class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs transition-colors flex-1 sm:flex-none shadow-md shadow-emerald-500/20"
                >
                    {{ batchOpnameForm.processing ? 'Menyimpan...' : 'Terapkan Hasil Opname' }}
                </button>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════
             7. MODAL SINGLE STOCK ADJUSTMENT
             ══════════════════════════════════════════════════════════ -->
        <div
            v-if="showAdjustModal && adjustTarget"
            class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
            @click.self="showAdjustModal = false"
        >
            <div class="bg-white border border-slate-200 rounded-2xl max-w-md w-full p-6 shadow-2xl relative space-y-4 text-slate-800">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-base font-black text-slate-900">Penyesuaian Stok Barang</h3>
                        <p class="text-xs text-slate-500">{{ adjustTarget.name }}</p>
                    </div>
                    <button type="button" @click="showAdjustModal = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form @submit.prevent="submitAdjust" class="space-y-3.5 text-xs">
                    <!-- Current Stock Info -->
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 flex justify-between items-center">
                        <span class="text-slate-600 font-bold">Stok Sistem Saat Ini:</span>
                        <span class="font-mono font-black text-slate-900 text-sm">{{ adjustTarget.stock }} {{ adjustTarget.unit }}</span>
                    </div>

                    <!-- New Actual Stock Input -->
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Stok Nyata / Hasil Hitung Fisik Baru</label>
                        <input
                            type="number"
                            min="0"
                            v-model.number="adjustForm.actual_stock"
                            required
                            class="w-full px-3 py-2.5 font-mono text-base font-black bg-slate-50 border border-slate-300 rounded-xl text-slate-900 focus:outline-none focus:bg-white focus:border-emerald-500"
                        />
                        <p class="text-[11px] text-slate-400 mt-1">
                            Selisih:
                            <strong :class="adjustForm.actual_stock - adjustTarget.stock < 0 ? 'text-rose-600' : 'text-emerald-600'">
                                {{ adjustForm.actual_stock - adjustTarget.stock > 0 ? `+${adjustForm.actual_stock - adjustTarget.stock}` : adjustForm.actual_stock - adjustTarget.stock }}
                            </strong>
                            {{ adjustTarget.unit }}
                        </p>
                    </div>

                    <!-- Adjustment Type -->
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Alasan / Jenis Penyesuaian</label>
                        <select
                            v-model="adjustForm.type"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 focus:outline-none focus:bg-white focus:border-emerald-500"
                        >
                            <option value="opname">Audit Fisik Rutin (Stock Opname)</option>
                            <option value="damage">Barang Rusak / Pecah / Cacat</option>
                            <option value="expired">Barang Kadaluarsa / Basi</option>
                            <option value="restock">Tambahan Masuk / Restock Kulakan</option>
                            <option value="correction">Koreksi Kesalahan Input Admin</option>
                            <option value="other">Alasan Lainnya</option>
                        </select>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Keterangan / Catatan Audit</label>
                        <input
                            type="text"
                            v-model="adjustForm.notes"
                            placeholder="Contoh: Ditemukan 2 botol pecah di rak gudang..."
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 focus:outline-none focus:bg-white focus:border-emerald-500"
                        />
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button
                            type="button"
                            @click="showAdjustModal = false"
                            class="w-1/3 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold transition-colors"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="adjustForm.processing"
                            class="w-2/3 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition-colors shadow-xs"
                        >
                            {{ adjustForm.processing ? 'Menyimpan...' : 'Simpan Penyesuaian' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </PosLayout>
</template>

<style scoped>
@media print {
    body {
        background: white !important;
        color: black !important;
    }
}
</style>
