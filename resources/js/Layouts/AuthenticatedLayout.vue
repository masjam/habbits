<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { usePWA } from '@/Composables/usePWA'
import Sidebar from '@/Layouts/Sidebar.vue'
import Topbar from '@/Layouts/Topbar.vue'

// ─── Inertia Page Props ─────────────────────────────────────────────────────
const page    = usePage()
const user    = computed(() => page.props.auth?.user ?? {})
const roles   = computed(() => page.props.auth?.roles ?? [])

// ─── UI State ───────────────────────────────────────────────────────────────
const isSidebarOpen  = ref(false)
const closeSidebar  = () => { isSidebarOpen.value  = false }
const toggleSidebar = () => { isSidebarOpen.value = !isSidebarOpen.value }

// ─── PWA ───────────────────────────────────────────────────────────────────
const { isOffline, isInstallable, promptInstall, listenInstallPrompt, listenNetworkStatus } = usePWA()
const offlineDismissed = ref(false)

onMounted(() => {
    listenInstallPrompt()
    listenNetworkStatus()
})
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-transparent">

        <!-- ═══ OFFLINE BANNER ══════════════════════════════════════ -->
        <Transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="-translate-y-full opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="-translate-y-full opacity-0"
        >
            <div v-if="isOffline && !offlineDismissed"
                class="fixed top-0 inset-x-0 z-[100] bg-amber-500 text-white px-4 py-2.5 flex items-center justify-between shadow-lg text-sm font-medium"
            >
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636a9 9 0 010 12.728M15.536 8.464a5 5 0 010 7.072M12 12h.01M8.464 15.536a5 5 0 010-7.072M5.636 18.364a9 9 0 010-12.728" />
                    </svg>
                    <span>Anda sedang offline. Data mungkin belum terbaru.</span>
                </div>
                <button @click="offlineDismissed = true" class="ml-4 opacity-80 hover:opacity-100 flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </Transition>

        <!-- ═══ INSTALL PROMPT BANNER ════════════════════════════════ -->
        <Transition
            enter-active-class="transition-all duration-500 ease-out"
            enter-from-class="translate-y-full opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition-all duration-300 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-full opacity-0"
        >
            <div v-if="isInstallable"
                class="fixed bottom-4 inset-x-4 z-[100] md:left-auto md:right-4 md:max-w-sm"
            >
                <div class="bg-slate-800 text-white rounded-2xl shadow-2xl p-4 flex items-center gap-3">
                    <img src="/logo.png" class="w-10 h-10 rounded-xl object-contain bg-emerald-600 p-1 flex-shrink-0" alt="Logo" />
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-sm">Install Aplikasi</p>
                        <p class="text-xs text-slate-300 dark:text-slate-600 mt-0.5">Tambahkan ke layar utama untuk akses lebih cepat</p>
                    </div>
                    <div class="flex flex-col gap-1 flex-shrink-0">
                        <button @click="promptInstall" class="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-400 text-white text-xs font-bold rounded-lg transition-colors">
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

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto bg-transparent p-4 sm:p-6 lg:p-8">
                <div class="mx-auto max-w-7xl">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
