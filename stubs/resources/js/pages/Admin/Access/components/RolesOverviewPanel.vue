<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import type { DataTableSortEvent } from 'primevue/datatable'

import AppIcon from '@/components/AppIcon.vue'
import ConfirmActionDialog from '@/components/Dialogs/ConfirmActionDialog.vue'
import { useCan } from '@/composables/useCan'
import roleRoutes from '@/routes/core-panel/roles'
import type { PermissionRecord, RoleRecord } from '@/types/core-panel'
import ColumnVisibilityDropdown from '@core-panel/components/TableBuilder/ColumnVisibilityDropdown.vue'
import TablePagination from '@core-panel/components/TableBuilder/TablePagination.vue'
import type {
    DataTableColumn,
    DataTablePagination,
} from '@core-panel/components/TableBuilder/types'

type ManagedRoleRecord = {
    name: string
    group: string
    label: string
    permissions: string[]
    protected: boolean
}

const props = withDefaults(
    defineProps<{
        defaultRoles: ManagedRoleRecord[]
        permissions: PermissionRecord[]
        permissionDefaults: string[]
        permissionGroups: Record<string, string>
        roles: RoleRecord[]
        variant?: 'full' | 'tab'
    }>(),
    {
        variant: 'full',
    },
)

const { can } = useCan()
const deleteDialogVisible = ref(false)
const pendingDeleteRole = ref<RoleRecord | null>(null)
const search = ref('')
const visibleColumns = ref(['permissionsCount', 'usersCount', 'createdAt'])
const rowsPerPage = ref(10)
const currentPage = ref(1)
const sortField = ref<
    'createdAt' | 'displayLabel' | 'permissionsCount' | 'usersCount'
>('displayLabel')
const sortOrder = ref<1 | -1>(1)
const createdAtFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
})

const canUpdateRole = computed(() => can('roles.update'))
const canDeleteRole = computed(() => can('roles.delete'))

