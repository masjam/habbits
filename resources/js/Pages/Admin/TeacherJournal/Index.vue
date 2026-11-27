<script setup>
import { ref, watch, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PageContainer from '@/Components/UI/PageContainer.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import Badge from '@/Components/UI/Badge.vue'
import Button from '@/Components/UI/Button.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import Modal from '@/Components/UI/Modal.vue'

const props = defineProps({
    journals: { type: Object, required: true },
    teacherSummaries: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
    classes: { type: Array, default: () => [] },
    teacherOptions: { type: Array, default: () => [] },
    academicYears: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ total_journals: 0, active_teachers: 0, total_teachers: 0, total_subjects: 0 }) },
    filters: { type: Object, required: true },
})

const tab = ref(props.filters.tab || 'per_guru')
const periodType = ref(props.filters.period_type || 'bulan')
const academicYear = ref(props.filters.academic_year || '')
const semester = ref(props.filters.semester || 'ganjil')
const month = ref(props.filters.month || '')
const startDate = ref(props.filters.start_date || '')
const endDate = ref(props.filters.end_date || '')
const teacherId = ref(props.filters.teacher_id || 'all')
const subject = ref(props.filters.subject || 'all')
const className = ref(props.filters.class_name || 'all')
const search = ref(props.filters.search || '')

