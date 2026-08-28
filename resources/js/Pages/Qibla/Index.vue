<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

// Koordinat Ka'bah (Masjidil Haram)
const KAABA_LAT = 21.422487;
const KAABA_LNG = 39.826206;

const errorMsg = ref('');
const userLat = ref(null);
const userLng = ref(null);
const qiblaAngle = ref(null);
const deviceHeading = ref(0);
const isCompassActive = ref(false);

// Menghitung arah kiblat (bearing) dari titik pengguna ke Ka'bah
function calculateQibla(lat1, lon1) {
    const toRad = (val) => (val * Math.PI) / 180;
    const toDeg = (val) => (val * 180) / Math.PI;

    const phi1 = toRad(lat1);
    const phi2 = toRad(KAABA_LAT);
    const deltaLambda = toRad(KAABA_LNG - lon1);

    const y = Math.sin(deltaLambda) * Math.cos(phi2);
    const x = Math.cos(phi1) * Math.sin(phi2) -
              Math.sin(phi1) * Math.cos(phi2) * Math.cos(deltaLambda);

    let bearing = toDeg(Math.atan2(y, x));
    return (bearing + 360) % 360; // Normalisasi ke 0-360
}

// Mendapatkan lokasi saat ini
function getLocation() {
    if (!navigator.geolocation) {
        errorMsg.value = "Geolokasi tidak didukung oleh browser Anda.";
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (position) => {
            userLat.value = position.coords.latitude;
            userLng.value = position.coords.longitude;
            qiblaAngle.value = calculateQibla(userLat.value, userLng.value);
            errorMsg.value = '';
        },
        (error) => {
            errorMsg.value = "Gagal mendapatkan lokasi. Pastikan GPS aktif dan Anda memberikan izin.";
        },
        { enableHighAccuracy: true }
    );
}

let isAbsoluteReceived = false;
let lastHeading = 0;

function handleOrientation(event) {
    // Jika event absolute sudah diterima, abaikan event relative biasa agar data tidak bentrok
    if (event.type === 'deviceorientation' && isAbsoluteReceived) {
        return;
    }

    let currentHeading = null;

    // 1. iOS (Safari / Chrome di iPhone)
    if (event.webkitCompassHeading !== undefined && event.webkitCompassHeading !== null) {
        currentHeading = event.webkitCompassHeading;
    } 
    // 2. Android Absolute (Sensor Magnetik Asli)
    else if (event.type === 'deviceorientationabsolute' || event.absolute === true) {
        isAbsoluteReceived = true;
        if (event.alpha !== null) {
            currentHeading = (360 - event.alpha) % 360;
        }
    } 
    // 3. Fallback jika perangkat sangat lama & tidak mendukung absolute sama sekali
    else if (!isAbsoluteReceived && event.alpha !== null) {
        currentHeading = (360 - event.alpha) % 360;
    }

    if (currentHeading !== null) {
        // Menjaga putaran kompas tetap mulus tanpa 'flicker' saat melintasi derajat 359 -> 0
        let delta = currentHeading - lastHeading;
        if (delta > 180) delta -= 360;
        if (delta < -180) delta += 360;

        deviceHeading.value = deviceHeading.value + delta;
        lastHeading = currentHeading;
        isCompassActive.value = true;
    }
}

async function requestOrientationPermission() {
    errorMsg.value = '';

    if (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
        // Khusus iOS 13+
        try {
            const permission = await DeviceOrientationEvent.requestPermission();
            if (permission === 'granted') {
                window.addEventListener('deviceorientation', handleOrientation, true);
                getLocation();
            } else {
                errorMsg.value = "Akses sensor kompas ditolak.";
            }
        } catch (e) {
            errorMsg.value = "Gagal meminta akses sensor.";
        }
    } else {
        // Khusus Android:
        // PENTING: Langsung daftarkan 'deviceorientationabsolute' TANPA pengecekan 'in window'
        window.addEventListener('deviceorientationabsolute', handleOrientation, true);
        window.addEventListener('deviceorientation', handleOrientation, true);
        getLocation();
    }
}

// Rotasi akhir jarum menuju Kiblat
const compassRotation = computed(() => {
    if (qiblaAngle.value === null) return 0;
    
    // Perangkat diputar ke deviceHeading, jadi untuk menunjuk kiblat, 
    // jarum harus menunjuk qiblaAngle - deviceHeading
    return qiblaAngle.value - deviceHeading.value;
});

onUnmounted(() => {
    window.removeEventListener('deviceorientation', handleOrientation, true);
    if ('ondeviceorientationabsolute' in window) {
        window.removeEventListener('deviceorientationabsolute', handleOrientation, true);
    }
});

</script>

