<script setup>
import { ref, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Modal from '@/Components/UI/Modal.vue'
import Badge from '@/Components/UI/Badge.vue'
import Button from '@/Components/UI/Button.vue'
import InsightPanel from './InsightPanel.vue'
import { usePerformanceScore } from '@/Composables/usePerformanceScore'

const props = defineProps({
    show: { type: Boolean, default: false },
    employee: { type: Object, default: null },
    filters: { type: Object, required: true },
    rubric: { type: Object, required: true },
})

const emit = defineEmits(['close'])

const { calculate, predicateVariant } = usePerformanceScore(props.rubric)

const form = useForm({
    user_id: '',
    academic_year: '',
    period_type: 'bulanan',
    period_label: '',
    category: 'guru',
    indicator_scores: {},
    notes: '',
    status: 'draft',
})

const initialSnapshot = ref('')
const insight = ref(null)
const loadingInsight = ref(false)
const clientError = ref('')

const buildEmptyScores = (category, existing = {}) => {
    const aspects = props.rubric?.categories?.[category]?.aspects || {}
    const out = {}
    for (const [aspectKey, meta] of Object.entries(aspects)) {
        out[aspectKey] = {}
        for (const ind of (meta.indicators || [])) {
            const val = existing?.[aspectKey]?.[ind.code]
            out[aspectKey][ind.code] = val !== undefined && val !== null ? Number(val) : null
        }
    }
    return out
}

const fetchInsight = async () => {
    if (!props.employee) return
    loadingInsight.value = true
    try {
        const url = route('admin.performance.insight', {
            user_id: props.employee.user_id,
            academic_year: form.academic_year,
            period_type: form.period_type,
            period_label: form.period_label,
            category: form.category,
        })
        const res = await fetch(url, { headers: { Accept: 'application/json' } })
        if (res.ok) {
            insight.value = await res.json()
        }
    } catch {
        insight.value = null
    } finally {
        loadingInsight.value = false
    }
}

watch(() => [props.show, props.employee], ([isOpen, emp]) => {
    if (!isOpen || !emp) return

    clientError.value = ''
    form.clearErrors()
    const ev = emp.evaluation
    const cat = ev?.category || emp.category || emp.auto_category || 'guru'

    form.user_id = emp.user_id
    form.academic_year = props.filters.academic_year
    form.period_type = props.filters.period_type
    form.period_label = props.filters.period_label
    form.category = cat
    form.indicator_scores = buildEmptyScores(cat, ev?.indicator_scores || {})
    form.notes = ev?.notes || ''
    form.status = ev?.status || 'draft'

    initialSnapshot.value = JSON.stringify({
        category: form.category,
        indicator_scores: form.indicator_scores,
        notes: form.notes,
    })

    fetchInsight()
}, { immediate: true })

const onCategoryChange = (newCat) => {
    form.category = newCat
    const existing = props.employee?.evaluation?.category === newCat
        ? (props.employee.evaluation.indicator_scores || {})
        : {}
    form.indicator_scores = buildEmptyScores(newCat, existing)
    fetchInsight()
}

const currentAspects = computed(() => props.rubric?.categories?.[form.category]?.aspects || {})
const scaleLabels = computed(() => props.rubric?.scale?.labels || { 1: 'Sangat Kurang', 2: 'Kurang', 3: 'Cukup', 4: 'Baik', 5: 'Sangat Baik' })
const preview = computed(() => calculate(form.category, form.indicator_scores))

const isDirty = computed(() => {
    if (!props.show) return false
    const now = JSON.stringify({
        category: form.category,
        indicator_scores: form.indicator_scores,
        notes: form.notes,
    })
    return now !== initialSnapshot.value
})

const handleClose = () => {
    if (isDirty.value && !confirm('Ada perubahan yang belum disimpan. Tutup form penilaian?')) {
        return
    }
    emit('close')
}

const submitAs = (targetStatus) => {
    clientError.value = ''
    if (targetStatus === 'published') {
        if (!preview.value.isComplete) {
            clientError.value = `Masih ada indikator yang belum diisi (${preview.value.filledCount}/${preview.value.totalIndicators}). Lengkapi seluruh indikator sebelum mempublikasikan.`
            return
        }
        if (!confirm(`Publikasikan penilaian kinerja ${props.employee?.name} dengan nilai ${preview.value.finalScore} (${preview.value.predicate})? Pegawai akan langsung dapat melihat rapor ini.`)) {
            return
        }
    }

    form.status = targetStatus
    form.post(route('admin.performance.store'), {
        preserveScroll: true,
        onSuccess: () => {
            emit('close')
        },
    })
}
</script>

<template>
    <Modal :show="show" max-width="4xl" @close="handleClose">
        <div class="bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 flex flex-col max-h-[88vh]">
            <!-- Header -->
            <div class="shrink-0 px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-lg font-black text-slate-900 dark:text-slate-100">{{ employee?.name }}</h3>
                        <Badge :variant="form.category === 'guru' ? 'primary' : 'info'">
                            {{ form.category === 'guru' ? 'Guru' : 'Karyawan' }}
                        </Badge>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        {{ employee?.nip || '-' }} • {{ employee?.divisi || '-' }} • {{ filters.academic_year }} ({{ filters.period_label }})
                    </p>
                </div>

                <div class="flex items-center gap-4">
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-0.5">Kategori Penilaian</label>
                        <select
                            :value="form.category"
                            @change="onCategoryChange($event.target.value)"
                            class="px-2.5 py-1.5 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800"
                        >
                            <option value="guru">Guru / Pendidik</option>
                            <option value="karyawan">Karyawan / Tendik</option>
                        </select>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] font-bold uppercase text-slate-400">Preview Nilai</div>
                        <div class="flex items-center gap-2">
                            <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ preview.finalScore.toFixed(2) }}</span>
                            <Badge :variant="predicateVariant(preview.predicate)">{{ preview.predicate }}</Badge>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div class="flex-1 min-h-0 p-6 space-y-5 overflow-y-auto overscroll-contain">
                <InsightPanel :insight="insight" :loading="loadingInsight" />

                <div v-if="clientError || form.errors.indicator_scores || form.errors.user_id" class="p-3 rounded-xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-800 text-xs font-semibold text-rose-700 dark:text-rose-300">
                    {{ clientError || form.errors.indicator_scores || form.errors.user_id }}
                </div>

                <!-- Aspek & Sub-Indikator -->
                <div v-for="(meta, aspectKey) in currentAspects" :key="aspectKey" class="rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <div class="px-4 py-3 bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-extrabold text-slate-800 dark:text-slate-100">{{ meta.label }}</h4>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ meta.description }}</p>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Skor Aspek</span>
                            <span class="text-base font-black text-emerald-600 dark:text-emerald-400">
                                {{ (preview.aspectScores[aspectKey] ?? 0).toFixed(2) }}
                            </span>
                        </div>
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-slate-700/70">
                        <div v-for="ind in meta.indicators" :key="ind.code" class="p-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
                            <div class="flex-1">
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ ind.label }}</div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ ind.description }}</div>
                            </div>

                            <div class="flex items-center gap-1.5 shrink-0" role="radiogroup" :aria-label="ind.label">
                                <label
                                    v-for="n in [1, 2, 3, 4, 5]"
                                    :key="n"
                                    :title="`${n} - ${scaleLabels[n]}`"
                                    class="relative cursor-pointer select-none"
                                >
                                    <input
                                        type="radio"
                                        :name="`${aspectKey}_${ind.code}`"
                                        :value="n"
                                        v-model="form.indicator_scores[aspectKey][ind.code]"
                                        class="sr-only"
                                    />
                                    <span
                                        class="inline-flex flex-col items-center justify-center w-11 h-10 rounded-xl border text-xs font-black transition-all"
                                        :class="form.indicator_scores[aspectKey]?.[ind.code] === n
                                            ? 'bg-emerald-600 border-emerald-600 text-white shadow-xs'
                                            : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-emerald-400'"
                                    >
                                        <span>{{ n }}</span>
                                        <span class="text-[8px] font-medium opacity-80 leading-none">{{ n * 20 }}</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Catatan -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Pesan Pembinaan / Catatan Evaluasi
                    </label>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        maxlength="2000"
                        placeholder="Berikan catatan, apresiasi, atau rencana tindak lanjut..."
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-200 focus:ring-emerald-500 focus:border-emerald-500"
                    ></textarea>
                    <p v-if="form.errors.notes" class="text-xs text-rose-600 mt-1">{{ form.errors.notes }}</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="shrink-0 px-6 py-4 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-100 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3">
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    Terisi: <strong>{{ preview.filledCount }}/{{ preview.totalIndicators }}</strong> indikator
                </div>
                <div class="flex items-center gap-2.5">
                    <Button type="button" variant="secondary" @click="handleClose">Batal</Button>
                    <Button type="button" variant="outline" :disabled="form.processing" @click="submitAs('draft')">
                        Simpan Draft
                    </Button>
                    <Button type="button" variant="primary" :disabled="form.processing" @click="submitAs('published')">
                        {{ form.processing ? 'Menyimpan...' : 'Publikasikan' }}
                    </Button>
                </div>
            </div>
        </div>
    </Modal>
</template>
