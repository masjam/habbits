<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
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

const filteredSurahs = computed(() => {
    if (!searchQuery.value) return surahs;
    return surahs.filter(s => s.name.toLowerCase().includes(searchQuery.value.toLowerCase()))
})

const selectSurah = (surah) => {
    emit('update:modelValue', surah.name)
    emit('update:maxAyat', surah.ayat)
    isOpen.value = false
    searchQuery.value = ''
}

// Close when clicking outside
const handleClickOutside = (event) => {
    if (containerRef.value && !containerRef.value.contains(event.target)) {
        isOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside)
    // Check initial value to emit maxAyat
    if (props.modelValue) {
        const found = surahs.find(s => s.name === props.modelValue);
        if (found) emit('update:maxAyat', found.ayat);
    }
})

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleClickOutside)
})
</script>

<template>
    <div class="relative w-full" ref="containerRef">
        <!-- Input as trigger -->
        <input 
            type="text" 
            readonly
            :placeholder="placeholder"
            :value="modelValue"
            @click="isOpen = !isOpen"
            class="w-full p-1.5 text-xs border-slate-300 rounded cursor-pointer focus:ring-emerald-500 focus:border-emerald-500 bg-white dark:bg-slate-700 dark:text-slate-100"
        />
        <!-- Dropdown arrow icon -->
        <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none text-slate-400">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </div>

        <!-- Dropdown Menu -->
        <div v-if="isOpen" class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-md shadow-lg">
            <div class="p-2 border-b border-slate-100">
                <input 
                    type="text" 
                    v-model="searchQuery" 
                    placeholder="Cari surat..." 
                    class="w-full p-1.5 text-xs border border-slate-200 rounded focus:ring-emerald-500 focus:border-emerald-500 dark:bg-slate-700 dark:text-slate-100"
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
                    @click="selectSurah(surah)"
                    class="p-2 text-xs text-slate-700 hover:bg-emerald-50 cursor-pointer flex justify-between items-center"
                >
                    <span>{{ surah.id }}. {{ surah.name }}</span>
                    <span class="text-[9px] text-slate-400">{{ surah.ayat }} ayat</span>
                </li>
            </ul>
        </div>
    </div>
</template>
