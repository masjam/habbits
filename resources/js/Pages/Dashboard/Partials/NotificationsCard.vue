<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import ActionList from '@/Components/Dashboard/ActionList.vue'

const page = usePage()
const unreadCount = computed(() => page.props.notifications?.unread_count || 0)
const items = computed(() => {
    const raw = page.props.notifications?.latest
    const latest = Array.isArray(raw) ? raw : (raw && typeof raw === 'object' ? Object.values(raw) : [])
    return latest.slice(0, 5).map((n) => ({
        id: n.id,
        title: n.data?.title || n.title || 'Notifikasi',
        subtitle: n.data?.message || n.message || '',
        badge: n.read_at ? null : 'Baru',
        tone: n.read_at ? 'neutral' : 'warn',
        href: route('notifications.index'),
    }))
})
</script>

<template>
    <ActionList
        :title="`Notifikasi (${unreadCount})`"
        :items="items"
        emptyText="Belum ada notifikasi baru."
        :viewAllHref="route('notifications.index')"
        viewAllLabel="Semua"
    />
</template>
