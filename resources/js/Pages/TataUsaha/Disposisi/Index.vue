<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const page = usePage()
const hasRole = (role) => page.props.auth.roles?.includes(role)

const props = defineProps({
    disposisi: Object,
    suratMasuk: Array,
    suratPerluDisposisi: Array,
    pegawai: Array,
    filters: Object,
})

const isModalOpen = ref(false)
const editingId = ref(null)
const searchQuery = ref(props.filters?.search || '')

let searchTimeout = null
watch(searchQuery, (newVal) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get(route('tata-usaha.disposisi.index'), { search: newVal }, { preserveState: true, preserveScroll: true, replace: true })
    }, 300)
})

const form = useForm({
    surat_masuk_id: '',
    penerima_id: [],
    instruksi: '',
    batas_waktu: '',
})

const openAddModal = (suratId = '') => {
    editingId.value = null
    form.reset()
    form.clearErrors()
    form.surat_masuk_id = suratId
    isModalOpen.value = true
}

const editDisposisi = (item) => {
    editingId.value = item.id
    form.surat_masuk_id = item.surat_masuk_id
    form.penerima_id = [item.penerima_id]
    form.instruksi = item.instruksi
    form.batas_waktu = item.batas_waktu ? item.batas_waktu.split('T')[0] : ''
    isModalOpen.value = true
}

const closeModal = () => {
    isModalOpen.value = false
    editingId.value = null
    form.reset()
    form.clearErrors()
}

const saveDisposisi = () => {
    if (editingId.value) {
        form.put(route('tata-usaha.disposisi.update', editingId.value), {
            onSuccess: () => closeModal(),
        })
    } else {
        form.post(route('tata-usaha.disposisi.store'), {
            onSuccess: () => closeModal(),
        })
    }
}

