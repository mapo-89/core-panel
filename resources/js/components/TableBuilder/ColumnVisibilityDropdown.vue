<script setup lang="ts">
import { computed, ref } from 'vue'
import { trans } from 'laravel-vue-i18n'

import AppIcon from '../AppIcon.vue'
import type { DataTableColumn } from './types'

const props = defineProps<{
    columns: DataTableColumn[]
    modelValue: string[]
}>()

const emit = defineEmits<{
    'update:modelValue': [value: string[]]
}>()

const menu = ref()

const toggleableColumns = computed(() =>
    props.columns.filter((column) => column.toggleable),
)

function isVisible(key: string): boolean {
    return props.modelValue.includes(key)
}

function toggleColumn(key: string): void {
    emit(
        'update:modelValue',
        isVisible(key)
            ? props.modelValue.filter((value) => value !== key)
            : [...props.modelValue, key],
    )
}

function toggleMenu(event: Event): void {
    menu.value?.toggle(event)
}

function resolveColumnLabel(column: DataTableColumn): string {
    const labelKey = column.meta?.labelKey

    if (typeof labelKey === 'string' && labelKey !== '') {
        return trans(labelKey)
    }

    return column.label ?? column.key
}
</script>

<template>
    <div class="cp-datatable-columns">
        <Button
            severity="secondary"
            outlined
            size="small"
            class="cp-datatable-columns__trigger"
            @click="toggleMenu"
        >
            <AppIcon name="columns" />
            <span>{{ $t('table-builder.labels.columns') }}</span>
        </Button>

        <Menu ref="menu" :model="[]" popup class="cp-datatable-columns__menu">
            <template #start>
                <div class="cp-datatable-columns__content">
                    <slot name="before-columns" />

                    <label
                        v-for="column in toggleableColumns"
                        :key="column.key"
                        class="cp-datatable-columns__option"
                    >
                        <Checkbox
                            binary
                            :model-value="isVisible(column.key)"
                            @update:model-value="toggleColumn(column.key)"
                        />
                        <span>{{ resolveColumnLabel(column) }}</span>
                    </label>
                </div>
            </template>
        </Menu>
    </div>
</template>
