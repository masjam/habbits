<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

const props = defineProps({
    notulen: {
        type: Object,
        required: true
    }
})

const printNotulen = () => {
    window.print()
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Preview: ${notulen.judul_rapat}`" />

        <div class="w-full mx-auto pb-12 max-w-5xl">
            <!-- Header Aksi (Sembunyi saat diprint) -->
            <div class="flex items-center justify-between mb-6 print:hidden">
                <div class="flex items-center gap-4">
                    <Link :href="route('notulen.index')" class="p-2 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-emerald-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <div>
                        <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">Preview Notulen</h1>
                    </div>
                </div>
                
                <button @click="printNotulen" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl shadow-sm transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Cetak / Simpan PDF
                </button>
            </div>

            <!-- Kertas Dokumen (Area Print) -->
            <div class="bg-white text-black p-8 md:p-12 shadow-md rounded-lg print:shadow-none print:p-0">
                <!-- Kop / Judul Dokumen -->
                <div class="text-center border-b-2 border-black pb-4 mb-6">
                    <h1 class="text-2xl font-black uppercase tracking-wider mb-1">NOTULEN HASIL RAPAT</h1>
                    <h2 class="text-lg font-bold uppercase">{{ notulen.judul_rapat }}</h2>
                </div>

                <!-- Informasi Dasar (3 Kolom) -->
                <div class="grid grid-cols-3 gap-6 text-sm mb-6 pb-6 border-b border-gray-300">
                    <div>
                        <div class="font-bold mb-1">Jenis Rapat</div>
                        <div>{{ notulen.jenis_rapat }}</div>
                    </div>
                    <div>
                        <div class="font-bold mb-1">Hari / Tanggal / Waktu</div>
                        <div>{{ new Date(notulen.tanggal_waktu).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) }}, {{ new Date(notulen.tanggal_waktu).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }} WIB</div>
                    </div>
                    <div>
                        <div class="mb-1"><span class="font-bold inline-block w-24">Tempat</span> : {{ notulen.lokasi }}</div>
                        <div><span class="font-bold inline-block w-24">Pimpinan Rapat</span> : {{ notulen.pimpinan_rapat }}</div>
                    </div>
                </div>

                <!-- Isi Pembahasan -->
                <div class="mb-6">
                    <h3 class="font-bold text-base border-b border-gray-300 pb-1 mb-3">1. Isi Pembahasan / Hasil Rapat</h3>
                    <div class="prose max-w-none text-sm text-black leading-relaxed" v-html="notulen.isi_pembahasan"></div>
                </div>

                <!-- Tindak Lanjut -->
                <div class="mb-6" v-if="notulen.tindak_lanjut">
                    <h3 class="font-bold text-base border-b border-gray-300 pb-1 mb-3">2. Tindak Lanjut (Follow up)</h3>
                    <div class="prose max-w-none text-sm text-black leading-relaxed" v-html="notulen.tindak_lanjut"></div>
                </div>

                <!-- Kehadiran -->
                <div class="mb-12">
                    <h3 class="font-bold text-base border-b border-gray-300 pb-1 mb-3">3. Daftar Hadir</h3>
                    <p class="text-sm mb-2">Total Peserta Hadir: <strong>{{ notulen.daftar_hadir ? notulen.daftar_hadir.split(', ').length : 0 }} orang</strong> (Dari {{ notulen.peserta_rapat ? notulen.peserta_rapat.split(', ').length : 0 }} Undangan)</p>
                    
                    <div v-if="notulen.daftar_hadir" class="mt-4">
                        <table class="w-full border-collapse border border-gray-400 text-xs">
                            <tbody>
                                <tr v-for="rowIndex in Math.ceil(notulen.daftar_hadir.split(', ').length / 5)" :key="rowIndex">
                                    <td v-for="colIndex in 5" :key="colIndex" class="border border-gray-400 p-2 w-1/5 align-top">
                                        <div v-if="notulen.daftar_hadir.split(', ')[(rowIndex - 1) * 5 + (colIndex - 1)]">
                                            <span class="font-bold">{{ (rowIndex - 1) * 5 + colIndex }}.</span> 
                                            {{ notulen.daftar_hadir.split(', ')[(rowIndex - 1) * 5 + (colIndex - 1)] }}
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-sm italic text-gray-500">
                        Belum ada yang tercatat hadir.
                    </div>
                </div>

                <!-- Tanda Tangan -->
                <div class="flex justify-between items-end mt-12 page-break-inside-avoid">
                    <!-- TTD Notulis (Pembuat) -->
                    <div class="text-center w-64">
                        <p class="text-sm mb-4">Notulis,</p>
                        <div class="h-20 flex items-center justify-center mb-2">
                            <img v-if="notulen.ttd_notulis" :src="notulen.ttd_notulis" class="max-h-full" alt="TTD Notulis">
                            <span v-else class="text-xs text-gray-300 border border-dashed border-gray-300 p-2">Tanda Tangan Notulis</span>
                        </div>
                        <p class="text-sm font-bold underline">{{ notulen.creator ? notulen.creator.name : 'Notulis' }}</p>
                    </div>

                    <!-- QR Code Validasi di Tengah -->
                    <div class="text-center flex flex-col items-center justify-center">
                        <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' + encodeURIComponent(route('notulen.show', notulen.id))" alt="QR Code Validasi" class="w-20 h-20 mb-2 print:border-none">
                        <p class="text-[10px] text-gray-500 font-mono">Scan untuk Validasi</p>
                    </div>

                    <!-- TTD Pimpinan -->
                    <div class="text-center w-64">
                        <p class="text-sm mb-4">Pimpinan Rapat,</p>
                        <div class="h-20 flex items-center justify-center mb-2">
                            <img v-if="notulen.ttd_pimpinan" :src="notulen.ttd_pimpinan" class="max-h-full" alt="TTD Pimpinan">
                            <span v-else class="text-xs text-gray-300 border border-dashed border-gray-300 p-2">Tanda Tangan Pimpinan</span>
                        </div>
                        <p class="text-sm font-bold underline">{{ notulen.pimpinan_rapat }}</p>
                    </div>
                </div>
            </div>
            
        </div>
    </AuthenticatedLayout>
</template>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    .print\:hidden {
        display: none !important;
    }
    .print\:shadow-none {
        box-shadow: none !important;
    }
    .print\:p-0 {
        padding: 0 !important;
    }
    /* Tampilkan hanya area kertas */
    .bg-white.text-black.p-8, .bg-white.text-black.p-8 * {
        visibility: visible;
    }
    .bg-white.text-black.p-8 {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 0;
    }

    /* Watermark Logo Sekolah */
    .bg-white.text-black.p-8::before {
        content: "";
        position: fixed;
        top: 50%;
        left: 50%;
        width: 300px;
        height: 300px;
        transform: translate(-50%, -50%);
        background-image: url('/images/logo.png'); /* Ganti dengan path logo Anda */
        background-size: contain;
        background-repeat: no-repeat;
        background-position: center;
        opacity: 0.1;
        z-index: -1;
        pointer-events: none;
    }
}
</style>
