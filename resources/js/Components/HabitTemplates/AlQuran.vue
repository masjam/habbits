<script setup>
import SurahSelect from '@/Components/SurahSelect.vue'

defineProps({
    log: {
        type: Object,
        required: true
    }
});
</script>

<template>
    <div class="p-3 grid grid-cols-2 gap-3 text-xs">
        <div class="col-span-2">
            <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 dark:text-slate-500 mb-1">Durasi (Menit)</label>
            <input type="number" min="0" v-model="log.details['durasi']" class="w-full p-1.5 text-xs text-center border-slate-300 dark:border-slate-600 rounded focus:ring-emerald-500 focus:border-emerald-500 dark:bg-slate-700 dark:text-slate-100" />
        </div>
        <div class="col-span-1 border border-slate-200 dark:border-slate-700 rounded p-2 bg-slate-50 dark:bg-slate-800/50 relative z-40">
            <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 dark:text-slate-500 mb-1 text-center">AWAL</label>
            <div class="mb-1">
                <SurahSelect 
                    v-model="log.details['surat_awal']"
                    @update:maxAyat="(max) => log.details['max_ayat_awal'] = max"
                    placeholder="Pilih Surat Awal"
                />
            </div>
            <div class="relative">
                <input type="number" min="1" :max="log.details['max_ayat_awal'] || null" placeholder="Ayat" v-model="log.details['ayat_awal']" class="w-full p-1.5 pr-10 text-xs border-slate-300 dark:border-slate-600 rounded focus:ring-emerald-500 focus:border-emerald-500 dark:bg-slate-700 dark:text-slate-100" />
                <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-[9px] text-slate-400 dark:text-slate-500 font-bold">
                    / {{ log.details['max_ayat_awal'] || '?' }}
                </div>
            </div>
        </div>
        <div class="col-span-1 border border-slate-200 dark:border-slate-700 rounded p-2 bg-slate-50 dark:bg-slate-800/50 relative z-30">
            <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 dark:text-slate-500 mb-1 text-center">AKHIR</label>
            <div class="mb-1">
                <SurahSelect 
                    v-model="log.details['surat_akhir']"
                    @update:maxAyat="(max) => log.details['max_ayat_akhir'] = max"
                    placeholder="Pilih Surat Akhir"
                />
            </div>
            <div class="relative">
                <input type="number" min="1" :max="log.details['max_ayat_akhir'] || null" placeholder="Ayat" v-model="log.details['ayat_akhir']" class="w-full p-1.5 pr-10 text-xs border-slate-300 dark:border-slate-600 rounded focus:ring-emerald-500 focus:border-emerald-500 dark:bg-slate-700 dark:text-slate-100" />
                <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-[9px] text-slate-400 dark:text-slate-500 font-bold">
                    / {{ log.details['max_ayat_akhir'] || '?' }}
                </div>
            </div>
        </div>
    </div>
</template>
