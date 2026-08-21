<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    logs: Object,
    filters: Object
})

const search = ref(props.filters.search || '')

// Watch search with basic debounce
let searchTimeout
watch(search, (val) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get(route('admin.login-logs'), { search: val }, { preserveState: true, replace: true })
    }, 400)
})

// Fungsi memformat tanggal (contoh: 21 Agu 2026 14:30)
const formatDate = (dateStr) => {
    if (!dateStr) return '-'
    const d = new Date(dateStr)
    return new Intl.DateTimeFormat('id-ID', { 
        day: 'numeric', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    }).format(d)
}

// Fungsi memformat user agent secara ringkas
const formatUserAgent = (ua) => {
    if (!ua) return 'Tidak Diketahui'
    if (ua.includes('Windows')) return 'Windows Desktop'
    if (ua.includes('Mac OS')) return 'Mac OS'
    if (ua.includes('Linux')) return 'Linux'
    if (ua.includes('Android')) return 'Android Mobile'
    if (ua.includes('iPhone')) return 'iPhone'
    return 'Lainnya'
}
</script>

<template>
    <Head title="Log Akses Login" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-200 leading-tight">
                Log Akses Login
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Wrapper Card -->
                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-2xl border border-slate-100 dark:border-slate-700 overflow-hidden">
                    
                    <!-- Toolbar (Search) -->
                    <div class="p-6 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex flex-col sm:flex-row justify-between gap-4">
                        <div class="relative max-w-sm w-full">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Cari nama, email, lokasi, atau IP..."
                                class="pl-10 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm dark:bg-slate-900 dark:border-slate-700 dark:text-white"
                            >
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead class="bg-slate-50 dark:bg-slate-900/50">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        User
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        Waktu Login
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        IP Address
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        Lokasi
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        Perangkat
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700">
                                <tr v-for="log in logs.data" :key="log.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-9 w-9 rounded-full bg-emerald-100 flex items-center justify-center border border-emerald-200">
                                                <span class="text-emerald-700 font-bold text-sm">{{ log.user?.name?.charAt(0) || '?' }}</span>
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-semibold text-slate-900 dark:text-white">
                                                    {{ log.user?.name || 'User Dihapus' }}
                                                </div>
                                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                                    {{ log.user?.email || '-' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-slate-700 dark:text-slate-300">
                                            {{ formatDate(log.created_at) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-mono text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-700 px-2 py-1 rounded inline-block">
                                            {{ log.ip_address || 'N/A' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300">
                                            <svg v-if="log.location" class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.242-4.243a8 8 0 1111.314 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            {{ log.location || 'Tidak Diketahui' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-slate-700 dark:text-slate-300 flex items-center gap-1.5" :title="log.user_agent">
                                            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            {{ formatUserAgent(log.user_agent) }}
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="logs.data.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                        Belum ada riwayat login yang terekam.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700" v-if="logs.links && logs.data.length > 0">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-500 dark:text-slate-400">
                                Menampilkan {{ logs.from }} sampai {{ logs.to }} dari {{ logs.total }} log
                            </span>
                            <div class="flex gap-1">
                                <template v-for="(link, i) in logs.links" :key="i">
                                    <component
                                        :is="link.url ? 'a' : 'span'"
                                        :href="link.url"
                                        @click.prevent="link.url ? router.get(link.url) : null"
                                        v-html="link.label"
                                        class="px-3 py-1.5 rounded-lg text-sm transition-colors cursor-pointer"
                                        :class="[
                                            link.active ? 'bg-emerald-600 text-white font-medium' : 
                                            !link.url ? 'text-slate-300 dark:text-slate-600 cursor-not-allowed' : 
                                            'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700'
                                        ]"
                                    ></component>
                                </template>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
