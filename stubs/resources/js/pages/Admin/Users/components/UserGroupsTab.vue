<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import { trans } from 'laravel-vue-i18n'
import type { DataTableSortEvent } from 'primevue/datatable'
import { useDialog } from 'primevue/usedialog'

import AppIcon from '@/components/AppIcon.vue'
import ConfirmActionDialog from '@/components/Dialogs/ConfirmActionDialog.vue'
import UserGroupForm from '@/pages/Admin/UserGroups/components/UserGroupForm.vue'
import userGroupRoutes from '@/routes/core-panel/user-groups'
import type { UserGroupRecord } from '@/types/core-panel'
import ColumnVisibilityDropdown from '@core-panel/components/TableBuilder/ColumnVisibilityDropdown.vue'
import TablePagination from '@core-panel/components/TableBuilder/TablePagination.vue'
import type {
    DataTableColumn,
    DataTablePagination,
} from '@core-panel/components/TableBuilder/types'

const props = defineProps<{
    userGroups: UserGroupRecord[]
}>()

const dialog = useDialog()
const deleteDialogVisible = ref(false)
const pendingDeleteUserGroup = ref<UserGroupRecord | null>(null)
const search = ref('')
const visibleColumns = ref(['name', 'usersCount', 'createdAt'])
const rowsPerPage = ref(10)
const currentPage = ref(1)
const sortField = ref<'createdAt' | 'name' | 'usersCount'>('name')
const sortOrder = ref<1 | -1>(1)
const createdAtFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
})

const columns = computed<DataTableColumn[]>(() => [
    {
        key: 'name',
        label: null,
        meta: {
            labelKey: 'common.ui.name',
        },
        searchable: true,
        sortable: true,
        toggleable: false,
        type: 'text',
        visible: true,
    },
    {
        key: 'usersCount',
        label: null,
        meta: {
            labelKey: 'page-user-groups.members',
        },
        searchable: false,
        sortable: true,
        toggleable: true,
        type: 'text',
        visible: true,
    },
    {
        key: 'createdAt',
        label: null,
        meta: {
            labelKey: 'table-builder.columns.created_at',
        },
        searchable: false,
        sortable: true,
        toggleable: true,
        type: 'text',
        visible: true,
    },
])

const activeColumns = computed(() =>
    columns.value.filter(
        (column) =>
            !column.toggleable || visibleColumns.value.includes(column.key),
    ),
)

const filteredRows = computed(() => {
    const query = search.value.trim().toLowerCase()
    const rows = [...props.userGroups]

    if (query === '') {
        return rows
    }

    return rows.filter((row) => row.name.toLowerCase().includes(query))
})

const sortedRows = computed(() => {
    return [...filteredRows.value].sort((left, right) => {
        const leftValue = left[sortField.value]
        const rightValue = right[sortField.value]

        if (sortField.value === 'createdAt') {
            const leftDate = leftValue
                ? new Date(String(leftValue)).getTime()
                : 0
            const rightDate = rightValue
                ? new Date(String(rightValue)).getTime()
                : 0

            return (leftDate - rightDate) * sortOrder.value
        }

        if (typeof leftValue === 'number' && typeof rightValue === 'number') {
            return (leftValue - rightValue) * sortOrder.value
        }

        return (
            String(leftValue ?? '').localeCompare(String(rightValue ?? '')) *
            sortOrder.value
        )
    })
})

const paginatedRows = computed(() => {
    const start = (currentPage.value - 1) * rowsPerPage.value

    return sortedRows.value.slice(start, start + rowsPerPage.value)
})

const pagination = computed<DataTablePagination>(() => {
    const total = sortedRows.value.length
    const from =
        total === 0 ? null : (currentPage.value - 1) * rowsPerPage.value + 1
    const to =
        total === 0
            ? null
            : Math.min(currentPage.value * rowsPerPage.value, total)

    return {
        from,
        lastPage: Math.max(1, Math.ceil(total / rowsPerPage.value)),
        page: currentPage.value,
        perPage: rowsPerPage.value,
        to,
        total,
    }
})

function refresh(): void {
    router.reload({
        only: ['userGroups', 'userGroupOptions'],
    })
}

function openEditDialog(userGroup: UserGroupRecord): void {
    dialog.open(UserGroupForm, {
        data: {
            onSaved: refresh,
            userGroup,
        },
        props: {
            header: trans('page-user-groups.edit'),
            modal: true,
            style: {
                width: 'min(32rem, 92vw)',
            },
        },
    })
}

function destroyUserGroup(userGroup: UserGroupRecord): void {
    pendingDeleteUserGroup.value = userGroup
    deleteDialogVisible.value = true
}

function confirmDestroyUserGroup(): void {
    if (pendingDeleteUserGroup.value === null) {
        return
    }

    router.delete(
        userGroupRoutes.destroy.url(pendingDeleteUserGroup.value.id),
        {
            onFinish: () => {
                deleteDialogVisible.value = false
                pendingDeleteUserGroup.value = null
            },
            onSuccess: () => {
                refresh()
            },
        },
    )
}

