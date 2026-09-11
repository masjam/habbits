<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const themes = [
    { id: 'default', name: 'Emerald Green', shortName: 'Emerald', hex: '#10b981', colorClass: 'bg-[#10b981]' },
    { id: 'theme-blue', name: 'Ocean Blue', shortName: 'Blue', hex: '#3b82f6', colorClass: 'bg-[#3b82f6]' },
    { id: 'theme-rose', name: 'Rose Pink', shortName: 'Rose', hex: '#f43f5e', colorClass: 'bg-[#f43f5e]' },
    { id: 'theme-amber', name: 'Warm Amber', shortName: 'Amber', hex: '#f59e0b', colorClass: 'bg-[#f59e0b]' },
    { id: 'theme-purple', name: 'Royal Purple', shortName: 'Purple', hex: '#a855f7', colorClass: 'bg-[#a855f7]' },
];

const selectedTheme = ref('default');
const isMobileMenuOpen = ref(false);

const activeThemeObj = computed(() => {
    return themes.find(t => t.id === selectedTheme.value) || themes[0];
});

const applyTheme = (themeId) => {
    if (typeof document === 'undefined') return;
    const htmlEl = document.documentElement;
    themes.forEach(t => {
        if (t.id !== 'default') htmlEl.classList.remove(t.id);
    });
    if (themeId !== 'default') {
        htmlEl.classList.add(themeId);
    }
    const themeObj = themes.find(t => t.id === themeId) || themes[0];
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta && themeObj) {
        meta.setAttribute('content', themeObj.hex);
    }
};

const selectTheme = (themeId) => {
    selectedTheme.value = themeId;
    applyTheme(themeId);
    try {
        localStorage.setItem('app-theme', themeId);
    } catch (e) {}
    isMobileMenuOpen.value = false;
};

const toggleMobileMenu = () => {
    isMobileMenuOpen.value = !isMobileMenuOpen.value;
};

const closeMobileMenu = () => {
    isMobileMenuOpen.value = false;
};

onMounted(() => {
    try {
        const saved = localStorage.getItem('app-theme') || 'default';
        selectedTheme.value = saved;
        applyTheme(saved);
    } catch (e) {}
});
</script>

<template>
    <div class="relative flex items-center">
        <!-- ═══ DESKTOP VERSION (Pill swatches langsung klik) ═══ -->
        <div class="hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100/90 dark:bg-slate-700/60 border border-slate-200/80 dark:border-slate-600/50 shadow-xs backdrop-blur-xs transition-all">
            <span class="text-[11px] font-semibold text-slate-400 dark:text-slate-400 mr-0.5 flex items-center gap-1 select-none">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="13.5" cy="6.5" r=".5" fill="currentColor" />
                    <circle cx="17.5" cy="10.5" r=".5" fill="currentColor" />
                    <circle cx="8.5" cy="7.5" r=".5" fill="currentColor" />
                    <circle cx="6.5" cy="12.5" r=".5" fill="currentColor" />
                    <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z" />
                </svg>
            </span>
            <div class="flex items-center gap-1.5">
                <button
                    v-for="theme in themes"
                    :key="'desktop-' + theme.id"
                    @click="selectTheme(theme.id)"
                    :title="'Tema: ' + theme.name"
                    class="w-5 h-5 rounded-full flex items-center justify-center transition-all duration-200 transform hover:scale-125 focus:outline-none cursor-pointer"
                    :class="[
                        theme.colorClass,
                        selectedTheme === theme.id
                            ? 'ring-2 ring-offset-2 ring-slate-600 dark:ring-slate-300 dark:ring-offset-slate-800 scale-110 shadow-xs'
                            : 'opacity-65 hover:opacity-100 hover:shadow-xs'
                    ]"
                >
                    <svg v-if="selectedTheme === theme.id" class="w-3 h-3 text-white drop-shadow-xs" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- ═══ MOBILE VERSION (Icon Button + Popover Dropdown) ═══ -->
        <div class="sm:hidden relative">
            <!-- Palette Icon Trigger Button -->
            <button
                @click="toggleMobileMenu"
                :title="'Ganti Tema (' + activeThemeObj.name + ')'"
                class="relative inline-flex items-center justify-center w-9 h-9 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 active:bg-slate-200 dark:active:bg-slate-600 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-400"
                aria-label="Pilih tema warna"
                :aria-expanded="isMobileMenuOpen"
            >
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="13.5" cy="6.5" r=".5" fill="currentColor" />
                    <circle cx="17.5" cy="10.5" r=".5" fill="currentColor" />
                    <circle cx="8.5" cy="7.5" r=".5" fill="currentColor" />
                    <circle cx="6.5" cy="12.5" r=".5" fill="currentColor" />
                    <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z" />
                </svg>
                <!-- Active Color Dot Indicator on Button -->
                <span
                    class="absolute top-1.5 right-1.5 w-2.5 h-2.5 rounded-full ring-2 ring-white dark:ring-slate-800 shadow-xs"
                    :style="{ backgroundColor: activeThemeObj.hex }"
                />
            </button>

            <!-- Backdrop (Click Outside to Close) -->
            <div
                v-if="isMobileMenuOpen"
                class="fixed inset-0 z-30"
                aria-hidden="true"
                @click="closeMobileMenu"
            />

            <!-- Mobile Popover Menu -->
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 scale-95 translate-y-1"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 scale-100 translate-y-0"
                leave-to-class="opacity-0 scale-95 translate-y-1"
            >
                <div
                    v-if="isMobileMenuOpen"
                    class="absolute right-0 top-full mt-2 w-56 origin-top-right bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200/90 dark:border-slate-700/80 p-2.5 z-40"
                    role="menu"
                >
                    <div class="px-2.5 py-1.5 mb-1.5 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-slate-400 dark:text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="13.5" cy="6.5" r=".5" fill="currentColor" />
                                <circle cx="17.5" cy="10.5" r=".5" fill="currentColor" />
                                <circle cx="8.5" cy="7.5" r=".5" fill="currentColor" />
                                <circle cx="6.5" cy="12.5" r=".5" fill="currentColor" />
                                <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z" />
                            </svg>
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-200">Tema Warna</span>
                        </div>
                        <span class="text-[10px] text-slate-400 dark:text-slate-500 font-medium">5 Pilihan</span>
                    </div>

                    <div class="space-y-1">
                        <button
                            v-for="theme in themes"
                            :key="'mobile-' + theme.id"
                            @click="selectTheme(theme.id)"
                            class="w-full flex items-center justify-between px-2.5 py-2 rounded-xl text-left transition-colors cursor-pointer"
                            :class="[
                                selectedTheme === theme.id
                                    ? 'bg-slate-100 dark:bg-slate-700/80 font-semibold'
                                    : 'hover:bg-slate-50 dark:hover:bg-slate-700/40 text-slate-600 dark:text-slate-300'
                            ]"
                        >
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="w-4 h-4 rounded-full shadow-xs flex-shrink-0"
                                    :class="theme.colorClass"
                                />
                                <span class="text-xs font-medium text-slate-700 dark:text-slate-200">{{ theme.name }}</span>
                            </div>

                            <svg v-if="selectedTheme === theme.id" class="w-4 h-4 text-emerald-500 dark:text-emerald-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </div>
</template>
