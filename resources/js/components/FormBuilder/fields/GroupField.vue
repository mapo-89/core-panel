<script setup lang="ts">
import { computed } from 'vue'

import FormRenderer from '../FormRenderer.vue'
import type { FormErrors, FormModel, GroupFieldSchema } from '../types'
import { resolveFieldHelp, resolveFieldLabel } from '../useFormSchema'

const props = defineProps<{
    disabled?: boolean
    error?: string
    errors?: FormErrors
    field: GroupFieldSchema
    locale?: string
    modelValue: FormModel
}>()

const emit = defineEmits<{
    'update:modelValue': [value: FormModel]
}>()

const label = computed(() => resolveFieldLabel(props.field, props.locale))
const help = computed(() => resolveFieldHelp(props.field, props.locale))
const groupErrors = computed<FormErrors>(() => {
    return Object.fromEntries(
        Object.entries(props.errors ?? {})
            .filter(([key]) => key.startsWith(`${props.field.name}.`))
            .map(([key, value]) => [
                key.replace(`${props.field.name}.`, ''),
                value,
            ]),
    )
})
</script>

<template>
    <fieldset
        class="grid gap-4"
        :style="{
            gridColumn: field.columnSpan
                ? `span ${field.columnSpan}`
                : undefined,
        }"
    >
        <legend class="text-sm font-medium text-[var(--cp-text-primary)]">
            {{ label }}
        </legend>
        <small v-if="help" class="text-[var(--cp-text-muted)]">{{
            help
        }}</small>
        <FormRenderer
            :errors="groupErrors"
            :locale="locale"
            :model-value="modelValue ?? {}"
            :schema="field.schema"
            :wrap-in-form="false"
            @update:model-value="emit('update:modelValue', $event)"
        />
    </fieldset>
</template>
