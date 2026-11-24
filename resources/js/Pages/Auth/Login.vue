<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref, computed, onMounted } from 'vue'
import { useDarkMode } from '@/Composables/useDarkMode'
import { useIslamicData } from '@/Composables/useIslamicData'

const { isDark, toggleDark } = useDarkMode()
const { dailyHadith, isLoadingHadith } = useIslamicData()
const showPassword = ref(false)

const form = useForm({
    email: '',
    password: '',
    remember: false,
    latitude: null,
    longitude: null,
})

// Hitung tanggal Masehi & Hijriah lokal untuk sisi visual
const currentDateMasehi = computed(() => {
    try {
        return new Intl.DateTimeFormat('id-ID', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        }).format(new Date())
    } catch {
        return ''
    }
})

const currentDateHijri = computed(() => {
    try {
        const fmt = new Intl.DateTimeFormat('id-u-ca-islamic-umalqura', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        })
        return fmt.format(new Date()) + ' H'
    } catch {
        return '1447 H'
    }
})

// Ekstraksi teks matan (isi inti hadits) dan singkirkan rentetan sanad panjang
const hadithMatn = computed(() => {
    const raw = dailyHadith.value?.text
    if (!raw) return 'Sebaik-baik manusia adalah yang paling bermanfaat bagi manusia lainnya.'

    let text = raw.trim()

    // 1. Ekstraksi kutipan setelah kata 'bersabda:' atau 'berkata:'
    const quoteAfterSabda = text.match(/(?:bersabda|berkata|ucapan beliau)\s*[:;,]?\s*["“'‘]([^"”'’]{15,})["”'’]/i)
    if (quoteAfterSabda && quoteAfterSabda[1]) {
        return quoteAfterSabda[1].trim()
    }

    // 2. Ekstraksi kutipan ganda pertama yang memuat matan (>20 karakter)
    const quotes = [...text.matchAll(/["“'‘]([^"”'’]{20,})["”'’]/g)]
    if (quotes.length > 0) {
        return quotes[0][1].trim()
    }

    // 3. Potong teks setelah kata 'bersabda:' atau 'berkata:' jika tidak bertanda kutip
    const afterSabda = text.match(/(?:bersabda|berkata)\s*[:;,]\s*(.+)$/i)
    if (afterSabda && afterSabda[1]) {
        let matn = afterSabda[1].trim()
        matn = matn.split(/(?:Abu\s+\w+\s+berkata|Telah\s+menceritakan\s+kepada\s+kami|\(dalam\s+riwayat\s+lain\))/i)[0].trim()
        matn = matn.replace(/^["“'‘\s:;,]+|["”'’\s]+$/g, '').trim()
        if (matn.length >= 15) return matn
    }

    // 4. Potong frasa sanad pembuka "bahwasanya / bahwa Nabi ..."
    const bahwaNabi = text.match(/(?:bahwasanya|bahwa)\s+(Nabi\s+shallallahu\s+[^\n]+)/i)
    if (bahwaNabi && bahwaNabi[1]) {
        let matn = bahwaNabi[1].trim()
        matn = matn.split(/(?:Abu\s+\w+\s+berkata|Telah\s+menceritakan\s+kepada\s+kami)/i)[0].trim()
        if (matn.length >= 15) return matn
    }

    // 5. Jika diawali "Telah menceritakan kepada kami...", potong rantai sanad
    if (/^telah\s+(?:menceritakan|mengabarkan)\s+kepada\s+kami/i.test(text)) {
        const parts = text.split(/(?:dari\s+(?:Nabi|Rasulullah)[^,;:]*[,;:]|Nabi\s+shallallahu\s+[^\s]+\s+bersabda\s*[:;,])/i)
        if (parts.length > 1) {
            let matn = parts.slice(1).join(' ').trim()
            matn = matn.replace(/^["“'‘\s:;,]+|["”'’\s]+$/g, '').trim()
            if (matn.length >= 15) return matn
        }
    }

    return text.replace(/^["“'‘\s]+|["”'’\s]+$/g, '').trim()
})

onMounted(() => {
    // Opsional: Rekam koordinat GPS perangkat secara aman jika diizinkan oleh pengguna/browser
    if (typeof navigator !== 'undefined' && 'geolocation' in navigator) {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                form.latitude = pos.coords.latitude
                form.longitude = pos.coords.longitude
            },
            () => {
                // Abaikan jika user menolak izin lokasi atau tidak didukung
            },
            {
                enableHighAccuracy: true,
                timeout: 5000,
                maximumAge: 60000,
            }
        )
    }
})

const submit = () => {
    form.post(route('login.attempt'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Login - SAHABAT SDAM" />

    <div class="min-h-screen w-full flex flex-col lg:flex-row bg-slate-50 dark:bg-slate-950 relative overflow-hidden selection:bg-emerald-500 selection:text-white">
        
        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- BACKGROUND DECORATIONS FOR MOBILE (Soft-UI VR Wallpaper)   -->
        <!-- ═══════════════════════════════════════════════════════════ -->
        <div class="lg:hidden absolute inset-0 z-0 pointer-events-none">
            <img 
                src="/img/vr-nature-bg.jpg" 
                alt="" 
                class="w-full h-full object-cover object-center filter blur-[2px] scale-105 opacity-40 dark:opacity-20"
                aria-hidden="true"
            />
            <div class="absolute inset-0 bg-gradient-to-b from-white/80 via-slate-50/90 to-white/95 dark:from-slate-950/85 dark:via-slate-900/90 dark:to-slate-950/95 backdrop-blur-xs"></div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- FLOATING THEME TOGGLE (POJOK KANAN ATAS)                   -->
        <!-- ═══════════════════════════════════════════════════════════ -->
        <div class="absolute top-4 right-4 z-30">
            <button
                type="button"
                @click="toggleDark"
                :title="isDark ? 'Beralih ke mode terang' : 'Beralih ke mode gelap'"
                class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/80 dark:bg-slate-800/80 backdrop-blur-md border border-slate-200/80 dark:border-slate-700/80 text-slate-600 dark:text-slate-300 hover:text-emerald-600 dark:hover:text-emerald-400 shadow-sm hover:shadow transition-all cursor-pointer"
                :aria-label="isDark ? 'Mode terang' : 'Mode gelap'"
            >
                <!-- Sun icon (mode gelap aktif) -->
                <svg v-if="isDark" class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z" />
                </svg>
                <!-- Moon icon (mode terang aktif) -->
                <svg v-else class="w-5 h-5 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>
            </button>
        </div>

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- SISI KIRI: FORMULIR LOGIN                                  -->
        <!-- (Desktop: Clean Split-Screen | Mobile: Floating Glass Card) -->
        <!-- ═══════════════════════════════════════════════════════════ -->
        <div class="w-full lg:w-[48%] xl:w-[45%] flex flex-col justify-between min-h-screen p-5 sm:p-8 lg:p-12 xl:p-16 z-10 relative">
            
            <!-- Header Brand -->
            <div class="flex items-center justify-between mb-6 lg:mb-0">
                <Link :href="route('welcome')" class="inline-flex items-center gap-3.5 group">
                    <img 
                        src="/logo.png" 
                        alt="Logo SDAM" 
                        class="w-11 h-11 sm:w-12 sm:h-12 object-contain group-hover:scale-105 transition-transform drop-shadow-sm" 
                    />
                    <div class="flex flex-col">
                        <span class="text-xl sm:text-2xl font-black text-slate-900 dark:text-slate-50 tracking-tight leading-none group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
                            SAHABAT SDAM
                        </span>
                        <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 mt-1 tracking-wide">
                                                    Sistem Absensi, Habit, Administrasi & Buku Tugas SD Al Mujahidin Wonosari
                        </span>
                    </div>
                </Link>
            </div>

            <!-- Formulir Container: (Mobile: Card Melayang | Desktop: Clean Minimalis) -->
            <div class="my-auto py-6 sm:py-8 w-full max-w-md mx-auto">
                
                <div class="bg-white/90 dark:bg-slate-900/90 lg:bg-transparent lg:dark:bg-transparent backdrop-blur-xl lg:backdrop-blur-none border border-white/80 dark:border-slate-800/80 lg:border-none rounded-3xl lg:rounded-none p-6 sm:p-8 lg:p-0 shadow-2xl lg:shadow-none shadow-emerald-950/10 transition-all">
                    
                    <!-- Sambutan & Header Form -->
                    <div class="mb-7">
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 text-xs font-bold border border-emerald-200/70 dark:border-emerald-800/70 mb-3">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 motion-safe:animate-pulse"></span>
                            Portal Resmi Kepegawaian
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                            Selamat Datang
                        </h1>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">
                            Silakan masuk dengan akun Anda untuk mengakses presensi, habit, dan administrasi sekolah.
                        </p>
                    </div>

                    <!-- Alert Error jika Login Gagal -->
                    <div 
                        v-if="form.errors.email || form.errors.password" 
                        class="mb-6 p-3.5 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 flex items-start gap-3 shadow-xs animate-in fade-in duration-200"
                    >
                        <div class="w-8 h-8 rounded-xl bg-rose-100 dark:bg-rose-900/60 text-rose-600 dark:text-rose-400 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold text-rose-800 dark:text-rose-300">Autentikasi Gagal</p>
                            <p class="text-xs text-rose-600 dark:text-rose-400 mt-0.5 leading-relaxed">
                                {{ form.errors.email || form.errors.password }}
                            </p>
                        </div>
                    </div>

                    <!-- Formulir Utama -->
                    <form @submit.prevent="submit" class="space-y-5">
                        
                        <!-- Kolom Email -->
                        <div>
                            <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-2">
                                Alamat Email
                            </label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-600 dark:group-focus-within:text-emerald-400 transition-colors">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <input 
                                    id="email" 
                                    type="email" 
                                    v-model="form.email" 
                                    required 
                                    autofocus 
                                    autocomplete="username"
                                    class="block w-full pl-11 pr-4 py-3 text-sm bg-slate-50/80 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder-slate-400 focus:bg-white dark:focus:bg-slate-800 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/15 transition-all outline-none"
                                    placeholder="nama@sdam.sch.id"
                                />
                            </div>
                        </div>

                        <!-- Kolom Password -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                    Kata Sandi
                                </label>
                                <a 
                                    href="https://wa.link/bh9pkl" 
                                    target="_blank"
                                    class="text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 hover:underline transition-colors"
                                >
                                    Lupa sandi?
                                </a>
                            </div>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-600 dark:group-focus-within:text-emerald-400 transition-colors">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                                <input 
                                    id="password" 
                                    :type="showPassword ? 'text' : 'password'" 
                                    v-model="form.password" 
                                    required 
                                    autocomplete="current-password"
                                    class="block w-full pl-11 pr-11 py-3 text-sm bg-slate-50/80 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder-slate-400 focus:bg-white dark:focus:bg-slate-800 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/15 transition-all outline-none"
                                    placeholder="••••••••"
                                />
                                <button 
                                    type="button" 
                                    @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 focus:outline-none transition-colors cursor-pointer"
                                    :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                                >
                                    <!-- Eye Open -->
                                    <svg v-if="!showPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <!-- Eye Closed -->
                                    <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Ingat Saya Checkbox -->
                        <div class="flex items-center justify-between pt-1">
                            <label class="flex items-center gap-2.5 cursor-pointer group select-none">
                                <div class="relative flex items-center justify-center">
                                    <input 
                                        type="checkbox" 
                                        v-model="form.remember"
                                        class="peer appearance-none w-4 h-4 border border-slate-300 dark:border-slate-600 rounded-md focus:ring-2 focus:ring-emerald-500 focus:outline-none checked:bg-emerald-600 checked:border-emerald-600 transition-colors cursor-pointer"
                                    />
                                    <svg class="w-3 h-3 text-white absolute pointer-events-none opacity-0 peer-checked:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-xs font-semibold text-slate-600 dark:text-slate-400 group-hover:text-slate-900 dark:group-hover:text-slate-200 transition-colors">
                                    Ingat saya di perangkat ini
                                </span>
                            </label>
                        </div>

                        <!-- Tombol Submit Utama -->
                        <div class="pt-2">
                            <button 
                                type="submit" 
                                :disabled="form.processing"
                                class="w-full inline-flex justify-center items-center gap-2.5 px-6 py-3.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 active:scale-[0.99] rounded-xl font-bold text-white shadow-lg shadow-emerald-700/20 hover:shadow-emerald-700/30 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all cursor-pointer text-sm"
                            >
                                <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 w-5 h-5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span v-if="form.processing">Memproses Masuk...</span>
                                <span v-else>Masuk ke Akun</span>
                                
                                <svg v-if="!form.processing" class="w-4 h-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </button>
                        </div>

                    </form>

                </div>

            </div>

            <!-- Footer Bantuan Waka SDM -->
            <div class="pt-6 border-t border-slate-200/60 dark:border-slate-800/60 text-center sm:text-left flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                <p>
                    Guru/Karyawan baru? 
                    <a 
                        href="https://wa.link/bh9pkl" 
                        target="_blank"
                        class="font-bold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 hover:underline transition-colors ml-1"
                    >
                        Hubungi Waka SDM &rarr;
                    </a>
                </p>
                <p class="text-[11px] text-slate-400 dark:text-slate-500">
                    &copy; {{ new Date().getFullYear() }} SD Al Mujahidin  || by @blue_core21
                </p>
            </div>

        </div>

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- SISI KANAN: SHOWCASE VISUAL ASIMETRIS (KHUSUS DESKTOP)       -->
        <!-- ═══════════════════════════════════════════════════════════ -->
        <div class="hidden lg:flex lg:w-[52%] xl:w-[55%] relative min-h-screen flex-col justify-between p-10 xl:p-14 2xl:p-16 overflow-hidden bg-emerald-950">
            
            <!-- Foto Wallpaper Lanskap Alam Soft-UI -->
            <div class="absolute inset-0 z-0">
                <img 
                    src="/img/vr-nature-bg.jpg" 
                    alt="Panorama Alam SD Al Mujahidin" 
                    class="w-full h-full object-cover object-center scale-105 filter blur-[0.5px] transform transition-transform duration-1000"
                />
                <!-- Gradient Overlay yang Sejuk & Elegan -->
                <div class="absolute inset-0 bg-gradient-to-t from-emerald-950/95 via-emerald-900/60 to-emerald-950/75 mix-blend-multiply"></div>
                <div class="absolute inset-0 bg-radial from-transparent via-emerald-950/40 to-emerald-950/90"></div>
            </div>

            <!-- Bagian Atas Sisi Kanan: Penanda Tanggal & Waktu Islami -->
            <div class="relative z-10 flex items-center justify-between">
                <div class="inline-flex items-center gap-2.5 px-4 py-2 rounded-2xl bg-white/15 dark:bg-slate-900/40 backdrop-blur-md border border-white/20 text-white text-xs font-semibold shadow-lg">
                    <!-- <svg class="w-4 h-4 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg> -->
                    <span>{{ currentDateMasehi }}</span>
                    <span class="text-white/40">•</span>
                    <span class="text-amber-200 font-bold">{{ currentDateHijri }}</span>
                </div>

                <!-- <div class="px-3.5 py-1.5 rounded-full bg-emerald-500/20 backdrop-blur-md border border-emerald-400/30 text-emerald-200 text-xs font-bold tracking-wide uppercase">
                    Terakreditasi A
                </div> -->
            </div>

            <!-- Bagian Tengah/Bawah: Kartu Kaca Melayang (Hadis & Pesan Inspirasi) -->
            <div class="relative z-10 mt-auto space-y-6">
                
                <div class="bg-white/15 dark:bg-slate-900/60 backdrop-blur-xl border border-white/25 dark:border-white/10 rounded-3xl p-8 xl:p-10 text-white shadow-2xl space-y-5 max-w-xl">
                    
                    <!-- Simbol Tanda Kutip Cantik -->
                    <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center text-amber-300 shadow-inner">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                            <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z" />
                        </svg>
                    </div>

                    <!-- Kutipan Hadits Harian Dinamis (Acak Tiap Hari via useIslamicData) -->
                    <div v-if="isLoadingHadith && !dailyHadith" class="space-y-2.5 py-1">
                        <div class="h-4 bg-white/20 rounded-md animate-pulse w-full"></div>
                        <div class="h-4 bg-white/20 rounded-md animate-pulse w-5/6"></div>
                        <div class="h-4 bg-white/20 rounded-md animate-pulse w-4/6"></div>
                    </div>
                    <blockquote v-else class="text-base xl:text-lg font-bold leading-relaxed text-white drop-shadow-sm font-sans line-clamp-4">
                        “{{ hadithMatn }}”
                    </blockquote>

                    <!-- Sumber & Identitas Sekolah -->
                    <div class="pt-4 border-t border-white/20 flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold text-amber-300 uppercase tracking-wider truncate">
                                {{ dailyHadith?.source || 'HR. Ahmad & Ath-Thabrani' }}
                            </p>
                            <p class="text-xs text-emerald-100/90 mt-0.5 truncate">
                                Hadits Harian &amp; Pengingat Ibadah
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-xs font-extrabold tracking-wide text-white">SD Al Mujahidin</span>
                            <p class="text-[10px] text-emerald-200">Wonosari, Gunungkidul</p>
                        </div>
                    </div>

                </div>

                <!-- Footer Informasi Ringkas Bawah -->
                <div class="flex items-center gap-6 text-xs text-emerald-100/80 font-medium pt-2">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <!-- Sistem Terenkripsi &amp; Terverifikasi -->
                    </span>
                    <span class="text-emerald-300/40">•</span>
                    <span>Presensi, Habits, Administrasi &amp; File Server</span>
                </div>

            </div>

        </div>

    </div>
</template>
