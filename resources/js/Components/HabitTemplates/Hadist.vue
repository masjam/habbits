<script setup>
defineProps({
    log: {
        type: Object,
        required: true
    }
});
</script>

<template>
    <div class="p-4 flex flex-col gap-4">
        <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" v-model="log.nilai_input" :true-value="1" :false-value="0" class="w-6 h-6 text-emerald-600 rounded border-slate-300 dark:border-slate-600 focus:ring-emerald-500 dark:bg-slate-700 dark:text-slate-100" />
            <div class="flex flex-col">
                <span class="text-sm font-bold text-slate-700 dark:text-slate-300 dark:text-slate-600">Selesai Dikerjakan</span>
                <span class="text-[10px] text-slate-500 dark:text-slate-400 dark:text-slate-500">Centang jika sudah membaca hadist hari ini</span>
            </div>
        </label>
        
        <!-- Optional Form when checked -->
        <div v-if="log.nilai_input > 0" class="space-y-3 bg-slate-50 dark:bg-slate-800/50 p-4 rounded-xl border border-slate-200 dark:border-slate-700">
            <p class="text-xs text-slate-500 dark:text-slate-400 dark:text-slate-500 italic mb-1 font-medium bg-white dark:bg-slate-800 p-2 rounded border border-slate-100 dark:border-slate-700 shadow-sm border-l-2 border-l-amber-400">
                📌 Form keterangan di bawah ini opsional (namun durasi wajib diisi jika ada target minimal).
            </p>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 dark:text-slate-500 mb-1 uppercase tracking-wider">Durasi (Menit)</label>
                <input type="number" min="0" v-model="log.details['durasi']" placeholder="Contoh: 5" class="w-full p-2 text-xs border-slate-300 dark:border-slate-600 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 shadow-sm dark:bg-slate-700 dark:text-slate-100" />
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 dark:text-slate-500 mb-1 uppercase tracking-wider">Tema Hadist</label>
                <input type="text" v-model="log.details['tema']" placeholder="Contoh: Menjaga lisan" class="w-full p-2 text-xs border-slate-300 dark:border-slate-600 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 shadow-sm dark:bg-slate-700 dark:text-slate-100" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 dark:text-slate-500 mb-1 uppercase tracking-wider">Riwayat</label>
                    <input type="text" v-model="log.details['riwayat']" placeholder="Cth: Bukhari & Muslim" class="w-full p-2 text-xs border-slate-300 dark:border-slate-600 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 shadow-sm dark:bg-slate-700 dark:text-slate-100" />
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 dark:text-slate-500 mb-1 uppercase tracking-wider">Sumber/Kitab</label>
                    <input type="text" v-model="log.details['sumber']" placeholder="Cth: Riyadhus Shalihin" class="w-full p-2 text-xs border-slate-300 dark:border-slate-600 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 shadow-sm dark:bg-slate-700 dark:text-slate-100" />
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 dark:text-slate-500 mb-1 uppercase tracking-wider">Isi / Kesimpulan Hadist</label>
                <textarea rows="3" v-model="log.details['isi']" placeholder="Tuliskan isi atau pelajaran yang bisa diambil..." class="w-full p-2 text-xs border-slate-300 dark:border-slate-600 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 shadow-sm resize-none dark:bg-slate-700 dark:text-slate-100"></textarea>
            </div>
        </div>
    </div>
</template>
