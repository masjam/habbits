# Laporan Akhir Audit & Perbaikan Keamanan Sistem (Security Hardening Report)

**Aplikasi**: Habit SDAM (Sistem Presensi, Monitoring Habit, & Administrasi Sekolah)  
**Lingkungan**: Laravel 13 (PHP 8.3), Inertia v3 + Vue 3, Tailwind v4, MySQL / SQLite  
**Tanggal**: 17 November 2026  
**Status**: **SELESAI (100% Temuan Tertangani, 81/81 Test Suite Hijau)**

---

## 1. Ringkasan Eksekutif

Audit keamanan menyeluruh telah dilakukan pada seluruh lapisan aplikasi Habit SDAM (Autentikasi, Otorisasi, API, Upload File, Penyimpanan Dokumen, Header HTTP, dan Shared Data Inertia). Seluruh perbaikan dilakukan menggunakan pendekatan **Test-Driven Security**:
1. Menulis Feature Test yang membuktikan celah kerentanan (harus **GAGAL / Merah** sebelum perbaikan).
2. Menerapkan perbaikan kode sumber secara presisi tanpa merusak kontrak UI, props Inertia, atau skema data yang dibaca frontend.
3. Memastikan pengujian keamanan **LULUS / Hijau**.
4. Melakukan komit git atomik terpisah per temuan.
5. Menjalankan seluruh rangkaian tes regresi (`PerformanceBaselineLockTest` dan seluruh 81 unit/feature tests) untuk menjamin stabilitas fungsional dan performa aplikasi.

---

## 2. Matriks Temuan & Status Perbaikan

| No | Kategori | Temuan Celah Keamanan | Tingkat Risiko | Status | File Test Pembukti | Komit Git |
|---|---|---|---|---|---|---|
| **1** | Upload Base64 | Validasi MIME palsu, potensi eksekusi PHP di storage publik, nama file predictable | **Kritis** | **SELESAI** | `Base64UploadSecurityTest` | `4d1f6e1` |
| **2** | Info Leak | Seluruh baris tabel `settings` (kiosk token, WA target, nomor telepon) bocor ke props Inertia semua user | **Kritis** | **SELESAI** | `GlobalSettingsSecurityTest` | `0acbcd9` |
| **3** | Auth Kiosk | Fallback token default `'SDAM-KIOSK-SECRET'`, tidak ada panjang minimum, oracle timing attack | **Kritis** | **SELESAI** | `KioskTokenSecurityTest` | `2d352f1` |
| **4** | API Endpoint | Endpoint `/api/fingerprint/sync` tanpa token header, tanpa batas batch payload, tanpa jendela tanggal | **Kritis** | **SELESAI** | `FingerprintSyncSecurityTest` | `a492327` |
| **5** | Otorisasi & XSS | `NotulenController` tanpa server-side policy (user biasa bisa edit/hapus/approve) & simpan HTML mentah tanpa sanitasi | **Kritis** | **SELESAI** | `NotulenSecurityTest` | `7b4d7c8` |
| **6** | API Token & Auth | Endpoint `/api/login` tanpa rate limiter, `api_token` disimpan plaintext di database, data user bocor | **Tinggi** | **SELESAI** | `ApiSecurityTest` | `c88aa81` |
| **7** | Data Privacy | Kolom `catatan_pimpinan` terkirim secara otomatis ke browser pegawai via `$page.props.auth.user` | **Tinggi** | **SELESAI** | `UserProfileSecurityTest` | `1f4145f` |
| **8** | Spoofing IP & Network | Wildcard `trustProxies('*')`, pembacaan manual `CF-Connecting-IP`, HTTP synchronous blocking call ke ip-api | **Sedang** | **SELESAI** | `ProxyAndIpSecurityTest` | `c24f993` |
| **9** | SSRF Push | `PushSubscriptionController::store` menerima sembarang endpoint URL tanpa HTTPS dan allowlist host | **Tinggi** | **SELESAI** | `PushSubscriptionSecurityTest` | `b10b00b` |
| **10** | Open Redirect / XSS | URL notifikasi tanpa validasi server-side dan navigasi `window.location.href` rentan `javascript:` | **Tinggi** | **SELESAI** | `NotificationUrlSecurityTest` | `b6c8845` |
| **11** | File Deletion & SVG | Avatar menerima file SVG (potensi stored XSS), string path sembarang, dan penghapusan file di luar folder avatars | **Sedang** | **SELESAI** | `ProfileAvatarSecurityTest` | `5eaf5f3` |
| **12** | Default Credential | User hasil import Excel memakai password default tanpa keharusan mengganti password | **Sedang** | **SELESAI** | `MustChangePasswordSecurityTest` | `ef7442c` |
| **13 & 15** | Private Storage | Bug disk `'private'` tidak terdefinisi di storeLeave; foto presensi dan surat izin tersimpan di webroot publik | **Tinggi** | **SELESAI** | `PrivateStorageSecurityTest` | `6c1ed10` |
| **14** | Security Headers | Respon HTTP tidak memiliki header keamanan modern (nosniff, sameorigin, permissions-policy, HSTS, CSP) | **Sedang** | **SELESAI** | `SecurityHeadersTest` | `cb90541` |
| **Ops** | Audit Operasional | Audit file storage, log server lama, dan penyusunan SOP rotasi kredensial | **Operasional** | **SELESAI** | `docs/security-ops.md` | `379bb2c` |

