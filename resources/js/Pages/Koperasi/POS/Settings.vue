<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

const props = defineProps({
    storeInfo: {
        type: Object,
        default: () => ({}),
    },
})

const form = useForm({
    name: props.storeInfo.name || 'Koperasi Rabbani',
    tagline: props.storeInfo.tagline || '',
    address: props.storeInfo.address || '',
    phone: props.storeInfo.phone || '',
    receipt_footer: props.storeInfo.receipt_footer || '',
})

const submit = () => {
    form.post(route('koperasi.settings.update'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Identitas Koperasi — Koperasi Rabbani" />

    <PosLayout>
        <div class="flex-1 py-6 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto w-full space-y-6 overflow-y-auto bg-slate-100/60">
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900">Pengaturan Identitas Koperasi</h1>
                <p class="text-xs text-slate-500">Atur nama toko, alamat, nomor telepon, dan catatan footer untuk struk belanja POS</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Form -->
                <form @submit.prevent="submit" class="lg:col-span-2 bg-white rounded-2xl p-6 border border-slate-200 shadow-xs space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nama Koperasi / Toko *</label>
                        <input v-model="form.name" type="text" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:border-emerald-500 font-bold text-slate-900" />
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tagline / Keterangan Singkat</label>
                        <input v-model="form.tagline" type="text" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:border-emerald-500" />
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Alamat Lengkap *</label>
                        <textarea v-model="form.address" rows="2" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:border-emerald-500"></textarea>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nomor Telepon / WhatsApp</label>
                        <input v-model="form.phone" type="text" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:border-emerald-500 font-mono" />
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Catatan Footer Struk Belanja</label>
                        <textarea v-model="form.receipt_footer" rows="3" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:border-emerald-500"></textarea>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-md shadow-emerald-600/20 transition-all"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Identitas Koperasi' }}
                        </button>
                    </div>
                </form>

                <!-- Live Preview Struk -->
                <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs space-y-3 h-fit">
                    <div class="text-[11px] font-black uppercase tracking-wider text-slate-400">Preview Header & Footer Struk</div>
                    <div class="p-4 rounded-xl bg-slate-50 border border-dashed border-slate-300 font-mono text-[11px] text-slate-800 space-y-3">
                        <div class="text-center space-y-0.5 border-b border-dashed border-slate-300 pb-2">
                            <div class="font-black text-xs uppercase">{{ form.name || 'KOPERASI RABBANI' }}</div>
                            <div class="text-[10px] text-slate-600">{{ form.address || '-' }}</div>
                            <div v-if="form.phone" class="text-[10px] text-slate-600">Telp: {{ form.phone }}</div>
                        </div>
                        <div class="text-center text-[10px] text-slate-400 py-2">... rincian belanja ...</div>
                        <div class="text-center text-[10px] text-slate-600 whitespace-pre-line border-t border-dashed border-slate-300 pt-2">
                            {{ form.receipt_footer || '-' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </PosLayout>
</template>
