<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    status: { type: String, default: 'info' }, // ok | warn | error | info
})

const dotClass = {
    ok: 'bg-emerald-500',
    warn: 'bg-amber-500',
    error: 'bg-rose-500',
    info: 'bg-slate-400',
}

const textClass = {
    ok: 'text-emerald-700 dark:text-emerald-400',
    warn: 'text-amber-700 dark:text-amber-400',
    error: 'text-rose-700 dark:text-rose-400',
    info: 'text-slate-700 dark:text-slate-300',
}
</script>

<template>
    <div class="flex items-center justify-between gap-2 py-2 px-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 text-xs">
        <div class="flex items-center gap-2 min-w-0">
            <span class="w-2 h-2 rounded-full shrink-0" :class="dotClass[status] || dotClass.info" aria-hidden="true" />
            <span class="text-slate-600 dark:text-slate-300 truncate">{{ label }}</span>
        </div>
        <span class="font-bold tabular-nums shrink-0" :class="textClass[status] || textClass.info">
            {{ value }}
        </span>
    </div>
</template>
