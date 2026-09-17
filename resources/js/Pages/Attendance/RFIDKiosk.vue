<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
    initialAttendances: {
        type: Array,
        default: () => []
    }
})

const page = usePage()
const attendances = ref(props.initialAttendances || [])

const isScannerFocused = ref(true)
const rfidInput = ref('')
const inputRef = ref(null)
const isLoading = ref(false)
const scanResult = ref(null) // { success: true/false, message: '', user: {} }
const currentTime = ref('')
const currentDate = ref('')

// Update time every second
let timeInterval = null
const updateTime = () => {
    const now = new Date()
    currentTime.value = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }).replace(/\./g, ':')
    currentDate.value = now.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
}

onMounted(() => {
    updateTime()
    timeInterval = setInterval(updateTime, 1000)
    
    // Always keep focus on the hidden input
    document.addEventListener('click', forceFocus)
    forceFocus()
})

onUnmounted(() => {
    clearInterval(timeInterval)
    document.removeEventListener('click', forceFocus)
})

const forceFocus = () => {
    if (inputRef.value) {
        inputRef.value.focus()
    }
}

const handleScan = async () => {
    if (!rfidInput.value.trim() || isLoading.value) return
    
    const uid = rfidInput.value.trim()
    rfidInput.value = '' // Clear immediately for next scan
    
    isLoading.value = true
    scanResult.value = null
    
    try {
        const response = await axios.post(route('attendance.rfid.scan'), {
            rfid_uid: uid
        })
        
        if (response.data.success) {
            scanResult.value = {
                success: true,
                type: response.data.type, // 'in' or 'out'
                message: response.data.message,
                user: response.data.user
            }
            
            if (response.data.attendance) {
                const idx = attendances.value.findIndex(a => a.id === response.data.attendance.id);
                if (idx !== -1) {
                    attendances.value[idx] = response.data.attendance;
                } else {
                    attendances.value.unshift(response.data.attendance);
                }
            }
        } else {
            scanResult.value = {
                success: false,
                message: response.data.message || 'Terjadi kesalahan pada sistem.'
            }
        }
        
        // Optional: Play success sound
        // new Audio('/sounds/success.mp3').play().catch(e => {})
    } catch (error) {
        scanResult.value = {
            success: false,
            message: error.response?.data?.message || 'Terjadi kesalahan pada sistem.'
        }
        
        // Optional: Play error sound
        // new Audio('/sounds/error.mp3').play().catch(e => {})
    } finally {
        isLoading.value = false
        forceFocus()
        
        // Clear result after 3 seconds
        setTimeout(() => {
            scanResult.value = null
        }, 3500)
    }
}
</script>

