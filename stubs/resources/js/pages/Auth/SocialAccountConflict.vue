<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import SocialAccountConflictDialog from '@/components/Auth/SocialAccountConflictDialog.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'

defineProps<{
    context: 'connections' | 'login'
    currentEmail?: string | null
    currentAvatarUrl?: string | null
    decisionType:
        | 'change_email'
        | 'confirm_link'
        | 'switch_user'
        | 'takeover_connection'
    existingUser?: {
        email: string
        fullName: string
    } | null
    provider: string
    providerAvatarUrl?: string | null
    providerEmail: string
    providerLabel: string
}>()
</script>

<template>
    <AuthLayout
        :heading="
            $t('page-settings.social_master_conflict_title', {
                provider: providerLabel,
            })
        "
        :subheading="
            $t('page-settings.social_master_conflict_subtitle', {
                provider: providerLabel,
            })
        "
    >
        <Head
            :title="
                $t('page-settings.social_master_conflict_title', {
                    provider: providerLabel,
                })
            "
        />

        <SocialAccountConflictDialog
            :context="context"
            :current-avatar-url="currentAvatarUrl"
            :current-email="currentEmail"
            :decision-type="decisionType"
            :existing-user="existingUser"
            :provider="provider"
            :provider-avatar-url="providerAvatarUrl"
            :provider-email="providerEmail"
            :provider-label="providerLabel"
        />
    </AuthLayout>
</template>
