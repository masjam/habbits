<script setup>
import { ref, computed } from 'vue'
import { PRESET_TASBIH } from '@/Data/dzikirData'
import { useDzikirSound } from '@/Composables/useDzikirSound'

const emit = defineEmits(['update:soundEnabled', 'update:vibrateEnabled']);

const props = defineProps({
    soundEnabled: Boolean,
    vibrateEnabled: Boolean
})

const { playTickSound, triggerVibrate } = useDzikirSound()

// ─── Standalone Tasbih Mode State ────────────────────────────────────────────
const selectedPresetIndex = ref(0)
const customTarget = ref(33)
const tasbihCount = ref(0)
const tasbihLap = ref(1)

const activePreset = computed(() => PRESET_TASBIH[selectedPresetIndex.value])

const selectPreset = (index) => {
    selectedPresetIndex.value = index
    customTarget.value = PRESET_TASBIH[index].target
    tasbihCount.value = 0
    tasbihLap.value = 1
}

const incrementTasbih = () => {
    const target = customTarget.value || 33
    tasbihCount.value += 1
    const justFinishedLap = tasbihCount.value % target === 0
    if (justFinishedLap) {
        tasbihLap.value += 1
        playTickSound(true, props.soundEnabled)
        triggerVibrate(true, props.vibrateEnabled)
    } else {
        playTickSound(false, props.soundEnabled)
        triggerVibrate(false, props.vibrateEnabled)
    }
}

const resetTasbih = () => {
    tasbihCount.value = 0
    tasbihLap.value = 1
}


</script>

<template>
<!-- ══════════════════════════════════════════════════════════════ -->
            <!-- SECTION 4: STANDALONE DIGITAL TASBIH MODE -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            <div class="space-y-6">
                <!-- Preset Selection (Dropdown on Mobile, Grid on Desktop) -->
                <div class="bg-white dark:bg-slate-800 rounded-3xl p-4 sm:p-5 border border-slate-200 dark:border-slate-700 shadow-sm space-y-2.5">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        Pilih Lafadz Dzikir:
                    </p>

                    <!-- Mobile: Single Dropdown Select -->
                    <div class="sm:hidden">
                        <div class="relative">
                            <select
                                :value="selectedPresetIndex"
                                @change="selectPreset(Number($event.target.value))"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-800 dark:text-slate-100 text-sm font-bold rounded-2xl border border-slate-200 dark:border-slate-600 py-3 pl-4 pr-10 focus:ring-2 focus:ring-emerald-500 cursor-pointer shadow-xs appearance-none"
                            >
                                <option
                                    v-for="(preset, idx) in PRESET_TASBIH"
                                    :key="preset.id"
                                    :value="idx"
                                >
                                    {{ preset.name }} — {{ preset.arabic }}
                                </option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3.5 text-slate-500">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop: 4-Column Grid Cards -->
                    <div class="hidden sm:grid sm:grid-cols-4 gap-2.5">
                        <button
                            v-for="(preset, idx) in PRESET_TASBIH"
                            :key="preset.id"
                            @click="selectPreset(idx)"
                            :class="[
                                'p-3 rounded-2xl border text-left transition-all duration-200 cursor-pointer',
                                selectedPresetIndex === idx
                                    ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 font-bold shadow-sm'
                                    : 'border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/50 text-slate-700 dark:text-slate-300 hover:border-emerald-300'
                            ]"
                        >
                            <p class="text-xs font-bold">{{ preset.name }}</p>
                            <p class="text-base font-arabic text-emerald-600 dark:text-emerald-400 mt-1" dir="rtl">{{ preset.arabic }}</p>
                        </button>
                    </div>
                </div>

                <!-- Main Tasbih Digital Interactive Circle -->
                <div class="bg-white dark:bg-slate-800 rounded-3xl p-8 sm:p-12 border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col items-center justify-center text-center relative overflow-hidden">
                    <!-- Current Active Lafadz -->
                    <div class="space-y-2 mb-8 max-w-lg">
                        <div class="font-arabic text-3xl sm:text-4xl text-slate-900 dark:text-white leading-loose select-text" dir="rtl">
                            {{ activePreset.arabic }}
                        </div>
                        <p class="text-base font-bold text-emerald-600 dark:text-emerald-400">
                            {{ activePreset.name }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            "{{ activePreset.meaning }}"
                        </p>
                    </div>

                    <!-- Target selector -->
                    <div class="flex items-center gap-2 mb-8">
                        <span class="text-xs font-semibold text-slate-500">Target:</span>
                        <div class="inline-flex rounded-xl bg-slate-100 dark:bg-slate-700 p-0.5">
                            <button
                                v-for="t in [33, 100, 1000]"
                                :key="t"
                                @click="customTarget = t; tasbihCount = 0; tasbihLap = 1"
                                :class="[
                                    'px-3 py-1 text-xs font-bold rounded-lg transition-colors cursor-pointer',
                                    customTarget === t
                                        ? 'bg-white dark:bg-slate-600 text-emerald-600 dark:text-emerald-400 shadow-xs'
                                        : 'text-slate-500 hover:text-slate-800'
                                ]"
                            >
                                {{ t }}x
                            </button>
                        </div>
                    </div>

                    <!-- Big Tap Button -->
                    <div class="relative flex items-center justify-center mb-8">
                        <button
                            @click="incrementTasbih"
                            class="w-56 h-56 sm:w-64 sm:h-64 rounded-full bg-gradient-to-tr from-emerald-500 to-teal-400 hover:from-emerald-600 hover:to-teal-500 text-white shadow-2xl shadow-emerald-600/30 flex flex-col items-center justify-center active:scale-95 transition-transform duration-150 focus:outline-none ring-8 ring-emerald-50 dark:ring-slate-700/50 cursor-pointer"
                        >
                            <span class="text-xs uppercase tracking-widest font-bold opacity-80 mb-1">
                                Putaran Ke-{{ tasbihLap }}
                            </span>
                            <span class="text-6xl sm:text-7xl font-black tracking-tight drop-shadow-sm font-mono">
                                {{ tasbihCount }}
                            </span>
                            <span class="text-xs font-semibold mt-2 opacity-90">
                                Target: {{ customTarget }}x
                            </span>
                        </button>
                    </div>

                    <!-- Tasbih Controls -->
                    <div class="flex items-center gap-3">
                        <button
                            @click="resetTasbih"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold hover:bg-red-50 hover:text-red-600 transition-colors cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span>Reset Hitungan</span>
                        </button>

                        <div class="flex items-center gap-3 pl-3 border-l border-slate-200 dark:border-slate-700 text-xs text-slate-500">
                            <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox" :checked="soundEnabled" @change="$emit('update:soundEnabled', $event.target.checked)" class="rounded text-emerald-600 focus:ring-emerald-500 dark:bg-slate-700" />
                                <span>Suara</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox" :checked="vibrateEnabled" @change="$emit('update:vibrateEnabled', $event.target.checked)" class="rounded text-emerald-600 focus:ring-emerald-500 dark:bg-slate-700" />
                                <span>Getar</span>
                            </label>
                        </div>
                    </div>
                </div>
            
</div>
</template>
