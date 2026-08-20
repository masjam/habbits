<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

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
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-if="historyLogs.length === 0" class="bg-white">
                                <td colspan="3" class="px-6 py-8 text-center text-slate-400 italic">
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
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </AuthenticatedLayout>
</template>
