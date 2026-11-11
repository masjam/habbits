<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const hptUrl = 'https://hpt.muhammadiyah.or.id/';
const tarjihUrl = 'https://tarjih.or.id/';

const openHpt = () => {
    window.open(hptUrl, '_blank', 'noopener,noreferrer');
};

const topics = [
    {
        title: 'Kitab Thaharah',
        desc: 'Tuntunan bersuci, wudhu, tayamum, mandi wajib, dan adab thaharah sesuai putusan Tarjih.',
        icon: '💧',
        query: 'thaharah'
    },
    {
        title: 'Kitab Shalat',
        desc: 'Tuntunan kaifiyah shalat fardhu, shalat sunnah, sujud sahwi, dan shalat berjamaah.',
        icon: '🕌',
        query: 'shalat'
    },
    {
        title: 'Kitab Puasa & Zakat',
        desc: 'Panduan ibadah puasa Ramadhan, fidyah, zakat fitrah, zakat mal, dan infaq.',
        icon: '🌙',
        query: 'puasa'
    },
    {
        title: 'Kitab Jenazah',
        desc: 'Tata cara perawatan jenazah, memandikan, mengafani, menshalatkan, hingga pemakaman.',
        icon: '🤲',
        query: 'jenazah'
    },
    {
        title: 'Fatwa & Tanya Jawab Tarjih',
        desc: 'Kumpulan fatwa kontemporer, hisab awal bulan hijriyah, dan persoalan muamalah.',
        icon: '📜',
        query: 'fatwa'
    },
    {
        title: 'Tuntunan Ramadhan',
        desc: 'Panduan qiyamul lail, iktikaf, tarawih 11 rakaat, dan takbiran idul fitri.',
        icon: '⭐',
        query: 'ramadhan'
    }
];

const openTopic = (query) => {
    window.open(`${hptUrl}?q=${encodeURIComponent(query)}`, '_blank', 'noopener,noreferrer');
};
</script>

<template>
    <Head title="Himpunan Putusan Tarjih (HPT)" />

    <AuthenticatedLayout>
        <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto space-y-6">
            
            <!-- Hero Banner Card -->
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-800 via-teal-800 to-emerald-950 text-white shadow-xl p-6 sm:p-10 border border-emerald-700/40">
                <!-- Decorative Pattern / Blurs -->
                <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-emerald-400/20 blur-3xl pointer-events-none"></div>
                <div class="absolute -bottom-24 -left-24 w-80 h-80 rounded-full bg-teal-400/20 blur-3xl pointer-events-none"></div>

                <div class="relative z-10 max-w-3xl space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/20 border border-emerald-400/30 text-emerald-200 text-xs font-semibold tracking-wide uppercase">
                        <span>✨</span> Portal Resmi Majelis Tarjih PP Muhammadiyah
                    </div>

                    <h1 class="text-2xl sm:text-4xl font-extrabold tracking-tight text-white leading-tight">
                        Himpunan Putusan Tarjih (HPT) Online
                    </h1>

                    <p class="text-sm sm:text-base text-emerald-100/90 leading-relaxed font-normal">
                        Akses referensi digital resmi putusan hukum syariat Islam, fatwa, dan tuntunan ibadah praktis warga Muhammadiyah langsung dari sistem resmi Majelis Tarjih dan Tajdid Pimpinan Pusat Muhammadiyah.
                    </p>

                    <!-- CTA Buttons -->
                    <div class="pt-3 flex flex-wrap items-center gap-3">
                        <button
                            type="button"
                            @click="openHpt"
                            class="inline-flex items-center gap-2 px-6 py-3.5 rounded-2xl bg-white text-emerald-900 font-bold text-sm shadow-lg shadow-emerald-950/20 hover:bg-emerald-50 active:scale-95 transition-all cursor-pointer"
                        >
                            <span>Buka HPT Online di Tab Baru</span>
                            <svg class="w-4 h-4 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                        </button>

                        <a
                            :href="tarjihUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 px-5 py-3.5 rounded-2xl bg-emerald-700/50 hover:bg-emerald-700/80 border border-emerald-500/40 text-white font-semibold text-sm transition-all"
                        >
                            <span>Portal Tarjih.or.id</span>
                            <svg class="w-4 h-4 opacity-75" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Catatan Informasi Keamanan (Penjelasan kenapa dibuka di Tab Baru) -->
            <!-- <div class="flex items-start gap-3.5 p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-800/60 text-amber-900 dark:text-amber-200 text-xs sm:text-sm leading-relaxed">
                <span class="text-lg shrink-0 mt-0.5">ℹ️</span>
                <div>
                    <span class="font-bold">Mengapa dibuka di tab baru?</span>
                    Situs resmi HPT Muhammadiyah menerapkan protokol keamanan ketat (<code class="bg-amber-100 dark:bg-amber-900/60 px-1.5 py-0.5 rounded text-xs font-mono font-bold text-amber-800 dark:text-amber-300">X-Frame-Options: DENY</code>) untuk mencegah pembajakan klik (anti-clickjacking). Demi kelancaran dan kenyamanan membaca tanpa batasan browser, portal dirancang untuk diakses langsung pada jendela tersendiri.
                </div>
            </div> -->

            <!-- Topik Utama HPT -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-base sm:text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <span>📚</span> Topik Rujukan Populer
                    </h2>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Pilih topik untuk pencarian langsung</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="topic in topics"
                        :key="topic.title"
                        @click="openTopic(topic.query)"
                        class="group p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700 shadow-xs hover:shadow-md hover:border-emerald-500/50 dark:hover:border-emerald-500/50 transition-all cursor-pointer flex flex-col justify-between"
                    >
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-2xl p-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700/60 inline-block">
                                    {{ topic.icon }}
                                </span>
                                <span class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold opacity-0 group-hover:opacity-100 group-hover:translate-x-0.5 transition-all flex items-center gap-0.5">
                                    Buka ↗
                                </span>
                            </div>
                            <h3 class="font-bold text-slate-800 dark:text-slate-100 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors text-base">
                                {{ topic.title }}
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                {{ topic.desc }}
                            </p>
                        </div>

                        <div class="pt-4 mt-2 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-[11px] text-slate-400">
                            <span>Majelis Tarjih</span>
                            <span class="text-emerald-600 dark:text-emerald-400 font-medium group-hover:underline">Telusuri putusan</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fitur Tambahan & Tautan Eksternal -->
            <div class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/80 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 flex items-center justify-center text-emerald-600 text-xl font-bold shrink-0">
                        📖
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">
                            Butuh rujukan fatwa dan hisab falakiyah?
                        </h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Kunjungi portal berita dan kajian resmi Majelis Tarjih dan Tajdid PP Muhammadiyah.
                        </p>
                    </div>
                </div>

                <a
                    href="https://hpt.muhammadiyah.or.id/explore?koleksi=tja"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="px-4 py-2 text-xs font-bold rounded-xl bg-slate-800 dark:bg-slate-700 text-white hover:bg-slate-900 transition shrink-0"
                >
                    Tanya Jawab Agama ↗
                </a>
            </div>

        </div>
    </AuthenticatedLayout>
</template>