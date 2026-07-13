<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import ProfileWorkspace from '@core-panel/pages/Admin/Settings/components/ProfileWorkspace.vue'
import AppLayout from '@core-panel/layouts/AppLayout.vue'
import type {
    SocialAccountRecord,
    SocialProviderRecord,
    UserSessionRecord,
} from '@core-panel/types/core-panel'

defineProps<{
    browserSessions: UserSessionRecord[]
    requiresPasswordSetup: boolean
    socialAccounts: SocialAccountRecord[]
    socialProviders: SocialProviderRecord[]
    twoFactor: {
        confirmed: boolean
        enabled: boolean
    }
}>()
</script>

<template>
    <AppLayout
        :title="$t('settings.profile')"
        :subtitle="$t('page-settings.profile_workspace_description')"
    >
        <Head :title="$t('settings.profile')" />

        <ProfileWorkspace
            :browser-sessions="browserSessions"
            initial-tab="general"
            :requires-password-setup="requiresPasswordSetup"
            :social-accounts="socialAccounts"
            :social-providers="socialProviders"
            :two-factor="twoFactor"
        />
    </AppLayout>
</template>