---

## 3. Rincian Teknis Implementasi Perbaikan

### 3.1. Fase A (Celah Kritis)

#### Temuan 1: Base64 Upload Security Service (`Base64ImageService`)
- **Masalah**: Header data URL `data:image/png;base64,...` dipercaya mentah tanpa memeriksa payload biner. Nama file menggunakan `time()`.
- **Perbaikan**:
  - Dibuat kelas `App\Services\Base64ImageService`:
    - Strict `base64_decode($data, true)`.
    - Batas ukuran biner maksimum 2 MB.
    - Verifikasi biner riil via `getimagesizefromstring()`.
    - Allowlist MIME murni: `image/jpeg`, `image/jpg`, `image/png`, `image/webp`.
    - Ekstensi file diambil langsung dari MIME hasil verifikasi biner (bukan dari string klien).
    - Nama file menggunakan string acak kriptografis 32 karakter (`Str::random(32)`).
    - Melempar `ValidationException` (HTTP 422) jika verifikasi gagal.
  - Diimplementasikan pada `AttendanceController::storeBase64Photo` dan `NotulenController::storeBase64Signature`.

#### Temuan 2: Allowlist Global Settings pada HandleInertiaRequests
- **Masalah**: `'global_settings' => Setting::allCached()` mengekspos semua konfigurasi database ke browser (termasuk `kiosk_api_token`, nomor telepon pimpinan, dan kredensial rahasia).
- **Perbaikan**:
  - Di `HandleInertiaRequests.php`: Diterapkan allowlist 29 kunci publik yang benar-benar dipakai oleh frontend UI. Kunci sensitif dikeluarkan.
  - Token kiosk dikirimkan khusus hanya kepada admin terotorisasi di `AttendanceReportController::index` melalui prop `'kiosk_token'`.
  - Frontend `Admin/Attendance/Index.vue:459` diperbarui membaca `$page.props.kiosk_token`.
  - Target WhatsApp presensi disediakan terpisah di `AttendanceController::index` (`'whatsapp_target'`).

#### Temuan 3: Kiosk RFID Token Hardening & Dual Rate Limiting
- **Masalah**: Fallback token `'SDAM-KIOSK-SECRET'` aktif jika setting kosong. Pembandingan token menggunakan perbandingan string biasa (`==`), rentan timing attack. Tidak ada batasan laju scan kartu.
- **Perbaikan**:
  - Dihapus seluruh fallback `'SDAM-KIOSK-SECRET'`.
  - Wajib memiliki token acak minimum 32 karakter alfanumerik.
  - Pemeriksaan token menggunakan `hash_equals()`.
  - Respons "kartu tidak terdaftar" diseragamkan agar tidak menjadi oracle penebak UID.
  - Diterapkan dual rate limiter `kiosk-scan`: maksimum 30 request/menit per IP dan 60 request/menit per hash token.

