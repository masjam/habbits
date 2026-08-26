<script setup>
import { ref, computed, onMounted, onUnmounted, watch, onErrorCaptured } from 'vue'
import { Head } from '@inertiajs/vue3'
import { defineAsyncComponent } from 'vue'
const TasbihMode = defineAsyncComponent(() => import('./Partials/TasbihMode.vue'))
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import {
    DZIKIR_PAGI,
    DZIKIR_PETANG,
    DZIKIR_SHOLAT,
    PRESET_TASBIH
} from '@/Data/dzikirData'
import { useDzikirSound } from '@/Composables/useDzikirSound'
import { useDzikirProgress } from '@/Composables/useDzikirProgress'

// ─── Tabs & Active Mode ───────────────────────────────────────────────────────

onErrorCaptured((err, instance, info) => {
    alert('Vue Error: ' + err.toString() + ' | Info: ' + info);
    console.error(err);
    return false;
});

const activeTab = ref('pagi') // 'pagi' | 'petang' | 'sholat' | 'tasbih'
const viewMode = ref('slide') // 'slide' (satu per satu tanpa scroll) | 'list' (semua)
const currentIndex = ref(0) // indeks dzikir saat ini dalam mode slide

// Smart Time Detection
const getRecommendedTab = () => {
    const currentHour = new Date().getHours()
    // Jam 04:00 - 14:59 disarankan Dzikir Pagi
    if (currentHour >= 4 && currentHour < 15) {
        return 'pagi'
    }
    // Jam 15:00 - 03:59 disarankan Dzikir Petang
    return 'petang'
}

// ─── Settings & Preferences ──────────────────────────────────────────────────
const fontSize = ref('normal') // 'sm' | 'normal' | 'lg' | 'xl'
const showLatin = ref(true)
const showTranslation = ref(true)
const showFaedah = ref(true)
const soundEnabled = ref(true)
const vibrateEnabled = ref(true)
const searchQuery = ref('')
const filterStatus = ref('all') // 'all' | 'uncompleted' | 'completed'
const showSettings = ref(false) // Toggle mode pengaturan & filter (terutama pada mobile)
const showMobileCardMenu = ref(false) // Hamburger menu pada mode mobile

const isMobileMenuOrFilterOpen = computed(() => showMobileCardMenu.value || showSettings.value)

const toggleMobileMenu = () => {
    if (isMobileMenuOrFilterOpen.value) {
        showMobileCardMenu.value = false
        showSettings.value = false
    } else {
        showMobileCardMenu.value = true
    }
}

const { playTickSound, triggerVibrate } = useDzikirSound()
const { progressMap, loadProgress, saveProgress, getCount, isCompleted, incrementCount, completeDirectly, resetItemCount, resetCurrentTabProgress } = useDzikirProgress(playTickSound, triggerVibrate, soundEnabled, vibrateEnabled)

const saveSettings = () => {
    try {
        localStorage.setItem('dzikir_user_settings', JSON.stringify({
            fontSize: fontSize.value,
            showLatin: showLatin.value,
            showTranslation: showTranslation.value,
            showFaedah: showFaedah.value,
            soundEnabled: soundEnabled.value,
            vibrateEnabled: vibrateEnabled.value,
            viewMode: viewMode.value
        }))
    } catch (e) {}
}

watch([fontSize, showLatin, showTranslation, showFaedah, soundEnabled, vibrateEnabled, viewMode], () => {
    saveSettings()
})


// ─── Current Tab Data & Computations ──────────────────────────────────────────
const currentList = computed(() => {
    if (activeTab.value === 'pagi') return DZIKIR_PAGI
    if (activeTab.value === 'petang') return DZIKIR_PETANG
    if (activeTab.value === 'sholat') return DZIKIR_SHOLAT
    return []
})

const filteredList = computed(() => {
    let list = currentList.value
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim()
        list = list.filter(item =>
            item.title.toLowerCase().includes(q) ||
            item.latin.toLowerCase().includes(q) ||
            item.translation.toLowerCase().includes(q) ||
            (item.faedah && item.faedah.toLowerCase().includes(q))
        )
    }
    if (filterStatus.value === 'uncompleted') {
        list = list.filter(item => !isCompleted(item))
    } else if (filterStatus.value === 'completed') {
        list = list.filter(item => isCompleted(item))
    }
    return list
})

// Current item in Slide Mode
const currentDzikirItem = computed(() => {
    if (filteredList.value.length === 0) return null
    const idx = Math.min(Math.max(0, currentIndex.value), filteredList.value.length - 1)
    return filteredList.value[idx]
})

// Reset index when changing tab, filter, or search
watch([activeTab, filterStatus, searchQuery], () => {
    currentIndex.value = 0
})

// Clamp index if filteredList changes
watch(filteredList, (newList) => {
    if (newList.length === 0) {
        currentIndex.value = 0
    } else if (currentIndex.value >= newList.length) {
        currentIndex.value = newList.length - 1
    }
})

// ─── Prev / Next Slide Navigation ───────────────────────────────────────────
const hasPrev = computed(() => currentIndex.value > 0)
const hasNext = computed(() => currentIndex.value < filteredList.value.length - 1)


const prevDzikir = () => {
    if (hasPrev.value) {
        currentIndex.value--
        window.scrollTo({ top: 0, behavior: 'smooth' })
    }
}

const nextDzikir = () => {
    if (hasNext.value) {
        currentIndex.value++
        window.scrollTo({ top: 0, behavior: 'smooth' })
    }
}

const goToIndex = (idx) => {
    if (idx >= 0 && idx < filteredList.value.length) {
        currentIndex.value = idx
        window.scrollTo({ top: 0, behavior: 'smooth' })
    }
}

