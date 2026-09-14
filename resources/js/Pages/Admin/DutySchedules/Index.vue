<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    daysList: Array,
    employees: Array,
    dailyWorkSchedules: Object,
    globalWorkStart: String,
    globalWorkEnd: String,
    globalTolerance: Number,
})

// Tab aktif: 'piket' atau 'harian'
const activeTab = ref('piket')

// ─── Modal Atur Jadwal Piket ──────────────────────────────────────────────
const isModalOpen = ref(false)
const selectedDay = ref(null)
const employeeSearch = ref('')

const piketForm = useForm({
    day_of_week: 1,
    name: '',
    time_in: '06:30',
    time_out: '14:30',
    late_tolerance: 15,
    is_active: true,
    notes: '',
    user_ids: [],
})

const openEditModal = (day) => {
    selectedDay.value = day
    piketForm.day_of_week = day.day_of_week
    piketForm.name = day.name || `Piket ${day.day_name}`
    piketForm.time_in = day.time_in ? day.time_in.substring(0, 5) : '06:30'
    piketForm.time_out = day.time_out ? day.time_out.substring(0, 5) : '14:30'
    piketForm.late_tolerance = day.late_tolerance !== null && day.late_tolerance !== undefined ? day.late_tolerance : 15
    piketForm.is_active = day.is_active
    piketForm.notes = day.notes || ''
    piketForm.user_ids = [...day.user_ids]
    employeeSearch.value = ''
    isModalOpen.value = true
}

const closeModal = () => {
    isModalOpen.value = false
    selectedDay.value = null
}

const submitPiket = () => {
    piketForm.post(route('admin.duty-schedules.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal()
        },
    })
}

// Filter daftar pegawai di modal
const filteredEmployees = computed(() => {
    const q = employeeSearch.value.toLowerCase().trim()
    if (!q) return props.employees
    return props.employees.filter((emp) => {
        return (
            emp.name?.toLowerCase().includes(q) ||
            emp.email?.toLowerCase().includes(q) ||
            emp.nip?.toLowerCase().includes(q) ||
            emp.divisi?.toLowerCase().includes(q)
        )
    })
})

const toggleUserSelection = (userId) => {
    const idx = piketForm.user_ids.indexOf(userId)
    if (idx > -1) {
        piketForm.user_ids.splice(idx, 1)
    } else {
        piketForm.user_ids.push(userId)
    }
}

const removeUserFromPiket = (day, userId) => {
    const updatedUserIds = day.user_ids.filter(id => id !== userId)
    router.post(route('admin.duty-schedules.store'), {
        day_of_week: day.day_of_week,
        name: day.name,
        time_in: day.time_in,
        time_out: day.time_out,
        late_tolerance: day.late_tolerance,
        is_active: day.is_active,
        notes: day.notes,
        user_ids: updatedUserIds,
    }, { preserveScroll: true })
}

// ─── Form Jam Kerja Harian Default (Senin - Minggu) ──────────────────────
const dailyForm = useForm({
    schedules: JSON.parse(JSON.stringify(props.dailyWorkSchedules)),
})