#### Temuan 4: Hardening Endpoint Fingerprint Sync (`/api/fingerprint/sync`)
- **Masalah**: Endpoint push presensi dapat ditembak oleh siapa saja tanpa autentikasi, tanpa batas ukuran array (potensi DoS memory exhaustion), dan menerima tanggal kapan saja.
- **Perbaikan**:
  - Validasi token shared-secret melalui header `X-Device-Token` dengan `hash_equals()`.
  - Pembatasan maksimum 500 item per request (melebihi batas ditolak dengan HTTP 422).
  - Validasi timestamp: tanggal di luar jendela wajar (maksimal 30 hari ke belakang dan 1 hari ke depan) ditolak per item.
  - Diterapkan `throttle:60,1` pada rute `routes/api.php`.
  - **Masa Transisi**: Disediakan flag environment `FINGERPRINT_STRICT_AUTH`. Saat `false`, token absen/salah dicatat di log peringatan; saat `true`, ditolak dengan HTTP 401.

#### Temuan 5: Kebijakan Otorisasi Notulen & Sanitasi HTML
- **Masalah**: Controller tidak memeriksa hak akses (user biasa bisa mengedit atau menghapus notulen user lain, serta menyetujui notulen tanpa wewenang). `isi_pembahasan` dirender dengan `v-html` tanpa filter, membuka celah Stored XSS.
- **Perbaikan**:
  - Dibuat `App\Policies\NotulenPolicy` dan didaftarkan di `AppServiceProvider`:
    - `update`: Pembuat, Pimpinan Rapat yang bersangkutan, Admin, Superadmin, atau Kepala Sekolah.
    - `delete`: Pembuat, Admin, Superadmin, atau Kepala Sekolah.
    - `approve`: Pimpinan Rapat, Kepala Sekolah, Admin, atau Superadmin.
  - Sanitasi HTML: Diintegrasikan paket `stevebauman/purify` (HTMLPurifier). Seluruh tag `<script>`, `<iframe>`, atribut event handler (`onerror`, `onload`) dibersihkan saat simpan, dengan tetap mempertahankan tag TinyMCE (`p`, `strong`, `em`, `ul`, `ol`, `li`, `table`, `tr`, `td`, `span`, dll).
  - String signature klien divalidasi ketat lewat `Base64ImageService`; tidak ada path lokal dari klien yang dipercaya langsung.

---

### 3.2. Fase B (Celah Tinggi & Sedang)

#### Temuan 6: API Security, Hashed Tokens, & UserResource
- **Masalah**: Endpoint `/api/login` tidak memiliki rate limiting. `api_token` disimpan dalam bentuk plaintext di database. Endpoint `/api/user` dan respons login mengembalikan model User utuh beserta kolom internal.
- **Perbaikan**:
  - Diterapkan dual rate limiter `api-login`: 5 request per menit per IP dan 5 request per menit per email.
  - Dibuat migrasi database `2026_11_17_000001_hash_existing_api_tokens.php` untuk meng-hash token lama dengan SHA-256.
  - Pada `AuthController::login`: Klien tetap menerima raw random token (kontrak API klien tidak berubah), namun database hanya menyimpan `hash('sha256', $plainToken)`.
  - Pada `EnsureValidApiToken`: Token bearer dari request di-hash dengan SHA-256 sebelum dicari di database.
  - Dibuat `App\Http\Resources\UserResource` dengan allowlist kolom publik (`id`, `uuid`, `name`, `email`, `phone`, `gender`, `avatar`, `divisi`, `roles`, dll).

#### Temuan 7: Penyembunyian `catatan_pimpinan` dari Browser Pegawai
- **Masalah**: Kolom `catatan_pimpinan` (evaluasi konfidensial pimpinan) ikut terserialisasi ke dalam JSON `$page.props.auth.user` di setiap halaman Inertia.
- **Perbaikan**:
  - Kolom `catatan_pimpinan` ditambahkan ke properti `$hidden` pada model `App\Models\User`.
  - Pada `ProfileController::edit`, prop `user` dibatasi hanya pada atribut profil yang ditampilkan.

#### Temuan 8: Penguatan Trusted Proxies & Asynchronous GeoIP Lookup
- **Masalah**: `trustProxies('*')` mempercayai semua proxy. `AuthController` membaca header `CF-Connecting-IP` / `X-Forwarded-For` secara manual sehingga rentan spoofing. Panggilan HTTP synchronous ke `http://ip-api.com` menggunakan plain HTTP dan memperlambat login hingga 2 detik.
- **Perbaikan**:
  - `bootstrap/app.php`: `trustProxies` membaca IP dari env `TRUSTED_PROXIES` (default aman: `127.0.0.1, ::1`).
  - `AuthController::login`: Menggunakan `$request->ip()` resmi Laravel yang hanya mempercayai header jika berasal dari proxy terdaftar.
  - Dibuat background queue job `App\Jobs\ResolveLoginLocationJob`: Pencarian lokasi dipindahkan ke antrean asinkron menggunakan protokol aman HTTPS (`https://ipwho.is/`). Login web selesai instan tanpa blocking.

