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

// ─── Bookmark / Terakhir Dibaca ──────────────────────────────────────────────
const lastReadBookmark = ref(null)

const loadBookmark = () => {
    try {
        const saved = localStorage.getItem('quran_last_read')
        if (saved) {
            lastReadBookmark.value = JSON.parse(saved)
        }
    } catch (e) {
        lastReadBookmark.value = null
    }
}

const toggleBookmark = (surah, ayat) => {
    if (isAyatBookmarked(surah.nomor, ayat.nomorAyat)) {
        lastReadBookmark.value = null
        localStorage.removeItem('quran_last_read')
    } else {
        const data = {
            surahNomor: surah.nomor,
            surahNamaLatin: surah.namaLatin,
            surahNamaArab: surah.nama,
            ayatNomor: ayat.nomorAyat,
            timestamp: new Date().toISOString()
        }
        lastReadBookmark.value = data
        localStorage.setItem('quran_last_read', JSON.stringify(data))
    }
}

const isAyatBookmarked = (surahNomor, ayatNomor) => {
    return lastReadBookmark.value &&
           lastReadBookmark.value.surahNomor === surahNomor &&
           lastReadBookmark.value.ayatNomor === ayatNomor
}

const clearBookmark = () => {
    lastReadBookmark.value = null
    localStorage.removeItem('quran_last_read')
}

const scrollToAyat = (nomorAyat) => {
    const el = document.getElementById(`ayat-${nomorAyat}`)
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
}

const goToBookmark = async () => {
    if (!lastReadBookmark.value) return
    const { surahNomor, ayatNomor } = lastReadBookmark.value
    await fetchSurahDetail(surahNomor)
    setTimeout(() => {
        scrollToAyat(ayatNomor)
    }, 400)
}

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

// ─── Pengaturan Tampilan Al-Qur'an (Latin & Terjemahan) ──────────────────────
const showLatin = ref(true)
const showTranslation = ref(true)

const loadQuranSettings = () => {
    if (typeof window !== 'undefined') {
        const savedLatin = localStorage.getItem('quran_show_latin')
        if (savedLatin !== null) {
            showLatin.value = savedLatin === 'true'
        }
        const savedTrans = localStorage.getItem('quran_show_translation')
        if (savedTrans !== null) {
            showTranslation.value = savedTrans === 'true'
        }
    }
}

const toggleLatin = () => {
    showLatin.value = !showLatin.value
    if (typeof window !== 'undefined') {
        localStorage.setItem('quran_show_latin', String(showLatin.value))
    }
}

const toggleTranslation = () => {
    showTranslation.value = !showTranslation.value
    if (typeof window !== 'undefined') {
        localStorage.setItem('quran_show_translation', String(showTranslation.value))
    }
}

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

// ─── Search Hadis Lintas Kitab ────────────────────────────────────────────────
const hadisSearchKeyword = ref('')
const hadisSearchResults = ref([])
const hadisSearchPaging  = ref(null)
const hadisSearchCurrentPage = ref(1)
const loadingSearchHadis = ref(false)
const hadisSearchError   = ref(null)
const isSearchModeHadis  = ref(false)

const searchHadis = async (page = 1) => {
    const q = hadisSearchKeyword.value.trim()
    if (!q) {
        isSearchModeHadis.value = false
        hadisSearchResults.value = []
        return
    }
    
    loadingSearchHadis.value = true
    hadisSearchError.value = null
    isSearchModeHadis.value = true
    hadisSearchCurrentPage.value = page

    try {
        const url = `https://api.myquran.com/v3/hadis/enc/cari/${encodeURIComponent(q)}?page=${page}`
        const res = await fetch(url)
        if (res.status === 429) {
            hadisSearchError.value = 'Terlalu banyak permintaan ke server hadis. Silakan tunggu beberapa saat dan coba lagi.'
            return
        }
        const json = await res.json()
        if (json.status && json.data && json.data.hadis) {
            hadisSearchResults.value = json.data.hadis
            hadisSearchPaging.value = json.data.paging
        } else {
            hadisSearchResults.value = []
            hadisSearchPaging.value = null
            hadisSearchError.value = `Tidak ditemukan hadis dengan kata kunci "${q}".`
        }
    } catch (e) {
        hadisSearchError.value = 'Gagal melakukan pencarian hadis. Periksa koneksi internet.'
    } finally {
        loadingSearchHadis.value = false
    }
}

const clearSearchHadis = () => {
    hadisSearchKeyword.value = ''
    hadisSearchResults.value = []
    hadisSearchPaging.value = null
    hadisSearchError.value = null
    isSearchModeHadis.value = false
    selectedSearchHadis.value = null
    selectedSearchHadisIndex.value = null
}

// Detail Hadis Pencarian
const selectedSearchHadis = ref(null)
const selectedSearchHadisIndex = ref(null)
const copyToast = ref(false)
const hadisDetailExtra = ref({})
const loadingExtraDetail = ref(false)

const fetchHadisExtra = async (id) => {
    if (!id || hadisDetailExtra.value[id]) return
    loadingExtraDetail.value = true
    try {
        const res = await fetch(`https://hadeethenc.com/api/v1/hadeeths/one/?language=id&id=${id}`)
        const data = await res.json()
        if (data && data.id) {
            hadisDetailExtra.value[id] = data
        }
    } catch (e) {
        // silent fail
    } finally {
        loadingExtraDetail.value = false
    }
}

const openSearchHadisDetail = (item, index) => {
    selectedSearchHadis.value = item
    selectedSearchHadisIndex.value = index
    fetchHadisExtra(item.id)
}

const closeSearchHadisDetail = () => {
    selectedSearchHadis.value = null
    selectedSearchHadisIndex.value = null
}

const prevSearchHadisDetail = () => {
    if (selectedSearchHadisIndex.value !== null && selectedSearchHadisIndex.value > 0) {
        selectedSearchHadisIndex.value--
        selectedSearchHadis.value = hadisSearchResults.value[selectedSearchHadisIndex.value]
        fetchHadisExtra(selectedSearchHadis.value.id)
    }
}

