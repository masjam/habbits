<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useDarkMode } from '@/Composables/useDarkMode'

const props = defineProps({
    status: { type: Number, required: true },
    title: { type: String, required: true },
    message: { type: String, required: true },
    tone: { type: String, default: 'neutral' },
    icon: { type: String, default: 'server' },
    reference: { type: String, default: null },
    retryAfter: { type: Number, default: null },
    homeUrl: { type: String, default: '/' },
    homeLabel: { type: String, default: 'Ke Beranda' },
    effectiveRole: { type: String, default: null },
    isPublicContext: { type: Boolean, default: false },
    isKioskContext: { type: Boolean, default: false },
    canSwitchAccount: { type: Boolean, default: false },
})

const { isDark, toggle: toggleDark } = useDarkMode()
const headingRef = ref(null)
const copied = ref(false)
const countdown = ref(props.retryAfter ? Math.max(0, Number(props.retryAfter)) : 0)
let timer = null

const isRetryable = computed(() => [408, 429, 500, 503].includes(props.status))
const statusDigits = computed(() => String(props.status).split(''))

const stickerMap = {
    400: 'PERMINTAAN KELIRU',
    401: 'SESI BERAKHIR',
    403: 'AKSES DIBATASI',
    404: 'SALAH ALAMAT',
    405: 'METODE DITOLAK',
    408: 'WAKTU HABIS',
    413: 'FILE TERLALU BESAR',
    419: 'TOKEN KEDALUWARSA',
    429: 'TERLALU CEPAT',
    500: 'KENDALA SERVER',
    503: 'PEMELIHARAAN',
}

const stickerText = computed(() => stickerMap[props.status] || `KODE ${props.status}`)

const stickerToneClass = computed(() => ({
    info: 'bg-sky-100 text-sky-800 border-sky-300 dark:bg-sky-950 dark:text-sky-300 dark:border-sky-800',
    warn: 'bg-amber-100 text-amber-900 border-amber-300 dark:bg-amber-950 dark:text-amber-300 dark:border-amber-800',
    danger: 'bg-rose-100 text-rose-900 border-rose-300 dark:bg-rose-950 dark:text-rose-300 dark:border-rose-800',
    neutral: 'bg-slate-200 text-slate-800 border-slate-300 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700',
}[props.tone] || 'bg-slate-200 text-slate-800 border-slate-300'))

const roleLabels = {
    user: 'Pegawai',
    tata_usaha: 'Tata Usaha',
    koperasi: 'Petugas Koperasi',
    kurikulum: 'Kurikulum',
    kepala_sekolah: 'Kepala Sekolah',
    admin: 'Admin',
    superadmin: 'Super Admin',
}

const formattedRole = computed(() => (props.effectiveRole ? (roleLabels[props.effectiveRole] || props.effectiveRole) : null))

const goBack = () => {
    if (typeof window !== 'undefined' && window.history.length > 1) {
        window.history.back()
    } else {
        router.visit(props.homeUrl)
    }
}

const reloadPage = () => {
    if (typeof window !== 'undefined') window.location.reload()
}

const copyReference = async () => {
    if (!props.reference || typeof navigator === 'undefined' || !navigator.clipboard) return
    try {
        await navigator.clipboard.writeText(props.reference)
        copied.value = true
        setTimeout(() => { copied.value = false }, 2000)
    } catch {}
}

onMounted(() => {
    headingRef.value?.focus()

    if (props.status === 429 && countdown.value > 0) {
        timer = setInterval(() => {
            if (countdown.value > 0) countdown.value -= 1
            else {
                clearInterval(timer)
                timer = null
            }
        }, 1000)
    }

    if (props.isKioskContext) {
        timer = setTimeout(() => router.visit(props.homeUrl), 8000)
    }
})

onUnmounted(() => {
    if (timer) {
        clearInterval(timer)
        clearTimeout(timer)
    }
})
</script>

