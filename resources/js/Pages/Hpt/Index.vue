<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const iframeUrl = 'https://hpt.muhammadiyah.or.id/';
const iframeRef = ref(null);
const isLoading = ref(true);

const reloadIframe = () => {
    isLoading.value = true;
    if (iframeRef.value) {
        iframeRef.value.src = iframeUrl;
    }
};

const handleLoad = () => {
    isLoading.value = false;
};
</script>

<template>
    <Head title="Himpunan Putusan Tarjih (HPT)" />

    <AuthenticatedLayout>
        <div class="py-4 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-4">
            <!-- Header bar & Tombol Aksi -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white dark:bg-slate-800 p-4 rounded-2xl shadow-xs border border-slate-200 dark:border-slate-700/60">
                <div>
                    <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <span>📖</span> Himpunan Putusan Tarjih (HPT)
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Akses referensi putusan tarjih Muhammadiyah langsung dari portal resmi.
                    </p>
                </div>
                
                <div class="flex items-center gap-2">
                    <button 
                        @click="reloadIframe"
                        type="button"
                        title="Muat Ulang"
                        class="p-2 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition cursor-pointer"
                    >
                        <svg class="w-4 h-4" :class="{ 'animate-spin': isLoading }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                    </button>
                    <a 
                        :href="iframeUrl" 
                        target="_blank" 
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 transition"
                    >
                        <span>Buka di Tab Baru</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Wadah Iframe -->
            <div class="relative w-full h-[calc(100vh-230px)] min-h-[600px] bg-white dark:bg-slate-800 rounded-2xl shadow-xs border border-slate-200 dark:border-slate-700/60 overflow-hidden">
                <!-- Indikator Loading -->
                <div v-if="isLoading" class="absolute inset-0 flex flex-col items-center justify-center bg-white/80 dark:bg-slate-800/80 backdrop-blur-xs z-10">
                    <div class="w-8 h-8 border-3 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
                    <span class="mt-2 text-xs text-slate-500">Memuat halaman...</span>
                </div>

                <!-- Iframe Element -->
                <iframe
                    ref="iframeRef"
                    :src="iframeUrl"
                    class="w-full h-full border-0"
                    allowfullscreen
                    loading="lazy"
                    @load="handleLoad"
                ></iframe>
            </div>
        </div>
    </AuthenticatedLayout>
</template>