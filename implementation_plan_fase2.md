# Rencana Implementasi: Fase 2 (Gamifikasi & Analitik Personal)

Fase 2 akan berfokus pada peningkatan **keterlibatan (engagement)** dan **motivasi** pegawai agar lebih antusias dalam mengisi laporan ibadah mereka sehari-hari.

## User Review Required
> [!IMPORTANT]
> **Push Notifications (PWA) Membutuhkan HTTPS.** Agar notifikasi _push_ (notifikasi langsung ke layar HP) dapat berfungsi, browser mewajibkan aplikasi berjalan di atas protokol aman (HTTPS). Karena saat ini kita mengembangkan di environment lokal HTTP (`laragon`), fitur push notification mungkin baru bisa dites secara sempurna ketika aplikasi sudah **dideploy ke server production** atau menggunakan *tunnel* seperti Ngrok. 
> 
> *Apakah Anda ingin saya menyiapkan struktur Push Notification-nya sekarang meskipun baru bisa dites nanti saat live, atau kita tunda fitur ini ke Fase 3 dan fokus pada Gamifikasi saja dulu?*

## Proposed Changes

### 1. Sistem Gamifikasi (Streaks & Badges)

Fitur ini akan memberikan _reward_ psikologis dengan melacak rentetan ibadah beruntun tanpa putus (_streaks_) dan memberikan lencana (_badges_) sebagai pencapaian.

#### [NEW] Database Migrations
- `add_streaks_to_users_table.php`: Menambahkan kolom `current_streak` dan `longest_streak` ke tabel `users`.
- `create_badges_table.php`: Tabel master untuk mendefinisikan lencana (nama lencana, ikon, kriteria/syarat).
- `create_user_badges_table.php`: Tabel pivot yang mencatat lencana apa saja yang sudah diraih oleh seorang pengguna beserta tanggalnya.

#### [MODIFY] `app/Http/Controllers/HabitController.php`
- Memodifikasi method `store` (saat submit ibadah harian).
- **Logika Streak:** Jika skor harian mencapai 100 poin, `current_streak` bertambah 1. Jika kurang dari 100 poin atau bolong, `current_streak` di-reset ke 0.
- **Logika Badge:** Mengecek secara otomatis apakah `current_streak` atau total akumulasi poin pengguna memenuhi syarat untuk membuka *badge* baru (contoh: Badge "Istiqomah 7 Hari").

#### [MODIFY] `resources/js/Pages/Dashboard.vue` & `app/Http/Controllers/DashboardController.php`
- Menampilkan indikator **Api (Streak 🔥)** di dashboard user.
- Menambahkan tab atau seksi **"Koleksi Lencana Saya"** agar pengguna bisa memamerkan/melihat lencana yang berhasil mereka kumpulkan.

---

### 2. Dashboard Analitik Personal (Evaluasi Otomatis)

Meningkatkan kualitas _feedback_ yang didapatkan oleh pengguna di dashboard mereka.

#### [MODIFY] `app/Http/Controllers/DashboardController.php`
- Menambahkan kalkulasi algoritma untuk mencari **"Habit Terlemah"** atau habit yang paling sering memiliki skor 0 di bulan berjalan.

#### [MODIFY] `resources/js/Pages/Dashboard.vue`
- Menambahkan **Insight Card** yang secara otomatis memberikan saran konstruktif. 
- Contoh teks otomatis: *"Performa ibadah Dhuha Anda bulan ini sangat baik, namun Anda sering melewatkan Dzikir Petang (Kosong 5 kali). Mari perbaiki besok!"*

---

## Verification Plan

### Automated/Manual Verification
1. Mengisi form habit dengan nilai sempurna (100) beberapa hari berturut-turut untuk memastikan *streak* (🔥) bertambah.
2. Memastikan pengisian habit di bawah target akan me-reset streak kembali ke angka 0.
3. Mengecek database dan UI apakah *Badge/Lencana* otomatis terbuka saat kriteria streak terpenuhi.
4. Mengecek Insight Card di Dashboard User apakah memberikan saran evaluasi habit yang tepat (sesuai data yang paling sering kosong).
