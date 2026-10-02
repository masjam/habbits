<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    journals: Object,
    filters: Object,
})

const search = ref(props.filters.search || '')
const startDate = ref(props.filters.start_date || '')
const endDate = ref(props.filters.end_date || '')

let timeout = null
watch([search, startDate, endDate], ([newSearch, newStartDate, newEndDate]) => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get(
            route('admin.teacher-journals.index'),
            { search: newSearch, start_date: newStartDate, end_date: newEndDate },
            { preserveState: true, replace: true }
        )
    }, 300)
})

const summarizeHtml = (html, length = 50) => {
    if (!html) return '-';
    let doc = new DOMParser().parseFromString(html, 'text/html');
    let text = doc.body.textContent || "";
    if (text.length <= length) return text;
    return text.substring(0, length) + '...';
}

const showDetailModal = ref(false)
const selectedJournal = ref(null)

const openDetail = (journal) => {
    selectedJournal.value = journal
    showDetailModal.value = true
}

const closeDetail = () => {
    showDetailModal.value = false
    selectedJournal.value = null
}
</script>

<template>
    <Head title="Rekap Jurnal Harian Guru" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Rekap Jurnal Harian Guru</h2>
        </template>

        <div class="py-6">
            <div class="w-full mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        
                        <!-- Filters -->
                        <div class="mb-6 grid grid-cols-3 gap-2 items-end">
                            <div>
                                <label class="block text-[10px] md:text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 truncate">Pencarian</label>
                                <input v-model="search" type="text" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-xs md:text-sm px-2 py-1.5 md:px-3 md:py-2" placeholder="Cari...">
                            </div>
                            <div>
                                <label class="block text-[10px] md:text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 truncate">Mulai Tgl</label>
                                <input v-model="startDate" type="date" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-xs md:text-sm px-2 py-1.5 md:px-3 md:py-2">
                            </div>
                            <div>
                                <label class="block text-[10px] md:text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 truncate">Sampai Tgl</label>
                                <input v-model="endDate" type="date" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-xs md:text-sm px-2 py-1.5 md:px-3 md:py-2">
                            </div>
                        </div>

                        <!-- Desktop Table -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th scope="col" class="px-4 py-3">Tanggal</th>
                                        <th scope="col" class="px-4 py-3">Nama Guru</th>
                                        <th scope="col" class="px-4 py-3">Kelas</th>
                                        <th scope="col" class="px-4 py-3">Mapel</th>
                                        <th scope="col" class="px-4 py-3">Tema</th>
                                        <th scope="col" class="px-4 py-3">Waktu</th>
                                        <th scope="col" class="px-4 py-3">Presensi</th>
                                        <th scope="col" class="px-4 py-3">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="journal in journals.data" :key="journal.id" class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <td class="px-4 py-3">{{ journal.date }}</td>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ journal.user?.name }}</td>
                                        <td class="px-4 py-3">{{ journal.class_name }}</td>
                                        <td class="px-4 py-3">{{ journal.subject }}</td>
                                        <td class="px-4 py-3">{{ journal.theme || '-' }}</td>
                                        <td class="px-4 py-3">
                                            {{ journal.time_start ? journal.time_start.substring(0,5) : '-' }} - 
                                            {{ journal.time_end ? journal.time_end.substring(0,5) : '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-xs">
                                            <span class="text-green-600">H:{{ journal.student_present }}</span> | 
                                            <span class="text-blue-600">S:{{ journal.student_sick }}</span> | 
                                            <span class="text-yellow-600">I:{{ journal.student_leave }}</span> | 
                                            <span class="text-red-600">A:{{ journal.student_absent }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <button @click="openDetail(journal)" class="text-blue-600 hover:underline">Detail</button>
                                        </td>
                                    </tr>
                                    <tr v-if="journals.data.length === 0">
                                        <td colspan="8" class="px-6 py-4 text-center">Belum ada rekap jurnal.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile Cards -->
                        <div class="block md:hidden space-y-4 mt-2">
                            <div v-for="journal in journals.data" :key="journal.id" class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                                <div class="flex justify-between items-start mb-3 border-b dark:border-gray-700 pb-2">
                                    <div>
                                        <h4 class="font-bold text-gray-900 dark:text-white">{{ journal.user?.name }}</h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ journal.date }}</p>
                                    </div>
                                    <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 text-xs font-semibold rounded-full border border-indigo-100 dark:border-indigo-800">
                                        {{ journal.subject }} (Kls {{ journal.class_name }})
                                    </span>
                                </div>
                                <div class="text-sm text-gray-700 dark:text-gray-300 mb-3 grid grid-cols-1 gap-1">
                                    <div><span class="text-gray-500 dark:text-gray-400">Tema:</span> {{ journal.theme || '-' }}</div>
                                    <div><span class="text-gray-500 dark:text-gray-400">Waktu:</span> {{ journal.time_start ? journal.time_start.substring(0,5) : '-' }} - {{ journal.time_end ? journal.time_end.substring(0,5) : '-' }}</div>
                                </div>
                                <div class="text-xs bg-gray-50/50 dark:bg-gray-900/50 p-2 rounded-lg mb-4 flex justify-between items-center border border-gray-100 dark:border-gray-800">
                                    <span class="text-gray-500 dark:text-gray-400 font-medium">Presensi:</span>
                                    <div class="space-x-2">
                                        <span class="text-green-600 font-medium">H:{{ journal.student_present }}</span>
                                        <span class="text-blue-600 font-medium">S:{{ journal.student_sick }}</span>
                                        <span class="text-yellow-600 font-medium">I:{{ journal.student_leave }}</span>
                                        <span class="text-red-600 font-medium">A:{{ journal.student_absent }}</span>
                                    </div>
                                </div>
                                <button @click="openDetail(journal)" class="w-full text-center py-2 bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-sm font-semibold transition border border-gray-200 dark:border-gray-600">
                                    Lihat Detail
                                </button>
                            </div>
                            <div v-if="journals.data.length === 0" class="text-center py-6 text-gray-500">
                                Belum ada rekap jurnal.
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-4 flex justify-between items-center" v-if="journals.links?.length > 3">
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                Menampilkan {{ journals.from }} sampai {{ journals.to }} dari {{ journals.total }} data
                            </div>
                            <div class="flex space-x-1">
                                <Link
                                    v-for="(link, k) in journals.links"
                                    :key="k"
                                    :href="link.url || '#'"
                                    class="px-3 py-1 border rounded text-sm"
                                    :class="[
                                        link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600',
                                        !link.url ? 'opacity-50 cursor-not-allowed' : ''
                                    ]"
                                    v-html="link.label"
                                ></Link>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <div v-if="showDetailModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="closeDetail"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="relative z-10 inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex justify-between items-center mb-4 border-b pb-3 dark:border-gray-700">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100" id="modal-title">
                                Detail Jurnal Guru
                            </h3>
                            <button @click="closeDetail" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        
                        <div class="mt-2 space-y-4 text-sm text-gray-700 dark:text-gray-300" v-if="selectedJournal">
                            <div class="grid grid-cols-2 gap-4">
                                <div><span class="font-bold">Guru:</span> {{ selectedJournal.user?.name }}</div>
                                <div><span class="font-bold">Tanggal:</span> {{ selectedJournal.date }}</div>
                                <div><span class="font-bold">Kelas:</span> {{ selectedJournal.class_name }}</div>
                                <div><span class="font-bold">Mapel:</span> {{ selectedJournal.subject }}</div>
                                <div><span class="font-bold">Tema:</span> {{ selectedJournal.theme || '-' }}</div>
                                <div><span class="font-bold">Waktu:</span> {{ selectedJournal.time_start ? selectedJournal.time_start.substring(0,5) : '-' }} - {{ selectedJournal.time_end ? selectedJournal.time_end.substring(0,5) : '-' }}</div>
                                <div><span class="font-bold">Pertemuan:</span> {{ selectedJournal.meeting_number }}</div>
                                <div><span class="font-bold">Jam Ke:</span> {{ selectedJournal.period }}</div>
                            </div>

                            <div>
                                <h4 class="font-bold mb-1 border-b dark:border-gray-700">Presensi</h4>
                                <div>Hadir: {{ selectedJournal.student_present }} | Sakit: {{ selectedJournal.student_sick }} | Izin: {{ selectedJournal.student_leave }} | Alpa: {{ selectedJournal.student_absent }}</div>
                            </div>

                            <div>
                                <h4 class="font-bold mb-1 border-b dark:border-gray-700">Uraian Materi</h4>
                                <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded" v-html="selectedJournal.material"></div>
                            </div>

                            <div>
                                <h4 class="font-bold mb-1 border-b dark:border-gray-700">Catatan Tambahan</h4>
                                <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded" v-html="selectedJournal.notes || 'Tidak ada catatan.'"></div>
                            </div>
                            
                            <div v-if="selectedJournal.photo">
                                <h4 class="font-bold mb-1 border-b dark:border-gray-700">Bukti Foto</h4>
                                <img :src="selectedJournal.photo" alt="Bukti Foto" class="mt-2 max-w-full h-auto rounded shadow-sm max-h-64 object-contain">
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" @click="closeDetail" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:w-auto sm:text-sm dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
