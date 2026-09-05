# Habbits App

Habbits adalah aplikasi manajemen dan pelacakan kebiasaan (habit tracker) yang dibangun menggunakan Laravel dan Vue.js. Aplikasi ini dirancang dengan antarmuka yang dinamis menggunakan Inertia.js dan dilengkapi dengan berbagai fitur analitik dan ekspor data.

## 🚀 Tech Stack

- **Backend:** [Laravel 13](https://laravel.com) (PHP 8.3)
- **Frontend:** [Vue 3](https://vuejs.org/) & [Inertia.js](https://inertiajs.com/)
- **Styling:** [Tailwind CSS v4](https://tailwindcss.com/)
- **Role & Permission:** [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)
- **Data Export:** [Laravel Excel](https://laravel-excel.com/) & [DOMPDF](https://github.com/barryvdh/laravel-dompdf)
- **Charts:** [Chart.js](https://www.chartjs.org/) & Vue-chartjs
- **Error Tracking:** [Sentry](https://sentry.io/)

## ✨ Fitur Utama

- Autentikasi dan Manajemen Pengguna (Role & Permissions)
- Visualisasi data statistik (Chart)
- Editor teks kaya (Rich Text Editor - TinyMCE)
- Drag and drop antarmuka pengguna
- Ekspor data laporan ke dalam format PDF dan Excel
- Pemantauan error secara real-time via Sentry

## 🛠️ Prasyarat (Requirements)

Sebelum memulai, pastikan Anda telah menginstal aplikasi berikut di sistem Anda:
- PHP >= 8.3
- Composer
- Node.js & NPM
- Database (SQLite/MySQL/PostgreSQL)

<!-- ## 💻 Panduan Instalasi (Instalasi Lokal)

Ikuti langkah-langkah di bawah ini untuk menjalankan aplikasi di lingkungan pengembangan lokal Anda:

1. **Clone repositori ini:**
   ```bash
   git clone https://github.com/masjam/habbits
   cd habit
   ```

2. **Instal dependensi PHP (Composer):**
   ```bash
   composer install
   ```

3. **Salin file konfigurasi lingkungan dan hasilkan application key:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Konfigurasi Database:**
   Buka file `.env` dan sesuaikan kredensial database Anda (misalnya menggunakan SQLite atau MySQL).

5. **Jalankan Migrasi Database (dan Seeder jika ada):**
   ```bash
   php artisan migrate --seed
   ```

6. **Instal dependensi Node.js (Frontend):**
   ```bash
   npm install
   ```

7. **Jalankan Development Server:**
   Buka dua terminal terpisah dan jalankan perintah berikut:
   
   Terminal 1 (Menjalankan server PHP & Queue):
   ```bash
   php artisan serve
   ```
   
   Terminal 2 (Menjalankan Vite untuk frontend):
   ```bash
   npm run dev
   ```
   Atau cukup jalankan perintah tunggal ini (jika didukung):
   ```bash
   composer dev
   ```

8. **Akses Aplikasi:**
   Buka browser Anda dan kunjungi: `http://localhost:8000` -->

## 🔒 Konfigurasi Sentry (Opsional)

Untuk mengaktifkan tracking error, pastikan Anda menambahkan DSN Sentry di file `.env` Anda:
```env
SENTRY_LARAVEL_DSN="your-sentry-dsn-here"
VITE_SENTRY_DSN_PUBLIC="your-sentry-dsn-public-here"
```

<!-- ## 📜 Lisensi

Aplikasi ini adalah perangkat lunak open-source di bawah lisensi [MIT license](https://opensource.org/licenses/MIT). -->
