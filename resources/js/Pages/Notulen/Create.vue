<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { ref, computed, nextTick } from 'vue'
import Editor from '@tinymce/tinymce-vue'
import SignaturePad from 'signature_pad'

const props = defineProps({
    users: {
        type: Array,
        default: () => []
    }
})

const tinymceApiKey = import.meta.env.VITE_TINYMCE_API_KEY || 'no-api-key'

// Form
const form = useForm({
    judul_rapat: '',
    jenis_rapat: '',
    tanggal_waktu: '',
    pimpinan_rapat: '',
    lokasi: '',
    peserta_rapat: '',
    isi_pembahasan: '',
    tindak_lanjut: '',
    ttd_notulis: '',
    dokumentasi: null
})

const jenisRapatOptions = [
    'Rutin Mingguan',
    'Panitia Wisuda',
    'Panitia SPMB',
    'Rapat Yayasan',
    'Rapat Wali Murid',
    'Lainnya'
]

// Custom Pimpinan Search
const searchPimpinan = ref('')
const showPimpinanList = ref(false)
const filteredPimpinan = computed(() => {
    if (!searchPimpinan.value) return props.users
    return props.users.filter(u => u.name.toLowerCase().includes(searchPimpinan.value.toLowerCase()))
})

const selectPimpinan = (name) => {
    form.pimpinan_rapat = name
    searchPimpinan.value = name
    showPimpinanList.value = false
}

const updatePimpinanManual = () => {
    form.pimpinan_rapat = searchPimpinan.value
}

// Custom Daftar Hadir Multi-select / Tagging
const searchHadir = ref('')
const showHadirList = ref(false)
const hadirTags = ref([])

const filteredHadir = computed(() => {
    if (!searchHadir.value) return props.users
    return props.users.filter(u => u.name.toLowerCase().includes(searchHadir.value.toLowerCase()))
})

const addHadir = (name) => {
    if (name && !hadirTags.value.includes(name)) {
        hadirTags.value.push(name)
    }
    searchHadir.value = ''
    showHadirList.value = false
    updateDaftarHadirForm()
}

const addHadirManual = () => {
    if (searchHadir.value && !hadirTags.value.includes(searchHadir.value)) {
        hadirTags.value.push(searchHadir.value)
        searchHadir.value = ''
        updateDaftarHadirForm()
    }
}

const removeHadir = (name) => {
    const idx = hadirTags.value.indexOf(name)
    if (idx > -1) {
        hadirTags.value.splice(idx, 1)
        updateDaftarHadirForm()
    }
}

const toggleHadir = (name) => {
    const idx = hadirTags.value.indexOf(name)
    if (idx > -1) {
        hadirTags.value.splice(idx, 1)
    } else {
        hadirTags.value.push(name)
    }
    updateDaftarHadirForm()
    checkSelectAllState()
}

const selectAllHadir = ref(false)
const toggleSelectAll = () => {
    if (selectAllHadir.value) {
        hadirTags.value = props.users.map(u => u.name)
    } else {
        // Hanya hapus yang ada di users, agar yang manual tidak hilang. 
        // Tapi lebih mudah kosongi semua.
        hadirTags.value = []
    }
    updateDaftarHadirForm()
}

const checkSelectAllState = () => {
    selectAllHadir.value = props.users.length > 0 && props.users.every(u => hadirTags.value.includes(u.name))
}

const updateDaftarHadirForm = () => {
    form.peserta_rapat = hadirTags.value.join(', ')
}

const hidePimpinanList = () => {
    setTimeout(() => {
        showPimpinanList.value = false
    }, 200)
}

const handleFileChange = (e) => {
    form.dokumentasi = e.target.files
}

// --- Signature Pad Logic ---
const showSignatureModal = ref(false)
const signatureCanvas = ref(null)
let signaturePadInstance = null

