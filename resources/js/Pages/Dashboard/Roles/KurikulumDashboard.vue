<script setup>
import { computed } from 'vue'
import { Link, Deferred, usePage } from '@inertiajs/vue3'
import DashboardShell from '@/Components/Dashboard/DashboardShell.vue'
import StatCard from '@/Components/Dashboard/StatCard.vue'
import ProgressRing from '@/Components/Dashboard/ProgressRing.vue'
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
const kur = computed(() => props.dashboard.kurikulum || {})

const todayLabel = computed(() => {
    const m = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' })
    return hijriDate.value ? `${m} · ${hijriDate.value}` : m
})

const missingTeachers = computed(() =>
    (props.deferred?.missing_journal_teachers || []).map((t) => ({
        id: t.id,
        title: t.name,
        subtitle: t.divisi || 'Guru',
        badge: 'Belum isi',
        tone: 'bad',
    }))
)
</script>

<template>
    <DashboardShell
        :greeting="`Salam, ${user.name || 'Kurikulum'}`"
        :dateLabel="todayLabel"
        :contextLine="`Tahun Ajaran ${kur.academic_year || '-'}`"
    >
        <template #actions>
            <Link
                :href="route('admin.teacher-administrations.index')"
                class="inline-flex items-center px-3.5 py-2 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold transition-colors min-h-[44px] sm:min-h-0"
            >
                Verifikasi Administrasi
            </Link>
        </template>

        <template #kpis>
            <StatCard
                label="Kepatuhan administrasi guru"
                :value="`${kur.admin_compliance_percent || 0}%`"
                :progress="kur.admin_compliance_percent || 0"
                :subtext="`${formatNumber(kur.admin_verified_docs)} / ${formatNumber(kur.admin_total_docs)} dokumen terverifikasi`"
                tone="primary"
                :href="route('admin.teacher-administrations.index')"
            />
            <StatCard
                label="Jurnal mengajar hari ini"
                :value="`${formatNumber(kur.journal_filled_today)} / ${formatNumber(kur.total_teachers)}`"
                :subtext="`${formatNumber(kur.journal_missing_today)} guru belum mengisi`"
                :tone="kur.journal_missing_today > 0 ? 'warn' : 'good'"
            />
            <StatCard
                label="Dokumen menunggu verifikasi"
                :value="formatNumber(kur.pending_verification)"
                subtext="Perlu diperiksa kurikulum"
                :tone="kur.pending_verification > 0 ? 'warn' : 'good'"
                :href="route('admin.teacher-administrations.index')"
            />
            <StatCard
                label="Total guru aktif"
                :value="formatNumber(kur.total_teachers)"
                subtext="Kategori pendidik"
                tone="neutral"
            />
        </template>

        <template #main>
            <div class="md:col-span-7 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-4 flex flex-col min-h-[200px]">
                <div class="flex items-center justify-between gap-2 mb-2 shrink-0">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Tren pengisian jurnal (14 hari)</h2>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Jumlah entri</span>
                </div>
                <div class="flex-1 min-h-0">
                    <Deferred data="deferred">
                        <template #fallback>
                            <div class="h-full min-h-[140px] rounded-xl bg-slate-100 dark:bg-slate-800 animate-pulse" />
                        </template>
                        <MiniTrendChart
                            v-if="deferred?.journal_trend?.labels?.length"
                            type="bar"
                            :labels="deferred.journal_trend.labels"
                            :values="deferred.journal_trend.values"
                            datasetLabel="Jurnal Terisi"
                            ariaLabel="Grafik tren pengisian jurnal guru"
                        />
                    </Deferred>
                </div>
            </div>

            <div class="md:col-span-5 min-h-0 flex flex-col gap-3">
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-4 flex items-center justify-between gap-4 shrink-0">
                    <div class="min-w-0">
                        <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Verifikasi perangkat</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            {{ kur.pending_verification || 0 }} dokumen menunggu review
                        </p>
                        <Link :href="route('admin.teacher-administrations.index')" class="inline-block mt-2 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline">
                            Periksa sekarang →
                        </Link>
                    </div>
                    <ProgressRing :percent="kur.admin_compliance_percent || 0" :size="64" :stroke="6" tone="primary" />
                </div>

                <Deferred data="deferred">
                    <template #fallback>
                        <div class="flex-1 min-h-[120px] rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 p-4 animate-pulse" />
                    </template>
                    <ActionList
                        title="Belum isi jurnal hari ini (Top 5)"
                        :items="missingTeachers"
                        emptyText="Semua guru sudah mengisi jurnal hari ini."
                        class="flex-1"
                    />
                </Deferred>
            </div>
        </template>

        <template #sidebar>
            <PersonalMiniCard :personal="dashboard.personal" />
            <NotificationsCard class="flex-1 min-h-[160px]" />
        </template>
    </DashboardShell>
</template>
