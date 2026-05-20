<script setup lang="ts">
import { computed } from 'vue'

import type { BaseField } from '../types'
import {
    resolveFieldHelp,
    resolveFieldLabel,
    resolveFieldPlaceholder,
} from '../useFormSchema'

const props = defineProps<{
    disabled?: boolean
    error?: string
    field: BaseField
    locale?: string
    modelValue: string | null
}>()

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

const label = computed(() => resolveFieldLabel(props.field, props.locale))
const placeholder = computed(() =>
    resolveFieldPlaceholder(props.field, props.locale),
)
const help = computed(() => resolveFieldHelp(props.field, props.locale))
</script>

<template>
    <label
        class="grid gap-2"
        :style="{
            gridColumn: field.columnSpan
                ? `span ${field.columnSpan}`
                : undefined,
        }"
    >
        <span class="text-sm font-medium text-[var(--cp-text-primary)]">{{
            label
        }}</span>
        <InputText
            :model-value="modelValue ?? ''"
            :disabled="disabled"
            fluid
            :invalid="Boolean(error)"
            :name="field.name"
            :placeholder="placeholder"
            @update:model-value="
                emit('update:modelValue', String($event ?? ''))
            "
        />
        <small v-if="help" class="text-[var(--cp-text-muted)]">{{
            help
        }}</small>
        <Message v-if="error" severity="error" size="small" variant="simple">{{
            error
        }}</Message>
    </label>
</template>
