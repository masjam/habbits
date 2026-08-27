<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    shareData: {
        type: Object,
        required: true,
        default: () => ({
            type: 'quran',
            title: '',
            arab: '',
            latin: '',
            translation: '',
            source: '',
            fullText: ''
        })
    }
})

const emit = defineEmits(['update:modelValue'])

const shareTab = ref('image') // 'image' | 'text'
const cardRatio = ref('1:1') // '1:1' | '9:16' | '4:5'
const cardTemplate = ref('emerald')
const cardIncludeArab = ref(true)
const cardIncludeLatin = ref(false)
const cardIncludeTranslation = ref(true)
const cardIncludeSource = ref(true)
const cardOverlayDarkness = ref(50) // percentage
const customUploadedImage = ref(null)
const isGeneratingImage = ref(false)
const customFileInput = ref(null)

const BG_TEMPLATES = [
    {
        id: 'emerald',
        name: 'Masjid Zamrud',
        type: 'image',
        thumb: 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=150&q=70',
        url: 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1200&q=85',
        gradient: 'linear-gradient(135deg, #064e3b 0%, #047857 50%, #022c22 100%)',
        textColor: '#ffffff',
        accentColor: '#34d399'
    },
    {
        id: 'midnight',
        name: 'Malam Bintang',
        type: 'image',
        thumb: 'https://images.unsplash.com/photo-1506703719100-a0f3a48c0f86?w=150&q=70',
        url: 'https://images.unsplash.com/photo-1506703719100-a0f3a48c0f86?w=1200&q=85',
        gradient: 'linear-gradient(135deg, #0b132b 0%, #1c2541 50%, #3a506b 100%)',
        textColor: '#f8fafc',
        accentColor: '#60a5fa'
    },
    {
        id: 'sunset',
        name: 'Senja Gurun',
        type: 'image',
        thumb: 'https://images.unsplash.com/photo-1509316975850-ff9c5deb0cd9?w=150&q=70',
        url: 'https://images.unsplash.com/photo-1509316975850-ff9c5deb0cd9?w=1200&q=85',
        gradient: 'linear-gradient(135deg, #451a03 0%, #9a3412 50%, #ea580c 100%)',
        textColor: '#fffbeb',
        accentColor: '#fcd34d'
    },
    {
        id: 'mosque_white',
        name: 'Kubah Putih',
        type: 'image',
        thumb: 'https://images.unsplash.com/photo-1564769625905-50e93615e769?w=150&q=70',
        url: 'https://images.unsplash.com/photo-1564769625905-50e93615e769?w=1200&q=85',
        gradient: 'linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%)',
        textColor: '#ffffff',
        accentColor: '#38bdf8'
    },
    {
        id: 'mist_mountain',
        name: 'Fajar Hening',
        type: 'image',
        thumb: 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=150&q=70',
        url: 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=1200&q=85',
        gradient: 'linear-gradient(135deg, #1e293b 0%, #0f172a 100%)',
        textColor: '#ffffff',
        accentColor: '#a7f3d0'
    },
    {
        id: 'ocean_calm',
        name: 'Lautan Teduh',
        type: 'image',
        thumb: 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=150&q=70',
        url: 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1200&q=85',
        gradient: 'linear-gradient(135deg, #083344 0%, #155e75 50%, #0e7490 100%)',
        textColor: '#f0fdfa',
        accentColor: '#2dd4bf'
    },
    {
        id: 'obsidian_gold',
        name: 'Obsidian Emas',
        type: 'gradient',
        thumb: 'linear-gradient(135deg, #09090b 0%, #18181b 50%, #27272a 100%)',
        gradient: 'linear-gradient(135deg, #09090b 0%, #18181b 50%, #27272a 100%)',
        textColor: '#fef08a',
        accentColor: '#eab308'
    },
    {
        id: 'classic_parchment',
        name: 'Mushaf Klasik',
        type: 'gradient',
        thumb: 'linear-gradient(135deg, #fef3c7 0%, #fde68a 50%, #d97706 100%)',
        gradient: 'linear-gradient(135deg, #fef3c7 0%, #fde68a 50%, #f59e0b 100%)',
        textColor: '#1e293b',
        accentColor: '#047857'
    },
    {
        id: 'lavender_sky',
        name: 'Ungu Senja',
        type: 'image',
        thumb: 'https://images.unsplash.com/photo-1518495973542-4542c06a5843?w=150&q=70',
        url: 'https://images.unsplash.com/photo-1518495973542-4542c06a5843?w=1200&q=85',
        gradient: 'linear-gradient(135deg, #3b0764 0%, #581c87 50%, #701a75 100%)',
        textColor: '#faf5ff',
        accentColor: '#f472b6'
    },
    {
        id: 'ruby_glow',
        name: 'Merah Marun',
        type: 'gradient',
        thumb: 'linear-gradient(135deg, #4c0519 0%, #881337 50%, #be123c 100%)',
        gradient: 'linear-gradient(135deg, #4c0519 0%, #881337 50%, #be123c 100%)',
        textColor: '#fff1f2',
        accentColor: '#fda4af'
    }
]

const currentBgTemplate = computed(() => {
    if (cardTemplate.value === 'custom' && customUploadedImage.value) {
        return {
            id: 'custom',
            name: 'Gambar Kustom',
            type: 'custom',
            url: customUploadedImage.value,
            gradient: 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)',
            textColor: '#ffffff',
            accentColor: '#34d399'
        }
    }
    return BG_TEMPLATES.find(t => t.id === cardTemplate.value) || BG_TEMPLATES[0]
})

const shareCopied = ref(false)

const closeShareModal = () => {
    emit('update:modelValue', false)
}

const triggerCustomUpload = () => {
    if (customFileInput.value) {
        customFileInput.value.click()
    }
}

