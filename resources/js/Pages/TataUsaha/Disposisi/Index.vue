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
    kepalaSekolah: Object,
    filters: Object,
})

const isModalOpen = ref(false)
const editingId = ref(null)
const searchQuery = ref(props.filters?.search || '')

// Status Modal State
const isStatusModalOpen = ref(false)
const selectedDisposisi = ref(null)
const statusForm = useForm({
    status: 'menunggu',
    catatan_penyelesaian: '',
})

const openStatusModal = (item) => {
    selectedDisposisi.value = item
    statusForm.status = item.status || 'menunggu'
    statusForm.catatan_penyelesaian = item.catatan_penyelesaian || ''
    statusForm.clearErrors()
    isStatusModalOpen.value = true
}

const closeStatusModal = () => {
    isStatusModalOpen.value = false
    selectedDisposisi.value = null
    statusForm.reset()
    statusForm.clearErrors()
}

const saveStatus = () => {
    if (!selectedDisposisi.value) return
    statusForm.put(route('tata-usaha.disposisi.update-status', selectedDisposisi.value.id), {
        preserveScroll: true,
        onSuccess: () => closeStatusModal(),
    })
}

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

const stripHtml = (html) => {
    if (!html) return ''
    const tmp = document.createElement('div')
    tmp.innerHTML = html
    return (tmp.textContent || tmp.innerText || '').trim()
}

const formatDate = (dateString) => {
    if (!dateString) return '-'
    return dateString.split('T')[0]
}

