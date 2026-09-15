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
    late_reason: '',
    late_photo: null,
})

// ─── Deteksi Keterlambatan Masuk (> Jam Masuk + Toleransi) ───────────────────
const isLateCheckInModalOpen = ref(false)
const latePhotoPreview = ref(null)

const isCheckInLate = computed(() => {
    if (!props.schedule?.work_start) return false
    const [startH, startM] = props.schedule.work_start.split(':').map(Number)
    const tolerance = Number(props.schedule.late_tolerance) || 0
    const [currH, currM] = currentTime.value.split(':').map(Number)

    const limitMinutes = startH * 60 + startM + tolerance
    const currentMinutes = currH * 60 + currM

    return currentMinutes > limitMinutes
})

const minutesLate = computed(() => {
    if (!props.schedule?.work_start) return 0
    const [startH, startM] = props.schedule.work_start.split(':').map(Number)
    const tolerance = Number(props.schedule.late_tolerance) || 0
    const [currH, currM] = currentTime.value.split(':').map(Number)

    const limitMinutes = startH * 60 + startM + tolerance
    const currentMinutes = currH * 60 + currM

    return Math.max(0, currentMinutes - limitMinutes)
})

const handleLatePhotoChange = (e) => {
    const file = e.target.files[0]
    if (!file) {
        checkInForm.late_photo = null
        latePhotoPreview.value = null
        return
    }
    const reader = new FileReader()
    reader.onload = (event) => {
        checkInForm.late_photo = event.target.result
        latePhotoPreview.value = event.target.result
    }
    reader.readAsDataURL(file)
}

// ─── Deteksi Pulang Mendahului (< Jam Kerja Berakhir) ──────────────────────
const isEarlyDepartureModalOpen = ref(false)

const isBeforeWorkEnd = computed(() => {
    if (!props.schedule?.work_end) return false
    const [endHours, endMinutes] = props.schedule.work_end.split(':').map(Number)
    const [currentHours, currentMinutes] = currentTime.value.split(':').map(Number)
    const endTotalMinutes = endHours * 60 + endMinutes
    const currentTotalMinutes = currentHours * 60 + currentMinutes
    return currentTotalMinutes < endTotalMinutes
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
    early_departure_reason: '',
})

// ─── Pengajuan Izin / Sakit Pegawai ─────────────────────────────────────────
const isPermitModalOpen = ref(false)
const selectedPermitDetail = ref(null)

const permitForm = useForm({
    status: 'izin', // 'izin' | 'sakit'
    date: new Date().toISOString().split('T')[0],
    notes: '',
    attachment: null,
})

const openPermitModal = (type = 'izin') => {
    permitForm.status = type
    permitForm.date = new Date().toISOString().split('T')[0]
    permitForm.notes = ''
    permitForm.attachment = null
    isPermitModalOpen.value = true
}

const handlePermitFileChange = (e) => {
    permitForm.attachment = e.target.files[0] || null
}

const submitPermitForm = () => {
    if (!permitForm.notes.trim()) {
        alert('Silakan tuliskan alasan/keterangan izin atau sakit.')
        return
    }
    permitForm.post(route('attendance.store-permit'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            isPermitModalOpen.value = false
            permitForm.reset()
        }
    })
}

const handleCheckInDirect = () => {
    if (!userLat.value || !userLng.value) {
        alert('Menunggu titik koordinat GPS...')
        return
    }
    checkInForm.latitude = userLat.value
    checkInForm.longitude = userLng.value
    checkInForm.photo = null
    checkInForm.notes = ''

    if (isCheckInLate.value) {
        checkInForm.late_reason = ''
        checkInForm.late_photo = null
        latePhotoPreview.value = null
        isLateCheckInModalOpen.value = true
        return
    }

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

    if (isCheckInLate.value) {
        closeCameraModal()
        checkInForm.late_reason = ''
        checkInForm.late_photo = null
        latePhotoPreview.value = null
        isLateCheckInModalOpen.value = true
        return
    }

    checkInForm.post(route('attendance.check-in'), {
        onSuccess: () => closeCameraModal()
    })
}

