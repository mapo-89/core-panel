<script setup lang="ts">
import { computed } from 'vue'

import UserAvatar from '@/components/UserAvatar.vue'

const props = withDefaults(
    defineProps<{
        avatarUrl?: string | null
        label?: string | null
        system?: boolean
        size?: 'sm' | 'md' | 'lg' | 'xl'
    }>(),
    {
        avatarUrl: null,
        label: null,
        system: false,
        size: 'sm',
    },
)

const initials = computed(() => {
    return (props.label ?? '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((segment) => segment[0] ?? '')
        .join('')
        .toUpperCase()
})
</script>

<template>
    <span v-if="system" class="text-sm text-[var(--cp-text-primary)]">
        {{ label ?? '—' }}
    </span>
    <UserAvatar
        v-else-if="label"
        v-tooltip.top="label"
        :avatar-url="avatarUrl"
        :initials="initials"
        :show-presence="false"
        :size="size"
    />
    <span v-else class="text-sm text-[var(--cp-text-primary)]">—</span>
</template>
