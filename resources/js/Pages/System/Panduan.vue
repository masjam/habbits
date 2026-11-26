<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const page = usePage()
const userRoles = computed(() => page.props.auth?.roles ?? [])
const hasRole = (role) => userRoles.value.includes(role)

// ─── Ikon & Tema Warna Infografis Alur Kerja ────────────────────────────────
const workflowStepIcons = [
    'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z',
    'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
]

const stepThemes = [
    {
        node: 'bg-emerald-600 text-white ring-emerald-100 dark:ring-emerald-900/60',
        badge: 'bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300',
        bar: 'bg-emerald-500',
        border: 'hover:border-emerald-300 dark:hover:border-emerald-700',
    },
    {
        node: 'bg-teal-600 text-white ring-teal-100 dark:ring-teal-900/60',
        badge: 'bg-teal-100 dark:bg-teal-900/60 text-teal-700 dark:text-teal-300',
        bar: 'bg-teal-500',
        border: 'hover:border-teal-300 dark:hover:border-teal-700',
    },
    {
        node: 'bg-sky-600 text-white ring-sky-100 dark:ring-sky-900/60',
        badge: 'bg-sky-100 dark:bg-sky-900/60 text-sky-700 dark:text-sky-300',
        bar: 'bg-sky-500',
        border: 'hover:border-sky-300 dark:hover:border-sky-700',
    },
    {
        node: 'bg-amber-500 text-white ring-amber-100 dark:ring-amber-900/60',
        badge: 'bg-amber-100 dark:bg-amber-900/60 text-amber-800 dark:text-amber-300',
        bar: 'bg-amber-500',
        border: 'hover:border-amber-300 dark:hover:border-amber-700',
    },
]

const getStepTheme = (idx) => stepThemes[idx % stepThemes.length]

