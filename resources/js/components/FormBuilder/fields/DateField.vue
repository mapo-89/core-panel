<script setup lang="ts">
import { computed } from 'vue'

import type { BaseField } from '../types'
import {
    resolveFieldHelp,
    resolveFieldLabel,
    resolveFieldPlaceholder,
} from '../useFormSchema'

type DatePickerValue = Date | Date[] | Array<Date | null> | null | undefined

const props = defineProps<{
    disabled?: boolean
    error?: string
    field: BaseField
    locale?: string
    modelValue: Date | string | null | Array<Date | string | null>
}>()

const emit = defineEmits<{
    'update:modelValue': [value: unknown]
}>()

const label = computed(() => resolveFieldLabel(props.field, props.locale))
const help = computed(() => resolveFieldHelp(props.field, props.locale))
const placeholder = computed(() =>
    resolveFieldPlaceholder(props.field, props.locale),
)
const showTime = computed(() => props.field.type === 'datetime')

const normalizedModelValue = computed<DatePickerValue>(() => {
    if (props.modelValue === null) {
        return null
    }

    if (Array.isArray(props.modelValue)) {
        return props.modelValue.map((value) => {
            if (value === null) {
                return null
            }

            return value instanceof Date ? value : new Date(value)
        })
    }

    return props.modelValue instanceof Date
        ? props.modelValue
        : new Date(props.modelValue)
})
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
        <DatePicker
            :model-value="normalizedModelValue"
            :disabled="disabled"
            fluid
            :input-id="field.name"
            :invalid="Boolean(error)"
            :manual-input="false"
            :placeholder="placeholder"
            :show-time="showTime"
            @update:model-value="emit('update:modelValue', $event)"
        />
        <small v-if="help" class="text-[var(--cp-text-muted)]">{{
            help
        }}</small>
        <Message v-if="error" severity="error" size="small" variant="simple">{{
            error
        }}</Message>
    </label>
</template>