const handleCustomImageUpload = (event) => {
    const file = event.target.files?.[0]
    if (!file) return
    const reader = new FileReader()
    reader.onload = (e) => {
        customUploadedImage.value = e.target.result
        cardTemplate.value = 'custom'
    }
    reader.readAsDataURL(file)
}

const generateCardBlob = async () => {
    const width = 1080
    let height = 1080
    if (cardRatio.value === '9:16') height = 1920
    else if (cardRatio.value === '4:5') height = 1350

    const canvas = document.createElement('canvas')
    canvas.width = width
    canvas.height = height
    const ctx = canvas.getContext('2d')
    if (!ctx) return null

    const tpl = currentBgTemplate.value

    // 1. Gambar Background
    if (tpl.type === 'custom' && customUploadedImage.value) {
        await new Promise((resolve) => {
            const img = new Image()
            img.onload = () => {
                const scale = Math.max(width / img.width, height / img.height)
                const x = (width - img.width * scale) / 2
                const y = (height - img.height * scale) / 2
                ctx.drawImage(img, x, y, img.width * scale, img.height * scale)
                resolve()
            }
            img.onerror = () => {
                ctx.fillStyle = '#0f172a'
                ctx.fillRect(0, 0, width, height)
                resolve()
            }
            img.src = customUploadedImage.value
        })
    } else if (tpl.type === 'image' && tpl.url) {
        await new Promise((resolve) => {
            const img = new Image()
            img.crossOrigin = 'anonymous'
            img.onload = () => {
                const scale = Math.max(width / img.width, height / img.height)
                const x = (width - img.width * scale) / 2
                const y = (height - img.height * scale) / 2
                ctx.drawImage(img, x, y, img.width * scale, img.height * scale)
                resolve()
            }
            img.onerror = () => {
                const grad = ctx.createLinearGradient(0, 0, width, height)
                grad.addColorStop(0, '#064e3b')
                grad.addColorStop(1, '#022c22')
                ctx.fillStyle = grad
                ctx.fillRect(0, 0, width, height)
                resolve()
            }
            img.src = tpl.url
        })
    } else {
        const grad = ctx.createLinearGradient(0, 0, width, height)
        if (tpl.id === 'obsidian_gold') {
            grad.addColorStop(0, '#09090b'); grad.addColorStop(0.5, '#18181b'); grad.addColorStop(1, '#27272a')
        } else if (tpl.id === 'classic_parchment') {
            grad.addColorStop(0, '#fef3c7'); grad.addColorStop(0.5, '#fde68a'); grad.addColorStop(1, '#d97706')
        } else if (tpl.id === 'ruby_glow') {
            grad.addColorStop(0, '#4c0519'); grad.addColorStop(0.5, '#881337'); grad.addColorStop(1, '#be123c')
        } else {
            grad.addColorStop(0, '#064e3b'); grad.addColorStop(1, '#022c22')
        }
        ctx.fillStyle = grad
        ctx.fillRect(0, 0, width, height)
    }

    // 2. Lapisan Overlay Kegelapan
    const overlayAlpha = (cardOverlayDarkness.value || 50) / 100
    ctx.fillStyle = `rgba(0, 0, 0, ${overlayAlpha})`
    ctx.fillRect(0, 0, width, height)

    // 3. Bingkai Dekorasi Islami
    const margin = 48
    ctx.strokeStyle = tpl.accentColor || '#34d399'
    ctx.lineWidth = 3
    ctx.globalAlpha = 0.45
    ctx.strokeRect(margin, margin, width - margin * 2, height - margin * 2)

    // Aksen Sudut
    const cornerSize = 24
    ctx.globalAlpha = 0.8
    ctx.fillStyle = tpl.accentColor || '#34d399'
    ctx.fillRect(margin - 4, margin - 4, cornerSize, 4)
    ctx.fillRect(margin - 4, margin - 4, 4, cornerSize)
    ctx.fillRect(width - margin - cornerSize + 4, margin - 4, cornerSize, 4)
    ctx.fillRect(width - margin, margin - 4, 4, cornerSize)
    ctx.fillRect(margin - 4, height - margin, cornerSize, 4)
    ctx.fillRect(margin - 4, height - margin - cornerSize + 4, 4, cornerSize)
    ctx.fillRect(width - margin - cornerSize + 4, height - margin, cornerSize, 4)
    ctx.fillRect(width - margin, height - margin - cornerSize + 4, 4, cornerSize)
    ctx.globalAlpha = 1.0

    // Helper wrap teks
    const wrapText = (text, maxWidth, font, lineHeight) => {
        ctx.font = font
        const words = text.split(' ')
        const lines = []
        let currentLine = words[0] || ''

        for (let i = 1; i < words.length; i++) {
            const word = words[i]
            const widthTest = ctx.measureText(currentLine + ' ' + word).width
            if (widthTest < maxWidth) {
                currentLine += ' ' + word
            } else {
                lines.push(currentLine)
                currentLine = word
            }
        }
        if (currentLine) lines.push(currentLine)
        return lines
    }

    // 4. Kalkulasi Tinggi Konten agar simetris di tengah
    const maxContentWidth = width - margin * 2 - 80
    let totalContentHeight = 0
    const sections = []

    // Header Title
    sections.push({ type: 'header', text: props.shareData.title, height: 45 })
    totalContentHeight += 55

    // Teks Arab
    if (cardIncludeArab.value && props.shareData.arab) {
        const arabFont = 'bold 44px "Scheherazade New", "Amiri Quran", "Amiri", serif'
        const arabLines = wrapText(props.shareData.arab, maxContentWidth, arabFont, 75)
        const h = arabLines.length * 75
        sections.push({ type: 'arab', lines: arabLines, font: arabFont, lineHeight: 75, height: h })
        totalContentHeight += h + 30
    }

    // Transliterasi Latin
    if (cardIncludeLatin.value && props.shareData.latin) {
        const latinFont = 'italic 24px "Instrument Sans", sans-serif'
        const latinLines = wrapText(props.shareData.latin, maxContentWidth, latinFont, 36)
        const h = latinLines.length * 36
        sections.push({ type: 'latin', lines: latinLines, font: latinFont, lineHeight: 36, height: h })
        totalContentHeight += h + 25
    }

    // Terjemahan
    if (cardIncludeTranslation.value && props.shareData.translation) {
        const transFont = '26px "Instrument Sans", sans-serif'
        const transLines = wrapText(`"${props.shareData.translation}"`, maxContentWidth, transFont, 42)
        const h = transLines.length * 42
        sections.push({ type: 'trans', lines: transLines, font: transFont, lineHeight: 42, height: h })
        totalContentHeight += h + 30
    }

    // Sumber / Referensi
    if (cardIncludeSource.value && props.shareData.source) {
        sections.push({ type: 'source', text: props.shareData.source, height: 40 })
        totalContentHeight += 50
    }

    // Mulai menggambar di titik Y
    let currentY = Math.max(margin + 50, (height - totalContentHeight) / 2)

    for (const sec of sections) {
        if (sec.type === 'header') {
            ctx.fillStyle = tpl.accentColor || '#34d399'
            ctx.font = 'bold 22px "Instrument Sans", sans-serif'
            ctx.textAlign = 'center'
            ctx.fillText(sec.text.toUpperCase(), width / 2, currentY)
            currentY += 45
        } else if (sec.type === 'arab') {
            ctx.fillStyle = tpl.textColor || '#ffffff'
            ctx.font = sec.font
            ctx.textAlign = 'center'
            for (const line of sec.lines) {
                ctx.fillText(line, width / 2, currentY)
                currentY += sec.lineHeight
            }
            currentY += 25
        } else if (sec.type === 'latin') {
            ctx.fillStyle = tpl.accentColor || '#34d399'
            ctx.font = sec.font
            ctx.textAlign = 'center'
            for (const line of sec.lines) {
                ctx.fillText(line, width / 2, currentY)
                currentY += sec.lineHeight
            }
            currentY += 20
        } else if (sec.type === 'trans') {
            ctx.fillStyle = tpl.textColor || '#ffffff'
            ctx.font = sec.font
            ctx.textAlign = 'center'
            for (const line of sec.lines) {
                ctx.fillText(line, width / 2, currentY)
                currentY += sec.lineHeight
            }
            currentY += 25
        } else if (sec.type === 'source') {
            ctx.strokeStyle = tpl.accentColor || '#34d399'
            ctx.globalAlpha = 0.3
            ctx.beginPath()
            ctx.moveTo(width / 2 - 80, currentY - 10)
            ctx.lineTo(width / 2 + 80, currentY - 10)
            ctx.stroke()
            ctx.globalAlpha = 1.0

            ctx.fillStyle = tpl.textColor || '#ffffff'
            ctx.globalAlpha = 0.75
            ctx.font = '18px "Instrument Sans", sans-serif'
            ctx.textAlign = 'center'
            ctx.fillText(sec.text, width / 2, currentY + 15)
            ctx.globalAlpha = 1.0
            currentY += 40
        }
    }

    // Watermark Branding
    ctx.fillStyle = tpl.accentColor || '#34d399'
    ctx.font = 'bold 16px "Instrument Sans", sans-serif'
    ctx.textAlign = 'center'
    ctx.globalAlpha = 0.6
    ctx.fillText('HABIT TRACKER SDAM', width / 2, height - margin - 20)
    ctx.globalAlpha = 1.0

    return new Promise((resolve) => {
        canvas.toBlob((blob) => {
            resolve(blob)
        }, 'image/png')
    })
}

