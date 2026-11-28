<script setup>
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import {
    IconDashboard,
    IconQuranHadis,
    IconDzikir,
    IconHpt,
    IconNotification,
    IconChat,
    IconNotulen,
    IconPresensi,
    IconFormHabit,
    IconInputHabit,
    IconRekapQuran,
    IconJurnalKajian,
    IconHaid,
    IconJurnalGuru,
    IconAdministrasiGuru,
    IconArsip,
    IconKpi,
    IconSuratMasuk,
    IconSuratKeluar,
    IconDisposisi,
    IconPengaturanSurat,
    IconRekapPresensi,
    IconRekapHabit,
    IconSemuaLaporan,
    IconBelumIsiHabit,
    IconRekapJurnalGuru,
    IconRekapAdministrasiGuru,
    IconMasterHabit,
    IconManajemenUser,
    IconDaftarUser,
    IconVerifikasiArsip,
    IconEvaluasiKpi,
    IconJadwalPiket,
    IconJadwalKhusus,
    IconDaftarDivisi,
    IconPengaturanSistem,
    IconPanduan,
    IconFiturHr,
    IconLogAkses,
    IconPemantauanSistem,
    IconChevronDown,
    IconVoucher,
    IconKoperasi
} from '@/Components/Icons'

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: true
    },
    user: {
        type: Object,
        default: () => ({})
    },
    roles: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['close', 'expand', 'toggle'])

const closeSidebar = () => {
    if (typeof window !== 'undefined' && window.innerWidth < 768) {
        emit('close')
    }
}

// ─── Role & Gender Helpers ──────────────────────────────────────────────────
const hasRole = (role) => props.roles.includes(role)
const hasAnyRole = (roleArray) => roleArray.some(r => props.roles.includes(r))
const isFemale = computed(() => props.user?.gender === 'P')

const roleLabel = computed(() => {
    if (hasRole('superadmin')) return 'Super Admin'
    if (hasRole('admin'))      return 'Admin'
    if (hasRole('kepala_sekolah')) return 'Kepala Sekolah'
    if (hasRole('kurikulum'))  return 'Kurikulum'
    if (hasRole('tata_usaha')) return 'Tata Usaha'
    if (hasRole('koperasi'))   return 'Petugas Koperasi'
    return 'Pegawai'
})

const userInitial = computed(() =>
    props.user?.name?.charAt(0)?.toUpperCase() ?? '?'
)

const userAvatarUrl = computed(() => {
    if (props.user?.avatar) {
        return props.user.avatar.startsWith('http') ? props.user.avatar : `/storage/${props.user.avatar}`
    }
    return null
})

// ─── Active Route Helper (computed cache per-URL via Ziggy) ──────────────────
const page = usePage()
const routeActiveCache = computed(() => {
    void page.url
    return new Map()
})

const isActive = (routeName) => {
    const cache = routeActiveCache.value
    if (cache.has(routeName)) {
        return cache.get(routeName)
    }
    let res = false
    try {
        if (typeof route !== 'undefined') {
            res = route().current(routeName)
        }
    } catch {
        res = false
    }
    cache.set(routeName, res)
    return res
}

const isAnyActive = (routePatterns = []) => routePatterns.some((r) => isActive(r))

// ─── Feature Flags (dari global_settings yang di-share via Inertia) ─────────
const featureEnabled = (key) => {
    const val = page.props.global_settings?.[key]
    if (['feature_presensi', 'feature_arsip', 'feature_kpi', 'feature_habit', 'feature_administrasi_guru'].includes(key)) {
        return val === undefined || val === '1' || val === 'true' || val === true
    }
    return val === '1' || val === 'true' || val === true
}

const featureJurnalEnabled = computed(() => {
    const val = page.props.global_settings?.feature_jurnal
    return val !== 'false' && val !== '0'
})

const isVisible = (cond) => (typeof cond === 'function' ? cond() : cond !== false)

