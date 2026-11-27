<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { IconKoperasi } from '@/Components/Icons'

const props = defineProps({
    title: {
        type: String,
        default: 'POS Koperasi Rabbani',
    },
})

const page = usePage()
const user = page.props.auth?.user || {}
const roles = page.props.auth?.roles || []
const hasMainApp = roles.includes('superadmin') || roles.includes('admin') || roles.includes('user')

const isSidebarCollapsed = ref(false)

const sidebarGroups = computed(() => {
    const groups = [
        {
            key: 'pos',
            title: 'POS',
            items: [
                { icon: 'pos', label: 'Mesin Kasir', desc: 'Transaksi penjualan & voucher', routeName: 'koperasi.pos', pattern: 'koperasi.pos*' },
            ],
        },
        {
            key: 'manajemen',
            title: 'Manajemen',
            items: [
                { icon: 'products', label: 'Katalog Barang', desc: 'Kelola produk, harga & stok', routeName: 'koperasi.products', pattern: 'koperasi.products*' },
                { icon: 'discounts', label: 'Diskon & Promo', desc: 'Atur promo event, member & kategori', routeName: 'koperasi.discounts', pattern: 'koperasi.discounts*' },
                { icon: 'opname', label: 'Stok Opname', desc: 'Audit & penyesuaian stok fisik', routeName: 'koperasi.stock-opname', pattern: 'koperasi.stock-opname*' },
                { icon: 'history', label: 'Riwayat Transaksi', desc: 'Arsip belanja, cetak struk & void', routeName: 'koperasi.history', pattern: 'koperasi.history*' },
                { icon: 'reports', label: 'Laporan Koperasi', desc: 'Laba/rugi, perubahan stok & member', routeName: 'koperasi.reports', pattern: 'koperasi.reports*' },
                { icon: 'settings', label: 'Identitas Koperasi', desc: 'Nama toko, alamat, telp & footer struk', routeName: 'koperasi.settings', pattern: 'koperasi.settings*' },
            ],
        },
        {
            key: 'member',
            title: 'Member',
            items: [
                { icon: 'members', label: 'Member Koperasi', desc: 'Pegawai, wali murid, siswa & topup', routeName: 'koperasi.members', pattern: 'koperasi.members*' },
                { icon: 'bon', label: 'BON / Piutang', desc: 'Catatan kasbon & pelunasan', routeName: 'koperasi.bon', pattern: 'koperasi.bon*' },
            ],
        },
    ]

    if (hasMainApp) {
        groups.push({
            key: 'home',
            title: 'Home',
            items: [
                { icon: 'home', label: 'Dashboard Utama', desc: 'Kembali ke aplikasi utama', routeName: 'dashboard', pattern: 'dashboard' },
            ],
        })
    }

    return groups
})

