<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'

const props = defineProps({
    evaluations: Array,
    selectedYear: Number
})

const year = ref(props.selectedYear)
const monthNames = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"]

watch(year, () => {
    router.get(route('performance.index'), { year: year.value }, { preserveState: true, preserveScroll: true })
})

// Hitung rata-rata tahunan
const annualAverage = computed(() => {
    if (props.evaluations.length === 0) return 0;
    const sum = props.evaluations.reduce((acc, curr) => acc + Number(curr.average_score), 0);
    return (sum / props.evaluations.length).toFixed(2);
})

const getPredicate = (score) => {
    if (score >= 90) return 'A (Sangat Baik)'
    if (score >= 80) return 'B (Baik)'
    if (score >= 70) return 'C (Cukup)'
    return 'D (Kurang)'
}

const getPredicateColor = (score) => {
    if (score >= 90) return 'text-emerald-600 dark:text-emerald-400'
    if (score >= 80) return 'text-blue-600 dark:text-blue-400'
    if (score >= 70) return 'text-amber-600 dark:text-amber-400'
    return 'text-red-600 dark:text-red-400'
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Rapor Kinerja (KPI)" />

        <div class="space-y-6 w-full pb-8">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 dark:text-slate-100 tracking-tight">Rapor Kinerja Saya</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Pantau hasil evaluasi kinerja Anda (Pedagogik, Profesional, Kepribadian, Sosial) setiap bulan.
                    </p>
                </div>
                
                <div class="flex items-center gap-2">
                    <label class="text-sm font-bold text-slate-700 dark:text-slate-300">Tahun:</label>
                    <select v-model="year" class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2 text-sm font-bold text-slate-700 dark:text-slate-300 focus:ring-emerald-500">
                        <option v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>
            </div>

            <!-- Summary Card -->
            <div class="bg-gradient-to-br from-emerald-600 to-teal-800 rounded-3xl p-6 md:p-8 text-white shadow-lg relative overflow-hidden">
                <div class="absolute inset-0 opacity-10 bg-[url('/img/islamic-pattern.png')] bg-repeat bg-[length:100px]"></div>
                <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6">
                    <div>
                        <h2 class="text-emerald-100 font-bold uppercase tracking-widest text-xs mb-1">Rata-Rata Kinerja Tahun {{ year }}</h2>
                        <div class="text-5xl font-black">{{ annualAverage }}</div>
                        <div class="mt-2 inline-block px-3 py-1 bg-white/20 rounded-full text-sm font-bold backdrop-blur-sm">
                            Predikat: {{ getPredicate(annualAverage) }}
                        </div>
                    </div>
                    <div class="text-right hidden md:block">
                        <p class="text-emerald-100 text-sm max-w-xs">
                            Skor ini merupakan gabungan rata-rata dari kompetensi mengajar, kedisiplinan, administrasi, dan etika sosial Anda sepanjang tahun.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Timeline / Monthly List -->
            <div v-if="evaluations.length > 0" class="space-y-6">
                <div v-for="evalu in evaluations" :key="evalu.id" class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 shadow-sm p-6 overflow-hidden relative group transition-all hover:shadow-md">
                    
                    <div class="flex flex-col md:flex-row md:items-start gap-6">
                        <!-- Header Bulan -->
                        <div class="md:w-1/4 shrink-0 border-b md:border-b-0 md:border-r border-slate-100 dark:border-slate-700 pb-4 md:pb-0 md:pr-6 flex md:flex-col justify-between md:justify-start items-center md:items-start">
                            <div>
                                <h3 class="text-xl font-black text-slate-900 dark:text-slate-100">{{ monthNames[evalu.evaluation_month - 1] }}</h3>
                                <p class="text-sm text-slate-500">{{ evalu.evaluation_year }}</p>
                            </div>
                            <div class="text-right md:text-left md:mt-6">
                                <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Skor Bulan Ini</div>
                                <div class="text-3xl font-black" :class="getPredicateColor(evalu.average_score)">{{ evalu.average_score }}</div>
                                <div class="text-xs font-bold mt-1" :class="getPredicateColor(evalu.average_score)">{{ getPredicate(evalu.average_score) }}</div>
                            </div>
                        </div>

                        <!-- Rincian -->
                        <div class="md:w-3/4 flex-1">
                            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-4">Rincian Kompetensi</h4>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                                <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-2xl">
                                    <div class="text-xs text-slate-500 mb-1">Pedagogik</div>
                                    <div class="text-xl font-bold text-slate-800 dark:text-slate-200">{{ evalu.score_pedagogic }}</div>
                                </div>
                                <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-2xl">
                                    <div class="text-xs text-slate-500 mb-1">Profesional</div>
                                    <div class="text-xl font-bold text-slate-800 dark:text-slate-200">{{ evalu.score_professional }}</div>
                                </div>
                                <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-2xl">
                                    <div class="text-xs text-slate-500 mb-1">Kepribadian</div>
                                    <div class="text-xl font-bold text-slate-800 dark:text-slate-200">{{ evalu.score_personality }}</div>
                                </div>
                                <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-2xl">
                                    <div class="text-xs text-slate-500 mb-1">Sosial</div>
                                    <div class="text-xl font-bold text-slate-800 dark:text-slate-200">{{ evalu.score_social }}</div>
                                </div>
                            </div>

                            <div v-if="evalu.notes" class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-900/50 rounded-2xl">
                                <div class="flex items-start gap-3">
                                    <svg class="w-5 h-5 text-blue-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <div>
                                        <div class="text-xs font-bold text-blue-800 dark:text-blue-400 mb-1">Catatan Pimpinan:</div>
                                        <p class="text-sm text-blue-900 dark:text-blue-300">{{ evalu.notes }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="bg-white dark:bg-slate-800 rounded-3xl border border-dashed border-slate-300 dark:border-slate-700 p-12 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-50 dark:bg-slate-900/50 text-slate-400 mb-4 shadow-inner">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-200">Belum Ada Penilaian</h3>
                <p class="text-slate-500 dark:text-slate-400 mt-2 max-w-sm mx-auto">Kepala Sekolah / HRD belum memberikan penilaian kinerja (KPI) untuk Anda pada tahun {{ year }}.</p>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