<template>
    <Head>
        <title>{{ `${status} — ${title} · Sahabat SDAM` }}</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <div
        class="min-h-[100dvh] w-full flex flex-col justify-between bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 px-4 sm:px-8 py-4 sm:py-6 pb-[calc(1rem+env(safe-area-inset-bottom))] pt-[calc(1rem+env(safe-area-inset-top))]"
        :role="status >= 500 || status === 429 ? 'alert' : undefined"
    >
        <!-- Header 1 baris -->
        <header class="w-full max-w-5xl mx-auto flex items-center justify-between gap-4">
            <Link v-if="!isPublicContext" :href="homeUrl" class="inline-flex items-center gap-2.5 font-extrabold text-sm tracking-tight text-slate-900 dark:text-slate-100">
                <img src="/img/icon-192.png" alt="Logo SAHABAT SDAM" class="w-7 h-7 rounded-lg object-contain" />
                <span>SAHABAT SDAM</span>
            </Link>
            <span v-else class="text-xs font-bold text-slate-500 dark:text-slate-400">Verifikasi Dokumen Publik</span>

            <button
                type="button"
                @click="toggleDark"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 min-h-[38px] rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                :aria-label="isDark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'"
            >
                <span>{{ isDark ? 'Mode Terang' : 'Mode Gelap' }}</span>
            </button>
        </header>

        <!-- Konten 1 layar terpusat (referensi 3-digit tiles + copy block) -->
        <main class="w-full max-w-5xl mx-auto my-auto py-6 grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-center">
            <!-- Kiri: 3 Digit Blok + Sticker -->
            <div class="lg:col-span-6 flex flex-col items-center lg:items-start">
                <div class="relative inline-block">
                    <span
                        class="inline-block px-3 py-1 rounded-lg border text-xs font-extrabold tracking-wide mb-3 -rotate-2 shadow-2xs"
                        :class="stickerToneClass"
                    >
                        {{ stickerText }}
                    </span>
                    <div class="flex items-center gap-2.5 sm:gap-3.5" :aria-label="`Kode kesalahan ${status}`">
                        <span
                            v-for="(digit, idx) in statusDigits"
                            :key="idx"
                            class="w-20 h-24 sm:w-28 sm:h-32 rounded-2xl border-2 border-slate-900/10 dark:border-slate-700 bg-white dark:bg-slate-900 flex items-center justify-center text-5xl sm:text-7xl font-black tracking-tighter tabular-nums shadow-sm select-none"
                            :class="idx === 1 ? 'text-primary-600 dark:text-primary-400 -translate-y-1.5' : 'text-slate-900 dark:text-slate-100'"
                        >
                            {{ digit }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Kanan: Penjelasan & Aksi -->
            <section class="lg:col-span-6 text-center lg:text-left">
                <p class="text-xs font-bold text-slate-500 dark:text-slate-400 tabular-nums">
                    Laporan Status {{ status }}
                </p>

                <h1
                    ref="headingRef"
                    tabindex="-1"
                    class="mt-2 text-2xl sm:text-4xl font-black text-slate-900 dark:text-slate-100 tracking-tight leading-tight focus:outline-none"
                >
                    {{ title }}
                </h1>

                <p class="mt-3 text-sm sm:text-base text-slate-600 dark:text-slate-400 leading-relaxed max-w-lg mx-auto lg:mx-0">
                    {{ message }}
                </p>

                <div v-if="status === 403 && formattedRole" class="mt-3">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-slate-200/70 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                        Peran aktif: {{ formattedRole }}
                    </span>
                </div>

                <div v-if="status === 429 && countdown > 0" class="mt-3 text-xs font-bold text-amber-700 dark:text-amber-400 tabular-nums">
                    Dapat dicoba lagi dalam {{ countdown }} detik
                </div>

                <div class="mt-6 flex flex-col sm:flex-row items-stretch sm:items-center justify-center lg:justify-start gap-2.5">
                    <button
                        v-if="isRetryable"
                        type="button"
                        :disabled="status === 429 && countdown > 0"
                        @click="reloadPage"
                        class="inline-flex items-center justify-center px-5 py-2.5 min-h-[44px] rounded-xl bg-primary-600 hover:bg-primary-700 disabled:opacity-50 text-white text-sm font-bold transition-colors cursor-pointer"
                    >
                        {{ status === 429 && countdown > 0 ? `Tunggu (${countdown}d)` : 'Coba Lagi' }}
                    </button>

                    <Link
                        v-else
                        :href="homeUrl"
                        class="inline-flex items-center justify-center px-5 py-2.5 min-h-[44px] rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-bold transition-colors"
                    >
                        {{ homeLabel }}
                    </Link>

                    <button
                        v-if="!isKioskContext"
                        type="button"
                        @click="goBack"
                        class="inline-flex items-center justify-center px-5 py-2.5 min-h-[44px] rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 text-sm font-semibold transition-colors cursor-pointer"
                    >
                        ← Kembali
                    </button>

                    <Link
                        v-if="status === 403 && canSwitchAccount"
                        :href="homeUrl"
                        class="inline-flex items-center justify-center px-4 py-2.5 min-h-[44px] rounded-xl text-primary-600 dark:text-primary-400 hover:underline text-xs font-bold"
                    >
                        Ganti Akun
                    </Link>
                </div>

                <div v-if="reference" class="mt-5 pt-4 border-t border-slate-200/80 dark:border-slate-800 inline-flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                    <span>ID Referensi: <code class="font-mono font-semibold text-slate-700 dark:text-slate-300">{{ reference }}</code></span>
                    <button
                        type="button"
                        @click="copyReference"
                        class="px-2 py-1 rounded-lg bg-slate-200/70 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 transition-colors cursor-pointer"
                    >
                        {{ copied ? 'Tersalin' : 'Salin' }}
                    </button>
                </div>

                <p v-if="isKioskContext" class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                    Kiosk akan kembali ke layar tap kartu otomatis...
                </p>
            </section>
        </main>

        <!-- Footer 1 baris -->
        <footer class="w-full max-w-5xl mx-auto flex items-center justify-between text-xs text-slate-400 dark:text-slate-600">
            <span>SAHABAT SDAM</span>
            <span>SD Al Mujahidin Wonosari</span>
        </footer>
    </div>
</template>
