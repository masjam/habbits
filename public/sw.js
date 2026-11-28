// ============================================================
//  Service Worker — Habit SDAM PWA
//  Strategi: Cache-first (aset), Network-first (halaman)
// ============================================================

const CACHE_VERSION   = 'v25';
const STATIC_CACHE    = `habit-static-${CACHE_VERSION}`;
const DYNAMIC_CACHE   = `habit-dynamic-${CACHE_VERSION}`;
const OFFLINE_URL     = '/offline.html';
const MAX_DYNAMIC_ITEMS = 50;

// Aset statis yang di-precache saat install
const PRECACHE_ASSETS = [
    '/offline.html',
    '/favicon.ico',
    '/img/icon-192.png',
    '/img/icon-512.png',
    '/img/gh.png',
    '/logo.png',
    '/manifest.json',
    '/img/vr-nature-bg.webp',
];

// ─── Install ─────────────────────────────────────────────────
self.addEventListener('install', (e) => {
    e.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS);
        }).then(() => self.skipWaiting())
    );
});

// ─── Activate (Bersihkan cache lama) ─────────────────────────
self.addEventListener('activate', (e) => {
    const allowedCaches = [STATIC_CACHE, DYNAMIC_CACHE];
    e.waitUntil(
        caches.keys().then((cacheNames) =>
            Promise.all(
                cacheNames
                    .filter((name) => !allowedCaches.includes(name))
                    .map((name) => caches.delete(name))
            )
        ).then(() => self.clients.claim())
    );
});

// ─── Fetch Strategy ──────────────────────────────────────────
self.addEventListener('fetch', (e) => {
    const { request } = e;
    const url = new URL(request.url);

    // Hanya handle GET dan origin yang sama
    if (request.method !== 'GET' || url.origin !== location.origin) return;

    // Lewatkan request Inertia SPA & API langsung ke browser native tanpa overhead SW
    if (request.headers.get('X-Inertia') || url.pathname.startsWith('/api/')) return;

    // Aset statis (JS, CSS, Font, Gambar) → Cache-first
    if (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/img/') ||
        url.pathname === '/img/gh.png' ||
        url.pathname === '/manifest.json' ||
        url.pathname.match(/\.(woff2?|png|jpg|jpeg|svg|ico|webp)$/)
    ) {
        e.respondWith(cacheFirst(request));
        return;
    }

    // Halaman navigasi → Network-first, fallback ke offline.html
    if (request.mode === 'navigate') {
        e.respondWith(networkFirstWithOfflineFallback(request, url));
        return;
    }

    // Lainnya → Network-first, simpan ke dynamic cache
    e.respondWith(networkFirst(request, url));
});

// ─── Helper: Trim Cache FIFO ─────────────────────────────────
async function trimCache(cacheName, maxItems) {
    try {
        const cache = await caches.open(cacheName);
        const keys = await cache.keys();
        if (keys.length > maxItems) {
            await cache.delete(keys[0]);
            return trimCache(cacheName, maxItems);
        }
    } catch (e) {
        // Abaikan error saat trim cache
    }
}

// ─── Helper: Check Private Data & Inertia ────────────────────
function isPrivateOrInertia(request, url) {
    if (request.headers.get('X-Inertia')) return true;

    const privatePrefixes = [
        '/dashboard',
        '/habits',
        '/presensi',
        '/attendance',
        '/admin',
        '/notifications',
        '/profile',
        '/login-logs',
        '/laporan',
        '/users',
        '/settings',
        '/sanctum',
        '/api/'
    ];

    return privatePrefixes.some(prefix => url.pathname.startsWith(prefix));
}

// ─── Helper: Cache-first ──────────────────────────────────────
async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response && response.status === 200) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch (err) {
        return new Response('Aset tidak tersedia offline.', { status: 503 });
    }
}

