<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Editor from '@tinymce/tinymce-vue'
import { usePWA } from '@/Composables/usePWA'
import axios from 'axios'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const page = usePage()

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({})
    },
    divisions: {
        type: Array,
        default: () => []
    },
    employees: {
        type: Array,
        default: () => []
    },
    academicYearSummary: {
        type: Object,
        default: () => ({
            years: [],
            years_list: [],
            active_year: '',
            active_semester: 'ganjil',
            archived_years: [],
        })
    },
    photoStats: {
        type: Object,
        default: () => ({ total_files: 0, total_bytes: 0, total_size_formatted: '0 KB' })
    },
    cleaningOptions: {
        type: Array,
        default: () => []
    },
    isSuperAdmin: {
        type: Boolean,
        default: false
    },
    initialTab: {
        type: String,
        default: 'presensi'
    }
})

const isSuperAdmin = computed(() => {
    return !!(
        props.isSuperAdmin ||
        page.props.auth?.roles?.includes('superadmin') ||
        page.props.auth?.is_actual_superadmin
    )
})

// ─── TAB NAVIGATION STATE ───────────────────────────────────────────────────
const activeTab = ref(props.initialTab || 'presensi')
const showMobileTabMenu = ref(false)

// ─── FORM UTAMA (MENCAKUP SEMUA PENGATURAN SISTEM) ─────────────────────────
const form = useForm({
    // Pengumuman & Tampilan
    announcement_text: props.settings.announcement_text || '',
    popup_active: props.settings.popup_active === '1' || props.settings.popup_active === true || props.settings.popup_active === 'true',
    popup_text: props.settings.popup_text || '',
    youtube_link: props.settings.youtube_link || '',
    running_text: props.settings.running_text || '',

    // Notifikasi PWA
    push_notifications_active: props.settings.push_notifications_active === '1' || props.settings.push_notifications_active === true || props.settings.push_notifications_active === 'true',

    // Presensi GPS & Jam Kerja
    feature_presensi: props.settings.feature_presensi === '1' || props.settings.feature_presensi === true || props.settings.feature_presensi === 'true',
    presensi_latitude: props.settings.presensi_latitude || '-7.7956',
    presensi_longitude: props.settings.presensi_longitude || '110.3695',
    presensi_radius_meters: Number(props.settings.presensi_radius_meters) || 100,
    presensi_work_start: props.settings.presensi_work_start || '07:00',
    presensi_late_tolerance: props.settings.presensi_late_tolerance || 15,
    presensi_work_end: props.settings.presensi_work_end || '15:00',
    presensi_whatsapp_target: props.settings.presensi_whatsapp_target || '',

    // Multi-Mode Presensi
    attendance_mode_gps: props.settings.attendance_mode_gps === '1' || props.settings.attendance_mode_gps === 'true' || props.settings.attendance_mode_gps === undefined,
    attendance_mode_rfid: props.settings.attendance_mode_rfid === '1' || props.settings.attendance_mode_rfid === 'true',
    attendance_mode_fingerprint: props.settings.attendance_mode_fingerprint === '1' || props.settings.attendance_mode_fingerprint === 'true',
    kiosk_api_token: props.settings.kiosk_api_token || '',

    // Modul & Fitur HR
    feature_badges: props.settings.feature_badges === '1' || props.settings.feature_badges === 'true',
    feature_divisi: props.settings.feature_divisi === '1' || props.settings.feature_divisi === 'true',
    feature_cuti: props.settings.feature_cuti === '1' || props.settings.feature_cuti === 'true',
    feature_idcard: props.settings.feature_idcard === '1' || props.settings.feature_idcard === 'true',
    feature_notes: props.settings.feature_notes === '1' || props.settings.feature_notes === 'true',
    feature_jurnal: props.settings.feature_jurnal === '1' || props.settings.feature_jurnal === 'true' || props.settings.feature_jurnal === undefined,
    feature_arsip: props.settings.feature_arsip === '1' || props.settings.feature_arsip === 'true' || props.settings.feature_arsip === undefined,
    feature_kpi: props.settings.feature_kpi === '1' || props.settings.feature_kpi === 'true' || props.settings.feature_kpi === undefined,
    feature_habit: props.settings.feature_habit === '1' || props.settings.feature_habit === 'true' || props.settings.feature_habit === undefined,
    feature_administrasi_guru: props.settings.feature_administrasi_guru === '1' || props.settings.feature_administrasi_guru === 'true' || props.settings.feature_administrasi_guru === undefined,
    custom_habit_divisions_active: props.settings.custom_habit_divisions_active === '1' || props.settings.custom_habit_divisions_active === 'true',

    // Fitur Sistem & Lanjutan
    gamification_active: props.settings.gamification_active === '1' || props.settings.gamification_active === 'true',
    dark_mode_active: props.settings.dark_mode_active === '1' || props.settings.dark_mode_active === 'true',
    auto_warning_active: props.settings.auto_warning_active === '1' || props.settings.auto_warning_active === 'true',

    // Pemeliharaan (Maintenance)
    maintenance_mode: props.settings.maintenance_mode === '1' || props.settings.maintenance_mode === 'true',
    maintenance_title: props.settings.maintenance_title || 'Sistem Sedang Dalam Pemeliharaan',
    maintenance_message: props.settings.maintenance_message || 'Kami sedang melakukan pemeliharaan rutin dan peningkatan performa sistem habit tracker. Mohon maaf atas ketidaknyamanan Anda. Sistem akan segera kembali normal.',
    maintenance_end_time: props.settings.maintenance_end_time || '',
})

// ─── TAB DEFINITIONS ────────────────────────────────────────────────────────
const tabOptions = computed(() => [
    {
        id: 'presensi',
        title: 'Presensi GPS & Jam Kerja',
        shortTitle: 'Presensi GPS',
        description: 'Pusat koordinat geofencing, jam kerja & RFID Kiosk',
        badge: form.feature_presensi ? '● Aktif' : '○ Nonaktif',
        badgeClass: form.feature_presensi 
            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300' 
            : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400',
        activeRing: 'border-emerald-500 bg-emerald-50/80 dark:bg-emerald-950/50 text-emerald-900 dark:text-emerald-200 ring-2 ring-emerald-500/20',
        iconBg: 'bg-emerald-600 text-white',
        iconInactiveBg: 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400',
        iconPath: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z'
    },
    {
        id: 'modul',
        title: 'Fitur & Modul Aplikasi',
        shortTitle: 'Modul Fitur',
        description: 'Kelola modul presensi, cuti, divisi, ID card, arsip & jurnal',
        badge: '12 Modul',
        badgeClass: 'bg-fuchsia-100 text-fuchsia-800 dark:bg-fuchsia-950/80 dark:text-fuchsia-300',
        activeRing: 'border-fuchsia-500 bg-fuchsia-50/80 dark:bg-fuchsia-950/50 text-fuchsia-900 dark:text-fuchsia-200 ring-2 ring-fuchsia-500/20',
        iconBg: 'bg-fuchsia-600 text-white',
        iconInactiveBg: 'bg-fuchsia-50 dark:bg-fuchsia-950/60 text-fuchsia-600 dark:text-fuchsia-400',
        iconPath: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6z M14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6z M4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2z M14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z'
    },
    {
        id: 'akademik',
        title: 'Tahun Ajaran & Akademik',
        shortTitle: 'Akademik & Arsip',
        description: 'Periode aktif semester & pengarsipan administrasi guru',
        badge: `${props.academicYearSummary?.years?.length || 0} Tahun`,
        badgeClass: 'bg-blue-100 text-blue-800 dark:bg-blue-950/80 dark:text-blue-300',
        activeRing: 'border-blue-500 bg-blue-50/80 dark:bg-blue-950/50 text-blue-900 dark:text-blue-200 ring-2 ring-blue-500/20',
        iconBg: 'bg-blue-600 text-white',
        iconInactiveBg: 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400',
        iconPath: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'
    },
    {
        id: 'tampilan',
        title: 'Pengumuman & Tampilan',
        shortTitle: 'Tampilan & Media',
        description: 'Pengumuman dashboard, modal popup, video YouTube & running text',
        badge: form.popup_active ? 'Popup Aktif' : 'Standar',
        badgeClass: form.popup_active 
            ? 'bg-purple-100 text-purple-800 dark:bg-purple-950/80 dark:text-purple-300' 
            : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400',
        activeRing: 'border-purple-500 bg-purple-50/80 dark:bg-purple-950/50 text-purple-900 dark:text-purple-200 ring-2 ring-purple-500/20',
        iconBg: 'bg-purple-600 text-white',
        iconInactiveBg: 'bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400',
        iconPath: 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z'
    },
    {
        id: 'notifikasi',
        title: 'Push Notifikasi PWA',
        shortTitle: 'Notifikasi PWA',
        description: 'Status langganan perangkat & siaran broadcast Web Push',
        badge: form.push_notifications_active ? '● Aktif' : '○ Nonaktif',
        badgeClass: form.push_notifications_active 
            ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300' 
            : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400',
        activeRing: 'border-amber-500 bg-amber-50/80 dark:bg-amber-950/50 text-amber-900 dark:text-amber-200 ring-2 ring-amber-500/20',
        iconBg: 'bg-amber-600 text-white',
        iconInactiveBg: 'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400',
        iconPath: 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0'
    },
    {
        id: 'pemeliharaan',
        title: 'Sistem & Pemeliharaan',
        shortTitle: 'Pemeliharaan',
        description: 'Mode gelap, gamifikasi, maintenance mode & pembersihan foto',
        badge: form.maintenance_mode ? '⚠️ Maintenance' : 'Normal',
        badgeClass: form.maintenance_mode 
            ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/80 dark:text-rose-300' 
            : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400',
        activeRing: 'border-rose-500 bg-rose-50/80 dark:bg-rose-950/50 text-rose-900 dark:text-rose-200 ring-2 ring-rose-500/20',
        iconBg: 'bg-rose-600 text-white',
        iconInactiveBg: 'bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400',
        iconPath: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'
    }
])

const currentTabInfo = computed(() => {
    return tabOptions.value.find(t => t.id === activeTab.value) || tabOptions.value[0]
})

const switchTab = (tabId) => {
    activeTab.value = tabId
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href)
        url.searchParams.set('tab', tabId)
        window.history.replaceState({}, '', url.toString())
    }
    if (tabId === 'presensi') {
        nextTick(() => {
            if (!hrMapInstance && form.feature_presensi) {
                initHrMap()
            } else if (hrMapInstance) {
                setTimeout(() => {
                    hrMapInstance?.invalidateSize()
                }, 150)
            }
        })
    }
}

const selectMobileTab = (tabId) => {
    switchTab(tabId)
    showMobileTabMenu.value = false
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

const submit = () => {
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
    })
}

// ─── LEAFLET MINI MAP PRESENSI ──────────────────────────────────────────────
let hrMapInstance = null
let hrMapMarker = null
let hrMapCircle = null
const isFullscreenMap = ref(false)
const isDetectingLocation = ref(false)

const initHrMap = () => {
    const mapEl = document.getElementById('hr-presensi-map')
    if (!mapEl) return

    if (hrMapInstance) {
        hrMapInstance.remove()
        hrMapInstance = null
    }

    const initialLat = Number(form.presensi_latitude) || -7.7956
    const initialLng = Number(form.presensi_longitude) || 110.3695
    const initialRadius = Number(form.presensi_radius_meters) || 100

    hrMapInstance = L.map('hr-presensi-map', {
        center: [initialLat, initialLng],
        zoom: 17,
        scrollWheelZoom: true,
    })

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(hrMapInstance)

    const markerIcon = L.divIcon({
        className: 'school-pin-marker',
        html: `
            <div style="background-color: #059669; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 16px rgba(5,150,105,0.65); border: 3px solid white; transform: translate(-50%, -50%); cursor: grab;">
                <svg style="width: 22px; height: 22px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
            </div>
        `,
        iconSize: [40, 40],
        iconAnchor: [0, 0]
    })

    hrMapMarker = L.marker([initialLat, initialLng], {
        icon: markerIcon,
        draggable: true
    }).addTo(hrMapInstance)

    hrMapCircle = L.circle([initialLat, initialLng], {
        radius: initialRadius,
        color: '#059669',
        fillColor: '#10B981',
        fillOpacity: 0.25,
        weight: 2
    }).addTo(hrMapInstance)

    hrMapInstance.on('click', (e) => {
        const { lat, lng } = e.latlng
        updateHrCoordinates(lat, lng)
    })

    hrMapMarker.on('dragend', (e) => {
        const pos = e.target.getLatLng()
        updateHrCoordinates(pos.lat, pos.lng)
    })

    setTimeout(() => {
        hrMapInstance?.invalidateSize()
    }, 300)
}

