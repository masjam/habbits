<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import dayjs from 'dayjs'
import 'dayjs/locale/id'
import relativeTime from 'dayjs/plugin/relativeTime'

dayjs.extend(relativeTime)
dayjs.locale('id')

const props = defineProps({
    notifications: Object,
})

const markAsRead = (id, url) => {
    router.post(route('notifications.mark-as-read', id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (url) {
                window.location.href = url
            }
        }
    })
}

const markAllAsRead = () => {
    router.post(route('notifications.mark-all-read'), {}, {
        preserveScroll: true
    })
}
</script>

<template>
    <Head title="Pesan & Notifikasi" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <span>Pesan &amp; Notifikasi</span>
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Daftar pesan dan pemberitahuan dari sistem.
                    </p>
                </div>
                <div>
                    <button 
                        @click="markAllAsRead"
                        class="px-4 py-2 bg-primary-50 dark:bg-primary-900/40 hover:bg-primary-100 dark:hover:bg-primary-800 text-primary-700 dark:text-primary-400 text-sm font-bold rounded-xl transition-colors w-full md:w-auto text-center"
                    >
                        Tandai Semua Dibaca
                    </button>
                </div>
            </div>
        </template>

        <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="bg-sidebar rounded-3xl border border-theme shadow-xs overflow-hidden">
                <div v-if="notifications.data.length === 0" class="p-12 text-center">
                    <div class="w-16 h-16 mx-auto bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-700 dark:text-slate-300">Belum Ada Pesan</h3>
                    <p class="text-slate-500 dark:text-slate-400 mt-1">Anda akan menerima notifikasi di sini jika ada info terbaru.</p>
                </div>

                <div v-else class="divide-y divide-subtle">
                    <div 
                        v-for="notification in notifications.data" 
                        :key="notification.id"
                        @click="markAsRead(notification.id, notification.data.url)"
                        :class="[
                            'p-4 sm:p-5 transition-colors cursor-pointer group',
                            notification.read_at === null 
                                ? 'bg-primary-50/50 dark:bg-primary-900/20 hover:bg-primary-100/50 dark:hover:bg-primary-900/40' 
                                : 'hover:bg-card-subtle'
                        ]"
                    >
                        <div class="flex gap-4">
                            <div class="flex-shrink-0 mt-1">
                                <div :class="[
                                    'w-10 h-10 rounded-full flex items-center justify-center overflow-hidden border',
                                    notification.read_at === null 
                                        ? 'bg-primary-100 border-primary-200 dark:bg-primary-900 dark:border-primary-700' 
                                        : 'bg-slate-100 border-slate-200 dark:bg-slate-800 dark:border-slate-700'
                                ]">
                                    <img v-if="notification.data.icon" :src="notification.data.icon" class="w-6 h-6 object-contain" />
                                    <svg v-else class="w-5 h-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mb-1">
                                    <h4 :class="[
                                        'text-base font-bold truncate pr-4',
                                        notification.read_at === null ? 'text-slate-900 dark:text-white' : 'text-slate-700 dark:text-slate-300'
                                    ]">
                                        {{ notification.data.title || 'Pemberitahuan' }}
                                    </h4>
                                    <span class="text-[11px] text-slate-400 font-medium whitespace-nowrap">
                                        {{ dayjs(notification.created_at).fromNow() }}
                                    </span>
                                </div>
                                <p :class="[
                                    'text-sm',
                                    notification.read_at === null ? 'text-slate-700 dark:text-slate-300 font-medium' : 'text-slate-500 dark:text-slate-400'
                                ]">
                                    {{ notification.data.body || notification.data.message }}
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2" v-if="notification.data.action_links">
                                    <a v-for="link in notification.data.action_links" 
                                       :key="link.url" 
                                       :href="link.url" 
                                       target="_blank" 
                                       @click.stop="markAsRead(notification.id, null)"
                                       class="inline-flex items-center px-3 py-1.5 bg-slate-100 dark:bg-slate-800 text-sm font-semibold rounded-lg hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition-colors">
                                        {{ link.label }}
                                    </a>
                                </div>
                                <div v-if="notification.read_at === null" class="mt-2">
                                    <span class="inline-block w-2 h-2 rounded-full bg-primary-500"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="notifications.links && notifications.data.length > 0" class="px-4 py-3 border-t border-subtle bg-card-subtle flex items-center justify-between sm:px-6">
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                Menampilkan <span class="font-medium text-slate-700 dark:text-slate-200">{{ notifications.from }}</span>
                                sampai <span class="font-medium text-slate-700 dark:text-slate-200">{{ notifications.to }}</span>
                                dari <span class="font-medium text-slate-700 dark:text-slate-200">{{ notifications.total }}</span> pesan
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-xs -space-x-px" aria-label="Pagination">
                                <Link
                                    v-for="(link, i) in notifications.links"
                                    :key="i"
                                    :href="link.url || '#'"
                                    v-html="link.label"
                                    :class="[
                                        'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                                        link.active ? 'z-10 bg-primary-50 dark:bg-primary-900/30 border-primary-500 text-primary-600 dark:text-primary-400' : 'bg-sidebar border-theme text-slate-500 hover:bg-card-subtle',
                                        !link.url && 'opacity-50 cursor-not-allowed',
                                        i === 0 && 'rounded-l-md',
                                        i === notifications.links.length - 1 && 'rounded-r-md'
                                    ]"
                                    :disabled="!link.url"
                                />
                            </nav>
                        </div>
                    </div>
                    
                    <!-- Mobile pagination -->
                    <div class="flex items-center justify-between w-full sm:hidden">
                        <Link
                            :href="notifications.prev_page_url || '#'"
                            :class="[
                                'relative inline-flex items-center px-4 py-2 border border-theme text-sm font-medium rounded-md text-slate-700 bg-sidebar hover:bg-card-subtle',
                                !notifications.prev_page_url && 'opacity-50 cursor-not-allowed'
                            ]"
                        >
                            Sebelumnya
                        </Link>
                        <span class="text-sm text-slate-500">{{ notifications.current_page }} / {{ notifications.last_page }}</span>
                        <Link
                            :href="notifications.next_page_url || '#'"
                            :class="[
                                'relative inline-flex items-center px-4 py-2 border border-theme text-sm font-medium rounded-md text-slate-700 bg-sidebar hover:bg-card-subtle',
                                !notifications.next_page_url && 'opacity-50 cursor-not-allowed'
                            ]"
                        >
                            Selanjutnya
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
