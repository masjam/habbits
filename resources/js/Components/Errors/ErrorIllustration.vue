<script setup>
defineProps({
    icon: { type: String, default: 'server' },
    tone: { type: String, default: 'neutral' },
})

const wrapClass = {
    info: 'bg-sky-50 text-sky-600 border-sky-200/80 dark:bg-sky-950/50 dark:text-sky-400 dark:border-sky-800/60',
    warn: 'bg-amber-50 text-amber-600 border-amber-200/80 dark:bg-amber-950/50 dark:text-amber-400 dark:border-amber-800/60',
    danger: 'bg-rose-50 text-rose-600 border-rose-200/80 dark:bg-rose-950/50 dark:text-rose-400 dark:border-rose-800/60',
    neutral: 'bg-slate-100 text-slate-600 border-slate-200/80 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
}
</script>

<template>
    <div
        class="w-16 h-16 rounded-2xl border flex items-center justify-center mx-auto"
        :class="wrapClass[tone] || wrapClass.neutral"
        aria-hidden="true"
    >
        <!-- 404 Not Found -->
        <svg v-if="icon === 'not_found'" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.5 9.5l3 3m0-3l-3 3" />
        </svg>

        <!-- 403 / 401 Forbidden -->
        <svg v-else-if="icon === 'forbidden'" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>

        <!-- 429 Rate Limit / 408 / 413 -->
        <svg v-else-if="icon === 'rate_limit' || icon === 'timeout' || icon === 'expired'" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>

        <!-- 503 Maintenance -->
        <svg v-else-if="icon === 'maintenance'" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>

        <!-- 500 / Bad Request -->
        <svg v-else class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
    </div>
</template>
