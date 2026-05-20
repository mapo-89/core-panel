<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { computed, ref } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import ConfirmActionDialog from '@/components/Dialogs/ConfirmActionDialog.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import ColumnVisibilityDropdown from '@core-panel/components/TableBuilder/ColumnVisibilityDropdown.vue'
import TableBuilderDataTable from '@core-panel/components/TableBuilder/DataTable.vue'
import userRoutes from '@/routes/core-panel/users'
import type {
    DataTableSchema,
    UserCapabilities,
    UserRecord,
} from '@/types/core-panel'

const props = defineProps<{
    capabilities: UserCapabilities
    filters: {
        role?: string
        search: string
        status?: string
        userGroupId?: string
        withTrashed: boolean
    }
    onEditUser?: (user: UserRecord) => void
    roleLabels: Record<string, string>
    userGroupOptions: Array<{
        color: string
        label: string
        value: string
    }>
    users: UserRecord[]
    usersTable: {
        pagination: DataTableSchema['pagination']
        state: DataTableSchema['state']
    }
}>()

const filterMenu = ref<{ toggle: (event: Event) => void } | null>(null)
const rowActionsMenu = ref<{ toggle: (event: Event) => void } | null>(null)
const deleteDialogState = ref<{
    type: 'delete' | 'force-delete'
    user: UserRecord
} | null>(null)
const deleteDialogConfig = computed(() => {
    if (deleteDialogState.value === null) {
        return null
    }

    return deleteDialogState.value.type === 'force-delete'
        ? {
              confirmLabel: trans('common.ui.force_delete'),
              description: trans('page-users.force_delete_message', {
                  name: deleteDialogState.value.user.name,
              }),
              message: deleteDialogState.value.user.name,
              title: trans('common.ui.force_delete'),
          }
        : {
              confirmLabel: trans('common.ui.delete'),
              description: trans('page-users.delete_message', {
                  name: deleteDialogState.value.user.name,
              }),
              message: deleteDialogState.value.user.name,
              title: trans('page-users.delete_header'),
          }
})
const rowActionItems = ref<
    Array<{
        class?: string
        command: () => void
        label: string
    }>
