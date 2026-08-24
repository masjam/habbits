<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import draggable from 'vuedraggable'

const props = defineProps({
    habits: Array,
})

const localHabits = ref([...props.habits])

watch(() => props.habits, (newHabits) => {
    localHabits.value = [...newHabits]
}, { deep: true })

const onDragEnd = () => {
    const orderedIds = localHabits.value.map(h => h.id)
    router.post(route('admin.habits.reorder'), { ordered_ids: orderedIds }, {
        preserveScroll: true,
        preserveState: true,
    })
}

// Modal State
const isModalOpen = ref(false)
const isEditing = ref(false)
const editingId = ref(null)

const form = useForm({
    nama_habit: '',
    deskripsi: '',
    tipe_input: 'boolean',
    template: 'default',
    satuan: '',
    target_pencapaian: 1,
    skor_maksimal: 5,
    hide_saat_haid: false,
    is_pengganti_haid: false,
    status_aktif: true,
})

const openAddModal = () => {
    isEditing.value = false
    editingId.value = null
    form.reset()
    form.clearErrors()
    // Defaults for new
    form.tipe_input = 'boolean'
    form.template = 'default'
    form.target_pencapaian = 1
    form.skor_maksimal = 5
    form.status_aktif = true
    form.hide_saat_haid = false
    form.is_pengganti_haid = false
    
    isModalOpen.value = true
}

const openEditModal = (habit) => {
    isEditing.value = true
    editingId.value = habit.id
    form.clearErrors()
    
    form.nama_habit = habit.nama_habit
    form.deskripsi = habit.deskripsi || ''
    form.tipe_input = habit.tipe_input
    form.template = habit.template
    form.satuan = habit.satuan || ''
    form.target_pencapaian = habit.target_pencapaian
    form.skor_maksimal = habit.skor_maksimal
    form.hide_saat_haid = habit.hide_saat_haid
    form.is_pengganti_haid = habit.is_pengganti_haid
    form.status_aktif = habit.status_aktif
    
    isModalOpen.value = true
}

const closeModal = () => {
    isModalOpen.value = false
    isEditing.value = false
    editingId.value = null
    form.reset()
    form.clearErrors()
}