const downloadQuoteImage = async () => {
    isGeneratingImage.value = true
    try {
        const blob = await generateCardBlob()
        if (!blob) return
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        const filename = `ayat-${props.shareData.title.replace(/[^a-zA-Z0-9]/g, '_')}.png`
        a.href = url
        a.download = filename
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        URL.revokeObjectURL(url)
    } finally {
        isGeneratingImage.value = false
    }
}

const copyImageSuccess = ref(false)

const copyQuoteImageToClipboard = async () => {
    isGeneratingImage.value = true
    try {
        const blob = await generateCardBlob()
        if (!blob) return
        if (typeof ClipboardItem !== 'undefined' && navigator.clipboard && navigator.clipboard.write) {
            const item = new ClipboardItem({ 'image/png': blob })
            await navigator.clipboard.write([item])
            copyImageSuccess.value = true
            setTimeout(() => {
                copyImageSuccess.value = false
            }, 3000)
            return
        }
        await downloadQuoteImage()
    } catch (e) {
        await downloadQuoteImage()
    } finally {
        isGeneratingImage.value = false
    }
}

const shareQuoteImageFile = async () => {
    isGeneratingImage.value = true
    try {
        const blob = await generateCardBlob()
        if (!blob) return

        const fileName = `ayat-${props.shareData.title.replace(/[^a-zA-Z0-9]/g, '_')}.png`
        const file = new File([blob], fileName, { type: 'image/png', lastModified: Date.now() })

        if (typeof navigator !== 'undefined' && navigator.share) {
            let canShareFiles = false
            try {
                canShareFiles = navigator.canShare && navigator.canShare({ files: [file] })
            } catch (err) {
                canShareFiles = false
            }

            if (canShareFiles) {
                await navigator.share({ files: [file] })
                return
            } else {
                try {
                    await navigator.share({ files: [file] })
                    return
                } catch (shareErr) {
                    if (shareErr.name === 'AbortError') return
                }
            }
        }

        await downloadQuoteImage()
    } catch (e) {
        if (e.name !== 'AbortError') {
            console.error('Share error:', e)
        }
    } finally {
        isGeneratingImage.value = false
    }
}

