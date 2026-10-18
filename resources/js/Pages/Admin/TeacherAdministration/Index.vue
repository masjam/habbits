<script setup>
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    documents: {
        type: Object,
        default: () => ({ data: [] })
    },
    teachers: {
        type: Array,
        default: () => []
    },
    matrix: {
        type: Array,
        default: () => []
    },
    categories: {
        type: Object,
        default: () => ({})
    },
    classesList: {
        type: Array,
        default: () => []
    },
    classStats: {
        type: Object,
        default: () => ({})
    },
    teacherGroups: {
        type: Array,
        default: () => []
    },
    totalAll: {
        type: Number,
        default: 0
    },
    totalAllPending: {
        type: Number,
        default: 0
    },
    stats: {
        type: Object,
        default: () => ({ total_berkas: 0, total_disetujui: 0, total_perlu_revisi: 0, total_menunggu: 0 })
    },
    yearsList: {
        type: Array,
        default: () => []
    },
    currentAcademicYear: {
        type: String,
        default: ''
    },
    currentSemester: {
        type: String,
        default: 'ganjil'
    },
    filters: {
        type: Object,
        default: () => ({})
    }
})

// Active Tab: 'list' or 'matrix'
const activeTab = ref('list')

// View Mode in List Tab: 'hierarchy' (default) or 'flat'
const viewMode = ref('hierarchy')

// Filter states
const filterGradeLevel = ref(props.filters.grade_level || 'all')
const filterYear = ref(props.filters.academic_year || props.currentAcademicYear)
const filterSemester = ref(props.filters.semester || props.currentSemester)
const filterUserId = ref(props.filters.user_id || 'all')
const filterCategory = ref(props.filters.category || 'all')
const filterStatus = ref(props.filters.status || 'all')
const searchQuery = ref(props.filters.search || '')