// ─── Direct Menu Items (tanpa grup, untuk Pegawai & semua role) ─────────────
const directMenus = computed(() => {
    const items = [
        {
            key: 'dashboard',
            label: 'Dashboard',
            icon: IconDashboard,
            route: 'dashboard',
            activeRoutes: ['dashboard'],
        },
        {
            key: 'kepala-sekolah-dashboard',
            label: 'Dashboard Kepala Sekolah',
            icon: IconDashboard,
            route: 'kepala-sekolah.dashboard',
            activeRoutes: ['kepala-sekolah.dashboard'],
            roles: ['kepala_sekolah'],
        },
        {
            key: 'admin-dashboard',
            label: 'Dashboard Admin',
            icon: IconDashboard,
            route: 'admin.dashboard',
            activeRoutes: ['admin.dashboard'],
            roles: ['admin', 'superadmin'],
            visible: () => props.roles.length > 1,
        },
        {
            key: 'presensiku',
            label: 'Presensiku',
            icon: IconPresensi,
            route: 'attendance.index',
            activeRoutes: ['attendance.index'],
            roles: ['user'],
            visible: () => featureEnabled('feature_presensi'),
        },
        {
            key: 'input-habit',
            label: 'Input Habit Harian',
            icon: IconInputHabit,
            route: 'habit.form',
            activeRoutes: ['habit.form'],
            roles: ['user'],
            visible: () => featureEnabled('feature_habit'),
        },
    ]

    return items.filter((item) => {
        if (item.roles && !hasAnyRole(item.roles)) return false
        return isVisible(item.visible)
    })
})

