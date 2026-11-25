<script setup>
import { ref, computed, watch } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const page = usePage()
const userRoles = computed(() => page.props.auth?.roles ?? [])
const hasRole = (role) => userRoles.value.includes(role)

// ─── Data Panduan Lengkap per Role & Menu (Sinkron dengan Sidebar) ─────────
const roleGuides = [
    {
        id: 'superadmin',
        role: 'superadmin',
        name: 'Superadmin',
        summary: 'Akses penuh seluruh modul aplikasi (Pegawai, Tata Usaha, Koperasi, Monitoring Guru, Admin) beserta kontrol sistem tingkat lanjut.',
        sections: [
            {
                group: 'Khusus Superadmin',
                items: [
                    {
                        menu: 'Cek Belum Isi Habit',
                        path: 'Habit & Voucher → Cek Belum Isi Habit',
                        detail: 'Memantau daftar pegawai yang belum mengisi form habit pada tanggal tertentu secara real-time, lengkap dengan ekspor data untuk tindak lanjut pembinaan.',
                    },
                    {
                        menu: 'Fitur HR & Lanjutan',
                        path: 'Pengaturan & Sistem → Fitur HR & Lanjutan',
                        detail: 'Mengaktifkan atau menonaktifkan modul aplikasi secara global (Presensi, Habit, Jurnal Guru, Administrasi Guru, Arsip, KPI, Divisi) serta membersihkan foto presensi lama untuk menghemat penyimpanan server.',
                    },
                    {
                        menu: 'Log Akses Login',
                        path: 'Pengaturan & Sistem → Log Akses Login',
                        detail: 'Audit keamanan riwayat login pengguna: mencatat waktu masuk, alamat IP, perangkat/browser, dan koordinat lokasi.',
                    },
                    {
                        menu: 'Pemantauan Sistem',
                        path: 'Pengaturan & Sistem → Pemantauan Sistem',
                        detail: 'Memeriksa kesehatan server, antrean pekerjaan (queue), status cache, dan performa aplikasi.',
                    },
                    {
                        menu: 'Simulasi Role (Role Simulator)',
                        path: 'Bar Atas Layar',
                        detail: 'Menguji tampilan dan hak akses aplikasi sebagai role lain (Pegawai, Kepala Sekolah, Kurikulum, TU, Koperasi, atau Admin) tanpa harus keluar akun.',
                    },
                ],
            },
            {
                group: 'Akses Lintas Modul',
                items: [
                    {
                        menu: 'Seluruh Modul Admin, TU, Koperasi & Monitoring',
                        path: 'Semua Grup Sidebar',
                        detail: 'Superadmin dapat mengakses semua menu Admin (Presensi, Habit, Kepegawaian, Pengaturan), Tata Usaha (Persuratan & Disposisi), Koperasi (POS, Katalog, Stok Opname, Riwayat), serta Monitoring Guru. Gunakan tab peran lain di atas untuk membaca panduan teknis masing-masing modul.',
                    },
                ],
            },
        ],
    },
    {
        id: 'admin',
        role: 'admin',
        name: 'Admin',
        summary: 'Mengelola data kepegawaian, jadwal & rekap presensi, laporan habit, penerbitan voucher koperasi, serta pengaturan sekolah.',
        sections: [
            {
                group: 'Monitoring Guru',
                items: [
                    {
                        menu: 'Rekap Administrasi Guru',
                        path: 'Monitoring Guru → Rekap Administrasi Guru',
                        detail: 'Memeriksa perangkat pembelajaran yang diunggah guru, melakukan verifikasi atau memberi catatan revisi, serta mengunduh arsip per tahun ajaran.',
                    },
                    {
                        menu: 'Rekap Jurnal Guru',
                        path: 'Monitoring Guru → Rekap Jurnal Guru',
                        detail: 'Memantau catatan aktivitas mengajar harian seluruh guru beserta dokumentasi fotonya.',
                    },
                ],
            },
            {
                group: 'Presensi & Jadwal',
                items: [
                    {
                        menu: 'Rekap Presensi',
                        path: 'Presensi & Jadwal → Rekap Presensi',
                        detail: 'Memantau kehadiran harian dan bulanan pegawai, menyetujui pengajuan izin/sakit/dinas luar, mengoreksi status kehadiran, serta mengunduh rekap Excel dan PDF (termasuk penanda warna jadwal khusus).',
                    },
                    {
                        menu: 'Jam Kerja Harian & Piket',
                        path: 'Presensi & Jadwal → Jam Kerja Harian & Piket',
                        detail: 'Mengatur jam masuk dan jam pulang standar per hari serta menugaskan jadwal piket khusus untuk pegawai tertentu.',
                    },
                    {
                        menu: 'Jadwal Khusus Tertentu',
                        path: 'Presensi & Jadwal → Jadwal Khusus Tertentu',
                        detail: 'Menetapkan jam kerja pengecualian pada tanggal atau rentang kegiatan tertentu (misal bulan Ramadan atau agenda khusus sekolah).',
                    },
                ],
            },
            {
                group: 'Habit & Voucher',
                items: [
                    {
                        menu: 'Semua Laporan Habit',
                        path: 'Habit & Voucher → Semua Laporan Habit',
                        detail: 'Melihat persentase capaian ibadah bulanan tiap pegawai, mengatur target minimal pencapaian voucher, melihat rincian harian per pegawai, dan ekspor Excel.',
                    },
                    {
                        menu: 'Voucher Koperasi',
                        path: 'Habit & Voucher → Voucher Koperasi',
                        detail: 'Menerbitkan (generate) voucher apresiasi bagi pegawai yang mencapai target habit bulanan, memantau status penukaran (aktif/sudah ditukar), dan mengatur nominal reward voucher.',
                    },
                    {
                        menu: 'Master Data Habit',
                        path: 'Habit & Voucher → Master Data Habit',
                        detail: 'Menambah, mengubah, mengurutkan ulang (reorder), dan mengaktifkan/menonaktifkan daftar pembiasaan ibadah yang muncul di form pegawai.',
                    },
                ],
            },
            {
                group: 'Kepegawaian',
                items: [
                    {
                        menu: 'Daftar User',
                        path: 'Kepegawaian → Daftar User',
                        detail: 'Menambah/mengedit data pegawai, menetapkan role (Pegawai, TU, Kurikulum, Kepala Sekolah, Koperasi), mengisi UID Kartu RFID presensi, reset password ke default, dan impor massal dari Excel.',
                    },
                    {
                        menu: 'Daftar Divisi',
                        path: 'Kepegawaian → Daftar Divisi',
                        detail: 'Mengelola daftar divisi atau unit kerja di sekolah (aktif bila fitur divisi dinyalakan oleh Superadmin).',
                    },
                    {
                        menu: 'Verifikasi Arsip Pegawai',
                        path: 'Kepegawaian → Verifikasi Arsip Pegawai',
                        detail: 'Memeriksa dan memverifikasi berkas kepegawaian digital (KTP, Ijazah, SK) yang diunggah oleh pegawai.',
                    },
                    {
                        menu: 'Evaluasi Kinerja (KPI)',
                        path: 'Kepegawaian → Evaluasi Kinerja (KPI)',
                        detail: 'Mengisi penilaian kinerja periodik pegawai dan menerbitkan rapor KPI.',
                    },
                ],
            },
            {
                group: 'Pengaturan & Sistem',
                items: [
                    {
                        menu: 'Pengaturan Sistem',
                        path: 'Pengaturan & Sistem → Pengaturan Sistem',
                        detail: 'Mengatur profil instansi, titik koordinat & radius GPS presensi sekolah, tahun ajaran aktif & pengarsipan, serta token Kiosk RFID.',
                    },
                ],
            },
        ],
    },
    {
        id: 'kepala_sekolah',
        role: 'kepala_sekolah',
        name: 'Kepala Sekolah',
        summary: 'Memantau jurnal & perangkat administrasi guru, menyetujui surat keluar, serta menindaklanjuti disposisi surat masuk.',
        sections: [
            {
                group: 'Monitoring Guru',
                items: [
                    {
                        menu: 'Rekap Administrasi Guru',
                        path: 'Monitoring Guru → Rekap Administrasi Guru',
                        detail: 'Meninjau kelengkapan perangkat pembelajaran guru, memberikan status verifikasi atau catatan revisi, serta mengunduh arsip per tahun ajaran.',
                    },
                    {
                        menu: 'Rekap Jurnal Guru',
                        path: 'Monitoring Guru → Rekap Jurnal Guru',
                        detail: 'Memantau seluruh jurnal aktivitas mengajar harian yang diisi oleh para guru di sekolah secara terpusat.',
                    },
                ],
            },
            {
                group: 'Tata Usaha & Persuratan',
                items: [
                    {
                        menu: 'Surat Masuk & Disposisi',
                        path: 'Tata Usaha → Surat Masuk / Disposisi',
                        detail: 'Membaca agenda dan file pindaian surat masuk, lalu memberikan disposisi arahan tindak lanjut kepada guru atau staf terkait.',
                    },
                    {
                        menu: 'Persetujuan Surat Keluar',
                        path: 'Tata Usaha → Surat Keluar',
                        detail: 'Memeriksa konsep surat keluar yang dibuat TU dan melakukan persetujuan (Approve) sehingga surat sah dengan QR Code verifikasi.',
                    },
                ],
            },
        ],
    },
    {
        id: 'kurikulum',
        role: 'kurikulum',
        name: 'Kurikulum',
        summary: 'Memeriksa dan memverifikasi perangkat pembelajaran serta administrasi akademik para guru.',
        sections: [
            {
                group: 'Kurikulum',
                items: [
                    {
                        menu: 'Rekap Administrasi Guru',
                        path: 'Kurikulum → Rekap Administrasi Guru',
                        detail: 'Memantau progres pengumpulan perangkat pembelajaran (RPP/Modul Ajar, Silabus, Program Tahunan/Semester), memeriksa pratinjau dokumen, dan melakukan verifikasi atau meminta revisi.',
                    },
                ],
            },
        ],
    },
    {
        id: 'tata_usaha',
        role: 'tata_usaha',
        name: 'Tata Usaha (TU)',
        summary: 'Mengelola administrasi persuratan sekolah: surat masuk, pembuatan surat keluar, disposisi, dan pengaturan kop surat.',
        sections: [
            {
                group: 'Tata Usaha',
                items: [
                    {
                        menu: 'Surat Masuk',
                        path: 'Tata Usaha → Surat Masuk',
                        detail: 'Mencatat surat masuk eksternal beserta nomor agenda, pengirim, tanggal, dan mengunggah file pindaian (PDF/gambar) agar tersimpan rapi sebagai arsip digital.',
                    },
                    {
                        menu: 'Surat Keluar',
                        path: 'Tata Usaha → Surat Keluar',
                        detail: 'Membuat surat resmi langsung menggunakan Builder template atau mengunggah file surat, kemudian mengajukan persetujuan Kepala Sekolah. Surat yang disetujui otomatis memiliki QR Code verifikasi keaslian.',
                    },
                    {
                        menu: 'Disposisi',
                        path: 'Tata Usaha → Disposisi',
                        detail: 'Membuat dan meneruskan lembar disposisi surat masuk ke pimpinan atau pegawai terkait, mencetak lembar disposisi, serta memantau status penyelesaiannya.',
                    },
                    {
                        menu: 'Pengaturan Surat',
                        path: 'Tata Usaha → Pengaturan Surat',
                        detail: 'Mengatur format kop surat, kode penomoran surat otomatis, serta data pejabat penandatangan resmi.',
                    },
                ],
            },
        ],
    },
    {
        id: 'koperasi',
        role: 'koperasi',
        name: 'Petugas Koperasi',
        summary: 'Mengoperasikan sistem kasir POS koperasi, validasi Voucher Habit, transaksi Kartu RFID guru, katalog barang, dan stock opname.',
        sections: [
            {
                group: 'Sistem POS Koperasi',
                items: [
                    {
                        menu: 'Kasir POS Voucher',
                        path: 'Koperasi Sekolah → Kasir POS Voucher',
                        detail: 'Melayani belanja dengan klik produk atau scan barcode. Scan kode Voucher Habit untuk potongan belanja (atau transfer nilainya ke saldo Kartu RFID), tap Kartu RFID presensi guru untuk bayar dengan saldo ATM koperasi, pilih metode Tunai / Kartu RFID / Split (RFID + Tunai) / QRIS, lalu cetak struk thermal.',
                    },
                    {
                        menu: 'Katalog Barang',
                        path: 'Koperasi Sekolah → Katalog Barang',
                        detail: 'Menambah dan mengedit barang per kategori (Seragam Siswa, Sabun & Cuci, ATK & Buku, Minuman, Makanan & Snack, Sembako), mengunduh Template Excel, serta mengunggah katalog massal dari file Excel.',
                    },
                    {
                        menu: 'Stok Opname',
                        path: 'Koperasi Sekolah → Stok Opname',
                        detail: 'Memantau total valuasi modal, stok menipis, dan barang habis; mengisi hitung fisik langsung di tabel (simpan massal sekaligus), menyesuaikan stok rusak/kadaluarsa/restock dengan riwayat audit, serta unduh Excel atau cetak lembar kerja audit.',
                    },
                    {
                        menu: 'Riwayat Transaksi',
                        path: 'Koperasi Sekolah → Riwayat Transaksi',
                        detail: 'Melihat log seluruh penjualan & penukaran voucher, cetak ulang struk, pembatalan (Void) transaksi jika terjadi kesalahan input, serta mengunduh Laporan Penjualan Bulanan (Excel) dan Laporan Stock Opname (Excel).',
                    },
                ],
            },
        ],
    },
    {
        id: 'pegawai',
        role: 'user',
        name: 'Pegawai / Guru',
        summary: 'Panduan aktivitas harian pegawai: presensi GPS, pengisian habit ibadah, bacaan Al-Qur\'an, voucher koperasi, jurnal guru, dan komunikasi.',
        sections: [
            {
                group: 'Menu Utama & Harian',
                items: [
                    {
                        menu: 'Dashboard',
                        path: 'Dashboard',
                        detail: 'Menampilkan ringkasan status presensi hari ini, persentase capaian habit bulan berjalan, jadwal kerja/piket aktif, dan pengingat penting.',
                    },
                    {
                        menu: 'Presensiku',
                        path: 'Presensiku',
                        detail: 'Lakukan Clock In dan Clock Out saat berada di dalam radius GPS sekolah dengan swafoto kamera langsung. Jika terlambat atau berhalangan hadir, isi keterangan terlambat atau ajukan Izin/Sakit beserta lampiran surat.',
                    },
                    {
                        menu: 'Input Habit Harian',
                        path: 'Input Habit Harian',
                        detail: 'Centang pembiasaan ibadah harian Anda. Mendukung mode offline PWA: data tetap tersimpan aman di perangkat dan otomatis terkirim saat koneksi internet kembali aktif.',
                    },
                ],
            },
            {
                group: 'Ibadah & Keislaman',
                items: [
                    {
                        menu: "Al-Qur'an & Hadis",
                        path: "Ibadah & Keislaman → Al-Qur'an & Hadis",
                        detail: 'Baca Al-Qur\'an dan hadis harian langsung dari aplikasi, lalu tekan tombol tandai baca agar sesi tilawah otomatis tercatat ke HabitLog harian Anda.',
                    },
                    {
                        menu: 'Dzikir Pagi & Petang',
                        path: 'Ibadah & Keislaman → Dzikir Pagi & Petang',
                        detail: 'Panduan bacaan dzikir pagi dan petang lengkap dengan penghitung tasbih digital sebagai referensi ibadah.',
                    },
                    {
                        menu: 'HPT Online',
                        path: 'Ibadah & Keislaman → HPT Online',
                        detail: 'Akses referensi Himpunan Putusan Tarjih Muhammadiyah untuk panduan tuntunan ibadah.',
                    },
                    {
                        menu: 'Arah Kiblat',
                        path: 'Ibadah & Keislaman → Arah Kiblat',
                        detail: 'Penunjuk arah kiblat menggunakan kompas/sensor dan lokasi perangkat Anda.',
                    },
                ],
            },
            {
                group: 'Capaian & Catatan',
                items: [
                    {
                        menu: 'Rekap Al-Quran',
                        path: 'Capaian & Catatan → Rekap Al-Quran',
                        detail: 'Melihat riwayat dan statistik akumulasi bacaan Al-Qur\'an Anda berdasarkan catatan HabitLog.',
                    },
                    {
                        menu: 'Jurnal Kajian & Hadits',
                        path: 'Capaian & Catatan → Jurnal Kajian & Hadits',
                        detail: 'Melihat ringkasan kajian dan catatan hadis yang telah diikuti/dibaca.',
                    },
                    {
                        menu: 'Voucher Habit',
                        path: 'Capaian & Catatan → Voucher Habit',
                        detail: 'Apabila target habit bulanan tercapai dan voucher diterbitkan Admin, kode voucher muncul di sini. Voucher bisa langsung ditunjukkan di Kasir Koperasi atau ditransfer menjadi saldo Kartu RFID presensi Anda.',
                    },
                    {
                        menu: 'Catatan Haid (Khusus Pegawai Wanita)',
                        path: 'Capaian & Catatan → Catatan Haid',
                        detail: 'Tandai masa haid agar item ibadah wajib tertentu otomatis menyesuaikan sehingga skor capaian habit bulanan Anda tetap adil.',
                    },
                ],
            },
            {
                group: 'Pekerjaan & Kepegawaian',
                items: [
                    {
                        menu: 'Jurnal Harian Guru',
                        path: 'Pekerjaan & Kepegawaian → Jurnal Harian Guru',
                        detail: 'Mencatat ringkasan kegiatan pembelajaran atau tugas harian guru disertai lampiran foto kegiatan.',
                    },
                    {
                        menu: 'Administrasi Guru',
                        path: 'Pekerjaan & Kepegawaian → Administrasi Guru',
                        detail: 'Mengunggah dokumen perangkat pembelajaran (RPP/Modul Ajar, Silabus, dll.) dan memantau status verifikasi dari Kurikulum/Kepala Sekolah.',
                    },
                    {
                        menu: 'Arsip Kepegawaian',
                        path: 'Pekerjaan & Kepegawaian → Arsip Kepegawaian',
                        detail: 'Menyimpan dokumen digital pribadi (KTP, Ijazah, SK, Sertifikat) dan melihat status verifikasinya.',
                    },
                    {
                        menu: 'Rapor Kinerja (KPI)',
                        path: 'Pekerjaan & Kepegawaian → Rapor Kinerja (KPI)',
                        detail: 'Melihat hasil penilaian kinerja periodik yang telah diterbitkan oleh pimpinan/admin.',
                    },
                ],
            },
            {
                group: 'Komunikasi & Rapat',
                items: [
                    {
                        menu: 'Pesan & Notifikasi',
                        path: 'Komunikasi & Rapat → Pesan & Notifikasi',
                        detail: 'Melihat daftar pemberitahuan sistem (persetujuan izin, voucher terbit, disposisi surat, dll.) dan menandai sudah dibaca.',
                    },
                    {
                        menu: 'Chat Pegawai',
                        path: 'Komunikasi & Rapat → Chat Pegawai',
                        detail: 'Berkirim pesan langsung maupun membuat grup diskusi kerja sesama pegawai sekolah.',
                    },
                    {
                        menu: 'Notulen Rapat',
                        path: 'Komunikasi & Rapat → Notulen Rapat',
                        detail: 'Membuat notulen rapat, mencatat daftar hadir dengan tanda tangan digital, serta menyetujui hasil rapat.',
                    },
                ],
            },
        ],
    },
]

