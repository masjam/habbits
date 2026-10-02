<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Editor from '@tinymce/tinymce-vue'

const props = defineProps({
    journals: {
        type: Object,
        default: () => ({ data: [] })
    }
})

const showModal = ref(false)
const isEdit = ref(false)
const currentJournalId = ref(null)
const activeFormTab = ref('detail')

const tinymceApiKey = import.meta.env.VITE_TINYMCE_API_KEY || 'no-api-key'

// --- Camera Modal State ---
const isCameraModalOpen = ref(false)
const cameraStream = ref(null)
const videoRef = ref(null)
const cameraError = ref(null)
const isCameraLoading = ref(false)

const startCamera = async () => {
    isCameraModalOpen.value = true
    isCameraLoading.value = true
    cameraError.value = null
    try {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: false
            })
            cameraStream.value = stream
            setTimeout(() => {
                if (videoRef.value) {
                    videoRef.value.srcObject = stream
                }
            }, 100)
        } else {
            cameraError.value = 'Kamera tidak didukung pada browser ini.'
        }
    } catch (e) {
        cameraError.value = 'Gagal mengakses kamera: ' + (e.message || 'Izin ditolak.')
    } finally {
        isCameraLoading.value = false
    }
}

const stopCamera = () => {
    if (cameraStream.value) {
        cameraStream.value.getTracks().forEach(track => track.stop())
        cameraStream.value = null
    }
    isCameraModalOpen.value = false
}

const dataURLtoFile = (dataurl, filename) => {
    let arr = dataurl.split(','),
        mime = arr[0].match(/:(.*?);/)[1],
        bstr = atob(arr[1]), 
        n = bstr.length, 
        u8arr = new Uint8Array(n);
        
    while(n--){
        u8arr[n] = bstr.charCodeAt(n);
    }
    return new File([u8arr], filename, {type:mime});
}

const takeSnapshot = () => {
    if (!videoRef.value) return
    const video = videoRef.value
    const canvas = document.createElement('canvas')
    canvas.width = video.videoWidth || 640
    canvas.height = video.videoHeight || 480
    const ctx = canvas.getContext('2d')
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height)
    
    const dataUrl = canvas.toDataURL('image/jpeg', 0.8)
    const file = dataURLtoFile(dataUrl, `foto_jurnal_${new Date().getTime()}.jpg`)
    
    form.photo = file
    stopCamera()
}

const summarizeHtml = (html, length = 250) => {
    if (!html) return '-';
    let doc = new DOMParser().parseFromString(html, 'text/html');
    let text = doc.body.textContent || "";
    if (text.length <= length) return text;
    return text.substring(0, length) + '...';
}

const form = useForm({
    date: '',
    class_name: '',
    subject: '',
    theme: '',
    meeting_number: '',
    period: '',
    time_start: '',
    time_end: '',
    material: '',
    notes: '',
    photo: null,
    student_present: 0,
    student_sick: 0,
    student_leave: 0,
    student_absent: 0,
})

const openCreateModal = () => {
    isEdit.value = false
    activeFormTab.value = 'detail'
    currentJournalId.value = null
    form.reset()
    form.clearErrors()
    isCameraModalOpen.value = false // Ensure camera is closed initially
    showModal.value = true
}

const openEditModal = (journal) => {
    isEdit.value = true
    currentJournalId.value = journal.id
    form.clearErrors()
    form.date = journal.date || ''
    form.class_name = journal.class_name || ''
    form.subject = journal.subject || ''
    form.theme = journal.theme || ''
    form.meeting_number = journal.meeting_number || ''
    form.period = journal.period || ''
    form.time_start = journal.time_start ? journal.time_start.substring(0, 5) : ''
    form.time_end = journal.time_end ? journal.time_end.substring(0, 5) : ''
    form.material = journal.material || ''
    form.notes = journal.notes || ''
    form.photo = null
    form.student_present = journal.student_present || 0
    form.student_sick = journal.student_sick || 0
    form.student_leave = journal.student_leave || 0
    form.student_absent = journal.student_absent || 0
    
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
    activeFormTab.value = 'detail'
}

