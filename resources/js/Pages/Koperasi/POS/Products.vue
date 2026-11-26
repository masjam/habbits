<script setup>
import { ref } from 'vue'
import { Head, useForm, router, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

const props = defineProps({
    products: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    categories: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ search: '', category: '' }),
    },
})

const filterSearch = ref(props.filters.search || '')
const filterCategory = ref(props.filters.category || '')

const applyFilter = () => {
    router.get(route('koperasi.products'), {
        search: filterSearch.value,
        category: filterCategory.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

// ─── Add / Edit Product Modal ───
const showModal = ref(false)
const isEditing = ref(false)
const productForm = useForm({
    id: null,
    name: '',
    barcode: '',
    category: 'ATK & Buku',
    price: 0,
    cost_price: 0,
    stock: 50,
    unit: 'Pcs',
})

const openAddModal = () => {
    isEditing.value = false
    productForm.reset()
    productForm.category = props.categories[0] || 'ATK & Buku'
    showModal.value = true
}

const openEditModal = (p) => {
    isEditing.value = true
    productForm.id = p.id
    productForm.name = p.name
    productForm.barcode = p.barcode || ''
    productForm.category = p.category
    productForm.price = p.price
    productForm.cost_price = p.cost_price || 0
    productForm.stock = p.stock
    productForm.unit = p.unit || 'Pcs'
    showModal.value = true
}

const saveProduct = () => {
    productForm.post(route('koperasi.products.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false
        },
    })
}

const deleteProduct = (p) => {
    if (!confirm(`Yakin ingin menghapus barang "${p.name}"?`)) return
    router.delete(route('koperasi.products.destroy', p.id), {
        preserveScroll: true,
    })
}

// ─── Excel Bulk Import Modal ───
const showImportModal = ref(false)
const importFileRef = ref(null)
const importForm = useForm({
    file: null,
})

const handleFileUpload = (e) => {
    importForm.file = e.target.files[0] || null
}

const submitImport = () => {
    if (!importForm.file) {
        alert('Silakan pilih file Excel (.xlsx / .xls / .csv) terlebih dahulu.')
        return
    }

    importForm.post(route('koperasi.products.import'), {
        preserveScroll: true,
        onSuccess: () => {
            showImportModal.value = false
            importForm.reset()
            if (importFileRef.value) importFileRef.value.value = ''
        },
    })
}

const formatRupiah = (val) => {
    const num = Number(val) || 0
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(num)
}
</script>

<template>
    <Head title="Katalog Barang — Koperasi Rabbani" />

    <PosLayout>
        <div class="flex-1 p-4 sm:p-6 overflow-y-auto space-y-5 bg-slate-100">
            <!-- Header with Action Buttons -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900">Katalog Barang Koperasi</h1>
                    <p class="text-xs text-slate-500">Daftar produk, seragam siswa, perlengkapan, dan stok Koperasi Sekolah SDAM</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- Download Template Excel -->
                    <a
                        :href="route('koperasi.export.product-template')"
                        class="px-3 py-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs transition-colors flex items-center gap-1.5 shadow-xs"
                        title="Unduh format template Excel untuk impor massal"
                    >
                        <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        <span>Template Excel</span>
                    </a>

                    <!-- Upload Excel -->
                    <button
                        type="button"
                        @click="showImportModal = true"
                        class="px-3 py-2 rounded-xl bg-teal-50 border border-teal-200 hover:bg-teal-100 text-teal-800 font-bold text-xs transition-colors flex items-center gap-1.5 shadow-xs"
                    >
                        <svg class="w-4 h-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                        <span>Upload Katalog Excel</span>
                    </button>

                    <!-- Download Stock Opname -->
                    <a
                        :href="route('koperasi.export.stock-opname')"
                        class="px-3 py-2 rounded-xl bg-amber-50 border border-amber-200 hover:bg-amber-100 text-amber-800 font-bold text-xs transition-colors flex items-center gap-1.5 shadow-xs"
                    >
                        <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        <span>Stock Opname Excel</span>
                    </a>

                    <!-- Tambah Produk Manual -->
                    <button
                        type="button"
                        @click="openAddModal"
                        class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-colors flex items-center gap-1.5 shadow-md shadow-emerald-600/20"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        <span>Tambah Produk</span>
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-col sm:flex-row gap-3 bg-white p-3 rounded-2xl border border-slate-200 shadow-xs">
                <input
                    type="text"
                    v-model="filterSearch"
                    @keyup.enter="applyFilter"
                    placeholder="Cari nama barang atau barcode..."
                    class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-emerald-500 focus:bg-white flex-1"
                />
                <select
                    v-model="filterCategory"
                    @change="applyFilter"
                    class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white sm:w-56"
                >
                    <option value="">Semua Kategori</option>
                    <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                </select>
                <button
                    type="button"
                    @click="applyFilter"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-colors shadow-xs"
                >
                    Filter
                </button>
            </div>

            <!-- Table -->
            <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50 text-slate-500 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3.5">Nama Produk</th>
                                <th class="px-4 py-3.5">Barcode / SKU</th>
                                <th class="px-4 py-3.5">Kategori</th>
                                <th class="px-4 py-3.5 text-right">Harga Jual</th>
                                <th class="px-4 py-3.5 text-center">Stok</th>
                                <th class="px-4 py-3.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="p in products.data" :key="p.id" class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-4 py-3 font-bold text-slate-900">{{ p.name }}</td>
                                <td class="px-4 py-3 font-mono text-[11px] text-slate-500">{{ p.barcode || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ p.category }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-black text-emerald-600 font-mono">
                                    {{ formatRupiah(p.price) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="px-2.5 py-0.5 rounded-md text-[10px] font-bold"
                                        :class="p.stock > 10 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'"
                                    >
                                        {{ p.stock }} {{ p.unit }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button
                                            type="button"
                                            @click="openEditModal(p)"
                                            class="px-3 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-xs font-bold text-slate-700 transition-colors"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            @click="deleteProduct(p)"
                                            class="px-3 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-xs font-bold text-rose-600 transition-colors"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="products.data.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                    Belum ada produk yang cocok dengan pencarian.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="products.links && products.links.length > 3" class="px-4 py-3 border-t border-slate-100 flex justify-center gap-1">
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
        </div>

        <!-- ══════════════════════════════════════════════════════════
             ADD / EDIT PRODUCT MODAL
             ══════════════════════════════════════════════════════════ -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
            @click.self="showModal = false"
        >
            <div class="bg-white border border-slate-200 rounded-2xl max-w-md w-full p-6 shadow-2xl relative space-y-4 text-slate-800">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-black text-slate-900">{{ isEditing ? 'Edit Data Produk' : 'Tambah Produk Baru' }}</h3>
                    <button type="button" @click="showModal = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form @submit.prevent="saveProduct" class="space-y-3 text-xs">
                    <div>
                        <label class="block text-slate-600 font-bold mb-1">Nama Produk</label>
                        <input type="text" v-model="productForm.name" required class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 focus:outline-none focus:border-emerald-500 focus:bg-white" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-600 font-bold mb-1">Barcode / SKU</label>
                            <input type="text" v-model="productForm.barcode" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 focus:outline-none focus:border-emerald-500 font-mono focus:bg-white" />
                        </div>
                        <div>
                            <label class="block text-slate-600 font-bold mb-1">Kategori</label>
                            <input type="text" v-model="productForm.category" required class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 focus:outline-none focus:border-emerald-500 focus:bg-white" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-600 font-bold mb-1">Harga Jual (Rp)</label>
                            <input type="number" v-model.number="productForm.price" required class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 focus:outline-none focus:border-emerald-500 font-mono focus:bg-white" />
                        </div>
                        <div>
                            <label class="block text-slate-600 font-bold mb-1">Stok Barang</label>
                            <input type="number" v-model.number="productForm.stock" required class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 focus:outline-none focus:border-emerald-500 font-mono focus:bg-white" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-600 font-bold mb-1">Harga Pokok (Rp)</label>
                            <input type="number" v-model.number="productForm.cost_price" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 focus:outline-none focus:border-emerald-500 font-mono focus:bg-white" />
                        </div>
                        <div>
                            <label class="block text-slate-600 font-bold mb-1">Satuan (Unit)</label>
                            <input type="text" v-model="productForm.unit" placeholder="Pcs / Set / Botol" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 focus:outline-none focus:border-emerald-500 focus:bg-white" />
                        </div>
                    </div>

                    <div class="flex gap-2 pt-3">
                        <button type="button" @click="showModal = false" class="w-1/3 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold transition-colors">Batal</button>
                        <button type="submit" :disabled="productForm.processing" class="w-2/3 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition-colors shadow-xs">Simpan Produk</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════
             UPLOAD EXCEL MODAL
             ══════════════════════════════════════════════════════════ -->
        <div
            v-if="showImportModal"
            class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
            @click.self="showImportModal = false"
        >
            <div class="bg-white border border-slate-200 rounded-2xl max-w-md w-full p-6 shadow-2xl relative space-y-4 text-slate-800">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                        </div>
                        <h3 class="text-base font-black text-slate-900">Upload Katalog dari File Excel</h3>
                    </div>
                    <button type="button" @click="showImportModal = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <p class="text-xs text-slate-500 leading-relaxed">
                    Upload file Excel untuk menambah atau memperbarui data produk secara massal. Jika nama produk atau barcode sudah ada, sistem akan otomatis memperbarui harga dan stoknya.
                </p>

                <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-[11px] text-amber-800 space-y-1">
                    <p class="font-bold flex items-center gap-1">
                        <span>💡</span>
                        <span>Petunjuk Format Excel:</span>
                    </p>
                    <p>Gunakan kolom: <code class="font-mono font-bold bg-white px-1 py-0.5 rounded border border-amber-200">nama_produk</code>, <code class="font-mono font-bold bg-white px-1 py-0.5 rounded border border-amber-200">kategori</code>, <code class="font-mono font-bold bg-white px-1 py-0.5 rounded border border-amber-200">harga_jual</code>, <code class="font-mono font-bold bg-white px-1 py-0.5 rounded border border-amber-200">stok</code>, <code class="font-mono font-bold bg-white px-1 py-0.5 rounded border border-amber-200">barcode</code>, <code class="font-mono font-bold bg-white px-1 py-0.5 rounded border border-amber-200">satuan</code>.</p>
                </div>

                <form @submit.prevent="submitImport" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Pilih File Excel (.xlsx / .xls / .csv)</label>
                        <input
                            ref="importFileRef"
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            @change="handleFileUpload"
                            class="block w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100 border border-slate-300 rounded-xl p-1 bg-slate-50 cursor-pointer"
                        />
                        <div v-if="importForm.errors.file" class="text-rose-600 text-xs mt-1 font-bold">{{ importForm.errors.file }}</div>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button type="button" @click="showImportModal = false" class="w-1/3 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">Batal</button>
                        <button
                            type="submit"
                            :disabled="importForm.processing || !importForm.file"
                            class="w-2/3 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs transition-colors shadow-xs disabled:opacity-50"
                        >
                            {{ importForm.processing ? 'Mengunggah & Memproses...' : 'Impor Data Sekarang' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </PosLayout>
</template>
