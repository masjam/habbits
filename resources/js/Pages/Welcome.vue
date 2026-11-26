<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
    canLogin: {
        type: Boolean,
    },
    settings: {
        type: Object,
        default: () => ({})
    },
    liveStats: {
        type: Object,
        default: () => ({ logs: 0, skor: 0 })
    }
})

// ─── Pop-up pengumuman ──────────────────────────────────────────────────────
const showPopup = ref(false)

const closePopup = () => {
    showPopup.value = false
}

const onKeydown = (e) => {
    if (e.key === 'Escape' && showPopup.value) closePopup()
}

onMounted(() => {
    const active = props.settings?.popup_active
    if ((active === '1' || active === true || active === 'true') && props.settings?.popup_text) {
        showPopup.value = true
    }
    window.addEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown)
})

// ─── Video YouTube (opsional, diatur admin) ─────────────────────────────────
const youtubeEmbedUrl = computed(() => {
    const url = props.settings?.youtube_link
    if (!url) return null

    // Mendukung /live/, /shorts/, watch?v=, youtu.be, atau ID 11 karakter saja
    let videoId = null
    const regExp = /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|live|shorts)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i
    const match = url.match(regExp)

    if (match && match[1].length === 11) {
        videoId = match[1]
    } else if (url.length === 11) {
        videoId = url
    }

    return videoId ? `https://www.youtube.com/embed/${videoId}` : null
})

// ─── Data tampilan ──────────────────────────────────────────────────────────
const today = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
}).format(new Date())

const logsThisMonth = computed(() => {
    const n = Number(props.liveStats?.logs ?? 0)
    return n > 0 ? new Intl.NumberFormat('id-ID').format(n) : null
})

// Miniatur form "Input Habit Harian" (nama dan kolom sama dengan form aslinya)
const attendanceModes = [
    { key: 'JM', title: 'Jamaah masjid' },
    { key: 'JR', title: 'Jamaah di rumah' },
    { key: 'M', title: 'Munfarid' },
]

// `order` = urutan centang ditulis saat halaman dibuka. Nilainya statis agar
// render ulang (mis. saat pop-up ditutup) tidak mengubah delay animasi.
const prayers = [
    { name: 'Subuh', pick: 'JM', order: 0 },
    { name: 'Dzuhur', pick: 'JM', order: 1 },
    { name: 'Ashar', pick: 'JR', order: 2 },
    { name: 'Maghrib', pick: 'JM', order: 3 },
    { name: 'Isya', pick: null, order: null },
]

const otherHabits = [
    { name: 'Membaca Al Quran', done: true, order: 4 },
    { name: 'Sholat Dhuha min. 4 rakaat', done: true, order: 5 },
    { name: 'Sedekah harian', done: false, order: null },
]

// Fitur disusun mengikuti hari kerja, karena urutan ini memang urutan pemakaiannya
const dayFlow = [
    {
        when: 'Saat tiba',
        title: 'Presensi',
        text: 'Presensi lewat HP dengan foto dan lokasi, atau tempelkan kartu RFID di kiosk sekolah. Terlambat atau tidak masuk? Isi keterangan atau ajukan izin beserta lampirannya dari halaman yang sama.',
    },
    {
        when: 'Saat mengajar',
        title: 'Jurnal dan administrasi guru',
        text: 'Catat jurnal harian lengkap dengan foto kegiatan, lalu unggah perangkat pembelajaran. Pemeriksaan dan verifikasinya dilakukan langsung di aplikasi.',
    },
    {
        when: 'Sepanjang hari',
        title: 'Habit dan ibadah',
        text: "Isi lembar habit harian: sholat wajib, rawatib, Al-Qur'an, dhuha, sedekah, dan lainnya. Bacaan Al-Qur'an dan hadis, dzikir pagi dan petang, serta arah kiblat ada di aplikasi yang sama.",
    },
    {
        when: 'Akhir bulan',
        title: 'Capaian dan voucher',
        text: 'Lihat skor bulanan, streak, dan rapor kinerja. Pegawai yang mencapai target habit mendapat voucher untuk ditukar di koperasi sekolah.',
    },
]

const otherRoles = [
    {
        role: 'Tata usaha',
        text: 'Mencatat surat masuk dan surat keluar, membuat disposisi, dan memeriksa keaslian surat keluar lewat tautan verifikasi.',
    },
    {
        role: 'Kepala sekolah dan kurikulum',
        text: 'Memantau jurnal harian dan administrasi guru.',
    },
    {
        role: 'Petugas koperasi',
        text: 'Menerima voucher di kasir, mengelola katalog barang, dan mencatat stok opname.',
    },
    {
        role: 'Admin',
        text: 'Mengelola pengguna, jam kerja dan piket, master habit, rekap presensi, serta verifikasi arsip pegawai.',
    },
]