// ─── Data Panduan Lengkap per Role (Alur Kerja + Langkah Detail + Akses Cepat) ──
const roleGuides = [
    {
        id: 'superadmin',
        role: 'superadmin',
        name: 'Superadmin',
        tagline: 'Kendali Penuh Sistem & Audit',
        summary: 'Mengelola seluruh modul operasional sekolah, mengaktifkan/menonaktifkan fitur HR, memantau kesehatan server, dan menguji peran pengguna.',
        workflow: [
            { step: '01', title: 'Cek Kesehatan & Log', desc: 'Pantau server & log login' },
            { step: '02', title: 'Atur Feature Flag', desc: 'Aktif/nonaktifkan modul HR' },
            { step: '03', title: 'Audit Habit Kosong', desc: 'Cek pegawai belum isi habit' },
            { step: '04', title: 'Simulasi Role', desc: 'Uji tampilan tiap peran' },
        ],
        sections: [
            {
                group: 'Kontrol Khusus Superadmin',
                items: [
                    {
                        menu: 'Cek Belum Isi Habit',
                        path: 'Habit & Voucher → Cek Belum Isi Habit',
                        route: 'admin.laporan.unfilled',
                        detail: 'Menampilkan daftar pegawai yang belum mengisi form habit pada tanggal tertentu agar pembinaan bisa dilakukan tepat waktu.',
                        steps: [
                            'Pilih tanggal yang ingin diperiksa.',
                            'Lihat daftar pegawai yang statusnya masih kosong.',
                            'Ekspor data ke Excel bila perlu ditindaklanjuti.',
                        ],
                        tip: 'Hanya Superadmin yang memiliki akses ke halaman pengecekan ini.',
                    },
                    {
                        menu: 'Fitur HR & Lanjutan',
                        path: 'Pengaturan & Sistem → Fitur HR & Lanjutan',
                        route: 'admin.settings.hr',
                        detail: 'Sakelar utama untuk mengaktifkan atau menonaktifkan modul aplikasi secara global serta pembersihan foto presensi lama.',
                        steps: [
                            'Nyalakan/matikan fitur (Presensi, Habit, Jurnal, Administrasi, Arsip, KPI, Divisi).',
                            'Gunakan fitur pembersihan foto lama untuk menghemat kapasitas server.',
                        ],
                        tip: 'Perubahan feature flag langsung menyembunyikan/memunculkan menu di Sidebar semua pegawai.',
                    },
                    {
                        menu: 'Log Akses Login',
                        path: 'Pengaturan & Sistem → Log Akses Login',
                        route: 'admin.login-logs',
                        detail: 'Rekam jejak keamanan aktivitas masuk pengguna mencakup waktu, IP address, perangkat/browser, dan lokasi.',
                        steps: [
                            'Filter log berdasarkan nama pegawai atau rentang tanggal.',
                            'Periksa login mencurigakan atau lokasi yang tidak wajar.',
                        ],
                    },
                    {
                        menu: 'Pemantauan Sistem',
                        path: 'Pengaturan & Sistem → Pemantauan Sistem',
                        route: 'admin.system.monitor',
                        detail: 'Dasbor teknis untuk mengecek status antrean (queue), cache, dan performa aplikasi secara langsung.',
                        steps: [
                            'Pantau metrik server secara berkala.',
                            'Pastikan antrean ekspor laporan berjalan lancar tanpa penumpukan.',
                        ],
                    },
                    {
                        menu: 'Simulasi Role (Role Simulator)',
                        path: 'Bar Atas Layar',
                        route: null,
                        detail: 'Memungkinkan Superadmin berpindah sudut pandang sementara ke role lain tanpa logout.',
                        steps: [
                            'Pilih role target pada bar simulasi di bagian atas halaman.',
                            'Uji menu dan hak akses role tersebut, lalu klik kembali ke Superadmin.',
                        ],
                    },
                ],
            },
        ],
    },
    {
        id: 'admin',
        role: 'admin',
        name: 'Admin',
        tagline: 'Operasional Kepegawaian, Presensi & Voucher',
        summary: 'Mengatur jadwal kerja, merekap presensi & habit bulanan, menerbitkan voucher koperasi, serta mengelola data user.',
        workflow: [
            { step: '01', title: 'Kelola User & RFID', desc: 'Daftarkan pegawai & kartu' },
            { step: '02', title: 'Atur Jadwal & GPS', desc: 'Jam kerja, piket, radius' },
            { step: '03', title: 'Rekap Presensi', desc: 'Approve izin & ekspor PDF' },
            { step: '04', title: 'Terbitkan Voucher', desc: 'Generate reward habit' },
        ],
        sections: [
            {
                group: 'Presensi & Jadwal',
                items: [
                    {
                        menu: 'Rekap Presensi',
                        path: 'Presensi & Jadwal → Rekap Presensi',
                        route: 'admin.attendance.index',
                        detail: 'Pusat pemantauan kehadiran harian & bulanan pegawai beserta persetujuan izin dan ekspor laporan.',
                        steps: [
                            'Pilih tampilan Harian atau Bulanan sesuai kebutuhan.',
                            'Setujui/tolak pengajuan izin atau koreksi catatan kehadiran bila perlu.',
                            'Unduh laporan dalam format Excel atau PDF (jadwal khusus otomatis diberi warna penanda).',
                        ],
                    },
                    {
                        menu: 'Jam Kerja Harian & Piket',
                        path: 'Presensi & Jadwal → Jam Kerja Harian & Piket',
                        route: 'admin.duty-schedules.index',
                        detail: 'Mengatur jam masuk dan pulang standar per hari serta penugasan piket pegawai tertentu.',
                        steps: [
                            'Tetapkan jam masuk & batas pulang harian instansi.',
                            'Tambahkan jadwal piket bagi pegawai dengan jam tugas khusus.',
                        ],
                    },
                    {
                        menu: 'Jadwal Khusus Tertentu',
                        path: 'Presensi & Jadwal → Jadwal Khusus Tertentu',
                        route: 'admin.special-schedules.index',
                        detail: 'Override jam kerja massal pada tanggal atau periode khusus seperti bulan Ramadan atau acara sekolah.',
                        steps: [
                            'Buat jadwal khusus dengan tanggal dan jam masuk/pulang baru.',
                            'Tanggal tersebut akan otomatis ditandai warna khusus pada rekap & ekspor PDF/Excel.',
                        ],
                    },
                ],
            },
            {
                group: 'Habit & Voucher',
                items: [
                    {
                        menu: 'Semua Laporan Habit',
                        path: 'Habit & Voucher → Semua Laporan Habit',
                        route: 'admin.laporan',
                        detail: 'Memantau persentase capaian ibadah bulanan tiap pegawai dan mengatur target minimal kelayakan voucher.',
                        steps: [
                            'Atur persentase target capaian bulanan sekolah.',
                            'Klik nama pegawai untuk melihat rincian log harian atau ekspor rekap Excel.',
                        ],
                    },
                    {
                        menu: 'Voucher Koperasi',
                        path: 'Habit & Voucher → Voucher Koperasi',
                        route: 'admin.vouchers.index',
                        detail: 'Menerbitkan voucher belanja koperasi bagi pegawai yang memenuhi target habit dan memantau penukarannya.',
                        steps: [
                            'Atur nominal & judul reward voucher di pengaturan voucher.',
                            'Klik Generate Voucher untuk menerbitkan kode voucher periode berjalan.',
                            'Pantau status voucher (Aktif / Sudah Ditukar di Koperasi).',
                        ],
                        tip: 'Pegawai baru bisa memakai atau mentransfer voucher ke saldo RFID setelah Admin menekan tombol Generate.',
                    },
                    {
                        menu: 'Master Data Habit',
                        path: 'Habit & Voucher → Master Data Habit',
                        route: 'admin.habits.index',
                        detail: 'Mengelola daftar amalan pembiasaan ibadah yang tampil di formulir harian pegawai.',
                        steps: [
                            'Tambah item habit baru, ubah bobot/poin, atau atur urutannya (drag/reorder).',
                            'Nonaktifkan item yang tidak dipakai tanpa menghapus riwayat lama.',
                        ],
                    },
                ],
            },
            {
                group: 'Kepegawaian & Monitoring',
                items: [
                    {
                        menu: 'Daftar User',
                        path: 'Kepegawaian → Daftar User',
                        route: 'admin.users.index',
                        detail: 'Manajemen akun pegawai, penetapan role, UID Kartu RFID, dan reset password.',
                        steps: [
                            'Tambah user manual atau impor massal lewat template Excel.',
                            'Isi UID Kartu RFID agar pegawai bisa absen di Kiosk RFID & belanja di POS Koperasi.',
                            'Gunakan tombol Reset Password jika pegawai lupa kata sandi.',
                        ],
                    },
                    {
                        menu: 'Verifikasi Arsip & Evaluasi KPI',
                        path: 'Kepegawaian → Verifikasi Arsip / KPI',
                        route: 'admin.employee-documents.index',
                        detail: 'Memvalidasi dokumen kepegawaian (KTP, Ijazah, SK) dan menerbitkan nilai rapor KPI pegawai.',
                        steps: [
                            'Periksa dokumen yang berstatus Menunggu Verifikasi.',
                            'Buka menu Evaluasi Kinerja (KPI) untuk mengisi skor kinerja periodik.',
                        ],
                    },
                    {
                        menu: 'Rekap Administrasi & Jurnal Guru',
                        path: 'Monitoring Guru',
                        route: 'admin.teacher-administrations.index',
                        detail: 'Memantau pengumpulan perangkat pembelajaran dan catatan jurnal harian seluruh guru.',
                        steps: [
                            'Tinjau perangkat ajar yang masuk dan berikan status verifikasi.',
                            'Buka Rekap Jurnal Guru untuk melihat aktivitas mengajar harian.',
                        ],
                    },
                    {
                        menu: 'Pengaturan Sistem',
                        path: 'Pengaturan & Sistem → Pengaturan Sistem',
                        route: 'admin.settings',
                        detail: 'Konfigurasi inti sekolah: koordinat & radius GPS presensi, tahun ajaran aktif, dan token Kiosk RFID.',
                        steps: [
                            'Tentukan titik koordinat sekolah dan radius maksimal (meter) untuk presensi GPS.',
                            'Kelola Tahun Ajaran aktif untuk pengarsipan administrasi guru.',
                        ],
                    },
                ],
            },
        ],
    },
    {
        id: 'kepala_sekolah',
        role: 'kepala_sekolah',
        name: 'Kepala Sekolah',
        tagline: 'Supervisi Guru & Persetujuan Surat',
        summary: 'Memantau jurnal harian & perangkat ajar guru, menyetujui surat keluar resmi, serta mendisposisi surat masuk.',
        workflow: [
            { step: '01', title: 'Cek Jurnal Guru', desc: 'Pantau KBM harian guru' },
            { step: '02', title: 'Verifikasi Perangkat', desc: 'Tinjau administrasi ajar' },
            { step: '03', title: 'Disposisi Surat', desc: 'Beri arahan tindak lanjut' },
            { step: '04', title: 'Approve Surat Keluar', desc: 'Sah dengan QR Verifikasi' },
        ],
        sections: [
            {
                group: 'Monitoring Guru',
                items: [
                    {
                        menu: 'Rekap Administrasi Guru',
                        path: 'Monitoring Guru → Rekap Administrasi Guru',
                        route: 'admin.teacher-administrations.index',
                        detail: 'Memeriksa kelengkapan perangkat pembelajaran (RPP/Modul Ajar, Silabus, Prota/Promes) milik seluruh guru.',
                        steps: [
                            'Pratinjau dokumen yang diunggah guru langsung dari browser.',
                            'Berikan status Disetujui atau catatan revisi bila ada kekurangan.',
                        ],
                    },
                    {
                        menu: 'Rekap Jurnal Guru',
                        path: 'Monitoring Guru → Rekap Jurnal Guru',
                        route: 'teacher-journals.index',
                        detail: 'Memantau seluruh jurnal aktivitas mengajar harian para guru di sekolah dalam satu daftar terpadu.',
                        steps: [
                            'Buka menu Rekap Jurnal Guru untuk melihat catatan harian beserta foto kegiatan.',
                            'Pantau keaktifan pengisian jurnal guru setiap hari.',
                        ],
                    },
                ],
            },
            {
                group: 'Tata Usaha & Persuratan',
                items: [
                    {
                        menu: 'Surat Masuk & Disposisi',
                        path: 'Tata Usaha → Surat Masuk / Disposisi',
                        route: 'tata-usaha.disposisi.index',
                        detail: 'Membaca surat masuk dan menerbitkan arahan disposisi kepada guru atau staf terkait.',
                        steps: [
                            'Buka lampiran surat masuk yang dicatat oleh Tata Usaha.',
                            'Tulis instruksi disposisi dan pilih penerima tindak lanjut.',
                        ],
                    },
                    {
                        menu: 'Persetujuan Surat Keluar',
                        path: 'Tata Usaha → Surat Keluar',
                        route: 'tata-usaha.surat-keluar.index',
                        detail: 'Meninjau draf surat keluar dan memberikan persetujuan resmi bertanda tangan/QR Code.',
                        steps: [
                            'Periksa isi draf surat keluar yang berstatus Menunggu Persetujuan.',
                            'Klik Approve agar QR Code verifikasi keaslian aktif dan surat siap dikirim.',
                        ],
                    },
                ],
            },
        ],
    },
    {
        id: 'kurikulum',
        role: 'kurikulum',
        name: 'Kurikulum',
        tagline: 'Supervisi Perangkat Pembelajaran',
        summary: 'Memantau progres pengumpulan serta memverifikasi kelayakan dokumen administrasi dan perangkat ajar guru.',
        workflow: [
            { step: '01', title: 'Cek Progres Unggahan', desc: 'Filter per tahun ajaran' },
            { step: '02', title: 'Pratinjau Dokumen', desc: 'Periksa isi RPP / Modul' },
            { step: '03', title: 'Verifikasi / Revisi', desc: 'Beri status & catatan' },
        ],
        sections: [
            {
                group: 'Kurikulum',
                items: [
                    {
                        menu: 'Rekap Administrasi Guru',
                        path: 'Kurikulum → Rekap Administrasi Guru',
                        route: 'admin.teacher-administrations.index',
                        detail: 'Dasbor khusus Kurikulum untuk meninjau dan memverifikasi perangkat ajar seluruh guru.',
                        steps: [
                            'Filter berdasarkan tahun ajaran aktif atau status verifikasi.',
                            'Klik Pratinjau untuk memeriksa dokumen tanpa harus mengunduh satu per satu.',
                            'Setujui dokumen yang lengkap atau beri catatan revisi untuk diperbaiki guru.',
                        ],
                    },
                ],
            },
        ],
    },
    {
        id: 'tata_usaha',
        role: 'tata_usaha',
        name: 'Tata Usaha (TU)',
        tagline: 'Administrasi Persuratan & Disposisi',
        summary: 'Mengelola arsip surat masuk, menyusun surat keluar lewat Builder, meneruskan disposisi, dan mengatur kop surat.',
        workflow: [
            { step: '01', title: 'Catat Surat Masuk', desc: 'Input agenda & scan PDF' },
            { step: '02', title: 'Kirim Disposisi', desc: 'Teruskan ke Kepsek / Staf' },
            { step: '03', title: 'Buat Surat Keluar', desc: 'Susun via Builder / Upload' },
            { step: '04', title: 'Ajukan Approval', desc: 'Terbit QR Verifikasi sah' },
        ],
        sections: [
            {
                group: 'Tata Usaha',
                items: [
                    {
                        menu: 'Surat Masuk',
                        path: 'Tata Usaha → Surat Masuk',
                        route: 'tata-usaha.surat-masuk.index',
                        detail: 'Pencatatan agenda surat masuk eksternal beserta penyimpanan arsip file digital (PDF/gambar).',
                        steps: [
                            'Klik Tambah Surat Masuk, isi nomor surat, asal instansi, dan perihal.',
                            'Unggah file pindaian surat agar bisa dibaca langsung oleh Kepala Sekolah.',
                        ],
                    },
                    {
                        menu: 'Surat Keluar',
                        path: 'Tata Usaha → Surat Keluar',
                        route: 'tata-usaha.surat-keluar.index',
                        detail: 'Pembuatan surat resmi sekolah menggunakan Surat Builder atau unggah file, lengkap dengan QR verifikasi.',
                        steps: [
                            'Gunakan Builder untuk mengetik surat langsung dengan kop otomatis, atau unggah dokumen jadi.',
                            'Ajukan persetujuan ke Kepala Sekolah hingga QR Code verifikasi terbit.',
                        ],
                    },
                    {
                        menu: 'Disposisi',
                        path: 'Tata Usaha → Disposisi',
                        route: 'tata-usaha.disposisi.index',
                        detail: 'Meneruskan surat masuk ke tujuan disposisi, mencetak lembar disposisi, dan memantau statusnya.',
                        steps: [
                            'Pilih surat masuk yang ingin didisposisikan dan tentukan penerimanya.',
                            'Cetak lembar disposisi fisik atau pantau status dibaca/selesai secara online.',
                        ],
                    },
                    {
                        menu: 'Pengaturan Surat',
                        path: 'Tata Usaha → Pengaturan Surat',
                        route: 'tata-usaha.settings.index',
                        detail: 'Konfigurasi kop surat resmi sekolah, format penomoran otomatis, dan pejabat penandatangan.',
                        steps: [
                            'Sesuaikan logo, alamat kop surat, dan format nomor surat keluar.',
                        ],
                    },
                ],
            },
        ],
    },
    {
        id: 'koperasi',
        role: 'koperasi',
        name: 'Petugas Koperasi',
        tagline: 'Kasir POS, RFID Saldo & Stock Opname',
        summary: 'Mengoperasikan kasir Waroong POS Koperasi, menerima pembayaran Voucher Habit & Kartu RFID guru, kelola katalog, dan audit stok.',
        workflow: [
            { step: '01', title: 'Scan Produk / Tap RFID', desc: 'Input belanja & cek saldo' },
            { step: '02', title: 'Pakai / Transfer Voucher', desc: 'Potong total / isi saldo RFID' },
            { step: '03', title: 'Bayar & Cetak Struk', desc: 'Tunai / RFID / Split / QRIS' },
            { step: '04', title: 'Opname & Laporan', desc: 'Audit fisik & unduh Excel' },
        ],
        sections: [
            {
                group: 'Sistem POS Koperasi',
                items: [
                    {
                        menu: 'Kasir POS Voucher',
                        path: 'Koperasi Sekolah → Kasir POS Voucher',
                        route: 'koperasi.pos',
                        detail: 'Mesin kasir cepat (autofocus scanner USB) untuk belanja barang koperasi, penukaran voucher, dan pembayaran saldo RFID.',
                        steps: [
                            'Klik produk di katalog atau scan barcode barang untuk masuk keranjang.',
                            'Tap Kartu RFID guru untuk memunculkan nama & saldo ATM koperasi miliknya.',
                            'Scan kode Voucher Habit untuk potongan langsung atau klik "Masukkan ke Saldo Kartu RFID".',
                            'Pilih metode bayar (Tunai, Kartu RFID, Split RFID+Tunai, atau QRIS), lalu cetak struk thermal.',
                        ],
                        tip: 'Shortcut keyboard kasir: tekan F2 untuk fokus ke kotak scanner, tekan F4 untuk langsung membuka jendela pembayaran.',
                    },
                    {
                        menu: 'Katalog Barang',
                        path: 'Koperasi Sekolah → Katalog Barang',
                        route: 'koperasi.products',
                        detail: 'Mengelola daftar produk per kategori (Seragam Siswa, Sabun & Cuci, ATK, Minuman, Snack, Sembako) termasuk impor Excel.',
                        steps: [
                            'Klik Tambah Produk untuk input satuan atau Edit untuk ubah harga/stok.',
                            'Unduh Template Excel, isi daftar barang, lalu unggah lewat tombol Upload Katalog Excel.',
                        ],
                    },
                    {
                        menu: 'Stok Opname',
                        path: 'Koperasi Sekolah → Stok Opname',
                        route: 'koperasi.stock-opname',
                        detail: 'Dasbor pengawasan inventaris untuk cek valuasi aset modal, stok menipis/habis, serta audit hitung fisik.',
                        steps: [
                            'Gunakan filter "Stok Menipis" atau "Stok Kosong" untuk cek barang yang perlu kulakan.',
                            'Ketik angka hitung fisik langsung di kolom tabel — selisih otomatis terhitung, lalu klik "Terapkan Hasil Opname".',
                            'Atau klik "Sesuaikan" untuk mencatat barang rusak, kadaluarsa, atau restock dengan catatan audit.',
                        ],
                    },
                    {
                        menu: 'Riwayat Transaksi',
                        path: 'Koperasi Sekolah → Riwayat Transaksi',
                        route: 'koperasi.history',
                        detail: 'Arsip transaksi kasir, cetak ulang struk, pembatalan (Void), serta ekspor laporan bulanan.',
                        steps: [
                            'Pilih bulan lalu klik "Laporan Penjualan (Excel)" untuk mengunduh rekap omset bulanan.',
                            'Klik "Void" jika ada transaksi yang perlu dibatalkan (saldo/voucher otomatis dikembalikan).',
                        ],
                    },
                ],
            },
        ],
    },
    {
        id: 'pegawai',
        role: 'user',
        name: 'Pegawai / Guru',
        tagline: 'Panduan Aktivitas Harian Pegawai',
        summary: 'Langkah praktis presensi GPS, pengisian habit harian (mendukung offline), tilawah Al-Qur\'an, penukaran voucher koperasi, hingga administrasi guru.',
        workflow: [
            { step: '01', title: 'Clock In GPS', desc: 'Absen datang di radius sekolah' },
            { step: '02', title: 'Isi Habit & Tilawah', desc: 'Centang ibadah & baca Qur\'an' },
            { step: '03', title: 'Jurnal / Tugas', desc: 'Isi jurnal & arsip bila guru' },
            { step: '04', title: 'Klaim Voucher', desc: 'Belanja / isi saldo RFID' },
        ],
        sections: [
            {
                group: 'Menu Utama & Harian',
                items: [
                    {
                        menu: 'Dashboard',
                        path: 'Menu Utama → Dashboard',
                        route: 'dashboard',
                        detail: 'Halaman ringkasan harian untuk memantau status absen hari ini, persentase habit bulanan, dan jadwal piket.',
                        steps: [
                            'Periksa status Clock In/Clock Out Anda setiap hari.',
                            'Pantau progres target habit bulanan untuk meraih Voucher Koperasi.',
                        ],
                    },
                    {
                        menu: 'Presensiku',
                        path: 'Menu Utama → Presensiku',
                        route: 'attendance.index',
                        detail: 'Absensi harian berbasis lokasi GPS sekolah dan swafoto kamera langsung, plus pengajuan izin/sakit.',
                        steps: [
                            'Izinkan akses lokasi (GPS) dan kamera pada browser HP Anda.',
                            'Pastikan Anda berada di dalam radius sekolah, lalu tekan Clock In / Clock Out dan ambil swafoto.',
                            'Jika berhalangan hadir, pilih tab Pengajuan Izin/Sakit dan unggah surat keterangan.',
                        ],
                    },
                    {
                        menu: 'Input Habit Harian',
                        path: 'Menu Utama → Input Habit Harian',
                        route: 'habit.form',
                        detail: 'Formulir pencatatan pembiasaan ibadah harian yang tetap bisa diisi walau sedang tanpa sinyal internet.',
                        steps: [
                            'Buka form dan centang ibadah yang telah dilaksanakan hari ini.',
                            'Bila ada perubahan, isi atau perbarui kapan saja pada hari tersebut.',
                        ],
                        tip: 'Mode Offline PWA: Saat internet mati, data disimpan di memori HP (IndexedDB) dan otomatis terkirim saat koneksi kembali aktif.',
                    },
                ],
            },
            {
                group: 'Ibadah & Keislaman',
                items: [
                    {
                        menu: "Al-Qur'an & Hadis",
                        path: "Ibadah & Keislaman → Al-Qur'an & Hadis",
                        route: 'quran.hadis',
                        detail: 'Membaca mushaf Al-Qur\'an dan hadis pilihan sekaligus mencatat sesi tilawah langsung ke HabitLog.',
                        steps: [
                            'Pilih surah/ayat atau bacaan hadis harian.',
                            'Tekan tombol Tandai Baca setelah selesai agar poin tilawah otomatis masuk ke laporan habit.',
                        ],
                    },
                    {
                        menu: 'Dzikir, HPT Online & Arah Kiblat',
                        path: 'Ibadah & Keislaman',
                        route: 'dzikir.index',
                        detail: 'Referensi bacaan Dzikir Pagi & Petang (dengan tasbih digital), Himpunan Putusan Tarjih (HPT), dan kompas Arah Kiblat.',
                        steps: [
                            'Gunakan Dzikir Pagi & Petang beserta tombol tasbih untuk menghitung dzikir.',
                            'Buka Arah Kiblat saat berada di lokasi baru untuk mengecek arah shalat.',
                        ],
                    },
                ],
            },
            {
                group: 'Capaian, Voucher & Catatan',
                items: [
                    {
                        menu: 'Rekap Al-Quran & Jurnal Kajian',
                        path: 'Capaian & Catatan',
                        route: 'quran.recap',
                        detail: 'Menampilkan statistik riwayat tilawah Al-Qur\'an serta rangkuman catatan kajian dan hadis Anda.',
                        steps: [
                            'Pantau konsistensi jumlah ayat/halaman yang telah dibaca tiap bulan.',
                        ],
                    },
                    {
                        menu: 'Voucher Habit',
                        path: 'Capaian & Catatan → Voucher Habit',
                        route: 'habit.vouchers',
                        detail: 'Daftar voucher apresiasi koperasi yang Anda peroleh saat target habit bulanan tercapai dan diterbitkan Admin.',
                        steps: [
                            'Tunjukkan kode/QR voucher di Kasir Koperasi saat berbelanja untuk mendapat potongan harga.',
                            'Atau klik "Transfer ke Saldo Kartu RFID" agar nilai voucher masuk menjadi saldo ATM Kartu Presensi Anda.',
                        ],
                        tip: 'Saldo Kartu RFID bisa digunakan berkali-kali untuk belanja di Koperasi cukup dengan tap kartu presensi Anda.',
                    },
                    {
                        menu: 'Catatan Haid (Khusus Pegawai Wanita)',
                        path: 'Capaian & Catatan → Catatan Haid',
                        route: 'haid.index',
                        detail: 'Penandaan masa halangan ibadah bagi pegawai wanita agar skor bulanan tetap adil.',
                        steps: [
                            'Aktifkan status masa haid saat mulai, dan nonaktifkan kembali ketika sudah suci.',
                        ],
                    },
                ],
            },
            {
                group: 'Pekerjaan & Kepegawaian',
                items: [
                    {
                        menu: 'Jurnal Harian & Administrasi Guru',
                        path: 'Pekerjaan & Kepegawaian',
                        route: 'teacher-journals.index',
                        detail: 'Pencatatan kegiatan KBM harian guru beserta pengunggahan perangkat pembelajaran (RPP/Modul Ajar).',
                        steps: [
                            'Isi Jurnal Harian Guru setiap selesai mengajar dan lampirkan foto dokumentasi.',
                            'Unggah dokumen perangkat pembelajaran di menu Administrasi Guru untuk diverifikasi Kurikulum/Kepsek.',
                        ],
                    },
                    {
                        menu: 'Arsip Kepegawaian & Rapor KPI',
                        path: 'Pekerjaan & Kepegawaian',
                        route: 'employee-documents.index',
                        detail: 'Penyimpanan berkas digital pribadi (KTP, Ijazah, SK) serta melihat hasil evaluasi kinerja (KPI).',
                        steps: [
                            'Unggah pindaian dokumen penting Anda dan pantau status verifikasinya oleh Admin.',
                            'Buka Rapor Kinerja (KPI) untuk melihat hasil penilaian periodik.',
                        ],
                    },
                ],
            },
            {
                group: 'Komunikasi & Rapat',
                items: [
                    {
                        menu: 'Pesan, Chat & Notulen Rapat',
                        path: 'Komunikasi & Rapat',
                        route: 'notifications.index',
                        detail: 'Pusat notifikasi sistem, obrolan internal antar pegawai, dan notulen rapat beserta tanda tangan daftar hadir digital.',
                        steps: [
                            'Cek ikon/badge merah di Pesan & Notifikasi untuk info persetujuan izin, disposisi, atau voucher.',
                            'Gunakan Chat Pegawai untuk koordinasi kerja dan Notulen Rapat untuk menandatangani kehadiran rapat.',
                        ],
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

// ─── Pencarian & Statistik Ringkas ──────────────────────────────────────────
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
                    item.detail.toLowerCase().includes(q) ||
                    item.steps.some((s) => s.toLowerCase().includes(q))
            ),
        }))
        .filter((sec) => sec.items.length > 0)

    return { ...guide, sections: filteredSections }
})

