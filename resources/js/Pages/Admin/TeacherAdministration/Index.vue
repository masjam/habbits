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

// Filter states
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
        search: searchQuery.value || null,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

const resetFilters = () => {
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
                label: 'Menunggu Telaah',
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
            <div v-show="activeTab === 'list'" class="rounded-3xl bg-surface border border-theme shadow-xs overflow-hidden">
                <div class="p-5 border-b border-subtle flex items-center justify-between">
                    <div>
                        <h2 class="font-bold text-base text-slate-800 dark:text-slate-100">Daftar Pengajuan Administrasi Guru</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Lakukan pemeriksaan berkas dan berikan persetujuan atau catatan revisi</p>
                    </div>
                    <span class="text-xs font-bold text-slate-500 px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800">
                        {{ documents.total || documents.data.length }} Dokumen
                    </span>
                </div>

                <div v-if="documents.data.length === 0" class="p-12 text-center text-slate-400">
                    Belum ada berkas yang sesuai dengan kriteria filter.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs md:text-sm border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 dark:bg-slate-900/60 border-b border-subtle text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wider font-bold">
                                <th class="py-3.5 px-4 md:px-6">Nama Guru</th>
                                <th class="py-3.5 px-4">Dokumen</th>
                                <th class="py-3.5 px-4">Kategori &amp; Mapel</th>
                                <th class="py-3.5 px-4">Periode</th>
                                <th class="py-3.5 px-4">Status Telaah</th>
                                <th class="py-3.5 px-4 md:px-6 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-subtle">
                            <tr
                                v-for="doc in documents.data"
                                :key="doc.id"
                                class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors"
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
