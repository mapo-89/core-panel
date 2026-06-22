<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, markRaw, ref } from 'vue'

import { trans } from 'laravel-vue-i18n'
import { useDialog } from 'primevue/usedialog'

import TabsRenderer from '@core-panel/components/TabBuilder/TabsRenderer.vue'
import AppIcon from '@/components/AppIcon.vue'
import RolesOverviewPanel from '@/pages/Admin/Access/components/RolesOverviewPanel.vue'
import { useCan } from '@/composables/useCan'
import UserFormDialog from '@/pages/Admin/Users/components/UserFormDialog.vue'
import RoleCreateDialog from '@/pages/Admin/Roles/components/RoleCreateDialog.vue'
import UserGroupsTab from '@/pages/Admin/Users/components/UserGroupsTab.vue'
import UsersTableTab from '@/pages/Admin/Users/components/UsersTableTab.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import UserGroupForm from '@/pages/Admin/UserGroups/components/UserGroupForm.vue'
import UserGroupImportForm from '@/pages/Admin/UserGroups/components/UserGroupImportForm.vue'
import roleRoutes from '@/routes/core-panel/roles'
import type {
    AssignableUser,
    DataTablePagination,
    DataTableState,
    PermissionRecord,
    RoleRecord,
    TabsSchema,
    UserGroupRecord,
    UserCapabilities,
    UserRecord,
} from '@/types/core-panel'

type ManagedRoleRecord = {
    name: string
    group: string
    label: string
    permissions: string[]
    protected: boolean
}

type UserManagementTab = 'users' | 'user_groups' | 'roles'

const props = defineProps<{
    assignableUsers: AssignableUser[]
    assignableRoles: RoleRecord[]
    canAssignRoles: boolean
    capabilities: UserCapabilities
    defaultRoles: ManagedRoleRecord[]
    activeTab?: UserManagementTab
    filters: {
        role?: string
        search: string
        status?: string
        userGroupId?: string
        withTrashed: boolean
    }
    permissionDefaults: string[]
    permissionGroups: Record<string, string>
    permissions: PermissionRecord[]
    roleLabels: Record<string, string>
    roles: RoleRecord[]
    userGroupOptions: Array<{
        color: string
        label: string
        value: string
    }>
    userGroups: UserGroupRecord[]
    users: UserRecord[]
    usersTable: {
        pagination: DataTablePagination
        state: DataTableState
    }
}>()

const activeTab = ref<UserManagementTab>(props.activeTab ?? 'users')
const dialog = useDialog()

const tabComponents = {
    RolesOverviewPanel: markRaw(RolesOverviewPanel),
    UserGroupsTab: markRaw(UserGroupsTab),
    UsersTableTab: markRaw(UsersTableTab),
}

const tabSchema = computed<TabsSchema>(() => ({
    panelSurface: true,
    panelSurfaceClass: 'cp-side-tabs__panel-surface--unpadded',
    panelSurfaceVariant: 'card',
    tabs: [
        {
            component: 'UsersTableTab',
            componentProps: {
                capabilities: props.capabilities,
                filters: props.filters,
                onEditUser: openEditDialog,
                roleLabels: props.roleLabels,
                users: props.users,
                usersTable: props.usersTable,
                userGroupOptions: props.userGroupOptions,
            },
            icon: 'users',
            key: 'users',
            label: 'navigation.users',
        },
        can('user-groups.view')
            ? {
                  component: 'UserGroupsTab',
                  componentProps: {
                      userGroups: props.userGroups,
                  },
                  icon: 'sitemap',
                  key: 'user_groups',
                  label: 'navigation.user_groups',
              }
            : null,
        can('roles.view')
            ? {
                  component: 'RolesOverviewPanel',
                  componentProps: {
                      defaultRoles: props.defaultRoles,
                      permissionDefaults: props.permissionDefaults,
                      permissionGroups: props.permissionGroups,
                      permissions: props.permissions,
                      roles: props.roles,
                      variant: 'tab',
                  },
                  icon: 'shield',
                  key: 'roles',
                  label: 'navigation.roles',
              }
            : null,
    ].filter((tab): tab is NonNullable<typeof tab> => tab !== null),
}))

const { can, hasRole } = useCan()

const canImportUserGroups = computed(() => can('user-groups.import'))
const canResyncManagedRoles = computed(
    () => hasRole('super-admin') && can('roles.update'),
)

