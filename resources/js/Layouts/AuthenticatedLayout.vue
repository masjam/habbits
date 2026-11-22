<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { usePage, Link, router } from '@inertiajs/vue3'
import { usePWA } from '@/Composables/usePWA'
import { useToast } from '@/Composables/useToast'
import Sidebar from '@/Layouts/Sidebar.vue'
import Topbar from '@/Layouts/Topbar.vue'
import RoleSimulatorBar from '@/Components/RoleSimulatorBar.vue'
import ToastContainer from '@/Components/UI/ToastContainer.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'

const resetRole = () => {
    router.post(route('admin.maintenance.switch-role'), {
        role: 'superadmin'
    })
}

// ─── Inertia Page Props ─────────────────────────────────────────────────────
const page    = usePage()
const user    = computed(() => page.props.auth?.user ?? {})
const roles   = computed(() => page.props.auth?.roles ?? [])

// ─── UI State ───────────────────────────────────────────────────────────────
const isSidebarOpen  = ref(true) // Default to open on desktop
const closeSidebar  = () => { isSidebarOpen.value  = false }
const toggleSidebar = () => { isSidebarOpen.value = !isSidebarOpen.value }

// ─── PWA ───────────────────────────────────────────────────────────────────
const { 
    isOffline, 
    isInstallable, 
    isPushSupported, 
    isPushSubscribed, 
    pushPermission, 
    isSubscribingPush, 
    subscribeToPush, 
    promptInstall, 
    listenInstallPrompt, 
    listenNetworkStatus 
} = usePWA()

const offlineDismissed = ref(false)
const pushBannerDismissed = ref(false)

const showPushBanner = computed(() => {
    if (pushBannerDismissed.value) return false
    if (!page.props.webpush?.is_active) return false
    if (!isPushSupported.value) return false
    if (isPushSubscribed.value) return false
    if (pushPermission.value === 'denied') return false
    if (typeof localStorage !== 'undefined' && localStorage.getItem('habit_push_banner_dismissed')) return false
    return true
})

const enablePushFromBanner = async () => {
    try {
        const vapidKey = page.props.webpush?.vapid_public_key || ''
        await subscribeToPush(vapidKey)
        pushBannerDismissed.value = true
    } catch (err) {
        alert(err.message || 'Gagal mengaktifkan notifikasi.')
    }
}

const dismissPushBanner = () => {
    pushBannerDismissed.value = true
    if (typeof localStorage !== 'undefined') {
        localStorage.setItem('habit_push_banner_dismissed', '1')
    }
}

// ─── Global Flash Messages to Toast ──────────────────────────────────────────
const toast = useToast()
watch(() => page.props.flash, (flash) => {
    if (!flash) return
    if (flash.success) toast.success(flash.success)
    if (flash.error) toast.error(flash.error)
    if (flash.warning) toast.warning(flash.warning)
    if (flash.info) toast.info(flash.info)
    if (flash.message) toast.info(flash.message)
}, { immediate: true, deep: true })

