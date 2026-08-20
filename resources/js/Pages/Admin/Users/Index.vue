<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    users: Array,
    isSuperadmin: Boolean,
})

const isModalOpen = ref(false)
const isEditing = ref(false)
const editingUserId = ref(null)

const isResetModalOpen = ref(false)
const resettingUser = ref(null)

const form = useForm({
    name: '',
    email: '',
    password: '',
    gender: 'L',
    role: 'user', // Default
})

const resetForm = useForm({
    password: '',
})

const uploadForm = useForm({
    file: null,
})
const isUploadModalOpen = ref(false)

const openUploadModal = () => {
    uploadForm.reset()
    uploadForm.clearErrors()
    isUploadModalOpen.value = true
}

const closeUploadModal = () => {
    isUploadModalOpen.value = false
    uploadForm.reset()
    uploadForm.clearErrors()
}

const submitUpload = () => {
    uploadForm.post(route('admin.users.import'), {
        onSuccess: () => closeUploadModal(),
    })
}

const openAddModal = () => {
    isEditing.value = false
    editingUserId.value = null
    form.reset()
    form.clearErrors()
    isModalOpen.value = true
}

const openEditModal = (user) => {
    isEditing.value = true
    editingUserId.value = user.id
    form.clearErrors()
    
    form.name = user.name
    form.email = user.email
    form.gender = user.gender
    // We only set the primary role here. If a user has multiple roles, just pick the first or highest.
    form.role = user.roles.includes('superadmin') ? 'superadmin' : (user.roles.includes('admin') ? 'admin' : 'user')
    form.password = '' // Don't prefill password
    
    isModalOpen.value = true
}

const closeModal = () => {
    isModalOpen.value = false
    isEditing.value = false
    editingUserId.value = null
    form.reset()
    form.clearErrors()
}

const saveUser = () => {
    if (isEditing.value) {
        form.put(route('admin.users.update', editingUserId.value), {
            onSuccess: () => closeModal(),
        })
    } else {
        form.post(route('admin.users.store'), {
            onSuccess: () => closeModal(),
        })
    }
}

const openResetModal = (user) => {
    resettingUser.value = user
    resetForm.reset()
    resetForm.clearErrors()
    isResetModalOpen.value = true
}

const closeResetModal = () => {
    isResetModalOpen.value = false
    resettingUser.value = null
    resetForm.reset()
    resetForm.clearErrors()
}

const saveResetPassword = () => {
    resetForm.post(route('admin.users.reset-password', resettingUser.value.id), {
        onSuccess: () => closeResetModal(),
    })
}

// Security Check helper
const canResetPassword = (user) => {
    if (props.isSuperadmin) return true
    // Admin can only reset 'user'
    return user.roles.includes('user') && !user.roles.includes('superadmin') && !user.roles.includes('admin')
}

const deleteUser = (user) => {
    if (confirm(`Apakah Anda yakin ingin menghapus pengguna ${user.name}?`)) {
        router.delete(route('admin.users.destroy', user.id), {
            preserveScroll: true,
        })
    }
}

