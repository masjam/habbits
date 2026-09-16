<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Editor from '@tinymce/tinymce-vue'
import { usePWA } from '@/Composables/usePWA'

const page = usePage()

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({})
    },
    divisions: {
        type: Array,
        default: () => []
    },
    employees: {
        type: Array,
        default: () => []
    }
})

const isSuperAdmin = computed(() => {
    return !!(
        page.props.auth?.roles?.includes('superadmin') ||
        page.props.auth?.is_actual_superadmin
    )
})

const form = useForm({
    announcement_text: props.settings.announcement_text || '',
    popup_active: props.settings.popup_active === '1' || props.settings.popup_active === true || props.settings.popup_active === 'true',
    popup_text: props.settings.popup_text || '',
    youtube_link: props.settings.youtube_link || '',
    running_text: props.settings.running_text || '',
    push_notifications_active: props.settings.push_notifications_active === '1' || props.settings.push_notifications_active === true || props.settings.push_notifications_active === 'true',
})

const submit = () => {
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Success notification via flash message
        }
    })
}

// ─── PWA WEB PUSH NOTIFICATIONS ─────────────────────────────────────────────
const {
    isPushSupported,
    isPushSubscribed,
    pushPermission,
    isSubscribingPush,
    subscribeToPush,
    unsubscribeFromPush,
    sendTestPush,
    checkPushSubscription,
} = usePWA()

const pushStatus = ref({
    vapid_configured: true,
    total_system_subscriptions: 0,
    user_subscriptions_count: 0,
    loading: false,
})

const pushTestLoading = ref(false)
const pushTestMessage = ref('')
const pushTestStatus = ref('') // 'success' | 'error'

const loadPushStatus = async () => {
    try {
        pushStatus.value.loading = true
        const res = await fetch('/push-subscriptions/status')
        if (res.ok) {
            const data = await res.json()
            pushStatus.value = { ...pushStatus.value, ...data, loading: false }
        }
    } catch (e) {
        console.warn('Load push status error:', e)
    } finally {
        pushStatus.value.loading = false
    }
}

const handleSubscribeThisDevice = async () => {
    pushTestMessage.value = ''
    try {
        const vapidKey = page.props.webpush?.vapid_public_key || ''
        await subscribeToPush(vapidKey)
        await loadPushStatus()
        pushTestStatus.value = 'success'
        pushTestMessage.value = 'Perangkat ini berhasil didaftarkan untuk Push Notification!'
    } catch (e) {
        pushTestStatus.value = 'error'
        pushTestMessage.value = e.message || 'Gagal mendaftarkan perangkat.'
    }
}

const handleUnsubscribeThisDevice = async () => {
    pushTestMessage.value = ''
    try {
        await unsubscribeFromPush()
        await loadPushStatus()
        pushTestStatus.value = 'success'
        pushTestMessage.value = 'Perangkat ini telah dinonaktifkan dari Push Notification.'
    } catch (e) {
        pushTestStatus.value = 'error'
        pushTestMessage.value = e.message || 'Gagal mematikan notifikasi di perangkat ini.'
    }
}

const handleSendTestPush = async () => {
    pushTestLoading.value = true
    pushTestMessage.value = ''
    try {
        const res = await sendTestPush()
        pushTestStatus.value = 'success'
        pushTestMessage.value = res.message || 'Notifikasi berhasil dikirim ke perangkat Anda! Silakan periksa notifikasi di layar Anda.'
    } catch (e) {
        pushTestStatus.value = 'error'
        pushTestMessage.value = e.message || 'Gagal mengirimkan tes notifikasi.'
    } finally {
        pushTestLoading.value = false
    }
}

// ─── PUSH NOTIFICATION BROADCAST (ADMIN & SUPERADMIN) ───────────────────────
const showBroadcastModal = ref(false)
const broadcastLoading = ref(false)
const broadcastMessage = ref('')
const broadcastStatus = ref('') // 'success' | 'error'

