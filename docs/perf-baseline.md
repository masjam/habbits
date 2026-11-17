# Performance Baseline Report

Generated at: 2026-11-17T08:24:32+07:00
Environment: Laravel 13, PHP 8.3, SQLite In-Memory Test Suite (50 realistic users, 30 days logs & attendances)

## 1. Database Queries & Response Times (Baseline)

| Halaman / Route | User Context | Jumlah Query | Waktu Respons (ms) |
| :--- | :--- | :---: | :---: |
| Dashboard Pegawai | Pegawai | 22 | 96.02 ms |
| Dashboard Admin | Admin | 11 | 10.79 ms |
| Form Habit | Pegawai | 6 | 6.22 ms |
| Presensi Pegawai | Pegawai | 13 | 12.76 ms |
| Presensi Admin (Report) | Admin | 710 | 135.90 ms |
| Laporan Admin (Leaderboard) | Admin | 11 | 10.91 ms |
| Notifikasi / Pesan | Pegawai | 5 | 3.94 ms |

### Detail Baseline Query Findings
- **Shared Props & Global Middleware**: Setiap request menjalankan query `Setting::where('key', 'maintenance_mode')`, `Setting::pluck('value', 'key')`, roles via spatie, dan unread notifications count.
- **Dashboard Pegawai**: Mengalami pembacaan `Setting` berulang (`feature_presensi` x2, `announcement_text`, `monthly_target_score`), `User::getEffectiveWorkSchedule` (menjalankan ~4-6 query setting & schedule), pemuatan `Habit::all()`, perulangan PHP untuk habit analytics, dan agregasi terpisah.
- **Dashboard Admin**: Menjalankan query berulang `whereDoesntHave('roles')` dan agregasi bulanan dengan fungsi SQL raw `MONTH()`.
- **Admin Laporan / Attendance**: Banyak query filter dan relasi belum ter-cache / query berulang.

## 2. Frontend Build Artifacts (Baseline)

| Asset / Chunk | Ukuran Asli | Gzip |
| :--- | :---: | :---: |
| `public/build/assets/app-IikcVV5G.js` | 412.92 kB | 142.84 kB |
| `public/build/assets/app-DuQf7rDE.css` | 276.45 kB | 35.28 kB |
| `public/build/assets/Index-Db1k3fZJ.js` | 193.28 kB | 65.52 kB |
| `public/build/assets/dist-BR7cVwhK.js` | 193.15 kB | 67.06 kB |
| `public/build/assets/leaflet-BGuttSqU.js` | 148.60 kB | 43.33 kB |
| `public/build/assets/Index-BNM9lr_j.js` | 103.20 kB | 19.41 kB |
| `public/build/assets/Hr-CAGV7Pb5.js` | 98.98 kB | 19.93 kB |
| `public/build/assets/Index-CF--KyIM.js` | 96.56 kB | 22.03 kB |
| `public/build/assets/Index-OnfHz3dc.js` | 91.63 kB | 20.94 kB |
| `public/build/assets/AuthenticatedLayout-CyOLlCgL.js` | 79.26 kB | 19.25 kB |
| `public/build/assets/FormHabit-kETI7Xmt.js` | 67.96 kB | 14.85 kB |
| `public/build/assets/useDzikirSound-CbU30NVc.js` | 52.13 kB | 10.49 kB |
| `public/build/assets/Dashboard-CGTsSrjV.js` | 41.27 kB | 10.47 kB |
| `public/build/assets/IslamicWidget-BkLJuK-6.js` | 12.49 kB | 4.22 kB |

## 3. Render-blocking Resource Baseline
- `resources/views/app.blade.php`: Google Fonts stylesheet (`family=Amiri...&family=Amiri+Quran&family=Scheherazade+New`) dimuat di `<head>` untuk seluruh halaman aplikasi.
