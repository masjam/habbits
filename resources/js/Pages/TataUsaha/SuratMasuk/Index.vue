<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import Editor from '@tinymce/tinymce-vue'

const tinymceApiKey = import.meta.env.VITE_TINYMCE_API_KEY || 'no-api-key'

const props = defineProps({
    suratMasuk: Object,
    filters: Object,
})

const isModalOpen = ref(false)
const editingId = ref(null)
const searchQuery = ref(props.filters?.search || '')

let searchTimeout = null
watch(searchQuery, (newVal) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get(route('tata-usaha.surat-masuk.index'), { search: newVal }, { preserveState: true, preserveScroll: true, replace: true })
    }, 300)
})

const form = useForm({
    nomor_surat: '',
    tanggal_surat: '',
    tanggal_diterima: '',
    pengirim: '',
    perihal: '',
    jenis_surat: '',
    perlu_disposisi: false,
    file: null,
})

const openAddModal = () => {
    editingId.value = null
    form.reset()
    form.clearErrors()
    isModalOpen.value = true
}

const editSurat = (surat) => {
    editingId.value = surat.id
    form.nomor_surat = surat.nomor_surat
    form.tanggal_surat = surat.tanggal_surat ? surat.tanggal_surat.split('T')[0] : ''
    form.tanggal_diterima = surat.tanggal_diterima ? surat.tanggal_diterima.split('T')[0] : ''
    form.pengirim = surat.pengirim
    form.perihal = surat.perihal
    form.jenis_surat = surat.jenis_surat || ''
    form.perlu_disposisi = surat.perlu_disposisi ? true : false
    form.file = null
    isModalOpen.value = true
}

const closeModal = () => {
    isModalOpen.value = false
    editingId.value = null
    form.reset()
    form.clearErrors()
}

const handleFileChange = (e) => {
    form.file = e.target.files[0]
}

const saveSurat = () => {
    if (editingId.value) {
        form.transform((data) => ({
            ...data,
            _method: 'put',
        })).post(route('tata-usaha.surat-masuk.update', editingId.value), {
            onSuccess: () => closeModal(),
        })
    } else {
        form.post(route('tata-usaha.surat-masuk.store'), {
            onSuccess: () => closeModal(),
        })
    }
}

const formatDate = (dateString) => {
    if (!dateString) return '-'
    return dateString.split('T')[0]
}

const getJenisSurat = (surat) => {
    if (surat.jenis_surat) return surat.jenis_surat
    const perihal = (surat.perihal || '').toLowerCase()
    if (perihal.includes('tugas')) return 'Surat Tugas'
    if (perihal.includes('keterangan')) return 'Surat Keterangan'
    if (perihal.includes('undangan')) return 'Surat Undangan'
    if (perihal.includes('keputusan')) return 'Surat Keputusan'
    if (perihal.includes('edaran')) return 'Surat Edaran'
    if (perihal.includes('pemberitahuan')) return 'Surat Pemberitahuan'
    if (perihal.includes('permohonan')) return 'Surat Permohonan'
    return 'Surat Umum'
}

const deleteSurat = (id) => {
    if (confirm('Yakin ingin menghapus surat masuk ini?')) {
        router.delete(route('tata-usaha.surat-masuk.destroy', id), {
            preserveScroll: true
        })
    }
}

// Camera handling
const isCameraOpen = ref(false)
const videoRef = ref(null)
const canvasRef = ref(null)
const capturedImage = ref(null)
let mediaStream = null

const openCamera = async () => {
    isCameraOpen.value = true
    capturedImage.value = null
    try {
        mediaStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        if (videoRef.value) {
            videoRef.value.srcObject = mediaStream
        }
    } catch (error) {
        console.error("Error accessing camera:", error)
        alert("Tidak dapat mengakses kamera. Pastikan Anda telah memberikan izin.")
        closeCamera()
    }
}

const takePhoto = () => {
    if (!videoRef.value || !canvasRef.value) return
    const context = canvasRef.value.getContext('2d')
    canvasRef.value.width = videoRef.value.videoWidth
    canvasRef.value.height = videoRef.value.videoHeight
    context.drawImage(videoRef.value, 0, 0, canvasRef.value.width, canvasRef.value.height)
    
    capturedImage.value = canvasRef.value.toDataURL('image/jpeg', 0.8)
}

