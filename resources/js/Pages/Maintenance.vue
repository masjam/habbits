<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import RoleSimulatorBar from '@/Components/RoleSimulatorBar.vue'

const props = defineProps({
    title: {
        type: String,
        default: 'Sistem Sedang Dalam Pemeliharaan'
    },
    message: {
        type: String,
        default: 'Kami sedang melakukan pemeliharaan rutin untuk meningkatkan performa dan keandalan sistem. Mohon maaf atas ketidaknyamanan Anda.'
    },
    endTime: {
        type: String,
        default: null
    },
    isActive: {
        type: Boolean,
        default: true
    },
    isActualSuperadmin: {
        type: Boolean,
        default: false
    },
    simulatedRole: {
        type: String,
        default: null
    }
})

// ─── Countdown Logic ────────────────────────────────────────────────────────
const timeLeft = ref({
    days: 0,
    hours: 0,
    minutes: 0,
    seconds: 0,
    isPassed: false,
    hasValidDate: false
})

let timerInterval = null

const calculateCountdown = () => {
    if (!props.endTime) {
        timeLeft.value.hasValidDate = false
        return
    }

    const target = new Date(props.endTime).getTime()
    if (isNaN(target)) {
        timeLeft.value.hasValidDate = false
        return
    }

    timeLeft.value.hasValidDate = true
    const now = new Date().getTime()
    const difference = target - now

    if (difference <= 0) {
        timeLeft.value.isPassed = true
        timeLeft.value.days = 0
        timeLeft.value.hours = 0
        timeLeft.value.minutes = 0
        timeLeft.value.seconds = 0
    } else {
        timeLeft.value.isPassed = false
        timeLeft.value.days = Math.floor(difference / (1000 * 60 * 60 * 24))
        timeLeft.value.hours = Math.floor((difference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))
        timeLeft.value.minutes = Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60))
        timeLeft.value.seconds = Math.floor((difference % (1000 * 60)) / 1000)
    }
}

onMounted(() => {
    calculateCountdown()
    timerInterval = setInterval(calculateCountdown, 1000)
})

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval)
})

// ─── Refresh / Check Status ─────────────────────────────────────────────────
const isChecking = ref(false)
const checkStatus = () => {
    isChecking.value = true
    router.reload({
        onFinish: () => {
            setTimeout(() => {
                isChecking.value = false
            }, 600)
        }
    })
}