const broadcastForm = ref({
    title: 'Pengingat Mutaba\'ah & Presensi 🔔',
    body: 'Bapak/Ibu guru dan karyawan, mohon pastikan presensi dan catatan mutaba\'ah harian hari ini sudah terisi.',
    target: 'all', // 'all' | 'division' | 'user'
    division: '',
    user_id: '',
    url: '/dashboard',
})

const handleOpenBroadcastModal = () => {
    broadcastMessage.value = ''
    broadcastStatus.value = ''
    showBroadcastModal.value = true
}

const handleSendBroadcast = async () => {
    if (!broadcastForm.value.title.trim() || !broadcastForm.value.body.trim()) {
        broadcastStatus.value = 'error'
        broadcastMessage.value = 'Judul dan isi pesan notifikasi wajib diisi.'
        return
    }

    broadcastLoading.value = true
    broadcastMessage.value = ''
    broadcastStatus.value = ''

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        const res = await fetch(route('push.broadcast'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(broadcastForm.value),
        })

        const data = await res.json()
        if (!res.ok) {
            throw new Error(data.message || 'Gagal menyiarkan notifikasi push.')
        }

        broadcastStatus.value = 'success'
        broadcastMessage.value = data.message || 'Notifikasi berhasil disiarkan!'
        await loadPushStatus()
    } catch (e) {
        broadcastStatus.value = 'error'
        broadcastMessage.value = e.message || 'Terjadi kesalahan saat mengirim notifikasi.'
    } finally {
        broadcastLoading.value = false
    }
}

onMounted(() => {
    loadPushStatus()
})
</script>

