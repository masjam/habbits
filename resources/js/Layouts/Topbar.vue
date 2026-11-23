<script setup>
import { ref, computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import { useDarkMode } from '@/Composables/useDarkMode'
import ThemeSelector from '@/Components/ThemeSelector.vue'
import dayjs from 'dayjs'
import 'dayjs/locale/id'
import relativeTime from 'dayjs/plugin/relativeTime'

dayjs.extend(relativeTime)
dayjs.locale('id')

const props = defineProps({
    user: {
        type: Object,
        default: () => ({})
    },
    roles: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['toggleSidebar'])

const page = usePage()

const isDropdownOpen = ref(false)
const closeDropdown = () => { isDropdownOpen.value = false }
const toggleDropdown = () => { 
    isDropdownOpen.value = !isDropdownOpen.value 
    if (isDropdownOpen.value) isNotificationOpen.value = false
}

const isNotificationOpen = ref(false)
const closeNotification = () => { isNotificationOpen.value = false }
const toggleNotification = () => { 
    isNotificationOpen.value = !isNotificationOpen.value 
    if (isNotificationOpen.value) isDropdownOpen.value = false
}

const safeNavigate = (url) => {
    if (!url) return
    const trimmed = String(url).trim()
    if (/^(javascript|data|vbscript):/i.test(trimmed)) return
    if (trimmed.startsWith('/') && !trimmed.startsWith('//')) {
        window.location.href = trimmed
        return
    }
    try {
        const parsed = new URL(trimmed, window.location.origin)
        if (parsed.origin === window.location.origin) {
            window.location.href = parsed.href
        }
    } catch {
        // Invalid URL, do nothing
    }
}

const markAsRead = (id, url) => {
    router.post(route('notifications.mark-as-read', id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (url) {
                safeNavigate(url)
            }
        }
    })
}

const { isDark, toggle: toggleDark } = useDarkMode()

const hasRole = (role) => props.roles.includes(role)
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
</script>

<template>
    <header class="flex items-center justify-between h-[var(--topbar-h,4rem)] px-4 md:px-6 bg-white/75 dark:bg-slate-900/80 backdrop-blur-2xl border-b border-white/60 dark:border-white/10 flex-shrink-0 z-20 shadow-xs transition-colors duration-200">
        <!-- Left: Hamburger (mobile/tablet drawer trigger) & Breadcrumb / App Title -->
        <div class="flex items-center gap-3">
            <button
                type="button"
                class="inline-flex items-center justify-center w-10 h-10 rounded-full text-slate-600 dark:text-slate-300 hover:bg-slate-200/50 dark:hover:bg-slate-800/60 active:bg-slate-300/60 dark:active:bg-slate-700/60 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/40 cursor-pointer"
                aria-label="Toggle navigasi menu"
                @click="emit('toggleSidebar')"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <span class="hidden md:inline-block text-sm font-semibold text-slate-700 dark:text-slate-300 tracking-normal">
                SAHABAT SDAM
            </span>
        </div>

        <!-- Right: Theme Selector + Dark Mode Toggle + Notifications + User Pill -->
        <div class="flex items-center gap-2 sm:gap-3">
            <!-- Theme Selector (Responsive: Swatch Pill on Desktop, Palette Popover on Mobile) -->
            <ThemeSelector />

            <!-- Dark Mode Toggle Button (M3 circular icon button) -->
            <button
                v-if="$page.props.global_settings?.dark_mode_active === '1' || $page.props.global_settings?.dark_mode_active === 'true' || $page.props.global_settings?.dark_mode_active === true"
                type="button"
                @click="toggleDark"
                :title="isDark ? 'Beralih ke mode terang' : 'Beralih ke mode gelap'"
                class="inline-flex items-center justify-center w-10 h-10 rounded-full text-slate-600 dark:text-slate-300 hover:bg-slate-200/50 dark:hover:bg-slate-800/60 active:bg-slate-300/60 dark:active:bg-slate-700/60 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/40 cursor-pointer"
                :aria-label="isDark ? 'Mode terang' : 'Mode gelap'"
            >
                <!-- Sun icon (mode gelap aktif) -->
                <svg v-if="isDark" class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z" />
                </svg>
                <!-- Moon icon (mode terang aktif) -->
                <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>
            </button>

            <!-- Notification Bell (M3 circular icon button) -->
            <div class="relative">
                <button
                    type="button"
                    @click="toggleNotification"
                    class="relative inline-flex items-center justify-center w-10 h-10 rounded-full text-slate-600 dark:text-slate-300 hover:bg-slate-200/50 dark:hover:bg-slate-800/60 active:bg-slate-300/60 dark:active:bg-slate-700/60 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/40 cursor-pointer"
                    aria-label="Lihat notifikasi"
                    :aria-expanded="isNotificationOpen"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <!-- M3 Notification Badge -->
                    <span 
                        v-if="$page.props.notifications?.unread_count > 0"
                        class="absolute top-2 right-2 flex h-2.5 w-2.5 items-center justify-center rounded-full bg-rose-500 ring-2 ring-white dark:ring-slate-900"
                    />
                </button>

                <!-- Transparent Backdrop -->
                <div
                    v-if="isNotificationOpen"
                    class="fixed inset-0 z-10"
                    aria-hidden="true"
                    @click="closeNotification"
                />

                <!-- M3 Surface Container: Notification Dropdown Panel -->
                <Transition
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="opacity-0 scale-95 translate-y-1"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition duration-100 ease-in"
                    leave-from-class="opacity-100 scale-100 translate-y-0"
                    leave-to-class="opacity-0 scale-95 translate-y-1"
                >
                    <div
                        v-if="isNotificationOpen"
                        class="fixed inset-x-4 top-[4.5rem] sm:absolute sm:inset-auto sm:-right-12 sm:top-full sm:mt-2 sm:w-[380px] origin-top sm:origin-top-right bg-white/85 dark:bg-slate-900/85 backdrop-blur-2xl rounded-2xl shadow-2xl border border-white/60 dark:border-white/10 overflow-hidden z-20 transition-colors duration-200"
                    >
                        <div class="flex items-center justify-between px-5 py-3.5 bg-white/40 dark:bg-slate-800/40 backdrop-blur-md border-b border-white/40 dark:border-white/10">
                            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Notifikasi</h3>
                            <Link 
                                :href="route('notifications.index')" 
                                class="text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-colors" 
                                @click="closeNotification"
                            >
                                Lihat semua
                            </Link>
                        </div>

                        <div class="max-h-[360px] overflow-y-auto">
                            <div v-if="!$page.props.notifications?.latest || $page.props.notifications.latest.length === 0" class="p-8 text-center">
                                <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada notifikasi baru.</p>
                            </div>
                            
                            <div v-else class="divide-y divide-subtle">
                                <div 
                                    v-for="notif in $page.props.notifications.latest" 
                                    :key="notif.id"
                                    @click="markAsRead(notif.id, notif.data.url)"
                                    :class="[
                                        'p-4 hover:bg-slate-200/40 dark:hover:bg-slate-800/40 transition-colors cursor-pointer group relative',
                                        notif.read_at === null ? 'bg-primary-50/60 dark:bg-primary-950/30' : ''
                                    ]"
                                >
                                    <div class="flex gap-3.5">
                                        <div :class="[
                                            'w-9 h-9 rounded-full flex-shrink-0 flex items-center justify-center border',
                                            notif.read_at === null ? 'bg-primary-100 border-primary-200 dark:bg-primary-900/60 dark:border-primary-800' : 'bg-slate-100 border-slate-200 dark:bg-slate-800 dark:border-slate-700'
                                        ]">
                                            <img v-if="notif.data.icon" :src="notif.data.icon" class="w-4 h-4 object-contain" alt="Ikon notifikasi" />
                                            <svg v-else class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h4 :class="['text-xs font-bold truncate pr-3', notif.read_at === null ? 'text-slate-900 dark:text-slate-100' : 'text-slate-700 dark:text-slate-300']">
                                                {{ notif.data.title || 'Pemberitahuan' }}
                                            </h4>
                                            <p :class="['text-xs mt-0.5 line-clamp-2 leading-relaxed', notif.read_at === null ? 'text-slate-700 dark:text-slate-300 font-medium' : 'text-slate-500 dark:text-slate-400']">
                                                {{ notif.data.body }}
                                            </p>
                                            <p class="text-[10px] text-slate-400 mt-1 font-medium">{{ dayjs(notif.created_at).fromNow() }}</p>
                                        </div>
                                    </div>
                                    <span v-if="notif.read_at === null" class="absolute top-4 right-3.5 w-2 h-2 rounded-full bg-primary-500" />
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>

            <!-- User Dropdown (M3 Tactile Pill Chip) -->
            <div class="relative">
                <!-- Trigger Button -->
                <button
                    type="button"
                    class="flex items-center gap-2.5 rounded-full p-1 pl-1.5 pr-3 sm:pr-3.5 border border-white/60 dark:border-white/10 bg-white/60 dark:bg-slate-800/60 backdrop-blur-md hover:bg-white/80 dark:hover:bg-slate-800/80 active:bg-slate-300/60 dark:active:bg-slate-700/60 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/40 cursor-pointer whitespace-nowrap"
                    aria-haspopup="true"
                    :aria-expanded="isDropdownOpen"
                    @click="toggleDropdown"
                >
                    <div class="w-8 h-8 rounded-full bg-primary-600 text-white flex items-center justify-center flex-shrink-0 shadow-xs overflow-hidden">
                        <img v-if="userAvatarUrl" :src="userAvatarUrl" :alt="user?.name" class="w-full h-full object-cover" />
                        <span v-else class="text-xs font-bold">{{ userInitial }}</span>
                    </div>

                    <div class="hidden sm:block text-left leading-tight">
                        <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 max-w-[120px] truncate">{{ user?.name }}</p>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400">{{ roleLabel }}</p>
                    </div>

                    <svg
                        :class="[
                            'w-4 h-4 text-slate-400 dark:text-slate-500 transition-transform duration-200 ease-in-out',
                            isDropdownOpen ? 'rotate-180' : 'rotate-0'
                        ]"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <!-- Transparent Backdrop -->
                <div
                    v-if="isDropdownOpen"
                    class="fixed inset-0 z-10"
                    aria-hidden="true"
                    @click="closeDropdown"
                />

                <!-- M3 Surface Container: User Dropdown Panel -->
                <Transition
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="opacity-0 scale-95 translate-y-1"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition duration-100 ease-in"
                    leave-from-class="opacity-100 scale-100 translate-y-0"
                    leave-to-class="opacity-0 scale-95 translate-y-1"
                >
                    <div
                        v-if="isDropdownOpen"
                        class="absolute right-0 top-full mt-2 w-64 origin-top-right bg-white/85 dark:bg-slate-900/85 backdrop-blur-2xl rounded-2xl shadow-2xl border border-white/60 dark:border-white/10 overflow-hidden z-20 transition-colors duration-200"
                        role="menu"
                    >
                        <!-- Header tile -->
                        <div class="flex items-center gap-3 px-4 py-3.5 bg-white/40 dark:bg-slate-800/40 backdrop-blur-md border-b border-white/40 dark:border-white/10">
                            <div class="w-10 h-10 rounded-full bg-primary-600 text-white flex items-center justify-center flex-shrink-0 overflow-hidden shadow-xs">
                                <img v-if="userAvatarUrl" :src="userAvatarUrl" :alt="user?.name" class="w-full h-full object-cover" />
                                <span v-else class="text-sm font-bold">{{ userInitial }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 truncate">{{ user?.name }}</p>
                                <p class="text-xs text-primary-600 dark:text-primary-400 font-medium">{{ roleLabel }}</p>
                            </div>
                        </div>

                        <!-- ─── MULTI ACCOUNT SECTION ─── -->
                        <div v-if="$page.props.auth?.multi_accounts && $page.props.auth.multi_accounts.length > 1" class="py-2 border-b border-subtle">
                            <p class="px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Beralih akun</p>
                            <div v-for="acc in $page.props.auth.multi_accounts" :key="acc.id">
                                <div v-if="acc.id !== user.id" class="flex items-center justify-between px-3 py-1.5 hover:bg-slate-200/50 dark:hover:bg-slate-800/50 group transition-colors rounded-xl mx-1.5">
                                    <Link
                                        :href="route('multi-account.switch', acc.id)"
                                        method="post"
                                        as="button"
                                        class="flex items-center gap-2.5 flex-1 text-left cursor-pointer"
                                        @click="closeDropdown"
                                    >
                                        <div class="w-6 h-6 rounded-full bg-primary-100 dark:bg-primary-900/60 flex items-center justify-center text-[10px] font-bold text-primary-700 dark:text-primary-300">
                                            {{ acc.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <span class="text-xs text-slate-700 dark:text-slate-300 truncate max-w-[130px] font-medium">{{ acc.name }}</span>
                                    </Link>
                                    <Link
                                        :href="route('multi-account.remove', acc.id)"
                                        method="post"
                                        as="button"
                                        title="Hapus dari sesi"
                                        class="text-slate-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity p-1 cursor-pointer"
                                        @click="closeDropdown"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </Link>
                                </div>
                            </div>
                        </div>
                        
                        <div v-if="$page.props.auth?.user?.can_multi_login || ($page.props.auth?.multi_accounts && $page.props.auth.multi_accounts.length > 0 && $page.props.auth.multi_accounts.some(a => a.can_multi_login))" class="py-1 px-1.5 border-b border-subtle">
                            <a
                                :href="route('multi-account.add')"
                                class="flex items-center gap-3 px-3 py-2 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:bg-primary-100/50 dark:hover:bg-primary-900/30 rounded-xl transition-colors w-full"
                                @click="closeDropdown"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                <span>Tambah akun lain</span>
                            </a>
                        </div>

                        <!-- Profile & Logout items -->
                        <div class="p-1.5 space-y-0.5">
                            <Link
                                :href="route('profile.edit')"
                                class="flex items-center gap-3 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-200/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white rounded-xl transition-colors w-full cursor-pointer"
                                role="menuitem"
                                @click="closeDropdown"
                            >
                                <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <span>Profil Saya</span>
                            </Link>

                            <div class="my-1 border-t border-subtle" />

                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="flex items-center gap-3 px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 rounded-xl transition-colors w-full text-left cursor-pointer"
                                role="menuitem"
                                @click="closeDropdown"
                            >
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                <span>Logout {{ ($page.props.auth?.multi_accounts && $page.props.auth.multi_accounts.length > 1) ? 'Semua Akun' : '' }}</span>
                            </Link>
                        </div>
                    </div>
                </Transition>
            </div>
        </div>
    </header>
</template>
