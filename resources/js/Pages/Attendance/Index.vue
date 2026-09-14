<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const props = defineProps({
    todayAttendance: Object,
    monthlyAttendances: Array,
    stats: Object,
    currentMonth: String,
    schedule: Object,
    officeLocation: Object,
    serverTime: String,
    serverDate: String,
})

// ─── Live Clock Waktu Server ────────────────────────────────────────────────
const currentTime = ref(props.serverTime || '07:00:00')
let clockInterval = null

const startLiveClock = () => {
    let [hours, minutes, seconds] = currentTime.value.split(':').map(Number)
    clockInterval = setInterval(() => {
        seconds++
        if (seconds >= 60) {
            seconds = 0
            minutes++
            if (minutes >= 60) {
                minutes = 0
                hours = (hours + 1) % 24
            }
        }
        currentTime.value = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
    }, 1000)
}

// ─── Geolocation Tracking ───────────────────────────────────────────────────
const userLat = ref(null)
const userLng = ref(null)
const userAccuracy = ref(null)
const gpsError = ref(null)
const isGpsLoading = ref(true)

const calculateDistance = (lat1, lon1, lat2, lon2) => {
    if (!lat1 || !lon1 || !lat2 || !lon2) return null
    const R = 6371000 // meter
    const dLat = (lat2 - lat1) * Math.PI / 180
    const dLon = (lon2 - lon1) * Math.PI / 180
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon / 2) * Math.sin(dLon / 2)
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
    return Math.round(R * c)
}

const currentDistance = computed(() => {
    if (!userLat.value || !userLng.value || !props.officeLocation) return null
    return calculateDistance(
        userLat.value,
        userLng.value,
        props.officeLocation.latitude,
        props.officeLocation.longitude
    )
})

const isInsideRadius = computed(() => {
    if (currentDistance.value === null || !props.officeLocation) return false
    return currentDistance.value <= props.officeLocation.radius
})

const refreshGpsLocation = () => {
    isGpsLoading.value = true
    gpsError.value = null

    if (!navigator.geolocation) {
        gpsError.value = 'Browser Anda tidak mendukung deteksi lokasi GPS.'
        isGpsLoading.value = false
        return
    }

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            userLat.value = pos.coords.latitude
            userLng.value = pos.coords.longitude
            userAccuracy.value = Math.round(pos.coords.accuracy)
            isGpsLoading.value = false
        },
        (err) => {
            let msg = 'Gagal mendeteksi lokasi GPS.'
            if (err.code === 1) msg = 'Izin lokasi ditolak. Aktifkan GPS browser Anda.'
            else if (err.code === 2) msg = 'Sinyal lokasi tidak tersedia.'
            else if (err.code === 3) msg = 'Waktu permintaan lokasi habis.'
            gpsError.value = msg
            isGpsLoading.value = false
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 5000 }
    )
}

// ─── Leaflet Mini Map (Titik Lokasi Sekolah & User) ──────────────────────────
let userMapInstance = null
let schoolMarker = null
let schoolCircle = null
let userMapMarker = null

const initAttendanceMap = () => {
    const mapEl = document.getElementById('user-attendance-minimap')
    if (!mapEl) return

    if (userMapInstance) {
        userMapInstance.remove()
        userMapInstance = null
    }

    const schoolLat = Number(props.officeLocation?.latitude) || -7.7956
    const schoolLng = Number(props.officeLocation?.longitude) || 110.3695
    const schoolRadius = Number(props.officeLocation?.radius) || 100

    userMapInstance = L.map('user-attendance-minimap', {
        center: [schoolLat, schoolLng],
        zoom: 16,
        scrollWheelZoom: false,
    })

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(userMapInstance)

    // 1. School Marker
    const schoolIcon = L.divIcon({
        className: 'school-marker',
        html: `
            <div style="background-color: #059669; color: white; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(5,150,105,0.5); border: 2.5px solid white; transform: translate(-50%, -50%);">
                <svg style="width: 18px; height: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
            </div>
        `,
        iconSize: [34, 34],
        iconAnchor: [0, 0]
    })

    schoolMarker = L.marker([schoolLat, schoolLng], { icon: schoolIcon }).addTo(userMapInstance)
    schoolMarker.bindPopup('<b>Titik Sekolah</b>')

    schoolCircle = L.circle([schoolLat, schoolLng], {
        radius: schoolRadius,
        color: '#059669',
        fillColor: '#10B981',
        fillOpacity: 0.2,
        weight: 2
    }).addTo(userMapInstance)

    updateUserMarkerOnMap()

    setTimeout(() => {
        userMapInstance?.invalidateSize()
    }, 350)
}

