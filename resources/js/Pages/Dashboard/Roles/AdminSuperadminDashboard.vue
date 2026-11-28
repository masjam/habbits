<script setup>
import { computed } from 'vue'
import { Link, Deferred, usePage } from '@inertiajs/vue3'
import DashboardShell from '@/Components/Dashboard/DashboardShell.vue'
import StatCard from '@/Components/Dashboard/StatCard.vue'
import ActionList from '@/Components/Dashboard/ActionList.vue'
import MiniTrendChart from '@/Components/Dashboard/MiniTrendChart.vue'
import HealthItem from '@/Components/Dashboard/HealthItem.vue'
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
const isSuperadmin = computed(() => props.dashboard.roleKey === 'superadmin')
const adm = computed(() => props.dashboard.admin || {})
const att = computed(() => adm.value.attendance_today || {})
const habit = computed(() => adm.value.habit_today || {})
const sys = computed(() => props.dashboard.system || {})

const todayLabel = computed(() => {
    const m = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' })
    return hijriDate.value ? `${m} · ${hijriDate.value}` : m
})

const notCheckedInItems = computed(() =>
    (props.deferred?.not_checked_in || []).map((u) => ({
        id: u.id,
        title: u.name,
        subtitle: u.divisi || 'Pegawai',
        badge: 'Belum masuk',
        tone: 'warn',
        href: route('admin.attendance.index'),
    }))
)

const loginItems = computed(() =>
    (props.deferred?.recent_logins || []).map((l) => ({
        id: l.id,
        title: l.user_name,
        subtitle: `${l.ip || '-'} · ${l.location || 'Lokasi tidak tercatat'}`,
        badge: l.time,
        tone: 'neutral',
        href: route('admin.login-logs'),
    }))
)

const adminTasks = computed(() => [
    {
        id: 'permits',
        title: 'Izin / cuti menunggu persetujuan',
        subtitle: 'Tinjau pengajuan presensi',
        badge: String(att.value.pending_permits || 0),
        tone: (att.value.pending_permits || 0) > 0 ? 'warn' : 'good',
        href: route('admin.attendance.index'),
    },
    {
        id: 'arsip',
        title: 'Arsip pegawai belum diverifikasi',
        subtitle: 'Dokumen kepegawaian',
        badge: String(adm.value.arsip_pending || 0),
        tone: (adm.value.arsip_pending || 0) > 0 ? 'warn' : 'good',
        href: route('admin.employee-documents.index'),
    },
    {
        id: 'kpi',
        title: 'Evaluasi kinerja (KPI)',
        subtitle: `${adm.value.kpi_published || 0} dari ${adm.value.total_employees || 0} pegawai dinilai`,
        badge: 'Kelola',
        tone: 'neutral',
        href: route('admin.performance.index'),
    },
])
</script>

