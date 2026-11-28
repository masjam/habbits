<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PageContainer from '@/Components/UI/PageContainer.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import Badge from '@/Components/UI/Badge.vue'
import Button from '@/Components/UI/Button.vue'
import Modal from '@/Components/UI/Modal.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import EvaluationFormModal from './EvaluationFormModal.vue'
import EvaluationStatusBadge from '@/Components/Performance/EvaluationStatusBadge.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'

const props = defineProps({
    assignments: { type: Object, required: true },
    filters: { type: Object, required: true },
    categoryCounts: { type: Object, default: () => ({ guru: 0, karyawan: 0 }) },
    academicYears: { type: Array, default: () => [] },
    divisions: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    activeAssignedEmployeeIds: { type: Array, default: () => [] },
    rubric: { type: Object, required: true },
})

const academicYear = ref(props.filters.academic_year)
const periodType = ref(props.filters.period_type)
const periodLabel = ref(props.filters.period_label)
const category = ref(props.filters.category === 'karyawan' ? 'karyawan' : 'guru')
const status = ref(props.filters.status)
const division = ref(props.filters.division)
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
    category: category.value,
    status: status.value,
    division: division.value,
    search: search.value || undefined,
})

const applyFilters = () => {
    router.get(route('admin.performance.assignments.index'), buildQuery(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

watch([academicYear, periodType, selectedMonthYear, category, status, division], applyFilters)
let searchTimer = null
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(applyFilters, 350)
})

const showAddModal = ref(false)
const assessorSearch = ref('')
const employeeSearch = ref('')
const modalDivision = ref('all')

const form = useForm({
    academic_year: props.filters.academic_year,
    period_type: props.filters.period_type,
    period_label: props.filters.period_label,
    assessor_id: '',
    employee_ids: [],
    note: '',
})

const openAddModal = () => {
    form.academic_year = academicYear.value
    form.period_type = periodType.value
    form.period_label = periodType.value === 'bulanan' ? selectedMonthYear.value : periodType.value
    form.assessor_id = ''
    form.employee_ids = []
    form.note = ''
    form.clearErrors()
    assessorSearch.value = ''
    employeeSearch.value = ''
    modalDivision.value = 'all'
    showAddModal.value = true
}

const filteredAssessors = computed(() => {
    const q = assessorSearch.value.trim().toLowerCase()
    if (!q) return props.employees
    return props.employees.filter((e) =>
        e.name.toLowerCase().includes(q) || (e.divisi || '').toLowerCase().includes(q)
    )
})

const assignedSet = computed(() => new Set(props.activeAssignedEmployeeIds.map(Number)))

const filteredTargets = computed(() => {
    const q = employeeSearch.value.trim().toLowerCase()
    return props.employees.filter((e) => {
        if (e.category !== category.value) return false
        if (modalDivision.value !== 'all' && e.divisi !== modalDivision.value) return false
        if (q && !e.name.toLowerCase().includes(q) && !(e.nip || '').toLowerCase().includes(q)) return false
        return true
    })
})

const selectAllAvailableInView = () => {
    const ids = filteredTargets.value
        .filter((e) => Number(e.id) !== Number(form.assessor_id) && !assignedSet.value.has(Number(e.id)))
        .map((e) => e.id)
    form.employee_ids = Array.from(new Set([...form.employee_ids, ...ids]))
}

const submitDelegation = () => {
    form.post(route('admin.performance.assignments.bulk-store'), {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false
        },
    })
}

const revoke = (item) => {
    if (!confirm(`Cabut delegasi ${item.assessor?.name} untuk menilai ${item.employee?.name}? Draft yang sudah dibuat tetap tersimpan.`)) {
        return
    }
    router.delete(route('admin.performance.assignments.revoke', item.id), {
        preserveScroll: true,
    })
}

const groupedAssignments = computed(() => {
    const groups = new Map()
    for (const item of props.assignments.data || []) {
        const key = item.assessor_id
        if (!groups.has(key)) {
            groups.set(key, {
                assessor_id: key,
                assessor: item.assessor,
                assigned_by: item.assigned_by,
                created_at: item.created_at,
                items: [],
            })
        }
        groups.get(key).items.push(item)
    }
    return Array.from(groups.values())
})

