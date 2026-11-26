<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { IconKoperasi, IconVoucher, IconArsip } from '@/Components/Icons'
import { printReceiptElement } from '@/utils/printReceipt'

const props = defineProps({
    cashier: {
        type: Object,
        default: () => ({ name: 'Petugas Koperasi', role: 'Petugas Koperasi', is_admin: false }),
    },
    products: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
    todayStats: {
        type: Object,
        default: () => ({ sales_count: 0, total_gross: 0, voucher_used: 0, rfid_used: 0, cash_received: 0, date: '' }),
    },
    recentTransactions: {
        type: Array,
        default: () => [],
    },
    storeInfo: {
        type: Object,
        default: () => ({ name: 'Koperasi Rabbani' }),
    },
    lastReceipt: {
        type: Object,
        default: null,
    },
})

// ─── Sound FX Synthesizer (Web Audio API) ────────────────────
const isSoundMuted = ref(false)
let audioCtx = null
const getAudioContext = () => {
    if (!audioCtx && typeof window !== 'undefined') {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext
        if (AudioContextClass) audioCtx = new AudioContextClass()
    }
    return audioCtx
}
const playBeep = (freq = 880, duration = 0.08, type = 'sine') => {
    if (isSoundMuted.value) return
    try {
        const ctx = getAudioContext()
        if (!ctx) return
        if (ctx.state === 'suspended') ctx.resume()
        const osc = ctx.createOscillator()
        const gain = ctx.createGain()
        osc.type = type
        osc.frequency.setValueAtTime(freq, ctx.currentTime)
        gain.gain.setValueAtTime(0.15, ctx.currentTime)
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + duration)
        osc.connect(gain)
        gain.connect(ctx.destination)
        osc.start()
        osc.stop(ctx.currentTime + duration)
    } catch (e) {}
}
const playSuccessSound = () => {
    playBeep(987.77, 0.06)
    setTimeout(() => playBeep(1318.51, 0.12), 70)
}
const playErrorSound = () => {
    playBeep(330, 0.1, 'square')
    setTimeout(() => playBeep(260, 0.15, 'square'), 120)
}

// ─── Format Rupiah ──────────────────────────────────────────
const formatRupiah = (val) => {
    const num = Number(val) || 0
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(num)
}

// ─── Catalog & Filter State ─────────────────────────────────
const selectedCategory = ref('Semua')
const searchProduct = ref('')
const allCategories = computed(() => ['Semua', ...props.categories])

const filteredProducts = computed(() => {
    return props.products.filter(p => {
        const matchCategory = selectedCategory.value === 'Semua' || p.category === selectedCategory.value
        const q = searchProduct.value.toLowerCase().trim()
        const matchSearch = !q || p.name.toLowerCase().includes(q) || (p.barcode && p.barcode.toLowerCase().includes(q))
        return matchCategory && matchSearch
    })
})

// ─── Cart State ─────────────────────────────────────────────
const cart = ref([])

const addToCart = (product) => {
    if (product.stock <= 0) {
        playErrorSound()
        alert('Stok produk habis!')
        return
    }

    const existingIndex = cart.value.findIndex(item => item.id === product.id)
    if (existingIndex > -1) {
        if (cart.value[existingIndex].qty < product.stock) {
            cart.value[existingIndex].qty++
            cart.value[existingIndex].subtotal = cart.value[existingIndex].qty * cart.value[existingIndex].price
            playBeep(1100, 0.05)
        } else {
            playErrorSound()
            alert('Jumlah di keranjang sudah mencapai batas stok!')
        }
    } else {
        cart.value.push({
            id: product.id,
            name: product.name,
            price: Number(product.price),
            qty: 1,
            unit: product.unit || 'Pcs',
            subtotal: Number(product.price),
            max_stock: product.stock,
        })
        playBeep(880, 0.05)
    }
}

const updateQty = (index, delta) => {
    const item = cart.value[index]
    const newQty = item.qty + delta
    if (newQty <= 0) {
        removeFromCart(index)
    } else if (newQty <= item.max_stock) {
        item.qty = newQty
        item.subtotal = item.qty * item.price
        playBeep(900, 0.04)
    } else {
        playErrorSound()
        alert('Jumlah melebihi stok yang tersedia!')
    }
}

const removeFromCart = (index) => {
    cart.value.splice(index, 1)
    playBeep(440, 0.05)
}

const clearCart = () => {
    if (cart.value.length === 0) return
    if (confirm('Kosongkan keranjang belanja?')) {
        cart.value = []
        appliedVoucher.value = null
        playBeep(400, 0.08)
    }
}