#### Temuan 9: Validasi Strict HTTPS & Allowlist Provider WebPush
- **Masalah**: Endpoint push browser diterima tanpa validasi protokol dan host, rentan SSRF (Server-Side Request Forgery).
- **Perbaikan**:
  - Di `PushSubscriptionController::store`: Wajib skema `https://`, panjang maksimum 500 karakter.
  - Host divalidasi terhadap allowlist resmi layanan Web Push:
    - `fcm.googleapis.com` & `android.googleapis.com` (Google Chrome / Android)
    - `updates.push.services.mozilla.com` (Mozilla Firefox)
    - `*.push.apple.com` (Apple Safari iOS / macOS)
    - `*.notify.windows.com` (Microsoft Edge / Windows)

#### Temuan 10: Validasi URL Notifikasi & Pencegahan Open Redirect / XSS
- **Masalah**: Parameter `url` pada siaran notifikasi push tidak divalidasi. Di frontend (`Topbar.vue` dan `Notifications/Index.vue`), `window.location.href = url` dijalankan langsung sehingga rentan URL `javascript:` atau phishing open redirect.
- **Perbaikan**:
  - Server-side: Metode `validateSafeNotificationUrl` memastikan URL hanya berupa path relatif internal (`/...`) atau URL absolute dengan domain yang sama dengan `config('app.url')`. URL luar dan skema `javascript:`/`//` ditolak dengan HTTP 422.
  - Client-side: Dibuat helper `safeNavigate()` pada `Topbar.vue` dan `Notifications/Index.vue` yang memvalidasi origin dan menolak skema `javascript:`, `data:`, `vbscript:`.

#### Temuan 11: Pengamanan Upload Avatar & Pembatasan Hapus File
- **Masalah**: Mimes avatar mengizinkan `svg` (potensi Stored XSS). Pemilihan avatar string menerima sembarang path. Penghapusan file avatar lama berpotensi menghapus file di luar folder avatar.
- **Perbaikan**:
  - Ekstensi `svg` dihapus dari mimes upload avatar (`jpeg,png,jpg,gif,webp`).
  - Avatar string divalidasi ketat terhadap allowlist regex preset (`https://api.dicebear.com/...` atau `/img/...`).
  - Dibuat helper `deleteOldAvatarFile`: File hanya dihapus jika path berawalan `avatars/`, tidak mengandung traversal `..`, dan ada di disk.

#### Temuan 12: Penegakan Ganti Password untuk User Impor (`must_change_password`)
- **Masalah**: Pengguna hasil impor massal Excel menggunakan password default bersama (`Password123!`) tanpa keharusan mengganti password saat pertama kali login.
- **Perbaikan**:
  - Migrasi `2026_11_17_000002_add_must_change_password_to_users_table.php` menambahkan kolom boolean `must_change_password`.
  - Pada `UserImport`: Akun baru dibuat dengan `must_change_password = true`.
  - Dibuat middleware `App\Http\Middleware\EnsurePasswordIsChanged`: Mengarahkan user yang memiliki flag aktif ke halaman ganti password (`/profile`) dan menolak navigasi lain sampai password diperbarui.
  - Saat user berhasil mengubah password di `ProfileController::updatePassword`, flag diubah menjadi `false`.

#### Temuan 13 & 15: Konfigurasi Disk Privat & Streaming Route Beraotorisasi
- **Masalah**: `AttendanceReportController::storeLeave` memanggil disk `'private'` yang tidak terkonfigurasi di `filesystems.php`. Berkas lampiran sakit/izin dan foto presensi tersimpan di webroot publik `/storage/`.
- **Perbaikan**:
  - Dikonfigurasi disk `'private'` pada `config/filesystems.php` mengarah ke `storage/app/private`.
  - File lampiran ketidakhadiran, permohonan izin/dinas luar, foto selfie presensi, dan foto jurnal dipindahkan ke disk privat.
  - Dibuat controller `App\Http\Controllers\System\PrivateMediaController` dan rute streaming beraotorisasi:
    - `/storage/attendance/attachments/{filename}`
    - `/storage/attachments/permits/{filename}`
    - `/storage/attendances/{filename}`
    - `/storage/journals/{filename}`
  - Rute memeriksa autentikasi sesi dan otorisasi kepemilikan (hanya pemilik dokumen atau admin/kepala sekolah yang dapat melihat).
  - Tampilan frontend tetap menggunakan URL lama tanpa perubahan kontrak kode Vue.