const totalMenusCount = computed(() =>
    (currentGuide.value?.sections ?? []).reduce((sum, sec) => sum + sec.items.length, 0)
)

const scrollToSection = (idx) => {
    const el = document.getElementById(`guide-section-${idx}`)
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
}

// ─── Scroll Reveal Observer & Mobile Accordion ──────────────────────────────
const activeSectionIndex = ref(0)
const openMobileSections = ref({ 0: true })
const isSectionOpen = (idx) => searchQuery.value.trim().length > 0 || !!openMobileSections.value[idx]
const toggleMobileSection = (idx) => {
    if (typeof window !== 'undefined' && window.innerWidth >= 1024) return
    openMobileSections.value[idx] = !openMobileSections.value[idx]
}

let observer = null

const setupObserver = () => {
    if (typeof window === 'undefined' || !('IntersectionObserver' in window)) return
    if (observer) observer.disconnect()

    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed')
                    const idx = Number(entry.target.dataset.sectionIdx)
                    if (!Number.isNaN(idx)) {
                        activeSectionIndex.value = idx
                    }
                }
            })
        },
        { threshold: 0.12, rootMargin: '0px 0px -10% 0px' }
    )

    document.querySelectorAll('.guide-reveal').forEach((el) => observer.observe(el))
}