const imageShareToast = ref(null) // null | { platform, copied, downloaded }
let imageShareToastTimer = null

const showImageShareToast = (msg) => {
    imageShareToast.value = msg
    if (imageShareToastTimer) clearTimeout(imageShareToastTimer)
    imageShareToastTimer = setTimeout(() => { imageShareToast.value = null }, 5000)
}

const shareImageToApp = async (platform) => {
    isGeneratingImage.value = true
    try {
        const blob = await generateCardBlob()
        if (!blob) return

        const fileName = `ayat-${props.shareData.title.replace(/[^a-zA-Z0-9]/g, '_')}.png`
        const file = new File([blob], fileName, { type: 'image/png', lastModified: Date.now() })

        const isSecure = typeof window !== 'undefined' && window.isSecureContext
        let canShareFiles = false
        if (isSecure && typeof navigator !== 'undefined' && navigator.canShare) {
            try { canShareFiles = navigator.canShare({ files: [file] }) } catch (e) { canShareFiles = false }
        }

        if (canShareFiles) {
            await navigator.share({ files: [file] })
            return
        }

        let imageCopied = false
        if (typeof ClipboardItem !== 'undefined' && navigator.clipboard && navigator.clipboard.write) {
            try {
                await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })])
                imageCopied = true
            } catch (e) { }
        }

        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = fileName
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        URL.revokeObjectURL(url)

        const platformLabels = { whatsapp: 'WhatsApp Web', telegram: 'Telegram Web', twitter: 'X / Twitter', facebook: 'Facebook' }
        showImageShareToast({ platform: platformLabels[platform] || platform, copied: imageCopied })

        await new Promise(r => setTimeout(r, 800))
        if (platform === 'whatsapp') shareToWhatsApp()
        else if (platform === 'telegram') shareToTelegram()
        else if (platform === 'twitter') shareToTwitter()
        else if (platform === 'facebook') shareToFacebook()

    } catch (e) {
        if (e.name !== 'AbortError') console.error('Share error:', e)
    } finally {
        isGeneratingImage.value = false
    }
}

const shareToWhatsApp = () => {
    const url = `https://api.whatsapp.com/send?text=${encodeURIComponent(props.shareData.fullText)}`
    window.open(url, '_blank')
}

const shareToTelegram = () => {
    const origin = typeof window !== 'undefined' ? window.location.origin : ''
    const url = `https://t.me/share/url?url=${encodeURIComponent(origin)}&text=${encodeURIComponent(props.shareData.fullText)}`
    window.open(url, '_blank')
}

const shareToTwitter = () => {
    const maxLen = 200
    const excerpt = props.shareData.translation.length > maxLen ? props.shareData.translation.slice(0, maxLen) + '...' : props.shareData.translation
    const tweetText = `${props.shareData.title}\n\n"${excerpt}"\n\n${props.shareData.source}`
    const url = `https://twitter.com/intent/tweet?text=${encodeURIComponent(tweetText)}`
    window.open(url, '_blank')
}

