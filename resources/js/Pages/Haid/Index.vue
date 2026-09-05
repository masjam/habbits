<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    isSedangHaid: Boolean,
    activeLog: Object,
    historyLogs: Array,
})

const form = useForm({})

const toggleHaid = () => {
    form.post(route('haid.toggle'), {
        preserveScroll: true,
    })
}

const formatTanggal = (dateStr) => {
    if (!dateStr) return '-'
    const d = new Date(dateStr)
    return d.toLocaleDateString('id-ID', {
        day: 'numeric', month: 'long', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    })
}

// Menghitung durasi (dalam hari)
const hitungDurasi = (mulai, selesai) => {
    const start = new Date(mulai)
    const end = selesai ? new Date(selesai) : new Date()
    const diffTime = Math.abs(end - start)
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
    return diffDays + ' hari'
}

// Edit Modal State
const showEditModal = ref(false)
const editingLog = ref(null)

const editForm = useForm({
    waktu_mulai: '',
    waktu_selesai: '',
})

const formatForInput = (dateStr) => {
    if (!dateStr) return ''
    const d = new Date(dateStr)
    return new Date(d.getTime() - (d.getTimezoneOffset() * 60000)).toISOString().slice(0, 16)
}

const openEditModal = (log) => {
    editingLog.value = log
    editForm.waktu_mulai = formatForInput(log.waktu_mulai)
    editForm.waktu_selesai = formatForInput(log.waktu_selesai)
    showEditModal.value = true
}

const closeEditModal = () => {
    showEditModal.value = false
    editingLog.value = null
    editForm.reset()
    editForm.clearErrors()
}

const updateLog = () => {
    if (!editingLog.value) return
    editForm.put(route('haid.update', editingLog.value.id), {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
    })
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Catatan Haid" />

        <div class="space-y-6">
            <!-- Header -->
            <div>
                <h2 class="text-xl font-bold text-slate-800 tracking-tight">Catatan Haid</h2>
                <p class="text-sm text-slate-500 mt-1">
                    Atur status masa haid Anda agar sistem dapat menyesuaikan target ibadah secara otomatis.
                </p>
            </div>

            <!-- Status Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative">
                <!-- Decorative background -->
                <div class="absolute inset-0 bg-gradient-to-br from-rose-50/50 to-white/20 pointer-events-none"></div>
                
                <div class="p-6 md:p-8 flex flex-col items-center justify-center relative z-10 text-center space-y-6">
                    <div class="space-y-2">
                        <div class="inline-flex items-center justify-center p-3 rounded-full mb-2" 
                            :class="isSedangHaid ? 'bg-rose-100 text-rose-600' : 'bg-slate-100 text-slate-400'">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-slate-800">
                            {{ isSedangHaid ? 'Mode Haid Sedang Aktif' : 'Anda Sedang Tidak Haid' }}
                        </h3>
                        <p class="text-sm text-slate-500 max-w-sm mx-auto">
                            {{ isSedangHaid 
                                ? `Masa haid Anda telah dicatat sejak ${formatTanggal(activeLog?.waktu_mulai)}. Form ibadah wajib kini disembunyikan.`
                                : 'Klik tombol di bawah ini saat Anda mulai memasuki masa haid agar skor ibadah Anda terlindungi.' 
                            }}
                        </p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                        <button 
                            @click="toggleHaid"
                            :disabled="form.processing"
                            class="px-8 py-4 rounded-xl font-bold text-lg shadow-sm transition-all focus:ring-2 focus:ring-offset-2 flex items-center justify-center gap-2 w-full sm:w-auto"
                            :class="[
                                isSedangHaid 
                                    ? 'bg-slate-800 text-white hover:bg-slate-900 focus:ring-slate-800 shadow-slate-200' 
                                    : 'bg-rose-500 text-white hover:bg-rose-600 focus:ring-rose-500 shadow-rose-200 hover:shadow-md'
                            ]"
                        >
                            <svg v-if="form.processing" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <template v-else>
                                <svg v-if="isSedangHaid" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <svg v-else class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>{{ isSedangHaid ? 'Tandai Selesai Haid' : 'Mulai Masa Haid' }}</span>
                            </template>
                        </button>
                        
                        <button v-if="isSedangHaid && activeLog"
                            @click="openEditModal(activeLog)"
                            class="px-6 py-4 rounded-xl font-bold text-lg shadow-sm border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 transition-all flex items-center justify-center gap-2 w-full sm:w-auto"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit
                        </button>
                    </div>
                </div>
            </div>

            <!-- History Table -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/50">
                    <h3 class="font-bold text-slate-800">Riwayat Siklus</h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500 font-semibold border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-3">Tanggal Mulai</th>
                                <th class="px-6 py-3">Tanggal Selesai</th>
                                <th class="px-6 py-3">Durasi</th>
                                <th class="px-6 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-if="historyLogs.length === 0" class="bg-white">
                                <td colspan="4" class="px-6 py-8 text-center text-slate-400 italic">
                                    Belum ada riwayat siklus yang dicatat.
                                </td>
                            </tr>
                            <tr v-for="log in historyLogs" :key="log.id" class="bg-white hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 font-medium text-slate-700">{{ formatTanggal(log.waktu_mulai) }}</td>
                                <td class="px-6 py-4">{{ formatTanggal(log.waktu_selesai) }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">
                                        {{ hitungDurasi(log.waktu_mulai, log.waktu_selesai) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button @click="openEditModal(log)" class="text-emerald-600 hover:text-emerald-900 bg-emerald-50 hover:bg-emerald-100 px-3 py-1 rounded-md text-xs font-bold transition-colors">
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>

        <!-- Edit Modal -->
        <Teleport to="body">
            <div v-if="showEditModal" class="fixed inset-0 z-[9999] flex items-center justify-center p-4" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <!-- Background overlay -->
                <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" @click="closeEditModal" aria-hidden="true"></div>

                <!-- Modal panel -->
                <div class="relative z-10 w-full max-w-md bg-white rounded-2xl text-left overflow-visible shadow-xl transform transition-all p-6 max-h-[90vh] overflow-y-auto">
                    <h3 class="text-lg font-bold text-slate-800 mb-4" id="modal-title">Edit Catatan Haid</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Waktu Mulai</label>
                            <input type="datetime-local" v-model="editForm.waktu_mulai" 
                                class="w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <div v-if="editForm.errors.waktu_mulai" class="text-sm text-red-600 mt-1">
                                {{ editForm.errors.waktu_mulai }}
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Waktu Selesai</label>
                            <input type="datetime-local" v-model="editForm.waktu_selesai"
                                class="w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <p class="text-xs text-slate-500 mt-1">Kosongkan jika masih dalam masa haid.</p>
                            <div v-if="editForm.errors.waktu_selesai" class="text-sm text-red-600 mt-1">
                                {{ editForm.errors.waktu_selesai }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col sm:flex-row justify-end gap-3">
                        <button @click="closeEditModal" type="button" class="w-full sm:w-auto px-4 py-2 bg-white border border-slate-300 rounded-md shadow-sm text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Batal
                        </button>
                        <button @click="updateLog" :disabled="editForm.processing" type="button" class="w-full sm:w-auto px-4 py-2 bg-emerald-600 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50 text-center">
                            Simpan Perubahan
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
