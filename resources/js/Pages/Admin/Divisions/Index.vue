<script setup>
import { ref, watch } from 'vue'
import { Head, useForm, router, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    divisions: Object,
    filters: Object
})

let debounceTimer = null
const debounce = (fn, delay) => {
    return (...args) => {
        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(() => fn(...args), delay)
    }
}

const searchQuery = ref(props.filters.search || '')

watch(searchQuery, debounce((value) => {
    router.get(route('admin.divisions.index'), { search: value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    })
}, 300))

const isModalOpen = ref(false)
const isEditing = ref(false)
const modalTitle = ref('')

const form = useForm({
    id: null,
    name: '',
    target_divisi: ''
})

const openAddModal = () => {
    isEditing.value = false
    modalTitle.value = 'Tambah Divisi'
    form.reset()
    form.clearErrors()
    isModalOpen.value = true
}

const openEditModal = (division) => {
    isEditing.value = true
    modalTitle.value = 'Edit Divisi'
    form.reset()
    form.clearErrors()
    form.id = division.id
    form.name = division.name
    form.target_divisi = division.target_divisi || ''
    isModalOpen.value = true
}

const closeModal = () => {
    isModalOpen.value = false
    setTimeout(() => form.reset(), 300)
}

const submitForm = () => {
    if (isEditing.value) {
        form.put(route('admin.divisions.update', form.id), {
            onSuccess: () => closeModal()
        })
    } else {
        form.post(route('admin.divisions.store'), {
            onSuccess: () => closeModal()
        })
    }
}

const deleteDivision = (id) => {
    if (confirm('Apakah Anda yakin ingin menghapus divisi ini? Semua pengguna di divisi ini mungkin kehilangan referensi divisinya.')) {
        router.delete(route('admin.divisions.destroy', id))
    }
}
</script>

<template>
    <Head title="Manajemen Divisi" />

    <AuthenticatedLayout>
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <!-- Toolbar -->
                <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/50">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">Daftar Divisi</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Kelola divisi dan target skor khusus per divisi</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input v-model="searchQuery" type="text" class="block w-48 pl-9 pr-3 py-2 border border-slate-200 rounded-xl bg-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-shadow text-sm" placeholder="Cari divisi..." />
                        </div>
                        <button @click="openAddModal" class="px-4 py-2 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 transition-colors shadow-sm font-semibold text-sm flex items-center gap-2 flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Tambah Divisi
                        </button>
                    </div>
                </div>

                <!-- Table List -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Nama Divisi</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Target Khusus Divisi</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <tr v-for="division in divisions.data" :key="division.id" class="hover:bg-slate-50/50 transition-colors group">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-slate-900">{{ division.name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span v-if="division.target_divisi" class="px-2.5 py-1 text-xs font-bold bg-emerald-100 text-emerald-700 rounded-full">
                                        Target: {{ division.target_divisi }}%
                                    </span>
                                    <span v-else class="text-xs text-slate-400 italic">Mengikuti Target Global</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button @click="openEditModal(division)" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-md transition-colors" title="Edit">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button @click="deleteDivision(division.id)" class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md transition-colors" title="Hapus">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="divisions.data.length === 0">
                                <td colspan="3" class="px-6 py-8 text-center text-sm text-slate-500">
                                    <svg class="mx-auto h-12 w-12 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    Belum ada data divisi.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div v-if="divisions.links && divisions.links.length > 3" class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <Link v-if="divisions.prev_page_url" :href="divisions.prev_page_url" class="relative inline-flex items-center px-4 py-2 border border-slate-300 text-sm font-medium rounded-md text-slate-700 bg-white hover:bg-slate-50">Previous</Link>
                        <Link v-if="divisions.next_page_url" :href="divisions.next_page_url" class="ml-3 relative inline-flex items-center px-4 py-2 border border-slate-300 text-sm font-medium rounded-md text-slate-700 bg-white hover:bg-slate-50">Next</Link>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-slate-700">
                                Menampilkan <span class="font-medium">{{ divisions.from }}</span> sampai <span class="font-medium">{{ divisions.to }}</span> dari <span class="font-medium">{{ divisions.total }}</span> hasil
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <template v-for="(link, index) in divisions.links" :key="index">
                                    <Link v-if="link.url" :href="link.url" v-html="link.label" :class="[link.active ? 'z-10 bg-emerald-50 border-emerald-500 text-emerald-600' : 'bg-white border-slate-300 text-slate-500 hover:bg-slate-50', 'relative inline-flex items-center px-4 py-2 border text-sm font-medium']"></Link>
                                    <span v-else v-html="link.label" class="relative inline-flex items-center px-4 py-2 border border-slate-300 bg-white text-sm font-medium text-slate-300"></span>
                                </template>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Formulir -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="closeModal"></div>
            
            <div class="relative bg-white rounded-2xl max-w-md w-full shadow-xl transform transition-all overflow-hidden border border-slate-100">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-slate-800">{{ modalTitle }}</h3>
                    <button @click="closeModal" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <div class="p-6">
                    <form @submit.prevent="submitForm" class="space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Nama Divisi <span class="text-rose-500">*</span></label>
                            <input type="text" v-model="form.name" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-shadow text-sm" placeholder="Contoh: Tim IT" required />
                            <p v-if="form.errors.name" class="text-xs text-rose-500 mt-1">{{ form.errors.name }}</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Target Skor Divisi (%)</label>
                            <input type="number" min="1" max="100" v-model="form.target_divisi" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-shadow text-sm" placeholder="Opsional (Kosongkan jika ikut target global)" />
                            <p v-if="form.errors.target_divisi" class="text-xs text-rose-500 mt-1">{{ form.errors.target_divisi }}</p>
                            <p class="text-[11px] text-slate-500 mt-1.5 leading-relaxed">
                                Jika diisi, semua pegawai dalam divisi ini akan dievaluasi menggunakan target ini (kecuali jika mereka memiliki target personal atau sedang inaktif).
                            </p>
                        </div>
                        
                        <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                            <button type="button" @click="closeModal" class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
                                Batal
                            </button>
                            <button type="submit" :disabled="form.processing" class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 border border-transparent rounded-xl hover:bg-emerald-700 focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-colors disabled:opacity-50">
                                {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