// ─── Mobile Bottom Nav Sheet ───
const activeMobileSheet = ref(null) // 'manajemen' | 'member' | null
const toggleMobileSheet = (name) => {
    activeMobileSheet.value = activeMobileSheet.value === name ? null : name
}
const activeSheetItems = computed(() => {
    return sidebarGroups.value.find(g => g.key === activeMobileSheet.value)?.items || []
})

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
                        <span class="font-black text-base text-slate-900 tracking-tight">KOPERASI RABBANI</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-200">
                            POS SDAM
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-500 truncate max-w-xs sm:max-w-md">
                        SD Muhammadiyah 1 Krian • Sistem Kasir & Penukaran Voucher Habit
                    </p>
                </div>
            </div>

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

        <!-- ── Desktop Sidebar + Main POS Workspace ── -->
        <div class="flex-1 flex overflow-hidden">
            <aside
                class="hidden md:flex flex-col bg-white border-r border-slate-200 flex-shrink-0 transition-all duration-200 overflow-y-auto"
                :class="isSidebarCollapsed ? 'w-16 p-2' : 'w-56 p-3'"
            >
                <div class="flex items-center justify-between mb-3 px-1">
                    <span v-if="!isSidebarCollapsed" class="text-[10px] font-black uppercase tracking-wider text-slate-400">Menu Koperasi</span>
                    <button
                        type="button"
                        @click="isSidebarCollapsed = !isSidebarCollapsed"
                        class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors ml-auto"
                        :title="isSidebarCollapsed ? 'Perluas Sidebar' : 'Ciutkan Sidebar'"
                    >
                        <svg class="w-3.5 h-3.5 transition-transform" :class="isSidebarCollapsed ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div v-for="group in sidebarGroups" :key="group.key" class="space-y-1">
                        <div v-if="!isSidebarCollapsed" class="px-2.5 text-[10px] font-black uppercase tracking-wider text-slate-400">
                            {{ group.title }}
                        </div>
                        <div v-else class="border-t border-slate-100 my-1"></div>

                        <Link
                            v-for="item in group.items"
                            :key="item.routeName"
                            :href="route(item.routeName)"
                            :title="item.label"
                            class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl text-xs font-bold transition-all"
                            :class="[
                                route().current(item.pattern) ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100',
                                isSidebarCollapsed ? 'justify-center' : ''
                            ]"
                        >
                            <!-- Custom Dual-Tone Menu Icons -->
                            <svg v-if="item.icon === 'pos'" class="w-[18px] h-[18px] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M7 7V3.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 .5.5V7" fill="currentColor" fill-opacity="0.16" />
                                <path d="M5 10.5A2.5 2.5 0 0 1 7.5 8h9a2.5 2.5 0 0 1 2.5 2.5V15H5v-4.5Z" />
                                <path d="M4 15h16l1 4.5a1 1 0 0 1-1 1.5H4a1 1 0 0 1-1-1.5L4 15Z" fill="currentColor" fill-opacity="0.12" />
                                <path d="M8 11.5h3M14.5 11.5h1.5M9 18h6" />
                            </svg>
                            <svg v-else-if="item.icon === 'products'" class="w-[18px] h-[18px] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12.5 3H6a2 2 0 0 0-2 2v6.5a2 2 0 0 0 .59 1.41l7.5 7.5a2 2 0 0 0 2.82 0l5.5-5.5a2 2 0 0 0 0-2.82l-7.5-7.5A2 2 0 0 0 12.5 3Z" fill="currentColor" fill-opacity="0.14" />
                                <circle cx="8.5" cy="7.5" r="1.2" fill="currentColor" stroke="none" />
                                <path d="M10.5 13.5l3-3M12.5 15.5l2-2" />
                            </svg>
                            <svg v-else-if="item.icon === 'discounts'" class="w-[18px] h-[18px] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="9" fill="currentColor" fill-opacity="0.13" />
                                <path d="M9 15l6-6" />
                                <circle cx="9.5" cy="9.5" r="1" fill="currentColor" stroke="none" />
                                <circle cx="14.5" cy="14.5" r="1" fill="currentColor" stroke="none" />
                            </svg>
                            <svg v-else-if="item.icon === 'opname'" class="w-[18px] h-[18px] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 8.5 12 4l8 4.5v7L12 20l-8-4.5v-7Z" fill="currentColor" fill-opacity="0.13" />
                                <path d="M12 12.5 20 8.5M12 12.5 4 8.5M12 12.5V20" />
                                <circle cx="17.5" cy="17.5" r="3.5" fill="white" class="text-white" stroke="currentColor" />
                                <path d="M16.2 17.5l1 1 2-2" />
                            </svg>
                            <svg v-else-if="item.icon === 'history'" class="w-[18px] h-[18px] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 3.5h10a1.5 1.5 0 0 1 1.5 1.5v15l-2-1.3-2 1.3-2-1.3-2 1.3-2-1.3-1.5 1.3V5A1.5 1.5 0 0 1 6 3.5Z" fill="currentColor" fill-opacity="0.13" />
                                <path d="M9 8.5h5.5M9 12h3.5" />
                                <circle cx="17.5" cy="16.5" r="3.5" fill="white" stroke="currentColor" />
                                <path d="M17.5 15v1.6l1.1.8" />
                            </svg>
                            <svg v-else-if="item.icon === 'reports'" class="w-[18px] h-[18px] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3.5" y="4" width="17" height="16" rx="3" fill="currentColor" fill-opacity="0.12" />
                                <path d="M7.5 16v-3M11.5 16V9.5M15.5 16v-5" />
                                <path d="M7.5 10.5 11 7.5l2.5 1.8L17 6.5" />
                            </svg>
                            <svg v-else-if="item.icon === 'members'" class="w-[18px] h-[18px] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="5" width="18" height="14" rx="2.5" fill="currentColor" fill-opacity="0.13" />
                                <circle cx="8.5" cy="11" r="2" />
                                <path d="M5.5 16c.6-1.4 1.7-2 3-2s2.4.6 3 2" />
                                <path d="M14.5 10.5a3 3 0 0 1 0 4M17 9a5 5 0 0 1 0 7" />
                            </svg>
                            <svg v-else-if="item.icon === 'bon'" class="w-[18px] h-[18px] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 4h9.5L19 7.5V19a1.5 1.5 0 0 1-1.5 1.5H6A1.5 1.5 0 0 1 4.5 19V5.5A1.5 1.5 0 0 1 6 4Z" fill="currentColor" fill-opacity="0.13" />
                                <path d="M15 4v4h4M8 10.5h5M8 14h3.5" />
                                <circle cx="16.5" cy="16.5" r="3.5" fill="white" stroke="currentColor" />
                                <path d="M16.5 15v3M15.3 16.5h2.4" />
                            </svg>
                            <svg v-else-if="item.icon === 'settings'" class="w-[18px] h-[18px] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 9.5 12 4l8 5.5V19a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 19V9.5Z" fill="currentColor" fill-opacity="0.13" />
                                <path d="M9.5 20.5V14h5v6.5" />
                                <circle cx="12" cy="10" r="1.5" />
                            </svg>
                            <svg v-else class="w-[18px] h-[18px] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3.5" y="3.5" width="7.5" height="9" rx="2" fill="currentColor" fill-opacity="0.16" />
                                <rect x="13" y="3.5" width="7.5" height="5.5" rx="2" />
                                <rect x="3.5" y="14.5" width="7.5" height="6" rx="2" />
                                <rect x="13" y="11" width="7.5" height="9.5" rx="2" fill="currentColor" fill-opacity="0.12" />
                            </svg>
                            <span v-if="!isSidebarCollapsed" class="truncate">{{ item.label }}</span>
                        </Link>
                    </div>
                </div>
            </aside>

            <main class="flex-1 overflow-hidden relative flex flex-col bg-slate-100 pb-16 md:pb-0">
                <slot />
            </main>
        </div>

        <!-- ── Mobile Submenu Bottom Sheet ── -->
        <div
            v-if="activeMobileSheet"
            class="fixed inset-0 z-40 md:hidden bg-slate-900/40 backdrop-blur-[1px]"
            @click="activeMobileSheet = null"
        >
            <div
                class="fixed bottom-16 inset-x-0 bg-white rounded-t-2xl border-t border-slate-200 shadow-2xl p-4 space-y-2"
                @click.stop
            >
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-500">
                        {{ activeMobileSheet === 'manajemen' ? 'Menu Manajemen' : 'Menu Member & BON' }}
                    </span>
                    <button type="button" @click="activeMobileSheet = null" class="text-xs font-bold text-slate-400 hover:text-slate-600 px-2 py-0.5">
                        Tutup
                    </button>
                </div>

                <Link
                    v-for="sub in activeSheetItems"
                    :key="sub.routeName"
                    :href="route(sub.routeName)"
                    @click="activeMobileSheet = null"
                    class="flex items-center justify-between p-3 rounded-xl border transition-all"
                    :class="route().current(sub.pattern) ? 'bg-emerald-50 border-emerald-500 text-emerald-900' : 'bg-slate-50 border-slate-200 text-slate-800 hover:bg-slate-100'"
                >
                    <div>
                        <div class="text-xs font-bold">{{ sub.label }}</div>
                        <div class="text-[11px] text-slate-500">{{ sub.desc }}</div>
                    </div>
                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </Link>
            </div>
        </div>

        <!-- ── Mobile App Bottom Navigation Bar ── -->
        <nav class="fixed bottom-0 inset-x-0 z-50 md:hidden bg-white border-t border-slate-200 h-16 px-2 grid grid-cols-4 items-center shadow-[0_-4px_12px_rgba(0,0,0,0.06)]">
            <!-- Home -->
            <Link
                :href="hasMainApp ? route('dashboard') : route('koperasi.pos')"
                @click="activeMobileSheet = null"
                class="flex flex-col items-center justify-center gap-0.5 py-1 rounded-xl transition-colors"
                :class="route().current('dashboard') ? 'text-emerald-600' : 'text-slate-500 hover:text-slate-800'"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                <span class="text-[10px] font-bold">Home</span>
            </Link>

            <!-- Manajemen -->
            <button
                type="button"
                @click="toggleMobileSheet('manajemen')"
                class="flex flex-col items-center justify-center gap-0.5 py-1 rounded-xl transition-colors"
                :class="(activeMobileSheet === 'manajemen' || route().current('koperasi.products*') || route().current('koperasi.stock-opname*') || route().current('koperasi.history*') || route().current('koperasi.reports*') || route().current('koperasi.settings*')) ? 'text-emerald-600' : 'text-slate-500 hover:text-slate-800'"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                <span class="text-[10px] font-bold">Manajemen</span>
            </button>

            <!-- POS -->
            <Link
                :href="route('koperasi.pos')"
                @click="activeMobileSheet = null"
                class="flex flex-col items-center justify-center gap-0.5 py-1 rounded-xl transition-colors"
                :class="route().current('koperasi.pos*') && !activeMobileSheet ? 'text-emerald-600' : 'text-slate-500 hover:text-slate-800'"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                <span class="text-[10px] font-bold">POS</span>
            </Link>

            <!-- Member -->
            <button
                type="button"
                @click="toggleMobileSheet('member')"
                class="flex flex-col items-center justify-center gap-0.5 py-1 rounded-xl transition-colors"
                :class="(activeMobileSheet === 'member' || route().current('koperasi.members*') || route().current('koperasi.bon*')) ? 'text-emerald-600' : 'text-slate-500 hover:text-slate-800'"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                <span class="text-[10px] font-bold">Member</span>
            </button>
        </nav>
    </div>
</template>