const retakePhoto = () => {
    capturedImage.value = null
}

const closeCamera = () => {
    if (mediaStream) {
        mediaStream.getTracks().forEach(track => track.stop())
        mediaStream = null
    }
    isCameraOpen.value = false
    capturedImage.value = null
}

const usePhoto = async () => {
    if (!capturedImage.value) return
    
    // Convert dataURL to File
    const res = await fetch(capturedImage.value)
    const blob = await res.blob()
    const file = new File([blob], `scan_surat_${Date.now()}.jpg`, { type: 'image/jpeg' })
    
    form.file = file
    closeCamera()
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Surat Masuk" />

        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-800 tracking-tight">Data Surat Masuk</h2>
                    <p class="text-sm text-slate-500 mt-1">
                        Manajemen pencatatan surat masuk ke instansi.
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row items-center gap-3">
                    <div class="relative w-full sm:w-64">
                        <input type="text" v-model="searchQuery" placeholder="Cari nomor, pengirim, perihal..." class="bg-white border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 pl-9 shadow-sm" />
                        <svg class="w-4 h-4 absolute left-3 top-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <button @click="openAddModal" class="inline-flex flex-1 sm:flex-none items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 text-white font-bold text-sm shadow-sm hover:bg-blue-700 transition-colors focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Tambah Surat</span>
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500 font-semibold border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3">No. Surat</th>
                                <th class="px-4 py-3">Tgl Surat / Diterima</th>
                                <th class="px-4 py-3">Pengirim</th>
                                <th class="px-4 py-3">Jenis Surat</th>
                                <th class="px-4 py-3">Perihal</th>
                                <th class="px-4 py-3">Penginput</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-if="suratMasuk.data.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-slate-500">Belum ada data surat masuk.</td>
                            </tr>
                            <tr v-for="surat in suratMasuk.data" :key="surat.id" class="bg-white hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-4 font-medium text-slate-800">{{ surat.nomor_surat }}</td>
                                <td class="px-4 py-4">
                                    <div class="text-xs">S: {{ formatDate(surat.tanggal_surat) }}</div>
                                    <div class="text-xs text-slate-400">D: {{ formatDate(surat.tanggal_diterima) }}</div>
                                </td>
                                <td class="px-4 py-4">{{ surat.pengirim }}</td>
                                <td class="px-4 py-4">
                                    <span class="px-2.5 py-1 rounded-md text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                        {{ getJenisSurat(surat) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4"><div class="line-clamp-2" v-html="surat.perihal"></div></td>
                                <td class="px-4 py-4 text-slate-500 text-xs">{{ surat.uploader?.name || '-' }}</td>
                                <td class="px-4 py-4 text-center">
                                    <span class="px-2 py-0.5 rounded text-xs font-bold uppercase tracking-wider"
                                          :class="{
                                              'bg-amber-100 text-amber-700': surat.status === 'baru',
                                              'bg-blue-100 text-blue-700': surat.status === 'didisposisikan',
                                              'bg-emerald-100 text-emerald-700': surat.status === 'selesai'
                                          }">
                                        {{ surat.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a v-if="surat.file_path" :href="`/storage/${surat.file_path}`" target="_blank" title="Lihat File" class="text-blue-500 hover:text-blue-700">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        </a>
                                        <button @click="editSurat(surat)" title="Edit" class="text-amber-500 hover:text-amber-700">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                        </button>
                                        <button @click="deleteSurat(surat.id)" title="Hapus" class="text-rose-500 hover:text-rose-700">
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
                        <form @submit.prevent="saveSurat">
                            <div class="bg-white px-4 pb-4 pt-5 sm:p-6">
                                <h3 class="text-lg font-bold text-slate-900 mb-4">{{ editingId ? 'Edit Surat Masuk' : 'Tambah Surat Masuk' }}</h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 mb-1">Nomor Surat</label>
                                        <input type="text" v-model="form.nomor_surat" class="w-full p-2 text-sm border-slate-300 rounded-lg" required />
                                        <p v-if="form.errors.nomor_surat" class="text-xs text-rose-500 mt-1">{{ form.errors.nomor_surat }}</p>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 mb-1">Tgl Surat</label>
                                            <input type="date" v-model="form.tanggal_surat" class="w-full p-2 text-sm border-slate-300 rounded-lg" required />
                                            <p v-if="form.errors.tanggal_surat" class="text-xs text-rose-500 mt-1">{{ form.errors.tanggal_surat }}</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 mb-1">Tgl Diterima</label>
                                            <input type="date" v-model="form.tanggal_diterima" class="w-full p-2 text-sm border-slate-300 rounded-lg" required />
                                            <p v-if="form.errors.tanggal_diterima" class="text-xs text-rose-500 mt-1">{{ form.errors.tanggal_diterima }}</p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 mb-1">Pengirim</label>
                                            <input type="text" v-model="form.pengirim" class="w-full p-2 text-sm border-slate-300 rounded-lg" required />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 mb-1">Jenis Surat</label>
                                            <input type="text" v-model="form.jenis_surat" class="w-full p-2 text-sm border-slate-300 rounded-lg" placeholder="Cth: Surat Undangan (Opsional)" />
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 mb-1">Perihal</label>
                                        <Editor
                                            :api-key="tinymceApiKey"
                                            v-model="form.perihal"
                                            :init="{
                                                height: 250,
                                                menubar: false,
                                                plugins: ['advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview', 'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen', 'insertdatetime', 'table', 'media', 'help', 'wordcount'],
                                                toolbar: 'undo redo | formatselect | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | removeformat | help',
                                                content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif; font-size: 14px; }'
                                            }"
                                        />
                                        <p v-if="form.errors.perihal" class="text-xs text-rose-500 mt-1">{{ form.errors.perihal }}</p>
                                    </div>
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <label class="block text-xs font-bold text-slate-500">File Upload (PDF/Image)</label>
                                            <button type="button" @click="openCamera" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                                Buka Kamera
                                            </button>
                                        </div>
                                        <input type="file" @change="handleFileChange" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                                        
                                        <div v-if="form.file && form.file.name.includes('scan_surat')" class="mt-2 text-xs text-emerald-600 font-medium">
                                            ✅ Gambar dari kamera siap digunakan.
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
                                        <input type="checkbox" id="perlu_disposisi" v-model="form.perlu_disposisi" class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500" />
                                        <label for="perlu_disposisi" class="text-xs font-bold text-slate-700">Surat ini memerlukan disposisi?</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-slate-50 px-4 py-3 flex flex-row-reverse">
                                <button type="submit" :disabled="form.processing" class="inline-flex w-full justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700 sm:ml-3 sm:w-auto">
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

        <!-- Camera Modal -->
        <div v-if="isCameraOpen" class="relative z-[60]">
            <div class="fixed inset-0 bg-slate-900/90 backdrop-blur-sm transition-opacity"></div>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="relative transform overflow-hidden rounded-2xl bg-black text-left shadow-2xl w-full max-w-lg border border-slate-700">
                        <div class="p-4 flex items-center justify-between border-b border-slate-800">
                            <h3 class="text-lg font-bold text-white">Ambil Foto Surat</h3>
                            <button @click="closeCamera" class="text-slate-400 hover:text-white">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        
                        <div class="relative bg-black flex items-center justify-center min-h-[300px]">
                            <!-- Video Feed -->
                            <video v-show="!capturedImage" ref="videoRef" autoplay playsinline class="w-full h-auto max-h-[60vh] object-contain"></video>
                            
                            <!-- Captured Image Preview -->
                            <img v-if="capturedImage" :src="capturedImage" class="w-full h-auto max-h-[60vh] object-contain" />
                            
                            <!-- Hidden Canvas for capture -->
                            <canvas ref="canvasRef" class="hidden"></canvas>
                        </div>
                        
                        <div class="p-4 bg-slate-900 flex items-center justify-center gap-4">
                            <template v-if="!capturedImage">
                                <button @click="takePhoto" class="w-16 h-16 rounded-full border-4 border-slate-300 flex items-center justify-center hover:border-white hover:bg-white/10 transition-all">
                                    <div class="w-12 h-12 bg-white rounded-full"></div>
                                </button>
                            </template>
                            <template v-else>
                                <button @click="retakePhoto" class="px-4 py-2 rounded-xl bg-slate-700 text-white font-bold text-sm hover:bg-slate-600">
                                    Ulangi
                                </button>
                                <button @click="usePhoto" class="px-4 py-2 rounded-xl bg-blue-600 text-white font-bold text-sm hover:bg-blue-700">
                                    Gunakan Foto
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
