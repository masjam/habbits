<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import surahs from '../surahs.json'

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    placeholder: {
        type: String,
        default: 'Pilih Surat'
    }
})

const emit = defineEmits(['update:modelValue', 'update:maxAyat'])

const isOpen = ref(false)
const searchQuery = ref('')
const containerRef = ref(null)
const triggerRef = ref(null)
const dropdownStyle = ref({})

const updateDropdownPosition = () => {
    if (!triggerRef.value) return
    const rect = triggerRef.value.getBoundingClientRect()
    const viewportHeight = window.innerHeight
    const dropdownHeight = 220

    dropdownStyle.value = {
        position: 'fixed',
        left: rect.left + 'px',
        width: rect.width + 'px',
        zIndex: 9999,
        ...(viewportHeight - rect.bottom >= dropdownHeight
            ? { top: (rect.bottom + 4) + 'px' }
            : { bottom: (viewportHeight - rect.top + 4) + 'px' }
        )
    }
}

const openDropdown = async () => {
    if (isOpen.value) {
        isOpen.value = false
        return
    }
    isOpen.value = true
    await nextTick()
    updateDropdownPosition()
}

const filteredSurahs = computed(() => {
    if (!searchQuery.value) return surahs
    return surahs.filter(s => s.name.toLowerCase().includes(searchQuery.value.toLowerCase()))
})

const selectSurah = (surah) => {
    emit('update:modelValue', surah.name)
    emit('update:maxAyat', surah.ayat)
    isOpen.value = false
    searchQuery.value = ''
}

const handleClickOutside = (event) => {
    if (containerRef.value && !containerRef.value.contains(event.target)) {
        const portal = document.getElementById('surah-select-portal')
        if (portal && portal.contains(event.target)) return
        isOpen.value = false
    }
}

const handleScrollResize = () => {
    if (isOpen.value) updateDropdownPosition()
}

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside)
    window.addEventListener('scroll', handleScrollResize, true)
    window.addEventListener('resize', handleScrollResize)
    if (props.modelValue) {
        const found = surahs.find(s => s.name === props.modelValue)
        if (found) emit('update:maxAyat', found.ayat)
    }
})

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleClickOutside)
    window.removeEventListener('scroll', handleScrollResize, true)
    window.removeEventListener('resize', handleScrollResize)
})
</script>

<template>
    <div class="relative w-full" ref="containerRef">
        <!-- Input trigger -->
        <input
            type="text"
            readonly
            ref="triggerRef"
            :placeholder="placeholder"
            :value="modelValue"
            @click="openDropdown"
            class="w-full p-1.5 text-xs border-slate-300 rounded cursor-pointer focus:ring-emerald-500 focus:border-emerald-500 bg-white dark:bg-slate-700 dark:text-slate-100"
        />
        <!-- Arrow icon -->
        <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none text-slate-400">
            <svg
                class="w-3 h-3 transition-transform duration-200"
                :class="isOpen ? 'rotate-180' : ''"
                fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>

        <!-- Teleport dropdown to body so it never gets clipped by card overflow -->
        <Teleport to="body">
            <div
                v-if="isOpen"
                id="surah-select-portal"
                :style="dropdownStyle"
                class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-lg shadow-2xl overflow-hidden"
            >
                <div class="p-2 border-b border-slate-100 dark:border-slate-700">
                    <input
                        type="text"
                        v-model="searchQuery"
                        placeholder="Cari surat..."
                        class="w-full p-1.5 text-xs border border-slate-200 dark:border-slate-600 rounded focus:ring-emerald-500 focus:border-emerald-500 dark:bg-slate-700 dark:text-slate-100"
                        autofocus
                    />
                </div>
                <ul class="max-h-48 overflow-y-auto">
                    <li v-if="filteredSurahs.length === 0" class="p-2 text-xs text-slate-500 text-center">
                        Tidak ditemukan
                    </li>
                    <li
                        v-for="surah in filteredSurahs"
                        :key="surah.id"
                        @mousedown.prevent="selectSurah(surah)"
                        class="p-2 text-xs text-slate-700 dark:text-slate-200 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 cursor-pointer flex justify-between items-center"
                    >
                        <span>{{ surah.id }}. {{ surah.name }}</span>
                        <span class="text-[9px] text-slate-400">{{ surah.ayat }} ayat</span>
                    </li>
                </ul>
            </div>
        </Teleport>
    </div>
</template>
