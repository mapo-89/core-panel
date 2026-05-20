<script setup lang="ts">
import { computed, ref } from 'vue'

import AppIcon from '../AppIcon.vue'

import type { DataTableAction, DataTableRow } from './types'

const props = defineProps<{
    actions: DataTableAction[]
    row: DataTableRow
}>()

const emit = defineEmits<{
    run: [action: DataTableAction, row: DataTableRow]
}>()

const menu = ref()

const menuItems = computed(() =>
    props.actions.map((action) => ({
        action,
        command: () => emit('run', action, props.row),
        danger:
            action.type === 'delete' ||
            action.name.includes('delete') ||
            action.name.includes('force'),
        label: action.label ?? action.name,
    })),
)

function toggleMenu(event: Event): void {
    menu.value?.toggle(event)
}
</script>

<template>
    <div class="cp-datatable-actions">
        <Button
            :aria-label="$t('table-builder.actions.open_menu')"
            severity="secondary"
            outlined
            size="small"
            class="cp-datatable-actions__trigger"
            @click="toggleMenu"
        >
            <AppIcon name="menu" />
        </Button>
        <Menu
            ref="menu"
            :model="menuItems"
            popup
            class="cp-datatable-actions__menu"
        >
            <template #item="{ item, props: itemProps }">
                <button
                    type="button"
                    class="cp-datatable-actions__item"
                    :class="{
                        'cp-datatable-actions__item--danger': item.danger,
                    }"
                    v-bind="itemProps.action"
                >
                    <span class="cp-datatable-actions__item-label">
                        {{ item.label }}
                    </span>
                </button>
            </template>
        </Menu>
    </div>
</template>
