<script setup>
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useDarkMode } from '@/Composables/useDarkMode'

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
const toggleDropdown = () => { isDropdownOpen.value = !isDropdownOpen.value }

const { isDark, toggle: toggleDark } = useDarkMode()

const hasRole = (role) => props.roles.includes(role)
const roleLabel = computed(() => {
    if (hasRole('superadmin')) return 'Super Admin'
    if (hasRole('admin'))      return 'Admin'
    return 'Pegawai'
})

const userInitial = computed(() =>
    props.user?.name?.charAt(0)?.toUpperCase() ?? '?'
)
</script>

<template>
    <header class="flex items-center justify-between h-16 px-4 md:px-6 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex-shrink-0 z-20 shadow-sm">
        <!-- Left: Hamburger (mobile only) -->
        <div class="flex items-center gap-3">
            <button
                class="md:hidden inline-flex items-center justify-center w-9 h-9 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 active:bg-slate-200 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-1"
                aria-label="Toggle menu"
                @click="emit('toggleSidebar')"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <span class="hidden md:inline-block text-sm font-semibold text-slate-500 dark:text-slate-400 tracking-wide">
                Sistem Pantauan Habit &amp; Ibadah
            </span>
        </div>

        <!-- Right: Dark Mode Toggle + User Dropdown -->
        <div class="flex items-center gap-2">
            <!-- Dark Mode Toggle Button -->
            <button
                v-if="$page.props.global_settings?.dark_mode_active === '1' || $page.props.global_settings?.dark_mode_active === 'true' || $page.props.global_settings?.dark_mode_active === true"
                @click="toggleDark"
                :title="isDark ? 'Mode Terang' : 'Mode Gelap'"
                class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-400"
            >
                <!-- Sun icon (mode gelap aktif) -->
                <svg v-if="isDark" class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z" />
                </svg>
                <!-- Moon icon (mode terang aktif) -->
                <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>
            </button>

            <!-- User Dropdown -->
            <div class="relative">
                <!-- Trigger Button -->
                <button
                    class="flex items-center gap-2.5 rounded-xl px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-700 active:bg-slate-100 dark:bg-slate-700 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-1"
                    aria-haspopup="true"
                    :aria-expanded="isDropdownOpen"
                    @click="toggleDropdown"
                >
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center flex-shrink-0 shadow-sm">
                        <span class="text-sm font-bold text-white">{{ userInitial }}</span>
                    </div>

                    <div class="hidden sm:block text-left leading-tight">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 max-w-[120px] truncate">{{ user?.name }}</p>
                        <p class="text-xs text-slate-400 dark:text-slate-500">{{ roleLabel }}</p>
                    </div>

                    <svg
                        :class="[
                            'w-4 h-4 text-slate-400 dark:text-slate-500 transition-transform duration-200 ease-in-out',
                            isDropdownOpen ? 'rotate-180' : 'rotate-0'
                        ]"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
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

                <!-- Dropdown Panel -->
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
                        class="absolute right-0 top-full mt-2 w-52 origin-top-right bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-100 dark:border-slate-700 overflow-hidden z-20"
                        role="menu"
                    >
                        <div class="flex items-center gap-3 px-4 py-3 bg-slate-50 dark:bg-slate-700/50 border-b border-slate-100 dark:border-slate-700">
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center flex-shrink-0">
                                <span class="text-sm font-bold text-white">{{ userInitial }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 truncate">{{ user?.name }}</p>
                                <p class="text-xs text-emerald-500 font-medium">{{ roleLabel }}</p>
                            </div>
                        </div>

                        <div class="py-1.5">
                            <Link
                                :href="route('profile.edit')"
                                class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-white transition-colors w-full"
                                role="menuitem"
                                @click="closeDropdown"
                            >
                                <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <span>Profil Saya</span>
                            </Link>

                            <div class="my-1 border-t border-slate-100 dark:border-slate-700" />

                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600 transition-colors w-full text-left"
                                role="menuitem"
                                @click="closeDropdown"
                            >
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                <span>Keluar</span>
                            </Link>
                        </div>
                    </div>
                </Transition>
            </div>
        </div>
    </header>
</template>
