<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router, Link, usePage } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'

const page = usePage()
const currentUser = computed(() => page.props.auth.user)

const props = defineProps({
    notulens: {
        type: Object,
        default: () => ({ data: [] })
    },
    filters: {
        type: Object,
        default: () => ({ search: '' })
    }
})

// Search
const search = ref(props.filters.search)
watch(search, (value) => {
    router.get(route('notulen.index'), { search: value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    })
})

const deleteNotulen = (id) => {
    if (confirm('Apakah Anda yakin ingin menghapus notulen ini?')) {
        router.delete(route('notulen.destroy', id), {
            preserveScroll: true
        })
    }
}

const markHadir = (id) => {
    if (confirm('Anda ingin menandai kehadiran di rapat ini?')) {
        router.post(route('notulen.hadir', id), {}, {
            preserveScroll: true
        })
    }
}

const isUserInvited = (notulen) => {
    if (!notulen.peserta_rapat) return false;
    return notulen.peserta_rapat.split(', ').includes(currentUser.value.name);
}

const hasUserAttended = (notulen) => {
    if (!notulen.daftar_hadir) return false;
    return notulen.daftar_hadir.split(', ').includes(currentUser.value.name);
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Notulen Rapat" />

        <div class="space-y-6 w-full pb-8">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 dark:text-slate-100 tracking-tight">Notulen Rapat</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Catat dan kelola hasil rapat, daftar hadir, serta tindak lanjutnya.
                    </p>
                </div>
                
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                        <input type="text" v-model="search" placeholder="Cari notulen..." class="pl-10 pr-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-emerald-500 focus:border-emerald-500 text-slate-700 dark:text-slate-300 w-full sm:w-64 shadow-sm" />
                    </div>
                    <Link :href="route('notulen.create')" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-sm transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Buat Notulen
                    </Link>
                </div>
            </div>

            <!-- List Notulen (Tabel) -->
            <div v-if="notulens.data.length > 0" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left whitespace-nowrap">
                        <thead class="text-xs text-slate-500 uppercase bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th scope="col" class="px-6 py-4 font-bold">Judul & Jenis</th>
                                <th scope="col" class="px-6 py-4 font-bold">Waktu & Lokasi</th>
                                <th scope="col" class="px-6 py-4 font-bold">Kehadiran</th>
                                <th scope="col" class="px-6 py-4 font-bold">Status</th>
                                <th scope="col" class="px-6 py-4 font-bold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            <tr v-for="notulen in notulens.data" :key="notulen.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-800 dark:text-slate-200">{{ notulen.judul_rapat }}</div>
                                    <div class="text-xs text-slate-500 mt-1">{{ notulen.jenis_rapat }}</div>
                                    <div class="text-xs text-slate-400 mt-1">Oleh: {{ notulen.pimpinan_rapat }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-slate-700 dark:text-slate-300">{{ new Date(notulen.tanggal_waktu).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) }}</div>
                                    <div class="text-xs text-slate-500 mt-1 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        {{ notulen.lokasi }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ notulen.daftar_hadir ? notulen.daftar_hadir.split(', ').length : 0 }}</span>
                                        <span class="text-xs text-slate-500">/ {{ notulen.peserta_rapat ? notulen.peserta_rapat.split(', ').length : 0 }} Hadir</span>
                                    </div>
                                    <div class="mt-2" v-if="isUserInvited(notulen)">
                                        <button v-if="!hasUserAttended(notulen)" @click="markHadir(notulen.id)" class="px-2 py-1 rounded bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] shadow-sm transition-colors flex items-center gap-1 w-max">
                                            Konfirmasi Hadir
                                        </button>
                                        <span v-else class="px-2 py-1 rounded border border-emerald-500 text-emerald-600 font-bold text-[10px] flex items-center gap-1 w-max">
                                            Telah Hadir
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span v-if="notulen.is_approved" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-800 text-xs font-medium">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        Disetujui
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800 text-xs font-medium">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        Menunggu Approval
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Approve Action (Only if not approved yet, maybe only for Pimpinan or Creator) -->
                                        <Link v-if="!notulen.is_approved && (currentUser.name === notulen.pimpinan_rapat || currentUser.id === notulen.user_id)" :href="route('notulen.approve', notulen.id)" method="post" as="button" class="p-1.5 text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors" title="Setujui Notulen">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                        </Link>
                                        
                                        <!-- Preview -->
                                        <Link :href="route('notulen.show', notulen.id)" class="p-1.5 text-slate-500 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors" title="Preview & Cetak">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        </Link>

                                        <!-- Edit -->
                                        <Link v-if="!notulen.is_approved && (currentUser.name === notulen.pimpinan_rapat || currentUser.id === notulen.user_id)" :href="route('notulen.edit', notulen.id)" class="p-1.5 text-emerald-600 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition-colors" title="Edit">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                        </Link>

                                        <!-- Delete -->
                                        <button v-if="!notulen.is_approved && (currentUser.id === notulen.user_id)" @click="deleteNotulen(notulen.id)" class="p-1.5 text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-colors" title="Hapus">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="bg-white dark:bg-slate-800 rounded-3xl border border-dashed border-slate-300 dark:border-slate-700 p-12 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-50 dark:bg-slate-900/50 text-slate-400 mb-4 shadow-inner">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-200">Belum Ada Notulen</h3>
                <p class="text-slate-500 dark:text-slate-400 mt-2 max-w-sm mx-auto">Klik tombol "Buat Notulen" untuk mulai mencatat hasil rapat Anda.</p>
            </div>
            
            <!-- Pagination (if any) -->
            <div v-if="notulens.links && notulens.links.length > 3" class="mt-6 flex justify-center">
                <div class="inline-flex bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-1">
                    <Link v-for="(link, k) in notulens.links" :key="k" :href="link.url || '#'" class="px-4 py-2 text-sm font-medium rounded-lg transition-colors" :class="{'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400': link.active, 'text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700': !link.active, 'opacity-50 cursor-not-allowed': !link.url}" v-html="link.label"></Link>
                </div>
            </div>
            
        </div>
    </AuthenticatedLayout>
</template>
