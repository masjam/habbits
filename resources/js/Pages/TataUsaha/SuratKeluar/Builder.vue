<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Editor from '@tinymce/tinymce-vue'
import { ref, computed } from 'vue'

const props = defineProps({
    next_nomor: String,
    current_nomor: String,
    tanggal_masehi: String,
    tanggal_hijriah: String,
    isEdit: { type: Boolean, default: false },
    suratId: { type: Number, default: null },
    builderData: { type: Object, default: () => null },
    users: { type: Array, default: () => [] },
})

const defaultMode = props.builderData?.nomor_mode || (props.isEdit ? 'manual' : 'otomatis')

const form = useForm({
    jenis_surat: props.builderData?.jenis_surat || 'Umum',
    nomor_mode: defaultMode,
    nomor_surat: props.builderData?.nomor_surat || props.current_nomor || props.next_nomor || '',
    lampiran: props.builderData?.lampiran || '-',
    perihal: props.builderData?.perihal || 'Surat Edaran',
    tanggal_masehi: props.builderData?.tanggal_masehi || props.tanggal_masehi,
    tanggal_hijriah: props.builderData?.tanggal_hijriah || props.tanggal_hijriah,
    kepada: props.builderData?.kepada || 'Bapak/Ibu Orangtua Wali Murid Kelas I-VI',
    di: props.builderData?.di || 'tempat',
    pegawai_ditugaskan: props.builderData?.pegawai_ditugaskan || [],
    pokok_tugas: props.builderData?.pokok_tugas || '',
    isi_surat: props.builderData?.isi_surat || '<p>Dalam rangka menindaklanjuti...</p>',
    line_spacing: props.builderData?.line_spacing || '1.15',
    paragraph_indent: props.builderData?.paragraph_indent || '0'
})

const customNomorDraft = ref(
    form.nomor_mode === 'manual'
        ? form.nomor_surat
        : (props.current_nomor || props.next_nomor || '')
)

const setNomorMode = (mode) => {
    form.nomor_mode = mode
    if (mode === 'otomatis') {
        form.nomor_surat = props.next_nomor || ''
    } else {
        form.nomor_surat = customNomorDraft.value || props.next_nomor || ''
    }
}

const onCustomNomorInput = (val) => {
    customNomorDraft.value = val
}

const searchPegawai = ref('')

const filteredUsers = computed(() => {
    if (!searchPegawai.value) return props.users
    const lowerSearch = searchPegawai.value.toLowerCase()
    return props.users.filter(u => u.name.toLowerCase().includes(lowerSearch))
})

const isPreviewing = ref(false)

const previewPdf = async () => {
    // Because we are downloading a file from a POST request, we use native fetch
    isPreviewing.value = true
    try {
        const response = await fetch(route('tata-usaha.surat-keluar.preview'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify(form.data())
        })
        
        if (response.ok) {
            const blob = await response.blob()
            const url = window.URL.createObjectURL(blob)
            window.open(url, '_blank')
        } else {
            alert('Gagal membuat preview PDF.')
        }
    } catch (e) {
        console.error(e)
        alert('Terjadi kesalahan jaringan.')
    } finally {
        isPreviewing.value = false
    }
}