<template>
    <Head title="Kiosk Presensi RFID" />
    
    <div class="min-h-screen bg-slate-50 flex flex-col items-center justify-center relative overflow-hidden font-sans selection:bg-emerald-500/30 text-slate-800">
        
        <!-- School Building Background -->
        <div class="absolute inset-0 z-0">
            <!-- Background Image -->
            <img src="https://images.unsplash.com/photo-1541339907198-e08756dedf3f?auto=format&fit=crop&q=80&w=2000" alt="School Background" class="w-full h-full object-cover object-center" />
            <!-- Light/Glass Overlay for readability -->
            <div class="absolute inset-0 bg-white/70 backdrop-blur-md"></div>
        </div>

        <!-- Animated Background Mesh (Kept for elegance over the building) -->
        <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none opacity-60">
            <div class="absolute -top-[20%] -left-[10%] w-[70vw] h-[70vw] rounded-full bg-emerald-500/20 blur-[120px] mix-blend-screen animate-blob"></div>
            <div class="absolute top-[20%] -right-[20%] w-[60vw] h-[60vw] rounded-full bg-blue-600/20 blur-[120px] mix-blend-screen animate-blob animation-delay-2000"></div>
            <div class="absolute -bottom-[20%] left-[20%] w-[80vw] h-[80vw] rounded-full bg-teal-500/20 blur-[130px] mix-blend-screen animate-blob animation-delay-4000"></div>
            
            <!-- Subtle Grid Overlay -->
            <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCI+CjxwYXRoIGQ9Ik00MCAwaC00MHY0MGg0MHpNMSAzOXYtMzhwMzh2Mzh6IiBmaWxsPSJyZ2JhKDI1NSwyNTUsMjU1LDAuMDMpIi8+Cjwvc3ZnPg==')] opacity-50"></div>
        </div>

        <!-- Top Header: Institution Identity -->
        <div class="absolute top-0 left-0 w-full p-6 md:p-10 z-20 flex justify-center md:justify-start items-center gap-4">
            <!-- App Logo (Placeholder / Default) -->
            <div class="w-12 h-12 md:w-14 md:h-14 bg-white/60 backdrop-blur-xl rounded-2xl border border-white/50 flex items-center justify-center shadow-[0_8px_30px_rgb(0,0,0,0.05)]">
                <img src="/logo.png" alt="logoSDAM" class="w-full h-full object-contain p-1 drop-shadow-sm">
            </div>
            <div class="text-center md:text-left">
                <h1 class="text-xl md:text-2xl font-black tracking-wide text-slate-800 drop-shadow-sm">
                    {{ $page.props.global_settings?.app_name || 'SD MUH AL MUJAHIDIN WONOSARI' }}
                </h1>
                <p class="text-xs md:text-sm text-emerald-600 font-bold tracking-widest uppercase mt-0.5">
                    Sistem Presensi Kiosk RFID
                </p>
            </div>
        </div>

        <!-- Hidden input for RFID Scanner -->
        <input 
            ref="inputRef"
            type="text" 
            v-model="rfidInput" 
            @keyup.enter="handleScan"
            @focus="isScannerFocused = true"
            @blur="isScannerFocused = false"
            class="absolute opacity-0 -z-10"
            autocomplete="off"
            autofocus
        />

        <div class="z-10 w-full max-w-[90rem] px-4 md:px-8 grid grid-cols-1 lg:grid-cols-2 gap-8 md:gap-12 items-center">
            
            <!-- Left Column: Clock and Scanner -->
            <div class="flex flex-col items-center">
                <!-- Header / Premium Clock Display -->
            <div class="text-center mb-10 flex flex-col items-center">
                <!-- Status Indicator (Clickable to refocus) -->
                <button @click="forceFocus" :class="[
                    'px-5 py-2 rounded-full border backdrop-blur-xl mb-6 inline-flex items-center gap-2 shadow-sm transition-all duration-300 hover:scale-105 cursor-pointer',
                    isScannerFocused ? 'bg-white/60 border-emerald-200 hover:bg-white' : 'bg-rose-100 border-rose-300 animate-pulse hover:bg-rose-200'
                ]">
                    <span class="relative flex h-3 w-3">
                      <span v-if="isScannerFocused" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                      <span :class="['relative inline-flex rounded-full h-3 w-3', isScannerFocused ? 'bg-emerald-500' : 'bg-rose-600']"></span>
                    </span>
                    <span :class="['text-xs font-black tracking-widest uppercase', isScannerFocused ? 'text-emerald-700' : 'text-rose-700']">
                        {{ isScannerFocused ? 'Sistem Kiosk Aktif' : '⚠️ KLIK DI SINI UNTUK MENGAKTIFKAN SCANNER' }}
                    </span>
                </button>
                
                <h1 class="text-7xl md:text-8xl font-black text-transparent bg-clip-text bg-gradient-to-br from-emerald-800 to-teal-600 mb-2 tracking-tight drop-shadow-sm" style="font-feature-settings: 'tnum'; font-variant-numeric: tabular-nums;">
                    {{ currentTime }}
                </h1>
                <p class="text-xl md:text-2xl text-slate-600 font-bold tracking-wide">{{ currentDate }}</p>
            </div>

            <!-- Main Scanner Glass Card -->
            <div class="w-full bg-white/40 backdrop-blur-2xl border border-white/60 rounded-[2.5rem] p-10 md:p-14 text-center shadow-[0_20px_60px_-15px_rgba(0,0,0,0.1)] relative transition-all duration-500 cursor-pointer"
                 @click="forceFocus"
                 :class="{
                     'scale-[1.02] bg-emerald-50/70 border-emerald-200/80 shadow-[0_20px_60px_-15px_rgba(16,185,129,0.2)]': scanResult?.success,
                     'scale-[1.02] bg-rose-50/70 border-rose-200/80 shadow-[0_20px_60px_-15px_rgba(244,63,94,0.2)]': scanResult && !scanResult.success,
                     'ring-4 ring-rose-400/60 scale-[0.98]': !isScannerFocused,
                     'ring-4 ring-white/40': !scanResult && isScannerFocused
                 }">
                 
                 <!-- OFFLINE OVERLAY -->
                 <div v-if="!isScannerFocused" class="absolute inset-0 z-50 bg-white/50 backdrop-blur-sm rounded-[2.5rem] flex flex-col items-center justify-center">
                     <div class="bg-rose-500 text-white rounded-full p-6 shadow-xl animate-bounce mb-4">
                         <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                         </svg>
                     </div>
                     <h3 class="text-2xl font-black text-rose-600 tracking-wide">SCANNER TIDAK FOKUS</h3>
                     <p class="text-slate-600 font-bold mt-2">Klik area ini agar mesin dapat membaca kartu.</p>
                 </div>
                 
                 <!-- STATE: Idle / Waiting for Scan -->
                 <transition name="fade" mode="out-in">
                     <div v-if="!scanResult && !isLoading" class="flex flex-col items-center">
                         <!-- Info Jam Kerja -->
                         <div class="mb-6 flex gap-4 md:gap-8 justify-center">
                             <div class="bg-white/50 backdrop-blur-md border border-white/60 px-4 py-2 rounded-xl text-center shadow-sm">
                                 <p class="text-[10px] uppercase font-bold text-slate-500 tracking-wider mb-0.5">Jam Masuk</p>
                                 <p class="text-xl font-black text-emerald-600">{{ $page.props.global_settings?.presensi_work_start || '07:00' }}</p>
                             </div>
                             <div class="bg-white/50 backdrop-blur-md border border-white/60 px-4 py-2 rounded-xl text-center shadow-sm">
                                 <p class="text-[10px] uppercase font-bold text-slate-500 tracking-wider mb-0.5">Jam Keluar</p>
                                 <p class="text-xl font-black text-rose-600">{{ $page.props.global_settings?.presensi_work_end || '16:00' }}</p>
                             </div>
                         </div>

                         <!-- Flex Row for ENTER and EXIT Scanners -->
                         <div class="flex flex-col md:flex-row gap-6 md:gap-12 mb-8 w-full justify-center items-center">
                             
                             <!-- ENTER Scanner -->
                             <div class="flex flex-col items-center group relative cursor-default">
                                 <div class="absolute -inset-4 bg-emerald-500/10 rounded-[3rem] animate-pulse opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                                 <div class="relative w-64 h-48 md:w-72 md:h-56 bg-white/70 border border-emerald-200/50 rounded-[2rem] flex items-center justify-center shadow-lg z-10 overflow-hidden backdrop-blur-xl mb-4 transition-all duration-500 group-hover:shadow-[0_10px_40px_-10px_rgba(16,185,129,0.3)]">
                                     <div class="absolute top-4 left-4 bg-emerald-100 text-emerald-700 px-3 py-1 rounded-lg text-xs font-black tracking-widest z-20 shadow-sm border border-emerald-200/50">ENTER</div>
                                     <svg viewBox="0 0 400 300" class="w-full h-full drop-shadow-sm group-hover:scale-105 transition-transform duration-500">
                                         <defs>
                                             <filter id="glow">
                                                 <feGaussianBlur stdDeviation="3" result="coloredBlur"/>
                                                 <feMerge>
                                                     <feMergeNode in="coloredBlur"/>
                                                     <feMergeNode in="SourceGraphic"/>
                                                 </feMerge>
                                             </filter>
                                         </defs>
                                         
                                         <!-- Floor -->
                                         <line x1="0" y1="240" x2="400" y2="240" stroke="#cbd5e1" stroke-width="4" stroke-linecap="round" />
                                         
                                         <!-- Wall / Door frame -->
                                         <path d="M260 80 L340 80 L340 240 L260 240 Z" fill="#f8fafc" stroke="#94a3b8" stroke-width="4" stroke-linejoin="round" />
                                         
                                         <!-- Door Leaf -->
                                         <rect class="anim-door" x="262" y="82" width="76" height="156" fill="#e2e8f0" stroke="#94a3b8" stroke-width="2" style="transform-origin: 262px 82px;" />
                                         
                                         <!-- RFID Reader -->
                                         <rect x="230" y="140" width="14" height="28" rx="3" fill="#cbd5e1" stroke="#94a3b8" stroke-width="2" />
                                         <!-- Reader Screen/Light -->
                                         <rect class="anim-reader" x="233" y="143" width="8" height="8" rx="2" fill="#ef4444" />

                                         <!-- Person Group -->
                                         <g class="anim-person">
                                             <!-- Head -->
                                             <circle cx="150" cy="110" r="16" fill="none" stroke="#475569" stroke-width="5" />
                                             <!-- Body -->
                                             <line x1="150" y1="126" x2="150" y2="175" stroke="#475569" stroke-width="6" stroke-linecap="round" />
                                             <!-- Legs -->
                                             <g>
                                                 <line class="anim-leg1" x1="150" y1="175" x2="140" y2="238" stroke="#475569" stroke-width="5" stroke-linecap="round" style="transform-origin: 150px 175px;" />
                                                 <line class="anim-leg2" x1="150" y1="175" x2="160" y2="238" stroke="#475569" stroke-width="5" stroke-linecap="round" style="transform-origin: 150px 175px;" />
                                             </g>
                                             <!-- Static Arm (Back) -->
                                             <line x1="150" y1="135" x2="140" y2="165" stroke="#94a3b8" stroke-width="5" stroke-linecap="round" />
                                             <!-- Moving Arm (Front) -->
                                             <g class="anim-arm" style="transform-origin: 150px 135px;">
                                                 <line x1="150" y1="135" x2="160" y2="175" stroke="#475569" stroke-width="5" stroke-linecap="round" />
                                                 <!-- Access Card -->
                                                 <rect x="153" y="171" width="14" height="10" rx="2" fill="#10b981" transform="rotate(-15 153 171)" style="filter: url(#glow);" />
                                             </g>
                                         </g>
                                     </svg>
                                 </div>
                             </div>

                             <!-- EXIT Scanner -->
                             <div class="flex flex-col items-center group relative cursor-default">
                                 <div class="absolute -inset-4 bg-rose-500/10 rounded-[3rem] animate-pulse opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                                 <div class="relative w-64 h-48 md:w-72 md:h-56 bg-white/70 border border-rose-200/50 rounded-[2rem] flex items-center justify-center shadow-lg z-10 overflow-hidden backdrop-blur-xl mb-4 transition-all duration-500 group-hover:shadow-[0_10px_40px_-10px_rgba(244,63,94,0.3)]">
                                     <div class="absolute top-4 left-4 bg-rose-100 text-rose-700 px-3 py-1 rounded-lg text-xs font-black tracking-widest z-20 shadow-sm border border-rose-200/50">EXIT</div>
                                     <!-- Mirrored SVG for Exit (Walking out) -->
                                     <svg viewBox="0 0 400 300" class="w-full h-full drop-shadow-sm group-hover:scale-105 transition-transform duration-500" style="transform: scaleX(-1);">
                                         <defs>
                                             <filter id="glow-exit">
                                                 <feGaussianBlur stdDeviation="3" result="coloredBlur"/>
                                                 <feMerge>
                                                     <feMergeNode in="coloredBlur"/>
                                                     <feMergeNode in="SourceGraphic"/>
                                                 </feMerge>
                                             </filter>
                                         </defs>
                                         
                                         <!-- Floor -->
                                         <line x1="0" y1="240" x2="400" y2="240" stroke="#cbd5e1" stroke-width="4" stroke-linecap="round" />
                                         
                                         <!-- Wall / Door frame -->
                                         <path d="M260 80 L340 80 L340 240 L260 240 Z" fill="#f8fafc" stroke="#94a3b8" stroke-width="4" stroke-linejoin="round" />
                                         
                                         <!-- Door Leaf -->
                                         <rect class="anim-door" x="262" y="82" width="76" height="156" fill="#e2e8f0" stroke="#94a3b8" stroke-width="2" style="transform-origin: 262px 82px;" />
                                         
                                         <!-- RFID Reader -->
                                         <rect x="230" y="140" width="14" height="28" rx="3" fill="#cbd5e1" stroke="#94a3b8" stroke-width="2" />
                                         <!-- Reader Screen/Light -->
                                         <rect class="anim-reader" x="233" y="143" width="8" height="8" rx="2" fill="#ef4444" />

                                         <!-- Person Group -->
                                         <g class="anim-person">
                                             <!-- Head -->
                                             <circle cx="150" cy="110" r="16" fill="none" stroke="#475569" stroke-width="5" />
                                             <!-- Body -->
                                             <line x1="150" y1="126" x2="150" y2="175" stroke="#475569" stroke-width="6" stroke-linecap="round" />
                                             <!-- Legs -->
                                             <g>
                                                 <line class="anim-leg1" x1="150" y1="175" x2="140" y2="238" stroke="#475569" stroke-width="5" stroke-linecap="round" style="transform-origin: 150px 175px;" />
                                                 <line class="anim-leg2" x1="150" y1="175" x2="160" y2="238" stroke="#475569" stroke-width="5" stroke-linecap="round" style="transform-origin: 150px 175px;" />
                                             </g>
                                             <!-- Static Arm (Back) -->
                                             <line x1="150" y1="135" x2="140" y2="165" stroke="#94a3b8" stroke-width="5" stroke-linecap="round" />
                                             <!-- Moving Arm (Front) -->
                                             <g class="anim-arm" style="transform-origin: 150px 135px;">
                                                 <line x1="150" y1="135" x2="160" y2="175" stroke="#475569" stroke-width="5" stroke-linecap="round" />
                                                 <!-- Access Card -->
                                                 <rect x="153" y="171" width="14" height="10" rx="2" fill="#10b981" transform="rotate(-15 153 171)" style="filter: url(#glow-exit);" />
                                             </g>
                                         </g>
                                     </svg>
                                 </div>
                             </div>
                             
                         </div>
                         <h2 class="text-3xl md:text-4xl font-black text-slate-800 mb-3 tracking-tight">Silakan Tempelkan Kartu</h2>
                         <p class="text-slate-500 text-lg font-medium">Arahkan kartu RFID Anda pada mesin pemindai</p>
                     </div>

                     <!-- STATE: Processing -->
                     <div v-else-if="isLoading" class="flex flex-col items-center py-8">
                         <div class="relative w-24 h-24 mb-6">
                             <svg class="animate-spin w-full h-full text-emerald-500 drop-shadow-md" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                 <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                 <path class="opacity-100" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                             </svg>
                         </div>
                         <h2 class="text-2xl font-black text-slate-700 tracking-widest animate-pulse">MEMPROSES...</h2>
                     </div>

                     <!-- STATE: Result -->
                     <div v-else-if="scanResult" class="flex flex-col items-center animate-scale-up">
                         <!-- Success State -->
                         <template v-if="scanResult.success">
                             <div class="w-24 h-24 bg-gradient-to-br from-emerald-400 to-emerald-600 rounded-full flex items-center justify-center mb-6 shadow-[0_15px_30px_rgba(16,185,129,0.3)] relative">
                                 <svg class="w-12 h-12 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                     <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                 </svg>
                             </div>
                             
                             <div class="bg-emerald-100 text-emerald-700 px-5 py-1.5 rounded-full font-black mb-4 uppercase tracking-[0.2em] text-xs border border-emerald-200 shadow-sm">
                                 {{ scanResult.type === 'in' ? 'BERHASIL CHECK-IN' : 'BERHASIL CHECK-OUT' }}
                             </div>
                             
                             <h2 class="text-4xl font-black text-slate-800 mb-2 tracking-tight">{{ scanResult.user.name }}</h2>
                             <p class="text-slate-500 font-medium text-xl">{{ scanResult.message }}</p>
                         </template>

                         <!-- Error State -->
                         <template v-else>
                             <div class="w-24 h-24 bg-gradient-to-br from-rose-400 to-rose-600 rounded-full flex items-center justify-center mb-6 shadow-[0_15px_30px_rgba(244,63,94,0.3)]">
                                 <svg class="w-12 h-12 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                     <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                 </svg>
                             </div>
                             
                             <div class="bg-rose-100 text-rose-700 px-5 py-1.5 rounded-full font-black mb-4 uppercase tracking-[0.2em] text-xs border border-rose-200 shadow-sm">
                                 GAGAL MEMINDAI
                             </div>
                             
                             <h2 class="text-3xl font-black text-slate-800 mb-2">Akses Ditolak</h2>
                             <p class="text-slate-500 font-medium text-lg">{{ scanResult.message }}</p>
                         </template>
                     </div>
                 </transition>
            </div>

            <!-- Footer & Utility -->
            <div class="mt-8 text-slate-500 text-sm flex flex-col items-center gap-4">
                <button @click="forceFocus" class="px-6 py-2.5 rounded-full bg-white/60 border border-white/80 hover:bg-white hover:text-slate-800 backdrop-blur-xl shadow-sm hover:shadow transition-all duration-300 text-xs font-bold tracking-wider flex items-center gap-2 text-slate-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    Fokuskan Kembali Scanner
                </button>
            </div>
            
            </div> <!-- End Left Column -->

            <!-- Right Column: History Table -->
            <div class="w-full bg-white/60 backdrop-blur-3xl border border-white/80 rounded-[2.5rem] p-6 shadow-[0_20px_60px_-15px_rgba(0,0,0,0.1)] flex flex-col lg:h-[85vh]">
                <div class="px-4 py-3 border-b border-slate-200/60 mb-4 flex items-center justify-between">
                    <h2 class="text-xl md:text-2xl font-black text-slate-800 tracking-tight flex items-center gap-3">
                        <svg class="w-7 h-7 text-emerald-600 drop-shadow-sm" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Riwayat Presensi Hari Ini
                    </h2>
                    <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-xs font-bold shadow-inner border border-emerald-200">{{ attendances.length }} Data</span>
                </div>
                
                <div class="overflow-y-auto flex-1 pr-2 custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead class="sticky top-0 bg-white/90 backdrop-blur-md z-10 shadow-sm rounded-lg">
                            <tr>
                                <th class="py-3 px-4 text-xs font-black text-slate-500 uppercase tracking-widest rounded-l-xl">Nama Pegawai</th>
                                <th class="py-3 px-4 text-xs font-black text-slate-500 uppercase tracking-widest text-center">Masuk</th>
                                <th class="py-3 px-4 text-xs font-black text-slate-500 uppercase tracking-widest text-center">Keluar</th>
                                <th class="py-3 px-4 text-xs font-black text-slate-500 uppercase tracking-widest text-center rounded-r-xl">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/50">
                            <tr v-for="att in attendances" :key="att.id" class="hover:bg-white/50 transition-colors group">
                                <td class="py-4 px-4 font-bold text-slate-800 text-sm group-hover:text-emerald-700 transition-colors">{{ att.name }}</td>
                                <td class="py-4 px-4 text-sm font-bold text-slate-600 text-center">
                                    <span class="inline-flex items-center justify-center gap-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded-md shadow-sm" :title="att.notes">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> 
                                        {{ att.time_in ? att.time_in.substring(0,5) : '--:--' }}
                                        <template v-if="att.time_in">
                                            <svg v-if="att.notes?.toLowerCase().includes('rfid')" class="w-3.5 h-3.5 opacity-70 ml-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                                            </svg>
                                            <svg v-else-if="att.notes?.toLowerCase().includes('finger')" class="w-3.5 h-3.5 opacity-70 ml-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4" />
                                            </svg>
                                            <svg v-else class="w-3.5 h-3.5 opacity-70 ml-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </template>
                                    </span>
                                </td>
                                <td class="py-4 px-4 text-sm font-bold text-slate-600 text-center">
                                    <span class="inline-flex items-center justify-center gap-1.5 bg-rose-50 text-rose-700 border border-rose-200 px-2 py-0.5 rounded-md shadow-sm" :title="att.notes">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> 
                                        {{ att.time_out ? att.time_out.substring(0,5) : '--:--' }}
                                        <template v-if="att.time_out">
                                            <svg v-if="att.notes?.toLowerCase().includes('rfid')" class="w-3.5 h-3.5 opacity-70 ml-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                                            </svg>
                                            <svg v-else-if="att.notes?.toLowerCase().includes('finger')" class="w-3.5 h-3.5 opacity-70 ml-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4" />
                                            </svg>
                                            <svg v-else class="w-3.5 h-3.5 opacity-70 ml-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </template>
                                    </span>
                                </td>
                                <td class="py-4 px-4 text-center">
                                    <span :class="['px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest border shadow-sm', 
                                        att.status === 'terlambat' ? 'bg-amber-50 text-amber-600 border-amber-200' : 'bg-emerald-50 text-emerald-600 border-emerald-200']">
                                        {{ att.status }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="attendances.length === 0">
                                <td colspan="4" class="text-center py-16">
                                    <div class="flex flex-col items-center justify-center text-slate-400">
                                        <svg class="w-12 h-12 mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                        </svg>
                                        <span class="font-bold text-sm tracking-widest uppercase">Belum ada data presensi</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div> <!-- End Right Column -->
        </div>
    </div>
</template>

<style>
/* Custom Animations */
@keyframes blob {
  0% { transform: translate(0px, 0px) scale(1); }
  33% { transform: translate(30px, -50px) scale(1.1); }
  66% { transform: translate(-20px, 20px) scale(0.9); }
  100% { transform: translate(0px, 0px) scale(1); }
}
.animate-blob {
  animation: blob 7s infinite;
}
.animation-delay-2000 {
  animation-delay: 2s;
}
.animation-delay-4000 {
  animation-delay: 4s;
}
.animate-scale-up {
  animation: scaleUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}
@keyframes scaleUp {
  from { opacity: 0; transform: scale(0.8); }
  to { opacity: 1; transform: scale(1); }
}

/* Fade Transition */
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(148, 163, 184, 0.3);
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(148, 163, 184, 0.5);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}
.fade-enter-from {
  opacity: 0;
  transform: translateY(10px);
}
.fade-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

