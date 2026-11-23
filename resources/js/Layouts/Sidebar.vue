<script setup>
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
    IconDashboard,
    IconQuranHadis,
    IconDzikir,
    IconHpt,
    IconNotification,
    IconChat,
    IconNotulen,
    IconPresensi,
    IconFormHabit,
    IconInputHabit,
    IconRekapQuran,
    IconJurnalKajian,
    IconHaid,
    IconJurnalGuru,
    IconAdministrasiGuru,
    IconArsip,
    IconKpi,
    IconSuratMasuk,
    IconSuratKeluar,
    IconDisposisi,
    IconPengaturanSurat,
    IconRekapPresensi,
    IconRekapHabit,
    IconSemuaLaporan,
    IconBelumIsiHabit,
    IconRekapJurnalGuru,
    IconRekapAdministrasiGuru,
    IconMasterHabit,
    IconManajemenUser,
    IconDaftarUser,
    IconVerifikasiArsip,
    IconEvaluasiKpi,
    IconJadwalPiket,
    IconJadwalKhusus,
    IconDaftarDivisi,
    IconPengaturanSistem,
    IconPanduan,
    IconFiturHr,
    IconLogAkses,
    IconPemantauanSistem,
    IconChevronDown
} from '@/Components/Icons'

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: true
    },
    user: {
        type: Object,
        default: () => ({})
    },
    roles: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['close', 'expand', 'toggle'])

const closeSidebar = () => {
    if (typeof window !== 'undefined' && window.innerWidth < 768) {
        emit('close')
    }
}

// ─── Role & Gender Helpers ──────────────────────────────────────────────────
const hasRole = (role) => props.roles.includes(role)
const hasAnyRole = (roleArray) => roleArray.some(r => props.roles.includes(r))
const isFemale = computed(() => props.user?.gender === 'P')

const roleLabel = computed(() => {
    if (hasRole('superadmin')) return 'Super Admin'
    if (hasRole('admin'))      return 'Admin'
    if (hasRole('kepala_sekolah')) return 'Kepala Sekolah'
    if (hasRole('kurikulum'))  return 'Kurikulum'
    if (hasRole('tata_usaha')) return 'Tata Usaha'
    return 'Pegawai'
})

const userInitial = computed(() =>
    props.user?.name?.charAt(0)?.toUpperCase() ?? '?'
)

const userAvatarUrl = computed(() => {
    if (props.user?.avatar) {
        return props.user.avatar.startsWith('http') ? props.user.avatar : `/storage/${props.user.avatar}`
    }
    return null
})

// ─── Active Route Helper (via Ziggy dengan per-URL Cache) ───────────────────
const page = usePage()
const routeActiveCache = new Map()
let lastPageUrl = ''

const isActive = (routeName) => {
    if (page.url !== lastPageUrl) {
        routeActiveCache.clear()
        lastPageUrl = page.url
    }
    if (routeActiveCache.has(routeName)) {
        return routeActiveCache.get(routeName)
    }
    let res = false
    try {
        if (typeof route !== 'undefined') {
            res = route().current(routeName)
        }
    } catch {
        res = false
    }
    routeActiveCache.set(routeName, res)
    return res
}

