<script setup>
import { computed } from 'vue'
import Badge from '@/Components/UI/Badge.vue'

const props = defineProps({
    status: { type: String, default: null },
    size: { type: String, default: 'sm' },
})

const meta = computed(() => {
    switch (props.status) {
        case 'published':
            return { label: 'Disetujui', variant: 'success' }
        case 'submitted':
            return { label: 'Diajukan', variant: 'info' }
        case 'revision_requested':
            return { label: 'Perlu Revisi', variant: 'warning' }
        case 'draft':
            return { label: 'Draft', variant: 'neutral' }
        default:
            return { label: 'Belum Dinilai', variant: 'neutral' }
    }
})
</script>

<template>
    <Badge :variant="meta.variant" :size="size">{{ meta.label }}</Badge>
</template>
