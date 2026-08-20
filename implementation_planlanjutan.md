# Master Implementation Plan: Enterprise Optimizations

Karena fitur yang disetujui mencakup hampir 12 modul baru yang tersebar di sisi _Frontend_, _Backend_, _Database_, hingga konfigurasi PWA, pengerjaannya akan dibagi menjadi **3 Fase Utama** untuk memastikan stabilitas aplikasi tetap terjaga pada setiap langkahnya.

## 📝 Fitur Kontrol (Super Admin Toggles)
Sesuai permintaan Anda, seluruh fitur berprioritas menengah dan rendah akan dilengkapi dengan **Toggle / Saklar On-Off** di halaman Pengaturan Super Admin.

---

## 🚀 FASE 1: Admin Power-Ups & Landing Page Revamp
_(Fase ini akan dikerjakan segera setelah rencana ini disetujui)_

### 1. Export Laporan (Excel) [Admin] - 🔥 Prioritas Tinggi
- **[NEW]** `app/Exports/LaporanExport.php`: Membuat kelas *exporter* menggunakan `maatwebsite/excel`.
- **[MODIFY]** `app/Http/Controllers/Admin/LaporanController.php`: Menambahkan method `export()` yang menarik data laporan berdasarkan filter bulan & tahun, lalu mengunduhnya dalam format `.xlsx`.
- **[MODIFY]** `resources/js/Pages/Admin/Laporan/Index.vue`: Menambahkan tombol "Unduh Excel" di sebelah *filter* bulan.

### 2. Dashboard Statistik Global [Admin] - 🔥 Prioritas Tinggi
- **[MODIFY]** `app/Http/Controllers/Admin/DashboardController.php`: Mengumpulkan agregasi data (rata-rata capaian, tren 6 bulan terakhir).
- **[MODIFY]** `resources/js/Pages/Admin/Dashboard.vue`: Menambahkan grafik komprehensif menggunakan `vue-chartjs`.

### 3. Integrasi API Jadwal Sholat Dinamis & Live Stats [Landing] - 🔥 Tinggi & ⭐ Menengah
- **[MODIFY]** `app/Http/Controllers/LandingController.php` (atau sejenisnya): Menarik total rakaat/habit yang telah diisi oleh seluruh user bulan ini untuk *Social Proof*.
- **[MODIFY]** `resources/js/Pages/Welcome.vue`: 
  - Memanggil API Kemenag / Aladhan secara dinamis berdasarkan lokasi/zona waktu pengunjung.
  - Menampilkan _counter_ statistik *Live* ("Total xxxx Ibadah Tercatat Bulan Ini").
  - Menambahkan slider mockup aplikasi.

### 4. Pengaturan Sistem (Super Admin Toggles)
- **[MODIFY]** `app/Models/Setting.php` & Controller: Menyimpan status *on/off* fitur menengah/rendah.
- **[MODIFY]** `resources/js/Pages/Admin/Settings/Index.vue` (atau di halaman dashboard admin): UI saklar untuk fitur-fitur lanjutan (Dark Mode, Auto-Warning, Gamification).

---

## 🎯 FASE 2: User Engagement (Gamification & Analytics)
_(Dikerjakan setelah Fase 1 stabil)_

- **Gamification (Badges & Streaks)**: Menambahkan tabel `badges` dan logika algoritma di `User` model untuk mendeteksi *streak* ibadah berturut-turut.
- **Dashboard Analitik Personal (User)**: Merombak `Dashboard.vue` sisi *user* untuk menampilkan grafik tren pribadi dan habit terkuat/terlemah.

---

## ⚙️ FASE 3: Advanced PWA & Automation
_(Dikerjakan di tahap akhir)_

- **Offline Mode (Background Sync)**: Memodifikasi `public/sw.js` dengan IndexedDB agar user bisa absen ibadah tanpa internet.
- **Dark Mode Toggle**: Mengaktifkan konfigurasi `darkMode` di Tailwind dan UI tombol bulan/matahari.
- **Auto-Warning System**: Membuat *Cron Job* (Laravel Task Scheduling) untuk mengecek pengguna dengan persentase rendah secara berkala.
- **Custom Habit by Division**: Memodifikasi skema tabel `habits` agar bisa *di-assign* ke pengguna spesifik.

---

## User Review Required
> [!IMPORTANT]
> Mengingat cakupan pengerjaan yang sangat luas, saya merekomendasikan kita memulai eksekusi dari **FASE 1 (Admin Power-Ups & Landing Page)** terlebih dahulu.
> 
> Jika Anda setuju dengan peta jalan (_roadmap_) ini, silakan klik **Proceed**, dan saya akan langsung mulai menulis kode untuk keseluruhan fitur di **FASE 1**! Setelah Fase 1 selesai, kita akan lanjut ke Fase berikutnya.
