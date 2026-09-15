<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { Head, useForm, router, Link, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { usePWA } from '@/Composables/usePWA'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({})
    },
    photoStats: {
        type: Object,
        default: () => ({ total_files: 0, total_bytes: 0, total_size_formatted: '0 KB' })
    },
    cleaningOptions: {
        type: Array,
        default: () => []
    }
})

// ─── PEMBERSIHAN FILE FOTO PRESENSI (CLEANSING STORAGE) ──────────────────────
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
        `Anda akan menghapus file foto presensi (dinas luar) s.d. tanggal ${dateText} (${countText}).\n\n` +
        `• File fisik gambar akan dihapus dari server untuk mengosongkan storage.\n` +
        `• Catatan riwayat jam masuk, jam pulang, dan status pegawai tetap aman tersimpan di database.\n\n` +
        `Apakah Anda yakin ingin melanjutkan pembersihan?`
    )

    if (!confirmed) return

    cleanForm.post(route('admin.settings.clean-photos'), {
        preserveScroll: true,
    })
}

// Tab yang sedang aktif: 'presensi' | 'hr' | 'lanjutan' | 'maintenance'
const activeTab = ref('presensi')

// State fullscreen peta penitikan koordinat
const isFullscreenMap = ref(false)

const isTogglingInstant = ref(false)

const form = useForm({
    gamification_active: props.settings.gamification_active === '1' || props.settings.gamification_active === 'true',
    push_notifications_active: props.settings.push_notifications_active === '1' || props.settings.push_notifications_active === 'true',
    dark_mode_active: props.settings.dark_mode_active === '1' || props.settings.dark_mode_active === 'true',
    auto_warning_active: props.settings.auto_warning_active === '1' || props.settings.auto_warning_active === 'true',
    custom_habit_divisions_active: props.settings.custom_habit_divisions_active === '1' || props.settings.custom_habit_divisions_active === 'true',
    feature_badges: props.settings.feature_badges === '1' || props.settings.feature_badges === 'true',
    feature_divisi: props.settings.feature_divisi === '1' || props.settings.feature_divisi === 'true',
    feature_cuti: props.settings.feature_cuti === '1' || props.settings.feature_cuti === 'true',
    feature_idcard: props.settings.feature_idcard === '1' || props.settings.feature_idcard === 'true',
    feature_notes: props.settings.feature_notes === '1' || props.settings.feature_notes === 'true',
    maintenance_mode: props.settings.maintenance_mode === '1' || props.settings.maintenance_mode === 'true',
    maintenance_title: props.settings.maintenance_title || 'Sistem Sedang Dalam Pemeliharaan',
    maintenance_message: props.settings.maintenance_message || 'Kami sedang melakukan pemeliharaan rutin dan peningkatan performa sistem habit tracker. Mohon maaf atas ketidaknyamanan Anda. Sistem akan segera kembali normal.',
    maintenance_end_time: props.settings.maintenance_end_time || '',

    // Pengaturan Presensi GPS & Jam Kerja
    feature_presensi: props.settings.feature_presensi === '1' || props.settings.feature_presensi === true || props.settings.feature_presensi === 'true',
    presensi_latitude: props.settings.presensi_latitude || '-7.7956',
    presensi_longitude: props.settings.presensi_longitude || '110.3695',
    presensi_radius_meters: Number(props.settings.presensi_radius_meters) || 100,
    presensi_work_start: props.settings.presensi_work_start || '07:00',
    presensi_late_tolerance: props.settings.presensi_late_tolerance || 15,
    presensi_work_end: props.settings.presensi_work_end || '15:00',
})

// ─── MOBILE FLOATING TAB MENU ───────────────────────────────────────────────
const showMobileTabMenu = ref(false)