const openSignatureModal = () => {
    // Validasi basic sebelum buka modal
    if (!form.judul_rapat || !form.jenis_rapat || !form.tanggal_waktu || !form.pimpinan_rapat || !form.lokasi || !form.isi_pembahasan) {
        alert('Harap lengkapi semua field wajib (*) terlebih dahulu sebelum menyimpan.')
        return
    }

    showSignatureModal.value = true
    nextTick(() => {
        if (signatureCanvas.value) {
            signaturePadInstance = new SignaturePad(signatureCanvas.value, {
                backgroundColor: 'rgb(255, 255, 255)'
            })
            // Resize canvas to fix display issues
            const ratio =  Math.max(window.devicePixelRatio || 1, 1);
            signatureCanvas.value.width = signatureCanvas.value.offsetWidth * ratio;
            signatureCanvas.value.height = signatureCanvas.value.offsetHeight * ratio;
            signatureCanvas.value.getContext("2").scale(ratio, ratio);
            signaturePadInstance.clear();
        }
    })
}

const clearSignature = () => {
    if (signaturePadInstance) {
        signaturePadInstance.clear()
    }
}

const submitWithSignature = () => {
    if (signaturePadInstance && signaturePadInstance.isEmpty()) {
        alert('Tanda tangan tidak boleh kosong!')
        return
    }
    
    if (signaturePadInstance) {
        form.ttd_notulis = signaturePadInstance.toDataURL()
    }

    showSignatureModal.value = false
    
    form.post(route('notulen.store'), {
        preserveScroll: true
    })
}

const submit = () => {
    openSignatureModal()
}

