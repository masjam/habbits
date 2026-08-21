// ============================================================
//  useDarkMode — Composable untuk Dark Mode Toggle
//  Menyimpan preferensi di localStorage, sync ke <html> class
// ============================================================

import { ref, watchEffect, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'

// State diletakkan di module scope agar reaktif global (shared antar komponen)
const isDark = ref(false)

export function useDarkMode() {
    const page = usePage()
    
    // Cek apakah admin mengaktifkan fitur dark mode secara global
    const isDarkModeFeatureActive = () => {
        const settings = page.props.global_settings || {}
        return settings.dark_mode_active === '1' || settings.dark_mode_active === 'true'
    }

    /**
     * Inisialisasi: baca dari localStorage, tapi fallback selalu ke Light Mode (false)
     */
    function init() {
        if (!isDarkModeFeatureActive()) {
            // Jika fitur dimatikan admin, paksa light mode
            isDark.value = false
            localStorage.removeItem('habit-dark-mode')
        } else {
            const stored = localStorage.getItem('habit-dark-mode')
            if (stored !== null) {
                isDark.value = stored === 'true'
            } else {
                // Default ke mode terang (false) sesuai instruksi, bukan mengikuti OS
                isDark.value = false
            }
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
        if (!isDarkModeFeatureActive()) return
        
        isDark.value = !isDark.value
        localStorage.setItem('habit-dark-mode', isDark.value)
        applyClass()
    }

    /**
     * Set secara eksplisit
     */
    function setDark(value) {
        if (!isDarkModeFeatureActive() && value === true) return
        
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
        isDarkModeFeatureActive
    }
}
