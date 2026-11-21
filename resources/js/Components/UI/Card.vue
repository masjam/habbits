<script setup>
defineProps({
    padding: {
        type: Boolean,
        default: true,
    },
})
</script>

<template>
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-xs overflow-hidden transition-colors">
        <div v-if="$slots.header" class="px-4 sm:px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-4">
            <slot name="header" />
        </div>
        <div :class="padding ? 'p-4 sm:p-6' : ''">
            <slot />
        </div>
        <div v-if="$slots.footer" class="px-4 sm:px-6 py-3.5 bg-slate-50/70 dark:bg-slate-800/60 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between gap-3">
            <slot name="footer" />
        </div>
    </div>
</template>
