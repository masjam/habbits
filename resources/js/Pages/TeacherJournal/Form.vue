<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    journal: {
        type: Object,
        default: () => null
    }
})

const form = useForm({
    date: props.journal?.date || '',
    class_name: props.journal?.class_name || '',
    subject: props.journal?.subject || '',
    meeting_number: props.journal?.meeting_number || '',
    period: props.journal?.period || '',
    material: props.journal?.material || '',
    notes: props.journal?.notes || '',
    photo: null,
    student_present: props.journal?.student_present || 0,
    student_sick: props.journal?.student_sick || 0,
    student_leave: props.journal?.student_leave || 0,
    student_absent: props.journal?.student_absent || 0,
})

const submit = () => {
    if (props.journal) {
        form.post(route('teacher-journals.update', props.journal.id), {
            preserveScroll: true,
            forceFormData: true, // Since it has a file
            onBefore: () => {
                form._method = 'PUT'
            }
        })
    } else {
        form.post(route('teacher-journals.store'), {
            preserveScroll: true,
        })
    }
}
</script>

<template>
    <Head :title="journal ? 'Edit Jurnal' : 'Tambah Jurnal'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ journal ? 'Edit Jurnal' : 'Tambah Jurnal Harian' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="w-full mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <form @submit.prevent="submit" class="space-y-6">
                        
                        <!-- Tanggal & Kelas -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Tanggal</label>
                                <input type="date" v-model="form.date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                                <p v-if="form.errors.date" class="text-sm text-red-600 mt-1">{{ form.errors.date }}</p>
                            </div>
                            <div>
                                <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Kelas</label>
                                <input type="text" v-model="form.class_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                                <p v-if="form.errors.class_name" class="text-sm text-red-600 mt-1">{{ form.errors.class_name }}</p>
                            </div>
                        </div>

                        <!-- Mapel & Pertemuan -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Mata Pelajaran</label>
                                <input type="text" v-model="form.subject" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                                <p v-if="form.errors.subject" class="text-sm text-red-600 mt-1">{{ form.errors.subject }}</p>
                            </div>
                            <div>
                                <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Pertemuan Ke-</label>
                                <input type="text" v-model="form.meeting_number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                                <p v-if="form.errors.meeting_number" class="text-sm text-red-600 mt-1">{{ form.errors.meeting_number }}</p>
                            </div>
                            <div>
                                <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Jam Ke-</label>
                                <input type="text" v-model="form.period" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                                <p v-if="form.errors.period" class="text-sm text-red-600 mt-1">{{ form.errors.period }}</p>
                            </div>
                        </div>

                        <!-- Uraian Materi -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Uraian Materi / Kegiatan</label>
                            <textarea v-model="form.material" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required></textarea>
                            <p v-if="form.errors.material" class="text-sm text-red-600 mt-1">{{ form.errors.material }}</p>
                        </div>

                        <!-- Presensi -->
                        <div>
                            <h4 class="font-medium text-sm text-gray-700 dark:text-gray-300 mb-2">Presensi Siswa</h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-xs text-gray-500">Hadir</label>
                                    <input type="number" min="0" v-model="form.student_present" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Sakit</label>
                                    <input type="number" min="0" v-model="form.student_sick" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Izin</label>
                                    <input type="number" min="0" v-model="form.student_leave" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Alpa</label>
                                    <input type="number" min="0" v-model="form.student_absent" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                                </div>
                            </div>
                        </div>

                        <!-- Catatan -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Catatan / Keterangan Tambahan</label>
                            <textarea v-model="form.notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"></textarea>
                        </div>

                        <!-- Foto -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Bukti Foto (Opsional)</label>
                            <input type="file" @input="form.photo = $event.target.files[0]" accept="image/*" class="mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-md cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400">
                            <p v-if="form.errors.photo" class="text-sm text-red-600 mt-1">{{ form.errors.photo }}</p>
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <Link :href="route('teacher-journals.index')" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white mr-4">
                                Batal
                            </Link>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded" :disabled="form.processing">
                                Simpan
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
