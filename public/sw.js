// ============================================================
//  Service Worker — Habit SDAM PWA
//  Strategi: Cache-first (aset), Network-first (halaman)
// ============================================================

const CACHE_VERSION   = 'v2';
const STATIC_CACHE    = `habit-static-${CACHE_VERSION}`;
const DYNAMIC_CACHE   = `habit-dynamic-${CACHE_VERSION}`;
const OFFLINE_URL     = '/offline.html';

// Aset statis yang di-precache saat install
const PRECACHE_ASSETS = [
    '/offline.html',
    '/logo.png',
    '/manifest.json',
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
        e.respondWith(networkFirstWithOfflineFallback(request));
        return;
    }

    // Lainnya → Network-first, simpan ke dynamic cache
    e.respondWith(networkFirst(request));
});

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
async function networkFirst(request) {
    try {
        const response = await fetch(request);
        if (response && response.status === 200) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch (err) {
        const cached = await caches.match(request);
        return cached || new Response('Tidak tersedia offline.', { status: 503 });
    }
}

// ─── Helper: Network-first + Offline Fallback Page ───────────
async function networkFirstWithOfflineFallback(request) {
    try {
        const response = await fetch(request);
        if (response && response.status === 200) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, response.clone());
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

    let data = {};
    if (e.data) {
        try { data = e.data.json(); } catch (err) { data = { title: 'Pengingat Habit' }; }
    }

    const title = data.title || 'Pengingat Habit';
    const options = {
        body:   data.body  || 'Jangan lupa isi form ibadah Anda hari ini! 📿',
        icon:   '/img/gh.png',
        badge:  '/img/gh.png',
        vibrate: [200, 100, 200],
        tag:    'habit-reminder',
        renotify: true,
        data: { url: data.url || '/habit/form' },
        actions: [
            { action: 'open', title: '📝 Isi Sekarang' },
            { action: 'dismiss', title: 'Nanti Saja' },
        ],
    };

    e.waitUntil(self.registration.showNotification(title, options));
});

// ─── Notification Click ──────────────────────────────────────
self.addEventListener('notificationclick', function (e) {
    e.notification.close();

    const targetUrl = e.action === 'open'
        ? (e.notification.data?.url || '/habit/form')
        : null;

    if (!targetUrl) return;

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