const canDeleteUser = (user) => {
    if (props.isSuperadmin) return true
    return user.roles.includes('user') && !user.roles.includes('superadmin') && !user.roles.includes('admin')
}
const canEditUser = (user) => {
    if (props.isSuperadmin) return true
    return user.roles.includes('user') && !user.roles.includes('superadmin') && !user.roles.includes('admin')
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Manajemen User" />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-800 tracking-tight">Manajemen Pengguna</h2>
                    <p class="text-sm text-slate-500 mt-1">
                        Daftar seluruh akun yang terdaftar dalam sistem.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="openUploadModal" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-700 font-bold text-sm shadow-sm hover:bg-slate-50 transition-colors focus:ring-2 focus:ring-offset-2 focus:ring-slate-200">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        <span class="hidden sm:inline">Upload Massal</span>
                    </button>
                    <button @click="openAddModal" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 text-white font-bold text-sm shadow-sm hover:bg-emerald-700 transition-colors focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Tambah Pengguna</span>
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500 font-semibold border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3">Nama Pegawai</th>
                                <th class="px-4 py-3">Email</th>
                                <th class="px-4 py-3 text-center">Gender</th>
                                <th class="px-4 py-3 text-center">Role</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-for="user in users" :key="user.id" class="bg-white hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-4 font-bold text-slate-800">{{ user.name }}</td>
                                <td class="px-4 py-4 text-slate-500">{{ user.email }}</td>
                                <td class="px-4 py-4 text-center">
                                    <span v-if="user.gender === 'L'" class="px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-700 font-bold">Laki-laki</span>
                                    <span v-else class="px-2 py-0.5 rounded text-xs bg-pink-100 text-pink-700 font-bold">Perempuan</span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <div class="flex flex-wrap justify-center gap-1">
                                        <span v-for="role in user.roles" :key="role" 
                                              class="px-2 py-0.5 rounded text-xs font-bold uppercase tracking-wider"
                                              :class="{
                                                  'bg-emerald-100 text-emerald-800': role === 'user',
                                                  'bg-amber-100 text-amber-800': role === 'admin',
                                                  'bg-purple-100 text-purple-800': role === 'superadmin'
                                              }">
                                            {{ role === 'user' ? 'Pegawai' : role }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button v-if="canEditUser(user)" @click="openEditModal(user)" title="Edit Profil" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                            </svg>
                                        </button>
                                        <button v-if="canResetPassword(user)" @click="openResetModal(user)" title="Reset Password" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-amber-600 hover:bg-amber-50 transition-colors">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                            </svg>
                                        </button>
                                        <button v-if="canDeleteUser(user)" @click="deleteUser(user)" title="Hapus User" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add User Modal -->
        <div v-if="isModalOpen" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>
            
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md border border-slate-100">
                        <form @submit.prevent="saveUser">
                            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                                <h3 class="text-lg font-bold leading-6 text-slate-900 mb-4" id="modal-title">
                                    {{ isEditing ? 'Edit Profil Pengguna' : 'Tambah Pengguna Baru' }}
                                </h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wider">Nama Lengkap</label>
                                        <input type="text" v-model="form.name" class="w-full p-2 text-sm border-slate-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500" required />
                                        <p v-if="form.errors.name" class="text-xs text-rose-500 mt-1">{{ form.errors.name }}</p>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wider">Email</label>
                                        <input type="email" v-model="form.email" class="w-full p-2 text-sm border-slate-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500" required />
                                        <p v-if="form.errors.email" class="text-xs text-rose-500 mt-1">{{ form.errors.email }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wider">
                                            Password <span v-if="isEditing" class="text-slate-400 normal-case font-normal">(Kosongkan jika tidak ingin mengubah)</span>
                                        </label>
                                        <input type="password" v-model="form.password" class="w-full p-2 text-sm border-slate-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500" :required="!isEditing" />
                                        <p v-if="form.errors.password" class="text-xs text-rose-500 mt-1">{{ form.errors.password }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wider">Jenis Kelamin</label>
                                        <select v-model="form.gender" class="w-full p-2 text-sm border-slate-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500" required>
                                            <option value="L">Laki-laki</option>
                                            <option value="P">Perempuan</option>
                                        </select>
                                        <p v-if="form.errors.gender" class="text-xs text-rose-500 mt-1">{{ form.errors.gender }}</p>
                                    </div>

                                    <div v-if="isSuperadmin">
                                        <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wider">Role (Hak Akses)</label>
                                        <select v-model="form.role" class="w-full p-2 text-sm border-slate-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500" required>
                                            <option value="user">Pegawai</option>
                                            <option value="admin">Admin</option>
                                            <option value="superadmin">Superadmin</option>
                                        </select>
                                        <p v-if="form.errors.role" class="text-xs text-rose-500 mt-1">{{ form.errors.role }}</p>
                                    </div>
                                    <div v-else-if="!isEditing" class="p-3 bg-slate-50 border border-slate-200 rounded-lg">
                                        <p class="text-xs text-slate-500 font-medium">Pengguna baru akan otomatis didaftarkan sebagai <strong>Pegawai</strong>.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t border-slate-100">
                                <button type="submit" :disabled="form.processing" class="inline-flex w-full justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 sm:ml-3 sm:w-auto focus:ring-2 focus:ring-emerald-500 disabled:opacity-50">
                                    <span>Simpan Pengguna</span>
                                </button>
                                <button type="button" @click="closeModal" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto">
                                    Batal
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reset Password Modal -->
        <div v-if="isResetModalOpen" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>
            
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md border border-slate-100">
                        <form @submit.prevent="saveResetPassword">
                            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                                <h3 class="text-lg font-bold leading-6 text-slate-900 mb-4" id="modal-title">
                                    Reset Password
                                </h3>
                                
                                <p class="text-sm text-slate-500 mb-4">
                                    Masukkan password baru untuk pengguna <strong>{{ resettingUser?.name }}</strong>.
                                </p>

                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wider">Password Baru</label>
                                        <input type="text" v-model="resetForm.password" class="w-full p-2 text-sm border-slate-300 rounded-lg focus:ring-amber-500 focus:border-amber-500" required />
                                        <p v-if="resetForm.errors.password" class="text-xs text-rose-500 mt-1">{{ resetForm.errors.password }}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t border-slate-100">
                                <button type="submit" :disabled="resetForm.processing" class="inline-flex w-full justify-center rounded-xl bg-amber-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-amber-700 sm:ml-3 sm:w-auto focus:ring-2 focus:ring-amber-500 disabled:opacity-50">
                                    <span>Ganti Password</span>
                                </button>
                                <button type="button" @click="closeResetModal" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto">
                                    Batal
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Modal -->
        <div v-if="isUploadModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="closeUploadModal"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-800">Upload Data Pegawai Massal</h3>
                    <button @click="closeUploadModal" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form @submit.prevent="submitUpload" class="p-6">
                    <div class="p-4 bg-emerald-50 rounded-xl border border-emerald-100 mb-6">
                        <p class="text-sm text-emerald-800 mb-2 font-medium">Pastikan format file Anda sesuai dengan template standar.</p>
                        <a :href="route('admin.users.template')" class="inline-flex items-center text-sm font-bold text-emerald-600 hover:text-emerald-700 transition-colors">
                            <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Unduh Template Excel
                        </a>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-slate-700 mb-2">File Excel (.xlsx, .xls, .csv)</label>
                        <input type="file" @input="uploadForm.file = $event.target.files[0]" accept=".xlsx,.xls,.csv" required class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer" />
                        <p v-if="uploadForm.errors.file" class="text-red-500 text-xs mt-1.5 font-medium">{{ uploadForm.errors.file }}</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" @click="closeUploadModal" class="px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 rounded-xl transition-colors">Batal</button>
                        <button type="submit" :disabled="uploadForm.processing" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-sm disabled:opacity-50 flex items-center transition-colors">
                            <span v-if="uploadForm.processing" class="animate-spin mr-2 h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
                            Upload Data
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
