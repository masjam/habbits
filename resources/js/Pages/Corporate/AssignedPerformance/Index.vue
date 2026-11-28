<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PageContainer from '@/Components/UI/PageContainer.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import Badge from '@/Components/UI/Badge.vue'
import Button from '@/Components/UI/Button.vue'
import Modal from '@/Components/UI/Modal.vue'
import EvaluationForm from '@/Components/Performance/EvaluationForm.vue'
import EvaluationStatusBadge from '@/Components/Performance/EvaluationStatusBadge.vue'
import { usePerformanceScore } from '@/Composables/usePerformanceScore'
import { Head, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    tasks: { type: Array, default: () => [] },
    rubric: { type: Object, required: true },
})

const { predicateVariant } = usePerformanceScore(props.rubric)

// Filter state (konsisten dengan halaman penilaian Kepala Sekolah)
const academicYears = computed(() => Array.from(new Set(props.tasks.map((t) => t.academic_year))).sort())
const divisions = computed(() => Array.from(new Set(props.tasks.map((t) => t.employee?.divisi).filter(Boolean))).sort())

const academicYear = ref('all')
const periodType = ref('all')
const division = ref('all')
const status = ref('all')
const search = ref('')
const category = ref(props.tasks.some((t) => t.employee?.category === 'guru') ? 'guru' : 'karyawan')

const baseFiltered = computed(() => {
    const q = search.value.trim().toLowerCase()
    return props.tasks.filter((t) => {
        if (academicYear.value !== 'all' && t.academic_year !== academicYear.value) return false
        if (periodType.value !== 'all' && t.period_type !== periodType.value) return false
        if (division.value !== 'all' && t.employee?.divisi !== division.value) return false
        if (q && !t.employee?.name?.toLowerCase().includes(q) && !(t.employee?.nip || '').toLowerCase().includes(q)) return false
        const st = t.evaluation?.status || 'belum'
        if (status.value !== 'all' && st !== status.value) return false
        return true
    })
})

const categoryCounts = computed(() => ({
    guru: baseFiltered.value.filter((t) => t.employee?.category === 'guru').length,
    karyawan: baseFiltered.value.filter((t) => t.employee?.category === 'karyawan').length,
}))

const filteredTasks = computed(() =>
    baseFiltered.value.filter((t) => t.employee?.category === category.value)
)

const summary = computed(() => {
    const list = filteredTasks.value
    const evaluated = list.filter((t) => t.evaluation !== null)
    const avg = evaluated.length
        ? evaluated.reduce((sum, t) => sum + Number(t.evaluation.final_score || 0), 0) / evaluated.length
        : 0
    return {
        total: list.length,
        evaluated: evaluated.length,
        not_evaluated: list.length - evaluated.length,
        average_score: avg,
    }
})

const hasActiveFilter = computed(() =>
    academicYear.value !== 'all' || periodType.value !== 'all' || division.value !== 'all' || status.value !== 'all' || search.value.trim() !== ''
)

const resetFilters = () => {
    academicYear.value = 'all'
    periodType.value = 'all'
    division.value = 'all'
    status.value = 'all'
    search.value = ''
}

const activeAspects = computed(() => props.rubric?.categories?.[category.value]?.aspects || {})
const shortLabel = (label) => label.replace(/^Kompetensi\s+/i, '')
const inputClass = 'w-full px-3 py-2 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors'

// Modal penilaian
const showModal = ref(false)
const selectedTask = ref(null)
const insight = ref(null)
const loadingInsight = ref(false)
const clientError = ref('')
const initialSnapshot = ref('')

const form = useForm({
    user_id: '',
    academic_year: '',
    period_type: 'bulanan',
    period_label: '',
    category: 'guru',
    indicator_scores: {},
    notes: '',
})