const applyFilters = () => {
    router.get(route('admin.teacher-administrations.index'), {
        academic_year: filterYear.value,
        semester: filterSemester.value,
        user_id: filterUserId.value === 'all' ? null : filterUserId.value,
        category: filterCategory.value === 'all' ? null : filterCategory.value,
        status: filterStatus.value === 'all' ? null : filterStatus.value,
        grade_level: filterGradeLevel.value === 'all' ? null : filterGradeLevel.value,
        search: searchQuery.value || null,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

const selectClassTab = (cls) => {
    filterGradeLevel.value = cls
    applyFilters()
}

const resetFilters = () => {
    filterGradeLevel.value = 'all'
    filterYear.value = props.currentAcademicYear
    filterSemester.value = props.currentSemester
    filterUserId.value = 'all'
    filterCategory.value = 'all'
    filterStatus.value = 'all'
    searchQuery.value = ''
    applyFilters()
}

// Review / Telaah Modal
const showReviewModal = ref(false)
const selectedDocForReview = ref(null)

const reviewForm = useForm({
    status: 'disetujui',
    feedback: '',
})

const openReviewModal = (doc) => {
    selectedDocForReview.value = doc
    reviewForm.status = doc.status === 'menunggu_verifikasi' ? 'disetujui' : doc.status
    reviewForm.feedback = doc.feedback || ''
    showReviewModal.value = true
}

const submitReview = () => {
    if (!selectedDocForReview.value) return
    reviewForm.post(route('admin.teacher-administrations.verify', selectedDocForReview.value.id), {
        onSuccess: () => {
            showReviewModal.value = false
            selectedDocForReview.value = null
            reviewForm.reset()
        }
    })
}

// Admin Delete Action
const deleteDocument = (doc) => {
    if (confirm(`Apakah Anda yakin ingin menghapus berkas "${doc.title}" milik ${doc.user?.name}?`)) {
        router.delete(route('admin.teacher-administrations.destroy', doc.id), {
            preserveScroll: true
        })
    }
}

// Helpers
const getStatusBadge = (status) => {
    switch (status) {
        case 'disetujui':
            return {
                label: 'Disetujui',
                bg: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800/60',
                dot: 'bg-emerald-500'
            }
        case 'perlu_revisi':
            return {
                label: 'Perlu Revisi',
                bg: 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800/60',
                dot: 'bg-rose-500'
            }
        default:
            return {
                label: 'Menunggu Persetujuan',
                bg: 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800/60',
                dot: 'bg-amber-500'
            }
    }
}

const getFileIconColor = (ext) => {
    const extension = (ext || '').toLowerCase()
    if (extension === 'pdf') return 'text-rose-500 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800/50'
    if (['doc', 'docx'].includes(extension)) return 'text-blue-500 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/40 border-blue-200 dark:border-blue-800/50'
    if (['xls', 'xlsx'].includes(extension)) return 'text-emerald-500 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800/50'
    if (['ppt', 'pptx'].includes(extension)) return 'text-orange-500 dark:text-orange-400 bg-orange-50 dark:bg-orange-950/40 border-orange-200 dark:border-orange-800/50'
    return 'text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700'
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Rekap Administrasi Guru - Sahabat SDAM" />

        <div class="space-y-6 pb-12">
            <!-- ── Header Banner ─────────────────────────────────── -->
            <div class="relative overflow-hidden rounded-3xl bg-linear-to-br from-emerald-700 via-teal-800 to-slate-900 p-6 md:p-8 text-white shadow-lg">
                <div class="absolute -right-10 -bottom-10 w-64 h-64 rounded-full bg-white/5 blur-2xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="space-y-2 max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 backdrop-blur-md text-emerald-200 text-xs font-semibold tracking-wide border border-white/10">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Monitoring Kurikulum &amp; Telaah Berkas</span>
                        </div>
                        <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-white drop-shadow-xs">
                            Rekap Administrasi Guru
                        </h1>
                        <p class="text-sm md:text-base text-emerald-100/90 leading-relaxed">
                            Pantau matriks pemenuhan berkas perangkat ajar seluruh guru per semester, lakukan penelaahan, dan berikan catatan perbaikan.
                        </p>
                    </div>

                    <!-- Navigation Tabs -->
                    <div class="inline-flex p-1.5 rounded-2xl bg-black/25 backdrop-blur-md border border-white/10 self-start md:self-center shrink-0">
                        <button
                            @click="activeTab = 'list'"
                            :class="[
                                'px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer',
                                activeTab === 'list'
                                    ? 'bg-white text-emerald-900 shadow-sm'
                                    : 'text-white/80 hover:text-white'
                            ]"
                        >
                            Daftar Pengajuan
                        </button>
                        <button
                            @click="activeTab = 'matrix'"
                            :class="[
                                'px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer',
                                activeTab === 'matrix'
                                    ? 'bg-white text-emerald-900 shadow-sm'
                                    : 'text-white/80 hover:text-white'
                            ]"
                        >
                            Matriks Kelengkapan
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Stat Cards ───────────────────────────────────── -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="p-5 rounded-3xl bg-surface border border-theme shadow-xs">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Total Berkas Terkumpul</p>
                    <p class="text-2xl md:text-3xl font-black text-slate-800 dark:text-slate-100 mt-1">{{ stats.total_berkas }}</p>
                </div>
                <div class="p-5 rounded-3xl bg-surface border border-theme shadow-xs">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Telah Disetujui</p>
                    <p class="text-2xl md:text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ stats.total_disetujui }}</p>
                </div>
                <div class="p-5 rounded-3xl bg-surface border border-theme shadow-xs">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Menunggu Telaah</p>
                    <p class="text-2xl md:text-3xl font-black text-amber-500 mt-1">{{ stats.total_menunggu }}</p>
                </div>
                <div class="p-5 rounded-3xl bg-surface border border-theme shadow-xs">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Perlu Revisi</p>
                    <p class="text-2xl md:text-3xl font-black text-rose-500 mt-1">{{ stats.total_perlu_revisi }}</p>
                </div>
            </div>

            <!-- ── Filter & Search Bar ──────────────────────────── -->
            <div class="p-4 md:p-5 rounded-3xl bg-surface border border-theme shadow-xs space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                    <!-- Search Input -->
                    <div class="lg:col-span-2">
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Pencarian</label>
                        <div class="relative">
                            <input
                                type="text"
                                v-model="searchQuery"
                                @keyup.enter="applyFilters"
                                placeholder="Cari guru, judul, atau mapel..."
                                class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 pl-9 pr-3 py-2.5 focus:ring-2 focus:ring-emerald-500"
                            />
                            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>

                    <!-- Tahun Ajaran -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Tahun Ajaran</label>
                        <select
                            v-model="filterYear"
                            @change="applyFilters"
                            class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500"
                        >
                            <option value="all">Semua Tahun</option>
                            <option v-for="yr in yearsList" :key="yr" :value="yr">{{ yr }}</option>
                        </select>
                    </div>

                    <!-- Semester -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Semester</label>
                        <select
                            v-model="filterSemester"
                            @change="applyFilters"
                            class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500"
                        >
                            <option value="all">Semua</option>
                            <option value="ganjil">Ganjil</option>
                            <option value="genap">Genap</option>
                        </select>
                    </div>

                    <!-- Guru -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Guru</label>
                        <select
                            v-model="filterUserId"
                            @change="applyFilters"
                            class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500"
                        >
                            <option value="all">Semua Guru</option>
                            <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Status Telaah</label>
                        <select
                            v-model="filterStatus"
                            @change="applyFilters"
                            class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500"
                        >
                            <option value="all">Semua Status</option>
                            <option value="menunggu_verifikasi">Menunggu Telaah</option>
                            <option value="disetujui">Disetujui</option>
                            <option value="perlu_revisi">Perlu Revisi</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-subtle">
                    <p class="text-xs text-slate-400">Tekan Enter atau ganti pilihan filter untuk menyaring data</p>
                    <button
                        @click="resetFilters"
                        class="text-xs font-bold text-slate-500 hover:text-emerald-600 transition-colors cursor-pointer"
                    >
                        Reset Filter
                    </button>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════ -->
            <!-- ── TAB 1: DAFTAR PENGAJUAN BERKAS ──────────────────── -->
            <!-- ══════════════════════════════════════════════════════ -->
            <div v-show="activeTab === 'list'" class="space-y-4">
                <!-- ── TAB BAR KELAS ─────────────────────────────────── -->
                <div class="p-4 md:p-5 rounded-3xl bg-surface border border-theme shadow-xs space-y-3">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                Tab Kelas:
                            </span>
                            <span v-if="totalAllPending > 0" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-300 dark:border-amber-800/60">
                                <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                                {{ totalAllPending }} berkas menunggu persetujuan
                            </span>
                        </div>

                        <div class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 px-3 py-1 rounded-xl border border-emerald-200 dark:border-emerald-900/60">
                            <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                            </svg>
                            <span>Struktur: Kelas &rarr; Guru &rarr; Jenis Berkas &rarr; Dokumen</span>
                        </div>
                    </div>

                    <!-- Scrollable Horizontal Tab Pills -->
                    <div class="flex items-center gap-2 overflow-x-auto pb-1.5 pt-1 scrollbar-thin">
                        <!-- Tab: Semua Kelas -->
                        <button
                            type="button"
                            @click="selectClassTab('all')"
                            :class="[
                                'flex items-center gap-2 px-4 py-2.5 rounded-2xl text-xs font-bold transition-all shrink-0 cursor-pointer border',
                                filterGradeLevel === 'all'
                                    ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm shadow-emerald-600/30 ring-2 ring-emerald-500/20'
                                    : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:border-emerald-300 dark:hover:border-emerald-700 hover:bg-slate-50 dark:hover:bg-slate-800'
                            ]"
                        >
                            <span>Semua Kelas</span>
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded-full text-[10px] font-extrabold',
                                    filterGradeLevel === 'all'
                                        ? 'bg-white/20 text-white'
                                        : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'
                                ]"
                            >
                                {{ totalAll }}
                            </span>
                            <span
                                v-if="totalAllPending > 0"
                                class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-amber-400 text-amber-950 animate-pulse"
                                title="Berkas menunggu persetujuan di semua kelas"
                            >
                                {{ totalAllPending }} Menunggu
                            </span>
                        </button>

                        <!-- Tab Tiap Kelas -->
                        <button
                            v-for="cls in classesList"
                            :key="cls"
                            type="button"
                            @click="selectClassTab(cls)"
                            :class="[
                                'flex items-center gap-2 px-4 py-2.5 rounded-2xl text-xs font-bold transition-all shrink-0 cursor-pointer border',
                                filterGradeLevel === cls
                                    ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm shadow-emerald-600/30 ring-2 ring-emerald-500/20'
                                    : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:border-emerald-300 dark:hover:border-emerald-700 hover:bg-slate-50 dark:hover:bg-slate-800'
                            ]"
                        >
                            <span>{{ cls }}</span>
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded-full text-[10px] font-extrabold',
                                    filterGradeLevel === cls
                                        ? 'bg-white/20 text-white'
                                        : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'
                                ]"
                            >
                                {{ classStats[cls]?.total || 0 }}
                            </span>
                            <span
                                v-if="classStats[cls]?.pending > 0"
                                class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-amber-400 text-amber-950 animate-pulse"
                                :title="'Berkas menunggu persetujuan pada ' + cls"
                            >
                                {{ classStats[cls].pending }} Menunggu
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Table Container -->
                <div class="rounded-3xl bg-surface border border-theme shadow-xs overflow-hidden">
                    <div class="p-5 border-b border-subtle flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="font-bold text-base text-slate-800 dark:text-slate-100">
                                    {{ filterGradeLevel === 'all' ? 'Tabel Seluruh Pengajuan Berkas Guru' : 'Tabel Pengajuan Berkas Guru - ' + filterGradeLevel }}
                                </h2>
                                <span class="px-2.5 py-0.5 rounded-lg text-[11px] font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60">
                                    Tab {{ filterGradeLevel === 'all' ? 'Semua Kelas' : filterGradeLevel }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Struktur tampilan: <strong>Kelas &rarr; Guru &rarr; Jenis Administrasi &rarr; Judul/Bulan Berkas</strong>
                            </p>
                        </div>

                        <div class="flex items-center gap-3 self-start sm:self-auto flex-wrap">
                            <!-- View Mode Switcher -->
                            <div class="inline-flex p-1 rounded-xl bg-slate-100 dark:bg-slate-800 border border-subtle">
                                <button
                                    type="button"
                                    @click="viewMode = 'hierarchy'"
                                    :class="[
                                        'px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5',
                                        viewMode === 'hierarchy'
                                            ? 'bg-white dark:bg-slate-900 text-emerald-600 dark:text-emerald-400 shadow-2xs font-extrabold'
                                            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-200'
                                    ]"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                    </svg>
                                    <span>Hirarki Guru</span>
                                </button>
                                <button
                                    type="button"
                                    @click="viewMode = 'flat'"
                                    :class="[
                                        'px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5',
                                        viewMode === 'flat'
                                            ? 'bg-white dark:bg-slate-900 text-emerald-600 dark:text-emerald-400 shadow-2xs font-extrabold'
                                            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-200'
                                    ]"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <span>Tabel Rinci</span>
                                </button>
                            </div>

                            <span class="text-xs font-bold text-slate-600 dark:text-slate-300 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 border border-subtle">
                                {{ viewMode === 'hierarchy' ? teacherGroups.reduce((acc, t) => acc + t.total_docs, 0) : (documents.total || documents.data.length) }} Dokumen
                            </span>
                        </div>
                    </div>

                    <!-- ══════════════════════════════════════════════════════ -->
                    <!-- ── VIEW MODE 1: TABEL HIRARKI GURU (DEFAULT) ───────── -->
                    <!-- ══════════════════════════════════════════════════════ -->
                    <div v-if="viewMode === 'hierarchy'">
                        <div v-if="teacherGroups.length === 0" class="p-12 text-center text-slate-400">
                            <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <p class="font-bold text-sm text-slate-600 dark:text-slate-300">Belum Ada Berkas Administrasi</p>
                            <p class="text-xs text-slate-400 mt-1">
                                Belum ada berkas pada {{ filterGradeLevel === 'all' ? 'periode ini' : filterGradeLevel }}.
                            </p>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-left text-xs md:text-sm border-collapse">
                                <thead>
                                    <tr class="bg-slate-50/80 dark:bg-slate-900/60 border-b border-subtle text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wider font-bold">
                                        <th class="py-3.5 px-4 md:px-6 w-[23%]">Nama Guru</th>
                                        <th class="py-3.5 px-4 w-[20%]">Jenis Administrasi</th>
                                        <th class="py-3.5 px-4">Judul / Bulan Berkas</th>
                                        <th class="py-3.5 px-4">Mata Pelajaran</th>
                                        <th class="py-3.5 px-4">Status Telaah</th>
                                        <th class="py-3.5 px-4 md:px-6 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-subtle">
                                    <template v-for="teacher in teacherGroups" :key="teacher.user_id">
                                        <template v-for="(category, catIndex) in teacher.categories" :key="category.category_key">
                                            <tr
                                                v-for="(doc, docIndex) in category.docs"
                                                :key="doc.id"
                                                :class="[
                                                    'transition-colors',
                                                    doc.status === 'menunggu_verifikasi'
                                                        ? 'bg-amber-50/50 dark:bg-amber-950/20 hover:bg-amber-100/60 dark:hover:bg-amber-900/30'
                                                        : 'hover:bg-slate-50/60 dark:hover:bg-slate-800/40',
                                                    catIndex > 0 && docIndex === 0 ? 'border-t border-dashed border-slate-200 dark:border-slate-800' : 'border-t border-subtle'
                                                ]"
                                            >
                                                <!-- Kolom Guru: hanya muncul di baris pertama Guru -->
                                                <td
                                                    v-if="catIndex === 0 && docIndex === 0"
                                                    :rowspan="teacher.categories.reduce((acc, c) => acc + c.docs.length, 0)"
                                                    class="py-4 px-4 md:px-6 align-top bg-white/70 dark:bg-slate-900/70 border-r border-subtle"
                                                >
                                                    <div class="space-y-1.5 sticky top-2">
                                                        <div class="flex items-center gap-2.5">
                                                            <div class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 font-black flex items-center justify-center shrink-0 text-xs shadow-2xs">
                                                                {{ teacher.name.charAt(0).toUpperCase() }}
                                                            </div>
                                                            <div>
                                                                <div class="font-extrabold text-slate-800 dark:text-slate-100 text-sm leading-tight">
                                                                    {{ teacher.name }}
                                                                </div>
                                                                <div class="text-[11px] text-slate-400">
                                                                    {{ teacher.divisi || 'Guru' }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div v-if="teacher.nip" class="text-[11px] text-slate-400 font-mono">
                                                            NIP: {{ teacher.nip }}
                                                        </div>
                                                        <div class="flex items-center gap-1.5 flex-wrap pt-1">
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-subtle">
                                                                {{ teacher.total_docs }} Berkas
                                                            </span>
                                                            <span
                                                                v-if="teacher.pending_count > 0"
                                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-black bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300 border border-amber-300 dark:border-amber-800/60 animate-pulse"
                                                            >
                                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                                                {{ teacher.pending_count }} Menunggu
                                                            </span>
                                                        </div>
                                                    </div>
                                                </td>

                                                <!-- Kolom Jenis Administrasi: hanya muncul di baris pertama Kategori -->
                                                <td
                                                    v-if="docIndex === 0"
                                                    :rowspan="category.docs.length"
                                                    class="py-4 px-4 align-top bg-slate-50/50 dark:bg-slate-900/40 border-r border-subtle"
                                                >
                                                    <div class="space-y-1">
                                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 shadow-2xs">
                                                            <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                            </svg>
                                                            <span>{{ category.category_label }}</span>
                                                        </div>
                                                        <div class="text-[11px] text-slate-400 pl-1 font-semibold">
                                                            {{ category.total_docs }} berkas
                                                            <span v-if="category.pending_count > 0" class="text-amber-600 dark:text-amber-400 font-bold">
                                                                &bull; {{ category.pending_count }} menunggu
                                                            </span>
                                                        </div>
                                                    </div>
                                                </td>

                                                <!-- Kolom Judul / Bulan Berkas -->
                                                <td class="py-3.5 px-4">
                                                    <div class="space-y-1">
                                                        <div class="flex items-center gap-2">
                                                            <span
                                                                :class="[
                                                                    'w-6 h-6 rounded-lg text-[10px] font-black flex items-center justify-center shrink-0 uppercase border',
                                                                    getFileIconColor(doc.file_extension)
                                                                ]"
                                                            >
                                                                {{ doc.file_extension || 'FILE' }}
                                                            </span>
                                                            <span class="font-bold text-slate-800 dark:text-slate-100 text-xs md:text-sm">
                                                                {{ doc.title }}
                                                            </span>
                                                        </div>
                                                        <div class="text-[11px] text-slate-400 pl-8 flex items-center gap-2 flex-wrap">
                                                            <span>{{ doc.file_name }}</span>
                                                            <span>&bull;</span>
                                                            <span>{{ doc.formatted_file_size }}</span>
                                                            <span v-if="filterGradeLevel === 'all' && doc.grade_level" class="px-1.5 py-0.2 rounded bg-slate-100 dark:bg-slate-800 font-semibold text-slate-600 dark:text-slate-300">
                                                                {{ doc.grade_level }}
                                                            </span>
                                                        </div>
                                                        <p v-if="doc.description" class="text-[11px] text-slate-500 dark:text-slate-400 pl-8 italic line-clamp-1">
                                                            "{{ doc.description }}"
                                                        </p>
                                                    </div>
                                                </td>

                                                <!-- Kolom Mata Pelajaran -->
                                                <td class="py-3.5 px-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                                        {{ doc.subject || 'Semua Mapel / Umum' }}
                                                    </span>
                                                </td>

                                                <!-- Kolom Status Telaah -->
                                                <td class="py-3.5 px-4 whitespace-nowrap">
                                                    <div class="space-y-1">
                                                        <span
                                                            :class="[
                                                                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border',
                                                                getStatusBadge(doc.status).bg
                                                            ]"
                                                        >
                                                            <span :class="['w-1.5 h-1.5 rounded-full', getStatusBadge(doc.status).dot]"></span>
                                                            {{ getStatusBadge(doc.status).label }}
                                                        </span>
                                                        <p v-if="doc.feedback" class="text-[11px] text-rose-600 dark:text-rose-400 max-w-xs line-clamp-2">
                                                            <span class="font-bold">Catatan:</span> {{ doc.feedback }}
                                                        </p>
                                                    </div>
                                                </td>

                                                <!-- Kolom Aksi -->
                                                <td class="py-3.5 px-4 md:px-6 text-right whitespace-nowrap">
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        <!-- Tombol Telaah -->
                                                        <button
                                                            @click="openReviewModal(doc)"
                                                            class="px-2.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition-all active:scale-95 cursor-pointer flex items-center gap-1"
                                                            :title="'Telaah berkas ' + doc.title"
                                                        >
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                            <span>{{ doc.status === 'menunggu_verifikasi' ? 'Telaah' : 'Ubah' }}</span>
                                                        </button>

                                                        <!-- Tombol Pratinjau -->
                                                        <a
                                                            :href="route('teacher-administrations.preview', doc.id)"
                                                            target="_blank"
                                                            class="p-1.5 rounded-xl text-slate-500 hover:text-emerald-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                                            title="Pratinjau Berkas"
                                                        >
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                            </svg>
                                                        </a>

                                                        <!-- Tombol Unduh -->
                                                        <a
                                                            :href="route('teacher-administrations.download', doc.id)"
                                                            class="p-1.5 rounded-xl text-slate-500 hover:text-teal-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                                            title="Unduh Berkas"
                                                        >
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                            </svg>
                                                        </a>

                                                        <!-- Tombol Hapus -->
                                                        <button
                                                            @click="deleteDocument(doc)"
                                                            class="p-1.5 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors cursor-pointer"
                                                            title="Hapus Berkas"
                                                        >
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ══════════════════════════════════════════════════════ -->
                    <!-- ── VIEW MODE 2: TABEL RINCI (FLAT LIST) ────────────── -->
                    <!-- ══════════════════════════════════════════════════════ -->
                    <div v-else>
                        <div v-if="documents.data.length === 0" class="p-12 text-center text-slate-400">
                            Belum ada berkas pada tab kelas ini.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-left text-xs md:text-sm border-collapse">
                                <thead>
                                    <tr class="bg-slate-50/80 dark:bg-slate-900/60 border-b border-subtle text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wider font-bold">
                                        <th class="py-3.5 px-4 md:px-6">Nama Guru</th>
                                        <th class="py-3.5 px-4">Dokumen</th>
                                        <th class="py-3.5 px-4">Kategori &amp; Mapel</th>
                                        <th class="py-3.5 px-4">Kelas &amp; Periode</th>
                                        <th class="py-3.5 px-4">Status Telaah</th>
                                        <th class="py-3.5 px-4 md:px-6 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-subtle">
                                    <tr
                                        v-for="doc in documents.data"
                                        :key="doc.id"
                                        :class="[
                                            'transition-colors',
                                            doc.status === 'menunggu_verifikasi'
                                                ? 'bg-amber-50/60 dark:bg-amber-950/20 hover:bg-amber-100/70 dark:hover:bg-amber-900/30 border-l-4 border-l-amber-500'
                                                : 'hover:bg-slate-50/60 dark:hover:bg-slate-800/40'
                                        ]"
                                    >
                                        <!-- Kolom Guru -->
                                        <td class="py-4 px-4 md:px-6 whitespace-nowrap">
                                            <div class="space-y-0.5">
                                                <p class="font-bold text-slate-800 dark:text-slate-100">{{ doc.user?.name || '-' }}</p>
                                                <p class="text-[11px] text-slate-400">{{ doc.user?.divisi || 'Guru SDAM' }}</p>
                                            </div>
                                        </td>

                                        <!-- Kolom Dokumen -->
                                        <td class="py-4 px-4">
                                            <div class="flex items-start gap-3">
                                                <div
                                                    class="w-9 h-9 rounded-xl border flex items-center justify-center font-bold text-xs uppercase shrink-0 shadow-2xs mt-0.5"
                                                    :class="getFileIconColor(doc.file_extension)"
                                                >
                                                    {{ doc.file_extension || 'FILE' }}
                                                </div>
                                                <div class="space-y-0.5 min-w-0">
                                                    <p class="font-bold text-slate-800 dark:text-slate-100 truncate max-w-xs md:max-w-sm">
                                                        {{ doc.title }}
                                                    </p>
                                                    <p class="text-[11px] text-slate-400 truncate max-w-xs md:max-w-sm">
                                                        {{ doc.file_name }} &bull; {{ doc.formatted_file_size }}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Kolom Kategori & Mapel -->
                                        <td class="py-4 px-4 whitespace-nowrap">
                                            <div class="space-y-1">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60">
                                                    {{ doc.category_label }}
                                                </span>
                                                <p class="text-[11px] text-slate-500">
                                                    {{ doc.grade_level ? doc.grade_level + ' • ' : '' }}{{ doc.subject || 'Umum' }}
                                                </p>
                                            </div>
                                        </td>

                                        <!-- Kolom Periode -->
                                        <td class="py-4 px-4 whitespace-nowrap">
                                            <div class="text-xs space-y-0.5">
                                                <p class="font-semibold text-slate-800 dark:text-slate-200">{{ doc.academic_year }}</p>
                                                <span class="inline-block text-[10px] uppercase font-bold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                                    Sem. {{ doc.semester }}
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Kolom Status Telaah -->
                                        <td class="py-4 px-4 whitespace-nowrap">
                                            <div class="space-y-1">
                                                <span
                                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold border"
                                                    :class="getStatusBadge(doc.status).bg"
                                                >
                                                    <span class="w-1.5 h-1.5 rounded-full" :class="getStatusBadge(doc.status).dot"></span>
                                                    {{ getStatusBadge(doc.status).label }}
                                                </span>
                                                <p v-if="doc.reviewer" class="text-[10px] text-slate-400">
                                                    Oleh: {{ doc.reviewer.name }}
                                                </p>
                                            </div>
                                        </td>

                                        <!-- Kolom Aksi -->
                                        <td class="py-4 px-4 md:px-6 text-right whitespace-nowrap">
                                            <div class="inline-flex items-center gap-1">
                                                <!-- Tombol Telaah -->
                                                <button
                                                    @click="openReviewModal(doc)"
                                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition-all active:scale-95 cursor-pointer mr-1"
                                                >
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <span>Telaah</span>
                                                </button>

                                                <!-- Pratinjau -->
                                                <a
                                                    :href="route('teacher-administrations.preview', doc.id)"
                                                    target="_blank"
                                                    title="Pratinjau Berkas"
                                                    class="p-2 rounded-xl text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-slate-800 transition-colors"
                                                >
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                </a>

                                                <!-- Unduh -->
                                                <a
                                                    :href="route('teacher-administrations.download', doc.id)"
                                                    title="Unduh Berkas"
                                                    class="p-2 rounded-xl text-slate-500 hover:text-teal-600 hover:bg-teal-50 dark:hover:bg-slate-800 transition-colors"
                                                >
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                    </svg>
                                                </a>

                                                <!-- Hapus -->
                                                <button
                                                    @click="deleteDocument(doc)"
                                                    title="Hapus Dokumen"
                                                    class="p-2 rounded-xl text-slate-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                                                >
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="documents.links && documents.links.length > 3" class="p-4 border-t border-subtle flex items-center justify-between">
                            <p class="text-xs text-slate-500">
                                Menampilkan {{ documents.from }} s.d. {{ documents.to }} dari {{ documents.total }} berkas
                            </p>
                            <div class="flex items-center gap-1">
                                <template v-for="(link, i) in documents.links" :key="i">
                                    <span
                                        v-if="!link.url"
                                        class="px-3 py-1.5 rounded-lg text-xs text-slate-400"
                                        v-html="link.label"
                                    />
                                    <button
                                        v-else
                                        @click="router.get(link.url, {}, { preserveState: true, preserveScroll: true })"
                                        :class="[
                                            'px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer',
                                            link.active
                                                ? 'bg-emerald-600 text-white'
                                                : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                                        ]"
                                        v-html="link.label"
                                    />
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════ -->
            <!-- ── TAB 2: MATRIKS KELENGKAPAN BERKAS GURU ──────────── -->
            <!-- ══════════════════════════════════════════════════════ -->
            <div v-show="activeTab === 'matrix'" class="rounded-3xl bg-surface border border-theme shadow-xs overflow-hidden">
                <div class="p-5 border-b border-subtle flex items-center justify-between">
                    <div>
                        <h2 class="font-bold text-base text-slate-800 dark:text-slate-100">Matriks Kelengkapan Berkas Guru</h2>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Ceklis pemenuhan berkas untuk Tahun Ajaran <strong>{{ filterYear }}</strong> - Semester <strong>{{ filterSemester }}</strong>
                        </p>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="inline-flex items-center gap-1.5 text-emerald-600 font-semibold">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Disetujui
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-amber-500 font-semibold">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Menunggu Telaah
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-rose-500 font-semibold">
                            <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Perlu Revisi
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-slate-400">
                            <span class="w-2.5 h-2.5 rounded-full bg-slate-300 dark:bg-slate-700"></span> Belum Unggah
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 dark:bg-slate-900/60 border-b border-subtle text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wider font-bold">
                                <th class="py-3 px-4 sticky left-0 bg-slate-50 dark:bg-slate-900 z-10">Nama Guru</th>
                                <th
                                    v-for="(label, catKey) in categories"
                                    :key="catKey"
                                    class="py-3 px-3 text-center whitespace-nowrap"
                                >
                                    {{ label.split(' ')[0] }}
                                </th>
                                <th class="py-3 px-4 text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-subtle">
                            <tr
                                v-for="row in matrix"
                                :key="row.user_id"
                                class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors"
                            >
                                <td class="py-3 px-4 font-bold text-slate-800 dark:text-slate-200 whitespace-nowrap sticky left-0 bg-surface z-10 shadow-2xs">
                                    {{ row.name }}
                                </td>

                                <td
                                    v-for="(label, catKey) in categories"
                                    :key="catKey"
                                    class="py-3 px-3 text-center"
                                >
                                    <template v-if="row.categories[catKey]">
                                        <!-- Disetujui -->
                                        <span
                                            v-if="row.categories[catKey].status === 'disetujui'"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400"
                                            :title="row.categories[catKey].title + ' (Disetujui)'"
                                        >
                                            &check;
                                        </span>
                                        <!-- Perlu Revisi -->
                                        <span
                                            v-else-if="row.categories[catKey].status === 'perlu_revisi'"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 font-bold"
                                            :title="row.categories[catKey].title + ' (Perlu Revisi)'"
                                        >
                                            !
                                        </span>
                                        <!-- Menunggu Telaah -->
                                        <span
                                            v-else
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 font-bold"
                                            :title="row.categories[catKey].title + ' (Menunggu Telaah)'"
                                        >
                                            &bull;
                                        </span>
                                    </template>
                                    <template v-else>
                                        <span class="text-slate-300 dark:text-slate-700">&mdash;</span>
                                    </template>
                                </td>

                                <td class="py-3 px-4 text-center font-bold text-slate-800 dark:text-slate-100 whitespace-nowrap">
                                    {{ row.total_docs }} Berkas
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- ── Modal Telaah & Verifikasi Dokumen ─────────────────── -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <div
            v-if="showReviewModal && selectedDocForReview"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto"
        >
            <div class="w-full max-w-lg rounded-3xl bg-surface border border-theme shadow-2xl p-6 md:p-8 space-y-6 my-8 animate-in fade-in zoom-in-95 duration-200">
                <div class="flex items-center justify-between border-b border-subtle pb-4">
                    <div class="space-y-0.5">
                        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">Telaah Administrasi Guru</h3>
                        <p class="text-xs text-slate-400">Verifikasi kelayakan berkas dan berikan feedback jika diperlukan</p>
                    </div>
                    <button
                        @click="showReviewModal = false"
                        class="p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Info Berkas -->
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 space-y-2">
                    <div>
                        <span class="text-[11px] font-semibold text-slate-400 uppercase">Guru Pengunggah:</span>
                        <p class="font-bold text-sm text-slate-800 dark:text-slate-100">{{ selectedDocForReview.user?.name }} ({{ selectedDocForReview.user?.divisi || 'Guru' }})</p>
                    </div>
                    <div>
                        <span class="text-[11px] font-semibold text-slate-400 uppercase">Judul &amp; Kategori:</span>
                        <p class="font-semibold text-xs text-slate-700 dark:text-slate-300">{{ selectedDocForReview.title }} &bull; {{ selectedDocForReview.category_label }}</p>
                    </div>
                    <div class="flex items-center justify-between text-xs text-slate-500 pt-2 border-t border-subtle">
                        <span>{{ selectedDocForReview.file_name }} ({{ selectedDocForReview.formatted_file_size }})</span>
                        <div class="flex items-center gap-2">
                            <a
                                :href="route('teacher-administrations.preview', selectedDocForReview.id)"
                                target="_blank"
                                class="text-emerald-600 font-bold hover:underline"
                            >
                                Pratinjau
                            </a>
                            <span>&bull;</span>
                            <a
                                :href="route('teacher-administrations.download', selectedDocForReview.id)"
                                class="text-teal-600 font-bold hover:underline"
                            >
                                Unduh
                            </a>
                        </div>
                    </div>
                </div>

                <form @submit.prevent="submitReview" class="space-y-4">
                    <!-- Pilihan Status -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-2">Keputusan Telaah <span class="text-rose-500">*</span></label>
                        <div class="grid grid-cols-2 gap-3">
                            <label
                                :class="[
                                    'flex items-center gap-2.5 p-3 rounded-2xl border-2 cursor-pointer transition-all',
                                    reviewForm.status === 'disetujui'
                                        ? 'border-emerald-500 bg-emerald-50/60 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 font-bold'
                                        : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400'
                                ]"
                            >
                                <input type="radio" value="disetujui" v-model="reviewForm.status" class="sr-only" />
                                <span class="w-4 h-4 rounded-full border-2 border-emerald-500 flex items-center justify-center shrink-0">
                                    <span v-if="reviewForm.status === 'disetujui'" class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                </span>
                                <span class="text-xs">Disetujui</span>
                            </label>

                            <label
                                :class="[
                                    'flex items-center gap-2.5 p-3 rounded-2xl border-2 cursor-pointer transition-all',
                                    reviewForm.status === 'perlu_revisi'
                                        ? 'border-rose-500 bg-rose-50/60 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 font-bold'
                                        : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400'
                                ]"
                            >
                                <input type="radio" value="perlu_revisi" v-model="reviewForm.status" class="sr-only" />
                                <span class="w-4 h-4 rounded-full border-2 border-rose-500 flex items-center justify-center shrink-0">
                                    <span v-if="reviewForm.status === 'perlu_revisi'" class="w-2 h-2 rounded-full bg-rose-500"></span>
                                </span>
                                <span class="text-xs">Perlu Revisi</span>
                            </label>
                        </div>
                    </div>

                    <!-- Catatan Telaah / Feedback -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">
                            Catatan Telaah / Rekomendasi Perbaikan
                            <span v-if="reviewForm.status === 'perlu_revisi'" class="text-rose-500">* (Wajib diisi jika revisi)</span>
                        </label>
                        <textarea
                            v-model="reviewForm.feedback"
                            :required="reviewForm.status === 'perlu_revisi'"
                            rows="4"
                            placeholder="Tuliskan catatan perbaikan untuk guru yang bersangkutan..."
                            class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 p-3 focus:ring-2 focus:ring-emerald-500"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-subtle">
                        <button
                            type="button"
                            @click="showReviewModal = false"
                            class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="reviewForm.processing"
                            class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md transition-all active:scale-95 cursor-pointer disabled:opacity-50"
                        >
                            <span v-if="reviewForm.processing">Menyimpan...</span>
                            <span v-else>Simpan Keputusan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