// Editor setup
const editorInit = {
    height: 300,
    menubar: false,
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
        'insertdatetime', 'media', 'table', 'code', 'help', 'wordcount'
    ],
    toolbar: 'undo redo | blocks | ' +
    'bold italic forecolor | alignleft aligncenter ' +
    'alignright alignjustify | bullist numlist outdent indent | ' +
    'removeformat | help',
    content_style: 'body { font-family:Inter,Helvetica,Arial,sans-serif; font-size:14px }',
    promotion: false
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Buat Notulen Rapat" />

        <div class="w-full mx-auto pb-12">
            <div class="flex items-center gap-4 mb-6">
                <Link :href="route('notulen.index')" class="p-2 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-emerald-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">Buat Notulen Baru</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Isi formulir di bawah untuk mencatat hasil rapat.</p>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                <form @submit.prevent="submit">
                    <div class="p-6 md:p-8">
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                            
                            <!-- Kiri: Isi & Followup (Dominan) -->
                            <div class="lg:col-span-9 space-y-6">
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Isi Pembahasan / Hasil Rapat <span class="text-red-500">*</span></label>
                                    <Editor :api-key="tinymceApiKey" :init="editorInit" v-model="form.isi_pembahasan" />
                                    <div v-if="form.errors.isi_pembahasan" class="text-red-500 text-xs mt-1">{{ form.errors.isi_pembahasan }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Tindak Lanjut (Follow up)</label>
                                    <Editor :api-key="tinymceApiKey" :init="{ ...editorInit, height: 200 }" v-model="form.tindak_lanjut" />
                                    <div v-if="form.errors.tindak_lanjut" class="text-red-500 text-xs mt-1">{{ form.errors.tindak_lanjut }}</div>
                                </div>

                                <div class="pt-4 border-t border-slate-100 dark:border-slate-700">
                                    <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">Foto / Dokumentasi (Bisa lebih dari 1)</label>
                                    <input type="file" @change="handleFileChange" accept="image/*,.pdf" multiple class="w-full text-sm text-slate-500 dark:text-slate-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-emerald-900/30 dark:file:text-emerald-400 border border-slate-200 dark:border-slate-700 rounded-xl" />
                                    <div v-if="form.errors.dokumentasi" class="text-red-500 text-xs mt-1">{{ form.errors.dokumentasi }}</div>
                                </div>
                            </div>

                            <!-- Kanan: Identitas Rapat (Sidebar) -->
                            <div class="lg:col-span-3 space-y-5 bg-slate-50/50 dark:bg-slate-900/20 p-5 rounded-2xl border border-slate-100 dark:border-slate-700/50">
                                <h3 class="font-bold text-slate-800 dark:text-slate-200 border-b border-slate-200 dark:border-slate-700 pb-2 mb-4">Identitas Rapat</h3>
                                
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Judul Rapat <span class="text-red-500">*</span></label>
                                    <input type="text" v-model="form.judul_rapat" required class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500 text-slate-700 dark:text-slate-300" />
                                    <div v-if="form.errors.judul_rapat" class="text-red-500 text-xs mt-1">{{ form.errors.judul_rapat }}</div>
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Jenis Rapat <span class="text-red-500">*</span></label>
                                    <select v-model="form.jenis_rapat" required class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500 text-slate-700 dark:text-slate-300">
                                        <option value="" disabled>Pilih...</option>
                                        <option v-for="jenis in jenisRapatOptions" :key="jenis" :value="jenis">{{ jenis }}</option>
                                    </select>
                                    <input v-if="form.jenis_rapat === 'Lainnya'" type="text" placeholder="Tulis jenis..." class="mt-2 w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm" />
                                    <div v-if="form.errors.jenis_rapat" class="text-red-500 text-xs mt-1">{{ form.errors.jenis_rapat }}</div>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Tanggal & Waktu <span class="text-red-500">*</span></label>
                                    <input type="datetime-local" v-model="form.tanggal_waktu" required class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:ring-emerald-500 focus:border-emerald-500 text-slate-700 dark:text-slate-300" />
                                    <div v-if="form.errors.tanggal_waktu" class="text-red-500 text-xs mt-1">{{ form.errors.tanggal_waktu }}</div>
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Lokasi <span class="text-red-500">*</span></label>
                                    <input type="text" v-model="form.lokasi" required class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500 text-slate-700 dark:text-slate-300" />
                                    <div v-if="form.errors.lokasi" class="text-red-500 text-xs mt-1">{{ form.errors.lokasi }}</div>
                                </div>

                                <div class="relative">
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Pimpinan Rapat <span class="text-red-500">*</span></label>
                                    <input type="text" v-model="searchPimpinan" @input="updatePimpinanManual" @focus="showPimpinanList = true" @blur="hidePimpinanList" placeholder="Ketik..." class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500 text-slate-700 dark:text-slate-300" required />
                                    <div v-if="showPimpinanList && filteredPimpinan.length > 0" class="absolute z-10 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-lg max-h-48 overflow-y-auto">
                                        <ul class="py-1 text-xs text-slate-700 dark:text-slate-300">
                                            <li v-for="user in filteredPimpinan" :key="user.id" @mousedown="selectPimpinan(user.name)" class="px-3 py-2 hover:bg-emerald-50 dark:hover:bg-slate-700 cursor-pointer">
                                                {{ user.name }}
                                            </li>
                                        </ul>
                                    </div>
                                    <div v-if="form.errors.pimpinan_rapat" class="text-red-500 text-xs mt-1">{{ form.errors.pimpinan_rapat }}</div>
                                </div>

                                <div class="relative">
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Peserta Rapat (Diundang)</label>
                                    
                                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden flex flex-col h-[280px]">
                                        <!-- Search input for filter / manual entry -->
                                        <div class="p-2 border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 flex gap-2">
                                            <input type="text" v-model="searchHadir" @keydown.enter.prevent="addHadirManual" placeholder="Cari / ketik nama Tamu" class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1.5 text-xs focus:ring-emerald-500 focus:border-emerald-500 text-slate-700 dark:text-slate-300" />
                                            <button type="button" @click.prevent="addHadirManual" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition-colors shadow-sm shrink-0">
                                                Tambah
                                            </button>
                                        </div>
                                        
                                        <!-- List of checkboxes -->
                                        <div class="flex-1 overflow-y-auto p-2 custom-scrollbar">
                                            <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-lg cursor-pointer transition-colors border-b border-slate-100 dark:border-slate-800 mb-1">
                                                <input type="checkbox" v-model="selectAllHadir" @change="toggleSelectAll" class="w-3.5 h-3.5 text-emerald-600 border-slate-300 rounded focus:ring-emerald-500 dark:bg-slate-700 dark:border-slate-600" />
                                                <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Pilih Semua</span>
                                            </label>

                                            <!-- Manual Tags -->
                                            <div v-for="tag in hadirTags.filter(t => !props.users.find(u => u.name === t))" :key="tag" class="flex items-center gap-2 px-2 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-lg cursor-pointer transition-colors">
                                                <input type="checkbox" checked @change="removeHadir(tag)" class="w-3.5 h-3.5 text-emerald-600 border-slate-300 rounded focus:ring-emerald-500 dark:bg-slate-700 dark:border-slate-600" />
                                                <span class="text-xs text-slate-700 dark:text-slate-300">{{ tag }} (Tamu/Non-Pegawai)</span>
                                            </div>

                                            <label v-for="user in filteredHadir" :key="user.id" class="flex items-center gap-2 px-2 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-lg cursor-pointer transition-colors">
                                                <input type="checkbox" :checked="hadirTags.includes(user.name)" @change="toggleHadir(user.name)" class="w-3.5 h-3.5 text-emerald-600 border-slate-300 rounded focus:ring-emerald-500 dark:bg-slate-700 dark:border-slate-600" />
                                                <span class="text-xs text-slate-700 dark:text-slate-300">{{ user.name }}</span>
                                            </label>
                                            
                                            <div v-if="filteredHadir.length === 0" class="text-center py-4 text-xs text-slate-400">
                                                Tekan Enter untuk menambah sebagai Tamu/Non-Pegawai.
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="form.errors.peserta_rapat" class="text-red-500 text-xs mt-1">{{ form.errors.peserta_rapat }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 md:px-8 py-5 bg-slate-50 dark:bg-slate-800/50 flex justify-end gap-3 rounded-b-3xl border-t border-slate-100 dark:border-slate-700">
                        <Link :href="route('notulen.index')" class="px-5 py-2.5 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">Batal</Link>
                        <button type="button" @click="openSignatureModal" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold transition-colors shadow-sm disabled:opacity-50">
                            Simpan Notulen
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Signature Modal -->
        <div v-if="showSignatureModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-xl max-w-lg w-full overflow-hidden flex flex-col border border-slate-200 dark:border-slate-700">
                <div class="p-6 border-b border-slate-100 dark:border-slate-700">
                    <h3 class="text-xl font-bold text-slate-900 dark:text-slate-100">Tanda Tangan Notulis</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Silakan gambar tanda tangan Anda di bawah ini sebagai pengesahan dokumen pembuat notulen.</p>
                </div>
                
                <div class="p-6 bg-slate-50 dark:bg-slate-900/50">
                    <div class="bg-white border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl overflow-hidden touch-none">
                        <canvas ref="signatureCanvas" class="w-full h-48 cursor-crosshair"></canvas>
                    </div>
                </div>
                
                <div class="p-6 border-t border-slate-100 dark:border-slate-700 flex justify-between items-center">
                    <button type="button" @click="clearSignature" class="text-sm font-bold text-red-600 hover:text-red-700 transition-colors">Bersihkan</button>
                    <div class="flex gap-3">
                        <button type="button" @click="showSignatureModal = false" class="px-4 py-2 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-200 transition-colors">Batal</button>
                        <button type="button" @click="submitWithSignature" :disabled="form.processing" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold transition-colors disabled:opacity-50 flex items-center gap-2">
                            Simpan & Sahkan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
