<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PageContainer from '@/Components/UI/PageContainer.vue'
import EmployeeDashboard from '@/Pages/Dashboard/Roles/EmployeeDashboard.vue'
import TataUsahaDashboard from '@/Pages/Dashboard/Roles/TataUsahaDashboard.vue'
import KoperasiDashboard from '@/Pages/Dashboard/Roles/KoperasiDashboard.vue'
import KurikulumDashboard from '@/Pages/Dashboard/Roles/KurikulumDashboard.vue'
import KepalaSekolahDashboard from '@/Pages/Dashboard/Roles/KepalaSekolahDashboard.vue'

const props = defineProps({
    dashboard: { type: Object, required: true },
    deferred: { type: Object, default: null },
})

const activeComponent = computed(() => {
    switch (props.dashboard?.roleKey) {
        case 'tata_usaha':
            return TataUsahaDashboard
        case 'koperasi':
            return KoperasiDashboard
        case 'kurikulum':
            return KurikulumDashboard
        case 'kepala_sekolah':
            return KepalaSekolahDashboard
        default:
            return EmployeeDashboard
    }
})
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Dashboard" />
        <PageContainer size="xl">
            <component :is="activeComponent" :dashboard="dashboard" :deferred="deferred" />
        </PageContainer>
    </AuthenticatedLayout>
</template>
