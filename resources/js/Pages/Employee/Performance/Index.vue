<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PageContainer from '@/Components/UI/PageContainer.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import Badge from '@/Components/UI/Badge.vue'
import { usePerformanceScore } from '@/Composables/usePerformanceScore'
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { Line } from 'vue-chartjs'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler)

const props = defineProps({
    evaluations: { type: Array, default: () => [] },
    rubric: { type: Object, required: true },
    userCategory: { type: String, default: 'guru' },
})

const { predicateVariant } = usePerformanceScore(props.rubric)

const selectedId = ref(props.evaluations[0]?.id ?? null)
const selectedEval = computed(() =>
    props.evaluations.find(e => e.id === selectedId.value) ?? props.evaluations[0] ?? null
)

const openAccordions = ref({})
const toggleAccordion = (key) => {
    openAccordions.value[key] = !openAccordions.value[key]
}

const currentAspects = computed(() => {
    const cat = selectedEval.value?.category || props.userCategory
    return props.rubric?.categories?.[cat]?.aspects || {}
})

const scaleLabels = computed(() => props.rubric?.scale?.labels || {})

const chartData = computed(() => {
    const chronological = [...props.evaluations].reverse()
    return {
        labels: chronological.map(e => e.period_display),
        datasets: [
            {
                label: 'Nilai Akhir KPI',
                data: chronological.map(e => Number(e.final_score)),
                borderColor: '#059669',
                backgroundColor: 'rgba(16, 185, 129, 0.12)',
                tension: 0.3,
                fill: true,
                pointRadius: 4,
            },
        ],
    }
})

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        y: { min: 0, max: 100 },
    },
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Rapor Kinerja (KPI)" />

        <PageContainer>
            <PageHeader
                title="Rapor Kinerja Saya"
                subtitle="Pantau hasil evaluasi kinerja Anda yang telah dipublikasikan."
            >
                <template v-if="evaluations.length > 0" #actions>
                    <select
                        v-model="selectedId"
                        class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2 text-sm font-bold text-slate-700 dark:text-slate-200"
                    >
                        <option v-for="ev in evaluations" :key="ev.id" :value="ev.id">
                            {{ ev.period_display }}
                        </option>
                    </select>
                </template>
            </PageHeader>

            <template v-if="selectedEval">
                <!-- Ringkasan Nilai Periode Terpilih -->
                <div class="bg-gradient-to-br from-emerald-600 to-teal-800 rounded-3xl p-6 md:p-8 text-white shadow-lg mb-6">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-widest text-emerald-100">
                                {{ selectedEval.period_display }} • {{ selectedEval.category === 'guru' ? 'Kategori Guru' : 'Kategori Karyawan' }}
                            </div>
                            <div class="text-5xl font-black mt-1">{{ Number(selectedEval.final_score).toFixed(2) }}</div>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <span class="px-3 py-1 rounded-full bg-white/20 text-xs font-bold">
                                    Predikat: {{ selectedEval.predicate }}
                                </span>
                                <span class="text-xs text-emerald-100">
                                    Dinilai oleh {{ selectedEval.evaluator_name }}
                                    <template v-if="selectedEval.approver_name"> · Disetujui oleh {{ selectedEval.approver_name }}</template>
                                    <template v-if="selectedEval.approved_at || selectedEval.published_at"> pada {{ selectedEval.approved_at || selectedEval.published_at }}</template>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4 Kartu Skor Aspek -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div
                        v-for="(meta, key) in currentAspects"
                        :key="key"
                        class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 p-4"
                    >
                        <div class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ meta.label }}</div>
                        <div class="text-2xl font-black text-slate-900 dark:text-slate-100 mt-1">
                            {{ Number(selectedEval.aspect_scores?.[key] ?? 0).toFixed(2) }}
                        </div>
                    </div>
                </div>

                <!-- Catatan Pimpinan (escaped, tanpa v-html) -->
                <div v-if="selectedEval.notes" class="mb-6 p-5 rounded-2xl bg-sky-50 dark:bg-sky-900/20 border border-sky-200 dark:border-sky-800">
                    <div class="text-xs font-extrabold uppercase text-sky-800 dark:text-sky-300 mb-1">Catatan Pimpinan</div>
                    <p class="text-sm text-sky-950 dark:text-sky-100 whitespace-pre-line">{{ selectedEval.notes }}</p>
                </div>

                <!-- Rincian Indikator (Accordion per Aspek) -->
                <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 p-6 mb-6 space-y-3">
                    <h3 class="text-base font-black text-slate-900 dark:text-slate-100">Rincian Skor Indikator</h3>
                    <div v-for="(meta, aspectKey) in currentAspects" :key="aspectKey" class="border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden">
                        <button
                            type="button"
                            @click="toggleAccordion(aspectKey)"
                            class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900/50 flex items-center justify-between text-left"
                        >
                            <span class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ meta.label }}</span>
                            <span class="text-sm font-black text-emerald-600 dark:text-emerald-400">
                                {{ Number(selectedEval.aspect_scores?.[aspectKey] ?? 0).toFixed(2) }}
                            </span>
                        </button>
                        <div v-if="openAccordions[aspectKey]" class="p-4 space-y-3 divide-y divide-slate-100 dark:divide-slate-700">
                            <div v-for="ind in meta.indicators" :key="ind.code" class="pt-2 first:pt-0">
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span class="font-bold text-slate-700 dark:text-slate-200">{{ ind.label }}</span>
                                    <span class="font-mono font-bold text-slate-600 dark:text-slate-300">
                                        <template v-if="selectedEval.indicator_scores?.[aspectKey]?.[ind.code]">
                                            {{ selectedEval.indicator_scores[aspectKey][ind.code] }}/5 ({{ selectedEval.indicator_scores[aspectKey][ind.code] * 20 }})
                                            • {{ scaleLabels[selectedEval.indicator_scores[aspectKey][ind.code]] }}
                                        </template>
                                        <template v-else>-</template>
                                    </span>
                                </div>
                                <div class="w-full h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                    <div
                                        class="h-full bg-emerald-500 rounded-full"
                                        :style="{ width: `${(selectedEval.indicator_scores?.[aspectKey]?.[ind.code] || 0) * 20}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grafik Tren Antar Periode (> 1 periode) -->
                <div v-if="evaluations.length > 1" class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 p-6">
                    <h3 class="text-base font-black text-slate-900 dark:text-slate-100 mb-4">Tren Nilai Kinerja Antar Periode</h3>
                    <div class="h-64">
                        <Line :data="chartData" :options="chartOptions" />
                    </div>
                </div>
            </template>

            <!-- Empty State -->
            <div v-else class="bg-white dark:bg-slate-800 rounded-3xl border border-dashed border-slate-300 dark:border-slate-700 p-12 text-center">
                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-200">Belum Ada Penilaian Terpublikasi</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-2 max-w-md mx-auto">
                    Pimpinan belum mempublikasikan rapor kinerja (KPI) untuk akun Anda.
                </p>
            </div>
        </PageContainer>
    </AuthenticatedLayout>
</template>
