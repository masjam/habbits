<script setup>
import { computed } from 'vue'

const props = defineProps({
    percent: { type: Number, default: 0 },
    size: { type: Number, default: 56 },
    stroke: { type: Number, default: 5 },
    tone: { type: String, default: 'primary' }, // primary | good | warn | bad
})

const clamped = computed(() => Math.max(0, Math.min(100, Number(props.percent) || 0)))
const radius = computed(() => (props.size - props.stroke) / 2)
const circumference = computed(() => 2 * Math.PI * radius.value)
const offset = computed(() => circumference.value - (clamped.value / 100) * circumference.value)

const strokeClass = computed(() => ({
    primary: 'stroke-primary-600 dark:stroke-primary-400',
    good: 'stroke-emerald-600 dark:stroke-emerald-400',
    warn: 'stroke-amber-500',
    bad: 'stroke-rose-600 dark:stroke-rose-400',
}[props.tone] || 'stroke-primary-600'))
</script>

<template>
    <div class="relative inline-flex items-center justify-center shrink-0" :style="{ width: `${size}px`, height: `${size}px` }">
        <svg :width="size" :height="size" class="-rotate-90" aria-hidden="true">
            <circle
                :cx="size / 2"
                :cy="size / 2"
                :r="radius"
                fill="none"
                :stroke-width="stroke"
                class="stroke-slate-200 dark:stroke-slate-800"
            />
            <circle
                :cx="size / 2"
                :cy="size / 2"
                :r="radius"
                fill="none"
                :stroke-width="stroke"
                stroke-linecap="round"
                :stroke-dasharray="circumference"
                :stroke-dashoffset="offset"
                :class="strokeClass"
            />
        </svg>
        <span class="absolute text-xs font-bold text-slate-900 dark:text-slate-100 tabular-nums">
            {{ Math.round(clamped) }}%
        </span>
    </div>
</template>
