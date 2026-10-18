<script setup>
import { ref, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    documents: {
        type: Object,
        default: () => ({ data: [] })
    },
    filters: {
        type: Object,
        default: () => ({})
    },
    categories: {
        type: Object,
        default: () => ({})
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
    stats: {
        type: Object,
        default: () => ({ total: 0, approved: 0, pending: 0, revision: 0 })
    },
    checklistProgress: {
        type: Object,
        default: () => ({ total_mandatory: 7, completed_count: 0 })
    }
})

// Modal states
const showUploadModal = ref(false)
const showEditModal = ref(false)
const showFeedbackModal = ref(false)
const activeFeedbackDoc = ref(null)

// Filter states
const filterYear = ref(props.filters.academic_year || props.currentAcademicYear)
const filterSemester = ref(props.filters.semester || props.currentSemester)
const filterCategory = ref(props.filters.category || 'all')
const filterStatus = ref(props.filters.status || 'all')

const applyFilters = () => {
    router.get(route('teacher-administrations.index'), {
        academic_year: filterYear.value,
        semester: filterSemester.value,
        category: filterCategory.value === 'all' ? null : filterCategory.value,
        status: filterStatus.value === 'all' ? null : filterStatus.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

// Upload Form
const form = useForm({
    academic_year: props.currentAcademicYear,
    semester: props.currentSemester,
    grade_level: '',
    subject: '',
    category: '',
    title: '',
    description: '',
    file: null,
})

const selectedFileName = ref('')
const selectedFileSize = ref('')

const handleFileSelect = (event) => {
    const file = event.target.files[0]
    if (file) {
        form.file = file
        selectedFileName.value = file.name
        selectedFileSize.value = (file.size / (1024 * 1024)).toFixed(2) + ' MB'
    }
}

const handleDrop = (event) => {
    const file = event.dataTransfer.files[0]
    if (file) {
        form.file = file
        selectedFileName.value = file.name
        selectedFileSize.value = (file.size / (1024 * 1024)).toFixed(2) + ' MB'
    }
}

const submitUpload = () => {
    form.post(route('teacher-administrations.store'), {
        onSuccess: () => {
            showUploadModal.value = false
            form.reset()
            selectedFileName.value = ''
            selectedFileSize.value = ''
        }
    })
}

// Edit / Revision Form
const editForm = useForm({
    id: null,
    grade_level: '',
    subject: '',
    category: '',
    title: '',
    description: '',
    file: null,
})
const editSelectedFileName = ref('')

const openEditModal = (doc) => {
    editForm.id = doc.id
    editForm.grade_level = doc.grade_level || ''
    editForm.subject = doc.subject || ''
    editForm.category = doc.category
    editForm.title = doc.title
    editForm.description = doc.description || ''
    editForm.file = null
    editSelectedFileName.value = ''
    showEditModal.value = true
}

const handleEditFileSelect = (event) => {
    const file = event.target.files[0]
    if (file) {
        editForm.file = file
        editSelectedFileName.value = file.name + ' (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB)'
    }
}

const submitEdit = () => {
    editForm.post(route('teacher-administrations.update', editForm.id), {
        onSuccess: () => {
            showEditModal.value = false
            editForm.reset()
            editSelectedFileName.value = ''
        }
    })
}

// Delete Action
const deleteDocument = (doc) => {
    if (confirm(`Apakah Anda yakin ingin menghapus berkas "${doc.title}"?`)) {
        router.delete(route('teacher-administrations.destroy', doc.id), {
            preserveScroll: true
        })
    }
}

// Feedback Modal Action
const openFeedback = (doc) => {
    activeFeedbackDoc.value = doc
    showFeedbackModal.value = true
}

// Helper: Status label & styling
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

// File icon helper
const getFileIconColor = (ext) => {
    const extension = (ext || '').toLowerCase()
    if (extension === 'pdf') return 'text-rose-500 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800/50'
    if (['doc', 'docx'].includes(extension)) return 'text-blue-500 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/40 border-blue-200 dark:border-blue-800/50'
    if (['xls', 'xlsx'].includes(extension)) return 'text-emerald-500 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800/50'
    if (['ppt', 'pptx'].includes(extension)) return 'text-orange-500 dark:text-orange-400 bg-orange-50 dark:bg-orange-950/40 border-orange-200 dark:border-orange-800/50'
    return 'text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700'
}

// Progress percentage
const progressPercent = computed(() => {
    if (!props.checklistProgress.total_mandatory) return 0
    return Math.min(100, Math.round((props.checklistProgress.completed_count / props.checklistProgress.total_mandatory) * 100))
})
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Administrasi Guru - Sahabat SDAM" />

        <div class="space-y-6 pb-12">
            <!-- ── Header Banner ─────────────────────────────────── -->
            <div class="relative overflow-hidden rounded-3xl bg-linear-to-br from-emerald-600 via-teal-700 to-emerald-800 p-6 md:p-8 text-white shadow-lg">
                <div class="absolute -right-10 -bottom-10 w-64 h-64 rounded-full bg-white/5 blur-2xl pointer-events-none"></div>
                <div class="absolute right-20 -top-10 w-48 h-48 rounded-full bg-emerald-400/10 blur-xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="space-y-2 max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 backdrop-blur-md text-emerald-100 text-xs font-semibold tracking-wide border border-white/10">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Perangkat Ajar &amp; Pembelajaran SDAM</span>
                        </div>
                        <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-white drop-shadow-xs">
                            Administrasi Guru
                        </h1>
                        <p class="text-sm md:text-base text-emerald-100/90 leading-relaxed">
                            Pusat pengunggahan dan arsip perangkat ajar (Modul Ajar, RPP, Prota, Promes, ATP, KKTP). Pastikan seluruh administrasi semester ini terpenuhi.
                        </p>
                    </div>

                    <button
                        @click="showUploadModal = true"
                        class="inline-flex items-center justify-center gap-2.5 px-5 py-3 rounded-2xl bg-white text-emerald-800 hover:bg-emerald-50 active:scale-95 font-bold text-sm shadow-md transition-all cursor-pointer shrink-0"
                    >
                        <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Unggah Berkas Baru</span>
                    </button>
                </div>
            </div>

            <!-- ── Alert: Catatan Revisi dari Kepala Sekolah ───── -->
            <div
                v-if="stats.revision > 0"
                class="flex items-start gap-3.5 p-4 md:p-5 rounded-2xl bg-rose-50/90 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/60 text-rose-800 dark:text-rose-300 shadow-xs"
            >
                <div class="w-9 h-9 rounded-xl bg-rose-100 dark:bg-rose-900/60 flex items-center justify-center text-rose-600 shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="space-y-1">
                    <h3 class="font-bold text-sm">Perhatian: Ada Berkas Memerlukan Revisi</h3>
                    <p class="text-xs md:text-sm text-rose-700 dark:text-rose-400">
                        Terdapat <strong>{{ stats.revision }} berkas</strong> yang diminta untuk direvisi oleh Kepala Sekolah atau tim kurikulum. Silakan periksa catatan telaah dan unggah berkas perbaikan.
                    </p>
                </div>
            </div>

            <!-- ── Stats Grid & Checklist Progres ───────────────── -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Card 1: Checklist Progres Wajib -->
                <div class="p-5 rounded-3xl bg-surface border border-theme shadow-xs space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Kelengkapan Dokumen</span>
                        <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">{{ progressPercent }}%</span>
                    </div>
                    <div>
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ checklistProgress.completed_count }}</span>
                            <span class="text-xs text-slate-400">/ {{ checklistProgress.total_mandatory }} Berkas Wajib</span>
                        </div>
                    </div>
                    <div class="w-full bg-slate-100 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
                        <div
                            class="bg-linear-to-r from-emerald-500 to-teal-500 h-full rounded-full transition-all duration-500"
                            :style="{ width: progressPercent + '%' }"
                        ></div>
                    </div>
                </div>

                <!-- Card 2: Disetujui -->
                <div class="p-5 rounded-3xl bg-surface border border-theme shadow-xs flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800/60 flex items-center justify-center text-emerald-600 shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Telah Disetujui</p>
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100 mt-0.5">{{ stats.approved }}</p>
                    </div>
                </div>

                <!-- Card 3: Menunggu Telaah -->
                <div class="p-5 rounded-3xl bg-surface border border-theme shadow-xs flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800/60 flex items-center justify-center text-amber-600 shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Menunggu Telaah</p>
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100 mt-0.5">{{ stats.pending }}</p>
                    </div>
                </div>

                <!-- Card 4: Perlu Revisi -->
                <div class="p-5 rounded-3xl bg-surface border border-theme shadow-xs flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800/60 flex items-center justify-center text-rose-600 shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Perlu Revisi</p>
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100 mt-0.5">{{ stats.revision }}</p>
                    </div>
                </div>
            </div>

            <!-- ── Filter & Search Bar ──────────────────────────── -->
            <div class="p-4 md:p-5 rounded-3xl bg-surface border border-theme shadow-xs space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <!-- Filter Tahun Ajaran -->
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

                    <!-- Filter Semester -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Semester</label>
                        <select
                            v-model="filterSemester"
                            @change="applyFilters"
                            class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500"
                        >
                            <option value="all">Semua Semester</option>
                            <option value="ganjil">Ganjil</option>
                            <option value="genap">Genap</option>
                        </select>
                    </div>

                    <!-- Filter Kategori -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Kategori Berkas</label>
                        <select
                            v-model="filterCategory"
                            @change="applyFilters"
                            class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500"
                        >
                            <option value="all">Semua Kategori</option>
                            <option v-for="(lbl, key) in categories" :key="key" :value="key">{{ lbl }}</option>
                        </select>
                    </div>

                    <!-- Filter Status -->
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
            </div>

            <!-- ── Document Table / List ────────────────────────── -->
            <div class="rounded-3xl bg-surface border border-theme shadow-xs overflow-hidden">
                <div class="p-5 border-b border-subtle flex items-center justify-between">
                    <div>
                        <h2 class="font-bold text-base text-slate-800 dark:text-slate-100">Daftar Berkas Pembelajaran</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Menampilkan berkas yang diunggah untuk periode yang dipilih</p>
                    </div>
                    <span class="text-xs font-bold text-slate-500 px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800">
                        {{ documents.total || documents.data.length }} Dokumen
                    </span>
                </div>

                <div v-if="documents.data.length === 0" class="p-12 text-center space-y-4">
                    <div class="w-16 h-16 rounded-3xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 flex items-center justify-center text-emerald-600 mx-auto">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div class="max-w-md mx-auto">
                        <h3 class="font-bold text-base text-slate-800 dark:text-slate-200">Belum Ada Berkas Ditemukan</h3>
                        <p class="text-xs md:text-sm text-slate-400 mt-1">
                            Anda belum mengunggah dokumen administrasi untuk kriteria filter ini. Silakan klik tombol "Unggah Berkas Baru".
                        </p>
                    </div>
                    <button
                        @click="showUploadModal = true"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs transition-colors cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Unggah Sekarang</span>
                    </button>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs md:text-sm border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 dark:bg-slate-900/60 border-b border-subtle text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wider font-bold">
                                <th class="py-3.5 px-4 md:px-6">Dokumen</th>
                                <th class="py-3.5 px-4">Kategori</th>
                                <th class="py-3.5 px-4">Tingkat &amp; Mapel</th>
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
                                <!-- Kolom Dokumen -->
                                <td class="py-4 px-4 md:px-6">
                                    <div class="flex items-start gap-3">
                                        <div
                                            class="w-10 h-10 rounded-xl border flex items-center justify-center font-bold text-xs uppercase shrink-0 shadow-2xs mt-0.5"
                                            :class="getFileIconColor(doc.file_extension)"
                                        >
                                            {{ doc.file_extension || 'FILE' }}
                                        </div>
                                        <div class="space-y-0.5 min-w-0">
                                            <p class="font-bold text-slate-800 dark:text-slate-100 truncate max-w-xs md:max-w-md">
                                                {{ doc.title }}
                                            </p>
                                            <p class="text-[11px] text-slate-400 truncate max-w-xs md:max-w-md">
                                                {{ doc.file_name }} &bull; {{ doc.formatted_file_size }}
                                            </p>
                                            <p v-if="doc.description" class="text-[11px] text-slate-500 dark:text-slate-400 italic line-clamp-1 mt-0.5">
                                                "{{ doc.description }}"
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Kolom Kategori -->
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60">
                                        {{ doc.category_label }}
                                    </span>
                                </td>

                                <!-- Kolom Tingkat & Mapel -->
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <div class="text-xs space-y-0.5">
                                        <p class="font-medium text-slate-700 dark:text-slate-300">
                                            {{ doc.grade_level || 'Semua Tingkat' }}
                                        </p>
                                        <p class="text-[11px] text-slate-400">
                                            {{ doc.subject || 'Umum / Tematik' }}
                                        </p>
                                    </div>
                                </td>

                                <!-- Kolom Periode -->
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <div class="text-xs space-y-0.5">
                                        <p class="font-semibold text-slate-800 dark:text-slate-200">{{ doc.academic_year }}</p>
                                        <span class="inline-block text-[10px] uppercase font-bold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                            Semester {{ doc.semester }}
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

                                        <button
                                            v-if="doc.feedback"
                                            @click="openFeedback(doc)"
                                            class="block text-[11px] font-semibold text-rose-600 dark:text-rose-400 hover:underline cursor-pointer"
                                        >
                                            Lihat Catatan Telaah &rarr;
                                        </button>
                                    </div>
                                </td>

                                <!-- Kolom Aksi -->
                                <td class="py-4 px-4 md:px-6 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1">
                                        <!-- Pratinjau (khusus PDF atau file didukung) -->
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

                                        <!-- Edit / Revisi -->
                                        <button
                                            @click="openEditModal(doc)"
                                            title="Edit / Ganti Berkas Revisi"
                                            class="p-2 rounded-xl text-slate-500 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>

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

        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- ── Modal Upload Berkas Baru ──────────────────────────── -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <div
            v-if="showUploadModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto"
        >
            <div class="w-full max-w-xl rounded-3xl bg-surface border border-theme shadow-2xl p-6 md:p-8 space-y-6 my-8 animate-in fade-in zoom-in-95 duration-200">
                <div class="flex items-center justify-between border-b border-subtle pb-4">
                    <div class="space-y-0.5">
                        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">Unggah Berkas Administrasi</h3>
                        <p class="text-xs text-slate-400">Pastikan informasi kategori dan berkas terisi dengan benar</p>
                    </div>
                    <button
                        @click="showUploadModal = false"
                        class="p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitUpload" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Tahun Ajaran -->
                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Tahun Ajaran <span class="text-rose-500">*</span></label>
                            <select
                                v-model="form.academic_year"
                                required
                                class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500"
                            >
                                <option v-for="yr in yearsList" :key="yr" :value="yr">{{ yr }}</option>
                            </select>
                        </div>

                        <!-- Semester -->
                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Semester <span class="text-rose-500">*</span></label>
                            <select
                                v-model="form.semester"
                                required
                                class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500"
                            >
                                <option value="ganjil">Ganjil</option>
                                <option value="genap">Genap</option>
                            </select>
                        </div>
                    </div>

                    <!-- Kategori Berkas -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Kategori Dokumen <span class="text-rose-500">*</span></label>
                        <select
                            v-model="form.category"
                            required
                            class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500"
                        >
                            <option value="" disabled>-- Pilih Kategori Administrasi --</option>
                            <option v-for="(lbl, key) in categories" :key="key" :value="key">{{ lbl }}</option>
                        </select>
                        <p v-if="form.errors.category" class="text-[11px] text-rose-500 mt-1">{{ form.errors.category }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Tingkat / Kelas -->
                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Tingkat / Kelas</label>
                            <input
                                type="text"
                                v-model="form.grade_level"
                                placeholder="Misal: Kelas 1, Kelas 4, Umum"
                                class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500"
                            />
                        </div>

                        <!-- Mata Pelajaran -->
                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Mata Pelajaran</label>
                            <input
                                type="text"
                                v-model="form.subject"
                                placeholder="Misal: Matematika, PAI, Tematik"
                                class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500"
                            />
                        </div>
                    </div>

                    <!-- Judul Dokumen -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Judul Dokumen <span class="text-rose-500">*</span></label>
                        <input
                            type="text"
                            v-model="form.title"
                            required
                            placeholder="Misal: Modul Ajar Matematika Bab 1 Pecahan"
                            class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500"
                        />
                        <p v-if="form.errors.title" class="text-[11px] text-rose-500 mt-1">{{ form.errors.title }}</p>
                    </div>

                    <!-- Keterangan Tambahan -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Deskripsi / Catatan Tambahan (Opsional)</label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            placeholder="Catatan tambahan mengenai berkas ini..."
                            class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500"
                        ></textarea>
                    </div>

                    <!-- Upload File Drag & Drop -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">File Berkas <span class="text-rose-500">*</span></label>
                        <div
                            @dragover.prevent
                            @drop.prevent="handleDrop"
                            class="border-2 border-dashed border-slate-300 dark:border-slate-700 hover:border-emerald-500 rounded-2xl p-6 text-center cursor-pointer transition-colors bg-slate-50/50 dark:bg-slate-900/30"
                            @click="$refs.fileInput.click()"
                        >
                            <input
                                ref="fileInput"
                                type="file"
                                @change="handleFileSelect"
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar"
                                class="hidden"
                            />
                            <div class="space-y-2">
                                <div class="w-12 h-12 rounded-2xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 flex items-center justify-center mx-auto">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                </div>
                                <div v-if="!selectedFileName">
                                    <p class="text-xs font-bold text-slate-700 dark:text-slate-200">Klik untuk memilih atau seret berkas ke sini</p>
                                    <p class="text-[11px] text-slate-400 mt-0.5">Mendukung PDF, Word (.docx), Excel (.xlsx), PPT, ZIP (Maks. 5 MB)</p>
                                </div>
                                <div v-else class="space-y-0.5">
                                    <p class="text-xs font-bold text-emerald-600 dark:text-emerald-400 truncate">{{ selectedFileName }}</p>
                                    <p class="text-[11px] text-slate-400">{{ selectedFileSize }}</p>
                                </div>
                            </div>
                        </div>
                        <p v-if="form.errors.file" class="text-[11px] text-rose-500 mt-1">{{ form.errors.file }}</p>
                    </div>

                    <!-- Tombol Aksi Modal -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-subtle">
                        <button
                            type="button"
                            @click="showUploadModal = false"
                            class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md transition-all active:scale-95 cursor-pointer disabled:opacity-50"
                        >
                            <span v-if="form.processing">Mengunggah...</span>
                            <span v-else>Simpan &amp; Unggah</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- ── Modal Edit / Upload Ulang Berkas Revisi ───────────── -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <div
            v-if="showEditModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto"
        >
            <div class="w-full max-w-xl rounded-3xl bg-surface border border-theme shadow-2xl p-6 md:p-8 space-y-6 my-8 animate-in fade-in zoom-in-95 duration-200">
                <div class="flex items-center justify-between border-b border-subtle pb-4">
                    <div class="space-y-0.5">
                        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">Edit / Perbarui Berkas</h3>
                        <p class="text-xs text-slate-400">Anda dapat memperbarui metadata atau mengunggah berkas revisi baru</p>
                    </div>
                    <button
                        @click="showEditModal = false"
                        class="p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitEdit" class="space-y-4">
                    <!-- Kategori Berkas -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Kategori Dokumen <span class="text-rose-500">*</span></label>
                        <select
                            v-model="editForm.category"
                            required
                            class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500"
                        >
                            <option v-for="(lbl, key) in categories" :key="key" :value="key">{{ lbl }}</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Tingkat / Kelas -->
                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Tingkat / Kelas</label>
                            <input
                                type="text"
                                v-model="editForm.grade_level"
                                class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500"
                            />
                        </div>

                        <!-- Mata Pelajaran -->
                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Mata Pelajaran</label>
                            <input
                                type="text"
                                v-model="editForm.subject"
                                class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500"
                            />
                        </div>
                    </div>

                    <!-- Judul Dokumen -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Judul Dokumen <span class="text-rose-500">*</span></label>
                        <input
                            type="text"
                            v-model="editForm.title"
                            required
                            class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500"
                        />
                    </div>

                    <!-- Keterangan Tambahan -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Deskripsi / Catatan Perbaikan</label>
                        <textarea
                            v-model="editForm.description"
                            rows="2"
                            class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500"
                        ></textarea>
                    </div>

                    <!-- Unggah Pengganti (Opsional) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">
                            Ganti File Berkas (Kosongkan jika tidak ingin mengganti file fisik)
                        </label>
                        <input
                            type="file"
                            @change="handleEditFileSelect"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar"
                            class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-slate-800 dark:file:text-slate-300 cursor-pointer"
                        />
                        <p v-if="editSelectedFileName" class="text-[11px] text-emerald-600 mt-1 font-semibold">
                            File baru dipilih: {{ editSelectedFileName }}
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-subtle">
                        <button
                            type="button"
                            @click="showEditModal = false"
                            class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md transition-all active:scale-95 cursor-pointer disabled:opacity-50"
                        >
                            <span v-if="editForm.processing">Menyimpan...</span>
                            <span v-else>Simpan Perubahan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- ── Modal Detail Catatan Telaah ───────────────────────── -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <div
            v-if="showFeedbackModal && activeFeedbackDoc"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto"
        >
            <div class="w-full max-w-lg rounded-3xl bg-surface border border-theme shadow-2xl p-6 md:p-8 space-y-5 animate-in fade-in zoom-in-95 duration-200">
                <div class="flex items-center justify-between border-b border-subtle pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-rose-100 dark:bg-rose-950/60 text-rose-600 flex items-center justify-center font-bold">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Catatan Hasil Telaah</h3>
                    </div>
                    <button
                        @click="showFeedbackModal = false"
                        class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-3">
                    <div>
                        <p class="text-xs font-semibold text-slate-400">Dokumen:</p>
                        <p class="font-bold text-sm text-slate-800 dark:text-slate-100">{{ activeFeedbackDoc.title }}</p>
                    </div>

                    <div class="p-4 rounded-2xl bg-rose-50/80 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/60 space-y-2">
                        <div class="flex items-center justify-between text-xs text-rose-600 dark:text-rose-400 font-semibold">
                            <span>Ditelaah oleh: {{ activeFeedbackDoc.reviewer?.name || 'Kepala Sekolah / Kurikulum' }}</span>
                            <span v-if="activeFeedbackDoc.reviewed_at">{{ new Date(activeFeedbackDoc.reviewed_at).toLocaleDateString('id-ID') }}</span>
                        </div>
                        <p class="text-xs md:text-sm text-slate-700 dark:text-slate-300 whitespace-pre-line leading-relaxed">
                            {{ activeFeedbackDoc.feedback }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-subtle">
                    <button
                        @click="showFeedbackModal = false; openEditModal(activeFeedbackDoc)"
                        class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-colors cursor-pointer"
                    >
                        Unggah Perbaikan Sekarang
                    </button>
                    <button
                        @click="showFeedbackModal = false"
                        class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
