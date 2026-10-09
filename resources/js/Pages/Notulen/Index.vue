<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router, Link } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

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

            <!-- List Notulen -->
            <div v-if="notulens.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="notulen in notulens.data" :key="notulen.id" class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden flex flex-col transition-all hover:shadow-md">
                    <div class="p-5 flex-1 space-y-3">
                        <div class="flex items-start justify-between">
                            <span class="inline-flex px-2 py-1 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded text-xs font-bold uppercase tracking-wider">
                                {{ notulen.jenis_rapat }}
                            </span>
                            <span class="text-xs font-medium text-slate-400">
                                {{ new Date(notulen.tanggal_waktu).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) }}
                            </span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100 line-clamp-2 leading-snug">{{ notulen.judul_rapat }}</h3>
                        
                        <div class="text-sm text-slate-500 dark:text-slate-400 flex items-start gap-2">
                            <svg class="w-4 h-4 shrink-0 text-slate-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                            <span class="truncate">{{ notulen.pimpinan_rapat }}</span>
                        </div>
                        <div class="text-sm text-slate-500 dark:text-slate-400 flex items-start gap-2">
                            <svg class="w-4 h-4 shrink-0 text-slate-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            <span class="truncate">{{ notulen.lokasi }}</span>
                        </div>

                        <div class="pt-3 border-t border-slate-100 dark:border-slate-700">
                            <div class="text-sm text-slate-600 dark:text-slate-300 line-clamp-3 prose prose-sm prose-emerald dark:prose-invert max-w-none" v-html="notulen.isi_pembahasan">
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
                        <div class="text-xs text-slate-400">
                            {{ notulen.dokumentasi ? notulen.dokumentasi.length + ' Lampiran' : '0 Lampiran' }}
                        </div>
                        <div class="flex items-center gap-2">
                            <!-- Tombol Detail / Hapus -->
                            <button @click="deleteNotulen(notulen.id)" class="p-2 text-slate-400 hover:text-red-600 dark:hover:text-red-400 bg-white dark:bg-slate-700 hover:bg-red-50 dark:hover:bg-slate-600 rounded-lg shadow-sm border border-slate-200 dark:border-slate-600 transition-colors" title="Hapus">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                    </div>
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