// ─── Helper: Network-first ───────────────────────────────────
async function networkFirst(request, url) {
    try {
        const response = await fetch(request);
        if (response && response.status === 200 && !isPrivateOrInertia(request, url)) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, response.clone());
            trimCache(DYNAMIC_CACHE, MAX_DYNAMIC_ITEMS);
        }
        return response;
    } catch (err) {
        const cached = await caches.match(request);
        return cached || new Response('Tidak tersedia offline.', { status: 503 });
    }
}

// ─── Helper: Network-first + Offline Fallback Page ───────────
async function networkFirstWithOfflineFallback(request, url) {
    try {
        const response = await fetch(request);
        if (response && response.status === 200 && !isPrivateOrInertia(request, url)) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, response.clone());
            trimCache(DYNAMIC_CACHE, MAX_DYNAMIC_ITEMS);
        }
        return response;
    } catch (err) {
        const cached = await caches.match(request);
        if (cached) return cached;
        return caches.match(OFFLINE_URL);
    }
}

// ─── Push Notification ───────────────────────────────────────
self.addEventListener('push', function (e) {
    if (!(self.Notification && self.Notification.permission === 'granted')) return;

    let payload = {};
    if (e.data) {
        try {
            payload = e.data.json();
        } catch (err) {
            try {
                payload = { body: e.data.text() };
            } catch (e2) {
                payload = {};
            }
        }
    }

    const title = payload.title || 'Habit SDAM 🔔';
    const options = {
        body: payload.body || 'Pengingat ibadah & aktivitas harian Anda.',
        icon: payload.icon || '/img/gh.png',
        badge: payload.badge || '/img/gh.png',
        vibrate: [200, 100, 200],
        tag: payload.tag || 'habit-notification',
        renotify: true,
        data: payload.data || { url: '/dashboard' },
        actions: payload.actions || [
            { action: 'open', title: '📱 Buka Aplikasi' },
            { action: 'dismiss', title: 'Tutup' },
        ],
    };

    e.waitUntil(self.registration.showNotification(title, options));
});

// ─── Notification Click ──────────────────────────────────────
self.addEventListener('notificationclick', function (e) {
    e.notification.close();

    if (e.action === 'dismiss') return;

    const targetUrl = e.notification.data?.url || '/dashboard';

    e.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (const client of windowClients) {
                if (client.url.includes(location.origin) && 'focus' in client) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }
            if (clients.openWindow) return clients.openWindow(targetUrl);
        })
    );
});

// ─── Background Sync ─────────────────────────────────────────
self.addEventListener('sync', function (e) {
    if (e.tag === 'sync-habit-form') {
        e.waitUntil(syncPendingForms());
    }
});

async function syncPendingForms() {
    try {
        const db = await openIndexedDB();
        const pendingForms = await getAllPending(db);

        for (const item of pendingForms) {
            try {
                const res = await fetch('/habit/form', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': item.csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(item.data),
                });
                if (res.ok) {
                    await deletePending(db, item.id);
                }
            } catch (err) {
                console.warn('[SW] Sync gagal untuk item:', item.id);
            }
        }
    } catch (err) {
        console.warn('[SW] Background sync error:', err);
    }
}

// ─── IndexedDB helpers ───────────────────────────────────────
function openIndexedDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open('HabitOfflineDB', 1);
        req.onupgradeneeded = (e) => {
            e.target.result.createObjectStore('pending-forms', { keyPath: 'id', autoIncrement: true });
        };
        req.onsuccess = (e) => resolve(e.target.result);
        req.onerror   = (e) => reject(e.target.error);
    });
}

function getAllPending(db) {
    return new Promise((resolve, reject) => {
        const tx  = db.transaction('pending-forms', 'readonly');
        const req = tx.objectStore('pending-forms').getAll();
        req.onsuccess = (e) => resolve(e.target.result);
        req.onerror   = (e) => reject(e.target.error);
    });
}

function deletePending(db, id) {
    return new Promise((resolve, reject) => {
        const tx  = db.transaction('pending-forms', 'readwrite');
        const req = tx.objectStore('pending-forms').delete(id);
        req.onsuccess = () => resolve();
        req.onerror   = (e) => reject(e.target.error);
    });
}