const updateHrCoordinates = (lat, lng) => {
    form.presensi_latitude = parseFloat(lat).toFixed(7)
    form.presensi_longitude = parseFloat(lng).toFixed(7)

    if (hrMapMarker) hrMapMarker.setLatLng([lat, lng])
    if (hrMapCircle) hrMapCircle.setLatLng([lat, lng])
}

const handleManualCoords = () => {
    const lat = Number(form.presensi_latitude)
    const lng = Number(form.presensi_longitude)
    if (!isNaN(lat) && !isNaN(lng)) {
        if (hrMapMarker) hrMapMarker.setLatLng([lat, lng])
        if (hrMapCircle) hrMapCircle.setLatLng([lat, lng])
        if (hrMapInstance) hrMapInstance.panTo([lat, lng])
    }
}

const toggleFullscreenMap = () => {
    isFullscreenMap.value = !isFullscreenMap.value
    nextTick(() => {
        setTimeout(() => {
            hrMapInstance?.invalidateSize()
            if (hrMapMarker) {
                hrMapInstance?.panTo(hrMapMarker.getLatLng())
            }
        }, 150)
    })
}

const handleKeyDown = (e) => {
    if (e.key === 'Escape' && isFullscreenMap.value) {
        toggleFullscreenMap()
    }
}

watch(() => form.presensi_radius_meters, (newR) => {
    const r = Number(newR) || 100
    if (hrMapCircle) {
        hrMapCircle.setRadius(r)
    }
})

watch(() => form.feature_presensi, (val) => {
    if (val && activeTab.value === 'presensi') {
        nextTick(() => initHrMap())
    }
})

watch(activeTab, (newTab) => {
    if (newTab === 'presensi') {
        nextTick(() => {
            if (!hrMapInstance && form.feature_presensi) {
                initHrMap()
            } else if (hrMapInstance) {
                setTimeout(() => {
                    hrMapInstance?.invalidateSize()
                }, 150)
            }
        })
    }
})

const getCurrentCoordinates = () => {
    if (!navigator.geolocation) {
        alert('Browser Anda tidak mendukung deteksi lokasi.')
        return
    }
    isDetectingLocation.value = true
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const lat = pos.coords.latitude
            const lng = pos.coords.longitude
            updateHrCoordinates(lat, lng)
            if (hrMapInstance) {
                hrMapInstance.setView([lat, lng], 18)
            }
            isDetectingLocation.value = false
        },
        (err) => {
            alert('Gagal mengambil lokasi GPS: ' + err.message)
            isDetectingLocation.value = false
        },
        { enableHighAccuracy: true }
    )
}

// ─── KIOSK RFID TOKEN & GENERATOR ──────────────────────────────────────────
const savedKioskToken = ref(props.settings.kiosk_api_token || '')
const isSavingToken = ref(false)
const tokenSaveMessage = ref('')
const tokenSaveStatus = ref('')

const isTokenDirty = computed(() => {
    return (form.kiosk_api_token || '').trim() !== (savedKioskToken.value || '').trim()
})

const generateRandomKioskToken = () => {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'
    let token = ''
    for (let i = 0; i < 32; i++) {
        token += chars.charAt(Math.floor(Math.random() * chars.length))
    }
    form.kiosk_api_token = token
    tokenSaveStatus.value = 'pending'
    tokenSaveMessage.value = 'Token acak 32 karakter baru dibuat. Simpan sekarang untuk mengaktifkan.'
}

const saveKioskTokenInstantly = async () => {
    if (isSavingToken.value) return false
    isSavingToken.value = true
    tokenSaveStatus.value = 'loading'
    tokenSaveMessage.value = 'Menyimpan token ke server...'

    try {
        const res = await axios.post(route('admin.settings.kiosk-token'), {
            token: form.kiosk_api_token
        })
        if (res.data.success) {
            savedKioskToken.value = res.data.token
            form.kiosk_api_token = res.data.token
            tokenSaveStatus.value = 'success'
            tokenSaveMessage.value = '✓ Token berhasil disimpan ke database & langsung aktif!'
            setTimeout(() => {
                if (tokenSaveStatus.value === 'success') {
                    tokenSaveMessage.value = ''
                }
            }, 5000)
            return true
        }
    } catch (err) {
        tokenSaveStatus.value = 'error'
        tokenSaveMessage.value = 'Gagal menyimpan token: ' + (err.response?.data?.message || err.message)
        return false
    } finally {
        isSavingToken.value = false
    }
    return false
}

const kioskFullUrl = computed(() => {
    const origin = typeof window !== 'undefined' ? window.location.origin : ''
    const token = form.kiosk_api_token?.trim() || ''
    return `${origin}/kiosk-rfid?token=${encodeURIComponent(token)}`
})

const isCopiedKioskUrl = ref(false)
const copyKioskUrl = async () => {
    if (isTokenDirty.value) {
        await saveKioskTokenInstantly()
    }
    try {
        await navigator.clipboard.writeText(kioskFullUrl.value)
        isCopiedKioskUrl.value = true
        setTimeout(() => {
            isCopiedKioskUrl.value = false
        }, 2500)
    } catch (err) {
        console.error('Gagal menyalin:', err)
    }
}

const openKiosk = async () => {
    if (isTokenDirty.value) {
        const ok = await saveKioskTokenInstantly()
        if (!ok) return
    }
    window.open(kioskFullUrl.value, '_blank')
}

// ─── TAHUN AJARAN & PENGARSIPAN ADMINISTRASI GURU ───────────────────────────
const addYearForm = useForm({
    year: '',
})

const activePeriodForm = useForm({
    year: props.academicYearSummary?.active_year || '',
    semester: props.academicYearSummary?.active_semester || 'ganjil',
})

const handleAddYear = () => {
    addYearForm.post(route('admin.academic-years.store'), {
        preserveScroll: true,
        onSuccess: () => {
            addYearForm.reset()
        }
    })
}

const handleSetActivePeriod = () => {
    activePeriodForm.post(route('admin.academic-years.set-active'), {
        preserveScroll: true,
    })
}

const handleToggleArchive = (item) => {
    const isCurrentlyArchived = item.is_archived
    const warningText = isCurrentlyArchived 
        ? `Buka kunci tahun ajaran ${item.year}? Guru akan kembali dapat mengunggah dan mengubah berkas pada tahun ajaran ini.`
        : `Arsipkan administrasi guru untuk tahun ajaran ${item.year}? Seluruh berkas administrasi guru pada tahun ajaran ini akan dikunci menjadi status hanya-baca (read-only).`
    
    if (!confirm(warningText)) return

    router.post(route('admin.academic-years.toggle-archive'), {
        year: item.year,
        is_archived: !isCurrentlyArchived,
    }, {
        preserveScroll: true,
    })
}

const handleDeleteYear = (year) => {
    if (!confirm(`Hapus tahun ajaran ${year} dari daftar sistem?`)) return

    router.delete(route('admin.academic-years.destroy', { year: encodeURIComponent(year) }), {
        preserveScroll: true,
    })
}

const handleDownloadZip = (year) => {
    window.location.href = route('admin.academic-years.export-archive', { year: encodeURIComponent(year) })
}

// ─── PWA WEB PUSH NOTIFICATIONS ─────────────────────────────────────────────
const {
    isPushSupported,
    isPushSubscribed,
    pushPermission,
    isSubscribingPush,
    subscribeToPush,
    unsubscribeFromPush,
    sendTestPush,
} = usePWA()

const pushStatus = ref({
    vapid_configured: true,
    total_system_subscriptions: 0,
    user_subscriptions_count: 0,
    loading: false,
})

const pushTestLoading = ref(false)
const pushTestMessage = ref('')
const pushTestStatus = ref('')

const loadPushStatus = async () => {
    try {
        pushStatus.value.loading = true
        const res = await fetch('/push-subscriptions/status')
        if (res.ok) {
            const data = await res.json()
            pushStatus.value = { ...pushStatus.value, ...data, loading: false }
        }
    } catch (e) {
        console.warn('Load push status error:', e)
    } finally {
        pushStatus.value.loading = false
    }
}

const handleSubscribeThisDevice = async () => {
    pushTestMessage.value = ''
    try {
        const vapidKey = page.props.webpush?.vapid_public_key || ''
        await subscribeToPush(vapidKey)
        await loadPushStatus()
        pushTestStatus.value = 'success'
        pushTestMessage.value = 'Perangkat ini berhasil didaftarkan untuk Push Notification!'
    } catch (e) {
        pushTestStatus.value = 'error'
        pushTestMessage.value = e.message || 'Gagal mendaftarkan perangkat.'
    }
}

const handleUnsubscribeThisDevice = async () => {
    pushTestMessage.value = ''
    try {
        await unsubscribeFromPush()
        await loadPushStatus()
        pushTestStatus.value = 'success'
        pushTestMessage.value = 'Perangkat ini telah dinonaktifkan dari Push Notification.'
    } catch (e) {
        pushTestStatus.value = 'error'
        pushTestMessage.value = e.message || 'Gagal mematikan notifikasi di perangkat ini.'
    }
}

const handleSendTestPush = async () => {
    pushTestLoading.value = true
    pushTestMessage.value = ''
    try {
        const res = await sendTestPush()
        pushTestStatus.value = 'success'
        pushTestMessage.value = res.message || 'Notifikasi berhasil dikirim ke perangkat Anda! Silakan periksa notifikasi di layar Anda.'
    } catch (e) {
        pushTestStatus.value = 'error'
        pushTestMessage.value = e.message || 'Gagal mengirimkan tes notifikasi.'
    } finally {
        pushTestLoading.value = false
    }
}

// ─── BROADCAST PUSH NOTIFICATION ───────────────────────────────────────────
const showBroadcastModal = ref(false)
const broadcastLoading = ref(false)
const broadcastMessage = ref('')
const broadcastStatus = ref('')

const broadcastForm = ref({
    title: 'Pengingat Mutaba\'ah & Presensi 🔔',
    body: 'Bapak/Ibu guru dan karyawan, mohon pastikan presensi dan catatan mutaba\'ah harian hari ini sudah terisi.',
    target: 'all',
    division: '',
    user_id: '',
    url: '/dashboard',
})

const handleOpenBroadcastModal = () => {
    broadcastMessage.value = ''
    broadcastStatus.value = ''
    showBroadcastModal.value = true
}

const handleSendBroadcast = async () => {
    if (!broadcastForm.value.title.trim() || !broadcastForm.value.body.trim()) {
        broadcastStatus.value = 'error'
        broadcastMessage.value = 'Judul dan isi pesan notifikasi wajib diisi.'
        return
    }

    broadcastLoading.value = true
    broadcastMessage.value = ''
    broadcastStatus.value = ''

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        const res = await fetch(route('push.broadcast'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(broadcastForm.value),
        })

        const data = await res.json()
        if (!res.ok) {
            throw new Error(data.message || 'Gagal menyiarkan notifikasi push.')
        }

        broadcastStatus.value = 'success'
        broadcastMessage.value = data.message || 'Notifikasi berhasil disiarkan!'
        await loadPushStatus()
    } catch (e) {
        broadcastStatus.value = 'error'
        broadcastMessage.value = e.message || 'Terjadi kesalahan saat mengirim notifikasi.'
    } finally {
        broadcastLoading.value = false
    }
}