const columns = computed<DataTableColumn[]>(() => [
    {
        key: 'displayLabel',
        label: null,
        meta: {
            labelKey: 'common.ui.roles',
        },
        searchable: true,
        sortable: true,
        toggleable: false,
        type: 'text',
        visible: true,
    },
    {
        key: 'permissionsCount',
        label: null,
        meta: {
            labelKey: 'common.ui.permissions',
        },
        searchable: false,
        sortable: true,
        toggleable: true,
        type: 'text',
        visible: true,
    },
    {
        key: 'usersCount',
        label: null,
        meta: {
            labelKey: 'navigation.users',
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

const roleRows = computed(() =>
    props.roles.map((role) => ({
        ...role,
        createdAt: role.createdAt ?? null,
        displayLabel: role.displayLabel?.trim() || managedRoleLabel(role),
        permissionsCount: role.permissionsCount ?? role.permissions.length,
        usersCount: role.usersCount ?? 0,
    })),
)

const filteredRows = computed(() => {
    const query = search.value.trim().toLowerCase()

    if (query === '') {
        return roleRows.value
    }

    return roleRows.value.filter((role) =>
        [role.displayLabel, role.name]
            .filter(Boolean)
            .some((value) => value.toLowerCase().includes(query)),
    )
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

function managedRoleLabel(role: RoleRecord): string {
    return (
        props.defaultRoles.find((defaultRole) => defaultRole.name === role.name)
            ?.label ?? headline(role.name)
    )
}

function refresh(): void {
    router.reload({
        only: [
            'defaultRoles',
            'permissionDefaults',
            'permissionGroups',
            'permissions',
            'roles',
        ],
    })
}

function openEditRoleDialog(role: RoleRecord): void {
    const matrixUrl = roleRoutes.matrix.url()
    const glue = matrixUrl.includes('?') ? '&' : '?'

    router.visit(`${matrixUrl}${glue}role=${encodeURIComponent(role.id)}`)
}

function confirmDeleteRole(role: RoleRecord): void {
    pendingDeleteRole.value = role
    deleteDialogVisible.value = true
}

function destroyRole(): void {
    if (pendingDeleteRole.value === null) {
        return
    }

    router.delete(roleRoutes.destroy.url(pendingDeleteRole.value.id), {
        onFinish: () => {
            deleteDialogVisible.value = false
            pendingDeleteRole.value = null
        },
        onSuccess: () => refresh(),
    })
}

function handleSort(event: DataTableSortEvent): void {
    if (typeof event.sortField !== 'string' || event.sortField === '') {
        return
    }

    sortField.value = event.sortField as
        | 'createdAt'
        | 'displayLabel'
        | 'permissionsCount'
        | 'usersCount'
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

function formatCreatedAt(value: string | null | undefined): string {
    if (!value) {
        return '—'
    }

    return createdAtFormatter.format(new Date(value))
}

function headline(value: string): string {
    return value
        .replace(/[._-]+/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase())
}
</script>

<template>
    <section class="cp-datatable cp-roles-panel">
        <ConfirmActionDialog
            v-model:visible="deleteDialogVisible"
            :cancel-label="$t('common.ui.cancel')"
            :confirm-label="$t('common.ui.delete')"
            confirm-severity="danger"
            :description="
                pendingDeleteRole
                    ? $t('page-roles.delete_role_message', {
                          name:
                              pendingDeleteRole.displayLabel ??
                              pendingDeleteRole.name,
                      })
                    : null
            "
            icon="trash"
            :message="
                pendingDeleteRole?.displayLabel ??
                pendingDeleteRole?.name ??
                $t('common.ui.delete')
            "
            :title="$t('page-roles.delete_role_header')"
            tone="danger"
            @confirm="destroyRole"
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

        <div class="cp-card cp-datatable__surface cp-roles-panel__surface">
            <DataTable
                class="cp-datatable__table cp-roles-panel__table"
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
                        <AppIcon name="shield" />
                        <span>{{
                            $t('table-builder.states.empty_title')
                        }}</span>
                    </div>
                </template>

                <Column
                    v-if="
                        activeColumns.some(
                            (column) => column.key === 'displayLabel',
                        )
                    "
                    field="displayLabel"
                    :header="$t('common.ui.roles')"
                    sortable
                >
                    <template #body="{ data }">
                        <div class="flex items-center justify-between gap-4">
                            <div class="grid gap-0.5">
                                <strong
                                    class="text-[0.92rem] text-[var(--cp-text-primary)]"
                                    >{{ data.displayLabel }}</strong
                                >
                                <small
                                    class="text-[0.78rem] text-[var(--cp-text-muted)]"
                                    >{{ data.name }}</small
                                >
                            </div>
                            <Tag
                                v-if="data.isProtected"
                                severity="contrast"
                                :value="$t('page-roles.managed_role')"
                            />
                        </div>
                    </template>
                </Column>
                <Column
                    v-if="
                        activeColumns.some(
                            (column) => column.key === 'permissionsCount',
                        )
                    "
                    field="permissionsCount"
                    :header="$t('common.ui.permissions')"
                    sortable
                >
                    <template #body="{ data }">
                        <span
                            class="flex items-center gap-[0.45rem] font-semibold text-[var(--cp-text-primary)]"
                        >
                            <AppIcon
                                class="h-[0.9rem] w-[0.9rem] text-[var(--cp-text-muted)]"
                                name="shield"
                            />
                            <span>{{ data.permissionsCount }}</span>
                        </span>
                    </template>
                </Column>
                <Column
                    v-if="
                        activeColumns.some(
                            (column) => column.key === 'usersCount',
                        )
                    "
                    field="usersCount"
                    :header="$t('navigation.users')"
                    sortable
                >
                    <template #body="{ data }">
                        <span
                            class="flex items-center gap-[0.45rem] font-semibold text-[var(--cp-text-primary)]"
                        >
                            <AppIcon
                                class="h-[0.9rem] w-[0.9rem] text-[var(--cp-text-muted)]"
                                name="users"
                            />
                            <span>{{ data.usersCount }}</span>
                        </span>
                    </template>
                </Column>
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
                    header-class="cp-roles-panel__actions-header"
                    header-style="width: 8.25rem"
                >
                    <template #body="{ data }">
                        <div class="flex items-center justify-end gap-[0.3rem]">
                            <Button
                                v-if="canUpdateRole"
                                :aria-label="$t('common.ui.edit')"
                                class="cp-datatable__action-button"
                                outlined
                                severity="secondary"
                                @click="openEditRoleDialog(data)"
                            >
                                <AppIcon name="pencil" />
                            </Button>
                            <Button
                                v-if="canDeleteRole && !data.isProtected"
                                :aria-label="$t('common.ui.delete')"
                                class="cp-datatable__action-button cp-datatable__action-button--danger"
                                severity="danger"
                                @click="confirmDeleteRole(data)"
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
