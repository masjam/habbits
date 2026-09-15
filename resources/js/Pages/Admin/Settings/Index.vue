<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Editor from '@tinymce/tinymce-vue'

const page = usePage()

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({})
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
})

const submit = () => {
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Success notification via flash message
        }
    })
}
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
    </AuthenticatedLayout>
</template>
