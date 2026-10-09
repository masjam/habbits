<script setup>
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const activeTab = ref('admin')

const tabs = [
    { id: 'admin', name: 'Admin (Superadmin)', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z' },
    { id: 'tu', name: 'Tata Usaha (TU)', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    { id: 'pegawai', name: 'Pegawai / Guru', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' }
]

const openFaq = ref(null)
const toggleFaq = (id) => {
    openFaq.value = openFaq.value === id ? null : id
}

</script>

<template>
    <AuthenticatedLayout>
        <Head title="Buku Panduan Penggunaan" />

        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Header Section -->
            <div class="relative overflow-hidden bg-gradient-to-br from-emerald-600 to-teal-800 rounded-3xl p-8 md:p-12 text-white shadow-lg">
                <div class="relative z-10">
                    <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/20 backdrop-blur-md rounded-full text-sm font-bold tracking-widest uppercase mb-4 border border-white/30">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        Manual Book
                    </div>
                    <h1 class="text-3xl md:text-5xl font-black mb-4 tracking-tight drop-shadow-md">Buku Panduan<br>Penggunaan Sistem</h1>
                    <p class="text-emerald-50 text-lg max-w-xl font-medium">Temukan panduan lengkap menggunakan aplikasi ini sesuai dengan peran (role) Anda di instansi.</p>
                </div>
                <!-- Decoration -->
                <div class="absolute right-0 top-0 -mr-20 -mt-20 opacity-20 pointer-events-none">
                    <svg class="w-96 h-96" fill="currentColor" viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="flex flex-wrap gap-2 p-1.5 bg-slate-100 rounded-2xl border border-slate-200">
                <button 
                    v-for="tab in tabs" 
                    :key="tab.id"
                    @click="activeTab = tab.id"
                    class="flex-1 min-w-[200px] flex items-center justify-center gap-2 py-3 px-6 rounded-xl font-bold transition-all duration-300"
                    :class="activeTab === tab.id ? 'bg-white text-emerald-600 shadow-sm border border-slate-200/60' : 'text-slate-500 hover:bg-slate-200/50 hover:text-slate-700'"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="tab.icon" />
                        <path v-if="tab.id === 'admin'" stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    {{ tab.name }}
                </button>
            </div>

            <!-- Content Area -->
            <div class="bg-white rounded-3xl p-6 md:p-10 shadow-sm border border-slate-200 min-h-[500px]">
                
                <!-- ADMIN CONTENT -->
                <transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 translate-y-4" enter-to-class="opacity-100 translate-y-0" leave-active-class="hidden">
                    <div v-show="activeTab === 'admin'" class="space-y-8">
                        <div>
                            <h2 class="text-2xl font-black text-slate-800 mb-2 flex items-center gap-3">
                                <span class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-lg shadow-sm">1</span>
                                Manajemen Pengguna (Data Pegawai)
                            </h2>
                            <div class="ml-13 space-y-4 text-slate-600">
                                <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 hover:border-emerald-200 transition-colors">
                                    <h4 class="font-bold text-slate-800 mb-1 flex items-center gap-2"><svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg> Menambah Pegawai Baru</h4>
                                    <p>Masuk ke menu <strong>Manajemen Pegawai</strong>, klik "Tambah Pegawai". Isi biodata lengkap, NIP, serta jam kerja (Work Start/End) agar sistem presensi dapat mengkalkulasi keterlambatan.</p>
                                </div>
                                <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 hover:border-emerald-200 transition-colors">
                                    <h4 class="font-bold text-slate-800 mb-1 flex items-center gap-2"><svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg> Reset Password</h4>
                                    <p>Jika ada pegawai yang lupa sandi, Admin dapat melakukan reset sandi menjadi *password default* (12345678) langsung dari profil pegawai tersebut melalui menu Manajemen Pegawai.</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h2 class="text-2xl font-black text-slate-800 mb-2 flex items-center gap-3">
                                <span class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-lg shadow-sm">2</span>
                                Pengaturan Presensi & Jadwal
                            </h2>
                            <div class="ml-13 space-y-4 text-slate-600">
                                <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 hover:border-blue-200 transition-colors">
                                    <h4 class="font-bold text-slate-800 mb-1">Jadwal Piket & Khusus</h4>
                                    <p>Sistem ini memiliki fitur override jam kerja. Anda bisa membuat <strong>Jadwal Piket</strong> untuk pegawai tertentu di hari tertentu. Atau <strong>Jadwal Khusus</strong> untuk event massal seperti Ramadan, di mana jam masuk/pulang berlaku untuk seluruh instansi.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </transition>

                <!-- TU CONTENT -->
                <transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 translate-y-4" enter-to-class="opacity-100 translate-y-0" leave-active-class="hidden">
                    <div v-show="activeTab === 'tu'" class="space-y-8">
                        <div>
                            <h2 class="text-2xl font-black text-slate-800 mb-2 flex items-center gap-3">
                                <span class="w-10 h-10 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center text-lg shadow-sm">1</span>
                                Manajemen Persuratan & Disposisi
                            </h2>
                            <div class="ml-13 space-y-4 text-slate-600">
                                <div class="bg-amber-50/50 rounded-2xl p-5 border border-amber-100 hover:border-amber-300 transition-colors">
                                    <h4 class="font-bold text-slate-800 mb-1">Surat Masuk & Disposisi</h4>
                                    <p>Catat setiap surat yang masuk dari pihak luar, unggah lampiran fisiknya, lalu <strong>Disposisikan</strong> kepada Kepala Sekolah atau Pegawai terkait untuk ditindaklanjuti. Anda bisa memantau apakah disposisi tersebut sudah dibaca atau belum melalui status disposisi.</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h2 class="text-2xl font-black text-slate-800 mb-2 flex items-center gap-3">
                                <span class="w-10 h-10 rounded-full bg-teal-100 text-teal-600 flex items-center justify-center text-lg shadow-sm">2</span>
                                Rekapitulasi Laporan
                            </h2>
                            <div class="ml-13 space-y-4 text-slate-600">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 hover:border-teal-200 transition-colors">
                                        <h4 class="font-bold text-slate-800 mb-1">Laporan Presensi</h4>
                                        <p>Unduh rekapitulasi kehadiran harian dan bulanan pegawai (termasuk status telat, izin, dan dinas luar) dalam format Excel yang rapi.</p>
                                    </div>
                                    <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 hover:border-teal-200 transition-colors">
                                        <h4 class="font-bold text-slate-800 mb-1">Arsip Dokumen Pegawai</h4>
                                        <p>Kelola berkas-berkas digital Ijazah, KTP, dan SK milik seluruh pegawai dalam menu E-Filing Dokumen secara tersentralisasi.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </transition>

                <!-- PEGAWAI CONTENT -->
                <transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 translate-y-4" enter-to-class="opacity-100 translate-y-0" leave-active-class="hidden">
                    <div v-show="activeTab === 'pegawai'" class="space-y-8">
                        <div>
                            <h2 class="text-2xl font-black text-slate-800 mb-2 flex items-center gap-3">
                                <span class="w-10 h-10 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center text-lg shadow-sm">1</span>
                                Presensi (Absensi Harian)
                            </h2>
                            <div class="ml-13 space-y-4 text-slate-600">
                                <div class="bg-purple-50/50 rounded-2xl p-5 border border-purple-100 hover:border-purple-300 transition-colors">
                                    <ul class="space-y-3 list-disc pl-5">
                                        <li><strong>Cara Melakukan Absen:</strong> Buka halaman dashboard utama, sistem akan otomatis membaca lokasi GPS Anda. Klik tombol <strong>Clock In</strong> jika Anda berada di dalam radius kantor.</li>
                                        <li><strong>Verifikasi Foto:</strong> Semua absensi mewajibkan foto *selfie* langsung dari kamera untuk mencegah kecurangan *Fake GPS*.</li>
                                        <li><strong>Pengajuan Izin:</strong> Gunakan menu <strong>Izin</strong> pada dashboard untuk melaporkan sakit/izin tanpa harus repot menghubungi TU/Admin. Lampirkan surat dokter jika ada.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h2 class="text-2xl font-black text-slate-800 mb-2 flex items-center gap-3">
                                <span class="w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center text-lg shadow-sm">2</span>
                                Pengisian Form Ibadah & Habit
                            </h2>
                            <div class="ml-13 space-y-4 text-slate-600">
                                <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 hover:border-rose-200 transition-colors">
                                    <p class="mb-3">Sistem ini memantau kualitas diri pegawai melalui <strong>Log Habit</strong> harian. Anda wajib mengisi form log pembiasaan ibadah (seperti sholat wajib, rawatib, tahajud, dll).</p>
                                    <p class="mb-3"><strong>Fitur Offline (PWA):</strong> Anda tidak memerlukan kuota internet setiap saat! Jika Anda sedang offline, isilah form habit seperti biasa. Sistem akan menyimpannya ke memori HP Anda sementara (IndexedDB). Data akan otomatis tersinkronisasi terkirim saat internet kembali menyala.</p>
                                    <div class="flex items-start gap-3 mt-4 p-3 bg-rose-100/50 rounded-xl text-rose-800 border border-rose-200">
                                        <svg class="w-6 h-6 mt-0.5 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                        <p class="text-sm"><strong>Bagi Pegawai Wanita:</strong> Terdapat fitur "Log Menstruasi". Ketika dalam masa haid, form kewajiban ibadah akan otomatis disembunyikan untuk menjaga skor bulanan Anda tidak turun secara tidak adil.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </transition>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
