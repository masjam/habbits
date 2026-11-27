<script setup>
defineProps({
    insight: { type: Object, default: null },
    loading: { type: Boolean, default: false },
})
</script>

<template>
    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/40 p-4 space-y-3">
        <div class="flex items-center justify-between">
            <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-600 dark:text-slate-300">
                Auto-Insight Kinerja Periode Ini
            </h4>
            <span v-if="insight" class="text-[11px] text-slate-400">
                {{ insight.period_start }} s/d {{ insight.period_end }}
            </span>
        </div>

        <!-- Skeleton Loading -->
        <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-4 gap-3 animate-pulse">
            <div v-for="i in 4" :key="i" class="h-20 rounded-xl bg-slate-200 dark:bg-slate-700"></div>
        </div>

        <!-- Insight Mini-Cards -->
        <div v-else-if="insight" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
            <!-- Kehadiran -->
            <div class="p-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                <div class="text-[10px] font-bold uppercase text-slate-400">Kehadiran</div>
                <template v-if="insight.attendance?.has_data">
                    <div class="text-lg font-black text-emerald-600 dark:text-emerald-400 mt-0.5">
                        {{ insight.attendance.attendance_rate !== null ? insight.attendance.attendance_rate + '%' : '-' }}
                    </div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                        Hadir {{ insight.attendance.hadir }} • Telat {{ insight.attendance.terlambat }} • Izin {{ insight.attendance.izin_sakit }}
                    </div>
                </template>
                <div v-else class="text-[11px] text-slate-400 italic mt-2">Tidak ada data presensi</div>
            </div>

            <!-- Habit -->
            <div class="p-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                <div class="text-[10px] font-bold uppercase text-slate-400">Konsistensi Habit</div>
                <template v-if="insight.habit?.has_data">
                    <div class="text-lg font-black text-sky-600 dark:text-sky-400 mt-0.5">
                        {{ insight.habit.consistency_rate !== null ? insight.habit.consistency_rate + '%' : '-' }}
                    </div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                        {{ insight.habit.filled_days }} / {{ insight.habit.work_days }} hari kerja
                    </div>
                </template>
                <div v-else class="text-[11px] text-slate-400 italic mt-2">Tidak ada data habit</div>
            </div>

            <!-- Administrasi Guru -->
            <div v-if="insight.teacher_administration !== null" class="p-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                <div class="text-[10px] font-bold uppercase text-slate-400">Administrasi Guru</div>
                <template v-if="insight.teacher_administration?.has_data">
                    <div class="text-lg font-black text-primary-600 dark:text-primary-400 mt-0.5">
                        {{ insight.teacher_administration.approved }} / {{ insight.teacher_administration.uploaded }}
                    </div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                        Disetujui ({{ insight.teacher_administration.approval_rate }}%)
                    </div>
                </template>
                <div v-else class="text-[11px] text-slate-400 italic mt-2">Belum ada berkas</div>
            </div>

            <!-- Jurnal Guru -->
            <div v-if="insight.teacher_journal !== null" class="p-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                <div class="text-[10px] font-bold uppercase text-slate-400">Jurnal Mengajar</div>
                <template v-if="insight.teacher_journal?.has_data">
                    <div class="text-lg font-black text-amber-600 dark:text-amber-400 mt-0.5">
                        {{ insight.teacher_journal.journal_rate !== null ? insight.teacher_journal.journal_rate + '%' : '-' }}
                    </div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                        {{ insight.teacher_journal.total_entries }} entri ({{ insight.teacher_journal.filled_days }} hari)
                    </div>
                </template>
                <div v-else class="text-[11px] text-slate-400 italic mt-2">Belum ada jurnal</div>
            </div>
        </div>
    </div>
</template>