// ─── PEMELIHARAAN (MAINTENANCE) & PEMBERSIHAN STORAGE FOTO ───────────────────
const isTogglingInstant = ref(false)
const handleInstantToggle = () => {
    isTogglingInstant.value = true
    router.post(route('admin.maintenance.toggle'), {
        target_mode: form.maintenance_mode,
        maintenance_title: form.maintenance_title,
        maintenance_message: form.maintenance_message,
        maintenance_end_time: form.maintenance_end_time,
    }, {
        preserveScroll: true,
        onFinish: () => {
            isTogglingInstant.value = false
        }
    })
}

const cleanForm = useForm({
    months: 1,
})

const selectedCleaningOption = computed(() => {
    return props.cleaningOptions?.find(o => o.months === Number(cleanForm.months)) || props.cleaningOptions?.[0]
})

const handleCleanPhotos = () => {
    const opt = selectedCleaningOption.value
    const dateText = opt?.formatted_date || ''
    const countText = opt?.photo_count !== undefined ? `${opt.photo_count} data foto` : ''

    const confirmed = confirm(
        `PERINGATAN PEMBERSIHAN FILE FOTO STORAGE:\n\n` +
        `Anda akan menghapus file foto presensi s.d. tanggal ${dateText} (${countText}).\n\n` +
        `• File fisik gambar akan dihapus dari server untuk mengosongkan storage.\n` +
        `• Catatan riwayat presensi pegawai tetap aman tersimpan di database.\n\n` +
        `Apakah Anda yakin ingin melanjutkan pembersihan?`
    )

    if (!confirmed) return

    cleanForm.post(route('admin.settings.clean-photos'), {
        preserveScroll: true,
    })
}

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown)
    if (activeTab.value === 'presensi' && form.feature_presensi) {
        nextTick(() => initHrMap())
    }
    loadPushStatus()
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown)
    if (hrMapInstance) {
        hrMapInstance.remove()
        hrMapInstance = null
    }
})
</script>