<template>
    <DashboardShell
        :greeting="`Salam, ${user.name || (isSuperadmin ? 'Superadmin' : 'Admin')}`"
        :dateLabel="todayLabel"
        :contextLine="isSuperadmin ? 'Kendali operasional & kesehatan sistem' : 'Operasional harian pegawai'"
    >
        <template #actions>
            <Link
                :href="route('admin.attendance.index')"
                class="inline-flex items-center px-3.5 py-2 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold transition-colors min-h-[44px] sm:min-h-0"
            >
                Rekap Presensi
            </Link>
            <Link
                :href="route('admin.laporan')"
                class="inline-flex items-center px-3.5 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold transition-colors min-h-[44px] sm:min-h-0"
            >
                Laporan Habit
            </Link>
            <Link
                v-if="isSuperadmin"
                :href="route('admin.system.monitor')"
                class="inline-flex items-center px-3.5 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold transition-colors min-h-[44px] sm:min-h-0"
            >
                System Monitor
            </Link>
        </template>

        <template #kpis>
            <StatCard
                label="Kehadiran hari ini"
                :value="`${formatNumber((att.hadir || 0) + (att.terlambat || 0))} / ${formatNumber(adm.total_employees)}`"
                :subtext="`${att.terlambat || 0} terlambat · ${att.izin || 0} izin · ${att.belum || 0} belum`"
                :tone="(att.belum || 0) > 0 ? 'warn' : 'good'"
                :href="route('admin.attendance.index')"
            />
            <StatCard
                label="Pengisian habit hari ini"
                :value="`${habit.percent_users || 0}%`"
                :progress="habit.percent_users || 0"
                :subtext="`${formatNumber(habit.filled_users)} dari ${formatNumber(adm.total_employees)} pegawai`"
                tone="primary"
                :href="isSuperadmin ? route('admin.laporan.unfilled') : route('admin.laporan')"
            />
            <StatCard
                v-if="!isSuperadmin"
                label="Izin menunggu persetujuan"
                :value="formatNumber(att.pending_permits)"
                subtext="Pengajuan presensi/cuti"
                :tone="(att.pending_permits || 0) > 0 ? 'warn' : 'good'"
                :href="route('admin.attendance.index')"
            />
            <StatCard
                v-else
                label="Antrean & failed jobs"
                :value="`${formatNumber(sys.queue_pending)} / ${formatNumber(sys.failed_jobs)}`"
                subtext="Pending / gagal di queue"
                :tone="(sys.failed_jobs || 0) > 0 ? 'bad' : 'good'"
                :href="route('admin.system.monitor')"
            />
            <StatCard
                label="Arsip & KPI"
                :value="`${formatNumber(adm.arsip_pending)} pending`"
                :subtext="`KPI terbit: ${formatNumber(adm.kpi_published)} pegawai`"
                :tone="(adm.arsip_pending || 0) > 0 ? 'warn' : 'good'"
                :href="route('admin.performance.index')"
            />
        </template>

        <template #main>
            <div class="md:col-span-7 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-4 flex flex-col min-h-[200px]">
                <div class="flex items-center justify-between gap-2 mb-2 shrink-0">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Tren habit lembaga (14 hari)</h2>
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
                            datasetLabel="Capaian Lembaga"
                            unit="%"
                            ariaLabel="Grafik tren habit lembaga 14 hari"
                        />
                    </Deferred>
                </div>
            </div>

            <div class="md:col-span-5 min-h-0 flex flex-col gap-3">
                <Deferred data="deferred">
                    <template #fallback>
                        <div class="flex-1 min-h-[140px] rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 p-4 animate-pulse" />
                    </template>
                    <ActionList
                        v-if="isSuperadmin"
                        title="Aktivitas login terbaru"
                        :items="loginItems"
                        emptyText="Belum ada log login."
                        :viewAllHref="route('admin.login-logs')"
                        class="flex-1"
                    />
                    <ActionList
                        v-else
                        title="Belum check-in hari ini (Top 5)"
                        :items="notCheckedInItems"
                        emptyText="Semua pegawai sudah presensi hari ini."
                        :viewAllHref="route('admin.attendance.index')"
                        class="flex-1"
                    />
                </Deferred>
            </div>
        </template>

        <template #sidebar>
            <div
                v-if="isSuperadmin"
                class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-3.5 space-y-2 shrink-0"
            >
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Kesehatan sistem</h2>
                    <Link :href="route('admin.system.monitor')" class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline">
                        Detail →
                    </Link>
                </div>
                <HealthItem label="Database" :value="sys.db_ok ? 'Terhubung' : 'Gangguan'" :status="sys.db_ok ? 'ok' : 'error'" />
                <HealthItem label="Reverb / WebSocket" :value="sys.reverb_configured ? 'Terkonfigurasi' : 'Nonaktif'" :status="sys.reverb_configured ? 'ok' : 'info'" />
                <HealthItem label="Maintenance" :value="sys.maintenance_mode ? 'Aktif' : 'Normal'" :status="sys.maintenance_mode ? 'warn' : 'ok'" />
                <HealthItem label="Fingerprint" :value="`${adm.fingerprint_registered || 0} terdaftar`" :status="adm.fingerprint_enabled ? 'ok' : 'info'" />
                <HealthItem label="Belum isi habit" :value="`${sys.unfilled_habit_today || 0} org`" :status="(sys.unfilled_habit_today || 0) > 0 ? 'warn' : 'ok'" />
            </div>

            <ActionList
                v-else
                title="Perlu tindakan"
                :items="adminTasks"
                class="shrink-0"
            />

            <PersonalMiniCard :personal="dashboard.personal" />
            <NotificationsCard class="flex-1 min-h-[120px]" />
        </template>
    </DashboardShell>
</template>