/* Custom SVG Scene Animations */
.anim-person { animation: personMove 5s infinite cubic-bezier(0.4, 0, 0.2, 1); }
.anim-leg1 { animation: leg1Move 5s infinite linear; }
.anim-leg2 { animation: leg2Move 5s infinite linear; }
.anim-arm { animation: armMove 5s infinite ease-in-out; }
.anim-reader { animation: readerLight 5s infinite step-end; }
.anim-door { animation: doorOpen 5s infinite ease-in-out; }

@keyframes personMove {
    0%, 5% { transform: translateX(-150px); opacity: 1; }
    25% { transform: translateX(45px); opacity: 1; }
    40% { transform: translateX(45px); opacity: 1; }
    50% { transform: translateX(100px); opacity: 0; }
    100% { transform: translateX(-150px); opacity: 0; }
}

@keyframes leg1Move {
    0%, 5% { transform: rotate(0deg); }
    10% { transform: rotate(25deg); }
    15% { transform: rotate(-25deg); }
    20% { transform: rotate(25deg); }
    25% { transform: rotate(0deg); }
    40% { transform: rotate(0deg); }
    43% { transform: rotate(25deg); }
    46% { transform: rotate(-25deg); }
    50%, 100% { transform: rotate(0deg); }
}

@keyframes leg2Move {
    0%, 5% { transform: rotate(0deg); }
    10% { transform: rotate(-25deg); }
    15% { transform: rotate(25deg); }
    20% { transform: rotate(-25deg); }
    25% { transform: rotate(0deg); }
    40% { transform: rotate(0deg); }
    43% { transform: rotate(-25deg); }
    46% { transform: rotate(25deg); }
    50%, 100% { transform: rotate(0deg); }
}

@keyframes armMove {
    0%, 25% { transform: rotate(0deg); }
    28%, 35% { transform: rotate(-55deg); }
    38%, 100% { transform: rotate(0deg); }
}

@keyframes readerLight {
    0%, 30% { fill: #ef4444; filter: none; }
    31%, 50% { fill: #10b981; filter: url(#glow); }
    51%, 100% { fill: #ef4444; filter: none; }
}

@keyframes doorOpen {
    0%, 32% { transform: scaleX(1); fill: #e2e8f0; }
    36%, 55% { transform: scaleX(0.15); fill: #f1f5f9; }
    60%, 100% { transform: scaleX(1); fill: #e2e8f0; }
}
</style>