const submitLateCheckIn = () => {
    if (!checkInForm.late_reason.trim()) {
        alert('Catatan alasan keterlambatan wajib diisi.')
        return
    }
    checkInForm.latitude = userLat.value
    checkInForm.longitude = userLng.value

    checkInForm.post(route('attendance.check-in'), {
        onSuccess: () => {
            isLateCheckInModalOpen.value = false
            closeCameraModal()
        }
    })
}

const initiateCheckOut = (photo = null) => {
    if (!userLat.value || !userLng.value) {
        alert('Menunggu titik koordinat GPS...')
        return
    }

    pendingCheckoutPhoto.value = photo

    // 1. Jika pulang lebih awal dari jadwal seharusnya (Izin Pulang Mendahului)
    if (isBeforeWorkEnd.value) {
        checkOutForm.early_departure_reason = ''
        closeCameraModal()
        isEarlyDepartureModalOpen.value = true
        return
    }

    // 2. Jika melebihi 60 menit setelah jam kerja pulang, buka modal konfirmasi lembur
    if (isOvertimeThresholdExceeded.value) {
        checkOutForm.is_overtime = false
        checkOutForm.overtime_activity = ''
        closeCameraModal()
        isOvertimeModalOpen.value = true
        return
    }

    submitFinalCheckOut()
}