>([])
const createdAtFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
})
const statusOptions = computed(() =>
    [
        {
            label: trans('common.ui.active'),
            value: 'active',
        },
        props.capabilities.supportsStatus
            ? {
                  label: trans('common.ui.inactive'),
                  value: 'inactive',
              }
            : null,
        props.capabilities.supportsStatus
            ? {
                  label: trans('common.ui.blocked'),
                  value: 'blocked',
              }
            : null,
    ].filter(
        (option): option is { label: string; value: string } => option !== null,
    ),
)
const roleOptions = computed(() =>
    Object.entries(props.roleLabels)
        .sort((left, right) => left[1].localeCompare(right[1]))
        .map(([value, label]) => ({
            label,
            value,
        })),
)
const userGroupOptions = computed(() =>
    [...props.userGroupOptions]
        .sort((left, right) => left.label.localeCompare(right.label))
        .map((option) => ({
            label: option.label,
            value: option.value,
        })),
)
const usersTableSchema = computed<DataTableSchema>(() => ({
    actions: [],
    bulkActions: [],
    columns: [
        {
            key: 'first_name',
            label: null,
            meta: {
                labelKey: 'navigation.users',
            },
            searchable: true,
            sortable: true,
            toggleable: false,
            type: 'text',
            visible: true,
        },
        {
            key: 'roles',
            label: null,
            meta: {
                labelKey: 'common.ui.role',
            },
            searchable: false,
            sortable: false,
            toggleable: true,
            type: 'text',
            visible: true,
        },
        {
            key: 'user_groups',
            label: null,
            meta: {
                labelKey: 'navigation.user_groups',
            },
            searchable: false,
            sortable: false,
            toggleable: true,
            type: 'text',
            visible: true,
        },
        {
            key: 'status',
            label: null,
            meta: {
                labelKey: 'common.ui.status',
            },
            searchable: false,
            sortable: false,
            toggleable: true,
            type: 'text',
            visible: true,
        },
        {
            key: 'locale',
            label: null,
            meta: {
                labelKey: 'common.ui.locale',
            },
            searchable: false,
            sortable: true,
            toggleable: true,
            type: 'text',
            visible: true,
        },
        {
            key: 'created_at',
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
    ],
    filters: [],
    pagination: props.usersTable.pagination,
    rows: props.users.map((user) => ({
        id: user.id,
        created_at: user.createdAt,
        first_name: user.firstName || user.name,
        locale: user.locale ?? '—',
        roles: user.roles,
        status: user.status,
        user_groups: user.userGroups,
        user,
    })),
    state: props.usersTable.state,
}))

function updateUsersIndex(overrides: {
    role?: string
    status?: string
    userGroupId?: string
}): void {
    router.get(
        userRoutes.index.url(),
        {
            columns:
                props.usersTable.state.visibleColumns.join(',') || undefined,
            role:
                'role' in overrides
                    ? overrides.role
                    : (props.filters.role ?? undefined),
            search: props.filters.search || undefined,
            sort: props.usersTable.state.sort || undefined,
            status:
                'status' in overrides
                    ? overrides.status
                    : (props.filters.status ?? undefined),
            user_group_id:
                'userGroupId' in overrides
                    ? overrides.userGroupId
                    : (props.filters.userGroupId ?? undefined),
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    )
}

function clearToolbarFilter(key: 'role' | 'status' | 'userGroupId'): void {
    updateUsersIndex({
        [key]: undefined,
    })
}

function resetToolbarFilters(): void {
    updateUsersIndex({
        role: undefined,
        status: undefined,
        userGroupId: undefined,
    })
}

function openFilterMenu(event: Event): void {
    filterMenu.value?.toggle(event)
}

function translateStatus(status: string): string {
    return status === 'blocked'
        ? trans('common.ui.blocked')
        : status === 'inactive'
          ? trans('common.ui.inactive')
          : trans('common.ui.active')
}

function statusSeverity(
    status: string,
): 'contrast' | 'danger' | 'secondary' | 'success' | 'warn' {
    return status === 'blocked'
        ? 'danger'
        : status === 'inactive'
          ? 'warn'
          : 'success'
}

function invitationStatusSeverity(
    status: UserRecord['invitationStatus'],
): 'contrast' | 'danger' | 'secondary' | 'success' | 'warn' {
    return status === 'accepted'
        ? 'success'
        : status === 'expired'
          ? 'warn'
          : status === 'pending'
            ? 'contrast'
            : 'secondary'
}

function invitationActionIcon(status: UserRecord['invitationStatus']): string {
    return status === 'accepted'
        ? 'circle-check-big'
        : status === 'expired'
          ? 'circle-alert'
          : status === 'pending'
            ? 'clock-3'
            : 'envelope'
}

function invitationActionTooltip(
    status: UserRecord['invitationStatus'],
): string {
    return trans(`page-users.invitation_action_hint.${status}`)
}

function userActions(user: UserRecord): Array<{
    danger?: boolean
    key: string
    label: string
    run: () => void
}> {
    return [
        {
            key: 'view',
            label: trans('common.ui.view'),
            run: () => router.visit(userRoutes.show.url(user.id)),
        },
        ...(user.requiresPasswordSetup || user.invitationStatus === 'accepted'
            ? [
                  {
                      key: 'reinvite',
                      label: trans('page-users.invite'),
                      run: () => reinviteUser(user),
                  },
              ]
            : []),
        {
            key: 'edit',
            label: trans('common.ui.edit'),
            run: () => props.onEditUser?.(user),
        },
        ...(user.deletedAt && props.capabilities.supportsSoftDeletes
            ? [
                  {
                      key: 'restore',
                      label: trans('common.ui.restore'),
                      run: () => restoreUser(user),
                  },
                  ...(user.canForceDelete
                      ? [
                            {
                                danger: true,
                                key: 'force-delete',
                                label: trans('common.ui.force_delete'),
                                run: () => forceDeleteUser(user),
                            },
                        ]
                      : []),
              ]
            : user.canDelete
              ? [
                    {
                        danger: true,
                        key: 'delete',
                        label: trans('common.ui.delete'),
                        run: () => destroyUser(user),
                    },
                ]
              : []),
    ]
}

function reinviteUser(user: UserRecord): void {
    if (user.invitationStatus === 'accepted') {
        return
    }

    router.post(
        userRoutes.reinvite.url(user.id),
        {},
        {
            preserveScroll: true,
        },
    )
}

function destroyUser(user: UserRecord): void {
    deleteDialogState.value = {
        type: 'delete',
        user,
    }
}

function restoreUser(user: UserRecord): void {
    router.post(
        userRoutes.restore.url(user.id),
        {},
        {
            preserveScroll: true,
        },
    )
}

function forceDeleteUser(user: UserRecord): void {
    deleteDialogState.value = {
        type: 'force-delete',
        user,
    }
}

function confirmDeleteAction(): void {
    if (deleteDialogState.value === null) {
        return
    }

    const { type, user } = deleteDialogState.value

    const action =
        type === 'force-delete'
            ? userRoutes.forceDelete.url(user.id)
            : userRoutes.destroy.url(user.id)

    router.delete(action, {
        preserveScroll: true,
        onFinish: () => {
            deleteDialogState.value = null
        },
    })
}

function closeDeleteDialog(): void {
    deleteDialogState.value = null
}

function openRowActionsMenu(event: Event, user: UserRecord): void {
    rowActionItems.value = userActions(user).map((action) => ({
        class: action.danger ? 'cp-users-tab__menu-item--danger' : undefined,
        command: action.run,
        label: action.label,
    }))

    rowActionsMenu.value?.toggle(event)
}
</script>

<template>
    <div class="cp-users-tab">
        <ConfirmActionDialog
            v-if="deleteDialogConfig"
            visible
            :cancel-label="$t('common.ui.cancel')"
            :confirm-label="deleteDialogConfig.confirmLabel"
            confirm-severity="danger"
            :description="deleteDialogConfig.description"
            icon="trash"
            :message="deleteDialogConfig.message"
            :title="deleteDialogConfig.title"
            tone="danger"
            @confirm="confirmDeleteAction"
            @update:visible="(visible) => !visible && closeDeleteDialog()"
        />

        <TableBuilderDataTable
            :empty-message="$t('page-users.index_description')"
            :schema="usersTableSchema"
            :only="['filters', 'users', 'usersTable']"
        >
            <template
                #toolbar-actions="{
                    columns,
                    setVisibleColumns,
                    visibleColumns,
                }"
            >
                <div class="cp-datatable__toolbar-actions">
                    <Button
                        severity="secondary"
                        outlined
                        size="small"
                        class="cp-datatable__toolbar-button"
                        @click="openFilterMenu"
                    >
                        <AppIcon name="filter" />
                        <span>{{ $t('table-builder.labels.filters') }}</span>
                    </Button>
                    <ColumnVisibilityDropdown
                        :columns="columns"
                        :model-value="visibleColumns"
                        @update:model-value="setVisibleColumns"
                    />
                </div>
            </template>

            <template #toolbar-footer>
                <div
                    v-if="
                        props.filters.status ||
                        props.filters.role ||
                        props.filters.userGroupId
                    "
                    class="flex flex-wrap items-center gap-2"
                >
                    <button
                        v-if="props.filters.status"
                        class="inline-flex items-center gap-2 rounded-full border border-[color:var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel-alt)_60%,transparent)] px-3 py-1.5 text-xs font-medium text-[var(--cp-text-primary)]"
                        type="button"
                        @click="clearToolbarFilter('status')"
                    >
                        <span>
                            {{ $t('common.ui.status') }}:
                            {{ translateStatus(props.filters.status) }}
                        </span>
                        <AppIcon name="x" />
                    </button>
                    <button
                        v-if="props.filters.role"
                        class="inline-flex items-center gap-2 rounded-full border border-[color:var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel-alt)_60%,transparent)] px-3 py-1.5 text-xs font-medium text-[var(--cp-text-primary)]"
                        type="button"
                        @click="clearToolbarFilter('role')"
                    >
                        <span>
                            {{ $t('common.ui.role') }}:
                            {{
                                props.roleLabels[props.filters.role] ??
                                props.filters.role
                            }}
                        </span>
                        <AppIcon name="x" />
                    </button>
                    <button
                        v-if="props.filters.userGroupId"
                        class="inline-flex items-center gap-2 rounded-full border border-[color:var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel-alt)_60%,transparent)] px-3 py-1.5 text-xs font-medium text-[var(--cp-text-primary)]"
                        type="button"
                        @click="clearToolbarFilter('userGroupId')"
                    >
                        <span>
                            {{ $t('navigation.user_groups') }}:
                            {{
                                userGroupOptions.find(
                                    (option) =>
                                        option.value ===
                                        props.filters.userGroupId,
                                )?.label ?? props.filters.userGroupId
                            }}
                        </span>
                        <AppIcon name="x" />
                    </button>
                    <Button
                        outlined
                        severity="secondary"
                        size="small"
                        class="cp-datatable__toolbar-button"
                        @click="resetToolbarFilters"
                    >
                        {{ $t('table-builder.actions.reset_filters') }}
                    </Button>
                </div>
            </template>

            <template #cell-first_name="{ row }">
                <div class="flex items-center gap-3">
                    <div class="shrink-0">
                        <UserAvatar
                            :avatar-url="(row.user as UserRecord).avatarUrl"
                            :initials="
                                [
                                    (row.user as UserRecord).firstName,
                                    (row.user as UserRecord).lastName,
                                ]
                                    .filter(Boolean)
                                    .map((value) => value.at(0))
                                    .join('')
                                    .toUpperCase()
                            "
                            :presence-last-seen-at="
                                (row.user as UserRecord).presenceLastSeenAt ??
                                null
                            "
                            :presence-status="
                                (row.user as UserRecord).presenceStatus ??
                                'offline'
                            "
                            :user-id="(row.user as UserRecord).id"
                            size="sm"
                        />
                    </div>
                    <div class="grid min-w-0 gap-0.5">
                        <Link
                            :href="
                                userRoutes.show.url((row.user as UserRecord).id)
                            "
                            class="truncate text-sm font-semibold text-[var(--cp-text-primary)]"
                        >
                            {{ (row.user as UserRecord).name }}
                        </Link>
                        <span
                            class="truncate text-xs text-[var(--cp-text-muted)]"
                        >
                            {{ (row.user as UserRecord).email }}
                        </span>
                    </div>
                </div>
            </template>

            <template #cell-roles="{ row }">
                <div class="flex flex-wrap items-center gap-2">
                    <Tag
                        v-for="role in (row.user as UserRecord).roles.slice(
                            0,
                            2,
                        )"
                        :key="role"
                        :value="props.roleLabels[role] ?? role"
                        severity="secondary"
                    />
                    <Badge
                        v-if="(row.user as UserRecord).roles.length > 2"
                        :value="`+${(row.user as UserRecord).roles.length - 2}`"
                    />
                    <span
                        v-if="(row.user as UserRecord).roles.length === 0"
                        class="text-sm text-[var(--cp-text-muted)]"
                    >
                        —
                    </span>
                </div>
            </template>

            <template #cell-user_groups="{ row }">
                <div
                    v-if="
                        ((row.user as UserRecord).userGroups ?? []).length > 0
                    "
                    class="flex flex-wrap items-center gap-2"
                >
                    <span
                        v-for="group in (row.user as UserRecord).userGroups"
                        :key="group.id"
                        class="inline-flex items-center gap-2 rounded-full border border-[color:var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel-alt)_60%,transparent)] px-2.5 py-1 text-xs font-medium text-[var(--cp-text-primary)]"
                    >
                        <span
                            class="h-2 w-2 rounded-full"
                            :style="{
                                backgroundColor: group.color,
                            }"
                        />
                        {{ group.name }}
                    </span>
                </div>
                <span v-else class="text-sm text-[var(--cp-text-muted)]"
                    >—</span
                >
            </template>

            <template #cell-status="{ row }">
                <div class="flex items-center gap-2">
                    <Tag
                        :severity="
                            statusSeverity((row.user as UserRecord).status)
                        "
                        :value="
                            translateStatus((row.user as UserRecord).status)
                        "
                    />
                    <span
                        v-tooltip.top="
                            (row.user as UserRecord).twoFactorEnabled
                                ? $t('common.ui.two_factor_enabled')
                                : $t('common.ui.two_factor_disabled')
                        "
                        class="cp-users-tab__status-security"
                        :class="{
                            'cp-users-tab__status-security--active': (
                                row.user as UserRecord
                            ).twoFactorEnabled,
                            'cp-users-tab__status-security--inactive': !(
                                row.user as UserRecord
                            ).twoFactorEnabled,
                        }"
                    >
                        <AppIcon name="lock" />
                    </span>
                </div>
            </template>

            <template #cell-locale="{ row }">
                <span class="text-sm text-[var(--cp-text-primary)]">
                    {{ (row.user as UserRecord).locale ?? '—' }}
                </span>
            </template>

            <template #cell-created_at="{ row }">
                <span class="text-sm text-[var(--cp-text-primary)]">
                    {{
                        (row.user as UserRecord).createdAt
                            ? createdAtFormatter.format(
                                  new Date(
                                      (row.user as UserRecord).createdAt ?? '',
                                  ),
                              )
                            : '—'
                    }}
                </span>
            </template>

            <template #row-actions="{ row }">
                <div class="flex items-center justify-end gap-1.5">
                    <template
                        v-if="userActions(row.user as UserRecord).length <= 4"
                    >
                        <Link
                            class="cp-users-tab__action-link"
                            :href="
                                userRoutes.show.url((row.user as UserRecord).id)
                            "
                        >
                            <Button
                                :aria-label="$t('common.ui.view')"
                                class="cp-datatable__action-button"
                                outlined
                                severity="secondary"
                                size="small"
                            >
                                <AppIcon name="eye" />
                            </Button>
                        </Link>
                        <Button
                            v-if="
                                (row.user as UserRecord)
                                    .requiresPasswordSetup ||
                                (row.user as UserRecord).invitationStatus ===
                                    'accepted'
                            "
                            v-tooltip.top="
                                invitationActionTooltip(
                                    (row.user as UserRecord).invitationStatus,
                                )
                            "
                            :aria-label="$t('page-users.invite')"
                            class="cp-datatable__action-button"
                            :disabled="
                                (row.user as UserRecord).invitationStatus ===
                                'accepted'
                            "
                            outlined
                            :severity="
                                invitationStatusSeverity(
                                    (row.user as UserRecord).invitationStatus,
                                )
                            "
                            size="small"
                            @click="reinviteUser(row.user as UserRecord)"
                        >
                            <AppIcon
                                :name="
                                    invitationActionIcon(
                                        (row.user as UserRecord)
                                            .invitationStatus,
                                    )
                                "
                            />
                        </Button>
                        <Button
                            :aria-label="$t('common.ui.edit')"
                            class="cp-datatable__action-button"
                            severity="secondary"
                            outlined
                            size="small"
                            @click="props.onEditUser?.(row.user as UserRecord)"
                        >
                            <AppIcon name="pencil" />
                        </Button>
                        <Button
                            v-if="
                                (row.user as UserRecord).deletedAt &&
                                capabilities.supportsSoftDeletes
                            "
                            :aria-label="$t('common.ui.restore')"
                            class="cp-datatable__action-button"
                            outlined
                            severity="secondary"
                            size="small"
                            @click="restoreUser(row.user as UserRecord)"
                        >
                            <AppIcon name="refresh" />
                        </Button>
                        <Button
                            v-if="
                                !(row.user as UserRecord).deletedAt &&
                                (row.user as UserRecord).canDelete
                            "
                            :aria-label="$t('common.ui.delete')"
                            class="cp-datatable__action-button cp-datatable__action-button--danger"
                            severity="danger"
                            size="small"
                            @click="destroyUser(row.user as UserRecord)"
                        >
                            <AppIcon name="trash" />
                        </Button>
                    </template>
                    <template v-else>
                        <Button
                            :aria-label="$t('common.ui.actions')"
                            class="cp-datatable__action-button"
                            outlined
                            severity="secondary"
                            size="small"
                            @click="
                                openRowActionsMenu(
                                    $event,
                                    row.user as UserRecord,
                                )
                            "
                        >
                            <AppIcon name="more-vertical" />
                        </Button>
                    </template>
                </div>
            </template>
        </TableBuilderDataTable>

        <Menu
            ref="filterMenu"
            popup
            :model="[]"
            class="cp-users-tab__filter-menu"
        >
            <template #start>
                <div class="cp-users-tab__filter-content">
                    <Select
                        :model-value="props.filters.status ?? null"
                        :options="statusOptions"
                        option-label="label"
                        option-value="value"
                        show-clear
                        :placeholder="$t('common.ui.status')"
                        @update:model-value="
                            updateUsersIndex({
                                status:
                                    typeof $event === 'string'
                                        ? $event
                                        : undefined,
                            })
                        "
                    />
                    <Select
                        :model-value="props.filters.role ?? null"
                        :options="roleOptions"
                        option-label="label"
                        option-value="value"
                        show-clear
                        :placeholder="$t('common.ui.role')"
                        @update:model-value="
                            updateUsersIndex({
                                role:
                                    typeof $event === 'string'
                                        ? $event
                                        : undefined,
                            })
                        "
                    />
                    <Select
                        :model-value="props.filters.userGroupId ?? null"
                        :options="userGroupOptions"
                        option-label="label"
                        option-value="value"
                        show-clear
                        :placeholder="$t('navigation.user_groups')"
                        @update:model-value="
                            updateUsersIndex({
                                userGroupId:
                                    typeof $event === 'string'
                                        ? $event
                                        : undefined,
                            })
                        "
                    />
                </div>
            </template>
        </Menu>

        <Menu ref="rowActionsMenu" popup :model="rowActionItems" />
    </div>
</template>
