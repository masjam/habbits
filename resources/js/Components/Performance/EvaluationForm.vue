<script setup>
import { computed } from 'vue'
import Badge from '@/Components/UI/Badge.vue'
import Button from '@/Components/UI/Button.vue'
import InsightPanel from '@/Pages/Admin/Performance/InsightPanel.vue'
import { usePerformanceScore } from '@/Composables/usePerformanceScore'

const props = defineProps({
    form: { type: Object, required: true },
    rubric: { type: Object, required: true },
    employeeName: { type: String, default: '' },
    employeeSubtitle: { type: String, default: '' },
    insight: { type: Object, default: null },
    loadingInsight: { type: Boolean, default: false },
    mode: { type: String, default: 'direct' }, // 'direct' | 'delegate' | 'approver'
    readonly: { type: Boolean, default: false },
    revisionNote: { type: String, default: '' },
    clientError: { type: String, default: '' },
    allowCategoryChange: { type: Boolean, default: true },
})

const emit = defineEmits(['category-change', 'save-draft', 'submit', 'approve', 'request-revision', 'close'])

const { calculate, predicateVariant } = usePerformanceScore(props.rubric)

const currentAspects = computed(() => props.rubric?.categories?.[props.form.category]?.aspects || {})
const scaleLabels = computed(() => props.rubric?.scale?.labels || {
    1: 'Sangat Kurang',
    2: 'Kurang',
    3: 'Cukup',
    4: 'Baik',
    5: 'Sangat Baik',
})
const preview = computed(() => calculate(props.form.category, props.form.indicator_scores))
</script>

<template>
    <div class="bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 flex flex-col">
        <!-- Header -->
        <div class="shrink-0 px-5 sm:px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="text-lg font-black text-slate-900 dark:text-slate-100">{{ employeeName }}</h3>
                    <Badge :variant="form.category === 'guru' ? 'primary' : 'info'">
                        {{ form.category === 'guru' ? 'Guru' : 'Karyawan' }}
                    </Badge>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ employeeSubtitle }}</p>
            </div>

            <div class="flex items-center gap-4">
                <div v-if="allowCategoryChange && !readonly">
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-0.5">Kategori Penilaian</label>
                    <select
                        :value="form.category"
                        @change="emit('category-change', $event.target.value)"
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
        <div class="p-5 sm:p-6 space-y-5 overflow-y-auto overscroll-contain max-h-[65vh]">
            <div v-if="revisionNote" class="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-200">
                <span class="font-extrabold block mb-0.5">Catatan Revisi Kepala Sekolah:</span>
                {{ revisionNote }}
            </div>

            <InsightPanel :insight="insight" :loading="loadingInsight" />

            <div v-if="clientError || form.errors?.indicator_scores || form.errors?.user_id" class="p-3 rounded-xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-800 text-xs font-semibold text-rose-700 dark:text-rose-300">
                {{ clientError || form.errors?.indicator_scores || form.errors?.user_id }}
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
                                class="relative select-none"
                                :class="readonly ? 'cursor-not-allowed opacity-85' : 'cursor-pointer'"
                            >
                                <input
                                    type="radio"
                                    :name="`${aspectKey}_${ind.code}`"
                                    :value="n"
                                    :disabled="readonly"
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
                    :disabled="readonly"
                    rows="3"
                    maxlength="2000"
                    placeholder="Berikan catatan, apresiasi, atau rencana tindak lanjut..."
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-200 focus:ring-emerald-500 focus:border-emerald-500 disabled:opacity-75"
                ></textarea>
                <p v-if="form.errors?.notes" class="text-xs text-rose-600 mt-1">{{ form.errors.notes }}</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="shrink-0 px-5 sm:px-6 py-4 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-100 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3">
            <div class="text-xs text-slate-500 dark:text-slate-400">
                Terisi: <strong>{{ preview.filledCount }}/{{ preview.totalIndicators }}</strong> indikator
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button type="button" variant="secondary" @click="emit('close')">Tutup</Button>
                <template v-if="!readonly && mode === 'direct'">
                    <Button type="button" variant="outline" :disabled="form.processing" @click="emit('save-draft', preview)">
                        Simpan Draft
                    </Button>
                    <Button type="button" variant="primary" :disabled="form.processing" @click="emit('submit', preview)">
                        {{ form.processing ? 'Menyimpan...' : 'Publikasikan' }}
                    </Button>
                </template>
                <template v-else-if="!readonly && mode === 'delegate'">
                    <Button type="button" variant="outline" :disabled="form.processing" @click="emit('save-draft', preview)">
                        Simpan Draft
                    </Button>
                    <Button type="button" variant="primary" :disabled="form.processing" @click="emit('submit', preview)">
                        {{ form.processing ? 'Mengajukan...' : 'Ajukan ke Kepala Sekolah' }}
                    </Button>
                </template>
                <template v-else-if="mode === 'approver'">
                    <Button type="button" variant="outline" :disabled="form.processing" @click="emit('request-revision', preview)">
                        Minta Revisi
                    </Button>
                    <Button type="button" variant="primary" :disabled="form.processing" @click="emit('approve', preview)">
                        {{ form.processing ? 'Menyetujui...' : 'Setujui & Publikasikan' }}
                    </Button>
                </template>
            </div>
        </div>
    </div>
</template>