// ─── Menu Groups (Struktur Data Berbasis Fungsi & Middleware Rute) ───────────
const rawMenuGroups = computed(() => [
    {
        key: 'ibadah',
        label: 'Ibadah & Keislaman',
        icon: IconQuranHadis,
        items: [
            {
                key: 'quran-hadis',
                label: "Al-Qur'an & Hadis",
                icon: IconQuranHadis,
                route: 'quran.hadis',
                activeRoutes: ['quran.hadis'],
            },
            {
                key: 'dzikir',
                label: 'Dzikir Pagi & Petang',
                icon: IconDzikir,
                route: 'dzikir.index',
                activeRoutes: ['dzikir.index'],
            },
            {
                key: 'hpt',
                label: 'HPT Online',
                icon: IconHpt,
                route: 'hpt.index',
                activeRoutes: ['hpt.*'],
            },
            {
                key: 'qiblat',
                label: 'Arah Kiblat',
                icon: IconDzikir, // TODO: ganti dengan IconQibla jika sudah tersedia di @/Components/Icons
                route: 'qiblat.index',
                activeRoutes: ['qiblat.index'],
            },
        ],
    },
    {
        key: 'capaian-catatan',
        label: 'Capaian & Catatan',
        icon: IconFormHabit,
        roles: ['user'],
        items: [
            {
                key: 'rekap-quran',
                label: 'Rekap Al-Quran',
                icon: IconRekapQuran,
                route: 'quran.recap',
                activeRoutes: ['quran.recap'],
                roles: ['user'],
                visible: () => featureEnabled('feature_habit'),
            },
            {
                key: 'kajian',
                label: 'Jurnal Kajian & Hadits',
                icon: IconJurnalKajian,
                route: 'kajian.index',
                activeRoutes: ['kajian.index'],
                roles: ['user'],
                visible: () => featureEnabled('feature_habit'),
            },
            {
                key: 'voucher-habit',
                label: 'Voucher Habit',
                icon: IconVoucher,
                route: 'habit.vouchers',
                activeRoutes: ['habit.vouchers*'],
                roles: ['user'],
                visible: () => featureEnabled('feature_habit'),
            },
            {
                key: 'haid',
                label: 'Catatan Haid',
                icon: IconHaid,
                route: 'haid.index',
                activeRoutes: ['haid.index'],
                roles: ['user'],
                visible: () => isFemale.value,
                badge: 'Aktif',
            },
        ],
    },
    {
        key: 'pekerjaan-kepegawaian',
        label: 'Pekerjaan & Kepegawaian',
        icon: IconAdministrasiGuru,
        roles: ['user'],
        visible: () => !hasAnyRole(['admin', 'superadmin']),
        items: [
            {
                key: 'jurnal-guru',
                label: 'Jurnal Harian Guru',
                icon: IconJurnalGuru,
                route: 'teacher-journals.index',
                activeRoutes: ['teacher-journals.index'],
                roles: ['user'],
                visible: () => featureJurnalEnabled.value,
            },
            {
                key: 'administrasi-guru',
                label: 'Administrasi Guru',
                icon: IconAdministrasiGuru,
                route: 'teacher-administrations.index',
                activeRoutes: ['teacher-administrations.*'],
                roles: ['user'],
                visible: () => featureEnabled('feature_administrasi_guru'),
            },
            {
                key: 'arsip-kepegawaian',
                label: 'Arsip Kepegawaian',
                icon: IconArsip,
                route: 'employee-documents.index',
                activeRoutes: ['employee-documents.*'],
                roles: ['user'],
                visible: () => featureEnabled('feature_arsip'),
            },
            {
                key: 'rapor-kpi',
                label: 'Rapor Kinerja (KPI)',
                icon: IconKpi,
                route: 'performance.index',
                activeRoutes: ['performance.*'],
                roles: ['user'],
                visible: () => featureEnabled('feature_kpi'),
            },
        ],
    },
    {
        key: 'komunikasi',
        label: 'Komunikasi & Rapat',
        icon: IconChat,
        items: [
            {
                key: 'notifikasi',
                label: 'Pesan & Notifikasi',
                icon: IconNotification,
                route: 'notifications.index',
                activeRoutes: ['notifications.index'],
                isUnreadBadge: true,
            },
            {
                key: 'chat',
                label: 'Chat Pegawai',
                icon: IconChat,
                route: 'chat.index',
                activeRoutes: ['chat.*'],
            },
            {
                key: 'notulen',
                label: 'Notulen Rapat',
                icon: IconNotulen,
                route: 'notulen.index',
                activeRoutes: ['notulen.*'],
            },
        ],
    },
    {
        key: 'tata-usaha',
        label: 'Tata Usaha',
        icon: IconSuratMasuk,
        roles: ['tata_usaha', 'kepala_sekolah', 'superadmin'],
        dashboardRoute: hasAnyRole(['tata_usaha', 'superadmin']) ? 'tata-usaha.dashboard' : null,
        items: [
            {
                key: 'tata-usaha-dashboard',
                label: 'Dashboard Tata Usaha',
                icon: IconDashboard,
                route: 'tata-usaha.dashboard',
                activeRoutes: ['tata-usaha.dashboard'],
                roles: ['tata_usaha', 'superadmin'],
            },
            {
                key: 'surat-masuk',
                label: 'Surat Masuk',
                icon: IconSuratMasuk,
                route: 'tata-usaha.surat-masuk.index',
                activeRoutes: ['tata-usaha.surat-masuk.*'],
                roles: ['tata_usaha', 'kepala_sekolah', 'superadmin'],
            },
            {
                key: 'surat-keluar',
                label: 'Surat Keluar',
                icon: IconSuratKeluar,
                route: 'tata-usaha.surat-keluar.index',
                activeRoutes: ['tata-usaha.surat-keluar.*'],
                roles: ['tata_usaha', 'kepala_sekolah', 'superadmin'],
            },
            {
                key: 'disposisi',
                label: 'Disposisi',
                icon: IconDisposisi,
                route: 'tata-usaha.disposisi.index',
                activeRoutes: ['tata-usaha.disposisi.*'],
                roles: ['tata_usaha', 'kepala_sekolah', 'superadmin'],
            },
            {
                key: 'pengaturan-surat',
                label: 'Pengaturan Surat',
                icon: IconPengaturanSurat,
                route: 'tata-usaha.settings.index',
                activeRoutes: ['tata-usaha.settings.*'],
                roles: ['tata_usaha', 'kepala_sekolah', 'superadmin'],
            },
        ],
    },
    {
        key: 'koperasi',
        label: 'Koperasi Rabbani',
        icon: IconKoperasi,
        roles: ['koperasi', 'superadmin'],
        dashboardRoute: 'koperasi.dashboard',
        items: [
            {
                key: 'koperasi-dashboard',
                label: 'Dashboard Koperasi',
                icon: IconDashboard,
                route: 'koperasi.dashboard',
                activeRoutes: ['koperasi.dashboard*'],
                roles: ['koperasi', 'superadmin'],
            },
            {
                key: 'koperasi-pos',
                label: 'Kasir POS Voucher',
                icon: IconKoperasi,
                route: 'koperasi.pos',
                activeRoutes: ['koperasi.pos*'],
                roles: ['koperasi', 'superadmin'],
            },
            {
                key: 'koperasi-products',
                label: 'Katalog Barang',
                icon: IconDaftarDivisi,
                route: 'koperasi.products',
                activeRoutes: ['koperasi.products*'],
                roles: ['koperasi', 'superadmin'],
            },
            {
                key: 'koperasi-discounts',
                label: 'Diskon & Promo',
                icon: IconVoucher,
                route: 'koperasi.discounts',
                activeRoutes: ['koperasi.discounts*'],
                roles: ['koperasi', 'superadmin'],
            },
            {
                key: 'koperasi-stock-opname',
                label: 'Stok Opname',
                icon: IconVerifikasiArsip,
                route: 'koperasi.stock-opname',
                activeRoutes: ['koperasi.stock-opname*'],
                roles: ['koperasi', 'superadmin'],
            },
            {
                key: 'koperasi-history',
                label: 'Riwayat Transaksi',
                icon: IconArsip,
                route: 'koperasi.history',
                activeRoutes: ['koperasi.history*'],
                roles: ['koperasi', 'superadmin'],
            },
            {
                key: 'koperasi-reports',
                label: 'Laporan Koperasi',
                icon: IconArsip,
                route: 'koperasi.reports',
                activeRoutes: ['koperasi.reports*'],
                roles: ['koperasi', 'superadmin'],
            },
            {
                key: 'koperasi-settings',
                label: 'Identitas Koperasi',
                icon: IconKoperasi,
                route: 'koperasi.settings',
                activeRoutes: ['koperasi.settings*'],
                roles: ['koperasi', 'superadmin'],
            },
            {
                key: 'koperasi-members',
                label: 'Member Pegawai',
                icon: IconDaftarDivisi,
                route: 'koperasi.members',
                activeRoutes: ['koperasi.members*'],
                roles: ['koperasi', 'superadmin'],
            },
            {
                key: 'koperasi-bon',
                label: 'BON / Piutang',
                icon: IconArsip,
                route: 'koperasi.bon',
                activeRoutes: ['koperasi.bon*'],
                roles: ['koperasi', 'superadmin'],
            },
        ],
    },
    {
        key: 'monitoring-guru',
        label: hasRole('kurikulum') && !hasAnyRole(['kepala_sekolah', 'admin', 'superadmin'])
            ? 'Kurikulum'
            : 'Monitoring Guru',
        icon: IconRekapAdministrasiGuru,
        roles: ['admin', 'superadmin', 'kepala_sekolah', 'kurikulum'],
        dashboardRoute: hasAnyRole(['kurikulum', 'superadmin']) ? 'kurikulum.dashboard' : null,
        items: [
            {
                key: 'kurikulum-dashboard',
                label: 'Dashboard Kurikulum',
                icon: IconDashboard,
                route: 'kurikulum.dashboard',
                activeRoutes: ['kurikulum.dashboard'],
                roles: ['kurikulum', 'superadmin'],
            },
            {
                key: 'rekap-administrasi-guru',
                label: 'Rekap Administrasi Guru',
                icon: IconRekapAdministrasiGuru,
                route: 'admin.teacher-administrations.index',
                activeRoutes: ['admin.teacher-administrations.*'],
                roles: ['admin', 'superadmin', 'kepala_sekolah', 'kurikulum'],
                visible: () => featureEnabled('feature_administrasi_guru'),
            },
            {
                key: 'rekap-jurnal-guru',
                label: 'Rekap Jurnal Guru',
                icon: IconRekapJurnalGuru,
                route: hasAnyRole(['admin', 'superadmin', 'kepala_sekolah']) ? 'admin.teacher-journals.index' : 'teacher-journals.index',
                activeRoutes: hasAnyRole(['admin', 'superadmin', 'kepala_sekolah']) ? ['admin.teacher-journals.index'] : ['teacher-journals.index'],
                roles: ['admin', 'superadmin', 'kepala_sekolah'],
            },
            {
                key: 'evaluasi-kpi',
                label: 'Evaluasi Kinerja (KPI)',
                icon: IconEvaluasiKpi,
                route: 'admin.performance.index',
                activeRoutes: ['admin.performance.*'],
                roles: ['admin', 'superadmin', 'kepala_sekolah'],
                visible: () => featureEnabled('feature_kpi'),
            },
        ],
    },
    {
        key: 'presensi-jadwal',
        label: 'Presensi & Jadwal',
        icon: IconRekapPresensi,
        roles: ['admin', 'superadmin'],
        visible: () => featureEnabled('feature_presensi'),
        items: [
            {
                key: 'rekap-presensi',
                label: 'Rekap Presensi',
                icon: IconRekapPresensi,
                route: 'admin.attendance.index',
                activeRoutes: ['admin.attendance.index'],
                roles: ['admin', 'superadmin'],
            },
            {
                key: 'jadwal-piket',
                label: 'Jam Kerja Harian & Piket',
                icon: IconJadwalPiket,
                route: 'admin.duty-schedules.index',
                activeRoutes: ['admin.duty-schedules.index'],
                roles: ['admin', 'superadmin'],
            },
            {
                key: 'jadwal-khusus',
                label: 'Jadwal Khusus Tertentu',
                icon: IconJadwalKhusus,
                route: 'admin.special-schedules.index',
                activeRoutes: ['admin.special-schedules.index'],
                roles: ['admin', 'superadmin'],
            },
        ],
    },
    {
        key: 'habit-voucher',
        label: 'Habit & Voucher',
        icon: IconRekapHabit,
        roles: ['admin', 'superadmin'],
        visible: () => featureEnabled('feature_habit'),
        items: [
            {
                key: 'semua-laporan',
                label: 'Semua Laporan Habit',
                icon: IconSemuaLaporan,
                route: 'admin.laporan',
                activeRoutes: ['admin.laporan'],
                roles: ['admin', 'superadmin'],
            },
            {
                key: 'belum-isi-habit',
                label: 'Cek Belum Isi Habit',
                icon: IconBelumIsiHabit,
                route: 'admin.laporan.unfilled',
                activeRoutes: ['admin.laporan.unfilled'],
                roles: ['superadmin'],
            },
            {
                key: 'voucher-koperasi',
                label: 'Voucher Koperasi',
                icon: IconVoucher,
                route: 'admin.vouchers.index',
                activeRoutes: ['admin.vouchers.*'],
                roles: ['admin', 'superadmin'],
            },
            {
                key: 'master-habit',
                label: 'Master Data Habit',
                icon: IconMasterHabit,
                route: 'admin.habits.index',
                activeRoutes: ['admin.habits.index'],
                roles: ['admin', 'superadmin'],
            },
        ],
    },
    {
        key: 'kepegawaian',
        label: 'Kepegawaian',
        icon: IconManajemenUser,
        roles: ['admin', 'superadmin'],
        items: [
            {
                key: 'daftar-user',
                label: 'Daftar User',
                icon: IconDaftarUser,
                route: 'admin.users.index',
                activeRoutes: ['admin.users.index'],
                roles: ['admin', 'superadmin'],
            },
            {
                key: 'daftar-divisi',
                label: 'Daftar Divisi',
                icon: IconDaftarDivisi,
                route: 'admin.divisions.index',
                activeRoutes: ['admin.divisions.index'],
                roles: ['admin', 'superadmin'],
                visible: () => featureEnabled('feature_divisi'),
            },
            {
                key: 'verifikasi-arsip',
                label: 'Verifikasi Arsip Pegawai',
                icon: IconVerifikasiArsip,
                route: 'admin.employee-documents.index',
                activeRoutes: ['admin.employee-documents.index'],
                roles: ['admin', 'superadmin'],
                visible: () => featureEnabled('feature_arsip'),
            },
        ],
    },
    {
        key: 'pengaturan-sistem',
        label: 'Pengaturan & Sistem',
        icon: IconPengaturanSistem,
        roles: ['admin', 'superadmin'],
        items: [
            {
                key: 'settings',
                label: 'Pengaturan Sistem',
                icon: IconPengaturanSistem,
                route: 'admin.settings',
                activeRoutes: ['admin.settings'],
                roles: ['admin', 'superadmin'],
            },
            {
                key: 'settings-hr',
                label: 'Fitur HR & Lanjutan',
                icon: IconFiturHr,
                route: 'admin.settings.hr',
                activeRoutes: ['admin.settings.hr'],
                roles: ['superadmin'],
            },
            {
                key: 'login-logs',
                label: 'Log Akses Login',
                icon: IconLogAkses,
                route: 'admin.login-logs',
                activeRoutes: ['admin.login-logs'],
                roles: ['superadmin'],
            },
            {
                key: 'system-monitor',
                label: 'Pemantauan Sistem',
                icon: IconPemantauanSistem,
                route: 'admin.system.monitor',
                activeRoutes: ['admin.system.monitor'],
                roles: ['superadmin'],
            },
        ],
    },
])