function formatCreatedAt(value: string | null | undefined): string {
    if (!value) {
        return '—'
    }

    return createdAtFormatter.format(new Date(value))
}

function handleSort(event: DataTableSortEvent): void {
    if (typeof event.sortField !== 'string' || event.sortField === '') {
        return
    }

    sortField.value = event.sortField as 'createdAt' | 'name' | 'usersCount'
    sortOrder.value = event.sortOrder === -1 ? -1 : 1
}

function updateSearch(value: string): void {
    search.value = value
    currentPage.value = 1
}

function updateVisibleColumns(columns: string[]): void {
    visibleColumns.value = columns
}

function updatePage(event: { page: number; rows: number }): void {
    currentPage.value = event.page + 1
    rowsPerPage.value = event.rows
}
</script>

<template>
    <section class="cp-user-groups-tab cp-datatable">
        <ConfirmActionDialog
            v-model:visible="deleteDialogVisible"
            :cancel-label="$t('common.ui.cancel')"
            :confirm-label="$t('common.ui.delete')"
            confirm-severity="danger"
            :description="
                pendingDeleteUserGroup
                    ? $t('page-user-groups.delete_confirm', {
                          name: pendingDeleteUserGroup.name,
                      })
                    : null
            "
            icon="trash"
            :message="pendingDeleteUserGroup?.name ?? $t('common.ui.delete')"
            :title="$t('page-user-groups.delete_header')"
            tone="danger"
            @confirm="confirmDestroyUserGroup"
        />

        <div class="grid gap-3 px-[1.125rem] pt-[1.125rem] pb-1">
            <div class="cp-datatable__toolbar">
                <div class="cp-datatable__search">
                    <span class="cp-datatable__search-icon">
                        <AppIcon name="search" />
                    </span>
                    <InputText
                        :model-value="search"
                        class="cp-datatable__search-input"
                        :placeholder="$t('table-builder.labels.search')"
                        @update:model-value="updateSearch(String($event ?? ''))"
                    />
                </div>

                <div class="cp-datatable__toolbar-actions">
                    <ColumnVisibilityDropdown
                        :columns="columns"
                        :model-value="visibleColumns"
                        @update:model-value="updateVisibleColumns"
                    />
                </div>
            </div>
        </div>

        <div class="cp-card cp-datatable__surface cp-user-groups-tab__surface">
            <DataTable
                class="cp-datatable__table cp-user-groups-tab__table"
                :sort-field="sortField"
                :sort-order="sortOrder"
                :value="paginatedRows"
                removable-sort
                @sort="handleSort"
            >
                <template #empty>
                    <div
                        class="flex items-center justify-center gap-2.5 px-4 py-8 text-[var(--cp-text-muted)]"
                    >
                        <AppIcon name="columns" />
                        <span>{{
                            $t('table-builder.states.empty_title')
                        }}</span>
                    </div>
                </template>

                <Column
                    v-if="activeColumns.some((column) => column.key === 'name')"
                    field="name"
                    :header="$t('common.ui.name')"
                    sortable
                >
                    <template #body="{ data }">
                        <div class="flex items-center gap-[0.7rem]">
                            <span
                                class="cp-user-groups-tab__color"
                                :style="{ backgroundColor: data.color }"
                            />
                            <span>{{ data.name }}</span>
                        </div>
                    </template>
                </Column>
                <Column
                    v-if="
                        activeColumns.some(
                            (column) => column.key === 'usersCount',
                        )
                    "
                    field="usersCount"
                    :header="$t('page-user-groups.members')"
                    sortable
                />
                <Column
                    v-if="
                        activeColumns.some(
                            (column) => column.key === 'createdAt',
                        )
                    "
                    field="createdAt"
                    :header="$t('table-builder.columns.created_at')"
                    sortable
                >
                    <template #body="{ data }">
                        {{ formatCreatedAt(data.createdAt) }}
                    </template>
                </Column>
                <Column
                    :header="$t('common.ui.actions')"
                    header-class="cp-user-groups-tab__actions-header"
                    header-style="width: 8.25rem"
                >
                    <template #body="{ data }">
                        <div class="flex items-center justify-end gap-1.5">
                            <Button
                                :aria-label="$t('common.ui.edit')"
                                class="cp-datatable__action-button"
                                outlined
                                severity="secondary"
                                size="small"
                                @click="openEditDialog(data)"
                            >
                                <AppIcon name="pencil" />
                            </Button>
                            <Button
                                :aria-label="$t('common.ui.delete')"
                                class="cp-datatable__action-button cp-datatable__action-button--danger"
                                severity="danger"
                                size="small"
                                @click="destroyUserGroup(data)"
                            >
                                <AppIcon name="trash" />
                            </Button>
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <TablePagination
            class="-mt-[0.15rem]"
            :pagination="pagination"
            @page="updatePage"
        />
    </section>
</template>
