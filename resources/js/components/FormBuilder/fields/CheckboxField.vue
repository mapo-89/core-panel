<script setup lang="ts">
import { computed } from 'vue'

import type { BaseField } from '../types'
import { resolveFieldHelp, resolveFieldLabel } from '../useFormSchema'

const props = defineProps<{
    disabled?: boolean
    error?: string
    field: BaseField
    locale?: string
    modelValue: boolean | null
}>()

const emit = defineEmits<{
    'update:modelValue': [value: boolean]
}>()

const label = computed(() => resolveFieldLabel(props.field, props.locale))
const help = computed(() => resolveFieldHelp(props.field, props.locale))
</script>

<template>
    <div
        class="grid gap-2"
        :style="{
            gridColumn: field.columnSpan
                ? `span ${field.columnSpan}`
                : undefined,
        }"
    >
        <label
            class="flex items-center gap-3 text-sm font-medium text-[var(--cp-text-primary)]"
        >
            <Checkbox
                binary
                :disabled="disabled"
                :input-id="field.name"
                :invalid="Boolean(error)"
                :model-value="Boolean(modelValue)"
                @update:model-value="emit('update:modelValue', Boolean($event))"
            />
            <span>{{ label }}</span>
        </label>
        <small v-if="help" class="text-[var(--cp-text-muted)]">{{
            help
        }}</small>
        <Message v-if="error" severity="error" size="small" variant="simple">{{
            error
        }}</Message>
    </div>
</template>
