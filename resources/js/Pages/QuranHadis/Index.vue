<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import axios from 'axios'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ShareImageModal from './Partials/ShareImageModal.vue'

const props = defineProps({
    initialTodaySessions: {
        type: Array,
        default: () => []
    },
    todayDate: {
        type: String,
        default: ''
    }
})

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

// ─── Sesi Tilawah Al-Qur'an Hari Ini (Multi-sesi Mulai & Selesai) ─────────────
const todaySessions = ref(props.initialTodaySessions ? [...props.initialTodaySessions] : [])
const isBookmarkModalOpen = ref(false)
const selectedAyatForBookmark = ref(null)
const isSavingBookmark = ref(false)
const bookmarkToast = ref({ show: false, message: '', type: 'success' })

const showToast = (message, type = 'success') => {
    bookmarkToast.value = { show: true, message, type }
    setTimeout(() => {
        bookmarkToast.value.show = false
    }, 4000)
}

const activeSession = computed(() => {
    return todaySessions.value.find(s => !s.surat_akhir)
})

const getAyatSesiInfo = (surahNamaLatin, ayatNomor) => {
    if (!todaySessions.value || !todaySessions.value.length) return null
    
    for (let i = 0; i < todaySessions.value.length; i++) {
        const s = todaySessions.value[i]
        const isStart = s.surat_awal === surahNamaLatin && Number(s.ayat_awal) === Number(ayatNomor)
        const isEnd = s.surat_akhir === surahNamaLatin && Number(s.ayat_akhir) === Number(ayatNomor)
        if (isStart && isEnd) {
            return { type: 'both', sessionIndex: i + 1, session: s }
        }
        if (isStart) {
            return { type: 'start', sessionIndex: i + 1, session: s }
        }
        if (isEnd) {
            return { type: 'end', sessionIndex: i + 1, session: s }
        }
    }
    return null
}

const inputDurasiMenit = ref('')

const openTandaiModal = (surah, ayat) => {
    selectedAyatForBookmark.value = {
        surahNomor: surah.nomor,
        surahNamaLatin: surah.namaLatin,
        surahNamaArab: surah.nama,
        ayatNomor: ayat.nomorAyat,
    }
    // Hitung perkiraan durasi menit dari jam mulai sesi aktif
    if (activeSession.value && activeSession.value.jam_mulai) {
        try {
            const [h, m] = activeSession.value.jam_mulai.split(':').map(Number)
            const now = new Date()
            const diff = Math.max(1, (now.getHours() * 60 + now.getMinutes()) - (h * 60 + m))
            inputDurasiMenit.value = diff
        } catch (e) {
            inputDurasiMenit.value = ''
        }
    } else {
        inputDurasiMenit.value = ''
    }
    isBookmarkModalOpen.value = true
}

const submitTandai = async (action, forceNew = false) => {
    if (!selectedAyatForBookmark.value || isSavingBookmark.value) return
    isSavingBookmark.value = true
    try {
        const payload = {
            action,
            surah_nomor: selectedAyatForBookmark.value.surahNomor,
            surah_nama: selectedAyatForBookmark.value.surahNamaLatin,
            ayat_nomor: selectedAyatForBookmark.value.ayatNomor,
            mode_baru: forceNew,
        }
        if (action === 'selesai' && inputDurasiMenit.value) {
            payload.durasi = parseInt(inputDurasiMenit.value) || undefined
        }
        const res = await axios.post(route('quran.tandai.baca'), payload)
        if (res.data && res.data.success) {
            todaySessions.value = res.data.sessions || []
            // Sinkronisasi dengan penanda lokal
            const data = {
                surahNomor: selectedAyatForBookmark.value.surahNomor,
                surahNamaLatin: selectedAyatForBookmark.value.surahNamaLatin,
                surahNamaArab: selectedAyatForBookmark.value.surahNamaArab,
                ayatNomor: selectedAyatForBookmark.value.ayatNomor,
                timestamp: new Date().toISOString()
            }
            lastReadBookmark.value = data
            if (typeof window !== 'undefined') {
                localStorage.setItem('quran_last_read', JSON.stringify(data))
            }

            showToast(res.data.message || 'Tanda baca berhasil disimpan ke Rekap Al-Qur\'an!', 'success')
            isBookmarkModalOpen.value = false
        }
    } catch (err) {
        console.error(err)
        showToast(err.response?.data?.message || 'Gagal menyimpan tanda baca ke rekap.', 'error')
    } finally {
        isSavingBookmark.value = false
    }
}

const hapusSesiById = async (sesiId) => {
    if (!confirm('Hapus sesi tilawah ini dari rekap Al-Qur\'an?')) return
    isSavingBookmark.value = true
    try {
        const res = await axios.delete(route('quran.hapus.sesi', { sesiId }))
        if (res.data && res.data.success) {
            todaySessions.value = res.data.sessions || []
            showToast('Sesi berhasil dihapus dari rekap Al-Qur\'an.', 'success')
            isBookmarkModalOpen.value = false
        }
    } catch (e) {
        console.error(e)
        showToast('Gagal menghapus sesi.', 'error')
    } finally {
        isSavingBookmark.value = false
    }
}

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

const targetHighlightedAyat = ref(null)
const selectedAyatJump = ref('')

const scrollToAyat = (nomorAyat) => {
    const el = document.getElementById(`ayat-${nomorAyat}`)
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' })
        targetHighlightedAyat.value = Number(nomorAyat)
        selectedAyatJump.value = String(nomorAyat)
        setTimeout(() => {
            targetHighlightedAyat.value = null
        }, 2500)
    }
}

const handleJumpToAyat = (nomorAyat) => {
    if (!nomorAyat) return
    scrollToAyat(nomorAyat)
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
    stopAudio() // Hentikan audio jika pindah surah
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
    stopAudio() // Hentikan audio jika kembali ke daftar surah
    surahDetail.value = null
    selectedSurah.value = null
    showMobileQuranSettings.value = false
}

const showMobileQuranSettings = ref(false)

const jumpAndCloseMobileSettings = (nomorAyat) => {
    if (!nomorAyat) return
    showMobileQuranSettings.value = false
    setTimeout(() => {
        scrollToAyat(nomorAyat)
    }, 200)
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
const quranItemsPerPage = computed(() => isMobile.value ? 10 : 50)

// ─── Pengaturan Tampilan Al-Qur'an (Font Scale, Latin & Terjemahan) ───────
const showLatin = ref(true)
const showTranslation = ref(true)
const fontScale = ref(100) // Persentase ukuran font arab: 70% s/d 180%, default 100%
const showQuranOptions = ref(false) // Toggle sembunyikan/tampilkan pengaturan

// ─── Pengaturan Audio Al-Qur'an ─────────
const audioQori = ref('05') // Default: Misyari Rasyid Al-Afasi
const currentAudio = ref(null)
const playingAyat = ref(null) // Format: 'surah_ayat' e.g. '1_1' or 'surah_1'
const isAudioPlaying = ref(false)

const qoriList = [
    { id: '01', name: 'Abdullah Al-Juhany' },
    { id: '02', name: 'Abdul Muhsin Al-Qasim' },
    { id: '03', name: 'Abdurrahman as-Sudais' },
    { id: '04', name: 'Ibrahim Al-Dossari' },
    { id: '05', name: 'Misyari Rasyid Al-Afasi' },
    { id: '06', name: 'Yasser Al-Dosari' },
]

const playAudio = (url, typeId) => {
    if (currentAudio.value && playingAyat.value === typeId) {
        if (isAudioPlaying.value) {
            currentAudio.value.pause()
            isAudioPlaying.value = false
        } else {
            currentAudio.value.play()
            isAudioPlaying.value = true
        }
        return
    }

    if (currentAudio.value) {
        currentAudio.value.pause()
    }

    currentAudio.value = new Audio(url)
    playingAyat.value = typeId
    isAudioPlaying.value = true
    
    currentAudio.value.play().catch(e => {
        console.error("Audio playback failed", e)
        isAudioPlaying.value = false
        playingAyat.value = null
    })
    
    currentAudio.value.onended = () => {
        isAudioPlaying.value = false
        playingAyat.value = null
    }
    currentAudio.value.onerror = () => {
        console.error("Audio failed to load")
        isAudioPlaying.value = false
        playingAyat.value = null
    }
}

const stopAudio = () => {
    if (currentAudio.value) {
        currentAudio.value.pause()
        currentAudio.value.currentTime = 0
    }
    isAudioPlaying.value = false
    playingAyat.value = null
}

const setAudioQori = (qoriId) => {
    audioQori.value = qoriId
    if (typeof window !== 'undefined') {
        localStorage.setItem('quran_audio_qori', qoriId)
    }
    // Stop currently playing audio if Qori changes
    stopAudio()
}

onUnmounted(() => {
    stopAudio()
})

const arabicFontSizeStyle = computed(() => ({
    fontSize: `calc(${fontScale.value / 100} * clamp(1.5rem, 3.5vw + 0.5rem, 2.25rem))`,
    lineHeight: '2.4'
}))

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
        const savedScale = localStorage.getItem('quran_font_scale')
        if (savedScale && !isNaN(Number(savedScale))) {
            fontScale.value = Math.min(180, Math.max(70, Number(savedScale)))
        } else {
            // Migrasi dari format lama jika ada
            const savedFontSize = localStorage.getItem('quran_font_size')
            if (savedFontSize === 'sm') fontScale.value = 85
            else if (savedFontSize === 'normal') fontScale.value = 100
            else if (savedFontSize === 'lg') fontScale.value = 115
            else if (savedFontSize === 'xl') fontScale.value = 135
        }
        const savedOptions = localStorage.getItem('quran_show_options')
        if (savedOptions !== null) {
            showQuranOptions.value = savedOptions === 'true'
        }
        const savedQori = localStorage.getItem('quran_audio_qori')
        if (savedQori && qoriList.some(q => q.id === savedQori)) {
            audioQori.value = savedQori
        }
    }
}