function reloadUsers(): void {
    router.reload({
        only: [
            'filters',
            'userGroups',
            'userGroupOptions',
            'users',
            'usersTable',
        ],
    })
}

function openCreateDialog(): void {
    dialog.open(UserFormDialog, {
        data: {
            canAssignRoles: props.canAssignRoles,
            capabilities: props.capabilities,
            onSaved: reloadUsers,
            roleLabels: props.roleLabels,
            roles: props.assignableRoles,
            userGroupOptions: props.userGroupOptions,
        },
        props: {
            header: trans('page-users.create_title'),
            modal: true,
            style: {
                width: 'min(58rem, 92vw)',
            },
        },
    })
}

function openEditDialog(user: UserRecord): void {
    dialog.open(UserFormDialog, {
        data: {
            canAssignRoles: props.canAssignRoles,
            capabilities: props.capabilities,
            onSaved: reloadUsers,
            roleLabels: props.roleLabels,
            roles: props.assignableRoles,
            userGroupOptions: props.userGroupOptions,
            user,
        },
        props: {
            header: trans('page-users.edit_title'),
            modal: true,
            style: {
                width: 'min(58rem, 92vw)',
            },
        },
    })
}

function openCreateUserGroupDialog(): void {
    dialog.open(UserGroupForm, {
        data: {
            onSaved: reloadUsers,
        },
        props: {
            header: trans('page-user-groups.create'),
            modal: true,
            style: {
                width: 'min(32rem, 92vw)',
            },
        },
    })
}

function openImportUserGroupsDialog(): void {
    dialog.open(UserGroupImportForm, {
        data: {
            onSaved: reloadUsers,
        },
        props: {
            header: trans('page-user-groups.import_title'),
            modal: true,
            style: {
                width: 'min(40rem, 92vw)',
            },
        },
    })
}

function reloadRoles(): void {
    router.reload({
        only: [
            'assignableRoles',
            'canAssignRoles',
            'defaultRoles',
            'permissionDefaults',
            'permissionGroups',
            'permissions',
            'roleLabels',
            'roles',
        ],
    })
}

function openCreateRoleDialog(): void {
    dialog.open(RoleCreateDialog, {
        props: {
            header: trans('page-roles.roles_create'),
            modal: true,
            style: {
                width: 'min(28rem, 92vw)',
            },
        },
    })
}

function resyncManagedRoles(): void {
    router.post(
        roleRoutes.resync.url(),
        {},
        {
            onSuccess: () => reloadRoles(),
        },
    )
}
</script>

<template>
    <AppLayout
        :title="$t('page-users.management_title')"
        :subtitle="$t('page-users.index_description')"
    >
        <Head :title="$t('page-users.management_title')" />

        <template #page-actions>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="activeTab === 'users'"
                    class="gap-2"
                    @click="openCreateDialog"
                >
                    <AppIcon name="user-plus" />
                    <span>{{ $t('page-users.new') }}</span>
                </Button>
                <template v-else-if="activeTab === 'user_groups'">
                    <Button
                        v-if="canImportUserGroups"
                        class="gap-2"
                        outlined
                        severity="secondary"
                        @click="openImportUserGroupsDialog"
                    >
                        <AppIcon name="upload" />
                        <span>{{ $t('page-user-groups.import_action') }}</span>
                    </Button>
                    <Button class="gap-2" @click="openCreateUserGroupDialog">
                        <AppIcon name="plus" />
                        <span>{{ $t('page-user-groups.create') }}</span>
                    </Button>
                </template>
                <template v-else-if="activeTab === 'roles'">
                    <Button
                        v-if="canResyncManagedRoles"
                        class="gap-2"
                        outlined
                        severity="secondary"
                        @click="resyncManagedRoles"
                    >
                        <AppIcon name="refresh" />
                        <span>{{ $t('page-roles.resync') }}</span>
                    </Button>
                    <Button
                        v-if="can('roles.create')"
                        class="gap-2"
                        @click="openCreateRoleDialog"
                    >
                        <AppIcon name="plus" />
                        <span>{{ $t('page-roles.new_role') }}</span>
                    </Button>
                </template>
            </div>
        </template>

        <div class="cp-user-management">
            <TabsRenderer
                v-model="activeTab"
                class="cp-side-tabs"
                :components="tabComponents"
                layout="vertical"
                :schema="tabSchema"
                sync-with-url
            />
        </div>
    </AppLayout>
</template>