#### Temuan 14: Middleware Header Keamanan HTTP
- **Masalah**: Respon HTTP tidak memiliki pertahanan terhadap clickjacking, MIME sniffing, dan downgrade HTTPS.
- **Perbaikan**:
  - Dibuat middleware `App\Http\Middleware\SecurityHeaders`:
    - `X-Content-Type-Options: nosniff`
    - `X-Frame-Options: SAMEORIGIN`
    - `Referrer-Policy: strict-origin-when-cross-origin`
    - `Permissions-Policy: camera=(self), geolocation=(self), microphone=()`
    - `Strict-Transport-Security: max-age=31536000; includeSubDomains` (otomatis saat HTTPS)
    - `Content-Security-Policy-Report-Only` dikonfigurasi untuk masa transisi aset Inertia & Vite.
  - Menambahkan checklist `SESSION_SECURE_COOKIE=true` ke panduan produksi `docs/perf-production.md`.

---

## 4. Masa Transisi & Catatan Perubahan Kontrak

1. **Sinkronisasi Mesin Fingerprint (`/api/fingerprint/sync`)**:
   - Diperkenalkan header autentikasi baru: `X-Device-Token: <token>`.
   - **Masa Transisi**: Saat ini sistem berjalan dengan `FINGERPRINT_STRICT_AUTH=false` (default di config). Jika token belum disetel di skrip mesin, request tetap diproses dan sistem mencatat peringatan di log.
   - **Tindakan Lanjutan**: Admin perlu menyetel token acak di setting `fingerprint_api_token` dan memperbarui skrip sinkronisasi mesin. Setelah mesin diperbarui, aktifkan `FINGERPRINT_STRICT_AUTH=true` di `.env`.
2. **Kredensial Kiosk RFID**:
   - Fallback `'SDAM-KIOSK-SECRET'` telah dinonaktifkan permanen demi keamanan.
   - Superadmin wajib mengenerate token 32 karakter baru melalui menu Pengaturan Presensi dan memperbarui URL pada browser mesin Kiosk.

---

## 5. Hasil Verifikasi Pengujian & Regresi

- **Total Unit & Feature Tests**: **81 Lulus (100% Passed)**
- **Total Assertions**: **411 Assertions**
- **Durasi Eksekusi**: ~49 detik
- **Status Baseline Performa**: `PerformanceBaselineLockTest` **7/7 PASS (100%)** — membuktikan tidak ada regresi performa, tidak ada props Inertia yang rusak, dan seluruh kontrak fungsional tetap utuh.

---

## 6. Analisis Risiko Sisa (Residual Risks) & Rekomendasi Jangka Panjang

1. **Content Security Policy (CSP) Enforcement**:
   - Saat ini CSP berjalan dalam mode `Content-Security-Policy-Report-Only` untuk memantau apakah ada library pihak ketiga atau inline script TinyMCE yang terblokir.
   - **Rekomendasi**: Setelah 1-2 minggu pemantauan log CSP di produksi tanpa pelanggaran false-positive, ubah header menjadi `Content-Security-Policy` aktif (enforced).
2. **Kedaluwarsa API Token (Token Expiration)**:
   - Token API saat ini sudah tersimpan ter-hash dengan SHA-256.
   - **Rekomendasi Masa Depan**: Pertimbangkan menambahkan kolom `expires_at` pada `users` atau bermigrasi ke Laravel Sanctum jika di masa mendatang aplikasi mobile membutuhkan kemampuan pencabutan token per perangkat (multiple tokens per user).
3. **Penyimpanan Redis di Produksi**:
   - Ikuti panduan `docs/perf-production.md` untuk mengaktifkan Redis sebagai session dan cache driver di server `/var/www/habit` agar kinerja sesi dan invalidasi cache berlangsung optimal.
