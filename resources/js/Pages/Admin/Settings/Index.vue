<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Editor from '@tinymce/tinymce-vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({})
    }
})

const form = useForm({
    announcement_text: props.settings.announcement_text || '',
    popup_active: props.settings.popup_active === '1' || props.settings.popup_active === true || props.settings.popup_active === 'true',
    popup_text: props.settings.popup_text || '',
    youtube_link: props.settings.youtube_link || '',
    running_text: props.settings.running_text || '',

    // Pengaturan Presensi GPS & Jam Kerja
    feature_presensi: props.settings.feature_presensi === '1' || props.settings.feature_presensi === true || props.settings.feature_presensi === 'true',
    presensi_latitude: props.settings.presensi_latitude || '-7.7956',
    presensi_longitude: props.settings.presensi_longitude || '110.3695',
    presensi_radius_meters: Number(props.settings.presensi_radius_meters) || 100,
    presensi_work_start: props.settings.presensi_work_start || '07:00',
    presensi_late_tolerance: props.settings.presensi_late_tolerance || 15,
    presensi_work_end: props.settings.presensi_work_end || '15:00',
})

// ─── LEAFLET MINI MAP SETTINGS ───────────────────────────────────────────────
let settingsMapInstance = null
let settingsMapMarker = null
let settingsMapCircle = null

const initSettingsMap = () => {
    const mapEl = document.getElementById('settings-mini-map')
    if (!mapEl) return

    if (settingsMapInstance) {
        settingsMapInstance.remove()
        settingsMapInstance = null
    }

    const initialLat = Number(form.presensi_latitude) || -7.7956
    const initialLng = Number(form.presensi_longitude) || 110.3695
    const initialRadius = Number(form.presensi_radius_meters) || 100

    settingsMapInstance = L.map('settings-mini-map', {
        center: [initialLat, initialLng],
        zoom: 17,
        scrollWheelZoom: true,
    })

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(settingsMapInstance)

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

    settingsMapMarker = L.marker([initialLat, initialLng], {
        icon: markerIcon,
        draggable: true
    }).addTo(settingsMapInstance)

    settingsMapCircle = L.circle([initialLat, initialLng], {
        radius: initialRadius,
        color: '#059669',
        fillColor: '#10B981',
        fillOpacity: 0.25,
        weight: 2
    }).addTo(settingsMapInstance)

    // Klik di peta => pindahkan marker dan circle
    settingsMapInstance.on('click', (e) => {
        const { lat, lng } = e.latlng
        updateSettingsCoordinates(lat, lng)
    })

    // Drag marker
    settingsMapMarker.on('dragend', (e) => {
        const pos = e.target.getLatLng()
        updateSettingsCoordinates(pos.lat, pos.lng)
    })

    setTimeout(() => {
        settingsMapInstance?.invalidateSize()
    }, 300)
}

const updateSettingsCoordinates = (lat, lng) => {
    form.presensi_latitude = parseFloat(lat).toFixed(7)
    form.presensi_longitude = parseFloat(lng).toFixed(7)

    if (settingsMapMarker) settingsMapMarker.setLatLng([lat, lng])
    if (settingsMapCircle) settingsMapCircle.setLatLng([lat, lng])
}

const handleManualCoords = () => {
    const lat = Number(form.presensi_latitude)
    const lng = Number(form.presensi_longitude)
    if (!isNaN(lat) && !isNaN(lng)) {
        if (settingsMapMarker) settingsMapMarker.setLatLng([lat, lng])
        if (settingsMapCircle) settingsMapCircle.setLatLng([lat, lng])
        if (settingsMapInstance) settingsMapInstance.panTo([lat, lng])
    }
}

// Watch radius input to update circle in real time
watch(() => form.presensi_radius_meters, (newR) => {
    const r = Number(newR) || 100
    if (settingsMapCircle) {
        settingsMapCircle.setRadius(r)
    }
})

// Watch feature_presensi
watch(() => form.feature_presensi, (val) => {
    if (val) {
        nextTick(() => initSettingsMap())
    }
})

onMounted(() => {
    if (form.feature_presensi) {
        nextTick(() => initSettingsMap())
    }
})

