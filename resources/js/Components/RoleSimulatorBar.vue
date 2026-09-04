<script setup>
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'

const page = usePage()

const isActualSuperadmin = computed(() => !!page.props.auth?.is_actual_superadmin)
const isMaintenanceActive = computed(() => {
    const val = page.props.global_settings?.maintenance_mode
    return val === '1' || val === 'true' || val === true
})
const currentSimulatedRole = computed(() => page.props.auth?.simulated_role ?? 'superadmin')

const isCollapsed = ref(false)
const isSwitching = ref(false)

const switchRole = (targetRole) => {
    if (isSwitching.value || targetRole === currentSimulatedRole.value) return
    isSwitching.value = true

    router.post(route('admin.maintenance.switch-role'), {
        role: targetRole
    }, {
        preserveScroll: false,
        onFinish: () => {
            isSwitching.value = false
        }
    })
}

const toggleMaintenance = () => {
    if (!confirm('Apakah Anda yakin ingin mengubah status mode maintenance?')) return
    router.post(route('admin.maintenance.toggle'), {}, {
        preserveScroll: true
    })
}
</script>

<template>
    <div
        v-if="isActualSuperadmin && (isMaintenanceActive || currentSimulatedRole !== 'superadmin')"
        class="fixed bottom-4 right-4 z-50 transition-all duration-300 max-w-xl"
    >
        <!-- Card Simulator -->
        <div class="bg-slate-900/95 dark:bg-slate-950/95 backdrop-blur-md text-white border-2 border-amber-500/80 shadow-2xl rounded-2xl overflow-hidden p-3 sm:p-4">
            <!-- Header Bar -->
            <div class="flex items-center justify-between gap-3 pb-2 border-b border-slate-700/60 text-xs">
                <div class="flex items-center gap-2">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                    </span>
                    <span class="font-bold text-amber-400 uppercase tracking-wider text-[11px]">
                        Super Admin Sandbox Pemeliharaan
                    </span>
                </div>
                <div class="flex items-center gap-1.5">
                    <button
                        @click="isCollapsed = !isCollapsed"
                        class="text-slate-400 hover:text-white p-1 rounded transition-colors text-[10px] font-semibold flex items-center gap-1"
                        :title="isCollapsed ? 'Perluas Kontrol' : 'Perkecil Kontrol'"
                    >
                        <span>{{ isCollapsed ? 'Tampilkan' : 'Kecilkan' }}</span>
                        <svg class="w-3.5 h-3.5 transform transition-transform" :class="isCollapsed ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Body (Expanded) -->
            <div v-show="!isCollapsed" class="pt-3 space-y-3">
                <div class="text-xs text-slate-300 flex items-center justify-between">
                    <span>Ujicoba Tampilan Sebagai Role:</span>
                    <span class="font-black text-amber-300 uppercase px-2 py-0.5 rounded bg-amber-950/60 border border-amber-500/30">
                        {{ currentSimulatedRole === 'superadmin' ? 'Super Admin (Asli)' : currentSimulatedRole.toUpperCase() }}
                    </span>
                </div>

                <!-- Role Selector Buttons -->
                <div class="grid grid-cols-3 gap-1.5 p-1 bg-slate-800/80 rounded-xl border border-slate-700/80 text-xs">
                    <!-- Super Admin -->
                    <button
                        type="button"
                        @click="switchRole('superadmin')"
                        :disabled="isSwitching"
                        :class="[
                            'py-1.5 px-2 rounded-lg font-bold transition-all text-center flex flex-col items-center justify-center gap-0.5',
                            currentSimulatedRole === 'superadmin'
                                ? 'bg-amber-500 text-slate-950 shadow-md scale-[1.02]'
                                : 'text-slate-300 hover:text-white hover:bg-slate-700/60'
                        ]"
                    >
                        <span class="text-[11px] font-black">Super Admin</span>
                        <span class="text-[9px] opacity-80">(Asli)</span>
                    </button>

                    <!-- Admin -->
                    <button
                        type="button"
                        @click="switchRole('admin')"
                        :disabled="isSwitching"
                        :class="[
                            'py-1.5 px-2 rounded-lg font-bold transition-all text-center flex flex-col items-center justify-center gap-0.5',
                            currentSimulatedRole === 'admin'
                                ? 'bg-indigo-500 text-white shadow-md scale-[1.02]'
                                : 'text-slate-300 hover:text-white hover:bg-slate-700/60'
                        ]"
                    >
                        <span class="text-[11px] font-black">Admin</span>
                        <span class="text-[9px] opacity-80">(Simulasi)</span>
                    </button>

                    <!-- Pegawai / User -->
                    <button
                        type="button"
                        @click="switchRole('user')"
                        :disabled="isSwitching"
                        :class="[
                            'py-1.5 px-2 rounded-lg font-bold transition-all text-center flex flex-col items-center justify-center gap-0.5',
                            currentSimulatedRole === 'user'
                                ? 'bg-emerald-500 text-white shadow-md scale-[1.02]'
                                : 'text-slate-300 hover:text-white hover:bg-slate-700/60'
                        ]"
                    >
                        <span class="text-[11px] font-black">Pegawai</span>
                        <span class="text-[9px] opacity-80">(Simulasi)</span>
                    </button>
                </div>

                <!-- Action Links -->
                <div class="flex items-center justify-between gap-2 pt-1 text-[11px]">
                    <a
                        :href="route('maintenance')"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 text-slate-300 hover:text-amber-300 underline font-medium transition-colors"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span>Pratinjau Hal. Maintenance</span>
                    </a>

                    <button
                        type="button"
                        @click="toggleMaintenance"
                        class="px-2.5 py-1 rounded-lg bg-red-500/20 hover:bg-red-500/30 text-red-300 border border-red-500/40 font-bold transition-colors text-[10px]"
                    >
                        {{ isMaintenanceActive ? 'Matikan Maintenance' : 'Aktifkan Maintenance' }}
                    </button>
                </div>
            </div>

            <!-- Mini Summary (When Collapsed) -->
            <div v-show="isCollapsed" class="pt-2 flex items-center justify-between text-xs gap-3">
                <span class="text-[11px] text-slate-300">
                    Role aktif: <strong class="text-amber-400 font-black uppercase">{{ currentSimulatedRole }}</strong>
                </span>
                <button
                    v-if="currentSimulatedRole !== 'superadmin'"
                    @click="switchRole('superadmin')"
                    class="text-[10px] bg-amber-500 text-slate-950 font-black px-2 py-0.5 rounded shadow"
                >
                    Reset Role
                </button>
            </div>
        </div>
    </div>
</template>
