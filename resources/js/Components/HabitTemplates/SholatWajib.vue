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
                <div class="flex gap-4 sm:gap-6 w-3/5 sm:w-1/2 justify-end">
                    <span class="w-8 text-center" title="Jamaah Masjid">JM</span>
                    <span class="w-8 text-center" title="Jamaah di Rumah">JD</span>
                    <span class="w-8 text-center" title="Munfarid">M</span>
                </div>
            </div>
            <div v-for="waktu in ['subuh', 'dhuhur', 'asar', 'maghrib', 'isya']" :key="'mob-'+waktu" class="flex justify-between items-center p-4 hover:bg-slate-50 dark:bg-slate-800/50 transition-colors">
                <span class="font-bold text-slate-700 dark:text-slate-300 dark:text-slate-600 text-xs uppercase w-1/3">{{ waktu }}</span>
                <div class="flex gap-4 sm:gap-6 w-3/5 sm:w-1/2 justify-end">
                    <div class="w-8 flex justify-center">
                        <input type="radio" :name="`sw_mob_${waktu}_${log.habit_id}`" value="JM" v-model="log.details[waktu]" class="w-4 h-4 text-emerald-600 focus:ring-emerald-500 cursor-pointer dark:bg-slate-700 dark:text-slate-100" />
                    </div>
                    <div class="w-8 flex justify-center">
                        <input type="radio" :name="`sw_mob_${waktu}_${log.habit_id}`" value="JD" v-model="log.details[waktu]" class="w-4 h-4 text-emerald-600 focus:ring-emerald-500 cursor-pointer dark:bg-slate-700 dark:text-slate-100" />
                    </div>
                    <div class="w-8 flex justify-center">
                        <input type="radio" :name="`sw_mob_${waktu}_${log.habit_id}`" value="M" v-model="log.details[waktu]" class="w-4 h-4 text-emerald-600 focus:ring-emerald-500 cursor-pointer dark:bg-slate-700 dark:text-slate-100" />
                    </div>
                </div>
            </div>
        </div>
        <!-- Desktop Layout (Table) -->
        <table class="hidden md:table w-full text-xs text-center border-collapse">
            <thead class="bg-slate-100 dark:bg-slate-700/50 dark:bg-slate-800/50 text-slate-600 dark:text-slate-400 dark:text-slate-500 text-[9px] uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                <tr>
                    <th colspan="3" class="p-1 border-r border-slate-200 dark:border-slate-700 w-1/5">Subuh</th>
                    <th colspan="3" class="p-1 border-r border-slate-200 dark:border-slate-700 w-1/5">Dhuhur</th>
                    <th colspan="3" class="p-1 border-r border-slate-200 dark:border-slate-700 w-1/5">Asar</th>
                    <th colspan="3" class="p-1 border-r border-slate-200 dark:border-slate-700 w-1/5">Maghrib</th>
                    <th colspan="3" class="p-1 w-1/5">Isya'</th>
                </tr>
                <tr class="text-[8px]">
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700" title="Jamaah Masjid">JM</th>
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700" title="Jamaah di Rumah">JD</th>
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700" title="Munfarid (Sendiri)">M</th>
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700">JM</th>
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700">JD</th>
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700">M</th>
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700">JM</th>
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700">JD</th>
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700">M</th>
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700">JM</th>
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700">JD</th>
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700">M</th>
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700">JM</th>
                    <th class="p-0.5 border-r border-t border-slate-200 dark:border-slate-700">JD</th>
                    <th class="p-0.5 border-t border-slate-200 dark:border-slate-700">M</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <template v-for="waktu in ['subuh', 'dhuhur', 'asar', 'maghrib', 'isya']" :key="waktu">
                        <td class="p-1 border-r border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:bg-slate-800/50">
                            <input type="radio" :name="`sw_desk_${waktu}_${log.habit_id}`" value="JM" v-model="log.details[waktu]" class="w-3 h-3 text-emerald-600 focus:ring-emerald-500 cursor-pointer dark:bg-slate-700 dark:text-slate-100" />
                        </td>
                        <td class="p-1 border-r border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:bg-slate-800/50">
                            <input type="radio" :name="`sw_desk_${waktu}_${log.habit_id}`" value="JD" v-model="log.details[waktu]" class="w-3 h-3 text-emerald-600 focus:ring-emerald-500 cursor-pointer dark:bg-slate-700 dark:text-slate-100" />
                        </td>
                        <td class="p-1 border-r border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 hover:bg-slate-100 dark:bg-slate-700">
                            <input type="radio" :name="`sw_desk_${waktu}_${log.habit_id}`" value="M" v-model="log.details[waktu]" class="w-3 h-3 text-emerald-600 focus:ring-emerald-500 cursor-pointer dark:bg-slate-700 dark:text-slate-100" />
                        </td>
                    </template>
                </tr>
            </tbody>
        </table>
    </div>
</template>
