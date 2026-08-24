<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Editor from '@tinymce/tinymce-vue'

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({})
    }
})

const form = useForm({
    announcement_text: props.settings.announcement_text || '',
    popup_active: props.settings.popup_active === '1' || props.settings.popup_active === true || props.settings.popup_active === 'true',
    popup_text: props.settings.popup_text || '',
    youtube_link: props.settings.youtube_link || '',
    running_text: props.settings.running_text || '',
    gamification_active: props.settings.gamification_active === '1' || props.settings.gamification_active === 'true',
    push_notifications_active: props.settings.push_notifications_active === '1' || props.settings.push_notifications_active === 'true',
    dark_mode_active: props.settings.dark_mode_active === '1' || props.settings.dark_mode_active === 'true',
    auto_warning_active: props.settings.auto_warning_active === '1' || props.settings.auto_warning_active === 'true',
    custom_habit_divisions_active: props.settings.custom_habit_divisions_active === '1' || props.settings.custom_habit_divisions_active === 'true',
})

const submit = () => {
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Optional: show a local toast/success message if not relying on session flash alone
        }
    })
}
</script>

<template>
    <Head title="Pengaturan Sistem" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-2xl text-slate-800 leading-tight">Pengaturan Sistem</h2>
        </template>

        <div class="max-w-full mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
            
            <div v-if="$page.props.flash?.success" class="mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 flex items-center gap-3 shadow-sm">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-bold">{{ $page.props.flash.success }}</span>
            </div>

            <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-3 xl:grid-cols-3 gap-6 xl:gap-8">
                
                <!-- Kolom 1: Pengaturan Umum (Pengumuman & Popup) -->
                <div class="space-y-6">
                    <div class="bg-white overflow-hidden shadow-sm rounded-3xl border border-slate-100 p-6 relative h-full">
                        <h2 class="text-xl font-black text-slate-800 mb-6 flex items-center gap-2">
                            <svg class="w-6 h-6 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Pengaturan Teks Utama
                        </h2>

                        <div class="space-y-8">
                            <!-- Section: Dashboard User -->
                            <div>
                                <h3 class="text-base font-bold text-slate-800 mb-1">Pengumuman Dashboard</h3>
                                <p class="text-xs text-slate-500 mb-3">Teks ini akan muncul sebagai banner pengumuman di halaman dashboard semua user.</p>
                                
                                <div>
                                    <div class="border rounded-xl overflow-hidden border-slate-200">
                                        <Editor
                                            v-model="form.announcement_text"
                                            tinymce-script-src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"
                                            :init="{
                                                height: 250,
                                                menubar: false,
                                                plugins: [
                                                    'advlist', 'autolink', 'lists', 'link', 'image', 'charmap',
                                                    'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                                                    'insertdatetime', 'media', 'table', 'preview', 'help', 'wordcount'
                                                ],
                                                toolbar: 'undo redo | bold italic | alignleft aligncenter alignright | bullist numlist | link image | removeformat',
                                                content_style: 'body { font-family:Inter,Helvetica,Arial,sans-serif; font-size:14px }',
                                                promotion: false,
                                                branding: false
                                            }"
                                        />
                                    </div>
                                    <div v-if="form.errors.announcement_text" class="text-red-500 text-sm mt-1">{{ form.errors.announcement_text }}</div>
                                </div>
                            </div>

                            <hr class="border-slate-100" />

                            <!-- Section: Landing Page Pop-up -->
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <h3 class="text-base font-bold text-slate-800">Pop-up Landing Page</h3>
                                    <div class="flex items-center">
                                        <input
                                            id="popup_active"
                                            type="checkbox"
                                            v-model="form.popup_active"
                                            class="w-4 h-4 rounded border-slate-300 text-emerald-600 shadow-sm focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 transition-colors cursor-pointer"
                                        />
                                        <label for="popup_active" class="ml-2 block text-xs font-bold text-slate-700 cursor-pointer">
                                            Aktifkan
                                        </label>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-500 mb-3">Muncul otomatis saat pengunjung membuka halaman utama. Bisa disisipi gambar & teks.</p>
                                
                                <div class="space-y-4">
                                    <div v-if="form.errors.popup_active" class="text-red-500 text-sm mt-1">{{ form.errors.popup_active }}</div>

                                    <div>
                                        <div :class="['border rounded-xl overflow-hidden border-slate-200', !form.popup_active ? 'opacity-50 pointer-events-none' : '']">
                                            <Editor
                                                v-model="form.popup_text"
                                                tinymce-script-src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"
                                                :disabled="!form.popup_active"
                                                :init="{
                                                    height: 350,
                                                    menubar: false,
                                                    plugins: [
                                                        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap',
                                                        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                                                        'insertdatetime', 'media', 'table', 'preview', 'help', 'wordcount'
                                                    ],
                                                    toolbar: 'undo redo | blocks | bold italic forecolor | image link | alignleft aligncenter alignright | bullist numlist | removeformat',
                                                    content_style: 'body { font-family:Inter,Helvetica,Arial,sans-serif; font-size:14px }',
                                                    promotion: false,
                                                    branding: false
                                                }"
                                            />
                                        </div>
                                        <div v-if="form.errors.popup_text" class="text-red-500 text-sm mt-1">{{ form.errors.popup_text }}</div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Kolom 2: Media & Ekstra (Video & Running Text) -->
                <div class="space-y-6">
                    <div class="bg-white overflow-hidden shadow-sm rounded-3xl border border-slate-100 p-6 relative h-full">
                        <h2 class="text-xl font-black text-slate-800 mb-6 flex items-center gap-2">
                            <svg class="w-6 h-6 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Media & Visual Landing
                        </h2>

                        <div class="space-y-8">
                            <!-- Section: Video YouTube -->
                            <div>
                                <h3 class="text-base font-bold text-slate-800 mb-1">Video YouTube</h3>
                                <p class="text-xs text-slate-500 mb-3">Sematkan video edukasi atau sambutan di bagian pahlawan (hero) landing page.</p>
                                
                                <div>
                                    <label for="youtube_link" class="block text-sm font-bold text-slate-700 mb-2">Link YouTube (URL lengkap/ID)</label>
                                    <input
                                        id="youtube_link"
                                        type="text"
                                        v-model="form.youtube_link"
                                        class="w-full px-4 py-2.5 rounded-xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 transition-colors text-sm"
                                        placeholder="Contoh: https://www.youtube.com/watch?v=dQw4w9WgXcQ"
                                    />
                                    <div v-if="form.errors.youtube_link" class="text-red-500 text-sm mt-1">{{ form.errors.youtube_link }}</div>
                                </div>
                            </div>

                            <hr class="border-slate-100" />

                            <!-- Section: Running Text -->
                            <div>
                                <h3 class="text-base font-bold text-slate-800 mb-1">Running Text (Teks Berjalan)</h3>
                                <p class="text-xs text-slate-500 mb-3">Tampilkan teks berjalan (marquee) di bagian atas halaman utama.</p>
                                
                                <div>
                                    <label for="running_text" class="block text-sm font-bold text-slate-700 mb-2">Isi Teks Berjalan</label>
                                    <textarea
                                        id="running_text"
                                        v-model="form.running_text"
                                        rows="4"
                                        class="w-full px-4 py-2.5 rounded-xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 transition-colors text-sm"
                                        placeholder="Kosongkan jika tidak ingin menampilkan teks berjalan..."
                                    ></textarea>
                                    <div v-if="form.errors.running_text" class="text-red-500 text-sm mt-1">{{ form.errors.running_text }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kolom 3: Pengaturan Lanjutan -->
                <div class="space-y-6">
                    <div class="bg-white overflow-hidden shadow-sm rounded-3xl border border-slate-100 p-6 relative">
                        <div v-if="$page.props.auth.roles?.includes('superadmin')">
                            <div class="flex flex-col mb-6">
                                <h2 class="text-xl font-black text-slate-800 flex items-center gap-2">
                                    <svg class="w-6 h-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                    </svg>
                                    Fitur Lanjutan
                                </h2>
                                <p class="text-xs text-slate-500 mt-2">Aktifkan atau nonaktifkan fitur-fitur eksperimental (Fase 2 & Fase 3).</p>
                            </div>
                            
                            <div class="space-y-3">
                                <!-- Gamification -->
                                <div class="flex items-start bg-slate-50 p-4 rounded-xl border border-slate-100 hover:border-slate-200 transition-colors">
                                    <div class="flex h-5 items-center mt-0.5">
                                        <input id="gamification_active" type="checkbox" v-model="form.gamification_active" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 cursor-pointer" />
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="gamification_active" class="font-bold text-slate-700 cursor-pointer">Sistem Gamifikasi</label>
                                        <p class="text-slate-500 text-xs mt-0.5">Reward lencana digital untuk pengguna.</p>
                                    </div>
                                </div>

                                <!-- Push Notifications -->
                                <div class="flex items-start bg-slate-50 p-4 rounded-xl border border-slate-100 hover:border-slate-200 transition-colors">
                                    <div class="flex h-5 items-center mt-0.5">
                                        <input id="push_notifications_active" type="checkbox" v-model="form.push_notifications_active" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 cursor-pointer" />
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="push_notifications_active" class="font-bold text-slate-700 cursor-pointer">Push Notifications</label>
                                        <p class="text-slate-500 text-xs mt-0.5">Kirim notifikasi langsung ke HP (PWA).</p>
                                    </div>
                                </div>

                                <!-- Custom Habits -->
                                <div class="flex items-start bg-slate-50 p-4 rounded-xl border border-slate-100 hover:border-slate-200 transition-colors">
                                    <div class="flex h-5 items-center mt-0.5">
                                        <input id="custom_habit_divisions_active" type="checkbox" v-model="form.custom_habit_divisions_active" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 cursor-pointer" />
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="custom_habit_divisions_active" class="font-bold text-slate-700 cursor-pointer">Custom Habit per Divisi</label>
                                        <p class="text-slate-500 text-xs mt-0.5">Habit spesifik untuk grup tertentu.</p>
                                    </div>
                                </div>

                                <!-- Auto Warning -->
                                <div class="flex items-start bg-slate-50 p-4 rounded-xl border border-slate-100 hover:border-slate-200 transition-colors">
                                    <div class="flex h-5 items-center mt-0.5">
                                        <input id="auto_warning_active" type="checkbox" v-model="form.auto_warning_active" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 cursor-pointer" />
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="auto_warning_active" class="font-bold text-slate-700 cursor-pointer">Auto-Warning System</label>
                                        <p class="text-slate-500 text-xs mt-0.5">Peringatan bagi yang di bawah target.</p>
                                    </div>
                                </div>

                                <!-- Dark Mode -->
                                <div class="flex items-start bg-slate-50 p-4 rounded-xl border border-slate-100 hover:border-slate-200 transition-colors">
                                    <div class="flex h-5 items-center mt-0.5">
                                        <input id="dark_mode_active" type="checkbox" v-model="form.dark_mode_active" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 cursor-pointer" />
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="dark_mode_active" class="font-bold text-slate-700 cursor-pointer">Dark Mode Toggle</label>
                                        <p class="text-slate-500 text-xs mt-0.5">Aktifkan opsi tema gelap bagi pengguna.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="bg-white rounded-3xl border border-slate-100 p-6 shadow-sm flex flex-col items-center">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full py-4 bg-emerald-600 text-white font-black text-sm rounded-2xl hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-all shadow-lg hover:shadow-xl disabled:opacity-50 flex items-center justify-center gap-2"
                        >
                            <svg v-if="form.processing" class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            SIMPAN PENGATURAN
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </AuthenticatedLayout>
</template>
