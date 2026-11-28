<script setup>
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { Deferred } from '@inertiajs/vue3'
import DashboardShell from '@/Components/Dashboard/DashboardShell.vue'
import StatCard from '@/Components/Dashboard/StatCard.vue'
import ProgressRing from '@/Components/Dashboard/ProgressRing.vue'
import MiniTrendChart from '@/Components/Dashboard/MiniTrendChart.vue'
import ActionList from '@/Components/Dashboard/ActionList.vue'
import NotificationsCard from '@/Pages/Dashboard/Partials/NotificationsCard.vue'
import { useIslamicData } from '@/Composables/useIslamicData'
import { formatNumber, formatTime } from '@/utils/formatters'

const props = defineProps({
    dashboard: { type: Object, required: true },
    deferred: { type: Object, default: null },
})

const page = usePage()
const { hijriDate, nextPrayerName, countdownText } = useIslamicData()
const user = computed(() => page.props.auth?.user || {})
const p = computed(() => props.dashboard.personal || {})
const att = computed(() => p.value.attendance_today)
const habit = computed(() => p.value.habit_today || {})
const monthlyAtt = computed(() => p.value.monthly_attendance || { hadir: 0, terlambat: 0, izin: 0 })

const isChecking = ref(false)
const activeTrendTab = ref('habit_daily') // habit_daily | habit_monthly | attendance_monthly
const quickAttendance = () => {
    if (!att.value || isChecking.value) return
    router.visit(route('attendance.index'))
}

const todayLabel = computed(() => {
    const m = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' })
    return hijriDate.value ? `${m} · ${hijriDate.value}` : m
})

const contextLine = computed(() => {
    const wh = p.value.work_hours
    const prayer = nextPrayerName.value ? ` · ${nextPrayerName.value} ${countdownText.value}` : ''
    return `Jam kerja ${wh?.start || '07:00'}–${wh?.end || '15:00'}${prayer}`
})

const attTone = computed(() => {
    if (!att.value) return 'neutral'
    if (!att.value.checked_in) return 'warn'
    if (att.value.status === 'terlambat') return 'warn'
    return 'good'
})

const attValue = computed(() => {
    if (!att.value) return 'Nonaktif'
    if (!att.value.checked_in) return 'Belum masuk'
    if (att.value.checked_out) return `${formatTime(att.value.time_in)}–${formatTime(att.value.time_out)}`
    return `Masuk ${formatTime(att.value.time_in)}`
})

const teacherItems = computed(() => {
    const t = p.value.teacher
    if (!t) return []
    const list = []
    if (t.journal_today_filled !== null) {
        list.push({
            id: 'journal',
            title: 'Jurnal mengajar hari ini',
            subtitle: t.journal_today_filled ? 'Sudah diisi hari ini' : 'Belum diisi hari ini',
            badge: t.journal_today_filled ? 'Selesai' : 'Perlu diisi',
            tone: t.journal_today_filled ? 'good' : 'bad',
            href: route('teacher-journals.index'),
        })
    }
    list.push({
        id: 'admin-guru',
        title: 'Administrasi guru',
        subtitle: `${t.admin_verified} dari ${t.admin_total} dokumen terverifikasi`,
        badge: `${t.admin_verified}/${t.admin_total}`,
        tone: t.admin_total > 0 && t.admin_verified === t.admin_total ? 'good' : 'neutral',
        href: route('teacher-administrations.index'),
    })
    return list
})
</script>