const updateUserMarkerOnMap = () => {
    if (!userMapInstance || !userLat.value || !userLng.value) return

    const schoolLat = Number(props.officeLocation?.latitude) || -7.7956
    const schoolLng = Number(props.officeLocation?.longitude) || 110.3695

    const userIcon = L.divIcon({
        className: 'user-marker',
        html: `
            <div style="position: relative; transform: translate(-50%, -50%);">
                <div style="position: absolute; width: 28px; height: 28px; border-radius: 50%; background-color: rgba(59, 130, 246, 0.4); animation: ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite; top: -7px; left: -7px;"></div>
                <div style="background-color: #2563eb; color: white; width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(37,99,235,0.5); border: 2.5px solid white; position: relative;">
                    <div style="width: 8px; height: 8px; background-color: white; border-radius: 50%;"></div>
                </div>
            </div>
        `,
        iconSize: [26, 26],
        iconAnchor: [0, 0]
    })

    if (!userMapMarker) {
        userMapMarker = L.marker([userLat.value, userLng.value], { icon: userIcon }).addTo(userMapInstance)
        userMapMarker.bindPopup('<b>Posisi Anda</b>')
    } else {
        userMapMarker.setLatLng([userLat.value, userLng.value])
    }

    // Fit bounds agar titik sekolah dan user terlihat bersama
    const bounds = L.latLngBounds([
        [schoolLat, schoolLng],
        [userLat.value, userLng.value]
    ])
    userMapInstance.fitBounds(bounds, { padding: [35, 35], maxZoom: 18 })
}

watch([userLat, userLng], () => {
    if (userMapInstance) {
        updateUserMarkerOnMap()
    }
})

// ─── Format Tanggal Riwayat (Hari, Tanggal Bulan Tahun) ─────────────────────
const formatRecordDate = (rawDate) => {
    if (!rawDate) return '-'
    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
    
    // Jika string memiliki format YYYY-MM-DD (bisa ada T timestamp)
    if (typeof rawDate === 'string' && rawDate.includes('-')) {
        const datePart = rawDate.split('T')[0]
        const parts = datePart.split('-')
        if (parts.length === 3) {
            const year = parseInt(parts[0], 10)
            const monthIdx = parseInt(parts[1], 10) - 1
            const day = parseInt(parts[2], 10)
            const localDate = new Date(year, monthIdx, day)
            const dayName = days[localDate.getDay()]
            const formattedDay = String(day).padStart(2, '0')
            const monthName = months[monthIdx] || ''
            return `${dayName}, ${formattedDay} ${monthName} ${year}`
        }
    }

    const d = new Date(rawDate)
    if (isNaN(d.getTime())) return rawDate
    const dayName = days[d.getDay()]
    const formattedDay = String(d.getDate()).padStart(2, '0')
    const monthName = months[d.getMonth()]
    const year = d.getFullYear()
    return `${dayName}, ${formattedDay} ${monthName} ${year}`
}

// ─── Kamera Facecapture (Khusus Luar Radius) ────────────────────────────────
const isCameraModalOpen = ref(false)
const cameraStream = ref(null)
const videoRef = ref(null)
const capturedPhoto = ref(null)
const actionType = ref('checkin') // 'checkin' | 'checkout'
const isCameraLoading = ref(false)
const cameraError = ref(null)

const openCameraModal = (type = 'checkin') => {
    actionType.value = type
    capturedPhoto.value = null
    cameraError.value = null
    isCameraModalOpen.value = true
    startCamera()
}

const closeCameraModal = () => {
    stopCamera()
    isCameraModalOpen.value = false
    capturedPhoto.value = null
}

const startCamera = async () => {
    isCameraLoading.value = true
    cameraError.value = null
    try {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false
            })
            cameraStream.value = stream
            if (videoRef.value) {
                videoRef.value.srcObject = stream
            }
        } else {
            cameraError.value = 'Kamera tidak didukung pada perangkat ini.'
        }
    } catch (e) {
        cameraError.value = 'Tidak dapat mengakses kamera: ' + (e.message || 'Izin kamera ditolak.')
    } finally {
        isCameraLoading.value = false
    }
}

const stopCamera = () => {
    if (cameraStream.value) {
        cameraStream.value.getTracks().forEach(track => track.stop())
        cameraStream.value = null
    }
}

const takeSnapshot = () => {
    if (!videoRef.value) return
    const video = videoRef.value
    const canvas = document.createElement('canvas')
    canvas.width = video.videoWidth || 640
    canvas.height = video.videoHeight || 480
    const ctx = canvas.getContext('2d')
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height)
    capturedPhoto.value = canvas.toDataURL('image/jpeg', 0.8)
    stopCamera()
}

const retakePhoto = () => {
    capturedPhoto.value = null
    startCamera()
}

// ─── Form Submission ────────────────────────────────────────────────────────
const checkInForm = useForm({
    latitude: null,
    longitude: null,
    photo: null,
    notes: '',
})

// ─── Deteksi Lembur (> 60 Menit Setelah Jam Kerja Pulang) ───────────────────
const isOvertimeModalOpen = ref(false)
const pendingCheckoutPhoto = ref(null)

