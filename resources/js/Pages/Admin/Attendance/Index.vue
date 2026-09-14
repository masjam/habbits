<script setup>
import { ref, computed, watch, nextTick, onUnmounted } from 'vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const page = usePage()

const props = defineProps({
    monthlyReportData: Array,
    dailyReportData: Array,
    monthlyStats: Object,
    dailyStats: Object,
    daysList: Array,
    daysInMonth: Number,
    officeLocation: Object,
    filters: Object,
    formattedMonth: String,
    formattedDate: String,
    divisions: Array,
    isSuperadmin: Boolean,
})

// Hak akses edit presensi: Superadmin
const canEditAttendance = computed(() => {
    return !!(
        props.isSuperadmin ||
        page.props.auth?.roles?.includes('superadmin') ||
        page.props.auth?.is_actual_superadmin
    )
})

// State Filter & Mode Tampilan
const activeTab = ref(props.filters?.view_mode || 'monthly') // 'monthly' | 'daily'
const selectedMonth = ref(props.filters?.month || new Date().toISOString().substring(0, 7))
const selectedDate = ref(props.filters?.date || new Date().toISOString().split('T')[0])
const selectedDivision = ref(props.filters?.division || '')
const selectedStatus = ref(props.filters?.status || '')
const searchQuery = ref(props.filters?.search || '')

let searchTimeout = null
watch(searchQuery, (val) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        applyFilters()
    }, 300)
})

