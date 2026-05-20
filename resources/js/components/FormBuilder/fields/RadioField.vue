<script setup lang="ts">
import { computed } from 'vue'

import type { BaseField } from '../types'
import {
    resolveFieldHelp,
    resolveFieldLabel,
    resolveFieldOptions,
} from '../useFormSchema'

const props = defineProps<{
    disabled?: boolean
    error?: string
    field: BaseField
    locale?: string
    modelValue: string | number | boolean | null
}>()

const emit = defineEmits<{
    'update:modelValue': [value: string | number | boolean | null]
}>()

const label = computed(() => resolveFieldLabel(props.field, props.locale))
const help = computed(() => resolveFieldHelp(props.field, props.locale))
const options = computed(() => resolveFieldOptions(props.field, props.locale))
</script>

<template>
    <fieldset
        class="grid gap-2"
        :style="{
            gridColumn: field.columnSpan
                ? `span ${field.columnSpan}`
                : undefined,
        }"
    >
        <legend class="text-sm font-medium text-[var(--cp-text-primary)]">
            {{ label }}
        </legend>
        <div class="grid gap-2">
            <label
                v-for="option in options"
                :key="String(option.value)"
                class="flex items-center gap-3 text-sm text-[var(--cp-text-primary)]"
            >
                <RadioButton
                    :disabled="disabled || option.disabled"
                    :input-id="`${field.name}-${String(option.value)}`"
                    :invalid="Boolean(error)"
                    :model-value="modelValue"
                    :name="field.name"
                    :value="option.value"
                    @update:model-value="emit('update:modelValue', $event)"
                />
                <span>{{ option.label }}</span>
            </label>
        </div>
        <small v-if="help" class="text-[var(--cp-text-muted)]">{{
            help
        }}</small>
        <Message v-if="error" severity="error" size="small" variant="simple">{{
            error
        }}</Message>
    </fieldset>
</template>