const shareToFacebook = () => {
    const origin = typeof window !== 'undefined' ? window.location.origin : ''
    const url = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(origin)}&quote=${encodeURIComponent(props.shareData.fullText)}`
    window.open(url, '_blank')
}

const shareNative = async () => {
    if (typeof navigator !== 'undefined' && navigator.share) {
        try {
            await navigator.share({
                title: props.shareData.title,
                text: props.shareData.fullText
            })
        } catch (e) {}
    }
}

// Fallback manual copy
const copyToClipboard = async (text) => {
    try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(text)
            return true
        }
        const textarea = document.createElement('textarea')
        textarea.value = text
        textarea.style.position = 'fixed'
        textarea.style.left = '-9999px'
        document.body.appendChild(textarea)
        textarea.select()
        textarea.setSelectionRange(0, textarea.value.length)
        const success = document.execCommand('copy')
        document.body.removeChild(textarea)
        return success
    } catch (err) {
        return false
    }
}

const copyShareText = async () => {
    const success = await copyToClipboard(props.shareData.fullText)
    if (success) {
        shareCopied.value = true
        setTimeout(() => {
            shareCopied.value = false
        }, 2500)
    }
}

</script>

<template>
    <transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
    >
        <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6" @keydown.esc="closeShareModal" tabindex="0">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeShareModal"></div>

            <!-- Hidden file input for custom image upload -->
            <input 
                type="file" 
                ref="customFileInput" 
                @change="handleCustomImageUpload" 
                accept="image/png, image/jpeg, image/webp" 
                class="hidden"
            />

            <!-- Modal Content -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl w-full max-w-5xl overflow-y-auto md:overflow-hidden relative z-10 flex flex-col md:flex-row border border-slate-200 dark:border-slate-700 max-h-[90vh] md:max-h-[85vh] custom-scrollbar">
                <!-- Panel Kiri: Live Preview -->
                <div class="w-full md:w-[55%] lg:w-[60%] bg-slate-100 dark:bg-slate-950 flex flex-col border-b md:border-b-0 md:border-r border-slate-200 dark:border-slate-800 shrink-0 md:h-full">
                    
                    <!-- Header Modal Share -->
                    <div class="p-4 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between shadow-xs z-10 shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base sm:text-lg font-bold text-slate-800 dark:text-slate-100 leading-tight">Bagikan ke Sosial Media</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 hidden sm:block">Buat visual kutipan yang indah atau salin teks langsung.</p>
                            </div>
                        </div>
                        <button 
                            @click="closeShareModal" 
                            class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 rounded-full transition-colors"
                            aria-label="Tutup Modal Bagikan"
                        >
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Tab Switcher: Mode Gambar vs Mode Teks -->
                    <div class="px-4 py-3 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 flex justify-center shrink-0">
                        <div class="inline-flex bg-slate-100 dark:bg-slate-800 p-1 rounded-xl w-full max-w-sm">
                            <button 
                                @click="shareTab = 'image'"
                                :class="['flex-1 py-1.5 px-3 rounded-lg text-sm font-bold transition-all', shareTab === 'image' ? 'bg-white dark:bg-slate-700 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200']"
                            >
                                Mode Gambar
                            </button>
                            <button 
                                @click="shareTab = 'text'"
                                :class="['flex-1 py-1.5 px-3 rounded-lg text-sm font-bold transition-all', shareTab === 'text' ? 'bg-white dark:bg-slate-700 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200']"
                            >
                                Mode Teks
                            </button>
                        </div>
                    </div>

                    <!-- ════════════════════════════════════════════════════ -->
                    <!-- TAB 1: MODE GAMBAR (IMAGE QUOTE CARD GENERATOR)     -->
                    <!-- ════════════════════════════════════════════════════ -->
                    <div v-if="shareTab === 'image'" class="flex flex-col md:h-full md:overflow-hidden">
                        <!-- Rasio Aspek (1:1, 9:16 Story, 4:5 Potret) -->
                        <div class="p-3 border-b border-slate-200 dark:border-slate-800 shrink-0 flex justify-center gap-2">
                            <button @click="cardRatio = '1:1'" :class="['px-3 py-1.5 text-xs font-bold rounded-lg border transition-all', cardRatio === '1:1' ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'border-slate-200 dark:border-slate-700 text-slate-500 hover:bg-slate-200 dark:hover:bg-slate-800']">
                                1:1 Persegi (Feed)
                            </button>
                            <button @click="cardRatio = '4:5'" :class="['px-3 py-1.5 text-xs font-bold rounded-lg border transition-all', cardRatio === '4:5' ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'border-slate-200 dark:border-slate-700 text-slate-500 hover:bg-slate-200 dark:hover:bg-slate-800']">
                                4:5 (IG Potret)
                            </button>
                            <button @click="cardRatio = '9:16'" :class="['px-3 py-1.5 text-xs font-bold rounded-lg border transition-all', cardRatio === '9:16' ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'border-slate-200 dark:border-slate-700 text-slate-500 hover:bg-slate-200 dark:hover:bg-slate-800']">
                                9:16 (Story/Reel)
                            </button>
                        </div>

                        <!-- LIVE VISUAL CARD PREVIEW -->
                        <div class="flex-1 p-4 md:p-6 md:overflow-y-auto flex items-center justify-center min-h-[300px]">
                            <!-- Container Skala Proporsional -->
                            <div 
                                class="relative bg-slate-200 shadow-xl overflow-hidden rounded-md transition-all duration-300 ring-4 ring-white/50"
                                :style="{
                                    width: cardRatio === '1:1' ? '320px' : (cardRatio === '4:5' ? '280px' : '220px'),
                                    aspectRatio: cardRatio === '1:1' ? '1/1' : (cardRatio === '4:5' ? '4/5' : '9/16'),
                                    background: currentBgTemplate.type === 'image' && currentBgTemplate.url ? `url(${currentBgTemplate.url}) center/cover no-repeat` : currentBgTemplate.gradient
                                }"
                            >
                                <!-- Overlay Kegelapan / Dimmer -->
                                <div class="absolute inset-0" :style="{ backgroundColor: `rgba(0,0,0,${cardOverlayDarkness / 100})` }"></div>

                                <!-- Bingkai Dekoratif Ornamen Islami -->
                                <div class="absolute inset-[16px] border-[1.5px] border-white/40 pointer-events-none flex flex-col justify-between" :style="{ borderColor: currentBgTemplate.accentColor, opacity: 0.45 }"></div>
                                
                                <!-- Aksen Sudut -->
                                <div class="absolute top-[14px] left-[14px] w-[10px] h-[3px]" :style="{ backgroundColor: currentBgTemplate.accentColor, opacity: 0.8 }"></div>
                                <div class="absolute top-[14px] left-[14px] w-[3px] h-[10px]" :style="{ backgroundColor: currentBgTemplate.accentColor, opacity: 0.8 }"></div>
                                <div class="absolute top-[14px] right-[14px] w-[10px] h-[3px]" :style="{ backgroundColor: currentBgTemplate.accentColor, opacity: 0.8 }"></div>
                                <div class="absolute top-[14px] right-[14px] w-[3px] h-[10px]" :style="{ backgroundColor: currentBgTemplate.accentColor, opacity: 0.8 }"></div>
                                <div class="absolute bottom-[14px] left-[14px] w-[10px] h-[3px]" :style="{ backgroundColor: currentBgTemplate.accentColor, opacity: 0.8 }"></div>
                                <div class="absolute bottom-[14px] left-[14px] w-[3px] h-[10px]" :style="{ backgroundColor: currentBgTemplate.accentColor, opacity: 0.8 }"></div>
                                <div class="absolute bottom-[14px] right-[14px] w-[10px] h-[3px]" :style="{ backgroundColor: currentBgTemplate.accentColor, opacity: 0.8 }"></div>
                                <div class="absolute bottom-[14px] right-[14px] w-[3px] h-[10px]" :style="{ backgroundColor: currentBgTemplate.accentColor, opacity: 0.8 }"></div>

                                <!-- Header Kartu: Judul Surah & Ayat -->
                                <div class="absolute top-8 left-0 right-0 text-center z-10 px-6">
                                    <h4 class="text-[10px] font-bold tracking-widest uppercase opacity-90 drop-shadow-md" :style="{ color: currentBgTemplate.accentColor }">
                                        {{ shareData.title }}
                                    </h4>
                                </div>

                                <!-- Konten Utama: Arab, Latin, Terjemahan -->
                                <div class="absolute inset-0 flex flex-col items-center justify-center z-10 px-8 py-16 gap-3">
                                    <!-- Teks Arab -->
                                    <p v-if="cardIncludeArab && shareData.arab" class="font-arabic text-[18px] leading-loose text-center drop-shadow-lg" :style="{ color: currentBgTemplate.textColor }">
                                        {{ shareData.arab }}
                                    </p>
                                    
                                    <!-- Transliterasi Latin -->
                                    <p v-if="cardIncludeLatin && shareData.latin" class="text-[10px] italic text-center drop-shadow-md opacity-90 font-medium" :style="{ color: currentBgTemplate.accentColor }">
                                        {{ shareData.latin }}
                                    </p>

                                    <!-- Terjemahan Bahasa Indonesia -->
                                    <p v-if="cardIncludeTranslation && shareData.translation" class="text-[11px] text-center drop-shadow-md line-clamp-5 leading-relaxed font-medium" :style="{ color: currentBgTemplate.textColor }">
                                        "{{ shareData.translation }}"
                                    </p>

                                    <!-- Pemisah Sumber -->
                                    <div v-if="cardIncludeSource && shareData.source" class="w-16 border-t my-1 opacity-30 drop-shadow-md" :style="{ borderColor: currentBgTemplate.accentColor }"></div>
                                    <p v-if="cardIncludeSource && shareData.source" class="text-[9px] text-center opacity-75 drop-shadow-md font-bold" :style="{ color: currentBgTemplate.textColor }">
                                        {{ shareData.source }}
                                    </p>
                                </div>

                                <!-- Footer Kartu: Sumber & Watermark -->
                                <div class="absolute bottom-6 left-0 right-0 text-center z-10 px-6">
                                    <div class="text-[8px] font-black tracking-widest opacity-60 uppercase" :style="{ color: currentBgTemplate.accentColor }">
                                        HABIT TRACKER SDAM
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ════════════════════════════════════════════════════ -->
                    <!-- TAB 2: MODE FORMAT TEKS PESAN MEDSOS                 -->
                    <!-- ════════════════════════════════════════════════════ -->
                    <div v-if="shareTab === 'text'" class="flex-1 overflow-y-auto p-4 md:p-6 bg-slate-50 dark:bg-slate-900">
                        <!-- Preview Teks yang Dibagikan -->
                        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-4 md:p-5 shadow-sm">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Preview Pesan:</label>
                            <div class="bg-slate-50 dark:bg-slate-900 rounded-xl p-4 border border-slate-100 dark:border-slate-700 text-sm whitespace-pre-wrap font-mono text-slate-700 dark:text-slate-300 max-h-[300px] overflow-y-auto select-all">
                                {{ shareData.fullText }}
                            </div>
                        </div>

                        <!-- Pilihan Tombol Media Sosial -->
                        <div class="mt-6">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Bagikan Langsung Ke:</label>
                            <div class="grid grid-cols-2 gap-3">
                                <!-- WhatsApp -->
                                <button @click="shareToWhatsApp" class="flex items-center gap-3 p-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-[#25D366] hover:bg-[#25D366]/5 rounded-xl transition-all group">
                                    <div class="w-10 h-10 rounded-full bg-[#25D366] text-white flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                    </div>
                                    <span class="text-sm font-bold text-slate-700 dark:text-slate-200 group-hover:text-[#25D366]">WhatsApp</span>
                                </button>
                                
                                <!-- Telegram -->
                                <button @click="shareToTelegram" class="flex items-center gap-3 p-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-[#0088cc] hover:bg-[#0088cc]/5 rounded-xl transition-all group">
                                    <div class="w-10 h-10 rounded-full bg-[#0088cc] text-white flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.18-.357.34-.635.34l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.121l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.458c.538-.196 1.006.128.832.894z"/></svg>
                                    </div>
                                    <span class="text-sm font-bold text-slate-700 dark:text-slate-200 group-hover:text-[#0088cc]">Telegram</span>
                                </button>

                                <!-- Twitter / X -->
                                <button @click="shareToTwitter" class="flex items-center gap-3 p-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-slate-900 hover:bg-slate-900/5 dark:hover:border-slate-400 rounded-xl transition-all group">
                                    <div class="w-10 h-10 rounded-full bg-black text-white flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                                    </div>
                                    <span class="text-sm font-bold text-slate-700 dark:text-slate-200 group-hover:text-black dark:group-hover:text-white">X / Twitter</span>
                                </button>

                                <!-- Facebook -->
                                <button @click="shareToFacebook" class="flex items-center gap-3 p-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-[#1877F2] hover:bg-[#1877F2]/5 rounded-xl transition-all group">
                                    <div class="w-10 h-10 rounded-full bg-[#1877F2] text-white flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M9 8H6v4h3v12h5V12h3.642L18 8h-4V6.333C14 5.374 14.5 5 15.657 5H18V0h-3.808C10.597 0 9 1.582 9 4.615V8z"/></svg>
                                    </div>
                                    <span class="text-sm font-bold text-slate-700 dark:text-slate-200 group-hover:text-[#1877F2]">Facebook</span>
                                </button>
                            </div>

                            <!-- Baris Aksi Tambahan (Salin & Share Native) -->
                            <div class="mt-4 flex gap-3">
                                <button 
                                    @click="copyShareText" 
                                    class="flex-1 p-3 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 rounded-xl text-slate-700 dark:text-slate-200 text-sm font-bold flex items-center justify-center gap-2 transition-colors"
                                >
                                    <svg v-if="!shareCopied" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10a2 2 0 01-2-2v-4a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2z"/></svg>
                                    <svg v-else class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    <span>{{ shareCopied ? 'Teks Tersalin!' : 'Salin Teks' }}</span>
                                </button>
                                
                                <button 
                                    @click="shareNative"
                                    class="p-3 bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-200 dark:hover:bg-emerald-900/60 rounded-xl font-bold flex items-center justify-center gap-2 transition-colors md:hidden"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                                    <span>Lainnya</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel Kanan: Customizer Controls (Hanya Tampil di Mode Gambar) -->
                <div v-if="shareTab === 'image'" class="w-full md:w-[45%] lg:w-[40%] bg-white dark:bg-slate-900 flex flex-col h-auto md:h-full md:overflow-y-auto custom-scrollbar">
                    <div class="p-4 md:p-6 space-y-6">
                        
                        <!-- CUSTOMIZER CONTROLS: 10 BACKGROUND TEMPLATES + UPLOAD -->
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Latar Belakang Estetik</label>
                            
                            <!-- Thumbnail Grid / Horizontal Scroll -->
                            <div class="flex md:grid md:grid-cols-3 gap-2 overflow-x-auto pb-2 md:pb-0 snap-x">
                                <!-- Custom Upload Thumbnail (if available) -->
                                <button 
                                    v-if="customUploadedImage"
                                    @click="cardTemplate = 'custom'" 
                                    :class="['relative w-20 h-20 md:w-full md:h-20 rounded-xl overflow-hidden shrink-0 border-2 transition-all', cardTemplate === 'custom' ? 'border-emerald-500 shadow-md scale-95' : 'border-transparent hover:scale-95']"
                                >
                                    <div class="absolute inset-0 bg-cover bg-center" :style="{ backgroundImage: `url(${customUploadedImage})` }"></div>
                                    <div class="absolute inset-0 bg-black/40 flex items-center justify-center">
                                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" v-if="cardTemplate === 'custom'"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" v-else/></svg>
                                    </div>
                                    <span class="absolute bottom-1 left-0 right-0 text-[9px] text-center text-white font-bold drop-shadow-md">Kustom</span>
                                </button>

                                <!-- 10 Curated Presets -->
                                <button 
                                    v-for="tpl in BG_TEMPLATES" 
                                    :key="tpl.id"
                                    @click="cardTemplate = tpl.id" 
                                    :class="['relative w-20 h-20 md:w-full md:h-20 rounded-xl overflow-hidden shrink-0 border-2 transition-all snap-start', cardTemplate === tpl.id ? 'border-emerald-500 shadow-md scale-95 ring-2 ring-emerald-500/20' : 'border-slate-200 dark:border-slate-700 hover:border-emerald-300 hover:scale-95']"
                                >
                                    <div class="absolute inset-0" :style="{ background: tpl.type === 'image' ? `url(${tpl.thumb}) center/cover` : tpl.thumb }"></div>
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
                                    <div v-if="cardTemplate === tpl.id" class="absolute top-1 right-1 bg-emerald-500 text-white rounded-full p-0.5">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <span class="absolute bottom-1.5 left-0 right-0 text-[9px] text-center text-white font-bold drop-shadow-md leading-tight px-1">{{ tpl.name }}</span>
                                </button>
                            </div>

                            <button @click="triggerCustomUpload" class="mt-3 w-full py-2 border-2 border-dashed border-slate-300 dark:border-slate-700 text-slate-500 hover:text-emerald-600 hover:border-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                Unggah Latar Sendiri
                            </button>
                        </div>

                        <!-- ELEMEN KONTEN (TOGGLE ON/OFF) -->
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Tampilkan Elemen</label>
                            <div class="space-y-2.5">
                                <label class="flex items-center justify-between p-2.5 border border-slate-200 dark:border-slate-700 rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Teks Arab</span>
                                    <input type="checkbox" v-model="cardIncludeArab" class="w-4 h-4 text-emerald-600 border-slate-300 rounded focus:ring-emerald-500">
                                </label>
                                <label class="flex items-center justify-between p-2.5 border border-slate-200 dark:border-slate-700 rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Transliterasi Latin</span>
                                    <input type="checkbox" v-model="cardIncludeLatin" class="w-4 h-4 text-emerald-600 border-slate-300 rounded focus:ring-emerald-500">
                                </label>
                                <label class="flex items-center justify-between p-2.5 border border-slate-200 dark:border-slate-700 rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Terjemahan Indonesia</span>
                                    <input type="checkbox" v-model="cardIncludeTranslation" class="w-4 h-4 text-emerald-600 border-slate-300 rounded focus:ring-emerald-500">
                                </label>
                                <label class="flex items-center justify-between p-2.5 border border-slate-200 dark:border-slate-700 rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Sumber Referensi</span>
                                    <input type="checkbox" v-model="cardIncludeSource" class="w-4 h-4 text-emerald-600 border-slate-300 rounded focus:ring-emerald-500">
                                </label>
                            </div>
                        </div>

                        <!-- KONTROL KEGELAPAN OVERLAY (SLIDER) -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Kegelapan Overlay</label>
                                <span class="text-xs font-bold text-emerald-600">{{ cardOverlayDarkness }}%</span>
                            </div>
                            <input 
                                type="range" 
                                v-model="cardOverlayDarkness" 
                                min="10" 
                                max="90" 
                                step="5"
                                class="w-full h-2 bg-slate-200 dark:bg-slate-700 rounded-lg appearance-none cursor-pointer accent-emerald-500"
                            >
                            <div class="flex justify-between text-[10px] text-slate-400 mt-1 px-1 font-medium">
                                <span>Terang</span>
                                <span>Gelap</span>
                            </div>
                            
                            <!-- Presets Kegelapan -->
                            <div class="flex gap-2 mt-3">
                                <button 
                                    v-for="val in [30, 50, 70]" 
                                    :key="val"
                                    @click="cardOverlayDarkness = val"
                                    :class="[
                                        'px-2 py-0.5 rounded-lg text-xs font-bold transition-colors cursor-pointer',
                                        cardOverlayDarkness === val ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'
                                    ]"
                                >
                                    {{ val }}%
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- TOMBOL AKSI MODE GAMBAR (DISATUKAN DALAM SCROLL) -->
                    <div v-if="shareTab === 'image'" class="mt-6 pt-4 border-t border-slate-200 dark:border-slate-700 space-y-3 pb-6">
                
                <!-- Toast Panduan Berbagi Gambar -->
                <transition
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-200 ease-in"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div v-if="imageShareToast" class="rounded-xl p-3 text-xs border bg-amber-50 dark:bg-amber-900/30 border-amber-200 dark:border-amber-700/50 text-amber-900 dark:text-amber-200 space-y-1 shadow-sm absolute -top-16 left-4 right-4 md:left-0 md:right-0 z-50">
                        <div class="font-black flex items-center gap-1.5">
                            <span>📋</span>
                            <span v-if="imageShareToast.copied">Gambar disalin ke clipboard & diunduh!</span>
                            <span v-else>Gambar diunduh ke perangkat Anda!</span>
                        </div>
                        <div class="leading-snug">
                            Buka <strong>{{ imageShareToast.platform }}</strong>, lalu
                            <span v-if="imageShareToast.copied"> tekan <kbd class="font-mono bg-amber-200 dark:bg-amber-800 px-1 rounded">Ctrl+V</kbd> / tahan &amp; <strong>Tempel</strong> untuk melampirkan gambar.</span>
                            <span v-else> lampirkan file gambar yang baru saja diunduh secara manual.</span>
                        </div>
                    </div>
                </transition>

                <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center hidden md:block">
                    Pilih Media Sosial untuk Membagikan:
                </div>

                <!-- 4 Tombol Cepat Medsos -->
                <div class="grid grid-cols-4 gap-2">
                    <button @click="shareImageToApp('whatsapp')" class="flex flex-col items-center justify-center p-2 rounded-2xl bg-[#25D366]/10 hover:bg-[#25D366]/20 text-[#128C7E] border border-[#25D366]/30 transition-all group" aria-label="Bagikan ke WhatsApp">
                        <div class="w-8 h-8 rounded-full bg-[#25D366] text-white flex items-center justify-center mb-1 shadow-sm group-hover:scale-105 transition-transform">
                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                        </div>
                        <span class="text-[10px] font-bold">WhatsApp</span>
                    </button>
                    <button @click="shareImageToApp('telegram')" class="flex flex-col items-center justify-center p-2 rounded-2xl bg-[#0088cc]/10 hover:bg-[#0088cc]/20 text-[#0088cc] border border-[#0088cc]/30 transition-all group" aria-label="Bagikan ke Telegram">
                        <div class="w-8 h-8 rounded-full bg-[#0088cc] text-white flex items-center justify-center mb-1 shadow-sm group-hover:scale-105 transition-transform">
                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.18-.357.34-.635.34l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.121l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.458c.538-.196 1.006.128.832.894z"/></svg>
                        </div>
                        <span class="text-[10px] font-bold">Telegram</span>
                    </button>
                    <button @click="shareImageToApp('twitter')" class="flex flex-col items-center justify-center p-2 rounded-2xl bg-slate-900/10 hover:bg-slate-900/20 text-slate-900 dark:text-slate-200 border border-slate-300 dark:border-slate-700 transition-all group" aria-label="Bagikan ke X / Twitter">
                        <div class="w-8 h-8 rounded-full bg-black text-white flex items-center justify-center mb-1 shadow-sm group-hover:scale-105 transition-transform">
                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        </div>
                        <span class="text-[10px] font-bold">X (Twitter)</span>
                    </button>
                    <button @click="shareImageToApp('facebook')" class="flex flex-col items-center justify-center p-2 rounded-2xl bg-[#1877F2]/10 hover:bg-[#1877F2]/20 text-[#1877F2] border border-[#1877F2]/30 transition-all group" aria-label="Bagikan ke Facebook">
                        <div class="w-8 h-8 rounded-full bg-[#1877F2] text-white flex items-center justify-center mb-1 shadow-sm group-hover:scale-105 transition-transform">
                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M9 8H6v4h3v12h5V12h3.642L18 8h-4V6.333C14 5.374 14.5 5 15.657 5H18V0h-3.808C10.597 0 9 1.582 9 4.615V8z"/></svg>
                        </div>
                        <span class="text-[10px] font-bold">Facebook</span>
                    </button>
                </div>

                <!-- Baris Aksi Unduh Gambar & Salin -->
                <div class="pt-1 flex gap-2">
                    <button 
                        @click="downloadQuoteImage"
                        :disabled="isGeneratingImage"
                        class="flex-1 py-2 px-2 bg-white dark:bg-slate-700 hover:bg-slate-100 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5 shadow-xs disabled:opacity-50"
                    >
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                        <span>Unduh PNG</span>
                    </button>
                    <button 
                        @click="copyQuoteImageToClipboard"
                        :disabled="isGeneratingImage"
                        class="flex-1 py-2 px-2 bg-white dark:bg-slate-700 hover:bg-slate-100 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5 shadow-xs disabled:opacity-50"
                    >
                        <svg v-if="!copyImageSuccess" class="w-4 h-4 text-slate-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10a2 2 0 01-2-2v-4a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2z" /></svg>
                        <svg v-else class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        <span>{{ copyImageSuccess ? 'Tersalin!' : 'Salin' }}</span>
                    </button>
                </div>
                    </div>
                </div>
            </div>
        </div>
    </transition>
</template>
