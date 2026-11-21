<script setup>
import { useToast } from '@/Composables/useToast'

const { toasts, removeToast } = useToast()
</script>

<template>
    <div
        aria-live="polite"
        class="fixed top-4 right-4 z-[9999] flex flex-col gap-2.5 max-w-sm w-full pointer-events-none px-4 sm:px-0"
    >
        <TransitionGroup
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-4"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                :class="[
                    'pointer-events-auto w-full rounded-xl shadow-lg border p-3.5 flex items-start gap-3 backdrop-blur-md transition-all',
                    toast.type === 'success' && 'bg-emerald-50/95 dark:bg-emerald-950/90 border-emerald-200 dark:border-emerald-800 text-emerald-900 dark:text-emerald-100 shadow-emerald-500/10',
                    toast.type === 'error' && 'bg-rose-50/95 dark:bg-rose-950/90 border-rose-200 dark:border-rose-800 text-rose-900 dark:text-rose-100 shadow-rose-500/10',
                    toast.type === 'warning' && 'bg-amber-50/95 dark:bg-amber-950/90 border-amber-200 dark:border-amber-800 text-amber-900 dark:text-amber-100 shadow-amber-500/10',
                    toast.type === 'info' && 'bg-sky-50/95 dark:bg-sky-950/90 border-sky-200 dark:border-sky-800 text-sky-900 dark:text-sky-100 shadow-sky-500/10',
                ]"
            >
                <!-- Icon -->
                <div class="flex-shrink-0 mt-0.5">
                    <div v-if="toast.type === 'success'" class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div v-else-if="toast.type === 'error'" class="w-6 h-6 rounded-full bg-rose-500 text-white flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                    <div v-else-if="toast.type === 'warning'" class="w-6 h-6 rounded-full bg-amber-500 text-white flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div v-else class="w-6 h-6 rounded-full bg-sky-500 text-white flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>

                <!-- Text + Action -->
                <div class="flex-1 min-w-0">
                    <p v-if="toast.title" class="text-xs font-bold leading-tight mb-0.5">
                        {{ toast.title }}
                    </p>
                    <p class="text-xs leading-relaxed opacity-90 break-words">
                        {{ toast.message }}
                    </p>
                    <!-- Tombol download jika ada action -->
                    <a
                        v-if="toast.action"
                        :href="toast.action.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        @click="removeToast(toast.id)"
                        class="mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-white dark:bg-slate-800 border border-current/20 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        {{ toast.action.label }}
                    </a>
                </div>

                <!-- Close Button -->
                <button
                    @click="removeToast(toast.id)"
                    class="flex-shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors p-1 -mr-1 -mt-1 rounded-lg"
                    aria-label="Tutup pemberitahuan"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
