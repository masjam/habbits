// ============================================================
//  useDarkMode — Composable untuk Dark Mode Toggle
//  Menyimpan preferensi di localStorage, sync ke <html> class
// ============================================================

import { ref, watchEffect, onMounted } from 'vue'

// State diletakkan di module scope agar reaktif global (shared antar komponen)
const isDark = ref(false)

export function useDarkMode() {
    /**
     * Inisialisasi: baca dari localStorage atau system preference
     */
    function init() {
        const stored = localStorage.getItem('habit-dark-mode')

        if (stored !== null) {
            isDark.value = stored === 'true'
        } else {
            // Ikuti preferensi OS jika belum pernah diatur
            isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches
        }

        applyClass()
    }

    /**
     * Terapkan/hapus class 'dark' pada <html>
     */
    function applyClass() {
        if (isDark.value) {
            document.documentElement.classList.add('dark')
        } else {
            document.documentElement.classList.remove('dark')
        }
    }

    /**
     * Toggle dark mode
     */
    function toggle() {
        isDark.value = !isDark.value
        localStorage.setItem('habit-dark-mode', isDark.value)
        applyClass()
    }

    /**
     * Set secara eksplisit
     */
    function setDark(value) {
        isDark.value = value
        localStorage.setItem('habit-dark-mode', value)
        applyClass()
    }

    onMounted(() => {
        init()
    })

    return {
        isDark,
        toggle,
        setDark,
        init,
    }
}
