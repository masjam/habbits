<script setup>
import { ref, watch, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import Modal from '@/Components/UI/Modal.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'

const props = defineProps({
    show: { type: Boolean, default: false },
    rubric: { type: Object, required: true },
    initialCategory: { type: String, default: 'guru' },
})

const emit = defineEmits(['close'])

const activeTab = ref('guru')
const draftCategories = ref({ guru: {}, karyawan: {} })
const addingAspect = ref(null)
const newItem = ref({ label: '', description: '' })
const editingKey = ref(null) // `${aspectKey}:${idx}`
const editBuffer = ref({ label: '', description: '' })

const cloneFromRubric = () => {
    const out = { guru: {}, karyawan: {} }
    for (const cat of ['guru', 'karyawan']) {
        const aspects = props.rubric?.categories?.[cat]?.aspects || {}
        for (const [aspectKey, meta] of Object.entries(aspects)) {
            out[cat][aspectKey] = (meta.indicators || []).map(ind => ({
                code: ind.code,
                label: ind.label,
                description: ind.description || '',
            }))
        }
    }
    draftCategories.value = out
}

watch(() => props.show, (isOpen) => {
    if (!isOpen) return
    activeTab.value = props.initialCategory === 'karyawan' ? 'karyawan' : 'guru'
    addingAspect.value = null
    editingKey.value = null
    cloneFromRubric()
}, { immediate: true })

const currentAspectsMeta = computed(() => props.rubric?.categories?.[activeTab.value]?.aspects || {})
const currentList = (aspectKey) => draftCategories.value?.[activeTab.value]?.[aspectKey] || []

const startAdd = (aspectKey) => {
    addingAspect.value = aspectKey
    editingKey.value = null
    newItem.value = { label: '', description: '' }
}

const confirmAdd = (aspectKey) => {
    const label = newItem.value.label.trim()
    if (!label) return
    draftCategories.value[activeTab.value][aspectKey].push({
        code: '',
        label,
        description: newItem.value.description.trim(),
    })
    addingAspect.value = null
    newItem.value = { label: '', description: '' }
}

const startEdit = (aspectKey, idx, item) => {
    editingKey.value = `${aspectKey}:${idx}`
    addingAspect.value = null
    editBuffer.value = { label: item.label, description: item.description || '' }
}

const confirmEdit = (aspectKey, idx) => {
    const label = editBuffer.value.label.trim()
    if (!label) return
    const list = draftCategories.value[activeTab.value][aspectKey]
    list[idx] = {
        ...list[idx],
        label,
        description: editBuffer.value.description.trim(),
    }
    editingKey.value = null
}

const removeItem = (aspectKey, idx) => {
    const list = draftCategories.value[activeTab.value][aspectKey]
    if (list.length <= 1) return
    if (!confirm(`Hapus komponen "${list[idx].label}"?`)) return
    list.splice(idx, 1)
}

const form = useForm({
    categories: {},
})

const saveAll = () => {
    form.categories = draftCategories.value
    form.put(route('admin.performance.rubric.update'), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    })
}

const resetToDefault = () => {
    if (!confirm('Kembalikan seluruh komponen indikator Guru dan Karyawan ke pengaturan default sistem?')) return
    router.post(route('admin.performance.rubric.reset'), {}, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    })
}
</script>

