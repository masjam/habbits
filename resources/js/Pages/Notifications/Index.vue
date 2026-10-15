<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import dayjs from 'dayjs'
import 'dayjs/locale/id'
import relativeTime from 'dayjs/plugin/relativeTime'

dayjs.extend(relativeTime)
dayjs.locale('id')

const props = defineProps({
    notifications: Object,
})

const isModalOpen = ref(false)
const modalTitle = ref('')
const modalSubtitle = ref('')
const modalUrl = ref('')
const modalType = ref('iframe') // 'iframe' | 'image'
const isIframeLoading = ref(true)
const iframeRef = ref(null)

const cleanHtmlText = (text) => {
    if (!text) return ''
    return text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
}

const detectFileType = (url) => {
    if (!url) return 'iframe'
    const cleanUrl = url.split('?')[0].toLowerCase()
    if (cleanUrl.match(/\.(jpeg|jpg|png|webp|gif|svg)$/)) {
        return 'image'
    }
    return 'iframe'
}

const currentDisposisiId = ref(null)
const currentDisposisi = ref(null)
const isUpdatingStatus = ref(false)
const isCompleteModalOpen = ref(false)
const completeNote = ref('')

const fetchDisposisiDetail = async (id) => {
    try {
        const response = await window.axios.get(route('tata-usaha.disposisi.detail', id))
        if (response.data?.disposisi) {
            currentDisposisi.value = response.data.disposisi
        }
    } catch (e) {
        console.warn('Error fetching disposisi detail:', e)
    }
}

const quickUpdateStatus = async (newStatus, note = null) => {
    if (!currentDisposisiId.value) return
    isUpdatingStatus.value = true
    try {
        const response = await window.axios.put(route('tata-usaha.disposisi.update-status', currentDisposisiId.value), {
            status: newStatus,
            catatan_penyelesaian: note !== null ? note : (currentDisposisi.value?.catatan_penyelesaian || '')
        })
        if (response.data?.disposisi) {
            currentDisposisi.value = response.data.disposisi
        } else if (currentDisposisi.value) {
            currentDisposisi.value.status = newStatus
            if (note !== null) currentDisposisi.value.catatan_penyelesaian = note
        }
        // reload iframe agar isi lembar cetak disposisi ikut terupdate statusnya
        if (iframeRef.value) {
            iframeRef.value.src = modalUrl.value
        }
    } catch (e) {
        alert('Gagal memperbarui status: ' + (e.response?.data?.message || e.message))
    } finally {
        isUpdatingStatus.value = false
    }
}

const openCompletePrompt = () => {
    completeNote.value = currentDisposisi.value?.catatan_penyelesaian || ''
    isCompleteModalOpen.value = true
}

const submitComplete = async () => {
    isCompleteModalOpen.value = false
    await quickUpdateStatus('selesai', completeNote.value)
}

const openModal = (link, notification) => {
    modalTitle.value = link.label || 'Dokumen'
    modalSubtitle.value = cleanHtmlText(notification.data?.title || '')
    modalUrl.value = link.url
    modalType.value = detectFileType(link.url)
    isIframeLoading.value = true
    isModalOpen.value = true

    // Cek apakah link ini adalah lembar disposisi
    const match = (link.url || '').match(/\/disposisi\/(\d+)/)
    if (match) {
        currentDisposisiId.value = match[1]
        currentDisposisi.value = null
        fetchDisposisiDetail(match[1])
    } else {
        currentDisposisiId.value = null
        currentDisposisi.value = null
    }

    // Tandai sebagai dibaca di database
    if (notification.read_at === null) {
        notification.read_at = new Date().toISOString()
        router.post(route('notifications.mark-as-read', notification.id), {}, {
            preserveScroll: true
        })
    }
}

const closeModal = () => {
    isModalOpen.value = false
    modalUrl.value = ''
    isIframeLoading.value = false
    currentDisposisiId.value = null
    currentDisposisi.value = null
    isCompleteModalOpen.value = false
}

