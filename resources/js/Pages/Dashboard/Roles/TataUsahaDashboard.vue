<script setup>
import { computed } from 'vue'
import { Link, Deferred, usePage } from '@inertiajs/vue3'
import DashboardShell from '@/Components/Dashboard/DashboardShell.vue'
import StatCard from '@/Components/Dashboard/StatCard.vue'
import ActionList from '@/Components/Dashboard/ActionList.vue'
import PersonalMiniCard from '@/Pages/Dashboard/Partials/PersonalMiniCard.vue'
import NotificationsCard from '@/Pages/Dashboard/Partials/NotificationsCard.vue'
import { useIslamicData } from '@/Composables/useIslamicData'
import { formatNumber, formatShortDate } from '@/utils/formatters'

const props = defineProps({
    dashboard: { type: Object, required: true },
    deferred: { type: Object, default: null },
})

const page = usePage()
const { hijriDate } = useIslamicData()
const user = computed(() => page.props.auth?.user || {})
const tu = computed(() => props.dashboard.tu || {})

const todayLabel = computed(() => {
    const m = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' })
    return hijriDate.value ? `${m} · ${hijriDate.value}` : m
})

const letterItems = computed(() =>
    (props.deferred?.recent_letters || []).map((item) => ({
        id: item.id,
        title: `${item.type} · ${item.nomor || '-'}`,
        subtitle: `${item.subject} (${item.party || '-'}) · ${formatShortDate(item.date)}`,
        badge: item.status,
        tone: item.status === 'selesai' || item.status === 'approved' ? 'good' : 'warn',
        href: item.type === 'Surat Masuk' ? route('tata-usaha.surat-masuk.index') : route('tata-usaha.surat-keluar.index'),
    }))
)

const notulenItems = computed(() =>
    (props.deferred?.pending_notulens || []).map((n) => ({
        id: n.id,
        title: n.title,
        subtitle: `${n.subtitle || 'Rapat'} · ${n.date || ''}`,
        badge: 'Approval',
        tone: 'warn',
        href: route('notulen.index'),
    }))
)
</script>

<template>
    <DashboardShell
        :greeting="`Salam, ${user.name || 'Tata Usaha'}`"
        :dateLabel="todayLabel"
        contextLine="Ringkasan persuratan & administrasi"
    >
        <template #actions>
            <Link
                :href="route('tata-usaha.surat-masuk.index')"
                class="inline-flex items-center px-3.5 py-2 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold transition-colors min-h-[44px] sm:min-h-0"
            >
                Surat Masuk
            </Link>
            <Link
                :href="route('tata-usaha.surat-keluar.builder')"
                class="inline-flex items-center px-3.5 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold transition-colors min-h-[44px] sm:min-h-0"
            >
                Buat Surat
            </Link>
        </template>

        <template #kpis>
            <StatCard
                label="Surat masuk perlu proses"
                :value="formatNumber(tu.surat_masuk_pending)"
                subtext="Menunggu tindak lanjut"
                :tone="tu.surat_masuk_pending > 0 ? 'warn' : 'good'"
                :href="route('tata-usaha.surat-masuk.index')"
            />
            <StatCard
                label="Surat keluar bulan ini"
                :value="formatNumber(tu.surat_keluar_bulan_ini)"
                subtext="Tercatat bulan berjalan"
                tone="primary"
                :href="route('tata-usaha.surat-keluar.index')"
            />
            <StatCard
                label="Disposisi menunggu"
                :value="formatNumber(tu.disposisi_pending)"
                subtext="Belum selesai"
                :tone="tu.disposisi_pending > 0 ? 'bad' : 'good'"
                :href="route('tata-usaha.disposisi.index')"
            />
            <StatCard
                label="Notulen butuh approval"
                :value="formatNumber(tu.notulen_pending)"
                subtext="Menunggu tanda tangan"
                :tone="tu.notulen_pending > 0 ? 'warn' : 'good'"
                :href="route('notulen.index')"
            />
        </template>

        <template #main>
            <div class="md:col-span-7 min-h-0 flex flex-col">
                <Deferred data="deferred">
                    <template #fallback>
                        <div class="flex-1 min-h-[180px] rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 p-4 animate-pulse" />
                    </template>
                    <ActionList
                        title="Aktivitas surat terbaru"
                        :items="letterItems"
                        emptyText="Belum ada aktivitas surat terbaru."
                        :viewAllHref="route('tata-usaha.surat-masuk.index')"
                        class="flex-1"
                    />
                </Deferred>
            </div>

            <div class="md:col-span-5 min-h-0 flex flex-col">
                <Deferred data="deferred">
                    <template #fallback>
                        <div class="flex-1 min-h-[180px] rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 p-4 animate-pulse" />
                    </template>
                    <ActionList
                        title="Notulen menunggu persetujuan"
                        :items="notulenItems"
                        emptyText="Semua notulen telah disetujui."
                        :viewAllHref="route('notulen.index')"
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