const submitDailyWork = () => {
    dailyForm.post(route('admin.duty-schedules.daily-work'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Pengaturan Jadwal Piket Pegawai" />

    <AuthenticatedLayout>
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- ── Header & Breadcrumbs ──────────────────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-xs text-slate-400 font-semibold mb-1">
                        <Link :href="route('admin.users.index')" class="hover:text-emerald-600 transition-colors">Manajemen User</Link>
                        <span>/</span>
                        <span class="text-emerald-600">Jadwal Piket &amp; Jam Kerja</span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-800 dark:text-slate-100 tracking-tight">
                        Pengaturan Jadwal Piket &amp; Jam Kerja
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                        Petakan pegawai yang bertugas piket harian serta atur jam kerja harian sekolah.
                    </p>
                </div>

                <!-- Tombol Navigasi Cepat -->
                <div class="flex items-center gap-2.5">
                    <Link
                        :href="route('admin.users.index')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-card-subtle border border-theme text-slate-700 dark:text-slate-200 font-bold text-xs sm:text-sm shadow-xs hover:bg-slate-100 dark:hover:bg-slate-800 transition-all cursor-pointer"
                    >
                        <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Daftar User</span>
                    </Link>
                </div>
            </div>

            <!-- ── Tab Navigasi ──────────────────────────────────────────────── -->
            <div class="flex items-center gap-2 p-1.5 bg-sidebar rounded-2xl border border-theme shadow-xs w-full sm:w-fit">
                <button
                    type="button"
                    @click="activeTab = 'piket'"
                    :class="[
                        'flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-black transition-all cursor-pointer',
                        activeTab === 'piket'
                            ? 'bg-emerald-600 text-white shadow-sm'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                    ]"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <span>Jadwal Piket Pegawai</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'harian'"
                    :class="[
                        'flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-black transition-all cursor-pointer',
                        activeTab === 'harian'
                            ? 'bg-emerald-600 text-white shadow-sm'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                    ]"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Jam Kerja Harian Default</span>
                </button>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- TAB 1: JADWAL PIKET PEGAWAI                                     -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'piket'" class="space-y-6">

                <!-- Alert Penjelasan Sistem Piket -->
                <div class="p-4 sm:p-5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200/80 dark:border-emerald-800/50 flex items-start gap-3.5">
                    <div class="p-2 bg-emerald-600 text-white rounded-xl shadow-xs flex-shrink-0 mt-0.5">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="text-xs sm:text-sm text-emerald-950 dark:text-emerald-200 space-y-1">
                        <p class="font-black text-emerald-900 dark:text-emerald-100">
                            Cara Kerja Jadwal Piket:
                        </p>
                        <p class="text-emerald-800/90 dark:text-emerald-300 leading-relaxed text-xs">
                            Pegawai yang dimasukkan ke dalam daftar piket pada hari tertentu (misal: <strong>Senin</strong>) akan otomatis menggunakan jam masuk &amp; jam pulang piket (misal: <strong>06:30 - 14:30</strong>) saat presensi di hari tersebut. Di hari lain ketika pegawai tidak bertugas piket, jam presensinya akan kembali mengikuti jam kerja normal.
                        </p>
                    </div>
                </div>

                <!-- ── 7 Hari Grid Kartu Piket (Senin - Minggu) ──────────────── -->
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                    <div
                        v-for="day in daysList"
                        :key="day.day_of_week"
                        class="bg-sidebar rounded-3xl p-5 border border-theme shadow-xs flex flex-col justify-between transition-all hover:border-emerald-500/40"
                    >
                        <div class="space-y-4">
                            <!-- Hari & Status Aktif -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-8 h-8 rounded-xl bg-card-subtle flex items-center justify-center font-black text-xs text-slate-700 dark:text-slate-200 border border-theme">
                                        {{ day.day_of_week }}
                                    </span>
                                    <div>
                                        <h3 class="text-base font-black text-slate-800 dark:text-slate-100">{{ day.day_name }}</h3>
                                        <p class="text-[11px] text-slate-400 font-medium">{{ day.name }}</p>
                                    </div>
                                </div>

                                <span
                                    :class="[
                                        'px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider',
                                        day.is_active
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                            : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                                    ]"
                                >
                                    {{ day.is_active ? 'Piket Aktif' : 'Non-Aktif' }}
                                </span>
                            </div>

                            <!-- Jam Piket Masuk & Pulang -->
                            <div class="grid grid-cols-2 gap-2.5 p-3 rounded-2xl bg-card-subtle border border-theme/60">
                                <div>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tight block">Jam Masuk</span>
                                    <span class="text-sm sm:text-base font-black text-emerald-600 font-mono">{{ day.time_in?.substring(0, 5) || '06:30' }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tight block">Jam Pulang</span>
                                    <span class="text-sm sm:text-base font-black text-rose-600 font-mono">{{ day.time_out?.substring(0, 5) || '14:30' }}</span>
                                </div>
                            </div>

                            <!-- Toleransi & Keterangan -->
                            <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 px-1">
                                <span>Toleransi Telat: <strong class="text-slate-700 dark:text-slate-200 font-mono">{{ day.late_tolerance ?? 15 }} mnt</strong></span>
                                <span>Total Petugas: <strong class="text-emerald-600 font-mono">{{ day.users.length }}</strong> orang</span>
                            </div>

                            <!-- Daftar Pegawai yang Bertugas -->
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Petugas Piket Terjadwal:</span>
                                
                                <div v-if="day.users && day.users.length > 0" class="flex flex-wrap gap-1.5 max-h-40 overflow-y-auto pr-1">
                                    <div
                                        v-for="u in day.users"
                                        :key="u.id"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-card-subtle border border-theme text-xs font-semibold text-slate-700 dark:text-slate-200 group"
                                    >
                                        <div class="w-5 h-5 rounded-full bg-emerald-100 dark:bg-emerald-950 flex items-center justify-center text-[10px] font-black text-emerald-700 overflow-hidden flex-shrink-0">
                                            <img v-if="u.avatar" :src="u.avatar" class="w-full h-full object-cover" />
                                            <span v-else>{{ u.name.charAt(0) }}</span>
                                        </div>
                                        <span class="truncate max-w-[110px]">{{ u.name }}</span>
                                        <button
                                            type="button"
                                            @click.stop="removeUserFromPiket(day, u.id)"
                                            class="text-slate-400 hover:text-rose-500 rounded-full p-0.5 ml-0.5 transition-colors cursor-pointer"
                                            title="Hapus dari piket hari ini"
                                        >
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div v-else class="p-4 rounded-2xl bg-card-subtle/50 border border-dashed border-theme text-center">
                                    <p class="text-[11px] text-slate-400">Belum ada pegawai yang ditugaskan piket.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Tombol Atur Jadwal -->
                        <div class="pt-4 mt-4 border-t border-subtle">
                            <button
                                type="button"
                                @click="openEditModal(day)"
                                class="w-full py-2.5 rounded-xl bg-card-subtle hover:bg-emerald-50 dark:hover:bg-emerald-950/40 border border-theme text-slate-700 dark:text-slate-200 hover:text-emerald-700 dark:hover:text-emerald-300 font-black text-xs transition-all active:scale-[0.99] flex items-center justify-center gap-1.5 cursor-pointer"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                <span>Atur Jam &amp; Petugas Piket</span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- TAB 2: JAM KERJA HARIAN DEFAULT (SENIN - MINGGU)                -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <div v-else-if="activeTab === 'harian'" class="space-y-6">

                <div class="p-4 sm:p-5 rounded-2xl bg-blue-50 dark:bg-blue-950/30 border border-blue-200/80 dark:border-blue-800/50 flex items-start gap-3.5">
                    <div class="p-2 bg-blue-600 text-white rounded-xl shadow-xs flex-shrink-0 mt-0.5">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="text-xs sm:text-sm text-blue-950 dark:text-blue-200 space-y-1">
                        <p class="font-black text-blue-900 dark:text-blue-100">
                            Pengaturan Jam Kerja Standar Harian:
                        </p>
                        <p class="text-blue-800/90 dark:text-blue-300 leading-relaxed text-xs">
                            Tentukan jam masuk dan jam pulang standar per hari (misal: Senin-Kamis 07:00 - 15:00, Jumat 07:00 - 11:30, Sabtu 07:00 - 13:00, dan Minggu Libur). Pengaturan ini berlaku untuk semua pegawai yang tidak bertugas piket dan tidak memiliki jam khusus perorangan.
                        </p>
                    </div>
                </div>

                <!-- Formulir Jam Kerja Harian -->
                <form @submit.prevent="submitDailyWork" class="bg-sidebar rounded-3xl p-6 border border-theme shadow-xs space-y-5">
                    <div class="flex items-center justify-between border-b border-subtle pb-4">
                        <div>
                            <h2 class="text-base font-black text-slate-800 dark:text-slate-100">Tabel Jam Kerja Standar (Senin - Minggu)</h2>
                            <p class="text-xs text-slate-400">Sesuaikan jadwal harian seluruh lembaga</p>
                        </div>
                        <button
                            type="submit"
                            :disabled="dailyForm.processing"
                            class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs sm:text-sm shadow-md transition-all active:scale-95 disabled:opacity-50 cursor-pointer"
                        >
                            {{ dailyForm.processing ? 'Menyimpan...' : 'Simpan Jam Harian' }}
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs sm:text-sm border-collapse">
                            <thead>
                                <tr class="border-b border-subtle bg-card-subtle text-slate-400 uppercase tracking-wider text-[11px]">
                                    <th class="py-3 px-4 font-bold">Hari</th>
                                    <th class="py-3 px-4 font-bold text-center">Status Hari Kerja</th>
                                    <th class="py-3 px-4 font-bold">Jam Masuk</th>
                                    <th class="py-3 px-4 font-bold">Jam Pulang</th>
                                    <th class="py-3 px-4 font-bold">Toleransi (Menit)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-subtle">
                                <tr
                                    v-for="(sched, dayNum) in dailyForm.schedules"
                                    :key="dayNum"
                                    :class="[
                                        'transition-colors',
                                        sched.is_active ? 'hover:bg-card-subtle/40' : 'bg-slate-50/50 dark:bg-slate-900/30 opacity-70'
                                    ]"
                                >
                                    <td class="py-3.5 px-4 font-black text-slate-800 dark:text-slate-100">
                                        <div class="flex items-center gap-2">
                                            <span class="w-6 h-6 rounded-lg bg-card-subtle flex items-center justify-center text-[10px] font-mono border border-theme">
                                                {{ dayNum }}
                                            </span>
                                            <span>{{ sched.day_name }}</span>
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-4 text-center">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="sched.is_active" class="sr-only peer" />
                                            <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                                            <span class="ml-2 text-xs font-bold" :class="sched.is_active ? 'text-emerald-600' : 'text-slate-400'">
                                                {{ sched.is_active ? 'Masuk' : 'Libur' }}
                                            </span>
                                        </label>
                                    </td>

                                    <td class="py-3.5 px-4">
                                        <input
                                            type="time"
                                            v-model="sched.start"
                                            :disabled="!sched.is_active"
                                            class="text-xs rounded-xl border border-theme bg-card-subtle px-3 py-1.5 text-slate-800 dark:text-slate-100 font-mono disabled:opacity-40"
                                        />
                                    </td>

                                    <td class="py-3.5 px-4">
                                        <input
                                            type="time"
                                            v-model="sched.end"
                                            :disabled="!sched.is_active"
                                            class="text-xs rounded-xl border border-theme bg-card-subtle px-3 py-1.5 text-slate-800 dark:text-slate-100 font-mono disabled:opacity-40"
                                        />
                                    </td>

                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-1.5">
                                            <input
                                                type="number"
                                                v-model="sched.tolerance"
                                                :disabled="!sched.is_active"
                                                min="0"
                                                max="120"
                                                class="w-20 text-xs rounded-xl border border-theme bg-card-subtle px-3 py-1.5 text-slate-800 dark:text-slate-100 font-mono disabled:opacity-40"
                                            />
                                            <span class="text-xs text-slate-400">menit</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="pt-4 border-t border-subtle flex justify-end">
                        <button
                            type="submit"
                            :disabled="dailyForm.processing"
                            class="px-6 py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm shadow-md hover:shadow-emerald-500/20 active:scale-95 transition-all disabled:opacity-50 cursor-pointer"
                        >
                            {{ dailyForm.processing ? 'Menyimpan Pengaturan...' : 'Simpan Perubahan Jam Kerja Harian' }}
                        </button>
                    </div>
                </form>

            </div>

        </div>

        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- MODAL ATUR JADWAL & PETUGAS PIKET                                   -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs" @click="closeModal" />

            <div class="relative z-10 w-full max-w-2xl bg-sidebar rounded-3xl shadow-2xl border border-theme overflow-hidden flex flex-col max-h-[90vh]">
                
                <!-- Header Modal -->
                <div class="p-5 border-b border-subtle flex items-center justify-between">
                    <div>
                        <h3 class="text-base sm:text-lg font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <span>Atur Jadwal Piket Hari {{ selectedDay?.day_name }}</span>
                        </h3>
                        <p class="text-xs text-slate-400">Tentukan jam masuk, jam pulang, dan pegawai yang bertugas piket.</p>
                    </div>
                    <button
                        type="button"
                        @click="closeModal"
                        class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-card-subtle transition-colors cursor-pointer"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Body Modal (Scrollable) -->
                <form @submit.prevent="submitPiket" class="p-5 overflow-y-auto space-y-5">
                    
                    <!-- Nama Piket & Status Aktif -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">Nama Jadwal Piket</label>
                            <input
                                type="text"
                                v-model="piketForm.name"
                                placeholder="Contoh: Piket Senin / Piket Gerbang Pagi"
                                class="w-full text-xs rounded-xl border border-theme bg-card-subtle px-3 py-2 text-slate-800 dark:text-slate-100 font-semibold"
                                required
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">Status Piket</label>
                            <div class="flex items-center h-9">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" v-model="piketForm.is_active" class="sr-only peer" />
                                    <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                                    <span class="ml-2 text-xs font-bold" :class="piketForm.is_active ? 'text-emerald-600' : 'text-slate-400'">
                                        {{ piketForm.is_active ? 'Aktif' : 'Non-Aktif' }}
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Jam Masuk, Jam Pulang, Toleransi -->
                    <div class="grid grid-cols-3 gap-3 p-3.5 rounded-2xl bg-card-subtle border border-theme/60">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1 uppercase tracking-tight">Jam Masuk Piket</label>
                            <input
                                type="time"
                                v-model="piketForm.time_in"
                                class="w-full text-xs rounded-xl border border-theme bg-sidebar px-2.5 py-1.5 text-emerald-600 font-black font-mono"
                                required
                            />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1 uppercase tracking-tight">Jam Pulang Piket</label>
                            <input
                                type="time"
                                v-model="piketForm.time_out"
                                class="w-full text-xs rounded-xl border border-theme bg-sidebar px-2.5 py-1.5 text-rose-600 font-black font-mono"
                                required
                            />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1 uppercase tracking-tight">Toleransi (Mnt)</label>
                            <input
                                type="number"
                                v-model="piketForm.late_tolerance"
                                min="0"
                                max="120"
                                placeholder="15"
                                class="w-full text-xs rounded-xl border border-theme bg-sidebar px-2.5 py-1.5 text-slate-800 dark:text-slate-100 font-black font-mono"
                            />
                        </div>
                    </div>

                    <!-- Pemilihan Pegawai Bertugas -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block text-xs font-black text-slate-700 dark:text-slate-200 uppercase tracking-wider">
                                    Pilih Pegawai Piket ({{ piketForm.user_ids.length }} Terpilih)
                                </label>
                                <p class="text-[11px] text-slate-400">Centang pegawai yang bertugas pada hari {{ selectedDay?.day_name }}.</p>
                            </div>

                            <button
                                type="button"
                                @click="piketForm.user_ids = []"
                                v-if="piketForm.user_ids.length > 0"
                                class="text-[11px] font-bold text-rose-500 hover:underline cursor-pointer"
                            >
                                Kosongkan Pilihan
                            </button>
                        </div>

                        <!-- Input Pencarian Pegawai -->
                        <div class="relative">
                            <input
                                type="text"
                                v-model="employeeSearch"
                                placeholder="Cari nama pegawai, NIP, atau divisi..."
                                class="w-full text-xs rounded-xl border border-theme bg-card-subtle pl-9 pr-4 py-2 text-slate-800 dark:text-slate-100"
                            />
                            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>

                        <!-- Daftar Checklist Pegawai -->
                        <div class="max-h-56 overflow-y-auto border border-theme rounded-2xl divide-y divide-subtle bg-card-subtle/30">
                            <div
                                v-for="emp in filteredEmployees"
                                :key="emp.id"
                                @click="toggleUserSelection(emp.id)"
                                :class="[
                                    'p-2.5 px-3 flex items-center justify-between cursor-pointer transition-colors',
                                    piketForm.user_ids.includes(emp.id)
                                        ? 'bg-emerald-50/80 dark:bg-emerald-950/40'
                                        : 'hover:bg-card-subtle'
                                ]"
                            >
                                <div class="flex items-center gap-2.5">
                                    <input
                                        type="checkbox"
                                        :checked="piketForm.user_ids.includes(emp.id)"
                                        @click.stop="toggleUserSelection(emp.id)"
                                        class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-slate-300 dark:border-slate-600"
                                    />
                                    <div class="w-7 h-7 rounded-full bg-emerald-100 dark:bg-emerald-900 flex items-center justify-center text-xs font-bold text-emerald-700 overflow-hidden flex-shrink-0">
                                        <img v-if="emp.avatar" :src="emp.avatar" class="w-full h-full object-cover" />
                                        <span v-else>{{ emp.name.charAt(0) }}</span>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-slate-800 dark:text-slate-100 leading-tight">{{ emp.name }}</p>
                                        <p class="text-[10px] text-slate-400 mt-0.5">
                                            <span v-if="emp.divisi" class="text-emerald-600 dark:text-emerald-400 font-semibold">{{ emp.divisi }}</span>
                                            <span v-if="emp.divisi && emp.nip"> &bull; </span>
                                            <span v-if="emp.nip">NIP: {{ emp.nip }}</span>
                                        </p>
                                    </div>
                                </div>

                                <span
                                    v-if="piketForm.user_ids.includes(emp.id)"
                                    class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                                >
                                    Piket
                                </span>
                            </div>

                            <div v-if="filteredEmployees.length === 0" class="p-6 text-center text-xs text-slate-400">
                                Pegawai tidak ditemukan dengan kata kunci "{{ employeeSearch }}".
                            </div>
                        </div>

                    </div>

                    <!-- Catatan Opsional -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">Catatan Khusus (Opsional)</label>
                        <input
                            type="text"
                            v-model="piketForm.notes"
                            placeholder="Contoh: Bertugas menyambut siswa di gerbang utama..."
                            class="w-full text-xs rounded-xl border border-theme bg-card-subtle px-3 py-2 text-slate-800 dark:text-slate-100"
                        />
                    </div>

                    <!-- Footer Tombol Modal -->
                    <div class="pt-4 border-t border-subtle flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="closeModal"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-500 hover:bg-card-subtle transition-colors cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="piketForm.processing"
                            class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs shadow-md transition-all active:scale-95 disabled:opacity-50 cursor-pointer"
                        >
                            {{ piketForm.processing ? 'Menyimpan...' : 'Simpan Jadwal Piket' }}
                        </button>
                    </div>

                </form>

            </div>
        </div>

    </AuthenticatedLayout>
</template>
