<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { IconKoperasi, IconArsip } from '@/Components/Icons'

const props = defineProps({
    title: {
        type: String,
        default: 'Waroong POS Koperasi',
    },
})

const page = usePage()
const user = page.props.auth?.user || {}
const roles = page.props.auth?.roles || []

// ─── Realtime Clock ───
const currentTime = ref('')
let timer = null
const updateClock = () => {
    const now = new Date()
    currentTime.value = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

// ─── Fullscreen Toggle ───
const isFullscreen = ref(false)
const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().then(() => {
            isFullscreen.value = true
        }).catch(() => {})
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen().then(() => {
                isFullscreen.value = false
            }).catch(() => {})
        }
    }
}

onMounted(() => {
    updateClock()
    timer = setInterval(updateClock, 1000)
})

onUnmounted(() => {
    if (timer) clearInterval(timer)
})
</script>

<template>
    <div class="min-h-screen bg-slate-100 text-slate-800 flex flex-col font-sans select-none antialiased">
        <!-- ── Top Standalone POS Bar (Light Theme) ── -->
        <header class="h-16 bg-white border-b border-slate-200/90 px-4 sm:px-6 flex items-center justify-between flex-shrink-0 z-30 shadow-xs">
            <!-- Brand & Store Info -->
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 text-white flex items-center justify-center shadow-md shadow-emerald-600/20 flex-shrink-0">
                    <IconKoperasi class="w-6 h-6" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-black text-base text-slate-900 tracking-tight">WAROONG KOPERASI</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-200">
                            POS SDAM
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-500 truncate max-w-xs sm:max-w-md">
                        SD Muhammadiyah 1 Krian • Sistem Kasir & Penukaran Voucher Habit
                    </p>
                </div>
            </div>

            <!-- Middle Navigation Links (Waroong POS Mode) -->
            <nav class="hidden md:flex items-center gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200">
                <Link
                    :href="route('koperasi.pos')"
                    class="px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5"
                    :class="route().current('koperasi.pos*') ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-white'"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    <span>Mesin Kasir</span>
                </Link>

                <Link
                    :href="route('koperasi.products')"
                    class="px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5"
                    :class="route().current('koperasi.products*') ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-white'"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                    <span>Katalog Barang</span>
                </Link>

                <Link
                    :href="route('koperasi.stock-opname')"
                    class="px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5"
                    :class="route().current('koperasi.stock-opname*') ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-white'"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                    <span>Stok Opname</span>
                </Link>

                <Link
                    :href="route('koperasi.history')"
                    class="px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5"
                    :class="route().current('koperasi.history*') ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-white'"
                >
                    <IconArsip class="w-4 h-4" />
                    <span>Riwayat Transaksi</span>
                </Link>
            </nav>

            <!-- Right Controls: Cashier, Clock, Fullscreen, Logout -->
            <div class="flex items-center gap-3">
                <!-- Clock -->
                <div class="hidden sm:flex flex-col items-end font-mono">
                    <span class="text-xs font-black text-emerald-700 tracking-wider">{{ currentTime }}</span>
                    <span class="text-[10px] text-slate-400 uppercase">WIB</span>
                </div>

                <!-- Cashier Avatar & Name -->
                <div class="flex items-center gap-2 pl-2 border-l border-slate-200">
                    <div class="w-8 h-8 rounded-lg bg-emerald-100 border border-emerald-300 text-emerald-800 font-black text-xs flex items-center justify-center">
                        {{ user.name?.charAt(0)?.toUpperCase() || 'K' }}
                    </div>
                    <div class="hidden lg:block text-left">
                        <div class="text-xs font-bold text-slate-800 truncate max-w-[130px]">{{ user.name }}</div>
                        <div class="text-[10px] text-emerald-600 font-semibold">Petugas Kasir</div>
                    </div>
                </div>

                <!-- Fullscreen button -->
                <button
                    type="button"
                    @click="toggleFullscreen"
                    class="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-900 transition-colors border border-slate-200"
                    title="Toggle Layar Penuh"
                >
                    <svg v-if="!isFullscreen" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                    <svg v-else class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>

                <!-- Back to Habit App if has other roles -->
                <Link
                    v-if="roles.includes('superadmin') || roles.includes('admin') || roles.includes('user')"
                    :href="route('dashboard')"
                    class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors border border-slate-200"
                    title="Kembali ke Dashboard Utama"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    <span>App Utama</span>
                </Link>

                <!-- Logout -->
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="p-2 rounded-lg bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-600 transition-colors"
                    title="Keluar dari Sistem POS"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                </Link>
            </div>
        </header>

        <!-- ── Main POS Workspace ── -->
        <main class="flex-1 overflow-hidden relative flex flex-col bg-slate-100">
            <slot />
        </main>
    </div>
</template>
