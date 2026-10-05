<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    settings: Object
})

const form = useForm({
    kop_baris_1: props.settings.kop_baris_1 || '',
    kop_baris_2: props.settings.kop_baris_2 || '',
    kop_baris_3: props.settings.kop_baris_3 || '',
    kop_alamat: props.settings.kop_alamat || '',
    kop_kontak: props.settings.kop_kontak || '',
    kepsek_nama: props.settings.kepsek_nama || '',
    kepsek_nbm: props.settings.kepsek_nbm || '',
    logo_kiri: null,
    logo_kanan: null,
    remove_logo_kiri: false,
    remove_logo_kanan: false,
})

const logoKiriPreview = ref(props.settings.kop_logo_kiri ? '/storage/' + props.settings.kop_logo_kiri : null)
const logoKananPreview = ref(props.settings.kop_logo_kanan ? '/storage/' + props.settings.kop_logo_kanan : null)

const handleLogoKiriChange = (e) => {
    const file = e.target.files[0]
    if (file) {
        form.logo_kiri = file
        logoKiriPreview.value = URL.createObjectURL(file)
        form.remove_logo_kiri = false
    }
}

const handleLogoKananChange = (e) => {
    const file = e.target.files[0]
    if (file) {
        form.logo_kanan = file
        logoKananPreview.value = URL.createObjectURL(file)
        form.remove_logo_kanan = false
    }
}

const removeLogoKiri = () => {
    form.logo_kiri = null
    logoKiriPreview.value = null
    form.remove_logo_kiri = true
}

const removeLogoKanan = () => {
    form.logo_kanan = null
    logoKananPreview.value = null
    form.remove_logo_kanan = true
}

const submit = () => {
    form.post(route('tata-usaha.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // alert success handled by flash messages globally if exists
        }
    })
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Pengaturan Surat" />

        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-800 tracking-tight">Pengaturan Kop Surat</h2>
                    <p class="text-sm text-slate-500 mt-1">
                        Atur logo dan teks kop surat yang akan dicetak pada generator surat.
                    </p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden p-6">
                <form @submit.prevent="submit" class="space-y-6">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Logos -->
                        <div class="space-y-4">
                            <h3 class="font-bold text-slate-700 border-b pb-2">Logo Kop Surat</h3>
                            
                            <!-- Logo Kiri -->
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2">Logo Kiri</label>
                                <div class="flex items-center gap-4">
                                    <div class="w-24 h-24 bg-slate-100 rounded-lg border-2 border-dashed border-slate-300 flex items-center justify-center overflow-hidden relative">
                                        <img v-if="logoKiriPreview" :src="logoKiriPreview" class="w-full h-full object-contain" />
                                        <svg v-else class="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    </div>
                                    <div class="space-y-2">
                                        <input type="file" id="logo_kiri" @change="handleLogoKiriChange" accept="image/png, image/jpeg" class="hidden" />
                                        <label for="logo_kiri" class="cursor-pointer inline-block px-3 py-1.5 bg-white border border-slate-300 rounded text-xs font-medium text-slate-700 hover:bg-slate-50">Upload Baru</label>
                                        <button v-if="logoKiriPreview" type="button" @click="removeLogoKiri" class="block px-3 py-1.5 bg-rose-50 text-rose-600 rounded text-xs font-medium hover:bg-rose-100">Hapus Logo</button>
                                    </div>
                                </div>
                                <p v-if="form.errors.logo_kiri" class="text-xs text-rose-500 mt-1">{{ form.errors.logo_kiri }}</p>
                            </div>

                            <!-- Logo Kanan -->
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2">Logo Kanan</label>
                                <div class="flex items-center gap-4">
                                    <div class="w-24 h-24 bg-slate-100 rounded-lg border-2 border-dashed border-slate-300 flex items-center justify-center overflow-hidden relative">
                                        <img v-if="logoKananPreview" :src="logoKananPreview" class="w-full h-full object-contain" />
                                        <svg v-else class="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    </div>
                                    <div class="space-y-2">
                                        <input type="file" id="logo_kanan" @change="handleLogoKananChange" accept="image/png, image/jpeg" class="hidden" />
                                        <label for="logo_kanan" class="cursor-pointer inline-block px-3 py-1.5 bg-white border border-slate-300 rounded text-xs font-medium text-slate-700 hover:bg-slate-50">Upload Baru</label>
                                        <button v-if="logoKananPreview" type="button" @click="removeLogoKanan" class="block px-3 py-1.5 bg-rose-50 text-rose-600 rounded text-xs font-medium hover:bg-rose-100">Hapus Logo</button>
                                    </div>
                                </div>
                                <p v-if="form.errors.logo_kanan" class="text-xs text-rose-500 mt-1">{{ form.errors.logo_kanan }}</p>
                            </div>

                        </div>

                        <!-- Teks -->
                        <div class="space-y-4">
                            <h3 class="font-bold text-slate-700 border-b pb-2">Teks Kop Surat</h3>
                            
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1">Baris 1 (Yayasan/Majelis)</label>
                                <input type="text" v-model="form.kop_baris_1" class="w-full p-2 text-sm border-slate-300 rounded-lg" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1">Baris 2 (Nama Sekolah)</label>
                                <input type="text" v-model="form.kop_baris_2" class="w-full p-2 text-sm border-slate-300 rounded-lg font-bold" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1">Baris 3 (Deskripsi/Sub-nama)</label>
                                <input type="text" v-model="form.kop_baris_3" class="w-full p-2 text-sm border-slate-300 rounded-lg" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1">Alamat (Baris 4)</label>
                                <input type="text" v-model="form.kop_alamat" class="w-full p-2 text-sm border-slate-300 rounded-lg" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1">Kontak (Baris 5)</label>
                                <input type="text" v-model="form.kop_kontak" class="w-full p-2 text-sm border-slate-300 rounded-lg text-blue-600" />
                            </div>

                            <h3 class="font-bold text-slate-700 border-b pb-2 mt-6">Tanda Tangan Surat</h3>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1">Nama Kepala Sekolah</label>
                                <input type="text" v-model="form.kepsek_nama" class="w-full p-2 text-sm border-slate-300 rounded-lg font-bold" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1">NBM Kepala Sekolah</label>
                                <input type="text" v-model="form.kepsek_nbm" class="w-full p-2 text-sm border-slate-300 rounded-lg" />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end border-t border-slate-200 pt-6">
                        <button type="submit" :disabled="form.processing" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-emerald-600 text-white font-bold shadow-sm hover:bg-emerald-700 transition-colors focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Simpan Pengaturan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
