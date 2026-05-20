<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import type { RequestPayload } from '@inertiajs/core'
import { trans } from 'laravel-vue-i18n'

import AppIcon from '@/components/AppIcon.vue'
import { useCan } from '@/composables/useCan'

import CheckboxField from './fields/CheckboxField.vue'
import DateField from './fields/DateField.vue'
import EmailField from './fields/EmailField.vue'
import FileField from './fields/FileField.vue'
import GroupField from './fields/GroupField.vue'
import MultiSelectField from './fields/MultiSelectField.vue'
import NumberField from './fields/NumberField.vue'
import PasswordField from './fields/PasswordField.vue'
import RadioField from './fields/RadioField.vue'
import RepeaterField from './fields/RepeaterField.vue'
import SelectField from './fields/SelectField.vue'
import TextField from './fields/TextField.vue'
import TextareaField from './fields/TextareaField.vue'
import type {
    FormErrors,
    FormModel,
    FormSchema,
    FormSchemaField,
    WayfinderAction,
} from './types'
import {
    createDefaultValue,
    getNestedValue,
    isFieldDisabled,
    isFieldVisible,
    resolveFieldError,
    resolveLocalePreference,
    setNestedValue,
} from './useFormSchema'

const props = withDefaults(
    defineProps<{
        action?: WayfinderAction | null
        columns?: number
        errors?: FormErrors
        locale?: string
        modelValue: FormModel
        permission?: string | null
        schema: FormSchema
        submitLabel?: string
        wrapInForm?: boolean
    }>(),
    {
        action: null,
        columns: 1,
        errors: () => ({}),
        locale: undefined,
        submitLabel: undefined,
        permission: null,
        wrapInForm: true,
    },
)

const emit = defineEmits<{
    submit: [payload: FormModel]
    'update:modelValue': [payload: FormModel]
}>()

const componentMap: Record<string, unknown> = {
    checkbox: CheckboxField,
    date: DateField,
    datetime: DateField,
    email: EmailField,
    file: FileField,
    group: GroupField,
    'multi-select': MultiSelectField,
    number: NumberField,
    password: PasswordField,
    radio: RadioField,
    repeater: RepeaterField,
    select: SelectField,
    text: TextField,
    textarea: TextareaField,
}
const { can } = useCan()

const activeLocale = computed(() => resolveLocalePreference(props.locale))
const isReadOnly = computed(() =>
    props.permission ? !can(props.permission) : false,
)
const visibleFields = computed(() =>
    (props.schema ?? []).filter((field) =>
        isFieldVisible(field, props.modelValue),
    ),
)
const gridStyle = computed(() => ({
    gridTemplateColumns: `repeat(${Math.max(props.columns, 1)}, minmax(0, 1fr))`,
}))

function resolveFieldComponent(field: FormSchemaField): unknown {
    return componentMap[field.type] ?? TextField
}

function updateFieldValue(name: string, value: unknown): void {
    emit('update:modelValue', setNestedValue(props.modelValue, name, value))
}

function submit(): void {
    emit('submit', props.modelValue)

    if (!props.action) {
        return
    }

    router.visit(props.action.url, {
        data: props.modelValue as RequestPayload,
        method: props.action.method ?? 'post',
    })
}

defineExpose({
    submit,
})
</script>

<template>
    <form
        v-if="wrapInForm"
        class="grid w-full gap-4"
        :style="gridStyle"
        @submit.prevent="submit"
    >
        <template v-for="field in visibleFields" :key="field.name">
            <input
                v-if="field.type === 'hidden'"
                :name="field.name"
                type="hidden"
                :value="modelValue[field.name] ?? createDefaultValue(field)"
            />
            <component
                :is="resolveFieldComponent(field)"
                v-else
                :disabled="isReadOnly || isFieldDisabled(field, modelValue)"
                :error="resolveFieldError(errors, field.name)"
                :errors="errors"
                :field="field"
                :form-model="field.type === 'password' ? modelValue : undefined"
                :locale="activeLocale"
                :model-value="
                    getNestedValue(modelValue, field.name) ??
                    createDefaultValue(field)
                "
                @update:model-value="updateFieldValue(field.name, $event)"
            />
        </template>

        <div
            v-if="(action || submitLabel) && !isReadOnly"
            class="flex justify-end"
        >
            <Button type="submit">
                <AppIcon name="save" />
                <span>{{ submitLabel ?? trans('common.ui.submit') }}</span>
            </Button>
        </div>
    </form>

    <div v-else class="grid w-full gap-4" :style="gridStyle">
        <template v-for="field in visibleFields" :key="field.name">
            <input
                v-if="field.type === 'hidden'"
                :name="field.name"
                type="hidden"
                :value="modelValue[field.name] ?? createDefaultValue(field)"
            />
            <component
                :is="resolveFieldComponent(field)"
                v-else
                :disabled="isReadOnly || isFieldDisabled(field, modelValue)"
                :error="resolveFieldError(errors, field.name)"
                :errors="errors"
                :field="field"
                :form-model="field.type === 'password' ? modelValue : undefined"
                :locale="activeLocale"
                :model-value="
                    getNestedValue(modelValue, field.name) ??
                    createDefaultValue(field)
                "
                @update:model-value="updateFieldValue(field.name, $event)"
            />
        </template>
    </div>
</template>
