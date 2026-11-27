<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

const props = defineProps({
    discounts: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ total: 0, active_now: 0, event_only: 0 }) },
    filters: { type: Object, default: () => ({ search: '', status: 'all' }) },
})

const search = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || 'all')

const applyFilters = () => {
    router.get(route('koperasi.discounts'), {
        search: search.value || undefined,
        status: statusFilter.value,
    }, { preserveState: true, replace: true })
}

const formatRupiah = (val) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
}).format(Number(val) || 0)

const memberTypeLabel = (type) => {
    switch (type) {
        case 'pegawai': return 'Pegawai / Guru'
        case 'wali_murid': return 'Wali Murid'
        case 'siswa': return 'Siswa'
        case 'umum': return 'Member Umum'
        case 'registered': return 'Semua Member & Pegawai'
        default: return 'Semua Pelanggan'
    }
}

const showModal = ref(false)
const editingDiscount = ref(null)

const form = useForm({
    name: '',
    discount_type: 'percentage',
    discount_value: 10,
    max_discount: '',
    min_purchase: 0,
    member_type: '',
    product_category: '',
    start_date: '',
    end_date: '',
    is_active: true,
    notes: '',
})

const openCreate = () => {
    editingDiscount.value = null
    form.reset()
    form.clearErrors()
    form.discount_type = 'percentage'
    form.discount_value = 10
    form.min_purchase = 0
    form.is_active = true
    showModal.value = true
}

const openEdit = (d) => {
    editingDiscount.value = d
    form.clearErrors()
    form.name = d.name
    form.discount_type = d.discount_type
    form.discount_value = Number(d.discount_value)
    form.max_discount = d.max_discount !== null ? Number(d.max_discount) : ''
    form.min_purchase = Number(d.min_purchase || 0)
    form.member_type = d.member_type || ''
    form.product_category = d.product_category || ''
    form.start_date = d.start_date || ''
    form.end_date = d.end_date || ''
    form.is_active = Boolean(d.is_active)
    form.notes = d.notes || ''
    showModal.value = true
}

const submitForm = () => {
    const payload = {
        ...form.data(),
        max_discount: form.discount_type === 'percentage' && form.max_discount !== '' ? Number(form.max_discount) : null,
        min_purchase: form.min_purchase !== '' ? Number(form.min_purchase) : 0,
        member_type: form.member_type || null,
        product_category: form.product_category || null,
        start_date: form.start_date || null,
        end_date: form.end_date || null,
    }

    if (editingDiscount.value) {
        form.transform(() => payload).put(route('koperasi.discounts.update', editingDiscount.value.id), {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false },
        })
    } else {
        form.transform(() => payload).post(route('koperasi.discounts.store'), {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false },
        })
    }
}

const toggleDiscount = (d) => {
    router.patch(route('koperasi.discounts.toggle', d.id), {}, { preserveScroll: true })
}

const deleteDiscount = (d) => {
    if (!confirm(`Hapus promo diskon "${d.name}"?`)) return
    router.delete(route('koperasi.discounts.destroy', d.id), { preserveScroll: true })
}
</script>

