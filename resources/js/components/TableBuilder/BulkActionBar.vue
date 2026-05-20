<script setup lang="ts">
import type { DataTableAction } from './types'

defineProps<{
    actions: DataTableAction[]
    selectedCount: number
}>()

const emit = defineEmits<{
    run: [action: DataTableAction]
}>()
</script>

<template>
    <div
        v-if="selectedCount > 0 && actions.length > 0"
        class="flex flex-wrap items-center justify-between gap-3 rounded border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] px-4 py-3"
    >
        <span class="text-sm text-[var(--cp-text-primary)]">
            {{
                $t('table-builder.labels.selected', {
                    count: String(selectedCount),
                })
            }}
        </span>

        <div class="flex flex-wrap gap-2">
            <Button
                v-for="action in actions"
                :key="action.name"
                :label="action.label ?? action.name"
                severity="secondary"
                size="small"
                @click="emit('run', action)"
            />
        </div>
    </div>
</template>
