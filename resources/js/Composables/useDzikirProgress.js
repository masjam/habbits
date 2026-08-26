import { ref } from 'vue'

export function useDzikirProgress(playTickSound, triggerVibrate, soundEnabled, vibrateEnabled) {
    const getTodayKey = () => {
        const d = new Date()
        return `dzikir_progress_${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
    }

    const progressMap = ref({})

    const loadProgress = () => {
        try {
            const saved = localStorage.getItem(getTodayKey())
            if (saved) {
                progressMap.value = JSON.parse(saved)
            } else {
                progressMap.value = {}
            }
        } catch (e) {
            progressMap.value = {}
        }
    }

    const saveProgress = () => {
        try {
            localStorage.setItem(getTodayKey(), JSON.stringify(progressMap.value))
        } catch (e) {}
    }

    const getCount = (id) => progressMap.value[id] || 0

    const isCompleted = (item) => getCount(item.id) >= item.target

    const incrementCount = (item) => {
        const current = getCount(item.id)
        if (current < item.target) {
            const next = current + 1
            progressMap.value[item.id] = next
            const justFinished = next >= item.target
            if (playTickSound) playTickSound(justFinished, soundEnabled.value)
            if (triggerVibrate) triggerVibrate(justFinished, vibrateEnabled.value)
            saveProgress()
        }
    }

    const completeDirectly = (item) => {
        progressMap.value[item.id] = item.target
        if (playTickSound) playTickSound(true, soundEnabled.value)
        if (triggerVibrate) triggerVibrate(true, vibrateEnabled.value)
        saveProgress()
    }

    const resetItemCount = (item) => {
        delete progressMap.value[item.id]
        saveProgress()
    }

    const resetCurrentTabProgress = (currentList) => {
        if (!confirm('Apakah Anda yakin ingin mengatur ulang progres dzikir untuk sesi ini?')) return
        currentList.forEach(item => {
            delete progressMap.value[item.id]
        })
        saveProgress()
    }

    return {
        progressMap,
        loadProgress,
        saveProgress,
        getCount,
        isCompleted,
        incrementCount,
        completeDirectly,
        resetItemCount,
        resetCurrentTabProgress
    }
}
