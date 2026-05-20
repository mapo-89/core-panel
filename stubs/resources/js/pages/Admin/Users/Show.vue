<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, markRaw, ref, watch } from 'vue'

import { trans } from 'laravel-vue-i18n'
import { useDialog } from 'primevue/usedialog'

import TabsRenderer from '@core-panel/components/TabBuilder/TabsRenderer.vue'

import AppLayout from '@/layouts/AppLayout.vue'
import UserFormDialog from '@/pages/Admin/Users/components/UserFormDialog.vue'
import users from '@/routes/core-panel/users'
import type {
    RoleRecord,
    SocialAccountRecord,
    SocialProviderRecord,
    TabsSchema,
    UserCapabilities,
    UserRecord,
} from '@/types/core-panel'
import UserConnectionsTab from './components/UserConnectionsTab.vue'
import UserOverviewTab from './components/UserOverviewTab.vue'
import UserSecurityTab from './components/UserSecurityTab.vue'
import UserSessionsTab from './components/UserSessionsTab.vue'

type UserShowTab = 'connections' | 'general' | 'security' | 'sessions'

const props = defineProps<{
    canAssignRoles: boolean
    canHardResetPassword: boolean
    capabilities: UserCapabilities
    roleLabels: Record<string, string>
    roles: RoleRecord[]
    sessionsEnabled: boolean
    socialAccounts: SocialAccountRecord[]
    socialProviders: SocialProviderRecord[]
    userGroupOptions: Array<{
        color: string
        label: string
        value: string
    }>
    user: UserRecord
}>()

const activeTab = ref<UserShowTab>('general')
const dialog = useDialog()

const tabComponents = {
    UserConnectionsTab: markRaw(UserConnectionsTab),
    UserOverviewTab: markRaw(UserOverviewTab),
    UserSecurityTab: markRaw(UserSecurityTab),
    UserSessionsTab: markRaw(UserSessionsTab),
}

const tabsSchema = computed<TabsSchema>(() => ({
    tabs: [
        {
            component: 'UserOverviewTab',
            componentProps: {
                capabilities: props.capabilities,
                roleLabels: props.roleLabels,
                user: props.user,
            },
            icon: 'user',
            key: 'general',
            label: 'page-settings.tab_general',
        },
        {
            component: 'UserConnectionsTab',
            componentProps: {
                socialAccounts: props.socialAccounts,
                socialProviders: props.socialProviders,
            },
            icon: 'building',
            key: 'connections',
            label: 'page-settings.tab_connections',
        },
        {
            component: 'UserSecurityTab',
            componentProps: {
                canHardResetPassword: props.canHardResetPassword,
                capabilities: props.capabilities,
                user: props.user,
            },
            icon: 'shield',
            key: 'security',
            label: 'page-settings.tab_security',
        },
        {
            component: 'UserSessionsTab',
            componentProps: {
                enabled: props.sessionsEnabled,
                userId: props.user.id,
            },
            icon: 'desktop',
            key: 'sessions',
            label: 'page-settings.tab_sessions',
        },
    ],
}))

watch(
    () => props.user.id,
    () => {
        activeTab.value = 'general'
    },
)

function reloadUser(): void {
    router.reload({
        only: ['user'],
    })
}

function openEditDialog(): void {
    dialog.open(UserFormDialog, {
        data: {
            canAssignRoles: props.canAssignRoles,
            capabilities: props.capabilities,
            onSaved: reloadUser,
            roleLabels: props.roleLabels,
            roles: props.roles,
            user: props.user,
            userGroupOptions: props.userGroupOptions,
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
</script>

<template>
    <AppLayout
        :title="user.name"
        :subtitle="$t('page-users.show_description')"
        :back-url="users.index.url()"
    >
        <Head :title="user.name" />

        <template #page-actions>
            <div class="flex flex-wrap gap-2">
                <Button :label="$t('common.ui.edit')" @click="openEditDialog" />
            </div>
        </template>

        <TabsRenderer
            v-model="activeTab"
            class="cp-side-tabs cp-user-profile"
            :components="tabComponents"
            layout="vertical"
            :schema="tabsSchema"
            sync-with-url
        />
    </AppLayout>
</template>
