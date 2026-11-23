<script setup>
import { onMounted, onUnmounted, watch } from 'vue'
import { useConfirm } from '@/Composables/useConfirm'

const { isOpen, dialogState, handleConfirm, handleCancel } = useConfirm()

const handleKeydown = (e) => {
    if (isOpen.value && e.key === 'Escape') {
        handleCancel()
    }
}

watch(isOpen, (open) => {
    if (typeof document !== 'undefined') {
        if (open) {
            document.body.style.overflow = 'hidden'
        } else {
            document.body.style.overflow = ''
        }
    }
})

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('keydown', handleKeydown)
    }
})

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', handleKeydown)
        document.body.style.overflow = ''
    }
})
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isOpen"
                class="fixed inset-0 z-[9998] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                @click.self="handleCancel"
                role="dialog"
                aria-modal="true"
            >
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="scale-95 opacity-0"
                    enter-to-class="scale-100 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="scale-100 opacity-100"
                    leave-to-class="scale-95 opacity-0"
                >
                    <div
                        v-if="isOpen"
                        class="bg-white/85 dark:bg-slate-900/85 backdrop-blur-2xl rounded-2xl shadow-2xl border border-white/60 dark:border-white/10 max-w-md w-full p-6 overflow-hidden relative"
                    >
                        <!-- Header with Icon -->
                        <div class="flex items-start gap-4">
                            <!-- Danger Icon -->
                            <div
                                v-if="dialogState.type === 'danger'"
                                class="w-12 h-12 rounded-xl bg-rose-100 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 flex items-center justify-center flex-shrink-0"
                            >
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </div>

                            <!-- Warning Icon -->
                            <div
                                v-else-if="dialogState.type === 'warning'"
                                class="w-12 h-12 rounded-xl bg-amber-100 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 flex items-center justify-center flex-shrink-0"
                            >
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>

                            <!-- Primary Icon -->
                            <div
                                v-else
                                class="w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center flex-shrink-0"
                            >
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>

                            <!-- Text Content -->
                            <div class="flex-1 min-w-0">
                                <h3 class="text-base font-bold text-slate-900 dark:text-slate-100 leading-snug">
                                    {{ dialogState.title }}
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">
                                    {{ dialogState.message }}
                                </p>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="mt-6 flex items-center justify-end gap-2.5">
                            <button
                                type="button"
                                @click="handleCancel"
                                class="px-4 py-2 text-xs font-semibold rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors focus:outline-none focus:ring-2 focus:ring-slate-400"
                            >
                                {{ dialogState.cancelText }}
                            </button>

                            <button
                                type="button"
                                @click="handleConfirm"
                                :class="[
                                    'px-4 py-2 text-xs font-semibold rounded-xl text-white transition-all shadow-sm focus:outline-none focus:ring-2',
                                    dialogState.type === 'danger' && 'bg-rose-600 hover:bg-rose-700 focus:ring-rose-500 shadow-rose-600/30',
                                    dialogState.type === 'warning' && 'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500 shadow-amber-600/30',
                                    dialogState.type === 'primary' && 'bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500 shadow-emerald-600/30',
                                ]"
                            >
                                {{ dialogState.confirmText }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
