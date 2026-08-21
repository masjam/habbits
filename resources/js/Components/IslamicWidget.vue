<script setup>
import { useIslamicData } from '@/Composables/useIslamicData'

const { prayerTimes, hijriDate, isLoading, error, locationName, getDailyHadith } = useIslamicData()
const hadith = getDailyHadith()

const masehiDate = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date())
</script>

<template>
    <div class="bg-gradient-to-br from-emerald-400 to-emerald-500 rounded-2xl p-6 shadow-md text-white relative overflow-hidden">
        <!-- Background Decoration (Islamic Ornament) -->
        <div class="absolute -right-8 -top-8 opacity-20">
            <svg class="w-48 h-48 text-emerald-300" viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="2">
                <!-- Square 1 -->
                <rect x="20" y="20" width="60" height="60" />
                <!-- Square 2 (Rotated) -->
                <rect x="20" y="20" width="60" height="60" transform="rotate(45 50 50)" />
                <!-- Inner Circle -->
                <circle cx="50" cy="50" r="24" />
                <!-- Outer Circle -->
                <circle cx="50" cy="50" r="46" />
                <!-- Center Diamond -->
                <polygon points="50,42 58,50 50,58 42,50" fill="currentColor" />
            </svg>
        </div>

        <div class="relative z-10">
            <!-- Header & Date -->
            <div class="mb-6">
                <h3 class="text-sm font-bold text-emerald-100 uppercase tracking-widest mb-1">{{ locationName }}</h3>
                <div v-if="isLoading" class="h-8 bg-emerald-500/50 rounded animate-pulse w-3/4"></div>
                <div v-else-if="error" class="text-sm text-rose-200">{{ error }}</div>
                <div v-else>
                    <div class="text-lg font-bold text-white mb-1">{{ masehiDate }}</div>
                    <div class="text-xl font-medium tracking-tight text-emerald-50">{{ hijriDate }}</div>
                </div>
                <p class="text-xs text-emerald-200 mt-3 font-medium">Jadwal Sholat Harian</p>
            </div>

            <!-- Prayer Times Grid -->
            <div class="grid grid-cols-2 gap-3" v-if="!isLoading && prayerTimes">
                <div class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl p-3 flex justify-between items-center hover:bg-white/20 transition-colors">
                    <span class="text-xs font-bold text-emerald-50 uppercase tracking-wider">Subuh</span>
                    <span class="text-sm font-black text-white">{{ prayerTimes.Subuh }}</span>
                </div>
                <div class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl p-3 flex justify-between items-center hover:bg-white/20 transition-colors">
                    <span class="text-xs font-bold text-emerald-50 uppercase tracking-wider">Terbit</span>
                    <span class="text-sm font-black text-white">{{ prayerTimes.Terbit }}</span>
                </div>
                <div class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl p-3 flex justify-between items-center hover:bg-white/20 transition-colors">
                    <span class="text-xs font-bold text-emerald-50 uppercase tracking-wider">Dzuhur</span>
                    <span class="text-sm font-black text-white">{{ prayerTimes.Dzuhur }}</span>
                </div>
                <div class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl p-3 flex justify-between items-center hover:bg-white/20 transition-colors">
                    <span class="text-xs font-bold text-emerald-50 uppercase tracking-wider">Ashar</span>
                    <span class="text-sm font-black text-white">{{ prayerTimes.Ashar }}</span>
                </div>
                <div class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl p-3 flex justify-between items-center hover:bg-white/20 transition-colors">
                    <span class="text-xs font-bold text-emerald-50 uppercase tracking-wider">Maghrib</span>
                    <span class="text-sm font-black text-white">{{ prayerTimes.Maghrib }}</span>
                </div>
                <div class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl p-3 flex justify-between items-center hover:bg-white/20 transition-colors">
                    <span class="text-xs font-bold text-emerald-50 uppercase tracking-wider">Isya</span>
                    <span class="text-sm font-black text-white">{{ prayerTimes.Isya }}</span>
                </div>
            </div>

            <!-- Loading State for Grid -->
            <div class="grid grid-cols-2 gap-3" v-if="isLoading">
                <div v-for="i in 6" :key="i" class="h-12 bg-white/10 rounded-xl animate-pulse"></div>
            </div>
            
            <!-- Hadits Harian (To fill empty space) -->
            <div class="mt-6 pt-6 border-t border-emerald-500/50">
                <h4 class="text-xs font-bold text-amber-200 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    Hadits Harian
                </h4>
                <p class="text-sm font-medium text-white italic leading-relaxed">
                    "{{ hadith.text }}"
                </p>
                <p class="text-xs font-bold text-emerald-200 mt-2">— {{ hadith.source }}</p>
            </div>
        </div>
    </div>
</template>
