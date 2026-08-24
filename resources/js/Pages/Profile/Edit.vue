<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { ref, computed, onMounted } from 'vue'

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
    avatar: null,
})

const photoPreview = ref(null)
const fileInput = ref(null)

const femaleSeeds = ['Aisyah', 'Fatima', 'Khadijah', 'Zainab', 'Maryam', 'Hafsah', 'Ruqayyah', 'Aminah', 'Safiyyah', 'Maimunah', 'Asma', 'Halimah']
const maleSeeds = ['Ahmad', 'Umar', 'Ali', 'Usman', 'Hasan', 'Husain', 'Ibrahim', 'Yusuf', 'Ismail', 'Ishaq', 'Yakub', 'Musa', 'Isa']

const predefinedAvatars = [
    ...femaleSeeds.map(seed => `https://api.dicebear.com/9.x/avataaars/svg?seed=${seed}&top=hijab&clothingColor=pastelBlue,pastelGreen,pastelOrange,pastelRed,pastelYellow,pink,red`),
    ...maleSeeds.map(seed => `https://api.dicebear.com/9.x/avataaars/svg?seed=${seed}&top=turban&clothingColor=black,blue01,blue02,gray01`)
]

const userAvatarUrl = computed(() => {
    if (photoPreview.value) return photoPreview.value
    if (props.user.avatar) {
        return props.user.avatar.startsWith('http') ? props.user.avatar : `/storage/${props.user.avatar}`
    }
    return null
})

const selectNewPhoto = () => {
    fileInput.value.click()
}

const updatePhotoPreview = () => {
    const photo = fileInput.value.files[0]
    if (!photo) return
    
    profileForm.avatar = photo

    const reader = new FileReader()
    reader.onload = (e) => {
        photoPreview.value = e.target.result
    }
    reader.readAsDataURL(photo)
}

const selectPredefinedAvatar = (avatarUrl) => {
    profileForm.avatar = avatarUrl
    photoPreview.value = avatarUrl
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

onMounted(() => {
    profileForm.name = props.user.name
    profileForm.phone = props.user.phone || ''
    profileForm.personal_target = props.user.personal_target || ''
})

const updateProfile = () => {
    profileForm.post(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // reload page to reflect avatar changes if needed, but inertia handles it mostly.
        }
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

        <div class="w-full space-y-6">
            <!-- Header -->
            <div>
                <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Profil Saya</h2>
                <p class="text-sm text-slate-500 mt-1">Kelola keamanan akun Anda dengan mengganti password.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-slate-100 relative overflow-hidden">
                <div class="absolute top-0 right-0 p-8 opacity-5 pointer-events-none text-emerald-500">
                    <svg class="w-32 h-32 transform rotate-12" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 4c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm0 14c-2.03 0-4.43-.82-6.14-2.88a9.947 9.947 0 0112.28 0C16.43 19.18 14.03 20 12 20z"/></svg>
                </div>
                
                <header class="relative z-10">
                    <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                        <svg class="w-6 h-6 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Informasi Profil
                    </h2>
                    <p class="mt-1.5 text-sm text-slate-500 max-w-lg">
                        Personalisasi akun Anda dengan memperbarui foto profil dan informasi lainnya.
                    </p>
                </header>

                <form @submit.prevent="updateProfile" class="mt-8 space-y-8 relative z-10">
                    
                    <!-- Avatar Selection Area -->
                    <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100">
                        <label class="block text-sm font-bold text-slate-700 mb-4">Foto Profil</label>
                        <div class="flex flex-col md:flex-row md:items-center gap-6">
                            
                            <!-- Current Avatar Preview -->
                            <div class="flex-shrink-0 relative group">
                                <div class="w-24 h-24 rounded-full overflow-hidden border-4 border-white shadow-md bg-emerald-50 flex items-center justify-center">
                                    <img v-if="userAvatarUrl" :src="userAvatarUrl" class="w-full h-full object-cover" />
                                    <span v-else class="text-3xl font-bold text-emerald-600">{{ props.user.name.charAt(0).toUpperCase() }}</span>
                                </div>
                                <button type="button" @click.prevent="selectNewPhoto" class="absolute bottom-0 right-0 bg-white p-1.5 rounded-full shadow-lg border border-slate-200 text-slate-600 hover:text-emerald-600 transition-colors">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </button>
                                <input type="file" ref="fileInput" class="hidden" @change="updatePhotoPreview" accept="image/*" />
                            </div>

                            <!-- Avatar Selection -->
                            <div class="flex-1">
                                <p class="text-xs text-slate-500 mb-3 font-medium uppercase tracking-wider">Atau pilih avatar lucu</p>
                                <div class="flex flex-wrap gap-3">
                                    <button 
                                        v-for="(avatar, index) in predefinedAvatars" 
                                        :key="index"
                                        type="button"
                                        @click="selectPredefinedAvatar(avatar)"
                                        :class="['w-12 h-12 rounded-full overflow-hidden border-2 transition-all duration-200 shadow-sm', (profileForm.avatar === avatar || (!fileInput?.value?.files?.length && userAvatarUrl === avatar)) ? 'border-emerald-500 scale-110 ring-2 ring-emerald-200' : 'border-transparent hover:scale-105 hover:shadow-md bg-white']"
                                    >
                                        <img :src="avatar" class="w-full h-full object-cover" />
                                    </button>
                                </div>
                                <p v-if="profileForm.errors.avatar" class="mt-2 text-sm text-rose-600">
                                    {{ profileForm.errors.avatar }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label for="name" class="block text-sm font-bold text-slate-700">Nama Lengkap</label>
                        <input
                            id="name"
                            v-model="profileForm.name"
                            type="text"
                            class="mt-1 block w-full px-4 py-2.5 rounded-xl border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            required
                        />
                        <p v-if="profileForm.errors.name" class="mt-2 text-sm text-rose-600">
                            {{ profileForm.errors.name }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-10 gap-6">
                        <div class="sm:col-span-7">
                            <label for="phone" class="block text-sm font-bold text-slate-700">No. HP/WA</label>
                            <input
                                id="phone"
                                v-model="profileForm.phone"
                                type="text"
                                class="mt-1 block w-full px-4 py-2.5 rounded-xl border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                placeholder="Contoh: 081234567890"
                            />
                            <p v-if="profileForm.errors.phone" class="mt-2 text-sm text-rose-600">
                                {{ profileForm.errors.phone }}
                            </p>
                        </div>

                        <div class="sm:col-span-3">
                            <label for="personal_target" class="block text-sm font-bold text-slate-700">Target (%)</label>
                            <input
                                id="personal_target"
                                v-model="profileForm.personal_target"
                                type="number"
                                step="0.1"
                                min="0"
                                max="100"
                                class="mt-1 block w-full px-4 py-2.5 rounded-xl border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                placeholder="Mis: 90"
                            />
                            <p v-if="profileForm.errors.personal_target" class="mt-2 text-sm text-rose-600">
                                {{ profileForm.errors.personal_target }}
                            </p>
                        </div>
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

                <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-slate-100 relative overflow-hidden">
                <header class="relative z-10">
                    <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                        <svg class="w-6 h-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Perbarui Password
                    </h2>
                    <p class="mt-1.5 text-sm text-slate-500 max-w-lg">
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
                            class="mt-1 block w-full px-4 py-2.5 rounded-xl border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
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
                            class="mt-1 block w-full px-4 py-2.5 rounded-xl border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
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
                            class="mt-1 block w-full px-4 py-2.5 rounded-xl border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
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
        </div>
    </AuthenticatedLayout>
</template>
