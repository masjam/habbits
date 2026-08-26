<script setup>
import { computed, ref, watch } from 'vue'
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SurahSelect from '@/Components/SurahSelect.vue'
import SholatWajib from '@/Components/HabitTemplates/SholatWajib.vue'
import SholatRawatib from '@/Components/HabitTemplates/SholatRawatib.vue'
import AlQuran from '@/Components/HabitTemplates/AlQuran.vue'
import Hadist from '@/Components/HabitTemplates/Hadist.vue'
import Buku from '@/Components/HabitTemplates/Buku.vue'

const props = defineProps({
    habits:       { type: Array,   default: () => [] },
    logsHariIni:  { type: Object,  default: () => ({}) },
    isSedangHaid: { type: Boolean, default: false },
    tanggal:      { type: String,  default: '' },
    today:        { type: String,  default: '' },
})

const page = usePage()

// --- Date constraints logic ---
const maxDateStr = props.today;
const todaySplit = props.today.split('-');
const todayY = parseInt(todaySplit[0]);
const todayM = parseInt(todaySplit[1]) - 1; // 0-indexed
const todayD = parseInt(todaySplit[2]);

let minDateObj = new Date(todayY, todayM, 1);
if (todayD <= 4) {
    minDateObj = new Date(todayY, todayM - 1, 1);
}
const toYMD = (date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};
const minDateStr = toYMD(minDateObj);

const selectedDate = ref(props.tanggal);

// Jika tanggal diubah, reload halaman dengan parameter date
watch(selectedDate, (newDate) => {
    if (newDate && newDate !== props.tanggal) {
        router.get(route('habit.form'), { date: newDate }, { preserveState: true, preserveScroll: true });
    }
})

// --- Tooltip Balon Deskripsi Habit ---
const activeTooltipHabitId = ref(null)
let tooltipTimeout = null

const showHabitTooltip = (habit) => {
    if (!habit || (!habit.deskripsi && !habit.nama_habit)) return
    if (tooltipTimeout) clearTimeout(tooltipTimeout)
    
    if (activeTooltipHabitId.value === habit.id) {
        activeTooltipHabitId.value = null
        return
    }

    activeTooltipHabitId.value = habit.id
    tooltipTimeout = setTimeout(() => {
        activeTooltipHabitId.value = null
    }, 2200) // Muncul selama 2 detik
}

// --- Form Initialization ---
const activeTabId = ref(props.habits.length > 0 ? props.habits[0].id : null);

const initLogs = () => {
    return props.habits.map(habit => {
        const existingLog = props.logsHariIni[habit.id];
        return {
            habit_id: habit.id,
            nilai_input: existingLog ? existingLog.nilai_input : (['default', 'boolean'].includes(habit.template) ? 0 : ''),
            details: existingLog?.details ? existingLog.details : {}
        }
    });
};

const form = useForm({
    tanggal: props.tanggal,
    logs: initLogs(),
})

// Perbarui form saat props berubah (karena watch tanggal me-reload data)
watch(() => props.tanggal, (newVal) => {
    form.tanggal = newVal;
    form.logs = initLogs();
});

// --- Modal Konfirmasi Rekap ---
const showConfirmModal = ref(false)
const recapItems = ref([])

