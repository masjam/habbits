# SAHABAT SDAM (Habbits App)

Aplikasi manajemen habit, presensi pegawai, administrasi sekolah, persuratan, komunikasi internal, dan POS koperasi berbasis web (PWA) dan JSON API. Dibangun dengan Laravel 13, Inertia.js, dan Vue 3.

## ✨ Fitur Utama

- **Pelacakan Habit & Ibadah**: Pengisian form habit harian, voucher reward habit, rekap Qur'an & Hadis, dzikir, arah kiblat, HPT, kajian, serta catatan haid.
- **Presensi & Kepegawaian (HR)**: Check-in/check-out berbasis geolokasi & foto selfie, pengajuan izin/cuti, Kiosk RFID, integrasi push mesin fingerprint (`/api/fingerprint/sync`), jadwal piket/jam kerja, e-filing arsip pegawai, penilaian kinerja (KPI), jurnal harian guru, dan perangkat pembelajaran (administrasi guru + arsip tahun ajaran).
- **Tata Usaha & Persuratan**: Pengelolaan surat masuk, surat keluar (HTML builder + PDF + QR verifikasi publik), disposisi, serta notulen rapat dengan tanda tangan digital dan verifikasi publik.
- **Koperasi Sekolah (POS)**: Kasir POS (tunai, QRIS, saldo kartu RFID, voucher habit, split payment, dan bon), manajemen member & top-up saldo, stok opname, serta laporan laba/rugi dan ekspor Excel.
- **Komunikasi & Sistem**: Chat internal pegawai (direct, grup, dan channel divisi), real-time event via Laravel Reverb, Web Push Notification (VAPID), multi-account switching, audit login log, dan mode maintenance dengan simulasi role.

## 🚀 Tech Stack

- **Backend:** [Laravel 13](https://laravel.com) (PHP ^8.3), [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)
- **Frontend:** [Vue 3](https://vuejs.org/), [Inertia.js](https://inertiajs.com/), [Tailwind CSS v4](https://tailwindcss.com/), [Vite 8](https://vite.dev/)
- **Real-time & Notifikasi:** [Laravel Reverb](https://reverb.laravel.com/), Laravel Echo, Pusher JS, [WebPush](https://github.com/laravel-notification-channels/webpush)
- **Dokumen, Ekspor & Keamanan Konten:** [Laravel Excel](https://laravel-excel.com/), [DOMPDF](https://github.com/barryvdh/laravel-dompdf), [Simple QrCode](https://www.simplesoftware.io/docs/simple-qrcode), [Purify (HTMLPurifier)](https://github.com/stevebauman/purify)
- **UI & Utilitas:** Chart.js, Leaflet, TinyMCE, Signature Pad, VueDraggable, Ziggy
- **Monitoring & Testing:** [Sentry](https://sentry.io/), PHPUnit 12

## 🛠️ Prasyarat

- PHP >= 8.3 (dengan ekstensi standar Laravel seperti `pdo_sqlite`/`pdo_mysql`, `mbstring`, `openssl`, `gd`/`zip`)
- Composer
- Node.js & NPM
- Database (SQLite / MySQL / PostgreSQL)

## 💻 Instalasi Lokal

1. **Clone repositori:**
   ```bash
   git clone https://github.com/masjam/habbits
   cd habit
   ```

2. **Setup cepat via Composer (opsional):**
   ```bash
   composer setup
   ```
   Atau lakukan langkah manual berikut:

   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   php artisan migrate --seed
   npm install
   npm run build
   ```

3. **Konfigurasi Database & Storage:**
   Sesuaikan koneksi database di `.env` (default `.env.example` menggunakan `sqlite` dengan session, cache, dan queue berbasis `database`). Jika menggunakan file publik di `storage/app/public`, jalankan:
   ```bash
   php artisan storage:link
   ```

4. **Jalankan Development Server:**
   ```bash
   composer dev
   ```
   Perintah di atas menjalankan `php artisan serve`, `php artisan queue:listen`, `php artisan pail`, dan `npm run dev` secara bersamaan. Aplikasi dapat diakses di `http://localhost:8000`.

## ⚙️ Konfigurasi Lingkungan Tambahan (Opsional)

- **Sentry Error Tracking:**
  ```env
  SENTRY_LARAVEL_DSN="your-sentry-dsn-here"
  VITE_SENTRY_DSN_PUBLIC="your-sentry-dsn-public-here"
  ```
- **Storage Eksternal Administrasi Guru:**
  ```env
  ADMINISTRASI_GURU_PATH=/mnt/dokumen_guru
  ```
- **Keamanan & Integrasi Lainnya:**
  - `FINGERPRINT_STRICT_AUTH=true` untuk mewajibkan verifikasi `X-Device-Token` pada endpoint `/api/fingerprint/sync`.
  - `TRUSTED_PROXIES` untuk daftar IP reverse proxy tepercaya (default `127.0.0.1,::1`).
  - `REVERB_*` dan `VAPID_*` untuk WebSocket Laravel Reverb dan Web Push Notification.

## 🧪 Pengujian

Jalankan test suite backend menggunakan PHPUnit:
```bash
composer test
```
Atau jalankan suite konsistensi UI secara terpisah:
```bash
php artisan test --testsuite=UI
```

## 📜 Lisensi

Perangkat lunak ini dirilis di bawah lisensi [MIT License](https://opensource.org/licenses/MIT).