onMounted(() => {
    if (typeof window !== 'undefined' && window.innerWidth < 768) {
        isSidebarOpen.value = false
    }
    listenInstallPrompt()
    listenNetworkStatus()

    // ─── Listen export selesai via Reverb ──────────────────────────────────
    if (user.value?.id && window.Echo) {
        window.Echo.private(`exports.${user.value.id}`)
            .listen('.ready', (e) => {
                toast.success(
                    `${e.label} siap diunduh.`,
                    'File Siap',
                    0, // tidak auto-dismiss — user harus klik download
                    { label: `Unduh ${e.label}`, url: e.fileUrl }
                )
            })
    }
})
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-transparent select-auto">

        <!-- ═══ OFFLINE BANNER ══════════════════════════════════════ -->
        <Transition
            enter-active-class="transition-transform transition-opacity duration-300 ease-out"
            enter-from-class="-translate-y-full opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition-transform transition-opacity duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="-translate-y-full opacity-0"
        >
            <div v-if="isOffline && !offlineDismissed"
                class="fixed top-0 inset-x-0 z-[100] bg-amber-500 text-white px-4 py-2.5 flex items-center justify-between shadow-lg text-sm font-medium"
            >
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636a9 9 0 010 12.728M15.536 8.464a5 5 0 010 7.072M12 12h.01M8.464 15.536a5 5 0 010-7.072M5.636 18.364a9 9 0 010-12.728" />
                    </svg>
                    <span>Anda sedang offline. Data mungkin belum terbaru.</span>
                </div>
                <button type="button" @click="offlineDismissed = true" class="ml-4 opacity-80 hover:opacity-100 flex-shrink-0 cursor-pointer" aria-label="Tutup pemberitahuan offline">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </Transition>

        <!-- ═══ PUSH NOTIFICATION PROMPT BANNER ══════════════════════ -->
        <Transition
            enter-active-class="transition-transform transition-opacity duration-300 ease-out"
            enter-from-class="-translate-y-full opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition-transform transition-opacity duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="-translate-y-full opacity-0"
        >
            <div v-if="showPushBanner && !isOffline"
                class="fixed top-0 inset-x-0 z-[95] bg-primary-600 text-white px-4 py-2.5 flex items-center justify-between shadow-md text-xs sm:text-sm font-medium"
            >
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-6 h-6 rounded-lg bg-white/20 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                    <span class="truncate">Aktifkan pengingat ibadah &amp; presensi harian langsung ke layar perangkat Anda.</span>
                </div>
                <div class="flex items-center gap-2 shrink-0 ml-3">
                    <button 
                        type="button"
                        @click="enablePushFromBanner" 
                        :disabled="isSubscribingPush" 
                        class="px-3 py-1 rounded-full bg-white hover:bg-primary-50 text-primary-900 font-bold text-xs shadow-xs transition-colors cursor-pointer disabled:opacity-50 whitespace-nowrap"
                    >
                        {{ isSubscribingPush ? 'Memproses...' : 'Aktifkan' }}
                    </button>
                    <button 
                        type="button"
                        @click="dismissPushBanner" 
                        class="p-1 rounded-xl text-white/80 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
                        title="Tutup banner"
                        aria-label="Tutup banner notifikasi"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </Transition>

        <!-- ═══ INSTALL PROMPT BANNER (M3 Surface Card) ═════════════ -->
        <Transition
            enter-active-class="transition-transform transition-opacity duration-300 ease-out"
            enter-from-class="translate-y-full opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition-transform transition-opacity duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-full opacity-0"
        >
            <div v-if="isInstallable"
                class="fixed bottom-4 inset-x-4 z-[100] md:left-auto md:right-4 md:max-w-sm"
            >
                <div class="bg-slate-900 text-white rounded-2xl shadow-2xl p-4 border border-slate-700/80 flex items-center gap-3.5">
                    <img src="/img/icon-192.png" class="w-11 h-11 rounded-2xl object-contain bg-white dark:bg-slate-800 p-1 shadow-xs flex-shrink-0" alt="SDAM Logo" />
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-sm">Install Aplikasi</p>
                        <p class="text-xs text-slate-300 mt-0.5">Akses presensi &amp; catatan lebih cepat dari beranda</p>
                    </div>
                    <div class="flex flex-col gap-1 flex-shrink-0">
                        <button type="button" @click="promptInstall" class="px-3.5 py-1.5 bg-primary-500 hover:bg-primary-400 text-white text-xs font-bold rounded-full transition-colors cursor-pointer shadow-xs">
                            Install
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- MOBILE BACKDROP -->
        <Transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isSidebarOpen"
                class="fixed inset-0 z-30 bg-slate-900/50 backdrop-blur-sm md:hidden"
                @click="closeSidebar"
                aria-hidden="true"
            />
        </Transition>

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- SIDEBAR -->
        <Sidebar 
            :isOpen="isSidebarOpen"
            :user="user"
            :roles="roles"
            @close="closeSidebar"
        />

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- MAIN CONTENT AREA -->
        <div class="flex flex-col flex-1 min-w-0 overflow-hidden">
            <!-- TOPBAR -->
            <Topbar 
                :user="user"
                :roles="roles"
                @toggleSidebar="toggleSidebar"
            />

            <!-- ═══ BANNER MAINTENANCE MODE AKTIF (KHUSUS SUPERADMIN) ═══ -->
            <div
                v-if="page.props.auth?.is_actual_superadmin && (page.props.global_settings?.maintenance_mode === '1' || page.props.global_settings?.maintenance_mode === 'true' || page.props.global_settings?.maintenance_mode === true)"
                class="bg-amber-600 text-white px-4 py-2 sm:py-2.5 flex items-center justify-between text-xs sm:text-sm font-semibold shadow-inner z-10"
            >
                <div class="flex items-center gap-2 min-w-0">
                    <span class="relative flex h-2.5 w-2.5 flex-shrink-0">
                        <span class="motion-safe:animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-200 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-100"></span>
                    </span>
                    <span class="truncate">
                        Mode Maintenance sedang <strong class="font-bold">aktif</strong>.
                        <template v-if="page.props.auth?.simulated_role">
                            (Simulasi Peran: <span class="underline font-bold">{{ page.props.auth.simulated_role }}</span>)
                        </template>
                    </span>
                </div>
                <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0 ml-3">
                    <a :href="route('maintenance')" target="_blank" class="underline hover:text-amber-100 text-xs font-bold hidden md:inline">
                        Pratinjau Hal. Maintenance
                    </a>
                    <Link
                        v-if="page.props.auth?.simulated_role !== 'user'"
                        :href="route('admin.settings.hr')"
                        class="px-2.5 py-1 bg-amber-800/90 hover:bg-amber-900 rounded-xl text-xs font-black text-white transition-colors"
                    >
                        Kelola
                    </Link>
                    <button
                        v-else
                        @click="resetRole"
                        type="button"
                        class="px-2.5 py-1 bg-slate-900/90 hover:bg-slate-950 rounded-xl text-xs font-black text-amber-300 border border-amber-400/40 transition-colors shadow-sm cursor-pointer"
                    >
                        Kembali ke Super Admin
                    </button>
                </div>
            </div>

            <!-- Main Content Canvas (Material 3 tonal surface) -->
            <main class="flex-1 overflow-y-auto bg-transparent p-4 sm:p-6">
                <div class="max-w-7xl mx-auto w-full">
                    <slot />
                </div>
            </main>
        </div>

        <!-- ═══ FLOATING ROLE SIMULATOR BAR (KHUSUS SUPERADMIN DI MODE MAINTENANCE) ═══ -->
        <RoleSimulatorBar />

        <!-- ═══ GLOBAL TOAST & CONFIRM DIALOG ═══ -->
        <ToastContainer />
        <ConfirmDialog />
    </div>
</template>
