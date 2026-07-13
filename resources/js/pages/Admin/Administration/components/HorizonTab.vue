<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'

defineProps<{
    url: string
}>()
</script>

<template>
    <div
        class="overflow-hidden rounded-3xl border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] shadow-sm"
    >
        <iframe
            :src="url"
            class="min-h-[75vh] w-full bg-white"
            loading="lazy"
            :title="trans('administration.horizon_title')"
        />
    </div>
</template>