const minutesPastWorkEnd = computed(() => {
    if (!props.schedule?.work_end) return 0
    const [endHours, endMinutes] = props.schedule.work_end.split(':').map(Number)
    const [currentHours, currentMinutes] = currentTime.value.split(':').map(Number)

    const endTotalMinutes = endHours * 60 + endMinutes
    const currentTotalMinutes = currentHours * 60 + currentMinutes

    return Math.max(0, currentTotalMinutes - endTotalMinutes)
})

const isOvertimeThresholdExceeded = computed(() => {
    return minutesPastWorkEnd.value > 60
})

const checkOutForm = useForm({
    latitude: null,
    longitude: null,
    photo: null,
    is_overtime: false,
    overtime_activity: '',
})

const handleCheckInDirect = () => {
    if (!userLat.value || !userLng.value) {
        alert('Menunggu titik koordinat GPS...')
        return
    }
    checkInForm.latitude = userLat.value
    checkInForm.longitude = userLng.value
    checkInForm.photo = null
    checkInForm.notes = ''
    checkInForm.post(route('attendance.check-in'))
}

const handleCheckInWithFace = () => {
    if (!capturedPhoto.value) {
        alert('Silakan ambil foto selfie terlebih dahulu.')
        return
    }
    checkInForm.latitude = userLat.value
    checkInForm.longitude = userLng.value
    checkInForm.photo = capturedPhoto.value
    checkInForm.post(route('attendance.check-in'), {
        onSuccess: () => closeCameraModal()
    })
}

const initiateCheckOut = (photo = null) => {
    if (!userLat.value || !userLng.value) {
        alert('Menunggu titik koordinat GPS...')
        return
    }

    pendingCheckoutPhoto.value = photo

    // Jika melebihi 60 menit setelah jam kerja pulang, buka modal konfirmasi lembur
    if (isOvertimeThresholdExceeded.value) {
        checkOutForm.is_overtime = false
        checkOutForm.overtime_activity = ''
        closeCameraModal()
        isOvertimeModalOpen.value = true
    } else {
        submitFinalCheckOut()
    }
}

const submitFinalCheckOut = () => {
    if (checkOutForm.is_overtime && !checkOutForm.overtime_activity.trim()) {
        alert('Silakan tuliskan kegiatan lembur Anda.')
        return
    }

    checkOutForm.latitude = userLat.value
    checkOutForm.longitude = userLng.value
    checkOutForm.photo = pendingCheckoutPhoto.value

    checkOutForm.post(route('attendance.check-out'), {
        preserveScroll: true,
        onSuccess: () => {
            isOvertimeModalOpen.value = false
            closeCameraModal()
        }
    })
}

const handleCheckOutDirect = () => {
    initiateCheckOut(null)
}

const handleCheckOutWithFace = () => {
    if (!capturedPhoto.value) {
        alert('Silakan ambil foto selfie terlebih dahulu.')
        return
    }
    initiateCheckOut(capturedPhoto.value)
}

// Filter Bulan
const selectedMonth = ref(props.currentMonth || '')
const changeMonth = () => {
    router.get(route('attendance.index'), { month: selectedMonth.value }, { preserveState: true, preserveScroll: true })
}

onMounted(() => {
    startLiveClock()
    refreshGpsLocation()
    nextTick(() => {
        initAttendanceMap()
    })
})

onUnmounted(() => {
    if (clockInterval) clearInterval(clockInterval)
    stopCamera()
    if (userMapInstance) {
        userMapInstance.remove()
        userMapInstance = null
    }
})
</script>