// ─── Toggle Maintenance (Khusus Superadmin) ──────────────────────────────────
const toggleMaintenance = () => {
    if (!confirm('Nonaktifkan mode maintenance sekarang dan pulihkan akses seluruh pengguna?')) return
    router.post(route('admin.maintenance.toggle'))
}
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col justify-between relative overflow-hidden font-sans selection:bg-emerald-500 selection:text-white">
        <!-- Ambient Glowing Background Lights -->
        <div class="absolute -top-40 -left-40 w-96 h-96 rounded-full bg-emerald-500/15 blur-3xl pointer-events-none animate-pulse" />
        <div class="absolute top-1/3 -right-32 w-96 h-96 rounded-full bg-amber-500/15 blur-3xl pointer-events-none animate-pulse" style="animation-delay: 1.5s;" />
        <div class="absolute -bottom-32 left-1/3 w-96 h-96 rounded-full bg-teal-500/10 blur-3xl pointer-events-none" />

        <!-- Geometric Pattern Overlay -->
        <div class="absolute inset-0 bg-[radial-gradient(#334155_1px,transparent_1px)] [background-size:24px_24px] opacity-25 pointer-events-none" />

        <!-- ═══ HEADER / LOGO ═════════════════════════════════════════════ -->
        <header class="relative z-10 w-full max-w-5xl mx-auto px-6 pt-8 sm:pt-12 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img
                    src="/logo.png"
                    alt="Logo SD Al Mujahidin"
                    class="w-10 h-10 sm:w-12 sm:h-12 object-contain drop-shadow-[0_4px_12px_rgba(16,185,129,0.35)]"
                />
                <div>
                    <h1 class="text-base sm:text-lg font-black tracking-tight text-white flex items-center gap-2">
                        <span>Gobit SDAM</span>
                        <span class="text-[10px] uppercase font-bold tracking-widest px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                            Habit Tracker
                        </span>
                    </h1>
                    <p class="text-xs text-slate-400 font-medium">SD Al Mujahidin Wonosari</p>
                </div>
            </div>

            <!-- Login Petugas Button (jika belum login atau sedang simulasi role non-superadmin) -->
            <div v-if="!isActualSuperadmin || (simulatedRole && simulatedRole !== 'superadmin')">
                <Link
                    :href="route('login')"
                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-slate-900/80 hover:bg-slate-800 text-slate-300 hover:text-white border border-slate-700/80 text-xs font-bold transition-all shadow-sm hover:border-slate-600"
                >
                    <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                    </svg>
                    <span>Login Petugas</span>
                </Link>
            </div>
            <div v-else>
                <Link
                    :href="route('dashboard')"
                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all shadow-lg shadow-emerald-900/40"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span>Masuk Dashboard</span>
                </Link>
            </div>
        </header>

        <!-- ═══ MAIN CONTENT ══════════════════════════════════════════════ -->
        <main class="relative z-10 flex-1 flex items-center justify-center px-4 sm:px-6 py-8 sm:py-12">
            <div class="w-full max-w-2xl text-center space-y-8">
                
                <!-- Animated Maintenance Emblem -->
                <div class="relative inline-flex items-center justify-center">
                    <!-- Outer pulsating ring -->
                    <div class="absolute w-28 h-28 sm:w-36 sm:h-36 rounded-full bg-gradient-to-tr from-amber-500/20 to-emerald-500/20 animate-ping opacity-60 pointer-events-none" />
                    
                    <!-- Decorative rotating circle -->
                    <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-3xl bg-slate-900/90 border border-slate-800 backdrop-blur-xl shadow-2xl shadow-emerald-950/60 flex items-center justify-center relative p-5 group">
                        <div class="absolute inset-0 rounded-3xl bg-gradient-to-br from-amber-500/10 via-transparent to-emerald-500/10 pointer-events-none" />
                        
                        <!-- Maintenance Gear / Tool Animated Icon -->
                        <svg class="w-12 h-12 sm:w-14 sm:h-14 text-amber-400 animate-[spin_12s_linear_infinite]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>

                        <!-- Sparkle Accent -->
                        <span class="absolute -top-1 -right-1 flex h-4 w-4">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-4 w-4 bg-emerald-500 border-2 border-slate-950"></span>
                        </span>
                    </div>
                </div>

                <!-- Status Pill & Title -->
                <div class="space-y-3">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-black uppercase tracking-wider shadow-sm">
                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                        <span>Mode Pemeliharaan Sedang Berlangsung</span>
                    </div>

                    <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight leading-tight">
                        {{ title }}
                    </h2>

                    <p class="text-sm sm:text-base text-slate-300 max-w-xl mx-auto leading-relaxed whitespace-pre-line font-medium">
                        {{ message }}
                    </p>
                </div>

                <!-- ═══ ESTIMATED TIME / COUNTDOWN CARD ═══════════════════════ -->
                <div v-if="endTime" class="max-w-lg mx-auto bg-slate-900/70 border border-slate-800 rounded-2xl p-5 sm:p-6 shadow-xl backdrop-blur-md space-y-4">
                    <div class="flex items-center justify-center gap-2 text-xs font-semibold text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Estimasi Waktu Selesai:</span>
                        <span class="text-slate-200 font-bold">{{ endTime }}</span>
                    </div>

                    <!-- Interactive Countdown Display (if valid date) -->
                    <div v-if="timeLeft.hasValidDate && !timeLeft.isPassed" class="grid grid-cols-4 gap-2 sm:gap-3 pt-1">
                        <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-2.5 text-center">
                            <span class="block text-xl sm:text-2xl font-black text-emerald-400 font-mono">{{ timeLeft.days }}</span>
                            <span class="text-[10px] uppercase tracking-wider text-slate-400 font-bold">Hari</span>
                        </div>
                        <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-2.5 text-center">
                            <span class="block text-xl sm:text-2xl font-black text-emerald-400 font-mono">{{ String(timeLeft.hours).padStart(2, '0') }}</span>
                            <span class="text-[10px] uppercase tracking-wider text-slate-400 font-bold">Jam</span>
                        </div>
                        <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-2.5 text-center">
                            <span class="block text-xl sm:text-2xl font-black text-emerald-400 font-mono">{{ String(timeLeft.minutes).padStart(2, '0') }}</span>
                            <span class="text-[10px] uppercase tracking-wider text-slate-400 font-bold">Menit</span>
                        </div>
                        <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-2.5 text-center">
                            <span class="block text-xl sm:text-2xl font-black text-amber-400 font-mono animate-pulse">{{ String(timeLeft.seconds).padStart(2, '0') }}</span>
                            <span class="text-[10px] uppercase tracking-wider text-slate-400 font-bold">Detik</span>
                        </div>
                    </div>
                    <div v-else-if="timeLeft.hasValidDate && timeLeft.isPassed" class="text-xs text-amber-400 font-bold py-1 bg-amber-500/10 rounded-lg border border-amber-500/20">
                        ⚡ Tahap finalisasi pembaruan sedang diselesaikan. Mohon tunggu sejenak.
                    </div>
                </div>

                <!-- ═══ ACTION BUTTONS ════════════════════════════════════════ -->
                <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                    <button
                        type="button"
                        @click="checkStatus"
                        :disabled="isChecking"
                        class="px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm transition-all shadow-lg shadow-emerald-950/50 hover:shadow-emerald-900/60 active:scale-95 disabled:opacity-50 flex items-center gap-2 cursor-pointer"
                    >
                        <svg
                            :class="['w-4 h-4', isChecking ? 'animate-spin' : '']"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>{{ isChecking ? 'Memeriksa Status...' : 'Cek Status Sekarang' }}</span>
                    </button>
                </div>

                <!-- ═══ SUPER ADMIN BYPASS NOTICE (JIKA LOGIN SEBAGAI SUPERADMIN & TIDAK SIMULASI USER/ADMIN) ═══ -->
                <div
                    v-if="isActualSuperadmin && (!simulatedRole || simulatedRole === 'superadmin')"
                    class="max-w-xl mx-auto bg-amber-950/40 border border-amber-500/50 rounded-2xl p-4 text-left backdrop-blur-md shadow-2xl"
                >
                    <div class="flex items-start gap-3">
                        <span class="text-2xl mt-0.5">👑</span>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-black text-amber-300">
                                Akses Khusus Super Admin Aktif
                            </h3>
                            <p class="text-xs text-amber-200/80 mt-1 leading-relaxed">
                                Anda memiliki hak akses penuh untuk melakukan perbaikan, konfigurasi, dan uji coba sistem. Pengguna biasa saat ini melihat halaman pemeliharaan ini.
                            </p>
                            <div class="flex flex-wrap items-center gap-2 mt-3">
                                <Link
                                    :href="route('dashboard')"
                                    class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition-colors"
                                >
                                    Masuk ke Dashboard
                                </Link>
                                <Link
                                    :href="route('admin.settings.hr')"
                                    class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs transition-colors border border-slate-700"
                                >
                                    Pengaturan HR &amp; Maintenance
                                </Link>
                                <button
                                    type="button"
                                    @click="toggleMaintenance"
                                    class="px-3 py-1.5 rounded-lg bg-red-600/80 hover:bg-red-600 text-white font-bold text-xs transition-colors"
                                >
                                    Matikan Maintenance
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>

        <!-- ═══ FOOTER ════════════════════════════════════════════════════ -->
        <footer class="relative z-10 w-full max-w-5xl mx-auto px-6 py-6 border-t border-slate-800/80 text-center text-xs text-slate-400">
            <p>&copy; {{ new Date().getFullYear() }} SD Al Mujahidin Wonosari. Seluruh hak cipta dilindungi.</p>
            <p class="text-[11px] text-slate-400 mt-1">Perlu bantuan mendesak? Hubungi Tim IT Support SDAM.</p>
        </footer>

        <!-- ═══ FLOATING ROLE SIMULATOR BAR (KHUSUS SUPERADMIN DI HALAMAN MAINTENANCE) ═══ -->
        <RoleSimulatorBar />
    </div>
</template>
