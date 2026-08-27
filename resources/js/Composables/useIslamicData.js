import { ref, onMounted, onUnmounted } from 'vue'

export function useIslamicData() {
    const prayerTimes = ref(null)
    const hijriDate = ref(null)
    const isLoading = ref(true)
    const error = ref(null)
    const locationName = ref('Jakarta, Indonesia')

    const nextPrayerName = ref('')
    const countdownText = ref('')
    let timerInterval = null

    const dailyHadith = ref(null)
    const isLoadingHadith = ref(false)

    const fetchDailyHadith = async () => {
        isLoadingHadith.value = true
        
        const perawiList = [
            { slug: 'bukhari', nama: 'Bukhari', total: 6638 },
            { slug: 'muslim', nama: 'Muslim', total: 3033 },
            { slug: 'tirmidzi', nama: 'Tirmidzi', total: 3956 },
            { slug: 'ibnumajah', nama: 'Ibnu Majah', total: 4341 },
            { slug: 'nasai', nama: "Nasa'i", total: 5758 },
            { slug: 'ahmad', nama: 'Ahmad', total: 26363 },
            { slug: 'darimi', nama: 'Darimi', total: 3367 },
            { slug: 'malik', nama: 'Malik', total: 1587 },
            { slug: 'abudaud', nama: 'Abu Daud', total: 5274 },
        ]

        // Create a daily seed
        const today = new Date()
        const seedStr = `${today.getFullYear()}-${today.getMonth()}-${today.getDate()}`
        
        // Simple hash function for string
        let hash = 0;
        for (let i = 0; i < seedStr.length; i++) {
            const char = seedStr.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        hash = Math.abs(hash)
        
        // Pick perawi
        const perawiIndex = hash % perawiList.length
        const selectedPerawi = perawiList[perawiIndex]
        
        // Pick nomor
        const nomor = (Math.floor(hash / perawiList.length) % selectedPerawi.total) + 1

        try {
            const res = await fetch(`https://api.myquran.com/v2/hadits/${selectedPerawi.slug}/${nomor}`)
            const json = await res.json()
            if (json.status && json.data) {
                dailyHadith.value = {
                    text: json.data.contents.id,
                    source: `HR. ${json.data.name} No. ${json.data.number}`,
                    url: `/quran-hadis?tab=hadis&perawi=${selectedPerawi.slug}&nomor=${nomor}`
                }
            } else {
                throw new Error("Invalid response")
            }
        } catch (e) {
            console.error("Failed to fetch daily hadith", e)
            dailyHadith.value = {
                text: "Barangsiapa menempuh jalan untuk menuntut ilmu, maka Allah akan mudahkan baginya jalan menuju surga.",
                source: "HR. Muslim",
                url: null
            }
        } finally {
            isLoadingHadith.value = false
        }
    }

    const fetchPrayerTimes = async () => {
        isLoading.value = true
        error.value = null
        try {
            // Kita gunakan Aladhan API. 
            // Method 20 adalah Kementerian Agama Republik Indonesia
            const date = new Date()
            const dd = String(date.getDate()).padStart(2, '0')
            const mm = String(date.getMonth() + 1).padStart(2, '0')
            const yyyy = date.getFullYear()
            const dateStr = `${dd}-${mm}-${yyyy}`

            // Fungsi fetch API Aladhan
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
                    // Gunakan ipinfo.io sebagai fallback jika akses GPS ditolak / HTTP
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
                    console.warn('IP Fallback failed, using Jakarta.', e)
                    locationName.value = 'Jakarta, Indonesia'
                    await fetchFromAladhan(`https://api.aladhan.com/v1/timingsByCity/${dateStr}?city=Jakarta&country=Indonesia&method=20`)
                } finally {
                    isLoading.value = false
                }
            }

            // Coba ambil Geolocation GPS
            if (navigator.geolocation && window.isSecureContext !== false) {
                await new Promise((resolve) => {
                    navigator.geolocation.getCurrentPosition(
                        async (position) => {
                            const lat = position.coords.latitude
                            const lng = position.coords.longitude
                            
                            // Coba dapatkan nama kota dari koordinat GPS
                            try {
                                const geoRes = await fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lng}&localityLanguage=id`);
                                const geoData = await geoRes.json();
                                
                                let kecamatan = '';
                                let kabupaten = '';

                                // Coba ekstrak dari array administrative (tingkat 6 = kecamatan, 5 = kabupaten/kota)
                                if (geoData.localityInfo && geoData.localityInfo.administrative) {
                                    const admin = geoData.localityInfo.administrative;
                                    const level6 = admin.find(a => a.adminLevel === 6);
                                    const level5 = admin.find(a => a.adminLevel === 5);
                                    
                                    if (level6) kecamatan = level6.name;
                                    if (level5) kabupaten = level5.name;
                                }

                                // Fallback jika tidak ditemukan di level administrative
                                if (!kecamatan) kecamatan = geoData.locality || '';
                                if (!kabupaten) kabupaten = geoData.city || '';

                                let displayName = '';
                                if (kecamatan && kabupaten && kecamatan !== kabupaten) {
                                    displayName = `${kecamatan}, ${kabupaten}`;
                                } else if (kabupaten) {
                                    displayName = kabupaten;
                                } else if (kecamatan) {
                                    displayName = kecamatan;
                                } else {
                                    displayName = geoData.principalSubdivision || 'Lokasi Saat Ini';
                                }

                                locationName.value = displayName ? `${displayName} (GPS)` : 'Lokasi Saat Ini (GPS)';
                            } catch (geoErr) {
                                locationName.value = 'Lokasi Saat Ini (GPS)';
                            }

                            await fetchFromAladhan(`https://api.aladhan.com/v1/timings/${dateStr}?latitude=${lat}&longitude=${lng}&method=20`)
                            isLoading.value = false
                            resolve()
                        },
                        async (err) => {
                            console.warn('Geolocation denied or failed, falling back to IP.', err)
                            await fetchWithIpFallback()
                            resolve()
                        },
                        { timeout: 5000, enableHighAccuracy: true }
                    )
                })
            } else {
                console.warn('Geolocation not supported or insecure context, falling back to IP.')
                await fetchWithIpFallback()
            }
        } catch (err) {
            console.error('Failed to fetch prayer times:', err)
            error.value = 'Gagal memuat jadwal sholat.'
            isLoading.value = false
        }
    }

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
            // Hilangkan zona waktu (seperti WIB) dari string jika ada (biasanya format HH:mm)
            const timeStr = p.time.split(' ')[0]
            const [h, m] = timeStr.split(':').map(Number)
            const pDate = new Date(now.getFullYear(), now.getMonth(), now.getDate(), h, m, 0)
            if (pDate > now) {
                nextPrayer = p
                nextPrayerDate = pDate
                break
            }
        }

        // Jika tidak ada sholat selanjutnya hari ini, berarti Subuh besok
        if (!nextPrayer) {
            nextPrayer = prayers[0]
            const timeStr = nextPrayer.time.split(' ')[0]
            const [h, m] = timeStr.split(':').map(Number)
            nextPrayerDate = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, h, m, 0)
        }

        nextPrayerName.value = nextPrayer.name

        const diff = nextPrayerDate - now // in ms
        const h = Math.floor(diff / (1000 * 60 * 60))
        const m = Math.floor((diff / (1000 * 60)) % 60)
        const s = Math.floor((diff / 1000) % 60)

        countdownText.value = `-${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
    }

    onMounted(() => {
        fetchPrayerTimes().then(() => {
            if (prayerTimes.value) {
                calculateCountdown()
                timerInterval = setInterval(calculateCountdown, 1000)
            }
        })
        fetchDailyHadith()
    })

    onUnmounted(() => {
        if (timerInterval) clearInterval(timerInterval)
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
