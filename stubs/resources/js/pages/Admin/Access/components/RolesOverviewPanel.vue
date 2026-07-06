<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import ConfirmActionDialog from '@/components/Dialogs/ConfirmActionDialog.vue'
import { useCan } from '@/composables/useCan'
import roleRoutes from '@/routes/core-panel/roles'
import type { PermissionRecord, RoleRecord } from '@/types/core-panel'
import TableBuilderDataTable from '@core-panel/components/TableBuilder/DataTable.vue'
import type { DataTableSchema } from '@core-panel/components/TableBuilder/types'

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
const createdAtFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
})

const canUpdateRole = computed(() => can('roles.update'))
const canDeleteRole = computed(() => can('roles.delete'))

const roleRows = computed(() =>
    props.roles.map((role) => ({
        ...role,
        createdAt: role.createdAt ?? null,
        displayLabel: role.displayLabel?.trim() || managedRoleLabel(role),
        permissionsCount: role.permissionsCount ?? role.permissions.length,
        usersCount: role.usersCount ?? 0,
    })),
)

const tableSchema = computed<DataTableSchema>(() => ({
    actions: [],
    bulkActions: [],
    columns: [
        {
            key: 'displayLabel',
            label: null,
            meta: {
                labelKey: 'common.ui.roles',
                searchKeys: ['name'],
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
                localSortType: 'number',
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
                localSortType: 'number',
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
                localSortType: 'date',
            },
            searchable: false,
            sortable: true,
            toggleable: true,
            type: 'text',
            visible: true,
        },
    ],
    filters: [],
    mode: 'local',
    pagination: {
        from: roleRows.value.length > 0 ? 1 : null,
        lastPage: Math.max(1, Math.ceil(roleRows.value.length / 10)),
        page: 1,
        perPage: 10,
        to:
            roleRows.value.length > 0
                ? Math.min(roleRows.value.length, 10)
                : null,
        total: roleRows.value.length,
    },
    rows: roleRows.value,
    state: {
        filters: {},
        search: '',
        sort: 'displayLabel',
        visibleColumns: [
            'displayLabel',
            'permissionsCount',
            'usersCount',
            'createdAt',
        ],
    },
}))

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

        <TableBuilderDataTable
            :action-column-width="
                canUpdateRole || canDeleteRole ? '8.25rem' : '0px'
            "
            :schema="tableSchema"
            surface-class="cp-roles-panel__surface"
        >
            <template #empty-state>
                <div
                    class="flex items-center justify-center gap-2.5 px-4 py-8 text-[var(--cp-text-muted)]"
                >
                    <AppIcon name="shield" />
                    <span>{{ $t('table-builder.states.empty_title') }}</span>
                </div>
            </template>

            <template #cell-displayLabel="{ row }">
                <div class="flex items-center justify-between gap-4">
                    <div class="grid gap-0.5">
                        <strong
                            class="text-[0.92rem] text-[var(--cp-text-primary)]"
                        >
                            {{ row.displayLabel }}
                        </strong>
                        <small
                            class="text-[0.78rem] text-[var(--cp-text-muted)]"
                        >
                            {{ row.name }}
                        </small>
                    </div>
                    <Tag
                        v-if="row.isProtected"
                        severity="contrast"
                        :value="$t('page-roles.managed_role')"
                    />
                </div>
            </template>

            <template #cell-permissionsCount="{ row }">
                <span
                    class="flex items-center gap-[0.45rem] font-semibold text-[var(--cp-text-primary)]"
                >
                    <AppIcon
                        class="h-[0.9rem] w-[0.9rem] text-[var(--cp-text-muted)]"
                        name="shield"
                    />
                    <span>{{ row.permissionsCount }}</span>
                </span>
            </template>

            <template #cell-usersCount="{ row }">
                <span
                    class="flex items-center gap-[0.45rem] font-semibold text-[var(--cp-text-primary)]"
                >
                    <AppIcon
                        class="h-[0.9rem] w-[0.9rem] text-[var(--cp-text-muted)]"
                        name="users"
                    />
                    <span>{{ row.usersCount }}</span>
                </span>
            </template>

            <template #cell-createdAt="{ row }">
                {{
                    formatCreatedAt(row.createdAt as string | null | undefined)
                }}
            </template>

            <template #row-actions="{ row }">
                <div class="flex items-center justify-end gap-[0.3rem]">
                    <Button
                        v-if="canUpdateRole"
                        :aria-label="$t('common.ui.edit')"
                        class="cp-datatable__action-button"
                        outlined
                        severity="secondary"
                        @click="openEditRoleDialog(row as RoleRecord)"
                    >
                        <AppIcon name="pencil" />
                    </Button>
                    <Button
                        v-if="canDeleteRole && !(row as RoleRecord).isProtected"
                        :aria-label="$t('common.ui.delete')"
                        class="cp-datatable__action-button cp-datatable__action-button--danger"
                        severity="danger"
                        @click="confirmDeleteRole(row as RoleRecord)"
                    >
                        <AppIcon name="trash" />
                    </Button>
                </div>
            </template>
        </TableBuilderDataTable>
    </section>
</template>
