<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import { trans } from 'laravel-vue-i18n'
import { useDialog } from 'primevue/usedialog'

import AppIcon from '@core-panel/components/AppIcon.vue'
import ConfirmActionDialog from '@core-panel/components/Dialogs/ConfirmActionDialog.vue'
import { useDateTime } from '@core-panel/composables/useDateTime'
import UserGroupForm from '@core-panel/pages/Admin/UserGroups/components/UserGroupForm.vue'
import userGroupRoutes from '@/routes/core-panel/user-groups'
import TableBuilderDataTable from '@core-panel/components/TableBuilder/DataTable.vue'
import type { UserGroupRecord } from '@core-panel/types/core-panel'
import type { DataTableSchema } from '@core-panel/components/TableBuilder/types'

const props = defineProps<{
    userGroups: UserGroupRecord[]
}>()

const dialog = useDialog()
const deleteDialogVisible = ref(false)
const pendingDeleteUserGroup = ref<UserGroupRecord | null>(null)
const { formatDateTime } = useDateTime()

const tableSchema = computed<DataTableSchema>(() => ({
    actions: [],
    bulkActions: [],
    columns: [
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
        from: props.userGroups.length > 0 ? 1 : null,
        lastPage: Math.max(1, Math.ceil(props.userGroups.length / 10)),
        page: 1,
        perPage: 10,
        to:
            props.userGroups.length > 0
                ? Math.min(props.userGroups.length, 10)
                : null,
        total: props.userGroups.length,
    },
    rows: props.userGroups.map((userGroup) => ({
        color: userGroup.color,
        createdAt: userGroup.createdAt,
        id: userGroup.id,
        name: userGroup.name,
        usersCount: userGroup.usersCount,
    })),
    state: {
        filters: {},
        search: '',
        sort: 'name',
        visibleColumns: ['name', 'usersCount', 'createdAt'],
    },
}))

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
    return formatDateTime(value)
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

        <TableBuilderDataTable
            action-column-width="8.25rem"
            :schema="tableSchema"
            surface-class="cp-user-groups-tab__surface"
        >
            <template #cell-name="{ row }">
                <div class="flex items-center gap-[0.7rem]">
                    <span
                        class="cp-user-groups-tab__color"
                        :style="{ backgroundColor: String(row.color ?? '') }"
                    />
                    <span>{{ row.name }}</span>
                </div>
            </template>

            <template #cell-createdAt="{ row }">
                {{ formatCreatedAt(String(row.createdAt ?? '')) }}
            </template>

            <template #row-actions="{ row }">
                <div class="flex items-center justify-end gap-1.5">
                    <Button
                        :aria-label="$t('common.ui.edit')"
                        class="cp-datatable__action-button"
                        outlined
                        severity="secondary"
                        size="small"
                        @click="openEditDialog(row as UserGroupRecord)"
                    >
                        <AppIcon name="pencil" />
                    </Button>
                    <Button
                        :aria-label="$t('common.ui.delete')"
                        class="cp-datatable__action-button cp-datatable__action-button--danger"
                        severity="danger"
                        size="small"
                        @click="destroyUserGroup(row as UserGroupRecord)"
                    >
                        <AppIcon name="trash" />
                    </Button>
                </div>
            </template>
        </TableBuilderDataTable>
    </section>
</template>
