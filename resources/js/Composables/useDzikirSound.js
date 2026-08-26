export function useDzikirSound() {
    let audioCtx = null

    const playTickSound = (isCompleted = false, soundEnabled = true) => {
        if (!soundEnabled) return
        try {
            if (!audioCtx) {
                const AudioContext = window.AudioContext || window.webkitAudioContext
                audioCtx = new AudioContext()
            }
            if (audioCtx.state === 'suspended') {
                audioCtx.resume()
            }
            const osc = audioCtx.createOscillator()
            const gain = audioCtx.createGain()
            osc.connect(gain)
            gain.connect(audioCtx.destination)

            if (isCompleted) {
                // Suara harmoni ganda untuk selesai target
                osc.type = 'sine'
                osc.frequency.setValueAtTime(587.33, audioCtx.currentTime) // D5
                osc.frequency.exponentialRampToValueAtTime(880, audioCtx.currentTime + 0.15) // A5
                gain.gain.setValueAtTime(0.2, audioCtx.currentTime)
                gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.25)
                osc.start()
                osc.stop(audioCtx.currentTime + 0.25)
            } else {
                // Suara klik ringan
                osc.type = 'triangle'
                osc.frequency.setValueAtTime(800, audioCtx.currentTime)
                gain.gain.setValueAtTime(0.08, audioCtx.currentTime)
                gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.04)
                osc.start()
                osc.stop(audioCtx.currentTime + 0.04)
            }
        } catch (e) {}
    }

    const triggerVibrate = (isCompleted = false, vibrateEnabled = true) => {
        if (!vibrateEnabled || !navigator?.vibrate) return
        try {
            if (isCompleted) {
                navigator.vibrate([40, 60, 80])
            } else {
                navigator.vibrate(20)
            }
        } catch (e) {}
    }

    return {
        playTickSound,
        triggerVibrate
    }
}