<template>
    <Head title="Pengaturan Sistem" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 dark:text-slate-100 leading-tight">Pengaturan Sistem</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Konfigurasi teks landing page, media visual, dan presensi berbasis GPS &amp; jadwal kerja.</p>
                </div>
            </div>
        </template>

        <div class="max-w-full mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
            
            <!-- Alert Flash Message -->
            <div v-if="$page.props.flash?.success" class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 flex items-center gap-3 shadow-xs">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-bold text-sm">{{ $page.props.flash.success }}</span>
            </div>

            <!-- Form Pengaturan (2 Kolom: Teks/Pengumuman & Media Landing) -->
            <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-2 gap-6 xl:gap-8 max-w-6xl mx-auto">
                
                <!-- ══════════════════════════════════════════════════════════════════
                     KOLOM 1: Pengaturan Teks Utama (Pengumuman & Popup)
                ══════════════════════════════════════════════════════════════════ -->
                <div class="space-y-6">
                    <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-xs rounded-3xl border border-slate-100 dark:border-slate-700 p-6 relative flex flex-col h-full">
                        <h2 class="text-lg font-black text-slate-800 dark:text-slate-100 mb-5 flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <span>1. Teks &amp; Pengumuman</span>
                        </h2>

                        <div class="space-y-6 flex-1">
                            <!-- Section: Dashboard User -->
                            <div>
                                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-1">Pengumuman Dashboard</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mb-2.5">Muncul sebagai banner pengumuman di dashboard seluruh pengguna.</p>
                                
                                <div>
                                    <div class="border rounded-xl overflow-hidden border-slate-200 dark:border-slate-700">
                                        <Editor
                                            v-model="form.announcement_text"
                                            tinymce-script-src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"
                                            :init="{
                                                height: 180,
                                                menubar: false,
                                                plugins: [
                                                    'advlist', 'autolink', 'lists', 'link', 'image', 'charmap',
                                                    'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                                                    'insertdatetime', 'media', 'table', 'preview', 'help', 'wordcount'
                                                ],
                                                toolbar: 'undo redo | bold italic | alignleft aligncenter alignright | bullist numlist | link | removeformat',
                                                content_style: 'body { font-family:Inter,Helvetica,Arial,sans-serif; font-size:13px }',
                                                promotion: false,
                                                branding: false,
                                                statusbar: false
                                            }"
                                        />
                                    </div>
                                    <div v-if="form.errors.announcement_text" class="text-red-500 text-xs mt-1">{{ form.errors.announcement_text }}</div>
                                </div>
                            </div>

                            <hr class="border-slate-100 dark:border-slate-700/60" />

                            <!-- Section: Popup Modal Landing -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div>
                                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Popup Modal (Halaman Depan)</h3>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">Muncul sekali saat pengunjung pertama kali membuka aplikasi.</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input
                                            type="checkbox"
                                            v-model="form.popup_active"
                                            class="sr-only peer"
                                        />
                                        <div class="w-11 h-6 bg-slate-200 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-emerald-600"></div>
                                    </label>
                                </div>

                                <div v-if="form.popup_active" class="mt-3">
                                    <div class="border rounded-xl overflow-hidden border-slate-200 dark:border-slate-700">
                                        <Editor
                                            v-model="form.popup_text"
                                            tinymce-script-src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"
                                            :init="{
                                                height: 180,
                                                menubar: false,
                                                plugins: [
                                                    'advlist', 'autolink', 'lists', 'link', 'image', 'charmap',
                                                    'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                                                    'insertdatetime', 'media', 'table', 'preview', 'help', 'wordcount'
                                                ],
                                                toolbar: 'undo redo | bold italic | alignleft aligncenter alignright | bullist numlist | link | removeformat',
                                                content_style: 'body { font-family:Inter,Helvetica,Arial,sans-serif; font-size:13px }',
                                                promotion: false,
                                                branding: false,
                                                statusbar: false
                                            }"
                                        />
                                    </div>
                                    <div v-if="form.errors.popup_text" class="text-red-500 text-xs mt-1">{{ form.errors.popup_text }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════════════════
                     KOLOM 2: Pengaturan Media Landing & Running Text
                ══════════════════════════════════════════════════════════════════ -->
                <div class="space-y-6">
                    <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-xs rounded-3xl border border-slate-100 dark:border-slate-700 p-6 relative flex flex-col h-full">
                        <h2 class="text-lg font-black text-slate-800 dark:text-slate-100 mb-5 flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <span>2. Media &amp; Visual Landing</span>
                        </h2>

                        <div class="space-y-6 flex-1">
                            <!-- Section: Video Youtube Profil Landing -->
                            <div>
                                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-1">Video Profil Youtube</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mb-2.5">Tautan video Youtube yang diputar di halaman utama (landing page).</p>
                                
                                <div>
                                    <label for="youtube_link" class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Link / URL Youtube</label>
                                    <input
                                        id="youtube_link"
                                        type="text"
                                        v-model="form.youtube_link"
                                        class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-xs focus:border-emerald-500 focus:ring focus:ring-emerald-200 text-xs text-slate-800 dark:text-slate-200 font-mono transition-colors"
                                        placeholder="Contoh: https://www.youtube.com/watch?v=dQw4w9WgXcQ"
                                    />
                                    <div v-if="form.errors.youtube_link" class="text-red-500 text-xs mt-1">{{ form.errors.youtube_link }}</div>
                                </div>
                            </div>

                            <hr class="border-slate-100 dark:border-slate-700/60" />

                            <!-- Section: Running Text -->
                            <div>
                                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-1">Running Text (Teks Berjalan)</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mb-2.5">Pesan berjalan (marquee) di bagian header paling atas halaman utama.</p>
                                
                                <div>
                                    <label for="running_text" class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Isi Teks Pesan Berjalan</label>
                                    <textarea
                                        id="running_text"
                                        v-model="form.running_text"
                                        rows="5"
                                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-xs focus:border-emerald-500 focus:ring focus:ring-emerald-200 text-xs text-slate-800 dark:text-slate-200 transition-colors"
                                        placeholder="Kosongkan jika tidak ingin menampilkan teks berjalan..."
                                    ></textarea>
                                    <div v-if="form.errors.running_text" class="text-red-500 text-xs mt-1">{{ form.errors.running_text }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════════════════
                     KOLOM 3 (FULL WIDTH): Pengaturan Push Notifications (PWA)
                ══════════════════════════════════════════════════════════════════ -->
                <div class="col-span-1 lg:col-span-2">
                    <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-xs rounded-3xl border border-slate-100 dark:border-slate-700 p-6 sm:p-7 relative space-y-5">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="flex items-start gap-3.5">
                                <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 shrink-0 shadow-xs mt-0.5">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-lg font-black text-slate-800 dark:text-slate-100">3. Push Notifications (PWA) &amp; Siaran Notifikasi</h2>
                                        <span v-if="pushStatus.vapid_configured" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                            ● VAPID Siap
                                        </span>
                                        <span v-else class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                                            ○ VAPID Belum Siap
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                        Kirimkan notifikasi pengingat ibadah, mutaba'ah harian, dan pengumuman sekolah langsung ke layar smartphone/laptop pengguna.
                                    </p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox" v-model="form.push_notifications_active" class="sr-only peer" />
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <!-- Sub-Panel: Kontrol & Pengujian Push (Tampil jika sistem aktif) -->
                        <div v-if="form.push_notifications_active" class="pt-4 border-t border-slate-100 dark:border-slate-700 space-y-4">
                            <!-- Status Bar Info -->
                            <div class="flex flex-wrap items-center justify-between gap-3 p-3.5 bg-indigo-50/60 dark:bg-indigo-950/30 rounded-2xl border border-indigo-100 dark:border-indigo-900/40 text-xs">
                                <div class="flex items-center gap-2 text-indigo-900 dark:text-indigo-200">
                                    <span class="font-bold">Total Perangkat Terdaftar di Sistem:</span>
                                    <span class="font-black px-2.5 py-0.5 rounded-md bg-indigo-200/70 dark:bg-indigo-900/60 text-indigo-900 dark:text-indigo-100">
                                        {{ pushStatus.total_system_subscriptions }} Perangkat
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-slate-500 dark:text-slate-400">Status Browser Saat Ini:</span>
                                    <span v-if="isPushSubscribed" class="font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Terdaftar
                                    </span>
                                    <span v-else class="font-bold text-amber-600 dark:text-amber-400">
                                        Belum Didaftarkan
                                    </span>
                                </div>
                            </div>

                            <!-- Feedback Alert -->
                            <div v-if="pushTestMessage" :class="[
                                'p-3.5 rounded-2xl text-xs flex items-center justify-between gap-2 font-medium',
                                pushTestStatus === 'success' 
                                    ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800'
                                    : 'bg-rose-50 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200 border border-rose-200 dark:border-rose-800'
                            ]">
                                <span>{{ pushTestMessage }}</span>
                                <button type="button" @click="pushTestMessage = ''" class="text-slate-400 hover:text-slate-600 cursor-pointer">✕</button>
                            </div>

                            <!-- Tombol Aksi Pengujian & Siaran -->
                            <div class="flex flex-wrap items-center gap-2.5">
                                <!-- Tombol 1: Izinkan & Daftarkan Browser Ini -->
                                <button
                                    v-if="!isPushSubscribed"
                                    type="button"
                                    @click="handleSubscribeThisDevice"
                                    :disabled="isSubscribingPush"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white font-bold text-xs shadow-xs transition-all cursor-pointer disabled:opacity-50"
                                >
                                    <svg v-if="isSubscribingPush" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg>
                                    <span>{{ isSubscribingPush ? 'Meminta Izin...' : '🔔 Daftarkan Perangkat Ini' }}</span>
                                </button>

                                <!-- Tombol 2: Kirim Tes Notifikasi PWA -->
                                <button
                                    v-if="isPushSubscribed"
                                    type="button"
                                    @click="handleSendTestPush"
                                    :disabled="pushTestLoading"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-bold text-xs shadow-xs transition-all cursor-pointer disabled:opacity-50"
                                >
                                    <svg v-if="pushTestLoading" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    <span>{{ pushTestLoading ? 'Mengirim Notifikasi...' : '🚀 Kirim Tes Notifikasi PWA' }}</span>
                                </button>

                                <!-- Tombol 3: Siarkan Notifikasi ke Pegawai -->
                                <button
                                    type="button"
                                    @click="handleOpenBroadcastModal"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-linear-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 active:scale-95 text-white font-bold text-xs shadow-xs transition-all cursor-pointer"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                                    </svg>
                                    <span>📢 Kirim Siaran ke Pegawai</span>
                                </button>

                                <!-- Tombol 4: Hapus Subscription Perangkat Ini -->
                                <button
                                    v-if="isPushSubscribed"
                                    type="button"
                                    @click="handleUnsubscribeThisDevice"
                                    :disabled="isSubscribingPush"
                                    class="inline-flex items-center gap-1.5 px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-slate-600 dark:text-slate-300 hover:text-rose-600 dark:hover:text-rose-400 font-bold text-xs transition-all cursor-pointer disabled:opacity-50"
                                >
                                    <span>Nonaktifkan di Perangkat Ini</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════════════════
                     SUBMIT ACTION BUTTON (FULL WIDTH - 2 KOLOM)
                ══════════════════════════════════════════════════════════════════ -->
                <div class="col-span-1 lg:col-span-2">
                    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 p-5 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            Pastikan data pengaturan telah sesuai sebelum menekan tombol simpan.
                        </div>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full sm:w-auto px-8 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm rounded-2xl focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-all shadow-md hover:shadow-lg disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>SIMPAN PENGATURAN SISTEM</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- ════ MODAL SIARKAN PUSH NOTIFICATION (ADMIN & SUPER ADMIN) ════ -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showBroadcastModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs"
                @click.self="showBroadcastModal = false"
            >
                <div class="bg-white dark:bg-slate-900 w-full max-w-lg rounded-3xl shadow-2xl border border-slate-200/80 dark:border-slate-800 overflow-hidden flex flex-col max-h-[90vh]">
                    <!-- Modal Header -->
                    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800/80 flex items-center justify-between bg-linear-to-r from-indigo-50/50 to-purple-50/30 dark:from-indigo-950/20 dark:to-purple-950/20">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-linear-to-br from-purple-500 to-indigo-600 flex items-center justify-center text-white shadow-md shadow-indigo-500/20">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 dark:text-slate-100 text-base">Kirim Siaran Push Notification</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Hak Akses: Admin &amp; Super Admin</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="showBroadcastModal = false"
                            class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        >
                            ✕
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6 overflow-y-auto space-y-4">
                        <!-- Alert Status -->
                        <div
                            v-if="broadcastMessage"
                            :class="[
                                'p-3.5 rounded-2xl text-xs font-medium flex items-center justify-between gap-2',
                                broadcastStatus === 'success'
                                    ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800'
                                    : 'bg-rose-50 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200 border border-rose-200 dark:border-rose-800'
                            ]"
                        >
                            <span>{{ broadcastMessage }}</span>
                            <button type="button" @click="broadcastMessage = ''" class="text-slate-400 hover:text-slate-600">✕</button>
                        </div>

                        <!-- Target Selection -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                                Target Penerima Notifikasi
                            </label>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    type="button"
                                    @click="broadcastForm.target = 'all'"
                                    :class="[
                                        'py-2 px-3 rounded-xl text-xs font-bold transition-all border text-center cursor-pointer',
                                        broadcastForm.target === 'all'
                                            ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                            : 'bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-100'
                                    ]"
                                >
                                    🌐 Semua Pegawai
                                </button>
                                <button
                                    type="button"
                                    @click="broadcastForm.target = 'division'"
                                    :class="[
                                        'py-2 px-3 rounded-xl text-xs font-bold transition-all border text-center cursor-pointer',
                                        broadcastForm.target === 'division'
                                            ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                            : 'bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-100'
                                    ]"
                                >
                                    🏢 Per Divisi
                                </button>
                                <button
                                    type="button"
                                    @click="broadcastForm.target = 'user'"
                                    :class="[
                                        'py-2 px-3 rounded-xl text-xs font-bold transition-all border text-center cursor-pointer',
                                        broadcastForm.target === 'user'
                                            ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                            : 'bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-100'
                                    ]"
                                >
                                    👤 Perorangan
                                </button>
                            </div>
                        </div>

                        <!-- Dropdown Divisi jika target division -->
                        <div v-if="broadcastForm.target === 'division'" class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Pilih Divisi</label>
                            <select
                                v-model="broadcastForm.division"
                                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="">-- Pilih Divisi --</option>
                                <option v-for="div in props.divisions" :key="div" :value="div">{{ div }}</option>
                            </select>
                        </div>

                        <!-- Dropdown Pegawai jika target user -->
                        <div v-if="broadcastForm.target === 'user'" class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Pilih Pegawai</label>
                            <select
                                v-model="broadcastForm.user_id"
                                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="">-- Pilih Nama Pegawai --</option>
                                <option v-for="emp in props.employees" :key="emp.id" :value="emp.id">
                                    {{ emp.name }} {{ emp.divisi ? `(${emp.divisi})` : '' }}
                                </option>
                            </select>
                        </div>

                        <!-- Judul Pesan -->
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Judul Notifikasi <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                v-model="broadcastForm.title"
                                placeholder="Contoh: Pengingat Presensi / Mutaba'ah"
                                maxlength="100"
                                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>

                        <!-- Isi Pesan -->
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Isi Pesan Notifikasi <span class="text-rose-500">*</span>
                            </label>
                            <textarea
                                v-model="broadcastForm.body"
                                rows="3"
                                placeholder="Tuliskan pesan yang akan muncul di layar notifikasi..."
                                maxlength="255"
                                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 resize-none"
                            ></textarea>
                            <div class="flex justify-end text-[10px] text-slate-400">
                                {{ broadcastForm.body.length }}/255 karakter
                            </div>
                        </div>

                        <!-- URL Klik (Opsional) -->
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Link Tujuan Saat Notifikasi Diklik (Opsional)
                            </label>
                            <input
                                type="text"
                                v-model="broadcastForm.url"
                                placeholder="/dashboard"
                                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>

                        <!-- Live Preview Card -->
                        <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-2xl border border-slate-200/70 dark:border-slate-700/60 text-xs space-y-1.5">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">
                                Pratinjau Tampilan di Perangkat Pegawai:
                            </span>
                            <div class="flex items-start gap-2.5 p-2 bg-white dark:bg-slate-900 rounded-xl border border-slate-150 dark:border-slate-700 shadow-2xs">
                                <img src="/img/gh.png" alt="Icon" class="w-7 h-7 rounded-lg object-contain shrink-0" />
                                <div class="min-w-0">
                                    <p class="font-bold text-slate-800 dark:text-slate-100 text-xs truncate">{{ broadcastForm.title || 'Judul Notifikasi' }}</p>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-2 mt-0.5">{{ broadcastForm.body || 'Isi pesan notifikasi...' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-850 flex items-center justify-end gap-2.5">
                        <button
                            type="button"
                            @click="showBroadcastModal = false"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200/60 dark:hover:bg-slate-700/60 transition-all cursor-pointer"
                        >
                            Tutup
                        </button>
                        <button
                            type="button"
                            @click="handleSendBroadcast"
                            :disabled="broadcastLoading"
                            class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-linear-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 active:scale-95 text-white font-bold text-xs shadow-md shadow-indigo-500/20 transition-all cursor-pointer disabled:opacity-50"
                        >
                            <svg v-if="broadcastLoading" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <span>{{ broadcastLoading ? 'Sedang Menyiarkan...' : 'Kirim Siaran Sekarang' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>
