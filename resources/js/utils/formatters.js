const numberFmt = new Intl.NumberFormat('id-ID')
const currencyFmt = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
})

export function formatNumber(val) {
    return numberFmt.format(Number(val) || 0)
}

export function formatRupiah(val) {
    return currencyFmt.format(Number(val) || 0)
}

export function formatPercent(val, decimals = 0) {
    const num = Number(val) || 0
    return `${num.toFixed(decimals)}%`
}

export function formatShortDate(dateStr) {
    if (!dateStr) return '-'
    const d = new Date(dateStr)
    if (Number.isNaN(d.getTime())) return String(dateStr)
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
}

export function formatTime(timeStr) {
    if (!timeStr) return '--:--'
    return String(timeStr).slice(0, 5)
}
