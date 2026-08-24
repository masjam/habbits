<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

// ─── Tabs ─────────────────────────────────────────────────────────────────────
const activeTab = ref('quran') // 'quran' | 'hadis'

// ══════════════════════════════════════════════════════════════════════════════
// TAB 1: AL-QUR'AN
// ══════════════════════════════════════════════════════════════════════════════
const QURAN_API = 'https://equran.id/api/v2'

// State
const surahList    = ref([])
const selectedSurah = ref(null)
const surahDetail  = ref(null)
const surahSearch  = ref('')
const loadingSurah = ref(false)
const loadingDetail = ref(false)
const quranError   = ref(null)

// Fetch daftar surah
const fetchSurahList = async () => {
    loadingSurah.value = true
    quranError.value = null
    try {
        const res = await fetch(`${QURAN_API}/surat`)
        const json = await res.json()
        surahList.value = json.data || []
    } catch (e) {
        quranError.value = 'Gagal memuat daftar surah. Periksa koneksi internet.'
    } finally {
        loadingSurah.value = false
    }
}

// Fetch detail surah + ayat
const fetchSurahDetail = async (nomor) => {
    loadingDetail.value = true
    quranError.value = null
    surahDetail.value = null
    try {
        const res = await fetch(`${QURAN_API}/surat/${nomor}`)
        const json = await res.json()
        surahDetail.value = json.data || null
        selectedSurah.value = nomor
    } catch (e) {
        quranError.value = 'Gagal memuat surah. Periksa koneksi internet.'
    } finally {
        loadingDetail.value = false
    }
}

const goBack = () => {
    surahDetail.value = null
    selectedSurah.value = null
}

const goToSurah = (nomor) => {
    if (nomor) fetchSurahDetail(nomor)
}

// Filter surah
const filteredSurah = computed(() => {
    if (!surahSearch.value) return surahList.value
    const q = surahSearch.value.toLowerCase()
    return surahList.value.filter(s =>
        s.namaLatin.toLowerCase().includes(q) ||
        s.arti.toLowerCase().includes(q) ||
        String(s.nomor).includes(q)
    )
})

const isMobile = ref(false)
const checkMobile = () => {
    isMobile.value = typeof window !== 'undefined' ? window.innerWidth < 768 : false
}

const quranCurrentPage = ref(1)
const quranItemsPerPage = computed(() => isMobile.value ? 10 : 30)

watch(surahSearch, () => {
    quranCurrentPage.value = 1
})

const quranTotalPages = computed(() => {
    return Math.ceil(filteredSurah.value.length / quranItemsPerPage.value)
})

const quranColumns = computed(() => {
    const start = (quranCurrentPage.value - 1) * quranItemsPerPage.value
    const end = start + quranItemsPerPage.value
    const paginated = filteredSurah.value.slice(start, end)
    
    const cols = []
    const rowsPerCol = 10
    for (let i = 0; i < paginated.length; i += rowsPerCol) {
        cols.push(paginated.slice(i, i + rowsPerCol))
    }
    return cols
})

// ══════════════════════════════════════════════════════════════════════════════
// TAB 2: HADIS
// ══════════════════════════════════════════════════════════════════════════════
const HADIS_API = 'https://api.myquran.com/v2/hadits'

const perawiList = [
    { slug: 'bukhari',  nama: 'Bukhari',   total: 6638 },
    { slug: 'muslim',   nama: 'Muslim',    total: 3033 },
    { slug: 'tirmidzi', nama: 'Tirmidzi',  total: 3956 },
    { slug: 'ibnumajah', nama: 'Ibnu Majah', total: 4341 },
    { slug: 'nasai',    nama: "Nasa'i",    total: 5758 },
    { slug: 'ahmad',    nama: 'Ahmad',     total: 26363 },
    { slug: 'darimi',   nama: 'Darimi',    total: 3367 },
    { slug: 'malik',    nama: 'Malik',     total: 1587 },
    { slug: 'abudaud',  nama: 'Abu Daud',  total: 5274 },
]

