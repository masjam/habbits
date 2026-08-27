<script setup>
import { useIslamicData } from '@/Composables/useIslamicData'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    horizontal: {
        type: Boolean,
        default: false
    }
})

const { prayerTimes, hijriDate, isLoading, error, locationName, dailyHadith, isLoadingHadith, nextPrayerName, countdownText } = useIslamicData()

const masehiDate = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date())
</script>

<template>
    <div class="bg-gradient-to-br from-emerald-600 to-emerald-800 rounded-2xl p-6 shadow-md text-white relative overflow-hidden" :class="{'md:p-8': horizontal}">
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

        <div class="relative z-10" :class="{'md:flex md:items-center md:gap-8': horizontal}">
            <!-- Header & Date -->
            <div :class="{'mb-6': !horizontal, 'mb-0 md:w-1/4 shrink-0': horizontal}">
                <h3 class="text-sm font-bold text-emerald-100 uppercase tracking-widest mb-1">{{ locationName }}</h3>
                <div v-if="isLoading" class="h-8 bg-emerald-500/50 rounded animate-pulse w-3/4"></div>
                <div v-else-if="error" class="text-sm text-rose-200">{{ error }}</div>
                <div v-else>
                    <div class="flex justify-between items-end mb-2">
                        <div>
                            <div class="text-lg font-bold text-white mb-1">{{ masehiDate }}</div>
                            <div class="text-xl font-medium tracking-tight text-emerald-50">{{ hijriDate }}</div>
                        </div>
                        <div v-if="countdownText" class="text-right">
                            <div class="text-[10px] font-bold text-emerald-200 uppercase tracking-widest mb-1">Menuju {{ nextPrayerName }}</div>
                            <div class="text-xl font-black text-white tabular-nums tracking-wider leading-none">{{ countdownText }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prayer Times Grid -->
            <div :class="['grid gap-2 md:gap-3 flex-1', horizontal ? 'grid-cols-3 lg:grid-cols-6' : 'grid-cols-2']" v-if="!isLoading && prayerTimes">
                <div class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl p-2 md:p-3 hover:bg-white/20 transition-colors flex" :class="horizontal ? 'flex-col items-center justify-center text-center' : 'justify-between items-center'">
                    <span class="font-bold text-emerald-50 uppercase tracking-wider" :class="horizontal ? 'text-[9px] sm:text-[10px] mb-0.5' : 'text-xs'">Subuh</span>
                    <span class="font-black text-white" :class="horizontal ? 'text-xs sm:text-sm' : 'text-sm'">{{ prayerTimes.Subuh }}</span>
                </div>
                <div class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl p-2 md:p-3 hover:bg-white/20 transition-colors flex" :class="horizontal ? 'flex-col items-center justify-center text-center' : 'justify-between items-center'">
                    <span class="font-bold text-emerald-50 uppercase tracking-wider" :class="horizontal ? 'text-[9px] sm:text-[10px] mb-0.5' : 'text-xs'">Terbit</span>
                    <span class="font-black text-white" :class="horizontal ? 'text-xs sm:text-sm' : 'text-sm'">{{ prayerTimes.Terbit }}</span>
                </div>
                <div class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl p-2 md:p-3 hover:bg-white/20 transition-colors flex" :class="horizontal ? 'flex-col items-center justify-center text-center' : 'justify-between items-center'">
                    <span class="font-bold text-emerald-50 uppercase tracking-wider" :class="horizontal ? 'text-[9px] sm:text-[10px] mb-0.5' : 'text-xs'">Dzuhur</span>
                    <span class="font-black text-white" :class="horizontal ? 'text-xs sm:text-sm' : 'text-sm'">{{ prayerTimes.Dzuhur }}</span>
                </div>
                <div class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl p-2 md:p-3 hover:bg-white/20 transition-colors flex" :class="horizontal ? 'flex-col items-center justify-center text-center' : 'justify-between items-center'">
                    <span class="font-bold text-emerald-50 uppercase tracking-wider" :class="horizontal ? 'text-[9px] sm:text-[10px] mb-0.5' : 'text-xs'">Ashar</span>
                    <span class="font-black text-white" :class="horizontal ? 'text-xs sm:text-sm' : 'text-sm'">{{ prayerTimes.Ashar }}</span>
                </div>
                <div class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl p-2 md:p-3 hover:bg-white/20 transition-colors flex" :class="horizontal ? 'flex-col items-center justify-center text-center' : 'justify-between items-center'">
                    <span class="font-bold text-emerald-50 uppercase tracking-wider" :class="horizontal ? 'text-[9px] sm:text-[10px] mb-0.5' : 'text-xs'">Maghrib</span>
                    <span class="font-black text-white" :class="horizontal ? 'text-xs sm:text-sm' : 'text-sm'">{{ prayerTimes.Maghrib }}</span>
                </div>
                <div class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl p-2 md:p-3 hover:bg-white/20 transition-colors flex" :class="horizontal ? 'flex-col items-center justify-center text-center' : 'justify-between items-center'">
                    <span class="font-bold text-emerald-50 uppercase tracking-wider" :class="horizontal ? 'text-[9px] sm:text-[10px] mb-0.5' : 'text-xs'">Isya</span>
                    <span class="font-black text-white" :class="horizontal ? 'text-xs sm:text-sm' : 'text-sm'">{{ prayerTimes.Isya }}</span>
                </div>
            </div>

            <!-- Loading State for Grid -->
            <div :class="['grid gap-3 flex-1', horizontal ? 'grid-cols-3 lg:grid-cols-6' : 'grid-cols-2']" v-if="isLoading">
                <div v-for="i in 6" :key="i" class="h-12 bg-white/10 rounded-xl animate-pulse"></div>
            </div>
            
            <!-- Hadits Harian (To fill empty space) -->
            <div :class="{'mt-6 pt-6 border-t border-emerald-500/50': !horizontal, 'mt-6 md:mt-0 pt-6 md:pt-0 border-t md:border-t-0 md:border-l md:pl-8 border-emerald-500/50 md:w-1/4 shrink-0': horizontal}">
                <h4 class="text-xs font-bold text-emerald-200 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    Hadits Harian
                </h4>
                <div v-if="isLoadingHadith" class="space-y-2">
                    <div class="h-3 bg-emerald-500/50 rounded animate-pulse w-full"></div>
                    <div class="h-3 bg-emerald-500/50 rounded animate-pulse w-5/6"></div>
                    <div class="h-3 bg-emerald-500/50 rounded animate-pulse w-4/6"></div>
                </div>
                <div v-else-if="dailyHadith">
                    <component 
                        :is="dailyHadith.url ? Link : 'div'" 
                        :href="dailyHadith.url"
                        class="block group"
                    >
                        <p class="text-sm font-medium text-white italic leading-relaxed group-hover:text-emerald-100 transition-colors">
                            "{{ dailyHadith.text }}"
                        </p>
                        <p class="text-xs font-bold text-emerald-200 mt-2 flex items-center gap-1 group-hover:text-emerald-100 transition-colors">
                            — {{ dailyHadith.source }}
                            <svg v-if="dailyHadith.url" class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </p>
                    </component>
                </div>
            </div>
        </div>
    </div>
</template>
