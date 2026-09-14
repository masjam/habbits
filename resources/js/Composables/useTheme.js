// ============================================================
//  useTheme — Composable untuk Pengelolaan Tema Warna Global
// ============================================================

import { ref, computed } from 'vue';

export const THEMES = [
    { 
        id: 'default', 
        name: 'Emerald Green', 
        shortName: 'Emerald', 
        hex: '#10b981', 
        primary600: '#059669', 
        colorClass: 'bg-[#10b981]' 
    },
    { 
        id: 'theme-blue', 
        name: 'Ocean Blue', 
        shortName: 'Blue', 
        hex: '#3b82f6', 
        primary600: '#2563eb', 
        colorClass: 'bg-[#3b82f6]' 
    },
    { 
        id: 'theme-rose', 
        name: 'Rose Pink', 
        shortName: 'Rose', 
        hex: '#f43f5e', 
        primary600: '#e11d48', 
        colorClass: 'bg-[#f43f5e]' 
    },
    { 
        id: 'theme-amber', 
        name: 'Warm Amber', 
        shortName: 'Amber', 
        hex: '#f59e0b', 
        primary600: '#d97706', 
        colorClass: 'bg-[#f59e0b]' 
    },
    { 
        id: 'theme-purple', 
        name: 'Royal Purple', 
        shortName: 'Purple', 
        hex: '#a855f7', 
        primary600: '#9333ea', 
        colorClass: 'bg-[#a855f7]' 
    },
];

// State module-level agar reaktif dan sinkron di seluruh komponen aplikasi
const currentTheme = ref('default');

export function useTheme() {
    const activeTheme = computed(() => {
        return THEMES.find(t => t.id === currentTheme.value) || THEMES[0];
    });

    function applyTheme(themeId) {
        if (typeof document === 'undefined') return;
        const htmlEl = document.documentElement;

        THEMES.forEach(t => {
            if (t.id !== 'default') htmlEl.classList.remove(t.id);
        });

        if (themeId !== 'default') {
            htmlEl.classList.add(themeId);
        }

        const themeObj = THEMES.find(t => t.id === themeId) || THEMES[0];
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta && themeObj) {
            meta.setAttribute('content', themeObj.primary600 || themeObj.hex);
        }

        // Dispatch custom event agar komponen non-Vue / kanvas chart bisa mendengarkan
        window.dispatchEvent(new CustomEvent('theme-changed', { detail: themeObj }));
    }

    function setTheme(themeId) {
        currentTheme.value = themeId;
        applyTheme(themeId);
        try {
            localStorage.setItem('app-theme', themeId);
        } catch (e) {}
    }

    function initTheme() {
        if (typeof window === 'undefined') return;
        try {
            const saved = localStorage.getItem('app-theme') || 'default';
            currentTheme.value = saved;
            applyTheme(saved);
        } catch (e) {}
    }

    return {
        themes: THEMES,
        currentTheme,
        activeTheme,
        setTheme,
        initTheme,
    };
}