const applyFilters = () => {
    router.get(route('admin.attendance.index'), {
        month: selectedMonth.value,
        date: selectedDate.value,
        view_mode: activeTab.value,
        division: selectedDivision.value,
        status: selectedStatus.value,
        search: searchQuery.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

// Beralih ke tanggal tertentu saat kolom tanggal di klik
const selectDateAndSwitch = (targetDate) => {
    selectedDate.value = targetDate
    activeTab.value = 'daily'
    applyFilters()
}

// Navigasi Ganti Bulan
const prevMonth = () => {
    const [year, month] = selectedMonth.value.split('-').map(Number)
    const prevDate = new Date(year, month - 2, 1)
    selectedMonth.value = `${prevDate.getFullYear()}-${String(prevDate.getMonth() + 1).padStart(2, '0')}`
    applyFilters()
}

const nextMonth = () => {
    const [year, month] = selectedMonth.value.split('-').map(Number)
    const nextDate = new Date(year, month, 1)
    selectedMonth.value = `${nextDate.getFullYear()}-${String(nextDate.getMonth() + 1).padStart(2, '0')}`
    applyFilters()
}

// Ekspor ke file Microsoft Excel (.xlsx)
const exportExcel = () => {
    const params = new URLSearchParams({
        type: activeTab.value,
        month: selectedMonth.value,
        date: selectedDate.value,
        division: selectedDivision.value,
    })
    window.location.href = `${route('admin.attendance.export')}?${params.toString()}`
}

// ─── MODAL DETAIL & EDIT PRESENSI (SUPERADMIN) ──────────────────────────────
const selectedDetailModal = ref(null)
const isDeletingAttendance = ref(false)

const editForm = useForm({
    id: null,
    user_id: null,
    date: '',
    time_in: '',
    time_out: '',
    status: 'hadir',
    notes: '',
})

const openCellDetail = (user, dayItem, record) => {
    // Jika bukan superadmin dan tidak ada data presensi, abaikan
    if (!canEditAttendance.value && !record) return

    selectedDetailModal.value = {
        userId: user.user_id,
        userName: user.name,
        userNip: user.nip,
        userDivisi: user.divisi,
        schedule: user.work_schedule,
        work_start: user.work_start,
        work_end: user.work_end,
        late_tolerance: user.late_tolerance,
        date: dayItem.date,
        dayNumber: dayItem.day,
        record: record || null,
    }

    // Populate form data
    editForm.id = record?.id || record?.attendance_id || null
    editForm.user_id = user.user_id
    editForm.date = dayItem.date
    editForm.time_in = record?.time_in ? record.time_in.substring(0, 5) : ''
    editForm.time_out = record?.time_out ? record.time_out.substring(0, 5) : ''
    editForm.status = (record?.status && record.status !== 'belum_hadir') ? record.status : 'hadir'
    editForm.notes = record?.notes || ''
}

const closeCellDetail = () => {
    selectedDetailModal.value = null
    editForm.reset()
    editForm.clearErrors()
}

const submitEditAttendance = () => {
    editForm.post(route('admin.attendance.update-record'), {
        preserveScroll: true,
        onSuccess: () => {
            closeCellDetail()
        }
    })
}

const deleteAttendanceRecord = () => {
    if (!editForm.id) return
    const name = selectedDetailModal.value?.userName || 'pegawai'
    const date = selectedDetailModal.value?.date || ''
    if (!confirm(`Hapus data presensi ${name} pada tanggal ${date}? Tindakan ini tidak dapat dibatalkan.`)) {
        return
    }
    isDeletingAttendance.value = true
    router.delete(route('admin.attendance.delete-record', editForm.id), {
        preserveScroll: true,
        onFinish: () => {
            isDeletingAttendance.value = false
            closeCellDetail()
        }
    })
}

// ─── PENGATURAN TITIK LOKASI & MINI MAP (LEAFLET) ───────────────────────────
const isLocationModalOpen = ref(false)
let mapInstance = null
let mapMarker = null
let mapCircle = null
const isLocatingAdmin = ref(false)

const locationForm = useForm({
    latitude: props.officeLocation?.latitude || -7.7956,
    longitude: props.officeLocation?.longitude || 110.3695,
    radius_meters: props.officeLocation?.radius_meters || 100,
    work_start: props.officeLocation?.work_start || '07:00',
    work_end: props.officeLocation?.work_end || '15:00',
    late_tolerance: props.officeLocation?.late_tolerance || 15,
})

const openLocationModal = () => {
    locationForm.latitude = props.officeLocation?.latitude || -7.7956
    locationForm.longitude = props.officeLocation?.longitude || 110.3695
    locationForm.radius_meters = props.officeLocation?.radius_meters || 100
    locationForm.work_start = props.officeLocation?.work_start || '07:00'
    locationForm.work_end = props.officeLocation?.work_end || '15:00'
    locationForm.late_tolerance = props.officeLocation?.late_tolerance || 15
    isLocationModalOpen.value = true

    nextTick(() => {
        initMiniMap()
    })
}

const closeLocationModal = () => {
    isLocationModalOpen.value = false
    if (mapInstance) {
        mapInstance.remove()
        mapInstance = null
        mapMarker = null
        mapCircle = null
    }
}

const initMiniMap = () => {
    const mapEl = document.getElementById('office-mini-map')
    if (!mapEl) return

    if (mapInstance) {
        mapInstance.remove()
        mapInstance = null
    }

    const initialLat = Number(locationForm.latitude) || -7.7956
    const initialLng = Number(locationForm.longitude) || 110.3695
    const initialRadius = Number(locationForm.radius_meters) || 100

    mapInstance = L.map('office-mini-map', {
        center: [initialLat, initialLng],
        zoom: 17,
        scrollWheelZoom: true,
    })

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(mapInstance)

    const markerIcon = L.divIcon({
        className: 'school-pin-marker',
        html: `
            <div style="background-color: #059669; color: white; width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 14px rgba(5,150,105,0.6); border: 2.5px solid white; transform: translate(-50%, -50%); cursor: grab;">
                <svg style="width: 22px; height: 22px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
            </div>
        `,
        iconSize: [38, 38],
        iconAnchor: [0, 0]
    })

    mapMarker = L.marker([initialLat, initialLng], {
        icon: markerIcon,
        draggable: true
    }).addTo(mapInstance)

    mapCircle = L.circle([initialLat, initialLng], {
        radius: initialRadius,
        color: '#059669',
        fillColor: '#10B981',
        fillOpacity: 0.25,
        weight: 2
    }).addTo(mapInstance)

    // Klik di peta => pindahkan marker dan circle
    mapInstance.on('click', (e) => {
        const { lat, lng } = e.latlng
        updateMarkerPosition(lat, lng)
    })

    // Drag marker
    mapMarker.on('dragend', (e) => {
        const pos = e.target.getLatLng()
        updateMarkerPosition(pos.lat, pos.lng)
    })

    // Render ulang ukuran setelah dialog terbuka
    setTimeout(() => {
        mapInstance?.invalidateSize()
    }, 250)
}

const updateMarkerPosition = (lat, lng) => {
    locationForm.latitude = parseFloat(lat).toFixed(7)
    locationForm.longitude = parseFloat(lng).toFixed(7)

    if (mapMarker) mapMarker.setLatLng([lat, lng])
    if (mapCircle) mapCircle.setLatLng([lat, lng])
}

// Watch radius input to update circle radius immediately
watch(() => locationForm.radius_meters, (newRadius) => {
    const r = Number(newRadius) || 100
    if (mapCircle) {
        mapCircle.setRadius(r)
    }
})

// Manual coord change
const handleManualCoordChange = () => {
    const lat = Number(locationForm.latitude)
    const lng = Number(locationForm.longitude)
    if (!isNaN(lat) && !isNaN(lng)) {
        if (mapMarker) mapMarker.setLatLng([lat, lng])
        if (mapCircle) mapCircle.setLatLng([lat, lng])
        if (mapInstance) mapInstance.panTo([lat, lng])
    }
}

// Ambil lokasi GPS saat ini
const getAdminCurrentGps = () => {
    if (!navigator.geolocation) {
        alert('Browser Anda tidak mendukung deteksi lokasi.')
        return
    }

    isLocatingAdmin.value = true
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const lat = pos.coords.latitude
            const lng = pos.coords.longitude
            updateMarkerPosition(lat, lng)
            if (mapInstance) {
                mapInstance.setView([lat, lng], 18)
            }
            isLocatingAdmin.value = false
        },
        (err) => {
            alert('Gagal mendeteksi lokasi GPS: ' + err.message)
            isLocatingAdmin.value = false
        },
        { enableHighAccuracy: true }
    )
}

const submitLocation = () => {
    locationForm.post(route('admin.attendance.location'), {
        preserveScroll: true,
        onSuccess: () => {
            closeLocationModal()
        }
    })
}

onUnmounted(() => {
    if (mapInstance) {
        mapInstance.remove()
        mapInstance = null
    }
})

// Helper Style Cell Matriks per Tanggal
const getCellClass = (record, isWeekend, isFuture) => {
    if (!record) {
        if (isWeekend) return 'bg-slate-100/50 dark:bg-slate-900/30 text-slate-300 dark:text-slate-600'
        if (isFuture) return 'bg-card-subtle/30 text-slate-300 dark:text-slate-600'
        return 'bg-card-subtle/40 text-slate-400 hover:bg-slate-100/60'
    }

    if (record.is_pulang_cepat) {
        return 'bg-purple-50/90 text-purple-900 border border-purple-200 dark:bg-purple-950/40 dark:text-purple-200 dark:border-purple-800/80 hover:scale-105 shadow-2xs'
    }
    if (record.status === 'dinas_luar') {
        return 'bg-blue-50/90 text-blue-900 border border-blue-200 dark:bg-blue-950/40 dark:text-blue-200 dark:border-blue-800/80 hover:scale-105 shadow-2xs'
    }
    if (record.status === 'terlambat') {
        return 'bg-amber-50/90 text-amber-900 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:border-amber-800/80 hover:scale-105 shadow-2xs'
    }
    return 'bg-emerald-50/90 text-emerald-900 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:border-emerald-800/80 hover:scale-105 shadow-2xs'
}
</script>

<template>
    <Head title="Rekap Presensi Pegawai" />

    <AuthenticatedLayout>
        <div class="space-y-6">

            <!-- ── Header & Action Bar ───────────────────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-800 dark:text-slate-100 flex items-center gap-2.5">
                        <span>Rekap Presensi Pegawai</span>
                        <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                            Matriks Bulanan GPS
                        </span>
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Matriks jam masuk &amp; jam pulang dalam satu kolom tanggal per pegawai dengan kode warna status.
                    </p>
                </div>

                <div class="flex items-center gap-2.5">
                    <!-- Tombol Atur Titik Lokasi & Mini Map (Akses Admin) -->
                    <button
                        type="button"
                        @click="openLocationModal"
                        class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-card-subtle hover:bg-emerald-50 dark:hover:bg-emerald-950/40 text-slate-700 dark:text-slate-200 border border-theme text-xs font-bold transition-all active:scale-95 cursor-pointer shadow-xs"
                    >
                        <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>Atur Titik Lokasi &amp; Radius</span>
                    </button>

                    <!-- Tombol Ekspor Excel -->
                    <button
                        type="button"
                        @click="exportExcel"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black shadow-md hover:shadow-emerald-600/20 active:scale-95 transition-all cursor-pointer"
                        :title="activeTab === 'monthly' ? 'Ekspor Matriks Bulanan (.xlsx)' : 'Ekspor Detail Harian (.xlsx)'"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Ekspor Excel (.xlsx)</span>
                    </button>
                </div>
            </div>

            <!-- ── Tab Mode & Pemilih Periode Bulan ───────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-subtle pb-3">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="activeTab = 'monthly'; applyFilters()"
                        :class="[
                            'flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer',
                            activeTab === 'monthly'
                                ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/30'
                                : 'bg-card-subtle text-slate-600 dark:text-slate-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/30'
                        ]"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <span>📊 Matriks Bulanan</span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'daily'; applyFilters()"
                        :class="[
                            'flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer',
                            activeTab === 'daily'
                                ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/30'
                                : 'bg-card-subtle text-slate-600 dark:text-slate-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/30'
                        ]"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span>📅 Detail Harian ({{ formattedDate }})</span>
                    </button>
                </div>

                <!-- Kontrol Navigasi Bulan -->
                <div class="flex items-center gap-2 self-start sm:self-auto">
                    <button
                        type="button"
                        @click="prevMonth"
                        class="p-1.5 rounded-lg bg-card-subtle hover:bg-emerald-100 dark:hover:bg-emerald-950 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer"
                        title="Bulan Sebelumnya"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <input 
                        type="month" 
                        v-model="selectedMonth" 
                        @change="applyFilters"
                        class="text-xs font-bold rounded-xl border border-theme bg-card-subtle px-3 py-1.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 cursor-pointer"
                    />

                    <button
                        type="button"
                        @click="nextMonth"
                        class="p-1.5 rounded-lg bg-card-subtle hover:bg-emerald-100 dark:hover:bg-emerald-950 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer"
                        title="Bulan Berikutnya"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- ── Statistik Ringkasan Bulan Ini ──────────────────────────────── -->
            <div v-if="activeTab === 'monthly'" class="grid grid-cols-2 sm:grid-cols-6 gap-3">
                <div class="p-3.5 bg-sidebar rounded-2xl border border-theme shadow-xs">
                    <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400">Total Pegawai</p>
                    <p class="text-xl font-black text-slate-800 dark:text-slate-100 mt-0.5">{{ monthlyStats?.total_pegawai || 0 }}</p>
                </div>
                <div class="p-3.5 bg-sidebar rounded-2xl border border-theme shadow-xs">
                    <p class="text-[11px] font-bold text-emerald-600">Rata-rata Hadir</p>
                    <p class="text-xl font-black text-emerald-600 mt-0.5">{{ monthlyStats?.avg_attendance_rate || 0 }}%</p>
                </div>
                <div class="p-3.5 bg-sidebar rounded-2xl border border-theme shadow-xs">
                    <p class="text-[11px] font-bold text-teal-600">Tepat Waktu</p>
                    <p class="text-xl font-black text-teal-600 mt-0.5">{{ monthlyStats?.total_hadir_sebulan || 0 }}</p>
                </div>
                <div class="p-3.5 bg-sidebar rounded-2xl border border-theme shadow-xs">
                    <p class="text-[11px] font-bold text-amber-600">Terlambat</p>
                    <p class="text-xl font-black text-amber-600 mt-0.5">{{ monthlyStats?.total_terlambat_sebulan || 0 }}</p>
                </div>
                <div class="p-3.5 bg-sidebar rounded-2xl border border-theme shadow-xs">
                    <p class="text-[11px] font-bold text-blue-600">Tugas Luar</p>
                    <p class="text-xl font-black text-blue-600 mt-0.5">{{ monthlyStats?.total_dinas_luar_sebulan || 0 }}</p>
                </div>
                <div class="p-3.5 bg-sidebar rounded-2xl border border-theme shadow-xs col-span-2 sm:col-span-1">
                    <p class="text-[11px] font-bold text-purple-600">Pulang Mendahului</p>
                    <p class="text-xl font-black text-purple-600 mt-0.5">{{ monthlyStats?.total_pulang_cepat_sebulan || 0 }}</p>
                </div>
            </div>

            <!-- ── Filter & Legenda Warna Matriks ─────────────────────────────── -->
            <div class="bg-sidebar p-4 rounded-2xl border border-theme shadow-xs space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <!-- Filter Divisi & Pencarian -->
                    <div class="flex flex-wrap items-center gap-2.5 flex-1 min-w-[280px]">
                        <div>
                            <select 
                                v-model="selectedDivision" 
                                @change="applyFilters"
                                class="text-xs rounded-xl border border-theme bg-card-subtle px-3 py-2 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500"
                            >
                                <option value="">Semua Divisi</option>
                                <option v-for="d in divisions" :key="d.id" :value="d.name">{{ d.name }}</option>
                            </select>
                        </div>

                        <div class="flex-1 min-w-[180px] max-w-sm">
                            <div class="relative">
                                <input 
                                    type="text" 
                                    v-model="searchQuery" 
                                    placeholder="Cari nama pegawai..." 
                                    class="w-full text-xs rounded-xl border border-theme bg-card-subtle pl-8 pr-3 py-2 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500"
                                />
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Legenda Warna Status Cell -->
                    <div class="flex flex-wrap items-center gap-2 text-[10px] font-bold">
                        <span class="text-slate-400 uppercase tracking-wider text-[9px] mr-1">Legenda:</span>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" /> Tepat Waktu
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500" /> Terlambat
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-blue-50 text-blue-800 border border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500" /> Tugas Luar
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-purple-50 text-purple-800 border border-purple-200 dark:bg-purple-950/60 dark:text-purple-300 dark:border-purple-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-purple-500" /> Pulang Mendahului
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-slate-100 text-slate-500 border border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400" /> Weekend / Libur
                        </span>
                    </div>
                </div>
            </div>

            <!-- ── TABEL 1: MATRIKS PRESENSI BULANAN (GRID CELL JAM MASUK & PULANG) ── -->
            <div v-if="activeTab === 'monthly'" class="bg-sidebar rounded-3xl border border-theme shadow-xs overflow-hidden">
                <div class="p-4 border-b border-subtle flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <span>Matriks Kehadiran Bulan {{ formattedMonth }}</span>
                        </h2>
                        <p class="text-[11px] text-slate-400">
                            Format cell per tanggal: <strong>[Jam Masuk]</strong> (atas) dan <strong>[Jam Pulang]</strong> (bawah). Klik tanggal di header atau cell untuk rincian.
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-subtle bg-card-subtle text-slate-600 dark:text-slate-300">
                                <!-- Sticky Header Pegawai di Kiri -->
                                <th class="py-3 px-4 font-bold sticky left-0 bg-card-subtle z-20 min-w-[210px] border-r border-subtle shadow-xs">
                                    Pegawai
                                </th>

                                <!-- Kolom-Kolom Tanggal (1 s/d 30/31) di Bagian Atas -->
                                <th 
                                    v-for="d in daysList" 
                                    :key="d.day"
                                    @click="selectDateAndSwitch(d.date)"
                                    :class="[
                                        'py-2 px-1 text-center min-w-[54px] max-w-[62px] border-r border-subtle/70 transition-colors cursor-pointer select-none group',
                                        d.is_today ? 'bg-emerald-100/60 dark:bg-emerald-950/50' : 
                                        d.is_weekend ? 'bg-slate-100/50 dark:bg-slate-800/30' : 'hover:bg-emerald-50 dark:hover:bg-emerald-900/20'
                                    ]"
                                    :title="`Klik untuk melihat rekap detail tanggal ${d.date}`"
                                >
                                    <div class="flex flex-col items-center justify-center">
                                        <span :class="['text-[9px] uppercase font-bold tracking-tight', d.is_weekend ? 'text-rose-500' : 'text-slate-400 group-hover:text-emerald-600']">
                                            {{ d.day_name }}
                                        </span>
                                        <span :class="['text-xs font-black leading-none mt-0.5', d.is_today ? 'text-emerald-700 dark:text-emerald-400 underline decoration-2' : 'text-slate-800 dark:text-slate-100']">
                                            {{ d.day }}
                                        </span>
                                    </div>
                                </th>

                                <!-- Kolom Ringkasan Akumulasi di Kanan -->
                                <th class="py-2 px-2.5 text-center min-w-[40px] font-bold text-emerald-600 border-r border-subtle/70" title="Tepat Waktu">H</th>
                                <th class="py-2 px-2.5 text-center min-w-[40px] font-bold text-amber-600 border-r border-subtle/70" title="Terlambat">T</th>
                                <th class="py-2 px-2.5 text-center min-w-[40px] font-bold text-blue-600 border-r border-subtle/70" title="Tugas Luar">DL</th>
                                <th class="py-2 px-2.5 text-center min-w-[40px] font-bold text-purple-600 border-r border-subtle/70" title="Pulang Mendahului">PC</th>
                                <th class="py-2 px-3 text-center min-w-[50px] font-black text-slate-800 dark:text-slate-100 border-r border-subtle/70" title="Total Hadir">Total</th>
                                <th class="py-2 px-3 text-center min-w-[55px] font-black text-emerald-600" title="Persentase">%</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-subtle">
                            <tr v-for="user in monthlyReportData" :key="user.user_id" class="hover:bg-card-subtle/30 transition-colors">
                                <!-- Sticky Info Pegawai -->
                                <td class="py-2.5 px-4 sticky left-0 bg-sidebar z-10 border-r border-subtle shadow-xs">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-950 flex items-center justify-center font-bold text-xs text-emerald-700 dark:text-emerald-400 flex-shrink-0 overflow-hidden border border-emerald-200 dark:border-emerald-800">
                                            <img v-if="user.avatar" :src="user.avatar.startsWith('http') ? user.avatar : `/storage/${user.avatar}`" class="w-full h-full object-cover" />
                                            <span v-else>{{ user.name.charAt(0).toUpperCase() }}</span>
                                        </div>
                                        <div class="truncate max-w-[150px]">
                                            <p class="font-bold text-slate-800 dark:text-slate-100 text-xs truncate" :title="user.name">{{ user.name }}</p>
                                            <p class="text-[10px] text-slate-400 truncate">
                                                {{ user.divisi || 'Tanpa Divisi' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Cell Presensi per Tanggal (Jam Masuk & Pulang dalam 1 cell) -->
                                <td
                                    v-for="d in daysList"
                                    :key="d.day"
                                    @click="openCellDetail(user, d, user.daily_records?.[d.day])"
                                    :class="[
                                        'py-1.5 px-0.5 text-center border-r border-subtle/60 transition-all cursor-pointer select-none',
                                        d.is_today ? 'bg-emerald-50/20' : '',
                                        canEditAttendance && !user.daily_records?.[d.day] && !d.is_future ? 'hover:bg-emerald-100/50 dark:hover:bg-emerald-950/40' : ''
                                    ]"
                                    :title="canEditAttendance ? (user.daily_records?.[d.day] ? `Klik untuk lihat/koreksi presensi (${user.name} - tgl ${d.day})` : `Klik untuk input presensi (${user.name} - tgl ${d.day})`) : (user.daily_records?.[d.day] ? 'Klik untuk melihat detail presensi' : '')"
                                >
                                    <div 
                                        :class="[
                                            'rounded-lg py-1 px-1 transition-all mx-auto max-w-[56px]',
                                            getCellClass(user.daily_records?.[d.day], d.is_weekend, d.is_future)
                                        ]"
                                    >
                                        <template v-if="user.daily_records?.[d.day]">
                                            <!-- Jam Masuk (Atas) -->
                                            <div class="text-[10px] font-black font-mono leading-none tracking-tight">
                                                {{ user.daily_records[d.day].time_in || '--:--' }}
                                            </div>
                                            <!-- Jam Pulang (Bawah) -->
                                            <div class="text-[9px] font-mono leading-none tracking-tight mt-1 opacity-80 flex items-center justify-center gap-0.5">
                                                <span>{{ user.daily_records[d.day].time_out || '--:--' }}</span>
                                                <span v-if="user.daily_records[d.day].is_overtime" class="text-[8px] text-amber-500 font-black" :title="`Lembur: ${user.daily_records[d.day].overtime_minutes}m`">⚡</span>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <span 
                                                :class="[
                                                    'text-[10px] font-bold block py-1 transition-all',
                                                    canEditAttendance && !d.is_future ? 'text-slate-400 dark:text-slate-500 hover:text-emerald-600 hover:scale-125 font-black' : 'text-slate-300 dark:text-slate-600'
                                                ]"
                                            >
                                                {{ canEditAttendance && !d.is_future ? '+' : '-' }}
                                            </span>
                                        </template>
                                    </div>
                                </td>

                                <!-- Ringkasan Hitungan Sebulan -->
                                <td class="py-2.5 px-2.5 text-center font-bold text-emerald-600 border-r border-subtle/60 text-xs">
                                    {{ user.total_hadir }}
                                </td>
                                <td class="py-2.5 px-2.5 text-center font-bold text-amber-600 border-r border-subtle/60 text-xs">
                                    {{ user.total_terlambat }}
                                </td>
                                <td class="py-2.5 px-2.5 text-center font-bold text-blue-600 border-r border-subtle/60 text-xs">
                                    {{ user.total_dinas_luar }}
                                </td>
                                <td class="py-2.5 px-2.5 text-center font-bold text-purple-600 border-r border-subtle/60 text-xs">
                                    {{ user.total_pulang_cepat }}
                                </td>
                                <td class="py-2.5 px-3 text-center font-black text-slate-800 dark:text-slate-100 border-r border-subtle/60 text-xs">
                                    {{ user.total_kehadiran }}
                                </td>
                                <td class="py-2.5 px-3 text-center font-black text-emerald-600 text-xs">
                                    {{ user.attendance_rate }}%
                                </td>
                            </tr>

                            <tr v-if="!monthlyReportData || monthlyReportData.length === 0">
                                <td :colspan="daysList.length + 7" class="py-12 text-center text-slate-400">
                                    Tidak ada data pegawai yang sesuai dengan filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── TABEL 2: DETAIL HARIAN (Saat Tab Harian Aktif) ──────────────── -->
            <div v-else class="bg-sidebar rounded-3xl border border-theme shadow-xs overflow-hidden">
                <div class="p-4 border-b border-subtle flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <span>Detail Presensi Tanggal: {{ formattedDate }}</span>
                        </h2>
                        <p class="text-[11px] text-slate-400">
                            Menampilkan jam masuk, jam kepulangan, jarak GPS, dan foto selfie dinas luar.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <input 
                            type="date" 
                            v-model="selectedDate" 
                            @change="applyFilters"
                            class="text-xs rounded-xl border border-theme bg-card-subtle px-3 py-1.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 cursor-pointer"
                        />
                        <button
                            type="button"
                            @click="activeTab = 'monthly'; applyFilters()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 border border-emerald-200 dark:border-emerald-800 transition-colors cursor-pointer"
                        >
                            <span>← Kembali ke Matriks Bulanan</span>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-subtle bg-card-subtle text-slate-500 dark:text-slate-400 uppercase tracking-wider text-[10px]">
                                <th class="py-3 px-4">Pegawai</th>
                                <th class="py-3 px-3">Jadwal Kerja</th>
                                <th class="py-3 px-3">Jam Masuk</th>
                                <th class="py-3 px-3">Jam Pulang</th>
                                <th class="py-3 px-3">Status</th>
                                <th class="py-3 px-4">Foto &amp; Keterangan</th>
                                <th v-if="canEditAttendance" class="py-3 px-3 text-center w-28">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-subtle">
                            <tr v-for="item in dailyReportData" :key="item.user_id" class="hover:bg-card-subtle/50 transition-colors">
                                <!-- Info Pegawai -->
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-emerald-100 dark:bg-emerald-950 flex items-center justify-center font-bold text-xs text-emerald-700 dark:text-emerald-400 flex-shrink-0 overflow-hidden border border-emerald-200 dark:border-emerald-800">
                                            <img v-if="item.avatar" :src="item.avatar.startsWith('http') ? item.avatar : `/storage/${item.avatar}`" class="w-full h-full object-cover" />
                                            <span v-else>{{ item.name.charAt(0).toUpperCase() }}</span>
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-800 dark:text-slate-100">{{ item.name }}</p>
                                            <p class="text-[11px] text-slate-400">
                                                {{ item.divisi || 'Tanpa Divisi' }} <span v-if="item.nip">· NIP: {{ item.nip }}</span>
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Jadwal Kerja -->
                                <td class="py-3.5 px-3">
                                    <span class="font-mono text-slate-700 dark:text-slate-300 font-semibold">{{ item.work_schedule }}</span>
                                    <span v-if="item.is_custom_schedule" class="ml-1.5 px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                        Khusus
                                    </span>
                                </td>

                                <!-- Jam Masuk -->
                                <td class="py-3.5 px-3 font-mono">
                                    <template v-if="item.time_in">
                                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ item.time_in }}</span>
                                        <span v-if="item.distance_in !== null" class="block text-[10px] text-slate-400">
                                            📍 {{ item.distance_in }}m
                                        </span>
                                    </template>
                                    <template v-else>
                                        <span class="text-slate-400">-</span>
                                    </template>
                                </td>

                                <!-- Jam Pulang -->
                                <td class="py-3.5 px-3 font-mono">
                                    <template v-if="item.time_out">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ item.time_out }}</span>
                                            <span v-if="item.is_overtime" class="px-1.5 py-0.5 rounded text-[9px] font-black bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200 font-sans" :title="item.overtime_activity || 'Lembur'">
                                                ⚡ {{ item.overtime_minutes }}m
                                            </span>
                                        </div>
                                        <span v-if="item.distance_out !== null" class="block text-[10px] text-slate-400">
                                            📍 {{ item.distance_out }}m
                                        </span>
                                    </template>
                                    <template v-else>
                                        <span class="text-slate-400">-</span>
                                    </template>
                                </td>

                                <!-- Status Badge -->
                                <td class="py-3.5 px-3">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span 
                                            :class="[
                                                'px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider',
                                                item.status === 'hadir' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' :
                                                item.status === 'terlambat' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' :
                                                item.status === 'dinas_luar' ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300' :
                                                'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300'
                                            ]"
                                        >
                                            {{ item.status === 'belum_hadir' ? 'Belum Hadir' : item.status.replace('_', ' ') }}
                                        </span>

                                        <span 
                                            v-if="item.is_pulang_cepat" 
                                            class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300"
                                            title="Pulang mendahului jadwal pulang kerja"
                                        >
                                            Pulang Cepat
                                        </span>
                                    </div>
                                </td>

                                <!-- Foto & Keterangan -->
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-2">
                                        <!-- Thumbnail Foto Masuk jika ada -->
                                        <button 
                                            v-if="item.photo_in" 
                                            type="button" 
                                            @click="openCellDetail(item, { date: selectedDate, day: '' }, item)"
                                            class="w-7 h-7 rounded-lg overflow-hidden border border-theme hover:scale-110 transition-transform flex-shrink-0 cursor-pointer"
                                            title="Lihat Foto Masuk (Selfie)"
                                        >
                                            <img :src="`/storage/${item.photo_in}`" class="w-full h-full object-cover" />
                                        </button>

                                        <!-- Keterangan Catatan & Lembur -->
                                        <div class="text-[11px] truncate max-w-xs">
                                            <span class="text-slate-600 dark:text-slate-400">{{ item.notes || '-' }}</span>
                                            <div v-if="item.is_overtime && item.overtime_activity" class="text-[10px] text-amber-600 dark:text-amber-400 font-medium truncate mt-0.5" :title="item.overtime_activity">
                                                ⚡ Lembur: {{ item.overtime_activity }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Aksi Edit Khusus Superadmin -->
                                <td v-if="canEditAttendance" class="py-3.5 px-3 text-center">
                                    <button 
                                        type="button" 
                                        @click="openCellDetail(item, { date: selectedDate, day: '' }, item)"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 text-xs font-bold border border-emerald-200 dark:border-emerald-800 transition-all cursor-pointer shadow-2xs hover:shadow-xs active:scale-95"
                                        title="Edit Presensi Masuk & Pulang (Superadmin)"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        <span>Edit</span>
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="!dailyReportData || dailyReportData.length === 0">
                                <td :colspan="canEditAttendance ? 7 : 6" class="py-12 text-center text-slate-400">
                                    Tidak ada data pegawai yang sesuai dengan filter pada tanggal ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── MODAL 1: ATUR TITIK LOKASI SEKOLAH & MINI MAP (LEAFLET) ──────── -->
            <div 
                v-if="isLocationModalOpen" 
                class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/75 backdrop-blur-xs overflow-y-auto"
                @click="closeLocationModal"
            >
                <div class="relative bg-sidebar max-w-2xl w-full rounded-3xl overflow-hidden shadow-2xl border border-theme my-8" @click.stop>
                    <div class="p-5 border-b border-subtle flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span>Atur Titik Lokasi Sekolah &amp; Radius Geofencing</span>
                            </h3>
                            <p class="text-xs text-slate-400 mt-0.5">Klik di peta atau geser pin hijau untuk menentukan titik koordinat presensi sekolah.</p>
                        </div>
                        <button 
                            type="button" 
                            @click="closeLocationModal"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitLocation">
                        <div class="p-5 space-y-4">

                            <!-- Petunjuk & Tombol Ambil Lokasi GPS Saya Saat Ini -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-3 rounded-2xl bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-200/60 dark:border-emerald-800/40">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">🗺️</span>
                                    <p class="text-xs text-emerald-900 dark:text-emerald-200 font-medium leading-tight">
                                        Klik langsung pada peta untuk memindahkan titik sekolah atau geser pin.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    @click="getAdminCurrentGps"
                                    :disabled="isLocatingAdmin"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-emerald-300 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300 text-xs font-bold shadow-2xs hover:bg-emerald-50 transition-all active:scale-95 cursor-pointer disabled:opacity-50 flex-shrink-0"
                                >
                                    <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': isLocatingAdmin }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>{{ isLocatingAdmin ? 'Mendeteksi...' : 'Ambil Lokasi Saya Saat Ini' }}</span>
                                </button>
                            </div>

                            <!-- ── Container Leaflet Mini Map ── -->
                            <div class="rounded-2xl overflow-hidden border border-theme relative shadow-inner bg-slate-100 dark:bg-slate-900">
                                <div id="office-mini-map" class="h-64 sm:h-72 w-full z-10" />
                            </div>

                            <!-- Input Koordinat & Radius -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Latitude</label>
                                    <input 
                                        type="text" 
                                        v-model="locationForm.latitude" 
                                        @change="handleManualCoordChange"
                                        class="w-full text-xs rounded-xl border border-theme bg-card-subtle px-3 py-2 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 font-mono"
                                        placeholder="-7.7956000"
                                        required
                                    />
                                    <p v-if="locationForm.errors.latitude" class="text-[10px] text-rose-500 mt-0.5">{{ locationForm.errors.latitude }}</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Longitude</label>
                                    <input 
                                        type="text" 
                                        v-model="locationForm.longitude" 
                                        @change="handleManualCoordChange"
                                        class="w-full text-xs rounded-xl border border-theme bg-card-subtle px-3 py-2 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 font-mono"
                                        placeholder="110.3695000"
                                        required
                                    />
                                    <p v-if="locationForm.errors.longitude" class="text-[10px] text-rose-500 mt-0.5">{{ locationForm.errors.longitude }}</p>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="block text-[10px] font-bold uppercase text-slate-500">Radius Sekolah</label>
                                        <span class="text-xs font-black text-emerald-600">{{ locationForm.radius_meters }} meter</span>
                                    </div>
                                    <input 
                                        type="range" 
                                        v-model="locationForm.radius_meters" 
                                        min="10" 
                                        max="1000" 
                                        step="10"
                                        class="w-full accent-emerald-600 cursor-pointer"
                                    />
                                    <p class="text-[9px] text-slate-400">Lingkaran hijau di peta membesar sesuai radius.</p>
                                </div>
                            </div>

                            <!-- Pengaturan Jam Kerja Global Default Sekolah -->
                            <div class="p-3.5 rounded-2xl bg-card-subtle border border-theme/60 space-y-2">
                                <label class="block text-xs font-black text-slate-700 dark:text-slate-200">
                                    Jam Kerja Global (Default Sekolah)
                                </label>
                                <div class="grid grid-cols-3 gap-2">
                                    <div>
                                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-0.5">Jam Masuk</label>
                                        <input 
                                            type="time" 
                                            v-model="locationForm.work_start" 
                                            class="w-full text-xs rounded-xl border border-theme bg-sidebar px-2.5 py-1.5 text-slate-800 dark:text-slate-200"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-0.5">Toleransi Telat</label>
                                        <div class="relative">
                                            <input 
                                                type="number" 
                                                v-model="locationForm.late_tolerance" 
                                                min="0" 
                                                max="120"
                                                class="w-full text-xs rounded-xl border border-theme bg-sidebar px-2 py-1.5 pr-7 text-slate-800 dark:text-slate-200"
                                            />
                                            <span class="absolute inset-y-0 right-2 flex items-center text-[10px] text-slate-400">mnt</span>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-0.5">Jam Pulang</label>
                                        <input 
                                            type="time" 
                                            v-model="locationForm.work_end" 
                                            class="w-full text-xs rounded-xl border border-theme bg-sidebar px-2.5 py-1.5 text-slate-800 dark:text-slate-200"
                                        />
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="p-4 border-t border-subtle bg-card-subtle flex items-center justify-end gap-2.5">
                            <button 
                                type="button" 
                                @click="closeLocationModal"
                                class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200/50 transition-colors cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="locationForm.processing"
                                class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-md hover:shadow-emerald-600/20 active:scale-95 transition-all cursor-pointer disabled:opacity-50"
                            >
                                <svg v-if="locationForm.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <span>Simpan Titik Lokasi &amp; Radius</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ── MODAL 2: DETAIL & EDIT PRESENSI (KHUSUS SUPERADMIN) ────────── -->
            <div 
                v-if="selectedDetailModal" 
                class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/75 backdrop-blur-xs overflow-y-auto"
                @click="closeCellDetail"
            >
                <div class="relative bg-sidebar max-w-lg w-full rounded-3xl overflow-hidden shadow-2xl border border-theme my-8" @click.stop>
                    
                    <!-- Modal Header -->
                    <div class="p-5 border-b border-subtle flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-black text-slate-800 dark:text-slate-100">{{ selectedDetailModal.userName }}</h3>
                                <span 
                                    v-if="canEditAttendance" 
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300 border border-purple-200 dark:border-purple-800"
                                >
                                    <span>👑 Super Admin</span>
                                </span>
                            </div>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Tanggal: <strong class="text-slate-700 dark:text-slate-300">{{ selectedDetailModal.date }}</strong> 
                                <span v-if="selectedDetailModal.userDivisi">· Divisi: {{ selectedDetailModal.userDivisi }}</span>
                            </p>
                        </div>
                        <button 
                            type="button" 
                            @click="closeCellDetail"
                            class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- ══════════ TAMPILAN KHUSUS SUPERADMIN: FORM EDIT ══════════ -->
                    <form v-if="canEditAttendance" @submit.prevent="submitEditAttendance">
                        <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">

                            <!-- Jadwal Kerja Pegawai -->
                            <div class="flex items-center justify-between p-3 rounded-2xl bg-card-subtle border border-theme/60 text-xs">
                                <div>
                                    <span class="text-slate-400 font-bold block text-[10px] uppercase">Jadwal Kerja Efektif</span>
                                    <span class="font-mono font-bold text-slate-700 dark:text-slate-200">{{ selectedDetailModal.schedule }}</span>
                                </div>
                                <span v-if="selectedDetailModal.record?.is_pulang_cepat" class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300">
                                    Pulang Cepat
                                </span>
                            </div>

                            <!-- Input Jam Masuk & Jam Pulang -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">
                                        Jam Masuk
                                    </label>
                                    <div class="relative">
                                        <input 
                                            type="time" 
                                            v-model="editForm.time_in"
                                            class="w-full text-sm font-mono font-bold rounded-xl border border-theme bg-card-subtle px-3 py-2 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500"
                                        />
                                    </div>
                                    <span v-if="selectedDetailModal.record?.distance_in !== null" class="block text-[10px] text-emerald-600 mt-1 font-semibold">
                                        📍 Masuk: {{ selectedDetailModal.record.distance_in }}m
                                    </span>
                                    <p v-if="editForm.errors.time_in" class="text-[10px] text-rose-500 mt-0.5">{{ editForm.errors.time_in }}</p>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">
                                        Jam Pulang
                                    </label>
                                    <div class="relative">
                                        <input 
                                            type="time" 
                                            v-model="editForm.time_out"
                                            class="w-full text-sm font-mono font-bold rounded-xl border border-theme bg-card-subtle px-3 py-2 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500"
                                        />
                                    </div>
                                    <span v-if="selectedDetailModal.record?.distance_out !== null" class="block text-[10px] text-rose-600 mt-1 font-semibold">
                                        📍 Pulang: {{ selectedDetailModal.record.distance_out }}m
                                    </span>
                                    <p v-if="editForm.errors.time_out" class="text-[10px] text-rose-500 mt-0.5">{{ editForm.errors.time_out }}</p>
                                </div>
                            </div>

                            <!-- Pilihan Status Kehadiran -->
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">
                                    Status Kehadiran
                                </label>
                                <select 
                                    v-model="editForm.status"
                                    class="w-full text-xs font-bold rounded-xl border border-theme bg-card-subtle px-3 py-2.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500"
                                    required
                                >
                                    <option value="hadir">Hadir (Tepat Waktu)</option>
                                    <option value="terlambat">Terlambat</option>
                                    <option value="dinas_luar">Dinas / Tugas Luar</option>
                                    <option value="izin">Izin</option>
                                    <option value="sakit">Sakit</option>
                                    <option value="alpa">Alpa (Tanpa Keterangan)</option>
                                </select>
                                <p v-if="editForm.errors.status" class="text-[10px] text-rose-500 mt-0.5">{{ editForm.errors.status }}</p>
                            </div>

                            <!-- Catatan / Keterangan Koreksi -->
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">
                                    Catatan / Keterangan
                                </label>
                                <textarea 
                                    v-model="editForm.notes"
                                    rows="2"
                                    class="w-full text-xs rounded-xl border border-theme bg-card-subtle px-3 py-2 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500"
                                    placeholder="Contoh: Koreksi jam masuk oleh Superadmin / Tugas lapangan..."
                                ></textarea>
                                <p v-if="editForm.errors.notes" class="text-[10px] text-rose-500 mt-0.5">{{ editForm.errors.notes }}</p>
                            </div>

                            <!-- Foto Selfie (jika sebelumnya ada dinas luar) -->
                            <div v-if="selectedDetailModal.record?.photo_in" class="p-3 rounded-2xl bg-card-subtle border border-theme/60 space-y-1.5">
                                <p class="text-[10px] font-bold uppercase text-slate-400">Bukti Foto Selfie Masuk:</p>
                                <div class="rounded-xl overflow-hidden border border-theme bg-black/20 flex items-center justify-center p-1">
                                    <img :src="`/storage/${selectedDetailModal.record.photo_in}`" class="max-h-40 w-auto rounded-lg object-contain" />
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer Superadmin -->
                        <div class="p-4 border-t border-subtle bg-card-subtle flex items-center justify-between gap-2">
                            <div>
                                <button 
                                    v-if="editForm.id" 
                                    type="button" 
                                    @click="deleteAttendanceRecord"
                                    :disabled="isDeletingAttendance || editForm.processing"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors cursor-pointer disabled:opacity-50"
                                    title="Hapus record presensi ini"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    <span>Hapus</span>
                                </button>
                            </div>

                            <div class="flex items-center gap-2">
                                <button 
                                    type="button" 
                                    @click="closeCellDetail"
                                    class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200/50 transition-colors cursor-pointer"
                                >
                                    Batal
                                </button>
                                <button 
                                    type="submit" 
                                    :disabled="editForm.processing"
                                    class="inline-flex items-center gap-1.5 px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-md hover:shadow-emerald-600/20 active:scale-95 transition-all cursor-pointer disabled:opacity-50"
                                >
                                    <svg v-if="editForm.processing" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Simpan Presensi</span>
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- ══════════ TAMPILAN NON-SUPERADMIN: READ ONLY ══════════ -->
                    <div v-else>
                        <div class="p-5 space-y-4">
                            <!-- Status Badge -->
                            <div class="flex items-center justify-between p-3 rounded-2xl bg-card-subtle border border-theme/60">
                                <span class="text-xs text-slate-500 font-bold">Status Kehadiran</span>
                                <div class="flex items-center gap-1.5">
                                    <span 
                                        :class="[
                                            'px-3 py-1 rounded-full text-xs font-black uppercase',
                                            selectedDetailModal.record?.status === 'hadir' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' :
                                            selectedDetailModal.record?.status === 'terlambat' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' :
                                            selectedDetailModal.record?.status === 'dinas_luar' ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300' :
                                            'bg-rose-100 text-rose-700'
                                        ]"
                                    >
                                        {{ (selectedDetailModal.record?.status || 'belum_hadir').replace('_', ' ') }}
                                    </span>
                                    <span 
                                        v-if="selectedDetailModal.record?.is_pulang_cepat" 
                                        class="px-2.5 py-1 rounded-full text-xs font-black uppercase bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300"
                                    >
                                        Pulang Cepat
                                    </span>
                                </div>
                            </div>

                            <!-- Jam Masuk & Pulang -->
                            <div class="grid grid-cols-2 gap-3">
                                <div class="p-3.5 rounded-2xl bg-card-subtle border border-theme/60">
                                    <p class="text-[10px] font-bold uppercase text-slate-400">Jam Masuk</p>
                                    <p class="text-base font-black text-slate-800 dark:text-slate-100 mt-0.5">
                                        {{ selectedDetailModal.record?.time_in || '--:--' }}
                                    </p>
                                    <p v-if="selectedDetailModal.record?.distance_in !== null" class="text-[10px] text-emerald-600 mt-1 font-semibold">
                                        📍 Jarak: {{ selectedDetailModal.record.distance_in }}m
                                    </p>
                                </div>
                                <div class="p-3.5 rounded-2xl bg-card-subtle border border-theme/60">
                                    <p class="text-[10px] font-bold uppercase text-slate-400">Jam Pulang</p>
                                    <p class="text-base font-black text-slate-800 dark:text-slate-100 mt-0.5">
                                        {{ selectedDetailModal.record?.time_out || '--:--' }}
                                    </p>
                                    <p v-if="selectedDetailModal.record?.distance_out !== null" class="text-[10px] text-rose-600 mt-1 font-semibold">
                                        📍 Jarak: {{ selectedDetailModal.record.distance_out }}m
                                    </p>
                                </div>
                            </div>

                            <!-- Foto Selfie jika ada -->
                            <div v-if="selectedDetailModal.record?.photo_in" class="space-y-1.5">
                                <p class="text-xs font-bold text-slate-600 dark:text-slate-300">Foto Selfie Bukti Presensi (Luar Radius):</p>
                                <div class="rounded-2xl overflow-hidden border border-theme bg-black/30 flex items-center justify-center p-2">
                                    <img :src="`/storage/${selectedDetailModal.record.photo_in}`" class="max-h-56 w-auto rounded-xl object-contain" />
                                </div>
                            </div>

                            <!-- Catatan / Alasan Dinas Luar -->
                            <div v-if="selectedDetailModal.record?.notes" class="p-3 rounded-2xl bg-blue-50/60 dark:bg-blue-950/30 border border-blue-100 dark:border-blue-900/40">
                                <p class="text-[10px] font-bold uppercase text-blue-600 mb-0.5">Catatan Pegawai:</p>
                                <p class="text-xs text-slate-700 dark:text-slate-300">{{ selectedDetailModal.record.notes }}</p>
                            </div>
                        </div>

                        <div class="p-4 border-t border-subtle bg-card-subtle text-right">
                            <button 
                                type="button" 
                                @click="closeCellDetail"
                                class="px-5 py-2 rounded-xl bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold hover:bg-slate-300 transition-colors cursor-pointer"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