const menuGroups = computed(() => {
    return rawMenuGroups.value
        .filter((group) => {
            if (group.roles && !hasAnyRole(group.roles)) return false
            return isVisible(group.visible)
        })
        .map((group) => {
            const visibleItems = group.items.filter((item) => {
                if (item.roles && !hasAnyRole(item.roles)) return false
                return isVisible(item.visible)
            })
            return {
                ...group,
                items: visibleItems,
                activeRoutes: visibleItems.flatMap((i) => i.activeRoutes),
                hasUnreadDot: visibleItems.some((i) => i.isUnreadBadge),
            }
        })
        .filter((group) => group.items.length > 0)
})

// ─── Collapsible Group States (Dihitung Otomatis dari Items Aktif) ───────────
const openGroups = ref(
    Object.fromEntries(
        menuGroups.value.map((g) => [g.key, isAnyActive(g.activeRoutes)])
    )
)

const toggleGroup = (group) => {
    const navigateIfNeeded = () => {
        if (group.dashboardRoute && !isAnyActive(group.activeRoutes)) {
            closeSidebar()
            router.visit(route(group.dashboardRoute))
        }
    }

    if (!props.isOpen) {
        emit('expand')
        emit('toggle')
        openGroups.value[group.key] = true
        navigateIfNeeded()
        return
    }
    const willOpen = !openGroups.value[group.key]
    openGroups.value[group.key] = willOpen
    if (willOpen) {
        navigateIfNeeded()
    }
}