const submitFinalCheckOut = () => {
    if (isBeforeWorkEnd.value && !checkOutForm.early_departure_reason.trim()) {
        alert('Wajib menuliskan keterangan alasan izin pulang mendahului.')
        return
    }

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
            isEarlyDepartureModalOpen.value = false
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
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3.5 border-b border-subtle gap-2">
                            <div>
                                <h2 class="text-base sm:text-lg font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                    <span>Status Presensi Hari Ini</span>
                                </h2>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    Jadwal: {{ schedule?.work_start }} - {{ schedule?.work_end }}
                                    <span v-if="schedule?.is_piket" class="text-amber-600 dark:text-amber-400 font-bold ml-1">({{ schedule?.piket_name || 'Petugas Piket' }})</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <!-- Tombol Buka Modal Ajukan Izin / Sakit -->
                                <button 
                                    type="button" 
                                    @click="openPermitModal('izin')" 
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 hover:bg-purple-100 dark:hover:bg-purple-900/60 transition-all cursor-pointer shadow-2xs active:scale-95"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span>Ajukan Izin / Sakit</span>
                                </button>

                                <!-- Status Badge Hari Ini -->
                                <span 
                                    v-if="todayAttendance" 
                                    :class="[
                                        'text-xs font-black uppercase px-3 py-1 rounded-full',
                                        todayAttendance.status === 'hadir' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' :
                                        todayAttendance.status === 'terlambat' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300' :
                                        todayAttendance.status === 'dinas_luar' ? 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300' :
                                        'bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300'
                                    ]"
                                >
                                    {{ todayAttendance.status }}
                                </span>
                                <span v-else class="text-xs font-bold uppercase px-3 py-1 rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                    Belum Presensi
                                </span>
                            </div>
                        </div>

                        <!-- ── Kondisi A: Hari Ini Mengajukan Izin / Sakit ── -->
                        <div v-if="todayAttendance && ['izin', 'sakit'].includes(todayAttendance.status)" class="my-4 p-4.5 rounded-2xl bg-purple-50/70 dark:bg-purple-950/30 border border-purple-200/80 dark:border-purple-900/50 space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-xl">{{ todayAttendance.status === 'sakit' ? '🏥' : '📋' }}</span>
                                    <div>
                                        <h3 class="text-sm font-black text-purple-950 dark:text-purple-100">
                                            Pengajuan {{ todayAttendance.status === 'sakit' ? 'Sakit' : 'Izin' }} Hari Ini
                                        </h3>
                                        <p class="text-[11px] text-purple-700 dark:text-purple-300">
                                            Dicatat pada sistem presensi pegawai
                                        </p>
                                    </div>
                                </div>

                                <!-- Badge Status Approval -->
                                <div>
                                    <span 
                                        v-if="todayAttendance.approval_status === 'pending'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 animate-pulse border border-amber-300"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500" />
                                        Menunggu Persetujuan
                                    </span>
                                    <span 
                                        v-else-if="todayAttendance.approval_status === 'approved'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                                        Disetujui Admin
                                    </span>
                                    <span 
                                        v-else-if="todayAttendance.approval_status === 'rejected'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border border-rose-300"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500" />
                                        Ditolak Admin
                                    </span>
                                </div>
                            </div>

                            <!-- Alasan Keterangan Pegawai -->
                            <div class="p-3 bg-white/80 dark:bg-slate-900/60 rounded-xl border border-purple-100 dark:border-purple-900/40 text-xs">
                                <span class="font-bold text-slate-500 block mb-0.5 text-[10px] uppercase">Keterangan:</span>
                                <p class="text-slate-800 dark:text-slate-200">{{ todayAttendance.notes || '-' }}</p>
                            </div>

                            <!-- Alert Alasan Penolakan dari Admin (Jika Ditolak) -->
                            <div v-if="todayAttendance.approval_status === 'rejected'" class="p-3 rounded-xl bg-rose-100/80 dark:bg-rose-950/60 border border-rose-300 dark:border-rose-800 text-xs space-y-1">
                                <span class="font-black text-rose-800 dark:text-rose-200 flex items-center gap-1">
                                    <svg class="w-4 h-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    Keterangan Penolakan oleh Admin:
                                </span>
                                <p class="text-rose-900 dark:text-rose-100 font-medium pl-5">
                                    "{{ todayAttendance.rejection_note || 'Tidak ada catatan penolakan.' }}"
                                </p>
                            </div>

                            <!-- Lampiran Berkas / Surat Dokter -->
                            <div v-if="todayAttendance.attachment" class="pt-1">
                                <a 
                                    :href="'/storage/' + todayAttendance.attachment" 
                                    target="_blank" 
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-purple-200 dark:border-purple-800 text-xs font-bold text-purple-700 dark:text-purple-300 hover:underline shadow-2xs"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                    <span>Lihat Berkas Lampiran / Surat Dokter</span>
                                </a>
                            </div>
                        </div>

                        <!-- ── Kondisi B: Presensi Reguler (Masuk & Pulang) ── -->
                        <template v-else>
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
                                                <span v-if="todayAttendance?.is_early_departure" class="px-2 py-0.5 rounded-md text-[10px] font-black bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-200">
                                                    Pulang Cepat
                                                </span>
                                                <span v-else-if="todayAttendance?.is_overtime" class="px-2 py-0.5 rounded-md text-[10px] font-black bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                                    ⚡ Lembur {{ todayAttendance.overtime_minutes }}m
                                                </span>
                                            </div>
                                            <p v-if="todayAttendance?.is_early_departure && todayAttendance?.early_departure_reason" class="text-[10px] text-purple-600 dark:text-purple-400 font-medium italic mt-0.5 truncate max-w-[180px]" :title="todayAttendance.early_departure_reason">
                                                "{{ todayAttendance.early_departure_reason }}"
                                            </p>
                                            <p v-else-if="todayAttendance?.is_overtime && todayAttendance?.overtime_activity" class="text-[10px] text-amber-600 dark:text-amber-400 font-medium italic mt-0.5 truncate max-w-[180px]" :title="todayAttendance.overtime_activity">
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

                            <!-- Alert Pulang Mendahului Status (Jika ada) -->
                            <div v-if="todayAttendance?.is_early_departure" class="p-3 rounded-2xl mb-4 text-xs border"
                                :class="[
                                    todayAttendance.approval_status === 'pending' ? 'bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-800 text-amber-900 dark:text-amber-200' :
                                    todayAttendance.approval_status === 'rejected' ? 'bg-rose-50 dark:bg-rose-950/30 border-rose-200 dark:border-rose-800 text-rose-900 dark:text-rose-200' :
                                    'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200'
                                ]"
                            >
                                <div class="flex items-center justify-between font-bold">
                                    <span>Status Izin Pulang Mendahului:</span>
                                    <span class="uppercase tracking-wider text-[10px]">
                                        {{ todayAttendance.approval_status === 'pending' ? '⏳ Menunggu Persetujuan Admin' : (todayAttendance.approval_status === 'rejected' ? '❌ Ditolak Admin' : '✅ Disetujui Admin') }}
                                    </span>
                                </div>
                                <div v-if="todayAttendance.approval_status === 'rejected' && todayAttendance.rejection_note" class="mt-1 font-semibold text-rose-700 dark:text-rose-300">
                                    Alasan Penolakan: "{{ todayAttendance.rejection_note }}"
                                </div>
                            </div>
                        </template>

                        <!-- Tombol Aksi Dinamis Sesuai Tahapan (Hanya jika bukan izin/sakit) -->
                        <template v-if="!todayAttendance || !['izin', 'sakit'].includes(todayAttendance.status)">
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

                                <!-- Tombol Alternatif: Berhalangan / Izin -->
                                <button
                                    type="button"
                                    @click="openPermitModal('izin')"
                                    class="w-full py-2.5 rounded-xl border border-purple-200 dark:border-purple-800 bg-purple-50/50 dark:bg-purple-950/30 text-purple-700 dark:text-purple-300 font-bold text-xs hover:bg-purple-100 dark:hover:bg-purple-900/40 transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                                >
                                    <span>📋 Berhalangan hadir hari ini? Klik untuk ajukan Izin atau Sakit</span>
                                </button>
                            </div>

                            <!-- 2. Sudah Masuk, Belum Pulang -->
                            <div v-else-if="!todayAttendance.time_out" class="space-y-2 mb-4">
                                <button
                                    v-if="isInsideRadius"
                                    type="button"
                                    @click="handleCheckOutDirect"
                                    :disabled="checkOutForm.processing || isGpsLoading"
                                    :class="[
                                        'w-full py-3.5 rounded-2xl text-white font-black text-sm sm:text-base shadow-md active:scale-[0.99] transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50',
                                        isBeforeWorkEnd ? 'bg-purple-600 hover:bg-purple-700 hover:shadow-purple-500/20' : 'bg-rose-600 hover:bg-rose-700 hover:shadow-rose-500/20'
                                    ]"
                                >
                                    <svg v-if="checkOutForm.processing" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    <span v-if="isBeforeWorkEnd">PRESENSI PULANG (IJIN PULANG MENDAHULUI)</span>
                                    <span v-else>PRESENSI PULANG SEKARANG</span>
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
                                    <span v-if="isBeforeWorkEnd">PULANG MENDAHULUI DI LUAR AREA (FOTO SELFIE)</span>
                                    <span v-else>PRESENSI PULANG DI LUAR AREA (FOTO SELFIE)</span>
                                </button>
                            </div>

                            <!-- 3. Sudah Selesai Lengkap -->
                            <div v-else class="p-3 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-center mb-4">
                                <p class="text-emerald-800 dark:text-emerald-200 font-bold text-xs sm:text-sm">
                                    🎉 Alhamdulillah, presensi hari ini telah lengkap (Masuk &amp; Pulang).
                                </p>
                            </div>
                        </template>

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
                                    <div class="flex flex-col gap-1 items-start">
                                        <span 
                                            :class="[
                                                'px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider',
                                                att.status === 'hadir' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' :
                                                att.status === 'terlambat' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' :
                                                att.status === 'dinas_luar' ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300' :
                                                att.status === 'izin' ? 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300' :
                                                att.status === 'sakit' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' :
                                                'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300'
                                            ]"
                                        >
                                            {{ att.status }}
                                        </span>

                                        <!-- Badge Status Persetujuan Admin -->
                                        <span 
                                            v-if="att.approval_status === 'pending'"
                                            class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-tight bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 animate-pulse border border-amber-300"
                                            title="Menunggu persetujuan admin"
                                        >
                                            ⏳ Menunggu
                                        </span>
                                        <span 
                                            v-else-if="att.approval_status === 'rejected'"
                                            class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-tight bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border border-rose-300"
                                            title="Izin ditolak oleh admin"
                                        >
                                            ❌ Ditolak
                                        </span>
                                        <span 
                                            v-else-if="att.approval_status === 'approved' && ['izin', 'sakit'].includes(att.status)"
                                            class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-tight bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300"
                                            title="Disetujui admin"
                                        >
                                            ✅ Disetujui
                                        </span>

                                        <span 
                                            v-if="att.is_early_departure"
                                            class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-tight bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300"
                                            title="Pulang mendahului jam kerja"
                                        >
                                            Pulang Cepat
                                        </span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-slate-500 max-w-xs space-y-1">
                                    <div v-if="att.notes" class="text-xs text-slate-700 dark:text-slate-200">
                                        {{ att.notes }}
                                    </div>

                                    <!-- Keterangan Izin Pulang Mendahului -->
                                    <div v-if="att.is_early_departure && att.early_departure_reason" class="text-[11px] text-purple-600 dark:text-purple-400 font-medium">
                                        Alasan pulang mendahului: "{{ att.early_departure_reason }}"
                                    </div>

                                    <!-- Alasan Penolakan dari Admin jika ditolak -->
                                    <div v-if="att.approval_status === 'rejected' && att.rejection_note" class="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-[11px] text-rose-700 dark:text-rose-300">
                                        <span class="font-bold block text-[10px] uppercase">Alasan Penolakan Admin:</span>
                                        "{{ att.rejection_note }}"
                                    </div>

                                    <!-- Tautan Lampiran Berkas Bukti / Surat Dokter -->
                                    <div v-if="att.attachment">
                                        <a 
                                            :href="'/storage/' + att.attachment" 
                                            target="_blank" 
                                            class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600 hover:underline"
                                        >
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                            </svg>
                                            <span>Lihat Berkas Bukti</span>
                                        </a>
                                    </div>

                                    <!-- Lembur -->
                                    <div v-if="att.is_overtime && att.overtime_activity" class="text-[11px] text-amber-600 dark:text-amber-400 font-medium truncate" :title="att.overtime_activity">
                                        ⚡ Lembur: {{ att.overtime_activity }}
                                    </div>

                                    <span v-if="!att.notes && !att.is_early_departure && !att.attachment && (!att.is_overtime || !att.overtime_activity)">-</span>
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

        <!-- ── Modal Keterangan Alasan Terlambat & Opsional Foto ──────────────── -->
        <div v-if="isLateCheckInModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/65 backdrop-blur-xs" @click="isLateCheckInModalOpen = false" />

            <div class="relative z-10 w-full max-w-lg bg-sidebar rounded-3xl shadow-2xl border border-theme overflow-hidden flex flex-col">
                <!-- Modal Header -->
                <div class="p-5 border-b border-subtle flex items-center justify-between bg-amber-500/10">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-800 dark:text-slate-100">
                                Presensi Masuk Terlambat
                            </h3>
                            <p class="text-xs text-amber-600 dark:text-amber-400 font-bold">
                                Terlambat ±{{ minutesLate }} menit dari batas toleransi jadwal masuk
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="isLateCheckInModalOpen = false"
                        class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors cursor-pointer"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitLateCheckIn">
                    <!-- Modal Body -->
                    <div class="p-5 space-y-4">
                        <div class="p-3.5 rounded-2xl bg-amber-50/70 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-xs text-amber-900 dark:text-amber-200 space-y-1">
                            <p class="font-bold flex items-center gap-1.5">
                                <span>⚠️ Pemberitahuan Keterlambatan:</span>
                            </p>
                            <p>
                                Jam masuk kerja Anda adalah <strong>{{ schedule?.work_start }}</strong> (toleransi {{ schedule?.late_tolerance || 0 }} menit). Anda wajib menuliskan keterangan alasan terlambat.
                            </p>
                        </div>

                        <!-- Alasan Keterlambatan (Wajib) -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-black text-slate-700 dark:text-slate-200 uppercase tracking-wider">
                                Alasan / Penyebab Terlambat <span class="text-rose-500">*</span>
                            </label>
                            <textarea
                                v-model="checkInForm.late_reason"
                                rows="3"
                                required
                                placeholder="Contoh: Terjebak macet perbaikan jalan / Mengantar keluarga ke faskes / Ban kendaraan bocor..."
                                class="w-full text-xs rounded-xl border border-theme bg-card-subtle px-3 py-2.5 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                            ></textarea>
                            <p v-if="checkInForm.errors.late_reason" class="text-[10px] text-rose-500 font-semibold">
                                {{ checkInForm.errors.late_reason }}
                            </p>
                        </div>

                        <!-- Upload Foto Bukti Keterlambatan (Opsional) -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">
                                Foto Bukti Pendukung <span class="text-slate-400 font-normal">(Opsional)</span>
                            </label>
                            <input
                                type="file"
                                accept="image/*"
                                @change="handleLatePhotoChange"
                                class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3.5 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-amber-100 file:text-amber-800 hover:file:bg-amber-200 cursor-pointer"
                            />
                            <p class="text-[10px] text-slate-400">
                                Unggah foto kendala di jalan / bukti lainnya untuk memperkuat alasan terlambat Anda.
                            </p>

                            <!-- Pratinjau Foto jika diunggah -->
                            <div v-if="latePhotoPreview" class="mt-2 rounded-xl overflow-hidden border border-theme max-h-40 w-auto bg-black/10 flex items-center justify-center p-1">
                                <img :src="latePhotoPreview" class="max-h-36 object-contain rounded-lg" />
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="p-4 bg-card-subtle border-t border-subtle flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="isLateCheckInModalOpen = false"
                            class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-700 transition-colors cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="checkInForm.processing || !checkInForm.late_reason.trim()"
                            class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-black text-xs sm:text-sm shadow-md transition-all active:scale-95 disabled:opacity-50 cursor-pointer"
                        >
                            {{ checkInForm.processing ? 'Memproses...' : 'Kirim Presensi Masuk' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── Modal Konfirmasi Izin Pulang Mendahului (< Jam Kerja Selesai) ───── -->
        <div v-if="isEarlyDepartureModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/65 backdrop-blur-xs" @click="isEarlyDepartureModalOpen = false" />

            <div class="relative z-10 w-full max-w-lg bg-sidebar rounded-3xl shadow-2xl border border-theme overflow-hidden flex flex-col">
                <!-- Modal Header -->
                <div class="p-5 border-b border-subtle flex items-center justify-between bg-purple-500/10">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-purple-500/20 text-purple-700 dark:text-purple-300 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-800 dark:text-slate-100">
                                Izin Pulang Mendahului
                            </h3>
                            <p class="text-xs text-purple-700 dark:text-purple-300 font-semibold">Presensi pulang sebelum jam kerja berakhir</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="isEarlyDepartureModalOpen = false"
                        class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors cursor-pointer"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-5 space-y-4">
                    <!-- Info Waktu -->
                    <div class="p-3.5 rounded-2xl bg-purple-50/70 dark:bg-purple-950/30 border border-purple-200/80 dark:border-purple-800/50 text-xs text-purple-950 dark:text-purple-200 leading-relaxed">
                        Jam saat ini adalah <strong>{{ currentTime }}</strong>, sedangkan jadwal kepulangan seharusnya adalah pukul <strong>{{ schedule?.work_end }}</strong>. Anda melakukan kepulangan lebih cepat dari jadwal.
                    </div>

                    <!-- Input Alasan / Keterangan Pulang Mendahului (Wajib) -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">
                            Alasan / Keterangan Izin Pulang Mendahului <span class="text-rose-500">*</span>
                        </label>
                        <textarea
                            v-model="checkOutForm.early_departure_reason"
                            rows="3"
                            placeholder="Contoh: Mengantar anggota keluarga berobat ke rumah sakit / urusan mendesak..."
                            class="w-full text-xs rounded-xl border border-theme bg-card-subtle p-3 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                            required
                        ></textarea>
                        <p class="text-[11px] text-slate-400 leading-snug">
                            Keterangan ini akan dikirimkan ke Admin / Pimpinan untuk diverifikasi dan disetujui.
                        </p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 bg-card-subtle border-t border-subtle flex items-center justify-end gap-3">
                    <button
                        type="button"
                        @click="isEarlyDepartureModalOpen = false"
                        class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-700 transition-colors cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="submitFinalCheckOut"
                        :disabled="checkOutForm.processing || !checkOutForm.early_departure_reason.trim()"
                        class="px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-black text-xs sm:text-sm shadow-md transition-all active:scale-95 disabled:opacity-50 cursor-pointer"
                    >
                        {{ checkOutForm.processing ? 'Memproses...' : 'Kirim Presensi Pulang' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Modal Pengajuan Izin / Sakit Pegawai ─────────────────────────────── -->
        <div v-if="isPermitModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/65 backdrop-blur-xs" @click="isPermitModalOpen = false" />

            <div class="relative z-10 w-full max-w-lg bg-sidebar rounded-3xl shadow-2xl border border-theme overflow-hidden flex flex-col">
                <!-- Modal Header -->
                <div class="p-5 border-b border-subtle flex items-center justify-between bg-card-subtle">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-purple-500/15 text-purple-600 dark:text-purple-400 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-800 dark:text-slate-100">
                                Formulir Pengajuan Izin &amp; Sakit
                            </h3>
                            <p class="text-xs text-slate-400">Kirim permohonan ketidakhadiran kerja</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="isPermitModalOpen = false"
                        class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors cursor-pointer"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitPermitForm">
                    <!-- Modal Body -->
                    <div class="p-5 space-y-4">
                        <!-- Pilihan Jenis: Izin / Sakit -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-black text-slate-700 dark:text-slate-200 uppercase tracking-wider">
                                Jenis Permohonan <span class="text-rose-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <label
                                    :class="[
                                        'p-3.5 rounded-2xl border cursor-pointer transition-all flex items-center gap-3',
                                        permitForm.status === 'izin'
                                            ? 'border-purple-500 bg-purple-50/60 dark:bg-purple-950/40 text-purple-950 dark:text-purple-100 shadow-xs'
                                            : 'border-theme bg-card-subtle text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
                                    ]"
                                >
                                    <input
                                        type="radio"
                                        value="izin"
                                        v-model="permitForm.status"
                                        class="text-purple-600 focus:ring-purple-500 w-4 h-4"
                                    />
                                    <div>
                                        <span class="text-xs font-black block">📋 Izin</span>
                                        <span class="text-[10px] opacity-75">Keperluan pribadi / dinas</span>
                                    </div>
                                </label>

                                <label
                                    :class="[
                                        'p-3.5 rounded-2xl border cursor-pointer transition-all flex items-center gap-3',
                                        permitForm.status === 'sakit'
                                            ? 'border-rose-500 bg-rose-50/60 dark:bg-rose-950/40 text-rose-950 dark:text-rose-100 shadow-xs'
                                            : 'border-theme bg-card-subtle text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
                                    ]"
                                >
                                    <input
                                        type="radio"
                                        value="sakit"
                                        v-model="permitForm.status"
                                        class="text-rose-600 focus:ring-rose-500 w-4 h-4"
                                    />
                                    <div>
                                        <span class="text-xs font-black block">🏥 Sakit</span>
                                        <span class="text-[10px] opacity-75">Kurang sehat / rawat</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Pilihan Tanggal -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">
                                Tanggal Izin / Sakit <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="date"
                                v-model="permitForm.date"
                                class="w-full text-xs font-bold rounded-xl border border-theme bg-card-subtle p-2.5 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500 cursor-pointer"
                                required
                            />
                        </div>

                        <!-- Keterangan Alasan -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">
                                Alasan / Keterangan Lengkap <span class="text-rose-500">*</span>
                            </label>
                            <textarea
                                v-model="permitForm.notes"
                                rows="3"
                                placeholder="Jelaskan alasan izin atau kondisi kesehatan Anda secara jelas..."
                                class="w-full text-xs rounded-xl border border-theme bg-card-subtle p-3 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                required
                            ></textarea>
                            <p v-if="permitForm.errors.notes" class="text-[10px] text-rose-500">{{ permitForm.errors.notes }}</p>
                        </div>

                        <!-- Upload File Lampiran / Surat Dokter -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">
                                Unggah Dokumen / Surat Dokter (Opsional)
                            </label>
                            <input
                                type="file"
                                @change="handlePermitFileChange"
                                accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
                                class="w-full text-xs rounded-xl border border-theme bg-card-subtle p-2 text-slate-800 dark:text-slate-100 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-purple-100 file:text-purple-700 dark:file:bg-purple-950 dark:file:text-purple-300 hover:file:bg-purple-200 cursor-pointer"
                            />
                            <p class="text-[10px] text-slate-400">Format: JPG, PNG, PDF (Maksimal 5MB). Lampirkan surat dokter jika sakit.</p>
                            <p v-if="permitForm.errors.attachment" class="text-[10px] text-rose-500">{{ permitForm.errors.attachment }}</p>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="p-4 bg-card-subtle border-t border-subtle flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="isPermitModalOpen = false"
                            class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-700 transition-colors cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="permitForm.processing || !permitForm.notes.trim()"
                            class="px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-black text-xs sm:text-sm shadow-md transition-all active:scale-95 disabled:opacity-50 cursor-pointer"
                        >
                            {{ permitForm.processing ? 'Mengirim...' : 'Kirim Permohonan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
