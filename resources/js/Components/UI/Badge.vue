<script setup>
const props = defineProps({
    variant: {
        type: String,
        default: 'neutral', // 'primary' | 'success' | 'danger' | 'warning' | 'info' | 'neutral'
    },
    size: {
        type: String,
        default: 'sm', // 'sm' | 'md'
    },
})

const variantClasses = {
    primary: 'bg-primary-100 dark:bg-primary-900/50 text-primary-800 dark:text-primary-200 border-primary-200 dark:border-primary-800',
    success: 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
    danger:  'bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800',
    warning: 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
    info:    'bg-sky-50 dark:bg-sky-950/50 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800',
    neutral: 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700',
}

const sizeClasses = {
    sm: 'px-2.5 py-0.5 text-[11px] gap-1',
    md: 'px-3 py-1 text-xs gap-1.5',
}
</script>

<template>
    <span
        :class="[
            'inline-flex items-center font-semibold rounded-full border shadow-2xs select-none transition-colors',
            variantClasses[props.variant] || variantClasses.neutral,
            sizeClasses[props.size] || sizeClasses.sm,
        ]"
    >
        <slot />
    </span>
</template>
