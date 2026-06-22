<script setup lang="ts">
import type { StyleValue } from 'vue'

import BadgeRenderer from '@/components/ui/BadgeRenderer.vue'

type BadgeVariant = 'soft' | 'outlined' | 'filled'
type TextColorMode = 'readable' | 'shade'

const props = defineProps<{
    badgeStyle?: StyleValue
    color?: string | null
    icon?: string | null
    label: string
    outlined?: boolean
    rounded?: boolean
    size?: 'sm' | 'md'
    textColor?: string | null
    textColorMode?: TextColorMode
    tone?: 'neutral' | 'success' | 'warning' | 'danger' | 'info'
    variant?: BadgeVariant
}>()
</script>

<template>
    <BadgeRenderer v-bind="props" />
</template>