<template>
    <Head title="Presensi GPS" />

    <AuthenticatedLayout>
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- ── Header Banner Waktu & Status Presensi ──────────────────────── -->
            <div class="bg-gradient-to-br from-emerald-600 to-teal-700 rounded-3xl p-6 sm:p-7 text-white shadow-xl relative overflow-hidden">
                <!-- Background Pattern Deco -->
                <div class="absolute -right-6 -bottom-6 w-44 h-44 rounded-full bg-white/10 blur-2xl pointer-events-none" />
                <div class="absolute right-6 top-6 opacity-15">
                    <svg class="w-32 h-32" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>

                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-5">
                    <div>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/20 backdrop-blur-md text-xs font-bold mb-2.5 tracking-wide">
                            <span class="w-2 h-2 rounded-full bg-emerald-300 animate-pulse" />
                            Presensi Berbasis GPS &amp; Waktu
                        </span>
                        <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Presensi Kehadiran</h1>
                        <p class="text-emerald-100 text-xs sm:text-sm mt-0.5">{{ serverDate }}</p>
                    </div>

                    <!-- Digital Live Clock Display -->
                    <div class="bg-black/25 backdrop-blur-md px-6 py-3.5 rounded-2xl border border-white/15 flex flex-col items-center justify-center self-start md:self-auto shadow-inner">
                        <span class="text-[10px] font-bold text-emerald-200 uppercase tracking-widest mb-0.5">Waktu Server (WIB)</span>
                        <div class="text-3xl sm:text-4xl font-black font-mono tracking-widest text-white leading-none">
                            {{ currentTime }}
                        </div>
                        <div class="flex items-center gap-1.5 mt-1 text-[11px] text-emerald-100 font-medium">
                            <span>Jadwal: {{ schedule?.work_start }} - {{ schedule?.work_end }}</span>
                            <span v-if="schedule?.is_piket" class="bg-amber-400 text-amber-950 px-2 py-0.5 rounded-md text-[9px] font-black uppercase flex items-center gap-1 shadow-xs">
                                <span>🛡️ PIKET HARI INI</span>
                            </span>
                            <span v-else-if="schedule?.is_custom" class="bg-amber-400/90 text-amber-950 px-1.5 py-0.2 rounded text-[9px] font-black uppercase">Khusus</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Grid 2 Kolom Maksimalkan Ruang ────────────────────────────── -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- ── KOLOM KIRI (7 Kolom): Status Presensi Hari Ini + Rekap 4 Statistik Terpadu ── -->
                <div class="lg:col-span-7 space-y-6">
                    <div class="bg-sidebar rounded-3xl p-6 sm:p-7 border border-theme shadow-xs flex flex-col justify-between">
                        
                        <!-- Header Status Hari Ini -->
                        <div class="flex items-center justify-between pb-3.5 border-b border-subtle">
                            <div>
                                <h2 class="text-base sm:text-lg font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                    <span>Status Presensi Hari Ini</span>
                                </h2>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    Jadwal: {{ schedule?.work_start }} - {{ schedule?.work_end }}
                                    <span v-if="schedule?.is_piket" class="text-amber-600 dark:text-amber-400 font-bold ml-1">({{ schedule?.piket_name || 'Petugas Piket' }})</span>
                                </p>
                            </div>
                            <span 
                                v-if="todayAttendance" 
                                :class="[
                                    'text-xs font-black uppercase px-3 py-1 rounded-full',
                                    todayAttendance.status === 'hadir' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' :
                                    todayAttendance.status === 'terlambat' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300' :
                                    'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300'
                                ]"
                            >
                                {{ todayAttendance.status }}
                            </span>
                            <span v-else class="text-xs font-bold uppercase px-3 py-1 rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                Belum Presensi
                            </span>
                        </div>

                        <!-- Ringkasan Jam Masuk & Pulang -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 my-4">
                            <!-- Kartu Masuk -->
                            <div class="p-4 rounded-2xl bg-card-subtle border border-theme/60 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-500/15 text-emerald-600 flex items-center justify-center">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Presensi Masuk</p>
                                        <p class="text-lg font-black text-slate-800 dark:text-slate-100">
                                            {{ todayAttendance?.time_in ? todayAttendance.time_in.substring(0, 5) : '-- : --' }}
                                        </p>
                                    </div>
                                </div>
                                <div v-if="todayAttendance?.distance_in !== null && todayAttendance?.distance_in !== undefined" class="text-right">
                                    <span class="text-[10px] font-semibold text-slate-400">Jarak</span>
                                    <p class="text-xs font-bold text-emerald-600">{{ todayAttendance.distance_in }}m</p>
                                </div>
                            </div>

                            <!-- Kartu Pulang -->
                            <div class="p-4 rounded-2xl bg-card-subtle border border-theme/60 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-rose-500/15 text-rose-600 flex items-center justify-center">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Presensi Pulang</p>
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <p class="text-lg font-black text-slate-800 dark:text-slate-100">
                                                {{ todayAttendance?.time_out ? todayAttendance.time_out.substring(0, 5) : '-- : --' }}
                                            </p>
                                            <span v-if="todayAttendance?.is_overtime" class="px-2 py-0.5 rounded-md text-[10px] font-black bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                                ⚡ Lembur {{ todayAttendance.overtime_minutes }}m
                                            </span>
                                        </div>
                                        <p v-if="todayAttendance?.is_overtime && todayAttendance?.overtime_activity" class="text-[10px] text-amber-600 dark:text-amber-400 font-medium italic mt-0.5 truncate max-w-[180px]" :title="todayAttendance.overtime_activity">
                                            "{{ todayAttendance.overtime_activity }}"
                                        </p>
                                    </div>
                                </div>
                                <div v-if="todayAttendance?.distance_out !== null && todayAttendance?.distance_out !== undefined" class="text-right">
                                    <span class="text-[10px] font-semibold text-slate-400">Jarak</span>
                                    <p class="text-xs font-bold text-rose-600">{{ todayAttendance.distance_out }}m</p>
                                </div>
                            </div>
                        </div>

                        <!-- Tombol Aksi Dinamis Sesuai Tahapan -->
                        <!-- 1. Belum Masuk -->
                        <div v-if="!todayAttendance || !todayAttendance.time_in" class="space-y-2 mb-4">
                            <button
                                v-if="isInsideRadius"
                                type="button"
                                @click="handleCheckInDirect"
                                :disabled="checkInForm.processing || isGpsLoading"
                                class="w-full py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm sm:text-base shadow-md hover:shadow-emerald-500/20 active:scale-[0.99] transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                            >
                                <svg v-if="checkInForm.processing" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>PRESENSI MASUK SEKARANG</span>
                            </button>

                            <div v-else class="space-y-1.5">
                                <button
                                    type="button"
                                    @click="openCameraModal('checkin')"
                                    :disabled="isGpsLoading"
                                    class="w-full py-3.5 rounded-2xl bg-amber-600 hover:bg-amber-700 text-white font-black text-sm sm:text-base shadow-md hover:shadow-amber-500/20 active:scale-[0.99] transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                                >
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span>PRESENSI DINAS LUAR (FOTO SELFIE)</span>
                                </button>
                                <p class="text-[11px] text-center text-slate-400">Posisi di luar area sekolah, sistem mewajibkan verifikasi foto selfie &amp; catatan.</p>
                            </div>
                        </div>

                        <!-- 2. Sudah Masuk, Belum Pulang -->
                        <div v-else-if="!todayAttendance.time_out" class="space-y-2 mb-4">
                            <button
                                v-if="isInsideRadius"
                                type="button"
                                @click="handleCheckOutDirect"
                                :disabled="checkOutForm.processing || isGpsLoading"
                                class="w-full py-3.5 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white font-black text-sm sm:text-base shadow-md hover:shadow-rose-500/20 active:scale-[0.99] transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                            >
                                <svg v-if="checkOutForm.processing" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                <span>PRESENSI PULANG SEKARANG</span>
                            </button>

                            <button
                                v-else
                                type="button"
                                @click="openCameraModal('checkout')"
                                :disabled="isGpsLoading"
                                class="w-full py-3.5 rounded-2xl bg-amber-600 hover:bg-amber-700 text-white font-black text-sm sm:text-base shadow-md hover:shadow-amber-500/20 active:scale-[0.99] transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                            >
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span>PRESENSI PULANG DI LUAR AREA (FOTO SELFIE)</span>
                            </button>
                        </div>

                        <!-- 3. Sudah Selesai Lengkap -->
                        <div v-else class="p-3 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-center mb-4">
                            <p class="text-emerald-800 dark:text-emerald-200 font-bold text-xs sm:text-sm">
                                🎉 Alhamdulillah, presensi hari ini telah lengkap (Masuk &amp; Pulang).
                            </p>
                        </div>

                        <!-- ── Rekap Akumulasi Bulanan (Disatukan di Card Ini) ── -->
                        <div class="pt-4 border-t border-subtle">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Rekap Kehadiran Bulan Ini</span>
                                <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 font-mono">{{ selectedMonth }}</span>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                <div class="p-2.5 bg-card-subtle rounded-xl border border-theme/60 text-center">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tight">Tepat Waktu</p>
                                    <p class="text-lg font-black text-emerald-600 mt-0.5">{{ stats?.total_hadir || 0 }}</p>
                                </div>
                                <div class="p-2.5 bg-card-subtle rounded-xl border border-theme/60 text-center">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tight">Terlambat</p>
                                    <p class="text-lg font-black text-amber-600 mt-0.5">{{ stats?.total_terlambat || 0 }}</p>
                                </div>
                                <div class="p-2.5 bg-card-subtle rounded-xl border border-theme/60 text-center">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tight">Dinas Luar</p>
                                    <p class="text-lg font-black text-blue-600 mt-0.5">{{ stats?.total_dinas_luar || 0 }}</p>
                                </div>
                                <div class="p-2.5 bg-card-subtle rounded-xl border border-theme/60 text-center">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tight">Izin / Sakit</p>
                                    <p class="text-lg font-black text-purple-600 mt-0.5">{{ stats?.total_izin_sakit || 0 }}</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ── KOLOM KANAN (5 Kolom): Radius Sekolah & Minimap GPS User + Titik Absen ── -->
                <div class="lg:col-span-5 space-y-6">
                    <div class="bg-sidebar rounded-3xl p-5 sm:p-6 border border-theme shadow-xs space-y-3.5">
                        
                        <!-- Header Radar GPS -->
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <h2 class="text-sm sm:text-base font-black text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
                                    <span>Radar Lokasi GPS Presensi</span>
                                </h2>
                                <p class="text-[11px] text-slate-400 mt-0.5">Titik absen sekolah dan posisi Anda</p>
                            </div>
                            <button
                                type="button"
                                @click="refreshGpsLocation"
                                :disabled="isGpsLoading"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-[11px] font-bold text-slate-700 dark:text-slate-200 bg-card-subtle hover:bg-emerald-50 dark:hover:bg-emerald-950/40 border border-theme/80 transition-all active:scale-95 cursor-pointer flex-shrink-0"
                            >
                                <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': isGpsLoading }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <span>Perbarui</span>
                            </button>
                        </div>

                        <!-- Container Leaflet Mini Map -->
                        <div class="rounded-2xl overflow-hidden border border-theme relative shadow-inner bg-slate-100 dark:bg-slate-900">
                            <div id="user-attendance-minimap" class="h-48 sm:h-52 w-full z-10" />
                            <!-- Legend Overlay -->
                            <div class="absolute bottom-2 left-2 z-20 bg-black/65 backdrop-blur-md text-white px-2.5 py-1 rounded-xl text-[10px] font-medium flex items-center gap-3">
                                <span class="flex items-center gap-1">
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block border border-white/60"></span> Sekolah
                                </span>
                                <span class="flex items-center gap-1">
                                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500 inline-block border border-white/60"></span> Posisi Anda
                                </span>
                            </div>
                        </div>

                        <!-- Keterangan Radius & Jarak -->
                        <div class="p-3.5 rounded-2xl bg-card-subtle border border-theme/60 space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-100">
                                    {{ isGpsLoading ? 'Mendeteksi GPS...' : (isInsideRadius ? 'Dalam Area Radius Sekolah' : 'Di Luar Area Radius') }}
                                </span>
                                <span 
                                    v-if="!isGpsLoading && currentDistance !== null"
                                    :class="[
                                        'px-2 py-0.5 rounded-full text-[10px] font-black uppercase font-mono',
                                        isInsideRadius ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                                    ]"
                                >
                                    {{ currentDistance }} meter
                                </span>
                            </div>

                            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                                <template v-if="gpsError">
                                    <span class="text-rose-500 font-semibold">{{ gpsError }}</span>
                                </template>
                                <template v-else-if="isGpsLoading">
                                    Menghubungkan ke satelit GPS untuk sinkronisasi posisi...
                                </template>
                                <template v-else-if="isInsideRadius">
                                    Jarak Anda <strong>{{ currentDistance }}m</strong> dari titik sekolah (Radius maksimal: {{ officeLocation?.radius }}m). Anda dapat langsung presensi tanpa foto.
                                </template>
                                <template v-else>
                                    Jarak Anda <strong>{{ currentDistance }}m</strong> dari titik sekolah (Radius maksimal: {{ officeLocation?.radius }}m). Wajib menyertakan foto selfie kamera (dinas luar).
                                </template>
                            </p>
                        </div>

                    </div>
                </div>

            </div>

            <!-- ── Riwayat Presensi Saya (Tabel Lengkap Full Width) ─────────────── -->
            <div class="bg-sidebar rounded-3xl p-6 sm:p-7 border border-theme shadow-xs space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-slate-800 dark:text-slate-100">Riwayat Presensi Saya</h3>
                        <p class="text-xs text-slate-400">Daftar kehadiran harian dalam bulan terpilih</p>
                    </div>

                    <!-- Filter Bulan -->
                    <input 
                        type="month" 
                        v-model="selectedMonth" 
                        @change="changeMonth"
                        class="text-xs rounded-xl border border-theme bg-card-subtle px-3 py-1.5 text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 cursor-pointer"
                    />
                </div>

                <!-- Tabel Riwayat -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-subtle bg-card-subtle text-slate-400 uppercase tracking-wider text-[10px]">
                                <th class="py-3 px-4 font-bold">Hari &amp; Tanggal</th>
                                <th class="py-3 px-3 font-bold font-mono">Jam Masuk</th>
                                <th class="py-3 px-3 font-bold font-mono">Jam Pulang</th>
                                <th class="py-3 px-3 font-bold">Status</th>
                                <th class="py-3 px-4 font-bold">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-subtle">
                            <tr v-for="att in monthlyAttendances" :key="att.id" class="hover:bg-card-subtle/50 transition-colors">
                                <td class="py-3 px-4 font-bold text-slate-800 dark:text-slate-100 whitespace-nowrap">
                                    {{ formatRecordDate(att.date) }}
                                </td>
                                <td class="py-3 px-3 font-mono">
                                    <template v-if="att.time_in">
                                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ att.time_in.substring(0, 5) }}</span>
                                        <span v-if="att.distance_in !== null" class="text-[10px] text-slate-400 block font-normal">({{ att.distance_in }}m)</span>
                                    </template>
                                    <template v-else>
                                        <span class="text-slate-400">-</span>
                                    </template>
                                </td>
                                <td class="py-3 px-3 font-mono">
                                    <template v-if="att.time_out">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ att.time_out.substring(0, 5) }}</span>
                                            <span v-if="att.is_overtime" class="px-1.5 py-0.5 rounded text-[9px] font-black bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200 font-sans" :title="att.overtime_activity || 'Lembur'">
                                                ⚡ {{ att.overtime_minutes }}m
                                            </span>
                                        </div>
                                        <span v-if="att.distance_out !== null" class="text-[10px] text-slate-400 block font-normal">({{ att.distance_out }}m)</span>
                                    </template>
                                    <template v-else>
                                        <span class="text-slate-400">-</span>
                                    </template>
                                </td>
                                <td class="py-3 px-3">
                                    <span 
                                        :class="[
                                            'px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider',
                                            att.status === 'hadir' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' :
                                            att.status === 'terlambat' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' :
                                            att.status === 'dinas_luar' ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300' :
                                            'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300'
                                        ]"
                                    >
                                        {{ att.status }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-slate-500 max-w-xs">
                                    <div v-if="att.notes" class="truncate">{{ att.notes }}</div>
                                    <div v-if="att.is_overtime && att.overtime_activity" class="text-[11px] text-amber-600 dark:text-amber-400 font-medium truncate" :title="att.overtime_activity">
                                        ⚡ Lembur: {{ att.overtime_activity }}
                                    </div>
                                    <span v-if="!att.notes && (!att.is_overtime || !att.overtime_activity)">-</span>
                                </td>
                            </tr>
                            <tr v-if="!monthlyAttendances || monthlyAttendances.length === 0">
                                <td colspan="5" class="py-10 text-center text-slate-400">
                                    Belum ada data presensi untuk bulan ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- ── Modal Facecapture Kamera Selfie (Dinas Luar) ───────────────────── -->
        <div v-if="isCameraModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-xs" @click="closeCameraModal" />

            <div class="relative z-10 w-full max-w-md bg-sidebar rounded-3xl shadow-2xl border border-theme overflow-hidden flex flex-col">
                <!-- Modal Header -->
                <div class="px-5 py-4 border-b border-subtle flex items-center justify-between bg-card-subtle">
                    <div>
                        <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100">
                            Foto Selfie Verifikasi Lokasi
                        </h3>
                        <p class="text-[11px] text-amber-600 dark:text-amber-400 font-semibold">
                            Presensi {{ actionType === 'checkin' ? 'Masuk' : 'Pulang' }} di Luar Area Sekolah
                        </p>
                    </div>
                    <button 
                        type="button" 
                        @click="closeCameraModal" 
                        class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 hover:text-slate-600 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Modal Body (Camera Live Preview / Snapshot) -->
                <div class="p-5 space-y-4">
                    <div class="relative w-full aspect-4/3 bg-black rounded-2xl overflow-hidden flex items-center justify-center">
                        <!-- Video Kamera Live -->
                        <video 
                            v-show="!capturedPhoto" 
                            ref="videoRef" 
                            autoplay 
                            playsinline 
                            muted 
                            class="w-full h-full object-cover transform -scale-x-100" 
                        />

                        <!-- Hasil Snapshot -->
                        <img 
                            v-if="capturedPhoto" 
                            :src="capturedPhoto" 
                            class="w-full h-full object-cover transform -scale-x-100" 
                            alt="Foto Selfie Presensi"
                        />

                        <!-- Kamera Loading State -->
                        <div v-if="isCameraLoading" class="absolute inset-0 bg-slate-900/75 flex flex-col items-center justify-center text-white gap-2">
                            <svg class="w-6 h-6 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                            <span class="text-xs">Membuka kamera...</span>
                        </div>

                        <!-- Kamera Error Message -->
                        <div v-if="cameraError" class="absolute inset-0 bg-slate-900/90 p-4 flex flex-col items-center justify-center text-center text-rose-300 text-xs">
                            <p class="font-bold mb-2">Gagal Mengakses Kamera</p>
                            <p>{{ cameraError }}</p>
                        </div>
                    </div>

                    <!-- Input Keterangan Dinas Luar (Khusus Check-in) -->
                    <div v-if="actionType === 'checkin'" class="space-y-1">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            Keterangan Kegiatan Dinas Luar <span class="text-rose-500">*</span>
                        </label>
                        <textarea
                            v-model="checkInForm.notes"
                            rows="2"
                            placeholder="Contoh: Mengikuti pelatihan kurikulum di dinas pendidikan / kunjungan dinas..."
                            class="w-full text-xs rounded-xl border border-theme bg-card-subtle p-2.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500"
                        />
                    </div>

                    <!-- Tombol Ambil Foto / Ulangi -->
                    <div class="flex items-center gap-2">
                        <button
                            v-if="!capturedPhoto"
                            type="button"
                            @click="takeSnapshot"
                            :disabled="isCameraLoading || cameraError"
                            class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md transition-all active:scale-95 flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            </svg>
                            <span>AMBIL FOTO SEKARANG</span>
                        </button>

                        <template v-else>
                            <button
                                type="button"
                                @click="retakePhoto"
                                class="px-4 py-3 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl transition-all cursor-pointer"
                            >
                                Ulangi
                            </button>
                            <button
                                type="button"
                                @click="actionType === 'checkin' ? handleCheckInWithFace() : handleCheckOutWithFace()"
                                :disabled="checkInForm.processing || checkOutForm.processing || (actionType === 'checkin' && !checkInForm.notes)"
                                class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md transition-all active:scale-95 flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                            >
                                <span>KIRIM PRESENSI</span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Modal Konfirmasi Lembur (Jika Pulang > 60 Menit Setelah Jam Kerja) ── -->
        <div v-if="isOvertimeModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/65 backdrop-blur-xs" @click="isOvertimeModalOpen = false" />

            <div class="relative z-10 w-full max-w-lg bg-sidebar rounded-3xl shadow-2xl border border-theme overflow-hidden flex flex-col">
                
                <!-- Modal Header -->
                <div class="p-5 border-b border-subtle flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-amber-500/15 text-amber-600 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-800 dark:text-slate-100">
                                Konfirmasi Pulang &amp; Lembur
                            </h3>
                            <p class="text-xs text-slate-400">Pulang lebih dari 60 menit setelah jam kerja</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="isOvertimeModalOpen = false"
                        class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors cursor-pointer"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-5 space-y-4">
                    
                    <!-- Alert Keterangan Waktu -->
                    <div class="p-3.5 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-800/50 text-xs text-amber-950 dark:text-amber-200 leading-relaxed">
                        Anda melakukan presensi pulang pada pukul <strong>{{ currentTime }}</strong>, yaitu <strong>{{ minutesPastWorkEnd }} menit</strong> setelah jam kerja berakhir (Jadwal pulang: <strong>{{ schedule?.work_end }}</strong>).
                    </div>

                    <!-- Pilihan Lembur / Bukan Lembur -->
                    <div class="space-y-2">
                        <label class="block text-xs font-black text-slate-700 dark:text-slate-200 uppercase tracking-wider">
                            Apakah kepulangan ini dihitung sebagai lembur?
                        </label>

                        <div class="grid grid-cols-2 gap-3">
                            <label
                                :class="[
                                    'p-3.5 rounded-2xl border cursor-pointer transition-all flex flex-col justify-between',
                                    !checkOutForm.is_overtime
                                        ? 'border-emerald-500 bg-emerald-50/60 dark:bg-emerald-950/40 text-emerald-950 dark:text-emerald-100 shadow-xs'
                                        : 'border-theme bg-card-subtle text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
                                ]"
                            >
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs font-black">Bukan Lembur</span>
                                    <input
                                        type="radio"
                                        :value="false"
                                        v-model="checkOutForm.is_overtime"
                                        class="text-emerald-600 focus:ring-emerald-500 w-4 h-4"
                                    />
                                </div>
                                <p class="text-[11px] opacity-80 leading-snug">Pulang biasa tanpa tugas lembur.</p>
                            </label>

                            <label
                                :class="[
                                    'p-3.5 rounded-2xl border cursor-pointer transition-all flex flex-col justify-between',
                                    checkOutForm.is_overtime
                                        ? 'border-amber-500 bg-amber-50/60 dark:bg-amber-950/40 text-amber-950 dark:text-amber-100 shadow-xs'
                                        : 'border-theme bg-card-subtle text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
                                ]"
                            >
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs font-black">Ya, Lembur</span>
                                    <input
                                        type="radio"
                                        :value="true"
                                        v-model="checkOutForm.is_overtime"
                                        class="text-amber-600 focus:ring-amber-500 w-4 h-4"
                                    />
                                </div>
                                <p class="text-[11px] opacity-80 leading-snug">Menjalankan kegiatan lembur.</p>
                            </label>
                        </div>
                    </div>

                    <!-- Form Input Kegiatan Lembur (Jika Ya, Lembur Dipilih) -->
                    <div v-if="checkOutForm.is_overtime" class="space-y-1.5 animate-fadeIn">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">
                            Deskripsi / Kegiatan Lembur <span class="text-rose-500">*</span>
                        </label>
                        <textarea
                            v-model="checkOutForm.overtime_activity"
                            rows="3"
                            placeholder="Contoh: Menyelesaikan rekap penilaian tengah semester dan persiapan materi ujian..."
                            class="w-full text-xs rounded-xl border border-theme bg-card-subtle p-3 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                            required
                        ></textarea>
                        <p class="text-[10px] text-slate-400">Jelaskan kegiatan atau pekerjaan yang Anda kerjakan selama jam lembur.</p>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="p-4 bg-card-subtle border-t border-subtle flex items-center justify-end gap-3">
                    <button
                        type="button"
                        @click="isOvertimeModalOpen = false"
                        class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-700 transition-colors cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="submitFinalCheckOut"
                        :disabled="checkOutForm.processing || (checkOutForm.is_overtime && !checkOutForm.overtime_activity.trim())"
                        class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-black text-xs sm:text-sm shadow-md transition-all active:scale-95 disabled:opacity-50 cursor-pointer"
                    >
                        {{ checkOutForm.processing ? 'Memproses...' : 'Kirim Presensi Pulang' }}
                    </button>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