const buildRecapItems = () => {
    return props.habits.map(habit => {
        const logIdx = form.logs.findIndex(l => l.habit_id === habit.id)
        const log = logIdx >= 0 ? form.logs[logIdx] : null
        const details = log?.details || {}
        const nilai = log?.nilai_input

        let lines = []
        let status = 'empty'

        if (habit.template === 'sholat_wajib') {
            const waktuList = ['subuh', 'dhuhur', 'asar', 'maghrib', 'isya']
            const filled = waktuList.filter(w => details[w])
            lines = waktuList.map(w => ({
                label: w.charAt(0).toUpperCase() + w.slice(1),
                value: details[w] || '–',
                filled: !!details[w]
            }))
            status = filled.length === 5 ? 'done' : filled.length > 0 ? 'partial' : 'empty'

        } else if (habit.template === 'sholat_rawatib') {
            const slots = [
                { key: 'subuh_q', label: 'Subuh Q' },
                { key: 'dhuhur_q', label: 'Dhuhur Q' }, { key: 'dhuhur_b', label: 'Dhuhur B' },
                { key: 'asar_q', label: 'Asar Q' },
                { key: 'maghrib_q', label: 'Maghrib Q' }, { key: 'maghrib_b', label: 'Maghrib B' },
                { key: 'isya_q', label: "Isya Q" }, { key: 'isya_b', label: "Isya B" },
            ]
            const filled = slots.filter(s => details[s.key])
            lines = slots.map(s => ({ label: s.label, value: details[s.key] ? '✓' : '–', filled: !!details[s.key] }))
            status = filled.length > 0 ? (filled.length === slots.length ? 'done' : 'partial') : 'empty'

        } else if (habit.template === 'quran') {
            const durasi = details['durasi'] || 0
            const suratAwal = details['surat_awal'] || ''
            const ayatAwal = details['ayat_awal'] || ''
            const suratAkhir = details['surat_akhir'] || ''
            const ayatAkhir = details['ayat_akhir'] || ''
            lines = [
                { label: 'Durasi', value: durasi ? `${durasi} menit` : '–', filled: !!durasi },
                { label: 'Awal', value: suratAwal ? `${suratAwal}:${ayatAwal || '?'}` : '–', filled: !!suratAwal },
                { label: 'Akhir', value: suratAkhir ? `${suratAkhir}:${ayatAkhir || '?'}` : '–', filled: !!suratAkhir },
            ]
            status = durasi > 0 ? 'done' : suratAwal ? 'partial' : 'empty'

        } else if (habit.template === 'tahajud') {
            const rakaat = nilai || 0
            const ket = details['keterangan'] || ''
            lines = [
                { label: 'Rakaat', value: rakaat > 0 ? `${rakaat} rakaat` : '–', filled: rakaat > 0 },
                { label: 'Waktu', value: ket || '–', filled: !!ket },
            ]
            status = rakaat > 0 ? 'done' : 'empty'

        } else if (habit.template === 'dhuha' || habit.template === 'integer') {
            const jumlah = nilai || 0
            const satuan = habit.satuan || 'rakaat'
            lines = [{ label: satuan, value: jumlah > 0 ? `${jumlah} ${satuan}` : '–', filled: jumlah > 0 }]
            status = jumlah > 0 ? 'done' : 'empty'

        } else if (habit.template === 'hadist') {
            const done = Number(nilai) > 0
            const tema = details['tema'] || ''
            lines = [{ label: 'Status', value: done ? 'Selesai dibaca' : 'Belum', filled: done }]
            if (done && tema) lines.push({ label: 'Tema', value: tema, filled: true })
            status = done ? 'done' : 'empty'

        } else if (habit.template === 'buku') {
            const done = Number(nilai) > 0
            const judul = details['judul'] || ''
            const durasi = details['durasi'] || ''
            lines = [{ label: 'Status', value: done ? 'Selesai dibaca' : 'Belum', filled: done }]
            if (done && judul) lines.push({ label: 'Judul', value: judul, filled: true })
            if (done && durasi) lines.push({ label: 'Durasi', value: `${durasi} menit`, filled: true })
            status = done ? 'done' : 'empty'

        } else {
            const done = Number(nilai) > 0
            lines = [{ label: 'Status', value: done ? 'Selesai ✓' : 'Belum ✗', filled: done }]
            status = done ? 'done' : 'empty'
        }

        return { habit, lines, status }
    })
}

const openConfirmModal = () => {
    recapItems.value = buildRecapItems()
    showConfirmModal.value = true
}

const closeConfirmModal = () => {
    showConfirmModal.value = false
}

const doSubmit = () => {
    showConfirmModal.value = false
    form.post(route('habit.form.store'), {
        preserveScroll: true,
    })
}

// --- Helper Functions ---
const getLogIndex = (habitId) => {
    return form.logs.findIndex(log => log.habit_id === habitId);
}