const startSteps = [
    {
        title: 'Minta akun ke admin',
        text: 'Akun dibuat oleh admin sekolah. Belum ada pendaftaran mandiri.',
    },
    {
        title: 'Masuk dengan email Anda',
        text: 'Gunakan email dan kata sandi dari admin, lalu ganti kata sandinya di halaman Profil. Lupa kata sandi? Admin bisa meresetnya.',
    },
    {
        title: 'Isi presensi dan habit hari ini',
        text: 'Dashboard menandai tanggal yang terlewat supaya mudah dilengkapi.',
    },
]
</script>

<template>
    <Head title="SAHABAT SDAM">
        <meta
            name="description"
            content="SAHABAT SDAM: Sistem Absensi, Habit, Administrasi dan Buku Tugas untuk guru dan karyawan SD Al Mujahidin Wonosari."
        />
    </Head>

    <div class="min-h-screen flex flex-col bg-primary-50/60 text-slate-700 dark:bg-slate-950 dark:text-slate-300 font-sans antialiased">

        <!-- Teks berjalan (diatur admin) -->
        <div v-if="settings?.running_text" class="w-full bg-primary-700 text-white overflow-hidden py-2 relative z-50">
            <div class="flex whitespace-nowrap overflow-hidden motion-reduce:whitespace-normal">
                <span class="animate-marquee motion-reduce:animate-none inline-block px-4 text-sm font-medium w-full">
                    {{ settings.running_text }}
                </span>
            </div>
        </div>

        <!-- Navigasi -->
        <header class="w-full">
            <div class="max-w-6xl mx-auto px-5 sm:px-8 h-20 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3 min-w-0">
                    <img src="/logo.png" alt="" class="w-11 h-11 object-contain shrink-0" />
                    <div class="leading-tight min-w-0">
                        <p class="text-lg font-semibold tracking-tight text-primary-900">SAHABAT SDAM</p>
                        <p class="text-xs text-slate-600 dark:text-slate-400 truncate">SD Al Mujahidin Wonosari</p>
                    </div>
                </div>

                <div v-if="canLogin" class="shrink-0">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="landing-btn landing-btn-quiet"
                    >
                        Buka dashboard
                    </Link>
                    <Link v-else :href="route('login')" class="landing-btn landing-btn-quiet">
                        Masuk
                    </Link>
                </div>
            </div>
        </header>

        <main class="flex-1">
            <!-- ═══ Hero ═══ -->
            <section class="max-w-6xl mx-auto px-5 sm:px-8 pt-8 pb-16 lg:pt-14 lg:pb-24 overflow-x-clip">
                <div class="grid lg:grid-cols-12 gap-12 lg:gap-8 items-center">

                    <div class="lg:col-span-7">
                        <h1 class="text-[clamp(2.25rem,5vw,4rem)] leading-[1.04] tracking-[-0.03em] font-semibold text-primary-950 dark:text-white text-balance">
                            Satu catatan untuk presensi, ibadah, dan tugas harian.
                        </h1>
                        <p class="mt-6 max-w-[34rem] text-lg leading-relaxed text-slate-600 dark:text-slate-400">
                            SAHABAT SDAM adalah Sistem Absensi, Habit, Administrasi &amp; Buku Tugas untuk guru dan karyawan SD Al Mujahidin Wonosari.
                        </p>

                        <div v-if="canLogin" class="mt-9 flex flex-wrap items-center gap-x-6 gap-y-4">
                            <Link
                                v-if="$page.props.auth.user"
                                :href="route('dashboard')"
                                class="landing-btn landing-btn-solid"
                            >
                                Buka dashboard
                            </Link>
                            <Link v-else :href="route('login')" class="landing-btn landing-btn-solid">
                                Masuk ke akun
                            </Link>
                            <a href="#hari-kerja" class="landing-link">Lihat isi aplikasi</a>
                        </div>

                        <p v-if="!$page.props.auth.user" class="mt-5 max-w-[34rem] text-sm text-slate-600 dark:text-slate-400">
                            Belum punya akun atau lupa kata sandi? Hubungi admin sekolah.
                        </p>
                    </div>

                    <!-- Lembar habit: satu-satunya momen bergerak di halaman ini -->
                    <figure class="lg:col-span-5 w-full max-w-md mx-auto lg:max-w-none">
                        <div class="sheet" aria-hidden="true">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-6 pt-5 pb-3 border-b-2 border-primary-200">
                                <p class="font-semibold text-primary-950 dark:text-white">Input Habit Harian</p>
                                <p class="text-xs text-slate-600 dark:text-slate-400">{{ today }}</p>
                            </div>

                            <div class="sheet-body">
                                <div class="grid grid-cols-[1fr_repeat(3,2.25rem)] items-center gap-x-1 py-2 pr-4 text-xs font-medium text-slate-600 dark:text-slate-400 border-b border-primary-100 dark:border-slate-800">
                                    <span>Sholat wajib berjamaah</span>
                                    <span v-for="m in attendanceModes" :key="m.key" class="text-center">{{ m.key }}</span>
                                </div>

                                <div
                                    v-for="p in prayers"
                                    :key="p.name"
                                    class="grid grid-cols-[1fr_repeat(3,2.25rem)] items-center gap-x-1 py-2.5 pr-4 border-b border-primary-100 dark:border-slate-800"
                                >
                                    <div>
                                        <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ p.name }}</p>
                                        <p v-if="!p.pick" class="text-xs text-amber-700 dark:text-amber-400">belum diisi</p>
                                    </div>
                                    <span v-for="m in attendanceModes" :key="m.key" class="flex justify-center">
                                        <span
                                            v-if="p.pick === m.key"
                                            class="stamp"
                                            :style="{ '--i': p.order }"
                                        >
                                            <svg viewBox="0 0 20 20" class="h-5 w-5" fill="none">
                                                <circle cx="10" cy="10" r="9" class="fill-primary-600" />
                                                <path class="tick-path" d="M5.8 10.4l2.9 2.9 5.5-6.2" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </span>
                                        <span v-else class="h-5 w-5 rounded-full border border-slate-300 dark:border-slate-600"></span>
                                    </span>
                                </div>

                                <div
                                    v-for="h in otherHabits"
                                    :key="h.name"
                                    class="flex items-center justify-between gap-3 py-2.5 pr-4 border-b border-primary-100 dark:border-slate-800 last:border-b-0"
                                >
                                    <span class="text-sm text-slate-800 dark:text-slate-200">{{ h.name }}</span>
                                    <span v-if="h.done" class="stamp" :style="{ '--i': h.order }">
                                        <svg viewBox="0 0 20 20" class="h-5 w-5" fill="none">
                                            <rect x="1" y="1" width="18" height="18" rx="5" class="fill-primary-600" />
                                            <path class="tick-path" d="M5.8 10.4l2.9 2.9 5.5-6.2" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                    <span v-else class="h-5 w-5 rounded-md border border-slate-300 dark:border-slate-600"></span>
                                </div>
                            </div>
                        </div>

                        <figcaption class="mt-5 text-sm text-slate-600 dark:text-slate-400">
                            <p>JM: jamaah masjid. JR: jamaah di rumah. M: munfarid (sendirian).</p>
                            <p class="mt-1">
                                Contoh tampilan Input Habit Harian.
                                <span v-if="logsThisMonth">Bulan ini sudah terisi {{ logsThisMonth }} catatan habit.</span>
                            </p>
                        </figcaption>
                    </figure>
                </div>
            </section>

            <!-- ═══ Video pengenalan (hanya jika admin mengisi tautan YouTube) ═══ -->
            <section v-if="youtubeEmbedUrl" class="border-t border-primary-100 dark:border-slate-800">
                <div class="max-w-6xl mx-auto px-5 sm:px-8 py-16 lg:py-20">
                    <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight text-primary-950 dark:text-white">Lihat cara memakainya</h2>
                    <div class="mt-8 max-w-4xl rounded-xl overflow-hidden border border-primary-100 dark:border-slate-800 bg-white dark:bg-slate-900">
                        <div class="relative w-full" style="padding-top: 56.25%;">
                            <iframe
                                :src="youtubeEmbedUrl"
                                title="Video pengenalan SAHABAT SDAM"
                                loading="lazy"
                                frameborder="0"
                                allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                allowfullscreen
                                class="absolute inset-0 w-full h-full"
                            ></iframe>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══ Satu hari kerja ═══ -->
            <section id="hari-kerja" class="border-t border-primary-100 dark:border-slate-800 scroll-mt-4 bg-white/70 dark:bg-slate-900/40">
                <div class="max-w-6xl mx-auto px-5 sm:px-8 py-16 lg:py-24 grid lg:grid-cols-12 gap-10 lg:gap-12">
                    <div class="lg:col-span-4">
                        <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight text-primary-950 dark:text-white text-balance">
                            Satu hari kerja di SAHABAT SDAM
                        </h2>
                        <p class="mt-4 max-w-sm text-slate-600 dark:text-slate-400">
                            Urutannya mengikuti hari di sekolah, dari presensi pagi sampai capaian akhir bulan.
                        </p>
                    </div>

                    <ol class="lg:col-span-8">
                        <li
                            v-for="step in dayFlow"
                            :key="step.title"
                            class="group/li grid sm:grid-cols-[9.5rem_1fr] sm:gap-x-8"
                        >
                            <!-- Penanda waktu: di kiri pada layar lebar, di atas judul pada layar kecil -->
                            <p class="hidden sm:block sm:text-right pt-0.5 text-sm font-medium text-primary-700">
                                {{ step.when }}
                            </p>
                            <div class="relative border-l border-primary-200 pl-7 pb-10 group-last/li:pb-0 ml-[5px] sm:ml-0">
                                <span class="absolute -left-[5px] top-[7px] h-[9px] w-[9px] rounded-full bg-primary-600"></span>
                                <p class="sm:hidden text-sm font-medium text-primary-700">{{ step.when }}</p>
                                <h3 class="font-semibold text-lg text-primary-950 dark:text-white">{{ step.title }}</h3>
                                <p class="mt-1.5 max-w-xl leading-relaxed text-slate-600 dark:text-slate-400">{{ step.text }}</p>
                            </div>
                        </li>
                    </ol>
                </div>
            </section>

            <!-- ═══ Peran lain ═══ -->
            <section class="border-t border-primary-100 dark:border-slate-800">
                <div class="max-w-6xl mx-auto px-5 sm:px-8 py-16 lg:py-24 grid lg:grid-cols-12 gap-10 lg:gap-12">
                    <div class="lg:col-span-4">
                        <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight text-primary-950 dark:text-white text-balance">
                            Peran lain di sekolah
                        </h2>
                        <p class="mt-4 max-w-sm text-slate-600 dark:text-slate-400">
                            Menu yang tampil menyesuaikan peran Anda setelah masuk.
                        </p>
                    </div>

                    <dl class="lg:col-span-8 border-t border-primary-200 dark:border-slate-800">
                        <div
                            v-for="r in otherRoles"
                            :key="r.role"
                            class="grid sm:grid-cols-[14rem_1fr] gap-x-8 gap-y-1 py-5 border-b border-primary-200 dark:border-slate-800"
                        >
                            <dt class="font-semibold text-primary-950 dark:text-white">{{ r.role }}</dt>
                            <dd class="max-w-xl leading-relaxed text-slate-600 dark:text-slate-400">{{ r.text }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <!-- ═══ Cara mulai ═══ -->
            <section class="border-t border-primary-100 dark:border-slate-800 bg-white/70 dark:bg-slate-900/40">
                <div class="max-w-6xl mx-auto px-5 sm:px-8 py-16 lg:py-24">
                    <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight text-primary-950 dark:text-white">Cara mulai</h2>

                    <ol class="mt-10 grid md:grid-cols-3 gap-x-10 gap-y-8">
                        <li v-for="(s, i) in startSteps" :key="s.title" class="border-t-2 border-primary-600 pt-4">
                            <p class="text-sm font-medium text-primary-700">Langkah {{ i + 1 }}</p>
                            <h3 class="mt-1 font-semibold text-lg text-primary-950 dark:text-white">{{ s.title }}</h3>
                            <p class="mt-2 leading-relaxed text-slate-600 dark:text-slate-400">{{ s.text }}</p>
                        </li>
                    </ol>

                    <div v-if="canLogin" class="mt-12">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="route('dashboard')"
                            class="landing-btn landing-btn-solid"
                        >
                            Buka dashboard
                        </Link>
                        <Link v-else :href="route('login')" class="landing-btn landing-btn-solid">
                            Masuk ke akun
                        </Link>
                    </div>
                </div>
            </section>
        </main>

        <footer class="sticky bottom-0 z-40 border-t border-primary-100 dark:border-slate-800 bg-white/90 dark:bg-slate-950/90 backdrop-blur-md">
            <div class="max-w-6xl mx-auto px-5 sm:px-8 py-3.5 sm:py-4 flex flex-wrap items-center justify-between gap-x-6 gap-y-1 text-xs sm:text-sm text-slate-600 dark:text-slate-400">
                <p>&copy; {{ new Date().getFullYear() }} SAHABAT SDAM. All rights reserved.</p>
                <p>@blue_core21</p>
            </div>
        </footer>

        <!-- Pop-up pengumuman -->
        <div v-if="showPopup" class="relative z-[100]" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closePopup"></div>

            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                    <div class="relative overflow-hidden rounded-2xl bg-white dark:bg-slate-900 text-left shadow-2xl sm:my-8 sm:w-full sm:max-w-lg border border-slate-100 dark:border-slate-800">
                        <div class="absolute top-0 right-0 pt-4 pr-4">
                            <button
                                @click="closePopup"
                                type="button"
                                class="rounded-lg bg-white dark:bg-slate-900 text-slate-400 hover:text-slate-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 transition-colors"
                            >
                                <span class="sr-only">Tutup pengumuman</span>
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="px-6 pb-6 pt-8">
                            <h3 class="text-xl font-semibold leading-6 text-slate-900 dark:text-white pr-8" id="modal-title">Pengumuman</h3>
                            <div class="mt-4 text-slate-600 dark:text-slate-300 whitespace-pre-wrap text-base leading-relaxed" v-html="settings?.popup_text"></div>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-800/60 px-6 py-4 flex justify-end">
                            <button @click="closePopup" type="button" class="landing-btn landing-btn-solid w-full sm:w-auto">
                                Mengerti
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ── Tombol dan tautan ─────────────────────────────────────────────────── */
.landing-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 2.75rem;
    padding: 0 1.5rem;
    border-radius: 0.75rem;
    font-size: 0.9375rem;
    font-weight: 600;
    transition: background-color 0.15s ease, color 0.15s ease;
}
.landing-btn:focus-visible,
.landing-link:focus-visible {
    outline: 2px solid var(--primary-700);
    outline-offset: 3px;
}
/* Skala primary terbalik di mode gelap: bg 700 jadi terang, jadi teks memakai 50 (gelap di mode gelap) */
.landing-btn-solid {
    background: var(--primary-700);
    color: var(--primary-50);
}
.landing-btn-solid:hover {
    background: var(--primary-800);
}
.landing-btn-quiet {
    color: var(--primary-800);
    background: var(--primary-100);
}
.landing-btn-quiet:hover {
    background: var(--primary-200);
}
.landing-link {
    font-weight: 600;
    font-size: 0.9375rem;
    color: var(--primary-800);
    text-decoration: underline;
    text-decoration-thickness: 1px;
    text-underline-offset: 4px;
}
.landing-link:hover {
    text-decoration-thickness: 2px;
}

