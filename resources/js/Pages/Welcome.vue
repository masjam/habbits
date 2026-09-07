<script setup>
import { Head, Link } from '@inertiajs/vue3'
import IslamicWidget from '@/Components/IslamicWidget.vue'
import { ref, computed, onMounted } from 'vue'

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

// --- Pop-up Logic ---
const showPopup = ref(false)

onMounted(() => {
    if (props.settings?.popup_active === '1' || props.settings?.popup_active === true || props.settings?.popup_active === 'true') {
        if (props.settings?.popup_text) {
            showPopup.value = true
        }
    }
})

const closePopup = () => {
    showPopup.value = false
}

// --- YouTube Logic ---
const youtubeEmbedUrl = computed(() => {
    const url = props.settings?.youtube_link
    if (!url) return null
    
    // Extract video ID from URL (supports /live/, /shorts/, watch?v=, youtu.be, etc)
    let videoId = null
    const regExp = /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|live|shorts)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i
    const match = url.match(regExp)
    
    if (match && match[1].length === 11) {
        videoId = match[1]
    } else if (url.length === 11) {
        // Assume they just entered the ID
        videoId = url
    }
    
    return videoId ? `https://www.youtube.com/embed/${videoId}` : null
})

</script>

<template>
    <Head title="Sistem Pantauan Habit" />
    
    <div class="min-h-screen relative overflow-hidden flex flex-col font-sans">
        
        <!-- Running Text Announcement -->
        <div v-if="settings?.running_text" class="w-full bg-emerald-600 text-white overflow-hidden py-2 shadow-sm relative z-50">
            <div class="flex whitespace-nowrap overflow-hidden">
                <span class="animate-marquee inline-block px-4 font-medium text-sm w-full" v-html="settings.running_text">
                </span>
            </div>
        </div>

        <!-- Navigation Bar -->
        <nav class="w-full bg-white border-b border-slate-100 shadow-sm relative z-50">
            <div class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
                <!-- Logo Area -->
                <div class="flex items-center gap-3">
                    <img src="/logo.png" alt="Logo" class="w-10 h-10 object-contain transform transition hover:scale-105" />
                    <span class="text-xl sm:text-2xl font-black tracking-tight text-slate-800 bg-clip-text text-transparent bg-gradient-to-r from-emerald-700 to-emerald-900">
                        Mutaba'ah Yaumiyah
                    </span>
                </div>

                <!-- Action Buttons -->
                <div v-if="canLogin" class="flex gap-3 sm:gap-4 items-center">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="px-4 sm:px-5 py-2 sm:py-2.5 rounded-xl font-bold text-xs sm:text-sm bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-colors"
                    >
                        Dashboard
                    </Link>

                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="px-4 sm:px-6 py-2 sm:py-2.5 rounded-xl font-bold text-xs sm:text-sm text-white bg-emerald-600 hover:bg-emerald-700 shadow-md hover:shadow-lg transform transition hover:-translate-y-0.5"
                        >
                            Log in
                        </Link>
                    </template>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="flex-grow flex flex-col justify-center relative overflow-hidden pb-12 w-full">
            <!-- Decorative Background Elements -->
            <div class="absolute top-0 right-0 -mr-20 -mt-20 w-96 h-96 rounded-full bg-emerald-100 blur-3xl opacity-50 pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 -ml-20 -mb-20 w-80 h-80 rounded-full bg-blue-50 blur-3xl opacity-50 pointer-events-none"></div>

            <div class="max-w-[90rem] mx-auto w-full px-4 sm:px-6 lg:px-8 mt-12 sm:mt-0 relative z-10">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-start">
                    
                    <!-- Column 1: Welcome Message -->
                    <div class="lg:col-span-4 space-y-8 self-center">
                        <div class="space-y-4">
                            <span class="inline-block px-3 sm:px-4 py-1.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] sm:text-xs font-bold tracking-widest uppercase">
                                Pantau Ibadah Harian
                            </span>
                            <h1 class="text-4xl sm:text-5xl md:text-5xl font-black text-slate-900 tracking-tight leading-tight">
                                Disiplin Ibadah, <br class="hidden md:block" />
                                <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-teal-500">
                                    Berkah Melimpah
                                </span>
                            </h1>
                            <p class="text-base sm:text-lg text-slate-600 max-w-xl font-medium leading-relaxed">
                                Catat dan pantau kebiasaan ibadah harian Anda. Bangun rutinitas yang istiqomah untuk mencapai ketenangan hidup dan keridhoan-Nya.
                            </p>
                            
                            <!-- Live Stats -->
                            <div class="pt-4 grid grid-cols-2 gap-4 max-w-lg">
                                <div class="bg-white/60 backdrop-blur border border-emerald-100 rounded-2xl p-4 flex flex-col items-center justify-center text-center">
                                    <span class="text-3xl font-black text-emerald-600">{{ liveStats.logs.toLocaleString('id-ID') }}</span>
                                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider mt-1">Total Ibadah Tercatat Bulan Ini</span>
                                </div>
                                <div class="bg-white/60 backdrop-blur border border-blue-100 rounded-2xl p-4 flex flex-col items-center justify-center text-center">
                                    <span class="text-3xl font-black text-blue-600">{{ liveStats.skor.toLocaleString('id-ID') }}</span>
                                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider mt-1">Akumulasi Skor Kebaikan</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Column 2: Islamic Widget -->
                    <div class="lg:col-span-3 self-center">
                        <IslamicWidget />
                    </div>

                    <!-- Column 3: YouTube Video Section or Mockup -->
                    <div class="lg:col-span-5 self-center w-full">
                        <div v-if="youtubeEmbedUrl" class="bg-white p-2 rounded-2xl shadow-lg border border-slate-100 transform transition hover:shadow-xl">
                            <div class="relative w-full overflow-hidden rounded-xl" style="padding-top: 56.25%;">
                                <iframe 
                                    :src="youtubeEmbedUrl" 
                                    title="YouTube video player" 
                                    frameborder="0" 
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                                    allowfullscreen
                                    class="absolute top-0 left-0 w-full h-full"
                                ></iframe>
                            </div>
                        </div>
                        <div v-else class="relative group cursor-pointer">
                            <div class="absolute -inset-1 bg-gradient-to-r from-emerald-600 to-teal-500 rounded-[2rem] blur opacity-25 group-hover:opacity-50 transition duration-1000 group-hover:duration-200"></div>
                            <img src="/mockup.jpg" alt="App Mockup" class="relative rounded-[2rem] shadow-2xl border border-slate-100 transform transition hover:scale-[1.02] duration-300 w-full object-cover aspect-square md:aspect-auto" />
                            <div class="absolute bottom-4 left-0 right-0 text-center">
                                <span class="bg-slate-900/80 backdrop-blur text-white text-xs font-bold px-4 py-1.5 rounded-full shadow-lg">Preview Tampilan Dashboard</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            
        </main>
        
        <!-- Footer -->
        <footer class="bg-white border-t border-slate-100 py-6 text-center text-sm font-medium text-slate-500 relative z-10">
            <p>&copy; {{ new Date().getFullYear() }} Mutaba'ah Yaumiah SDAM. All rights reserved. @blue_core21</p>
        </footer>
        
        <!-- Pop-up Modal -->
        <div v-if="showPopup" class="relative z-[100]" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closePopup"></div>
            
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-100">
                        <!-- Header with close button -->
                        <div class="absolute top-0 right-0 pt-4 pr-4">
                            <button @click="closePopup" type="button" class="rounded-lg bg-white text-slate-400 hover:text-slate-600 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors">
                                <span class="sr-only">Close</span>
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        
                        <div class="bg-white px-6 pb-6 pt-8">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-emerald-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                                    <h3 class="text-xl font-bold leading-6 text-slate-900" id="modal-title">Pengumuman</h3>
                                    <div class="mt-4">
                                        <div class="text-slate-600 whitespace-pre-wrap text-base leading-relaxed" v-html="settings?.popup_text">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-slate-50 px-6 py-4 flex justify-end">
                            <button @click="closePopup" type="button" class="inline-flex w-full justify-center rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 sm:w-auto transition-colors">
                                Mengerti
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</template>
