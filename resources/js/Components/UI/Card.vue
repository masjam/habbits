<script setup>
defineProps({
    padding: {
        type: Boolean,
        default: true,
    },
})
</script>

<template>
    <div class="bg-white/75 dark:bg-slate-900/75 backdrop-blur-xl border border-white/60 dark:border-white/10 rounded-2xl shadow-xl shadow-slate-900/5 dark:shadow-slate-950/20 overflow-hidden transition-colors">
        <div v-if="$slots.header" class="px-4 sm:px-6 py-4 bg-white/40 dark:bg-slate-800/40 backdrop-blur-md border-b border-slate-200/50 dark:border-white/5 flex items-center justify-between gap-4">
            <slot name="header" />
        </div>
        <div :class="padding ? 'p-4 sm:p-6' : ''">
            <slot />
        </div>
        <div v-if="$slots.footer" class="px-4 sm:px-6 py-3.5 bg-slate-50/50 dark:bg-slate-900/50 backdrop-blur-md border-t border-slate-200/50 dark:border-white/5 flex items-center justify-between gap-3">
            <slot name="footer" />
        </div>
    </div>
</template>
