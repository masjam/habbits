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
    gamification_active: props.settings.gamification_active === '1' || props.settings.gamification_active === 'true',
    push_notifications_active: props.settings.push_notifications_active === '1' || props.settings.push_notifications_active === 'true',
    dark_mode_active: props.settings.dark_mode_active === '1' || props.settings.dark_mode_active === 'true',
    auto_warning_active: props.settings.auto_warning_active === '1' || props.settings.auto_warning_active === 'true',
    custom_habit_divisions_active: props.settings.custom_habit_divisions_active === '1' || props.settings.custom_habit_divisions_active === 'true',
    feature_badges: props.settings.feature_badges === '1' || props.settings.feature_badges === 'true',
    feature_divisi: props.settings.feature_divisi === '1' || props.settings.feature_divisi === 'true',
    feature_cuti: props.settings.feature_cuti === '1' || props.settings.feature_cuti === 'true',
    feature_idcard: props.settings.feature_idcard === '1' || props.settings.feature_idcard === 'true',
    feature_notes: props.settings.feature_notes === '1' || props.settings.feature_notes === 'true',
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
    <Head title="Pengaturan HR & Fitur Lanjutan" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-2xl text-slate-800 leading-tight">Pengaturan HR & Fitur Lanjutan</h2>
        </template>

        <div class="max-w-full mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
            
            <div v-if="$page.props.flash?.success" class="mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 flex items-center gap-3 shadow-sm">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-bold">{{ $page.props.flash.success }}</span>
            </div>

            <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-2 gap-6 xl:gap-8">
                
                <!-- Kolom 1: Fitur HR -->
                <div class="space-y-6">
                    <div class="bg-white overflow-hidden shadow-sm rounded-3xl border border-slate-100 p-6 relative">
                        <div v-if="$page.props.auth.roles?.includes('superadmin')">
                            <div class="flex flex-col mb-6">
                                <h2 class="text-xl font-black text-slate-800 flex items-center gap-2">
                                    <svg class="w-6 h-6 text-fuchsia-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    Fitur HR (Manajemen Pegawai)
                                </h2>
                                <p class="text-xs text-slate-500 mt-2">Eksklusif fitur-fitur tambahan untuk interaksi dan analitik pegawai tingkat lanjut.</p>
                            </div>
                            
                            <div class="space-y-3">
                                <!-- Gamifikasi HR (Badge Manual) -->
                                <div class="flex items-start bg-slate-50 p-3 rounded-xl border border-slate-100 hover:border-slate-200 transition-colors">
                                    <div class="flex h-5 items-center mt-0.5">
                                        <input id="feature_badges" type="checkbox" v-model="form.feature_badges" class="h-4 w-4 rounded border-slate-300 text-fuchsia-600 focus:ring-fuchsia-600 cursor-pointer" />
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="feature_badges" class="font-bold text-slate-700 cursor-pointer">Lencana Khusus (Manual Badge)</label>
                                        <p class="text-slate-500 text-xs mt-0.5">Berikan badge penghargaan manual kepada pegawai pilihan.</p>
                                    </div>
                                </div>

                                <!-- Fitur Divisi -->
                                <div class="flex items-start bg-slate-50 p-3 rounded-xl border border-slate-100 hover:border-slate-200 transition-colors">
                                    <div class="flex h-5 items-center mt-0.5">
                                        <input id="feature_divisi" type="checkbox" v-model="form.feature_divisi" class="h-4 w-4 rounded border-slate-300 text-fuchsia-600 focus:ring-fuchsia-600 cursor-pointer" />
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="feature_divisi" class="font-bold text-slate-700 cursor-pointer">Grup / Divisi Jabatan</label>
                                        <p class="text-slate-500 text-xs mt-0.5">Kelompokkan pegawai dan berikan filter leaderboard per-divisi.</p>
                                    </div>
                                </div>

                                <!-- Status Kehadiran (Cuti/Sakit) -->
                                <div class="flex items-start bg-slate-50 p-3 rounded-xl border border-slate-100 hover:border-slate-200 transition-colors">
                                    <div class="flex h-5 items-center mt-0.5">
                                        <input id="feature_cuti" type="checkbox" v-model="form.feature_cuti" class="h-4 w-4 rounded border-slate-300 text-fuchsia-600 focus:ring-fuchsia-600 cursor-pointer" />
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="feature_cuti" class="font-bold text-slate-700 cursor-pointer">Status Cuti / Sakit / Dinas</label>
                                        <p class="text-slate-500 text-xs mt-0.5">Tandai pegawai agar mereka di-gray out di leaderboard tanpa memutus habitnya.</p>
                                    </div>
                                </div>

                                <!-- ID Card Digital -->
                                <div class="flex items-start bg-slate-50 p-3 rounded-xl border border-slate-100 hover:border-slate-200 transition-colors">
                                    <div class="flex h-5 items-center mt-0.5">
                                        <input id="feature_idcard" type="checkbox" v-model="form.feature_idcard" class="h-4 w-4 rounded border-slate-300 text-fuchsia-600 focus:ring-fuchsia-600 cursor-pointer" />
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="feature_idcard" class="font-bold text-slate-700 cursor-pointer">Kartu Profil (Digital ID Card)</label>
                                        <p class="text-slate-500 text-xs mt-0.5">Popup profil mewah dengan avatar dan analitik ringkas saat nama diklik.</p>
                                    </div>
                                </div>

                                <!-- Catatan Pimpinan -->
                                <div class="flex items-start bg-slate-50 p-3 rounded-xl border border-slate-100 hover:border-slate-200 transition-colors">
                                    <div class="flex h-5 items-center mt-0.5">
                                        <input id="feature_notes" type="checkbox" v-model="form.feature_notes" class="h-4 w-4 rounded border-slate-300 text-fuchsia-600 focus:ring-fuchsia-600 cursor-pointer" />
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="feature_notes" class="font-bold text-slate-700 cursor-pointer">Catatan Rahasia Pimpinan (Notes)</label>
                                        <p class="text-slate-500 text-xs mt-0.5">Sisipkan pesan/evaluasi pribadi pada profil pegawai.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kolom 2: Pengaturan Lanjutan -->
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
