<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    documents: {
        type: Object,
        default: () => ({ data: [] })
    },
    groupedEmployees: {
        type: Array,
        default: () => []
    },
    stats: {
        type: Object,
        default: () => ({
            total_all: 0,
            total_verified: 0,
            total_pending: 0,
            total_employees: 0,
        })
    },
    filters: {
        type: Object,
        default: () => ({})
    },
})

// Filter & Pencarian
const filterStatus = ref(props.filters?.status || 'all')
const searchQuery = ref(props.filters?.search || '')

// Mode Tampilan: 'grouped' (dikelompokkan per pegawai - default) atau 'flat'
const viewMode = ref('grouped')

const applyFilters = () => {
    router.get(route('admin.employee-documents.index'), {
        status: filterStatus.value,
        search: searchQuery.value || null,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

// ─── ACCORDION PER PEGAWAI ──────────────────────────────────────────────────
// Menyimpan ID user yang sedang di-expand / dibuka detail dokumennya
const expandedUserIds = ref([])

const toggleUser = (userId) => {
    const idx = expandedUserIds.value.indexOf(userId)
    if (idx > -1) {
        expandedUserIds.value.splice(idx, 1)
    } else {
        expandedUserIds.value.push(userId)
    }
}

const isExpanded = (userId) => {
    return expandedUserIds.value.includes(userId)
}

const expandAll = () => {
    expandedUserIds.value = props.groupedEmployees.map(e => e.user_id)
}

const collapseAll = () => {
    expandedUserIds.value = []
}

// Avatar color helper
const getAvatarColor = (name) => {
    const colors = [
        'from-emerald-500 to-teal-600',
        'from-indigo-500 to-purple-600',
        'from-blue-500 to-cyan-600',
        'from-amber-500 to-orange-600',
        'from-rose-500 to-pink-600',
    ]
    const charCode = (name || 'A').charCodeAt(0)
    return colors[charCode % colors.length]
}

const getInitials = (name) => {
    if (!name) return 'PG'
    return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase()
}

// ─── MODAL VERIFIKASI ───────────────────────────────────────────────────────
const showVerifyModal = ref(false)
const selectedDoc = ref(null)

const verifyForm = useForm({
    is_verified: true,
    notes: ''
})

const openVerifyModal = (doc) => {
    selectedDoc.value = doc
    verifyForm.is_verified = doc.is_verified
    verifyForm.notes = doc.notes || ''
    showVerifyModal.value = true
}

const submitVerify = () => {
    verifyForm.patch(route('employee-documents.verify', selectedDoc.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showVerifyModal.value = false
        }
    })
}

// ─── MODAL PRATINJAU DOKUMEN ────────────────────────────────────────────────
const showPreviewModal = ref(false)
const previewUrl = ref('')
const previewTitle = ref('')

const openPreviewModal = (doc) => {
    previewUrl.value = route('employee-documents.preview', doc.id)
    previewTitle.value = doc.title
    showPreviewModal.value = true
}

// ─── HAPUS DOKUMEN ──────────────────────────────────────────────────────────
const deleteDocument = (id) => {
    if (confirm('Yakin ingin menghapus arsip pegawai ini? (File fisik juga akan terhapus)')) {
        router.delete(route('employee-documents.destroy', id), {
            preserveScroll: true
        })
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Verifikasi Arsip Pegawai - Sahabat SDAM" />

        <div class="space-y-6 w-full pb-12">
            <!-- ── Header Banner ─────────────────────────────────── -->
            <div class="relative overflow-hidden rounded-3xl bg-linear-to-br from-indigo-700 via-slate-800 to-slate-900 p-6 md:p-8 text-white shadow-lg">
                <div class="absolute -right-10 -bottom-10 w-64 h-64 rounded-full bg-white/5 blur-2xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="space-y-2 max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 backdrop-blur-md text-indigo-200 text-xs font-semibold tracking-wide border border-white/10">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Administrasi &amp; Arsip Kepegawaian</span>
                        </div>
                        <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-white drop-shadow-xs">
                            Rekap Arsip Dokumen Pegawai
                        </h1>
                        <p class="text-sm md:text-base text-indigo-100/90 leading-relaxed">
                            Pantau, verifikasi, dan kelola kelengkapan dokumen pribadi guru &amp; karyawan. Dokumen telah dikelompokkan rapi per nama pegawai.
                        </p>
                    </div>

                    <!-- Mode Tampilan Switch -->
                    <div class="inline-flex p-1.5 rounded-2xl bg-black/25 backdrop-blur-md border border-white/10 self-start md:self-center shrink-0">
                        <button
                            @click="viewMode = 'grouped'"
                            :class="[
                                'px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5',
                                viewMode === 'grouped'
                                    ? 'bg-white text-indigo-900 shadow-sm'
                                    : 'text-white/80 hover:text-white'
                            ]"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            <span>Per Pegawai</span>
                        </button>
                        <button
                            @click="viewMode = 'flat'"
                            :class="[
                                'px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5',
                                viewMode === 'flat'
                                    ? 'bg-white text-indigo-900 shadow-sm'
                                    : 'text-white/80 hover:text-white'
                            ]"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                            <span>Tabel Semua</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Stat Cards Ringkasan ─────────────────────────── -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="p-5 rounded-3xl bg-surface border border-theme shadow-xs">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Berkas Diunggah</p>
                    <p class="text-2xl md:text-3xl font-black text-slate-800 dark:text-slate-100 mt-1">{{ stats.total_all }}</p>
                </div>
                <div class="p-5 rounded-3xl bg-surface border border-theme shadow-xs">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Telah Disetujui (Valid)</p>
                    <p class="text-2xl md:text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ stats.total_verified }}</p>
                </div>
                <div class="p-5 rounded-3xl bg-surface border border-theme shadow-xs">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Menunggu Verifikasi</p>
                    <p class="text-2xl md:text-3xl font-black text-amber-500 mt-1">{{ stats.total_pending }}</p>
                </div>
                <div class="p-5 rounded-3xl bg-surface border border-theme shadow-xs">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Pegawai dg Arsip</p>
                    <p class="text-2xl md:text-3xl font-black text-indigo-600 dark:text-indigo-400 mt-1">{{ stats.total_employees }}</p>
                </div>
            </div>

            <!-- ── Filter & Search Bar ──────────────────────────── -->
            <div class="p-4 md:p-5 rounded-3xl bg-surface border border-theme shadow-xs space-y-4">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 flex-1">
                        <!-- Filter Status -->
                        <div class="w-full sm:w-64">
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Filter Status Verifikasi:</label>
                            <select
                                v-model="filterStatus"
                                @change="applyFilters"
                                class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="all">Semua Status Arsip</option>
                                <option value="verified">Sudah Disetujui / Valid Saja</option>
                                <option value="unverified">Menunggu Verifikasi Saja</option>
                            </select>
                        </div>

                        <!-- Pencarian -->
                        <div class="flex-1">
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Pencarian Pegawai / Dokumen:</label>
                            <div class="relative">
                                <input
                                    type="text"
                                    v-model="searchQuery"
                                    @keyup.enter="applyFilters"
                                    placeholder="Cari nama pegawai, NIP, judul, atau jenis dokumen..."
                                    class="w-full text-xs md:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 pl-9 pr-3 py-2.5 focus:ring-2 focus:ring-indigo-500"
                                />
                                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Expand / Collapse All (Khusus Grouped View) -->
                    <div v-if="viewMode === 'grouped' && groupedEmployees.length > 0" class="flex items-center gap-2 self-end md:self-end pt-1">
                        <button
                            type="button"
                            @click="expandAll"
                            class="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 text-xs font-bold transition-colors cursor-pointer"
                            title="Buka seluruh dokumen pegawai"
                        >
                            <span>Buka Semua</span>
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            @click="collapseAll"
                            class="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 text-xs font-bold transition-colors cursor-pointer"
                            title="Tutup seluruh dokumen pegawai"
                        >
                            <span>Tutup Semua</span>
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════
                 MODE 1: DIKELOMPOKKAN BERDASARKAN NAMA PEGAWAI (ACCORDION)
            ══════════════════════════════════════════════════════════ -->
            <div v-if="viewMode === 'grouped'" class="space-y-4">
                <!-- Empty State -->
                <div v-if="groupedEmployees.length === 0" class="p-12 text-center rounded-3xl bg-surface border border-theme shadow-xs space-y-3">
                    <div class="w-16 h-16 rounded-3xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/60 flex items-center justify-center text-indigo-600 mx-auto">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="font-bold text-base text-slate-800 dark:text-slate-200">Tidak Ada Dokumen Arsip Ditemukan</h3>
                    <p class="text-xs md:text-sm text-slate-400 max-w-md mx-auto">
                        Belum ada arsip pegawai yang sesuai dengan filter atau kata kunci pencarian yang dipilih.
                    </p>
                </div>

                <!-- List Pegawai -->
                <div
                    v-for="emp in groupedEmployees"
                    :key="emp.user_id"
                    class="rounded-3xl bg-surface border border-theme shadow-xs overflow-hidden transition-all duration-200 hover:border-indigo-300 dark:hover:border-indigo-700"
                >
                    <!-- Header Pegawai (Klik untuk Expand/Collapse Detail Arsip) -->
                    <div
                        @click="toggleUser(emp.user_id)"
                        class="p-4 md:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 cursor-pointer select-none transition-colors"
                        :class="[
                            isExpanded(emp.user_id)
                                ? 'bg-indigo-50/50 dark:bg-indigo-950/20 border-b border-indigo-100 dark:border-indigo-900/40'
                                : 'hover:bg-slate-50/80 dark:hover:bg-slate-800/50'
                        ]"
                    >
                        <!-- Kolom Kiri: Avatar & Info Pegawai -->
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div
                                class="w-11 h-11 rounded-2xl bg-linear-to-br flex items-center justify-center text-white font-black text-sm shrink-0 shadow-md shadow-slate-900/10"
                                :class="getAvatarColor(emp.name)"
                            >
                                {{ getInitials(emp.name) }}
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="font-extrabold text-base text-slate-900 dark:text-slate-100 group-hover:text-indigo-600 transition-colors truncate">
                                        {{ emp.name }}
                                    </h3>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                        {{ emp.total_docs }} Dokumen
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                                    {{ emp.nip || 'NIP: -' }} &bull; {{ emp.divisi || 'Guru/Karyawan' }}
                                </p>
                            </div>
                        </div>

                        <!-- Kolom Kanan: Status Kelengkapan & Tombol Buka -->
                        <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-subtle">
                            <!-- Badge Status Verifikasi Dokumen Pegawai -->
                            <div class="flex items-center gap-2">
                                <span
                                    v-if="emp.verified_count > 0"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>{{ emp.verified_count }} Valid</span>
                                </span>

                                <span
                                    v-if="emp.unverified_count > 0"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800/60"
                                >
                                    <span>⏳ {{ emp.unverified_count }} Menunggu</span>
                                </span>
                            </div>

                            <!-- Tombol Toggle Accordion -->
                            <div
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl font-bold text-xs transition-colors"
                                :class="[
                                    isExpanded(emp.user_id)
                                        ? 'bg-indigo-600 text-white shadow-xs'
                                        : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-indigo-50 hover:text-indigo-600'
                                ]"
                            >
                                <span>{{ isExpanded(emp.user_id) ? 'Tutup Detail' : 'Lihat Arsip' }}</span>
                                <svg
                                    class="w-4 h-4 transition-transform duration-200"
                                    :class="isExpanded(emp.user_id) ? 'rotate-180' : ''"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- ── DETAIL DOKUMEN PEGAWAI (MUNCUL SAAT DIKLIK) ──── -->
                    <div v-show="isExpanded(emp.user_id)" class="p-4 md:p-6 bg-slate-50/40 dark:bg-slate-900/30 border-t border-subtle">
                        <div class="overflow-x-auto rounded-2xl border border-slate-200/80 dark:border-slate-700/80 bg-white dark:bg-slate-850 shadow-2xs">
                            <table class="w-full text-left text-xs md:text-sm">
                                <thead>
                                    <tr class="bg-slate-100/70 dark:bg-slate-800 border-b border-subtle text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wider font-bold">
                                        <th class="py-3 px-4 md:px-5">Kategori &amp; Dokumen</th>
                                        <th class="py-3 px-4">Detail Tambahan</th>
                                        <th class="py-3 px-4 text-center">Status</th>
                                        <th class="py-3 px-4 md:px-5 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-subtle">
                                    <tr
                                        v-for="doc in emp.documents"
                                        :key="doc.id"
                                        class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors"
                                    >
                                        <!-- Kategori & Judul Dokumen -->
                                        <td class="py-3.5 px-4 md:px-5">
                                            <div class="space-y-1">
                                                <span class="inline-block px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-900/50">
                                                    {{ doc.document_type }}
                                                </span>
                                                <div class="font-extrabold text-slate-900 dark:text-slate-100 text-xs md:text-sm">
                                                    {{ doc.title }}
                                                </div>
                                                <div class="text-[11px] text-slate-400 font-mono">
                                                    Ekstensi: .{{ doc.file_extension }}
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Detail Tambahan (Nomor & Tahun) -->
                                        <td class="py-3.5 px-4 whitespace-nowrap">
                                            <div class="text-xs space-y-0.5">
                                                <div>
                                                    <span class="font-bold text-slate-500">Nomor:</span>
                                                    <span class="font-semibold text-slate-700 dark:text-slate-200 ml-1">
                                                        {{ doc.document_number || '-' }}
                                                    </span>
                                                </div>
                                                <div>
                                                    <span class="font-bold text-slate-500">Tahun:</span>
                                                    <span class="font-semibold text-slate-700 dark:text-slate-200 ml-1">
                                                        {{ doc.document_year || '-' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Status Verifikasi & Catatan -->
                                        <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                            <div class="space-y-1.5">
                                                <span
                                                    v-if="doc.is_verified"
                                                    class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 px-3 py-1 rounded-full text-xs font-bold border border-emerald-200 dark:border-emerald-800/60 shadow-2xs"
                                                >
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    <span>Valid</span>
                                                </span>
                                                <span
                                                    v-else
                                                    class="inline-flex items-center gap-1 bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 px-3 py-1 rounded-full text-xs font-bold border border-amber-200 dark:border-amber-800/60"
                                                >
                                                    <span>Menunggu</span>
                                                </span>

                                                <!-- Catatan HR jika ada -->
                                                <div v-if="doc.notes" class="text-[11px] text-slate-500 dark:text-slate-400 italic max-w-xs mx-auto truncate" :title="doc.notes">
                                                    "{{ doc.notes }}"
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Aksi Lengkap -->
                                        <td class="py-3.5 px-4 md:px-5 text-right whitespace-nowrap">
                                            <div class="inline-flex items-center gap-1.5">
                                                <!-- Pratinjau File -->
                                                <button
                                                    type="button"
                                                    @click="openPreviewModal(doc)"
                                                    class="p-2 text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 bg-slate-50 dark:bg-slate-800 hover:bg-indigo-50 dark:hover:bg-slate-700 rounded-xl shadow-2xs border border-slate-200 dark:border-slate-700 transition-colors cursor-pointer"
                                                    title="Pratinjau Berkas"
                                                >
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                </button>

                                                <!-- Unduh File -->
                                                <a
                                                    :href="route('employee-documents.download', doc.id)"
                                                    class="p-2 text-slate-500 hover:text-emerald-600 dark:hover:text-emerald-400 bg-slate-50 dark:bg-slate-800 hover:bg-emerald-50 dark:hover:bg-slate-700 rounded-xl shadow-2xs border border-slate-200 dark:border-slate-700 transition-colors cursor-pointer"
                                                    title="Unduh Berkas"
                                                >
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                    </svg>
                                                </a>

                                                <!-- Verifikasi / Ubah Status -->
                                                <button
                                                    type="button"
                                                    @click="openVerifyModal(doc)"
                                                    class="p-2 text-slate-500 hover:text-blue-600 dark:hover:text-blue-400 bg-slate-50 dark:bg-slate-800 hover:bg-blue-50 dark:hover:bg-slate-700 rounded-xl shadow-2xs border border-slate-200 dark:border-slate-700 transition-colors cursor-pointer"
                                                    title="Verifikasi Status"
                                                >
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </button>

                                                <!-- Hapus Berkas -->
                                                <button
                                                    type="button"
                                                    @click="deleteDocument(doc.id)"
                                                    class="p-2 text-slate-500 hover:text-rose-600 dark:hover:text-rose-400 bg-slate-50 dark:bg-slate-800 hover:bg-rose-50 dark:hover:bg-slate-700 rounded-xl shadow-2xs border border-slate-200 dark:border-slate-700 transition-colors cursor-pointer"
                                                    title="Hapus Berkas"
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
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════
                 MODE 2: TABEL FLAT SEMUA ARSIP (FALLBACK LAMA)
            ══════════════════════════════════════════════════════════ -->
            <div v-else class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600 dark:text-slate-400">
                        <thead class="bg-slate-50/80 dark:bg-slate-900/50 border-b border-slate-100 dark:border-slate-700 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-bold">
                            <tr>
                                <th class="px-6 py-4">Pegawai</th>
                                <th class="px-6 py-4">Kategori &amp; Dokumen</th>
                                <th class="px-6 py-4">Detail Tambahan</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="doc in documents.data" :key="doc.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-900 dark:text-slate-100">{{ doc.user?.name }}</div>
                                    <div class="text-xs text-slate-500 mt-1">{{ doc.user?.nip || '-' }} | {{ doc.user?.divisi || 'Guru' }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="inline-block px-2 py-0.5 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded text-[10px] font-bold uppercase tracking-wider mb-1">
                                        {{ doc.document_type }}
                                    </div>
                                    <div class="font-medium text-slate-800 dark:text-slate-200">{{ doc.title }}</div>
                                    <div class="text-xs text-slate-400 mt-1">Ekstensi: .{{ doc.file_extension }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-xs space-y-1">
                                        <div><span class="font-bold">Nomor:</span> {{ doc.document_number || '-' }}</div>
                                        <div><span class="font-bold">Tahun:</span> {{ doc.document_year || '-' }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span v-if="doc.is_verified" class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400 px-2.5 py-1 rounded-full text-xs font-bold">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                        Valid
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400 px-2.5 py-1 rounded-full text-xs font-bold">
                                        Menunggu
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <button @click="openPreviewModal(doc)" class="p-2 text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 bg-white dark:bg-slate-700 hover:bg-indigo-50 dark:hover:bg-slate-600 rounded-lg shadow-sm border border-slate-200 dark:border-slate-600 transition-colors" title="Lihat (Preview) File">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        </button>
                                        <a :href="route('employee-documents.download', doc.id)" class="p-2 text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 bg-white dark:bg-slate-700 hover:bg-emerald-50 dark:hover:bg-slate-600 rounded-lg shadow-sm border border-slate-200 dark:border-slate-600 transition-colors" title="Unduh File">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                        </a>
                                        <button @click="openVerifyModal(doc)" class="p-2 text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 bg-white dark:bg-slate-700 hover:bg-blue-50 dark:hover:bg-slate-600 rounded-lg shadow-sm border border-slate-200 dark:border-slate-600 transition-colors" title="Verifikasi">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        </button>
                                        <button @click="deleteDocument(doc.id)" class="p-2 text-slate-400 hover:text-red-600 dark:hover:text-red-400 bg-white dark:bg-slate-700 hover:bg-red-50 dark:hover:bg-slate-600 rounded-lg shadow-sm border border-slate-200 dark:border-slate-600 transition-colors" title="Hapus">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="documents.data.length === 0">
                                <td colspan="5" class="px-6 py-10 text-center text-slate-500 dark:text-slate-400">
                                    Tidak ada dokumen arsip yang ditemukan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination Flat Mode -->
                <div class="px-6 py-4 bg-slate-50/50 dark:bg-slate-800/30 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between" v-if="documents.links && documents.links.length > 3">
                    <span class="text-sm text-slate-500 dark:text-slate-400">
                        Menampilkan {{ documents.from }} - {{ documents.to }} dari {{ documents.total }} data
                    </span>
                    <div class="flex space-x-1">
                        <component :is="link.url ? 'Link' : 'span'" v-for="(link, i) in documents.links" :key="i" :href="link.url"
                                   v-html="link.label"
                                   class="px-3 py-1 rounded-md text-sm"
                                   :class="[
                                       link.active ? 'bg-emerald-600 text-white font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700',
                                       !link.url && 'opacity-50 cursor-not-allowed'
                                   ]"></component>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Modal Verifikasi Arsip ───────────────────────────── -->
        <div v-if="showVerifyModal" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="showVerifyModal = false"></div>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-3xl bg-white dark:bg-slate-800 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-100 dark:border-slate-700">
                        <form @submit.prevent="submitVerify">
                            <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700">
                                <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">Verifikasi Arsip Pegawai</h3>
                            </div>
                            <div class="px-6 py-5 space-y-4">
                                <div v-if="selectedDoc" class="p-4 bg-slate-50 dark:bg-slate-900 rounded-2xl mb-4 border border-subtle">
                                    <p class="text-xs text-slate-400 font-semibold uppercase tracking-wider">{{ selectedDoc.document_type }}</p>
                                    <p class="text-sm font-black text-slate-800 dark:text-slate-100 mt-0.5">{{ selectedDoc.title }}</p>
                                    <p class="text-xs text-slate-500 mt-1">Pegawai: {{ selectedDoc.user?.name }}</p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Status Dokumen</label>
                                    <div class="flex gap-4">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" v-model="verifyForm.is_verified" :value="true" class="w-4 h-4 text-emerald-600 focus:ring-emerald-600 border-slate-300">
                                            <span class="text-sm text-slate-700 dark:text-slate-300 font-bold">Valid &amp; Disetujui</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" v-model="verifyForm.is_verified" :value="false" class="w-4 h-4 text-amber-600 focus:ring-amber-600 border-slate-300">
                                            <span class="text-sm text-slate-700 dark:text-slate-300 font-bold">Menunggu / Ditolak</span>
                                        </label>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">Catatan HR (Opsional)</label>
                                    <textarea v-model="verifyForm.notes" rows="3" placeholder="Tulis catatan jika ada kekurangan atau keterangan dokumen..." class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2 text-sm text-slate-700 dark:text-slate-300 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                                </div>
                            </div>
                            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 flex justify-end gap-3 rounded-b-3xl">
                                <button type="button" @click="showVerifyModal = false" class="px-4 py-2 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors cursor-pointer">Batal</button>
                                <button type="submit" :disabled="verifyForm.processing" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold transition-colors disabled:opacity-50 cursor-pointer">
                                    {{ verifyForm.processing ? 'Menyimpan...' : 'Simpan Status' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Modal Pratinjau Dokumen (Preview) ────────────────── -->
        <div v-if="showPreviewModal" class="relative z-[60]" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/80 backdrop-blur-xs transition-opacity" @click="showPreviewModal = false"></div>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-3xl bg-white dark:bg-slate-800 text-left shadow-2xl transition-all w-full max-w-5xl h-[85vh] flex flex-col border border-slate-100 dark:border-slate-700">
                        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-900/50">
                            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100 truncate pr-4">{{ previewTitle }}</h3>
                            <button @click="showPreviewModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors cursor-pointer">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div class="flex-1 w-full bg-slate-100 dark:bg-slate-900 overflow-hidden relative">
                            <iframe v-if="previewUrl" :src="previewUrl" class="w-full h-full border-0" title="Document Preview"></iframe>
                            <div v-else class="flex items-center justify-center h-full text-slate-500">Memuat berkas...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
