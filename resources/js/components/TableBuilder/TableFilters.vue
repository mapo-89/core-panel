<script setup lang="ts">
import type { DataTableFilter } from './types'

type DatePickerValue = Date | Date[] | Array<Date | null> | null | undefined

const props = defineProps<{
    filters: Record<string, unknown>
    schema: DataTableFilter[]
}>()

const emit = defineEmits<{
    change: [key: string, value: unknown]
}>()

function updateDateRange(
    key: string,
    part: 'from' | 'to',
    value: unknown,
): void {
    const current = (props.filters[key] ?? {}) as Record<string, unknown>

    emit('change', key, {
        ...current,
        [part]: value,
    })
}

function resolveDateValue(value: unknown): DatePickerValue {
    if (value === null || value === undefined || value === '') {
        return null
    }

    if (value instanceof Date) {
        return value
    }

    if (Array.isArray(value)) {
        return value.map((entry) => {
            if (entry === null || entry === undefined || entry === '') {
                return null
            }

            return entry instanceof Date ? entry : new Date(String(entry))
        })
    }

    return new Date(String(value))
}
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <template v-for="filter in schema" :key="filter.key">
            <label v-if="filter.type === 'text'" class="grid gap-2">
                <span
                    class="text-sm font-medium text-[var(--cp-text-primary)]"
                    >{{ filter.label }}</span
                >
                <InputText
                    :model-value="String(filters[filter.key] ?? '')"
                    :placeholder="
                        $t('table-builder.placeholders.filter', {
                            label: filter.label ?? filter.key,
                        })
                    "
                    @update:model-value="emit('change', filter.key, $event)"
                />
            </label>

            <label v-else-if="filter.type === 'select'" class="grid gap-2">
                <span
                    class="text-sm font-medium text-[var(--cp-text-primary)]"
                    >{{ filter.label }}</span
                >
                <Select
                    :model-value="filters[filter.key] ?? null"
                    :options="
                        Object.entries(filter.options ?? {}).map(
                            ([value, label]) => ({
                                label,
                                value,
                            }),
                        )
                    "
                    option-label="label"
                    option-value="value"
                    show-clear
                    :placeholder="
                        $t('table-builder.placeholders.filter', {
                            label: filter.label ?? filter.key,
                        })
                    "
                    @update:model-value="emit('change', filter.key, $event)"
                />
            </label>

            <div v-else-if="filter.type === 'date-range'" class="grid gap-2">
                <span
                    class="text-sm font-medium text-[var(--cp-text-primary)]"
                    >{{ filter.label }}</span
                >
                <div class="grid gap-2 md:grid-cols-2">
                    <DatePicker
                        :model-value="
                            resolveDateValue(
                                (
                                    filters[filter.key] as
                                        | Record<string, unknown>
                                        | undefined
                                )?.from,
                            )
                        "
                        date-format="yy-mm-dd"
                        fluid
                        input-class="w-full"
                        :placeholder="$t('table-builder.labels.from')"
                        @update:model-value="
                            updateDateRange(filter.key, 'from', $event)
                        "
                    />
                    <DatePicker
                        :model-value="
                            resolveDateValue(
                                (
                                    filters[filter.key] as
                                        | Record<string, unknown>
                                        | undefined
                                )?.to,
                            )
                        "
                        date-format="yy-mm-dd"
                        fluid
                        input-class="w-full"
                        :placeholder="$t('table-builder.labels.to')"
                        @update:model-value="
                            updateDateRange(filter.key, 'to', $event)
                        "
                    />
                </div>
            </div>
        </template>
    </div>
</template>
