<script setup>
import { useIslamicData } from '@/Composables/useIslamicData'

const { prayerTimes, hijriDate, isLoading, error, locationName, getDailyHadith } = useIslamicData()
const hadith = getDailyHadith()

const masehiDate = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date())
</script>

<template>
    <div class="bg-gradient-to-br from-emerald-800 to-emerald-950 rounded-2xl p-6 shadow-md text-white relative overflow-hidden">
        <!-- Background Decoration -->
        <div class="absolute -right-10 -top-10 opacity-10">
            <svg class="w-40 h-40" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm4.59-12.42L10 14.17l-2.59-2.58L6 13l4 4 8-8z" />
            </svg>
        </div>

        <div class="relative z-10">
            <!-- Header & Date -->
            <div class="mb-6">
                <h3 class="text-sm font-bold text-emerald-200 uppercase tracking-widest mb-1">{{ locationName }}</h3>
                <div v-if="isLoading" class="h-8 bg-emerald-700/50 rounded animate-pulse w-3/4"></div>
                <div v-else-if="error" class="text-sm text-rose-300">{{ error }}</div>
                <div v-else>
                    <div class="text-lg font-bold text-white mb-1">{{ masehiDate }}</div>
                    <div class="text-xl font-medium tracking-tight text-emerald-100">{{ hijriDate }}</div>
                </div>
                <p class="text-xs text-emerald-300 mt-3 font-medium">Jadwal Sholat Harian</p>
            </div>

            <!-- Prayer Times Grid -->
            <div class="grid grid-cols-2 gap-3" v-if="!isLoading && prayerTimes">
                <div class="bg-emerald-900/50 backdrop-blur-sm border border-emerald-700/50 rounded-xl p-3 flex justify-between items-center hover:bg-emerald-800/50 transition-colors">
                    <span class="text-xs font-bold text-emerald-100 uppercase tracking-wider">Subuh</span>
                    <span class="text-sm font-black text-white">{{ prayerTimes.Subuh }}</span>
                </div>
                <div class="bg-emerald-900/50 backdrop-blur-sm border border-emerald-700/50 rounded-xl p-3 flex justify-between items-center hover:bg-emerald-800/50 transition-colors">
                    <span class="text-xs font-bold text-emerald-100 uppercase tracking-wider">Terbit</span>
                    <span class="text-sm font-black text-white">{{ prayerTimes.Terbit }}</span>
                </div>
                <div class="bg-emerald-900/50 backdrop-blur-sm border border-emerald-700/50 rounded-xl p-3 flex justify-between items-center hover:bg-emerald-800/50 transition-colors">
                    <span class="text-xs font-bold text-emerald-100 uppercase tracking-wider">Dzuhur</span>
                    <span class="text-sm font-black text-white">{{ prayerTimes.Dzuhur }}</span>
                </div>
                <div class="bg-emerald-900/50 backdrop-blur-sm border border-emerald-700/50 rounded-xl p-3 flex justify-between items-center hover:bg-emerald-800/50 transition-colors">
                    <span class="text-xs font-bold text-emerald-100 uppercase tracking-wider">Ashar</span>
                    <span class="text-sm font-black text-white">{{ prayerTimes.Ashar }}</span>
                </div>
                <div class="bg-emerald-900/50 backdrop-blur-sm border border-emerald-700/50 rounded-xl p-3 flex justify-between items-center hover:bg-emerald-800/50 transition-colors">
                    <span class="text-xs font-bold text-emerald-100 uppercase tracking-wider">Maghrib</span>
                    <span class="text-sm font-black text-white">{{ prayerTimes.Maghrib }}</span>
                </div>
                <div class="bg-emerald-900/50 backdrop-blur-sm border border-emerald-700/50 rounded-xl p-3 flex justify-between items-center hover:bg-emerald-800/50 transition-colors">
                    <span class="text-xs font-bold text-emerald-100 uppercase tracking-wider">Isya</span>
                    <span class="text-sm font-black text-white">{{ prayerTimes.Isya }}</span>
                </div>
            </div>

            <!-- Loading State for Grid -->
            <div class="grid grid-cols-2 gap-3" v-if="isLoading">
                <div v-for="i in 6" :key="i" class="h-12 bg-emerald-700/50 rounded-xl animate-pulse"></div>
            </div>
            
            <!-- Hadits Harian (To fill empty space) -->
            <div class="mt-6 pt-6 border-t border-emerald-800/50">
                <h4 class="text-xs font-bold text-emerald-300 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    Hadits Harian
                </h4>
                <p class="text-sm font-medium text-emerald-50 italic leading-relaxed">
                    "{{ hadith.text }}"
                </p>
                <p class="text-xs font-bold text-emerald-400 mt-2">— {{ hadith.source }}</p>
            </div>
        </div>
    </div>
</template>
