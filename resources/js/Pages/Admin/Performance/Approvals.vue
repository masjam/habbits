<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PageContainer from '@/Components/UI/PageContainer.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import Badge from '@/Components/UI/Badge.vue'
import Button from '@/Components/UI/Button.vue'
import Modal from '@/Components/UI/Modal.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import EvaluationForm from '@/Components/Performance/EvaluationForm.vue'
import EvaluationStatusBadge from '@/Components/Performance/EvaluationStatusBadge.vue'
import { usePerformanceScore } from '@/Composables/usePerformanceScore'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    queue: { type: Object, required: true },
    rubric: { type: Object, required: true },
})

const { predicateVariant } = usePerformanceScore(props.rubric)

const selectedIds = ref([])
const reviewingItem = ref(null)
const showReviewModal = ref(false)
const showRevisionModal = ref(false)
const insight = ref(null)
const loadingInsight = ref(false)

const reviewForm = useForm({
    category: 'guru',
    indicator_scores: {},
    notes: '',
    approval_note: '',
})

const revisionForm = useForm({
    note: '',
})

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

const openReview = async (item) => {
    reviewingItem.value = item
    reviewForm.category = item.category
    reviewForm.indicator_scores = buildEmptyScores(item.category, item.indicator_scores || {})
    reviewForm.notes = item.notes || ''
    reviewForm.approval_note = ''
    reviewForm.clearErrors()
    showReviewModal.value = true

    loadingInsight.value = true
    try {
        const url = route('admin.performance.insight', {
            user_id: item.user_id,
            academic_year: item.academic_year,
            period_type: item.period_type,
            period_label: item.period_label,
            category: item.category,
        })
        const res = await fetch(url, { headers: { Accept: 'application/json' } })
        insight.value = res.ok ? await res.json() : null
    } catch {
        insight.value = null
    } finally {
        loadingInsight.value = false
    }
}

const approveSingle = (item) => {
    if (!confirm(`Setujui dan publikasikan KPI ${item.employee_name} (nilai ${item.final_score})?`)) return
    router.post(route('admin.performance.approvals.approve', item.id), {}, { preserveScroll: true })
}

const bulkApprove = () => {
    if (selectedIds.value.length === 0) return
    if (!confirm(`Setujui dan publikasikan ${selectedIds.value.length} evaluasi KPI yang dipilih?`)) return
    router.post(route('admin.performance.approvals.bulk-approve'), { ids: selectedIds.value }, {
        preserveScroll: true,
        onSuccess: () => { selectedIds.value = [] },
    })
}

const approveFromModal = (preview) => {
    if (!preview.isComplete) return
    if (!confirm(`Setujui & publikasikan KPI ${reviewingItem.value.employee_name} dengan nilai ${preview.finalScore} (${preview.predicate})?`)) return
    reviewForm.post(route('admin.performance.approvals.update-and-approve', reviewingItem.value.id), {
        preserveScroll: true,
        onSuccess: () => { showReviewModal.value = false },
    })
}

const openRevisionModal = () => {
    revisionForm.note = ''
    revisionForm.clearErrors()
    showRevisionModal.value = true
}

