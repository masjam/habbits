import { ref, onMounted, onUnmounted } from 'vue'

// ─── Singleton Reactive State (Shared across all consumers) ─────────────────
const prayerTimes = ref(null)
const hijriDate = ref(null)
const isLoading = ref(true)
const error = ref(null)
const locationName = ref('Jakarta, Indonesia')

const nextPrayerName = ref('')
const countdownText = ref('')

const dailyHadith = ref(null)
const isLoadingHadith = ref(false)

let fetchPrayerPromise = null
let fetchHadithPromise = null
let timerInterval = null
let activeComponents = 0

const calculateCountdown = () => {
    if (!prayerTimes.value) return

    const now = new Date()
    let nextPrayer = null

    const prayers = [
        { name: 'Subuh', time: prayerTimes.value.Subuh },
        { name: 'Dzuhur', time: prayerTimes.value.Dzuhur },
        { name: 'Ashar', time: prayerTimes.value.Ashar },
        { name: 'Maghrib', time: prayerTimes.value.Maghrib },
        { name: 'Isya', time: prayerTimes.value.Isya }
    ]

    let nextPrayerDate = new Date(now.getTime())
    for (const p of prayers) {
        if (!p.time) continue
        const timeStr = p.time.split(' ')[0]
        const [h, m] = timeStr.split(':').map(Number)
        const pDate = new Date(now.getFullYear(), now.getMonth(), now.getDate(), h, m, 0)
        if (pDate > now) {
            nextPrayer = p
            nextPrayerDate = pDate
            break
        }
    }

    if (!nextPrayer && prayers[0]?.time) {
        nextPrayer = prayers[0]
        const timeStr = nextPrayer.time.split(' ')[0]
        const [h, m] = timeStr.split(':').map(Number)
        nextPrayerDate = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, h, m, 0)
    }

    if (nextPrayer) {
        nextPrayerName.value = nextPrayer.name
        const diff = nextPrayerDate - now // in ms
        const h = Math.floor(diff / (1000 * 60 * 60))
        const m = Math.floor((diff / (1000 * 60)) % 60)
        const s = Math.floor((diff / 1000) % 60)
        countdownText.value = `-${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
    }
}

const startCountdownTimer = () => {
    if (!timerInterval) {
        calculateCountdown()
        timerInterval = setInterval(calculateCountdown, 1000)
    }
}

const stopCountdownTimer = () => {
    if (timerInterval && activeComponents <= 0) {
        clearInterval(timerInterval)
        timerInterval = null
    }
}

const fetchDailyHadith = async () => {
    if (dailyHadith.value) return
    isLoadingHadith.value = true

    const perawiList = [
        { slug: 'bukhari', nama: 'Bukhari', total: 6638 },
        { slug: 'muslim', nama: 'Muslim', total: 3033 },
        { slug: 'tirmidzi', nama: 'Tirmidzi', total: 3956 },
        { slug: 'ibnu-majah', nama: 'Ibnu Majah', total: 4341 },
        { slug: 'nasai', nama: "Nasa'i", total: 5758 },
        { slug: 'ahmad', nama: 'Ahmad', total: 26363 },
        { slug: 'darimi', nama: 'Darimi', total: 3367 },
        { slug: 'malik', nama: 'Malik', total: 1587 },
        { slug: 'abu-dawud', nama: 'Abu Dawud', total: 5274 },
    ]

    const today = new Date()
    const msPerDay = 86400000
    const dayIndex = Math.floor((today.getTime() - today.getTimezoneOffset() * 60000) / msPerDay)
    const cacheKey = `hadith_daily_v2_${dayIndex}`

    try {
        const cached = localStorage.getItem(cacheKey)
        if (cached) {
            const parsed = JSON.parse(cached)
            if (parsed && parsed.text) {
                dailyHadith.value = parsed
                isLoadingHadith.value = false
                return
            }
        }
    } catch (e) {
        // ignore
    }

    const perawiIndex = dayIndex % perawiList.length
    const selectedPerawi = perawiList[perawiIndex]
    const nomor = (Math.floor(dayIndex / perawiList.length) % selectedPerawi.total) + 1

    try {
        const controller = new AbortController()
        const timeoutId = setTimeout(() => controller.abort(), 6000)

        const res = await fetch(`https://api.myquran.com/v2/hadits/${selectedPerawi.slug}/${nomor}`, {
            signal: controller.signal
        })
        clearTimeout(timeoutId)

        const json = await res.json()
        if (json.status && json.data) {
            const hadithText = json.data.id || json.data.contents?.id || ''
            const perawiName = json.info?.perawi?.name || selectedPerawi.nama
            const hadithNumber = json.data.number || nomor

            const result = {
                text: hadithText,
                source: `HR. ${perawiName} No. ${hadithNumber}`,
                url: `/quran-hadis?tab=hadis&perawi=${selectedPerawi.slug}&nomor=${hadithNumber}`
            }

            dailyHadith.value = result

            try {
                localStorage.setItem(cacheKey, JSON.stringify(result))
            } catch (e) {}
        } else {
            throw new Error("Invalid response")
        }
    } catch (e) {
        dailyHadith.value = {
            text: "Barangsiapa menempuh jalan untuk menuntut ilmu, maka Allah akan mudahkan baginya jalan menuju surga.",
            source: "HR. Muslim No. 2699",
            url: `/quran-hadis?tab=hadis&perawi=muslim&nomor=2699`
        }
    } finally {
        isLoadingHadith.value = false
    }
}

