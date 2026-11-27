<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PageContainer from '@/Components/UI/PageContainer.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import Badge from '@/Components/UI/Badge.vue'
import Button from '@/Components/UI/Button.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import EvaluationFormModal from './EvaluationFormModal.vue'
import RubricManagerModal from './RubricManagerModal.vue'
import { usePerformanceScore } from '@/Composables/usePerformanceScore'
import { Head, router } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'

const props = defineProps({
    employees: { type: Object, required: true },
    filters: { type: Object, required: true },
    categoryCounts: { type: Object, default: () => ({ guru: 0, karyawan: 0 }) },
    academicYears: { type: Array, default: () => [] },
    divisions: { type: Array, default: () => [] },
    rubric: { type: Object, required: true },
    summary: { type: Object, required: true },
})

const { predicateVariant } = usePerformanceScore(props.rubric)

const academicYear = ref(props.filters.academic_year)
const periodType = ref(props.filters.period_type)
const periodLabel = ref(props.filters.period_label)
const division = ref(props.filters.division)
const category = ref(props.filters.category === 'karyawan' ? 'karyawan' : 'guru')
const status = ref(props.filters.status)
const search = ref(props.filters.search || '')

const selectedMonthYear = ref(
    periodType.value === 'bulanan' && /^\d{4}-\d{2}$/.test(periodLabel.value)
        ? periodLabel.value
        : `${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, '0')}`
)

const buildQuery = () => ({
    academic_year: academicYear.value,
    period_type: periodType.value,
    period_label: periodType.value === 'bulanan' ? selectedMonthYear.value : periodType.value,
    division: division.value,
    category: category.value,
    status: status.value,
    search: search.value || undefined,
})

