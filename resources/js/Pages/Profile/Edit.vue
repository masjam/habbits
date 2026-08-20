<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

defineProps({
    status: {
        type: String,
    },
})

const passwordInput = ref(null)
const currentPasswordInput = ref(null)

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

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
