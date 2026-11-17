# Laporan Komprehensif Optimasi Performa Aplikasi Habit SDAM

**Tanggal Pelaksanaan**: 17 November 2026  
**Stack**: Laravel 13 (PHP 8.3), Inertia v3 + Vue 3, Tailwind v4, Spatie Permission, MySQL Produksi  
**Prinsip Utama**: Zero Contract Breaches — seluruh props Inertia, API responses, nama route, teks UI, schema database, dan logika kalkulasi skor tetap 100% identik dan terkunci oleh unit/feature tests.

---

## 1. Tabel Ringkasan Pengukuran (Sebelum vs Sesudah)

Pengujian diukur pada lingkungan benchmark terisolasi dengan data seed realistis (50 pegawai, 30 hari log ibadah dan presensi, ratusan record):

### A. Metrik Database Query & Response Time

| Halaman / Fitur | User Role | Query Sebelum | Query Sesudah (Cold) | Query Sesudah (Warm/Cached) | Waktu Sebelum (ms) | Waktu Sesudah (ms) | Penurunan Query (%) |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **Dashboard Pegawai** | Pegawai | 22 | 14 | **0** | 96.02 ms | **4.49 ms** | **-100%** (Warm) / -36% (Cold) |
| **Dashboard Admin** | Admin | 11 | 7 | **0** | 10.79 ms | **3.66 ms** | **-100%** (Warm) / -36% (Cold) |
| **Form Habit** | Pegawai | 6 | 1 | **1** | 6.22 ms | **5.90 ms** | **-83.3%** |
| **Presensi Pegawai** | Pegawai | 13 | 4 | **3** | 12.76 ms | **8.55 ms** | **-76.9%** |
| **Presensi Admin (Report)** | Admin | 710 | 4 | **4** | 135.90 ms | **42.09 ms** | **-99.4%** |
| **Laporan Admin (Leaderboard)**| Admin | 11 | 5 | **4** | 10.91 ms | **5.99 ms** | **-63.6%** |
| **Notifikasi / Pesan** | Pegawai | 5 | 1 | **1** | 3.94 ms | **3.31 ms** | **-80.0%** |

> *Catatan*: Penurunan query 99.4% pada halaman **Presensi Admin** disebabkan oleh eliminasi N+1 query loop saat memanggil jadwal efektif pegawai (`dutySchedules`).

---

### B. Metrik Frontend Assets & Bundle Size (`npm run build`)

| Aset / Resource | Sebelum | Sesudah | Penghematan / Status |
| :--- | :---: | :---: | :--- |
| **Render-blocking Google Fonts** (`app.blade.php`) | 3 Font Families dimuat di `<head>` global | **0 font di `<head>` global** | Dihapus dari global head. Font Arab dimuat secara asinkron/scoped hanya pada halaman Dzikir & Quran. |
| **Total Global CSS** (`app.css`) | 276.45 kB (gzip: 35.28 kB) | 289.56 kB (gzip: 36.77 kB) | Menggunakan Tailwind v4 `@custom-variant dark`. |
| **IslamicWidget Mount Count (Mobile)** | **2x per request** (desktop + mobile modal) | **1x (on-demand)** | Dioptimasi dengan `v-if` berbasis `matchMedia`, widget desktop tidak dimount pada viewport kecil. |
| **Jadwal Sholat & Hadis API Calls** | Dipanggil di setiap mount | **Singleton & Cached** | Disimpan di `localStorage` per hari, reverse-geocode dan Aladhan berjalan paralel (`Promise.all`). |
| **Chart.js Chunk** | Terpisah | `dist-ujiJ1PP_.js` (193 kB) | Hanya dimuat saat mengakses halaman Dashboard / Laporan Detail. |
| **Leaflet Chunk** | Terpisah | `leaflet-BGuttSqU.js` (148 kB) | Hanya dimuat saat membuka halaman Presensi / Kiosk. |
| **TinyMCE** | On-demand CDN | Loaded via Cloudflare CDN | Tidak membebani initial bundle aplikasi. |

---

## 2. Rincian Perubahan per Fase

