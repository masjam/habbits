<script setup>
defineProps({
    greeting: { type: String, required: true },
    dateLabel: { type: String, default: '' },
    contextLine: { type: String, default: '' },
})
</script>

<template>
    <div
        class="flex flex-col gap-3 pb-[calc(1rem+env(safe-area-inset-bottom))] md:pb-0 md:h-[calc(100dvh-var(--topbar-h,4rem)-2rem)] md:overflow-hidden"
    >
        <!-- Baris 1: Header 1 baris -->
        <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 shrink-0">
            <div class="min-w-0 flex flex-wrap items-baseline gap-x-2.5 gap-y-0.5">
                <h1 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-slate-100 tracking-tight truncate">
                    {{ greeting }}
                </h1>
                <span v-if="dateLabel" class="text-xs font-medium text-slate-500 dark:text-slate-400">
                    {{ dateLabel }}
                </span>
                <span v-if="contextLine" class="text-xs text-slate-500 dark:text-slate-400 hidden lg:inline">
                    — {{ contextLine }}
                </span>
            </div>
            <div v-if="$slots.actions" class="flex items-center gap-2 flex-wrap shrink-0">
                <slot name="actions" />
            </div>
        </header>

        <!-- Grid Bento 1-Layar Desktop (4 KPI + Panel Kanan + 2 Panel Utama) -->
        <div class="flex-1 min-h-0 grid grid-cols-1 lg:grid-cols-12 lg:grid-rows-[auto_minmax(0,1fr)] gap-3">
            <!-- KPI Strip: 4 kolom (2x2 di mobile, 4 kolom di desktop, span 9 dari 12 kolom jika ada panel kanan) -->
            <section
                aria-label="Ringkasan metrik utama"
                class="lg:col-span-9 grid grid-cols-2 md:grid-cols-4 gap-3 shrink-0"
            >
                <slot name="kpis" />
            </section>

            <!-- Panel Kanan (Tugas / Notifikasi / Ringkasan) — tinggi penuh di desktop -->
            <aside
                aria-label="Daftar tindakan dan informasi"
                class="lg:col-span-3 lg:row-span-2 min-h-0 flex flex-col gap-3 lg:overflow-y-auto"
            >
                <slot name="sidebar" />
            </aside>

            <!-- Baris Bawah Kiri: 2 Panel Utama (Grafik & Status) -->
            <section
                aria-label="Analitik dan status utama"
                class="lg:col-span-9 min-h-0 grid grid-cols-1 md:grid-cols-12 gap-3"
            >
                <slot name="main" />
            </section>
        </div>
    </div>
</template>
