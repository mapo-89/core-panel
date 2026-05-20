<script setup lang="ts">
import { computed } from 'vue'

import { usePresenceStatus } from '@/composables/usePresenceRealtime'

const props = withDefaults(
    defineProps<{
        avatarUrl?: string | null
        initials?: string | null
        presenceLastSeenAt?: number | null
        presenceStatus?: 'online' | 'away' | 'offline' | null
        size?: 'sm' | 'md' | 'lg' | 'xl'
        showPresence?: boolean
        userId?: string | null
    }>(),
    {
        avatarUrl: null,
        initials: '',
        presenceLastSeenAt: null,
        presenceStatus: null,
        size: 'md',
        showPresence: true,
        userId: null,
    },
)

const sizeClasses = computed(() => {
    return {
        container: {
            sm: 'h-9 w-9',
            md: 'h-10 w-10',
            lg: 'h-12 w-12',
            xl: 'h-24 w-24',
        }[props.size],
        text: {
            sm: 'text-xs',
            md: 'text-sm',
            lg: 'text-base',
            xl: 'text-2xl',
        }[props.size],
        indicator: {
            sm: 'h-3.5 w-3.5 -right-0.5 -top-0.5 border-2',
            md: 'h-3.5 w-3.5 -right-0.5 -top-0.5 border-2',
            lg: 'h-4 w-4 -right-0.5 -top-0.5 border-2',
            xl: 'h-5 w-5 -right-0.5 -top-0.5 border-[3px]',
        }[props.size],
    }
})

const resolvedPresenceStatus = usePresenceStatus({
    fallbackLastSeenAt: computed(() => props.presenceLastSeenAt ?? null),
    fallbackStatus: computed(() => props.presenceStatus ?? 'offline'),
    userId: computed(() => props.userId ?? null),
})

const presenceClasses = computed(() => {
    return {
        'bg-emerald-500': resolvedPresenceStatus.value === 'online',
        'bg-amber-400': resolvedPresenceStatus.value === 'away',
        'bg-surface-400 dark:bg-surface-500':
            resolvedPresenceStatus.value === 'offline',
    }
})
</script>

<template>
    <div class="relative inline-flex shrink-0 items-center justify-center">
        <img
            v-if="avatarUrl"
            :src="avatarUrl"
            :alt="initials || 'Avatar'"
            class="rounded-full object-cover ring-2 ring-white shadow-sm dark:ring-surface-900"
            :class="sizeClasses.container"
        />
        <div
            v-else
            class="flex items-center justify-center rounded-full bg-surface-200 font-semibold uppercase text-surface-700 ring-2 ring-white shadow-sm dark:bg-surface-700 dark:text-surface-100 dark:ring-surface-900"
            :class="[sizeClasses.container, sizeClasses.text]"
        >
            {{ initials }}
        </div>

        <div
            v-if="showPresence"
            :title="$t(`common.avatar.presence.${resolvedPresenceStatus}`)"
            class="absolute rounded-full border-white shadow-sm dark:border-surface-900"
            :class="[presenceClasses, sizeClasses.indicator]"
        />
    </div>
</template>
