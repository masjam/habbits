<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: null,
    },
})
</script>

<template>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2">
        <div class="space-y-1">
            <div class="flex items-center gap-2.5 flex-wrap">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">
                    {{ title }}
                </h1>
                <slot name="badge" />
            </div>
            <p v-if="subtitle" class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                {{ subtitle }}
            </p>
        </div>
        <div v-if="$slots.actions" class="flex items-center gap-2.5 flex-wrap shrink-0">
            <slot name="actions" />
        </div>
    </div>
</template>