const submitRevision = () => {
    revisionForm.post(route('admin.performance.approvals.request-revision', reviewingItem.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showRevisionModal.value = false
            showReviewModal.value = false
        },
    })
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Antrean Persetujuan KPI" />

        <PageContainer>
            <PageHeader
                title="Antrean Persetujuan KPI"
                subtitle="Tinjau, sesuaikan, atau setujui hasil penilaian KPI yang diajukan oleh penilai delegasi."
            >
                <template #actions>
                    <Link
                        :href="route('admin.performance.index')"
                        class="inline-flex items-center px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800"
                    >
                        Kembali ke KPI
                    </Link>
                    <Button
                        v-if="selectedIds.length > 0"
                        type="button"
                        variant="primary"
                        size="sm"
                        @click="bulkApprove"
                    >
                        Setujui Massal ({{ selectedIds.length }})
                    </Button>
                </template>
            </PageHeader>

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div v-if="queue.data.length === 0" class="p-10 text-center text-sm text-slate-500 dark:text-slate-400">
                    Tidak ada pengajuan KPI yang menunggu persetujuan.
                </div>

                <div v-else class="divide-y divide-slate-100 dark:divide-slate-700">
                    <div
                        v-for="item in queue.data"
                        :key="item.id"
                        class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                    >
                        <div class="flex items-start gap-3">
                            <input
                                type="checkbox"
                                :value="item.id"
                                v-model="selectedIds"
                                class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                            />
                            <div class="space-y-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm font-extrabold text-slate-900 dark:text-slate-100">{{ item.employee_name }}</span>
                                    <EvaluationStatusBadge :status="item.status" />
                                    <Badge :variant="predicateVariant(item.predicate)">
                                        {{ item.final_score.toFixed(2) }} ({{ item.predicate }})
                                    </Badge>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ item.employee_divisi || '-' }} • Periode {{ item.academic_year }} ({{ item.period_label }})
                                    • Dinilai oleh <strong>{{ item.assessor_name }}</strong> pada {{ item.submitted_at }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <Button type="button" variant="outline" size="sm" @click="openReview(item)">Tinjau</Button>
                            <Button type="button" variant="primary" size="sm" @click="approveSingle(item)">Setujui</Button>
                        </div>
                    </div>
                </div>

                <div v-if="queue.links?.length > 3" class="p-4 border-t border-slate-100 dark:border-slate-700">
                    <Pagination :links="queue.links" />
                </div>
            </div>
        </PageContainer>

        <!-- Modal Tinjau & Edit/Setujui -->
        <Modal :show="showReviewModal" max-width="4xl" @close="showReviewModal = false">
            <EvaluationForm
                :form="reviewForm"
                :rubric="rubric"
                :employee-name="reviewingItem?.employee_name || ''"
                :employee-subtitle="`Penilai: ${reviewingItem?.assessor_name || '-'} • ${reviewingItem?.academic_year || ''} (${reviewingItem?.period_label || ''})`"
                :insight="insight"
                :loading-insight="loadingInsight"
                mode="approver"
                :allow-category-change="false"
                @approve="approveFromModal"
                @request-revision="openRevisionModal"
                @close="showReviewModal = false"
            />
            <div v-if="reviewingItem?.logs?.length" class="px-6 py-3 bg-slate-50 dark:bg-slate-900/60 border-t border-slate-100 dark:border-slate-700 text-[11px] text-slate-500 dark:text-slate-400 space-y-1 max-h-32 overflow-y-auto">
                <div class="font-bold uppercase text-slate-400">Riwayat Audit</div>
                <div v-for="log in reviewingItem.logs" :key="log.id">
                    • {{ log.created_at }} — <strong>{{ log.actor_name }}</strong> ({{ log.action }})
                    <span v-if="log.note">: {{ log.note }}</span>
                </div>
            </div>
        </Modal>

        <!-- Modal Minta Revisi -->
        <Modal :show="showRevisionModal" max-width="md" @close="showRevisionModal = false">
            <div class="p-6 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 space-y-4">
                <h3 class="text-base font-black">Minta Revisi Penilaian</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Berikan catatan perbaikan untuk <strong>{{ reviewingItem?.assessor_name }}</strong>.
                </p>
                <div>
                    <textarea
                        v-model="revisionForm.note"
                        rows="3"
                        maxlength="2000"
                        placeholder="Tuliskan alasan atau bagian yang perlu diperbaiki..."
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 text-xs"
                    ></textarea>
                    <p v-if="revisionForm.errors.note" class="text-xs text-rose-600 mt-1">{{ revisionForm.errors.note }}</p>
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" @click="showRevisionModal = false">Batal</Button>
                    <Button type="button" variant="primary" :disabled="revisionForm.processing" @click="submitRevision">
                        Kirim Permintaan Revisi
                    </Button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
