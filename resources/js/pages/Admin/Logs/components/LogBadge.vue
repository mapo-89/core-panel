<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
    defineProps<{
        dot?: boolean
        label: string
        tone?: 'danger' | 'info' | 'neutral' | 'success' | 'warning'
    }>(),
    {
        dot: false,
        tone: 'neutral',
    },
)

const toneClass = computed(() => {
    return {
        danger: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        info: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        neutral:
            'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-400',
        success:
            'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        warning:
            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    }[props.tone]
})
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-xs font-medium"
        :class="toneClass"
    >
        <span v-if="dot" aria-hidden="true" class="text-[0.625rem] leading-none"
            >●</span
        >
        <span>{{ label }}</span>
    </span>
</template>
