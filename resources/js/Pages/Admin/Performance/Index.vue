<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'

const props = defineProps({
    employees: Array,
    selectedMonth: Number,
    selectedYear: Number
})

const month = ref(props.selectedMonth)
const year = ref(props.selectedYear)

const monthNames = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"]

watch([month, year], () => {
    router.get(route('admin.performance.index'), { month: month.value, year: year.value }, { preserveState: true, preserveScroll: true })
})

// Modal Penilaian
const showModal = ref(false)
const selectedUser = ref(null)

const form = useForm({
    user_id: '',
    evaluation_month: month.value,
    evaluation_year: year.value,
    score_pedagogic: 0,
    score_professional: 0,
    score_personality: 0,
    score_social: 0,
    notes: ''
})

const averageScorePreview = computed(() => {
    return (Number(form.score_pedagogic) + Number(form.score_professional) + Number(form.score_personality) + Number(form.score_social)) / 4
})

const openEvaluationModal = (employee) => {
    selectedUser.value = employee
    form.user_id = employee.user_id
    form.evaluation_month = month.value
    form.evaluation_year = year.value
    
    if (employee.evaluation) {
        form.score_pedagogic = employee.evaluation.score_pedagogic
        form.score_professional = employee.evaluation.score_professional
        form.score_personality = employee.evaluation.score_personality
        form.score_social = employee.evaluation.score_social
        form.notes = employee.evaluation.notes
    } else {
        form.score_pedagogic = 0
        form.score_professional = 0
        form.score_personality = 0
        form.score_social = 0
        form.notes = ''
    }
    
    showModal.value = true
}

