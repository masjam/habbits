<script setup>
import { ref, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Modal from '@/Components/UI/Modal.vue'
import EvaluationForm from '@/Components/Performance/EvaluationForm.vue'

const props = defineProps({
    show: { type: Boolean, default: false },
    employee: { type: Object, default: null },
    filters: { type: Object, required: true },
    rubric: { type: Object, required: true },
})

const emit = defineEmits(['close'])

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
        insight.value = res.ok ? await res.json() : null
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

const isDirty = computed(() => {
    if (!props.show) return false
    return JSON.stringify({
        category: form.category,
        indicator_scores: form.indicator_scores,
        notes: form.notes,
    }) !== initialSnapshot.value
})

const handleClose = () => {
    if (isDirty.value && !confirm('Ada perubahan yang belum disimpan. Tutup form penilaian?')) {
        return
    }
    emit('close')
}

const submitAs = (targetStatus, preview) => {
    clientError.value = ''
    if (targetStatus === 'published') {
        if (!preview.isComplete) {
            clientError.value = `Masih ada indikator yang belum diisi (${preview.filledCount}/${preview.totalIndicators}). Lengkapi seluruh indikator sebelum mempublikasikan.`
            return
        }
        if (!confirm(`Publikasikan penilaian kinerja ${props.employee?.name} dengan nilai ${preview.finalScore} (${preview.predicate})? Pegawai akan langsung dapat melihat rapor ini.`)) {
            return
        }
    }

    form.status = targetStatus
    form.post(route('admin.performance.store'), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    })
}
</script>

<template>
    <Modal :show="show" max-width="4xl" @close="handleClose">
        <EvaluationForm
            :form="form"
            :rubric="rubric"
            :employee-name="employee?.name || ''"
            :employee-subtitle="`${employee?.nip || '-'} • ${employee?.divisi || '-'} • ${filters.academic_year} (${filters.period_label})`"
            :insight="insight"
            :loading-insight="loadingInsight"
            mode="direct"
            :client-error="clientError"
            @category-change="onCategoryChange"
            @save-draft="submitAs('draft', $event)"
            @submit="submitAs('published', $event)"
            @close="handleClose"
        />
    </Modal>
</template>