const nextSearchHadisDetail = () => {
    if (selectedSearchHadisIndex.value !== null && selectedSearchHadisIndex.value < hadisSearchResults.value.length - 1) {
        selectedSearchHadisIndex.value++
        selectedSearchHadis.value = hadisSearchResults.value[selectedSearchHadisIndex.value]
        fetchHadisExtra(selectedSearchHadis.value.id)
    }
}

const copyToClipboard = async (text) => {
    if (!text) return false

    // 1. Coba navigator.clipboard jika di secure context
    if (typeof navigator !== 'undefined' && navigator.clipboard && (window.isSecureContext || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')) {
        try {
            await navigator.clipboard.writeText(text)
            return true
        } catch (err) {
            console.warn('navigator.clipboard gagal, gunakan fallback:', err)
        }
    }

    // 2. Fallback universal untuk non-HTTPS / Laragon .test domain
    try {
        const textarea = document.createElement('textarea')
        textarea.value = text
        textarea.style.position = 'fixed'
        textarea.style.top = '0'
        textarea.style.left = '0'
        textarea.style.width = '2em'
        textarea.style.height = '2em'
        textarea.style.padding = '0'
        textarea.style.border = 'none'
        textarea.style.outline = 'none'
        textarea.style.boxShadow = 'none'
        textarea.style.background = 'transparent'
        textarea.style.opacity = '0'
        textarea.setAttribute('readonly', '')
        
        document.body.appendChild(textarea)
        textarea.focus()
        textarea.select()
        textarea.setSelectionRange(0, textarea.value.length)
        
        const success = document.execCommand('copy')
        document.body.removeChild(textarea)
        return success
    } catch (err) {
        console.error('Fallback execCommand copy gagal:', err)
        return false
    }
}

const copyHadisText = async (text) => {
    if (!text) return
    const success = await copyToClipboard(text)
    if (success) {
        copyToast.value = true
        setTimeout(() => {
            copyToast.value = false
        }, 2500)
    }
}

const highlightKeyword = (text, keyword) => {
    if (!keyword || !text) return text
    const escaped = keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    const regex = new RegExp(`(${escaped})`, 'gi')
    return text.replace(regex, '<mark class="bg-amber-200 text-amber-950 px-1 py-0.5 rounded font-semibold">$1</mark>')
}

// ─── Fitur Bagikan ke Media Sosial ────────────────────────────────────────────
const shareModalOpen = ref(false)
const shareData = ref({
    type: 'quran', // 'quran' | 'hadis'
    title: '',
    arab: '',
    translation: '',
    source: '',
    fullText: ''
})
const shareCopied = ref(false)

const openShareQuran = (surah, ayat) => {
    const title = `QS. ${surah.namaLatin} : Ayat ${ayat.nomorAyat}`
    const arab = ayat.teksArab || ''
    const translation = ayat.teksIndonesia || ''
    const source = `Al-Qur'an Surah ${surah.namaLatin} (${surah.arti}) [${surah.nomor}:${ayat.nomorAyat}]`
    const fullText = `📖 *${title}*\n\n${arab}\n\n"${translation}"\n\n📌 _Sumber: ${source}_\n_Dibagikan melalui Habit Tracker SDAM_`

    shareData.value = {
        type: 'quran',
        title,
        arab,
        translation,
        source,
        fullText
    }
    shareCopied.value = false
    shareModalOpen.value = true
}

const openShareHadisPerawi = (perawiName, nomor, data) => {
    const title = `Hadis Riwayat ${perawiName} No. ${nomor}`
    const arab = data.arab || ''
    const translation = data.id || ''
    const source = `HR. ${perawiName} (No. ${nomor})`
    const fullText = `📜 *${title}*\n\n${arab ? arab + '\n\n' : ''}"${translation}"\n\n📌 _Sumber: ${source}_\n_Dibagikan melalui Habit Tracker SDAM_`

    shareData.value = {
        type: 'hadis',
        title,
        arab,
        translation,
        source,
        fullText
    }
    shareCopied.value = false
    shareModalOpen.value = true
}

const openShareHadisSearch = (item, extra) => {
    const source = extra?.attribution || 'Ensiklopedia Hadis'
    const grade = extra?.grade ? ` (Derajat: ${extra.grade})` : ''
    const title = `Hadis: ${source}`
    const arab = extra?.hadeeth_ar || ''
    const translation = item.text || ''
    const fullText = `📜 *${title}${grade}*\n\n${arab ? arab + '\n\n' : ''}"${translation}"\n\n📌 _Sumber: ${source}_\n_Dibagikan melalui Habit Tracker SDAM_`

    shareData.value = {
        type: 'hadis',
        title: `${source}${grade}`,
        arab,
        translation,
        source,
        fullText
    }
    shareCopied.value = false
    shareModalOpen.value = true
}

const closeShareModal = () => {
    shareModalOpen.value = false
}

const shareToWhatsApp = () => {
    const url = `https://api.whatsapp.com/send?text=${encodeURIComponent(shareData.value.fullText)}`
    window.open(url, '_blank')
}

const shareToTelegram = () => {
    const origin = typeof window !== 'undefined' ? window.location.origin : ''
    const url = `https://t.me/share/url?url=${encodeURIComponent(origin)}&text=${encodeURIComponent(shareData.value.fullText)}`
    window.open(url, '_blank')
}

const shareToTwitter = () => {
    const maxLen = 200
    const excerpt = shareData.value.translation.length > maxLen ? shareData.value.translation.slice(0, maxLen) + '...' : shareData.value.translation
    const tweetText = `${shareData.value.title}\n\n"${excerpt}"\n\n${shareData.value.source}`
    const url = `https://twitter.com/intent/tweet?text=${encodeURIComponent(tweetText)}`
    window.open(url, '_blank')
}

const shareToFacebook = () => {
    const origin = typeof window !== 'undefined' ? window.location.origin : ''
    const url = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(origin)}&quote=${encodeURIComponent(shareData.value.fullText)}`
    window.open(url, '_blank')
}

const shareNative = async () => {
    if (typeof navigator !== 'undefined' && navigator.share) {
        try {
            await navigator.share({
                title: shareData.value.title,
                text: shareData.value.fullText
            })
        } catch (e) {
            // User cancel / dismiss
        }
    }
}

const copyShareText = async () => {
    const success = await copyToClipboard(shareData.value.fullText)
    if (success) {
        shareCopied.value = true
        setTimeout(() => {
            shareCopied.value = false
        }, 2500)
    }
}

// ─── Init ─────────────────────────────────────────────────────────────────────
onMounted(() => {
    fetchSurahList()
    fetchHadis(1)
    loadBookmark()
    loadQuranSettings()
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
    <AuthenticatedLayout>
        <Head title="Al-Qur'an & Hadis" />

        <div class="w-full space-y-6">

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
                        <div class="flex items-center justify-between gap-2 mb-5">
                            <button @click="goBack" class="flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-slate-600 hover:text-emerald-700 transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                </svg>
                                Kembali
                            </button>
                            <div class="flex items-center gap-1.5 sm:gap-2">
                                <!-- Tombol cepat ke bookmark jika di surah ini -->
                                <button 
                                    v-if="lastReadBookmark?.surahNomor === surahDetail.nomor" 
                                    @click="scrollToAyat(lastReadBookmark.ayatNomor)" 
                                    class="px-2 sm:px-2.5 py-1.5 bg-emerald-100 text-emerald-800 rounded-lg text-[11px] sm:text-xs font-bold hover:bg-emerald-200 transition-colors flex items-center gap-1 shadow-sm"
                                    title="Lompat ke ayat yang ditandai"
                                >
                                    <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                                    <span>Ayat {{ lastReadBookmark.ayatNomor }}</span>
                                </button>
                                <button v-if="surahDetail.suratSebelumnya" @click="goToSurah(surahDetail.suratSebelumnya.nomor)" class="px-2 sm:px-2.5 py-1.5 bg-emerald-50 text-emerald-700 rounded-lg text-xs font-bold hover:bg-emerald-100 transition-colors flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                    Prev
                                </button>
                                <button v-if="surahDetail.suratSelanjutnya" @click="goToSurah(surahDetail.suratSelanjutnya.nomor)" class="px-2 sm:px-2.5 py-1.5 bg-emerald-50 text-emerald-700 rounded-lg text-xs font-bold hover:bg-emerald-100 transition-colors flex items-center gap-1">
                                    Next
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Header surah -->
                        <div class="text-center mb-5 pb-5 border-b border-slate-100">
                            <div class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-emerald-100 text-emerald-700 font-bold text-sm mb-2">{{ surahDetail.nomor }}</div>
                            <div class="text-3xl font-arabic text-slate-800 mb-1">{{ surahDetail.nama }}</div>
                            <div class="text-base font-bold text-slate-700">{{ surahDetail.namaLatin }}</div>
                            <div class="text-sm text-slate-500 mt-0.5">{{ surahDetail.arti }} · {{ surahDetail.jumlahAyat }} Ayat · {{ surahDetail.tempatTurun }}</div>
                        </div>

                        <!-- Bar Pengaturan Opsi Tampilan Ayat (Tepat di bawah Nama Surah) -->
                        <div class="flex flex-wrap items-center justify-between gap-3 p-3 sm:p-3.5 bg-emerald-50/50 rounded-2xl border border-emerald-100 mb-6">
                            <div class="flex items-center gap-2 text-xs font-bold text-slate-700">
                                <div class="w-7 h-7 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                    </svg>
                                </div>
                                <span>Pengaturan Tampilan:</span>
                            </div>
                            
                            <div class="flex items-center gap-2">
                                <!-- Switch Transliterasi Latin -->
                                <button 
                                    type="button" 
                                    @click="toggleLatin"
                                    :class="[
                                        'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all border shadow-xs',
                                        showLatin 
                                            ? 'bg-emerald-600 text-white border-emerald-600 ring-1 ring-emerald-600' 
                                            : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50'
                                    ]"
                                    title="Tampilkan / sembunyikan bacaan Latin (Transliterasi)"
                                >
                                    <span class="w-2 h-2 rounded-full" :class="showLatin ? 'bg-white' : 'bg-slate-300'"></span>
                                    <span>Latin</span>
                                </button>

                                <!-- Switch Terjemahan Bahasa Indonesia -->
                                <button 
                                    type="button" 
                                    @click="toggleTranslation"
                                    :class="[
                                        'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all border shadow-xs',
                                        showTranslation 
                                            ? 'bg-emerald-600 text-white border-emerald-600 ring-1 ring-emerald-600' 
                                            : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50'
                                    ]"
                                    title="Tampilkan / sembunyikan arti terjemahan bahasa Indonesia"
                                >
                                    <span class="w-2 h-2 rounded-full" :class="showTranslation ? 'bg-white' : 'bg-slate-300'"></span>
                                    <span>Terjemahan</span>
                                </button>
                            </div>
                        </div>

                        <!-- Basmalah (kecuali At-Taubah surah 9) -->
                        <div v-if="surahDetail.nomor !== 9 && surahDetail.nomor !== 1" class="text-center text-2xl font-arabic text-slate-700 mb-6 py-2">
                            بِسْمِ اللّٰهِ الرَّحْمٰنِ الرَّحِيْمِ
                        </div>

                        <!-- Ayat-ayat -->
                        <div class="space-y-5">
                            <div 
                                v-for="ayat in surahDetail.ayat" 
                                :key="ayat.nomorAyat" 
                                :id="'ayat-' + ayat.nomorAyat" 
                                class="relative group scroll-mt-24"
                            >
                                <div 
                                    :class="[
                                        'rounded-2xl p-4 sm:p-5 border transition-all duration-200',
                                        isAyatBookmarked(surahDetail.nomor, ayat.nomorAyat) 
                                            ? 'bg-emerald-50/80 border-emerald-400 ring-2 ring-emerald-200/70 shadow-sm' 
                                            : 'bg-slate-50/60 border-slate-100 hover:border-emerald-200 hover:bg-emerald-50/20'
                                    ]"
                                >
                                    <!-- Baris 1: Header Aksi Ayat (Baris Tersendiri - Nomor, Bookmark, Bagikan) -->
                                    <div class="flex items-center justify-between gap-2 pb-3 mb-4 border-b border-slate-100/90">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs sm:text-sm font-bold flex-shrink-0">
                                                {{ ayat.nomorAyat }}
                                            </div>
                                            <span class="text-xs font-semibold text-slate-400">Ayat {{ ayat.nomorAyat }}</span>
                                        </div>

                                        <div class="flex items-center gap-1.5 sm:gap-2">
                                            <!-- Tombol Bookmark -->
                                            <button
                                                type="button"
                                                @click="toggleBookmark(surahDetail, ayat)"
                                                :title="isAyatBookmarked(surahDetail.nomor, ayat.nomorAyat) ? 'Hapus penanda terakhir dibaca' : 'Tandai sebagai terakhir dibaca'"
                                                :class="[
                                                    'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-semibold transition-all shadow-xs',
                                                    isAyatBookmarked(surahDetail.nomor, ayat.nomorAyat)
                                                        ? 'bg-emerald-600 text-white ring-1 ring-emerald-600'
                                                        : 'text-slate-500 hover:text-emerald-700 hover:bg-emerald-50 bg-white border border-slate-200'
                                                ]"
                                            >
                                                <svg class="w-3.5 h-3.5" :fill="isAyatBookmarked(surahDetail.nomor, ayat.nomorAyat) ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                                                </svg>
                                                <span class="text-[11px] sm:text-xs">
                                                    {{ isAyatBookmarked(surahDetail.nomor, ayat.nomorAyat) ? 'Terakhir Baca' : 'Tandai' }}
                                                </span>
                                            </button>

                                            <!-- Tombol Bagikan Ayat -->
                                            <button
                                                type="button"
                                                @click="openShareQuran(surahDetail, ayat)"
                                                title="Bagikan ayat ini ke media sosial"
                                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-semibold text-slate-500 hover:text-emerald-700 hover:bg-emerald-50 bg-white border border-slate-200 transition-all shadow-xs"
                                            >
                                                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                                </svg>
                                                <span class="text-[11px] sm:text-xs">Bagikan</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Baris 2: Teks Arab Ayat (Lebar Penuh) -->
                                    <div class="w-full text-right text-2xl sm:text-3xl md:text-4xl font-arabic leading-loose text-slate-800 mb-4 py-1" dir="rtl">
                                        {{ ayat.teksArab }}
                                    </div>

                                    <!-- Transliterasi Latin (Conditional) -->
                                    <div v-if="showLatin" class="text-xs sm:text-sm text-emerald-700 italic mb-2 pl-1 sm:pl-2">
                                        {{ ayat.teksLatin }}
                                    </div>

                                    <!-- Terjemahan Bahasa Indonesia (Conditional) -->
                                    <div v-if="showTranslation" class="text-sm text-slate-700 leading-relaxed pl-1 sm:pl-2">
                                        {{ ayat.teksIndonesia }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Navigasi bawah -->
                        <div class="flex gap-2 sm:gap-3 mt-8 pt-5 border-t border-slate-100">
                            <button v-if="surahDetail.suratSebelumnya" @click="goToSurah(surahDetail.suratSebelumnya.nomor)" class="flex-1 py-2.5 px-2 bg-slate-50 text-slate-700 rounded-xl text-xs sm:text-sm font-bold hover:bg-slate-100 transition-colors flex items-center justify-center gap-1.5 border border-slate-200 min-w-0">
                                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                <span class="truncate">{{ surahDetail.suratSebelumnya.namaLatin }}</span>
                            </button>
                            <button v-if="surahDetail.suratSelanjutnya" @click="goToSurah(surahDetail.suratSelanjutnya.nomor)" class="flex-1 py-2.5 px-2 bg-emerald-600 text-white rounded-xl text-xs sm:text-sm font-bold hover:bg-emerald-700 transition-colors flex items-center justify-center gap-1.5 min-w-0">
                                <span class="truncate">{{ surahDetail.suratSelanjutnya.namaLatin }}</span>
                                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
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
                        <!-- Banner Terakhir Dibaca (Bookmark) -->
                        <div v-if="lastReadBookmark" class="mb-5 bg-gradient-to-r from-emerald-600 to-teal-600 rounded-2xl p-4 sm:p-5 text-white shadow-md flex flex-col sm:flex-row sm:items-center justify-between gap-3 relative overflow-hidden">
                            <div class="absolute right-0 top-0 translate-x-4 -translate-y-4 opacity-10 pointer-events-none">
                                <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                                </svg>
                            </div>
                            <div class="flex items-center gap-3 relative z-10">
                                <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0 text-amber-300">
                                    <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                                        <path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-[11px] font-semibold text-emerald-100 uppercase tracking-wider">Penanda Terakhir Dibaca</div>
                                    <div class="text-base sm:text-lg font-bold">
                                        Surah {{ lastReadBookmark.surahNamaLatin }} : Ayat {{ lastReadBookmark.ayatNomor }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 relative z-10">
                                <button
                                    @click="goToBookmark"
                                    class="flex-1 sm:flex-none px-4 py-2.5 bg-white text-emerald-800 hover:bg-emerald-50 rounded-xl text-xs sm:text-sm font-bold shadow-sm transition-all flex items-center justify-center gap-1.5"
                                >
                                    <span>Lanjutkan Membaca</span>
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </button>
                                <button
                                    @click="clearBookmark"
                                    title="Hapus Penanda"
                                    class="p-2.5 text-emerald-200 hover:text-white hover:bg-white/10 rounded-xl transition-colors shrink-0"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

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
                            Tidak ada surah yang cocok dengan pencarian "{{ surahSearch }}".
                        </div>

                        <!-- Pagination (only show if more than 1 page) -->
                        <div v-if="quranTotalPages > 1" class="flex items-center justify-center gap-3 mt-6 pt-4 border-t border-slate-100">
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

                    <!-- Form Pencarian Hadis Lintas Kitab -->
                    <form @submit.prevent="searchHadis(1)" class="mb-6">
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <input 
                                    v-model="hadisSearchKeyword" 
                                    type="text" 
                                    placeholder="Cari hadis dari berbagai kitab (contoh: niat, sholat, sedekah, sabar, surga)..." 
                                    class="w-full pl-10 pr-10 py-2.5 sm:py-3 border border-slate-200 rounded-xl text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 bg-slate-50 min-w-0" 
                                />
                                <button 
                                    v-if="hadisSearchKeyword" 
                                    type="button" 
                                    @click="clearSearchHadis" 
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1"
                                    title="Hapus pencarian"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <button 
                                type="submit" 
                                :disabled="!hadisSearchKeyword.trim() || loadingSearchHadis"
                                class="px-4 sm:px-6 py-2.5 sm:py-3 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs sm:text-sm font-bold shadow-sm transition-all flex items-center justify-center gap-1.5 disabled:opacity-50 shrink-0"
                            >
                                <svg v-if="loadingSearchHadis" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                <span>Cari</span>
                            </button>
                        </div>
                    </form>

                    <!-- MODE 1: HASIL PENCARIAN HADIS LINTAS KITAB -->
                    <div v-if="isSearchModeHadis" class="space-y-4">
                        <!-- Search Header Bar -->
                        <div class="flex items-center justify-between gap-3 bg-amber-50/70 border border-amber-200/80 rounded-2xl p-3.5 sm:p-4">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-[11px] text-slate-500 font-medium uppercase tracking-wider">Hasil Pencarian Lintas Kitab</div>
                                    <div class="text-sm font-bold text-slate-800 truncate">
                                        "{{ hadisSearchKeyword }}" 
                                        <span v-if="hadisSearchPaging?.total_data !== undefined" class="text-xs font-semibold text-amber-700">
                                            ({{ hadisSearchPaging.total_data }} hadis ditemukan)
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <button 
                                @click="clearSearchHadis" 
                                class="px-3 py-1.5 bg-white border border-slate-200 text-slate-600 hover:text-amber-700 hover:border-amber-300 rounded-xl text-xs font-bold transition-all shrink-0 flex items-center gap-1 shadow-sm"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                <span>Tutup Hasil</span>
                            </button>
                        </div>

                        <!-- Error Search -->
                        <div v-if="hadisSearchError" class="bg-rose-50 text-rose-700 rounded-2xl p-4 text-sm text-center border border-rose-100">
                            {{ hadisSearchError }}
                        </div>

                        <!-- Loading Search -->
                        <div v-else-if="loadingSearchHadis" class="flex flex-col items-center justify-center py-16 text-slate-400">
                            <svg class="w-8 h-8 animate-spin mb-3 text-amber-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <span class="text-sm font-medium">Mencari hadis dari berbagai kitab...</span>
                        </div>

                        <!-- List Hasil Pencarian (Cuplikan / Potongan Hadist) -->
                        <div v-else-if="hadisSearchResults.length > 0" class="space-y-3">
                            <div 
                                v-for="(item, idx) in hadisSearchResults" 
                                :key="item.id || idx" 
                                @click="openSearchHadisDetail(item, idx)"
                                class="bg-gradient-to-br from-amber-50/50 to-orange-50/20 border border-amber-100 hover:border-amber-300 hover:bg-amber-50/90 rounded-2xl p-4 sm:p-5 space-y-2.5 transition-all duration-200 shadow-sm cursor-pointer group"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="px-2.5 py-0.5 bg-amber-100 text-amber-800 rounded-full text-xs font-bold border border-amber-200">
                                        Hadis #{{ ((hadisSearchPaging?.current || 1) - 1) * (hadisSearchPaging?.per_page || 10) + idx + 1 }}
                                    </span>
                                    <span v-if="item.focus && item.focus.length > 0" class="text-[11px] text-amber-800 bg-amber-100/70 px-2.5 py-0.5 rounded-full font-medium border border-amber-200 truncate max-w-[200px] sm:max-w-[300px]">
                                        {{ item.focus[0] }}
                                    </span>
                                </div>
                                
                                <!-- Cuplikan Teks Hadis (Line-clamp 2 / 3) -->
                                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed line-clamp-2 sm:line-clamp-3">
                                    {{ item.text }}
                                </p>

                                <div class="pt-1.5 flex items-center justify-between border-t border-amber-100/80 text-xs font-bold text-amber-700 group-hover:text-amber-800">
                                    <span class="flex items-center gap-1.5">
                                        <span>Buka Hadis Lengkap</span>
                                        <svg class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                        </svg>
                                    </span>
                                    <span class="text-[11px] text-slate-400 font-normal">Klik untuk membaca</span>
                                </div>
                            </div>

                            <!-- Pagination Search -->
                            <div v-if="hadisSearchPaging && (hadisSearchPaging.has_prev || hadisSearchPaging.has_next)" class="flex items-center justify-center gap-3 pt-4 border-t border-slate-100">
                                <button 
                                    @click="searchHadis(hadisSearchPaging.current - 1)" 
                                    :disabled="!hadisSearchPaging.has_prev"
                                    class="px-3 py-2 border border-slate-200 rounded-xl text-xs sm:text-sm font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:hover:bg-transparent flex items-center gap-1"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                                    Sebelumnya
                                </button>
                                
                                <span class="text-xs font-bold text-slate-600">
                                    Halaman {{ hadisSearchPaging.current }} dari {{ hadisSearchPaging.total_pages }}
                                </span>
                                
                                <button 
                                    @click="searchHadis(hadisSearchPaging.current + 1)" 
                                    :disabled="!hadisSearchPaging.has_next"
                                    class="px-3 py-2 border border-slate-200 rounded-xl text-xs sm:text-sm font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:hover:bg-transparent flex items-center gap-1"
                                >
                                    Berikutnya
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- MODE 2: JELAJAH PER KAWIT/PERAWI -->
                    <div v-else>
                        <!-- Pilih Perawi -->
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Pilih Kitab / Perawi</label>
                            <div class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-9 gap-1.5 sm:gap-2">
                                <button
                                    v-for="p in perawiList"
                                    :key="p.slug"
                                    @click="selectedPerawi = p.slug"
                                    :class="['px-2 py-2 rounded-xl text-xs font-semibold transition-all border text-center truncate', selectedPerawi === p.slug ? 'bg-amber-500 text-white border-amber-500 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:border-amber-300 hover:bg-amber-50 hover:text-amber-700']"
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
                            <!-- Info & Tombol Bagikan Hadis Perawi -->
                            <div class="flex items-center justify-between text-xs text-slate-400">
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-1 bg-amber-50 text-amber-700 rounded-full font-bold border border-amber-100">
                                        {{ currentPerawi?.nama }} · No. {{ hadisNomor }}
                                    </span>
                                    <span class="hidden sm:inline text-slate-400">dari {{ currentPerawi?.total?.toLocaleString('id-ID') }} hadis</span>
                                </div>
                                <button
                                    type="button"
                                    @click="openShareHadisPerawi(currentPerawi?.nama, hadisNomor, hadisData)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1 bg-white border border-slate-200 hover:border-amber-300 hover:bg-amber-50 text-slate-700 hover:text-amber-800 rounded-xl text-xs font-bold transition-all shadow-xs"
                                    title="Bagikan hadis ini ke media sosial"
                                >
                                    <svg class="w-3.5 h-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                    </svg>
                                    <span>Bagikan</span>
                                </button>
                            </div>

                            <!-- Kartu Isi Hadis -->
                            <div class="bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-100 rounded-2xl p-4 sm:p-5 space-y-4">
                                <!-- Teks Arab -->
                                <div class="text-right" dir="rtl">
                                    <p class="text-xl sm:text-2xl font-arabic leading-loose text-slate-800">
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

                            <!-- Navigasi Hadis (Responsive 3 Kolom) -->
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    @click="prevHadis"
                                    :disabled="hadisNomor <= 1"
                                    class="flex items-center justify-center gap-1 sm:gap-1.5 px-2 sm:px-4 py-2.5 bg-white border border-slate-200 text-slate-700 rounded-xl text-xs sm:text-sm font-bold hover:bg-slate-50 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                >
                                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                    <span class="truncate">Sebelumnya</span>
                                </button>

                                <button
                                    @click="randomHadis"
                                    class="flex items-center justify-center gap-1 sm:gap-1.5 px-2 sm:px-4 py-2.5 bg-amber-50 text-amber-700 rounded-xl text-xs sm:text-sm font-bold hover:bg-amber-100 transition-colors border border-amber-200"
                                >
                                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    <span class="truncate">Acak</span>
                                </button>

                                <button
                                    @click="nextHadis"
                                    :disabled="hadisNomor >= (currentPerawi?.total ?? 9999)"
                                    class="flex items-center justify-center gap-1 sm:gap-1.5 px-2 sm:px-4 py-2.5 bg-amber-500 text-white rounded-xl text-xs sm:text-sm font-bold hover:bg-amber-600 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                >
                                    <span class="truncate">Berikutnya</span>
                                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </button>
                            </div>

                            <!-- Loncat ke nomor -->
                            <div class="flex gap-2">
                                <input
                                    v-model="inputNomor"
                                    type="number"
                                    :placeholder="`Loncat ke nomor (1–${currentPerawi?.total?.toLocaleString('id-ID')})`"
                                    @keyup.enter="goToNomor"
                                    class="flex-1 px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 bg-slate-50 min-w-0"
                                    :min="1"
                                    :max="currentPerawi?.total"
                                />
                                <button
                                    @click="goToNomor"
                                    class="px-4 py-2.5 bg-slate-700 text-white rounded-xl text-sm font-bold hover:bg-slate-800 transition-colors shrink-0"
                                >
                                    Tampilkan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════ -->
            <!-- MODAL DETAIL HADIS PENCARIAN (LENGKAP)                 -->
            <!-- ══════════════════════════════════════════════════════ -->
            <div v-if="selectedSearchHadis" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeSearchHadisDetail"></div>
                
                <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                    <div class="flex min-h-full items-center justify-center p-3 sm:p-4 text-center">
                        <div class="relative transform overflow-hidden rounded-3xl bg-white dark:bg-slate-800 text-left shadow-2xl transition-all my-8 w-full max-w-2xl border border-slate-100 dark:border-slate-700">
                            
                            <!-- Header Modal -->
                            <div class="bg-gradient-to-r from-amber-500 to-orange-500 px-5 py-4 text-white flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl bg-white/20 flex items-center justify-center font-bold text-xs shadow-sm">
                                        #{{ ((hadisSearchPaging?.current || 1) - 1) * (hadisSearchPaging?.per_page || 10) + (selectedSearchHadisIndex ?? 0) + 1 }}
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-sm sm:text-base leading-tight">Ensiklopedia Hadis</h3>
                                        <p class="text-[11px] text-amber-100">Pencarian: "{{ hadisSearchKeyword }}"</p>
                                    </div>
                                </div>
                                <button 
                                    @click="closeSearchHadisDetail" 
                                    class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition-colors"
                                >
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Body Modal (Sumber Kitab, Teks Arab & Terjemahan Lengkap) -->
                            <div class="p-5 sm:p-6 max-h-[65vh] overflow-y-auto space-y-4">
                                
                                <!-- Baris Sumber Kitab & Derajat Hadis -->
                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 text-amber-900 rounded-xl text-xs font-bold border border-amber-200 shadow-xs">
                                        <svg class="w-4 h-4 text-amber-700 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                        </svg>
                                        <span>Sumber: {{ hadisDetailExtra[selectedSearchHadis.id]?.attribution || 'Memuat sumber kitab...' }}</span>
                                    </div>

                                    <div v-if="hadisDetailExtra[selectedSearchHadis.id]?.grade" class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-xl text-xs font-bold border border-emerald-200 shadow-xs">
                                        <svg class="w-3.5 h-3.5 text-emerald-600 fill-current" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                        <span>{{ hadisDetailExtra[selectedSearchHadis.id].grade }}</span>
                                    </div>

                                    <div v-if="selectedSearchHadis.focus && selectedSearchHadis.focus.length > 0" class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 text-slate-700 rounded-xl text-xs font-medium border border-slate-200">
                                        <span>Konteks: {{ selectedSearchHadis.focus[0] }}</span>
                                    </div>
                                </div>

                                <!-- Teks Arab Hadis (Jika ada) -->
                                <div v-if="hadisDetailExtra[selectedSearchHadis.id]?.hadeeth_ar" class="bg-amber-50/40 p-4 sm:p-5 rounded-2xl border border-amber-100 text-right" dir="rtl">
                                    <p class="text-xl sm:text-2xl font-arabic leading-loose text-slate-800">
                                        {{ hadisDetailExtra[selectedSearchHadis.id].hadeeth_ar }}
                                    </p>
                                </div>

                                <!-- Terjemahan Bahasa Indonesia Lengkap -->
                                <div class="bg-slate-50 dark:bg-slate-800/60 p-4 sm:p-5 rounded-2xl border border-slate-100 dark:border-slate-700 space-y-2">
                                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Terjemahan Lengkap:</div>
                                    <div 
                                        v-html="highlightKeyword(selectedSearchHadis.text, hadisSearchKeyword)" 
                                        class="text-sm sm:text-base text-slate-800 dark:text-slate-100 leading-relaxed font-normal whitespace-pre-line"
                                    ></div>
                                </div>

                                <!-- Faedah / Penjelasan Makna Hadis -->
                                <div v-if="hadisDetailExtra[selectedSearchHadis.id]?.explanation" class="bg-amber-50/30 p-4 rounded-2xl border border-amber-100/80 space-y-1.5">
                                    <div class="text-xs font-bold text-amber-900 flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span>Penjelasan / Makna Hadis:</span>
                                    </div>
                                    <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                                        {{ hadisDetailExtra[selectedSearchHadis.id].explanation }}
                                    </p>
                                </div>
                            </div>

                            <!-- Footer Modal (Aksi & Navigasi) -->
                            <div class="bg-slate-50 dark:bg-slate-800/50 px-5 py-3.5 border-t border-slate-100 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3">
                                <!-- Tombol Aksi Kiri (Salin & Bagikan) -->
                                <div class="flex items-center gap-2">
                                    <button 
                                        @click="copyHadisText(selectedSearchHadis.text)" 
                                        class="px-3 py-2 bg-white dark:bg-slate-700 hover:bg-slate-100 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-xs"
                                    >
                                        <svg v-if="!copyToast" class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10a2 2 0 01-2-2v-4a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2z" />
                                        </svg>
                                        <svg v-else class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>{{ copyToast ? 'Tersalin!' : 'Salin' }}</span>
                                    </button>

                                    <button 
                                        @click="openShareHadisSearch(selectedSearchHadis, hadisDetailExtra[selectedSearchHadis.id])" 
                                        class="px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-xs"
                                        title="Bagikan ke media sosial"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                        </svg>
                                        <span>Bagikan</span>
                                    </button>
                                </div>

                                <!-- Navigasi Prev/Next Hadis -->
                                <div class="flex items-center gap-2">
                                    <button 
                                        @click="prevSearchHadisDetail" 
                                        :disabled="selectedSearchHadisIndex === 0" 
                                        class="p-2 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-600 dark:text-slate-200 hover:bg-slate-100 disabled:opacity-40 disabled:hover:bg-white flex items-center justify-center transition-colors"
                                        title="Hadis Sebelumnya"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                    </button>
                                    <span class="text-xs font-semibold text-slate-500">
                                        {{ (selectedSearchHadisIndex ?? 0) + 1 }} / {{ hadisSearchResults.length }}
                                    </span>
                                    <button 
                                        @click="nextSearchHadisDetail" 
                                        :disabled="selectedSearchHadisIndex === hadisSearchResults.length - 1" 
                                        class="p-2 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-600 dark:text-slate-200 hover:bg-slate-100 disabled:opacity-40 disabled:hover:bg-white flex items-center justify-center transition-colors"
                                        title="Hadis Berikutnya"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                    <button 
                                        @click="closeSearchHadisDetail" 
                                        class="ml-1.5 px-3.5 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold transition-colors shadow-sm"
                                    >
                                        Tutup
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════ -->
            <!-- MODAL BAGIKAN KE MEDIA SOSIAL (SHARE SHEET)           -->
            <!-- ══════════════════════════════════════════════════════ -->
            <div v-if="shareModalOpen" class="relative z-50" aria-labelledby="modal-share" role="dialog" aria-modal="true">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeShareModal"></div>

                <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                    <div class="flex min-h-full items-center justify-center p-3 sm:p-4 text-center">
                        <div class="relative transform overflow-hidden rounded-3xl bg-white dark:bg-slate-800 text-left shadow-2xl transition-all my-8 w-full max-w-lg border border-slate-100 dark:border-slate-700">
                            
                            <!-- Header Modal Share -->
                            <div :class="['px-5 py-4 text-white flex items-center justify-between', shareData.type === 'quran' ? 'bg-gradient-to-r from-emerald-600 to-teal-600' : 'bg-gradient-to-r from-amber-500 to-orange-500']">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl bg-white/20 flex items-center justify-center">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-sm sm:text-base leading-tight">Bagikan ke Media Sosial</h3>
                                        <p class="text-[11px] opacity-90 truncate max-w-[220px] sm:max-w-[280px]">{{ shareData.title }}</p>
                                    </div>
                                </div>
                                <button 
                                    @click="closeShareModal" 
                                    class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition-colors"
                                >
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Preview Teks yang Dibagikan -->
                            <div class="p-5 sm:p-6 space-y-4 max-h-[45vh] overflow-y-auto">
                                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Preview Pesan:</div>
                                <div class="bg-slate-50 dark:bg-slate-800/80 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-700 space-y-2.5">
                                    <div class="font-bold text-xs sm:text-sm text-slate-800 dark:text-slate-200">{{ shareData.title }}</div>
                                    <div v-if="shareData.arab" class="text-right text-base sm:text-lg font-arabic leading-relaxed text-slate-700 dark:text-slate-300" dir="rtl">
                                        {{ shareData.arab }}
                                    </div>
                                    <div class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 italic leading-relaxed">
                                        "{{ shareData.translation }}"
                                    </div>
                                    <div class="text-[11px] text-slate-400 pt-1 border-t border-slate-200 dark:border-slate-700">
                                        {{ shareData.source }}
                                    </div>
                                </div>
                            </div>

                            <!-- Pilihan Tombol Media Sosial -->
                            <div class="bg-slate-50 dark:bg-slate-800/50 p-5 border-t border-slate-100 dark:border-slate-700 space-y-3">
                                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider text-center">Pilih Platform:</div>
                                
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                    <!-- WhatsApp -->
                                    <button 
                                        @click="shareToWhatsApp" 
                                        class="flex flex-col items-center justify-center p-3 rounded-2xl bg-[#25D366]/10 hover:bg-[#25D366]/20 text-[#128C7E] border border-[#25D366]/30 transition-all group"
                                    >
                                        <div class="w-9 h-9 rounded-full bg-[#25D366] text-white flex items-center justify-center mb-1.5 shadow-sm group-hover:scale-105 transition-transform">
                                            <!-- WhatsApp SVG Icon -->
                                            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                                                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                                            </svg>
                                        </div>
                                        <span class="text-xs font-bold">WhatsApp</span>
                                    </button>

                                    <!-- Telegram -->
                                    <button 
                                        @click="shareToTelegram" 
                                        class="flex flex-col items-center justify-center p-3 rounded-2xl bg-[#0088cc]/10 hover:bg-[#0088cc]/20 text-[#0088cc] border border-[#0088cc]/30 transition-all group"
                                    >
                                        <div class="w-9 h-9 rounded-full bg-[#0088cc] text-white flex items-center justify-center mb-1.5 shadow-sm group-hover:scale-105 transition-transform">
                                            <!-- Telegram SVG Icon -->
                                            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                                                <path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.18-.357.34-.635.34l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.121l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.458c.538-.196 1.006.128.832.894z"/>
                                            </svg>
                                        </div>
                                        <span class="text-xs font-bold">Telegram</span>
                                    </button>

                                    <!-- Twitter / X -->
                                    <button 
                                        @click="shareToTwitter" 
                                        class="flex flex-col items-center justify-center p-3 rounded-2xl bg-slate-900/10 hover:bg-slate-900/20 text-slate-900 dark:text-white border border-slate-300 dark:border-slate-600 transition-all group"
                                    >
                                        <div class="w-9 h-9 rounded-full bg-black text-white flex items-center justify-center mb-1.5 shadow-sm group-hover:scale-105 transition-transform">
                                            <!-- X / Twitter SVG Icon -->
                                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                                                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                            </svg>
                                        </div>
                                        <span class="text-xs font-bold">X (Twitter)</span>
                                    </button>

                                    <!-- Facebook -->
                                    <button 
                                        @click="shareToFacebook" 
                                        class="flex flex-col items-center justify-center p-3 rounded-2xl bg-[#1877F2]/10 hover:bg-[#1877F2]/20 text-[#1877F2] border border-[#1877F2]/30 transition-all group"
                                    >
                                        <div class="w-9 h-9 rounded-full bg-[#1877F2] text-white flex items-center justify-center mb-1.5 shadow-sm group-hover:scale-105 transition-transform">
                                            <!-- Facebook SVG Icon -->
                                            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                                                <path d="M9 8H6v4h3v12h5V12h3.642L18 8h-4V6.333C14 5.374 14.5 5 15.657 5H18V0h-3.808C10.597 0 9 1.582 9 4.615V8z"/>
                                            </svg>
                                        </div>
                                        <span class="text-xs font-bold">Facebook</span>
                                    </button>
                                </div>

                                <!-- Baris Aksi Tambahan (Salin & Share Native) -->
                                <div class="pt-2 flex gap-2">
                                    <button 
                                        @click="copyShareText" 
                                        class="flex-1 py-2.5 px-4 bg-white dark:bg-slate-700 hover:bg-slate-100 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5 shadow-xs"
                                    >
                                        <svg v-if="!shareCopied" class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10a2 2 0 01-2-2v-4a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2z" />
                                        </svg>
                                        <svg v-else class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>{{ shareCopied ? 'Teks Lengkap Tersalin!' : 'Salin Teks Format Cantik' }}</span>
                                    </button>

                                    <button 
                                        v-if="typeof navigator !== 'undefined' && !!navigator.share" 
                                        @click="shareNative" 
                                        class="py-2.5 px-4 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5 shadow-xs"
                                        title="Buka menu berbagi bawaan perangkat/HP"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                        </svg>
                                        <span>Lainnya...</span>
                                    </button>
                                </div>
                            </div>
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
    font-family: 'Scheherazade New', 'Amiri Quran', 'Traditional Arabic', serif;
    line-height: 2.2;
}
</style>