// ─── Dynamic Classes (Responsive Desktop Rail vs Expanded Drawer) ───────────
const navLinkClass = (routeName) => [
    'group relative flex items-center rounded-2xl text-sm font-medium transition-all duration-200 ease-out select-none cursor-pointer',
    props.isOpen
        ? 'w-full gap-3.5 px-4 py-2.5'
        : 'w-11 h-11 p-2.5 mx-auto justify-center',
    isActive(routeName)
        ? (props.isOpen
            ? 'bg-gradient-to-r from-primary-500/20 via-primary-500/10 to-transparent text-primary-950 dark:text-primary-100 font-bold shadow-xs translate-x-1 border-l-4 border-primary-500'
            : 'bg-primary-500/20 text-primary-700 dark:text-primary-300 font-bold shadow-xs ring-2 ring-primary-500/40 scale-105')
        : (props.isOpen
            ? 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/50 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100 hover:translate-x-1.5'
            : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/50 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100 hover:scale-110')
]

const subNavLinkClass = (routeName) => [
    'group flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-medium transition-all duration-200 ease-out w-full select-none cursor-pointer',
    isActive(routeName)
        ? 'bg-primary-100/80 dark:bg-primary-900/50 text-primary-900 dark:text-primary-100 font-bold shadow-xs translate-x-1'
        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/50 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100 hover:translate-x-1'
]

const accordionButtonClass = (isOpenState) => [
    'group flex items-center rounded-2xl text-sm font-medium transition-all duration-200 select-none cursor-pointer',
    props.isOpen
        ? 'w-full gap-3.5 px-4 py-2.5 rounded-full'
        : 'w-11 h-11 p-2.5 mx-auto justify-center',
    isOpenState
        ? 'bg-slate-200/60 dark:bg-slate-800/70 text-slate-900 dark:text-slate-100 font-semibold'
        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/50 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100' + (!props.isOpen ? ' hover:scale-110' : '')
]

const iconClass = (routeName) => [
    'w-5 h-5 flex-shrink-0 transition-colors',
    isActive(routeName)
        ? 'text-primary-600 dark:text-primary-400'
        : 'text-slate-400 dark:text-slate-500 group-hover:text-primary-600 dark:group-hover:text-primary-400'
]

const subIconClass = (routeName) => [
    'w-4 h-4 flex-shrink-0 transition-colors',
    isActive(routeName)
        ? 'text-primary-600 dark:text-primary-400'
        : 'text-slate-400 dark:text-slate-500 group-hover:text-primary-600 dark:group-hover:text-primary-400'
]

// ─── Feature Flags (dari global_settings yang di-share via Inertia) ─────────
const featureEnabled = (key) => {
    const val = page.props.global_settings?.[key]
    if (['feature_presensi', 'feature_arsip', 'feature_kpi', 'feature_habit', 'feature_administrasi_guru'].includes(key)) {
        return val === undefined || val === '1' || val === 'true' || val === true
    }
    return val === '1' || val === 'true' || val === true
}

// ─── Menu Toggle States & Mini Rail Click Behavior ──────────────────────────
const isFormHabitOpen = ref(
    isActive('habit.form') || 
    isActive('quran.recap') || 
    isActive('kajian.index')
)

const isRekapHabitOpen = ref(
    isActive('admin.laporan') || 
    isActive('admin.laporan.unfilled')
)

const isManajemenUserOpen = ref(
    isActive('admin.users.*') || 
    isActive('admin.employee-documents.*') || 
    isActive('admin.performance.*') ||
    isActive('admin.duty-schedules.*') ||
    isActive('admin.special-schedules.*') ||
    isActive('admin.divisions.*')
)

const toggleAccordion = (type) => {
    if (!props.isOpen) {
        emit('expand')
        emit('toggle')
        if (type === 'formHabit') isFormHabitOpen.value = true
        if (type === 'rekapHabit') isRekapHabitOpen.value = true
        if (type === 'manajemenUser') isManajemenUserOpen.value = true
        return
    }
    if (type === 'formHabit') isFormHabitOpen.value = !isFormHabitOpen.value
    if (type === 'rekapHabit') isRekapHabitOpen.value = !isRekapHabitOpen.value
    if (type === 'manajemenUser') isManajemenUserOpen.value = !isManajemenUserOpen.value
}
</script>

<template>
    <aside
        :class="[
            'fixed inset-y-0 left-0 z-40 flex flex-col',
            'bg-white/80 dark:bg-slate-900/85 backdrop-blur-2xl shadow-2xl border-r border-white/60 dark:border-white/10',
            'transition-all duration-300 ease-in-out select-none',
            // Mobile: drawer behavior (< 768px)
            isOpen ? 'translate-x-0 w-72' : '-translate-x-full w-72',
            // Desktop: always visible (>= 768px), mini rail or expanded
            'md:translate-x-0 md:relative md:z-auto md:flex-shrink-0',
            isOpen ? 'md:w-72' : 'md:w-20'
        ]"
    >
        <!-- ── Sidebar Header / Brand ─────────────────────────── -->
        <div
            :class="[
                'flex items-center py-5 border-b border-white/40 dark:border-white/10 flex-shrink-0 group cursor-default transition-all duration-300',
                isOpen ? 'px-6 gap-3.5 justify-start' : 'px-2 justify-center'
            ]"
            :title="!isOpen ? 'SAHABAT SDAM' : undefined"
        >
            <div class="w-10 h-10 rounded-2xl bg-primary-100/70 dark:bg-primary-900/40 border border-primary-200/60 dark:border-primary-800/50 flex items-center justify-center p-1.5 shadow-xs flex-shrink-0 group-hover:scale-105 group-hover:rotate-3 transition-transform duration-300 ease-out">
                <img src="/logo.png" alt="Logo SDAM" class="w-full h-full object-contain" />
            </div>
            <div v-show="isOpen" class="leading-tight min-w-0 transition-opacity duration-200">
                <p class="text-base font-bold text-primary-600 dark:text-primary-400 tracking-tight group-hover:text-primary-700 dark:group-hover:text-primary-300 transition-colors">SAHABAT SDAM</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">Presensi &amp; Kebiasaan</p>
            </div>
        </div>

        <!-- ── Navigation Links ──────────────────────────────── -->
        <nav class="flex-1 overflow-y-auto overflow-x-hidden px-2.5 py-4 space-y-1">
            <!-- ── Section: Menu utama ── -->
            <p v-show="isOpen" class="px-4 pt-1 pb-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400 tracking-normal transition-opacity duration-200">
                Menu utama
            </p>

            <!-- Dashboard -->
            <Link
                :href="route('dashboard')"
                :class="navLinkClass('dashboard')"
                :title="!isOpen ? 'Dashboard' : undefined"
                @click="closeSidebar"
            >
                <IconDashboard :class="iconClass('dashboard')" />
                <span v-show="isOpen" class="truncate">Dashboard</span>
            </Link>

            <!-- Al-Qur'an & Hadis -->
            <Link
                :href="route('quran.hadis')"
                :class="navLinkClass('quran.hadis')"
                :title="!isOpen ? 'Al-Qur\'an & Hadis' : undefined"
                @click="closeSidebar"
            >
                <IconQuranHadis :class="iconClass('quran.hadis')" />
                <span v-show="isOpen" class="truncate">Al-Qur'an &amp; Hadis</span>
            </Link>

            <!-- Dzikir Pagi & Petang -->
            <Link
                :href="route('dzikir.index')"
                :class="navLinkClass('dzikir.index')"
                :title="!isOpen ? 'Dzikir Pagi & Petang' : undefined"
                @click="closeSidebar"
            >
                <IconDzikir :class="iconClass('dzikir.index')" />
                <span v-show="isOpen" class="truncate">Dzikir Pagi &amp; Petang</span>
            </Link>

            <!-- HPT Online -->
            <Link
                :href="route('hpt.index')"
                :class="navLinkClass('hpt.*')"
                :title="!isOpen ? 'HPT Online' : undefined"
                @click="closeSidebar"
            >
                <IconHpt :class="iconClass('hpt.*')" />
                <span v-show="isOpen" class="truncate">HPT Online</span>
            </Link>

            <!-- Pesan & Notifikasi -->
            <Link
                :href="route('notifications.index')"
                :class="navLinkClass('notifications.index')"
                :title="!isOpen ? 'Pesan & Notifikasi' : undefined"
                @click="closeSidebar"
            >
                <div class="relative flex items-center justify-center">
                    <IconNotification :class="iconClass('notifications.index')" />
                    <span
                        v-if="!isOpen && $page.props.notifications?.unread_count > 0"
                        class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-rose-500 rounded-full ring-2 ring-white dark:ring-slate-900"
                    />
                </div>
                <span v-show="isOpen" class="flex-1 truncate">Pesan &amp; Notifikasi</span>
                <span
                    v-if="isOpen && $page.props.notifications?.unread_count > 0"
                    class="ml-auto flex items-center justify-center px-2 py-0.5 text-xs font-bold bg-rose-500 text-white rounded-full flex-shrink-0"
                >
                    {{ $page.props.notifications.unread_count }}
                </span>
            </Link>

            <!-- Chat Pegawai -->
            <Link
                :href="route('chat.index')"
                :class="navLinkClass('chat.*')"
                :title="!isOpen ? 'Chat Pegawai' : undefined"
                @click="closeSidebar"
            >
                <IconChat :class="iconClass('chat.*')" />
                <span v-show="isOpen" class="flex-1 truncate">Chat Pegawai</span>
            </Link>

            <!-- Notulen Rapat -->
            <Link
                :href="route('notulen.index')"
                :class="navLinkClass('notulen.*')"
                :title="!isOpen ? 'Notulen Rapat' : undefined"
                @click="closeSidebar"
            >
                <IconNotulen :class="iconClass('notulen.*')" />
                <span v-show="isOpen" class="truncate">Notulen Rapat</span>
            </Link>

            <!-- ── Section: Catatan ibadahku ── -->
            <template v-if="hasRole('user')">
                <p v-show="isOpen" class="px-4 pt-4 pb-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400 tracking-normal transition-opacity duration-200">
                    Catatan ibadahku
                </p>

                <!-- Presensiku -->
                <Link
                    v-if="featureEnabled('feature_presensi')"
                    :href="route('attendance.index')"
                    :class="navLinkClass('attendance.index')"
                    :title="!isOpen ? 'Presensiku' : undefined"
                    @click="closeSidebar"
                >
                    <IconPresensi :class="iconClass('attendance.index')" />
                    <span v-show="isOpen" class="truncate">Presensiku</span>
                </Link>

                <!-- Accordion: Form Isi Habit -->
                <div class="space-y-1" v-if="featureEnabled('feature_habit')">
                    <button
                        type="button"
                        @click="toggleAccordion('formHabit')"
                        :class="accordionButtonClass(isFormHabitOpen)"
                        :title="!isOpen ? 'Form Isi Habit' : undefined"
                    >
                        <IconFormHabit :class="['w-5 h-5 flex-shrink-0 transition-colors', isFormHabitOpen ? 'text-primary-600 dark:text-primary-400' : 'text-slate-400 dark:text-slate-500']" />
                        <span v-show="isOpen" class="truncate">Form Isi Habit</span>
                        <IconChevronDown
                            v-show="isOpen"
                            class="w-4 h-4 ml-auto transition-transform duration-200 text-slate-400"
                            :class="{ 'rotate-180': isFormHabitOpen }"
                        />
                    </button>

                    <Transition
                        enter-active-class="transition-all duration-200 ease-out overflow-hidden"
                        enter-from-class="opacity-0 -translate-y-2 max-h-0"
                        enter-to-class="opacity-100 translate-y-0 max-h-96"
                        leave-active-class="transition-all duration-150 ease-in overflow-hidden"
                        leave-from-class="opacity-100 translate-y-0 max-h-96"
                        leave-to-class="opacity-0 -translate-y-2 max-h-0"
                    >
                        <div v-show="isFormHabitOpen && isOpen" class="ml-4 pl-3 border-l-2 border-primary-500/30 space-y-1 pt-1">
                            <Link
                                :href="route('habit.form')"
                                :class="subNavLinkClass('habit.form')"
                                @click="closeSidebar"
                            >
                                <IconInputHabit :class="subIconClass('habit.form')" />
                                <span>Input Habit Harian</span>
                            </Link>

                            <Link
                                :href="route('quran.recap')"
                                :class="subNavLinkClass('quran.recap')"
                                @click="closeSidebar"
                            >
                                <IconRekapQuran :class="subIconClass('quran.recap')" />
                                <span>Rekap Al-Quran</span>
                            </Link>

                            <Link
                                :href="route('kajian.index')"
                                :class="subNavLinkClass('kajian.index')"
                                @click="closeSidebar"
                            >
                                <IconJurnalKajian :class="subIconClass('kajian.index')" />
                                <span>Jurnal Kajian &amp; Hadits</span>
                            </Link>
                        </div>
                    </Transition>
                </div>

                <!-- Catatan Haid -->
                <Link
                    v-if="isFemale"
                    :href="route('haid.index')"
                    :class="navLinkClass('haid.index')"
                    :title="!isOpen ? 'Catatan Haid' : undefined"
                    @click="closeSidebar"
                >
                    <IconHaid :class="iconClass('haid.index')" />
                    <span v-show="isOpen" class="truncate">Catatan Haid</span>
                    <span v-show="isOpen" class="ml-auto text-xs font-medium px-2 py-0.5 rounded-full bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300 flex-shrink-0">
                        Aktif
                    </span>
                </Link>

                <!-- Jurnal Harian Guru -->
                <Link
                    v-if="!hasAnyRole(['admin', 'superadmin']) && page.props.global_settings?.feature_jurnal !== 'false' && page.props.global_settings?.feature_jurnal !== '0'"
                    :href="route('teacher-journals.index')"
                    :class="navLinkClass('teacher-journals.index')"
                    :title="!isOpen ? 'Jurnal Harian Guru' : undefined"
                    @click="closeSidebar"
                >
                    <IconJurnalGuru :class="iconClass('teacher-journals.index')" />
                    <span v-show="isOpen" class="truncate">Jurnal Harian Guru</span>
                </Link>

                <!-- Administrasi Guru -->
                <Link
                    v-if="!hasAnyRole(['admin', 'superadmin']) && featureEnabled('feature_administrasi_guru')"
                    :href="route('teacher-administrations.index')"
                    :class="navLinkClass('teacher-administrations.*')"
                    :title="!isOpen ? 'Administrasi Guru' : undefined"
                    @click="closeSidebar"
                >
                    <IconAdministrasiGuru :class="iconClass('teacher-administrations.*')" />
                    <span v-show="isOpen" class="truncate">Administrasi Guru</span>
                </Link>

                <!-- Arsip Kepegawaian -->
                <Link
                    v-if="!hasAnyRole(['admin', 'superadmin']) && featureEnabled('feature_arsip')"
                    :href="route('employee-documents.index')"
                    :class="navLinkClass('employee-documents.*')"
                    :title="!isOpen ? 'Arsip Kepegawaian' : undefined"
                    @click="closeSidebar"
                >
                    <IconArsip :class="iconClass('employee-documents.*')" />
                    <span v-show="isOpen" class="truncate">Arsip Kepegawaian</span>
                </Link>

                <!-- Rapor Kinerja (KPI) -->
                <Link
                    v-if="!hasAnyRole(['admin', 'superadmin']) && featureEnabled('feature_kpi')"
                    :href="route('performance.index')"
                    :class="navLinkClass('performance.*')"
                    :title="!isOpen ? 'Rapor Kinerja (KPI)' : undefined"
                    @click="closeSidebar"
                >
                    <IconKpi :class="iconClass('performance.*')" />
                    <span v-show="isOpen" class="truncate">Rapor Kinerja (KPI)</span>
                </Link>
            </template>

            <!-- ── Section: Tata usaha ── -->
            <template v-if="hasAnyRole(['tata_usaha', 'kepala_sekolah', 'superadmin'])">
                <p v-show="isOpen" class="px-4 pt-4 pb-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400 tracking-normal transition-opacity duration-200">
                    Tata usaha
                </p>
                
                <Link
                    :href="route('tata-usaha.surat-masuk.index')"
                    :class="navLinkClass('tata-usaha.surat-masuk.*')"
                    :title="!isOpen ? 'Surat Masuk' : undefined"
                    @click="closeSidebar"
                >
                    <IconSuratMasuk :class="iconClass('tata-usaha.surat-masuk.*')" />
                    <span v-show="isOpen" class="truncate">Surat Masuk</span>
                </Link>

                <Link
                    :href="route('tata-usaha.surat-keluar.index')"
                    :class="navLinkClass('tata-usaha.surat-keluar.*')"
                    :title="!isOpen ? 'Surat Keluar' : undefined"
                    @click="closeSidebar"
                >
                    <IconSuratKeluar :class="iconClass('tata-usaha.surat-keluar.*')" />
                    <span v-show="isOpen" class="truncate">Surat Keluar</span>
                </Link>

                <Link
                    :href="route('tata-usaha.disposisi.index')"
                    :class="navLinkClass('tata-usaha.disposisi.*')"
                    :title="!isOpen ? 'Disposisi' : undefined"
                    @click="closeSidebar"
                >
                    <IconDisposisi :class="iconClass('tata-usaha.disposisi.*')" />
                    <span v-show="isOpen" class="truncate">Disposisi</span>
                </Link>

                <Link
                    :href="route('tata-usaha.settings.index')"
                    :class="navLinkClass('tata-usaha.settings.*')"
                    :title="!isOpen ? 'Pengaturan Surat' : undefined"
                    @click="closeSidebar"
                >
                    <IconPengaturanSurat :class="iconClass('tata-usaha.settings.*')" />
                    <span v-show="isOpen" class="truncate">Pengaturan Surat</span>
                </Link>
            </template>

            <!-- ── Section: Monitoring Guru / Kurikulum ── -->
            <template v-if="(hasRole('kepala_sekolah') || hasRole('kurikulum')) && !hasAnyRole(['admin', 'superadmin'])">
                <p v-show="isOpen" class="px-4 pt-4 pb-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400 tracking-normal transition-opacity duration-200">
                    {{ hasRole('kurikulum') && !hasRole('kepala_sekolah') ? 'Kurikulum' : 'Monitoring guru' }}
                </p>

                <Link
                    v-if="featureEnabled('feature_administrasi_guru')"
                    :href="route('admin.teacher-administrations.index')"
                    :class="navLinkClass('admin.teacher-administrations.*')"
                    :title="!isOpen ? 'Rekap Administrasi Guru' : undefined"
                    @click="closeSidebar"
                >
                    <IconRekapAdministrasiGuru :class="iconClass('admin.teacher-administrations.*')" />
                    <span v-show="isOpen" class="truncate">Rekap Administrasi Guru</span>
                </Link>

                <Link
                    v-if="hasRole('kepala_sekolah')"
                    :href="route('admin.teacher-journals.index')"
                    :class="navLinkClass('admin.teacher-journals.index')"
                    :title="!isOpen ? 'Rekap Jurnal Guru' : undefined"
                    @click="closeSidebar"
                >
                    <IconRekapJurnalGuru :class="iconClass('admin.teacher-journals.index')" />
                    <span v-show="isOpen" class="truncate">Rekap Jurnal Guru</span>
                </Link>
            </template>

            <!-- ── Section: Administrasi (Admin & Superadmin) ── -->
            <template v-if="hasAnyRole(['admin', 'superadmin'])">
                <p v-show="isOpen" class="px-4 pt-4 pb-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400 tracking-normal transition-opacity duration-200">
                    Administrasi
                </p>

                <!-- Rekap Presensi GPS -->
                <Link
                    v-if="featureEnabled('feature_presensi')"
                    :href="route('admin.attendance.index')"
                    :class="navLinkClass('admin.attendance.index')"
                    :title="!isOpen ? 'Rekap Presensi' : undefined"
                    @click="closeSidebar"
                >
                    <IconRekapPresensi :class="iconClass('admin.attendance.index')" />
                    <span v-show="isOpen" class="truncate">Rekap Presensi</span>
                </Link>

                <!-- Accordion: Rekap Habits -->
                <div class="space-y-1" v-if="featureEnabled('feature_habit')">
                    <button
                        type="button"
                        @click="toggleAccordion('rekapHabit')"
                        :class="accordionButtonClass(isRekapHabitOpen)"
                        :title="!isOpen ? 'Rekap Habits' : undefined"
                    >
                        <IconRekapHabit :class="['w-5 h-5 flex-shrink-0 transition-colors', isRekapHabitOpen ? 'text-primary-600 dark:text-primary-400' : 'text-slate-400 dark:text-slate-500']" />
                        <span v-show="isOpen" class="truncate">Rekap Habits</span>
                        <IconChevronDown
                            v-show="isOpen"
                            class="w-4 h-4 ml-auto transition-transform duration-200 text-slate-400"
                            :class="{ 'rotate-180': isRekapHabitOpen }"
                        />
                    </button>

                    <Transition
                        enter-active-class="transition-all duration-200 ease-out overflow-hidden"
                        enter-from-class="opacity-0 -translate-y-2 max-h-0"
                        enter-to-class="opacity-100 translate-y-0 max-h-96"
                        leave-active-class="transition-all duration-150 ease-in overflow-hidden"
                        leave-from-class="opacity-100 translate-y-0 max-h-96"
                        leave-to-class="opacity-0 -translate-y-2 max-h-0"
                    >
                        <div v-show="isRekapHabitOpen && isOpen" class="ml-4 pl-3 border-l-2 border-primary-500/30 space-y-1 pt-1">
                            <Link
                                :href="route('admin.laporan')"
                                :class="subNavLinkClass('admin.laporan')"
                                @click="closeSidebar"
                            >
                                <IconSemuaLaporan :class="subIconClass('admin.laporan')" />
                                <span>Semua Laporan</span>
                            </Link>

                            <Link
                                v-if="hasRole('superadmin')"
                                :href="route('admin.laporan.unfilled')"
                                :class="subNavLinkClass('admin.laporan.unfilled')"
                                @click="closeSidebar"
                            >
                                <IconBelumIsiHabit :class="subIconClass('admin.laporan.unfilled')" />
                                <span>Cek Belum Isi Habit</span>
                            </Link>
                        </div>
                    </Transition>
                </div>

                <!-- Rekap Jurnal Guru -->
                <Link
                    :href="route('admin.teacher-journals.index')"
                    :class="navLinkClass('admin.teacher-journals.index')"
                    :title="!isOpen ? 'Rekap Jurnal Guru' : undefined"
                    @click="closeSidebar"
                >
                    <IconRekapJurnalGuru :class="iconClass('admin.teacher-journals.index')" />
                    <span v-show="isOpen" class="truncate">Rekap Jurnal Guru</span>
                </Link>

                <!-- Rekap Administrasi Guru -->
                <Link
                    v-if="featureEnabled('feature_administrasi_guru')"
                    :href="route('admin.teacher-administrations.index')"
                    :class="navLinkClass('admin.teacher-administrations.*')"
                    :title="!isOpen ? 'Rekap Administrasi Guru' : undefined"
                    @click="closeSidebar"
                >
                    <IconRekapAdministrasiGuru :class="iconClass('admin.teacher-administrations.*')" />
                    <span v-show="isOpen" class="truncate">Rekap Administrasi Guru</span>
                </Link>

                <!-- Master Data Habit -->
                <Link
                    v-if="featureEnabled('feature_habit')"
                    :href="route('admin.habits.index')"
                    :class="navLinkClass('admin.habits.index')"
                    :title="!isOpen ? 'Master Data Habit' : undefined"
                    @click="closeSidebar"
                >
                    <IconMasterHabit :class="iconClass('admin.habits.index')" />
                    <span v-show="isOpen" class="truncate">Master Data Habit</span>
                </Link>

                <!-- Accordion: Manajemen User -->
                <div class="space-y-1">
                    <button
                        type="button"
                        @click="toggleAccordion('manajemenUser')"
                        :class="accordionButtonClass(isManajemenUserOpen)"
                        :title="!isOpen ? 'Manajemen User' : undefined"
                    >
                        <IconManajemenUser :class="['w-5 h-5 flex-shrink-0 transition-colors', isManajemenUserOpen ? 'text-primary-600 dark:text-primary-400' : 'text-slate-400 dark:text-slate-500']" />
                        <span v-show="isOpen" class="truncate">Manajemen User</span>
                        <IconChevronDown
                            v-show="isOpen"
                            class="w-4 h-4 ml-auto transition-transform duration-200 text-slate-400"
                            :class="{ 'rotate-180': isManajemenUserOpen }"
                        />
                    </button>

                    <Transition
                        enter-active-class="transition-all duration-200 ease-out overflow-hidden"
                        enter-from-class="opacity-0 -translate-y-2 max-h-0"
                        enter-to-class="opacity-100 translate-y-0 max-h-[32rem]"
                        leave-active-class="transition-all duration-150 ease-in overflow-hidden"
                        leave-from-class="opacity-100 translate-y-0 max-h-[32rem]"
                        leave-to-class="opacity-0 -translate-y-2 max-h-0"
                    >
                        <div v-show="isManajemenUserOpen && isOpen" class="ml-4 pl-3 border-l-2 border-primary-500/30 space-y-1 pt-1">
                            <!-- Submenu Daftar User -->
                            <Link
                                :href="route('admin.users.index')"
                                :class="subNavLinkClass('admin.users.index')"
                                @click="closeSidebar"
                            >
                                <IconDaftarUser :class="subIconClass('admin.users.index')" />
                                <span>Daftar User</span>
                            </Link>

                            <!-- Submenu Verifikasi Arsip Pegawai -->
                            <Link
                                v-if="featureEnabled('feature_arsip')"
                                :href="route('admin.employee-documents.index')"
                                :class="subNavLinkClass('admin.employee-documents.index')"
                                @click="closeSidebar"
                            >
                                <IconVerifikasiArsip :class="subIconClass('admin.employee-documents.index')" />
                                <span>Verifikasi Arsip Pegawai</span>
                            </Link>

                            <!-- Submenu Evaluasi Kinerja (KPI) -->
                            <Link
                                v-if="featureEnabled('feature_kpi')"
                                :href="route('admin.performance.index')"
                                :class="subNavLinkClass('admin.performance.index')"
                                @click="closeSidebar"
                            >
                                <IconEvaluasiKpi :class="subIconClass('admin.performance.index')" />
                                <span>Evaluasi Kinerja (KPI)</span>
                            </Link>

                            <!-- Submenu Jadwal Piket -->
                            <Link
                                v-if="featureEnabled('feature_presensi')"
                                :href="route('admin.duty-schedules.index')"
                                :class="subNavLinkClass('admin.duty-schedules.index')"
                                @click="closeSidebar"
                            >
                                <IconJadwalPiket :class="subIconClass('admin.duty-schedules.index')" />
                                <span>Jam Kerja Harian &amp; Piket</span>
                            </Link>
                            
                            <!-- Submenu Jadwal Khusus -->
                            <Link
                                v-if="featureEnabled('feature_presensi')"
                                :href="route('admin.special-schedules.index')"
                                :class="subNavLinkClass('admin.special-schedules.index')"
                                @click="closeSidebar"
                            >
                                <IconJadwalKhusus :class="subIconClass('admin.special-schedules.index')" />
                                <span>Jadwal Khusus Tertentu</span>
                            </Link>

                            <!-- Submenu Daftar Divisi -->
                            <Link
                                v-if="featureEnabled('feature_divisi')"
                                :href="route('admin.divisions.index')"
                                :class="subNavLinkClass('admin.divisions.index')"
                                @click="closeSidebar"
                            >
                                <IconDaftarDivisi :class="subIconClass('admin.divisions.index')" />
                                <span>Daftar Divisi</span>
                            </Link>
                        </div>
                    </Transition>
                </div>

                <!-- Pengaturan Sistem -->
                <Link
                    :href="route('admin.settings')"
                    :class="navLinkClass('admin.settings')"
                    :title="!isOpen ? 'Pengaturan Sistem' : undefined"
                    @click="closeSidebar"
                >
                    <IconPengaturanSistem :class="iconClass('admin.settings')" />
                    <span v-show="isOpen" class="truncate">Pengaturan Sistem</span>
                </Link>

                <!-- Buku Panduan -->
                <Link
                    :href="route('panduan')"
                    :class="navLinkClass('panduan')"
                    :title="!isOpen ? 'Buku Panduan' : undefined"
                    @click="closeSidebar"
                >
                    <IconPanduan :class="iconClass('panduan')" />
                    <span v-show="isOpen" class="truncate">Buku Panduan</span>
                </Link>
                
                <template v-if="hasRole('superadmin')">
                    <!-- Fitur HR & Lanjutan -->
                    <Link
                        :href="route('admin.settings.hr')"
                        :class="navLinkClass('admin.settings.hr')"
                        :title="!isOpen ? 'Fitur HR & Lanjutan' : undefined"
                        @click="closeSidebar"
                    >
                        <IconFiturHr :class="iconClass('admin.settings.hr')" />
                        <span v-show="isOpen" class="truncate">Fitur HR &amp; Lanjutan</span>
                    </Link>

                    <!-- Log Akses Login -->
                    <Link
                        :href="route('admin.login-logs')"
                        :class="navLinkClass('admin.login-logs')"
                        :title="!isOpen ? 'Log Akses Login' : undefined"
                        @click="closeSidebar"
                    >
                        <IconLogAkses :class="iconClass('admin.login-logs')" />
                        <span v-show="isOpen" class="truncate">Log Akses Login</span>
                    </Link>

                    <!-- Pemantauan Sistem -->
                    <Link
                        :href="route('admin.system.monitor')"
                        :class="navLinkClass('admin.system.monitor')"
                        :title="!isOpen ? 'Pemantauan Sistem' : undefined"
                        @click="closeSidebar"
                    >
                        <IconPemantauanSistem :class="iconClass('admin.system.monitor')" />
                        <span v-show="isOpen" class="truncate">Pemantauan Sistem</span>
                    </Link>
                </template>
            </template>
        </nav>

        <!-- ── Sidebar Footer (User Chip Card) ────────────────── -->
        <div
            :class="[
                'flex-shrink-0 py-3.5 border-t border-white/40 dark:border-white/10 transition-all duration-300',
                isOpen ? 'px-3.5' : 'px-2 flex justify-center'
            ]"
        >
            <div
                :class="[
                    'flex items-center rounded-2xl bg-white/60 dark:bg-slate-800/60 backdrop-blur-md border border-white/60 dark:border-white/10 shadow-sm hover:shadow-md hover:bg-white/80 dark:hover:bg-slate-800/80 hover:-translate-y-0.5 transition-all duration-200 group cursor-pointer',
                    isOpen ? 'gap-3 px-3 py-2 w-full' : 'p-2 justify-center w-11 h-11'
                ]"
                :title="!isOpen ? (user?.name + ' (' + roleLabel + ')') : undefined"
            >
                <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/50 flex items-center justify-center flex-shrink-0 border border-primary-200 dark:border-primary-700/60 overflow-hidden group-hover:scale-105 transition-transform duration-200">
                    <img v-if="userAvatarUrl" :src="userAvatarUrl" :alt="user?.name" class="w-full h-full object-cover" />
                    <span v-else class="text-xs font-bold text-primary-700 dark:text-primary-300">{{ userInitial }}</span>
                </div>
                <div v-show="isOpen" class="flex-1 min-w-0 transition-opacity duration-200">
                    <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate group-hover:text-primary-700 dark:group-hover:text-primary-400 transition-colors">{{ user?.name }}</p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ roleLabel }}</p>
                </div>
                <span v-show="isOpen" class="relative flex h-2.5 w-2.5 flex-shrink-0" title="Online">
                    <span class="motion-safe:animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500 ring-2 ring-white dark:ring-slate-900"></span>
                </span>
            </div>
        </div>
    </aside>
</template>

<style scoped>
nav a svg,
nav button svg:first-child {
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), color 0.15s ease-out;
}
nav a:hover svg,
nav button:hover svg:first-child {
    transform: scale(1.14) translateY(-1px);
}
nav a:active svg,
nav button:active svg:first-child {
    transform: scale(0.95);
}
@media (prefers-reduced-motion: reduce) {
    nav a,
    nav button,
    nav a svg,
    nav button svg {
        transition: none !important;
        transform: none !important;
    }
}
</style>
