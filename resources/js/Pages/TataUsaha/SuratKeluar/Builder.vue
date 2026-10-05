<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Editor from '@tinymce/tinymce-vue'
import { ref } from 'vue'

const props = defineProps({
    next_nomor: String,
    tanggal_masehi: String,
    tanggal_hijriah: String,
})

const tinymceApiKey = import.meta.env.VITE_TINYMCE_API_KEY || 'no-api-key'

const form = useForm({
    nomor_surat: props.next_nomor,
    lampiran: '-',
    perihal: 'Surat Edaran',
    tanggal_masehi: props.tanggal_masehi,
    tanggal_hijriah: props.tanggal_hijriah,
    kepada: 'Bapak/Ibu Orangtua Wali Murid Kelas I-VI',
    di: 'tempat',
    isi_surat: '<p>Dalam rangka menindaklanjuti...</p>'
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
    form.post(route('tata-usaha.surat-keluar.generate'), {
        preserveScroll: true
    })
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
                    
                    <!-- Informasi Meta Surat yang disusun secara kompak (1 Baris) -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
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
                        <input type="hidden" v-model="form.nomor_surat">
                        <input type="hidden" v-model="form.tanggal_hijriah">
                        <input type="hidden" v-model="form.tanggal_masehi">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-bold text-slate-700">Isi Surat (Pesan Utama)</label>
                            <span class="text-xs text-slate-500">Kop, Tanggal Surat (Otomatis), Salam, dan Penutup sudah ditambahkan oleh sistem.</span>
                        </div>
                        <Editor
                            :api-key="tinymceApiKey"
                            v-model="form.isi_surat"
                            :init="{
                                height: 450,
                                menubar: false,
                                plugins: ['advlist', 'autolink', 'lists', 'link', 'charmap', 'preview', 'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen', 'insertdatetime', 'table', 'help', 'wordcount'],
                                toolbar: 'undo redo | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
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
                            <span>Generate & Simpan Arsip</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
