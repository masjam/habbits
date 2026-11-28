<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PageContainer from '@/Components/UI/PageContainer.vue'
import AdminSuperadminDashboard from '@/Pages/Dashboard/Roles/AdminSuperadminDashboard.vue'

defineProps({
    dashboard: { type: Object, required: true },
    deferred: { type: Object, default: null },
})
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="dashboard?.roleKey === 'superadmin' ? 'Dashboard Superadmin' : 'Dashboard Admin'" />
        <PageContainer size="xl">
            <AdminSuperadminDashboard :dashboard="dashboard" :deferred="deferred" />
        </PageContainer>
    </AuthenticatedLayout>
</template>
