<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const props = defineProps({
    documents: Object,
    filters: Object,
})

const filterStatus = ref(props.filters?.status || 'all')

watch(filterStatus, (newVal) => {
    router.get(route('admin.employee-documents.index'), { status: newVal }, { preserveState: true, preserveScroll: true })
})

// Verifikasi Modal
const showVerifyModal = ref(false)
const selectedDoc = ref(null)

const verifyForm = useForm({
    is_verified: true,
    notes: ''
})

const openVerifyModal = (doc) => {
    selectedDoc.value = doc
    verifyForm.is_verified = doc.is_verified
    verifyForm.notes = doc.notes || ''
    showVerifyModal.value = true
}

const submitVerify = () => {
    verifyForm.patch(route('employee-documents.verify', selectedDoc.value.id), {
        onSuccess: () => {
            showVerifyModal.value = false
        }
    })
}

const deleteDocument = (id) => {
    if (confirm('Yakin ingin menghapus arsip pegawai ini? (File fisik juga akan terhapus)')) {
        router.delete(route('employee-documents.destroy', id), {
            preserveScroll: true
        })
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Verifikasi Arsip Pegawai" />

        <div class="space-y-6 w-full pb-8">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 dark:text-slate-100 tracking-tight">Verifikasi Arsip Pegawai</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Pantau dan verifikasi kelengkapan dokumen seluruh guru & karyawan.
                    </p>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <div class="flex items-center gap-4">
                    <label class="text-sm font-bold text-slate-700 dark:text-slate-300">Filter Status:</label>
                    <select v-model="filterStatus" class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2 text-sm text-slate-700 dark:text-slate-300 focus:ring-emerald-500">
                        <option value="all">Semua Arsip</option>
                        <option value="unverified">Menunggu Verifikasi</option>
                        <option value="verified">Sudah Terverifikasi</option>
                    </select>
                </div>
            </div>

            <!-- Documents Table -->
            <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600 dark:text-slate-400">
                        <thead class="bg-slate-50/80 dark:bg-slate-900/50 border-b border-slate-100 dark:border-slate-700 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">
                            <tr>
                                <th class="px-6 py-4">Pegawai</th>
                                <th class="px-6 py-4">Kategori & Dokumen</th>
                                <th class="px-6 py-4">Detail Tambahan</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="doc in documents.data" :key="doc.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-900 dark:text-slate-100">{{ doc.user?.name }}</div>
                                    <div class="text-xs text-slate-500 mt-1">{{ doc.user?.nip || '-' }} | {{ doc.user?.divisi || 'Guru' }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="inline-block px-2 py-0.5 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded text-[10px] font-bold uppercase tracking-wider mb-1">
                                        {{ doc.document_type }}
                                    </div>
                                    <div class="font-medium text-slate-800 dark:text-slate-200">{{ doc.title }}</div>
                                    <div class="text-xs text-slate-400 mt-1">Ekstensi: .{{ doc.file_extension }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-xs space-y-1">
                                        <div><span class="font-bold">Nomor:</span> {{ doc.document_number || '-' }}</div>
                                        <div><span class="font-bold">Tahun:</span> {{ doc.document_year || '-' }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span v-if="doc.is_verified" class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400 px-2.5 py-1 rounded-full text-xs font-bold">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                        Valid
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400 px-2.5 py-1 rounded-full text-xs font-bold">
                                        Menunggu
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <a :href="route('employee-documents.download', doc.id)" target="_blank" class="p-2 text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 bg-white dark:bg-slate-700 hover:bg-emerald-50 dark:hover:bg-slate-600 rounded-lg shadow-sm border border-slate-200 dark:border-slate-600 transition-colors" title="Unduh / Lihat File">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                        </a>
                                        <button @click="openVerifyModal(doc)" class="p-2 text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 bg-white dark:bg-slate-700 hover:bg-blue-50 dark:hover:bg-slate-600 rounded-lg shadow-sm border border-slate-200 dark:border-slate-600 transition-colors" title="Verifikasi">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        </button>
                                        <button @click="deleteDocument(doc.id)" class="p-2 text-slate-400 hover:text-red-600 dark:hover:text-red-400 bg-white dark:bg-slate-700 hover:bg-red-50 dark:hover:bg-slate-600 rounded-lg shadow-sm border border-slate-200 dark:border-slate-600 transition-colors" title="Hapus">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="documents.data.length === 0">
                                <td colspan="5" class="px-6 py-10 text-center text-slate-500 dark:text-slate-400">
                                    Tidak ada dokumen arsip yang ditemukan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="px-6 py-4 bg-slate-50/50 dark:bg-slate-800/30 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between" v-if="documents.links && documents.links.length > 3">
                    <span class="text-sm text-slate-500 dark:text-slate-400">
                        Menampilkan {{ documents.from }} - {{ documents.to }} dari {{ documents.total }} data
                    </span>
                    <div class="flex space-x-1">
                        <component :is="link.url ? 'Link' : 'span'" v-for="(link, i) in documents.links" :key="i" :href="link.url"
                                   v-html="link.label"
                                   class="px-3 py-1 rounded-md text-sm"
                                   :class="[
                                       link.active ? 'bg-emerald-600 text-white font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700',
                                       !link.url && 'opacity-50 cursor-not-allowed'
                                   ]"></component>
                    </div>
                </div>
            </div>
        </div>

        <!-- Verify Modal -->
        <div v-if="showVerifyModal" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-3xl bg-white dark:bg-slate-800 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-100 dark:border-slate-700">
                        <form @submit.prevent="submitVerify">
                            <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700">
                                <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">Verifikasi Arsip Pegawai</h3>
                            </div>
                            <div class="px-6 py-5 space-y-4">
                                <div v-if="selectedDoc" class="p-4 bg-slate-50 dark:bg-slate-900 rounded-xl mb-4">
                                    <p class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ selectedDoc.user?.name }}</p>
                                    <p class="text-xs text-slate-500 mt-1">{{ selectedDoc.title }}</p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Status Dokumen</label>
                                    <div class="flex gap-4">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" v-model="verifyForm.is_verified" :value="true" class="w-4 h-4 text-emerald-600 focus:ring-emerald-600 border-slate-300">
                                            <span class="text-sm text-slate-700 dark:text-slate-300 font-bold">Valid & Terverifikasi</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" v-model="verifyForm.is_verified" :value="false" class="w-4 h-4 text-amber-600 focus:ring-amber-600 border-slate-300">
                                            <span class="text-sm text-slate-700 dark:text-slate-300 font-bold">Tidak Valid / Ditolak</span>
                                        </label>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">Catatan HR (Opsional)</label>
                                    <textarea v-model="verifyForm.notes" rows="3" placeholder="Tulis catatan jika ada kekurangan pada dokumen..." class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2 text-sm text-slate-700 dark:text-slate-300 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                                </div>
                            </div>
                            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 flex justify-end gap-3 rounded-b-3xl">
                                <button type="button" @click="showVerifyModal = false" class="px-4 py-2 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">Batal</button>
                                <button type="submit" :disabled="verifyForm.processing" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold transition-colors disabled:opacity-50">
                                    {{ verifyForm.processing ? 'Menyimpan...' : 'Simpan Status' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