// ─── Dynamic Classes (Responsive Desktop Rail vs Expanded Drawer) ───────────
const navLinkClass = (routePatterns) => {
    const patterns = Array.isArray(routePatterns) ? routePatterns : [routePatterns]
    const active = isAnyActive(patterns)
    return [
        'group relative flex items-center rounded-2xl text-sm font-medium transition-all duration-200 ease-out select-none cursor-pointer',
        props.isOpen
            ? 'w-full gap-3.5 px-4 py-2.5'
            : 'w-11 h-11 p-2.5 mx-auto justify-center',
        active
            ? (props.isOpen
                ? 'bg-gradient-to-r from-primary-500/20 via-primary-500/10 to-transparent text-primary-950 dark:text-primary-100 font-bold shadow-xs translate-x-1 border-l-4 border-primary-500'
                : 'bg-primary-500/20 text-primary-700 dark:text-primary-300 font-bold shadow-xs ring-2 ring-primary-500/40 scale-105')
            : (props.isOpen
                ? 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/50 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100 hover:translate-x-1.5'
                : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/50 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100 hover:scale-110')
    ]
}

const subNavLinkClass = (routePatterns) => {
    const patterns = Array.isArray(routePatterns) ? routePatterns : [routePatterns]
    const active = isAnyActive(patterns)
    return [
        'group flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-medium transition-all duration-200 ease-out w-full select-none cursor-pointer',
        active
            ? 'bg-primary-100/80 dark:bg-primary-900/50 text-primary-900 dark:text-primary-100 font-bold shadow-xs translate-x-1'
            : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/50 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100 hover:translate-x-1'
    ]
}

const accordionButtonClass = (group) => {
    const isExpanded = !!openGroups.value[group.key]
    const active = isAnyActive(group.activeRoutes)
    return [
        'group flex items-center rounded-2xl text-sm font-medium transition-all duration-200 select-none cursor-pointer',
        props.isOpen
            ? 'w-full gap-3.5 px-4 py-2.5 rounded-full'
            : 'w-11 h-11 p-2.5 mx-auto justify-center',
        active || isExpanded
            ? 'bg-slate-200/60 dark:bg-slate-800/70 text-slate-900 dark:text-slate-100 font-semibold'
            : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/50 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100' + (!props.isOpen ? ' hover:scale-110' : '')
    ]
}

const iconClass = (routePatterns) => {
    const patterns = Array.isArray(routePatterns) ? routePatterns : [routePatterns]
    return [
        'w-5 h-5 flex-shrink-0 transition-colors',
        isAnyActive(patterns)
            ? 'text-primary-600 dark:text-primary-400'
            : 'text-slate-400 dark:text-slate-500 group-hover:text-primary-600 dark:group-hover:text-primary-400'
    ]
}

const subIconClass = (routePatterns) => {
    const patterns = Array.isArray(routePatterns) ? routePatterns : [routePatterns]
    return [
        'w-4 h-4 flex-shrink-0 transition-colors',
        isAnyActive(patterns)
            ? 'text-primary-600 dark:text-primary-400'
            : 'text-slate-400 dark:text-slate-500 group-hover:text-primary-600 dark:group-hover:text-primary-400'
    ]
}
</script>

<template>
    <aside
        :class="[
            'fixed inset-y-0 left-0 z-40 flex flex-col',
            'bg-white/80 dark:bg-slate-900/85 backdrop-blur-2xl shadow-2xl border-r border-white/60 dark:border-white/10',
            'transition-all duration-300 ease-in-out select-none',
            // Mobile: drawer behavior (< 768px)
            isOpen ? 'translate-x-0 w-72' : '-translate-x-full w-72',
            // Desktop: always visible (>= 768px), mini rail or expanded
            'md:translate-x-0 md:relative md:z-auto md:flex-shrink-0',
            isOpen ? 'md:w-72' : 'md:w-20'
        ]"
    >
        <!-- ── Sidebar Header / Brand ─────────────────────────── -->
        <div
            :class="[
                'flex items-center py-5 border-b border-white/40 dark:border-white/10 flex-shrink-0 group cursor-default transition-all duration-300',
                isOpen ? 'px-6 gap-3.5 justify-start' : 'px-2 justify-center'
            ]"
            :title="!isOpen ? 'SAHABAT SDAM' : undefined"
        >
            <div class="w-10 h-10 rounded-2xl bg-primary-100/70 dark:bg-primary-900/40 border border-primary-200/60 dark:border-primary-800/50 flex items-center justify-center p-1.5 shadow-xs flex-shrink-0 group-hover:scale-105 group-hover:rotate-3 transition-transform duration-300 ease-out">
                <img src="/logo.png" alt="Logo SDAM" class="w-full h-full object-contain" />
            </div>
            <div v-show="isOpen" class="leading-tight min-w-0 transition-opacity duration-200">
                <p class="text-base font-bold text-primary-600 dark:text-primary-400 tracking-tight group-hover:text-primary-700 dark:group-hover:text-primary-300 transition-colors">SAHABAT SDAM</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">Presensi &amp; Kebiasaan</p>
            </div>
        </div>

        <!-- ── Navigation Links ──────────────────────────────── -->
        <nav class="flex-1 overflow-y-auto overflow-x-hidden px-2.5 py-4 space-y-1">

            <!-- Menu langsung (tanpa grup) -->
            <Link
                v-for="item in directMenus"
                :key="item.key"
                :href="route(item.route)"
                :class="navLinkClass(item.activeRoutes)"
                :title="!isOpen ? item.label : undefined"
                @click="closeSidebar"
            >
                <component :is="item.icon" :class="iconClass(item.activeRoutes)" />
                <span v-show="isOpen" class="truncate">{{ item.label }}</span>
            </Link>

            <!-- Kelompok menu (collapsible accordion) -->
            <div v-for="group in menuGroups" :key="group.key" class="space-y-1">
                <button
                    type="button"
                    @click="toggleGroup(group)"
                    :class="accordionButtonClass(group)"
                    :title="!isOpen ? group.label : undefined"
                >
                    <div class="relative flex items-center justify-center">
                        <component
                            :is="group.icon"
                            :class="[
                                'w-5 h-5 flex-shrink-0 transition-colors',
                                openGroups[group.key] || isAnyActive(group.activeRoutes)
                                    ? 'text-primary-600 dark:text-primary-400'
                                    : 'text-slate-400 dark:text-slate-500'
                            ]"
                        />
                        <span
                            v-if="!isOpen && group.hasUnreadDot && $page.props.notifications?.unread_count > 0"
                            class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-rose-500 rounded-full ring-2 ring-white dark:ring-slate-900"
                        />
                    </div>
                    <span v-show="isOpen" class="truncate text-left flex-1">{{ group.label }}</span>
                    <span
                        v-if="isOpen && !openGroups[group.key] && group.hasUnreadDot && $page.props.notifications?.unread_count > 0"
                        class="ml-auto flex items-center justify-center px-2 py-0.5 text-xs font-bold bg-rose-500 text-white rounded-full flex-shrink-0"
                    >
                        {{ $page.props.notifications.unread_count }}
                    </span>
                    <IconChevronDown
                        v-show="isOpen"
                        class="w-4 h-4 ml-auto transition-transform duration-200 text-slate-400 flex-shrink-0"
                        :class="{ 'rotate-180': openGroups[group.key] }"
                    />
                </button>

                <Transition
                    enter-active-class="transition-all duration-200 ease-out overflow-hidden"
                    enter-from-class="opacity-0 -translate-y-2 max-h-0"
                    enter-to-class="opacity-100 translate-y-0 max-h-96"
                    leave-active-class="transition-all duration-150 ease-in overflow-hidden"
                    leave-from-class="opacity-100 translate-y-0 max-h-96"
                    leave-to-class="opacity-0 -translate-y-2 max-h-0"
                >
                    <div v-show="openGroups[group.key] && isOpen" class="ml-4 pl-3 border-l-2 border-primary-500/30 space-y-1 pt-1">
                        <Link
                            v-for="item in group.items"
                            :key="item.key"
                            :href="route(item.route)"
                            :class="subNavLinkClass(item.activeRoutes)"
                            @click="closeSidebar"
                        >
                            <component :is="item.icon" :class="subIconClass(item.activeRoutes)" />
                            <span class="flex-1 truncate">{{ item.label }}</span>
                            <span
                                v-if="item.isUnreadBadge && $page.props.notifications?.unread_count > 0"
                                class="ml-auto flex items-center justify-center px-2 py-0.5 text-xs font-bold bg-rose-500 text-white rounded-full flex-shrink-0"
                            >
                                {{ $page.props.notifications.unread_count }}
                            </span>
                            <span
                                v-else-if="item.badge"
                                class="ml-auto text-xs font-medium px-2 py-0.5 rounded-full bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300 flex-shrink-0"
                            >
                                {{ item.badge }}
                            </span>
                        </Link>
                    </div>
                </Transition>
            </div>
        </nav>

        <!-- ── Sidebar Footer (Buku Panduan + User Chip Card) ───── -->
        <div
            :class="[
                'flex-shrink-0 py-3.5 border-t border-white/40 dark:border-white/10 space-y-2.5 transition-all duration-300',
                isOpen ? 'px-3.5' : 'px-2 flex flex-col items-center'
            ]"
        >
            <!-- Buku Panduan (tampil untuk semua role) -->
            <Link
                :href="route('panduan')"
                :class="navLinkClass('panduan')"
                :title="!isOpen ? 'Buku Panduan' : undefined"
                @click="closeSidebar"
            >
                <IconPanduan :class="iconClass('panduan')" />
                <span v-show="isOpen" class="truncate">Buku Panduan</span>
            </Link>

            <!-- User Chip Card -->
            <div
                :class="[
                    'flex items-center rounded-2xl bg-white/60 dark:bg-slate-800/60 backdrop-blur-md border border-white/60 dark:border-white/10 shadow-sm hover:shadow-md hover:bg-white/80 dark:hover:bg-slate-800/80 hover:-translate-y-0.5 transition-all duration-200 group cursor-pointer',
                    isOpen ? 'gap-3 px-3 py-2 w-full' : 'p-2 justify-center w-11 h-11'
                ]"
                :title="!isOpen ? (user?.name + ' (' + roleLabel + ')') : undefined"
            >
                <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/50 flex items-center justify-center flex-shrink-0 border border-primary-200 dark:border-primary-700/60 overflow-hidden group-hover:scale-105 transition-transform duration-200">
                    <img v-if="userAvatarUrl" :src="userAvatarUrl" :alt="user?.name" class="w-full h-full object-cover" />
                    <span v-else class="text-xs font-bold text-primary-700 dark:text-primary-300">{{ userInitial }}</span>
                </div>
                <div v-show="isOpen" class="flex-1 min-w-0 transition-opacity duration-200">
                    <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate group-hover:text-primary-700 dark:group-hover:text-primary-400 transition-colors">{{ user?.name }}</p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ roleLabel }}</p>
                </div>
                <span v-show="isOpen" class="relative flex h-2.5 w-2.5 flex-shrink-0" title="Online">
                    <span class="motion-safe:animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500 ring-2 ring-white dark:ring-slate-900"></span>
                </span>
            </div>
        </div>
    </aside>
</template>

<style scoped>
nav a svg,
nav button svg:first-child {
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), color 0.15s ease-out;
}
nav a:hover svg,
nav button:hover svg:first-child {
    transform: scale(1.14) translateY(-1px);
}
nav a:active svg,
nav button:active svg:first-child {
    transform: scale(0.95);
}
</style>