const submit = () => {
    if (props.isEdit) {
        form.post(route('tata-usaha.surat-keluar.update-builder', props.suratId), {
            preserveScroll: true
        })
    } else {
        form.post(route('tata-usaha.surat-keluar.generate'), {
            preserveScroll: true
        })
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Buat Surat Keluar Otomatis" />

        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <Link :href="route('tata-usaha.surat-keluar.index')" class="text-slate-500 hover:text-slate-700">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                </Link>
                <div>
                    <h2 class="text-xl font-bold text-slate-800 tracking-tight">Formulir Generator Surat Keluar</h2>
                    <p class="text-sm text-slate-500 mt-1">
                        Surat akan di-generate ke dalam format PDF menggunakan kop surat sekolah secara otomatis.
                    </p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden p-6">
                <form @submit.prevent="submit" class="space-y-6">
                    
                    <!-- Format Surat -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4">
                        <label class="block text-sm font-bold text-slate-700 mb-2">Pilih Format Surat</label>
                        <div class="flex flex-wrap gap-4">
                            <label class="inline-flex items-center">
                                <input type="radio" v-model="form.jenis_surat" value="Umum" class="text-emerald-600 focus:ring-emerald-500 w-4 h-4" />
                                <span class="ml-2 text-sm text-slate-700">Surat Umum (Edaran, Undangan)</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" v-model="form.jenis_surat" value="Tugas" class="text-emerald-600 focus:ring-emerald-500 w-4 h-4" />
                                <span class="ml-2 text-sm text-slate-700">Surat Tugas</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" v-model="form.jenis_surat" value="Keterangan" class="text-emerald-600 focus:ring-emerald-500 w-4 h-4" />
                                <span class="ml-2 text-sm text-slate-700">Surat Keterangan</span>
                            </label>
                        </div>
                    </div>

                    <!-- Pilihan Penomoran Surat (Otomatis vs Custom Manual) -->
                    <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100">
                            <div>
                                <label class="block text-sm font-bold text-slate-800">Penomoran Surat Keluar</label>
                                <p class="text-xs text-slate-500 mt-0.5">Pilih penomoran otomatis sesuai urutan sistem sekolah atau gunakan nomor custom (manual).</p>
                            </div>
                            <div class="inline-flex rounded-lg bg-slate-100 p-1 border border-slate-200 shrink-0">
                                <button
                                    type="button"
                                    @click="setNomorMode('otomatis')"
                                    :class="form.nomor_mode === 'otomatis' ? 'bg-white text-emerald-700 shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900 font-medium'"
                                    class="px-3 py-1.5 rounded-md text-xs transition-all flex items-center gap-1.5"
                                >
                                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    <span>Nomor Otomatis</span>
                                </button>
                                <button
                                    type="button"
                                    @click="setNomorMode('manual')"
                                    :class="form.nomor_mode === 'manual' ? 'bg-white text-amber-700 shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900 font-medium'"
                                    class="px-3 py-1.5 rounded-md text-xs transition-all flex items-center gap-1.5"
                                >
                                    <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    <span>Nomor Custom (Manual)</span>
                                </button>
                            </div>
                        </div>

                        <!-- Mode Otomatis View -->
                        <div v-if="form.nomor_mode === 'otomatis'" class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 rounded-xl bg-emerald-50/70 border border-emerald-200/80">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-emerald-800 uppercase tracking-wider">Nomor Surat Otomatis</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        Otomatis Sistem
                                    </span>
                                </div>
                                <div class="text-base sm:text-lg font-mono font-bold text-emerald-950">
                                    {{ form.nomor_surat }}
                                </div>
                                <p class="text-xs text-emerald-700">
                                    Nomor dihitung otomatis berdasarkan nomor urut arsip surat keluar tahun {{ new Date().getFullYear() }}.
                                </p>
                            </div>
                            <button
                                type="button"
                                @click="setNomorMode('manual')"
                                class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 hover:text-emerald-900 bg-white hover:bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-lg transition-colors shadow-xs shrink-0"
                            >
                                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                                <span>Ganti ke Nomor Custom</span>
                            </button>
                        </div>

                        <!-- Mode Manual View -->
                        <div v-else class="space-y-2 p-4 rounded-xl bg-amber-50/70 border border-amber-200/80">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-bold text-amber-900 uppercase">Input Nomor Surat Custom (Manual)</label>
                                <button
                                    type="button"
                                    @click="setNomorMode('otomatis')"
                                    class="text-xs text-amber-700 hover:text-amber-900 font-semibold underline underline-offset-2"
                                >
                                    Kembali ke Nomor Otomatis
                                </button>
                            </div>
                            <div class="relative">
                                <input
                                    type="text"
                                    v-model="form.nomor_surat"
                                    @input="onCustomNomorInput($event.target.value)"
                                    placeholder="Contoh: 05/Pan.Milad/SDM/X/2026 atau 421.2/100/SDM/2026"
                                    class="w-full p-2.5 text-sm font-mono border-amber-300 rounded-lg focus:ring-amber-500 focus:border-amber-500 bg-white"
                                    required
                                />
                            </div>
                            <p v-if="form.errors.nomor_surat" class="text-xs text-rose-500 font-semibold">{{ form.errors.nomor_surat }}</p>
                            <p class="text-xs text-amber-800">
                                Format nomor bebas sesuai kebutuhan agenda, kepanitiaan, atau kode klasifikasi khusus. Pastikan nomor belum pernah dipakai sebelumnya.
                            </p>
                        </div>
                    </div>

                    <!-- Informasi Meta Surat (Hanya untuk Surat Umum) -->
                    <div v-if="form.jenis_surat === 'Umum'" class="grid grid-cols-1 md:grid-cols-4 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Lampiran</label>
                            <input type="text" v-model="form.lampiran" class="w-full p-2 text-sm border-slate-300 rounded-lg" required />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Hal / Perihal</label>
                            <input type="text" v-model="form.perihal" class="w-full p-2 text-sm border-slate-300 rounded-lg" required />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Kepada Yth.</label>
                            <input type="text" v-model="form.kepada" class="w-full p-2 text-sm border-slate-300 rounded-lg" required />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Di (Tempat)</label>
                            <input type="text" v-model="form.di" class="w-full p-2 text-sm border-slate-300 rounded-lg" required />
                        </div>
                        
                        <!-- Hidden Inputs -->
                        <input type="hidden" v-model="form.tanggal_hijriah">
                        <input type="hidden" v-model="form.tanggal_masehi">
                    </div>
                    <div v-else-if="form.jenis_surat === 'Tugas'" class="grid grid-cols-1 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                        <p class="text-xs text-slate-500 mb-2">
                            <i>Silakan pilih pegawai yang ditugaskan dan isi pokok kegiatan. Detail kegiatan bisa ditulis di bagian Isi Surat di bawah.</i>
                        </p>
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Pegawai Yang Ditugaskan</label>
                            <div class="border border-slate-300 rounded-lg p-2 bg-white">
                                <input type="text" v-model="searchPegawai" placeholder="Cari nama pegawai..." class="w-full p-2 mb-2 text-sm border-slate-200 rounded bg-slate-50 focus:bg-white transition-colors" />
                                <div class="max-h-64 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2 p-1">
                                    <label v-for="user in filteredUsers" :key="user.id" class="flex items-center space-x-2 p-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors border border-transparent hover:border-slate-200">
                                        <input type="checkbox" :value="user.name" v-model="form.pegawai_ditugaskan" class="w-4 h-4 text-emerald-600 border-slate-300 rounded focus:ring-emerald-500 flex-shrink-0">
                                        <span class="text-xs text-slate-700 select-none line-clamp-1" :title="user.name">{{ user.name }}</span>
                                    </label>
                                    <div v-if="filteredUsers.length === 0" class="col-span-full text-xs text-slate-500 p-3 text-center">Pegawai tidak ditemukan.</div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Pokok Tugas / Kegiatan</label>
                            <input type="text" v-model="form.pokok_tugas" class="w-full p-2 text-sm border-slate-300 rounded-lg" placeholder="Contoh: Mengikuti Pelatihan Kurikulum Merdeka" required />
                        </div>
                        <!-- Hidden Inputs -->
                        <input type="hidden" v-model="form.tanggal_hijriah">
                        <input type="hidden" v-model="form.tanggal_masehi">
                    </div>
                    
                    <div v-else class="grid grid-cols-1 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                        <p class="text-xs text-slate-500">
                            <i>Format Surat Keterangan tidak menggunakan field Hal, Lampiran, dan Kepada Yth. Format ini akan menggunakan judul tengah otomatis.</i>
                        </p>
                        <input type="hidden" v-model="form.tanggal_hijriah">
                        <input type="hidden" v-model="form.tanggal_masehi">
                    </div>

                    <!-- Layout Settings -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Spasi Antar Baris</label>
                            <select v-model="form.line_spacing" class="w-full p-2 text-sm border-slate-300 rounded-lg focus:ring-emerald-500">
                                <option value="1.0">Spasi 1.0 (Rapat)</option>
                                <option value="1.15">Spasi 1.15 (Normal)</option>
                                <option value="1.5">Spasi 1.5 (Longgar)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Indentasi Awal Paragraf</label>
                            <select v-model="form.paragraph_indent" class="w-full p-2 text-sm border-slate-300 rounded-lg focus:ring-emerald-500">
                                <option value="0">Tidak (Rata Kiri)</option>
                                <option value="40px">Ya (Menjorok ke Dalam)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-bold text-slate-700">Isi Surat (Pesan Utama)</label>
                            <span class="text-xs text-slate-500">Kop, Tanggal Surat (Otomatis), Salam, dan Penutup sudah ditambahkan oleh sistem.</span>
                        </div>
                        <Editor
                            tinymce-script-src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"
                            v-model="form.isi_surat"
                            :init="{
                                height: 450,
                                menubar: false,
                                plugins: ['advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview', 'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen', 'insertdatetime', 'table', 'media', 'help', 'wordcount'],
                                toolbar: 'undo redo | formatselect | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | removeformat | help',
                                content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif; font-size: 14px; }'
                            }"
                        />
                        <p v-if="form.errors.isi_surat" class="text-xs text-rose-500 mt-1">{{ form.errors.isi_surat }}</p>
                    </div>
                    
                    <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-6">
                        <button type="button" @click="previewPdf" :disabled="isPreviewing" class="inline-flex items-center justify-left gap-2 px-6 py-3 rounded-xl bg-slate-100 border border-slate-300 text-slate-700 font-bold shadow-sm hover:bg-slate-200 transition-colors focus:ring-2 focus:ring-offset-2 focus:ring-slate-500">
                            <svg class="w-5 h-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span>{{ isPreviewing ? 'Memproses...' : 'Preview PDF' }}</span>
                        </button>

                        <button type="submit" :disabled="form.processing" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-emerald-600 text-white font-bold shadow-sm hover:bg-emerald-700 transition-colors focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                            </svg>
                            <span>{{ isEdit ? 'Perbarui & Generate Ulang' : 'Generate & Simpan Arsip' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
