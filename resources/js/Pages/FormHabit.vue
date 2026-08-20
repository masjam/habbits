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

const submit = () => {
    form.post(route('habit.form.store'), {
        preserveScroll: true,
        onSuccess: () => {
            alert('Data habit berhasil disimpan!');
        }
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

        <div class="w-full max-w-[1920px] px-4 sm:px-6 lg:px-8 mx-auto space-y-6">
            
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

            <form @submit.prevent="submit" class="pb-20">
                
                <!-- Mobile Tabs (Visible only on small screens) -->
                <div class="md:hidden overflow-x-auto whitespace-nowrap mb-4 pb-2 border-b border-slate-200 dark:border-slate-700 flex gap-2 hide-scrollbar">
                    <button 
                        v-for="habit in habits" 
                        :key="'tab-'+habit.id"
                        type="button"
                        @click="activeTabId = habit.id"
                        :class="[
                            'px-4 py-2 text-sm font-semibold rounded-full transition-colors',
                            activeTabId === habit.id 
                                ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' 
                                : 'bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:bg-slate-700'
                        ]"
                    >
                        {{ habit.nama_habit.replace('[Pengganti Haid] ', '') }}
                    </button>
                </div>

                <!-- Cards Layout -->
                <div class="grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-3 gap-6 items-start">
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
                        
                        <!-- Habit Header -->
                        <div class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 px-4 py-2.5 flex justify-between items-center rounded-t-xl">
                            <div class="flex-1 pr-2 truncate">
                                <h3 class="font-bold text-slate-800 dark:text-slate-200 text-xs truncate" :title="habit.nama_habit">{{ habit.nama_habit }}</h3>
                                <p v-if="habit.deskripsi" class="text-[9px] text-slate-500 dark:text-slate-400 dark:text-slate-500 mt-0.5 truncate" :title="habit.deskripsi">{{ habit.deskripsi }}</p>
                            </div>
                            <span class="text-[9px] font-bold px-1.5 py-0.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 dark:text-slate-500 rounded whitespace-nowrap">
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
                            <span v-else>Simpan</span>
                        </button>
                    </div>
                </div>

            </form>

        </div>
    </AuthenticatedLayout>
</template>