const buildEmptyScores = (cat, existing = {}) => {
    const aspects = props.rubric?.categories?.[cat]?.aspects || {}
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

const fetchInsight = async (task) => {
    loadingInsight.value = true
    try {
        const url = route('performance.assigned.insight', {
            user_id: task.employee.id,
            academic_year: task.academic_year,
            period_type: task.period_type,
            period_label: task.period_label,
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

const openTask = (task) => {
    selectedTask.value = task
    clientError.value = ''
    form.clearErrors()

    const ev = task.evaluation
    const cat = ev?.category || task.employee.category || 'guru'
    form.user_id = task.employee.id
    form.academic_year = task.academic_year
    form.period_type = task.period_type
    form.period_label = task.period_label
    form.category = cat
    form.indicator_scores = buildEmptyScores(cat, ev?.indicator_scores || {})
    form.notes = ev?.notes || ''

    initialSnapshot.value = JSON.stringify({
        category: form.category,
        indicator_scores: form.indicator_scores,
        notes: form.notes,
    })

    showModal.value = true
    fetchInsight(task)
}

const isReadonly = computed(() => {
    const st = selectedTask.value?.evaluation?.status
    return st === 'submitted' || st === 'published'
})

const isDirty = computed(() => {
    if (!showModal.value || isReadonly.value) return false
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
    showModal.value = false
}

const onCategoryChange = (newCat) => {
    form.category = newCat
    const existing = selectedTask.value?.evaluation?.category === newCat
        ? (selectedTask.value.evaluation.indicator_scores || {})
        : {}
    form.indicator_scores = buildEmptyScores(newCat, existing)
    if (selectedTask.value) fetchInsight(selectedTask.value)
}

const saveDraft = () => {
    clientError.value = ''
    form.post(route('performance.assigned.save-draft'), {
        preserveScroll: true,
        onSuccess: () => { showModal.value = false },
    })
}

const submitToPrincipal = (preview) => {
    clientError.value = ''
    if (!preview.isComplete) {
        clientError.value = `Masih ada indikator yang belum diisi (${preview.filledCount}/${preview.totalIndicators}). Lengkapi seluruh indikator sebelum mengajukan.`
        return
    }
    if (!confirm(`Ajukan penilaian ${selectedTask.value?.employee?.name} (nilai ${preview.finalScore} - ${preview.predicate}) ke Kepala Sekolah? Setelah diajukan, nilai tidak dapat diubah kecuali diminta revisi.`)) {
        return
    }
    form.post(route('performance.assigned.submit'), {
        preserveScroll: true,
        onSuccess: () => { showModal.value = false },
    })
}

const actionLabel = (task) => {
    const st = task.evaluation?.status
    if (!st) return 'Beri Nilai'
    if (st === 'draft') return 'Lanjutkan'
    if (st === 'revision_requested') return 'Perbaiki'
    return 'Lihat'
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Tugas Penilaian Saya" />

        <PageContainer>
            <PageHeader
                title="Tugas Penilaian Saya"
                subtitle="Daftar pegawai yang didelegasikan kepada Anda untuk dinilai dan diajukan ke Kepala Sekolah."
            />

            <!-- Ringkasan -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-4">
                    <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                        Total {{ category === 'guru' ? 'Guru' : 'Karyawan' }}
                    </div>
                    <div class="text-2xl font-extrabold text-slate-900 dark:text-slate-100 mt-1 tabular-nums">{{ summary.total }}</div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-emerald-200/80 dark:border-emerald-900/50 p-4">
                    <div class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Sudah dinilai</div>
                    <div class="text-2xl font-extrabold text-emerald-700 dark:text-emerald-400 mt-1 tabular-nums">{{ summary.evaluated }}</div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-rose-200/80 dark:border-rose-900/50 p-4">
                    <div class="text-xs font-semibold text-rose-600 dark:text-rose-400">Belum dinilai</div>
                    <div class="text-2xl font-extrabold text-rose-600 dark:text-rose-400 mt-1 tabular-nums">{{ summary.not_evaluated }}</div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-4">
                    <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">Rata-rata nilai</div>
                    <div class="text-2xl font-extrabold text-slate-900 dark:text-slate-100 mt-1 tabular-nums">{{ Number(summary.average_score).toFixed(2) }}</div>
                </div>
            </div>

            <!-- Bar Filter -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200/80 dark:border-slate-700 p-4 mb-5 space-y-3 shadow-2xs">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        Periode & Filter Penilaian
                    </span>
                    <button
                        v-if="hasActiveFilter"
                        type="button"
                        @click="resetFilters"
                        class="text-[11px] font-bold text-rose-600 hover:text-rose-700 dark:text-rose-400 cursor-pointer"
                    >
                        Reset Filter
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
                    <div class="lg:col-span-3">
                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Tahun Ajaran</label>
                        <select v-model="academicYear" :class="inputClass">
                            <option value="all">Semua Tahun</option>
                            <option v-for="y in academicYears" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>

                    <div class="lg:col-span-3">
                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Periode</label>
                        <select v-model="periodType" :class="inputClass">
                            <option value="all">Semua Periode</option>
                            <option value="bulanan">Bulanan</option>
                            <option value="semester_1">Semester 1 (Ganjil)</option>
                            <option value="semester_2">Semester 2 (Genap)</option>
                        </select>
                    </div>

                    <div class="lg:col-span-3">
                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Divisi</label>
                        <select v-model="division" :class="inputClass">
                            <option value="all">Semua Divisi</option>
                            <option v-for="d in divisions" :key="d" :value="d">{{ d }}</option>
                        </select>
                    </div>

                    <div class="lg:col-span-3">
                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Status</label>
                        <select v-model="status" :class="inputClass">
                            <option value="all">Semua Status</option>
                            <option value="belum">Belum Dinilai</option>
                            <option value="draft">Draft</option>
                            <option value="revision_requested">Perlu Revisi</option>
                            <option value="submitted">Diajukan</option>
                            <option value="published">Disetujui</option>
                        </select>
                    </div>

                    <div class="sm:col-span-2 lg:col-span-12">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari nama pegawai atau NIP..."
                            :class="inputClass"
                        />
                    </div>
                </div>
            </div>

            <!-- 2 Tab Kategori: Guru vs Karyawan -->
            <div class="flex items-center gap-2 mb-4">
                <button
                    type="button"
                    @click="category = 'guru'"
                    class="px-4 py-2.5 rounded-xl text-xs font-extrabold transition-colors flex items-center gap-2 border cursor-pointer"
                    :class="category === 'guru'
                        ? 'bg-emerald-600 border-emerald-600 text-white shadow-xs'
                        : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50'"
                >
                    <span>Guru / Pendidik</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black" :class="category === 'guru' ? 'bg-white/20 text-white' : 'bg-slate-100 dark:bg-slate-700'">
                        {{ categoryCounts.guru }}
                    </span>
                </button>
                <button
                    type="button"
                    @click="category = 'karyawan'"
                    class="px-4 py-2.5 rounded-xl text-xs font-extrabold transition-colors flex items-center gap-2 border cursor-pointer"
                    :class="category === 'karyawan'
                        ? 'bg-emerald-600 border-emerald-600 text-white shadow-xs'
                        : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50'"
                >
                    <span>Karyawan / Tendik</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black" :class="category === 'karyawan' ? 'bg-white/20 text-white' : 'bg-slate-100 dark:bg-slate-700'">
                        {{ categoryCounts.karyawan }}
                    </span>
                </button>
            </div>

            <!-- Tabel Daftar Tugas Penilaian -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200/80 dark:border-slate-700 shadow-2xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3.5">Pegawai</th>
                                <th
                                    v-for="(meta, key) in activeAspects"
                                    :key="key"
                                    class="px-3 py-3.5 text-center whitespace-nowrap"
                                    :title="meta.label"
                                >
                                    {{ shortLabel(meta.label) }}
                                </th>
                                <th class="px-3 py-3.5 text-center">Nilai Akhir</th>
                                <th class="px-3 py-3.5 text-center">Status / Predikat</th>
                                <th class="px-4 py-3.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/70">
                            <tr
                                v-for="task in filteredTasks"
                                :key="task.assignment_id"
                                class="hover:bg-slate-50/70 dark:hover:bg-slate-700/30 transition-colors"
                            >
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 flex items-center justify-center font-extrabold text-xs shrink-0">
                                            {{ task.employee.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold text-slate-900 dark:text-slate-100 truncate">{{ task.employee.name }}</div>
                                            <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                                                {{ task.employee.nip || '-' }} • {{ task.employee.divisi || '-' }} • {{ task.academic_year }} ({{ task.period_label }})
                                            </div>
                                            <div v-if="task.evaluation?.status === 'revision_requested' && task.evaluation?.revision_note" class="text-[11px] text-amber-700 dark:text-amber-300 font-semibold mt-0.5">
                                                Revisi: {{ task.evaluation.revision_note }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td
                                    v-for="(meta, key) in activeAspects"
                                    :key="key"
                                    class="px-3 py-3 text-center font-mono font-bold text-slate-700 dark:text-slate-200 whitespace-nowrap"
                                >
                                    {{ task.evaluation && task.evaluation.aspect_scores?.[key] !== undefined ? Number(task.evaluation.aspect_scores[key]).toFixed(1) : '-' }}
                                </td>

                                <td class="px-3 py-3 text-center whitespace-nowrap">
                                    <span
                                        v-if="task.evaluation"
                                        class="font-mono text-sm font-black text-emerald-600 dark:text-emerald-400"
                                    >
                                        {{ Number(task.evaluation.final_score).toFixed(2) }}
                                    </span>
                                    <span v-else class="text-slate-400">-</span>
                                </td>

                                <td class="px-3 py-3 text-center whitespace-nowrap">
                                    <div v-if="!task.evaluation">
                                        <EvaluationStatusBadge :status="null" />
                                    </div>
                                    <div v-else class="inline-flex items-center gap-1.5">
                                        <EvaluationStatusBadge :status="task.evaluation.status" />
                                        <Badge v-if="task.evaluation.status === 'published'" :variant="predicateVariant(task.evaluation.predicate)">
                                            {{ task.evaluation.predicate }}
                                        </Badge>
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <Button
                                        type="button"
                                        size="sm"
                                        :variant="task.evaluation?.status === 'revision_requested' || !task.evaluation ? 'primary' : 'outline'"
                                        @click="openTask(task)"
                                    >
                                        {{ actionLabel(task) }}
                                    </Button>
                                </td>
                            </tr>

                            <tr v-if="filteredTasks.length === 0">
                                <td :colspan="Object.keys(activeAspects).length + 4" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">
                                    Tidak ada tugas penilaian {{ category === 'guru' ? 'guru' : 'karyawan' }} yang cocok dengan filter saat ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </PageContainer>

        <Modal :show="showModal" max-width="4xl" @close="handleClose">
            <EvaluationForm
                :form="form"
                :rubric="rubric"
                :employee-name="selectedTask?.employee?.name || ''"
                :employee-subtitle="`${selectedTask?.employee?.nip || '-'} • ${selectedTask?.employee?.divisi || '-'} • ${selectedTask?.academic_year || ''} (${selectedTask?.period_label || ''})`"
                :insight="insight"
                :loading-insight="loadingInsight"
                mode="delegate"
                :readonly="isReadonly"
                :revision-note="selectedTask?.evaluation?.revision_note || ''"
                :client-error="clientError"
                @category-change="onCategoryChange"
                @save-draft="saveDraft"
                @submit="submitToPrincipal"
                @close="handleClose"
            />
        </Modal>
    </AuthenticatedLayout>
</template>