<template>
    <DashboardShell
        :greeting="`Salam, ${user.name || 'Sahabat SDAM'}`"
        :dateLabel="todayLabel"
        :contextLine="contextLine"
    >
        <template #actions>
            <Link
                :href="route('habit.form')"
                class="inline-flex items-center px-3.5 py-2 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold transition-colors min-h-[44px] sm:min-h-0"
            >
                Isi Habit
            </Link>
            <button
                v-if="att"
                type="button"
                @click="quickAttendance"
                class="inline-flex items-center px-3.5 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold transition-colors min-h-[44px] sm:min-h-0 cursor-pointer"
            >
                {{ att.checked_in && !att.checked_out ? 'Check-Out' : (att.checked_in ? 'Presensiku' : 'Check-In') }}
            </button>
        </template>

        <template #kpis>
            <StatCard
                label="Presensi hari ini"
                :value="attValue"
                :subtext="att?.status === 'terlambat' ? 'Status: Terlambat' : (att?.checked_in ? 'Tercatat hadir' : 'Silakan lakukan presensi')"
                :tone="attTone"
                :href="att ? route('attendance.index') : null"
            />
            <StatCard
                label="Habit hari ini"
                :value="`${formatNumber(habit.score)} / ${formatNumber(habit.max)}`"
                :progress="habit.percent"
                :subtext="`Streak ${habit.streak || 0} hari · ${habit.vouchers || 0} voucher`"
                tone="primary"
                :href="route('habit.form')"
            />
            <StatCard
                label="Kehadiran bulan ini"
                :value="`${monthlyAtt.hadir + monthlyAtt.terlambat} hari`"
                :subtext="`${monthlyAtt.terlambat} terlambat · ${monthlyAtt.izin} izin/cuti`"
                :tone="monthlyAtt.terlambat > 2 ? 'warn' : 'good'"
                :href="route('attendance.index')"
            />
            <StatCard
                label="Rapor KPI terbaru"
                :value="p.latest_kpi ? p.latest_kpi.score.toFixed(1) : 'Belum ada'"
                :subtext="p.latest_kpi ? `${p.latest_kpi.predicate} · ${p.latest_kpi.period}` : 'Menunggu penilaian periode'"
                :tone="p.latest_kpi ? 'good' : 'neutral'"
                :href="route('performance.index')"
            />
        </template>

        <template #main>
            <div class="md:col-span-7 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-4 flex flex-col min-h-[200px]">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2 shrink-0">
                    <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800 p-1 rounded-xl">
                        <button
                            type="button"
                            @click="activeTrendTab = 'habit_daily'"
                            class="px-2.5 py-1 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                            :class="activeTrendTab === 'habit_daily' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-2xs' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700'"
                        >
                            Habit 14 Hari
                        </button>
                        <button
                            type="button"
                            @click="activeTrendTab = 'habit_monthly'"
                            class="px-2.5 py-1 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                            :class="activeTrendTab === 'habit_monthly' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-2xs' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700'"
                        >
                            Habit Bulanan
                        </button>
                        <button
                            type="button"
                            @click="activeTrendTab = 'attendance_monthly'"
                            class="px-2.5 py-1 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                            :class="activeTrendTab === 'attendance_monthly' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-2xs' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700'"
                        >
                            Rekap Presensi
                        </button>
                    </div>
                    <span v-if="activeTrendTab === 'attendance_monthly' && deferred?.attendance_monthly_trend?.summary" class="text-xs text-slate-500 dark:text-slate-400 tabular-nums">
                        {{ deferred.attendance_monthly_trend.summary.hadir }} hadir · {{ deferred.attendance_monthly_trend.summary.terlambat }} terlambat · {{ deferred.attendance_monthly_trend.summary.izin }} izin
                    </span>
                    <span v-else class="text-xs text-slate-500 dark:text-slate-400">Capaian (%)</span>
                </div>
                <div class="flex-1 min-h-0">
                    <Deferred data="deferred">
                        <template #fallback>
                            <div class="h-full min-h-[140px] rounded-xl bg-slate-100 dark:bg-slate-800 animate-pulse" />
                        </template>
                        <MiniTrendChart
                            v-if="activeTrendTab === 'habit_daily' && deferred?.habit_trend?.labels?.length"
                            type="line"
                            :labels="deferred.habit_trend.labels"
                            :values="deferred.habit_trend.values"
                            datasetLabel="Capaian Habit"
                            unit="%"
                            ariaLabel="Grafik tren habit 14 hari terakhir"
                        />
                        <MiniTrendChart
                            v-else-if="activeTrendTab === 'habit_monthly' && deferred?.habit_monthly_trend?.labels?.length"
                            type="bar"
                            :labels="deferred.habit_monthly_trend.labels"
                            :values="deferred.habit_monthly_trend.values"
                            datasetLabel="Capaian Habit Bulanan"
                            unit="%"
                            ariaLabel="Grafik tren habit per bulan"
                        />
                        <MiniTrendChart
                            v-else-if="activeTrendTab === 'attendance_monthly' && deferred?.attendance_monthly_trend?.labels?.length"
                            type="bar"
                            :labels="deferred.attendance_monthly_trend.labels"
                            :values="deferred.attendance_monthly_trend.values"
                            datasetLabel="Hari Hadir"
                            unit=" hari"
                            ariaLabel="Grafik rekap presensi per bulan"
                        />
                        <div v-else class="h-full flex items-center justify-center text-xs text-slate-500 dark:text-slate-400">
                            Belum ada data pada periode ini.
                        </div>
                    </Deferred>
                </div>
            </div>

            <div class="md:col-span-5 flex flex-col gap-3 min-h-0">
                <ActionList
                    v-if="p.teacher"
                    title="Tugas mengajar & perangkat"
                    :items="teacherItems"
                    class="flex-1"
                />
                <div
                    v-else
                    class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-4 flex items-center justify-between gap-4 flex-1"
                >
                    <div class="min-w-0">
                        <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Progres habit hari ini</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            {{ habit.score || 0 }} dari {{ habit.max || 0 }} poin tercapai
                        </p>
                        <Link :href="route('habit.form')" class="inline-block mt-2 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline">
                            Lengkapi sekarang →
                        </Link>
                    </div>
                    <ProgressRing :percent="habit.percent || 0" :size="68" :stroke="6" tone="primary" />
                </div>
            </div>
        </template>

        <template #sidebar>
            <NotificationsCard class="flex-1 min-h-[180px]" />
            <div v-if="dashboard.announcement" class="bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 rounded-2xl p-3.5 shrink-0">
                <div class="text-xs font-bold text-amber-900 dark:text-amber-200">Pengumuman</div>
                <p class="text-xs text-amber-800 dark:text-amber-300 mt-1 line-clamp-3">{{ dashboard.announcement }}</p>
            </div>
        </template>
    </DashboardShell>
</template>