const submit = () => {
    form.post(route('admin.performance.store'), {
        onSuccess: () => {
            showModal.value = false
        }
    })
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Evaluasi Kinerja Pegawai" />

        <div class="space-y-6 w-full pb-8">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 dark:text-slate-100 tracking-tight">Evaluasi Kinerja (KPI)</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Lakukan penilaian kinerja kompetensi guru dan karyawan setiap bulan.
                    </p>
                </div>
                
                <div class="flex items-center gap-2">
                    <select v-model="month" class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-sm font-bold text-slate-700 dark:text-slate-300 focus:ring-emerald-500">
                        <option v-for="(m, i) in monthNames" :key="i" :value="i+1">{{ m }}</option>
                    </select>
                    <select v-model="year" class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-sm font-bold text-slate-700 dark:text-slate-300 focus:ring-emerald-500">
                        <option v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>
            </div>

            <!-- Employees Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="emp in employees" :key="emp.user_id" class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 shadow-sm p-5 relative overflow-hidden group">
                    <div v-if="emp.evaluation" class="absolute top-0 right-0 bg-emerald-500 text-white text-[10px] font-bold px-3 py-1 rounded-bl-xl z-10">
                        Sudah Dinilai
                    </div>
                    <div v-else class="absolute top-0 right-0 bg-rose-500 text-white text-[10px] font-bold px-3 py-1 rounded-bl-xl z-10">
                        Belum Dinilai
                    </div>

                    <div class="flex items-start gap-4 mt-2">
                        <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-lg font-bold text-emerald-600 shrink-0">
                            {{ emp.name.charAt(0) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100 truncate">{{ emp.name }}</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ emp.nip || '-' }} • {{ emp.divisi || 'Guru' }}</p>
                        </div>
                    </div>

                    <div class="mt-5 mb-4 grid grid-cols-2 gap-2">
                        <div class="bg-slate-50 dark:bg-slate-900/50 p-2.5 rounded-xl border border-slate-100 dark:border-slate-700 text-center">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Pedagogik</div>
                            <div class="text-lg font-extrabold text-slate-700 dark:text-slate-200">{{ emp.evaluation ? emp.evaluation.score_pedagogic : '-' }}</div>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-900/50 p-2.5 rounded-xl border border-slate-100 dark:border-slate-700 text-center">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Profesional</div>
                            <div class="text-lg font-extrabold text-slate-700 dark:text-slate-200">{{ emp.evaluation ? emp.evaluation.score_professional : '-' }}</div>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-900/50 p-2.5 rounded-xl border border-slate-100 dark:border-slate-700 text-center">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Kepribadian</div>
                            <div class="text-lg font-extrabold text-slate-700 dark:text-slate-200">{{ emp.evaluation ? emp.evaluation.score_personality : '-' }}</div>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-900/50 p-2.5 rounded-xl border border-slate-100 dark:border-slate-700 text-center">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Sosial</div>
                            <div class="text-lg font-extrabold text-slate-700 dark:text-slate-200">{{ emp.evaluation ? emp.evaluation.score_social : '-' }}</div>
                        </div>
                    </div>

                    <div v-if="emp.evaluation" class="flex items-center justify-between p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl mb-4 border border-emerald-100 dark:border-emerald-900/50">
                        <span class="text-xs font-bold text-emerald-800 dark:text-emerald-400">RATA-RATA (KPI)</span>
                        <span class="text-lg font-black text-emerald-600 dark:text-emerald-400">{{ emp.evaluation.average_score }}</span>
                    </div>

                    <button @click="openEvaluationModal(emp)" class="w-full py-2.5 rounded-xl text-sm font-bold transition-colors" :class="emp.evaluation ? 'bg-white border-2 border-emerald-600 text-emerald-600 hover:bg-emerald-50 dark:bg-slate-800 dark:hover:bg-slate-700' : 'bg-emerald-600 text-white hover:bg-emerald-700'">
                        {{ emp.evaluation ? 'Edit Penilaian' : 'Beri Penilaian' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Evaluation Modal -->
        <div v-if="showModal" class="relative z-50">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-3xl bg-white dark:bg-slate-800 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl border border-slate-100 dark:border-slate-700">
                        <form @submit.prevent="submit">
                            <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-900/50">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">Evaluasi Kinerja</h3>
                                    <p class="text-xs text-slate-500">{{ selectedUser?.name }} - {{ monthNames[month-1] }} {{ year }}</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Rata-Rata</div>
                                    <div class="text-2xl font-black text-emerald-600">{{ averageScorePreview.toFixed(2) }}</div>
                                </div>
                            </div>
                            
                            <div class="px-6 py-5 space-y-6">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <!-- Kompetensi 1 -->
                                    <div class="space-y-1">
                                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300">Kompetensi Pedagogik</label>
                                        <p class="text-[10px] text-slate-400 mb-2">Kualitas & inovasi mengajar</p>
                                        <input type="number" v-model="form.score_pedagogic" min="0" max="100" class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2 text-slate-700 dark:text-slate-300 focus:ring-emerald-500 text-lg font-bold" />
                                    </div>
                                    <!-- Kompetensi 2 -->
                                    <div class="space-y-1">
                                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300">Kompetensi Profesional</label>
                                        <p class="text-[10px] text-slate-400 mb-2">Administrasi kelas & materi</p>
                                        <input type="number" v-model="form.score_professional" min="0" max="100" class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2 text-slate-700 dark:text-slate-300 focus:ring-emerald-500 text-lg font-bold" />
                                    </div>
                                    <!-- Kompetensi 3 -->
                                    <div class="space-y-1">
                                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300">Kompetensi Kepribadian</label>
                                        <p class="text-[10px] text-slate-400 mb-2">Kedisiplinan & keteladanan</p>
                                        <input type="number" v-model="form.score_personality" min="0" max="100" class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2 text-slate-700 dark:text-slate-300 focus:ring-emerald-500 text-lg font-bold" />
                                    </div>
                                    <!-- Kompetensi 4 -->
                                    <div class="space-y-1">
                                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300">Kompetensi Sosial</label>
                                        <p class="text-[10px] text-slate-400 mb-2">Kerjasama & komunikasi</p>
                                        <input type="number" v-model="form.score_social" min="0" max="100" class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2 text-slate-700 dark:text-slate-300 focus:ring-emerald-500 text-lg font-bold" />
                                    </div>
                                </div>

                                <div class="pt-2">
                                    <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Pesan Pembinaan / Catatan</label>
                                    <textarea v-model="form.notes" rows="3" placeholder="Berikan catatan, apresiasi, atau masukan untuk pegawai ini..." class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-sm text-slate-700 dark:text-slate-300 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                                </div>
                            </div>
                            
                            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 flex justify-end gap-3 rounded-b-3xl border-t border-slate-100 dark:border-slate-700">
                                <button type="button" @click="showModal = false" class="px-4 py-2 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">Batal</button>
                                <button type="submit" :disabled="form.processing" class="px-6 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold transition-colors disabled:opacity-50">
                                    {{ form.processing ? 'Menyimpan...' : 'Simpan Penilaian' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
