# Panduan Operasional Keamanan & Rotasi Kredensial

Dokumen ini adalah panduan standar operasional untuk tim IT / DevOps dalam melakukan audit berkala, manajemen rahasia (secret management), dan rotasi kredensial sistem pada aplikasi Habit SDAM.

---

## 1. Hasil Audit File Storage & Log Server

### 1.1. Pemeriksaan File Mencurigakan di Direktori Storage
- **Perintah Audit**:
  ```bash
  find storage/app/public -type f ! \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' -o -iname '*.webp' -o -iname '*.pdf' \)
  ```
- **Hasil Pemeriksaan**:
  - File non-gambar/PDF yang ditemukan: `storage/app/public/fonts/Amiri-Regular.ttf` (aset font resmi untuk generator dokumen PDF bahasa Arab).
  - **Status**: **BERSIH**. Tidak ditemukan webshell, skrip PHP/PHTML, atau file eksekusi berbahaya lainnya di direktori penyimpanan publik.

### 1.2. Pemeriksaan Log Error Terhadap Jejak Exploit Upload
- **Perintah Audit**:
  ```bash
  grep -iE "storage/.*\.php" storage/logs/laravel.log
  ```
- **Hasil Pemeriksaan**:
  - Semua entri yang merujuk pada file `.php` di direktori `storage/` berasal dari cache template internal Laravel Blade (`storage/framework/views/*.php`).
  - Tidak ditemukan jejak percobaan pemanggilan atau eksekusi file `.php` berbahaya di dalam direktori penyimpanan publik `/storage/`.
  - **Status**: **AMAN**.

---

## 2. Panduan Rotasi Kredensial & Rahasia (Credential Rotation)

### 2.1. Rotasi Token Kiosk Presensi RFID
Token Kiosk digunakan untuk otentikasi mesin pembaca RFID / tablet pemindai kartu presensi.
1. **Langkah Pembuatan Token Baru**:
   - Masuk sebagai **Superadmin** ke panel dashboard web.
   - Buka menu **Pengaturan (Settings)** -> Tab **Presensi**.
   - Klik tombol **Generate Token Acak (32 karakter)** pada bagian Token Kiosk.
   - Simpan pengaturan.
2. **Pembaruan di Mesin Kiosk**:
   - Salin URL Kiosk baru yang menyertakan token:
     `https://<domain-sekolah>/kiosk?token=<TOKEN_BARU_32_KARAKTER>`
   - Buka browser / fullscreen kiosk app pada tablet RFID dan navigasikan ke URL baru tersebut.
   - Uji coba pemindaian kartu RFID untuk memastikan respons 200 OK diterima.

---

### 2.2. Rotasi Token Mesin / Skrip Sinkronisasi Fingerprint
Token Fingerprint digunakan oleh mesin ADMS / skrip penjadwal sinkronisasi presensi sidik jari (via header `X-Device-Token`).
1. **Buat Token Acak Baru**:
   ```bash
   php -r "echo bin2hex(random_bytes(32));"
   ```
2. **Perbarui Nilai Setting di Database**:
   - Update setting `fingerprint_api_token` via panel admin atau database:
     ```sql
     UPDATE settings SET value = '<TOKEN_BARU_HEX>' WHERE key = 'fingerprint_api_token';
     ```
   - Hapus cache aplikasi:
     ```bash
     php artisan cache:clear
     ```
3. **Perbarui Skrip Mesin Fingerprint**:
   - Buka konfigurasi skrip sinkronisasi mesin (misal cron job / daemon Python).
   - Perbarui header HTTP:
     ```http
     X-Device-Token: <TOKEN_BARU_HEX>
     ```
4. **Masa Transisi**:
   - Jika `FINGERPRINT_STRICT_AUTH=false` pada `.env`, request dari mesin yang belum diperbarui akan tetap diproses dengan peringatan `Log::warning`.
   - Setelah memastikan skrip mesin telah diperbarui, aktifkan penegakan ketat di `.env`:
     ```dotenv
     FINGERPRINT_STRICT_AUTH=true
     ```

---

### 2.3. Rotasi Kunci Aplikasi (`APP_KEY`)
`APP_KEY` digunakan Laravel untuk mengenkripsi cookie sesi, payload queue terenkripsi, dan nilai database tertentu.
> [!CAUTION]
> Mengganti `APP_KEY` akan membatalkan seluruh sesi pengguna yang sedang aktif (semua user harus login ulang) dan tidak dapat mendekripsi data yang dienkripsi menggunakan key lama.

1. **Jadwalkan Waktu Maintenance**:
   - Aktifkan mode pemeliharaan agar tidak ada transaksi atau session yang sedang berjalan:
     ```bash
     php artisan down --message="Sedang dilakukan pemeliharaan sistem keamanan berkala."
     ```
2. **Cadangkan Key Lama**:
   - Salin nilai `APP_KEY` lama dari file `.env` ke tempat yang aman sebagai arsip.
3. **Generate Kunci Baru**:
   ```bash
   php artisan key:generate
   ```
4. **Bersihkan Cache Konfigurasi & Sesi**:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan view:clear
   ```
5. **Nonaktifkan Mode Maintenance**:
   ```bash
   php artisan up
   ```

---

### 2.4. Rotasi Kunci WebPush (VAPID Keys)
VAPID Keys digunakan untuk menandatangani pesan notifikasi browser (Web Push API).
1. **Generate Key VAPID Baru**:
   ```bash
   php artisan webpush:vapid
   ```
   Perintah ini akan secara otomatis memperbarui `VAPID_PUBLIC_KEY` dan `VAPID_PRIVATE_KEY` pada file `.env`.
2. **Reload Konfigurasi**:
   ```bash
   php artisan config:clear
   ```
3. **Dampak pada Pengguna**:
   - Browser pengguna akan mendeteksi perubahan public key saat membuka aplikasi dan secara otomatis memperbarui pendaftaran endpoint via `PushSubscriptionController::store`.

---

### 2.5. Rotasi Kredensial Database & Redis
1. Buat user baru atau ubah password user pada database MySQL / Redis server.
2. Perbarui nilai `DB_PASSWORD` atau `REDIS_PASSWORD` pada `.env`.
3. Jalankan `php artisan config:cache` untuk memuat nilai baru ke runtime produksi.
4. Restart antrian background worker:
   ```bash
   php artisan queue:restart
   ```

---

## 3. Prosedur Tanggap Insiden Keamanan (Incident Response)

Jika terdeteksi indikasi peretasan atau kebocoran kredensial:
1. **Segera Karantina Sistem**:
   ```bash
   php artisan down --secret="tim-it-darurat"
   ```
2. **Putuskan Semua Sesi API Aktif**:
   ```sql
   UPDATE users SET api_token = NULL;
   ```
3. **Rotasi Kunci Segera**: Lakukan rotasi `APP_KEY`, token Kiosk, dan token Fingerprint mengikuti panduan di atas.
4. **Periksa Integritas File Repository**:
   ```bash
   git status
   git verify-pack
   ```
5. **Periksa Riwayat Akses**: Analisis tabel `login_logs` dan file `/var/log/nginx/access.log` untuk IP mencurigakan.
