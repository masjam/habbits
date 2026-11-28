<script setup>
import { computed } from 'vue'
import { Link, Deferred, usePage } from '@inertiajs/vue3'
import DashboardShell from '@/Components/Dashboard/DashboardShell.vue'
import StatCard from '@/Components/Dashboard/StatCard.vue'
import ActionList from '@/Components/Dashboard/ActionList.vue'
import MiniTrendChart from '@/Components/Dashboard/MiniTrendChart.vue'
import PersonalMiniCard from '@/Pages/Dashboard/Partials/PersonalMiniCard.vue'
import NotificationsCard from '@/Pages/Dashboard/Partials/NotificationsCard.vue'
import { useIslamicData } from '@/Composables/useIslamicData'
import { formatNumber } from '@/utils/formatters'

const props = defineProps({
    dashboard: { type: Object, required: true },
    deferred: { type: Object, default: null },
})

const page = usePage()
const { hijriDate } = useIslamicData()
const user = computed(() => page.props.auth?.user || {})
const ks = computed(() => props.dashboard.kepala_sekolah || {})
const att = computed(() => ks.value.attendance_today || {})
const habit = computed(() => ks.value.habit_today || {})
const approvals = computed(() => ks.value.pending_approvals || {})

const todayLabel = computed(() => {
    const m = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' })
    return hijriDate.value ? `${m} · ${hijriDate.value}` : m
})

const approvalItems = computed(() => [
    {
        id: 'disposisi',
        title: 'Disposisi surat menunggu',
        subtitle: 'Tindak lanjut persuratan',
        badge: String(approvals.value.disposisi || 0),
        tone: (approvals.value.disposisi || 0) > 0 ? 'warn' : 'good',
        href: route('tata-usaha.disposisi.index'),
    },
    {
        id: 'notulen',
        title: 'Notulen rapat belum disetujui',
        subtitle: 'Butuh tanda tangan pimpinan',
        badge: String(approvals.value.notulen || 0),
        tone: (approvals.value.notulen || 0) > 0 ? 'warn' : 'good',
        href: route('notulen.index'),
    },
    {
        id: 'kpi',
        title: 'Evaluasi Kinerja (KPI)',
        subtitle: `${ks.value.kpi_published || 0} dari ${ks.value.total_employees || 0} pegawai dinilai`,
        badge: 'KPI',
        tone: 'neutral',
        href: route('admin.performance.index'),
    },
])
</script>

<template>
    <DashboardShell
        :greeting="`Salam, ${user.name || 'Kepala Sekolah'}`"
        :dateLabel="todayLabel"
        contextLine="Ringkasan kehadiran, kepatuhan guru & persetujuan"
    >
        <template #actions>
            <Link
                :href="route('admin.performance.index')"
                class="inline-flex items-center px-3.5 py-2 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold transition-colors min-h-[44px] sm:min-h-0"
            >
                Monitoring KPI
            </Link>
            <Link
                :href="route('admin.teacher-journals.index')"
                class="inline-flex items-center px-3.5 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold transition-colors min-h-[44px] sm:min-h-0"
            >
                Rekap Jurnal
            </Link>
        </template>

        <template #kpis>
            <StatCard
                label="Kehadiran hari ini"
                :value="`${formatNumber((att.hadir || 0) + (att.terlambat || 0))} / ${formatNumber(ks.total_employees)}`"
                :subtext="`${att.terlambat || 0} terlambat · ${att.izin || 0} izin · ${att.belum || 0} belum`"
                :tone="(att.belum || 0) > 0 ? 'warn' : 'good'"
            />
            <StatCard
                label="Kepatuhan guru"
                :value="`${ks.teacher_admin_percent || 0}%`"
                :progress="ks.teacher_admin_percent || 0"
                :subtext="`Jurnal hari ini: ${formatNumber(ks.teacher_journal_today)} guru`"
                tone="primary"
                :href="route('admin.teacher-administrations.index')"
            />
            <StatCard
                label="Evaluasi KPI terbit"
                :value="`${formatNumber(ks.kpi_published)} / ${formatNumber(ks.total_employees)}`"
                subtext="Pegawai sudah dinilai"
                tone="good"
                :href="route('admin.performance.index')"
            />
            <StatCard
                label="Konsistensi habit lembaga"
                :value="`${habit.score_percent || 0}%`"
                :subtext="`${habit.filled_users || 0} pegawai mengisi hari ini`"
                tone="primary"
            />
        </template>

        <template #main>
            <div class="md:col-span-6 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-4 flex flex-col min-h-[200px]">
                <div class="flex items-center justify-between gap-2 mb-2 shrink-0">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Tren kehadiran 7 hari</h2>
                    <span class="text-xs text-slate-500 dark:text-slate-400">% hadir</span>
                </div>
                <div class="flex-1 min-h-0">
                    <Deferred data="deferred">
                        <template #fallback>
                            <div class="h-full min-h-[140px] rounded-xl bg-slate-100 dark:bg-slate-800 animate-pulse" />
                        </template>
                        <MiniTrendChart
                            v-if="deferred?.attendance_trend?.labels?.length"
                            type="bar"
                            :labels="deferred.attendance_trend.labels"
                            :values="deferred.attendance_trend.values"
                            datasetLabel="Kehadiran"
                            unit="%"
                            ariaLabel="Grafik tren kehadiran 7 hari"
                        />
                    </Deferred>
                </div>
            </div>

            <div class="md:col-span-6 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-4 flex flex-col min-h-[200px]">
                <div class="flex items-center justify-between gap-2 mb-2 shrink-0">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Konsistensi habit 14 hari</h2>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Skor (%)</span>
                </div>
                <div class="flex-1 min-h-0">
                    <Deferred data="deferred">
                        <template #fallback>
                            <div class="h-full min-h-[140px] rounded-xl bg-slate-100 dark:bg-slate-800 animate-pulse" />
                        </template>
                        <MiniTrendChart
                            v-if="deferred?.habit_trend?.labels?.length"
                            type="line"
                            :labels="deferred.habit_trend.labels"
                            :values="deferred.habit_trend.values"
                            datasetLabel="Rata-rata Habit"
                            unit="%"
                            ariaLabel="Grafik konsistensi habit lembaga"
                        />
                    </Deferred>
                </div>
            </div>
        </template>

        <template #sidebar>
            <ActionList
                :title="`Persetujuan & Monitoring (${approvals.total || 0})`"
                :items="approvalItems"
                class="shrink-0"
            />
            <PersonalMiniCard :personal="dashboard.personal" />
            <NotificationsCard class="flex-1 min-h-[120px]" />
        </template>
    </DashboardShell>
</template>