// Keyboard shortcuts for Left & Right Arrow
const handleKeydown = (e) => {
    if (activeTab.value === 'tasbih') return
    if (['INPUT', 'TEXTAREA'].includes(e.target?.tagName)) return

    if (e.key === 'ArrowLeft') {
        e.preventDefault()
        prevDzikir()
    } else if (e.key === 'ArrowRight') {
        e.preventDefault()
        nextDzikir()
    } else if (e.key === ' ' && viewMode.value === 'slide' && currentDzikirItem.value) {
        // Spacebar to count
        e.preventDefault()
        incrementCount(currentDzikirItem.value)
    }
}

const currentTabStats = computed(() => {
    const total = currentList.value.length
    if (total === 0) return { total: 0, completed: 0, percentage: 0 }
    const completed = currentList.value.filter(item => isCompleted(item)).length
    const percentage = Math.round((completed / total) * 100)
    return { total, completed, percentage }
})

const isAllCompleted = computed(() => {
    return currentTabStats.value.total > 0 && currentTabStats.value.completed === currentTabStats.value.total
})


// ─── Font Size Classes ───────────────────────────────────────────────────────
const arabicFontSizeClass = computed(() => {
    switch (fontSize.value) {
        case 'sm': return 'text-xl sm:text-2xl leading-loose'
        case 'normal': return 'text-2xl sm:text-3xl md:text-4xl leading-loose'
        case 'lg': return 'text-3xl sm:text-4xl md:text-5xl leading-loose'
        case 'xl': return 'text-4xl sm:text-5xl md:text-6xl leading-loose'
        default: return 'text-2xl sm:text-3xl md:text-4xl leading-loose'
    }
})

