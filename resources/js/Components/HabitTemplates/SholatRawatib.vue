<script setup>
defineProps({
    log: {
        type: Object,
        required: true
    }
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
            <div class="flex justify-between items-center p-4 hover:bg-slate-50 dark:bg-slate-800/50 transition-colors">
                <span class="font-bold text-slate-700 dark:text-slate-300 dark:text-slate-600 text-xs uppercase w-1/3">Subuh</span>
                <div class="flex gap-4 sm:gap-6 w-1/2 justify-end items-center">
                    <div class="w-10 flex justify-center">
                        <input type="checkbox" v-model="log.details['subuh_q']" class="w-4 h-4 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100" />
                    </div>
                    <div class="w-10 flex justify-center">
                        <div class="w-4 h-4 bg-slate-800 rounded-sm" title="Tidak ada Ba'diyah Subuh"></div>
                    </div>
                </div>
            </div>
            <!-- Dhuhur -->
            <div class="flex justify-between items-center p-4 hover:bg-slate-50 dark:bg-slate-800/50 transition-colors">
                <span class="font-bold text-slate-700 dark:text-slate-300 dark:text-slate-600 text-xs uppercase w-1/3">Dhuhur</span>
                <div class="flex gap-4 sm:gap-6 w-1/2 justify-end items-center">
                    <div class="w-10 flex justify-center">
                        <input type="checkbox" v-model="log.details['dhuhur_q']" class="w-4 h-4 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100" />
                    </div>
                    <div class="w-10 flex justify-center">
                        <input type="checkbox" v-model="log.details['dhuhur_b']" class="w-4 h-4 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100" />
                    </div>
                </div>
            </div>
            <!-- Asar -->
            <div class="flex justify-between items-center p-4 hover:bg-slate-50 dark:bg-slate-800/50 transition-colors">
                <span class="font-bold text-slate-700 dark:text-slate-300 dark:text-slate-600 text-xs uppercase w-1/3">Asar</span>
                <div class="flex gap-4 sm:gap-6 w-1/2 justify-end items-center">
                    <div class="w-10 flex justify-center bg-slate-200 dark:bg-slate-600 rounded p-1" title="Qobliyah Asar (Ghoiru Muakkad)">
                        <input type="checkbox" v-model="log.details['asar_q']" class="w-4 h-4 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100" />
                    </div>
                    <div class="w-10 flex justify-center">
                        <div class="w-4 h-4 bg-slate-800 rounded-sm" title="Tidak ada Ba'diyah Asar"></div>
                    </div>
                </div>
            </div>
            <!-- Maghrib -->
            <div class="flex justify-between items-center p-4 hover:bg-slate-50 dark:bg-slate-800/50 transition-colors">
                <span class="font-bold text-slate-700 dark:text-slate-300 dark:text-slate-600 text-xs uppercase w-1/3">Maghrib</span>
                <div class="flex gap-4 sm:gap-6 w-1/2 justify-end items-center">
                    <div class="w-10 flex justify-center bg-slate-200 dark:bg-slate-600 rounded p-1" title="Qobliyah Maghrib (Ghoiru Muakkad)">
                        <input type="checkbox" v-model="log.details['maghrib_q']" class="w-4 h-4 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100" />
                    </div>
                    <div class="w-10 flex justify-center">
                        <input type="checkbox" v-model="log.details['maghrib_b']" class="w-4 h-4 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100" />
                    </div>
                </div>
            </div>
            <!-- Isya -->
            <div class="flex justify-between items-center p-4 hover:bg-slate-50 dark:bg-slate-800/50 transition-colors">
                <span class="font-bold text-slate-700 dark:text-slate-300 dark:text-slate-600 text-xs uppercase w-1/3">Isya'</span>
                <div class="flex gap-4 sm:gap-6 w-1/2 justify-end items-center">
                    <div class="w-10 flex justify-center bg-slate-200 dark:bg-slate-600 rounded p-1" title="Qobliyah Isya (Ghoiru Muakkad)">
                        <input type="checkbox" v-model="log.details['isya_q']" class="w-4 h-4 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100" />
                    </div>
                    <div class="w-10 flex justify-center">
                        <input type="checkbox" v-model="log.details['isya_b']" class="w-4 h-4 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100" />
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
                    <td class="p-1 border-r border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:bg-slate-800/50 cursor-pointer" @click="log.details['subuh_q'] = !log.details['subuh_q']">
                        <input type="checkbox" v-model="log.details['subuh_q']" class="w-3.5 h-3.5 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 pointer-events-none dark:bg-slate-700 dark:text-slate-100" />
                    </td>
                    <td class="p-1 border-r border-slate-200 dark:border-slate-700 bg-slate-800" title="Tidak ada Ba'diyah Subuh"></td>
                    <!-- Dhuhur -->
                    <td class="p-1 border-r border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:bg-slate-800/50 cursor-pointer" @click="log.details['dhuhur_q'] = !log.details['dhuhur_q']">
                        <input type="checkbox" v-model="log.details['dhuhur_q']" class="w-3.5 h-3.5 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 pointer-events-none dark:bg-slate-700 dark:text-slate-100" />
                    </td>
                    <td class="p-1 border-r border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:bg-slate-800/50 cursor-pointer" @click="log.details['dhuhur_b'] = !log.details['dhuhur_b']">
                        <input type="checkbox" v-model="log.details['dhuhur_b']" class="w-3.5 h-3.5 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 pointer-events-none dark:bg-slate-700 dark:text-slate-100" />
                    </td>
                    <!-- Asar -->
                    <td class="p-1 border-r border-slate-200 dark:border-slate-700 bg-slate-200 dark:bg-slate-600 hover:bg-slate-300 dark:hover:bg-slate-500 cursor-pointer" title="Qobliyah Asar (Ghoiru Muakkad)" @click="log.details['asar_q'] = !log.details['asar_q']">
                        <input type="checkbox" v-model="log.details['asar_q']" class="w-3.5 h-3.5 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 pointer-events-none dark:bg-slate-700 dark:text-slate-100" />
                    </td>
                    <td class="p-1 border-r border-slate-200 dark:border-slate-700 bg-slate-800" title="Tidak ada Ba'diyah Asar"></td>
                    <!-- Maghrib -->
                    <td class="p-1 border-r border-slate-200 dark:border-slate-700 bg-slate-200 dark:bg-slate-600 hover:bg-slate-300 dark:hover:bg-slate-500 cursor-pointer" title="Qobliyah Maghrib (Ghoiru Muakkad)" @click="log.details['maghrib_q'] = !log.details['maghrib_q']">
                        <input type="checkbox" v-model="log.details['maghrib_q']" class="w-3.5 h-3.5 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 pointer-events-none dark:bg-slate-700 dark:text-slate-100" />
                    </td>
                    <td class="p-1 border-r border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:bg-slate-800/50 cursor-pointer" @click="log.details['maghrib_b'] = !log.details['maghrib_b']">
                        <input type="checkbox" v-model="log.details['maghrib_b']" class="w-3.5 h-3.5 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 pointer-events-none dark:bg-slate-700 dark:text-slate-100" />
                    </td>
                    <!-- Isya -->
                    <td class="p-1 border-r border-slate-200 dark:border-slate-700 bg-slate-200 dark:bg-slate-600 hover:bg-slate-300 dark:hover:bg-slate-500 cursor-pointer" title="Qobliyah Isya (Ghoiru Muakkad)" @click="log.details['isya_q'] = !log.details['isya_q']">
                        <input type="checkbox" v-model="log.details['isya_q']" class="w-3.5 h-3.5 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 pointer-events-none dark:bg-slate-700 dark:text-slate-100" />
                    </td>
                    <td class="p-1 hover:bg-slate-50 dark:bg-slate-800/50 cursor-pointer" @click="log.details['isya_b'] = !log.details['isya_b']">
                        <input type="checkbox" v-model="log.details['isya_b']" class="w-3.5 h-3.5 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 pointer-events-none dark:bg-slate-700 dark:text-slate-100" />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