const selectedPerawi = ref('bukhari')
const currentPerawi  = computed(() => perawiList.find(p => p.slug === selectedPerawi.value))
const hadisNomor     = ref(1)
const hadisData      = ref(null)
const loadingHadis   = ref(false)
const hadisError     = ref(null)
const inputNomor     = ref('')

const fetchHadis = async (nomor) => {
    loadingHadis.value = true
    hadisError.value = null
    hadisData.value = null
    try {
        const res = await fetch(`${HADIS_API}/${selectedPerawi.value}/${nomor}`)
        const json = await res.json()
        if (json.status && json.data) {
            hadisData.value = json.data
            hadisNomor.value = nomor
        } else {
            hadisError.value = 'Hadis tidak ditemukan pada nomor tersebut.'
        }
    } catch (e) {
        hadisError.value = 'Gagal memuat hadis. Periksa koneksi internet.'
    } finally {
        loadingHadis.value = false
    }
}

const prevHadis = () => {
    if (hadisNomor.value > 1) fetchHadis(hadisNomor.value - 1)
}

const nextHadis = () => {
    if (hadisNomor.value < (currentPerawi.value?.total ?? 9999)) fetchHadis(hadisNomor.value + 1)
}

const goToNomor = () => {
    const n = parseInt(inputNomor.value)
    if (n >= 1 && n <= (currentPerawi.value?.total ?? 9999)) {
        fetchHadis(n)
        inputNomor.value = ''
    }
}

const randomHadis = () => {
    const total = currentPerawi.value?.total ?? 100
    const n = Math.floor(Math.random() * Math.min(total, 500)) + 1
    fetchHadis(n)
}

watch(selectedPerawi, () => {
    hadisNomor.value = 1
    fetchHadis(1)
})

// ─── Init ─────────────────────────────────────────────────────────────────────
onMounted(() => {
    fetchSurahList()
    fetchHadis(1)
    checkMobile()
    if (typeof window !== 'undefined') {
        window.addEventListener('resize', checkMobile)
    }
})

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', checkMobile)
    }
})
</script>