const printModalContent = () => {
    if (iframeRef.value?.contentWindow) {
        try {
            iframeRef.value.contentWindow.focus()
            iframeRef.value.contentWindow.print()
            return
        } catch (e) {
            console.warn('Iframe print error:', e)
        }
    }
    window.open(modalUrl.value, '_blank')
}

const handleKeyDown = (e) => {
    if (e.key === 'Escape' && isModalOpen.value) {
        closeModal()
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown)
})

const markAsRead = (id, url) => {
    router.post(route('notifications.mark-as-read', id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (url) {
                window.location.href = url
            }
        }
    })
}

const markAllAsRead = () => {
    router.post(route('notifications.mark-all-read'), {}, {
        preserveScroll: true
    })
}
</script>

<template>
    <Head title="Pesan & Notifikasi" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <span>Pesan &amp; Notifikasi</span>
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Daftar pesan dan pemberitahuan dari sistem.
                    </p>
                </div>
                <div>
                    <button 
                        @click="markAllAsRead"
                        class="px-4 py-2 bg-primary-50 dark:bg-primary-900/40 hover:bg-primary-100 dark:hover:bg-primary-800 text-primary-700 dark:text-primary-400 text-sm font-bold rounded-xl transition-colors w-full md:w-auto text-center"
                    >
                        Tandai Semua Dibaca
                    </button>
                </div>
            </div>
        </template>

        <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="bg-sidebar rounded-3xl border border-theme shadow-xs overflow-hidden">
                <div v-if="notifications.data.length === 0" class="p-12 text-center">
                    <div class="w-16 h-16 mx-auto bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-700 dark:text-slate-300">Belum Ada Pesan</h3>
                    <p class="text-slate-500 dark:text-slate-400 mt-1">Anda akan menerima notifikasi di sini jika ada info terbaru.</p>
                </div>

                <div v-else class="divide-y divide-subtle">
                    <div 
                        v-for="notification in notifications.data" 
                        :key="notification.id"
                        @click="markAsRead(notification.id, notification.data.url)"
                        :class="[
                            'p-4 sm:p-5 transition-colors cursor-pointer group',
                            notification.read_at === null 
                                ? 'bg-primary-50/50 dark:bg-primary-900/20 hover:bg-primary-100/50 dark:hover:bg-primary-900/40' 
                                : 'hover:bg-card-subtle'
                        ]"
                    >
                        <div class="flex gap-4">
                            <div class="flex-shrink-0 mt-1">
                                <div :class="[
                                    'w-10 h-10 rounded-full flex items-center justify-center overflow-hidden border',
                                    notification.read_at === null 
                                        ? 'bg-primary-100 border-primary-200 dark:bg-primary-900 dark:border-primary-700' 
                                        : 'bg-slate-100 border-slate-200 dark:bg-slate-800 dark:border-slate-700'
                                ]">
                                    <img v-if="notification.data.icon" :src="notification.data.icon" class="w-6 h-6 object-contain" />
                                    <svg v-else class="w-5 h-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mb-1">
                                    <h4 :class="[
                                        'text-base font-bold truncate pr-4',
                                        notification.read_at === null ? 'text-slate-900 dark:text-white' : 'text-slate-700 dark:text-slate-300'
                                    ]">
                                        {{ cleanHtmlText(notification.data.title || 'Pemberitahuan') }}
                                    </h4>
                                    <span class="text-[11px] text-slate-400 font-medium whitespace-nowrap">
                                        {{ dayjs(notification.created_at).fromNow() }}
                                    </span>
                                </div>
                                <p :class="[
                                    'text-sm',
                                    notification.read_at === null ? 'text-slate-700 dark:text-slate-300 font-medium' : 'text-slate-500 dark:text-slate-400'
                                ]">
                                    {{ cleanHtmlText(notification.data.body || notification.data.message) }}
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2" v-if="notification.data.action_links">
                                    <button 
                                        v-for="link in notification.data.action_links" 
                                        :key="link.url" 
                                        type="button"
                                        @click.stop="openModal(link, notification)"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-white dark:bg-slate-800 hover:bg-emerald-50 dark:hover:bg-emerald-950/50 text-xs font-bold rounded-xl hover:text-emerald-700 dark:hover:text-emerald-300 text-slate-700 dark:text-slate-300 border border-slate-200 hover:border-emerald-300 dark:border-slate-700 dark:hover:border-emerald-700 transition-all cursor-pointer shadow-2xs active:scale-95"
                                    >
                                        <svg v-if="link.label.toLowerCase().includes('disposisi')" class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <svg v-else class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                        </svg>
                                        <span>{{ link.label }}</span>
                                    </button>
                                </div>
                                <div v-if="notification.read_at === null" class="mt-2">
                                    <span class="inline-block w-2 h-2 rounded-full bg-primary-500"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="notifications.links && notifications.data.length > 0" class="px-4 py-3 border-t border-subtle bg-card-subtle flex items-center justify-between sm:px-6">
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                Menampilkan <span class="font-medium text-slate-700 dark:text-slate-200">{{ notifications.from }}</span>
                                sampai <span class="font-medium text-slate-700 dark:text-slate-200">{{ notifications.to }}</span>
                                dari <span class="font-medium text-slate-700 dark:text-slate-200">{{ notifications.total }}</span> pesan
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-xs -space-x-px" aria-label="Pagination">
                                <Link
                                    v-for="(link, i) in notifications.links"
                                    :key="i"
                                    :href="link.url || '#'"
                                    v-html="link.label"
                                    :class="[
                                        'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                                        link.active ? 'z-10 bg-primary-50 dark:bg-primary-900/30 border-primary-500 text-primary-600 dark:text-primary-400' : 'bg-sidebar border-theme text-slate-500 hover:bg-card-subtle',
                                        !link.url && 'opacity-50 cursor-not-allowed',
                                        i === 0 && 'rounded-l-md',
                                        i === notifications.links.length - 1 && 'rounded-r-md'
                                    ]"
                                    :disabled="!link.url"
                                />
                            </nav>
                        </div>
                    </div>
                    
                    <!-- Mobile pagination -->
                    <div class="flex items-center justify-between w-full sm:hidden">
                        <Link
                            :href="notifications.prev_page_url || '#'"
                            :class="[
                                'relative inline-flex items-center px-4 py-2 border border-theme text-sm font-medium rounded-md text-slate-700 bg-sidebar hover:bg-card-subtle',
                                !notifications.prev_page_url && 'opacity-50 cursor-not-allowed'
                            ]"
                        >
                            Sebelumnya
                        </Link>
                        <span class="text-sm text-slate-500">{{ notifications.current_page }} / {{ notifications.last_page }}</span>
                        <Link
                            :href="notifications.next_page_url || '#'"
                            :class="[
                                'relative inline-flex items-center px-4 py-2 border border-theme text-sm font-medium rounded-md text-slate-700 bg-sidebar hover:bg-card-subtle',
                                !notifications.next_page_url && 'opacity-50 cursor-not-allowed'
                            ]"
                        >
                            Selanjutnya
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════
             MODAL POP-UP PREVIEW DOKUMEN / DISPOSISI / SURAT ASLI
        ══════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div 
                    v-if="isModalOpen" 
                    class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-slate-900/75 backdrop-blur-sm"
                    @click.self="closeModal"
                >
                    <div 
                        class="bg-white dark:bg-slate-900 w-full max-w-5xl h-[90vh] rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 flex flex-col overflow-hidden animate-scale-up"
                    >
                        <!-- Modal Header -->
                        <div class="px-5 py-3.5 bg-slate-50 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700/80 flex items-center justify-between gap-3 shrink-0">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" :class="modalTitle.toLowerCase().includes('disposisi') ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300' : 'bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300'">
                                    <svg v-if="modalTitle.toLowerCase().includes('disposisi')" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-sm sm:text-base font-black text-slate-800 dark:text-slate-100 truncate">
                                        {{ modalTitle }}
                                    </h3>
                                    <p v-if="modalSubtitle" class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                        {{ modalSubtitle }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <!-- Tombol Cetak jika iframe (terutama disposisi) -->
                                <button
                                    v-if="modalType === 'iframe'"
                                    type="button"
                                    @click="printModalContent"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-700 hover:bg-slate-100 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-600 text-xs font-bold transition-all shadow-2xs cursor-pointer active:scale-95"
                                    title="Cetak lembar dokumen"
                                >
                                    <svg class="w-3.5 h-3.5 text-slate-600 dark:text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                    </svg>
                                    <span class="hidden sm:inline">Cetak</span>
                                </button>

                                <!-- Tombol Buka Tab Baru (sebagai opsi tambahan) -->
                                <a
                                    :href="modalUrl"
                                    target="_blank"
                                    class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-200/60 dark:hover:bg-slate-700/60 transition-colors"
                                    title="Buka di tab baru"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </a>

                                <!-- Tombol Tutup -->
                                <button
                                    type="button"
                                    @click="closeModal"
                                    class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-200/60 dark:hover:bg-slate-700/60 transition-colors cursor-pointer"
                                    title="Tutup (Esc)"
                                >
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Status & Tindak Lanjut Disposisi (Khusus jika melihat lembar disposisi) -->
                        <div 
                            v-if="currentDisposisi" 
                            class="px-5 py-3 bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700/80 flex flex-wrap items-center justify-between gap-3 shrink-0"
                        >
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Status Tugas:</span>
                                <span 
                                    class="px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider border inline-flex items-center gap-1.5 shadow-2xs"
                                    :class="{
                                        'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-700 dark:text-slate-200': currentDisposisi.status === 'menunggu',
                                        'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/60 dark:text-blue-300': currentDisposisi.status === 'dibaca',
                                        'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300': currentDisposisi.status === 'dikerjakan',
                                        'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300': currentDisposisi.status === 'selesai'
                                    }"
                                >
                                    <span class="w-2 h-2 rounded-full"
                                        :class="{
                                            'bg-slate-400': currentDisposisi.status === 'menunggu',
                                            'bg-blue-500': currentDisposisi.status === 'dibaca',
                                            'bg-amber-500': currentDisposisi.status === 'dikerjakan',
                                            'bg-emerald-500': currentDisposisi.status === 'selesai'
                                        }"></span>
                                    <span>
                                        {{ currentDisposisi.status === 'menunggu' ? 'Menunggu' : (currentDisposisi.status === 'dibaca' ? 'Sudah Dibaca YBS' : (currentDisposisi.status === 'dikerjakan' ? 'Sedang Dikerjakan' : 'Selesai Dilaksanakan')) }}
                                    </span>
                                </span>

                                <div v-if="currentDisposisi.catatan_penyelesaian" class="text-xs text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-700 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-600 max-w-sm truncate" :title="currentDisposisi.catatan_penyelesaian">
                                    <span class="font-bold text-slate-700 dark:text-slate-200">Catatan:</span> {{ currentDisposisi.catatan_penyelesaian }}
                                </div>
                            </div>

                            <!-- Tombol Aksi Langsung bagi Pegawai Penerima -->
                            <div class="flex items-center gap-2">
                                <button
                                    v-if="currentDisposisi.status !== 'dikerjakan' && currentDisposisi.status !== 'selesai'"
                                    type="button"
                                    @click="quickUpdateStatus('dikerjakan')"
                                    :disabled="isUpdatingStatus"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-500 hover:bg-amber-600 active:scale-95 text-white text-xs font-bold rounded-xl transition-all shadow-xs cursor-pointer disabled:opacity-50"
                                    title="Tandai bahwa Anda sedang memproses atau menindaklanjuti instruksi ini"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Tandai Sedang Dikerjakan</span>
                                </button>

                                <button
                                    v-if="currentDisposisi.status !== 'selesai'"
                                    type="button"
                                    @click="openCompletePrompt"
                                    :disabled="isUpdatingStatus"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs font-bold rounded-xl transition-all shadow-xs cursor-pointer disabled:opacity-50"
                                    title="Tandai bahwa tugas instruksi ini sudah tuntas dilaksanakan"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Tandai Selesai Dilaksanakan</span>
                                </button>

                                <span v-else class="text-xs font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1 bg-emerald-50 dark:bg-emerald-950/50 px-3 py-1 rounded-xl border border-emerald-200 dark:border-emerald-800">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Tugas Selesai Dilaksanakan</span>
                                </span>
                            </div>
                        </div>

                        <!-- Modal Body / Content -->
                        <div class="relative flex-1 bg-slate-100 dark:bg-slate-950 overflow-hidden">
                            <!-- Loading Indicator -->
                            <div 
                                v-if="isIframeLoading" 
                                class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-white/80 dark:bg-slate-900/80 backdrop-blur-xs"
                            >
                                <div class="w-10 h-10 border-3 border-emerald-500 border-t-transparent rounded-full animate-spin mb-3"></div>
                                <p class="text-xs font-bold text-slate-600 dark:text-slate-300">Memuat dokumen...</p>
                            </div>

                            <!-- Preview Tipe Gambar -->
                            <div v-if="modalType === 'image'" class="w-full h-full p-4 flex items-center justify-center overflow-auto bg-slate-950/90">
                                <img 
                                    :src="modalUrl" 
                                    @load="isIframeLoading = false"
                                    class="max-w-full max-h-full object-contain rounded-xl shadow-lg" 
                                    alt="Surat Asli" 
                                />
                            </div>

                            <!-- Preview Tipe PDF atau Iframe (Disposisi) -->
                            <iframe
                                v-else
                                ref="iframeRef"
                                :src="modalUrl"
                                class="w-full h-full border-0 bg-white"
                                @load="isIframeLoading = false"
                            ></iframe>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Complete Disposisi Modal Prompt -->
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div 
                    v-if="isCompleteModalOpen" 
                    class="fixed inset-0 z-60 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs"
                    @click.self="isCompleteModalOpen = false"
                >
                    <div class="bg-white dark:bg-slate-800 w-full max-w-md rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 p-5 space-y-4">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-700">
                            <h4 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                Konfirmasi Penyelesaian Tugas
                            </h4>
                            <button @click="isCompleteModalOpen = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <p class="text-xs text-slate-600 dark:text-slate-300">
                            Tandai bahwa penugasan disposisi ini telah selesai Anda laksanakan. Anda dapat menyertakan keterangan atau laporan singkat hasil pelaksanaan di bawah:
                        </p>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Catatan / Hasil Pelaksanaan (Opsional):
                            </label>
                            <textarea 
                                v-model="completeNote"
                                rows="3"
                                class="w-full text-xs p-2.5 border border-slate-300 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 focus:ring-emerald-500 focus:border-emerald-500"
                                placeholder="Contoh: Sudah dihadiri dan berkas laporan telah diserahkan ke bagian terkait..."
                            ></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-700">
                            <button 
                                type="button"
                                @click="isCompleteModalOpen = false"
                                class="px-3.5 py-1.5 text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition-colors cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="button"
                                @click="submitComplete"
                                :disabled="isUpdatingStatus"
                                class="px-4 py-1.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-xs transition-colors cursor-pointer disabled:opacity-50"
                            >
                                {{ isUpdatingStatus ? 'Menyimpan...' : 'Selesaikan Tugas' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>
