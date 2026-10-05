<script setup>
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false
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

const emit = defineEmits(['close'])

const closeSidebar = () => {
    emit('close')
}

// ─── Role & Gender Helpers ──────────────────────────────────────────────────
const hasRole = (role) => props.roles.includes(role)
const hasAnyRole = (roleArray) => roleArray.some(r => props.roles.includes(r))
const isFemale = computed(() => props.user?.gender === 'P')

const roleLabel = computed(() => {
    if (hasRole('superadmin')) return 'Super Admin'
    if (hasRole('admin'))      return 'Admin'
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

// ─── Active Route Helper (via Ziggy) ────────────────────────────────────────
const isActive = (routeName) => {
    try { return route().current(routeName) } catch { return false }
}

// ─── Nav Link Class Builder ──────────────────────────────────────────────────
const navLinkClass = (routeName) => [
    'group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium',
    'transition-all duration-150 w-full',
    isActive(routeName)
        ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400 font-semibold'
        : 'text-slate-600 dark:text-slate-400 hover:bg-emerald-50 dark:hover:bg-primary-950/40 hover:text-emerald-700 dark:hover:text-emerald-400',
]

// ─── Feature Flags (dari global_settings yang di-share via Inertia) ─────────
const page = usePage()
const featureEnabled = (key) => {
    const val = page.props.global_settings?.[key]
    if (['feature_presensi', 'feature_arsip', 'feature_kpi', 'feature_habit'].includes(key)) {
        return val === undefined || val === '1' || val === 'true' || val === true
    }
    return val === '1' || val === 'true' || val === true
}

// ─── Menu Toggle States ──────────────────────────────────────────────────────
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
</script>

<template>
    <aside
        :class="[
            'fixed inset-y-0 left-0 z-40 flex w-64 flex-col',
            'bg-sidebar shadow-xs',
            'border-r border-theme',
            'transition-all duration-300 ease-in-out',
            isOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0 md:-ml-64',
            'md:relative md:z-auto md:flex-shrink-0',
        ]"
    >
        <!-- ── Sidebar Header / Brand ─────────────────────────── -->
        <div class="flex items-center gap-3 px-5 py-5 border-b border-subtle flex-shrink-0">
            <img src="/logo.png" alt="Logo SDAM" class="w-9 h-9 object-contain flex-shrink-0" />
            <div class="leading-none">
                <p class="text-[15px] font-bold text-emerald-600 tracking-tight">HabitTracker</p>
                <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">Sistem Pantauan Ibadah</p>
            </div>
        </div>

        <!-- ── Navigation Links ──────────────────────────────── -->
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">
            <!-- ── Section: Menu Utama ── -->
            <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">
                Menu Utama
            </p>

            <!-- A. Dashboard — Semua Role -->
            <Link
                :href="route('dashboard')"
                :class="navLinkClass('dashboard')"
                @click="closeSidebar"
            >
                <svg
                    :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('dashboard') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span>Dashboard</span>
            </Link>

            <!-- Al-Qur'an & Hadis — Semua Role -->
            <Link
                :href="route('quran.hadis')"
                :class="navLinkClass('quran.hadis')"
                @click="closeSidebar"
            >
                <svg
                    :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('quran.hadis') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
                <span>Al-Qur'an & Hadis</span>
            </Link>

            <!-- Dzikir Pagi & Petang — Semua Role -->
            <Link
                :href="route('dzikir.index')"
                :class="navLinkClass('dzikir.index')"
                @click="closeSidebar"
            >
                <svg
                    :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('dzikir.index') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                </svg>
                <span>Dzikir Pagi &amp; Petang</span>
            </Link>

            <!-- Pesan & Notifikasi — Semua Role -->
            <Link
                :href="route('notifications.index')"
                :class="navLinkClass('notifications.index')"
                @click="closeSidebar"
            >
                <svg
                    :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('notifications.index') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <span>Pesan &amp; Notifikasi</span>
            </Link>

            <!-- Presensi GPS — Semua Role (bisa diakses pegawai & admin) -->
           
            <!-- Jurnal Harian Guru -->
            <Link
                v-if="!hasAnyRole(['admin', 'superadmin']) && page.props.global_settings?.feature_jurnal !== 'false' && page.props.global_settings?.feature_jurnal !== '0'"
                :href="route('teacher-journals.index')"
                :class="navLinkClass('teacher-journals.index')"
                @click="closeSidebar"
            >
                <svg
                    :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('teacher-journals.*') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                <span>Jurnal Harian Guru</span>
            </Link>

            <!-- Arsip Saya -->
            <Link
                v-if="!hasAnyRole(['admin', 'superadmin']) && featureEnabled('feature_arsip')"
                :href="route('employee-documents.index')"
                :class="navLinkClass('employee-documents.*')"
                @click="closeSidebar"
            >
                <svg
                    :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('employee-documents.*') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Arsip Kepegawaian</span>
            </Link>

            <!-- Rapor Kinerja (KPI) -->
            <Link
                v-if="!hasAnyRole(['admin', 'superadmin']) && featureEnabled('feature_kpi')"
                :href="route('performance.index')"
                :class="navLinkClass('performance.*')"
                @click="closeSidebar"
            >
                <svg
                    :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('performance.*') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
                <span>Rapor Kinerja (KPI)</span>
            </Link>

            <!-- Arah Kiblat (Hanya Mobile) -->
            <!-- <Link
                :href="route('qibla.index')"
                :class="[...navLinkClass('qibla.index'), 'md:hidden']"
                @click="closeSidebar"
            >
                <svg 
                    :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('qibla.index') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']" 
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 2L15 8H9L12 2Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 12L15 8" />
                </svg>
                <span>Arah Kiblat</span>
            </Link> -->

            <!-- B. Menu Khusus Pegawai (role: user) -->
            <template v-if="hasRole('user')">
                <div class="pt-3 pb-1">
                    <p class="px-3 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">
                        Catatan Ibadahku
                    </p>
                </div>
                 <Link
                    v-if="featureEnabled('feature_presensi')"
                    :href="route('attendance.index')"
                    :class="navLinkClass('attendance.index')"
                    @click="closeSidebar"
                >
                    <svg
                        :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('attendance.index') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Mobile Presensi</span>
                </Link>

                <Link
                    v-if="featureEnabled('feature_habit')"
                    :href="route('habit.form')"
                    :class="navLinkClass('habit.form')"
                    @click="closeSidebar"
                >
                    <svg
                        :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('habit.form') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Form Isi Habit</span>
                </Link>

                <Link
                    :href="route('quran.recap')"
                    :class="navLinkClass('quran.recap')"
                    @click="closeSidebar"
                >
                    <svg
                        :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('quran.recap') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <span>Rekap Al-Quran</span>
                </Link>

                <Link
                    :href="route('kajian.index')"
                    :class="navLinkClass('kajian.index')"
                    @click="closeSidebar"
                >
                    <svg
                        :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('kajian.index') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                    <span>Jurnal Kajian & Hadits</span>
                </Link>

                <Link
                    v-if="isFemale"
                    :href="route('haid.index')"
                    :class="navLinkClass('haid.index')"
                    @click="closeSidebar"
                >
                    <svg
                        :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('haid.index') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4c0 0-6 5.5-6 8.5a6 6 0 0012 0C18 9.5 12 4 12 4z" />
                    </svg>
                    <span>Catatan Haid</span>
                    <span class="ml-auto text-[10px] font-medium px-1.5 py-0.5 rounded-full bg-pink-100 text-pink-600">
                        Aktif
                    </span>
                </Link>
            </template>

            <!-- D. Menu Tata Usaha (tata_usaha & superadmin) -->
            <template v-if="hasAnyRole(['tata_usaha', 'superadmin'])">
                <div class="pt-3 pb-1">
                    <p class="px-3 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">
                        Tata Usaha
                    </p>
                </div>
                
                <Link
                    :href="route('tata-usaha.surat-masuk.index')"
                    :class="navLinkClass('tata-usaha.surat-masuk.*')"
                    @click="closeSidebar"
                >
                    <svg
                        :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('tata-usaha.surat-masuk.*') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <span>Surat Masuk</span>
                </Link>

                <Link
                    :href="route('tata-usaha.surat-keluar.index')"
                    :class="navLinkClass('tata-usaha.surat-keluar.*')"
                    @click="closeSidebar"
                >
                    <svg
                        :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('tata-usaha.surat-keluar.*') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                    <span>Surat Keluar</span>
                </Link>

                <Link
                    :href="route('tata-usaha.disposisi.index')"
                    :class="navLinkClass('tata-usaha.disposisi.*')"
                    @click="closeSidebar"
                >
                    <svg
                        :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('tata-usaha.disposisi.*') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                    <span>Disposisi</span>
                </Link>

                <Link
                    :href="route('tata-usaha.settings.index')"
                    :class="navLinkClass('tata-usaha.settings.*')"
                    @click="closeSidebar"
                >
                    <svg
                        :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('tata-usaha.settings.*') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Pengaturan Surat</span>
                </Link>
            </template>

            <!-- C. Menu Administrasi (Admin & Superadmin) -->
            <template v-if="hasAnyRole(['admin', 'superadmin'])">
                <div class="pt-3 pb-1">
                    <p class="px-3 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">
                        Administrasi
                    </p>
                </div>

                <!-- Rekap Presensi GPS -->
                <Link
                    v-if="featureEnabled('feature_presensi')"
                    :href="route('admin.attendance.index')"
                    :class="navLinkClass('admin.attendance.index')"
                    @click="closeSidebar"
                >
                    <svg
                        :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('admin.attendance.index') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                    <span>Rekap Presensi </span>
                </Link>

                <div class="space-y-0.5">
                    <button
                        v-if="featureEnabled('feature_habit')"
                        @click="isRekapHabitOpen = !isRekapHabitOpen"
                        :class="[
                            'w-full flex items-center gap-3 px-3 py-2.5 rounded-2xl text-sm font-semibold transition-all group',
                            isRekapHabitOpen ? 'bg-emerald-50/80 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800'
                        ]"
                    >
                        <svg
                            :class="['w-5 h-5 flex-shrink-0 transition-colors', isRekapHabitOpen ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <span>Rekap Habits</span>
                        <svg
                            class="w-4 h-4 ml-auto transition-transform duration-200"
                            :class="{ 'rotate-180': isRekapHabitOpen }"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div v-show="isRekapHabitOpen" class="ml-5 pl-2 border-l-2 border-subtle space-y-0.5 mt-1">
                        <Link
                            v-if="featureEnabled('feature_habit')"
                            :href="route('admin.laporan')"
                            :class="[...navLinkClass('admin.laporan'), 'text-xs py-2']"
                            @click="closeSidebar"
                        >
                            <svg class="w-4 h-4 flex-shrink-0 transition-colors" :class="isActive('admin.laporan') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                            <span>Semua Laporan</span>
                        </Link>

                        <Link
                            v-if="hasRole('superadmin') && featureEnabled('feature_habit')"
                            :href="route('admin.laporan.unfilled')"
                            :class="[...navLinkClass('admin.laporan.unfilled'), 'text-xs py-2']"
                            @click="closeSidebar"
                        >
                            <svg class="w-4 h-4 flex-shrink-0 transition-colors" :class="isActive('admin.laporan.unfilled') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span>Cek Belum Isi Habit</span>
                        </Link>
                    </div>
                </div>

                <Link
                    :href="route('admin.teacher-journals.index')"
                    :class="navLinkClass('admin.teacher-journals.index')"
                    @click="closeSidebar"
                >
                    <svg
                        :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('admin.teacher-journals.index') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <span>Rekap Jurnal Guru</span>
                </Link>

                <Link
                    v-if="featureEnabled('feature_habit')"
                    :href="route('admin.habits.index')"
                    :class="navLinkClass('admin.habits.index')"
                    @click="closeSidebar"
                >
                    <svg
                        :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('admin.habits.index') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                    </svg>
                    <span>Master Data Habit</span>
                </Link>

                <div class="space-y-0.5">
                    <button
                        @click="isManajemenUserOpen = !isManajemenUserOpen"
                        :class="[
                            'w-full flex items-center gap-3 px-3 py-2.5 rounded-2xl text-sm font-semibold transition-all group',
                            isManajemenUserOpen ? 'bg-emerald-50/80 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800'
                        ]"
                    >
                        <svg
                            :class="['w-5 h-5 flex-shrink-0 transition-colors', isManajemenUserOpen ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span>Manajemen User</span>
                        <svg
                            class="w-4 h-4 ml-auto transition-transform duration-200"
                            :class="{ 'rotate-180': isManajemenUserOpen }"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div v-show="isManajemenUserOpen" class="ml-5 pl-2 border-l-2 border-subtle space-y-0.5 mt-1">
                        <!-- Submenu Daftar User -->
                        <Link
                            :href="route('admin.users.index')"
                            :class="[...navLinkClass('admin.users.index'), 'text-xs py-2']"
                            @click="closeSidebar"
                        >
                            <svg class="w-4 h-4 flex-shrink-0 transition-colors" :class="isActive('admin.users.index') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span>Daftar User</span>
                        </Link>

                        <!-- Submenu Verifikasi Arsip Pegawai -->
                        <Link
                            v-if="featureEnabled('feature_arsip')"
                            :href="route('admin.employee-documents.index')"
                            :class="[...navLinkClass('admin.employee-documents.index'), 'text-xs py-2']"
                            @click="closeSidebar"
                        >
                            <svg class="w-4 h-4 flex-shrink-0 transition-colors" :class="isActive('admin.employee-documents.index') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>Verifikasi Arsip Pegawai</span>
                        </Link>

                        <!-- Submenu Evaluasi Kinerja (KPI) -->
                        <Link
                            v-if="featureEnabled('feature_kpi')"
                            :href="route('admin.performance.index')"
                            :class="[...navLinkClass('admin.performance.index'), 'text-xs py-2']"
                            @click="closeSidebar"
                        >
                            <svg class="w-4 h-4 flex-shrink-0 transition-colors" :class="isActive('admin.performance.index') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            <span>Evaluasi Kinerja (KPI)</span>
                        </Link>

                        <!-- Submenu Jadwal Piket -->
                        <Link
                            v-if="featureEnabled('feature_presensi')"
                            :href="route('admin.duty-schedules.index')"
                            :class="[...navLinkClass('admin.duty-schedules.index'), 'text-xs py-2']"
                            @click="closeSidebar"
                        >
                            <svg class="w-4 h-4 flex-shrink-0 transition-colors" :class="isActive('admin.duty-schedules.index') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>Jam Kerja Harian & Piket</span>
                        </Link>
                        
                        <!-- Submenu Jadwal Khusus -->
                        <Link
                            v-if="featureEnabled('feature_presensi')"
                            :href="route('admin.special-schedules.index')"
                            :class="[...navLinkClass('admin.special-schedules.index'), 'text-xs py-2']"
                            @click="closeSidebar"
                        >
                            <svg class="w-4 h-4 flex-shrink-0 transition-colors" :class="isActive('admin.special-schedules.index') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Jadwal Khusus Tanggal Tertentu</span>
                        </Link>

                        <!-- Submenu Daftar Divisi — hanya jika fitur Divisi aktif -->
                        <Link
                            v-if="featureEnabled('feature_divisi')"
                            :href="route('admin.divisions.index')"
                            :class="[...navLinkClass('admin.divisions.index'), 'text-xs py-2']"
                            @click="closeSidebar"
                        >
                            <svg class="w-4 h-4 flex-shrink-0 transition-colors" :class="isActive('admin.divisions.index') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <span>Daftar Divisi</span>
                        </Link>
                    </div>
                </div>

              

                <Link
                    :href="route('admin.settings')"
                    :class="navLinkClass('admin.settings')"
                    @click="closeSidebar"
                >
                    <svg
                        :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('admin.settings') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Pengaturan Sistem</span>
                </Link>
                
                <template v-if="hasRole('superadmin')">
                    <Link
                        :href="route('admin.settings.hr')"
                        :class="navLinkClass('admin.settings.hr')"
                        @click="closeSidebar"
                    >
                        <svg
                            :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('admin.settings.hr') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span>Fitur HR & Lanjutan</span>
                    </Link>
                </template>

                <template v-if="hasRole('superadmin')">
                    <Link
                        :href="route('admin.login-logs')"
                        :class="navLinkClass('admin.login-logs')"
                        @click="closeSidebar"
                    >
                        <svg
                            :class="['w-5 h-5 flex-shrink-0 transition-colors', isActive('admin.login-logs') ? 'text-emerald-600' : 'text-slate-400 dark:text-slate-500 group-hover:text-emerald-500']"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Log Akses Login</span>
                    </Link>


                </template>
            </template>
        </nav>

        <!-- ── Sidebar Footer (mini user card) ───────────────── -->
        <div class="flex-shrink-0 px-3 py-4 border-t border-subtle">
            <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-card-subtle border border-theme/60">
                <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center flex-shrink-0 border-2 border-emerald-200 dark:border-emerald-700 overflow-hidden">
                    <img v-if="userAvatarUrl" :src="userAvatarUrl" class="w-full h-full object-cover" />
                    <span v-else class="text-xs font-bold text-emerald-700 dark:text-emerald-400">{{ userInitial }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-slate-700 dark:text-slate-200 truncate">{{ user?.name }}</p>
                    <p class="text-[10px] text-slate-400 dark:text-slate-500 truncate">{{ roleLabel }}</p>
                </div>
                <span class="w-2 h-2 rounded-full bg-emerald-500 flex-shrink-0 ring-2 ring-white dark:ring-slate-900" />
            </div>
        </div>
    </aside>
</template>
