<script setup>
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

const props = defineProps({
    members: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    stats: {
        type: Object,
        default: () => ({ total_members: 0, total_balance: 0, total_bon: 0 }),
    },
    filters: {
        type: Object,
        default: () => ({ search: '', type: 'all' }),
    },
})

const search = ref(props.filters.search || '')
const typeFilter = ref(props.filters.type || 'all')

const applyFilter = () => {
    router.get(route('koperasi.members'), {
        search: search.value,
        type: typeFilter.value,
    }, { preserveState: true, preserveScroll: true })
}

const formatRupiah = (val) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
}).format(Number(val) || 0)

// ─── Modal Tambah / Edit Member ───
const showFormModal = ref(false)
const form = useForm({
    id: null,
    member_code: '',
    name: '',
    type: 'wali_murid',
    phone: '',
    rfid_uid: '',
    bon_limit: 0,
    notes: '',
    is_active: true,
})

const openAddModal = () => {
    form.reset()
    form.clearErrors()
    form.id = null
    form.type = 'wali_murid'
    form.bon_limit = 0
    form.is_active = true
    showFormModal.value = true
}

const openEditModal = (m) => {
    form.clearErrors()
    form.id = m.id
    form.member_code = m.member_code
    form.name = m.name
    form.type = m.type
    form.phone = m.phone || ''
    form.rfid_uid = m.rfid_uid || ''
    form.bon_limit = m.bon_limit
    form.notes = m.notes || ''
    form.is_active = m.is_active
    showFormModal.value = true
}

const submitMember = () => {
    form.post(route('koperasi.members.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showFormModal.value = false
        },
    })
}

// ─── Modal Topup Saldo ───
const topupTarget = ref(null)
const topupForm = useForm({
    amount: 50000,
    notes: '',
})

const openTopupModal = (m) => {
    topupTarget.value = m
    topupForm.reset()
    topupForm.amount = 50000
}

const submitTopup = () => {
    if (!topupTarget.value) return
    const targetRoute = topupTarget.value.source === 'user'
        ? route('koperasi.members.topup-user', topupTarget.value.id)
        : route('koperasi.members.topup', topupTarget.value.id)

    topupForm.post(targetRoute, {
        preserveScroll: true,
        onSuccess: () => {
            topupTarget.value = null
        },
    })
}

const removeMember = (m) => {
    if (!confirm(`Hapus / nonaktifkan member ${m.name}?`)) return
    router.delete(route('koperasi.members.destroy', m.id), { preserveScroll: true })
}

// ─── Modal Edit Limit BON Pegawai ───
const bonLimitTarget = ref(null)
const bonLimitForm = useForm({
    bon_limit: 0,
})

const openBonLimitModal = (m) => {
    bonLimitTarget.value = m
    bonLimitForm.bon_limit = m.bon_limit || 0
}

const submitBonLimit = () => {
    if (!bonLimitTarget.value) return
    bonLimitForm.post(route('koperasi.members.user-bon-limit', bonLimitTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            bonLimitTarget.value = null
        },
    })
}
</script>