<template>
    <Head title="Al-Qur'an & Hadis" />

    <AuthenticatedLayout>
        <div class="max-w-4xl mx-auto space-y-4">

            <!-- ── Header ──────────────────────────────────────────────── -->
            <div class="bg-gradient-to-br from-emerald-600 to-teal-700 rounded-2xl p-6 text-white shadow-lg relative overflow-hidden">
                <div class="absolute inset-0 opacity-10">
                    <div class="absolute top-2 right-4 text-6xl font-arabic leading-none select-none">بِسْمِ اللّٰهِ</div>
                </div>
                <div class="relative">
                    <div class="flex items-center gap-3 mb-1">
                        <svg class="w-6 h-6 text-emerald-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        <h1 class="text-lg font-bold">Al-Qur'an & Hadis</h1>
                    </div>
                    <p class="text-emerald-100 text-xs">Baca Al-Qur'an lengkap dan Hadis dari para perawi terpercaya</p>
                </div>
            </div>

            <!-- ── Tabs ────────────────────────────────────────────────── -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="flex border-b border-slate-100">
                    <button
                        @click="activeTab = 'quran'"
                        :class="['flex-1 px-4 py-3.5 text-sm font-semibold transition-colors flex items-center justify-center gap-2', activeTab === 'quran' ? 'text-emerald-700 border-b-2 border-emerald-500 bg-emerald-50/50' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50']"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        Al-Qur'an
                    </button>
                    <button
                        @click="activeTab = 'hadis'"
                        :class="['flex-1 px-4 py-3.5 text-sm font-semibold transition-colors flex items-center justify-center gap-2', activeTab === 'hadis' ? 'text-amber-700 border-b-2 border-amber-500 bg-amber-50/50' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50']"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Hadis
                    </button>
                </div>

                <!-- ══════════════════════════════════════════════════════ -->
                <!-- TAB AL-QUR'AN                                         -->
                <!-- ══════════════════════════════════════════════════════ -->
                <div v-if="activeTab === 'quran'" class="p-4 sm:p-6">

                    <!-- Error -->
                    <div v-if="quranError" class="bg-rose-50 text-rose-700 rounded-xl p-4 text-sm text-center border border-rose-100 mb-4">
                        {{ quranError }}
                    </div>

                    <!-- DETAIL SURAH -->
                    <div v-else-if="surahDetail">
                        <!-- Navigasi atas -->
                        <div class="flex items-center justify-between mb-5">
                            <button @click="goBack" class="flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-emerald-700 transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                </svg>
                                Kembali
                            </button>
                            <div class="flex gap-2">
                                <button v-if="surahDetail.suratSebelumnya" @click="goToSurah(surahDetail.suratSebelumnya.nomor)" class="px-2.5 py-1.5 bg-emerald-50 text-emerald-700 rounded-lg text-xs font-bold hover:bg-emerald-100 transition-colors flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                    Prev
                                </button>
                                <button v-if="surahDetail.suratSelanjutnya" @click="goToSurah(surahDetail.suratSelanjutnya.nomor)" class="px-2.5 py-1.5 bg-emerald-50 text-emerald-700 rounded-lg text-xs font-bold hover:bg-emerald-100 transition-colors flex items-center gap-1">
                                    Next
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Header surah -->
                        <div class="text-center mb-6 pb-5 border-b border-slate-100">
                            <div class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-emerald-100 text-emerald-700 font-bold text-sm mb-2">{{ surahDetail.nomor }}</div>
                            <div class="text-3xl font-arabic text-slate-800 mb-1">{{ surahDetail.nama }}</div>
                            <div class="text-base font-bold text-slate-700">{{ surahDetail.namaLatin }}</div>
                            <div class="text-sm text-slate-500 mt-0.5">{{ surahDetail.arti }} · {{ surahDetail.jumlahAyat }} Ayat · {{ surahDetail.tempatTurun }}</div>
                        </div>

                        <!-- Basmalah (kecuali At-Taubah surah 9) -->
                        <div v-if="surahDetail.nomor !== 9 && surahDetail.nomor !== 1" class="text-center text-2xl font-arabic text-slate-700 mb-6 py-2">
                            بِسْمِ اللّٰهِ الرَّحْمٰنِ الرَّحِيْمِ
                        </div>

                        <!-- Ayat-ayat -->
                        <div class="space-y-5">
                            <div v-for="ayat in surahDetail.ayat" :key="ayat.nomorAyat" class="relative group">
                                <div class="bg-slate-50/60 rounded-xl p-4 border border-slate-100 hover:border-emerald-200 hover:bg-emerald-50/20 transition-all">
                                    <!-- Nomor ayat -->
                                    <div class="flex items-start justify-between gap-3 mb-3">
                                        <div class="w-7 h-7 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-bold flex-shrink-0 mt-1">
                                            {{ ayat.nomorAyat }}
                                        </div>
                                        <!-- Teks Arab -->
                                        <div class="flex-1 text-right text-xl sm:text-2xl font-arabic leading-relaxed text-slate-800 pt-1" dir="rtl">
                                            {{ ayat.teksArab }}
                                        </div>
                                    </div>
                                    <!-- Transliterasi -->
                                    <div class="text-xs text-emerald-700 italic mb-1.5 pl-10">{{ ayat.teksLatin }}</div>
                                    <!-- Terjemahan -->
                                    <div class="text-sm text-slate-700 leading-relaxed pl-10">{{ ayat.teksIndonesia }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Navigasi bawah -->
                        <div class="flex gap-3 mt-8 pt-5 border-t border-slate-100">
                            <button v-if="surahDetail.suratSebelumnya" @click="goToSurah(surahDetail.suratSebelumnya.nomor)" class="flex-1 py-2.5 bg-slate-50 text-slate-700 rounded-xl text-sm font-bold hover:bg-slate-100 transition-colors flex items-center justify-center gap-1.5 border border-slate-200">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                {{ surahDetail.suratSebelumnya.namaLatin }}
                            </button>
                            <button v-if="surahDetail.suratSelanjutnya" @click="goToSurah(surahDetail.suratSelanjutnya.nomor)" class="flex-1 py-2.5 bg-emerald-600 text-white rounded-xl text-sm font-bold hover:bg-emerald-700 transition-colors flex items-center justify-center gap-1.5">
                                {{ surahDetail.suratSelanjutnya.namaLatin }}
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- LOADING DETAIL -->
                    <div v-else-if="loadingDetail" class="flex flex-col items-center justify-center py-16 text-slate-400">
                        <svg class="w-8 h-8 animate-spin mb-3 text-emerald-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span class="text-sm">Memuat surah...</span>
                    </div>

                    <!-- DAFTAR SURAH -->
                    <div v-else>
                        <!-- Search -->
                        <div class="relative mb-4">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input v-model="surahSearch" type="text" placeholder="Cari nama surah atau arti..." class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-slate-50" />
                        </div>

                        <!-- Loading daftar -->
                        <div v-if="loadingSurah" class="flex flex-col items-center justify-center py-16 text-slate-400">
                            <svg class="w-8 h-8 animate-spin mb-3 text-emerald-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <span class="text-sm">Memuat daftar surah...</span>
                        </div>

                        <!-- Grid Surah split into columns of 10 rows -->
                        <div v-else class="flex flex-col md:flex-row gap-4">
                            <div v-for="(col, colIdx) in quranColumns" :key="colIdx" class="flex flex-col gap-2.5 flex-1">
                                <button
                                    v-for="surah in col"
                                    :key="surah.nomor"
                                    @click="fetchSurahDetail(surah.nomor)"
                                    class="flex items-center gap-3 p-3 rounded-xl border border-slate-100 hover:border-emerald-300 hover:bg-emerald-50/40 transition-all text-left group"
                                >
                                    <!-- Nomor -->
                                    <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-xs flex items-center justify-center flex-shrink-0 group-hover:bg-emerald-100 transition-colors">
                                        {{ surah.nomor }}
                                    </div>
                                    <!-- Info -->
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-bold text-slate-800 leading-none">{{ surah.namaLatin }}</div>
                                        <div class="text-[11px] text-slate-500 mt-0.5">{{ surah.arti }} · {{ surah.jumlahAyat }} ayat</div>
                                    </div>
                                    <!-- Arab -->
                                    <div class="text-lg font-arabic text-emerald-700 flex-shrink-0">{{ surah.nama }}</div>
                                </button>
                            </div>
                        </div>

                        <!-- No results -->
                        <div v-if="filteredSurah.length === 0" class="text-center py-10 text-slate-400 text-sm">
                            Surah "{{ surahSearch }}" tidak ditemukan.
                        </div>

                        <!-- Pagination for Quran -->
                        <div v-if="filteredSurah.length > quranItemsPerPage" class="flex items-center justify-center gap-3 mt-6 pt-4 border-t border-slate-100">
                            <button 
                                @click="quranCurrentPage--" 
                                :disabled="quranCurrentPage === 1"
                                class="p-2 border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:hover:bg-transparent flex items-center justify-center"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            
                            <span class="text-xs font-bold text-slate-600">
                                Halaman {{ quranCurrentPage }} dari {{ quranTotalPages }}
                            </span>
                            
                            <button 
                                @click="quranCurrentPage++" 
                                :disabled="quranCurrentPage === quranTotalPages"
                                class="p-2 border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:hover:bg-transparent flex items-center justify-center"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════ -->
                <!-- TAB HADIS                                              -->
                <!-- ══════════════════════════════════════════════════════ -->
                <div v-else-if="activeTab === 'hadis'" class="p-4 sm:p-6">

                    <!-- Pilih Perawi -->
                    <div class="mb-5">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Pilih Perawi</label>
                        <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                            <button
                                v-for="p in perawiList"
                                :key="p.slug"
                                @click="selectedPerawi = p.slug"
                                :class="['px-2 py-2 rounded-xl text-xs font-semibold transition-all border', selectedPerawi === p.slug ? 'bg-amber-500 text-white border-amber-500 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:border-amber-300 hover:bg-amber-50 hover:text-amber-700']"
                            >
                                {{ p.nama }}
                            </button>
                        </div>
                    </div>

                    <!-- Error -->
                    <div v-if="hadisError" class="bg-rose-50 text-rose-700 rounded-xl p-4 text-sm text-center border border-rose-100 mb-4">
                        {{ hadisError }}
                    </div>

                    <!-- Loading -->
                    <div v-else-if="loadingHadis" class="flex flex-col items-center justify-center py-16 text-slate-400">
                        <svg class="w-8 h-8 animate-spin mb-3 text-amber-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span class="text-sm">Memuat hadis...</span>
                    </div>

                    <!-- Kartu Hadis -->
                    <div v-else-if="hadisData" class="space-y-4">
                        <!-- Info -->
                        <div class="flex items-center justify-between text-xs text-slate-400">
                            <span class="px-2.5 py-1 bg-amber-50 text-amber-700 rounded-full font-bold border border-amber-100">
                                {{ currentPerawi?.nama }} · No. {{ hadisNomor }}
                            </span>
                            <span class="text-slate-400">dari {{ currentPerawi?.total?.toLocaleString('id-ID') }} hadis</span>
                        </div>

                        <!-- Kartu Isi Hadis -->
                        <div class="bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-100 rounded-2xl p-5 space-y-4">
                            <!-- Teks Arab -->
                            <div class="text-right" dir="rtl">
                                <p class="text-xl sm:text-2xl font-arabic leading-relaxed text-slate-800">
                                    {{ hadisData.arab }}
                                </p>
                            </div>

                            <!-- Divider -->
                            <div class="border-t border-amber-200"></div>

                            <!-- Terjemahan -->
                            <div class="text-sm text-slate-700 leading-relaxed">
                                {{ hadisData.id }}
                            </div>
                        </div>

                        <!-- Navigasi -->
                        <div class="flex items-center gap-3">
                            <button
                                @click="prevHadis"
                                :disabled="hadisNomor <= 1"
                                class="flex items-center gap-1.5 px-4 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                Sebelumnya
                            </button>

                            <button
                                @click="randomHadis"
                                class="flex-1 py-2.5 bg-amber-50 text-amber-700 rounded-xl text-sm font-bold hover:bg-amber-100 transition-colors border border-amber-100 flex items-center justify-center gap-1.5"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Acak
                            </button>

                            <button
                                @click="nextHadis"
                                :disabled="hadisNomor >= (currentPerawi?.total ?? 9999)"
                                class="flex items-center gap-1.5 px-4 py-2.5 bg-amber-500 text-white rounded-xl text-sm font-bold hover:bg-amber-600 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                Berikutnya
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>

                        <!-- Loncat ke nomor -->
                        <div class="flex gap-2">
                            <input
                                v-model="inputNomor"
                                type="number"
                                :placeholder="`Loncat ke nomor (1–${currentPerawi?.total?.toLocaleString('id-ID')})`"
                                @keyup.enter="goToNomor"
                                class="flex-1 px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 bg-slate-50"
                                :min="1"
                                :max="currentPerawi?.total"
                            />
                            <button
                                @click="goToNomor"
                                class="px-4 py-2.5 bg-slate-700 text-white rounded-xl text-sm font-bold hover:bg-slate-800 transition-colors"
                            >
                                Tampilkan
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kredit -->
            <p class="text-center text-[11px] text-slate-400 pb-2">
                Data Al-Qur'an: <a href="https://equran.id" target="_blank" class="underline hover:text-emerald-600">EQuran.id</a> (sumber: Kemenag RI) ·
                Hadis: <a href="https://api.myquran.com" target="_blank" class="underline hover:text-amber-600">MyQuran.com</a>
            </p>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.font-arabic {
    font-family: 'Traditional Arabic', 'Scheherazade New', 'KFGQPC Uthmanic Script HAFS', serif;
}
</style>