// ─── Customer / RFID & Voucher State ────────────────────────
const scannerInput = ref('')
const scannerInputRef = ref(null)
const isScanning = ref(false)
const customer = ref(null)
const appliedVoucher = ref(null)
const scanNotification = ref({ type: '', message: '' })

const showScanNotice = (msg, type = 'success') => {
    scanNotification.value = { type, message: msg }
    setTimeout(() => {
        scanNotification.value = { type: '', message: '' }
    }, 4000)
}

// Handle Scanner: Bisa membaca barcode produk, kartu RFID presensi, atau kode voucher!
const handleGlobalScanner = async () => {
    const raw = scannerInput.value.trim()
    if (!raw) return
    scannerInput.value = ''
    isScanning.value = true

    // 1. Cek apakah barcode produk
    const productMatch = props.products.find(p => p.barcode === raw)
    if (productMatch) {
        addToCart(productMatch)
        showScanNotice(`Produk "${productMatch.name}" ditambahkan ke keranjang.`)
        isScanning.value = false
        return
    }

    // 2. Cek apakah kode voucher habit (berawalan SDAM-VCR atau VCR)
    if (raw.toUpperCase().includes('VCR') || raw.startsWith('{')) {
        await checkVoucherCode(raw)
        isScanning.value = false
        return
    }

    // 3. Cek apakah kartu RFID presensi guru atau NIP
    await lookupRfidCard(raw)
    isScanning.value = false
}

// Lookup RFID Card / NIP Guru
const lookupRfidCard = async (rfidOrNip) => {
    try {
        const res = await fetch(route('koperasi.pos.rfid-scan'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ rfid_uid: rfidOrNip }),
        })
        const data = await res.json()
        if (res.ok && data.success) {
            customer.value = data.customer
            playSuccessSound()
            showScanNotice(`Kartu Presensi RFID ${data.customer.name} terdeteksi! Saldo: ${data.customer.formatted_balance}`)
        } else {
            // Jika bukan RFID, coba cek apakah voucher
            await checkVoucherCode(rfidOrNip)
        }
    } catch (e) {
        playErrorSound()
        showScanNotice('Gagal membaca kartu RFID.', 'error')
    }
}

// Lookup Voucher Code
const checkVoucherCode = async (code) => {
    try {
        const res = await fetch(route('koperasi.pos.check'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ code }),
        })
        const data = await res.json()
        if (res.ok && data.success && data.status === 'valid') {
            appliedVoucher.value = data.voucher
            if (!customer.value && data.voucher.user) {
                customer.value = {
                    id: data.voucher.user.id,
                    name: data.voucher.user.name,
                    nip: data.voucher.user.nip,
                    divisi: data.voucher.user.divisi,
                    rfid_uid: data.voucher.user.rfid_uid,
                    coop_balance: data.voucher.user.coop_balance || 0,
                    formatted_balance: formatRupiah(data.voucher.user.coop_balance || 0),
                }
            }
            playSuccessSound()
            showScanNotice(`Voucher ${data.voucher.code} valid senilai ${data.voucher.formatted_value}!`)
        } else {
            playErrorSound()
            showScanNotice(data.message || 'Kode tidak dikenali.', 'error')
        }
    } catch (e) {
        playErrorSound()
        showScanNotice('Gagal memvalidasi kode voucher.', 'error')
    }
}

// Konversi voucher ke saldo kartu RFID langsung dari POS
const isConvertingVoucher = ref(false)
const convertVoucherToCardBalance = async () => {
    if (!appliedVoucher.value) return
    isConvertingVoucher.value = true
    try {
        const res = await fetch(route('koperasi.pos.convert-voucher'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                voucher_code: appliedVoucher.value.code,
                user_id: customer.value?.id,
            }),
        })
        const data = await res.json()
        if (res.ok && data.success) {
            playSuccessSound()
            if (customer.value) {
                customer.value.coop_balance = data.new_balance
                customer.value.formatted_balance = data.formatted_balance
            }
            appliedVoucher.value = null
            showScanNotice(data.message)
        } else {
            playErrorSound()
            alert(data.message || 'Gagal mentransfer voucher ke saldo kartu.')
        }
    } catch (e) {
        playErrorSound()
        alert('Terjadi kesalahan jaringan.')
    } finally {
        isConvertingVoucher.value = false
    }
}

const removeCustomer = () => {
    customer.value = null
    appliedVoucher.value = null
}

const removeVoucher = () => {
    appliedVoucher.value = null
    playBeep(500, 0.05)
}