### Fase 0: Baseline & Pengaman
- **Komitmen**: `4fb6815`
- Dibuat suite pengunci kontrak props Inertia di `tests/Feature/PerformanceBaselineLockTest.php`:
  - `test_shared_inertia_props_contract`
  - `test_user_dashboard_props_contract`
  - `test_admin_dashboard_props_contract`
  - `test_form_habit_props_contract`
  - `test_notifications_index_props_contract`
  - `test_attendance_index_props_contract`
  - `test_admin_laporan_index_props_contract`
- Dibuat `tests/Feature/BenchmarkTest.php` untuk mengukur metrik baseline.
- Dihasilkan `docs/perf-baseline.md`.

### Fase 1: Settings & Shared Props
- **Komitmen**: `55b702f`
- Dibuat titik akses ter-cache pada `Setting.php`:
  - `Setting::allCached()` (Cache `global_app_settings` forever).
  - `Setting::get($key, $default)` & `Setting::getBoolean($key)`.
  - Otomatis di-invalidate saat model `Setting` di-save atau di-delete via Eloquent model events.
- Mengganti seluruh query berulang `Setting::where` & `Setting::pluck` di middleware (`CheckMaintenanceMode`), `HandleInertiaRequests`, `User::getEffectiveWorkSchedule`, dan controllers.
- Relasi `roles` pada `HandleInertiaRequests` dioptimasi dengan `$user->loadMissing('roles')`.
- Notifikasi unread count dan latest list di-cache per-user (TTL 60 detik) dengan auto-invalidasi saat ada notifikasi masuk (`NotificationSent` event) atau saat dibaca (`NotificationController`).
- Menghapus dead code cache `habits_active_*` dan mengimplementasikan `Habit::allActiveCached()` dan `Habit::allCachedById()` ter-cache dengan invalidasi saat habit diubah/disimpan.

### Fase 2: Database & Query Structure
- **Komitmen**: `7ff0017`
- Dibuat migration baru `database/migrations/2026_11_17_090000_add_performance_indexes_to_habit_logs_and_notifications.php`:
  - Index compound `habit_logs`: `(tanggal, user_id)` dan `(user_id, tanggal)`.
  - Index compound `notifications`: `(notifiable_type, notifiable_id, read_at)`.
  - Terbukti lewat MySQL `EXPLAIN`: Query range `tanggal` pada tabel `habit_logs` turun dari full table scan (`type: ALL`, 745 baris, temporary table) menjadi index range scan (`type: range`, 1 baris, filtered 100%).
- Dibuat helper `User::getNonAdminUserIds()` ter-cache (TTL 120s) dengan auto-invalidasi di `User.php`.
- Mengganti subquery berat `whereHas('user', whereDoesntHave('roles'))` pada `habit_logs` menjadi `whereIn('user_id', User::getNonAdminUserIds())`.
- Mengganti query kolom tanggal `whereDate`, `whereYear`, `whereMonth` menjadi direct equality `where('tanggal', 'YYYY-MM-DD')` atau range `whereBetween` agar index B-tree terpakai optimal.

### Fase 3: Query Controller & Dashboard Caching
- **Komitmen**: `e044b1c`
- **Dashboard Pegawai**:
  - Skor hari ini, bulan ini, dan bulan dipilih dihitung sekaligus dari agregasi harian `$dailyRaw` tanpa query terpisah.
  - Mengeliminasi loop `Habit::all()` dan `Habit::find()`, digantikan oleh `Habit::allCachedById()`.
  - Memilih kolom spesifik pada `monthlyLogs` (`id`, `user_id`, `habit_id`, `tanggal`, `nilai_input`, `details`).
  - Caching dashboard per user (`Cache::remember` TTL 60s) dengan atomic versioning (`user_dash_ver_{userId}`).
- **Dashboard Admin**:
  - Caching agregat admin (`Cache::remember` TTL 300s) dengan atomic versioning (`admin_dash_ver`).
  - Invalidasi instan saat `HabitLog` atau `Attendance` disimpan/diupdate/dihapus.
- **N+1 Fixes**:
  - Eager-loading relasi `dutySchedules` pada `AttendanceReportController` dan `ProcessAttendanceExport`, menurunkan query dari 710 query menjadi 4 query.
  - Caching `SpecialSchedule::getActiveCached()`.
  - Caching `Division::getTargetsMap()` dan `Division::allCached()`.
  - Optimasi `NotificationController::markAllAsRead()` menjadi bulk SQL update tanpa memuat seluruh model ke memori.