<template>
    <Head title="Pengaturan Sistem" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="font-black text-2xl text-slate-800 dark:text-slate-100 leading-tight">Pengaturan Sistem</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Pusat kontrol konfigurasi presensi GPS, modul aplikasi, tahun ajaran, tampilan, dan pemeliharaan.
                    </p>
                </div>
                <div class="flex items-center gap-2 self-start sm:self-center">
                    <span v-if="isSuperAdmin" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300 border border-purple-200 dark:border-purple-800 shadow-2xs">
                        👑 Superadmin
                    </span>
                    <span v-else class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                        🛡️ Admin
                    </span>
                </div>
            </div>
        </template>

        <div class="w-full py-6 px-4 sm:px-6 lg:px-8 space-y-6">
            
            <!-- Alert Flash Success -->
            <div v-if="$page.props.flash?.success" class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 flex items-center gap-3 shadow-xs">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-bold text-sm">{{ $page.props.flash.success }}</span>
            </div>

            <!-- Alert Errors -->
            <div v-if="$page.props.errors && Object.keys($page.props.errors).length > 0" class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 flex items-start gap-3 shadow-xs">
                <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div class="text-xs space-y-1">
                    <span class="font-bold text-sm block">Terjadi kesalahan pada data pengaturan:</span>
                    <p v-for="(err, key) in $page.props.errors" :key="key">{{ err }}</p>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════════════
                 TAB NAVIGATION BAR (Desktop & Mobile)
            ══════════════════════════════════════════════════════════════════ -->
            <!-- Mobile Quick Banner (Hidden on Desktop) -->
            <div class="md:hidden flex items-center justify-between p-3 bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 shadow-xs">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div :class="['w-8 h-8 rounded-xl flex items-center justify-center shrink-0 shadow-2xs', currentTabInfo.iconBg]">
                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="currentTabInfo.iconPath" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-xs text-slate-800 dark:text-slate-100 truncate">
                                {{ currentTabInfo.title }}
                            </span>
                            <span :class="['text-[9px] px-1.5 py-0.5 rounded-md font-bold shrink-0', currentTabInfo.badgeClass]">
                                {{ currentTabInfo.badge }}
                            </span>
                        </div>
                        <p class="text-[10px] text-slate-400 dark:text-slate-500 truncate">
                            {{ currentTabInfo.description }}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="showMobileTabMenu = true"
                    class="shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:hover:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 font-bold text-xs border border-emerald-200/80 dark:border-emerald-800/80 transition-all cursor-pointer active:scale-95 shadow-2xs"
                >
                    <span>Pilih Tab</span>
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </div>

            <!-- Desktop Tab Bar (Hidden on Mobile) -->
            <div class="hidden md:flex items-center gap-2 p-1.5 bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 shadow-xs overflow-x-auto">
                <button
                    v-for="tab in tabOptions"
                    :key="tab.id"
                    type="button"
                    @click="switchTab(tab.id)"
                    :class="[
                        'flex items-center gap-2.5 px-4 py-2.5 rounded-xl font-bold text-xs transition-all duration-200 shrink-0 cursor-pointer',
                        activeTab === tab.id
                            ? tab.activeRing + ' shadow-xs'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50'
                    ]"
                >
                    <div :class="['w-7 h-7 rounded-lg flex items-center justify-center transition-colors', activeTab === tab.id ? tab.iconBg : 'bg-slate-100 dark:bg-slate-700 text-slate-500']">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="tab.iconPath" />
                        </svg>
                    </div>
                    <span class="text-xs">{{ tab.shortTitle }}</span>
                    <span :class="['text-[10px] px-2 py-0.5 rounded-full font-bold', tab.badgeClass]">
                        {{ tab.badge }}
                    </span>
                </button>
            </div>

            <!-- ══════════════════════════════════════════════════════════════════
                 FORM INDUK PENGATURAN
            ══════════════════════════════════════════════════════════════════ -->
            <form @submit.prevent="submit">

                <!-- ──────────────────────────────────────────────────────────────
                     TAB 1: PRESENSI GPS & JAM KERJA
                ────────────────────────────────────────────────────────────── -->
                <div v-show="activeTab === 'presensi'" class="space-y-6">
                    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 p-6 sm:p-8 shadow-xs">
                        
                        <!-- Header Tab Presensi -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-100 dark:border-slate-700">
                            <div>
                                <h3 class="text-xl font-black text-slate-800 dark:text-slate-100 flex items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </div>
                                    <span>Pengaturan Presensi GPS &amp; Jam Kerja</span>
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                    Atur titik koordinat sekolah, radius geofencing presensi HP, dan jam kerja masuk/pulang harian.
                                </p>
                            </div>

                            <!-- Switch Aktifkan Presensi -->
                            <div class="flex items-center gap-3 self-start sm:self-center bg-emerald-50/60 dark:bg-emerald-950/30 px-4 py-2 rounded-2xl border border-emerald-200/60 dark:border-emerald-800/40">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-200">
                                    {{ form.feature_presensi ? 'Fitur Presensi Aktif' : 'Fitur Presensi Nonaktif' }}
                                </span>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.feature_presensi"
                                        class="sr-only peer"
                                    />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-emerald-600"></div>
                                </label>
                            </div>
                        </div>

                        <!-- Body Presensi (Jika Aktif) -->
                        <div v-if="form.feature_presensi" class="mt-6 space-y-6">
                            
                            <!-- Pilih Metode Presensi Yang Aktif -->
                            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xs space-y-4">
                                <h5 class="text-xs font-bold text-slate-800 dark:text-slate-200 mb-3 uppercase tracking-wider">Pilih Metode Presensi Yang Aktif:</h5>
                                <div class="space-y-2.5">
                                    <label class="flex items-center justify-between p-3 rounded-xl border border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer transition-colors">
                                        <div>
                                            <span class="block text-sm font-bold text-slate-800 dark:text-slate-200">Presensi GPS (Selfie & Lokasi)</span>
                                            <span class="block text-xs text-slate-500 mt-0.5">Pegawai dapat absen melalui smartphone mereka sendiri.</span>
                                        </div>
                                        <input type="checkbox" v-model="form.attendance_mode_gps" class="rounded text-emerald-600 focus:ring-emerald-500 w-5 h-5 bg-card-subtle border-slate-300">
                                    </label>
                                    
                                    <!-- Presensi RFID & Kiosk Token Panel -->
                                    <div class="rounded-xl border border-slate-100 dark:border-slate-800 transition-all overflow-hidden"
                                         :class="{ 'bg-emerald-50/20 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800/60': form.attendance_mode_rfid }">
                                        <label class="flex items-center justify-between p-3 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer transition-colors">
                                            <div>
                                                <span class="block text-sm font-bold text-slate-800 dark:text-slate-200">Presensi RFID (Mesin Scanner)</span>
                                                <span class="block text-xs text-slate-500 mt-0.5">Menggunakan kartu RFID yang ditempel pada Kiosk.</span>
                                            </div>
                                            <input type="checkbox" v-model="form.attendance_mode_rfid" class="rounded text-emerald-600 focus:ring-emerald-500 w-5 h-5 bg-card-subtle border-slate-300">
                                        </label>

                                        <!-- Panel Pengaturan Token & Link Kiosk RFID -->
                                        <div v-if="form.attendance_mode_rfid" class="p-4 pt-3 border-t border-emerald-100 dark:border-emerald-900/40 bg-white/70 dark:bg-slate-900/70 space-y-4">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <span class="relative flex h-2.5 w-2.5">
                                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                                                    </span>
                                                    <span class="text-xs font-black uppercase tracking-wider text-emerald-800 dark:text-emerald-300">
                                                        Token Keamanan &amp; Link Akses Kiosk RFID
                                                    </span>
                                                </div>
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-700">
                                                    Kiosk Security
                                                </span>
                                            </div>

                                            <div class="space-y-2">
                                                <div class="flex items-center justify-between">
                                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                                        Token Keamanan Kiosk:
                                                    </label>
                                                    <span v-if="!isTokenDirty" class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
                                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                                        Aktif di Server
                                                    </span>
                                                    <span v-else class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-600 dark:text-amber-400 animate-pulse">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                                        Belum Disimpan
                                                    </span>
                                                </div>
                                                <div class="flex flex-col sm:flex-row gap-2">
                                                    <div class="relative flex-1">
                                                        <input
                                                            type="text"
                                                            v-model="form.kiosk_api_token"
                                                            placeholder="Minimal 32 karakter (Gunakan tombol Generate Acak)"
                                                            class="w-full px-3.5 py-2 font-mono text-xs font-bold bg-white dark:bg-slate-800 border rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-slate-800 dark:text-slate-100 tracking-wider transition-colors"
                                                            :class="isTokenDirty ? 'border-amber-300 dark:border-amber-700 bg-amber-50/20' : 'border-slate-300 dark:border-slate-600'"
                                                        />
                                                    </div>
                                                    <div class="flex items-center flex-wrap gap-2">
                                                        <button
                                                            type="button"
                                                            @click="saveKioskTokenInstantly"
                                                            :disabled="isSavingToken"
                                                            :class="[
                                                                'inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-xl transition-all active:scale-95 cursor-pointer shadow-xs',
                                                                isTokenDirty
                                                                    ? 'bg-emerald-600 hover:bg-emerald-700 text-white ring-2 ring-emerald-500/30'
                                                                    : 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-100'
                                                            ]"
                                                            title="Simpan token ini ke server sekarang"
                                                        >
                                                            <svg v-if="isSavingToken" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                            <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
                                                            <span>{{ isSavingToken ? 'Menyimpan...' : 'Simpan Token' }}</span>
                                                        </button>

                                                        <button
                                                            type="button"
                                                            @click="generateRandomKioskToken"
                                                            class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-xl transition-all active:scale-95 cursor-pointer shadow-2xs"
                                                            title="Generate token acak baru minimal 32 karakter"
                                                        >
                                                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                            </svg>
                                                            <span>Generate Acak 32 Karakter</span>
                                                        </button>
                                                    </div>
                                                </div>

                                                <div v-if="tokenSaveMessage" :class="[
                                                    'p-2.5 rounded-lg text-xs font-medium flex items-center gap-2 transition-all',
                                                    tokenSaveStatus === 'success' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : '',
                                                    tokenSaveStatus === 'error' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border border-rose-200 dark:border-rose-800' : '',
                                                    tokenSaveStatus === 'pending' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-800' : '',
                                                    tokenSaveStatus === 'loading' ? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' : ''
                                                ]">
                                                    <span>{{ tokenSaveMessage }}</span>
                                                </div>
                                            </div>

                                            <div class="p-3 bg-slate-50 dark:bg-slate-800/80 rounded-xl border border-slate-200/80 dark:border-slate-700/80 space-y-2">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">URL Kiosk Presensi Sekolah:</span>
                                                    <span class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">Otomatis Terisi Token</span>
                                                </div>
                                                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                                                    <input
                                                        type="text"
                                                        readonly
                                                        :value="kioskFullUrl"
                                                        class="flex-1 px-3 py-1.5 text-xs font-mono bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-700 dark:text-slate-300 select-all cursor-text"
                                                    />
                                                    <div class="flex items-center gap-2">
                                                        <button
                                                            type="button"
                                                            @click="copyKioskUrl"
                                                            :class="[
                                                                'inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg transition-all active:scale-95 cursor-pointer shadow-2xs flex-1 sm:flex-initial',
                                                                isCopiedKioskUrl
                                                                    ? 'bg-emerald-600 text-white'
                                                                    : 'bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:hover:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                                                            ]"
                                                        >
                                                            <svg v-if="!isCopiedKioskUrl" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                                            </svg>
                                                            <svg v-else class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                            </svg>
                                                            <span>{{ isCopiedKioskUrl ? 'Tersalin! ✓' : 'Salin URL' }}</span>
                                                        </button>

                                                        <button
                                                            type="button"
                                                            @click="openKiosk"
                                                            class="inline-flex items-center justify-center gap-1.5 px-3.5 py-1.5 text-xs font-bold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition-all cursor-pointer shadow-2xs flex-1 sm:flex-initial"
                                                            title="Buka tampilan Kiosk di tab baru (otomatis menyimpan token)"
                                                        >
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                            </svg>
                                                            <span>Buka Kiosk</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex items-start gap-2 p-2.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/40 text-[11px] text-emerald-800 dark:text-emerald-300">
                                                <svg class="w-4 h-4 shrink-0 text-emerald-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>
                                                    <strong>Otomatis Tersimpan:</strong> Tombol <em>Buka Kiosk</em> dan <em>Salin URL</em> di atas sudah otomatis menyimpan token ke database sebelum membuka halaman.
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <label class="flex items-center justify-between p-3 rounded-xl border border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer transition-colors">
                                        <div>
                                            <span class="block text-sm font-bold text-slate-800 dark:text-slate-200">Presensi Sidik Jari / Fingerprint</span>
                                            <span class="block text-xs text-slate-500 mt-0.5">Menggunakan mesin Fingerprint ZKTeco / API Tarik Data.</span>
                                        </div>
                                        <input type="checkbox" v-model="form.attendance_mode_fingerprint" class="rounded text-emerald-600 focus:ring-emerald-500 w-5 h-5 bg-card-subtle border-slate-300">
                                    </label>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 xl:gap-8">
                                <!-- Kolom Kiri: Peta Leaflet & Koordinat -->
                                <div class="xl:col-span-8 space-y-4">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                        <div>
                                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300">
                                                Peta Titik Pusat Lokasi Sekolah
                                            </label>
                                            <p class="text-xs text-slate-400 mt-0.5">Klik peta atau seret pin hijau untuk memindahkan titik pusat sekolah.</p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                @click="getCurrentCoordinates"
                                                :disabled="isDetectingLocation"
                                                class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 px-3.5 py-2 rounded-xl border border-emerald-200 dark:border-emerald-800 transition-colors cursor-pointer"
                                            >
                                                <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': isDetectingLocation }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>{{ isDetectingLocation ? 'Mendeteksi...' : 'GPS Saat Ini' }}</span>
                                            </button>

                                            <button
                                                type="button"
                                                @click="toggleFullscreenMap"
                                                class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-600 transition-all shadow-2xs cursor-pointer"
                                                title="Buka peta ukuran layar penuh (Fullscreen)"
                                            >
                                                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                                                </svg>
                                                <span>Layar Penuh (Fullscreen)</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Container Peta & Fullscreen Overlay -->
                                    <div
                                        :class="[
                                            'transition-all duration-300',
                                            isFullscreenMap
                                                ? 'fixed inset-0 z-[99999] w-screen h-screen bg-slate-950/95 backdrop-blur-md flex flex-col p-4 sm:p-6'
                                                : 'relative'
                                        ]"
                                    >
                                        <div v-if="isFullscreenMap" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 mb-3 border-b border-slate-800">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shadow-lg shadow-emerald-600/30 shrink-0">
                                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    </svg>
                                                </div>
                                                <div>
                                                    <h4 class="text-base font-black text-white flex items-center gap-2">
                                                        <span>Mode Layar Penuh: Penitikan Titik Koordinat Presensi</span>
                                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-950 text-emerald-300 border border-emerald-800">Fullscreen</span>
                                                    </h4>
                                                    <p class="text-xs text-slate-400">
                                                        Geser pin hijau atau klik di peta untuk menentukan koordinat sekolah secara leluasa.
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-2 self-end sm:self-center">
                                                <button
                                                    type="button"
                                                    @click="getCurrentCoordinates"
                                                    :disabled="isDetectingLocation"
                                                    class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-300 bg-emerald-950 hover:bg-emerald-900 px-3.5 py-2 rounded-xl border border-emerald-800 transition-colors cursor-pointer"
                                                >
                                                    <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': isDetectingLocation }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <span>{{ isDetectingLocation ? 'Mendeteksi...' : 'GPS Saat Ini' }}</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    @click="toggleFullscreenMap"
                                                    class="inline-flex items-center gap-1.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 px-4 py-2 rounded-xl transition-all shadow-md cursor-pointer"
                                                >
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                    <span>Tutup Layar Penuh (Esc)</span>
                                                </button>
                                            </div>
                                        </div>

                                        <div
                                            :class="[
                                                'rounded-2xl overflow-hidden border relative shadow-inner bg-slate-100 dark:bg-slate-900',
                                                isFullscreenMap
                                                    ? 'flex-1 w-full border-slate-700/80'
                                                    : 'h-80 sm:h-96 lg:h-[460px] w-full border-slate-200 dark:border-slate-700'
                                            ]"
                                        >
                                            <div id="hr-presensi-map" class="w-full h-full z-10" />

                                            <div class="absolute bottom-3 left-3 z-[1000] bg-white/90 dark:bg-slate-900/90 backdrop-blur-md px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm text-[11px] font-mono font-bold text-slate-700 dark:text-slate-200 pointer-events-none flex items-center gap-2">
                                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
                                                <span>Lat: {{ form.presensi_latitude }}</span>
                                                <span class="text-slate-300 dark:text-slate-600">|</span>
                                                <span>Lng: {{ form.presensi_longitude }}</span>
                                            </div>
                                        </div>

                                        <div v-if="isFullscreenMap" class="mt-3 p-3.5 bg-slate-900/90 backdrop-blur-md rounded-2xl border border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                            <div class="flex items-center gap-4 flex-1">
                                                <div class="flex items-center gap-2 shrink-0">
                                                    <span class="text-xs font-bold text-slate-300">Radius:</span>
                                                    <span class="text-xs font-black text-emerald-400 font-mono bg-emerald-950/80 px-2 py-0.5 rounded-lg border border-emerald-800">
                                                        {{ form.presensi_radius_meters }} meter
                                                    </span>
                                                </div>
                                                <input 
                                                    type="range" 
                                                    v-model="form.presensi_radius_meters" 
                                                    min="10" 
                                                    max="1000" 
                                                    step="10" 
                                                    class="w-full max-w-md accent-emerald-500 cursor-pointer"
                                                />
                                            </div>
                                            <button
                                                type="button"
                                                @click="toggleFullscreenMap"
                                                class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-md transition-all self-end sm:self-center cursor-pointer"
                                            >
                                                Selesai Penitikan &rarr;
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Input Manual Latitude & Longitude -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">Latitude</label>
                                            <input 
                                                type="text" 
                                                v-model="form.presensi_latitude" 
                                                @change="handleManualCoords"
                                                placeholder="-7.7956" 
                                                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 font-mono shadow-xs"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">Longitude</label>
                                            <input 
                                                type="text" 
                                                v-model="form.presensi_longitude" 
                                                @change="handleManualCoords"
                                                placeholder="110.3695" 
                                                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 font-mono shadow-xs"
                                            />
                                        </div>
                                    </div>

                                    <!-- Slider Radius -->
                                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700/60 space-y-2.5">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Radius Toleransi Geofencing Presensi</label>
                                                <p class="text-[11px] text-slate-400">Jarak lingkaran maksimal dari titik pusat sekolah agar presensi HP diterima.</p>
                                            </div>
                                            <span class="text-xs font-black text-emerald-600 dark:text-emerald-400 font-mono bg-emerald-50 dark:bg-emerald-950/60 px-2.5 py-1 rounded-lg border border-emerald-200 dark:border-emerald-800">
                                                {{ form.presensi_radius_meters }} meter
                                            </span>
                                        </div>
                                        <input 
                                            type="range" 
                                            v-model="form.presensi_radius_meters" 
                                            min="10" 
                                            max="1000" 
                                            step="10" 
                                            class="w-full accent-emerald-600 cursor-pointer"
                                        />
                                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                                            <span>10 m (Sangat Ketat)</span>
                                            <span>250 m</span>
                                            <span>500 m</span>
                                            <span>1.000 m (Lebar)</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Kolom Kanan: Jam Kerja & Shortcut Piket -->
                                <div class="xl:col-span-4 space-y-5">
                                    <div class="p-6 rounded-3xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700/60 space-y-4">
                                        <h4 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                            <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>Jam Kerja Default Sekolah</span>
                                        </h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                            Jam kerja standar yang berlaku untuk seluruh pegawai jika tidak memiliki jadwal piket harian khusus.
                                        </p>

                                        <div class="space-y-3.5 pt-1">
                                            <div>
                                                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">Jam Masuk (Check-In)</label>
                                                <input 
                                                    type="time" 
                                                    v-model="form.presensi_work_start" 
                                                    class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-slate-800 dark:text-slate-200 font-mono shadow-xs"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">Toleransi Keterlambatan</label>
                                                <div class="relative">
                                                    <input 
                                                        type="number" 
                                                        v-model="form.presensi_late_tolerance" 
                                                        min="0" 
                                                        max="120" 
                                                        class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-2.5 pr-8 text-slate-800 dark:text-slate-200 font-mono shadow-xs"
                                                    />
                                                    <span class="absolute inset-y-0 right-3 flex items-center text-xs font-bold text-slate-400">menit</span>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">Jam Pulang (Check-Out)</label>
                                                <input 
                                                    type="time" 
                                                    v-model="form.presensi_work_end" 
                                                    class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-slate-800 dark:text-slate-200 font-mono shadow-xs"
                                                />
                                            </div>
                                            <div class="pt-2 border-t border-slate-200/70 dark:border-slate-700/70">
                                                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">No. WhatsApp Admin / SDM (Opsional)</label>
                                                <input 
                                                    type="text" 
                                                    v-model="form.presensi_whatsapp_target" 
                                                    placeholder="081234567890"
                                                    class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-slate-800 dark:text-slate-200 font-mono shadow-xs"
                                                />
                                                <p class="text-[10px] text-slate-400 mt-1 leading-snug">
                                                    Tujuan langsung kirim WA saat pegawai mengajukan Izin / Sakit / Dinas Luar.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="p-6 rounded-3xl bg-linear-to-br from-emerald-500/10 via-emerald-500/5 to-transparent border border-emerald-500/20 space-y-3.5 shadow-2xs">
                                        <div class="flex items-start gap-3.5">
                                            <div class="w-9 h-9 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-md shadow-emerald-600/30">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h5 class="text-sm font-bold text-slate-800 dark:text-slate-100">Jadwal Khusus &amp; Piket Harian</h5>
                                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                                    Ingin membedakan jam kerja hari Jumat atau memetakan giliran guru/pegawai piket harian?
                                                </p>
                                            </div>
                                        </div>
                                        <Link
                                            :href="route('admin.duty-schedules.index')"
                                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all shadow-md hover:shadow-lg cursor-pointer"
                                        >
                                            <span>Kelola Jadwal Harian &amp; Piket Pegawai</span>
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </Link>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- Jika Presensi Nonaktif -->
                        <div v-else class="mt-8 text-center py-16 px-4 rounded-3xl bg-slate-50 dark:bg-slate-900/40 border border-dashed border-slate-200 dark:border-slate-700">
                            <div class="w-14 h-14 mx-auto rounded-3xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 mb-3.5 shadow-inner">
                                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                            </div>
                            <h4 class="text-base font-bold text-slate-700 dark:text-slate-200">Fitur Presensi GPS Sedang Dinonaktifkan</h4>
                            <p class="text-xs text-slate-400 mt-1.5 max-w-md mx-auto">
                                Aktifkan sakelar di atas untuk mengaktifkan validasi lokasi GPS mobile bagi pegawai dan memunculkan menu presensi di sidebar.
                            </p>
                        </div>

                        <!-- Tombol Simpan Tab Presensi -->
                        <div class="mt-8 pt-5 border-t border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row items-center justify-between gap-3">
                            <span class="text-xs text-slate-400">Pastikan koordinat dan jam kerja telah sesuai sebelum menyimpan.</span>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full sm:w-auto px-8 py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs rounded-2xl transition-all shadow-md hover:shadow-lg disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer"
                            >
                                <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>SIMPAN PENGATURAN PRESENSI</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ──────────────────────────────────────────────────────────────
                     TAB 2: FITUR & MODUL APLIKASI
                ────────────────────────────────────────────────────────────── -->
                <div v-show="activeTab === 'modul'" class="space-y-6">
                    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 p-6 sm:p-8 shadow-xs">
                        
                        <div class="pb-6 border-b border-slate-100 dark:border-slate-700">
                            <h3 class="text-xl font-black text-slate-800 dark:text-slate-100 flex items-center gap-2.5">
                                <div class="w-9 h-9 rounded-xl bg-fuchsia-50 dark:bg-fuchsia-950/50 flex items-center justify-center text-fuchsia-600">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6z M14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6z M4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2z M14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                    </svg>
                                </div>
                                <span>Manajemen Fitur &amp; Modul Aplikasi</span>
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                Aktifkan atau nonaktifkan modul aplikasi sesuai dengan kebutuhan alur operasional sekolah.
                            </p>
                        </div>

                        <!-- 12 Kartu Modul -->
                        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                            
                            <!-- 1. Presensi Pegawai -->
                            <div class="flex items-start justify-between p-5 rounded-2xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-fuchsia-200 dark:hover:border-fuchsia-800 transition-all shadow-2xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-emerald-100 dark:bg-emerald-950/60 flex items-center justify-center text-emerald-600 shrink-0 shadow-xs mt-0.5">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">Presensi &amp; Kehadiran</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Modul presensi GPS selfie, scan RFID, dan log kehadiran pegawai.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.feature_presensi" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-fuchsia-600"></div>
                                </label>
                            </div>

                            <!-- 2. Habit Tracker (Mutaba'ah) -->
                            <div class="flex items-start justify-between p-5 rounded-2xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-fuchsia-200 dark:hover:border-fuchsia-800 transition-all shadow-2xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-fuchsia-100 dark:bg-fuchsia-950/60 flex items-center justify-center text-fuchsia-600 shrink-0 shadow-xs mt-0.5">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">Habit Tracker (Mutaba'ah)</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Modul pencatatan ibadah harian pegawai beserta rekap laporan bulanan.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.feature_habit" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-fuchsia-600"></div>
                                </label>
                            </div>

                            <!-- 3. Pengajuan Cuti & Izin -->
                            <div class="flex items-start justify-between p-5 rounded-2xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-fuchsia-200 dark:hover:border-fuchsia-800 transition-all shadow-2xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-fuchsia-100 dark:bg-fuchsia-950/60 flex items-center justify-center text-fuchsia-600 shrink-0 shadow-xs mt-0.5">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">Status Cuti / Sakit / Izin</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Pengajuan izin dan cuti pegawai dengan proteksi streak nilai di leaderboard.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.feature_cuti" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-fuchsia-600"></div>
                                </label>
                            </div>

                            <!-- 4. Grup / Divisi Jabatan -->
                            <div class="flex items-start justify-between p-5 rounded-2xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-fuchsia-200 dark:hover:border-fuchsia-800 transition-all shadow-2xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-fuchsia-100 dark:bg-fuchsia-950/60 flex items-center justify-center text-fuchsia-600 shrink-0 shadow-xs mt-0.5">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">Grup / Divisi Jabatan</h4>
                                            <Link v-if="form.feature_divisi" :href="route('admin.divisions.index')" class="text-[11px] font-bold text-fuchsia-600 hover:underline">
                                                (Kelola &rarr;)
                                            </Link>
                                        </div>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Kelompokkan pegawai ke dalam unit divisi dan filter leaderboard per-divisi.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.feature_divisi" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-fuchsia-600"></div>
                                </label>
                            </div>

                            <!-- 5. ID Card Digital -->
                            <div class="flex items-start justify-between p-5 rounded-2xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-fuchsia-200 dark:hover:border-fuchsia-800 transition-all shadow-2xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-fuchsia-100 dark:bg-fuchsia-950/60 flex items-center justify-center text-fuchsia-600 shrink-0 shadow-xs mt-0.5">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">ID Card Digital</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Pop-up ID card pegawai dengan avatar, lencana, dan QR/Barcode.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.feature_idcard" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-fuchsia-600"></div>
                                </label>
                            </div>

                            <!-- 6. Lencana / Badges -->
                            <div class="flex items-start justify-between p-5 rounded-2xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-fuchsia-200 dark:hover:border-fuchsia-800 transition-all shadow-2xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-fuchsia-100 dark:bg-fuchsia-950/60 flex items-center justify-center text-fuchsia-600 shrink-0 shadow-xs mt-0.5">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">Lencana Khusus (Manual Badge)</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Penghargaan atau tanda lencana manual kepada pegawai teladan.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.feature_badges" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-fuchsia-600"></div>
                                </label>
                            </div>

                            <!-- 7. Catatan Rahasia Pimpinan -->
                            <div class="flex items-start justify-between p-5 rounded-2xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-fuchsia-200 dark:hover:border-fuchsia-800 transition-all shadow-2xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-fuchsia-100 dark:bg-fuchsia-950/60 flex items-center justify-center text-fuchsia-600 shrink-0 shadow-xs mt-0.5">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">Catatan Rahasia Pimpinan</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Pesan evaluasi pribadi rahasia dari pimpinan/superadmin untuk pegawai.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.feature_notes" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-fuchsia-600"></div>
                                </label>
                            </div>

                            <!-- 8. Jurnal Harian Guru -->
                            <div class="flex items-start justify-between p-5 rounded-2xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-fuchsia-200 dark:hover:border-fuchsia-800 transition-all shadow-2xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-fuchsia-100 dark:bg-fuchsia-950/60 flex items-center justify-center text-fuchsia-600 shrink-0 shadow-xs mt-0.5">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">Jurnal Harian Guru</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Fitur pengisian dan monitoring jurnal harian mengajar bagi guru.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.feature_jurnal" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-fuchsia-600"></div>
                                </label>
                            </div>

                            <!-- 9. Arsip Kepegawaian (E-Filing) -->
                            <div class="flex items-start justify-between p-5 rounded-2xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-fuchsia-200 dark:hover:border-fuchsia-800 transition-all shadow-2xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-fuchsia-100 dark:bg-fuchsia-950/60 flex items-center justify-center text-fuchsia-600 shrink-0 shadow-xs mt-0.5">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">Arsip Kepegawaian (E-Filing)</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Pegawai mengunggah dokumen pribadi (Ijazah, SK, KTP) untuk diverifikasi Admin.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.feature_arsip" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-fuchsia-600"></div>
                                </label>
                            </div>

                            <!-- 10. Penilaian Kinerja (KPI) -->
                            <div class="flex items-start justify-between p-5 rounded-2xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-fuchsia-200 dark:hover:border-fuchsia-800 transition-all shadow-2xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-fuchsia-100 dark:bg-fuchsia-950/60 flex items-center justify-center text-fuchsia-600 shrink-0 shadow-xs mt-0.5">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">Penilaian Kinerja (KPI)</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Evaluasi bulanan rapor kinerja guru dan karyawan oleh pimpinan.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.feature_kpi" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-fuchsia-600"></div>
                                </label>
                            </div>

                            <!-- 11. Administrasi Guru -->
                            <div class="flex items-start justify-between p-5 rounded-2xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-fuchsia-200 dark:hover:border-fuchsia-800 transition-all shadow-2xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-fuchsia-100 dark:bg-fuchsia-950/60 flex items-center justify-center text-fuchsia-600 shrink-0 shadow-xs mt-0.5">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">Administrasi Guru (Perangkat Ajar)</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Modul Ajar/RPP, Prota, Promes, ATP, KKTP &amp; telaah verifikasi Kepala Sekolah.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.feature_administrasi_guru" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-fuchsia-600"></div>
                                </label>
                            </div>

                            <!-- 12. Custom Habit per Divisi -->
                            <div class="flex items-start justify-between p-5 rounded-2xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-fuchsia-200 dark:hover:border-fuchsia-800 transition-all shadow-2xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-fuchsia-100 dark:bg-fuchsia-950/60 flex items-center justify-center text-fuchsia-600 shrink-0 shadow-xs mt-0.5">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.429 9.75L2.25 12l4.179 2.25m0-4.5l5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0l4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0l-5.571 3-5.571-3" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">Custom Habit per Divisi</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Tugaskan pembiasaan khusus yang hanya diikuti oleh anggota divisi tertentu.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.custom_habit_divisions_active" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-fuchsia-600"></div>
                                </label>
                            </div>

                        </div>

                        <!-- Tombol Simpan Tab Modul -->
                        <div class="mt-8 pt-5 border-t border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row items-center justify-between gap-3">
                            <span class="text-xs text-slate-400">Aktifkan modul yang dibutuhkan organisasi Anda lalu simpan perubahan.</span>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full sm:w-auto px-8 py-3.5 bg-fuchsia-600 hover:bg-fuchsia-700 text-white font-black text-xs rounded-2xl transition-all shadow-md hover:shadow-lg disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer"
                            >
                                <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>SIMPAN MODUL APLIKASI</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ──────────────────────────────────────────────────────────────
                     TAB 3: TAHUN AJARAN & AKADEMIK
                ────────────────────────────────────────────────────────────── -->
                <div v-show="activeTab === 'akademik'" class="space-y-6">
                    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 p-6 sm:p-8 space-y-6 shadow-xs">
                        
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-slate-100 dark:border-slate-700">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-linear-to-br from-indigo-500 to-blue-600 flex items-center justify-center text-white shadow-md shadow-indigo-500/20 shrink-0">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-lg font-black text-slate-800 dark:text-slate-100">Tahun Ajaran &amp; Pengarsipan Administrasi Guru</h2>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Atur tahun ajaran aktif, semester berjalan, dan arsipkan berkas guru agar berstatus hanya-baca (read-only).</p>
                                </div>
                            </div>

                            <Link
                                :href="route('admin.teacher-administrations.index')"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 transition-colors shrink-0"
                            >
                                <span>Lihat Rekap Administrasi Guru</span>
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                            </Link>
                        </div>

                        <!-- 2 Form Terpisah: 1) Periode Aktif & 2) Tambah Tahun -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <!-- Periode Aktif -->
                            <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-850/60 border border-slate-200/70 dark:border-slate-700/60 space-y-3.5">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">Periode Aktif Saat Ini</h3>
                                </div>

                                <div class="space-y-3">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Tahun Ajaran Aktif</label>
                                            <select
                                                v-model="activePeriodForm.year"
                                                class="w-full text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                                            >
                                                <option v-for="y in props.academicYearSummary?.years_list || []" :key="y" :value="y">{{ y }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Semester Aktif</label>
                                            <select
                                                v-model="activePeriodForm.semester"
                                                class="w-full text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                                            >
                                                <option value="ganjil">Semester Ganjil</option>
                                                <option value="genap">Semester Genap</option>
                                            </select>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        @click="handleSetActivePeriod"
                                        :disabled="activePeriodForm.processing"
                                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-slate-900 dark:bg-slate-100 hover:bg-slate-800 dark:hover:bg-white text-white dark:text-slate-900 text-xs font-bold transition-all shadow-xs cursor-pointer disabled:opacity-50"
                                    >
                                        <svg v-if="activePeriodForm.processing" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span>Terapkan Periode Aktif</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Tambah Tahun Ajaran Baru -->
                            <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-850/60 border border-slate-200/70 dark:border-slate-700/60 space-y-3.5">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">Tambah Tahun Ajaran Baru</h3>
                                </div>

                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                            Input Tahun Ajaran (Format: YYYY/YYYY)
                                        </label>
                                        <div class="flex gap-2">
                                            <input
                                                type="text"
                                                v-model="addYearForm.year"
                                                placeholder="Contoh: 2027/2028"
                                                maxlength="9"
                                                class="flex-1 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500"
                                            />
                                            <button
                                                type="button"
                                                @click="handleAddYear"
                                                :disabled="addYearForm.processing || !addYearForm.year"
                                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition-all shadow-xs cursor-pointer disabled:opacity-50 shrink-0"
                                            >
                                                <svg v-if="addYearForm.processing" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                                </svg>
                                                <span>Tambah</span>
                                            </button>
                                        </div>
                                        <div v-if="addYearForm.errors.year" class="text-rose-500 text-[11px] mt-1 font-semibold">
                                            {{ addYearForm.errors.year }}
                                        </div>
                                    </div>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                        Tahun yang ditambahkan akan otomatis muncul di seluruh dropdown filter dan form administrasi guru.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Tabel Kelola Tahun & Arsip -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                    Daftar Tahun Ajaran &amp; Status Pengarsipan Dokumen
                                </h3>
                                <span class="text-[11px] text-slate-400">
                                    Total: {{ (props.academicYearSummary?.years || []).length }} Tahun Ajaran
                                </span>
                            </div>

                            <div class="overflow-x-auto rounded-2xl border border-slate-200/80 dark:border-slate-700/80">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-50 dark:bg-slate-850/80 text-slate-600 dark:text-slate-300 font-bold border-b border-slate-200/80 dark:border-slate-700/80">
                                        <tr>
                                            <th class="py-3 px-4">Tahun Ajaran</th>
                                            <th class="py-3 px-4">Status Periode</th>
                                            <th class="py-3 px-4">Status Pengarsipan Guru</th>
                                            <th class="py-3 px-4">Dokumen Tersimpan</th>
                                            <th class="py-3 px-4 text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-750">
                                        <tr
                                            v-for="item in (props.academicYearSummary?.years || [])"
                                            :key="item.year"
                                            class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors"
                                        >
                                            <td class="py-3.5 px-4 font-black text-slate-800 dark:text-slate-100 text-sm">
                                                {{ item.year }}
                                            </td>

                                            <td class="py-3.5 px-4">
                                                <span
                                                    v-if="item.is_active"
                                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60"
                                                >
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                    ⭐ Aktif (Semester {{ props.academicYearSummary?.active_semester === 'genap' ? 'Genap' : 'Ganjil' }})
                                                </span>
                                                <span v-else class="text-slate-400 dark:text-slate-500 text-xs">
                                                    Tidak Aktif
                                                </span>
                                            </td>

                                            <td class="py-3.5 px-4">
                                                <span
                                                    v-if="item.is_archived"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60"
                                                    title="Guru hanya dapat melihat dan mengunduh berkas pada tahun ajaran ini."
                                                >
                                                    📦 Diarsipkan (Hanya-Baca)
                                                </span>
                                                <span
                                                    v-else
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50"
                                                >
                                                    🟢 Aktif Terbuka
                                                </span>
                                            </td>

                                            <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300 font-semibold">
                                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-700 text-xs">
                                                    {{ item.doc_count }} Berkas
                                                </span>
                                            </td>

                                            <td class="py-3.5 px-4 text-right">
                                                <div class="inline-flex items-center gap-1.5">
                                                    <button
                                                        v-if="!item.is_active"
                                                        type="button"
                                                        @click="handleToggleArchive(item)"
                                                        :class="[
                                                            'inline-flex items-center gap-1 px-3 py-1.5 rounded-xl font-bold text-[11px] transition-all cursor-pointer shadow-2xs',
                                                            item.is_archived
                                                                ? 'bg-slate-100 dark:bg-slate-700 hover:bg-emerald-50 text-slate-700 dark:text-slate-200 hover:text-emerald-700 border border-slate-200 dark:border-slate-600'
                                                                : 'bg-amber-500 hover:bg-amber-600 text-white'
                                                        ]"
                                                        :title="item.is_archived ? 'Buka kunci arsip' : 'Kunci dan arsipkan berkas tahun ajaran ini'"
                                                    >
                                                        <span>{{ item.is_archived ? '🔓 Buka Kunci' : '📦 Arsipkan' }}</span>
                                                    </button>

                                                    <button
                                                        v-if="item.doc_count > 0"
                                                        type="button"
                                                        @click="handleDownloadZip(item.year)"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 text-indigo-700 dark:text-indigo-300 font-bold text-[11px] border border-indigo-200/60 dark:border-indigo-800/60 transition-colors cursor-pointer"
                                                        title="Unduh seluruh dokumen guru pada tahun ini dalam 1 berkas ZIP"
                                                    >
                                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                        </svg>
                                                        <span>ZIP</span>
                                                    </button>

                                                    <button
                                                        v-if="!item.is_active && item.doc_count === 0"
                                                        type="button"
                                                        @click="handleDeleteYear(item.year)"
                                                        class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors cursor-pointer"
                                                        title="Hapus tahun ajaran"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ──────────────────────────────────────────────────────────────
                     TAB 4: PENGUMUMAN & TAMPILAN
                ────────────────────────────────────────────────────────────── -->
                <div v-show="activeTab === 'tampilan'" class="space-y-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 xl:gap-8">
                        
                        <!-- Kolom 1: Pengumuman & Popup Modal -->
                        <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 p-6 flex flex-col h-full shadow-xs">
                            <h2 class="text-lg font-black text-slate-800 dark:text-slate-100 mb-5 flex items-center gap-2">
                                <div class="w-8 h-8 rounded-xl bg-purple-50 dark:bg-purple-950/50 flex items-center justify-center text-purple-600">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                                    </svg>
                                </div>
                                <span>1. Teks Pengumuman &amp; Banner</span>
                            </h2>

                            <div class="space-y-6 flex-1">
                                <!-- Pengumuman Dashboard -->
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-1">Pengumuman Dashboard Pegawai</h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-2.5">Muncul sebagai kartu pengumuman di dashboard seluruh pengguna.</p>
                                    
                                    <div class="border rounded-xl overflow-hidden border-slate-200 dark:border-slate-700">
                                        <Editor
                                            v-model="form.announcement_text"
                                            tinymce-script-src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"
                                            :init="{
                                                height: 180,
                                                menubar: false,
                                                plugins: [
                                                    'advlist', 'autolink', 'lists', 'link', 'image', 'charmap',
                                                    'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                                                    'insertdatetime', 'media', 'table', 'preview', 'help', 'wordcount'
                                                ],
                                                toolbar: 'undo redo | bold italic | alignleft aligncenter alignright | bullist numlist | link | removeformat',
                                                content_style: 'body { font-family:Inter,Helvetica,Arial,sans-serif; font-size:13px }',
                                                promotion: false,
                                                branding: false,
                                                statusbar: false
                                            }"
                                        />
                                    </div>
                                    <div v-if="form.errors.announcement_text" class="text-red-500 text-xs mt-1">{{ form.errors.announcement_text }}</div>
                                </div>

                                <hr class="border-slate-100 dark:border-slate-700/60" />

                                <!-- Popup Modal Landing -->
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <div>
                                            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Popup Modal (Halaman Depan)</h3>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">Muncul sekali saat pengunjung pertama kali membuka aplikasi.</p>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input
                                                type="checkbox"
                                                v-model="form.popup_active"
                                                class="sr-only peer"
                                            />
                                            <div class="w-11 h-6 bg-slate-200 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-purple-600"></div>
                                        </label>
                                    </div>

                                    <div v-if="form.popup_active" class="mt-3">
                                        <div class="border rounded-xl overflow-hidden border-slate-200 dark:border-slate-700">
                                            <Editor
                                                v-model="form.popup_text"
                                                tinymce-script-src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"
                                                :init="{
                                                    height: 180,
                                                    menubar: false,
                                                    plugins: [
                                                        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap',
                                                        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                                                        'insertdatetime', 'media', 'table', 'preview', 'help', 'wordcount'
                                                    ],
                                                    toolbar: 'undo redo | bold italic | alignleft aligncenter alignright | bullist numlist | link | removeformat',
                                                    content_style: 'body { font-family:Inter,Helvetica,Arial,sans-serif; font-size:13px }',
                                                    promotion: false,
                                                    branding: false,
                                                    statusbar: false
                                                }"
                                            />
                                        </div>
                                        <div v-if="form.errors.popup_text" class="text-red-500 text-xs mt-1">{{ form.errors.popup_text }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Kolom 2: Media YouTube & Running Text -->
                        <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 p-6 flex flex-col h-full shadow-xs">
                            <h2 class="text-lg font-black text-slate-800 dark:text-slate-100 mb-5 flex items-center gap-2">
                                <div class="w-8 h-8 rounded-xl bg-purple-50 dark:bg-purple-950/50 flex items-center justify-center text-purple-600">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <span>2. Media &amp; Visual Landing</span>
                            </h2>

                            <div class="space-y-6 flex-1">
                                <!-- Video YouTube -->
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-1">Video Profil Youtube</h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-2.5">Tautan video Youtube yang diputar di halaman utama (landing page).</p>
                                    
                                    <div>
                                        <label for="youtube_link" class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Link / URL Youtube</label>
                                        <input
                                            id="youtube_link"
                                            type="text"
                                            v-model="form.youtube_link"
                                            class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-xs focus:border-purple-500 focus:ring focus:ring-purple-200 text-xs text-slate-800 dark:text-slate-200 font-mono transition-colors"
                                            placeholder="Contoh: https://www.youtube.com/watch?v=dQw4w9WgXcQ"
                                        />
                                        <div v-if="form.errors.youtube_link" class="text-red-500 text-xs mt-1">{{ form.errors.youtube_link }}</div>
                                    </div>
                                </div>

                                <hr class="border-slate-100 dark:border-slate-700/60" />

                                <!-- Running Text -->
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-1">Running Text (Teks Berjalan)</h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-2.5">Pesan berjalan (marquee) di bagian header paling atas halaman utama.</p>
                                    
                                    <div>
                                        <label for="running_text" class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Isi Teks Pesan Berjalan</label>
                                        <textarea
                                            id="running_text"
                                            v-model="form.running_text"
                                            rows="5"
                                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-xs focus:border-purple-500 focus:ring focus:ring-purple-200 text-xs text-slate-800 dark:text-slate-200 transition-colors"
                                            placeholder="Kosongkan jika tidak ingin menampilkan teks berjalan..."
                                        ></textarea>
                                        <div v-if="form.errors.running_text" class="text-red-500 text-xs mt-1">{{ form.errors.running_text }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Tombol Simpan Tampilan -->
                    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 p-6 flex flex-col sm:flex-row items-center justify-between gap-3 shadow-xs">
                        <span class="text-xs text-slate-400">Pengaturan pengumuman dan media landing page akan langsung aktif di browser.</span>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full sm:w-auto px-8 py-3.5 bg-purple-600 hover:bg-purple-700 text-white font-black text-xs rounded-2xl transition-all shadow-md hover:shadow-lg disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>SIMPAN PENGATURAN TAMPILAN</span>
                        </button>
                    </div>
                </div>

                <!-- ──────────────────────────────────────────────────────────────
                     TAB 5: PUSH NOTIFIKASI PWA
                ────────────────────────────────────────────────────────────── -->
                <div v-show="activeTab === 'notifikasi'" class="space-y-6">
                    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 p-6 sm:p-7 space-y-6 shadow-xs">
                        
                        <!-- Header & Switch Master Notifikasi -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-slate-100 dark:border-slate-700">
                            <div class="flex items-start gap-3.5">
                                <div class="w-10 h-10 rounded-2xl bg-amber-50 dark:bg-amber-950/60 flex items-center justify-center text-amber-600 shrink-0 shadow-xs mt-0.5">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Layanan Push Notifications (PWA Web Push)</h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        Kirim notifikasi pengingat ibadah &amp; presensi langsung ke layar HP / laptop pengguna meskipun browser tertutup.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 self-start sm:self-center">
                                <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                                    {{ form.push_notifications_active ? 'Fitur Aktif' : 'Fitur Nonaktif' }}
                                </span>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.push_notifications_active"
                                        class="sr-only peer"
                                    />
                                    <div class="w-11 h-6 bg-slate-200 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-amber-600"></div>
                                </label>
                            </div>
                        </div>

                        <!-- Status & Stats Card -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-850/60 border border-slate-200/70 dark:border-slate-700/60">
                                <span class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400">Total Langganan Sistem</span>
                                <span class="text-lg font-black text-slate-800 dark:text-slate-100 mt-0.5 block">
                                    {{ pushStatus.total_system_subscriptions || 0 }} Perangkat
                                </span>
                            </div>
                            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-850/60 border border-slate-200/70 dark:border-slate-700/60">
                                <span class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400">Perangkat Akun Anda</span>
                                <span class="text-lg font-black text-slate-800 dark:text-slate-100 mt-0.5 block">
                                    {{ pushStatus.user_subscriptions_count || 0 }} Terdaftar
                                </span>
                            </div>
                            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-850/60 border border-slate-200/70 dark:border-slate-700/60">
                                <span class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400">Status Perangkat Ini</span>
                                <span class="inline-flex items-center gap-1.5 text-xs font-bold mt-1.5" :class="isPushSubscribed ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400'">
                                    <span class="w-2 h-2 rounded-full" :class="isPushSubscribed ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"></span>
                                    {{ isPushSubscribed ? 'Terdaftar & Aktif' : 'Belum Terdaftar' }}
                                </span>
                            </div>
                        </div>

                        <!-- Kontrol Perangkat Ini -->
                        <div class="p-4 rounded-2xl bg-amber-50/40 dark:bg-amber-950/20 border border-amber-200/70 dark:border-amber-800/40 space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-amber-900 dark:text-amber-200">
                                        Pendaftaran Perangkat Browser Ini
                                    </h4>
                                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
                                        Daftarkan browser Anda saat ini agar dapat menerima tes push notification secara langsung.
                                    </p>
                                </div>
                                <div class="flex items-center flex-wrap gap-2">
                                    <button
                                        v-if="!isPushSubscribed"
                                        type="button"
                                        @click="handleSubscribeThisDevice"
                                        :disabled="isSubscribingPush || !isPushSupported"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition-all shadow-xs cursor-pointer disabled:opacity-50"
                                    >
                                        <svg v-if="isSubscribingPush" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                        </svg>
                                        <span>Aktifkan Notifikasi di Perangkat Ini</span>
                                    </button>

                                    <template v-else>
                                        <button
                                            type="button"
                                            @click="handleSendTestPush"
                                            :disabled="pushTestLoading"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all shadow-xs cursor-pointer disabled:opacity-50"
                                        >
                                            <svg v-if="pushTestLoading" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                            </svg>
                                            <span>Kirim Tes Push ke Perangkat Ini</span>
                                        </button>

                                        <button
                                            type="button"
                                            @click="handleUnsubscribeThisDevice"
                                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white dark:bg-slate-800 hover:bg-slate-100 text-slate-700 dark:text-slate-300 text-xs font-bold border border-slate-200 dark:border-slate-700 transition-all cursor-pointer"
                                        >
                                            <span>Matikan di Perangkat Ini</span>
                                        </button>
                                    </template>

                                    <!-- Tombol Siaran Notifikasi (Broadcast) -->
                                    <button
                                        type="button"
                                        @click="handleOpenBroadcastModal"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all shadow-xs cursor-pointer ml-auto"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                        </svg>
                                        <span>Siarkan Pesan (Broadcast)</span>
                                    </button>
                                </div>
                            </div>

                            <div v-if="pushTestMessage" class="p-2.5 rounded-xl text-xs font-bold flex items-center gap-2" :class="pushTestStatus === 'success' ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800'">
                                <span>{{ pushTestMessage }}</span>
                            </div>
                        </div>

                        <!-- Tombol Simpan Notifikasi -->
                        <div class="pt-5 border-t border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row items-center justify-between gap-3">
                            <span class="text-xs text-slate-400">Pastikan konfigurasi VAPID dan sakelar aktif sebelum menyimpan.</span>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full sm:w-auto px-8 py-3.5 bg-amber-600 hover:bg-amber-700 text-white font-black text-xs rounded-2xl transition-all shadow-md hover:shadow-lg disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer"
                            >
                                <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>SIMPAN PENGATURAN NOTIFIKASI</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ──────────────────────────────────────────────────────────────
                     TAB 6: SISTEM & PEMELIHARAAN (MAINTENANCE)
                ────────────────────────────────────────────────────────────── -->
                <div v-show="activeTab === 'pemeliharaan'" class="space-y-6">
                    
                    <!-- Preferensi UI & Sistem -->
                    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 p-6 sm:p-8 shadow-xs">
                        <div class="pb-6 border-b border-slate-100 dark:border-slate-700">
                            <h3 class="text-xl font-black text-slate-800 dark:text-slate-100 flex items-center gap-2.5">
                                <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center text-indigo-600">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <span>Preferensi UI &amp; Perilaku Sistem</span>
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                Konfigurasi mode tampilan gelap, reward gamifikasi, dan peringatan evaluasi capaian pegawai.
                            </p>
                        </div>

                        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-5">
                            <!-- 1. Dark Mode -->
                            <div class="flex items-start justify-between p-5 rounded-2xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all shadow-2xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-indigo-100 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 shrink-0 shadow-xs mt-0.5">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">Opsi Tema Gelap</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Tombol tema gelap di navigasi pengguna.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.dark_mode_active" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-indigo-600"></div>
                                </label>
                            </div>

                            <!-- 2. Gamifikasi -->
                            <div class="flex items-start justify-between p-5 rounded-2xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all shadow-2xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-indigo-100 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 shrink-0 shadow-xs mt-0.5">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.504-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.982-3.172M12 3a4.5 4.5 0 00-4.5 4.5c0 1.637.882 3.064 2.196 3.844m4.608 0A4.5 4.5 0 0016.5 7.5 4.5 4.5 0 0012 3z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">Sistem Gamifikasi</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Reward lencana digital otomatis perolehan skor streak.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.gamification_active" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-indigo-600"></div>
                                </label>
                            </div>

                            <!-- 3. Auto-Warning -->
                            <div class="flex items-start justify-between p-5 rounded-2xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all shadow-2xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-indigo-100 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 shrink-0 shadow-xs mt-0.5">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">Auto-Warning Capaian</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Peringatan otomatis bagi pengguna yang capaian habitnya di bawah target.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.auto_warning_active" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-indigo-600"></div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Mode Pemeliharaan (Maintenance Mode) -->
                    <div
                        :class="[
                            'overflow-hidden rounded-3xl border transition-all duration-300 p-6 sm:p-8 shadow-xs',
                            form.maintenance_mode
                                ? 'bg-amber-50/40 dark:bg-amber-950/20 border-amber-300 dark:border-amber-700/60 ring-2 ring-amber-400/20'
                                : 'bg-white dark:bg-slate-800 border-slate-100 dark:border-slate-700'
                        ]"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-200/70 dark:border-slate-700">
                            <div>
                                <div class="flex items-center gap-2.5">
                                    <div :class="['w-10 h-10 rounded-2xl flex items-center justify-center shadow-md shadow-amber-500/20', form.maintenance_mode ? 'bg-amber-500 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-500']">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg sm:text-xl font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                            <span>Mode Pemeliharaan (Maintenance Mode)</span>
                                            <span
                                                :class="[
                                                    'text-[10px] font-black uppercase px-2 py-0.5 rounded-full border',
                                                    form.maintenance_mode
                                                        ? 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-900/50 dark:text-amber-300 dark:border-amber-700'
                                                        : 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600'
                                                ]"
                                            >
                                                {{ form.maintenance_mode ? '● Aktif' : '○ Nonaktif' }}
                                            </span>
                                        </h3>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                            Alihkan pengunjung &amp; user biasa ke halaman pemeliharaan. Superadmin tetap memiliki akses penuh.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 self-start sm:self-center">
                                <a
                                    :href="route('maintenance')"
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white dark:bg-slate-700 hover:bg-slate-50 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 text-xs font-bold transition-all shadow-xs"
                                >
                                    <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <span>Pratinjau Halaman</span>
                                </a>

                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.maintenance_mode"
                                        @change="handleInstantToggle"
                                        :disabled="isTogglingInstant"
                                        class="sr-only peer"
                                    />
                                    <div class="w-13 h-7 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[3px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all dark:border-slate-600 peer-checked:bg-amber-500"></div>
                                </label>
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                            <div>
                                <label for="maintenance_title" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                    Judul Halaman Pemeliharaan
                                </label>
                                <input
                                    id="maintenance_title"
                                    type="text"
                                    v-model="form.maintenance_title"
                                    class="w-full px-4 py-2.5 rounded-xl border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-sm shadow-xs focus:border-amber-500 focus:ring focus:ring-amber-200"
                                    placeholder="Contoh: Sistem Sedang Dalam Pemeliharaan"
                                />
                                <div v-if="form.errors.maintenance_title" class="text-xs text-red-500 mt-1">{{ form.errors.maintenance_title }}</div>
                            </div>

                            <div>
                                <label for="maintenance_end_time" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                    Estimasi Selesai (Countdown / Keterangan Waktu)
                                </label>
                                <input
                                    id="maintenance_end_time"
                                    type="text"
                                    v-model="form.maintenance_end_time"
                                    class="w-full px-4 py-2.5 rounded-xl border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-sm shadow-xs focus:border-amber-500 focus:ring focus:ring-amber-200"
                                    placeholder="Contoh: 2026-09-04 15:00 atau 14:00 WIB"
                                />
                                <p class="text-[11px] text-slate-400 mt-1">Gunakan format tanggal (YYYY-MM-DD HH:mm) jika ingin mengaktifkan timer hitung mundur otomatis.</p>
                                <div v-if="form.errors.maintenance_end_time" class="text-xs text-red-500 mt-1">{{ form.errors.maintenance_end_time }}</div>
                            </div>

                            <div class="col-span-1 md:col-span-2">
                                <label for="maintenance_message" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                    Pesan / Penjelasan Pemeliharaan
                                </label>
                                <textarea
                                    id="maintenance_message"
                                    v-model="form.maintenance_message"
                                    rows="3"
                                    class="w-full px-4 py-2.5 rounded-xl border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-sm shadow-xs focus:border-amber-500 focus:ring focus:ring-amber-200"
                                    placeholder="Tuliskan pesan penjelasan kepada pengguna..."
                                ></textarea>
                                <div v-if="form.errors.maintenance_message" class="text-xs text-red-500 mt-1">{{ form.errors.maintenance_message }}</div>
                            </div>

                            <div class="col-span-1 md:col-span-2 pt-4 border-t border-slate-200/60 dark:border-slate-700/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    💡 <em>Sakelar di atas menyimpan status pemeliharaan secara instan. Klik tombol di samping untuk menyimpan perubahan teks judul &amp; pesan.</em>
                                </p>
                                <button
                                    type="button"
                                    @click="handleInstantToggle"
                                    :disabled="isTogglingInstant"
                                    class="px-5 py-2.5 bg-amber-600 hover:bg-amber-500 text-white rounded-xl text-xs font-bold transition-all shadow-md flex items-center justify-center gap-2 cursor-pointer self-end sm:self-center"
                                >
                                    <svg v-if="isTogglingInstant" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>{{ isTogglingInstant ? 'Menyimpan...' : 'Simpan Teks Pemeliharaan' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Pembersihan Storage Foto Presensi -->
                    <div v-if="isSuperAdmin" class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 p-6 sm:p-8 shadow-xs">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-700 pb-5 mb-6">
                            <div>
                                <div class="flex items-center gap-2.5">
                                    <div class="w-10 h-10 rounded-2xl bg-rose-50 dark:bg-rose-950/50 flex items-center justify-center text-rose-600 shadow-xs">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg sm:text-xl font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                            <span>Pembersihan File Foto Presensi (Storage Cleansing)</span>
                                            <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 dark:bg-rose-950/80 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                                Storage Disk
                                            </span>
                                        </h3>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                            Hapus file fisik foto selfie presensi lampau secara aman untuk menghemat kapasitas storage disk server.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 bg-slate-50 dark:bg-slate-900/60 px-4 py-2.5 rounded-2xl border border-slate-200 dark:border-slate-700/80 shrink-0">
                                <div>
                                    <span class="block text-[10px] font-bold text-slate-400 uppercase">Foto di Server</span>
                                    <span class="text-sm font-black text-slate-800 dark:text-slate-100">{{ photoStats?.total_files || 0 }} File</span>
                                </div>
                                <div class="w-px h-7 bg-slate-200 dark:bg-slate-700" />
                                <div>
                                    <span class="block text-[10px] font-bold text-slate-400 uppercase">Kapasitas Terpakai</span>
                                    <span class="text-sm font-black text-emerald-600 dark:text-emerald-400">{{ photoStats?.total_size_formatted || '0 KB' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                            <div class="lg:col-span-8 space-y-3">
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                                    Pilih Batas Waktu Pembersihan (1 s/d 6 Bulan Lalu):
                                </label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <select 
                                            v-model="cleanForm.months"
                                            class="w-full text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 bg-card-subtle px-3 py-2.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-rose-500 cursor-pointer"
                                        >
                                            <option v-for="opt in cleaningOptions" :key="opt.months" :value="opt.months">
                                                {{ opt.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="p-3 rounded-xl bg-amber-50/70 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-xs text-amber-900 dark:text-amber-200">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold">Batas Tanggal:</span>
                                            <span class="font-mono font-bold">{{ selectedCleaningOption?.formatted_date }}</span>
                                        </div>
                                        <div class="flex items-center justify-between mt-1 text-[11px] text-amber-700 dark:text-amber-300">
                                            <span>Foto yang akan dihapus:</span>
                                            <span class="font-black">{{ selectedCleaningOption?.photo_count || 0 }} data foto</span>
                                        </div>
                                    </div>
                                </div>

                                <p class="text-[11px] text-slate-400 leading-relaxed">
                                    🛡️ <strong>Catatan Keamanan:</strong> Pembersihan ini hanya menghapus file fisik foto selfie di folder server (<code class="text-[10px] bg-slate-100 dark:bg-slate-900 px-1 py-0.5 rounded">storage/attendances/</code>). Data riwayat presensi jam masuk, jam pulang, jarak GPS, dan status kehadiran pegawai <strong>tetap aman tersimpan utuh di database</strong>.
                                </p>
                            </div>

                            <div class="lg:col-span-4 flex flex-col items-stretch sm:items-end justify-center">
                                <button 
                                    type="button"
                                    @click="handleCleanPhotos"
                                    :disabled="cleanForm.processing || (selectedCleaningOption?.photo_count === 0 && photoStats?.total_files === 0)"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-md hover:shadow-rose-600/20 active:scale-95 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <svg v-if="cleanForm.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    <span>Bersihkan File Foto Terpilih</span>
                                </button>
                                <span v-if="selectedCleaningOption?.photo_count === 0 && photoStats?.total_files === 0" class="text-[10px] text-slate-400 mt-1.5 text-center sm:text-right">
                                    Tidak ada file foto pada periode ini.
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Simpan Tab Pemeliharaan -->
                    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 p-6 flex flex-col sm:flex-row items-center justify-between gap-3 shadow-xs">
                        <span class="text-xs text-slate-400">Pengaturan preferensi sistem akan langsung berlaku untuk seluruh pengguna.</span>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full sm:w-auto px-8 py-3.5 bg-rose-600 hover:bg-rose-700 text-white font-black text-xs rounded-2xl transition-all shadow-md hover:shadow-lg disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>SIMPAN PENGATURAN SISTEM</span>
                        </button>
                    </div>
                </div>

            </form>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════
             MODAL SIARAN NOTIFIKASI PUSH (BROADCAST)
        ══════════════════════════════════════════════════════════════════ -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="showBroadcastModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs"
            >
                <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 dark:border-slate-800 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 flex items-center justify-center shadow-xs">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100">Siarkan Pesan Notifikasi Push</h3>
                                <p class="text-[11px] text-slate-400">Kirim pesan langsung ke browser pegawai</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="showBroadcastModal = false"
                            class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-3.5">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Target Penerima</label>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    type="button"
                                    @click="broadcastForm.target = 'all'"
                                    :class="['py-2 px-3 rounded-xl text-xs font-bold border transition-all cursor-pointer', broadcastForm.target === 'all' ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700']"
                                >
                                    Semua Pegawai
                                </button>
                                <button
                                    type="button"
                                    @click="broadcastForm.target = 'division'"
                                    :class="['py-2 px-3 rounded-xl text-xs font-bold border transition-all cursor-pointer', broadcastForm.target === 'division' ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700']"
                                >
                                    Per Divisi
                                </button>
                                <button
                                    type="button"
                                    @click="broadcastForm.target = 'user'"
                                    :class="['py-2 px-3 rounded-xl text-xs font-bold border transition-all cursor-pointer', broadcastForm.target === 'user' ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700']"
                                >
                                    Pribadi
                                </button>
                            </div>
                        </div>

                        <!-- Dropdown Divisi -->
                        <div v-if="broadcastForm.target === 'division'">
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Pilih Divisi</label>
                            <select
                                v-model="broadcastForm.division"
                                class="w-full text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="">-- Pilih Divisi Sasaran --</option>
                                <option v-for="d in props.divisions" :key="d" :value="d">{{ d }}</option>
                            </select>
                        </div>

                        <!-- Dropdown Pegawai -->
                        <div v-if="broadcastForm.target === 'user'">
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Pilih Pegawai</label>
                            <select
                                v-model="broadcastForm.user_id"
                                class="w-full text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="">-- Pilih Pegawai Sasaran --</option>
                                <option v-for="emp in props.employees" :key="emp.id" :value="emp.id">{{ emp.name }} ({{ emp.divisi || 'Tanpa Divisi' }})</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Judul Notifikasi</label>
                            <input
                                type="text"
                                v-model="broadcastForm.title"
                                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                                placeholder="Contoh: Pengingat Mutaba'ah & Presensi 🔔"
                            />
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Isi Pesan Notifikasi</label>
                            <textarea
                                v-model="broadcastForm.body"
                                rows="3"
                                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                                placeholder="Tuliskan pesan notifikasi push..."
                            ></textarea>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Tautan Saat Diklik (URL)</label>
                            <input
                                type="text"
                                v-model="broadcastForm.url"
                                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-800 dark:text-slate-100 font-mono focus:ring-2 focus:ring-indigo-500"
                                placeholder="/dashboard"
                            />
                        </div>

                        <div v-if="broadcastMessage" class="p-2.5 rounded-xl text-xs font-bold" :class="broadcastStatus === 'success' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300'">
                            {{ broadcastMessage }}
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            @click="showBroadcastModal = false"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            @click="handleSendBroadcast"
                            :disabled="broadcastLoading"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all shadow-xs cursor-pointer disabled:opacity-50"
                        >
                            <svg v-if="broadcastLoading" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                            <span>{{ broadcastLoading ? 'Mengirim...' : 'Kirim Siaran Notifikasi' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- ══════════════════════════════════════════════════════════════════
             MOBILE FLOATING ACTION BUTTON (FAB) FOR TAB SELECTOR
        ══════════════════════════════════════════════════════════════════ -->
        <Transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0 scale-75 translate-y-4"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-75 translate-y-4"
        >
            <button
                v-if="!isFullscreenMap"
                @click="showMobileTabMenu = true"
                type="button"
                class="md:hidden fixed bottom-6 right-6 z-40 bg-emerald-600 hover:bg-emerald-700 active:scale-90 text-white p-3.5 rounded-full shadow-[0_8px_30px_rgb(0,0,0,0.22)] hover:shadow-[0_8px_30px_rgba(52,211,153,0.35)] transition-all hover:-translate-y-1 flex items-center justify-center focus:outline-none focus:ring-4 focus:ring-emerald-400/40 cursor-pointer"
                title="Pilih Menu Pengaturan"
                aria-label="Pilih Menu Pengaturan"
            >
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                </svg>
            </button>
        </Transition>

        <!-- ══════════════════════════════════════════════════════════════════
             MOBILE TAB SWITCHER BOTTOM SHEET MODAL
        ══════════════════════════════════════════════════════════════════ -->
        <Transition
            enter-active-class="transition-opacity ease-out duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div 
                v-if="showMobileTabMenu && !isFullscreenMap" 
                class="fixed inset-0 z-50 flex items-end md:hidden justify-center p-0"
            >
                <!-- Backdrop -->
                <div 
                    class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" 
                    @click="showMobileTabMenu = false"
                />

                <!-- Panel Bottom Sheet -->
                <div 
                    class="relative z-10 w-full bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 rounded-t-3xl shadow-2xl border-t border-slate-200 dark:border-slate-700 overflow-hidden flex flex-col max-h-[85vh] transition-all transform animate-in slide-in-from-bottom duration-300"
                >
                    <div class="pt-3 pb-1 flex justify-center">
                        <div class="w-12 h-1.5 rounded-full bg-slate-300 dark:bg-slate-600" />
                    </div>

                    <!-- Header Modal -->
                    <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/80">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center shadow-xs">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100">Menu Pengaturan Sistem</h3>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">Pilih fungsi pengaturan yang ingin diubah</p>
                            </div>
                        </div>
                        <button 
                            type="button"
                            @click="showMobileTabMenu = false" 
                            class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700/60 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-500 hover:text-slate-700 dark:text-slate-300 flex items-center justify-center transition-colors cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Tab Options List -->
                    <div class="p-4 space-y-2.5 overflow-y-auto max-h-[calc(85vh-100px)]">
                        <button
                            v-for="tab in tabOptions"
                            :key="tab.id"
                            type="button"
                            @click="selectMobileTab(tab.id)"
                            :class="[
                                'w-full text-left p-3.5 rounded-2xl border transition-all flex items-center justify-between gap-3 cursor-pointer',
                                activeTab === tab.id
                                    ? tab.activeRing + ' shadow-sm'
                                    : 'border-slate-200/80 dark:border-slate-700/80 hover:bg-slate-50 dark:hover:bg-slate-750 bg-white dark:bg-slate-800'
                            ]"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <div :class="['w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-colors shadow-2xs', activeTab === tab.id ? tab.iconBg : tab.iconInactiveBg]">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="tab.iconPath" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sm text-slate-800 dark:text-slate-100">
                                            {{ tab.title }}
                                        </span>
                                        <span :class="['text-[10px] px-2 py-0.5 rounded-full font-bold', tab.badgeClass]">
                                            {{ tab.badge }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-1">
                                        {{ tab.description }}
                                    </p>
                                </div>
                            </div>

                            <div class="shrink-0 flex items-center">
                                <div v-if="activeTab === tab.id" class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center shadow-xs">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <svg v-else class="w-4 h-4 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </button>
                    </div>

                    <div class="p-3 bg-slate-50 dark:bg-slate-850 border-t border-slate-100 dark:border-slate-800 text-center text-[11px] text-slate-400">
                        Klik salah satu menu untuk berpindah tab pengaturan
                    </div>
                </div>
            </div>
        </Transition>

    </AuthenticatedLayout>
</template>
