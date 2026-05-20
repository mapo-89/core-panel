<script setup lang="ts">
import { computed } from 'vue'

import type { BaseField } from '../types'
import { resolveFieldHelp, resolveFieldLabel } from '../useFormSchema'

type FileSelectEvent = {
    files?: File[]
}

const props = defineProps<{
    disabled?: boolean
    error?: string
    field: BaseField
    locale?: string
    modelValue: File | File[] | null
}>()

const emit = defineEmits<{
    'update:modelValue': [value: File | File[] | null]
}>()

const label = computed(() => resolveFieldLabel(props.field, props.locale))
const help = computed(() => resolveFieldHelp(props.field, props.locale))
const multiple = computed(() => Boolean(props.field.meta?.multiple))

function onSelect(event: FileSelectEvent): void {
    const files = event.files ?? []

    emit('update:modelValue', multiple.value ? files : (files[0] ?? null))
}

function clear(): void {
    emit('update:modelValue', multiple.value ? [] : null)
}
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
        <span class="text-sm font-medium text-[var(--cp-text-primary)]">{{
            label
        }}</span>
        <FileUpload
            custom-upload
            :disabled="disabled"
            :invalid="Boolean(error)"
            mode="basic"
            :multiple="multiple"
            :name="field.name"
            @clear="clear"
            @select="onSelect"
        />
        <small v-if="help" class="text-[var(--cp-text-muted)]">{{
            help
        }}</small>
        <Message v-if="error" severity="error" size="small" variant="simple">{{
            error
        }}</Message>
    </div>
</template>
