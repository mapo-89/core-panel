<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'

import AppIcon from '@/components/AppIcon.vue'

const props = withDefaults(
    defineProps<{
        backUrl?: boolean | string
        subtitle?: string
        title?: string
    }>(),
    {
        backUrl: false,
        subtitle: '',
        title: '',
    },
)

const resolvedBackUrl = computed(() => {
    if (props.backUrl === true) {
        return typeof window !== 'undefined' && window.history.length > 1
            ? 'back'
            : null
    }

    return props.backUrl || null
})

function goBack(): void {
    if (props.backUrl === true) {
        window.history.back()
        return
    }

    if (typeof props.backUrl === 'string') {
        router.visit(props.backUrl)
    }
}
</script>

<template>
    <div
        v-if="title || resolvedBackUrl || $slots.actions"
        class="mb-6 flex shrink-0 flex-col items-start justify-between gap-4 md:flex-row md:items-center"
    >
        <div v-if="title" class="min-w-0">
            <h1
                class="text-2xl font-bold leading-tight text-[var(--cp-text-primary)]"
            >
                {{ title }}
            </h1>
            <p
                v-if="subtitle"
                class="mt-[0.35rem] text-sm text-[var(--cp-text-muted)]"
            >
                {{ subtitle }}
            </p>
        </div>

        <div
            v-if="resolvedBackUrl || $slots.actions"
            class="flex w-full shrink-0 items-center gap-2 md:w-auto"
        >
            <Button
                v-if="resolvedBackUrl"
                class="gap-2"
                outlined
                severity="secondary"
                @click="goBack"
            >
                <AppIcon name="arrow-left" />
                <span>{{ $t('common.ui.back') }}</span>
            </Button>
            <slot name="actions" />
        </div>
    </div>
</template>
