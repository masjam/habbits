<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { formatTime } from '@/utils/formatters'

defineProps({
    personal: { type: Object, required: true },
})

const page = usePage()
const featurePresensi = computed(() => {
    const v = page.props.global_settings?.feature_presensi
    return v === undefined || v === '1' || v === 'true' || v === true
})
const featureHabit = computed(() => {
    const v = page.props.global_settings?.feature_habit
    return v === undefined || v === '1' || v === 'true' || v === true
})
</script>

<template>
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-3.5 shrink-0 space-y-2.5">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-xs font-bold text-slate-900 dark:text-slate-100">Ringkasan pribadi hari ini</h2>
            <span class="text-xs text-slate-500 dark:text-slate-400 tabular-nums">
                {{ personal.work_hours?.start }}–{{ personal.work_hours?.end }}
            </span>
        </div>

        <div class="grid grid-cols-2 gap-2 text-xs">
            <Link
                v-if="featurePresensi && personal.attendance_today"
                :href="route('attendance.index')"
                class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 hover:bg-primary-50 dark:hover:bg-primary-950/40 transition-colors"
            >
                <div class="text-slate-500 dark:text-slate-400">Presensi</div>
                <div
                    class="font-bold mt-0.5 tabular-nums"
                    :class="personal.attendance_today.checked_in ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'"
                >
                    {{ personal.attendance_today.checked_in ? formatTime(personal.attendance_today.time_in) : 'Belum masuk' }}
                </div>
            </Link>

            <Link
                v-if="featureHabit"
                :href="route('habit.form')"
                class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 hover:bg-primary-50 dark:hover:bg-primary-950/40 transition-colors"
            >
                <div class="text-slate-500 dark:text-slate-400">Habit hari ini</div>
                <div class="font-bold text-primary-600 dark:text-primary-400 mt-0.5 tabular-nums">
                    {{ personal.habit_today?.score || 0 }} / {{ personal.habit_today?.max || 0 }}
                </div>
            </Link>
        </div>
    </div>
</template>
