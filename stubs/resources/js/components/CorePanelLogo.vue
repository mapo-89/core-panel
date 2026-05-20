<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue'

defineProps<{
    title?: string
}>()
</script>

<template>
    <span v-if="title" :aria-label="title" class="core-panel-logo" role="img">
        <AppIcon class="core-panel-logo__icon" name="logo" :stroke-width="2" />
    </span>
    <span v-else aria-hidden="true" class="core-panel-logo">
        <AppIcon class="core-panel-logo__icon" name="logo" :stroke-width="2" />
    </span>
</template>