const toggleBoolean = (index) => {
    form.logs[index].nilai_input = form.logs[index].nilai_input ? 0 : 1;
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Form Ibadah Harian" />

        <div class="w-full space-y-6">
            
            <!-- Header Section -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-6 sm:p-8 relative overflow-hidden">
                <div class="absolute -right-20 -top-20 w-64 h-64 bg-emerald-50 rounded-full blur-3xl pointer-events-none" aria-hidden="true" />
                
                <div class="relative z-10">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-200">Laporan Ibadah Harian</h1>
                            <p class="text-slate-500 dark:text-slate-400 dark:text-slate-500 mt-1">Lengkapi tabel capaian Anda di bawah ini.</p>
                            <span v-if="isSedangHaid" class="inline-flex items-center gap-1.5 mt-3 px-3 py-1 bg-pink-50 text-pink-700 text-xs font-bold rounded-full border border-pink-100">
                                <span class="w-1.5 h-1.5 rounded-full bg-pink-500 animate-pulse" />
                                Mode Haid Aktif
                            </span>
                        </div>
                        
                        <!-- Date Picker -->
                        <div class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-xl border border-slate-200 dark:border-slate-700">
                            <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 dark:text-slate-500 mb-1.5 uppercase tracking-wider">Tanggal Laporan</label>
                            <input 
                                type="date" 
                                v-model="selectedDate"
                                :min="minDateStr"
                                :max="maxDateStr"
                                class="w-full sm:w-auto px-4 py-2 border-slate-300 dark:border-slate-600 rounded-lg text-sm font-semibold text-slate-700 dark:text-slate-300 dark:text-slate-600 focus:ring-emerald-500 focus:border-emerald-500 dark:bg-slate-700 dark:text-slate-100"
                            />
                            <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 max-w-[200px]">Pilih tanggal laporan (Batas: maks tgl 4 untuk bulan lalu).</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error Alerts -->
            <div v-if="Object.keys(form.errors).length > 0 || page.props.errors?.date" class="p-4 rounded-xl bg-red-50 border border-red-100 flex flex-col gap-1">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span class="text-sm font-bold text-red-800">Terdapat Kesalahan:</span>
                </div>
                <div class="text-xs text-red-700 ml-7 space-y-1">
                    <p v-if="page.props.errors?.date">{{ page.props.errors.date }}</p>
                    <p v-else>Silakan periksa kembali input Anda.</p>
                </div>
            </div>

            <form @submit.prevent="openConfirmModal" class="pb-20">
                
                <!-- Mobile Dropdown (Visible only on small screens) -->
                <div class="md:hidden mb-4 relative">
                    <label for="habit-select" class="sr-only">Pilih Ibadah</label>
                    <select 
                        id="habit-select"
                        v-model="activeTabId"
                        class="w-full pl-4 pr-10 py-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-semibold text-slate-700 dark:text-slate-300 focus:ring-emerald-500 focus:border-emerald-500 shadow-sm appearance-none"
                    >
                        <option 
                            v-for="habit in habits" 
                            :key="'opt-'+habit.id"
                            :value="habit.id"
                        >
                            {{ habit.nama_habit.replace('[Pengganti Haid] ', '') }}
                        </option>
                    </select>
                    <!-- Custom Arrow Icon -->
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-500 dark:text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                </div>

                <!-- Cards Layout (Masonry Columns) -->
                <div class="columns-1 lg:columns-2 2xl:columns-3 gap-6">
                    <div 
                        v-for="habit in habits" 
                        :key="habit.id" 
                        :class="[
                            'bg-white dark:bg-slate-800 rounded-xl border shadow-sm transition-all duration-200 break-inside-avoid mb-6',
                            activeTabId === habit.id ? 'border-emerald-300 ring-1 ring-emerald-50' : 'border-slate-200 dark:border-slate-700',
                            'md:block', // Selalu tampil di desktop
                            activeTabId === habit.id ? 'block' : 'hidden' // Di mobile hanya tampil jika aktif
                        ]"
                    >
                        
                        <!-- Habit Header (Dengan Balon Pop-up Deskripsi) -->
                        <div class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 px-4 py-2.5 flex justify-between items-center rounded-t-xl relative">
                            <div 
                                class="flex-1 pr-2 truncate cursor-pointer select-none group"
                                @click="showHabitTooltip(habit)"
                                title="Klik untuk melihat deskripsi lengkap"
                            >
                                <div class="flex items-center gap-1.5 truncate">
                                    <h3 class="font-bold text-slate-800 dark:text-slate-200 text-xs truncate">{{ habit.nama_habit }}</h3>
                                    <span v-if="habit.deskripsi" class="text-slate-400 group-hover:text-emerald-600 transition-colors shrink-0">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </span>
                                </div>
                                <p v-if="habit.deskripsi" class="text-[9px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">{{ habit.deskripsi }}</p>

                                <!-- Balon Pop-up Deskripsi (Muncul 2 detik saat diklik) -->
                                <transition
                                    enter-active-class="transition duration-200 ease-out"
                                    enter-from-class="opacity-0 -translate-y-1 scale-95"
                                    enter-to-class="opacity-100 translate-y-0 scale-100"
                                    leave-active-class="transition duration-150 ease-in"
                                    leave-from-class="opacity-100 translate-y-0 scale-100"
                                    leave-to-class="opacity-0 -translate-y-1 scale-95"
                                >
                                    <div 
                                        v-if="activeTooltipHabitId === habit.id" 
                                        class="absolute left-3 right-3 top-full mt-1.5 z-40 p-3 bg-slate-900/95 text-white text-xs rounded-xl shadow-2xl backdrop-blur-md border border-slate-700/80 pointer-events-none"
                                    >
                                        <!-- Arrow Balon -->
                                        <div class="absolute -top-1 left-6 w-2.5 h-2.5 bg-slate-900 rotate-45 border-l border-t border-slate-700/80"></div>
                                        
                                        <div class="font-bold text-xs text-emerald-400 mb-1 flex items-center justify-between">
                                            <span>{{ habit.nama_habit }}</span>
                                            <span class="text-[9px] text-emerald-300 bg-emerald-950/80 px-1.5 py-0.5 rounded border border-emerald-800">
                                                Max {{ habit.skor_maksimal }}pt
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-slate-200 leading-relaxed font-normal whitespace-normal">
                                            {{ habit.deskripsi || 'Tidak ada deskripsi tambahan.' }}
                                        </div>
                                    </div>
                                </transition>
                            </div>
                            <span class="text-[9px] font-bold px-1.5 py-0.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 rounded whitespace-nowrap shrink-0">
                                Max {{ habit.skor_maksimal }}pt
                            </span>
                        </div>

                        <!-- Habit Body (Dynamic Templates) -->
                        <div :class="['p-0 max-w-full rounded-b-xl', ['quran', 'tahajud', 'dhuha', 'integer', 'default', 'boolean'].includes(habit.template) ? '' : 'overflow-x-auto overflow-y-hidden']">
                            
                            <!-- 1. SHOLAT WAJIB -->
                            <div v-if="habit.template === 'sholat_wajib'">
                                <SholatWajib :log="form.logs[getLogIndex(habit.id)]" />
                            </div>

                            <!-- 2. SHOLAT RAWATIB -->
                            <div v-else-if="habit.template === 'sholat_rawatib'">
                                <SholatRawatib :log="form.logs[getLogIndex(habit.id)]" />
                            </div>

                            <!-- 3. AL QURAN -->
                            <div v-else-if="habit.template === 'quran'">
                                <AlQuran :log="form.logs[getLogIndex(habit.id)]" />
                            </div>

                            <!-- 4. TAHAJUD -->
                            <div v-else-if="habit.template === 'tahajud'" class="p-3 flex gap-3 text-xs">
                                <div class="w-1/3">
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 dark:text-slate-500 mb-1">Rakaat</label>
                                    <input type="number" min="0" v-model="form.logs[getLogIndex(habit.id)].nilai_input" class="w-full p-1.5 text-center border-slate-300 dark:border-slate-600 rounded focus:ring-emerald-500 focus:border-emerald-500 dark:bg-slate-700 dark:text-slate-100" />
                                </div>
                                <div class="w-2/3">
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 dark:text-slate-500 mb-1">Keterangan</label>
                                    <select v-model="form.logs[getLogIndex(habit.id)].details['keterangan']" class="w-full p-1.5 text-xs border-slate-300 dark:border-slate-600 rounded focus:ring-emerald-500 focus:border-emerald-500 text-slate-700 dark:text-slate-300 dark:text-slate-600 dark:bg-slate-700 dark:text-slate-100">
                                        <option value="" disabled selected>Pilih...</option>
                                        <option value="Sebelum Tidur">Sebelum Tidur</option>
                                        <option value="Sesudah Tidur">Sesudah Tidur</option>
                                    </select>
                                </div>
                            </div>

                            <!-- 5. DHUHA ATAU INTEGER BIASA -->
                            <div v-else-if="habit.template === 'dhuha' || habit.template === 'integer'" class="p-3">
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 dark:text-slate-500 mb-1 uppercase">{{ habit.satuan || 'Jumlah' }}</label>
                                <input type="number" min="0" v-model="form.logs[getLogIndex(habit.id)].nilai_input" class="w-full p-1.5 text-center border-slate-300 dark:border-slate-600 rounded focus:ring-emerald-500 focus:border-emerald-500 dark:bg-slate-700 dark:text-slate-100" />
                            </div>

                            <!-- 6. HADIST -->
                            <div v-else-if="habit.template === 'hadist'">
                                <Hadist :log="form.logs[getLogIndex(habit.id)]" />
                            </div>

                            <!-- 7. BUKU -->
                            <div v-else-if="habit.template === 'buku'">
                                <Buku :log="form.logs[getLogIndex(habit.id)]" />
                            </div>
                            <div v-else class="p-3 flex items-center gap-3">
                                <button 
                                    type="button"
                                    @click="toggleBoolean(getLogIndex(habit.id))"
                                    :class="[
                                        'w-8 h-8 rounded flex items-center justify-center border-2 transition-colors flex-shrink-0',
                                        form.logs[getLogIndex(habit.id)].nilai_input > 0 
                                            ? 'bg-emerald-500 border-emerald-500 text-white' 
                                            : 'bg-slate-50 dark:bg-slate-800/50 border-slate-300 dark:border-slate-600 text-transparent hover:border-emerald-400 hover:bg-white dark:bg-slate-800'
                                    ]"
                                >
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                                <div class="cursor-pointer select-none" @click="toggleBoolean(getLogIndex(habit.id))">
                                    <span class="text-xs font-bold" :class="form.logs[getLogIndex(habit.id)].nilai_input > 0 ? 'text-emerald-700' : 'text-slate-600 dark:text-slate-400 dark:text-slate-500'">
                                        {{ form.logs[getLogIndex(habit.id)].nilai_input > 0 ? 'Selesai Dikerjakan' : 'Belum Dikerjakan' }}
                                    </span>
                                    <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">Ketuk kotak/teks ini untuk menandai.</p>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Submit Button Area -->
                <div class="fixed bottom-0 left-0 right-0 md:left-64 bg-white dark:bg-slate-800/80 backdrop-blur-md border-t border-slate-200 dark:border-slate-700 p-4 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] z-20">
                    <div class="max-w-5xl mx-auto flex items-center justify-between">
                        <p class="text-sm text-slate-500 dark:text-slate-400 dark:text-slate-500 hidden sm:block">
                            Pastikan data <span class="font-bold text-slate-700 dark:text-slate-300 dark:text-slate-600">{{ selectedDate }}</span> telah diisi dengan benar.
                        </p>
                        
                        <button 
                            type="submit" 
                            :disabled="form.processing"
                            class="w-full sm:w-auto inline-flex justify-center items-center gap-2 px-8 py-2.5 bg-emerald-600 rounded-xl font-bold text-white shadow-sm hover:bg-emerald-700 hover:shadow-md focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 active:bg-emerald-800 disabled:opacity-50 transition-all"
                        >
                            <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 w-5 h-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span v-if="form.processing">Menyimpan...</span>
                            <span v-else>Simpan Laporan</span>
                        </button>
                    </div>
                </div>

            </form>

        </div>

        <!-- =============================================
             MODAL KONFIRMASI REKAP HABIT
             ============================================= -->
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div 
                v-if="showConfirmModal" 
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="closeConfirmModal" />

                <!-- Modal Panel -->
                <transition
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
                    enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                >
                    <div 
                        v-if="showConfirmModal"
                        class="relative z-10 w-full max-w-md sm:max-w-lg bg-white dark:bg-slate-900 rounded-3xl shadow-2xl overflow-hidden flex flex-col"
                        style="max-height: 90vh"
                    >
                        <!-- Modal Header -->
                        <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-5 py-4 flex items-center justify-between shrink-0">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-base font-black text-white">Rekap Ibadah Harian</h2>
                                    <p class="text-xs text-emerald-100">Tanggal: {{ selectedDate }}</p>
                                </div>
                            </div>
                            <button 
                                type="button"
                                @click="closeConfirmModal"
                                class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-colors cursor-pointer"
                            >
                                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Subheader info -->
                        <div class="px-5 py-2.5 bg-amber-50 dark:bg-amber-900/20 border-b border-amber-100 dark:border-amber-800/30 shrink-0">
                            <p class="text-xs text-amber-800 dark:text-amber-300 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Periksa kembali rekap ibadah di bawah sebelum menyimpan.
                            </p>
                        </div>

                        <!-- Recap List -->
                        <div class="overflow-y-auto flex-1 divide-y divide-slate-100 dark:divide-slate-800">
                            <div 
                                v-for="item in recapItems" 
                                :key="item.habit.id"
                                class="px-5 py-3.5 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                            >
                                <!-- Habit Name + Status Badge -->
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <span class="text-sm font-bold text-slate-800 dark:text-slate-200 truncate">
                                        {{ item.habit.nama_habit.replace('[Pengganti Haid] ', '') }}
                                    </span>
                                    <span 
                                        :class="[
                                            'text-[10px] font-black px-2 py-0.5 rounded-full whitespace-nowrap shrink-0',
                                            item.status === 'done' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' :
                                            item.status === 'partial' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' :
                                            'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400'
                                        ]"
                                    >
                                        {{ item.status === 'done' ? '✓ Selesai' : item.status === 'partial' ? '⚠ Sebagian' : '✗ Belum' }}
                                    </span>
                                </div>

                                <!-- Detail Lines -->
                                <div class="flex flex-wrap gap-x-4 gap-y-1">
                                    <div 
                                        v-for="(line, i) in item.lines" 
                                        :key="i"
                                        class="flex items-center gap-1 text-xs"
                                    >
                                        <span class="text-slate-400 dark:text-slate-500 font-medium">{{ line.label }}:</span>
                                        <span 
                                            :class="[
                                                'font-bold',
                                                line.filled ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-400 dark:text-red-400'
                                            ]"
                                        >
                                            {{ line.value }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="px-5 py-4 bg-slate-50 dark:bg-slate-800/80 border-t border-slate-200 dark:border-slate-700 flex gap-3 shrink-0">
                            <!-- Kembali Cek -->
                            <button 
                                type="button"
                                @click="closeConfirmModal"
                                class="flex-1 py-3 px-4 rounded-xl border-2 border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 font-bold text-sm hover:border-slate-400 hover:bg-white dark:hover:bg-slate-700 transition-all cursor-pointer flex items-center justify-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5m0 0l5-5m-5 5h12" />
                                </svg>
                                Cek Lagi
                            </button>

                            <!-- Simpan -->
                            <button 
                                type="button"
                                @click="doSubmit"
                                :disabled="form.processing"
                                class="flex-1 py-3 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-black text-sm shadow-md hover:shadow-lg transition-all cursor-pointer disabled:opacity-50 flex items-center justify-center gap-2 active:scale-98"
                            >
                                <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>{{ form.processing ? 'Menyimpan...' : 'Ya, Simpan!' }}</span>
                            </button>
                        </div>
                    </div>
                </transition>
            </div>
        </transition>

    </AuthenticatedLayout>
</template>
