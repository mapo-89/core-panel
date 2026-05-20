<script setup lang="ts">
import Message from 'primevue/message'
import { computed } from 'vue'

import type {
    SocialAccountRecord,
    SocialProviderRecord,
} from '@/types/core-panel'
import SocialProviderConnectionCard from './SocialProviderConnectionCard.vue'

const props = defineProps<{
    socialAccounts: SocialAccountRecord[]
    socialProviders: SocialProviderRecord[]
}>()

const visibleSocialProviders = computed(() =>
    ['microsoft', 'github', 'google']
        .map((providerName) =>
            props.socialProviders.find(
                (provider) => provider.provider === providerName,
            ),
        )
        .filter(
            (provider): provider is SocialProviderRecord =>
                provider !== undefined,
        ),
)
</script>

<template>
    <section class="cp-profile-panel">
        <Message v-if="visibleSocialProviders.length === 0" severity="info">
            {{ $t('page-settings.social_providers_empty') }}
        </Message>

        <template v-else>
            <SocialProviderConnectionCard
                v-for="provider in visibleSocialProviders"
                :key="provider.provider"
                :provider="provider.provider"
                :social-accounts="props.socialAccounts"
                :social-providers="props.socialProviders"
            />
        </template>
    </section>
</template>
