<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    status: {
        type: String,
    },
    profileStatus: {
        type: String,
    },
    user: {
        type: Object,
        required: true,
    }
})

const passwordInput = ref(null)
const currentPasswordInput = ref(null)

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const profileForm = useForm({
    name: '',
    phone: '',
    personal_target: '',
})

import { onMounted } from 'vue'

onMounted(() => {
    profileForm.name = props.user.name
    profileForm.phone = props.user.phone || ''
    profileForm.personal_target = props.user.personal_target || ''
})

const updateProfile = () => {
    profileForm.put(route('profile.update'), {
        preserveScroll: true,
    })
}

const updatePassword = () => {
    form.put(route('profile.password'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation')
                passwordInput.value.focus()
            }
            if (form.errors.current_password) {
                form.reset('current_password')
                currentPasswordInput.value.focus()
            }
        },
    })
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Profil Saya" />

        <div class="max-w-xl mx-auto space-y-6">
            <!-- Header -->
            <div>
                <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Profil Saya</h2>
                <p class="text-sm text-slate-500 mt-1">Kelola keamanan akun Anda dengan mengganti password.</p>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                <header>
                    <h2 class="text-lg font-bold text-slate-900">Informasi Profil</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Perbarui informasi profil dan target pribadi Anda.
                    </p>
                </header>

                <form @submit.prevent="updateProfile" class="mt-6 space-y-6">
                    <div>
                        <label for="name" class="block text-sm font-bold text-slate-700">Nama Lengkap</label>
                        <input
                            id="name"
                            v-model="profileForm.name"
                            type="text"
                            class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            required
                        />
                        <p v-if="profileForm.errors.name" class="mt-2 text-sm text-rose-600">
                            {{ profileForm.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-bold text-slate-700">No. HP/WA</label>
                        <input
                            id="phone"
                            v-model="profileForm.phone"
                            type="text"
                            class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            placeholder="Contoh: 081234567890"
                        />
                        <p v-if="profileForm.errors.phone" class="mt-2 text-sm text-rose-600">
                            {{ profileForm.errors.phone }}
                        </p>
                    </div>

                    <div>
                        <label for="personal_target" class="block text-sm font-bold text-slate-700">Target Pribadi Bulanan (%)</label>
                        <input
                            id="personal_target"
                            v-model="profileForm.personal_target"
                            type="number"
                            step="0.1"
                            min="0"
                            max="100"
                            class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            placeholder="Opsional. Kosongkan untuk mengikuti target instansi."
                        />
                        <p v-if="profileForm.errors.personal_target" class="mt-2 text-sm text-rose-600">
                            {{ profileForm.errors.personal_target }}
                        </p>
                    </div>

                    <div class="flex items-center gap-4">
                        <button :disabled="profileForm.processing" class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-xl font-bold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition shadow-sm disabled:opacity-50">
                            Simpan Profil
                        </button>

                        <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                            <p v-if="profileForm.recentlySuccessful || profileStatus === 'profile-updated'" class="text-sm text-emerald-600 font-bold">Tersimpan.</p>
                        </Transition>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                <header>
                    <h2 class="text-lg font-bold text-slate-900">Perbarui Password</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Pastikan akun Anda menggunakan kata sandi yang panjang dan acak agar tetap aman.
                    </p>
                </header>

                <form @submit.prevent="updatePassword" class="mt-6 space-y-6">
                    <div>
                        <label for="current_password" class="block text-sm font-bold text-slate-700">Password Lama</label>
                        <input
                            id="current_password"
                            ref="currentPasswordInput"
                            v-model="form.current_password"
                            type="password"
                            class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            autocomplete="current-password"
                        />
                        <p v-if="form.errors.current_password" class="mt-2 text-sm text-rose-600">
                            {{ form.errors.current_password }}
                        </p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-bold text-slate-700">Password Baru</label>
                        <input
                            id="password"
                            ref="passwordInput"
                            v-model="form.password"
                            type="password"
                            class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            autocomplete="new-password"
                        />
                        <p v-if="form.errors.password" class="mt-2 text-sm text-rose-600">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-bold text-slate-700">Konfirmasi Password Baru</label>
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            autocomplete="new-password"
                        />
                        <p v-if="form.errors.password_confirmation" class="mt-2 text-sm text-rose-600">
                            {{ form.errors.password_confirmation }}
                        </p>
                    </div>

                    <div class="flex items-center gap-4">
                        <button :disabled="form.processing" class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-xl font-bold text-white hover:bg-emerald-700 focus:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm disabled:opacity-50">
                            Simpan Perubahan
                        </button>

                        <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                            <p v-if="form.recentlySuccessful" class="text-sm text-emerald-600 font-bold">Tersimpan.</p>
                        </Transition>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
