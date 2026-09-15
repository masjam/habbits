// ============================================================
//  usePWA — Composable untuk Service Worker, Install & Web Push
// ============================================================

import { ref, onMounted } from 'vue'

const deferredInstallPrompt = ref(null)
const isInstallable         = ref(false)
const isInstalled           = ref(false)
const isOffline             = ref(!navigator.onLine)
const swRegistration        = ref(null)

const isPushSupported       = ref(false)
const isPushSubscribed      = ref(false)
const pushPermission        = ref('default')
const isSubscribingPush     = ref(false)

/**
 * Konversi base64 VAPID public key ke Uint8Array (standar W3C Push API)
 */
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/')

    const rawData = window.atob(base64)
    const outputArray = new Uint8Array(rawData.length)

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i)
    }
    return outputArray
}

export function usePWA() {
    /**
     * Register Service Worker
     */
    async function registerSW() {
        if (!('serviceWorker' in navigator)) return

        try {
            const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' })
            swRegistration.value = reg
            console.log('[PWA] Service Worker terdaftar:', reg.scope)

            // Cek status push setelah SW siap
            checkPushSubscription()
        } catch (err) {
            console.warn('[PWA] Registrasi SW gagal:', err)
        }
    }

    /**
     * Dengarkan event beforeinstallprompt (Add to Home Screen)
     */
    function listenInstallPrompt() {
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault()
            deferredInstallPrompt.value = e
            isInstallable.value = true
        })

        window.addEventListener('appinstalled', () => {
            isInstallable.value = false
            isInstalled.value   = true
            deferredInstallPrompt.value = null
            console.log('[PWA] Aplikasi berhasil diinstall!')
        })
    }

    /**
     * Dengarkan status koneksi online/offline
     */
    function listenNetworkStatus() {
        window.addEventListener('online',  () => { isOffline.value = false })
        window.addEventListener('offline', () => { isOffline.value = true })
    }

    /**
     * Trigger prompt install (Tambah ke Layar Utama)
     */
    async function promptInstall() {
        if (!deferredInstallPrompt.value) return false
        deferredInstallPrompt.value.prompt()
        const { outcome } = await deferredInstallPrompt.value.userChoice
        deferredInstallPrompt.value = null
        isInstallable.value = false
        return outcome === 'accepted'
    }

    /**
     * Cek status subscription Web Push saat ini
     */
    async function checkPushSubscription() {
        if (typeof window === 'undefined') return

        isPushSupported.value = ('Notification' in window && 'serviceWorker' in navigator && 'PushManager' in window)
        if (!isPushSupported.value) return

        pushPermission.value = Notification.permission

        try {
            const reg = swRegistration.value || await navigator.serviceWorker.ready
            if (reg) {
                const sub = await reg.pushManager.getSubscription()
                isPushSubscribed.value = !!sub
            }
        } catch (err) {
            console.warn('[PWA] Cek push subscription gagal:', err)
        }
    }

    /**
     * Berlangganan / Daftarkan perangkat ke Web Push
     */
    async function subscribeToPush(vapidPublicKey) {
        if (!isPushSupported.value) {
            throw new Error('Browser ini tidak mendukung Push Notification.')
        }

        if (!vapidPublicKey) {
            // Ambil dari server jika tidak disertakan
            try {
                const res = await fetch('/push-subscriptions/vapid-public-key')
                const data = await res.json()
                vapidPublicKey = data.publicKey
            } catch (err) {
                throw new Error('Kunci publik VAPID belum dikonfigurasi di server.')
            }
        }

        if (!vapidPublicKey) {
            throw new Error('Kunci publik VAPID kosong.')
        }

        isSubscribingPush.value = true

        try {
            // 1. Minta izin notifikasi jika belum granted
            const permission = await Notification.requestPermission()
            pushPermission.value = permission

            if (permission !== 'granted') {
                throw new Error(
                    permission === 'denied'
                        ? 'Izin notifikasi ditolak oleh browser. Mohon izinkan notifikasi pada setelan browser Anda.'
                        : 'Izin notifikasi tidak diberikan.'
                )
            }

            // 2. Pastikan Service Worker ready
            const reg = swRegistration.value || await navigator.serviceWorker.ready
            if (!reg) throw new Error('Service Worker belum siap.')

            // 3. Daftarkan ke PushManager
            const convertedKey = urlBase64ToUint8Array(vapidPublicKey)
            const subscription = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: convertedKey,
            })

            // 4. Kirim data subscription ke server
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            const subData = subscription.toJSON()

            const response = await fetch('/push-subscriptions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    endpoint: subData.endpoint,
                    keys: subData.keys,
                    contentEncoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0],
                }),
            })

            const resJson = await response.json()
            if (!response.ok) {
                throw new Error(resJson.message || 'Gagal menyimpan subscription ke server.')
            }

            isPushSubscribed.value = true
            return true
        } finally {
            isSubscribingPush.value = false
        }
    }

    /**
     * Berhenti berlangganan notifikasi push
     */
    async function unsubscribeFromPush() {
        if (!isPushSupported.value) return false

        isSubscribingPush.value = true

        try {
            const reg = swRegistration.value || await navigator.serviceWorker.ready
            if (!reg) return false

            const sub = await reg.pushManager.getSubscription()
            if (sub) {
                const endpoint = sub.endpoint
                await sub.unsubscribe()

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                await fetch('/push-subscriptions/destroy', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ endpoint }),
                })
            }

            isPushSubscribed.value = false
            return true
        } catch (err) {
            console.warn('[PWA] Unsubscribe push gagal:', err)
            return false
        } finally {
            isSubscribingPush.value = false
        }
    }

    /**
     * Kirim tes notifikasi ke perangkat saat ini
     */
    async function sendTestPush() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        const res = await fetch('/push-subscriptions/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({}),
        })

        const json = await res.json()
        if (!res.ok) {
            throw new Error(json.message || 'Gagal mengirim tes notifikasi.')
        }

        return json
    }

    /**
     * Simpan data form ke IndexedDB (untuk offline background sync)
     */
    async function savePendingForm(data, csrf) {
        if (!window.indexedDB) return

        const request = indexedDB.open('HabitOfflineDB', 1)
        request.onupgradeneeded = (e) => {
            e.target.result.createObjectStore('pending-forms', { keyPath: 'id', autoIncrement: true })
        }
        request.onsuccess = (e) => {
            const db = e.target.result
            const tx = db.transaction('pending-forms', 'readwrite')
            tx.objectStore('pending-forms').add({ data, csrf, timestamp: Date.now() })
            
            // Daftarkan background sync jika tersedia
            if (swRegistration.value && 'SyncManager' in window) {
                swRegistration.value.sync.register('sync-habit-form').catch(console.warn)
            }
        }
    }

    onMounted(() => {
        // Cek apakah sudah diinstall sebagai PWA
        if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
            isInstalled.value = true
        }
        checkPushSubscription()
    })

    return {
        isOffline,
        isInstallable,
        isInstalled,
        swRegistration,
        isPushSupported,
        isPushSubscribed,
        pushPermission,
        isSubscribingPush,
        registerSW,
        listenInstallPrompt,
        listenNetworkStatus,
        promptInstall,
        checkPushSubscription,
        subscribeToPush,
        unsubscribeFromPush,
        sendTestPush,
        savePendingForm,
    }
}
