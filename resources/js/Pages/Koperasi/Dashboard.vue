<script setup>
import { Head } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import KoperasiDashboard from '@/Pages/Dashboard/Roles/KoperasiDashboard.vue'

defineProps({
    dashboard: { type: Object, required: true },
    deferred: { type: Object, default: null },
})
</script>

<template>
    <Head title="Dashboard Koperasi" />

    <PosLayout>
        <div class="flex-1 w-full overflow-y-auto md:overflow-hidden bg-slate-50 dark:bg-slate-950">
            <div class="w-full py-4 px-4 sm:px-6 h-full">
                <KoperasiDashboard :dashboard="dashboard" :deferred="deferred" />
            </div>
        </div>
    </PosLayout>
</template>