<template>
    <Head title="Member Non-Pegawai — Koperasi Rabbani" />

    <PosLayout>
        <div class="flex-1 w-full overflow-y-auto bg-slate-50">
            <div class="w-full py-5 px-4 sm:px-6 space-y-5">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900">Member Koperasi</h1>
                    <p class="text-xs text-slate-500">Data member Pegawai/Guru, Wali Murid, Siswa, & Umum untuk kartu RFID, saldo deposit, dan BON</p>
                </div>
                <button
                    type="button"
                    @click="openAddModal"
                    class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md shadow-emerald-600/20 transition-all"
                >
                    + Tambah Member
                </button>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                    <p class="text-xs font-semibold text-slate-500">Member Aktif</p>
                    <p class="text-2xl font-black text-slate-900 mt-1">{{ stats.total_members }}</p>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                    <p class="text-xs font-semibold text-slate-500">Total Saldo Deposit Member</p>
                    <p class="text-2xl font-black text-emerald-600 mt-1">{{ formatRupiah(stats.total_balance) }}</p>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                    <p class="text-xs font-semibold text-slate-500">Total BON Aktif Member</p>
                    <p class="text-2xl font-black text-amber-600 mt-1">{{ formatRupiah(stats.total_bon) }}</p>
                </div>
            </div>

            <!-- Filter -->
            <form @submit.prevent="applyFilter" class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs flex flex-col sm:flex-row gap-3">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Cari nama, kode member, no HP, atau RFID..."
                    class="flex-1 px-3 py-2 text-xs rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:border-emerald-500"
                />
                <select
                    v-model="typeFilter"
                    @change="applyFilter"
                    class="px-3 py-2 text-xs rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:border-emerald-500 font-semibold"
                >
                    <option value="all">Semua Kategori</option>
                    <option value="pegawai">Pegawai / Guru</option>
                    <option value="wali_murid">Wali Murid</option>
                    <option value="siswa">Siswa</option>
                    <option value="umum">Umum</option>
                </select>
                <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs">
                    Cari
                </button>
            </form>

            <!-- Table -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50 text-slate-500 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3">Kode / Nama</th>
                                <th class="px-4 py-3">Kategori</th>
                                <th class="px-4 py-3">RFID / HP</th>
                                <th class="px-4 py-3 text-right">Saldo Deposit</th>
                                <th class="px-4 py-3 text-right">BON Aktif / Limit</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="m in members.data" :key="m.id" class="hover:bg-slate-50/70">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900">{{ m.name }}</div>
                                    <div class="font-mono text-[11px] text-slate-400">{{ m.member_code }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-bold text-[11px]">{{ m.type_label }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-mono text-[11px] text-slate-700">{{ m.rfid_uid || '-' }}</div>
                                    <div class="text-[11px] text-slate-400">{{ m.phone || '-' }}</div>
                                </td>
                                <td class="px-4 py-3 text-right font-black text-emerald-600">
                                    {{ formatRupiah(m.coop_balance) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="font-bold" :class="m.active_bon > 0 ? 'text-amber-600' : 'text-slate-500'">
                                        {{ formatRupiah(m.active_bon) }}
                                    </div>
                                    <div class="text-[10px] text-slate-400">
                                        Limit: {{ m.bon_limit > 0 ? formatRupiah(m.bon_limit) : 'Tanpa batas' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                        :class="m.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                    >
                                        {{ m.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button type="button" @click="openTopupModal(m)" class="px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold text-[11px]">
                                            Topup
                                        </button>
                                        <button v-if="m.source === 'user'" type="button" @click="openBonLimitModal(m)" class="px-2.5 py-1 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-800 font-bold text-[11px]">
                                            Limit BON
                                        </button>
                                        <button v-if="m.source === 'member'" type="button" @click="openEditModal(m)" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[11px]">
                                            Edit
                                        </button>
                                        <button v-if="m.source === 'member'" type="button" @click="removeMember(m)" class="px-2 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold text-[11px]">
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="members.data.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-slate-400">Belum ada data member non-pegawai.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="members.links && members.links.length > 3" class="px-4 py-3 border-t border-slate-100 flex justify-center gap-1">
                    <template v-for="(link, i) in members.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="px-3 py-1 rounded text-xs font-bold"
                            :class="link.active ? 'bg-emerald-600 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'"
                            v-html="link.label"
                        />
                        <span v-else class="px-3 py-1 rounded text-xs text-slate-400" v-html="link.label"></span>
                    </template>
                </div>
            </div>
        </div>

        <!-- Modal Form Member -->
        <div v-if="showFormModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" @click.self="showFormModal = false">
            <form @submit.prevent="submitMember" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                <h3 class="text-base font-black text-slate-900">{{ form.id ? 'Edit Member' : 'Tambah Member Baru' }}</h3>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">Nama Lengkap *</label>
                        <input v-model="form.name" type="text" required class="w-full px-3 py-2 rounded-xl border border-slate-200" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-600 mb-1">Kategori *</label>
                            <select v-model="form.type" class="w-full px-3 py-2 rounded-xl border border-slate-200">
                                <option value="wali_murid">Wali Murid</option>
                                <option value="siswa">Siswa</option>
                                <option value="umum">Umum</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-600 mb-1">Kode Member (opsional)</label>
                            <input v-model="form.member_code" type="text" placeholder="Otomatis MBR-..." class="w-full px-3 py-2 rounded-xl border border-slate-200 font-mono" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-600 mb-1">No. HP / WA</label>
                            <input v-model="form.phone" type="text" class="w-full px-3 py-2 rounded-xl border border-slate-200" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-600 mb-1">UID Kartu RFID</label>
                            <input v-model="form.rfid_uid" type="text" placeholder="Tap kartu RFID..." class="w-full px-3 py-2 rounded-xl border border-slate-200 font-mono" />
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">Batas Maksimal BON (Rp, 0 = tanpa batas)</label>
                        <input v-model.number="form.bon_limit" type="number" min="0" step="10000" class="w-full px-3 py-2 rounded-xl border border-slate-200 font-mono" />
                    </div>
                    <label class="flex items-center gap-2 font-semibold text-slate-700">
                        <input v-model="form.is_active" type="checkbox" class="rounded text-emerald-600" />
                        <span>Member Aktif</span>
                    </label>
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="button" @click="showFormModal = false" class="flex-1 py-2.5 rounded-xl bg-slate-100 text-slate-700 font-bold text-xs">Batal</button>
                    <button type="submit" :disabled="form.processing" class="flex-1 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Modal Topup Saldo -->
        <div v-if="topupTarget" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" @click.self="topupTarget = null">
            <form @submit.prevent="submitTopup" class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4">
                <div>
                    <h3 class="text-base font-black text-slate-900">Topup Saldo Deposit</h3>
                    <p class="text-xs text-slate-500">{{ topupTarget.name }} • Saldo saat ini: <strong>{{ formatRupiah(topupTarget.coop_balance) }}</strong></p>
                </div>
                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">Nominal Topup (Rp) *</label>
                        <input v-model.number="topupForm.amount" type="number" min="1000" step="1000" required class="w-full px-3 py-2 rounded-xl border border-slate-200 font-mono text-base font-bold" />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">Catatan</label>
                        <input v-model="topupForm.notes" type="text" placeholder="Setoran tunai di kasir..." class="w-full px-3 py-2 rounded-xl border border-slate-200" />
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="button" @click="topupTarget = null" class="flex-1 py-2.5 rounded-xl bg-slate-100 text-slate-700 font-bold text-xs">Batal</button>
                    <button type="submit" :disabled="topupForm.processing" class="flex-1 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs">
                        {{ topupForm.processing ? 'Memproses...' : 'Topup Saldo' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Modal Edit Limit BON Pegawai -->
        <div v-if="bonLimitTarget" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" @click.self="bonLimitTarget = null">
            <form @submit.prevent="submitBonLimit" class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4">
                <div>
                    <h3 class="text-base font-black text-slate-900">Atur Limit BON Pegawai</h3>
                    <p class="text-xs text-slate-500">{{ bonLimitTarget.name }} ({{ bonLimitTarget.type_label }})</p>
                </div>
                <div class="text-xs">
                    <label class="block font-bold text-slate-600 mb-1">Batas Maksimal BON (Rp, 0 = tanpa batas)</label>
                    <input v-model.number="bonLimitForm.bon_limit" type="number" min="0" step="10000" required class="w-full px-3 py-2 rounded-xl border border-slate-200 font-mono text-base font-bold" />
                </div>
                <div class="flex gap-2">
                    <button type="button" @click="bonLimitTarget = null" class="flex-1 py-2.5 rounded-xl bg-slate-100 text-slate-700 font-bold text-xs">Batal</button>
                    <button type="submit" :disabled="bonLimitForm.processing" class="flex-1 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs">
                        {{ bonLimitForm.processing ? 'Menyimpan...' : 'Simpan Limit' }}
                    </button>
                </div>
            </form>
            </div>
        </div>
    </PosLayout>
</template>
