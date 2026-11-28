<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    subtext: { type: String, default: '' },
    delta: { type: Number, default: null },
    deltaLabel: { type: String, default: '' },
    progress: { type: Number, default: null },
    tone: { type: String, default: 'neutral' }, // neutral | good | warn | bad | primary
    href: { type: String, default: null },
})

const toneMap = {
    neutral: 'text-slate-900 dark:text-slate-100',
    primary: 'text-primary-600 dark:text-primary-400',
    good: 'text-emerald-600 dark:text-emerald-400',
    warn: 'text-amber-600 dark:text-amber-400',
    bad: 'text-rose-600 dark:text-rose-400',
}

const barMap = {
    neutral: 'bg-slate-600 dark:bg-slate-400',
    primary: 'bg-primary-600 dark:bg-primary-500',
    good: 'bg-emerald-600 dark:bg-emerald-500',
    warn: 'bg-amber-500',
    bad: 'bg-rose-600 dark:bg-rose-500',
}
</script>

<template>
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-3.5 sm:p-4 flex flex-col justify-between min-w-0">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 truncate">{{ label }}</span>
            <span
                v-if="delta !== null && delta !== undefined"
                class="text-xs font-bold tabular-nums shrink-0"
                :class="delta >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
            >
                {{ delta >= 0 ? '▲' : '▼' }} {{ Math.abs(delta) }}%{{ deltaLabel ? ` ${deltaLabel}` : '' }}
            </span>
        </div>

        <div class="mt-2">
            <div class="text-xl sm:text-2xl font-extrabold tracking-tight tabular-nums truncate" :class="toneMap[tone] || toneMap.neutral">
                {{ value }}
            </div>
            <div v-if="progress !== null && progress !== undefined" class="mt-2 h-1.5 w-full rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                <div
                    class="h-full rounded-full transition-transform duration-300"
                    :class="barMap[tone] || barMap.primary"
                    :style="{ width: `${Math.max(0, Math.min(100, Number(progress) || 0))}%` }"
                />
            </div>
        </div>

        <div class="mt-2 flex items-center justify-between gap-2 min-w-0">
            <span v-if="subtext" class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ subtext }}</span>
            <Link
                v-if="href"
                :href="href"
                class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline shrink-0 ml-auto"
            >
                Buka →
            </Link>
        </div>
    </div>
</template>