const tabOptions = computed(() => [
    {
        id: 'presensi',
        title: 'Presensi GPS & Jam Kerja',
        shortTitle: 'Presensi GPS',
        description: 'Pusat koordinat, radius geofencing & jam kerja',
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
        id: 'hr',
        title: 'Fitur HR (Pegawai)',
        shortTitle: 'Fitur HR',
        description: 'Badge, divisi, pengajuan cuti, ID card & catatan',
        badge: '5 Fitur',
        badgeClass: 'bg-fuchsia-100 text-fuchsia-800 dark:bg-fuchsia-950/80 dark:text-fuchsia-300',
        activeRing: 'border-fuchsia-500 bg-fuchsia-50/80 dark:bg-fuchsia-950/50 text-fuchsia-900 dark:text-fuchsia-200 ring-2 ring-fuchsia-500/20',
        iconBg: 'bg-fuchsia-600 text-white',
        iconInactiveBg: 'bg-fuchsia-50 dark:bg-fuchsia-950/60 text-fuchsia-600 dark:text-fuchsia-400',
        iconPath: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'
    },
    {
        id: 'lanjutan',
        title: 'Fitur Lanjutan',
        shortTitle: 'Fitur Lanjutan',
        description: 'Gamifikasi, push notifikasi, dark mode & kebiasaan',
        badge: '5 Opsi',
        badgeClass: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/80 dark:text-indigo-300',
        activeRing: 'border-indigo-500 bg-indigo-50/80 dark:bg-indigo-950/50 text-indigo-900 dark:text-indigo-200 ring-2 ring-indigo-500/20',
        iconBg: 'bg-indigo-600 text-white',
        iconInactiveBg: 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400',
        iconPath: 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z'
    },
    {
        id: 'maintenance',
        title: 'Pemeliharaan Sistem',
        shortTitle: 'Pemeliharaan',
        description: 'Mode pemeliharaan & pembersihan penyimpanan foto',
        badge: form.maintenance_mode ? '● Aktif' : '○ Nonaktif',
        badgeClass: form.maintenance_mode 
            ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300' 
            : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400',
        activeRing: 'border-amber-500 bg-amber-50/80 dark:bg-amber-950/50 text-amber-900 dark:text-amber-200 ring-2 ring-amber-500/20',
        iconBg: 'bg-amber-500 text-white',
        iconInactiveBg: 'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400',
        iconPath: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'
    }
])

const currentTabInfo = computed(() => {
    return tabOptions.value.find(t => t.id === activeTab.value) || tabOptions.value[0]
})

const selectMobileTab = (tabId) => {
    activeTab.value = tabId
    showMobileTabMenu.value = false
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

// ─── LEAFLET MINI MAP PRESENSI ──────────────────────────────────────────────
let hrMapInstance = null
let hrMapMarker = null
let hrMapCircle = null

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

// Ketika tab berganti ke presensi, refresh ukuran peta agar tidak blank
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

// ─── PWA WEB PUSH NOTIFICATIONS ─────────────────────────────────────────────
const {
    isPushSupported,
    isPushSubscribed,
    pushPermission,
    isSubscribingPush,
    subscribeToPush,
    unsubscribeFromPush,
    sendTestPush,
    checkPushSubscription,
} = usePWA()

const pushStatus = ref({
    vapid_configured: true,
    total_system_subscriptions: 0,
    user_subscriptions_count: 0,
    loading: false,
})

const pushTestLoading = ref(false)
const pushTestMessage = ref('')
const pushTestStatus = ref('') // 'success' | 'error'

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
        const page = usePage()
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

const isDetectingLocation = ref(false)
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

const submit = () => {
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Success notification
        }
    })
}
</script>

