<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    links: {
        type: Array,
        required: true,
    },
    from: {
        type: Number,
        default: null,
    },
    to: {
        type: Number,
        default: null,
    },
    total: {
        type: Number,
        default: null,
    },
})
</script>

<template>
    <div v-if="links && links.length > 3" class="flex flex-col sm:flex-row items-center justify-between gap-3 py-3 px-2">
        <div v-if="total !== null" class="text-xs text-slate-500 dark:text-slate-400">
            Menampilkan <span class="font-semibold text-slate-700 dark:text-slate-200">{{ from || 0 }}</span> sampai
            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ to || 0 }}</span> dari
            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ total }}</span> data
        </div>

        <nav class="flex items-center gap-1 flex-wrap" aria-label="Pagination">
            <template v-for="(link, key) in links" :key="key">
                <span
                    v-if="!link.url"
                    class="px-3 py-1.5 text-xs text-slate-400 dark:text-slate-600 rounded-lg select-none"
                    v-html="link.label"
                />
                <Link
                    v-else
                    :href="link.url"
                    preserve-scroll
                    :class="[
                        'px-3 py-1.5 text-xs font-medium rounded-lg transition-colors',
                        link.active
                            ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/30 font-bold'
                            : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700'
                    ]"
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>
