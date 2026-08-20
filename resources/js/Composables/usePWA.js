// ============================================================
//  usePWA — Composable untuk Service Worker & Install Prompt
// ============================================================

import { ref, onMounted } from 'vue'

const deferredInstallPrompt = ref(null)
const isInstallable         = ref(false)
const isInstalled           = ref(false)
const isOffline             = ref(!navigator.onLine)
const swRegistration        = ref(null)

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
    })

    return {
        isOffline,
        isInstallable,
        isInstalled,
        swRegistration,
        registerSW,
        listenInstallPrompt,
        listenNetworkStatus,
        promptInstall,
        savePendingForm,
    }
}
