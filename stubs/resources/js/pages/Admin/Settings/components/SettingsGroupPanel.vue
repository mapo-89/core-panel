<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

import AppIcon from '@core-panel/components/AppIcon.vue'
import FormRenderer from '@core-panel/components/FormBuilder/FormRenderer.vue'
import type {
    FormModel,
    FormSchema,
} from '@core-panel/components/FormBuilder/types'

import settings from '@/routes/core-panel/settings'
import type {
    SettingFieldRecord,
    SettingGroupRecord,
} from '@core-panel/types/core-panel'

type SettingFormValue = boolean | number | string | string[] | null

const props = defineProps<{
    group: SettingGroupRecord
}>()

const form = useForm({
    values: Object.fromEntries(
        props.group.fields.map((field) => [
            field.key,
            { value: cloneValue(field.value) as SettingFormValue },
        ]),
    ),
})

function cloneValue(value: unknown): SettingFormValue {
    if (Array.isArray(value)) {
        return value.map((entry) => String(entry))
    }

    if (typeof value === 'boolean' || typeof value === 'number') {
        return value
    }

    if (value === null || typeof value === 'string') {
        return value
    }

    return JSON.stringify(value)
}

const schemaByField = computed<Record<string, FormSchema>>(() => {
    return Object.fromEntries(
        props.group.fields.map((field) => [
            field.key,
            [
                {
                    label: field.label,
                    name: `values.${field.key}.value`,
                    options: field.options ?? [],
                    type: resolveFieldType(field),
                },
            ],
        ]),
    )
})

function resolveFieldType(
    field: SettingFieldRecord,
): FormSchema[number]['type'] {
    if (field.type === 'boolean') {
        return 'checkbox'
    }

    if (field.type === 'multiselect') {
        return 'multi-select'
    }

    if (field.type === 'number') {
        return 'number'
    }

    if (field.type === 'select') {
        return 'select'
    }

    return 'text'
}

function updateFieldValue(
    field: SettingFieldRecord,
    value: Record<string, unknown>,
): void {
    const entry = (
        value.values as Record<string, { value: SettingFormValue }>
    )?.[field.key]

    if (entry !== undefined) {
        form.values[field.key].value = entry.value
    }
}

function saveGroup(): void {
    form.put(settings.update.url(props.group.key), {
        onSuccess: () => {
            form.defaults()
        },
        preserveScroll: true,
    })
}
</script>

<template>
    <form
        class="cp-side-tabs__panel-surface cp-side-tabs__panel-surface--spacious"
        @submit.prevent="saveGroup"
    >
        <section class="cp-section cp-section--sticky-actions">
            <div class="cp-section__header">
                <div class="grid gap-1">
                    <h2
                        class="text-lg font-semibold text-[var(--cp-text-primary)]"
                    >
                        {{ group.label }}
                    </h2>
                    <p class="text-sm text-[var(--cp-text-muted)]">
                        {{ group.description }}
                    </p>
                </div>
            </div>

            <div class="cp-section__body">
                <div class="grid gap-4 md:grid-cols-2">
                    <section
                        v-for="field in group.fields"
                        :key="field.key"
                        class="grid gap-3 rounded-[var(--cp-radius-md)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="grid gap-1">
                                <label
                                    class="text-sm font-medium text-[var(--cp-text-primary)]"
                                >
                                    {{ field.label }}
                                </label>
                                <p
                                    v-if="field.help"
                                    class="text-sm text-[var(--cp-text-muted)]"
                                >
                                    {{ field.help }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Tag
                                    v-if="field.isPublic"
                                    severity="secondary"
                                    :value="$t('page-settings.public')"
                                />
                                <Tag
                                    v-if="field.isLocalized"
                                    severity="info"
                                    :value="$t('page-settings.localized')"
                                />
                            </div>
                        </div>

                        <FormRenderer
                            :errors="form.errors"
                            :model-value="form as unknown as FormModel"
                            :schema="schemaByField[field.key]"
                            :wrap-in-form="false"
                            @update:model-value="
                                updateFieldValue(field, $event)
                            "
                        />
                    </section>
                </div>

                <div
                    class="cp-settings-group-panel__actions cp-settings-group-panel__actions--sticky"
                >
                    <Button
                        :disabled="form.processing || !form.isDirty"
                        :loading="form.processing"
                        type="submit"
                    >
                        <AppIcon name="save" />
                        <span>{{ $t('common.ui.save') }}</span>
                    </Button>
                </div>
            </div>
        </section>
    </form>
</template>