const applyFilters = () => {
    router.get(route('admin.teacher-journals.index'), {
        tab: tab.value,
        period_type: periodType.value,
        academic_year: academicYear.value,
        semester: semester.value,
        month: month.value,
        start_date: startDate.value || undefined,
        end_date: endDate.value || undefined,
        teacher_id: teacherId.value,
        subject: subject.value,
        class_name: className.value,
        search: search.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

watch([tab, periodType, academicYear, semester, month, startDate, endDate, teacherId, subject, className], applyFilters)

let searchTimer = null
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(applyFilters, 350)
})

const viewTeacherJournals = (uid) => {
    teacherId.value = String(uid)
    tab.value = 'detail'
}

const hasActiveFilter = computed(() =>
    teacherId.value !== 'all' || subject.value !== 'all' || className.value !== 'all' || search.value.trim() !== ''
)

const resetFilters = () => {
    teacherId.value = 'all'
    subject.value = 'all'
    className.value = 'all'
    search.value = ''
}

const showDetailModal = ref(false)
const selectedJournal = ref(null)

const openDetail = (journal) => {
    selectedJournal.value = journal
    showDetailModal.value = true
}

const inputClass = 'w-full px-3 py-2 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors'
</script>

<template>
    <Head title="Rekap Jurnal Harian Guru" />

    <AuthenticatedLayout>
        <PageContainer>
            <PageHeader
                title="Rekap Jurnal Guru"
                subtitle="Pantau aktivitas pengisian jurnal mengajar per guru berdasarkan periode, mata pelajaran, dan kelas."
            />

            <!-- Ringkasan -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200/80 dark:border-slate-700 p-4">
                    <div class="text-[11px] font-bold uppercase text-slate-400">Total Entri Jurnal</div>
                    <div class="text-2xl font-black text-slate-900 dark:text-slate-100 mt-1">{{ stats.total_journals }}</div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-emerald-200 dark:border-emerald-900/50 p-4">
                    <div class="text-[11px] font-bold uppercase text-emerald-600">Guru Aktif Mengisi</div>
                    <div class="text-2xl font-black text-emerald-700 dark:text-emerald-400 mt-1">{{ stats.active_teachers }}</div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200/80 dark:border-slate-700 p-4">
                    <div class="text-[11px] font-bold uppercase text-slate-400">Total Guru</div>
                    <div class="text-2xl font-black text-slate-900 dark:text-slate-100 mt-1">{{ stats.total_teachers }}</div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200/80 dark:border-slate-700 p-4">
                    <div class="text-[11px] font-bold uppercase text-slate-400">Mata Pelajaran</div>
                    <div class="text-2xl font-black text-slate-900 dark:text-slate-100 mt-1">{{ stats.total_subjects }}</div>
                </div>
            </div>

            <!-- Bar Filter -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200/80 dark:border-slate-700 p-4 mb-5 space-y-3 shadow-2xs">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        Filter Periode, Mapel & Kelas
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
                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Mode Periode</label>
                        <select v-model="periodType" :class="inputClass">
                            <option value="bulan">Bulanan</option>
                            <option value="tanggal">Rentang Tanggal</option>
                            <option value="semester">Semester</option>
                            <option value="semua">Semua Waktu</option>
                        </select>
                    </div>

                    <div v-if="periodType === 'bulan'" class="lg:col-span-2">
                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Bulan</label>
                        <input v-model="month" type="month" :class="inputClass" />
                    </div>

                    <template v-else-if="periodType === 'tanggal'">
                        <div class="lg:col-span-2">
                            <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Dari Tanggal</label>
                            <input v-model="startDate" type="date" :class="inputClass" />
                        </div>
                        <div class="lg:col-span-2">
                            <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Sampai Tanggal</label>
                            <input v-model="endDate" type="date" :class="inputClass" />
                        </div>
                    </template>

                    <template v-else-if="periodType === 'semester'">
                        <div class="lg:col-span-2">
                            <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Tahun Ajaran</label>
                            <select v-model="academicYear" :class="inputClass">
                                <option v-for="y in academicYears" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </div>
                        <div class="lg:col-span-2">
                            <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Semester</label>
                            <select v-model="semester" :class="inputClass">
                                <option value="ganjil">Semester 1 (Ganjil)</option>
                                <option value="genap">Semester 2 (Genap)</option>
                            </select>
                        </div>
                    </template>

                    <div class="lg:col-span-2">
                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Mata Pelajaran</label>
                        <select v-model="subject" :class="inputClass">
                            <option value="all">Semua Mapel</option>
                            <option v-for="s in subjects" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Kelas</label>
                        <select v-model="className" :class="inputClass">
                            <option value="all">Semua Kelas</option>
                            <option v-for="c in classes" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </div>

                    <div v-if="tab === 'detail'" class="lg:col-span-2">
                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Filter Guru</label>
                        <select v-model="teacherId" :class="inputClass">
                            <option value="all">Semua Guru</option>
                            <option v-for="t in teacherOptions" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
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
                                placeholder="Cari nama guru, NIP, mapel, kelas, atau tema..."
                                class="w-full pl-10 pr-4 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2 Tab: Rekap Per Guru vs Daftar Detail Jurnal -->
            <div class="flex items-center gap-2 mb-4">
                <button
                    type="button"
                    @click="tab = 'per_guru'"
                    class="px-4 py-2.5 rounded-xl text-xs font-extrabold transition-all flex items-center gap-2 border"
                    :class="tab === 'per_guru'
                        ? 'bg-emerald-600 border-emerald-600 text-white shadow-xs'
                        : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50'"
                >
                    <span>Daftar Per Guru</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black" :class="tab === 'per_guru' ? 'bg-white/20 text-white' : 'bg-slate-100 dark:bg-slate-700'">
                        {{ teacherSummaries.length }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="tab = 'detail'"
                    class="px-4 py-2.5 rounded-xl text-xs font-extrabold transition-all flex items-center gap-2 border"
                    :class="tab === 'detail'
                        ? 'bg-emerald-600 border-emerald-600 text-white shadow-xs'
                        : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50'"
                >
                    <span>Detail Semua Jurnal</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black" :class="tab === 'detail' ? 'bg-white/20 text-white' : 'bg-slate-100 dark:bg-slate-700'">
                        {{ journals.total }}
                    </span>
                </button>
            </div>

            <!-- TAB 1: Daftar Per Guru -->
            <div v-if="tab === 'per_guru'" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200/80 dark:border-slate-700 shadow-2xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3.5">Nama Guru</th>
                                <th class="px-3 py-3.5 text-center">Jumlah Jurnal</th>
                                <th class="px-3 py-3.5 text-center">Hari Mengajar</th>
                                <th class="px-4 py-3.5">Mata Pelajaran</th>
                                <th class="px-4 py-3.5">Kelas</th>
                                <th class="px-3 py-3.5 text-center">Terakhir Isi</th>
                                <th class="px-4 py-3.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/70">
                            <tr v-for="t in teacherSummaries" :key="t.user_id" class="hover:bg-slate-50/70 dark:hover:bg-slate-700/30 transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 flex items-center justify-center font-extrabold text-xs shrink-0">
                                            {{ t.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold text-slate-900 dark:text-slate-100 truncate">{{ t.name }}</div>
                                            <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ t.nip || '-' }} • {{ t.divisi || '-' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <Badge :variant="t.total_journals > 0 ? 'success' : 'danger'">
                                        {{ t.total_journals }} entri
                                    </Badge>
                                </td>
                                <td class="px-3 py-3 text-center font-mono font-bold text-slate-700 dark:text-slate-200">
                                    {{ t.active_days }} hari
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="t.subjects.length > 0" class="flex flex-wrap gap-1">
                                        <Badge v-for="s in t.subjects" :key="s" variant="info" size="sm">{{ s }}</Badge>
                                    </div>
                                    <span v-else class="text-slate-400">-</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="t.classes.length > 0" class="flex flex-wrap gap-1">
                                        <Badge v-for="c in t.classes" :key="c" variant="neutral" size="sm">{{ c }}</Badge>
                                    </div>
                                    <span v-else class="text-slate-400">-</span>
                                </td>
                                <td class="px-3 py-3 text-center font-mono text-[11px] text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                    {{ t.last_date || '-' }}
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <Button type="button" size="sm" variant="outline" @click="viewTeacherJournals(t.user_id)">
                                        Lihat Jurnal
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="teacherSummaries.length === 0">
                                <td colspan="7" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">
                                    Tidak ada data guru yang cocok dengan filter saat ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: Detail Jurnal -->
            <template v-else>
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200/80 dark:border-slate-700 shadow-2xs overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <tr>
                                    <th class="px-4 py-3.5">Tanggal</th>
                                    <th class="px-4 py-3.5">Nama Guru</th>
                                    <th class="px-3 py-3.5">Kelas</th>
                                    <th class="px-3 py-3.5">Mapel</th>
                                    <th class="px-4 py-3.5">Tema</th>
                                    <th class="px-3 py-3.5">Waktu</th>
                                    <th class="px-3 py-3.5 text-center">Presensi</th>
                                    <th class="px-4 py-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/70">
                                <tr v-for="journal in journals.data" :key="journal.id" class="hover:bg-slate-50/70 dark:hover:bg-slate-700/30 transition-colors">
                                    <td class="px-4 py-3 font-mono whitespace-nowrap">{{ journal.date }}</td>
                                    <td class="px-4 py-3 font-bold text-slate-900 dark:text-slate-100">{{ journal.user?.name }}</td>
                                    <td class="px-3 py-3 whitespace-nowrap"><Badge variant="neutral" size="sm">{{ journal.class_name }}</Badge></td>
                                    <td class="px-3 py-3 whitespace-nowrap"><Badge variant="info" size="sm">{{ journal.subject }}</Badge></td>
                                    <td class="px-4 py-3">{{ journal.theme || '-' }}</td>
                                    <td class="px-3 py-3 font-mono whitespace-nowrap text-slate-500">
                                        {{ journal.time_start ? journal.time_start.substring(0,5) : '-' }} -
                                        {{ journal.time_end ? journal.time_end.substring(0,5) : '-' }}
                                    </td>
                                    <td class="px-3 py-3 text-center whitespace-nowrap font-mono text-[11px]">
                                        <span class="text-emerald-600 font-bold">H:{{ journal.student_present }}</span> ·
                                        <span class="text-sky-600">S:{{ journal.student_sick }}</span> ·
                                        <span class="text-amber-600">I:{{ journal.student_leave }}</span> ·
                                        <span class="text-rose-600">A:{{ journal.student_absent }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <Button type="button" size="sm" variant="outline" @click="openDetail(journal)">Detail</Button>
                                    </td>
                                </tr>
                                <tr v-if="journals.data.length === 0">
                                    <td colspan="8" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">
                                        Belum ada jurnal mengajar pada filter ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="journals.links?.length > 3" class="mt-5">
                    <Pagination :links="journals.links" />
                </div>
            </template>
        </PageContainer>

        <!-- Detail Modal -->
        <Modal :show="showDetailModal" max-width="2xl" @close="showDetailModal = false">
            <div v-if="selectedJournal" class="bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-3">
                    <div>
                        <h3 class="text-lg font-black text-slate-900 dark:text-slate-100">Detail Jurnal Guru</h3>
                        <p class="text-xs text-slate-500">{{ selectedJournal.user?.name }} • {{ selectedJournal.date }}</p>
                    </div>
                    <button type="button" @click="showDetailModal = false" class="text-slate-400 hover:text-slate-600 font-bold">✕</button>
                </div>

                <div class="grid grid-cols-2 gap-3 text-xs bg-slate-50 dark:bg-slate-900/50 p-4 rounded-xl">
                    <div><span class="font-bold text-slate-400 block">Mata Pelajaran</span>{{ selectedJournal.subject }}</div>
                    <div><span class="font-bold text-slate-400 block">Kelas</span>{{ selectedJournal.class_name }}</div>
                    <div><span class="font-bold text-slate-400 block">Tema / Topik</span>{{ selectedJournal.theme || '-' }}</div>
                    <div><span class="font-bold text-slate-400 block">Jam Mengajar</span>{{ selectedJournal.time_start?.substring(0,5) || '-' }} - {{ selectedJournal.time_end?.substring(0,5) || '-' }}</div>
                </div>

                <div class="text-xs space-y-1">
                    <div class="font-bold text-slate-500 uppercase">Uraian Materi</div>
                    <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-700 prose prose-sm dark:prose-invert max-w-none" v-html="selectedJournal.material"></div>
                </div>

                <div v-if="selectedJournal.notes" class="text-xs space-y-1">
                    <div class="font-bold text-slate-500 uppercase">Catatan</div>
                    <p class="p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 text-amber-900 dark:text-amber-200">{{ selectedJournal.notes }}</p>
                </div>

                <div v-if="selectedJournal.photo" class="text-xs space-y-1">
                    <div class="font-bold text-slate-500 uppercase">Foto Dokumentasi</div>
                    <img :src="`/storage/journals/${selectedJournal.photo.split('/').pop()}`" alt="Dokumentasi Jurnal" class="rounded-xl max-h-64 object-contain border border-slate-200 dark:border-slate-700" />
                </div>

                <div class="flex justify-end pt-2">
                    <Button type="button" variant="secondary" @click="showDetailModal = false">Tutup</Button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