<template>
    <Head title="Pengaturan HR & Fitur Lanjutan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="font-black text-2xl text-slate-800 dark:text-slate-100 leading-tight">Pengaturan HR &amp; Fitur Lanjutan</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Pusat kontrol fitur presensi GPS, manajemen pegawai, gamifikasi, dan pemeliharaan sistem.</p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300 border border-purple-200 dark:border-purple-800 self-start sm:self-center shadow-2xs">
                    👑 Khusus Superadmin
                </span>
            </div>
        </template>

        <!-- Container Lebar Penuh (W-Full) untuk Memaksimalkan Penggunaan Halaman Layar Lebar -->
        <div class="w-full py-6 px-4 sm:px-6 lg:px-8 space-y-6">
            
            <!-- Alert Flash Success -->
            <div v-if="$page.props.flash?.success" class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 flex items-center gap-3 shadow-xs">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-bold text-sm">{{ $page.props.flash.success }}</span>
            </div>

            <!-- ══════════════════════════════════════════════════════════════════
                 TAB NAVIGATION BAR (Desktop & Mobile)
            ══════════════════════════════════════════════════════════════════ -->
            <!-- Mobile Active Tab Quick Banner (Hidden on Desktop) -->
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
                    <span>Ganti Tab</span>
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </div>

            <!-- Desktop Tab Bar (Hidden on Mobile) -->
            <div class="hidden md:flex items-center gap-2 p-1.5 bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 shadow-xs overflow-x-auto">
                <!-- Tab 1: Presensi GPS & Jam Kerja -->
                <button
                    type="button"
                    @click="activeTab = 'presensi'"
                    :class="[
                        'flex items-center gap-2.5 px-5 py-3 rounded-xl font-bold text-xs transition-all duration-200 shrink-0 cursor-pointer',
                        activeTab === 'presensi'
                            ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 shadow-xs ring-1 ring-emerald-300 dark:ring-emerald-700'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50'
                    ]"
                >
                    <div :class="['w-7 h-7 rounded-lg flex items-center justify-center transition-colors', activeTab === 'presensi' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-700 text-slate-500']">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <span class="text-sm">Presensi GPS &amp; Jam Kerja</span>
                    <span
                        :class="[
                            'text-[10px] px-2 py-0.5 rounded-full font-bold transition-colors',
                            form.feature_presensi
                                ? 'bg-emerald-200/70 text-emerald-900 dark:bg-emerald-900 dark:text-emerald-200'
                                : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-400'
                        ]"
                    >
                        {{ form.feature_presensi ? '● Aktif' : '○ Nonaktif' }}
                    </span>
                </button>

                <!-- Tab 2: Fitur HR (Manajemen Pegawai) -->
                <button
                    type="button"
                    @click="activeTab = 'hr'"
                    :class="[
                        'flex items-center gap-2.5 px-5 py-3 rounded-xl font-bold text-xs transition-all duration-200 shrink-0 cursor-pointer',
                        activeTab === 'hr'
                            ? 'bg-fuchsia-50 dark:bg-fuchsia-950/60 text-fuchsia-800 dark:text-fuchsia-300 shadow-xs ring-1 ring-fuchsia-300 dark:ring-fuchsia-700'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50'
                    ]"
                >
                    <div :class="['w-7 h-7 rounded-lg flex items-center justify-center transition-colors', activeTab === 'hr' ? 'bg-fuchsia-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-700 text-slate-500']">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <span class="text-sm">Fitur HR (Pegawai)</span>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-bold bg-fuchsia-100 text-fuchsia-800 dark:bg-fuchsia-950/80 dark:text-fuchsia-300">
                        5 Fitur
                    </span>
                </button>

                <!-- Tab 3: Fitur Lanjutan -->
                <button
                    type="button"
                    @click="activeTab = 'lanjutan'"
                    :class="[
                        'flex items-center gap-2.5 px-5 py-3 rounded-xl font-bold text-xs transition-all duration-200 shrink-0 cursor-pointer',
                        activeTab === 'lanjutan'
                            ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-800 dark:text-indigo-300 shadow-xs ring-1 ring-indigo-300 dark:ring-indigo-700'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50'
                    ]"
                >
                    <div :class="['w-7 h-7 rounded-lg flex items-center justify-center transition-colors', activeTab === 'lanjutan' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-700 text-slate-500']">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                    </div>
                    <span class="text-sm">Fitur Lanjutan</span>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950/80 dark:text-indigo-300">
                        5 Opsi
                    </span>
                </button>

                <!-- Tab 4: Mode Pemeliharaan -->
                <button
                    type="button"
                    @click="activeTab = 'maintenance'"
                    :class="[
                        'flex items-center gap-2.5 px-5 py-3 rounded-xl font-bold text-xs transition-all duration-200 shrink-0 cursor-pointer',
                        activeTab === 'maintenance'
                            ? 'bg-amber-50 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 shadow-xs ring-1 ring-amber-300 dark:ring-amber-700'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50'
                    ]"
                >
                    <div :class="['w-7 h-7 rounded-lg flex items-center justify-center transition-colors', activeTab === 'maintenance' ? 'bg-amber-500 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-700 text-slate-500']">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <span class="text-sm">Pemeliharaan Sistem</span>
                    <span
                        :class="[
                            'text-[10px] px-2 py-0.5 rounded-full font-bold transition-colors',
                            form.maintenance_mode
                                ? 'bg-amber-200/80 text-amber-900 dark:bg-amber-900 dark:text-amber-200'
                                : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-400'
                        ]"
                    >
                        {{ form.maintenance_mode ? '● Aktif' : '○ Nonaktif' }}
                    </span>
                </button>
            </div>

            <!-- ══════════════════════════════════════════════════════════════════
                 FORM INDUK: SELURUH TAB BERADA DI DALAM SATU FORM
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
                        <div v-if="form.feature_presensi" class="mt-6 grid grid-cols-1 xl:grid-cols-12 gap-6 xl:gap-8">
                            
                            <!-- ═══ KOLOM KIRI: PETA LEAFLET & KOORDINAT (XL: 8 COLS) ═══ -->
                            <div class="xl:col-span-8 space-y-4">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div>
                                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300">
                                            Peta Titik Pusat Lokasi Sekolah
                                        </label>
                                        <p class="text-xs text-slate-400 mt-0.5">Klik peta atau seret pin hijau untuk memindahkan titik pusat sekolah.</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <!-- Tombol GPS -->
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

                                        <!-- Tombol Fullscreen Peta -->
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

                                <!-- ─── CONTAINER PETA & FULLSCREEN OVERLAY ─── -->
                                <div
                                    :class="[
                                        'transition-all duration-300',
                                        isFullscreenMap
                                            ? 'fixed inset-0 z-[99999] w-screen h-screen bg-slate-950/95 backdrop-blur-md flex flex-col p-4 sm:p-6'
                                            : 'relative'
                                    ]"
                                >
                                    <!-- Header Khusus Mode Fullscreen -->
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
                                            <!-- Tombol Deteksi GPS Fullscreen -->
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

                                            <!-- Tombol Keluar Fullscreen -->
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

                                    <!-- Canvas Peta Leaflet -->
                                    <div
                                        :class="[
                                            'rounded-2xl overflow-hidden border relative shadow-inner bg-slate-100 dark:bg-slate-900',
                                            isFullscreenMap
                                                ? 'flex-1 w-full border-slate-700/80'
                                                : 'h-80 sm:h-96 lg:h-[460px] w-full border-slate-200 dark:border-slate-700'
                                        ]"
                                    >
                                        <div id="hr-presensi-map" class="w-full h-full z-10" />

                                        <!-- Floating Badge Koordinat Aktif di Pojok Peta -->
                                        <div class="absolute bottom-3 left-3 z-[1000] bg-white/90 dark:bg-slate-900/90 backdrop-blur-md px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm text-[11px] font-mono font-bold text-slate-700 dark:text-slate-200 pointer-events-none flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
                                            <span>Lat: {{ form.presensi_latitude }}</span>
                                            <span class="text-slate-300 dark:text-slate-600">|</span>
                                            <span>Lng: {{ form.presensi_longitude }}</span>
                                        </div>
                                    </div>

                                    <!-- Floating Toolbar Bawah Mode Fullscreen -->
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

                                <!-- Input Latitude, Longitude & Radius -->
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

                                <!-- Radius Geofence Slider & Number Input -->
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

                            <!-- ═══ KOLOM KANAN: JAM KERJA & SHORTCUT PIKET (XL: 4 COLS) ═══ -->
                            <div class="xl:col-span-4 space-y-5">
                                <!-- Jam Kerja Card -->
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
                                    </div>
                                </div>

                                <!-- Shortcut Jadwal Piket & Harian -->
                                <div class="p-6 rounded-3xl bg-gradient-to-br from-emerald-500/10 via-emerald-500/5 to-transparent border border-emerald-500/20 space-y-3.5 shadow-2xs">
                                    <div class="flex items-start gap-3.5">
                                        <div class="w-9 h-9 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-md shadow-emerald-600/30">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h5 class="text-sm font-bold text-slate-800 dark:text-slate-100">Jadwal Khusus &amp; Piket Harian</h5>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                                Ingin membedakan jam kerja hari Jumat (misal pulang 11:30) atau memetakan giliran guru/pegawai piket harian?
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

                        <!-- Jika Fitur Presensi Nonaktif -->
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
                     TAB 2: FITUR HR (MANAJEMEN PEGAWAI)
                ────────────────────────────────────────────────────────────── -->
                <div v-show="activeTab === 'hr'" class="space-y-6">
                    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 p-6 sm:p-8 shadow-xs">
                        
                        <div class="pb-6 border-b border-slate-100 dark:border-slate-700">
                            <h3 class="text-xl font-black text-slate-800 dark:text-slate-100 flex items-center gap-2.5">
                                <div class="w-9 h-9 rounded-xl bg-fuchsia-50 dark:bg-fuchsia-950/50 flex items-center justify-center text-fuchsia-600">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                </div>
                                <span>Fitur HR (Manajemen Pegawai)</span>
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                Kelola modul kepegawaian eksklusif untuk analitik, pengelompokan divisi, dan profil pegawai.
                            </p>
                        </div>

                        <!-- 5 Kartu Modul HR (Grid 3 Kolom Responsif) -->
                        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                            <!-- 1. Lencana Khusus (Manual Badge) -->
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
                                            Berikan penghargaan atau tanda lencana manual kepada pegawai teladan pilihan pimpinan.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.feature_badges" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-fuchsia-600"></div>
                                </label>
                            </div>

                            <!-- 2. Grup / Divisi Jabatan -->
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
                                                (Kelola Divisi &rarr;)
                                            </Link>
                                        </div>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Kelompokkan pegawai ke dalam unit divisi dan hadirkan filter leaderboard per-divisi.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.feature_divisi" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-fuchsia-600"></div>
                                </label>
                            </div>

                            <!-- 3. Status Kehadiran (Cuti/Sakit) -->
                            <div class="flex items-start justify-between p-5 rounded-2xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-fuchsia-200 dark:hover:border-fuchsia-800 transition-all shadow-2xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-fuchsia-100 dark:bg-fuchsia-950/60 flex items-center justify-center text-fuchsia-600 shrink-0 shadow-xs mt-0.5">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">Status Cuti / Sakit / Dinas</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Tandai pegawai agar mereka di-gray out di leaderboard tanpa memutus catatan streak habitnya.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.feature_cuti" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-fuchsia-600"></div>
                                </label>
                            </div>

                            <!-- 4. ID Card Digital -->
                            <div class="flex items-start justify-between p-5 rounded-2xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-fuchsia-200 dark:hover:border-fuchsia-800 transition-all shadow-2xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-fuchsia-100 dark:bg-fuchsia-950/60 flex items-center justify-center text-fuchsia-600 shrink-0 shadow-xs mt-0.5">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">Kartu Profil (Digital ID Card)</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Modal pop-up ID card mewah dengan avatar, lencana, dan analitik ringkas saat nama pegawai diklik.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.feature_idcard" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-fuchsia-600"></div>
                                </label>
                            </div>

                            <!-- 5. Catatan Rahasia Pimpinan -->
                            <div class="flex items-start justify-between p-5 rounded-2xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-fuchsia-200 dark:hover:border-fuchsia-800 transition-all shadow-2xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-fuchsia-100 dark:bg-fuchsia-950/60 flex items-center justify-center text-fuchsia-600 shrink-0 shadow-xs mt-0.5">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">Catatan Rahasia Pimpinan (Notes)</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Fitur khusus bagi pimpinan/superadmin untuk menyisipkan pesan evaluasi pribadi rahasia pada kartu profil pegawai.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.feature_notes" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-fuchsia-600"></div>
                                </label>
                            </div>
                        </div>

                        <!-- Tombol Simpan Tab HR -->
                        <div class="mt-8 pt-5 border-t border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row items-center justify-between gap-3">
                            <span class="text-xs text-slate-400">Aktifkan modul yang dibutuhkan organisasi Anda lalu simpan.</span>
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
                                <span>SIMPAN FITUR HR</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ──────────────────────────────────────────────────────────────
                     TAB 3: FITUR LANJUTAN
                ────────────────────────────────────────────────────────────── -->
                <div v-show="activeTab === 'lanjutan'" class="space-y-6">
                    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 p-6 sm:p-8 shadow-xs">
                        
                        <div class="pb-6 border-b border-slate-100 dark:border-slate-700">
                            <h3 class="text-xl font-black text-slate-800 dark:text-slate-100 flex items-center gap-2.5">
                                <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center text-indigo-600">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                    </svg>
                                </div>
                                <span>Fitur Lanjutan &amp; Eksperimental</span>
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                Aktifkan kemampuan gamifikasi, notifikasi push PWA, dan penyesuaian tema sistem.
                            </p>
                        </div>

                        <!-- 5 Kartu Fitur Lanjutan (Grid 3 Kolom Responsif) -->
                        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                            <!-- 1. Gamifikasi -->
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
                                            Reward lencana pencapaian digital otomatis untuk memotivasi konsistensi pengguna.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.gamification_active" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-indigo-600"></div>
                                </label>
                            </div>

                            <!-- 2. Push Notifications -->
                            <div class="p-5 sm:p-6 rounded-2xl border border-slate-150 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all shadow-2xs space-y-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex items-start gap-3.5 min-w-0">
                                        <div class="w-10 h-10 rounded-2xl bg-indigo-100 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 shrink-0 shadow-xs mt-0.5">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                                            </svg>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">Push Notifications (PWA)</h4>
                                                <span v-if="pushStatus.vapid_configured" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                    ● VAPID Siap
                                                </span>
                                                <span v-else class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                                                    ○ VAPID Belum Siap
                                                </span>
                                            </div>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                                Kirimkan notifikasi pengingat ibadah, mutaba'ah harian, dan pengumuman langsung ke layar smartphone/laptop pengguna.
                                            </p>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-2">
                                        <input type="checkbox" v-model="form.push_notifications_active" class="sr-only peer" />
                                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-indigo-600"></div>
                                    </label>
                                </div>

                                <!-- Sub-Panel: Kontrol & Pengujian Push (Tampil jika sistem aktif) -->
                                <div v-if="form.push_notifications_active" class="pt-3 border-t border-slate-200/60 dark:border-slate-700/60 space-y-3">
                                    <!-- Status Bar Info -->
                                    <div class="flex flex-wrap items-center justify-between gap-2 p-3 bg-indigo-50/60 dark:bg-indigo-950/30 rounded-xl border border-indigo-100 dark:border-indigo-900/40 text-xs">
                                        <div class="flex items-center gap-2 text-indigo-900 dark:text-indigo-200">
                                            <span class="font-bold">Total Terdaftar di Sistem:</span>
                                            <span class="font-black px-2 py-0.5 rounded-md bg-indigo-200/70 dark:bg-indigo-900/60 text-indigo-900 dark:text-indigo-100">
                                                {{ pushStatus.total_system_subscriptions }} Perangkat
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-slate-500 dark:text-slate-400">Status Perangkat Ini:</span>
                                            <span v-if="isPushSubscribed" class="font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                </svg>
                                                Terdaftar
                                            </span>
                                            <span v-else class="font-bold text-amber-600 dark:text-amber-400">
                                                Belum Didaftarkan
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Feedback Alert -->
                                    <div v-if="pushTestMessage" :class="[
                                        'p-3 rounded-xl text-xs flex items-center justify-between gap-2 font-medium',
                                        pushTestStatus === 'success' 
                                            ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800'
                                            : 'bg-rose-50 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200 border border-rose-200 dark:border-rose-800'
                                    ]">
                                        <span>{{ pushTestMessage }}</span>
                                        <button type="button" @click="pushTestMessage = ''" class="text-slate-400 hover:text-slate-600 cursor-pointer">✕</button>
                                    </div>

                                    <!-- Tombol Aksi Registrasi & Pengujian -->
                                    <div class="flex flex-wrap items-center gap-2.5">
                                        <!-- Tombol 1: Izinkan & Daftarkan Browser Ini -->
                                        <button
                                            v-if="!isPushSubscribed"
                                            type="button"
                                            @click="handleSubscribeThisDevice"
                                            :disabled="isSubscribingPush"
                                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white font-bold text-xs shadow-xs transition-all cursor-pointer disabled:opacity-50"
                                        >
                                            <svg v-if="isSubscribingPush" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                            </svg>
                                            <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                            </svg>
                                            <span>{{ isSubscribingPush ? 'Meminta Izin...' : '🔔 Daftarkan Perangkat Ini' }}</span>
                                        </button>

                                        <!-- Tombol 2: Kirim Tes Notifikasi PWA -->
                                        <button
                                            v-if="isPushSubscribed"
                                            type="button"
                                            @click="handleSendTestPush"
                                            :disabled="pushTestLoading"
                                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-bold text-xs shadow-xs transition-all cursor-pointer disabled:opacity-50"
                                        >
                                            <svg v-if="pushTestLoading" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                            </svg>
                                            <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                            </svg>
                                            <span>{{ pushTestLoading ? 'Mengirim Notifikasi...' : '🚀 Kirim Tes Notifikasi PWA' }}</span>
                                        </button>

                                        <!-- Tombol 3: Hapus Subscription Perangkat Ini -->
                                        <button
                                            v-if="isPushSubscribed"
                                            type="button"
                                            @click="handleUnsubscribeThisDevice"
                                            :disabled="isSubscribingPush"
                                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-slate-600 dark:text-slate-300 hover:text-rose-600 dark:hover:text-rose-400 font-bold text-xs transition-all cursor-pointer disabled:opacity-50"
                                        >
                                            <span>Nonaktifkan di Perangkat Ini</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. Custom Habit per Divisi -->
                            <div class="flex items-start justify-between p-5 rounded-2xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all shadow-2xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-indigo-100 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 shrink-0 shadow-xs mt-0.5">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.429 9.75L2.25 12l4.179 2.25m0-4.5l5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0l4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0l-5.571 3-5.571-3" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">Custom Habit per Divisi</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Tugaskan pembiasaan khusus yang hanya diikuti oleh anggota grup / divisi tertentu.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.custom_habit_divisions_active" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-indigo-600"></div>
                                </label>
                            </div>

                            <!-- 4. Auto-Warning System -->
                            <div class="flex items-start justify-between p-5 rounded-2xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all shadow-2xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-indigo-100 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 shrink-0 shadow-xs mt-0.5">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">Auto-Warning System</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Peringatan otomatis bersahabat untuk pengguna yang capaian habitnya jauh di bawah target.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.auto_warning_active" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-indigo-600"></div>
                                </label>
                            </div>

                            <!-- 5. Dark Mode Toggle -->
                            <div class="flex items-start justify-between p-5 rounded-2xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/50 dark:bg-slate-900/40 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all shadow-2xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-2xl bg-indigo-100 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 shrink-0 shadow-xs mt-0.5">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-100">Opsi Tema Gelap (Dark Mode)</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                            Beri kenyamanan membaca di malam hari dengan tombol tema gelap di navigasi pengguna.
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                                    <input type="checkbox" v-model="form.dark_mode_active" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-indigo-600"></div>
                                </label>
                            </div>
                        </div>

                        <!-- Tombol Simpan Tab Lanjutan -->
                        <div class="mt-8 pt-5 border-t border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row items-center justify-between gap-3">
                            <span class="text-xs text-slate-400">Pengaturan lanjutan akan aktif secara langsung untuk seluruh pengguna.</span>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full sm:w-auto px-8 py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs rounded-2xl transition-all shadow-md hover:shadow-lg disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer"
                            >
                                <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>SIMPAN FITUR LANJUTAN</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ──────────────────────────────────────────────────────────────
                     TAB 4: MODE PEMELIHARAAN (MAINTENANCE)
                ────────────────────────────────────────────────────────────── -->
                <div v-show="activeTab === 'maintenance'" class="space-y-6">
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
                                            Alihkan pengunjung &amp; user biasa ke halaman pemeliharaan. Superadmin tetap memiliki akses penuh untuk uji coba.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Switch & Pratinjau -->
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

                        <!-- Body Setting Maintenance -->
                        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                            <!-- Judul Pemeliharaan -->
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

                            <!-- Estimasi Waktu Selesai -->
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

                            <!-- Pesan / Deskripsi -->
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

                            <!-- Footer Aksi Cepat Kartu Maintenance -->
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
                                    <span>{{ isTogglingInstant ? 'Menyimpan...' : 'Simpan Perubahan Teks Pemeliharaan' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ─── CARD 2: PEMBERSIHAN FILE FOTO STORAGE (STORAGE CLEANSING) ─── -->
                    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 p-6 sm:p-8 shadow-xs">
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
                                            Hapus file fisik foto selfie presensi (dinas luar) lampau secara aman untuk menghemat kapasitas storage disk server.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Statistik Storage Saat Ini -->
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
                            <!-- Penjelasan & Dropdown Pilihan Bulan -->
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

                                    <!-- Kartu Preview Rincian -->
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

                            <!-- Tombol Aksi Cleansing -->
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
                </div>

            </form>
        </div>

        <!-- ══════════════════════════════════════════════════════ -->
        <!-- Floating Action Button for Mobile Tab Menu (Islamic Widget Style) -->
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

        <!-- ══════════════════════════════════════════════════════ -->
        <!-- Mobile Tab Switcher Bottom Sheet Modal -->
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
                    <!-- Drag handle indicator for mobile bottom sheet -->
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
                                <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100">Menu Pengaturan</h3>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">Pilih tab pengaturan sistem yang ingin diubah</p>
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

                            <!-- Right Indicator -->
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

                    <!-- Footer Note -->
                    <div class="p-3 bg-slate-50 dark:bg-slate-850 border-t border-slate-100 dark:border-slate-800 text-center text-[11px] text-slate-400">
                        Klik salah satu menu untuk berpindah tab pengaturan
                    </div>
                </div>
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>
