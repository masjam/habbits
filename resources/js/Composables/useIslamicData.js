import { ref, onMounted } from 'vue'

export function useIslamicData() {
    const prayerTimes = ref(null)
    const hijriDate = ref(null)
    const isLoading = ref(true)
    const error = ref(null)
    const locationName = ref('Jakarta, Indonesia')

    // Daftar hadits harian (30 buah untuk dirotasi berdasarkan tanggal)
    const dailyHadiths = [
        { text: "Sesungguhnya amal itu tergantung niatnya, dan seseorang akan mendapatkan apa yang ia niatkan.", source: "HR. Bukhari & Muslim" },
        { text: "Sebaik-baik kalian adalah orang yang belajar Al-Qur'an dan mengajarkannya.", source: "HR. Bukhari" },
        { text: "Barangsiapa menempuh jalan untuk menuntut ilmu, maka Allah akan mudahkan baginya jalan menuju surga.", source: "HR. Muslim" },
        { text: "Senyummu di hadapan saudaramu adalah sedekah.", source: "HR. Tirmidzi" },
        { text: "Kebersihan itu sebagian dari iman.", source: "HR. Muslim" },
        { text: "Tidaklah beriman seseorang dari kalian sehingga dia mencintai untuk saudaranya apa yang dia cintai untuk dirinya sendiri.", source: "HR. Bukhari & Muslim" },
        { text: "Orang mukmin yang paling sempurna imannya adalah yang paling baik akhlaknya.", source: "HR. Tirmidzi" },
        { text: "Barangsiapa beriman kepada Allah dan hari akhir, hendaklah ia berkata baik atau diam.", source: "HR. Bukhari & Muslim" },
        { text: "Agama itu adalah nasihat.", source: "HR. Muslim" },
        { text: "Tidak akan masuk surga orang yang memutus tali silaturahmi.", source: "HR. Bukhari & Muslim" },
        { text: "Bertakwalah kepada Allah di mana saja kamu berada, dan iringilah keburukan dengan kebaikan niscaya ia akan menghapusnya.", source: "HR. Tirmidzi" },
        { text: "Doa itu adalah senjata orang mukmin, tiang agama, dan cahaya langit dan bumi.", source: "HR. Hakim" },
        { text: "Barangsiapa tidak menyayangi, maka ia tidak akan disayangi.", source: "HR. Bukhari" },
        { text: "Dua kenikmatan yang sering dilupakan oleh kebanyakan manusia adalah kesehatan dan waktu luang.", source: "HR. Bukhari" },
        { text: "Orang yang kuat bukanlah yang pandai bergulat, tapi orang yang kuat adalah yang mampu menahan amarahnya.", source: "HR. Bukhari" },
        { text: "Tangan yang di atas (pemberi) lebih baik dari tangan yang di bawah (penerima).", source: "HR. Bukhari" },
        { text: "Barangsiapa yang meringankan penderitaan seorang mukmin di dunia, niscaya Allah akan meringankan penderitaannya di hari kiamat.", source: "HR. Muslim" },
        { text: "Malu itu sebagian dari iman.", source: "HR. Bukhari" },
        { text: "Sabar itu berada pada pukulan (kejadian) pertama.", source: "HR. Bukhari" },
        { text: "Jauhilah sifat hasad, karena hasad itu memakan kebaikan sebagaimana api memakan kayu bakar.", source: "HR. Abu Dawud" },
        { text: "Barangsiapa menunjukkan suatu kebaikan, maka baginya pahala seperti pahala orang yang melakukannya.", source: "HR. Muslim" },
        { text: "Perumpamaan teman yang baik dan buruk ibarat penjual minyak wangi dan pandai besi.", source: "HR. Bukhari & Muslim" },
        { text: "Janganlah kalian saling membenci, saling mendengki, dan saling membelakangi. Jadilah hamba-hamba Allah yang bersaudara.", source: "HR. Bukhari" },
        { text: "Doa yang paling cepat dikabulkan adalah doa seseorang untuk saudaranya tanpa sepengetahuannya.", source: "HR. Abu Dawud" },
        { text: "Allah tidak melihat bentuk rupamu dan hartamu, tapi Allah melihat hati dan amalmu.", source: "HR. Muslim" },
        { text: "Barangsiapa yang hari ini lebih baik dari kemarin, maka ia beruntung.", source: "HR. Al-Hakim" },
        { text: "Sedekah itu menghapus dosa sebagaimana air memadamkan api.", source: "HR. Tirmidzi" },
        { text: "Hak seorang muslim terhadap muslim lainnya ada enam: menjawab salam, menjenguk yang sakit, mengantar jenazah...", source: "HR. Muslim" },
        { text: "Ridha Allah terletak pada ridha orang tua, dan murka Allah terletak pada murka orang tua.", source: "HR. Tirmidzi" },
        { text: "Setiap amal anak Adam dilipatgandakan pahalanya. 10 hingga 700 kali lipat. Kecuali puasa, karena ia untuk-Ku.", source: "HR. Bukhari & Muslim" },
        { text: "Bertaubat dari dosa seperti orang yang tidak berdosa.", source: "HR. Ibnu Majah" }
    ]

    const getDailyHadith = () => {
        const today = new Date().getDate() // 1 - 31
        // Gunakan (today - 1) sebagai index agar selalu berulang tiap bulan
        const index = (today - 1) % dailyHadiths.length
        return dailyHadiths[index]
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
                    },
                    async (err) => {
                        console.warn('Geolocation denied or failed, falling back to IP.', err)
                        await fetchWithIpFallback()
                    },
                    { timeout: 5000, enableHighAccuracy: true }
                )
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

    onMounted(() => {
        fetchPrayerTimes()
    })

    return {
        prayerTimes,
        hijriDate,
        isLoading,
        error,
        locationName,
        getDailyHadith
    }
}