// ─── Tab yang Terbuka Sesuai Role Pengguna ──────────────────────────────────
const visibleGuides = computed(() => {
    if (hasRole('superadmin')) {
        return roleGuides
    }
    const matched = roleGuides.filter((g) => hasRole(g.role))
    return matched.length > 0 ? matched : [roleGuides[roleGuides.length - 1]]
})

const activeTab = ref(visibleGuides.value[0]?.id ?? 'pegawai')

watch(visibleGuides, (list) => {
    if (!list.some((g) => g.id === activeTab.value)) {
        activeTab.value = list[0]?.id ?? 'pegawai'
    }
})

// ─── Pencarian Cepat Menu Panduan ───────────────────────────────────────────
const searchQuery = ref('')

const currentGuide = computed(() => {
    const guide = visibleGuides.value.find((g) => g.id === activeTab.value) || visibleGuides.value[0]
    const q = searchQuery.value.trim().toLowerCase()
    if (!q || !guide) return guide

    const filteredSections = guide.sections
        .map((sec) => ({
            ...sec,
            items: sec.items.filter(
                (item) =>
                    item.menu.toLowerCase().includes(q) ||
                    item.path.toLowerCase().includes(q) ||
                    item.detail.toLowerCase().includes(q)
            ),
        }))
        .filter((sec) => sec.items.length > 0)

    return { ...guide, sections: filteredSections }
})
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Buku Panduan Penggunaan" />

        <div class="w-full space-y-6">
            <!-- Header Section -->
            <div class="relative overflow-hidden bg-gradient-to-br from-emerald-600 to-teal-800 rounded-3xl p-7 md:p-10 text-white shadow-lg">
                <div class="relative z-10 flex flex-col md:flex-row md:items-end justify-between gap-6">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/20 backdrop-blur-md rounded-full text-xs font-bold tracking-widest uppercase mb-3 border border-white/30">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                            <span>Buku Panduan • Sesuai Role Anda</span>
                        </div>
                        <h1 class="text-2xl md:text-4xl font-black mb-2 tracking-tight">Panduan Penggunaan Sistem</h1>
                        <p class="text-emerald-50 text-sm md:text-base max-w-2xl font-medium">
                            Penjelasan fungsi dan langkah penggunaan setiap menu yang tersedia untuk peran Anda di aplikasi SAHABAT SDAM.
                        </p>
                    </div>

                    <!-- Search Box -->
                    <div class="w-full md:w-72 relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Cari menu atau fitur..."
                            class="w-full pl-9 pr-4 py-2.5 rounded-xl bg-white/95 text-slate-800 placeholder-slate-400 text-xs font-medium border border-white/50 focus:outline-none focus:ring-2 focus:ring-emerald-300 shadow-sm"
                        />
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Role Tabs (Hanya menampilkan tab sesuai role yang dimiliki) -->
            <div v-if="visibleGuides.length > 1" class="flex flex-wrap gap-2 p-1.5 bg-slate-100 dark:bg-slate-800/80 rounded-2xl border border-slate-200 dark:border-slate-700">
                <button
                    v-for="tab in visibleGuides"
                    :key="tab.id"
                    type="button"
                    @click="activeTab = tab.id"
                    class="flex-1 min-w-[140px] flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all duration-200 cursor-pointer"
                    :class="activeTab === tab.id
                        ? 'bg-white dark:bg-slate-900 text-emerald-700 dark:text-emerald-400 shadow-sm border border-slate-200/70 dark:border-slate-700'
                        : 'text-slate-500 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-700/60 hover:text-slate-800 dark:hover:text-slate-200'"
                >
                    <span>{{ tab.name }}</span>
                </button>
            </div>

            <!-- Content Area -->
            <div v-if="currentGuide" class="bg-white dark:bg-slate-900 rounded-3xl p-6 md:p-8 shadow-sm border border-slate-200 dark:border-slate-800 space-y-8">
                <!-- Role Summary Banner -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-5 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-300 mb-1.5">
                            Panduan Peran: {{ currentGuide.name }}
                        </span>
                        <p class="text-sm text-slate-600 dark:text-slate-300">{{ currentGuide.summary }}</p>
                    </div>
                </div>

                <!-- Menu Groups -->
                <div v-if="currentGuide.sections.length > 0" class="space-y-8">
                    <section v-for="(section, sIdx) in currentGuide.sections" :key="section.group" class="space-y-3.5">
                        <h2 class="text-lg font-black text-slate-800 dark:text-slate-100 flex items-center gap-3">
                            <span class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 flex items-center justify-center text-sm font-black flex-shrink-0">
                                {{ sIdx + 1 }}
                            </span>
                            <span>{{ section.group }}</span>
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5 md:pl-11">
                            <div
                                v-for="item in section.items"
                                :key="item.menu"
                                class="bg-slate-50 dark:bg-slate-800/60 rounded-2xl p-4 sm:p-5 border border-slate-200/80 dark:border-slate-700/70 hover:border-emerald-300 dark:hover:border-emerald-700 transition-colors space-y-2"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">{{ item.menu }}</h3>
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700 flex-shrink-0">
                                        {{ item.path }}
                                    </span>
                                </div>
                                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                    {{ item.detail }}
                                </p>
                            </div>
                        </div>
                    </section>
                </div>

                <div v-else class="py-12 text-center text-sm text-slate-400">
                    Tidak ada panduan menu yang cocok dengan kata kunci "{{ searchQuery }}".
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
