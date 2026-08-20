<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

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
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Pengaturan Sistem</h2>
        </template>

        <div class="max-w-4xl mx-auto py-6">
            
            <div v-if="$page.props.flash?.success" class="mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-medium">{{ $page.props.flash.success }}</span>
            </div>

            <div class="bg-white overflow-hidden shadow-sm rounded-2xl border border-slate-100">
                <div class="p-6 md:p-8 bg-white border-b border-slate-200">
                    
                    <form @submit.prevent="submit" class="space-y-8">
                        
                        <!-- Section: Dashboard User -->
                        <div>
                            <h3 class="text-lg font-bold text-slate-800 mb-1">Pengumuman Dashboard User</h3>
                            <p class="text-sm text-slate-500 mb-4">Teks ini akan muncul sebagai banner pengumuman di halaman dashboard semua user.</p>
                            
                            <div>
                                <label for="announcement_text" class="block text-sm font-medium text-slate-700 mb-2">Teks Pengumuman</label>
                                <textarea
                                    id="announcement_text"
                                    v-model="form.announcement_text"
                                    rows="3"
                                    class="w-full rounded-xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 transition-colors"
                                    placeholder="Masukkan pengumuman di sini..."
                                ></textarea>
                                <div v-if="form.errors.announcement_text" class="text-red-500 text-sm mt-1">{{ form.errors.announcement_text }}</div>
                            </div>
                        </div>

                        <hr class="border-slate-100" />

                        <!-- Section: Landing Page Pop-up -->
                        <div>
                            <h3 class="text-lg font-bold text-slate-800 mb-1">Pop-up Landing Page</h3>
                            <p class="text-sm text-slate-500 mb-4">Atur pop-up informasi yang akan muncul otomatis saat pengunjung membuka halaman utama.</p>
                            
                            <div class="space-y-4">
                                <div class="flex items-center">
                                    <input
                                        id="popup_active"
                                        type="checkbox"
                                        v-model="form.popup_active"
                                        class="w-5 h-5 rounded border-slate-300 text-emerald-600 shadow-sm focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 transition-colors cursor-pointer"
                                    />
                                    <label for="popup_active" class="ml-3 block text-sm font-medium text-slate-700 cursor-pointer">
                                        Aktifkan Pop-up
                                    </label>
                                </div>
                                <div v-if="form.errors.popup_active" class="text-red-500 text-sm mt-1">{{ form.errors.popup_active }}</div>

                                <div>
                                    <label for="popup_text" class="block text-sm font-medium text-slate-700 mb-2">Konten Pop-up</label>
                                    <textarea
                                        id="popup_text"
                                        v-model="form.popup_text"
                                        rows="4"
                                        :disabled="!form.popup_active"
                                        :class="['w-full rounded-xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 transition-colors', !form.popup_active ? 'bg-slate-50 text-slate-400' : '']"
                                        placeholder="Ketik isi pesan pop-up..."
                                    ></textarea>
                                    <div v-if="form.errors.popup_text" class="text-red-500 text-sm mt-1">{{ form.errors.popup_text }}</div>
                                </div>
                            </div>
                        </div>

                        <hr class="border-slate-100" />

                        <!-- Section: Video YouTube -->
                        <div>
                            <h3 class="text-lg font-bold text-slate-800 mb-1">Video YouTube</h3>
                            <p class="text-sm text-slate-500 mb-4">Sematkan video edukasi atau sambutan di landing page.</p>
                            
                            <div>
                                <label for="youtube_link" class="block text-sm font-medium text-slate-700 mb-2">Link YouTube (URL lengkap atau ID Video)</label>
                                <input
                                    id="youtube_link"
                                    type="text"
                                    v-model="form.youtube_link"
                                    class="w-full rounded-xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 transition-colors"
                                    placeholder="Contoh: https://www.youtube.com/watch?v=dQw4w9WgXcQ"
                                />
                                <div v-if="form.errors.youtube_link" class="text-red-500 text-sm mt-1">{{ form.errors.youtube_link }}</div>
                                <p class="text-xs text-slate-400 mt-2">Sistem otomatis akan mengambil ID video dan menampilkannya sebagai embed.</p>
                            </div>
                        </div>

                        <hr class="border-slate-100" />

                        <!-- Section: Running Text -->
                        <div>
                            <h3 class="text-lg font-bold text-slate-800 mb-1">Running Text (Teks Berjalan)</h3>
                            <p class="text-sm text-slate-500 mb-4">Tampilkan teks berjalan (marquee) di bagian atas halaman utama (landing page).</p>
                            
                            <div>
                                <label for="running_text" class="block text-sm font-medium text-slate-700 mb-2">Teks Berjalan</label>
                                <input
                                    id="running_text"
                                    type="text"
                                    v-model="form.running_text"
                                    class="w-full rounded-xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 transition-colors"
                                    placeholder="Kosongkan jika tidak ingin menampilkan teks berjalan..."
                                />
                                <div v-if="form.errors.running_text" class="text-red-500 text-sm mt-1">{{ form.errors.running_text }}</div>
                            </div>
                        </div>

                        <hr class="border-slate-100" />

                        <!-- Section: Fitur Lanjutan (Phase 2 & 3) -->
                        <div v-if="$page.props.auth.roles?.includes('superadmin')">
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="text-lg font-bold text-slate-800">Pengaturan Fitur Lanjutan</h3>
                                <span class="px-2 py-0.5 rounded text-[10px] bg-amber-100 text-amber-700 font-bold uppercase tracking-wider">Super Admin</span>
                            </div>
                            <p class="text-sm text-slate-500 mb-4">Aktifkan atau nonaktifkan fitur-fitur pengembangan lanjutan (Fase 2 & Fase 3).</p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Gamification -->
                                <div class="flex items-start bg-slate-50 p-4 rounded-xl border border-slate-100">
                                    <div class="flex h-5 items-center">
                                        <input id="gamification_active" type="checkbox" v-model="form.gamification_active" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600 cursor-pointer" />
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="gamification_active" class="font-bold text-slate-700 cursor-pointer">Sistem Gamifikasi (Badges/Streaks)</label>
                                        <p class="text-slate-500">Berikan reward lencana digital untuk pengguna yang konsisten.</p>
                                    </div>
                                </div>

                                <!-- Push Notifications -->
                                <div class="flex items-start bg-slate-50 p-4 rounded-xl border border-slate-100">
                                    <div class="flex h-5 items-center">
                                        <input id="push_notifications_active" type="checkbox" v-model="form.push_notifications_active" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600 cursor-pointer" />
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="push_notifications_active" class="font-bold text-slate-700 cursor-pointer">Push Notifications (PWA)</label>
                                        <p class="text-slate-500">Kirim notifikasi pengingat langsung ke HP pengguna.</p>
                                    </div>
                                </div>

                                <!-- Custom Habits -->
                                <div class="flex items-start bg-slate-50 p-4 rounded-xl border border-slate-100">
                                    <div class="flex h-5 items-center">
                                        <input id="custom_habit_divisions_active" type="checkbox" v-model="form.custom_habit_divisions_active" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600 cursor-pointer" />
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="custom_habit_divisions_active" class="font-bold text-slate-700 cursor-pointer">Custom Habit per Divisi</label>
                                        <p class="text-slate-500">Izinkan habit spesifik yang hanya berlaku untuk divisi/grup tertentu.</p>
                                    </div>
                                </div>

                                <!-- Auto Warning -->
                                <div class="flex items-start bg-slate-50 p-4 rounded-xl border border-slate-100">
                                    <div class="flex h-5 items-center">
                                        <input id="auto_warning_active" type="checkbox" v-model="form.auto_warning_active" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600 cursor-pointer" />
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="auto_warning_active" class="font-bold text-slate-700 cursor-pointer">Auto-Warning System</label>
                                        <p class="text-slate-500">Peringatan otomatis bagi pengguna di bawah target bulanan.</p>
                                    </div>
                                </div>

                                <!-- Dark Mode -->
                                <div class="flex items-start bg-slate-50 p-4 rounded-xl border border-slate-100">
                                    <div class="flex h-5 items-center">
                                        <input id="dark_mode_active" type="checkbox" v-model="form.dark_mode_active" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600 cursor-pointer" />
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="dark_mode_active" class="font-bold text-slate-700 cursor-pointer">Dark Mode Toggle</label>
                                        <p class="text-slate-500">Aktifkan opsi tema gelap bagi pengguna.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 flex justify-end">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-6 py-2.5 bg-emerald-600 text-white font-bold text-sm rounded-xl hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm disabled:opacity-50 flex items-center gap-2"
                            >
                                <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Simpan Pengaturan
                            </button>
                        </div>
                        
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
