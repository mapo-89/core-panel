<script setup lang="ts">
import { computed, markRaw, ref, watch } from 'vue'

import TabsRenderer from '@core-panel/components/TabBuilder/TabsRenderer.vue'

import type {
    SocialAccountRecord,
    SocialProviderRecord,
    TabsSchema,
    UserSessionRecord,
} from '@/types/core-panel'
import ProfileConnectionsTab from './ProfileConnectionsTab.vue'
import ProfileInfoTab from './ProfileInfoTab.vue'
import ProfilePasswordTab from './ProfilePasswordTab.vue'
import ProfileSecurityTab from './ProfileSecurityTab.vue'
import ProfileSessionsTab from './ProfileSessionsTab.vue'

type ProfileWorkspaceTab =
    | 'connections'
    | 'general'
    | 'password'
    | 'security'
    | 'sessions'

const props = defineProps<{
    browserSessions: UserSessionRecord[]
    initialTab?: ProfileWorkspaceTab
    requiresPasswordSetup: boolean
    socialAccounts: SocialAccountRecord[]
    socialProviders: SocialProviderRecord[]
    twoFactor: {
        confirmed: boolean
        enabled: boolean
    }
}>()

const activeTab = ref<ProfileWorkspaceTab>(props.initialTab ?? 'general')

watch(
    () => props.initialTab,
    (value) => {
        if (value !== undefined) {
            activeTab.value = value
        }
    },
)

const tabComponents = {
    ProfileConnectionsTab: markRaw(ProfileConnectionsTab),
    ProfileInfoTab: markRaw(ProfileInfoTab),
    ProfilePasswordTab: markRaw(ProfilePasswordTab),
    ProfileSecurityTab: markRaw(ProfileSecurityTab),
    ProfileSessionsTab: markRaw(ProfileSessionsTab),
}

const tabSchema = computed<TabsSchema>(() => ({
    tabs: [
        {
            component: 'ProfileInfoTab',
            icon: 'user',
            key: 'general',
            label: 'page-settings.tab_general',
        },
        {
            component: 'ProfileConnectionsTab',
            componentProps: {
                socialAccounts: props.socialAccounts,
                socialProviders: props.socialProviders,
            },
            icon: 'building',
            key: 'connections',
            label: 'page-settings.tab_connections',
        },
        {
            component: 'ProfilePasswordTab',
            componentProps: {
                requiresPasswordSetup: props.requiresPasswordSetup,
            },
            icon: 'lock',
            key: 'password',
            label: 'page-settings.tab_password',
        },
        {
            component: 'ProfileSecurityTab',
            componentProps: {
                twoFactor: props.twoFactor,
            },
            icon: 'shield',
            key: 'security',
            label: 'page-settings.tab_security',
        },
        {
            component: 'ProfileSessionsTab',
            componentProps: {
                browserSessions: props.browserSessions,
            },
            icon: 'desktop',
            key: 'sessions',
            label: 'page-settings.tab_sessions',
        },
    ],
}))
</script>

<template>
    <TabsRenderer
        v-model="activeTab"
        class="cp-side-tabs"
        :components="tabComponents"
        layout="vertical"
        :schema="tabSchema"
        sync-with-url
    />
</template>
