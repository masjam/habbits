<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router } from '@inertiajs/vue3'

const props = defineProps({
    catatan: Array,
    filters: Object,
    months: Array,
    years: Array
})

const updateFilter = () => {
    router.get(route('kajian.index'), props.filters, {
        preserveState: true,
        preserveScroll: true
    })
}

const formatTanggal = (dateStr) => {
    const d = new Date(dateStr)
    return d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Jurnal Kajian & Bacaan" />

        <div class="space-y-6">
            <!-- Header -->
            <div>
                <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Jurnal Kajian & Bacaan</h2>
                <p class="text-sm text-slate-500 mt-1">Rekam jejak hadist dan buku agama yang telah Anda pelajari.</p>
            </div>

            <!-- Filters -->
            <div class="flex flex-col sm:flex-row items-center gap-4 bg-white p-3 rounded-2xl border border-slate-200 shadow-sm">
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <select v-model="filters.month" @change="updateFilter" class="bg-slate-50 border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block w-full p-2.5 transition-colors">
                        <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                    <select v-model="filters.year" @change="updateFilter" class="bg-slate-50 border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block w-full p-2.5 transition-colors">
                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>
                <div class="hidden sm:block flex-1"></div>
                <div class="text-sm font-semibold text-slate-500 w-full sm:w-auto text-right">
                    Total: <span class="text-emerald-600 font-black text-lg">{{ catatan.length }}</span> catatan
                </div>
            </div>

            <!-- List Catatan -->
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6 sm:p-8">
                <div v-if="catatan.length > 0" class="space-y-6">
                    <div v-for="log in catatan" :key="log.id" class="p-5 sm:p-6 rounded-2xl bg-slate-50 hover:bg-slate-100/50 border border-slate-200 transition-colors">
                        
                        <div class="flex flex-wrap justify-between items-start gap-4 mb-4">
                            <div>
                                <h3 class="font-black text-slate-800 text-lg sm:text-xl tracking-tight">
                                    {{ log.details?.tema || 'Tema Tidak Disebutkan' }}
                                </h3>
                                <p class="text-xs font-semibold uppercase tracking-widest text-emerald-600 mt-1">
                                    {{ log.habit?.nama_habit }}
                                </p>
                            </div>
                            <span class="text-xs bg-white border border-slate-200 px-3 py-1.5 rounded-lg text-slate-500 font-bold shadow-sm whitespace-nowrap">
                                {{ formatTanggal(log.tanggal) }}
                            </span>
                        </div>
                        
                        <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-500 mb-4 border-b border-slate-200/60 pb-4">
                            <p class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                                <span class="font-bold text-slate-600">Riwayat/Pengarang:</span> 
                                <span>{{ log.details?.riwayat || log.details?.pengarang || '-' }}</span>
                            </p>
                            <p class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                                </svg>
                                <span class="font-bold text-slate-600">Sumber/Buku:</span> 
                                <span>{{ log.details?.sumber || log.details?.judul_buku || '-' }}</span>
                            </p>
                        </div>
                        
                        <div class="prose prose-sm prose-slate max-w-none text-slate-600 leading-relaxed break-words">
                            <p class="whitespace-pre-wrap font-medium">{{ log.details?.isi || log.details?.catatan || 'Tidak ada catatan yang dilampirkan.' }}</p>
                        </div>
                    </div>
                </div>
                
                <div v-else class="flex flex-col items-center justify-center py-16 text-center">
                    <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-10 h-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-700 mb-1">Belum Ada Catatan</h3>
                    <p class="text-sm text-slate-500 max-w-sm">Anda belum memiliki catatan belajar hadist atau bacaan buku pada bulan ini.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