onUnmounted(() => {
    if (settingsMapInstance) {
        settingsMapInstance.remove()
        settingsMapInstance = null
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
            updateSettingsCoordinates(lat, lng)
            if (settingsMapInstance) {
                settingsMapInstance.setView([lat, lng], 18)
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

const submit = () => {
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Success notification via flash message
        }
    })
}
</script>

<template>
    <Head title="Pengaturan Sistem" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 dark:text-slate-100 leading-tight">Pengaturan Sistem</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Konfigurasi teks landing page, media visual, dan presensi berbasis GPS &amp; jadwal kerja.</p>
                </div>
            </div>
        </template>

        <div class="max-w-full mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
            
            <!-- Alert Flash Message -->
            <div v-if="$page.props.flash?.success" class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 flex items-center gap-3 shadow-xs">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-bold text-sm">{{ $page.props.flash.success }}</span>
            </div>

            <!-- Form 3 Kolom -->
            <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-3 gap-6 xl:gap-8">
                
                <!-- ══════════════════════════════════════════════════════════════════
                     KOLOM 1: Pengaturan Teks Utama (Pengumuman & Popup)
                ══════════════════════════════════════════════════════════════════ -->
                <div class="space-y-6">
                    <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-xs rounded-3xl border border-slate-100 dark:border-slate-700 p-6 relative flex flex-col h-full">
                        <h2 class="text-lg font-black text-slate-800 dark:text-slate-100 mb-5 flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <span>1. Teks &amp; Pengumuman</span>
                        </h2>

                        <div class="space-y-6 flex-1">
                            <!-- Section: Dashboard User -->
                            <div>
                                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-1">Pengumuman Dashboard</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mb-2.5">Muncul sebagai banner pengumuman di dashboard seluruh pengguna.</p>
                                
                                <div>
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
                                                branding: false
                                            }"
                                        />
                                    </div>
                                    <div v-if="form.errors.announcement_text" class="text-red-500 text-xs mt-1">{{ form.errors.announcement_text }}</div>
                                </div>
                            </div>

                            <hr class="border-slate-100 dark:border-slate-700/60" />

                            <!-- Section: Landing Page Pop-up -->
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Pop-up Landing Page</h3>
                                    <div class="flex items-center">
                                        <input
                                            id="popup_active"
                                            type="checkbox"
                                            v-model="form.popup_active"
                                            class="w-4 h-4 rounded border-slate-300 text-emerald-600 shadow-xs focus:ring-emerald-500 transition-colors cursor-pointer"
                                        />
                                        <label for="popup_active" class="ml-2 block text-xs font-bold text-slate-700 dark:text-slate-300 cursor-pointer">
                                            Aktifkan
                                        </label>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mb-2.5">Muncul otomatis saat pengunjung membuka beranda habit.</p>
                                
                                <div class="space-y-4">
                                    <div v-if="form.errors.popup_active" class="text-red-500 text-xs mt-1">{{ form.errors.popup_active }}</div>

                                    <div>
                                        <div :class="['border rounded-xl overflow-hidden border-slate-200 dark:border-slate-700 transition-opacity', !form.popup_active ? 'opacity-40 pointer-events-none' : '']">
                                            <Editor
                                                v-model="form.popup_text"
                                                tinymce-script-src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"
                                                :disabled="!form.popup_active"
                                                :init="{
                                                    height: 160,
                                                    menubar: false,
                                                    plugins: [
                                                        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap',
                                                        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                                                        'insertdatetime', 'media', 'table', 'preview', 'help', 'wordcount'
                                                    ],
                                                    toolbar: 'undo redo | bold italic forecolor | image link | alignleft aligncenter | removeformat',
                                                    content_style: 'body { font-family:Inter,Helvetica,Arial,sans-serif; font-size:13px }',
                                                    promotion: false,
                                                    branding: false
                                                }"
                                            />
                                        </div>
                                        <div v-if="form.errors.popup_text" class="text-red-500 text-xs mt-1">{{ form.errors.popup_text }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════════════════
                     KOLOM 2: Media & Ekstra (Video YouTube & Running Text)
                ══════════════════════════════════════════════════════════════════ -->
                <div class="space-y-6">
                    <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-xs rounded-3xl border border-slate-100 dark:border-slate-700 p-6 relative flex flex-col h-full">
                        <h2 class="text-lg font-black text-slate-800 dark:text-slate-100 mb-5 flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-rose-50 dark:bg-rose-950/50 flex items-center justify-center text-rose-500">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <span>2. Media &amp; Visual Landing</span>
                        </h2>

                        <div class="space-y-6 flex-1">
                            <!-- Section: Video YouTube -->
                            <div>
                                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-1">Video Sambutan YouTube</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mb-2.5">Sematkan video profil / motivasi di pahlawan (hero) beranda.</p>
                                
                                <div>
                                    <label for="youtube_link" class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Link YouTube (URL lengkap / ID)</label>
                                    <input
                                        id="youtube_link"
                                        type="text"
                                        v-model="form.youtube_link"
                                        class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-xs focus:border-emerald-500 focus:ring focus:ring-emerald-200 text-xs text-slate-800 dark:text-slate-200 font-mono transition-colors"
                                        placeholder="Contoh: https://www.youtube.com/watch?v=dQw4w9WgXcQ"
                                    />
                                    <div v-if="form.errors.youtube_link" class="text-red-500 text-xs mt-1">{{ form.errors.youtube_link }}</div>
                                </div>
                            </div>

                            <hr class="border-slate-100 dark:border-slate-700/60" />

                            <!-- Section: Running Text -->
                            <div>
                                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-1">Running Text (Teks Berjalan)</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mb-2.5">Pesan berjalan (marquee) di bagian header paling atas halaman utama.</p>
                                
                                <div>
                                    <label for="running_text" class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Isi Teks Pesan Berjalan</label>
                                    <textarea
                                        id="running_text"
                                        v-model="form.running_text"
                                        rows="5"
                                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-xs focus:border-emerald-500 focus:ring focus:ring-emerald-200 text-xs text-slate-800 dark:text-slate-200 transition-colors"
                                        placeholder="Kosongkan jika tidak ingin menampilkan teks berjalan..."
                                    ></textarea>
                                    <div v-if="form.errors.running_text" class="text-red-500 text-xs mt-1">{{ form.errors.running_text }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════════════════
                     KOLOM 3: Pengaturan Presensi GPS & Jam Kerja
                ══════════════════════════════════════════════════════════════════ -->
                <div class="space-y-6">
                    <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-xs rounded-3xl border border-slate-100 dark:border-slate-700 p-6 relative flex flex-col h-full">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <span>3. Presensi GPS &amp; Jam Kerja</span>
                            </h2>
                        </div>

                        <div class="space-y-4 flex-1">
                            <!-- Toggle Aktifkan Fitur Presensi -->
                            <div class="flex items-start bg-emerald-50/70 dark:bg-emerald-950/30 p-3 rounded-xl border border-emerald-200/70 dark:border-emerald-800/40">
                                <div class="flex h-5 items-center mt-0.5">
                                    <input
                                        id="feature_presensi"
                                        type="checkbox"
                                        v-model="form.feature_presensi"
                                        class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                                    />
                                </div>
                                <div class="ml-2.5 text-xs">
                                    <label for="feature_presensi" class="font-bold text-slate-800 dark:text-slate-100 cursor-pointer">
                                        Aktifkan Fitur Presensi GPS
                                    </label>
                                    <p class="text-slate-500 dark:text-slate-400 text-[11px] mt-0.5">
                                        Presensi HP dengan validasi geofencing lokasi sekolah.
                                    </p>
                                </div>
                            </div>

                            <div v-if="form.feature_presensi" class="space-y-4 pt-1">
                                <!-- Peta Titik Lokasi Leaflet & Deteksi GPS -->
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                            Titik Lokasi Sekolah
                                        </label>
                                        <button
                                            type="button"
                                            @click="getCurrentCoordinates"
                                            :disabled="isDetectingLocation"
                                            class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 dark:text-emerald-400 hover:underline cursor-pointer bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded-lg border border-emerald-200 dark:border-emerald-800"
                                        >
                                            <svg class="w-3 h-3" :class="{ 'animate-spin': isDetectingLocation }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>{{ isDetectingLocation ? 'Mendeteksi...' : 'GPS Saat Ini' }}</span>
                                        </button>
                                    </div>

                                    <!-- Leaflet Mini Map Container -->
                                    <div class="rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 relative shadow-inner bg-slate-100 dark:bg-slate-900">
                                        <div id="settings-mini-map" class="h-48 sm:h-52 w-full z-10" />
                                    </div>
                                    <p class="text-[10px] text-slate-500 leading-tight">
                                        💡 Klik peta atau geser pin hijau untuk memindahkan titik pusat sekolah.
                                    </p>

                                    <!-- Latitude & Longitude Input -->
                                    <div class="grid grid-cols-2 gap-2 pt-1">
                                        <div>
                                            <label class="block text-[9px] font-bold uppercase text-slate-400 mb-0.5">Latitude</label>
                                            <input 
                                                type="text" 
                                                v-model="form.presensi_latitude" 
                                                @change="handleManualCoords"
                                                placeholder="-7.7956" 
                                                class="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 py-1.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 font-mono"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-[9px] font-bold uppercase text-slate-400 mb-0.5">Longitude</label>
                                            <input 
                                                type="text" 
                                                v-model="form.presensi_longitude" 
                                                @change="handleManualCoords"
                                                placeholder="110.3695" 
                                                class="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 py-1.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 font-mono"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- Radius Toleransi -->
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Radius Sekolah</label>
                                        <span class="text-xs font-black text-emerald-600 font-mono">{{ form.presensi_radius_meters }} m</span>
                                    </div>
                                    <div class="space-y-1.5">
                                        <input 
                                            type="range" 
                                            v-model="form.presensi_radius_meters" 
                                            min="10" 
                                            max="1000" 
                                            step="10"
                                            class="w-full accent-emerald-600 cursor-pointer"
                                        />
                                        <div class="relative">
                                            <input 
                                                type="number" 
                                                v-model="form.presensi_radius_meters" 
                                                min="10" 
                                                max="5000" 
                                                class="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 py-1.5 pr-14 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 font-mono"
                                            />
                                            <span class="absolute inset-y-0 right-2.5 flex items-center text-[10px] font-semibold text-slate-400">Meter</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Jam Kerja Global Default -->
                                <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60">
                                    <label class="block text-xs font-bold text-slate-800 dark:text-slate-200 mb-2">Jam Kerja Default Sekolah</label>
                                    <div class="grid grid-cols-3 gap-2">
                                        <div>
                                            <label class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Masuk</label>
                                            <input 
                                                type="time" 
                                                v-model="form.presensi_work_start" 
                                                class="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 py-1.5 text-slate-800 dark:text-slate-200 font-mono"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Toleransi</label>
                                            <div class="relative">
                                                <input 
                                                    type="number" 
                                                    v-model="form.presensi_late_tolerance" 
                                                    min="0" 
                                                    max="120" 
                                                    class="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 py-1.5 pr-6 text-slate-800 dark:text-slate-200 font-mono"
                                                />
                                                <span class="absolute inset-y-0 right-1.5 flex items-center text-[9px] text-slate-400">m</span>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Pulang</label>
                                            <input 
                                                type="time" 
                                                v-model="form.presensi_work_end" 
                                                class="w-full text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 py-1.5 text-slate-800 dark:text-slate-200 font-mono"
                                            />
                                        </div>
                                    </div>
                                    <div class="mt-2.5 pt-2 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                                        <span class="text-[10px] text-slate-400">Jadwal per hari / piket:</span>
                                        <Link :href="route('admin.duty-schedules.index')" class="text-[11px] font-bold text-emerald-600 hover:text-emerald-700 underline flex items-center gap-1">
                                            <span>Atur Jadwal Harian &amp; Piket &rarr;</span>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ══════════════════════════════════════════════════════════════════
                     SUBMIT ACTION BUTTON (FULL WIDTH - 3 KOLOM)
                ══════════════════════════════════════════════════════════════════ -->
                <div class="col-span-1 lg:col-span-3">
                    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 p-5 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            Pastikan data pengaturan telah sesuai sebelum menekan tombol simpan.
                        </div>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full sm:w-auto px-8 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm rounded-2xl focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-all shadow-md hover:shadow-lg disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer"
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
    </AuthenticatedLayout>
</template>