const submit = () => {
    if (isEdit.value) {
        form.post(route('teacher-journals.update', currentJournalId.value), {
            preserveScroll: true,
            forceFormData: true, 
            onBefore: () => {
                form._method = 'PUT'
            },
            onSuccess: () => closeModal()
        })
    } else {
        form.post(route('teacher-journals.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal()
        })
    }
}
</script>

<template>
    <Head title="Jurnal Harian Guru" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Jurnal Harian Guru</h2>
        </template>

        <div class="py-12">
            <div class="w-full mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-medium">Daftar Jurnal</h3>
                            <button 
                                @click="openCreateModal"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                            >
                                Tambah Jurnal
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th scope="col" class="px-6 py-3">Tanggal</th>
                                        <th scope="col" class="px-6 py-3">Kelas</th>
                                        <th scope="col" class="px-6 py-3">Mapel</th>
                                        <th scope="col" class="px-6 py-3">Tema</th>
                                        <th scope="col" class="px-6 py-3">Pertemuan</th>
                                        <th scope="col" class="px-6 py-3">Uraian</th>
                                        <th scope="col" class="px-6 py-3">Catatan</th>
                                        <th scope="col" class="px-6 py-3">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="journal in journals.data" :key="journal.id" class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <td class="px-6 py-4">{{ journal.date }}</td>
                                        <td class="px-6 py-4">{{ journal.class_name || '-' }}</td>
                                        <td class="px-6 py-4">{{ journal.subject || '-' }}</td>
                                        <td class="px-6 py-4">{{ journal.theme || '-' }}</td>
                                        <td class="px-6 py-4">{{ journal.meeting_number || '-' }}</td>
                                        <td class="px-6 py-4" :title="journal.material">{{ summarizeHtml(journal.material, 250) }}</td>
                                        <td class="px-6 py-4" :title="journal.notes">{{ summarizeHtml(journal.notes, 50) }}</td>
                                        <td class="px-6 py-4 flex space-x-2">
                                            <button 
                                                @click="openEditModal(journal)"
                                                class="text-blue-600 hover:underline"
                                            >Edit</button>
                                            
                                            <Link 
                                                :href="route('teacher-journals.destroy', journal.id)"
                                                method="delete"
                                                as="button"
                                                class="text-red-600 hover:underline ml-2"
                                                preserve-scroll
                                            >Hapus</Link>
                                        </td>
                                    </tr>
                                    <tr v-if="journals.data.length === 0">
                                        <td colspan="8" class="px-6 py-4 text-center">Belum ada data jurnal.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Form -->
        <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="closeModal"></div>

                <!-- Center modal vertically -->
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <!-- Modal panel -->
                <div class="relative z-10 inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex justify-between items-center mb-4 border-b pb-3 border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100" id="modal-title">
                                {{ isEdit ? 'Edit Jurnal' : 'Tambah Jurnal Harian' }}
                            </h3>
                            <button @click="closeModal" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                <span class="sr-only">Tutup</span>
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="flex lg:hidden border-b dark:border-gray-700 mb-4 overflow-x-auto">
                            <button type="button" @click="activeFormTab = 'detail'" :class="['flex-1 py-2 px-2 text-sm font-medium border-b-2 whitespace-nowrap transition-colors', activeFormTab === 'detail' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300']">
                                Detail Jurnal
                            </button>
                            <button type="button" @click="activeFormTab = 'uraian'" :class="['flex-1 py-2 px-2 text-sm font-medium border-b-2 whitespace-nowrap transition-colors', activeFormTab === 'uraian' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300']">
                                Uraian & Bukti
                            </button>
                        </div>
                        
                        <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                            
                            <!-- KIRI: Input Singkat (Porsi lebih kecil) -->
                            <div :class="['lg:col-span-3 space-y-4 lg:block', activeFormTab === 'detail' ? 'block' : 'hidden']">
                                <div>
                                    <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Tanggal</label>
                                    <input type="date" v-model="form.date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm" required>
                                    <p v-if="form.errors.date" class="text-xs text-red-600 mt-1">{{ form.errors.date }}</p>
                                </div>
                                <div>
                                    <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Kelas (Opsional)</label>
                                    <input type="text" v-model="form.class_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                                    <p v-if="form.errors.class_name" class="text-xs text-red-600 mt-1">{{ form.errors.class_name }}</p>
                                </div>
                                <div>
                                    <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Mata Pelajaran (Opsional)</label>
                                    <input type="text" v-model="form.subject" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                                    <p v-if="form.errors.subject" class="text-xs text-red-600 mt-1">{{ form.errors.subject }}</p>
                                </div>
                                <div>
                                    <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Tema / Subtema (Opsional)</label>
                                    <input type="text" v-model="form.theme" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                                    <p v-if="form.errors.theme" class="text-xs text-red-600 mt-1">{{ form.errors.theme }}</p>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Pertemuan (Opsional)</label>
                                        <input type="text" v-model="form.meeting_number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                                        <p v-if="form.errors.meeting_number" class="text-xs text-red-600 mt-1">{{ form.errors.meeting_number }}</p>
                                    </div>
                                    <div>
                                        <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Jam Ke- (Opsional)</label>
                                        <input type="text" v-model="form.period" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                                        <p v-if="form.errors.period" class="text-xs text-red-600 mt-1">{{ form.errors.period }}</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Waktu Mulai</label>
                                        <input type="time" v-model="form.time_start" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm" required>
                                        <p v-if="form.errors.time_start" class="text-xs text-red-600 mt-1">{{ form.errors.time_start }}</p>
                                    </div>
                                    <div>
                                        <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Waktu Selesai</label>
                                        <input type="time" v-model="form.time_end" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm" required>
                                        <p v-if="form.errors.time_end" class="text-xs text-red-600 mt-1">{{ form.errors.time_end }}</p>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="font-medium text-sm text-gray-700 dark:text-gray-300 mb-2 border-b dark:border-gray-600 pb-1">Presensi Siswa (Opsional)</h4>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-[11px] text-gray-500 uppercase font-semibold">Hadir</label>
                                            <input type="number" min="0" v-model="form.student_present" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500 uppercase font-semibold">Sakit</label>
                                            <input type="number" min="0" v-model="form.student_sick" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500 uppercase font-semibold">Izin</label>
                                            <input type="number" min="0" v-model="form.student_leave" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500 uppercase font-semibold">Alpa</label>
                                            <input type="number" min="0" v-model="form.student_absent" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="block font-medium text-sm text-gray-700 dark:text-gray-300 mb-1">Bukti Foto (Opsional)</label>
                                    
                                    <div class="flex gap-2">
                                        <button type="button" @click="startCamera" class="flex-1 flex items-center justify-center gap-1 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 border border-gray-300 dark:border-gray-600 rounded text-xs py-2 text-gray-700 dark:text-gray-200 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                            Kamera
                                        </button>
                                        <button type="button" @click="$refs.galleryInput.click()" class="flex-1 flex items-center justify-center gap-1 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 border border-gray-300 dark:border-gray-600 rounded text-xs py-2 text-gray-700 dark:text-gray-200 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            Galeri
                                        </button>
                                    </div>
                                    
                                    <div v-if="form.photo" class="mt-2 text-xs text-green-600 dark:text-green-400 flex items-center gap-1 bg-green-50 dark:bg-green-900/30 p-2 rounded">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="truncate">{{ form.photo.name || 'Foto siap diunggah' }}</span>
                                        <button type="button" @click="form.photo = null; $refs.galleryInput.value = ''" class="ml-auto text-red-500 hover:text-red-700 font-semibold p-1">Hapus</button>
                                    </div>

                                    <input type="file" ref="galleryInput" @change="form.photo = $event.target.files[0]" accept="image/*" class="hidden">
                                    <p v-if="form.errors.photo" class="text-xs text-red-600 mt-1">{{ form.errors.photo }}</p>
                                </div>
                            </div>

                            <!-- KANAN: Textarea Panjang (Porsi lebih besar) -->
                            <div :class="['lg:col-span-9 flex-col space-y-4 lg:flex', activeFormTab === 'uraian' ? 'flex' : 'hidden']">
                                <div class="flex-1 flex flex-col">
                                    <label class="block font-medium text-sm text-gray-700 dark:text-gray-300 mb-2">Uraian Materi / Kegiatan</label>
                                    <Editor
                                        :api-key="tinymceApiKey"
                                        v-model="form.material"
                                        :init="{
                                            height: 300,
                                            menubar: false,
                                            plugins: ['advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview', 'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen', 'insertdatetime', 'media', 'table', 'help', 'wordcount'],
                                            toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help'
                                        }"
                                    />
                                    <p v-if="form.errors.material" class="text-xs text-red-600 mt-1">{{ form.errors.material }}</p>
                                </div>

                                <div>
                                    <label class="block font-medium text-sm text-gray-700 dark:text-gray-300 mb-2">Catatan / Keterangan Tambahan</label>
                                    <Editor
                                        :api-key="tinymceApiKey"
                                        v-model="form.notes"
                                        :init="{
                                            height: 200,
                                            menubar: false,
                                            plugins: ['advlist', 'autolink', 'lists', 'link', 'charmap', 'preview', 'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen', 'insertdatetime', 'table', 'help', 'wordcount'],
                                            toolbar: 'undo redo | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help'
                                        }"
                                    />
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 flex flex-row-reverse justify-between sm:justify-start">
                        <div class="flex">
                            <button type="button" @click="submit" :disabled="form.processing" class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 ml-3 sm:w-auto sm:text-sm">
                                Simpan
                            </button>
                            <button type="button" @click="closeModal" class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto sm:text-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                                Batal
                            </button>
                        </div>
                        <button v-if="activeFormTab === 'detail'" @click="activeFormTab = 'uraian'" type="button" class="lg:hidden inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:text-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600">
                            Selanjutnya &raquo;
                        </button>
                        <button v-if="activeFormTab === 'uraian'" @click="activeFormTab = 'detail'" type="button" class="lg:hidden inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:text-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600">
                            &laquo; Kembali
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>

    <!-- Camera Modal -->
    <div v-if="isCameraModalOpen" class="fixed inset-0 z-[60] overflow-y-auto" aria-labelledby="camera-modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-900 bg-opacity-90 transition-opacity" aria-hidden="true" @click="stopCamera"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-black rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full relative">
                <div class="p-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-white">Ambil Foto</h3>
                        <button @click="stopCamera" class="text-gray-400 hover:text-white">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    
                    <div class="relative bg-gray-800 rounded-lg overflow-hidden aspect-video flex items-center justify-center">
                        <p v-if="isCameraLoading" class="text-white text-sm">Memuat kamera...</p>
                        <p v-else-if="cameraError" class="text-red-400 text-sm p-4 text-center">{{ cameraError }}</p>
                        <video v-else ref="videoRef" class="w-full h-full object-cover" autoplay playsinline></video>
                    </div>
                    
                    <div class="mt-4 flex justify-center">
                        <button @click="takeSnapshot" :disabled="isCameraLoading || !!cameraError" class="bg-blue-600 hover:bg-blue-700 text-white rounded-full p-4 shadow-lg disabled:opacity-50 transition transform active:scale-95">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><circle cx="12" cy="13" r="4" stroke-width="2"></circle></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