const showEvalModal = ref(false)
const selectedEmployee = ref(null)

const openEvalModal = (item) => {
    selectedEmployee.value = {
        user_id: item.employee_id,
        name: item.employee?.name || '-',
        nip: item.employee?.nip || '-',
        divisi: item.employee?.divisi || '-',
        auto_category: item.employee?.category || 'guru',
        category: item.employee?.category || 'guru',
        evaluation: item.evaluation || null,
    }
    showEvalModal.value = true
}

const approveItem = (item) => {
    if (!item.evaluation || item.evaluation.status !== 'submitted') return
    if (!confirm(`Setujui & publikasikan KPI ${item.employee?.name} (nilai ${item.evaluation.final_score})?`)) return
    router.post(route('admin.performance.approvals.approve', item.evaluation.id), {}, { preserveScroll: true })
}

const activeAspects = computed(() => props.rubric?.categories?.[category.value]?.aspects || {})
const shortLabel = (label) => label.replace(/^Kompetensi\s+/i, '')

const aspectScoreFor = (item, aspectKey) => {
    const val = item.evaluation?.aspect_scores?.[aspectKey]
    return val !== undefined && val !== null ? Number(val).toFixed(1) : '-'
}

const inputClass = 'w-full px-3 py-2 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500'
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Delegasi Penilai KPI" />

        <PageContainer>
            <PageHeader
                title="Delegasi Penilai KPI"
                subtitle="Tugaskan penilai delegasi untuk menilai pegawai pada periode tertentu."
            >
                <template #actions>
                    <Link
                        :href="route('admin.performance.index')"
                        class="inline-flex items-center px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800"
                    >
                        Kembali ke KPI
                    </Link>
                    <Button type="button" variant="primary" size="sm" @click="openAddModal">
                        + Tambah Delegasi
                    </Button>
                </template>
            </PageHeader>

            <!-- Filter -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Tahun Ajaran</label>
                    <select v-model="academicYear" :class="inputClass">
                        <option v-for="y in academicYears" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Jenis Periode</label>
                    <select v-model="periodType" :class="inputClass">
                        <option value="bulanan">Bulanan</option>
                        <option value="semester_1">Semester 1</option>
                        <option value="semester_2">Semester 2</option>
                    </select>
                </div>
                <div v-if="periodType === 'bulanan'">
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Bulan</label>
                    <input type="month" v-model="selectedMonthYear" :class="inputClass" />
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Status</label>
                    <select v-model="status" :class="inputClass">
                        <option value="active">Aktif</option>
                        <option value="revoked">Dicabut</option>
                        <option value="all">Semua</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Cari</label>
                    <input v-model="search" type="text" placeholder="Nama penilai / pegawai..." :class="inputClass" />
                </div>
            </div>

            <!-- Tab Guru / Tendik -->
            <div class="flex items-center gap-2 mb-4">
                <button
                    type="button"
                    @click="category = 'guru'"
                    class="px-4 py-2 rounded-xl text-xs font-extrabold transition-all cursor-pointer flex items-center gap-2"
                    :class="category === 'guru'
                        ? 'bg-emerald-600 text-white shadow-xs'
                        : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700'"
                >
                    <span>Guru / Pendidik</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px]" :class="category === 'guru' ? 'bg-white/20 text-white' : 'bg-slate-100 dark:bg-slate-700'">
                        {{ categoryCounts.guru }}
                    </span>
                </button>
                <button
                    type="button"
                    @click="category = 'karyawan'"
                    class="px-4 py-2 rounded-xl text-xs font-extrabold transition-all cursor-pointer flex items-center gap-2"
                    :class="category === 'karyawan'
                        ? 'bg-emerald-600 text-white shadow-xs'
                        : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700'"
                >
                    <span>Karyawan / Tendik</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px]" :class="category === 'karyawan' ? 'bg-white/20 text-white' : 'bg-slate-100 dark:bg-slate-700'">
                        {{ categoryCounts.karyawan }}
                    </span>
                </button>
            </div>

            <!-- Tabel Delegasi -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div v-if="assignments.data.length === 0" class="p-10 text-center text-sm text-slate-500 dark:text-slate-400">
                    Belum ada delegasi penilai {{ category === 'guru' ? 'Guru' : 'Tendik' }} untuk periode ini.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <tr>
                                <th rowspan="2" class="px-3 py-2.5 border-r border-slate-200 dark:border-slate-700 text-center w-10">No</th>
                                <th rowspan="2" class="px-3 py-2.5 border-r border-slate-200 dark:border-slate-700">Penilai</th>
                                <th rowspan="2" class="px-3 py-2.5 border-r border-slate-200 dark:border-slate-700">Pegawai</th>
                                <th :colspan="Object.keys(activeAspects).length + 1" class="px-3 py-1.5 border-b border-r border-slate-200 dark:border-slate-700 text-center">Draf Nilai</th>
                                <th rowspan="2" class="px-3 py-2.5 text-right">Aksi</th>
                            </tr>
                            <tr>
                                <th
                                    v-for="(meta, aKey) in activeAspects"
                                    :key="aKey"
                                    :title="meta.label"
                                    class="px-2.5 py-1.5 border-r border-slate-200 dark:border-slate-700 text-center whitespace-nowrap"
                                >
                                    {{ shortLabel(meta.label) }}
                                </th>
                                <th class="px-2.5 py-1.5 border-r border-slate-200 dark:border-slate-700 text-center">Akhir</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/70">
                            <template v-for="(group, gIdx) in groupedAssignments" :key="group.assessor_id">
                                <tr
                                    v-for="(item, iIdx) in group.items"
                                    :key="item.id"
                                    class="hover:bg-slate-50/70 dark:hover:bg-slate-700/30 transition-colors"
                                >
                                    <td
                                        v-if="iIdx === 0"
                                        :rowspan="group.items.length"
                                        class="px-3 py-2.5 border-r border-slate-200 dark:border-slate-700 text-center font-bold text-slate-600 dark:text-slate-300 align-top"
                                    >
                                        {{ gIdx + 1 }}
                                    </td>
                                    <td
                                        v-if="iIdx === 0"
                                        :rowspan="group.items.length"
                                        class="px-3 py-2.5 border-r border-slate-200 dark:border-slate-700 font-extrabold text-slate-900 dark:text-slate-100 align-top whitespace-nowrap"
                                    >
                                        {{ group.assessor?.name || '-' }}
                                    </td>
                                    <td class="px-3 py-2.5 border-r border-slate-200 dark:border-slate-700 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-slate-800 dark:text-slate-100">{{ item.employee?.name || '-' }}</span>
                                            <EvaluationStatusBadge :status="item.evaluation?.status" />
                                            <Badge v-if="item.status === 'revoked'" variant="neutral" size="sm">Dicabut</Badge>
                                        </div>
                                    </td>
                                    <td
                                        v-for="(meta, aKey) in activeAspects"
                                        :key="aKey"
                                        class="px-2.5 py-2.5 border-r border-slate-200 dark:border-slate-700 text-center font-mono text-slate-700 dark:text-slate-200"
                                    >
                                        {{ aspectScoreFor(item, aKey) }}
                                    </td>
                                    <td class="px-2.5 py-2.5 border-r border-slate-200 dark:border-slate-700 text-center font-mono font-black text-emerald-600 dark:text-emerald-400">
                                        {{ item.evaluation ? Number(item.evaluation.final_score).toFixed(1) : '-' }}
                                    </td>
                                    <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1.5 text-[11px] font-bold">
                                            <button
                                                v-if="item.status === 'active'"
                                                type="button"
                                                class="text-rose-600 hover:underline cursor-pointer"
                                                @click="revoke(item)"
                                            >
                                                Cabut
                                            </button>
                                            <span v-if="item.status === 'active'" class="text-slate-300 dark:text-slate-600">|</span>
                                            <button
                                                type="button"
                                                class="text-emerald-600 dark:text-emerald-400 hover:underline cursor-pointer"
                                                @click="openEvalModal(item)"
                                            >
                                                Nilai
                                            </button>
                                            <template v-if="item.evaluation?.status === 'submitted'">
                                                <span class="text-slate-300 dark:text-slate-600">|</span>
                                                <button
                                                    type="button"
                                                    class="text-sky-600 dark:text-sky-400 hover:underline cursor-pointer"
                                                    @click="approveItem(item)"
                                                >
                                                    Approval
                                                </button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div v-if="assignments.links?.length > 3" class="p-4 border-t border-slate-100 dark:border-slate-700">
                    <Pagination :links="assignments.links" />
                </div>
            </div>
        </PageContainer>

        <EvaluationFormModal
            :show="showEvalModal"
            :employee="selectedEmployee"
            :filters="filters"
            :rubric="rubric"
            @close="showEvalModal = false"
        />

        <!-- Modal Tambah Delegasi -->
        <Modal :show="showAddModal" max-width="3xl" @close="showAddModal = false">
            <div class="p-6 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-black">Tambah Delegasi Penilai</h3>
                    <span class="text-xs font-bold text-slate-500">{{ form.academic_year }} ({{ form.period_label }})</span>
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1">1. Pilih Penilai Delegasi</label>
                    <input v-model="assessorSearch" type="text" placeholder="Cari nama atau divisi penilai..." :class="inputClass" class="mb-2" />
                    <select v-model="form.assessor_id" :class="inputClass">
                        <option value="">-- Pilih Penilai --</option>
                        <option v-for="u in filteredAssessors" :key="u.id" :value="u.id">
                            {{ u.name }} ({{ u.divisi || 'Umum' }})
                        </option>
                    </select>
                    <p v-if="form.errors.assessor_id" class="text-xs text-rose-600 mt-1">{{ form.errors.assessor_id }}</p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-bold">2. Pilih Pegawai yang Dinilai ({{ form.employee_ids.length }} dipilih)</label>
                        <button type="button" class="text-xs font-bold text-emerald-600 hover:underline" @click="selectAllAvailableInView">
                            Pilih Semua Tersedia
                        </button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-2">
                        <input v-model="employeeSearch" type="text" placeholder="Cari pegawai..." :class="inputClass" />
                        <select v-model="modalDivision" :class="inputClass">
                            <option value="all">Semua Divisi</option>
                            <option v-for="d in divisions" :key="d" :value="d">{{ d }}</option>
                        </select>
                    </div>

                    <div class="max-h-56 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-700 divide-y divide-slate-100 dark:divide-slate-700">
                        <label
                            v-for="emp in filteredTargets"
                            :key="emp.id"
                            class="flex items-center justify-between px-3 py-2 text-xs cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-900/40"
                        >
                            <div class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    :value="emp.id"
                                    v-model="form.employee_ids"
                                    :disabled="Number(emp.id) === Number(form.assessor_id) || assignedSet.has(Number(emp.id))"
                                    class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                />
                                <span class="font-bold">{{ emp.name }}</span>
                                <span class="text-slate-400">({{ emp.divisi || '-' }})</span>
                            </div>
                            <span v-if="Number(emp.id) === Number(form.assessor_id)" class="text-[10px] font-bold text-amber-600">Penilai sendiri</span>
                            <span v-else-if="assignedSet.has(Number(emp.id))" class="text-[10px] font-bold text-slate-400">Sudah punya penilai</span>
                        </label>
                    </div>
                    <p v-if="form.errors.employee_ids" class="text-xs text-rose-600 mt-1">{{ form.errors.employee_ids }}</p>
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1">Catatan Penugasan (opsional)</label>
                    <input v-model="form.note" type="text" placeholder="Mis. Koordinator Kurikulum menilai guru kelas" :class="inputClass" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="secondary" @click="showAddModal = false">Batal</Button>
                    <Button type="button" variant="primary" :disabled="form.processing || !form.assessor_id || form.employee_ids.length === 0" @click="submitDelegation">
                        {{ form.processing ? 'Menyimpan...' : `Simpan (${form.employee_ids.length} Pegawai)` }}
                    </Button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