<template>
    <Head title="Diskon & Promo - Koperasi Rabbani" />
    <PosLayout title="Diskon & Promo Koperasi">
        <div class="p-4 sm:p-6 max-w-6xl mx-auto space-y-5">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-black text-slate-900">Pengaturan Diskon & Promo</h1>
                    <p class="text-xs text-slate-500">Atur diskon otomatis berdasarkan event tanggal, jenis member, dan kategori barang.</p>
                </div>
                <button
                    type="button"
                    @click="openCreate"
                    class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black shadow-sm transition-colors"
                >
                    + Tambah Promo Diskon
                </button>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="bg-white border border-slate-200 rounded-2xl p-4">
                    <div class="text-[11px] font-bold text-slate-400 uppercase">Total Promo</div>
                    <div class="text-2xl font-black text-slate-900 mt-1">{{ stats.total }}</div>
                </div>
                <div class="bg-white border border-emerald-200 rounded-2xl p-4">
                    <div class="text-[11px] font-bold text-emerald-600 uppercase">Aktif Berlaku Hari Ini</div>
                    <div class="text-2xl font-black text-emerald-700 mt-1">{{ stats.active_now }}</div>
                </div>
                <div class="bg-white border border-amber-200 rounded-2xl p-4">
                    <div class="text-[11px] font-bold text-amber-600 uppercase">Promo Event Bertanggal</div>
                    <div class="text-2xl font-black text-amber-700 mt-1">{{ stats.event_only }}</div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="bg-white border border-slate-200 rounded-2xl p-3 flex flex-col sm:flex-row gap-2.5">
                <input
                    v-model="search"
                    @keyup.enter="applyFilters"
                    type="text"
                    placeholder="Cari nama promo atau kategori..."
                    class="flex-1 px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500"
                />
                <select
                    v-model="statusFilter"
                    @change="applyFilters"
                    class="px-3 py-2 text-xs border border-slate-200 rounded-xl bg-white focus:outline-none focus:border-emerald-500"
                >
                    <option value="all">Semua Status</option>
                    <option value="active">Aktif Hari Ini</option>
                    <option value="inactive">Nonaktif</option>
                </select>
                <button
                    type="button"
                    @click="applyFilters"
                    class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold"
                >
                    Filter
                </button>
            </div>

            <!-- Table -->
            <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[11px] font-black uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Nama Promo / Event</th>
                                <th class="px-4 py-3">Diskon</th>
                                <th class="px-4 py-3">Target Member</th>
                                <th class="px-4 py-3">Kategori Barang</th>
                                <th class="px-4 py-3">Periode Event</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="d in discounts.data" :key="d.id" class="hover:bg-slate-50/70">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900">{{ d.name }}</div>
                                    <div v-if="d.min_purchase > 0" class="text-[10px] text-slate-500">
                                        Min. belanja: {{ formatRupiah(d.min_purchase) }}
                                    </div>
                                    <div v-if="d.notes" class="text-[10px] text-slate-400">{{ d.notes }}</div>
                                </td>
                                <td class="px-4 py-3 font-mono font-bold text-emerald-700">
                                    <span v-if="d.discount_type === 'percentage'">{{ d.discount_value }}%</span>
                                    <span v-else>{{ formatRupiah(d.discount_value) }}</span>
                                    <div v-if="d.discount_type === 'percentage' && d.max_discount" class="text-[10px] font-sans font-normal text-slate-500">
                                        Maks. {{ formatRupiah(d.max_discount) }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">
                                        {{ memberTypeLabel(d.member_type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-teal-700">
                                        {{ d.product_category || 'Semua Kategori' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-[11px] text-slate-600">
                                    <span v-if="d.start_date || d.end_date">
                                        {{ d.start_date || 'Awal' }} s/d {{ d.end_date || 'Seterusnya' }}
                                    </span>
                                    <span v-else class="text-slate-400">Tanpa batas tanggal</span>
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        type="button"
                                        @click="toggleDiscount(d)"
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold transition-colors"
                                        :class="d.is_active ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-slate-200 text-slate-600 hover:bg-slate-300'"
                                    >
                                        {{ d.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-right space-x-1.5 whitespace-nowrap">
                                    <button
                                        type="button"
                                        @click="openEdit(d)"
                                        class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        @click="deleteDiscount(d)"
                                        class="px-2.5 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold"
                                    >
                                        Hapus
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="discounts.data.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-slate-400">
                                    Belum ada promo diskon yang dibuat.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Modal Form -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
            @click.self="showModal = false"
        >
            <div class="bg-white border border-slate-200 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-black text-slate-900">
                        {{ editingDiscount ? 'Edit Promo Diskon' : 'Tambah Promo Diskon' }}
                    </h3>
                    <button type="button" @click="showModal = false" class="text-slate-400 hover:text-slate-600 text-sm font-bold">✕</button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nama Event / Promo *</label>
                        <input v-model="form.name" type="text" required placeholder="Contoh: Promo Milad SDAM / Diskon Guru" class="w-full px-3 py-2 border border-slate-300 rounded-xl" />
                        <p v-if="form.errors.name" class="text-rose-600 text-[11px] mt-1">{{ form.errors.name }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Tipe Diskon *</label>
                            <select v-model="form.discount_type" class="w-full px-3 py-2 border border-slate-300 rounded-xl bg-white">
                                <option value="percentage">Persentase (%)</option>
                                <option value="fixed">Potongan Nominal (Rp)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">
                                {{ form.discount_type === 'percentage' ? 'Persentase (%)' : 'Nominal Potongan (Rp)' }} *
                            </label>
                            <input v-model.number="form.discount_value" type="number" step="0.01" min="0.01" :max="form.discount_type === 'percentage' ? 100 : undefined" required class="w-full px-3 py-2 border border-slate-300 rounded-xl" />
                            <p v-if="form.errors.discount_value" class="text-rose-600 text-[11px] mt-1">{{ form.errors.discount_value }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div v-if="form.discount_type === 'percentage'">
                            <label class="block font-bold text-slate-700 mb-1">Maksimal Diskon (Rp)</label>
                            <input v-model="form.max_discount" type="number" min="0" placeholder="Kosongkan jika tanpa batas" class="w-full px-3 py-2 border border-slate-300 rounded-xl" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Minimal Belanja (Rp)</label>
                            <input v-model.number="form.min_purchase" type="number" min="0" class="w-full px-3 py-2 border border-slate-300 rounded-xl" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Target Jenis Member</label>
                            <select v-model="form.member_type" class="w-full px-3 py-2 border border-slate-300 rounded-xl bg-white">
                                <option value="">Semua Pelanggan</option>
                                <option value="registered">Semua Member & Pegawai</option>
                                <option value="pegawai">Pegawai / Guru</option>
                                <option value="wali_murid">Wali Murid</option>
                                <option value="siswa">Siswa</option>
                                <option value="umum">Member Umum</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Target Kategori Barang</label>
                            <select v-model="form.product_category" class="w-full px-3 py-2 border border-slate-300 rounded-xl bg-white">
                                <option value="">Semua Kategori</option>
                                <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Tanggal Mulai Event</label>
                            <input v-model="form.start_date" type="date" class="w-full px-3 py-2 border border-slate-300 rounded-xl" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Tanggal Selesai Event</label>
                            <input v-model="form.end_date" type="date" class="w-full px-3 py-2 border border-slate-300 rounded-xl" />
                            <p v-if="form.errors.end_date" class="text-rose-600 text-[11px] mt-1">{{ form.errors.end_date }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Catatan (Opsional)</label>
                        <input v-model="form.notes" type="text" maxlength="255" class="w-full px-3 py-2 border border-slate-300 rounded-xl" />
                    </div>

                    <label class="flex items-center gap-2 font-bold text-slate-700 pt-1">
                        <input v-model="form.is_active" type="checkbox" class="rounded text-emerald-600" />
                        <span>Promo Aktif</span>
                    </label>

                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="showModal = false" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 font-bold">Batal</button>
                        <button type="submit" :disabled="form.processing" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Promo' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </PosLayout>
</template>