// ─── Financial Calculations ─────────────────────────────────
const cartTotalGross = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.subtotal, 0)
})

const voucherDiscount = computed(() => {
    if (!appliedVoucher.value) return 0
    const val = Number(appliedVoucher.value.reward_numeric) || 50000
    return Math.min(val, cartTotalGross.value)
})

const cartNetPayable = computed(() => {
    return Math.max(0, cartTotalGross.value - voucherDiscount.value)
})

// ─── Checkout & Payment Modal State ─────────────────────────
const showPaymentModal = ref(false)
const paymentMethod = ref('cash') // 'cash', 'rfid_card', 'voucher', 'split', 'qris'
const cashReceived = ref(0)
const checkoutNotes = ref('')
const isProcessing = ref(false)

const openPaymentModal = () => {
    if (cart.value.length === 0) return
    cashReceived.value = cartNetPayable.value
    // Default smart payment method
    if (cartNetPayable.value === 0 && voucherDiscount.value > 0) {
        paymentMethod.value = 'voucher'
    } else if (customer.value && customer.value.coop_balance >= cartNetPayable.value) {
        paymentMethod.value = 'rfid_card'
    } else {
        paymentMethod.value = 'cash'
    }
    showPaymentModal.value = true
}

// Quick cash buttons
const setQuickCash = (amount) => {
    if (amount === 'pas') {
        cashReceived.value = cartNetPayable.value
    } else {
        cashReceived.value = Number(amount)
    }
}

const changeAmount = computed(() => {
    if (paymentMethod.value === 'cash') {
        return Math.max(0, (Number(cashReceived.value) || 0) - cartNetPayable.value)
    } else if (paymentMethod.value === 'split') {
        const remaining = Math.max(0, cartNetPayable.value - (customer.value?.coop_balance || 0))
        return Math.max(0, (Number(cashReceived.value) || 0) - remaining)
    }
    return 0
})

// Submit Checkout
const submitCheckout = () => {
    if (cart.value.length === 0 || isProcessing.value) return

    // Validasi saldo jika bayar pakai kartu RFID
    if (paymentMethod.value === 'rfid_card') {
        if (!customer.value) {
            alert('Wajib tap/masukkan kartu RFID guru terlebih dahulu!')
            return
        }
        if (customer.value.coop_balance < cartNetPayable.value) {
            alert('Saldo kartu RFID tidak mencukupi! Silakan pilih metode Split (Kartu + Tunai).')
            return
        }
    }

    isProcessing.value = true

    router.post(route('koperasi.pos.checkout'), {
        items: cart.value.map(i => ({
            id: i.id,
            name: i.name,
            price: i.price,
            qty: i.qty,
            subtotal: i.subtotal,
        })),
        total_amount: cartTotalGross.value,
        payment_method: paymentMethod.value,
        customer_id: customer.value?.id || null,
        voucher_code: appliedVoucher.value?.code || null,
        cash_paid: Number(cashReceived.value) || 0,
        notes: checkoutNotes.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            playSuccessSound()
            cart.value = []
            appliedVoucher.value = null
            customer.value = null
            showPaymentModal.value = false
            isProcessing.value = false
            activeReceipt.value = props.lastReceipt || null
        },
        onError: (err) => {
            playErrorSound()
            isProcessing.value = false
        },
    })
}

// ─── Thermal Receipt Modal ──────────────────────────────────
const activeReceipt = ref(null)

const printReceipt = () => {
    printReceiptElement('receipt-print-area')
}

// ─── Keyboard Shortcuts ─────────────────────────────────────
const handleKeyDown = (e) => {
    if (e.key === 'F2') {
        e.preventDefault()
        scannerInputRef.value?.focus()
    }
    if (e.key === 'F4') {
        e.preventDefault()
        if (cart.value.length > 0 && !showPaymentModal.value) {
            openPaymentModal()
        }
    }
    if (e.key === 'Escape') {
        if (showPaymentModal.value) showPaymentModal.value = false
        if (activeReceipt.value) activeReceipt.value = null
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown)
    nextTick(() => {
        scannerInputRef.value?.focus()
    })
    if (props.lastReceipt) {
        activeReceipt.value = props.lastReceipt
    }
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown)
})
</script>

