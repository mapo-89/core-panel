<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'

import { trans } from 'laravel-vue-i18n'
import RolesOverviewPanel from '@/pages/Admin/Access/components/RolesOverviewPanel.vue'
import AppIcon from '@/components/AppIcon.vue'
import { useCan } from '@/composables/useCan'
import AppLayout from '@/layouts/AppLayout.vue'
import RoleCreateDialog from '@/pages/Admin/Roles/components/RoleCreateDialog.vue'
import roleRoutes from '@/routes/core-panel/roles'
import type { PermissionRecord, RoleRecord } from '@/types/core-panel'
import { useDialog } from 'primevue/usedialog'

type ManagedRoleRecord = {
    name: string
    group: string
    label: string
    permissions: string[]
    protected: boolean
}

defineProps<{
    defaultRoles: ManagedRoleRecord[]
    roles: RoleRecord[]
    permissions: PermissionRecord[]
    permissionDefaults: string[]
    permissionGroups: Record<string, string>
}>()

const { can, hasRole } = useCan()
const dialog = useDialog()
const canResyncManagedRoles = computed(
    () => hasRole('super-admin') && can('roles.update'),
)

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
            onSuccess: () => refresh(),
        },
    )
}
</script>

<template>
    <AppLayout
        :title="$t('navigation.roles')"
        :subtitle="$t('page-roles.description')"
    >
        <Head :title="$t('navigation.roles')" />

        <template #page-actions>
            <div class="flex flex-wrap gap-2">
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
                <Button
                    class="gap-2"
                    outlined
                    severity="secondary"
                    @click="router.visit(roleRoutes.matrix.url())"
                >
                    <AppIcon name="shield" />
                    <span>{{ $t('page-roles.matrix') }}</span>
                </Button>
            </div>
        </template>

        <RolesOverviewPanel
            :default-roles="defaultRoles"
            :permission-defaults="permissionDefaults"
            :permission-groups="permissionGroups"
            :permissions="permissions"
            :roles="roles"
            variant="full"
        />
    </AppLayout>
</template>