### Fase 4: Frontend & PWA Optimization
- **Komitmen**: `89a9ad2`
- Menghapus Google Fonts pemblokir render dari `<head>` di `app.blade.php`. Font Arab dipindahkan ke `@import` di `Dzikir/Index.vue` dan `QuranHadis/Index.vue`.
- `useIslamicData.js`:
  - Diubah menjadi reactive singleton module scope (state dibagi bersama antar komponen, mencegah duplikasi request).
  - Cache jadwal sholat + koordinat di `localStorage` per hari (`islamic_prayer_cache_{dateStr}`).
  - Reverse-geocode dan Aladhan dijalankan secara paralel (`Promise.all`).
  - GPS Geolocation opsi: `enableHighAccuracy: false` dan `timeout: 5000`.
- Menghindari double-mounting `IslamicWidget` di layar HP menggunakan `v-if` berbasis `window.matchMedia` di `Dashboard.vue` dan `Admin/Dashboard.vue`.
- `public/sw.js`:
  - Cache version dinaikkan ke `v4`.
  - Menolak caching response yang membawa header `X-Inertia` atau rute data pribadi/otentikasi.
  - Implementasi batas ukuran FIFO pada `DYNAMIC_CACHE` (maksimal 50 item).

### Fase 5: Produksi & Deploy
- **Komitmen**: `14e6d87`
- Dibuat `docs/perf-production.md` yang merinci checklist konfigurasi OPcache (`validate_timestamps=0`), PHP-FPM pool tuning, driver Redis untuk cache/session/queue, serta kompresi Gzip dan header cache panjang immutable untuk `/build/*`.
- Memperbarui `.github/workflows/deploy.yml` dengan penambahan langkah `php artisan event:cache` dan `php artisan queue:restart`.

---

## 3. Risiko yang Tersisa (Residual Risks) & Mitigasi

1. **Sinkronisasi Cache Multi-Server**:
   - *Risiko*: Jika di kemudian hari sistem di-scale ke multi-server tanpa Redis (masih menggunakan driver `file` atau `database`), cache versioning lokal pada memory cache store mungkin tidak tersinkronisasi.
   - *Mitigasi*: Telah disediakan panduan konfigurasi `CACHE_STORE=redis` di `docs/perf-production.md`.
2. **Perubahan Jadwal Sholat Dinamis saat Pindah Kota di Hari yang Sama**:
   - *Risiko*: Jadwal sholat di-cache di `localStorage` per hari (`islamic_prayer_cache_DD-MM-YYYY`). Jika pengguna bepergian jauh antar zona waktu dalam 1 hari yang sama, jadwal sholat baru ter-update keesokan harinya kecuali cache browser dibersihkan.
   - *Mitigasi*: Pengguna tetap dapat melihat waktu sholat lokal atau me-refresh cache browser jika diperlukan.

---

## 4. Hal yang Sengaja TIDAK Diubah & Alasannya

1. **Enkripsi Data Log Menstruasi / Haid**:
   - Data haid pada `MenstruationLog` tetap dibaca secara selektif dengan `whereNull('waktu_selesai')` dan tidak di-cache di shared cache demi mematuhi privasi data pribadi (data rahasia per-user tidak boleh bocor ke cache publik).
2. **Kalkulasi Skor di Client vs Server**:
   - Seluruh logika kalkulasi skor, badge tier, dan target streak tetap diproses persis sama di backend untuk menjamin konsistensi penilaian tanpa memindahkan beban kalkulasi yang berisiko mengubah nilai historis.
3. **Queue Job untuk Laporan & Presensi Export**:
   - Export berukuran besar (`ProcessAttendanceExport` & `ProcessLaporanExport`) tetap dipertahankan pada background queue Laravel agar HTTP worker tidak mengalami timeout atau kehabisan memori.
4. **File .env Produksi**:
   - File `.env` produksi tidak disentuh/diubah langsung di repository; seluruh rekomendasi disediakan dalam bentuk checklist terdokumentasi di `docs/perf-production.md`.