const saveHabit = () => {
    if (isEditing.value) {
        form.put(route('admin.habits.update', editingId.value), {
            onSuccess: () => closeModal(),
        })
    } else {
        form.post(route('admin.habits.store'), {
            onSuccess: () => closeModal(),
        })
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Manajemen Habit" />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-800 tracking-tight">Manajemen Habit</h2>
                    <p class="text-sm text-slate-500 mt-1">
                        Atur nama, deskripsi, urutan, satuan, target, dan skor maksimal untuk setiap habit di sistem. Anda bisa menggeser baris untuk mengubah urutan habit.
                    </p>
                </div>
                <button @click="openAddModal" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 text-white font-bold text-sm shadow-sm hover:bg-emerald-700 transition-colors focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Tambah Habit</span>
                </button>
            </div>

            <!-- Table & Mobile Cards -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <!-- Desktop View -->
                    <table class="hidden md:table w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500 font-semibold border-b border-slate-200">
                            <tr>
                                <th class="px-2 py-3 text-center w-8"></th>
                                <th class="px-2 py-3 text-center w-10">#</th>
                                <th class="px-4 py-3">Nama Habit</th>
                                <th class="px-4 py-3 hidden md:table-cell">Deskripsi</th>
                                <th class="px-4 py-3 text-center">Target / Satuan</th>
                                <th class="px-4 py-3 text-center">Skor Maks</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <draggable 
                            v-model="localHabits"
                            tag="tbody"
                            class="divide-y divide-slate-200"
                            item-key="id"
                            handle=".drag-handle"
                            @end="onDragEnd"
                            animation="200"
                        >
                            <template #item="{ element: habit, index }">
                                <tr class="bg-white hover:bg-slate-50 transition-colors">
                                    <td class="px-2 py-4 text-center">
                                        <button type="button" class="drag-handle cursor-move p-1 text-slate-300 hover:text-slate-500 transition-colors">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16M4 16h16" />
                                            </svg>
                                        </button>
                                    </td>
                                    <td class="px-2 py-4 text-center text-slate-400 font-medium">{{ index + 1 }}</td>
                                    <td class="px-4 py-4">
                                        <div class="font-bold text-slate-800">{{ habit.nama_habit }}</div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">
                                            <span v-if="habit.is_pengganti_haid" class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 mr-1">Pengganti Haid</span>
                                            <span v-if="habit.hide_saat_haid" class="px-1.5 py-0.5 rounded bg-rose-100 text-rose-700 mr-1">Disembunyikan saat Haid</span>
                                            Type: {{ habit.tipe_input }}, Tpl: {{ habit.template }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 hidden md:table-cell">
                                        <p class="text-xs text-slate-500 line-clamp-2" :title="habit.deskripsi">{{ habit.deskripsi || '-' }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <div class="font-bold text-slate-700">{{ habit.target_pencapaian }}</div>
                                        <div class="text-[10px] text-slate-400 uppercase tracking-wider">{{ habit.satuan || '-' }}</div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                            {{ habit.skor_maksimal }} pt
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span v-if="habit.status_aktif" class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block shadow-sm shadow-emerald-200" title="Aktif"></span>
                                        <span v-else class="w-2.5 h-2.5 rounded-full bg-slate-300 inline-block shadow-sm" title="Non-aktif"></span>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <button @click="openEditModal(habit)" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </draggable>
                    </table>
                    
                    <!-- Mobile View -->
                    <draggable 
                        v-model="localHabits"
                        class="md:hidden divide-y divide-slate-100"
                        item-key="id"
                        handle=".drag-handle"
                        @end="onDragEnd"
                        animation="200"
                    >
                        <template #item="{ element: habit, index }">
                            <div class="p-4 space-y-3 bg-white hover:bg-slate-50 transition-colors">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-start gap-2">
                                        <button type="button" class="drag-handle cursor-move mt-0.5 text-slate-300 hover:text-slate-500">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16M4 16h16" /></svg>
                                        </button>
                                        <div>
                                            <div class="font-bold text-slate-800 text-sm">{{ habit.nama_habit }}</div>
                                            <div class="text-[10px] text-slate-500 mt-0.5">
                                                Target: <span class="font-bold text-slate-700">{{ habit.target_pencapaian }} {{ habit.satuan || '' }}</span> &bull; Skor: <span class="font-bold text-slate-700">{{ habit.skor_maksimal }}pt</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <span v-if="habit.status_aktif" class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-sm shadow-emerald-200" title="Aktif"></span>
                                        <span v-else class="w-2.5 h-2.5 rounded-full bg-slate-300 shadow-sm" title="Non-aktif"></span>
                                        <button @click="openEditModal(habit)" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-emerald-600 bg-emerald-50 hover:bg-emerald-100">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="pl-7 flex flex-wrap gap-1.5 text-[9px] font-bold uppercase tracking-wider">
                                    <span v-if="habit.is_pengganti_haid" class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-700">P. Haid</span>
                                    <span v-if="habit.hide_saat_haid" class="px-1.5 py-0.5 rounded bg-rose-100 text-rose-700">Sembunyi Haid</span>
                                    <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">Tipe: {{ habit.tipe_input }}</span>
                                    <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">Tpl: {{ habit.template }}</span>
                                </div>
                            </div>
                        </template>
                    </draggable>
                    <div v-if="localHabits.length === 0" class="md:hidden p-6 text-center text-slate-500 text-sm bg-white">Belum ada habit.</div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div v-if="isModalOpen" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>
            
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-xl border border-slate-100">
                        <form @submit.prevent="saveHabit">
                            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4 max-h-[80vh] overflow-y-auto">
                                <h3 class="text-lg font-bold leading-6 text-slate-900 mb-4" id="modal-title">
                                    {{ isEditing ? 'Edit Habit' : 'Tambah Habit Baru' }}
                                </h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wider">Nama Habit</label>
                                        <input type="text" v-model="form.nama_habit" class="w-full p-2 text-sm border-slate-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500" required />
                                        <p v-if="form.errors.nama_habit" class="text-xs text-rose-500 mt-1">{{ form.errors.nama_habit }}</p>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wider">Deskripsi (Ditampilkan di Form)</label>
                                        <textarea rows="2" v-model="form.deskripsi" class="w-full p-2 text-sm border-slate-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 resize-none"></textarea>
                                        <p v-if="form.errors.deskripsi" class="text-xs text-rose-500 mt-1">{{ form.errors.deskripsi }}</p>
                                    </div>

                                    <template v-if="!isEditing">
                                        <div class="grid grid-cols-2 gap-4 p-4 bg-slate-50 rounded-xl border border-slate-200">
                                            <div class="col-span-2 text-xs font-medium text-slate-500 italic mb-1">
                                                Pengaturan Tampilan (Hanya saat Tambah Baru)
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wider">Tipe Input</label>
                                                <select v-model="form.tipe_input" class="w-full p-2 text-sm border-slate-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500">
                                                    <option value="boolean">Selesai/Belum (Ya/Tidak)</option>
                                                    <option value="integer">Angka / Jumlah</option>
                                                </select>
                                                <p v-if="form.errors.tipe_input" class="text-xs text-rose-500 mt-1">{{ form.errors.tipe_input }}</p>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wider">Template Form</label>
                                                <select v-model="form.template" class="w-full p-2 text-sm border-slate-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500">
                                                    <option value="default">Form Sederhana</option>
                                                    <option value="integer">Form Angka</option>
                                                    <option value="sholat_wajib">Form Sholat Wajib</option>
                                                    <option value="sholat_rawatib">Form Sholat Rawatib</option>
                                                    <option value="quran">Form Al Quran</option>
                                                    <option value="hadist">Form Hadist</option>
                                                    <option value="buku">Form Buku</option>
                                                </select>
                                                <p v-if="form.errors.template" class="text-xs text-rose-500 mt-1">{{ form.errors.template }}</p>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 gap-2 p-4 bg-rose-50 rounded-xl border border-rose-100">
                                            <div class="text-xs font-medium text-slate-500 italic mb-2">
                                                Pengaturan Mode Haid
                                            </div>
                                            <label class="flex items-center gap-3 cursor-pointer">
                                                <input type="checkbox" v-model="form.hide_saat_haid" class="w-5 h-5 text-rose-600 rounded border-slate-300 focus:ring-rose-500" />
                                                <span class="text-sm font-bold text-slate-700">Sembunyikan form ini saat Mode Haid</span>
                                            </label>
                                            <label class="flex items-center gap-3 cursor-pointer mt-1">
                                                <input type="checkbox" v-model="form.is_pengganti_haid" class="w-5 h-5 text-amber-600 rounded border-slate-300 focus:ring-amber-500" />
                                                <span class="text-sm font-bold text-slate-700">Jadikan ini sebagai Amalan Pengganti Haid</span>
                                            </label>
                                        </div>
                                    </template>

                                    <div class="grid grid-cols-2 gap-4 pt-2">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wider">Target Pencapaian</label>
                                            <input type="number" min="0" v-model="form.target_pencapaian" class="w-full p-2 text-sm border-slate-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500" required />
                                            <p v-if="form.errors.target_pencapaian" class="text-xs text-rose-500 mt-1">{{ form.errors.target_pencapaian }}</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wider">Satuan (Opsional)</label>
                                            <input type="text" v-model="form.satuan" placeholder="Cth: Rakaat" class="w-full p-2 text-sm border-slate-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500" />
                                            <p v-if="form.errors.satuan" class="text-xs text-rose-500 mt-1">{{ form.errors.satuan }}</p>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wider">Skor Maksimal</label>
                                        <input type="number" min="0" v-model="form.skor_maksimal" class="w-full p-2 text-sm border-slate-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500" required />
                                        <p v-if="form.errors.skor_maksimal" class="text-xs text-rose-500 mt-1">{{ form.errors.skor_maksimal }}</p>
                                    </div>

                                    <div class="pt-2">
                                        <label class="flex items-center gap-3 cursor-pointer">
                                            <input type="checkbox" v-model="form.status_aktif" class="w-5 h-5 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500" />
                                            <div class="flex flex-col">
                                                <span class="text-sm font-bold text-slate-700">Status Aktif</span>
                                                <span class="text-[10px] text-slate-500">Centang agar habit ini muncul di form pengisian user</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t border-slate-100">
                                <button type="submit" :disabled="form.processing" class="inline-flex w-full justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 sm:ml-3 sm:w-auto focus:ring-2 focus:ring-emerald-500 disabled:opacity-50">
                                    <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>Simpan Habit</span>
                                </button>
                                <button type="button" @click="closeModal" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto">
                                    Batal
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
