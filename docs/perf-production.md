# Panduan Checklist Optimasi Produksi (Production Deployment Checklist)

Dokumen ini berisi panduan konfigurasi server, runtime PHP, web server Nginx, dan Laravel environment untuk performa maksimal pada lingkungan produksi (`/var/www/habit`).

---

## 1. Environment Laravel (`.env` Produksi)

Pastikan konfigurasi environment di server produksi disesuaikan dengan nilai-nilai berikut:

```dotenv
# Nonaktifkan debug mode untuk menghentikan pengumpulan data stack trace yang berat
APP_DEBUG=false
APP_ENV=production

# Tingkatkan level logging agar I/O disk minim, hanya mencatat warning dan error
LOG_LEVEL=warning

# Gunakan Redis untuk session, cache, dan queue (atau 'file'/'database' jika Redis belum diinstal)
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Jika menggunakan Redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

> [!IMPORTANT]
> Mengubah `CACHE_STORE` dan `SESSION_DRIVER` ke `redis` menghilangkan beban disk I/O dan query database pada tabel `sessions` dan `cache` di setiap request.

---

## 2. Optimasi PHP 8.3 & OPcache (`/etc/php/8.3/fpm/php.ini` atau `/etc/php/8.3/fpm/conf.d/10-opcache.ini`)

Pastikan modul OPcache aktif dan dikonfigurasi untuk produksi:

```ini
[opcache]
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.revalidate_freq=0
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1
```

> [!NOTE]
> Dengan `opcache.validate_timestamps=0`, OPcache tidak akan melakukan stat check ke disk pada setiap request. Kode yang dicache di memori akan terus digunakan hingga PHP-FPM di-reload. Oleh karena itu, pastikan proses deployment menyertakan reload PHP-FPM jika opsi ini digunakan (misal `systemctl reload php8.3-fpm`). Jika ingin revalidasi otomatis tanpa reload, gunakan `opcache.validate_timestamps=1` dan `opcache.revalidate_freq=60`.

---

## 3. Tuning PHP-FPM (`/etc/php/8.3/fpm/pool.d/www.conf`)

Sesuaikan pool worker PHP-FPM berdasarkan kapasitas RAM server (contoh untuk server 4GB - 8GB RAM):

```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 1000
```

---

## 4. Konfigurasi Nginx (Kompresi Gzip/Brotli & Caching Aset Statis)

Tambahkan blok konfigurasi berikut pada server block Nginx aplikasi:

### A. Gzip Compression (`/etc/nginx/nginx.conf` atau server block)
```nginx
gzip on;
gzip_vary on;
gzip_proxied any;
gzip_comp_level 6;
gzip_types
    text/plain
    text/css
    text/xml
    application/json
    application/javascript
    application/x-javascript
    application/xml
    application/xml+rss
    text/javascript
    image/svg+xml;
```

### B. Long-lived Cache Header untuk Aset Vite (`/build/*`)
File aset build Vite memiliki hashing konten unik pada namanya (contoh: `app-BycrMpm-.js`), sehingga aman diberi cache seumur hidup (`immutable`):

```nginx
# Cache seumur hidup untuk bundel aset Vite
location /build/ {
    expires 1y;
    add_header Cache-Control "public, max-age=31536000, immutable";
    access_log off;
    try_files $uri =404;
}

# Cache untuk font dan gambar publik
location ~* \.(?:ico|css|js|gif|jpe?g|png|woff2?|eot|ttf|svg|webp)$ {
    expires 30d;
    add_header Cache-Control "public, max-age=2592000";
    access_log off;
    try_files $uri =404;
}

# Service Worker TIDAK boleh di-cache oleh browser/CDN agar update langsung diterima
location = /sw.js {
    expires -1;
    add_header Cache-Control "no-store, no-cache, must-revalidate, proxy-revalidate, max-age=0";
}
```

---

## 5. Pipeline Deployment GitHub Actions (`deploy.yml`)

Workflow GitHub Actions pada `.github/workflows/deploy.yml` telah dilengkapi perintah cache bawaan Laravel:

```yaml
      - name: Optimizations & Migrations
        run: |
          cd /var/www/habit
          php artisan migrate --force
          php artisan optimize:clear
          php artisan config:cache
          php artisan route:cache
          php artisan view:cache
          php artisan event:cache
          php artisan storage:link
          php artisan queue:restart
```

Perintah di atas memastikan:
1. `config:cache` & `route:cache`: Menyatukan seluruh file route dan konfigurasi ke single PHP array.
2. `event:cache`: Menghilangkan penemuan event listener dinamis saat runtime.
3. `queue:restart`: Memberi tahu worker queue yang sedang berjalan untuk memuat ulang kode terbaru secara graceful.
