<script setup lang="ts">
import { computed } from 'vue'
import { trans } from 'laravel-vue-i18n'

import AppIcon from '../../AppIcon.vue'
import FormRenderer from '../FormRenderer.vue'
import type { FormErrors, FormModel, RepeaterFieldSchema } from '../types'
import {
    createDefaultValue,
    resolveFieldHelp,
    resolveFieldLabel,
} from '../useFormSchema'

const props = defineProps<{
    disabled?: boolean
    error?: string
    errors?: FormErrors
    field: RepeaterFieldSchema
    locale?: string
    modelValue: FormModel[]
}>()

const emit = defineEmits<{
    'update:modelValue': [value: FormModel[]]
}>()

const label = computed(() => resolveFieldLabel(props.field, props.locale))
const help = computed(() => resolveFieldHelp(props.field, props.locale))
const rows = computed(() => props.modelValue ?? [])

function createRow(): FormModel {
    return Object.fromEntries(
        props.field.schema.map((nestedField) => [
            nestedField.name,
            createDefaultValue(nestedField),
        ]),
    )
}

function addRow(): void {
    emit('update:modelValue', [...rows.value, createRow()])
}

function removeRow(index: number): void {
    emit(
        'update:modelValue',
        rows.value.filter((_, currentIndex) => currentIndex !== index),
    )
}

function updateRow(index: number, value: FormModel): void {
    emit(
        'update:modelValue',
        rows.value.map((row, currentIndex) =>
            currentIndex === index ? value : row,
        ),
    )
}

function rowErrors(index: number): FormErrors {
    return Object.fromEntries(
        Object.entries(props.errors ?? {})
            .filter(([key]) => key.startsWith(`${props.field.name}.${index}.`))
            .map(([key, value]) => [
                key.replace(`${props.field.name}.${index}.`, ''),
                value,
            ]),
    )
}
</script>

<template>
    <div
        class="grid gap-3"
        :style="{
            gridColumn: field.columnSpan
                ? `span ${field.columnSpan}`
                : undefined,
        }"
    >
        <div class="flex items-center justify-between gap-3">
            <div class="grid gap-1">
                <span
                    class="text-sm font-medium text-[var(--cp-text-primary)]"
                    >{{ label }}</span
                >
                <small v-if="help" class="text-[var(--cp-text-muted)]">{{
                    help
                }}</small>
            </div>
            <Button
                :disabled="disabled"
                class="gap-2"
                size="small"
                @click.prevent="addRow"
            >
                <AppIcon name="plus" />
                <span>{{ trans('common.ui.add') }}</span>
            </Button>
        </div>

        <div
            v-if="rows.length === 0"
            class="rounded border border-[var(--cp-border-subtle)] px-4 py-3 text-sm text-[var(--cp-text-muted)]"
        >
            {{ trans('common.ui.no_items_added') }}
        </div>

        <div
            v-for="(row, index) in rows"
            :key="index"
            class="grid gap-4 rounded border border-[var(--cp-border-subtle)] p-4"
        >
            <div class="flex justify-end">
                <Button
                    :disabled="disabled"
                    class="gap-2"
                    severity="danger"
                    size="small"
                    text
                    @click.prevent="removeRow(index)"
                >
                    <AppIcon name="trash" />
                    <span>{{ trans('common.ui.remove') }}</span>
                </Button>
            </div>

            <FormRenderer
                :errors="rowErrors(index)"
                :locale="locale"
                :model-value="row"
                :schema="field.schema"
                :wrap-in-form="false"
                @update:model-value="updateRow(index, $event)"
            />
        </div>

        <Message v-if="error" severity="error" size="small" variant="simple">{{
            error
        }}</Message>
    </div>
</template>
