<script setup>
const props = defineProps({
    size: {
        type: String,
        default: 'xl', // 'md' | 'lg' | 'xl' | 'full'
        validator: (v) => ['md', 'lg', 'xl', 'full'].includes(v),
    },
})

const sizeClasses = {
    md: 'max-w-4xl',
    lg: 'max-w-6xl',
    xl: 'max-w-7xl',
    full: 'w-full',
}
</script>

<template>
    <div :class="['w-full mx-auto space-y-6 pb-8', sizeClasses[props.size] || sizeClasses.xl]">
        <slot />
    </div>
</template>
