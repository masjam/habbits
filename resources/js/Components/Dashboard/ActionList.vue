<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    title: { type: String, required: true },
    items: { type: Array, default: () => [] },
    emptyText: { type: String, default: 'Semua beres — tidak ada item tertunda.' },
    viewAllHref: { type: String, default: null },
    viewAllLabel: { type: String, default: 'Lihat semua' },
})

const badgeClass = (tone) => ({
    good: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
    warn: 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
    bad: 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300',
    neutral: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
}[tone] || 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300')
</script>

<template>
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 flex flex-col min-h-0 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2 shrink-0">
            <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100 truncate">{{ title }}</h2>
            <Link
                v-if="viewAllHref"
                :href="viewAllHref"
                class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline shrink-0"
            >
                {{ viewAllLabel }} →
            </Link>
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
            <template v-if="items.length > 0">
                <component
                    :is="item.href ? Link : 'div'"
                    v-for="(item, idx) in items"
                    :key="item.id ?? idx"
                    :href="item.href"
                    class="flex items-center justify-between gap-3 px-4 py-2.5 text-xs transition-colors"
                    :class="item.href ? 'hover:bg-slate-50 dark:hover:bg-slate-800/60 cursor-pointer' : ''"
                >
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-800 dark:text-slate-200 truncate">{{ item.title }}</p>
                        <p v-if="item.subtitle" class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5">{{ item.subtitle }}</p>
                    </div>
                    <span
                        v-if="item.badge"
                        class="px-2 py-0.5 rounded-full text-xs font-semibold shrink-0 tabular-nums"
                        :class="badgeClass(item.tone)"
                    >
                        {{ item.badge }}
                    </span>
                </component>
            </template>
            <div v-else class="px-4 py-6 text-center text-xs text-slate-500 dark:text-slate-400">
                {{ emptyText }}
            </div>
        </div>
    </div>
</template>
