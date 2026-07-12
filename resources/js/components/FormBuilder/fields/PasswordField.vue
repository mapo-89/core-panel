<script setup lang="ts">
import { computed } from 'vue'

import type { BaseField, FormModel } from '../types'
import type { PasswordRequirementsMeta } from '../passwordRequirements'
import {
    getNestedValue,
    resolveFieldHelp,
    resolveFieldLabel,
    resolveFieldPlaceholder,
} from '../useFormSchema'
import TranslatedPassword from '@core-panel/components/TranslatedPassword.vue'

const props = defineProps<{
    disabled?: boolean
    error?: string
    field: BaseField
    formModel?: FormModel
    locale?: string
    modelValue: string | null
}>()

const emit = defineEmits<{
    'update:modelValue': [value: string | null]
}>()

const label = computed(() => resolveFieldLabel(props.field, props.locale))
const placeholder = computed(() =>
    resolveFieldPlaceholder(props.field, props.locale),
)
const help = computed(() => resolveFieldHelp(props.field, props.locale))
const passwordRequirements = computed<
    PasswordRequirementsMeta['passwordRequirements'] | null
>(() => {
    const meta = props.field.meta

    if (meta === undefined || meta === null) {
        return null
    }

    const candidate = meta.passwordRequirements

    if (typeof candidate !== 'object' || candidate === null) {
        return null
    }

    return candidate as PasswordRequirementsMeta['passwordRequirements']
})
const matchedFieldValue = computed<string | undefined>(() => {
    const matchField = passwordRequirements.value?.matchField

    if (matchField === undefined || props.formModel === undefined) {
        return undefined
    }

    return String(getNestedValue(props.formModel, matchField) ?? '')
})
const minLengthValue = computed(
    () => passwordRequirements.value?.minLength ?? null,
)
</script>

<template>
    <label
        class="grid content-start gap-2"
        :style="{
            gridColumn: field.columnSpan
                ? `span ${field.columnSpan}`
                : undefined,
        }"
    >
        <span class="text-sm font-medium text-[var(--cp-text-primary)]">{{
            label
        }}</span>
        <TranslatedPassword
            :model-value="modelValue ?? ''"
            :disabled="disabled"
            fluid
            :invalid="Boolean(error)"
            :match-password="matchedFieldValue"
            :min-length="minLengthValue"
            :name="field.name"
            :placeholder="placeholder"
            toggle-mask
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
