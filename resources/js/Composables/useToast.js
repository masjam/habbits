import { ref } from 'vue'

const toasts = ref([])
let nextId = 1

export function useToast() {
    const removeToast = (id) => {
        const index = toasts.value.findIndex(t => t.id === id)
        if (index !== -1) {
            toasts.value.splice(index, 1)
        }
    }

    const addToast = ({ type = 'info', message, title = null, duration = 4000, action = null }) => {
        if (!message) return null

        const id = nextId++
        const toast = {
            id,
            type, // 'success' | 'error' | 'warning' | 'info'
            title,
            message,
            duration,
            action, // { label: string, url: string } — opsional, tampilkan tombol download
        }

        toasts.value.push(toast)

        if (duration > 0) {
            setTimeout(() => {
                removeToast(id)
            }, duration)
        }

        return id
    }

    const success = (message, title = 'Berhasil', duration = 4000, action = null) => {
        return addToast({ type: 'success', message, title, duration, action })
    }

    const error = (message, title = 'Terjadi Kesalahan', duration = 5000) => {
        return addToast({ type: 'error', message, title, duration })
    }

    const warning = (message, title = 'Peringatan', duration = 4500) => {
        return addToast({ type: 'warning', message, title, duration })
    }

    const info = (message, title = 'Informasi', duration = 4000) => {
        return addToast({ type: 'info', message, title, duration })
    }

    const clearAll = () => {
        toasts.value = []
    }

    return {
        toasts,
        addToast,
        removeToast,
        clearAll,
        success,
        error,
        warning,
        info,
    }
}
