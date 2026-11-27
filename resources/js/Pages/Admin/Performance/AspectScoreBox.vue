<script setup>
defineProps({
    aspectsConfig: { type: Object, required: true },
    aspectScores: { type: Object, default: () => ({}) },
    hasEvaluation: { type: Boolean, default: false },
})

const shortLabel = (label) => label.replace(/^Kompetensi\s+/i, '')
</script>

<template>
    <div class="grid grid-cols-2 gap-2">
        <div
            v-for="(meta, key) in aspectsConfig"
            :key="key"
            class="bg-slate-50 dark:bg-slate-900/50 p-2.5 rounded-xl border border-slate-100 dark:border-slate-700 text-center"
        >
            <div class="text-[10px] uppercase font-bold text-slate-400 truncate" :title="meta.label">
                {{ shortLabel(meta.label) }}
            </div>
            <div class="text-lg font-extrabold text-slate-700 dark:text-slate-200 mt-0.5">
                {{ hasEvaluation && aspectScores?.[key] !== undefined ? Number(aspectScores[key]).toFixed(1) : '-' }}
            </div>
        </div>
    </div>
</template>