<template>
    <Head title="Arah Kiblat" />

    <AuthenticatedLayout>
        <div class="max-w-md mx-auto min-h-[80vh] flex flex-col items-center py-10 px-4">
            
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">Pencari Arah Kiblat</h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-2">
                    Letakkan HP secara mendatar seperti kompas.
                </p>
            </div>

            <!-- Error Banner -->
            <div v-if="errorMsg" class="w-full bg-red-100 text-red-600 p-4 rounded-xl text-sm mb-6 text-center shadow-sm">
                {{ errorMsg }}
                <div class="mt-2 text-xs text-red-500 font-medium">
                    (Pastikan Anda mengakses ini lewat HTTPS, atau dari localhost jika sedang development)
                </div>
            </div>

            <!-- Compass UI -->
            <div class="relative w-72 h-72 rounded-full border-4 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-2xl flex flex-col items-center justify-center p-4">
                
                <!-- Lingkaran Luar Kompas berputar menyesuaikan heading -->
                <div class="absolute inset-0 rounded-full transition-transform duration-300 ease-out flex items-center justify-center"
                     :style="{ transform: `rotate(${-deviceHeading}deg)` }">
                    
                    <!-- Grid Sudut (Ticks) -->
                    <div v-for="i in 72" :key="i" 
                         class="absolute w-full h-full flex justify-center"
                         :style="{ transform: `rotate(${i * 5}deg)` }">
                        <div :class="[
                            'bg-slate-300 dark:bg-slate-500',
                            i % 18 === 0 ? 'h-4 w-[2px] bg-emerald-500/70' : (i % 6 === 0 ? 'h-3 w-[1.5px]' : 'h-1.5 w-[1px] opacity-40')
                        ]"></div>
                    </div>

                    <span class="absolute top-5 text-red-500 font-bold text-lg bg-white dark:bg-slate-800 px-1 rounded">U</span>
                    <span class="absolute bottom-5 text-slate-400 font-bold text-lg bg-white dark:bg-slate-800 px-1 rounded">S</span>
                    <span class="absolute right-5 text-slate-400 font-bold text-lg bg-white dark:bg-slate-800 px-1 rounded">T</span>
                    <span class="absolute left-5 text-slate-400 font-bold text-lg bg-white dark:bg-slate-800 px-1 rounded">B</span>
                </div>

                <!-- Jarum Kiblat -->
                <div v-if="qiblaAngle !== null" 
                     class="absolute inset-0 transition-transform duration-300 ease-out z-10"
                     :style="{ transform: `rotate(${compassRotation}deg)` }">
                    
                    <!-- Jarum Hijau (Atas) -->
                    <div class="absolute left-1/2 bottom-1/2 -ml-[15px]
                                w-0 h-0 
                                border-l-[15px] border-l-transparent
                                border-b-[100px] border-b-emerald-500
                                border-r-[15px] border-r-transparent">
                    </div>
                    
                    <!-- Jarum Abu (Bawah) -->
                    <div class="absolute left-1/2 top-1/2 -ml-[10px]
                                w-0 h-0 
                                border-l-[10px] border-l-transparent
                                border-t-[80px] border-t-slate-300 dark:border-t-slate-600
                                border-r-[10px] border-r-transparent">
                    </div>

                    <!-- Lingkaran Tengah Jarum -->
                    <div class="absolute left-1/2 top-1/2 -ml-4 -mt-4 w-8 h-8 rounded-full bg-emerald-600 border-4 border-white shadow-md"></div>
                </div>

                <div v-else class="text-center z-10 text-slate-400 text-sm">
                    Mencari lokasi...
                </div>

                <!-- Titik Tengah -->
                <div class="absolute left-1/2 top-1/2 -ml-1.5 -mt-1.5 w-3 h-3 bg-white rounded-full z-20 shadow-inner"></div>
            </div>

            <!-- Info Lokasi & Tombol Izin -->
            <div class="mt-10 w-full text-center">
                <button 
                    v-if="!userLat && !errorMsg" 
                    @click="requestOrientationPermission" 
                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 px-6 rounded-xl w-full shadow-lg transition-colors"
                >
                    Mulai Kompas (Izinkan Sensor)
                </button>
                
                <div v-if="userLat && userLng" class="bg-slate-50 dark:bg-slate-700/50 p-4 rounded-xl shadow-sm text-sm">
                    <p class="text-slate-600 dark:text-slate-300"><span class="font-bold text-slate-800 dark:text-white">Lokasi Anda:</span> {{ userLat.toFixed(4) }}, {{ userLng.toFixed(4) }}</p>
                    <p class="text-slate-600 dark:text-slate-300 mt-1"><span class="font-bold text-slate-800 dark:text-white">Arah Kiblat:</span> {{ qiblaAngle.toFixed(1) }}° dari Utara</p>
                    
                    <p v-if="!isCompassActive" class="mt-3 text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 p-2 rounded-lg">
                        Sensor kompas tidak terdeteksi. HP Anda mungkin tidak memiliki magnetometer, atau akses diblokir oleh browser.
                    </p>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
