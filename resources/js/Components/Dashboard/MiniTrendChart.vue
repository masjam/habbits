<script setup>
import { computed } from 'vue'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Tooltip,
    Filler,
} from 'chart.js'
import { Line, Bar } from 'vue-chartjs'
import { useTheme } from '@/Composables/useTheme'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Tooltip, Filler)

const props = defineProps({
    type: { type: String, default: 'line' }, // line | bar
    labels: { type: Array, default: () => [] },
    values: { type: Array, default: () => [] },
    datasetLabel: { type: String, default: 'Nilai' },
    unit: { type: String, default: '' },
    ariaLabel: { type: String, default: 'Grafik tren' },
})

const { activeTheme } = useTheme()

const chartData = computed(() => {
    const color = activeTheme.value?.primary600 || '#059669'
    return {
        labels: props.labels,
        datasets: [
            {
                label: props.datasetLabel,
                data: props.values,
                borderColor: color,
                backgroundColor: props.type === 'bar' ? color : `${color}20`,
                borderWidth: 2,
                borderRadius: props.type === 'bar' ? 6 : 0,
                tension: 0.35,
                fill: props.type === 'line',
                pointRadius: 2.5,
                pointHoverRadius: 4,
            },
        ],
    }
})

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 200 },
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.92)',
            padding: 8,
            cornerRadius: 8,
            callbacks: {
                label: (ctx) => `${ctx.dataset.label}: ${ctx.raw}${props.unit}`,
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            grid: { color: 'rgba(148, 163, 184, 0.14)' },
            ticks: { color: 'rgb(148, 163, 184)', font: { size: 11 } },
        },
        x: {
            grid: { display: false },
            ticks: { color: 'rgb(148, 163, 184)', font: { size: 11 }, maxTicksLimit: 8 },
        },
    },
}))
</script>

<template>
    <div class="w-full h-full min-h-[140px]" role="img" :aria-label="ariaLabel">
        <Line v-if="type === 'line'" :data="chartData" :options="chartOptions" />
        <Bar v-else :data="chartData" :options="chartOptions" />
    </div>
</template>
