<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    status: String, // 'valid', 'not_found', 'preview'
    message: String,
    surat: Object
});
</script>

<template>
    <Head title="Verifikasi Dokumen" />

    <div class="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8 flex items-center justify-center">
        <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-xl shadow-lg border border-gray-100">
            <!-- Header Logo -->
            <div class="text-center">
                <img src="/logo.png" alt="Logo" class="h-16 mx-auto mb-4" onerror="this.src='https://ui-avatars.com/api/?name=SD&color=7F9CF5&background=EBF4FF'" />
                <h2 class="text-2xl font-extrabold text-gray-900">
                    Sistem Verifikasi Dokumen
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    SD MUH. AL MUJAHIDIN WONOSARI
                </p>
            </div>

            <!-- Status: Preview -->
            <div v-if="status === 'preview'" class="rounded-md bg-blue-50 p-4 border border-blue-200">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">Mode Pratinjau</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p>{{ message }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status: Not Found / Invalid -->
            <div v-else-if="status === 'not_found'" class="rounded-md bg-red-50 p-4 border border-red-200">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-10 w-10 text-red-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-medium text-red-800">Dokumen Tidak Valid</h3>
                        <div class="mt-1 text-sm text-red-700">
                            <p>{{ message }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status: Valid -->
            <div v-else-if="status === 'valid'" class="space-y-6">
                <div class="rounded-md bg-green-50 p-4 border border-green-200 text-center">
                    <svg class="h-16 w-16 text-green-500 mx-auto mb-2" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                    <h3 class="text-xl font-bold text-green-800">Dokumen Valid Asli</h3>
                    <p class="mt-2 text-sm text-green-700">{{ message }}</p>
                </div>

                <div class="bg-gray-50 p-5 rounded-lg border border-gray-200 shadow-inner text-sm">
                    <h4 class="font-semibold text-gray-900 mb-4 border-b pb-2">Informasi Dokumen Asli:</h4>
                    
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-xs text-gray-500 font-medium uppercase tracking-wider">Nomor Surat</dt>
                            <dd class="mt-1 font-semibold text-gray-900">{{ surat.nomor_surat }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 font-medium uppercase tracking-wider">Tujuan</dt>
                            <dd class="mt-1 font-semibold text-gray-900">{{ surat.tujuan }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 font-medium uppercase tracking-wider">Perihal</dt>
                            <dd class="mt-1 font-semibold text-gray-900">{{ surat.perihal }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 font-medium uppercase tracking-wider">Tanggal Dikeluarkan</dt>
                            <dd class="mt-1 text-gray-900">{{ surat.dibuat_pada }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 font-medium uppercase tracking-wider">Kode Keamanan (Hash)</dt>
                            <dd class="mt-1 text-xs text-gray-400 font-mono break-all">{{ surat.hash }}</dd>
                        </div>
                    </dl>
                </div>
                
                <div class="bg-blue-50 p-4 rounded-md border border-blue-100 text-sm text-blue-800">
                    <span class="font-bold">Instruksi:</span> Pastikan informasi yang tertera di atas <b>sama persis</b> dengan isi dokumen fisik/digital yang sedang Anda periksa. Jika ada perbedaan walau sedikit, maka dokumen yang Anda pegang telah dipalsukan.
                </div>
            </div>

            <div class="mt-6 text-center">
                <Link href="/" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                    &larr; Kembali ke Beranda
                </Link>
            </div>
        </div>
    </div>
</template>
