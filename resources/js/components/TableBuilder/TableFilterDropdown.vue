<script setup lang="ts">
import { computed, ref } from 'vue'

import AppIcon from '../AppIcon.vue'
import TableFilters from './TableFilters.vue'
import type { DataTableFilter } from './types'

const props = defineProps<{
    filters: Record<string, unknown>
    schema: DataTableFilter[]
}>()

const emit = defineEmits<{
    change: [key: string, value: unknown]
}>()

const menu = ref()

const hasFilters = computed(() => props.schema.length > 0)

function toggleMenu(event: Event): void {
    menu.value?.toggle(event)
}

function forwardChange(key: string, value: unknown): void {
    emit('change', key, value)
}
</script>

<template>
    <div v-if="hasFilters" class="cp-datatable-filter">
        <Button
            severity="secondary"
            outlined
            size="small"
            class="cp-datatable-filter__trigger"
            @click="toggleMenu"
        >
            <AppIcon name="filter" />
            <span>{{ $t('table-builder.labels.filters') }}</span>
        </Button>

        <Menu
            ref="menu"
            :model="[]"
            popup
            class="cp-datatable-filter__menu cp-users-tab__filter-menu"
        >
            <template #start>
                <div
                    class="cp-datatable-filter__content cp-users-tab__filter-content"
                >
                    <TableFilters
                        :filters="filters"
                        :schema="schema"
                        @change="forwardChange"
                    />
                </div>
            </template>
        </Menu>
    </div>
</template>