/* ── Lembar habit (kertas bergaris dengan garis margin merah) ──────────── */
.sheet {
    background: #fff;
    border: 1px solid var(--primary-200);
    border-radius: 0.5rem;
    box-shadow: 0 1px 0 var(--primary-100), 0 18px 40px -22px rgba(0, 30, 26, 0.5);
    transform: rotate(-1.2deg);
}
:global(.dark) .sheet {
    background: #0f172a;
    box-shadow: 0 18px 40px -22px rgba(0, 0, 0, 0.8);
}
.sheet-body {
    position: relative;
    padding-left: 3rem;
}
.sheet-body::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 2.25rem;
    width: 1px;
    background: #fda4af;
    opacity: 0.7;
}

/* ── Centang yang tertulis satu per satu saat halaman dibuka ───────────── */
.stamp {
    display: inline-flex;
    opacity: 0;
    transform: scale(0.6);
    animation: stamp-in 0.28s cubic-bezier(0.2, 0.9, 0.3, 1.2) forwards;
    animation-delay: calc(0.35s + var(--i, 0) * 0.16s);
}
.tick-path {
    stroke-dasharray: 20;
    stroke-dashoffset: 20;
    animation: tick-draw 0.32s ease-out forwards;
    animation-delay: calc(0.45s + var(--i, 0) * 0.16s);
}
@keyframes stamp-in {
    to { opacity: 1; transform: scale(1); }
}
@keyframes tick-draw {
    to { stroke-dashoffset: 0; }
}

@media (prefers-reduced-motion: reduce) {
    .stamp {
        opacity: 1;
        transform: none;
        animation: none;
    }
    .tick-path {
        stroke-dashoffset: 0;
        animation: none;
    }
}
</style>