const fetchPrayerTimes = async () => {
    if (prayerTimes.value) {
        isLoading.value = false
        startCountdownTimer()
        return
    }

    isLoading.value = true
    error.value = null

    const date = new Date()
    const dd = String(date.getDate()).padStart(2, '0')
    const mm = String(date.getMonth() + 1).padStart(2, '0')
    const yyyy = date.getFullYear()
    const dateStr = `${dd}-${mm}-${yyyy}`
    const cacheKey = `islamic_prayer_cache_${dateStr}`

    // 1. Coba baca cache harian dari localStorage
    try {
        const cached = localStorage.getItem(cacheKey)
        if (cached) {
            const parsed = JSON.parse(cached)
            if (parsed && parsed.prayerTimes) {
                prayerTimes.value = parsed.prayerTimes
                hijriDate.value = parsed.hijriDate
                locationName.value = parsed.locationName || 'Lokasi Saat Ini'
                isLoading.value = false
                startCountdownTimer()
                return
            }
        }
    } catch (e) {
        // ignore
    }

    const savePrayerCache = () => {
        if (prayerTimes.value) {
            try {
                localStorage.setItem(cacheKey, JSON.stringify({
                    prayerTimes: prayerTimes.value,
                    hijriDate: hijriDate.value,
                    locationName: locationName.value
                }))
            } catch (e) {}
        }
    }

    const fetchFromAladhan = async (url) => {
        const response = await fetch(url)
        if (!response.ok) throw new Error('Network response was not ok')

        const result = await response.json()
        if (result.code === 200) {
            const timings = result.data.timings
            prayerTimes.value = {
                Subuh: timings.Fajr,
                Terbit: timings.Sunrise,
                Dzuhur: timings.Dhuhr,
                Ashar: timings.Asr,
                Maghrib: timings.Maghrib,
                Isya: timings.Isha
            }
            const hijri = result.data.date.hijri
            hijriDate.value = `${hijri.day} ${hijri.month.en} ${hijri.year} H`
        }
    }

    const fetchWithIpFallback = async () => {
        try {
            const ipRes = await fetch('https://ipinfo.io/json')
            const ipData = await ipRes.json()

            if (ipData && ipData.loc) {
                const [lat, lng] = ipData.loc.split(',')
                locationName.value = ipData.city ? `${ipData.city} (via IP)` : 'Lokasi Saat Ini'
                await fetchFromAladhan(`https://api.aladhan.com/v1/timings/${dateStr}?latitude=${lat}&longitude=${lng}&method=20`)
            } else {
                throw new Error("No IP location found")
            }
        } catch (e) {
            locationName.value = 'Jakarta, Indonesia'
            await fetchFromAladhan(`https://api.aladhan.com/v1/timingsByCity/${dateStr}?city=Jakarta&country=Indonesia&method=20`)
        } finally {
            savePrayerCache()
            isLoading.value = false
            startCountdownTimer()
        }
    }

    try {
        if (navigator.geolocation && window.isSecureContext !== false) {
            await new Promise((resolve) => {
                navigator.geolocation.getCurrentPosition(
                    async (position) => {
                        const lat = position.coords.latitude
                        const lng = position.coords.longitude

                        // Jalankan reverse-geocode dan Aladhan secara paralel
                        await Promise.all([
                            (async () => {
                                try {
                                    const geoRes = await fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lng}&localityLanguage=id`)
                                    const geoData = await geoRes.json()

                                    let kecamatan = ''
                                    let kabupaten = ''

                                    if (geoData.localityInfo && geoData.localityInfo.administrative) {
                                        const admin = geoData.localityInfo.administrative
                                        const level6 = admin.find(a => a.adminLevel === 6)
                                        const level5 = admin.find(a => a.adminLevel === 5)

                                        if (level6) kecamatan = level6.name
                                        if (level5) kabupaten = level5.name
                                    }

                                    if (!kecamatan) kecamatan = geoData.locality || ''
                                    if (!kabupaten) kabupaten = geoData.city || ''

                                    let displayName = ''
                                    if (kecamatan && kabupaten && kecamatan !== kabupaten) {
                                        displayName = `${kecamatan}, ${kabupaten}`
                                    } else if (kabupaten) {
                                        displayName = kabupaten
                                    } else if (kecamatan) {
                                        displayName = kecamatan
                                    } else {
                                        displayName = geoData.principalSubdivision || 'Lokasi Saat Ini'
                                    }

                                    locationName.value = displayName ? `${displayName} ` : 'Lokasi Saat Ini '
                                } catch (geoErr) {
                                    locationName.value = 'Lokasi Saat Ini'
                                }
                            })(),
                            fetchFromAladhan(`https://api.aladhan.com/v1/timings/${dateStr}?latitude=${lat}&longitude=${lng}&method=20`)
                        ])

                        savePrayerCache()
                        isLoading.value = false
                        startCountdownTimer()
                        resolve()
                    },
                    async (err) => {
                        console.warn('Geolocation denied or failed, falling back to IP.', err)
                        await fetchWithIpFallback()
                        resolve()
                    },
                    { timeout: 5000, enableHighAccuracy: false }
                )
            })
        } else {
            await fetchWithIpFallback()
        }
    } catch (err) {
        console.error('Failed to fetch prayer times:', err)
        error.value = 'Gagal memuat jadwal sholat.'
        isLoading.value = false
    }
}

export function useIslamicData() {
    onMounted(() => {
        activeComponents++
        if (!fetchPrayerPromise) {
            fetchPrayerPromise = fetchPrayerTimes()
        } else {
            startCountdownTimer()
        }

        if (!fetchHadithPromise) {
            fetchHadithPromise = fetchDailyHadith()
        }
    })

    onUnmounted(() => {
        activeComponents--
        stopCountdownTimer()
    })

    return {
        prayerTimes,
        hijriDate,
        isLoading,
        error,
        locationName,
        dailyHadith,
        isLoadingHadith,
        nextPrayerName,
        countdownText
    }
}