onMounted(() => {
    setupObserver()
})

watch(activeTab, () => {
    openMobileSections.value = { 0: true }
    activeSectionIndex.value = 0
})

watch([activeTab, searchQuery], () => {
    setTimeout(setupObserver, 50)
})

onUnmounted(() => {
    if (observer) observer.disconnect()
})
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Buku Panduan Penggunaan" />

        <div class="w-full space-y-6">
            <!-- ── 1. Dimensional Hero Header ───────────────────────────── -->
            <div class="relative overflow-hidden bg-gradient-to-br from-emerald-600 via-emerald-700 to-teal-900 rounded-3xl p-6 sm:p-8 md:p-10 text-white shadow-lg">
                <!-- Background depth planes -->
                <div class="absolute -right-16 -top-16 w-64 h-64 rounded-full bg-teal-400/20 blur-2xl pointer-events-none"></div>
                <div class="absolute right-24 -bottom-20 w-56 h-56 rounded-full bg-emerald-300/15 blur-xl pointer-events-none"></div>
                <div class="hidden md:block absolute right-8 top-1/2 -translate-y-1/2 opacity-15 pointer-events-none">
                    <svg class="w-48 h-48" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                </div>

                <div class="relative z-10 flex flex-col lg:flex-row lg:items-end justify-between gap-6">
                    <div class="space-y-3 max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/15 backdrop-blur-md rounded-full text-xs font-bold tracking-wider uppercase border border-white/25">
                            <span class="w-2 h-2 rounded-full bg-amber-300 animate-pulse"></span>
                            <span>Buku Panduan Interaktif • {{ currentGuide?.name }}</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl md:text-4xl font-black tracking-tight leading-tight">
                            {{ currentGuide?.tagline || 'Panduan Penggunaan Sistem' }}
                        </h1>
                        <p class="text-emerald-50/90 text-sm md:text-base leading-relaxed">
                            {{ currentGuide?.summary }}
                        </p>

                        <!-- Quick Stats Pills -->
                        <div class="flex flex-wrap items-center gap-2 pt-1 text-xs font-bold">
                            <span class="px-3 py-1 rounded-full bg-white/15 border border-white/20">
                                {{ currentGuide?.sections?.length ?? 0 }} Kelompok Menu
                            </span>
                            <span class="px-3 py-1 rounded-full bg-white/15 border border-white/20">
                                {{ totalMenusCount }} Panduan Fitur
                            </span>
                            <span class="px-3 py-1 rounded-full bg-amber-400/20 border border-amber-300/40 text-amber-100">
                                Klik "Buka Menu" untuk langsung mencoba
                            </span>
                        </div>
                    </div>

                    <!-- Search Box -->
                    <div class="w-full lg:w-80 relative flex-shrink-0">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Cari menu, langkah, atau fitur..."
                            class="w-full pl-10 pr-4 py-3 rounded-xl bg-white/95 text-slate-800 placeholder-slate-400 text-xs sm:text-sm font-medium border border-white/60 focus:outline-none focus:ring-2 focus:ring-amber-300 shadow-md"
                        />
                        <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- ── 2. Role Switcher (Jika memiliki > 1 role) ────────────── -->
            <div v-if="visibleGuides.length > 1" class="flex flex-wrap gap-2 p-1.5 bg-slate-100 dark:bg-slate-800/80 rounded-2xl border border-slate-200 dark:border-slate-700">
                <button
                    v-for="tab in visibleGuides"
                    :key="tab.id"
                    type="button"
                    @click="activeTab = tab.id"
                    class="flex-1 min-w-[130px] flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all duration-200 cursor-pointer"
                    :class="activeTab === tab.id
                        ? 'bg-white dark:bg-slate-900 text-emerald-700 dark:text-emerald-400 shadow-sm border border-slate-200/70 dark:border-slate-700'
                        : 'text-slate-500 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-700/60 hover:text-slate-800 dark:hover:text-slate-200'"
                >
                    <span>{{ tab.name }}</span>
                </button>
            </div>

            <!-- ── 3. Infografis Alur Kerja Utama ───────────────────────── -->
            <div
                v-if="currentGuide?.workflow?.length && !searchQuery"
                class="relative overflow-hidden bg-white dark:bg-slate-900 rounded-3xl p-5 sm:p-7 border border-slate-200 dark:border-slate-800 shadow-xs"
            >
                <!-- Header Infografis -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-6">
                    <div>
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            Infografis Alur Kerja • {{ currentGuide.name }}
                        </span>
                        <h2 class="text-base sm:text-lg font-black text-slate-900 dark:text-white mt-0.5">
                            Langkah Praktis dari Awal hingga Selesai
                        </h2>
                    </div>
                    <span class="self-start sm:self-auto px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-[11px] font-bold text-emerald-700 dark:text-emerald-300">
                        {{ currentGuide.workflow.length }} Tahap Utama
                    </span>
                </div>

                <!-- Desktop Horizontal Infographic Flow (lg+) -->
                <div class="hidden lg:block relative">
                    <!-- Connector Line Behind Nodes -->
                    <div class="absolute top-7 left-12 right-12 h-1 rounded-full bg-gradient-to-r from-emerald-400 via-teal-400 to-amber-400 opacity-35 pointer-events-none"></div>

                    <div
                        class="grid gap-4 relative z-10"
                        :style="{ gridTemplateColumns: `repeat(${currentGuide.workflow.length}, minmax(0, 1fr))` }"
                    >
                        <div
                            v-for="(wf, idx) in currentGuide.workflow"
                            :key="wf.step"
                            class="group relative flex flex-col items-center text-center"
                        >
                            <!-- Directional Arrow between nodes -->
                            <div
                                v-if="idx < currentGuide.workflow.length - 1"
                                class="absolute top-4 -right-3.5 z-20 w-5 h-5 rounded-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-400 flex items-center justify-center shadow-2xs"
                            >
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>

                            <!-- Circular Infographic Node -->
                            <div
                                class="w-14 h-14 rounded-2xl ring-8 flex items-center justify-center shadow-md transition-transform duration-200 group-hover:-translate-y-1 group-hover:scale-105"
                                :class="getStepTheme(idx).node"
                            >
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="workflowStepIcons[idx % workflowStepIcons.length]" />
                                </svg>
                            </div>

                            <!-- Infographic Card -->
                            <div
                                class="mt-4 w-full flex-1 bg-slate-50/90 dark:bg-slate-800/60 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-700/70 transition-all duration-200 group-hover:-translate-y-0.5 group-hover:bg-white dark:group-hover:bg-slate-800 group-hover:shadow-sm overflow-hidden relative"
                                :class="getStepTheme(idx).border"
                            >
                                <div class="absolute top-0 inset-x-0 h-1" :class="getStepTheme(idx).bar"></div>
                                <span
                                    class="inline-block px-2 py-0.5 rounded-full text-[10px] font-black tracking-wider uppercase mb-2"
                                    :class="getStepTheme(idx).badge"
                                >
                                    Tahap {{ wf.step }}
                                </span>
                                <p class="font-black text-sm text-slate-900 dark:text-white leading-snug">{{ wf.title }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">{{ wf.desc }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile & Tablet Vertical Infographic Timeline (< lg) -->
                <div class="lg:hidden relative pl-4 space-y-4">
                    <div class="absolute left-7 top-4 bottom-4 w-0.5 bg-gradient-to-b from-emerald-400 via-teal-400 to-amber-400 opacity-50 pointer-events-none"></div>

                    <div
                        v-for="(wf, idx) in currentGuide.workflow"
                        :key="wf.step"
                        class="relative flex items-start gap-4"
                    >
                        <div
                            class="relative z-10 w-10 h-10 rounded-xl ring-4 flex items-center justify-center flex-shrink-0 shadow-sm mt-1"
                            :class="getStepTheme(idx).node"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="workflowStepIcons[idx % workflowStepIcons.length]" />
                            </svg>
                        </div>

                        <div
                            class="flex-1 bg-slate-50 dark:bg-slate-800/60 rounded-2xl p-3.5 border border-slate-200/80 dark:border-slate-700/70 relative overflow-hidden"
                        >
                            <div class="absolute left-0 inset-y-0 w-1" :class="getStepTheme(idx).bar"></div>
                            <div class="flex items-center justify-between gap-2 mb-1 pl-1.5">
                                <p class="font-black text-xs sm:text-sm text-slate-900 dark:text-white">{{ wf.title }}</p>
                                <span
                                    class="px-2 py-0.5 rounded-full text-[10px] font-black flex-shrink-0"
                                    :class="getStepTheme(idx).badge"
                                >
                                    {{ wf.step }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 pl-1.5">{{ wf.desc }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── 4. Dokumentasi 2-Kolom (Desktop TOC) & Accordion (Mobile) ── -->
            <div
                v-if="currentGuide?.sections?.length"
                class="lg:grid lg:grid-cols-12 lg:gap-6 lg:items-start"
            >
                <!-- Desktop Sticky Vertical TOC (Opsi 3) -->
                <aside
                    v-if="currentGuide.sections.length > 1"
                    class="hidden lg:block lg:col-span-4 xl:col-span-3 sticky top-4 bg-white dark:bg-slate-900 rounded-3xl p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-2"
                >
                    <p class="px-3 pt-1 pb-2 text-[11px] font-black uppercase tracking-wider text-slate-400 border-b border-slate-100 dark:border-slate-800">
                        Daftar Isi Panduan
                    </p>
                    <nav class="space-y-1">
                        <button
                            v-for="(sec, idx) in currentGuide.sections"
                            :key="sec.group"
                            type="button"
                            @click="scrollToSection(idx)"
                            class="w-full flex items-center justify-between gap-2 px-3 py-2.5 rounded-xl text-left text-xs font-bold transition-all cursor-pointer"
                            :class="activeSectionIndex === idx
                                ? 'bg-emerald-600 text-white shadow-xs'
                                : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-emerald-700 dark:hover:text-emerald-400'"
                        >
                            <span class="flex items-center gap-2.5 min-w-0">
                                <span
                                    class="w-5 h-5 rounded-full text-[10px] font-black flex items-center justify-center flex-shrink-0"
                                    :class="activeSectionIndex === idx
                                        ? 'bg-white/20 text-white'
                                        : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'"
                                >
                                    {{ idx + 1 }}
                                </span>
                                <span class="truncate">{{ sec.group }}</span>
                            </span>
                            <span
                                class="text-[10px] px-1.5 py-0.5 rounded-full flex-shrink-0"
                                :class="activeSectionIndex === idx
                                    ? 'bg-white/20 text-white'
                                    : 'bg-slate-100 dark:bg-slate-800 text-slate-400'"
                            >
                                {{ sec.items.length }}
                            </span>
                        </button>
                    </nav>
                </aside>

                <!-- Menu Detail Sections (Mobile Accordion Opsi 2 + Desktop Full View) -->
                <div
                    class="space-y-5"
                    :class="currentGuide.sections.length > 1 ? 'lg:col-span-8 xl:col-span-9' : 'lg:col-span-12'"
                >
                    <section
                        v-for="(section, sIdx) in currentGuide.sections"
                        :key="section.group"
                        :id="`guide-section-${sIdx}`"
                        :data-section-idx="sIdx"
                        class="guide-reveal bg-white dark:bg-slate-900 rounded-3xl p-4 sm:p-6 border border-slate-200 dark:border-slate-800 shadow-xs scroll-mt-6"
                    >
                        <!-- Chapter Header (Interactive Accordion on Mobile, Static Header on Desktop) -->
                        <button
                            type="button"
                            @click="toggleMobileSection(sIdx)"
                            class="w-full flex items-center justify-between gap-3 text-left lg:cursor-default cursor-pointer"
                            :class="isSectionOpen(sIdx) ? 'pb-4 border-b border-slate-100 dark:border-slate-800' : 'lg:pb-4 lg:border-b lg:border-slate-100 lg:dark:border-slate-800'"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="w-9 h-9 rounded-2xl bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 flex items-center justify-center text-sm font-black flex-shrink-0">
                                    0{{ sIdx + 1 }}
                                </span>
                                <div class="min-w-0">
                                    <h2 class="text-base sm:text-lg font-black text-slate-900 dark:text-white truncate">{{ section.group }}</h2>
                                    <p class="text-xs text-slate-400">{{ section.items.length }} menu tersedia di kelompok ini</p>
                                </div>
                            </div>
                            <svg
                                class="w-5 h-5 text-slate-400 flex-shrink-0 transition-transform duration-200 lg:hidden"
                                :class="{ 'rotate-180 text-emerald-600': isSectionOpen(sIdx) }"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Cards Grid (Always visible on Desktop, collapsible on Mobile) -->
                        <div
                            class="grid-cols-1 md:grid-cols-2 gap-4 pt-4"
                            :class="isSectionOpen(sIdx) ? 'grid' : 'hidden lg:grid'"
                        >
                            <article
                                v-for="item in section.items"
                                :key="item.menu"
                                class="flex flex-col justify-between bg-slate-50/80 dark:bg-slate-800/50 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-700/70 hover:border-emerald-300 dark:hover:border-emerald-700 hover:bg-white dark:hover:bg-slate-800 transition-all duration-200 space-y-4"
                            >
                                <div class="space-y-3">
                                    <!-- Title + Sidebar Path -->
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <h3 class="font-black text-sm sm:text-base text-slate-900 dark:text-white">{{ item.menu }}</h3>
                                        <span class="text-[10px] font-semibold px-2.5 py-1 rounded-full bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                            {{ item.path }}
                                        </span>
                                    </div>

                                    <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                        {{ item.detail }}
                                    </p>

                                    <!-- Step-by-Step List -->
                                    <div v-if="item.steps?.length" class="space-y-1.5 pt-1">
                                        <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Langkah Penggunaan:</p>
                                        <ol class="space-y-1.5">
                                            <li
                                                v-for="(stepText, idx) in item.steps"
                                                :key="idx"
                                                class="flex items-start gap-2 text-xs text-slate-600 dark:text-slate-300"
                                            >
                                                <span class="w-4 h-4 rounded-full bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 font-bold text-[10px] flex items-center justify-center flex-shrink-0 mt-0.5">
                                                    {{ idx + 1 }}
                                                </span>
                                                <span>{{ stepText }}</span>
                                            </li>
                                        </ol>
                                    </div>

                                    <!-- Optional Tip Callout -->
                                    <div v-if="item.tip" class="flex items-start gap-2.5 p-3 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-800/60 text-amber-900 dark:text-amber-200 text-xs">
                                        <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>{{ item.tip }}</span>
                                    </div>
                                </div>

                                <!-- Quick Launch Action -->
                                <div v-if="item.route" class="pt-3 border-t border-slate-200/70 dark:border-slate-700/60 flex justify-end">
                                    <Link
                                        :href="route(item.route)"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-colors shadow-xs"
                                    >
                                        <span>Buka Menu</span>
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                        </svg>
                                    </Link>
                                </div>
                            </article>
                        </div>
                    </section>
                </div>
            </div>

            <!-- Empty Search State -->
            <div v-else class="bg-white dark:bg-slate-900 rounded-3xl p-12 text-center border border-slate-200 dark:border-slate-800">
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">Tidak ada panduan menu yang cocok dengan "{{ searchQuery }}".</p>
                <button
                    type="button"
                    @click="searchQuery = ''"
                    class="mt-3 px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer"
                >
                    Tampilkan Semua Panduan
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.guide-reveal {
    opacity: 0;
    transform: translateY(16px);
    transition: opacity 0.45s cubic-bezier(0.16, 1, 0.3, 1), transform 0.45s cubic-bezier(0.16, 1, 0.3, 1);
}
.guide-reveal.is-revealed {
    opacity: 1;
    transform: translateY(0);
}
@media (prefers-reduced-motion: reduce) {
    .guide-reveal {
        opacity: 1;
        transform: none;
        transition: none;
    }
}
</style>
