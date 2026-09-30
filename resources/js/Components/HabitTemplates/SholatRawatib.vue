<script setup>
import { computed } from 'vue';

const props = defineProps({
    log: {
        type: Object,
        required: true
    },
    waktuSelesaiHaid: {
        type: String,
        default: null
    }
});

const disabledPrayers = computed(() => {
    const disabled = {
        subuh: false,
        dhuhur: false,
        asar: false,
        maghrib: false,
        isya: false
    };
    
    if (props.waktuSelesaiHaid) {
        const selesaiDate = new Date(props.waktuSelesaiHaid);
        const hour = selesaiDate.getHours();
        const minute = selesaiDate.getMinutes();
        const timeVal = hour + (minute / 60);

        if (timeVal > 6.0) disabled.subuh = true;
        if (timeVal > 15.0) disabled.dhuhur = true;
        if (timeVal > 18.0) disabled.asar = true;
        if (timeVal > 19.0) disabled.maghrib = true;
    }
    
    return disabled;
});
</script>

<template>
    <div>
        <!-- Mobile Layout (Vertical) -->
        <div class="md:hidden flex flex-col divide-y divide-slate-100">
            <div class="flex justify-between items-center px-4 py-2 bg-slate-100 dark:bg-slate-700/50 dark:bg-slate-800/50 text-[10px] font-bold text-slate-500 dark:text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                <span>Waktu</span>
                <div class="flex gap-4 sm:gap-6 w-1/2 justify-end">
                    <span class="w-10 text-center" title="Qobliyah">Qobliyah</span>
                    <span class="w-10 text-center" title="Ba'diyah">Ba'diyah</span>
                </div>
            </div>
            <!-- Subuh -->
            <div class="flex justify-between items-center p-4 hover:bg-slate-50 dark:bg-slate-800/50 transition-colors" :class="disabledPrayers.subuh ? 'opacity-50 bg-slate-50 dark:bg-slate-800/80' : ''">
                <span class="font-bold text-slate-700 dark:text-slate-300 dark:text-slate-600 text-xs uppercase w-1/3">
                    Subuh
                    <span v-if="disabledPrayers.subuh" class="block text-[8px] text-pink-600 dark:text-pink-400 normal-case mt-0.5">Terkunci (Haid)</span>
                </span>
                <div class="flex gap-4 sm:gap-6 w-1/2 justify-end items-center">
                    <div class="w-10 flex justify-center">
                        <input type="checkbox" v-model="log.details['subuh_q']" :disabled="disabledPrayers.subuh" class="w-4 h-4 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100 disabled:cursor-not-allowed" />
                    </div>
                    <div class="w-10 flex justify-center">
                        <div class="w-4 h-4 bg-slate-800 rounded-sm" title="Tidak ada Ba'diyah Subuh"></div>
                    </div>
                </div>
            </div>
            <!-- Dhuhur -->
            <div class="flex justify-between items-center p-4 hover:bg-slate-50 dark:bg-slate-800/50 transition-colors" :class="disabledPrayers.dhuhur ? 'opacity-50 bg-slate-50 dark:bg-slate-800/80' : ''">
                <span class="font-bold text-slate-700 dark:text-slate-300 dark:text-slate-600 text-xs uppercase w-1/3">
                    Dhuhur
                    <span v-if="disabledPrayers.dhuhur" class="block text-[8px] text-pink-600 dark:text-pink-400 normal-case mt-0.5">Terkunci (Haid)</span>
                </span>
                <div class="flex gap-4 sm:gap-6 w-1/2 justify-end items-center">
                    <div class="w-10 flex justify-center">
                        <input type="checkbox" v-model="log.details['dhuhur_q']" :disabled="disabledPrayers.dhuhur" class="w-4 h-4 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100 disabled:cursor-not-allowed" />
                    </div>
                    <div class="w-10 flex justify-center">
                        <input type="checkbox" v-model="log.details['dhuhur_b']" :disabled="disabledPrayers.dhuhur" class="w-4 h-4 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100 disabled:cursor-not-allowed" />
                    </div>
                </div>
            </div>
            <!-- Asar -->
            <div class="flex justify-between items-center p-4 hover:bg-slate-50 dark:bg-slate-800/50 transition-colors" :class="disabledPrayers.asar ? 'opacity-50 bg-slate-50 dark:bg-slate-800/80' : ''">
                <span class="font-bold text-slate-700 dark:text-slate-300 dark:text-slate-600 text-xs uppercase w-1/3">
                    Asar
                    <span v-if="disabledPrayers.asar" class="block text-[8px] text-pink-600 dark:text-pink-400 normal-case mt-0.5">Terkunci (Haid)</span>
                </span>
                <div class="flex gap-4 sm:gap-6 w-1/2 justify-end items-center">
                    <div class="w-10 flex justify-center bg-slate-200 dark:bg-slate-600 rounded p-1" title="Qobliyah Asar (Ghoiru Muakkad)">
                        <input type="checkbox" v-model="log.details['asar_q']" :disabled="disabledPrayers.asar" class="w-4 h-4 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100 disabled:cursor-not-allowed" />
                    </div>
                    <div class="w-10 flex justify-center">
                        <div class="w-4 h-4 bg-slate-800 rounded-sm" title="Tidak ada Ba'diyah Asar"></div>
                    </div>
                </div>
            </div>
            <!-- Maghrib -->
            <div class="flex justify-between items-center p-4 hover:bg-slate-50 dark:bg-slate-800/50 transition-colors" :class="disabledPrayers.maghrib ? 'opacity-50 bg-slate-50 dark:bg-slate-800/80' : ''">
                <span class="font-bold text-slate-700 dark:text-slate-300 dark:text-slate-600 text-xs uppercase w-1/3">
                    Maghrib
                    <span v-if="disabledPrayers.maghrib" class="block text-[8px] text-pink-600 dark:text-pink-400 normal-case mt-0.5">Terkunci (Haid)</span>
                </span>
                <div class="flex gap-4 sm:gap-6 w-1/2 justify-end items-center">
                    <div class="w-10 flex justify-center bg-slate-200 dark:bg-slate-600 rounded p-1" title="Qobliyah Maghrib (Ghoiru Muakkad)">
                        <input type="checkbox" v-model="log.details['maghrib_q']" :disabled="disabledPrayers.maghrib" class="w-4 h-4 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100 disabled:cursor-not-allowed" />
                    </div>
                    <div class="w-10 flex justify-center">
                        <input type="checkbox" v-model="log.details['maghrib_b']" :disabled="disabledPrayers.maghrib" class="w-4 h-4 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100 disabled:cursor-not-allowed" />
                    </div>
                </div>
            </div>
            <!-- Isya -->
            <div class="flex justify-between items-center p-4 hover:bg-slate-50 dark:bg-slate-800/50 transition-colors" :class="disabledPrayers.isya ? 'opacity-50 bg-slate-50 dark:bg-slate-800/80' : ''">
                <span class="font-bold text-slate-700 dark:text-slate-300 dark:text-slate-600 text-xs uppercase w-1/3">
                    Isya'
                    <span v-if="disabledPrayers.isya" class="block text-[8px] text-pink-600 dark:text-pink-400 normal-case mt-0.5">Terkunci (Haid)</span>
                </span>
                <div class="flex gap-4 sm:gap-6 w-1/2 justify-end items-center">
                    <div class="w-10 flex justify-center bg-slate-200 dark:bg-slate-600 rounded p-1" title="Qobliyah Isya (Ghoiru Muakkad)">
                        <input type="checkbox" v-model="log.details['isya_q']" :disabled="disabledPrayers.isya" class="w-4 h-4 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100 disabled:cursor-not-allowed" />
                    </div>
                    <div class="w-10 flex justify-center">
                        <input type="checkbox" v-model="log.details['isya_b']" :disabled="disabledPrayers.isya" class="w-4 h-4 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100 disabled:cursor-not-allowed" />
                    </div>
                </div>
            </div>
        </div>
        <!-- Desktop Layout (Table) -->
        <table class="hidden md:table w-full text-xs text-center border-collapse">
            <thead class="bg-slate-100 dark:bg-slate-700/50 dark:bg-slate-800/50 text-slate-600 dark:text-slate-400 dark:text-slate-500 text-[9px] uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                <tr>
                    <th colspan="2" class="p-1 border-r border-slate-200 dark:border-slate-700 w-1/5">Subuh</th>
                    <th colspan="2" class="p-1 border-r border-slate-200 dark:border-slate-700 w-1/5">Dhuhur</th>
                    <th colspan="2" class="p-1 border-r border-slate-200 dark:border-slate-700 w-1/5">Asar</th>
                    <th colspan="2" class="p-1 border-r border-slate-200 dark:border-slate-700 w-1/5">Maghrib</th>
                    <th colspan="2" class="p-1 w-1/5">Isya'</th>
                </tr>
                <tr class="text-[8px]">
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700" title="Qobliyah">Q</th>
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700" title="Ba'diyah">B</th>
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700">Q</th>
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700">B</th>
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700">Q</th>
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700">B</th>
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700">Q</th>
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700">B</th>
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700">Q</th>
                    <th class="p-0.5 border-t border-slate-200 dark:border-slate-700">B</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <!-- Subuh -->
                    <td class="p-1 border-r border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:bg-slate-800/50" :class="disabledPrayers.subuh ? 'bg-slate-100 dark:bg-slate-800/80 opacity-60' : 'cursor-pointer'" @click="!disabledPrayers.subuh && (log.details['subuh_q'] = !log.details['subuh_q'])">
                        <input type="checkbox" v-model="log.details['subuh_q']" :disabled="disabledPrayers.subuh" :title="disabledPrayers.subuh ? 'Terkunci (Masa Haid)' : ''" class="w-3.5 h-3.5 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 pointer-events-none dark:bg-slate-700 dark:text-slate-100" />
                    </td>
                    <td class="p-1 border-r border-slate-200 dark:border-slate-700 bg-slate-800" title="Tidak ada Ba'diyah Subuh"></td>
                    <!-- Dhuhur -->
                    <td class="p-1 border-r border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:bg-slate-800/50" :class="disabledPrayers.dhuhur ? 'bg-slate-100 dark:bg-slate-800/80 opacity-60' : 'cursor-pointer'" @click="!disabledPrayers.dhuhur && (log.details['dhuhur_q'] = !log.details['dhuhur_q'])">
                        <input type="checkbox" v-model="log.details['dhuhur_q']" :disabled="disabledPrayers.dhuhur" :title="disabledPrayers.dhuhur ? 'Terkunci (Masa Haid)' : ''" class="w-3.5 h-3.5 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 pointer-events-none dark:bg-slate-700 dark:text-slate-100" />
                    </td>
                    <td class="p-1 border-r border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:bg-slate-800/50" :class="disabledPrayers.dhuhur ? 'bg-slate-100 dark:bg-slate-800/80 opacity-60' : 'cursor-pointer'" @click="!disabledPrayers.dhuhur && (log.details['dhuhur_b'] = !log.details['dhuhur_b'])">
                        <input type="checkbox" v-model="log.details['dhuhur_b']" :disabled="disabledPrayers.dhuhur" :title="disabledPrayers.dhuhur ? 'Terkunci (Masa Haid)' : ''" class="w-3.5 h-3.5 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 pointer-events-none dark:bg-slate-700 dark:text-slate-100" />
                    </td>
                    <!-- Asar -->
                    <td class="p-1 border-r border-slate-200 dark:border-slate-700 bg-slate-200 dark:bg-slate-600 hover:bg-slate-300 dark:hover:bg-slate-500" :class="disabledPrayers.asar ? 'opacity-60' : 'cursor-pointer'" :title="disabledPrayers.asar ? 'Terkunci (Masa Haid)' : 'Qobliyah Asar (Ghoiru Muakkad)'" @click="!disabledPrayers.asar && (log.details['asar_q'] = !log.details['asar_q'])">
                        <input type="checkbox" v-model="log.details['asar_q']" :disabled="disabledPrayers.asar" class="w-3.5 h-3.5 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 pointer-events-none dark:bg-slate-700 dark:text-slate-100" />
                    </td>
                    <td class="p-1 border-r border-slate-200 dark:border-slate-700 bg-slate-800" title="Tidak ada Ba'diyah Asar"></td>
                    <!-- Maghrib -->
                    <td class="p-1 border-r border-slate-200 dark:border-slate-700 bg-slate-200 dark:bg-slate-600 hover:bg-slate-300 dark:hover:bg-slate-500" :class="disabledPrayers.maghrib ? 'opacity-60' : 'cursor-pointer'" :title="disabledPrayers.maghrib ? 'Terkunci (Masa Haid)' : 'Qobliyah Maghrib (Ghoiru Muakkad)'" @click="!disabledPrayers.maghrib && (log.details['maghrib_q'] = !log.details['maghrib_q'])">
                        <input type="checkbox" v-model="log.details['maghrib_q']" :disabled="disabledPrayers.maghrib" class="w-3.5 h-3.5 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 pointer-events-none dark:bg-slate-700 dark:text-slate-100" />
                    </td>
                    <td class="p-1 border-r border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:bg-slate-800/50" :class="disabledPrayers.maghrib ? 'bg-slate-100 dark:bg-slate-800/80 opacity-60' : 'cursor-pointer'" @click="!disabledPrayers.maghrib && (log.details['maghrib_b'] = !log.details['maghrib_b'])">
                        <input type="checkbox" v-model="log.details['maghrib_b']" :disabled="disabledPrayers.maghrib" :title="disabledPrayers.maghrib ? 'Terkunci (Masa Haid)' : ''" class="w-3.5 h-3.5 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 pointer-events-none dark:bg-slate-700 dark:text-slate-100" />
                    </td>
                    <!-- Isya -->
                    <td class="p-1 border-r border-slate-200 dark:border-slate-700 bg-slate-200 dark:bg-slate-600 hover:bg-slate-300 dark:hover:bg-slate-500" :class="disabledPrayers.isya ? 'opacity-60' : 'cursor-pointer'" :title="disabledPrayers.isya ? 'Terkunci (Masa Haid)' : 'Qobliyah Isya (Ghoiru Muakkad)'" @click="!disabledPrayers.isya && (log.details['isya_q'] = !log.details['isya_q'])">
                        <input type="checkbox" v-model="log.details['isya_q']" :disabled="disabledPrayers.isya" class="w-3.5 h-3.5 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 pointer-events-none dark:bg-slate-700 dark:text-slate-100" />
                    </td>
                    <td class="p-1 hover:bg-slate-50 dark:bg-slate-800/50" :class="disabledPrayers.isya ? 'bg-slate-100 dark:bg-slate-800/80 opacity-60' : 'cursor-pointer'" @click="!disabledPrayers.isya && (log.details['isya_b'] = !log.details['isya_b'])">
                        <input type="checkbox" v-model="log.details['isya_b']" :disabled="disabledPrayers.isya" :title="disabledPrayers.isya ? 'Terkunci (Masa Haid)' : ''" class="w-3.5 h-3.5 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 pointer-events-none dark:bg-slate-700 dark:text-slate-100" />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