// ─── Lifecycle ───────────────────────────────────────────────────────────────
onMounted(() => {
    loadProgress()
    activeTab.value = getRecommendedTab()
    window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Dzikir Pagi & Petang" />

        <div class="max-w-5xl mx-auto space-y-6 pb-16">
            <!-- ── Header Banner ───────────────────────────────────────────── -->
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-600 via-teal-600 to-emerald-800 text-white p-6 sm:p-8 shadow-xl shadow-emerald-900/10">
                <!-- Background Geometric Motif -->
                <div class="absolute -right-12 -top-12 w-64 h-64 rounded-full bg-white/10 blur-2xl pointer-events-none"></div>
                <div class="absolute right-8 bottom-0 opacity-10 pointer-events-none hidden sm:block">
                    <svg class="w-56 h-56 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                    </svg>
                </div>

                <div class="relative z-10">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 backdrop-blur-md text-emerald-100 text-xs font-semibold tracking-wide uppercase mb-3">
                        <svg class="w-3.5 h-3.5 text-amber-300" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
                        </svg>
                        Tuntunan As-Sunnah &amp; Hisnul Muslim
                    </div>

                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white mb-2">
                        Dzikir Pagi &amp; Petang
                    </h1>
                    <p class="text-emerald-100 text-sm sm:text-base max-w-2xl leading-relaxed">
                        Benteng perlindungan diri harian dengan dzikir-dzikir shahih yang diajarkan Rasulullah ﷺ.
                    </p>
                </div>
            </div>

            <!-- ── Navigation Tabs ─────────────────────────────────────────── -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 bg-slate-100 dark:bg-slate-800/80 p-1.5 rounded-2xl border border-slate-200/80 dark:border-slate-700">
                <button
                    @click="activeTab = 'pagi'"
                    :class="[
                        'flex items-center justify-center gap-2 py-3 px-4 rounded-xl font-bold text-sm transition-all duration-200',
                        activeTab === 'pagi'
                            ? 'bg-white dark:bg-slate-700 text-amber-600 dark:text-amber-400 shadow-sm'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                    ]"
                >
                    <!-- Sun icon -->
                    <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z" />
                    </svg>
                    <span>Dzikir Pagi</span>
                    <span v-if="getRecommendedTab() === 'pagi'" class="hidden md:inline-block text-[10px] bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300 font-semibold px-1.5 py-0.5 rounded-full">
                        Waktu Ini
                    </span>
                </button>

                <button
                    @click="activeTab = 'petang'"
                    :class="[
                        'flex items-center justify-center gap-2 py-3 px-4 rounded-xl font-bold text-sm transition-all duration-200',
                        activeTab === 'petang'
                            ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-sm'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                    ]"
                >
                    <!-- Moon icon -->
                    <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                    <span>Dzikir Petang</span>
                    <span v-if="getRecommendedTab() === 'petang'" class="hidden md:inline-block text-[10px] bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 font-semibold px-1.5 py-0.5 rounded-full">
                        Waktu Ini
                    </span>
                </button>

                <button
                    @click="activeTab = 'sholat'"
                    :class="[
                        'flex items-center justify-center gap-2 py-3 px-4 rounded-xl font-bold text-sm transition-all duration-200',
                        activeTab === 'sholat'
                            ? 'bg-white dark:bg-slate-700 text-emerald-600 dark:text-emerald-400 shadow-sm'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                    ]"
                >
                    <!-- Mosque / Prayer Icon -->
                    <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    <span>Setelah Sholat</span>
                </button>

                <button
                    @click="activeTab = 'tasbih'"
                    :class="[
                        'flex items-center justify-center gap-2 py-3 px-4 rounded-xl font-bold text-sm transition-all duration-200',
                        activeTab === 'tasbih'
                            ? 'bg-white dark:bg-slate-700 text-teal-600 dark:text-teal-400 shadow-sm'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                    ]"
                >
                    <!-- Tasbih beads icon -->
                    <svg class="w-4 h-4 text-teal-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="5" r="2" />
                        <circle cx="17" cy="8" r="2" />
                        <circle cx="19" cy="13" r="2" />
                        <circle cx="17" cy="18" r="2" />
                        <circle cx="12" cy="20" r="2" />
                        <circle cx="7" cy="18" r="2" />
                        <circle cx="5" cy="13" r="2" />
                        <circle cx="7" cy="8" r="2" />
                    </svg>
                    <span>Tasbih Digital</span>
                </button>
            </div>

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- SECTION 1, 2, 3: DZIKIR MODULE (PAGI / PETANG / SHOLAT) -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            <div v-if="activeTab !== 'tasbih'" class="space-y-5">
                <!-- ── 1 Unified Progress & Control Card ────────────────────── -->
                <div class="bg-white dark:bg-slate-800 rounded-3xl p-4 sm:p-6 border border-slate-200 dark:border-slate-700 shadow-sm transition-all duration-200">
                    <div class="flex items-center justify-between gap-3">
                        <!-- Left: Title & Status -->
                        <div class="space-y-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-slate-800 dark:text-slate-100 text-sm sm:text-lg truncate">
                                    Progres {{ activeTab === 'pagi' ? 'Dzikir Pagi' : activeTab === 'petang' ? 'Dzikir Petang' : 'Dzikir Setelah Sholat' }}
                                </h3>
                                <span class="px-2 sm:px-2.5 py-0.5 text-[11px] sm:text-xs font-black rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 flex-shrink-0">
                                    {{ currentTabStats.completed }}/{{ currentTabStats.total }} Selesai
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 hidden sm:block">
                                Gunakan tombol <strong>&lt;&lt;</strong> dan <strong>&gt;&gt;</strong> untuk berpindah bacaan tanpa perlu scroll.
                            </p>
                        </div>

                        <!-- Mobile: Hamburger Menu Button (sm:hidden) -->
                        <div class="relative sm:hidden flex-shrink-0">
                            <button
                                @click="toggleMobileMenu"
                                :class="[
                                    'p-2 rounded-2xl border transition-all duration-150 flex items-center justify-center cursor-pointer select-none shadow-xs active:scale-95',
                                    isMobileMenuOrFilterOpen
                                        ? 'bg-emerald-600 text-white border-emerald-600 ring-2 ring-emerald-400/40'
                                        : 'bg-slate-50 dark:bg-slate-700/60 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-600 hover:bg-slate-100'
                                ]"
                                title="Menu Opsi Dzikir"
                                aria-label="Menu Opsi Dzikir"
                            >
                                <svg v-if="!isMobileMenuOrFilterOpen" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                                <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Desktop: Actions & Mode Switcher (hidden sm:flex) -->
                        <div class="hidden sm:flex flex-wrap items-center gap-2">
                            <!-- Toggle View Mode: Slide vs List -->
                            <div class="inline-flex rounded-xl bg-slate-100 dark:bg-slate-700 p-0.5 text-xs font-bold">
                                <button
                                    @click="viewMode = 'slide'"
                                    :class="[
                                        'px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 cursor-pointer',
                                        viewMode === 'slide'
                                            ? 'bg-white dark:bg-slate-600 text-emerald-600 dark:text-emerald-400 shadow-xs'
                                            : 'text-slate-500 dark:text-slate-400 hover:text-slate-800'
                                    ]"
                                    title="Tampilkan satu per satu dengan tombol << dan >>"
                                >
                                    <span>Mode Slide (&lt;&lt; &gt;&gt;)</span>
                                </button>
                                <button
                                    @click="viewMode = 'list'"
                                    :class="[
                                        'px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 cursor-pointer',
                                        viewMode === 'list'
                                            ? 'bg-white dark:bg-slate-600 text-emerald-600 dark:text-emerald-400 shadow-xs'
                                            : 'text-slate-500 dark:text-slate-400 hover:text-slate-800'
                                    ]"
                                    title="Tampilkan semua dzikir dalam daftar panjang"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                                    </svg>
                                    <span>Mode Daftar</span>
                                </button>
                            </div>

                            <!-- Reset Sesi -->
                            <button
                                @click="resetCurrentTabProgress"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 rounded-xl transition-colors border border-slate-200 dark:border-slate-700 cursor-pointer"
                                title="Reset hitungan dzikir sesi ini"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <span>Reset</span>
                            </button>

                            <!-- Toggle Opsi & Filter Button -->
                            <button
                                @click="showSettings = !showSettings"
                                :class="[
                                    'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-xl transition-colors border cursor-pointer select-none',
                                    showSettings
                                        ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-700'
                                        : 'bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-100'
                                ]"
                                title="Buka / Tutup Pengaturan & Filter"
                            >
                                <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                </svg>
                                <span>Filter &amp; Opsi</span>
                                <span v-if="searchQuery || filterStatus !== 'all'" class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <svg
                                    :class="['w-3.5 h-3.5 transition-transform duration-200', showSettings ? 'rotate-180' : 'rotate-0']"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Mobile Hamburger Dropdown Menu -->
                    <Transition
                        enter-active-class="transition duration-150 ease-out"
                        enter-from-class="opacity-0 -translate-y-2 scale-98"
                        enter-to-class="opacity-100 translate-y-0 scale-100"
                        leave-active-class="transition duration-100 ease-in"
                        leave-from-class="opacity-100 translate-y-0 scale-100"
                        leave-to-class="opacity-0 -translate-y-2 scale-98"
                    >
                        <div v-if="showMobileCardMenu" class="sm:hidden mt-3 pt-3 border-t border-slate-100 dark:border-slate-700/80 space-y-2.5">
                            <!-- View Mode Selector -->
                            <div class="flex items-center justify-between gap-1 p-1 bg-slate-100 dark:bg-slate-700 rounded-xl">
                                <button
                                    @click="viewMode = 'slide'; showMobileCardMenu = false; showSettings = false"
                                    :class="[
                                        'flex-1 py-1.5 text-xs font-bold rounded-lg transition-colors text-center cursor-pointer',
                                        viewMode === 'slide'
                                            ? 'bg-white dark:bg-slate-600 text-emerald-600 dark:text-emerald-400 shadow-xs'
                                            : 'text-slate-600 dark:text-slate-300'
                                    ]"
                                >
                                    Mode Slide (&lt;&lt; &gt;&gt;)
                                </button>
                                <button
                                    @click="viewMode = 'list'; showMobileCardMenu = false; showSettings = false"
                                    :class="[
                                        'flex-1 py-1.5 text-xs font-bold rounded-lg transition-colors text-center cursor-pointer',
                                        viewMode === 'list'
                                            ? 'bg-white dark:bg-slate-600 text-emerald-600 dark:text-emerald-400 shadow-xs'
                                            : 'text-slate-600 dark:text-slate-300'
                                    ]"
                                >
                                    Mode Daftar
                                </button>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <!-- Toggle Filter & Opsi -->
                                <button
                                    @click="showSettings = !showSettings"
                                    :class="[
                                        'flex items-center justify-center gap-1.5 py-2 px-3 border rounded-xl text-xs font-bold transition-colors cursor-pointer',
                                        showSettings
                                            ? 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-300 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300'
                                            : 'bg-slate-50 dark:bg-slate-700/50 border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-100'
                                    ]"
                                >
                                    <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                    </svg>
                                    <span>{{ showSettings ? 'Tutup Filter' : 'Filter & Opsi' }}</span>
                                </button>

                                <!-- Reset Sesi -->
                                <button
                                    @click="resetCurrentTabProgress(currentList); showMobileCardMenu = false; showSettings = false"
                                    class="flex items-center justify-center gap-1.5 py-2 px-3 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800 rounded-xl text-xs font-bold text-red-600 dark:text-red-400 hover:bg-red-100 cursor-pointer"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    <span>Reset</span>
                                </button>
                            </div>
                        </div>
                    </Transition>

                    <!-- Progress Bar -->
                    <div class="mt-3">
                        <div class="w-full bg-slate-100 dark:bg-slate-700 h-2.5 rounded-full overflow-hidden">
                            <div
                                class="h-full bg-gradient-to-r from-emerald-500 to-teal-400 transition-all duration-500 ease-out rounded-full"
                                :style="{ width: `${currentTabStats.percentage}%` }"
                            ></div>
                        </div>
                    </div>

                    <!-- Completion Celebration Message -->
                    <div
                        v-if="isAllCompleted"
                        class="mt-4 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 flex items-start gap-3"
                    >
                        <div class="w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center flex-shrink-0 mt-0.5">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-bold text-emerald-800 dark:text-emerald-300 text-sm">
                                Alhamdulillah! Seluruh dzikir sesi ini telah selesai dibaca.
                            </p>
                            <p class="text-xs text-emerald-700 dark:text-emerald-400 mt-0.5">
                                Semoga Allah subhanahu wa ta'ala menerima amalan kita dan menjadikannya pelindung serta pembuka pintu kebaikan sepanjang hari.
                            </p>
                        </div>
                    </div>

                    <!-- ── Collapsible Settings & Filter Panel (Toggle Mode) ── -->
                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="opacity-0 -translate-y-2 scale-98"
                        enter-to-class="opacity-100 translate-y-0 scale-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="opacity-100 translate-y-0 scale-100"
                        leave-to-class="opacity-0 -translate-y-2 scale-98"
                    >
                        <div v-if="showSettings" class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700/70 space-y-4">
                            <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
                                <!-- Search Box -->
                                <div class="relative flex-1">
                                    <input
                                        v-model="searchQuery"
                                        type="text"
                                        placeholder="Cari judul, bacaan arab, latin, atau arti..."
                                        class="w-full pl-9 pr-4 py-2 bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600 rounded-2xl text-xs sm:text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:ring-2 focus:ring-emerald-500 focus:outline-none transition-all"
                                    />
                                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    <button
                                        v-if="searchQuery"
                                        @click="searchQuery = ''"
                                        class="absolute right-3 top-2 text-slate-400 hover:text-slate-600 text-xs font-bold"
                                    >
                                        ✕
                                    </button>
                                </div>

                                <!-- Status Filter -->
                                <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-700/60 p-1 rounded-2xl">
                                    <button
                                        @click="filterStatus = 'all'"
                                        :class="[
                                            'px-3 py-1.5 text-xs font-bold rounded-xl transition-colors cursor-pointer',
                                            filterStatus === 'all'
                                                ? 'bg-white dark:bg-slate-600 text-slate-800 dark:text-white shadow-sm'
                                                : 'text-slate-500 dark:text-slate-400 hover:text-slate-800'
                                        ]"
                                    >
                                        Semua ({{ currentList.length }})
                                    </button>
                                    <button
                                        @click="filterStatus = 'uncompleted'"
                                        :class="[
                                            'px-3 py-1.5 text-xs font-bold rounded-xl transition-colors cursor-pointer',
                                            filterStatus === 'uncompleted'
                                                ? 'bg-white dark:bg-slate-600 text-amber-600 dark:text-amber-400 shadow-sm'
                                                : 'text-slate-500 dark:text-slate-400 hover:text-slate-800'
                                        ]"
                                    >
                                        Belum ({{ currentList.filter(i => !isCompleted(i)).length }})
                                    </button>
                                    <button
                                        @click="filterStatus = 'completed'"
                                        :class="[
                                            'px-3 py-1.5 text-xs font-bold rounded-xl transition-colors cursor-pointer',
                                            filterStatus === 'completed'
                                                ? 'bg-white dark:bg-slate-600 text-emerald-600 dark:text-emerald-400 shadow-sm'
                                                : 'text-slate-500 dark:text-slate-400 hover:text-slate-800'
                                        ]"
                                    >
                                        Selesai ({{ currentTabStats.completed }})
                                    </button>
                                </div>
                            </div>

                            <!-- Customizer Toggles (Font size & Checkboxes) -->
                            <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100 dark:border-slate-700/60 text-xs text-slate-600 dark:text-slate-300">
                                <!-- Font Size Selector -->
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-slate-500">Ukuran Arab:</span>
                                    <div class="inline-flex rounded-xl bg-slate-100 dark:bg-slate-700/50 p-0.5">
                                        <button
                                            @click="fontSize = 'sm'"
                                            :class="['px-2 py-1 rounded-lg font-bold transition-colors cursor-pointer', fontSize === 'sm' ? 'bg-white dark:bg-slate-600 text-emerald-600 dark:text-emerald-400 shadow-xs' : 'text-slate-500']"
                                        >
                                            A-
                                        </button>
                                        <button
                                            @click="fontSize = 'normal'"
                                            :class="['px-2 py-1 rounded-lg font-bold transition-colors cursor-pointer', fontSize === 'normal' ? 'bg-white dark:bg-slate-600 text-emerald-600 dark:text-emerald-400 shadow-xs' : 'text-slate-500']"
                                        >
                                            A
                                        </button>
                                        <button
                                            @click="fontSize = 'lg'"
                                            :class="['px-2 py-1 rounded-lg font-bold transition-colors cursor-pointer', fontSize === 'lg' ? 'bg-white dark:bg-slate-600 text-emerald-600 dark:text-emerald-400 shadow-xs' : 'text-slate-500']"
                                        >
                                            A+
                                        </button>
                                        <button
                                            @click="fontSize = 'xl'"
                                            :class="['px-2 py-1 rounded-lg font-bold transition-colors cursor-pointer', fontSize === 'xl' ? 'bg-white dark:bg-slate-600 text-emerald-600 dark:text-emerald-400 shadow-xs' : 'text-slate-500']"
                                        >
                                            A++
                                        </button>
                                    </div>
                                </div>

                                <!-- Visibility Switches -->
                                <div class="flex flex-wrap items-center gap-3">
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
                                        <input type="checkbox" v-model="showLatin" class="rounded text-emerald-600 focus:ring-emerald-500 dark:bg-slate-700 dark:border-slate-600" />
                                        <span>Latin</span>
                                    </label>
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
                                        <input type="checkbox" v-model="showTranslation" class="rounded text-emerald-600 focus:ring-emerald-500 dark:bg-slate-700 dark:border-slate-600" />
                                        <span>Arti</span>
                                    </label>
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
                                        <input type="checkbox" v-model="showFaedah" class="rounded text-emerald-600 focus:ring-emerald-500 dark:bg-slate-700 dark:border-slate-600" />
                                        <span>Faedah</span>
                                    </label>
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
                                        <input type="checkbox" v-model="soundEnabled" class="rounded text-emerald-600 focus:ring-emerald-500 dark:bg-slate-700 dark:border-slate-600" />
                                        <span>Suara Klik</span>
                                    </label>
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
                                        <input type="checkbox" v-model="vibrateEnabled" class="rounded text-emerald-600 focus:ring-emerald-500 dark:bg-slate-700 dark:border-slate-600" />
                                        <span>Getar</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>

                <!-- ══════════════════════════════════════════════════════════ -->
                <!-- A. SLIDE MODE (SATU PER SATU DENGAN << DAN >> TANPA SCROLL) -->
                <!-- ══════════════════════════════════════════════════════════ -->
                <div v-if="viewMode === 'slide' && filteredList.length > 0 && currentDzikirItem" class="space-y-4">
                    <!-- Top Navigation Bar << >> & Indicator (Hidden on Mobile, Displayed on Desktop sm:flex) -->
                    <div class="hidden sm:flex items-center justify-between gap-1.5 sm:gap-3 bg-white dark:bg-slate-800 p-2.5 sm:p-4 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm w-full min-w-0">
                        <!-- Tombol << SEBELUMNYA -->
                        <button
                            @click="prevDzikir"
                            :disabled="!hasPrev"
                            :class="[
                                'flex-shrink-0 inline-flex items-center justify-center gap-1.5 px-3 py-2 sm:px-4 sm:py-2.5 rounded-2xl font-black text-xs sm:text-sm transition-all duration-150 select-none shadow-sm active:scale-95',
                                hasPrev
                                    ? 'bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer ring-2 ring-emerald-500/30'
                                    : 'bg-slate-100 dark:bg-slate-700/50 text-slate-300 dark:text-slate-600 cursor-not-allowed'
                            ]"
                            title="Ke Dzikir Sebelumnya (Tombol Panah Kiri)"
                        >
                            <span class="text-sm sm:text-base font-black tracking-tighter">&lt;&lt;</span>
                            <!-- <span class="hidden sm:inline"></span> -->
                        </button>

                        <!-- Indikator Nomor & Quick Dropdown Jump (Truncated & Flexible) -->
                        <div class="flex-1 min-w-0 flex items-center justify-center gap-1.5 px-1">
                            <select
                                :value="currentIndex"
                                @change="goToIndex(Number($event.target.value))"
                                class="w-full max-w-[170px] sm:max-w-[240px] truncate bg-slate-50 dark:bg-slate-700 text-slate-800 dark:text-slate-100 text-xs sm:text-sm font-black rounded-xl sm:rounded-2xl border-slate-200 dark:border-slate-600 py-1.5 sm:py-2 px-2 sm:px-3 focus:ring-2 focus:ring-emerald-500 cursor-pointer shadow-xs"
                            >
                                <option
                                    v-for="(item, idx) in filteredList"
                                    :key="item.id"
                                    :value="idx"
                                >
                                    {{ idx + 1 }}. {{ item.title }} {{ isCompleted(item) ? '✓' : '' }}
                                </option>
                            </select>
                            <span class="text-[11px] sm:text-xs font-black text-slate-400 dark:text-slate-500 whitespace-nowrap flex-shrink-0">
                                / {{ filteredList.length }}
                            </span>
                        </div>

                        <!-- Tombol >> BERIKUTNYA -->
                        <button
                            @click="nextDzikir"
                            :disabled="!hasNext"
                            :class="[
                                'flex-shrink-0 inline-flex items-center justify-center gap-1.5 px-3 py-2 sm:px-4 sm:py-2.5 rounded-2xl font-black text-xs sm:text-sm transition-all duration-150 select-none shadow-sm active:scale-95',
                                hasNext
                                    ? 'bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer ring-2 ring-emerald-500/30'
                                    : 'bg-slate-100 dark:bg-slate-700/50 text-slate-300 dark:text-slate-600 cursor-not-allowed'
                            ]"
                            title="Ke Dzikir Berikutnya (Tombol Panah Kanan)"
                        >
                            <!-- <span class="hidden sm:inline">next</span> -->
                            <span class="text-sm sm:text-base font-black tracking-tighter">&gt;&gt;</span>
                        </button>
                    </div>

                    <!-- Slide Item Card -->
                    <div
                        :class="[
                            'bg-white dark:bg-slate-800 rounded-3xl p-5 sm:p-8 border transition-all duration-300 shadow-md relative overflow-hidden',
                            isCompleted(currentDzikirItem)
                                ? 'border-emerald-400 dark:border-emerald-700/80 bg-emerald-50/15 dark:bg-emerald-950/10'
                                : 'border-slate-200 dark:border-slate-700'
                        ]"
                    >
                        <!-- Header of Current Slide -->
                        <div class="flex items-center justify-between gap-3 mb-4 sm:mb-6">
                            <div class="space-y-1 min-w-0">
                                <div class="flex items-center gap-2 sm:gap-2.5">
                                    <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-xl sm:rounded-2xl bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 font-black text-xs sm:text-sm flex items-center justify-center shadow-xs flex-shrink-0">
                                        {{ currentIndex + 1 }}
                                    </span>
                                    <h2 class="text-base sm:text-xl font-bold text-slate-800 dark:text-slate-100 truncate">
                                        {{ currentDzikirItem.title }}
                                    </h2>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 pl-9 sm:pl-10">
                                    <span v-if="currentDzikirItem.surah" class="font-semibold text-emerald-600 dark:text-emerald-400">
                                        {{ currentDzikirItem.surah }}
                                    </span>
                                    <span v-if="currentDzikirItem.surah">•</span>
                                    <span class="font-medium bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded-lg text-slate-600 dark:text-slate-300">
                                        {{ currentDzikirItem.note }}
                                    </span>
                                </div>
                            </div>

                            <!-- Header Quick Actions / Status Badge -->
                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                <span
                                    v-if="isCompleted(currentDzikirItem)"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 text-xs font-black shadow-xs"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Selesai</span>
                                </span>

                                <button
                                    v-if="!isCompleted(currentDzikirItem)"
                                    @click="completeDirectly(currentDzikirItem)"
                                    class="p-2 text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors cursor-pointer"
                                    title="Tandai langsung selesai"
                                >
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                                <button
                                    v-else
                                    @click="resetItemCount(currentDzikirItem)"
                                    class="p-2 text-slate-400 hover:text-red-500 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors cursor-pointer"
                                    title="Ulangi hitungan"
                                >
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Arabic Text Box -->
                        <div class="my-4 sm:my-5 text-right p-4 sm:p-8 bg-slate-50 dark:bg-slate-900/50 rounded-3xl border border-slate-100 dark:border-slate-800 shadow-inner">
                            <div
                                :class="[
                                    'w-full text-right font-arabic leading-loose text-slate-900 dark:text-slate-100 select-text',
                                    arabicFontSizeClass
                                ]"
                                dir="rtl"
                            >
                                {{ currentDzikirItem.arabic }}
                            </div>
                        </div>

                        <!-- Latin Transliteration -->
                        <div v-if="showLatin && currentDzikirItem.latin" class="mt-4 sm:mt-5 space-y-1 bg-emerald-50/40 dark:bg-emerald-950/20 p-3.5 sm:p-4 rounded-2xl border border-emerald-100 dark:border-emerald-900/40">
                            <p class="text-[11px] sm:text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                                Transliterasi:
                            </p>
                            <p class="text-xs sm:text-base font-medium text-slate-700 dark:text-slate-300 leading-relaxed italic">
                                {{ currentDzikirItem.latin }}
                            </p>
                        </div>

                        <!-- Translation (Arti) -->
                        <div v-if="showTranslation && currentDzikirItem.translation" class="mt-3.5 sm:mt-4 space-y-1 bg-slate-50 dark:bg-slate-900/30 p-3.5 sm:p-4 rounded-2xl border border-slate-100 dark:border-slate-800">
                            <p class="text-[11px] sm:text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                Terjemahan:
                            </p>
                            <p class="text-xs sm:text-base text-slate-600 dark:text-slate-300 leading-relaxed">
                                {{ currentDzikirItem.translation }}
                            </p>
                        </div>

                        <!-- Hadith Benefit & Virtues (Faedah) -->
                        <div
                            v-if="showFaedah && currentDzikirItem.faedah"
                            class="mt-4 sm:mt-5 p-3.5 sm:p-5 rounded-2xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200/70 dark:border-amber-800/40 text-xs sm:text-sm text-amber-900 dark:text-amber-200 space-y-1"
                        >
                            <div class="flex items-center gap-1.5 font-bold text-amber-800 dark:text-amber-300">
                                <svg class="w-4 h-4 flex-shrink-0 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                                <span>Keutamaan &amp; Riwayat:</span>
                            </div>
                            <p class="leading-relaxed pl-5">
                                {{ currentDzikirItem.faedah }}
                            </p>
                        </div>

                        <!-- Bottom Navigation: [ << ] ( [BULAT] Counter ) [ >> ] -->
                        <div class="mt-6 sm:mt-8 pt-5 sm:pt-6 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between gap-2 sm:gap-4 w-full min-w-0">
                            <!-- Tombol << Sebelumnya -->
                            <button
                                @click="prevDzikir"
                                :disabled="!hasPrev"
                                :class="[
                                    'flex-1 min-w-0 flex items-center justify-center gap-1.5 sm:gap-2 py-3 sm:py-3.5 px-3 sm:px-4 rounded-2xl font-black text-xs sm:text-sm transition-all duration-150 select-none shadow-sm active:scale-95 cursor-pointer',
                                    hasPrev
                                        ? 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-100 hover:bg-slate-200 dark:hover:bg-slate-600'
                                        : 'bg-slate-50 dark:bg-slate-800/40 text-slate-300 dark:text-slate-600 cursor-not-allowed'
                                ]"
                                title="Ke Dzikir Sebelumnya"
                            >
                                <span class="text-sm sm:text-base font-black tracking-tighter">&lt;&lt;</span>
                                <span class="hidden sm:inline">Sebelumnya</span>
                            </button>

                            <!-- Circular Counter Button in Center -->
                            <div class="flex items-center justify-center flex-shrink-0 px-1">
                                <button
                                    @click="incrementCount(currentDzikirItem)"
                                    :class="[
                                        'w-16 h-16 sm:w-20 sm:h-20 rounded-full font-black transition-all duration-150 flex flex-col items-center justify-center shadow-lg active:scale-90 select-none cursor-pointer ring-4',
                                        isCompleted(currentDzikirItem)
                                            ? 'bg-emerald-600 text-white hover:bg-emerald-700 ring-emerald-100 dark:ring-emerald-950/60 shadow-emerald-600/30'
                                            : 'bg-gradient-to-tr from-emerald-500 to-teal-400 hover:from-emerald-600 hover:to-teal-500 text-white ring-emerald-100 dark:ring-slate-700/70 shadow-emerald-500/30'
                                    ]"
                                    :title="isCompleted(currentDzikirItem) ? 'Target bacaan selesai' : 'Ketuk untuk menghitung'"
                                >
                                    <svg v-if="isCompleted(currentDzikirItem)" class="w-4 h-4 sm:w-5 sm:h-5 -mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span class="text-xs sm:text-base font-mono font-black tracking-tight leading-none">
                                        {{ getCount(currentDzikirItem.id) }}/{{ currentDzikirItem.target }}x
                                    </span>
                                    <span class="text-[9px] sm:text-[10px] font-bold opacity-90 uppercase tracking-wider mt-0.5">
                                        {{ isCompleted(currentDzikirItem) ? 'Selesai' : 'Hitung' }}
                                    </span>
                                </button>
                            </div>

                            <!-- Tombol >> Berikutnya -->
                            <button
                                @click="nextDzikir"
                                :disabled="!hasNext"
                                :class="[
                                    'flex-1 min-w-0 flex items-center justify-center gap-1.5 sm:gap-2 py-3 sm:py-3.5 px-3 sm:px-4 rounded-2xl font-black text-xs sm:text-sm transition-all duration-150 select-none shadow-md active:scale-95 cursor-pointer',
                                    hasNext
                                        ? 'bg-emerald-600 hover:bg-emerald-700 text-white ring-2 ring-emerald-500/40'
                                        : 'bg-slate-50 dark:bg-slate-800/40 text-slate-300 dark:text-slate-600 cursor-not-allowed'
                                ]"
                                title="Ke Dzikir Berikutnya"
                            >
                                <span class="hidden sm:inline">Berikutnya</span>
                                <span class="text-sm sm:text-base font-black tracking-tighter">&gt;&gt;</span>
                            </button>
                        </div>
                    </div>

                    <!-- Quick Jump Number Pills -->
                    <div class="bg-white dark:bg-slate-800 p-4 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm">
                        <div class="flex items-center justify-between mb-3 text-xs text-slate-500 dark:text-slate-400 font-semibold">
                            <span>Lompat ke Nomor Dzikir:</span>
                            <span>{{ currentIndex + 1 }} dari {{ filteredList.length }}</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="(item, idx) in filteredList"
                                :key="item.id"
                                @click="goToIndex(idx)"
                                :class="[
                                    'w-9 h-9 rounded-xl font-bold text-xs transition-all duration-150 flex items-center justify-center relative select-none',
                                    currentIndex === idx
                                        ? 'bg-emerald-600 text-white ring-2 ring-emerald-400 shadow-sm scale-105'
                                        : isCompleted(item)
                                            ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700'
                                            : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200'
                                ]"
                                :title="`${idx + 1}. ${item.title}`"
                            >
                                {{ idx + 1 }}
                                <span
                                    v-if="isCompleted(item) && currentIndex !== idx"
                                    class="absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-slate-800"
                                ></span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════════ -->
                <!-- B. LIST MODE (MODE DAFTAR BERURUTAN) -->
                <!-- ══════════════════════════════════════════════════════════ -->
                <div v-else-if="viewMode === 'list' && filteredList.length > 0" class="space-y-5">
                    <div
                        v-for="(item, index) in filteredList"
                        :key="item.id"
                        :class="[
                            'bg-white dark:bg-slate-800 rounded-3xl p-6 sm:p-7 border transition-all duration-300 shadow-sm relative overflow-hidden',
                            isCompleted(item)
                                ? 'border-emerald-300 dark:border-emerald-700/60 bg-emerald-50/20 dark:bg-emerald-950/10'
                                : 'border-slate-200 dark:border-slate-700 hover:border-slate-300'
                        ]"
                    >
                        <!-- Top Header of Card -->
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2.5">
                                    <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-black text-xs flex items-center justify-center">
                                        {{ index + 1 }}
                                    </span>
                                    <h2 class="text-base sm:text-lg font-bold text-slate-800 dark:text-slate-100">
                                        {{ item.title }}
                                    </h2>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 pl-9">
                                    <span v-if="item.surah" class="font-semibold text-emerald-600 dark:text-emerald-400">
                                        {{ item.surah }}
                                    </span>
                                    <span v-if="item.surah">•</span>
                                    <span class="font-medium bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded-lg text-slate-600 dark:text-slate-300">
                                        {{ item.note }}
                                    </span>
                                </div>
                            </div>

                            <!-- Interactive Target Counter Button -->
                            <div class="flex items-center gap-2">
                                <button
                                    @click="incrementCount(item)"
                                    :class="[
                                        'relative group px-4 py-2.5 rounded-2xl font-black text-sm transition-all duration-200 flex items-center gap-2 shadow-sm active:scale-95 select-none cursor-pointer',
                                        isCompleted(item)
                                            ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                                            : 'bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-100 hover:bg-emerald-500 hover:text-white dark:hover:bg-emerald-600'
                                    ]"
                                    :title="isCompleted(item) ? 'Target bacaan telah tercapai' : 'Klik untuk menghitung bacaan'"
                                >
                                    <svg v-if="isCompleted(item)" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span v-else class="text-xs opacity-75">Hitung:</span>

                                    <span class="text-base font-extrabold tracking-tight font-mono">
                                        {{ getCount(item.id) }} / {{ item.target }}x
                                    </span>
                                </button>

                                <button
                                    v-if="!isCompleted(item)"
                                    @click="completeDirectly(item)"
                                    class="p-2 text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                                    title="Tandai langsung selesai"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                                <button
                                    v-else
                                    @click="resetItemCount(item)"
                                    class="p-2 text-slate-400 hover:text-red-500 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                                    title="Ulangi hitungan"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Arabic Text -->
                        <div class="my-4 text-right p-4 sm:p-6 bg-slate-50 dark:bg-slate-900/40 rounded-2xl border border-slate-100 dark:border-slate-800">
                            <div
                                :class="[
                                    'w-full text-right font-arabic leading-loose text-slate-900 dark:text-slate-100 select-text',
                                    arabicFontSizeClass
                                ]"
                                dir="rtl"
                            >
                                {{ item.arabic }}
                            </div>
                        </div>

                        <!-- Latin Transliteration -->
                        <div v-if="showLatin && item.latin" class="mt-4 space-y-1">
                            <p class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                                Transliterasi:
                            </p>
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-300 leading-relaxed italic">
                                {{ item.latin }}
                            </p>
                        </div>

                        <!-- Translation (Arti) -->
                        <div v-if="showTranslation && item.translation" class="mt-4 space-y-1">
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                Terjemahan:
                            </p>
                            <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                {{ item.translation }}
                            </p>
                        </div>

                        <!-- Hadith Benefit & Virtues (Faedah) -->
                        <div
                            v-if="showFaedah && item.faedah"
                            class="mt-5 p-4 rounded-2xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200/70 dark:border-amber-800/40 text-xs text-amber-900 dark:text-amber-200 space-y-1"
                        >
                            <div class="flex items-center gap-1.5 font-bold text-amber-800 dark:text-amber-300">
                                <svg class="w-4 h-4 flex-shrink-0 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                                <span>Keutamaan &amp; Riwayat:</span>
                            </div>
                            <p class="leading-relaxed pl-5">
                                {{ item.faedah }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="bg-white dark:bg-slate-800 rounded-3xl p-12 text-center border border-slate-200 dark:border-slate-700">
                    <svg class="w-12 h-12 text-slate-300 dark:text-slate-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <h3 class="font-bold text-slate-700 dark:text-slate-200 text-base">Tidak ada dzikir yang cocok</h3>
                    <p class="text-xs text-slate-400 mt-1">Coba ubah kata kunci pencarian atau filter status Anda.</p>
                </div>
            </div>
            <TasbihMode v-else :soundEnabled="soundEnabled" @update:soundEnabled="soundEnabled = $event; saveSettings()" :vibrateEnabled="vibrateEnabled" @update:vibrateEnabled="vibrateEnabled = $event; saveSettings()" />


        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.font-arabic {
    font-family: 'Scheherazade New', 'Amiri Quran', 'Amiri', 'Traditional Arabic', serif;
    line-height: 2.2;
}
</style>