const applyFilters = () => {
    router.get(route('admin.performance.index'), buildQuery(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

watch([academicYear, periodType, selectedMonthYear, division, category, status], applyFilters)

let searchTimer = null
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(applyFilters, 350)
})

const switchTab = (cat) => {
    category.value = cat
}

const hasActiveFilter = computed(() =>
    division.value !== 'all' || status.value !== 'all' || search.value.trim() !== ''
)

const resetFilters = () => {
    division.value = 'all'
    status.value = 'all'
    search.value = ''
}

const exportUrl = (format) => route('admin.performance.export', { ...buildQuery(), format })

const showModal = ref(false)
const showRubricModal = ref(false)
const selectedEmployee = ref(null)

const openEvaluationModal = (emp) => {
    selectedEmployee.value = emp
    showModal.value = true
}

const activeAspects = computed(() => props.rubric?.categories?.[category.value]?.aspects || {})
const shortLabel = (label) => label.replace(/^Kompetensi\s+/i, '')

const inputClass = 'w-full px-3 py-2 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors'
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Evaluasi Kinerja Pegawai (KPI)" />

        <PageContainer>
            <PageHeader
                title="Evaluasi Kinerja (KPI)"
                subtitle="Penilaian kinerja berbasis rubrik kompetensi untuk Guru dan Karyawan."
            >
                <template #actions>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="showRubricModal = true"
                    >
                        Kelola Indikator
                    </Button>
                    <a
                        :href="exportUrl('xlsx')"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition-colors"
                    >
                        Export Excel
                    </a>
                    <a
                        :href="exportUrl('pdf')"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-bold shadow-xs transition-colors"
                    >
                        Export PDF
                    </a>
                </template>
            </PageHeader>

            <!-- Ringkasan -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200/80 dark:border-slate-700 p-4">
                    <div class="text-[11px] font-bold uppercase text-slate-400">
                        Total {{ category === 'guru' ? 'Guru' : 'Karyawan' }}
                    </div>
                    <div class="text-2xl font-black text-slate-900 dark:text-slate-100 mt-1">{{ summary.total }}</div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-emerald-200 dark:border-emerald-900/50 p-4">
                    <div class="text-[11px] font-bold uppercase text-emerald-600">Sudah Dinilai</div>
                    <div class="text-2xl font-black text-emerald-700 dark:text-emerald-400 mt-1">{{ summary.evaluated }}</div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-rose-200 dark:border-rose-900/50 p-4">
                    <div class="text-[11px] font-bold uppercase text-rose-600">Belum Dinilai</div>
                    <div class="text-2xl font-black text-rose-600 dark:text-rose-400 mt-1">{{ summary.not_evaluated }}</div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200/80 dark:border-slate-700 p-4">
                    <div class="text-[11px] font-bold uppercase text-slate-400">Rata-Rata Nilai</div>
                    <div class="text-2xl font-black text-slate-900 dark:text-slate-100 mt-1">{{ Number(summary.average_score).toFixed(2) }}</div>
                </div>
            </div>

            <!-- Bar Pengaturan Periode & Filter -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200/80 dark:border-slate-700 p-4 mb-5 space-y-3 shadow-2xs">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        Periode & Filter Penilaian
                    </span>
                    <button
                        v-if="hasActiveFilter"
                        type="button"
                        @click="resetFilters"
                        class="text-[11px] font-bold text-rose-600 hover:text-rose-700 dark:text-rose-400"
                    >
                        Reset Filter
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
                    <div class="lg:col-span-2">
                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Tahun Ajaran</label>
                        <select v-model="academicYear" :class="inputClass">
                            <option v-for="y in academicYears" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Periode</label>
                        <select v-model="periodType" :class="inputClass">
                            <option value="bulanan">Bulanan</option>
                            <option value="semester_1">Semester 1 (Ganjil)</option>
                            <option value="semester_2">Semester 2 (Genap)</option>
                        </select>
                    </div>

                    <div v-if="periodType === 'bulanan'" class="lg:col-span-2">
                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Bulan</label>
                        <input v-model="selectedMonthYear" type="month" :class="inputClass" />
                    </div>

                    <div :class="periodType === 'bulanan' ? 'lg:col-span-3' : 'lg:col-span-4'">
                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Divisi</label>
                        <select v-model="division" :class="inputClass">
                            <option value="all">Semua Divisi</option>
                            <option v-for="d in divisions" :key="d" :value="d">{{ d }}</option>
                        </select>
                    </div>

                    <div :class="periodType === 'bulanan' ? 'lg:col-span-3' : 'lg:col-span-4'">
                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Status</label>
                        <select v-model="status" :class="inputClass">
                            <option value="all">Semua Status</option>
                            <option value="belum">Belum Dinilai</option>
                            <option value="draft">Draft</option>
                            <option value="published">Dipublikasikan</option>
                        </select>
                    </div>

                    <div class="sm:col-span-2 lg:col-span-12">
                        <div class="relative">
                            <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                            </svg>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Cari nama pegawai atau NIP..."
                                class="w-full pl-10 pr-4 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2 Tab Kategori: Guru vs Karyawan -->
            <div class="flex items-center gap-2 mb-4">
                <button
                    type="button"
                    @click="switchTab('guru')"
                    class="px-4 py-2.5 rounded-xl text-xs font-extrabold transition-all flex items-center gap-2 border"
                    :class="category === 'guru'
                        ? 'bg-emerald-600 border-emerald-600 text-white shadow-xs'
                        : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50'"
                >
                    <span>Guru / Pendidik</span>
                    <span
                        class="px-2 py-0.5 rounded-full text-[10px] font-black"
                        :class="category === 'guru' ? 'bg-white/20 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'"
                    >
                        {{ categoryCounts.guru }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="switchTab('karyawan')"
                    class="px-4 py-2.5 rounded-xl text-xs font-extrabold transition-all flex items-center gap-2 border"
                    :class="category === 'karyawan'
                        ? 'bg-emerald-600 border-emerald-600 text-white shadow-xs'
                        : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50'"
                >
                    <span>Karyawan / Tendik</span>
                    <span
                        class="px-2 py-0.5 rounded-full text-[10px] font-black"
                        :class="category === 'karyawan' ? 'bg-white/20 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'"
                    >
                        {{ categoryCounts.karyawan }}
                    </span>
                </button>
            </div>

            <!-- Tabel Daftar Pegawai dengan Judul Kolom Aspek -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200/80 dark:border-slate-700 shadow-2xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3.5">Pegawai</th>
                                <th
                                    v-for="(meta, key) in activeAspects"
                                    :key="key"
                                    class="px-3 py-3.5 text-center"
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
                                v-for="emp in employees.data"
                                :key="emp.user_id"
                                class="hover:bg-slate-50/70 dark:hover:bg-slate-700/30 transition-colors"
                            >
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 flex items-center justify-center font-extrabold text-xs shrink-0">
                                            {{ emp.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold text-slate-900 dark:text-slate-100 truncate">{{ emp.name }}</div>
                                            <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                                                {{ emp.nip || '-' }} • {{ emp.divisi || '-' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td
                                    v-for="(meta, key) in activeAspects"
                                    :key="key"
                                    class="px-3 py-3 text-center font-mono font-bold text-slate-700 dark:text-slate-200 whitespace-nowrap"
                                >
                                    {{ emp.evaluation && emp.evaluation.aspect_scores?.[key] !== undefined ? Number(emp.evaluation.aspect_scores[key]).toFixed(1) : '-' }}
                                </td>

                                <td class="px-3 py-3 text-center whitespace-nowrap">
                                    <span
                                        v-if="emp.evaluation"
                                        class="font-mono text-sm font-black text-emerald-600 dark:text-emerald-400"
                                    >
                                        {{ Number(emp.evaluation.final_score).toFixed(2) }}
                                    </span>
                                    <span v-else class="text-slate-400">-</span>
                                </td>

                                <td class="px-3 py-3 text-center whitespace-nowrap">
                                    <Badge v-if="!emp.evaluation" variant="danger">Belum Dinilai</Badge>
                                    <Badge v-else-if="emp.evaluation.status === 'draft'" variant="warning">Draft</Badge>
                                    <Badge v-else :variant="predicateVariant(emp.evaluation.predicate)">
                                        {{ emp.evaluation.predicate }}
                                    </Badge>
                                </td>

                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <Button
                                        type="button"
                                        size="sm"
                                        :variant="emp.evaluation ? 'outline' : 'primary'"
                                        @click="openEvaluationModal(emp)"
                                    >
                                        {{ emp.evaluation ? 'Edit Nilai' : 'Beri Nilai' }}
                                    </Button>
                                </td>
                            </tr>

                            <tr v-if="employees.data.length === 0">
                                <td :colspan="Object.keys(activeAspects).length + 4" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">
                                    Tidak ada {{ category === 'guru' ? 'guru' : 'karyawan' }} yang cocok dengan filter saat ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="employees.links?.length > 3" class="mt-5">
                <Pagination :links="employees.links" />
            </div>
        </PageContainer>

        <EvaluationFormModal
            :show="showModal"
            :employee="selectedEmployee"
            :filters="filters"
            :rubric="rubric"
            @close="showModal = false"
        />

        <RubricManagerModal
            :show="showRubricModal"
            :rubric="rubric"
            :initial-category="category"
            @close="showRubricModal = false"
        />
    </AuthenticatedLayout>
</template>
