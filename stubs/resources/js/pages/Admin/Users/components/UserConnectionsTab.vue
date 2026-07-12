<script setup lang="ts">
import Message from 'primevue/message'

import AppIcon from '@core-panel/components/AppIcon.vue'
import type {
    SocialAccountRecord,
    SocialProviderRecord,
} from '@core-panel/types/core-panel'

const props = defineProps<{
    socialAccounts: SocialAccountRecord[]
    socialProviders: SocialProviderRecord[]
}>()

function linkedAccount(
    provider: SocialProviderRecord,
): SocialAccountRecord | undefined {
    return props.socialAccounts.find(
        (account) => account.provider === provider.provider,
    )
}
</script>

<template>
    <section class="cp-card grid gap-5 p-6">
        <div class="grid gap-1">
            <h2 class="text-lg font-semibold text-[var(--cp-text-primary)]">
                {{ $t('page-settings.connected_accounts') }}
            </h2>
            <p class="text-sm text-[var(--cp-text-muted)]">
                {{ $t('page-settings.connected_accounts_description') }}
            </p>
        </div>

        <Message
            v-if="socialProviders.length === 0"
            severity="secondary"
            size="small"
            variant="simple"
        >
            {{ $t('page-settings.social_providers_empty') }}
        </Message>

        <div v-else class="cp-user-profile__list">
            <article
                v-for="provider in socialProviders"
                :key="provider.provider"
                class="cp-user-profile__list-item"
            >
                <div class="flex items-center gap-3">
                    <div class="cp-user-profile__provider-icon">
                        <AppIcon name="building" />
                    </div>

                    <div class="grid gap-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <strong
                                class="text-sm text-[var(--cp-text-primary)]"
                            >
                                {{ provider.label }}
                            </strong>
                            <Tag
                                v-if="provider.isMaster"
                                severity="contrast"
                                :value="
                                    $t(
                                        'page-settings.social_provider_master_badge',
                                    )
                                "
                            />
                        </div>
                        <span class="text-xs text-[var(--cp-text-muted)]">
                            {{
                                linkedAccount(provider)?.providerEmail ??
                                $t('page-settings.not_connected')
                            }}
                        </span>
                    </div>
                </div>

                <div class="grid justify-items-end gap-2 text-right">
                    <Tag
                        :severity="
                            linkedAccount(provider) ? 'success' : 'secondary'
                        "
                        :value="
                            linkedAccount(provider)
                                ? $t('page-settings.connected')
                                : $t('page-settings.not_connected')
                        "
                    />
                    <span
                        v-if="linkedAccount(provider)?.connectedAt"
                        class="text-xs text-[var(--cp-text-muted)]"
                    >
                        {{ linkedAccount(provider)?.connectedAt }}
                    </span>
                </div>
            </article>
        </div>
    </section>
</template>