<template>
    <Modal :show="show" max-width="4xl" @close="emit('close')">
        <div class="bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 flex flex-col max-h-[88vh]">
            <!-- Header -->
            <div class="shrink-0 px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-black text-slate-900 dark:text-slate-100">Kelola Komponen Indikator KPI</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Tambah, edit, atau hapus komponen penilaian untuk masing-masing aspek Guru dan Karyawan.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="activeTab = 'guru'"
                        class="px-3 py-1.5 rounded-xl text-xs font-extrabold border transition-colors"
                        :class="activeTab === 'guru'
                            ? 'bg-emerald-600 border-emerald-600 text-white'
                            : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'"
                    >
                        Guru / Pendidik
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'karyawan'"
                        class="px-3 py-1.5 rounded-xl text-xs font-extrabold border transition-colors"
                        :class="activeTab === 'karyawan'
                            ? 'bg-emerald-600 border-emerald-600 text-white'
                            : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'"
                    >
                        Karyawan / Tendik
                    </button>
                </div>
            </div>

            <!-- Body -->
            <div class="flex-1 min-h-0 p-6 space-y-5 overflow-y-auto overscroll-contain">
                <div v-if="form.errors.rubric" class="p-3 rounded-xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-800 text-xs font-semibold text-rose-700 dark:text-rose-300">
                    {{ form.errors.rubric }}
                </div>

                <div v-for="(meta, aspectKey) in currentAspectsMeta" :key="aspectKey" class="rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <div class="px-4 py-3 bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="text-sm font-extrabold text-slate-800 dark:text-slate-100">{{ meta.label }}</h4>
                                <Badge variant="neutral" size="sm">{{ currentList(aspectKey).length }} komponen</Badge>
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ meta.description }}</p>
                        </div>
                        <Button type="button" size="sm" variant="primary" @click="startAdd(aspectKey)">
                            + Tambah
                        </Button>
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-slate-700/70">
                        <div v-for="(item, idx) in currentList(aspectKey)" :key="`${aspectKey}-${idx}`" class="p-3.5">
                            <!-- Mode Edit -->
                            <div v-if="editingKey === `${aspectKey}:${idx}`" class="space-y-2 bg-slate-50 dark:bg-slate-900/40 p-3 rounded-xl">
                                <input
                                    v-model="editBuffer.label"
                                    type="text"
                                    maxlength="120"
                                    placeholder="Nama komponen indikator..."
                                    class="w-full px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800"
                                />
                                <input
                                    v-model="editBuffer.description"
                                    type="text"
                                    maxlength="255"
                                    placeholder="Deskripsi singkat (opsional)..."
                                    class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800"
                                />
                                <div class="flex justify-end gap-2">
                                    <Button type="button" size="sm" variant="secondary" @click="editingKey = null">Batal</Button>
                                    <Button type="button" size="sm" variant="primary" @click="confirmEdit(aspectKey, idx)">Simpan</Button>
                                </div>
                            </div>

                            <!-- Mode Tampil -->
                            <div v-else class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ item.label }}</div>
                                    <div v-if="item.description" class="text-[11px] text-slate-500 dark:text-slate-400">{{ item.description }}</div>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button
                                        type="button"
                                        @click="startEdit(aspectKey, idx, item)"
                                        class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-[11px] font-bold text-slate-700 dark:text-slate-200"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        :disabled="currentList(aspectKey).length <= 1"
                                        @click="removeItem(aspectKey, idx)"
                                        class="px-2.5 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 dark:bg-rose-900/30 text-[11px] font-bold text-rose-600 dark:text-rose-300 disabled:opacity-40 disabled:cursor-not-allowed"
                                        :title="currentList(aspectKey).length <= 1 ? 'Minimal 1 komponen wajib ada' : 'Hapus komponen'"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Form Tambah Komponen -->
                        <div v-if="addingAspect === aspectKey" class="p-3.5 bg-emerald-50/50 dark:bg-emerald-950/20 space-y-2">
                            <input
                                v-model="newItem.label"
                                type="text"
                                maxlength="120"
                                placeholder="Nama komponen baru..."
                                class="w-full px-3 py-1.5 text-xs font-bold rounded-lg border border-emerald-300 dark:border-emerald-700 bg-white dark:bg-slate-800"
                            />
                            <input
                                v-model="newItem.description"
                                type="text"
                                maxlength="255"
                                placeholder="Deskripsi singkat (opsional)..."
                                class="w-full px-3 py-1.5 text-xs rounded-lg border border-emerald-200 dark:border-emerald-800 bg-white dark:bg-slate-800"
                            />
                            <div class="flex justify-end gap-2">
                                <Button type="button" size="sm" variant="secondary" @click="addingAspect = null">Batal</Button>
                                <Button type="button" size="sm" variant="primary" @click="confirmAdd(aspectKey)">Tambahkan</Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="shrink-0 px-6 py-4 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-100 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3">
                <Button type="button" variant="outline" @click="resetToDefault">
                    Reset Default
                </Button>
                <div class="flex items-center gap-2.5">
                    <Button type="button" variant="secondary" @click="emit('close')">Tutup</Button>
                    <Button type="button" variant="primary" :disabled="form.processing" @click="saveAll">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                    </Button>
                </div>
            </div>
        </div>
    </Modal>
</template>