<template>
    <Head title="POS Koperasi Rabbani — SD Muhammadiyah 1 Krian" />

    <PosLayout>
        <!-- ── Top Notification Banner ── -->
        <div
            v-if="scanNotification.message"
            class="px-4 py-2 text-xs font-bold text-center transition-all z-40"
            :class="scanNotification.type === 'error' ? 'bg-rose-600 text-white' : 'bg-emerald-600 text-white'"
        >
            {{ scanNotification.message }}
        </div>

        <!-- ── Main POS Workspace: Two Column Layout ── -->
        <div class="flex-1 flex flex-col lg:flex-row overflow-hidden bg-slate-100">

            <!-- ══════════════════════════════════════════════════════════
                 LEFT PANEL: Product Catalog & Barcode Scanner (Flex-1)
                 ══════════════════════════════════════════════════════════ -->
            <div class="flex-1 flex flex-col min-w-0 border-r border-slate-200 bg-slate-50">

                <!-- 1. Universal Scanner & Search Bar -->
                <div class="p-3 bg-white border-b border-slate-200 flex flex-col sm:flex-row gap-2.5 items-center shadow-xs">
                    <!-- Scanner Input (Autofocus USB RFID & Barcode Scanner) -->
                    <div class="relative flex-1 w-full">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-emerald-600">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                        </div>
                        <input
                            ref="scannerInputRef"
                            type="text"
                            v-model="scannerInput"
                            @keydown.enter.prevent="handleGlobalScanner"
                            autocomplete="off"
                            placeholder="Tap Kartu RFID Guru / Scan Barcode Produk / Scan Voucher (Enter)..."
                            class="w-full pl-9 pr-20 py-2.5 bg-slate-50 hover:bg-white border border-emerald-500/50 rounded-xl text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 font-mono transition-all"
                        />
                        <button
                            type="button"
                            @click="handleGlobalScanner"
                            class="absolute inset-y-1 right-1 px-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition-colors shadow-xs"
                        >
                            Scan
                        </button>
                    </div>

                    <!-- Search filter product catalog -->
                    <div class="relative w-full sm:w-56">
                        <input
                            type="text"
                            v-model="searchProduct"
                            placeholder="Cari nama barang..."
                            class="w-full px-3 py-2 bg-slate-50 hover:bg-white border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-slate-400 transition-all"
                        />
                    </div>
                </div>

                <!-- 2. Category Filter Pills -->
                <div class="px-3 py-2 bg-white/80 border-b border-slate-200 flex items-center gap-1.5 overflow-x-auto scrollbar-none">
                    <button
                        v-for="cat in allCategories"
                        :key="cat"
                        type="button"
                        @click="selectedCategory = cat"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all whitespace-nowrap"
                        :class="selectedCategory === cat ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/20' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 hover:text-slate-900'"
                    >
                        {{ cat }}
                    </button>
                </div>

                <!-- 3. Product Grid (Waroong Catalog) -->
                <div class="flex-1 p-3 overflow-y-auto">
                    <div v-if="filteredProducts.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-2.5">
                        <div
                            v-for="product in filteredProducts"
                            :key="product.id"
                            @click="addToCart(product)"
                            class="group relative bg-white hover:bg-emerald-50/20 border border-slate-200 hover:border-emerald-400/80 rounded-xl p-3 flex flex-col justify-between cursor-pointer transition-all duration-150 active:scale-95 shadow-xs hover:shadow-sm"
                            :class="{'opacity-50 pointer-events-none': product.stock <= 0}"
                        >
                            <div>
                                <div class="flex items-start justify-between gap-1 mb-1.5">
                                    <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">
                                        {{ product.category }}
                                    </span>
                                    <span
                                        class="text-[9px] font-bold px-1.5 py-0.5 rounded"
                                        :class="product.stock > 10 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/60' : (product.stock > 0 ? 'bg-amber-50 text-amber-700 border border-amber-200/60' : 'bg-rose-50 text-rose-700 border border-rose-200/60')"
                                    >
                                        {{ product.stock > 0 ? `Stok ${product.stock}` : 'Habis' }}
                                    </span>
                                </div>
                                <h4 class="text-xs font-bold text-slate-800 group-hover:text-emerald-700 line-clamp-2 leading-tight">
                                    {{ product.name }}
                                </h4>
                            </div>

                            <div class="mt-3 pt-2 border-t border-slate-100 flex items-center justify-between">
                                <span class="text-xs font-black text-emerald-600">
                                    {{ formatRupiah(product.price) }}
                                </span>
                                <span class="w-6 h-6 rounded-lg bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white flex items-center justify-center text-xs font-bold transition-colors">
                                    +
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-else class="h-full flex flex-col items-center justify-center text-slate-400 text-xs py-12">
                        <svg class="w-12 h-12 mb-2 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                        <p>Tidak ada produk yang cocok dengan pencarian.</p>
                    </div>
                </div>

            </div>

            <!-- ══════════════════════════════════════════════════════════
                 RIGHT PANEL: POS Shopping Cart & Register (w-full lg:w-96)
                 ══════════════════════════════════════════════════════════ -->
            <div class="w-full lg:w-96 flex flex-col bg-white border-l border-slate-200 h-full flex-shrink-0 shadow-sm">

                <!-- 1. Customer & RFID Card / Voucher Bar -->
                <div class="p-3 bg-slate-50/80 border-b border-slate-200 space-y-2">

                    <!-- Customer (Guru/Pegawai) Badge -->
                    <div v-if="customer" class="p-2.5 rounded-xl bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200 text-slate-800 relative shadow-xs">
                        <button
                            type="button"
                            @click="removeCustomer"
                            class="absolute top-2 right-2 text-slate-400 hover:text-rose-500 p-0.5"
                            title="Hapus Pelanggan"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>

                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-lg bg-emerald-600 text-white font-black text-xs flex items-center justify-center flex-shrink-0 shadow-xs">
                                {{ customer.name.charAt(0) }}
                            </div>
                            <div class="min-w-0 pr-4">
                                <div class="text-xs font-bold text-slate-900 truncate">{{ customer.name }}</div>
                                <div class="text-[10px] text-slate-500">NIP: {{ customer.nip }} • {{ customer.divisi }}</div>
                            </div>
                        </div>

                        <!-- Saldo Kartu RFID Guru -->
                        <div class="mt-2 pt-2 border-t border-emerald-200/80 flex items-center justify-between text-xs">
                            <span class="text-[10px] font-bold uppercase text-emerald-800 flex items-center gap-1">
                                <svg class="w-3 h-3 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                Saldo Kartu RFID:
                            </span>
                            <span class="font-black text-emerald-700 text-sm tracking-tight">{{ customer.formatted_balance }}</span>
                        </div>
                    </div>

                    <!-- Scan / Input Prompt if no customer -->
                    <div v-else class="flex items-center justify-between p-2 rounded-xl bg-white border border-dashed border-slate-300 text-xs text-slate-600">
                        <div class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                            <span class="font-medium">Pelanggan Umum (Tanpa Kartu)</span>
                        </div>
                        <span class="text-[10px] text-slate-400">Tap RFID untuk guru</span>
                    </div>

                    <!-- Applied Habit Voucher Badge -->
                    <div v-if="appliedVoucher" class="p-2.5 rounded-xl bg-amber-50 border border-amber-200 text-xs space-y-1.5 relative shadow-xs">
                        <button
                            type="button"
                            @click="removeVoucher"
                            class="absolute top-2 right-2 text-slate-400 hover:text-rose-500 p-0.5"
                            title="Hapus Voucher"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>

                        <div class="flex items-center gap-1.5 text-amber-800 font-bold">
                            <IconVoucher class="w-4 h-4 text-amber-600" />
                            <span>Voucher Apresiasi Habit</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="font-mono text-[11px] font-bold text-slate-700">{{ appliedVoucher.code }}</span>
                            <span class="font-black text-amber-700">{{ appliedVoucher.formatted_value }}</span>
                        </div>

                        <!-- Option to transfer voucher into RFID card balance -->
                        <div class="pt-1.5 border-t border-amber-200 flex gap-1">
                            <button
                                type="button"
                                @click="convertVoucherToCardBalance"
                                :disabled="isConvertingVoucher"
                                class="w-full py-1 px-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold text-[10px] transition-colors flex items-center justify-center gap-1 shadow-xs"
                            >
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                <span>{{ isConvertingVoucher ? 'Mentransfer...' : 'Masukkan ke Saldo Kartu RFID' }}</span>
                            </button>
                        </div>
                    </div>

                </div>

                <!-- 2. Cart Items Table -->
                <div class="flex-1 p-3 overflow-y-auto">
                    <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-200 text-xs">
                        <span class="font-bold text-slate-700">Keranjang Belanja ({{ cart.length }})</span>
                        <button
                            v-if="cart.length > 0"
                            type="button"
                            @click="clearCart"
                            class="text-[11px] text-rose-600 hover:underline font-medium"
                        >
                            Kosongkan
                        </button>
                    </div>

                    <div v-if="cart.length > 0" class="space-y-2">
                        <div
                            v-for="(item, idx) in cart"
                            :key="item.id"
                            class="p-2 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between text-xs hover:border-slate-300 transition-colors"
                        >
                            <div class="min-w-0 pr-2 flex-1">
                                <div class="font-bold text-slate-800 truncate">{{ item.name }}</div>
                                <div class="text-[11px] text-slate-500 font-mono">{{ formatRupiah(item.price) }} / {{ item.unit }}</div>
                            </div>

                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                <button
                                    type="button"
                                    @click="updateQty(idx, -1)"
                                    class="w-6 h-6 rounded bg-white hover:bg-slate-100 text-slate-700 font-bold flex items-center justify-center border border-slate-200"
                                >
                                    -
                                </button>
                                <span class="w-7 text-center font-mono font-bold text-slate-800">{{ item.qty }}</span>
                                <button
                                    type="button"
                                    @click="updateQty(idx, 1)"
                                    class="w-6 h-6 rounded bg-white hover:bg-slate-100 text-slate-700 font-bold flex items-center justify-center border border-slate-200"
                                >
                                    +
                                </button>
                                <span class="font-mono font-black text-emerald-600 w-16 text-right">
                                    {{ formatRupiah(item.subtotal) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-else class="h-40 flex flex-col items-center justify-center text-slate-400 text-xs">
                        <svg class="w-8 h-8 mb-1 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                        <p class="font-medium text-slate-500">Keranjang masih kosong</p>
                        <p class="text-[10px] text-slate-400">Klik produk untuk menambahkan</p>
                    </div>
                </div>

                <!-- 3. Cart Checkout Summary & Action -->
                <div class="p-3.5 bg-slate-50 border-t border-slate-200 space-y-2.5">
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between text-slate-600">
                            <span>Subtotal Belanja:</span>
                            <span class="font-mono font-bold text-slate-800">{{ formatRupiah(cartTotalGross) }}</span>
                        </div>
                        <div v-if="voucherDiscount > 0" class="flex justify-between text-amber-700 font-bold">
                            <span>Voucher Habit:</span>
                            <span class="font-mono">- {{ formatRupiah(voucherDiscount) }}</span>
                        </div>
                        <div class="flex justify-between text-base font-black text-slate-900 pt-1 border-t border-slate-200">
                            <span>Total Tagihan:</span>
                            <span class="text-emerald-600 font-mono text-lg">{{ formatRupiah(cartNetPayable) }}</span>
                        </div>
                    </div>

                    <!-- Checkout Button (Waroong POS Big Button) -->
                    <button
                        type="button"
                        @click="openPaymentModal"
                        :disabled="cart.length === 0"
                        class="w-full py-3.5 rounded-xl font-black text-white text-sm bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 active:scale-[0.99] disabled:opacity-40 disabled:cursor-not-allowed shadow-md shadow-emerald-600/20 transition-all flex items-center justify-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        <span>BAYAR SEKARANG (F4)</span>
                    </button>
                </div>

            </div>

        </div>

        <!-- ══════════════════════════════════════════════════════════
             MODAL PEMBAYARAN KASIR (WAROONG POS MULTI-PAYMENT)
             ══════════════════════════════════════════════════════════ -->
        <div
            v-if="showPaymentModal"
            class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
            @click.self="showPaymentModal = false"
        >
            <div class="bg-white border border-slate-200 rounded-2xl max-w-lg w-full p-6 shadow-2xl relative space-y-5 text-slate-800">

                <!-- Header Modal -->
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-base font-black text-slate-900">Pembayaran Kasir POS</h3>
                        <p class="text-xs text-slate-500">Total Tagihan: <strong class="text-emerald-600 text-sm">{{ formatRupiah(cartNetPayable) }}</strong></p>
                    </div>
                    <button type="button" @click="showPaymentModal = false" class="text-slate-400 hover:text-slate-600 p-1">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Metode Pembayaran Selector -->
                <div class="space-y-2">
                    <label class="text-xs font-bold text-slate-600 uppercase tracking-wider block">Pilih Metode Pembayaran</label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <!-- Tunai -->
                        <button
                            type="button"
                            @click="paymentMethod = 'cash'"
                            class="p-3 rounded-xl border text-center transition-all"
                            :class="paymentMethod === 'cash' ? 'bg-emerald-50 border-emerald-500 text-emerald-800 font-black shadow-xs' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'"
                        >
                            <span class="text-lg block">💵</span>
                            <span class="text-xs mt-1 block">Tunai</span>
                        </button>

                        <!-- Saldo Kartu RFID -->
                        <button
                            type="button"
                            @click="paymentMethod = 'rfid_card'"
                            class="p-3 rounded-xl border text-center transition-all"
                            :class="paymentMethod === 'rfid_card' ? 'bg-emerald-50 border-emerald-500 text-emerald-800 font-black shadow-xs' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'"
                        >
                            <span class="text-lg block">💳</span>
                            <span class="text-xs mt-1 block">Kartu RFID</span>
                        </button>

                        <!-- Split (Kartu RFID + Tunai) -->
                        <button
                            type="button"
                            @click="paymentMethod = 'split'"
                            class="p-3 rounded-xl border text-center transition-all"
                            :class="paymentMethod === 'split' ? 'bg-emerald-50 border-emerald-500 text-emerald-800 font-black shadow-xs' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'"
                        >
                            <span class="text-lg block">⚖️</span>
                            <span class="text-xs mt-1 block">RFID + Tunai</span>
                        </button>

                        <!-- QRIS -->
                        <button
                            type="button"
                            @click="paymentMethod = 'qris'"
                            class="p-3 rounded-xl border text-center transition-all"
                            :class="paymentMethod === 'qris' ? 'bg-emerald-50 border-emerald-500 text-emerald-800 font-black shadow-xs' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'"
                        >
                            <span class="text-lg block">📱</span>
                            <span class="text-xs mt-1 block">QRIS</span>
                        </button>
                    </div>
                </div>

                <!-- Input Tunai (Jika Cash atau Split) -->
                <div v-if="paymentMethod === 'cash' || paymentMethod === 'split'" class="space-y-3 p-4 bg-slate-50 rounded-xl border border-slate-200">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">
                            {{ paymentMethod === 'split' ? 'Nominal Tunai Tambahan (Rp)' : 'Nominal Uang Tunai Diterima (Rp)' }}
                        </label>
                        <input
                            type="number"
                            v-model.number="cashReceived"
                            step="1000"
                            class="w-full px-4 py-2.5 bg-white border border-slate-300 rounded-xl font-mono text-xl font-black text-slate-900 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                        />
                    </div>

                    <!-- Quick Cash Chips -->
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            type="button"
                            @click="setQuickCash('pas')"
                            class="px-2.5 py-1 rounded bg-white hover:bg-slate-100 border border-slate-200 text-xs font-bold text-slate-700"
                        >
                            Uang Pas
                        </button>
                        <button
                            v-for="amt in [10000, 20000, 50000, 100000]"
                            :key="amt"
                            type="button"
                            @click="setQuickCash(amt)"
                            class="px-2.5 py-1 rounded bg-white hover:bg-slate-100 border border-slate-200 text-xs font-bold text-slate-700"
                        >
                            {{ formatRupiah(amt) }}
                        </button>
                    </div>

                    <!-- Kembalian Display -->
                    <div class="pt-2 border-t border-slate-200 flex justify-between items-center text-xs">
                        <span class="text-slate-600 font-medium">Kembalian:</span>
                        <span class="text-lg font-black text-emerald-600 font-mono">{{ formatRupiah(changeAmount) }}</span>
                    </div>
                </div>

                <!-- Info Saldo Kartu RFID jika memilih RFID -->
                <div v-if="paymentMethod === 'rfid_card' || paymentMethod === 'split'" class="p-4 bg-emerald-50 rounded-xl border border-emerald-200 text-xs space-y-1">
                    <div class="font-bold text-emerald-800 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                        <span>Pembayaran Saldo Kartu RFID Presensi Guru</span>
                    </div>
                    <p v-if="customer" class="text-slate-700">Guru: <strong>{{ customer.name }}</strong> (Saldo: <strong class="text-emerald-700">{{ customer.formatted_balance }}</strong>)</p>
                    <p v-else class="text-amber-700 font-medium">⚠️ Silakan tap kartu RFID guru pada input scanner terlebih dahulu.</p>
                </div>

                <!-- Action Button Checkout -->
                <div class="flex gap-2.5 pt-2">
                    <button
                        type="button"
                        @click="showPaymentModal = false"
                        class="w-1/3 py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-xs font-bold text-slate-700 transition-colors"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="submitCheckout"
                        :disabled="isProcessing"
                        class="w-2/3 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black shadow-md shadow-emerald-600/20 transition-all flex items-center justify-center gap-1.5"
                    >
                        <span>{{ isProcessing ? 'MEMPROSES...' : 'SELESAIKAN & CETAK STRUK' }}</span>
                    </button>
                </div>

            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════
             STRUK THERMAL PRINTER MODAL (58mm / 80mm AUTO-PRINT)
             ══════════════════════════════════════════════════════════ -->
        <div
            v-if="activeReceipt"
            class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
            @click.self="activeReceipt = null"
        >
            <div class="bg-white text-slate-900 rounded-2xl max-w-sm w-full p-5 shadow-2xl relative space-y-4">
                <button
                    type="button"
                    @click="activeReceipt = null"
                    class="absolute top-3 right-3 text-slate-400 hover:text-slate-600 p-1"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>

                <!-- Thermal Paper Layout -->
                <div id="receipt-print-area" class="font-mono text-xs text-slate-800 space-y-2.5 pb-2 border-b border-dashed border-slate-300">
                    <div class="text-center space-y-0.5">
                        <h3 class="font-black text-sm uppercase tracking-tight">{{ activeReceipt.store_name }}</h3>
                        <p class="text-[10px] text-slate-500">{{ activeReceipt.store_address }}</p>
                        <p class="text-[10px] text-slate-500">Telp: {{ activeReceipt.store_phone }}</p>
                    </div>

                    <div class="border-t border-b border-dashed border-slate-300 py-1.5 space-y-0.5 text-[10px]">
                        <div class="flex justify-between">
                            <span>No. TRX:</span>
                            <span class="font-bold">{{ activeReceipt.transaction_code }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Tanggal:</span>
                            <span>{{ activeReceipt.date_time }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Kasir:</span>
                            <span>{{ activeReceipt.cashier_name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Pelanggan:</span>
                            <span class="font-bold">{{ activeReceipt.customer_name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Metode:</span>
                            <span class="uppercase font-bold">{{ activeReceipt.payment_method }}</span>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div class="space-y-1 text-[11px]">
                        <div v-for="(it, i) in activeReceipt.items" :key="i" class="flex justify-between">
                            <span>{{ it.name }} x{{ it.qty }}</span>
                            <span class="font-bold">{{ formatRupiah(it.subtotal) }}</span>
                        </div>

                        <div class="border-t border-dashed border-slate-300 pt-1 flex justify-between font-bold">
                            <span>Subtotal:</span>
                            <span>{{ formatRupiah(activeReceipt.total_gross) }}</span>
                        </div>

                        <div v-if="activeReceipt.voucher_amount > 0" class="flex justify-between text-emerald-700 font-bold">
                            <span>Voucher Habit:</span>
                            <span>- {{ formatRupiah(activeReceipt.voucher_amount) }}</span>
                        </div>

                        <div class="flex justify-between font-black text-sm border-t border-slate-400 pt-1">
                            <span>Total Bayar:</span>
                            <span>{{ formatRupiah(activeReceipt.payable_amount) }}</span>
                        </div>

                        <div v-if="activeReceipt.rfid_paid_amount > 0" class="flex justify-between text-[10px]">
                            <span>Dibayar Saldo RFID:</span>
                            <span>{{ formatRupiah(activeReceipt.rfid_paid_amount) }}</span>
                        </div>

                        <div v-if="activeReceipt.cash_paid_amount > 0" class="flex justify-between text-[10px]">
                            <span>Dibayar Tunai:</span>
                            <span>{{ formatRupiah(activeReceipt.cash_paid_amount) }}</span>
                        </div>

                        <div v-if="activeReceipt.change_amount > 0" class="flex justify-between font-bold text-[10px]">
                            <span>Kembalian Tunai:</span>
                            <span>{{ formatRupiah(activeReceipt.change_amount) }}</span>
                        </div>

                        <div v-if="activeReceipt.customer_balance !== null && activeReceipt.customer_balance !== undefined" class="flex justify-between text-[10px] font-bold text-emerald-800 pt-1 border-t border-dashed border-slate-300">
                            <span>Sisa Saldo Kartu RFID:</span>
                            <span>{{ formatRupiah(activeReceipt.customer_balance) }}</span>
                        </div>
                    </div>

                    <div class="text-center pt-2 text-[9px] text-slate-500 whitespace-pre-line leading-relaxed">
                        {{ activeReceipt.receipt_footer }}
                    </div>
                </div>

                <div class="flex gap-2">
                    <button
                        type="button"
                        @click="printReceipt"
                        class="flex-1 py-2.5 px-4 rounded-xl font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors text-xs flex items-center justify-center gap-1"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                        <span>Cetak Struk Thermal</span>
                    </button>
                    <button
                        type="button"
                        @click="activeReceipt = null"
                        class="py-2.5 px-4 rounded-xl font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition-colors text-xs"
                    >
                        Selesai
                    </button>
                </div>
            </div>
        </div>

    </PosLayout>
</template>