const toggleQuranOptions = () => {
    showQuranOptions.value = !showQuranOptions.value
    if (typeof window !== 'undefined') {
        localStorage.setItem('quran_show_options', String(showQuranOptions.value))
    }
}

const decreaseFontSize = () => {
    fontScale.value = Math.max(70, fontScale.value - 5)
    if (typeof window !== 'undefined') {
        localStorage.setItem('quran_font_scale', String(fontScale.value))
    }
}

const increaseFontSize = () => {
    fontScale.value = Math.min(180, fontScale.value + 5)
    if (typeof window !== 'undefined') {
        localStorage.setItem('quran_font_scale', String(fontScale.value))
    }
}

const resetFontSize = () => {
    fontScale.value = 100
    if (typeof window !== 'undefined') {
        localStorage.setItem('quran_font_scale', '100')
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
    { slug: 'ibnu-majah', nama: 'Ibnu Majah', total: 4341 },
    { slug: 'nasai',    nama: "Nasa'i",    total: 5758 },
    { slug: 'ahmad',    nama: 'Ahmad',     total: 26363 },
    { slug: 'darimi',   nama: 'Darimi',    total: 3367 },
    { slug: 'malik',    nama: 'Malik',     total: 1587 },
    { slug: 'abu-dawud',  nama: 'Abu Dawud',  total: 5274 },
]

const selectedPerawi = ref('bukhari')
const currentPerawi  = computed(() => perawiList.find(p => p.slug === selectedPerawi.value))
const hadisNomor     = ref(1)
const hadisData      = ref(null)
const loadingHadis   = ref(false)
const hadisError     = ref(null)
const inputNomor     = ref('')

// ─── Parse Hadis: Ekstrak Sanad & Matan ──────────────────────────────────────
// Fungsi ini memisahkan bagian sanad dan matan dari hadis Indonesia & Arab,
// serta mengekstrak silsilah rantai perawi untuk visualisasi.
const parseSanadMatan = (text) => {
    if (!text) return { sanad: '', matan: '', rawis: [] }

    // Ekstrak nama rawi dalam kurung siku [...]
    const rawis = [...text.matchAll(/\[(.*?)\]/g)].map(m => m[1].trim()).filter(Boolean)

    // Cek apakah ada kutipan matan dengan tanda petik "..." atau “...”
    const quoteMatch = text.match(/["“]([^"”]{12,})["”]/)
    if (quoteMatch) {
        const matan = quoteMatch[1].trim()
        const beforeQuote = text.substring(0, quoteMatch.index).trim()
        const afterQuote = text.substring(quoteMatch.index + quoteMatch[0].length).trim()
        const sanad = (beforeQuote + (afterQuote ? ' ' + afterQuote : '')).trim()
        return { sanad, matan, rawis }
    }

    // Jika tidak ada tanda petik, cari pemisah kalimat sabda
    const delimiters = [
        /bahwa(?:sanya)?\s+(?:Rasulullah|Nabi)\s*(?:shallallahu\s*'alaihi\s*wasallam|ﷺ)?\s*(?:bersabda|berkata|mengatakan)\s*[:,-]?\s*/i,
        /(?:Rasulullah|Nabi)\s*(?:shallallahu\s*'alaihi\s*wasallam|ﷺ)?\s*(?:bersabda|berkata|mengatakan)\s*[:,-]?\s*/i,
        /bersabda\s*[:,-]?\s*/i,
        /bahwa(?:sanya)?\s+(?:Nabi|Rasulullah)\s*(?:shallallahu\s*'alaihi\s*wasallam|ﷺ)?\s*/i,
    ]

    for (const delim of delimiters) {
        const match = text.match(delim)
        if (match && match.index !== undefined) {
            const splitPos = match.index + match[0].length
            const sanad = text.substring(0, splitPos).trim()
            const matan = text.substring(splitPos).trim()
            if (matan.length > 5) {
                return { sanad, matan, rawis }
            }
        }
    }

    // Fallback: gunakan bracket terakhir sebagai pembatas sanad
    const bracketMatches = [...text.matchAll(/\[(.*?)\]/g)]
    if (bracketMatches.length > 0) {
        const lastBracket = bracketMatches[bracketMatches.length - 1]
        const lastIdx = lastBracket.index + lastBracket[0].length
        return {
            sanad: text.substring(0, lastIdx).trim(),
            matan: text.substring(lastIdx).trim().replace(/^[:\s\-;,]+/, ''),
            rawis
        }
    }

    return { sanad: '', matan: text, rawis }
}

// Pisahkan teks Arab hadis menjadi bagian sanad (awal) dan matan (isi sabda)
const parseArabSanadMatan = (arabText) => {
    if (!arabText) return { sanadArab: '', matanArab: '' }

    const markers = [
        'صَلَّى اللَّهُ عَلَيْهِ وَسَلَّمَ يَقُولُ',
        'صَلَّى اللَّهُ عَلَيْهِ وَسَلَّمَ يَقُولُ',
        'صَلَّى اللهُ عَلَيْهِ وَسَلَّمَ يَقُولُ',
        'صَلَّى اللَّهُ عَلَيْهِ وَسَلَّمَ قَالَ',
        'صَلَّى اللَّهُ عَلَيْهِ وَسَلَّمَ قَالَ',
        'صَلَّى اللهُ عَلَيْهِ وَسَلَّمَ قَالَ',
        'صَلَّى اللَّهُ عَلَيْهِ وَسَلَّمَ :',
        'صَلَّى اللَّهُ عَلَيْهِ وَسَلَّمَ :',
        'صَلَّى اللَّهُ عَلَيْهِ وَسَلَّمَ:',
        'صَلَّى اللَّهُ عَلَيْهِ وَسَلَّمَ:',
        'صَلَّى اللَّهُ عَلَيْهِ وَسَلَّمَ',
        'صَلَّى اللَّهُ عَلَيْهِ وَسَلَّمَ',
        'صَلَّى اللهُ عَلَيْهِ وَسَلَّمَ',
        'قَالَ رَسُولُ اللَّهِ',
        'قَالَ رَسُولُ اللَّهِ',
        'قَالَ رَسُولُ اللهِ',
        'قَالَ النَّبِيُّ',
        'قَالَ النَّبِيُّ',
        'أَنَّ رَسُولَ اللَّهِ',
        'أَنَّ رَسُولَ اللَّهِ',
        'أَنَّ النَّبِيَّ',
        'أَنَّ النَّبِيَّ'
    ]

    for (const marker of markers) {
        const idx = arabText.indexOf(marker)
        if (idx > 15) { // Sanad minimal 15 karakter di awal
            const splitPos = idx + marker.length
            return {
                sanadArab: arabText.substring(0, splitPos).trim(),
                matanArab: arabText.substring(splitPos).trim().replace(/^[:\s\-\.]+|[؛]+$/g, '')
            }
        }
    }

    return { sanadArab: '', matanArab: arabText }
}

// State untuk Hadis View
const hadisTab = ref('lengkap') // 'lengkap' | 'matan' | 'sanad' | 'syarah'
const showRawText = ref(false)
const showSanadDetail = ref(true)

// State untuk syarah dari hadis
const hadisDetailSyarah = ref(null)
const loadingSyarah = ref(false)
const syarahError = ref(null)

const fetchHadisSyarah = async () => {
    if (!hadisData.value) return
    loadingSyarah.value = true
    syarahError.value = null
    hadisDetailSyarah.value = null

    try {
        const matan = hadisData.value.matan || hadisData.value.id || ''
        const clean = matan.replace(/[\.,\/#!$%\^&\*;:{}=\-_`~()""'']/g, ' ')
        const ignored = ['bahwa', 'bahwasanya', 'adalah', 'mereka', 'kepada', 'dengan', 'untuk', 'dalam', 'beliau', 'maka', 'apabila', 'tidak', 'sesungguhnya', 'tentang', 'orang', 'dari', 'yang', 'akan', 'telah']
        const words = clean.split(/\s+/).filter(w => w.length >= 3 && !ignored.includes(w.toLowerCase()))
        const keyword = words.slice(0, 3).join(' ') || (currentPerawi.value?.nama || 'hadis')

        const res = await fetch(`https://api.myquran.com/v3/hadis/enc/cari/${encodeURIComponent(keyword)}?page=1`)
        const json = await res.json()

        if (json.status && json.data && json.data.hadis && json.data.hadis.length > 0) {
            const first = json.data.hadis[0]
            const detailRes = await fetch(`https://hadeethenc.com/api/v1/hadeeths/one/?language=id&id=${first.id}`)
            const detail = await detailRes.json()
            if (detail && detail.id) {
                hadisDetailSyarah.value = {
                    ...detail,
                    searchKeyword: keyword
                }
            } else {
                syarahError.value = `Syarah spesifik belum tersedia untuk hadis ini.`
            }
        } else {
            syarahError.value = `Tidak ditemukan referensi syarah dengan kata kunci "${keyword}".`
        }
    } catch (e) {
        syarahError.value = 'Gagal memuat syarah. Periksa koneksi internet.'
    } finally {
        loadingSyarah.value = false
    }
}

const fetchHadis = async (nomor) => {
    loadingHadis.value = true
    hadisError.value = null
    hadisData.value = null
    hadisDetailSyarah.value = null
    syarahError.value = null
    try {
        const res = await fetch(`${HADIS_API}/${selectedPerawi.value}/${nomor}`)
        const json = await res.json()
        if (json.status && json.data) {
            const rawArab = json.data.arab || ''
            const rawId = json.data.id || json.data.contents?.id || ''

            // Parse sanad & matan dari teks Indonesia
            const { sanad, matan, rawis } = parseSanadMatan(rawId)
            // Parse sanad & matan dari teks Arab
            const { sanadArab, matanArab } = parseArabSanadMatan(rawArab)

            hadisData.value = {
                ...json.data,
                arab: rawArab,
                arabRaw: rawArab,
                id: rawId,
                idRaw: rawId,
                // Hasil parsing
                sanad,
                matan: matan || rawId,
                rawis: rawis || [],
                sanadArab,
                matanArab: matanArab || rawArab,
                // Indikator apakah sanad tersedia
                hasSanad: !!(sanad || sanadArab || (rawis && rawis.length > 0)),
            }
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
            hadisSearchResults.value = json.data.hadis.map(h => ({
                ...h,
                text: h.text // Keep original text (sanad included)
            }))
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


const shareModalOpen = ref(false)
const shareData = ref({
    type: 'quran',
    title: '',
    arab: '',
    latin: '',
    translation: '',
    source: '',
    fullText: ''
})

const openShareQuran = (surah, ayat) => {
    const title = `QS. ${surah.namaLatin} : Ayat ${ayat.nomorAyat}`
    const arab = ayat.teksArab || ''
    const latin = ayat.teksLatin || ''
    const translation = ayat.teksIndonesia || ''
    const source = `Al-Qur'an Surah ${surah.namaLatin} (${surah.arti}) [${surah.nomor}:${ayat.nomorAyat}]`
    const fullText = `📖 *${title}*\n\n${arab}\n\n${latin ? '_' + latin + '_\n\n' : ''}"${translation}"\n\n📌 _Sumber: ${source}_\n_Dibagikan melalui Habit Tracker SDAM_`

    shareData.value = { type: 'quran', title, arab, latin, translation, source, fullText }
    shareModalOpen.value = true
}

const openShareHadisPerawi = (perawiName, nomor, data) => {
    const title = `Hadis Riwayat ${perawiName} No. ${nomor}`
    const matanArab = data.matanArab || data.arab || ''
    const matanId = data.matan || data.id || ''
    const sanadText = data.sanad ? `\n\n🔗 *Sanad:* ${data.sanad}` : ''
    const source = `HR. ${perawiName} (No. ${nomor})`
    const fullText = `📜 *${title}*\n\n${matanArab ? matanArab + '\n\n' : ''}"${matanId}"${sanadText}\n\n📌 _Sumber: ${source}_\n_Dibagikan melalui Habit Tracker SDAM_`

    shareData.value = {
        type: 'hadis',
        title,
        arab: matanArab,
        latin: '',
        translation: matanId,
        source,
        fullText
    }
    shareModalOpen.value = true
}

const openShareHadisSearch = (item, extra) => {
    const source = extra?.attribution || 'Ensiklopedia Hadis'
    const grade = extra?.grade ? ` (Derajat: ${extra.grade})` : ''
    const title = `Hadis: ${source}`
    const arab = extra?.hadeeth_ar || ''
    const latin = ''
    const translation = item.text || ''
    const fullText = `📜 *${title}${grade}*\n\n${arab ? arab + '\n\n' : ''}"${translation}"\n\n📌 _Sumber: ${source}_\n_Dibagikan melalui Habit Tracker SDAM_`

    shareData.value = { type: 'hadis', title: `${source}${grade}`, arab, latin, translation, source, fullText }
    shareModalOpen.value = true
}

// ─── Init ─────────────────────────────────────────────────────────────────────
onMounted(() => {
    fetchSurahList()

    // Cek URL params untuk direct link Hadis Harian
    if (typeof window !== 'undefined') {
        const urlParams = new URLSearchParams(window.location.search)
        const tab = urlParams.get('tab')
        if (tab === 'hadis') {
            activeTab.value = 'hadis'
            const perawi = urlParams.get('perawi')
            const nomor = urlParams.get('nomor')
            if (perawi) selectedPerawi.value = perawi
            if (nomor) fetchHadis(parseInt(nomor))
            else fetchHadis(1)
        } else {
            fetchHadis(1)
        }
    } else {
        fetchHadis(1)
    }

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
                            <div class="text-sm text-slate-500 mt-0.5 mb-3">{{ surahDetail.arti }} · {{ surahDetail.jumlahAyat }} Ayat · {{ surahDetail.tempatTurun }}</div>
                            
                            <!-- Tombol Putar Full Surah -->
                            <button
                                v-if="surahDetail.audioFull && surahDetail.audioFull[audioQori]"
                                type="button"
                                @click="playAudio(surahDetail.audioFull[audioQori], `surah_${surahDetail.nomor}`)"
                                :class="[
                                    'inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-sm border cursor-pointer',
                                    isAudioPlaying && playingAyat === `surah_${surahDetail.nomor}`
                                        ? 'bg-rose-50 text-rose-600 border-rose-200 hover:bg-rose-100'
                                        : 'bg-emerald-600 text-white border-emerald-600 hover:bg-emerald-700'
                                ]"
                            >
                                <svg v-if="isAudioPlaying && playingAyat === `surah_${surahDetail.nomor}`" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                                <svg v-else class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                <span>{{ isAudioPlaying && playingAyat === `surah_${surahDetail.nomor}` ? 'Jeda Murottal Surat' : 'Putar Murottal Surat' }}</span>
                            </button>
                        </div>

                        <!-- Widget Banner Sesi Tilawah Hari Ini (Multi-Sesi) -->
                        <div v-if="todaySessions && todaySessions.length > 0" class="mb-5 p-3 sm:p-4 rounded-2xl bg-gradient-to-r from-emerald-50 via-teal-50 to-emerald-50 dark:from-emerald-950/40 dark:via-teal-950/30 dark:to-emerald-950/40 border border-emerald-200/80 dark:border-emerald-800/60 shadow-xs">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-emerald-600 text-white font-bold text-xs shadow-2xs flex-shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                                        Tilawah Hari Ini
                                    </span>
                                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                                        {{ todaySessions.length }} Sesi Tercatat
                                    </span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <Link
                                        :href="route('quran.recap')"
                                        class="inline-flex items-center gap-1 text-xs font-bold text-emerald-700 dark:text-emerald-300 hover:text-emerald-800 underline transition-colors"
                                    >
                                        Buka Rekap Al-Qur'an ↗
                                    </Link>
                                </div>
                            </div>

                            <!-- Daftar Chip Sesi -->
                            <div class="mt-2.5 flex flex-wrap items-center gap-2">
                                <div
                                    v-for="(sesi, sIdx) in todaySessions"
                                    :key="sesi.id || sIdx"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium border transition-all"
                                    :class="sesi.surat_akhir 
                                        ? 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-emerald-200 dark:border-emerald-800 shadow-2xs' 
                                        : 'bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-200 border-amber-300 dark:border-amber-700 animate-pulse'"
                                >
                                    <span class="font-bold text-emerald-700 dark:text-emerald-400">S{{ sIdx + 1 }}:</span>
                                    <span>{{ sesi.surat_awal }}:{{ sesi.ayat_awal }}</span>
                                    <span class="text-slate-400">➔</span>
                                    <span v-if="sesi.surat_akhir" class="font-semibold text-emerald-800 dark:text-emerald-300">
                                        {{ sesi.surat_akhir }}:{{ sesi.ayat_akhir }} ✓
                                    </span>
                                    <span v-else class="text-amber-600 dark:text-amber-400 font-semibold italic">
                                        sedang dibaca...
                                    </span>
                                    <button
                                        type="button"
                                        @click="hapusSesiById(sesi.id)"
                                        class="ml-1 text-slate-400 hover:text-rose-600 p-0.5 rounded transition-colors"
                                        title="Hapus sesi ini dari rekap"
                                    >
                                        ✕
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Bar Pengaturan Opsi Tampilan Ayat (Toggleable - Desktop/Tablet only, di Mobile via Floating Button) -->
                        <div class="hidden sm:block p-3 sm:p-3.5 bg-emerald-50/50 dark:bg-emerald-950/20 rounded-2xl border border-emerald-100 dark:border-emerald-900/40 mb-6 transition-all duration-200 shadow-2xs">
                            <div class="flex items-center justify-between gap-3">
                                <!-- Tombol Toggle Pengaturan -->
                                <button
                                    type="button"
                                    @click="toggleQuranOptions"
                                    class="flex items-center gap-2 text-xs font-bold text-slate-700 hover:text-emerald-800 transition-colors cursor-pointer select-none py-0.5"
                                    :title="showQuranOptions ? 'Sembunyikan Pengaturan Tampilan' : 'Tampilkan Pengaturan Tampilan'"
                                >
                                    <div :class="['w-7 h-7 rounded-xl flex items-center justify-center transition-all duration-150', showQuranOptions ? 'bg-emerald-600 text-white shadow-xs' : 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200']">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                        </svg>
                                    </div>
                                    <span>Pengaturan Tampilan</span>
                                    <svg
                                        :class="['w-3.5 h-3.5 text-slate-400 transition-transform duration-200', showQuranOptions ? 'rotate-180 text-emerald-600' : 'rotate-0']"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <!-- Mini Badge Ringkasan Saat Tertutup -->
                                <div v-if="!showQuranOptions" class="hidden sm:flex items-center gap-1.5 text-xs text-slate-500 font-medium">
                                    <span class="px-2 py-0.5 rounded-md bg-white border border-slate-200 text-[10px] font-bold text-emerald-700 uppercase">Font: {{ fontScale }}%</span>
                                    <span v-if="showLatin" class="px-2 py-0.5 rounded-md bg-white border border-slate-200 text-[10px] font-semibold text-slate-600">Latin</span>
                                    <span v-if="showTranslation" class="px-2 py-0.5 rounded-md bg-white border border-slate-200 text-[10px] font-semibold text-slate-600">Terjemahan</span>
                                </div>
                            </div>

                            <!-- Panel Opsi Pengaturan (Dapat Disembunyikan/Ditampilkan) -->
                            <Transition
                                enter-active-class="transition duration-150 ease-out"
                                enter-from-class="opacity-0 -translate-y-2 scale-98"
                                enter-to-class="opacity-100 translate-y-0 scale-100"
                                leave-active-class="transition duration-100 ease-in"
                                leave-from-class="opacity-100 translate-y-0 scale-100"
                                leave-to-class="opacity-0 -translate-y-2 scale-98"
                            >
                                <div 
                                    v-if="showQuranOptions" 
                                    class="mt-2.5 pt-2 border-t border-emerald-100/80 flex items-center justify-between gap-1 sm:gap-2 w-full min-w-0 overflow-x-auto pb-0.5"
                                    style="scrollbar-width: none; -ms-overflow-style: none;"
                                >
                                    <!-- 1. Ukuran Font Arab: - A + (-5%, Reset 100%, +5%) -->
                                    <div class="inline-flex items-center rounded-lg bg-white border border-slate-200 p-0.5 text-xs shadow-2xs flex-shrink-0">
                                        <button
                                            type="button"
                                            @click="decreaseFontSize"
                                            class="w-5 sm:w-6 h-5 sm:h-6 flex items-center justify-center rounded-md font-bold text-slate-600 hover:text-emerald-700 hover:bg-emerald-50 active:scale-90 transition-all cursor-pointer select-none"
                                            title="Perkecil Ukuran Font (-5%)"
                                        >
                                            <span class="text-sm leading-none font-bold">−</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="resetFontSize"
                                            :class="[
                                                'px-1.5 sm:px-2 h-5 sm:h-6 flex items-center justify-center rounded-md text-[10px] sm:text-[11px] font-bold transition-all cursor-pointer select-none',
                                                fontScale === 100
                                                    ? 'bg-emerald-600 text-white shadow-xs'
                                                    : 'text-slate-600 hover:text-emerald-700 hover:bg-emerald-50'
                                            ]"
                                            :title="`Ukuran Font: ${fontScale}% (Klik untuk reset ke normal 100%)`"
                                        >
                                            A
                                        </button>
                                        <button
                                            type="button"
                                            @click="increaseFontSize"
                                            class="w-5 sm:w-6 h-5 sm:h-6 flex items-center justify-center rounded-md font-bold text-slate-600 hover:text-emerald-700 hover:bg-emerald-50 active:scale-90 transition-all cursor-pointer select-none"
                                            title="Perbesar Ukuran Font (+5%)"
                                        >
                                            <span class="text-sm leading-none font-bold">+</span>
                                        </button>
                                    </div>

                                    <!-- 2. Opsi Lompat ke Ayat Tertentu -->
                                    <div class="relative flex-1 min-w-[50px] sm:min-w-[60px] max-w-[80px]">
                                        <select
                                            v-model="selectedAyatJump"
                                            @change="handleJumpToAyat($event.target.value)"
                                            class="w-full bg-white border border-slate-200 text-slate-700 text-[10px] sm:text-[11px] font-bold rounded-lg py-0.5 pl-1.5 pr-4 focus:ring-2 focus:ring-emerald-500 cursor-pointer shadow-2xs appearance-none truncate"
                                        >
                                            <option value="" disabled>Ayat</option>
                                            <option
                                                v-for="a in surahDetail.ayat"
                                                :key="a.nomorAyat"
                                                :value="String(a.nomorAyat)"
                                            >
                                                {{ a.nomorAyat }}
                                            </option>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-1 text-slate-400">
                                            <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </div>
                                    </div>

                                    <!-- 2.5 Opsi Qori (Audio) -->
                                    <div class="relative flex-1 min-w-[60px] sm:min-w-[70px] max-w-[100px]">
                                        <select
                                            v-model="audioQori"
                                            @change="setAudioQori($event.target.value)"
                                            class="w-full bg-white border border-slate-200 text-slate-700 text-[10px] sm:text-[11px] font-bold rounded-lg py-0.5 pl-1.5 pr-4 focus:ring-2 focus:ring-emerald-500 cursor-pointer shadow-2xs appearance-none truncate"
                                        >
                                            <option v-for="qori in qoriList" :key="qori.id" :value="qori.id">
                                                {{ qori.name }}
                                            </option>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-1 text-slate-400">
                                            <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </div>
                                    </div>

                                    <!-- 3. Switch Transliterasi Latin -->
                                    <button 
                                        type="button" 
                                        @click="toggleLatin"
                                        :class="[
                                            'inline-flex items-center justify-center gap-1 px-1.5 sm:px-2 py-0.5 rounded-lg text-[10px] sm:text-[11px] font-bold transition-all border shadow-2xs flex-shrink-0 cursor-pointer',
                                            showLatin 
                                                ? 'bg-emerald-600 text-white border-emerald-600' 
                                                : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50'
                                        ]"
                                        title="Tampilkan / sembunyikan bacaan Latin"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :class="showLatin ? 'bg-white' : 'bg-slate-300'"></span>
                                        <span>Latin</span>
                                    </button>

                                    <!-- 4. Switch Terjemahan Bahasa Indonesia -->
                                    <button 
                                        type="button" 
                                        @click="toggleTranslation"
                                        :class="[
                                            'inline-flex items-center justify-center gap-1 px-1.5 sm:px-2 py-0.5 rounded-lg text-[10px] sm:text-[11px] font-bold transition-all border shadow-2xs flex-shrink-0 cursor-pointer',
                                            showTranslation 
                                                ? 'bg-emerald-600 text-white border-emerald-600' 
                                                : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50'
                                        ]"
                                        title="Tampilkan / sembunyikan arti terjemahan"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :class="showTranslation ? 'bg-white' : 'bg-slate-300'"></span>
                                        <span>Arti</span>
                                    </button>
                                </div>
                            </Transition>
                        </div>

                        <!-- Basmalah (kecuali At-Taubah surah 9) -->
                        <div 
                            v-if="surahDetail.nomor !== 9 && surahDetail.nomor !== 1" 
                            class="text-center font-arabic text-slate-700 mb-6 py-2"
                            :style="{ 
                                fontSize: `calc(${fontScale / 100} * clamp(1.35rem, 3vw + 0.5rem, 1.875rem))`,
                                lineHeight: '2.2'
                            }"
                        >
                            بِسْمِ اللّٰهِ الرَّحْمٰنِ الرَّحِيْمِ
                        </div>

                        <!-- Ayat-ayat -->
                        <div class="space-y-3 sm:space-y-4">
                            <div 
                                v-for="ayat in surahDetail.ayat" 
                                :key="ayat.nomorAyat" 
                                :id="'ayat-' + ayat.nomorAyat" 
                                class="relative group scroll-mt-24"
                            >
                                <div 
                                    :class="[
                                        'rounded-2xl px-3.5 sm:px-5 py-2.5 sm:py-3.5 border transition-all duration-300',
                                        targetHighlightedAyat === ayat.nomorAyat
                                            ? 'bg-emerald-100/90 border-emerald-500 ring-4 ring-emerald-400/70 shadow-md scale-[1.01]'
                                            : isAyatBookmarked(surahDetail.nomor, ayat.nomorAyat) 
                                                ? 'bg-emerald-50/80 border-emerald-400 ring-2 ring-emerald-200/70 shadow-sm' 
                                                : 'bg-slate-50/60 border-slate-100 hover:border-emerald-200 hover:bg-emerald-50/20'
                                    ]"
                                >
                                    <!-- Baris 1: Header Aksi Ayat (Kompak - Tinggi Berkurang 50%) -->
                                    <div class="flex items-center justify-between gap-2 pb-1.5 mb-2 sm:mb-2.5 border-b border-slate-100/90">
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-[11px] font-bold flex-shrink-0">
                                                {{ ayat.nomorAyat }}
                                            </div>
                                            <span class="text-xs font-semibold text-slate-400">Ayat {{ ayat.nomorAyat }}</span>
                                        </div>

                                        <div class="flex items-center gap-1.5">
                                            <!-- Tombol Tandai Baca (Mulai / Selesai) -->
                                            <button
                                                type="button"
                                                @click="openTandaiModal(surahDetail, ayat)"
                                                :title="getAyatSesiInfo(surahDetail.namaLatin, ayat.nomorAyat) ? 'Kelola tanda baca sesi ini' : 'Tandai mulai atau selesai baca'"
                                                :class="[
                                                    'inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[11px] font-semibold transition-all shadow-xs cursor-pointer',
                                                    getAyatSesiInfo(surahDetail.namaLatin, ayat.nomorAyat)?.type === 'start'
                                                        ? 'bg-emerald-600 text-white ring-1 ring-emerald-600 shadow-xs'
                                                        : getAyatSesiInfo(surahDetail.namaLatin, ayat.nomorAyat)?.type === 'end'
                                                            ? 'bg-teal-600 text-white ring-1 ring-teal-600 shadow-xs'
                                                            : getAyatSesiInfo(surahDetail.namaLatin, ayat.nomorAyat)?.type === 'both'
                                                                ? 'bg-emerald-700 text-white ring-1 ring-emerald-700 shadow-xs'
                                                                : isAyatBookmarked(surahDetail.nomor, ayat.nomorAyat)
                                                                    ? 'bg-emerald-500 text-white ring-1 ring-emerald-500'
                                                                    : 'text-slate-500 hover:text-emerald-700 hover:bg-emerald-50 bg-white border border-slate-200'
                                                ]"
                                            >
                                                <svg class="w-3 h-3" :fill="getAyatSesiInfo(surahDetail.namaLatin, ayat.nomorAyat) || isAyatBookmarked(surahDetail.nomor, ayat.nomorAyat) ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                                                </svg>
                                                <span>
                                                    <template v-if="getAyatSesiInfo(surahDetail.namaLatin, ayat.nomorAyat)?.type === 'start'">
                                                        Mulai S{{ getAyatSesiInfo(surahDetail.namaLatin, ayat.nomorAyat).sessionIndex }}
                                                    </template>
                                                    <template v-else-if="getAyatSesiInfo(surahDetail.namaLatin, ayat.nomorAyat)?.type === 'end'">
                                                        Selesai S{{ getAyatSesiInfo(surahDetail.namaLatin, ayat.nomorAyat).sessionIndex }}
                                                    </template>
                                                    <template v-else-if="getAyatSesiInfo(surahDetail.namaLatin, ayat.nomorAyat)?.type === 'both'">
                                                        S{{ getAyatSesiInfo(surahDetail.namaLatin, ayat.nomorAyat).sessionIndex }} Mulai &amp; Selesai
                                                    </template>
                                                    <template v-else-if="isAyatBookmarked(surahDetail.nomor, ayat.nomorAyat)">
                                                        Terakhir
                                                    </template>
                                                    <template v-else>
                                                        Tandai
                                                    </template>
                                                </span>
                                            </button>

                                            <!-- Tombol Putar Ayat -->
                                            <button
                                                v-if="ayat.audio && ayat.audio[audioQori]"
                                                type="button"
                                                @click="playAudio(ayat.audio[audioQori], `${surahDetail.nomor}_${ayat.nomorAyat}`)"
                                                :title="isAudioPlaying && playingAyat === `${surahDetail.nomor}_${ayat.nomorAyat}` ? 'Jeda Audio' : 'Putar Audio'"
                                                :class="[
                                                    'inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[11px] font-semibold transition-all shadow-xs cursor-pointer',
                                                    isAudioPlaying && playingAyat === `${surahDetail.nomor}_${ayat.nomorAyat}`
                                                        ? 'bg-rose-50 text-rose-600 border border-rose-200'
                                                        : 'text-slate-500 hover:text-emerald-700 hover:bg-emerald-50 bg-white border border-slate-200'
                                                ]"
                                            >
                                                <svg v-if="isAudioPlaying && playingAyat === `${surahDetail.nomor}_${ayat.nomorAyat}`" class="w-3 h-3 text-rose-600" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                                                <svg v-else class="w-3 h-3 text-emerald-600" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                                <span>{{ isAudioPlaying && playingAyat === `${surahDetail.nomor}_${ayat.nomorAyat}` ? 'Jeda' : 'Putar' }}</span>
                                            </button>

                                            <!-- Tombol Bagikan Ayat -->
                                            <button
                                                type="button"
                                                @click="openShareQuran(surahDetail, ayat)"
                                                title="Bagikan ayat ini ke media sosial"
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[11px] font-semibold text-slate-500 hover:text-emerald-700 hover:bg-emerald-50 bg-white border border-slate-200 transition-all shadow-xs cursor-pointer"
                                            >
                                                <svg class="w-3 h-3 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                                </svg>
                                                <span>Bagikan</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Baris 2: Teks Arab Ayat (Lebar Penuh dengan Ukuran Dinamis) -->
                                    <div 
                                        class="w-full text-right font-arabic leading-loose text-slate-800 mb-1 sm:mb-2 py-0.5 select-text"
                                        :style="arabicFontSizeStyle"
                                        dir="rtl"
                                    >
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

                        <!-- Grid Surah 5 kolom (desktop) / 1 kolom (mobile) x 10 per kolom -->
                        <div v-else class="grid grid-cols-1 md:grid-cols-5 gap-2 md:gap-3">
                            <div v-for="(col, colIdx) in quranColumns" :key="colIdx" class="flex flex-col gap-1.5">
                                <button
                                    v-for="surah in col"
                                    :key="surah.nomor"
                                    @click="fetchSurahDetail(surah.nomor)"
                                    class="flex items-center gap-2 p-2 md:p-2.5 rounded-xl border border-slate-100 hover:border-emerald-300 hover:bg-emerald-50/40 transition-all text-left group"
                                >
                                    <!-- Nomor -->
                                    <div class="w-7 h-7 md:w-8 md:h-8 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-xs flex items-center justify-center flex-shrink-0 group-hover:bg-emerald-100 transition-colors">
                                        {{ surah.nomor }}
                                    </div>
                                    <!-- Info -->
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs md:text-sm font-bold text-slate-800 leading-none truncate">{{ surah.namaLatin }}</div>
                                        <div class="text-[10px] text-slate-500 mt-0.5 truncate">{{ surah.arti }} · {{ surah.jumlahAyat }} ayat</div>
                                    </div>
                                    <!-- Arab -->
                                    <div class="text-base font-arabic text-emerald-700 flex-shrink-0">{{ surah.nama }}</div>
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
                            <!-- <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Pilih Kitab / Perawi</label> -->
                            
                            <!-- Mobile Dropdown Select -->
                            <div class="sm:hidden relative">
                                <select
                                    v-model="selectedPerawi"
                                    class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 shadow-xs appearance-none pr-10 cursor-pointer"
                                >
                                    <option v-for="p in perawiList" :key="p.slug" :value="p.slug">
                                        Kitab {{ p.nama }} ({{ p.total?.toLocaleString('id-ID') }} Hadis)
                                    </option>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </div>

                            <!-- Desktop / Tablet Grid Buttons -->
                            <div class="hidden sm:grid sm:grid-cols-5 md:grid-cols-9 gap-1.5 sm:gap-2">
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
                            <!-- Info & Kontrol Tampilan Hadis -->
                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-1 bg-amber-50 text-amber-800 rounded-full font-bold border border-amber-200 shadow-xs">
                                        {{ currentPerawi?.nama }} · No. {{ hadisNomor }}
                                    </span>
                                    <span class="text-slate-400">dari {{ currentPerawi?.total?.toLocaleString('id-ID') }} hadis</span>
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <!-- Toggle Mode Tampilan (Terstruktur vs Teks Asli) -->
                                    <button
                                        type="button"
                                        @click="showRawText = !showRawText"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-semibold border transition-all"
                                        :class="showRawText ? 'bg-amber-100 text-amber-900 border-amber-300' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                                        title="Beralih antara tampilan terstruktur (Matan, Sanad, Syarah) dan teks utuh orisinil"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7" />
                                        </svg>
                                        <span>{{ showRawText ? 'Mode: Teks Utuh' : 'Mode: Terstruktur' }}</span>
                                    </button>

                                    <!-- Tombol Salin Teks -->
                                    <button
                                        type="button"
                                        @click="copyHadisText((hadisData.matanArab || hadisData.arab) + '\n\n' + (hadisData.matan || hadisData.id))"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 bg-white border border-slate-200 hover:border-slate-300 text-slate-700 rounded-xl text-xs font-semibold transition-all shadow-xs"
                                        title="Salin hadis ke papan klip"
                                    >
                                        <svg v-if="!copyToast" class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10a2 2 0 01-2-2v-4a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2z" />
                                        </svg>
                                        <svg v-else class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>{{ copyToast ? 'Tersalin' : 'Salin' }}</span>
                                    </button>

                                    <!-- Tombol Bagikan -->
                                    <button
                                        type="button"
                                        @click="openShareHadisPerawi(currentPerawi?.nama, hadisNomor, hadisData)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold transition-all shadow-xs"
                                        title="Bagikan hadis ini ke media sosial"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                        </svg>
                                        <span>Bagikan</span>
                                    </button>
                                </div>
                            </div>

                            <!-- ───────────────────────────────────────────── -->
                            <!-- TAMPILAN 1: MODE TERSTRUKTUR (MATAN, SANAD, SYARAH) -->
                            <!-- ───────────────────────────────────────────── -->
                            <div v-if="!showRawText" class="space-y-4">
                                <!-- 1. SEKSI MATAN HADIS (Isi Sabda / Pokok Riwayat) -->
                                <div class="bg-gradient-to-br from-amber-50/90 via-orange-50/50 to-white border-2 border-amber-200/80 rounded-2xl p-4 sm:p-6 shadow-sm space-y-4">
                                    <!-- Header Matan -->
                                    <div class="flex items-center justify-between pb-3 border-b border-amber-200/60">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-600 text-white text-xs font-bold rounded-lg shadow-xs uppercase tracking-wider">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                                </svg>
                                                Matan Hadis
                                            </span>
                                            <span class="text-xs text-amber-800 font-medium hidden sm:inline">Inti Sabda & Riwayat</span>
                                        </div>
                                        <span class="text-[11px] font-semibold text-amber-700 bg-amber-100/70 px-2 py-0.5 rounded-md">
                                            Isi Pokok
                                        </span>
                                    </div>

                                    <!-- Teks Arab Matan -->
                                    <div class="text-right" dir="rtl">
                                        <p class="text-xl sm:text-2xl font-arabic leading-loose text-slate-900 tracking-wide">
                                            {{ hadisData.matanArab || hadisData.arab }}
                                        </p>
                                    </div>

                                    <!-- Divider Halus -->
                                    <div class="relative py-1">
                                        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-amber-200/70"></div></div>
                                        <div class="relative flex justify-center">
                                            <span class="bg-gradient-to-r from-amber-50 to-orange-50 px-3 text-[11px] text-amber-700 font-bold uppercase tracking-wider">
                                                Terjemahan Matan
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Terjemahan Bahasa Indonesia Matan -->
                                    <div class="bg-white/80 rounded-xl p-3.5 sm:p-4 border border-amber-100 shadow-xs">
                                        <p class="text-sm sm:text-base text-slate-800 font-normal leading-relaxed">
                                            "{{ hadisData.matan || hadisData.id }}"
                                        </p>
                                    </div>
                                </div>

                                <!-- 2. SEKSI SANAD LENGKAP (Jalur Periwayatan & Rantai Rawi) -->
                                <div class="bg-slate-50/90 border border-slate-200 rounded-2xl p-4 sm:p-5 shadow-xs space-y-3.5">
                                    <!-- Header Sanad -->
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-700 text-white text-xs font-bold rounded-lg shadow-xs uppercase tracking-wider">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                                </svg>
                                                Sanad Lengkap
                                            </span>
                                            <span v-if="hadisData.rawis?.length" class="text-xs text-slate-500 font-medium">
                                                {{ hadisData.rawis.length }} Jalur Rawi
                                            </span>
                                        </div>

                                        <button
                                            type="button"
                                            @click="showSanadDetail = !showSanadDetail"
                                            class="text-xs font-bold text-slate-600 hover:text-slate-900 flex items-center gap-1 transition-colors"
                                        >
                                            <span>{{ showSanadDetail ? 'Sembunyikan' : 'Buka Detail' }}</span>
                                            <svg class="w-3.5 h-3.5 transform transition-transform" :class="showSanadDetail ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                    </div>

                                    <div v-show="showSanadDetail" class="space-y-3 pt-1">
                                        <!-- Visual Silsilah Rantai Rawi -->
                                        <div v-if="hadisData.rawis && hadisData.rawis.length > 0" class="bg-white rounded-xl p-3 border border-slate-200/80 space-y-2">
                                            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                                                <svg class="w-3 h-3 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                                </svg>
                                                Silsilah Rantai Perawi (Jalur Transmisi):
                                            </div>
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <template v-for="(rawi, rIdx) in hadisData.rawis" :key="rIdx">
                                                    <div class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50/80 hover:bg-blue-100 text-blue-900 border border-blue-200 rounded-lg text-xs font-medium transition-colors">
                                                        <span class="w-4 h-4 rounded-full bg-blue-200/80 text-[10px] text-blue-800 font-bold flex items-center justify-center shrink-0">
                                                            {{ rIdx + 1 }}
                                                        </span>
                                                        <span>{{ rawi }}</span>
                                                    </div>
                                                    <span class="text-slate-400 font-bold text-xs">→</span>
                                                </template>
                                                <div class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 text-emerald-800 border border-emerald-300 rounded-lg text-xs font-bold shadow-2xs">
                                                    <span>Rasulullah ﷺ</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Sanad Teks Arab (Jika terdeteksi) -->
                                        <div v-if="hadisData.sanadArab" class="bg-white/60 rounded-xl p-3 border border-slate-200/70 text-right space-y-1" dir="rtl">
                                            <div class="text-[10px] font-bold text-slate-400 tracking-wider text-left uppercase" dir="ltr">Sanad Arab:</div>
                                            <p class="text-base sm:text-lg font-arabic leading-relaxed text-slate-700">
                                                {{ hadisData.sanadArab }}
                                            </p>
                                        </div>

                                        <!-- Sanad Teks Terjemahan Indonesia -->
                                        <div v-if="hadisData.sanad" class="bg-white/60 rounded-xl p-3 border border-slate-200/70 space-y-1">
                                            <div class="text-[10px] font-bold text-slate-400 tracking-wider uppercase">Terjemahan Sanad:</div>
                                            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed italic">
                                                {{ hadisData.sanad }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. SEKSI SYARAH & PENJELASAN HADIS (Fitur Unggulan) -->
                                <div class="bg-gradient-to-br from-indigo-50/70 via-purple-50/40 to-amber-50/30 border border-indigo-100 rounded-2xl p-4 sm:p-5 shadow-xs space-y-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-indigo-600 text-white text-xs font-bold rounded-lg shadow-xs uppercase tracking-wider">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                                </svg>
                                                Syarah & Penjelasan
                                            </span>
                                            <span class="text-xs text-indigo-900/70 font-medium hidden sm:inline">Makna & Faedah Hadis</span>
                                        </div>

                                        <button
                                            v-if="!hadisDetailSyarah && !loadingSyarah"
                                            type="button"
                                            @click="fetchHadisSyarah"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-all shadow-xs"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                            <span>Buka Syarah</span>
                                        </button>
                                    </div>

                                    <!-- Loading Syarah -->
                                    <div v-if="loadingSyarah" class="flex items-center justify-center py-6 gap-2 text-indigo-700 text-xs font-semibold">
                                        <svg class="w-4 h-4 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                        <span>Menelusuri syarah dan faedah hadis dari ensiklopedia...</span>
                                    </div>

                                    <!-- Konten Syarah Bila Berhasil Dimuat -->
                                    <div v-else-if="hadisDetailSyarah" class="space-y-3 pt-1">
                                        <!-- Sumber & Derajat -->
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span v-if="hadisDetailSyarah.attribution" class="px-2.5 py-1 bg-white border border-indigo-200 text-indigo-900 rounded-lg text-xs font-semibold shadow-2xs">
                                                Sumber: {{ hadisDetailSyarah.attribution }}
                                            </span>
                                            <span v-if="hadisDetailSyarah.grade" class="px-2.5 py-1 bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-lg text-xs font-bold">
                                                Derajat: {{ hadisDetailSyarah.grade }}
                                            </span>
                                        </div>

                                        <!-- Penjelasan Makna Hadis -->
                                        <div v-if="hadisDetailSyarah.explanation" class="bg-white rounded-xl p-3.5 sm:p-4 border border-indigo-100/80 space-y-1.5 shadow-2xs">
                                            <div class="text-xs font-bold text-indigo-950 flex items-center gap-1.5">
                                                <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>Penjelasan / Makna Hadis:</span>
                                            </div>
                                            <p class="text-xs sm:text-sm text-slate-700 leading-relaxed whitespace-pre-line">
                                                {{ hadisDetailSyarah.explanation }}
                                            </p>
                                        </div>

                                        <!-- Faedah / Pelajaran dari Hadis (Jika ada hints) -->
                                        <div v-if="hadisDetailSyarah.hints && hadisDetailSyarah.hints.length > 0" class="bg-amber-50/50 rounded-xl p-3.5 sm:p-4 border border-amber-200/60 space-y-2">
                                            <div class="text-xs font-bold text-amber-900 flex items-center gap-1.5">
                                                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138z" />
                                                </svg>
                                                <span>Faedah & Pelajaran Hadis:</span>
                                            </div>
                                            <ul class="text-xs sm:text-sm text-slate-700 space-y-1.5 list-disc list-inside">
                                                <li v-for="(hint, hIdx) in hadisDetailSyarah.hints" :key="hIdx" class="leading-relaxed">
                                                    {{ hint }}
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Pesan Bila Belum Dibuka / Error -->
                                    <div v-else-if="syarahError" class="bg-white/80 rounded-xl p-3 border border-indigo-100 flex items-center justify-between gap-2">
                                        <span class="text-xs text-slate-500">{{ syarahError }}</span>
                                        <button
                                            type="button"
                                            @click="hadisSearchKeyword = hadisData.matan ? hadisData.matan.split(' ').slice(0, 3).join(' ') : 'niat'; searchHadis(1)"
                                            class="text-xs font-bold text-amber-700 hover:text-amber-800 underline shrink-0"
                                        >
                                            Cari di Ensiklopedia →
                                        </button>
                                    </div>

                                    <!-- Prompt Awal Jika Belum Dimuat -->
                                    <div v-else class="text-xs text-slate-500 flex items-center justify-between pt-1">
                                        <span>Dapatkan penjelasan makna hadis, kandungan fiqih, serta faedah mutiara hadis.</span>
                                        <button
                                            type="button"
                                            @click="fetchHadisSyarah"
                                            class="font-bold text-indigo-700 hover:text-indigo-800 underline shrink-0 ml-2"
                                        >
                                            Muat Penjelasan
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- ───────────────────────────────────────────── -->
                            <!-- TAMPILAN 2: MODE TEKS UTUH (ORISINIL DARI KITAB) -->
                            <!-- ───────────────────────────────────────────── -->
                            <div v-else class="bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-200 rounded-2xl p-4 sm:p-6 space-y-4 shadow-sm">
                                <div class="flex items-center justify-between pb-2 border-b border-amber-200/60">
                                    <span class="text-xs font-bold text-amber-900 uppercase tracking-wider">Teks Asli Lengkap (Sanad + Matan)</span>
                                    <span class="text-[11px] text-slate-500">Kitab {{ currentPerawi?.nama }}</span>
                                </div>

                                <!-- Teks Arab Utuh -->
                                <div class="text-right" dir="rtl">
                                    <p class="text-xl sm:text-2xl font-arabic leading-loose text-slate-800">
                                        {{ hadisData.arab }}
                                    </p>
                                </div>

                                <!-- Divider -->
                                <div class="border-t border-amber-200"></div>

                                <!-- Terjemahan Utuh -->
                                <div class="text-sm sm:text-base text-slate-700 leading-relaxed">
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
                                    aria-label="Tutup modal detail hadis"
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
                                    <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 leading-relaxed whitespace-pre-line">
                                        {{ hadisDetailExtra[selectedSearchHadis.id].explanation }}
                                    </p>
                                </div>

                                <!-- Faedah & Pelajaran Hadis (Jika Ada) -->
                                <div v-if="hadisDetailExtra[selectedSearchHadis.id]?.hints?.length" class="bg-emerald-50/40 p-4 rounded-2xl border border-emerald-100 space-y-2">
                                    <div class="text-xs font-bold text-emerald-900 flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138z" />
                                        </svg>
                                        <span>Faedah & Hikmah Hadis:</span>
                                    </div>
                                    <ul class="text-xs sm:text-sm text-slate-700 dark:text-slate-300 space-y-1 list-disc list-inside">
                                        <li v-for="(hint, hIdx) in hadisDetailExtra[selectedSearchHadis.id].hints" :key="hIdx" class="leading-relaxed">
                                            {{ hint }}
                                        </li>
                                    </ul>
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
            <!-- Share Modal component -->
            <ShareImageModal v-model="shareModalOpen" :shareData="shareData" />

            <!-- ══════════════════════════════════════════════════════ -->
            <!-- Floating Action Button for Mobile Quran Settings -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 scale-75 translate-y-4"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 scale-100 translate-y-0"
                leave-to-class="opacity-0 scale-75 translate-y-4"
            >
                <button
                    v-if="surahDetail && activeTab === 'quran'"
                    @click="showMobileQuranSettings = true"
                    type="button"
                    class="sm:hidden fixed bottom-6 right-6 z-40 bg-emerald-600 hover:bg-emerald-700 active:scale-90 text-white p-3.5 rounded-full shadow-[0_8px_30px_rgb(0,0,0,0.18)] hover:shadow-[0_8px_30px_rgba(52,211,153,0.3)] transition-all hover:-translate-y-1 flex items-center justify-center focus:outline-none focus:ring-4 focus:ring-emerald-400/40 cursor-pointer"
                    title="Pengaturan Tampilan Al-Qur'an"
                    aria-label="Pengaturan Tampilan Al-Qur'an"
                >
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                </button>
            </Transition>

            <!-- ══════════════════════════════════════════════════════ -->
            <!-- Mobile Quran Settings Off-Canvas / Bottom Sheet Modal -->
            <Transition
                enter-active-class="transition-opacity ease-out duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div 
                    v-if="showMobileQuranSettings && surahDetail" 
                    class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
                >
                    <!-- Backdrop -->
                    <div 
                        class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" 
                        @click="showMobileQuranSettings = false"
                    />

                    <!-- Panel Bottom Sheet -->
                    <div 
                        class="relative z-10 w-full max-w-md bg-sidebar text-slate-800 dark:text-slate-100 rounded-t-3xl sm:rounded-3xl shadow-2xl border-t sm:border border-theme overflow-hidden flex flex-col max-h-[85vh] transition-all transform animate-in slide-in-from-bottom duration-300"
                    >
                        <!-- Drag handle indicator for mobile bottom sheet -->
                        <div class="sm:hidden pt-2.5 pb-1 flex justify-center">
                            <div class="w-12 h-1.5 rounded-full bg-slate-300 dark:bg-slate-600" />
                        </div>

                        <!-- Header Modal -->
                        <div class="flex items-center justify-between px-5 py-3.5 border-b border-subtle bg-card-subtle">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center shadow-xs">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100">Pengaturan Tampilan</h3>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">QS. {{ surahDetail.namaLatin }} ({{ surahDetail.jumlahAyat }} Ayat)</p>
                                </div>
                            </div>
                            <button 
                                type="button"
                                @click="showMobileQuranSettings = false" 
                                class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700/60 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-500 hover:text-slate-700 dark:text-slate-300 flex items-center justify-center transition-colors cursor-pointer"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Isi Pengaturan (Scrollable) -->
                        <div class="p-5 space-y-4.5 overflow-y-auto max-h-[calc(85vh-140px)]">
                            <!-- 1. Ukuran Font Arab -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="text-xs font-bold text-slate-700 dark:text-slate-200">
                                        Ukuran Tulisan Arab
                                    </label>
                                    <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 font-bold text-[11px]">
                                        {{ fontScale }}%
                                    </span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="decreaseFontSize"
                                        class="flex-1 py-2 px-3 rounded-xl bg-slate-100 dark:bg-slate-700/60 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 text-slate-700 dark:text-slate-200 font-bold text-base flex items-center justify-center active:scale-95 transition-all cursor-pointer border border-transparent hover:border-emerald-200"
                                        title="Kecilkan Font (-5%)"
                                    >
                                        −
                                    </button>
                                    <button
                                        type="button"
                                        @click="resetFontSize"
                                        :class="[
                                            'px-4 py-2 rounded-xl text-xs font-bold transition-all active:scale-95 cursor-pointer border',
                                            fontScale === 100 
                                                ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' 
                                                : 'bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-200 border-transparent hover:bg-emerald-50'
                                        ]"
                                        title="Reset Normal (100%)"
                                    >
                                        Normal (100%)
                                    </button>
                                    <button
                                        type="button"
                                        @click="increaseFontSize"
                                        class="flex-1 py-2 px-3 rounded-xl bg-slate-100 dark:bg-slate-700/60 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 text-slate-700 dark:text-slate-200 font-bold text-base flex items-center justify-center active:scale-95 transition-all cursor-pointer border border-transparent hover:border-emerald-200"
                                        title="Besarkan Font (+5%)"
                                    >
                                        +
                                    </button>
                                </div>

                                <!-- Live preview teks arab -->
                                <div class="p-3 bg-card-subtle rounded-xl border border-subtle text-center overflow-hidden">
                                    <p 
                                        class="font-arabic text-slate-800 dark:text-slate-100 leading-relaxed truncate transition-all duration-150"
                                        :style="{ fontSize: `calc(${fontScale / 100} * 1.35rem)` }"
                                    >
                                        بِسْمِ اللّٰهِ الرَّحْمٰنِ الرَّحِيْمِ
                                    </p>
                                </div>
                            </div>

                            <!-- 2. Lompat Cepat ke Ayat -->
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-200">Lompat ke Ayat</label>
                                <div class="relative">
                                    <select
                                        v-model="selectedAyatJump"
                                        @change="jumpAndCloseMobileSettings($event.target.value)"
                                        class="w-full bg-slate-50 dark:bg-slate-700/40 border border-theme text-slate-800 dark:text-slate-100 text-xs font-semibold rounded-xl py-2.5 pl-3.5 pr-8 focus:ring-2 focus:ring-emerald-500 cursor-pointer shadow-xs appearance-none"
                                    >
                                        <option value="" disabled>Pilih Nomor Ayat (1 - {{ surahDetail.jumlahAyat }})</option>
                                        <option
                                            v-for="a in surahDetail.ayat"
                                            :key="a.nomorAyat"
                                            :value="String(a.nomorAyat)"
                                        >
                                            Ayat {{ a.nomorAyat }}
                                        </option>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. Pilih Qori Audio Murottal -->
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-200">Suara Qori (Audio Ayat)</label>
                                <div class="relative">
                                    <select
                                        v-model="audioQori"
                                        @change="setAudioQori($event.target.value)"
                                        class="w-full bg-slate-50 dark:bg-slate-700/40 border border-theme text-slate-800 dark:text-slate-100 text-xs font-semibold rounded-xl py-2.5 pl-3.5 pr-8 focus:ring-2 focus:ring-emerald-500 cursor-pointer shadow-xs appearance-none"
                                    >
                                        <option v-for="qori in qoriList" :key="qori.id" :value="qori.id">
                                            {{ qori.name }}
                                        </option>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- 4. Toggle Opsi Teks (Latin & Terjemahan) -->
                            <div class="pt-1 space-y-2.5 border-t border-subtle">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-200 block">Teks Tambahan</label>
                                
                                <!-- Toggle Transliterasi Latin -->
                                <div 
                                    @click="toggleLatin"
                                    class="flex items-center justify-between p-3 rounded-xl bg-card-subtle border border-theme/60 cursor-pointer select-none active:scale-[0.99] transition-all"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-lg bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 flex items-center justify-center text-xs font-bold">
                                            Aa
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Teks Latin</p>
                                            <p class="text-[10px] text-slate-500 dark:text-slate-400">Transliterasi pengucapan bahasa Arab</p>
                                        </div>
                                    </div>
                                    <div 
                                        :class="[
                                            'w-10 h-6 rounded-full transition-colors relative flex items-center px-0.5',
                                            showLatin ? 'bg-emerald-600' : 'bg-slate-300 dark:bg-slate-600'
                                        ]"
                                    >
                                        <div 
                                            :class="[
                                                'w-5 h-5 rounded-full bg-white shadow-xs transition-transform transform',
                                                showLatin ? 'translate-x-4' : 'translate-x-0'
                                            ]"
                                        />
                                    </div>
                                </div>

                                <!-- Toggle Terjemahan Bahasa Indonesia -->
                                <div 
                                    @click="toggleTranslation"
                                    class="flex items-center justify-between p-3 rounded-xl bg-card-subtle border border-theme/60 cursor-pointer select-none active:scale-[0.99] transition-all"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-lg bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 flex items-center justify-center text-xs font-bold">
                                            ID
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Terjemahan Arti</p>
                                            <p class="text-[10px] text-slate-500 dark:text-slate-400">Terjemahan Kemenag bahasa Indonesia</p>
                                        </div>
                                    </div>
                                    <div 
                                        :class="[
                                            'w-10 h-6 rounded-full transition-colors relative flex items-center px-0.5',
                                            showTranslation ? 'bg-emerald-600' : 'bg-slate-300 dark:bg-slate-600'
                                        ]"
                                    >
                                        <div 
                                            :class="[
                                                'w-5 h-5 rounded-full bg-white shadow-xs transition-transform transform',
                                                showTranslation ? 'translate-x-4' : 'translate-x-0'
                                            ]"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Action -->
                        <div class="p-4 border-t border-subtle bg-card-subtle">
                            <button
                                type="button"
                                @click="showMobileQuranSettings = false"
                                class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs tracking-wide shadow-sm active:scale-98 transition-all cursor-pointer"
                            >
                                Terapkan &amp; Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Modal Tandai Baca Al-Qur'an (Mulai / Selesai) -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-if="isBookmarkModalOpen && selectedAyatForBookmark"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
                    @click.self="isBookmarkModalOpen = false"
                >
                    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-100 dark:border-slate-700 w-full max-w-md overflow-hidden">
                        <!-- Header Modal -->
                        <div class="px-5 py-4 bg-gradient-to-r from-emerald-600 to-teal-700 text-white flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center font-bold text-sm">
                                    🔖
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold">Tandai Bacaan Al-Qur'an</h3>
                                    <p class="text-[11px] text-emerald-100">
                                        {{ selectedAyatForBookmark.surahNamaLatin }} : Ayat {{ selectedAyatForBookmark.ayatNomor }}
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="isBookmarkModalOpen = false"
                                class="text-white/80 hover:text-white p-1 rounded-lg hover:bg-white/10 transition-colors"
                            >
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <!-- Body Modal -->
                        <div class="p-5 space-y-4">
                            <!-- Info Status Sesi Hari Ini -->
                            <div v-if="todaySessions.length > 0" class="p-3 rounded-xl bg-slate-50 dark:bg-slate-700/50 border border-slate-100 dark:border-slate-700 text-xs text-slate-600 dark:text-slate-300">
                                <div class="font-bold text-slate-700 dark:text-slate-200 mb-1.5 flex items-center justify-between">
                                    <span>📅 Tilawah Hari Ini ({{ todaySessions.length }} Sesi)</span>
                                </div>
                                <div class="space-y-1 max-h-28 overflow-y-auto pr-1">
                                    <div v-for="(s, idx) in todaySessions" :key="s.id || idx" class="flex items-center justify-between text-[11px] py-1 border-b border-slate-100 dark:border-slate-700/60 last:border-none">
                                        <span><b>Sesi {{ idx + 1 }}:</b> {{ s.surat_awal }}:{{ s.ayat_awal }} ➔ {{ s.surat_akhir ? `${s.surat_akhir}:${s.ayat_akhir}` : '(Sedang Berjalan)' }}</span>
                                        <button
                                            type="button"
                                            @click="hapusSesiById(s.id)"
                                            class="text-rose-500 hover:text-rose-700 text-[10px] ml-2 px-1 py-0.5 rounded hover:bg-rose-50 dark:hover:bg-rose-950/40"
                                            title="Hapus sesi ini dari rekap"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Tombol Aksi 1: Tandai Mulai -->
                            <div class="p-3.5 rounded-xl border border-emerald-200 dark:border-emerald-800/80 bg-emerald-50/50 dark:bg-emerald-950/30">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="flex items-center gap-1.5 font-bold text-xs text-emerald-800 dark:text-emerald-300 mb-0.5">
                                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                            Tandai Mulai Baca (Surat Awal)
                                        </div>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                            Jadikan <b class="text-slate-700 dark:text-slate-200">{{ selectedAyatForBookmark.surahNamaLatin }} ayat {{ selectedAyatForBookmark.ayatNomor }}</b> sebagai titik awal tilawah.
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center gap-2">
                                    <button
                                        type="button"
                                        :disabled="isSavingBookmark"
                                        @click="submitTandai('mulai', false)"
                                        class="flex-1 py-2 px-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition-all active:scale-98 disabled:opacity-50 cursor-pointer flex items-center justify-center gap-1.5"
                                    >
                                        <span>🟢 Tandai Mulai</span>
                                    </button>
                                    <button
                                        v-if="todaySessions.length > 0"
                                        type="button"
                                        :disabled="isSavingBookmark"
                                        @click="submitTandai('mulai', true)"
                                        class="py-2 px-2.5 rounded-lg bg-white dark:bg-slate-700 border border-emerald-300 dark:border-emerald-700 hover:bg-emerald-50 text-emerald-700 dark:text-emerald-300 font-semibold text-[11px] shadow-xs transition-all active:scale-98 disabled:opacity-50 cursor-pointer"
                                        title="Buka sesi tilawah baru (misal membaca lagi di waktu berikutnya)"
                                    >
                                        + Sesi Baru
                                    </button>
                                </div>
                            </div>

                            <!-- Tombol Aksi 2: Tandai Selesai -->
                            <div class="p-3.5 rounded-xl border border-teal-200 dark:border-teal-800/80 bg-teal-50/50 dark:bg-teal-950/30">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="flex items-center gap-1.5 font-bold text-xs text-teal-800 dark:text-teal-300 mb-0.5">
                                            <span class="w-2 h-2 rounded-full bg-teal-500"></span>
                                            Tandai Selesai Baca (Surat Akhir)
                                        </div>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                            Simpan <b class="text-slate-700 dark:text-slate-200">{{ selectedAyatForBookmark.surahNamaLatin }} ayat {{ selectedAyatForBookmark.ayatNomor }}</b> sebagai titik akhir tilawah &amp; masukkan ke Rekap Al-Qur'an.
                                        </p>
                                    </div>
                                </div>

                                <!-- Input Durasi Bacaan -->
                                <div class="mt-2.5 p-2 rounded-lg bg-teal-100/60 dark:bg-teal-900/40 border border-teal-200/80 dark:border-teal-800 flex items-center justify-between gap-2">
                                    <div class="text-[11px] font-medium text-teal-900 dark:text-teal-200">
                                        ⏱️ Durasi Membaca:
                                        <span v-if="activeSession?.jam_mulai" class="text-[10px] text-teal-700 dark:text-teal-400 block font-normal">
                                            Mulai jam {{ activeSession.jam_mulai }} (terhitung otomatis)
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <input 
                                            type="number" 
                                            min="1" 
                                            v-model="inputDurasiMenit" 
                                            placeholder="Auto" 
                                            class="w-16 py-1 px-2 text-center text-xs font-bold rounded-md border border-teal-300 dark:border-teal-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:ring-1 focus:ring-teal-500"
                                        />
                                        <span class="text-[11px] font-semibold text-teal-800 dark:text-teal-300">menit</span>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <button
                                        type="button"
                                        :disabled="isSavingBookmark"
                                        @click="submitTandai('selesai', false)"
                                        class="w-full py-2 px-3 rounded-lg bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs shadow-xs transition-all active:scale-98 disabled:opacity-50 cursor-pointer flex items-center justify-center gap-1.5"
                                    >
                                        <span>🏁 Tandai Selesai</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Modal -->
                        <div class="px-5 py-3 bg-slate-50 dark:bg-slate-800/80 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
                            <Link
                                :href="route('quran.recap')"
                                class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 flex items-center gap-1 underline"
                            >
                                Buka Rekap Al-Qur'an ↗
                            </Link>
                            <button
                                type="button"
                                @click="isBookmarkModalOpen = false"
                                class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 cursor-pointer"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Toast Notifikasi Penanda Al-Qur'an -->
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-3 scale-95"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0 scale-100"
                leave-to-class="opacity-0 translate-y-3 scale-95"
            >
                <div
                    v-if="bookmarkToast.show"
                    class="fixed bottom-6 right-6 z-50 max-w-sm p-4 rounded-xl shadow-xl flex items-center gap-3 border text-xs font-medium"
                    :class="bookmarkToast.type === 'error' ? 'bg-rose-600 text-white border-rose-700' : 'bg-emerald-600 text-white border-emerald-700'"
                >
                    <span class="text-base flex-shrink-0">{{ bookmarkToast.type === 'error' ? '⚠️' : '✅' }}</span>
                    <div class="flex-1 leading-relaxed">{{ bookmarkToast.message }}</div>
                    <button type="button" @click="bookmarkToast.show = false" class="text-white/80 hover:text-white font-bold ml-1">✕</button>
                </div>
            </Transition>

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
    font-family: 'Scheherazade New', 'Amiri Quran', 'Amiri', 'Traditional Arabic', serif;
    line-height: 2.2;
}
</style>