const deleteDisposisi = (id) => {
    if (confirm('Yakin ingin menghapus instruksi disposisi ini?')) {
        router.delete(route('tata-usaha.disposisi.destroy', id), {
            preserveScroll: true
        })
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Disposisi Surat" />

        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-800 tracking-tight">Manajemen Disposisi</h2>
                    <p class="text-sm text-slate-500 mt-1">
                        Daftar penugasan atau instruksi berdasarkan surat masuk.
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row items-center gap-3">
                    <div class="relative w-full sm:w-64">
                        <input type="text" v-model="searchQuery" placeholder="Cari disposisi..." class="bg-white border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block w-full p-2.5 pl-9 shadow-sm" />
                        <svg class="w-4 h-4 absolute left-3 top-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <button @click="openAddModal" class="inline-flex flex-1 sm:flex-none items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-purple-600 text-white font-bold text-sm shadow-sm hover:bg-purple-700 transition-colors focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Buat Disposisi</span>
                    </button>
                </div>
            </div>

            <!-- Surat Perlu Disposisi Table -->
            <div v-if="suratPerluDisposisi && suratPerluDisposisi.length > 0" class="bg-white rounded-xl shadow-sm border border-amber-200 overflow-hidden mb-6">
                <div class="px-4 py-3 bg-amber-50 border-b border-amber-200">
                    <h3 class="text-sm font-bold text-amber-800">Menunggu Disposisi</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="bg-amber-50/50 text-xs uppercase text-amber-700 font-semibold border-b border-amber-100">
                            <tr>
                                <th class="px-4 py-3">No. Surat</th>
                                <th class="px-4 py-3">Pengirim</th>
                                <th class="px-4 py-3">Perihal</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-amber-100">
                            <tr v-for="surat in suratPerluDisposisi" :key="surat.id" class="bg-white hover:bg-amber-50/50 transition-colors">
                                <td class="px-4 py-4 font-bold text-slate-800">{{ surat.nomor_surat }}</td>
                                <td class="px-4 py-4">{{ surat.pengirim }}</td>
                                <td class="px-4 py-4"><div class="line-clamp-2" v-html="surat.perihal"></div></td>
                                <td class="px-4 py-4 text-right">
                                    <button @click="openAddModal(surat.id)" class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-purple-100 text-purple-700 text-xs font-bold hover:bg-purple-200 transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                        Beri Disposisi
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500 font-semibold border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3">Surat & Instruksi</th>
                                <th class="px-4 py-3">Dari -> Ke</th>
                                <th class="px-4 py-3">Batas Waktu</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-if="disposisi.data.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500">Belum ada data disposisi.</td>
                            </tr>
                            <tr v-for="item in disposisi.data" :key="item.id" class="bg-white hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-4">
                                    <div class="font-bold text-slate-800">{{ item.surat_masuk?.nomor_surat }}</div>
                                    <div class="text-xs text-slate-500 mt-1 italic">"{{ item.instruksi }}"</div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="text-xs text-slate-500">Dari: <span class="font-bold text-slate-700">{{ item.pemberi?.name }}</span></div>
                                    <div class="text-xs text-slate-500">Ke: <span class="font-bold text-blue-600">{{ item.penerima?.name }}</span></div>
                                </td>
                                <td class="px-4 py-4 text-slate-500">
                                    <span v-if="item.batas_waktu">{{ item.batas_waktu }}</span>
                                    <span v-else class="text-slate-400 italic">Tidak ada</span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                          :class="{
                                              'bg-slate-100 text-slate-700': item.status === 'menunggu',
                                              'bg-blue-100 text-blue-700': item.status === 'dibaca',
                                              'bg-amber-100 text-amber-700': item.status === 'dikerjakan',
                                              'bg-emerald-100 text-emerald-700': item.status === 'selesai'
                                          }">
                                        {{ item.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a :href="route('tata-usaha.disposisi.print', item.id)" target="_blank" title="Cetak Disposisi" class="text-blue-500 hover:text-blue-700">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                        </a>
                                        <button v-if="hasRole('kepala_sekolah') || hasRole('superadmin')" @click="editDisposisi(item)" title="Edit" class="text-amber-500 hover:text-amber-700">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                        </button>
                                        <button v-if="hasRole('kepala_sekolah') || hasRole('superadmin')" @click="deleteDisposisi(item.id)" title="Hapus" class="text-rose-500 hover:text-rose-700">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add Modal -->
        <div v-if="isModalOpen" class="relative z-50">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl w-full max-w-md">
                        <form @submit.prevent="saveDisposisi">
                            <div class="bg-white px-4 pb-4 pt-5 sm:p-6">
                                <h3 class="text-lg font-bold text-slate-900 mb-4">{{ editingId ? 'Edit Disposisi' : 'Buat Disposisi / Penugasan' }}</h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 mb-1">Pilih Surat Masuk</label>
                                        <select v-model="form.surat_masuk_id" class="w-full p-2 text-sm border-slate-300 rounded-lg" required>
                                            <option value="">-- Pilih Surat --</option>
                                            <option v-for="surat in suratMasuk" :key="surat.id" :value="surat.id">
                                                {{ surat.nomor_surat }} - {{ surat.perihal }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.surat_masuk_id" class="text-xs text-rose-500 mt-1">{{ form.errors.surat_masuk_id }}</p>
                                    </div>
                                    
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <label class="block text-xs font-bold text-slate-500">Tugaskan Kepada</label>
                                            <span class="text-[10px] text-slate-400">Gunakan CTRL/CMD untuk pilih lebih dari satu</span>
                                        </div>
                                        <select v-model="form.penerima_id" multiple class="w-full p-2 text-sm border-slate-300 rounded-lg h-32" required>
                                            <option v-for="user in pegawai" :key="user.id" :value="user.id">
                                                {{ user.name }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.penerima_id" class="text-xs text-rose-500 mt-1">{{ form.errors.penerima_id }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 mb-1">Instruksi / Pesan</label>
                                        <textarea v-model="form.instruksi" rows="3" class="w-full p-2 text-sm border-slate-300 rounded-lg" required placeholder="Tolong segera ditindaklanjuti..."></textarea>
                                        <p v-if="form.errors.instruksi" class="text-xs text-rose-500 mt-1">{{ form.errors.instruksi }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 mb-1">Batas Waktu (Opsional)</label>
                                        <input type="date" v-model="form.batas_waktu" class="w-full p-2 text-sm border-slate-300 rounded-lg" />
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-slate-50 px-4 py-3 flex flex-row-reverse">
                                <button type="submit" :disabled="form.processing" class="inline-flex w-full justify-center rounded-xl bg-purple-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-purple-700 sm:ml-3 sm:w-auto">
                                    Simpan
                                </button>
                                <button type="button" @click="closeModal" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto">
                                    Batal
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
