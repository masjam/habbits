<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    documents: {
        type: Array,
        default: () => []
    }
})

// Modal State
const showUploadModal = ref(false)

// Form State
const form = useForm({
    document_type: '',
    title: '',
    document_number: '',
    document_year: '',
    file: null
})

// Tipe Dokumen Options
const documentTypes = [
    'Kartu Tanda Penduduk (KTP)',
    'Kartu Keluarga (KK)',
    'Ijazah SD',
    'Ijazah SMP',
    'Ijazah SMA/SMK',
    'Ijazah S1',
    'Ijazah S2 / S3',
    'SK Pengangkatan / Yayasan',
    'Sertifikat Pendidik',
    'Sertifikat Pelatihan',
    'Dokumen Lainnya'
]

const handleFileChange = (e) => {
    form.file = e.target.files[0]
}

const submit = () => {
    form.post(route('employee-documents.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showUploadModal.value = false
            form.reset()
        }
    })
}

const deleteDocument = (id) => {
    if (confirm('Apakah Anda yakin ingin menghapus dokumen ini?')) {
        router.delete(route('employee-documents.destroy', id), {
            preserveScroll: true
        })
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Arsip Kepegawaian" />

        <div class="space-y-6 w-full pb-8">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 dark:text-slate-100 tracking-tight">Arsip Kepegawaian</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Kelola dan simpan dokumen penting Anda secara digital.
                    </p>
                </div>
                
                <button @click="showUploadModal = true" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-sm transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Unggah Dokumen
                </button>
            </div>

            <!-- Upload Modal -->
            <div v-if="showUploadModal" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>
                <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                        <div class="relative transform overflow-hidden rounded-3xl bg-white dark:bg-slate-800 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-100 dark:border-slate-700">
                            <form @submit.prevent="submit">
                                <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700">
                                    <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">Unggah Dokumen Baru</h3>
                                </div>
                                <div class="px-6 py-5 space-y-4">
                                    
                                    <div>
                                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">Kategori Dokumen <span class="text-red-500">*</span></label>
                                        <select v-model="form.document_type" required class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2 text-sm text-slate-700 dark:text-slate-300 focus:ring-emerald-500 focus:border-emerald-500">
                                            <option value="" disabled>Pilih Kategori...</option>
                                            <option v-for="type in documentTypes" :key="type" :value="type">{{ type }}</option>
                                        </select>
                                        <div v-if="form.errors.document_type" class="text-red-500 text-xs mt-1">{{ form.errors.document_type }}</div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">Nama Dokumen / Judul <span class="text-red-500">*</span></label>
                                        <input type="text" v-model="form.title" required placeholder="Cth: SK Pengangkatan Guru Tetap" class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2 text-sm text-slate-700 dark:text-slate-300 focus:ring-emerald-500 focus:border-emerald-500" />
                                        <div v-if="form.errors.title" class="text-red-500 text-xs mt-1">{{ form.errors.title }}</div>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">Nomor (Opsional)</label>
                                            <input type="text" v-model="form.document_number" placeholder="Nomor SK / Ijazah" class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2 text-sm text-slate-700 dark:text-slate-300 focus:ring-emerald-500 focus:border-emerald-500" />
                                            <div v-if="form.errors.document_number" class="text-red-500 text-xs mt-1">{{ form.errors.document_number }}</div>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">Tahun (Opsional)</label>
                                            <input type="text" v-model="form.document_year" placeholder="Cth: 2024" maxlength="4" class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2 text-sm text-slate-700 dark:text-slate-300 focus:ring-emerald-500 focus:border-emerald-500" />
                                            <div v-if="form.errors.document_year" class="text-red-500 text-xs mt-1">{{ form.errors.document_year }}</div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">File Dokumen <span class="text-red-500">*</span></label>
                                        <input type="file" @change="handleFileChange" accept=".pdf,.jpg,.jpeg,.png" required class="w-full text-sm text-slate-500 dark:text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-emerald-900/30 dark:file:text-emerald-400" />
                                        <p class="text-xs text-slate-400 mt-1">Format: PDF, JPG, PNG. Maksimal 2MB.</p>
                                        <div v-if="form.errors.file" class="text-red-500 text-xs mt-1">{{ form.errors.file }}</div>
                                    </div>
                                </div>
                                <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 flex justify-end gap-3 rounded-b-3xl">
                                    <button type="button" @click="showUploadModal = false" class="px-4 py-2 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">Batal</button>
                                    <button type="submit" :disabled="form.processing" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold transition-colors disabled:opacity-50">
                                        {{ form.processing ? 'Menyimpan...' : 'Simpan Dokumen' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documents Grid -->
            <div v-if="documents.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="doc in documents" :key="doc.id" class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden flex flex-col transition-all hover:shadow-md">
                    <div class="p-5 flex-1">
                        <div class="flex items-start justify-between mb-3">
                            <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0" :class="doc.file_extension === 'pdf' ? 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' : 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400'">
                                <svg v-if="doc.file_extension === 'pdf'" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                <svg v-else class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            </div>
                            <span v-if="doc.is_verified" class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400 px-2.5 py-1 rounded-full text-xs font-bold">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                Terverifikasi
                            </span>
                            <span v-else class="inline-flex items-center gap-1 bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400 px-2.5 py-1 rounded-full text-xs font-bold">
                                Menunggu
                            </span>
                        </div>
                        
                        <div class="inline-block px-2 py-1 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded text-[10px] font-bold uppercase tracking-wider mb-2">
                            {{ doc.document_type }}
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100 line-clamp-2 leading-snug">{{ doc.title }}</h3>
                        
                        <div class="mt-4 space-y-1">
                            <div v-if="doc.document_number" class="text-sm text-slate-500 dark:text-slate-400 flex items-start gap-2">
                                <span class="font-medium shrink-0">Nomor:</span> <span class="truncate">{{ doc.document_number }}</span>
                            </div>
                            <div v-if="doc.document_year" class="text-sm text-slate-500 dark:text-slate-400 flex items-start gap-2">
                                <span class="font-medium shrink-0">Tahun:</span> <span>{{ doc.document_year }}</span>
                            </div>
                        </div>

                        <div v-if="doc.notes" class="mt-3 p-3 bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-900/50 rounded-xl text-sm text-red-700 dark:text-red-400">
                            <strong>Catatan HR:</strong> {{ doc.notes }}
                        </div>
                    </div>
                    
                    <div class="p-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
                        <div class="text-xs text-slate-400">
                            Diunggah: {{ new Date(doc.created_at).toLocaleDateString('id-ID') }}
                        </div>
                        <div class="flex items-center gap-2">
                            <a :href="route('employee-documents.download', doc.id)" target="_blank" class="p-2 text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 bg-white dark:bg-slate-700 hover:bg-emerald-50 dark:hover:bg-slate-600 rounded-lg shadow-sm border border-slate-200 dark:border-slate-600 transition-colors" title="Unduh">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            </a>
                            <button v-if="!doc.is_verified" @click="deleteDocument(doc.id)" class="p-2 text-slate-400 hover:text-red-600 dark:hover:text-red-400 bg-white dark:bg-slate-700 hover:bg-red-50 dark:hover:bg-slate-600 rounded-lg shadow-sm border border-slate-200 dark:border-slate-600 transition-colors" title="Hapus">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="bg-white dark:bg-slate-800 rounded-3xl border border-dashed border-slate-300 dark:border-slate-700 p-12 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-50 dark:bg-slate-900/50 text-slate-400 mb-4 shadow-inner">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" /></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-200">Belum Ada Dokumen</h3>
                <p class="text-slate-500 dark:text-slate-400 mt-2 max-w-sm mx-auto">Anda belum mengunggah dokumen apapun. Silakan klik tombol "Unggah Dokumen" untuk mulai menyimpan arsip Anda.</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