const sendWaToKs = (surat) => {
    const ksName = props.kepalaSekolah?.name ? `Bapak/Ibu ${props.kepalaSekolah.name}` : 'Bapak/Ibu Kepala Sekolah'
    let rawPhone = props.kepalaSekolah?.phone || '6282223527261'
    let phone = String(rawPhone).replace(/[^0-9]/g, '')
    if (phone.startsWith('0')) {
        phone = '62' + phone.slice(1)
    }
    if (!phone) {
        phone = '6282223527261'
    }

    const tglSurat = surat.tanggal_surat ? formatDate(surat.tanggal_surat) : '-'
    const perihalClean = stripHtml(surat.perihal)
    const jenis = surat.jenis_surat || 'Surat Masuk'
    const linkApp = window.location.origin + '/tata-usaha/disposisi'

    const message = `*PEMBERITAHUAN DISPOSISI SURAT MASUK*\n\n` +
        `Assalamu'alaikum Wr. Wb.\n` +
        `Yth. ${ksName},\n\n` +
        `Terdapat surat masuk baru yang membutuhkan arahan / disposisi dari Kepala Sekolah:\n\n` +
        `📋 *No. Surat:* ${surat.nomor_surat}\n` +
        `🏢 *Pengirim:* ${surat.pengirim}\n` +
        `📅 *Tgl Surat:* ${tglSurat}\n` +
        `🏷️ *Jenis:* ${jenis}\n` +
        `📝 *Perihal:* ${perihalClean}\n\n` +
        `Mohon berkenan membuka aplikasi untuk memberikan arahan/disposisi penugasan:\n` +
        `🔗 ${linkApp}\n\n` +
        `Terima kasih.\n_Tata Usaha SAHABAT SDAM_`
        `pesan ini dikirim otomatis dari Aplikasi`

    const url = `https://api.whatsapp.com/send?phone=${phone}&text=${encodeURIComponent(message)}`
    window.open(url, '_blank')
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
                                    <div class="flex items-center justify-end gap-2">
                                        <button 
                                            @click="sendWaToKs(surat)" 
                                            type="button"
                                            title="Kirim Notifikasi ke Kepala Sekolah via WhatsApp"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white border border-emerald-300 text-xs font-bold transition-all shadow-sm"
                                        >
                                            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                                            </svg>
                                            <span>Kirim WA ke KS</span>
                                        </button>
                                        <button @click="openAddModal(surat.id)" class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-purple-100 text-purple-700 text-xs font-bold hover:bg-purple-200 transition-colors">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                            Beri Disposisi
                                        </button>
                                    </div>
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
                                    <div class="flex flex-col items-center">
                                        <button 
                                            @click="openStatusModal(item)"
                                            type="button"
                                            title="Klik untuk ubah status atau lihat catatan penyelesaian"
                                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider transition-all hover:scale-105 cursor-pointer shadow-2xs border"
                                            :class="{
                                                'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100': item.status === 'menunggu',
                                                'bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100': item.status === 'dibaca',
                                                'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100': item.status === 'dikerjakan',
                                                'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100': item.status === 'selesai'
                                            }">
                                            <span class="w-2 h-2 rounded-full"
                                                :class="{
                                                    'bg-slate-400': item.status === 'menunggu',
                                                    'bg-blue-500': item.status === 'dibaca',
                                                    'bg-amber-500': item.status === 'dikerjakan',
                                                    'bg-emerald-500': item.status === 'selesai'
                                                }"></span>
                                            <span>
                                                {{ item.status === 'menunggu' ? 'Menunggu' : (item.status === 'dibaca' ? 'Dibaca YBS' : (item.status === 'dikerjakan' ? 'Dikerjakan' : 'Selesai')) }}
                                            </span>
                                            <svg class="w-3 h-3 opacity-60 ml-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>

                                        <button 
                                            @click="openStatusModal(item)"
                                            type="button"
                                            class="mt-1 text-[10px] text-purple-600 hover:text-purple-800 font-semibold transition-colors cursor-pointer"
                                        >
                                            Ubah Status
                                        </button>

                                        <div 
                                            v-if="item.catatan_penyelesaian" 
                                            class="mt-1.5 text-[11px] text-slate-600 bg-slate-50 border border-slate-200 rounded-lg p-1.5 max-w-[220px] text-left leading-tight"
                                            title="Catatan Penyelesaian"
                                        >
                                            <span class="font-bold text-slate-700 block text-[10px]">Catatan:</span>
                                            <p class="truncate italic">{{ item.catatan_penyelesaian }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button 
                                            @click="openStatusModal(item)" 
                                            type="button"
                                            title="Ubah Status & Catatan" 
                                            class="text-purple-600 hover:text-purple-800 transition-colors p-1"
                                        >
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>
                                        <a :href="route('tata-usaha.disposisi.print', item.id)" target="_blank" title="Cetak Disposisi" class="text-blue-500 hover:text-blue-700 transition-colors p-1">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                        </a>
                                        <button v-if="hasRole('kepala_sekolah') || hasRole('superadmin')" @click="editDisposisi(item)" title="Edit" class="text-amber-500 hover:text-amber-700 transition-colors p-1">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                        </button>
                                        <button v-if="hasRole('kepala_sekolah') || hasRole('superadmin')" @click="deleteDisposisi(item.id)" title="Hapus" class="text-rose-500 hover:text-rose-700 transition-colors p-1">
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

        <!-- Update Status Modal -->
        <div v-if="isStatusModalOpen" class="relative z-50">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeStatusModal"></div>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl w-full max-w-lg border border-slate-100">
                        <form @submit.prevent="saveStatus">
                            <div class="bg-white px-5 pt-5 pb-4 sm:p-6">
                                <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                                    <div>
                                        <h3 class="text-base font-bold text-slate-900">Perbarui Status Disposisi</h3>
                                        <p class="text-xs text-slate-500 mt-0.5">
                                            Surat: <span class="font-semibold text-slate-700">{{ selectedDisposisi?.surat_masuk?.nomor_surat }}</span>
                                        </p>
                                    </div>
                                    <button type="button" @click="closeStatusModal" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>

                                <div class="space-y-4">
                                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 text-xs text-slate-600 space-y-1">
                                        <div><span class="font-semibold text-slate-700">Tujuan (YBS):</span> {{ selectedDisposisi?.penerima?.name }}</div>
                                        <div><span class="font-semibold text-slate-700">Instruksi:</span> "{{ selectedDisposisi?.instruksi }}"</div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-2">Pilih Status</label>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            <label 
                                                class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition-all"
                                                :class="statusForm.status === 'menunggu' ? 'border-slate-500 bg-slate-100/70 text-slate-900 ring-2 ring-slate-400/20' : 'border-slate-200 hover:bg-slate-50/60 text-slate-600'"
                                            >
                                                <input type="radio" value="menunggu" v-model="statusForm.status" class="mt-0.5 text-slate-600 focus:ring-slate-500" />
                                                <div class="text-xs">
                                                    <span class="font-bold text-slate-800 block">Menunggu</span>
                                                    <span class="text-[11px] text-slate-500">Baru dikirim, penerima belum membaca</span>
                                                </div>
                                            </label>

                                            <label 
                                                class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition-all"
                                                :class="statusForm.status === 'dibaca' ? 'border-blue-500 bg-blue-50/70 text-blue-900 ring-2 ring-blue-500/20' : 'border-slate-200 hover:bg-slate-50/60 text-slate-600'"
                                            >
                                                <input type="radio" value="dibaca" v-model="statusForm.status" class="mt-0.5 text-blue-600 focus:ring-blue-500" />
                                                <div class="text-xs">
                                                    <span class="font-bold text-blue-800 block">Dibaca YBS</span>
                                                    <span class="text-[11px] text-slate-500">Penerima telah membuka & membaca</span>
                                                </div>
                                            </label>

                                            <label 
                                                class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition-all"
                                                :class="statusForm.status === 'dikerjakan' ? 'border-amber-500 bg-amber-50/70 text-amber-900 ring-2 ring-amber-500/20' : 'border-slate-200 hover:bg-slate-50/60 text-slate-600'"
                                            >
                                                <input type="radio" value="dikerjakan" v-model="statusForm.status" class="mt-0.5 text-amber-600 focus:ring-amber-500" />
                                                <div class="text-xs">
                                                    <span class="font-bold text-amber-800 block">Dikerjakan</span>
                                                    <span class="text-[11px] text-slate-500">Penerima sedang menindaklanjuti</span>
                                                </div>
                                            </label>

                                            <label 
                                                class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition-all"
                                                :class="statusForm.status === 'selesai' ? 'border-emerald-500 bg-emerald-50/70 text-emerald-900 ring-2 ring-emerald-500/20' : 'border-slate-200 hover:bg-slate-50/60 text-slate-600'"
                                            >
                                                <input type="radio" value="selesai" v-model="statusForm.status" class="mt-0.5 text-emerald-600 focus:ring-emerald-500" />
                                                <div class="text-xs">
                                                    <span class="font-bold text-emerald-800 block">Selesai</span>
                                                    <span class="text-[11px] text-slate-500">Instruksi telah tuntas dilaksanakan</span>
                                                </div>
                                            </label>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1">
                                            Catatan Penyelesaian / Hasil Tindak Lanjut (Opsional)
                                        </label>
                                        <textarea 
                                            v-model="statusForm.catatan_penyelesaian" 
                                            rows="3" 
                                            class="w-full p-2.5 text-sm border-slate-300 rounded-xl focus:ring-purple-500 focus:border-purple-500" 
                                            placeholder="Contoh: Sudah ditindaklanjuti, berkas/surat balasan telah diserahkan..."
                                        ></textarea>
                                        <p v-if="statusForm.errors.catatan_penyelesaian" class="text-xs text-rose-500 mt-1">
                                            {{ statusForm.errors.catatan_penyelesaian }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-slate-50 px-5 py-3.5 flex flex-row-reverse gap-2 rounded-b-2xl border-t border-slate-100">
                                <button 
                                    type="submit" 
                                    :disabled="statusForm.processing" 
                                    class="inline-flex justify-center rounded-xl bg-purple-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-purple-700 disabled:opacity-50 transition-all cursor-pointer"
                                >
                                    {{ statusForm.processing ? 'Menyimpan...' : 'Simpan Status' }}
                                </button>
                                <button 
                                    type="button" 
                                    @click="closeStatusModal" 
                                    class="inline-flex justify-center rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 transition-all cursor-pointer"
                                >
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
