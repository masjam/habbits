<script setup>
const props = defineProps({
    size: {
        type: String,
        default: 'xl', // 'md' | 'lg' | 'xl' | 'full'
        validator: (v) => ['md', 'lg', 'xl', 'full'].includes(v),
    },
})

const sizeClasses = {
    md: 'max-w-5xl',
    lg: 'max-w-7xl',
    xl: 'w-full max-w-[1850px]',
    full: 'w-full',
}
</script>

<template>
    <div :class="['w-full mx-auto space-y-6 pb-8', sizeClasses[props.size] || sizeClasses.xl]">
        <slot />
    </div>
</template>
