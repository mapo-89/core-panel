<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue'
import ApiTokenManager from '@/pages/Admin/ApiTokens/components/ApiTokenManager.vue'
import type { ApiTokenManagerPayload } from '@/types/core-panel'

const props = withDefaults(
    defineProps<{
        apiTokenManager?: ApiTokenManagerPayload | null
        createRequestKey?: number
        onRequestCreateToken?: (() => void) | null
    }>(),
    {
        apiTokenManager: null,
        createRequestKey: 0,
        onRequestCreateToken: null,
    },
)
</script>

<template>
    <section class="cp-section">
        <div class="cp-section__header cp-section__header--split">
            <div class="grid min-w-0 flex-1 gap-1">
                <h2 class="text-lg font-semibold text-[var(--cp-text-primary)]">
                    {{ $t('page-api-tokens.title') }}
                </h2>
                <p class="text-sm text-[var(--cp-text-muted)]">
                    {{ $t('page-api-tokens.description') }}
                </p>
            </div>

            <Button
                v-if="props.apiTokenManager?.canCreate"
                class="gap-2"
                @click="props.onRequestCreateToken?.()"
            >
                <AppIcon name="plus" />
                <span>{{ $t('page-api-tokens.new') }}</span>
            </Button>
        </div>

        <ApiTokenManager
            v-if="props.apiTokenManager"
            :abilities="props.apiTokenManager.abilities"
            :can-create="props.apiTokenManager.canCreate"
            :can-delete="props.apiTokenManager.canDelete"
            :create-request-key="props.createRequestKey"
            embedded
            :tokens="props.apiTokenManager.tokens"
        />
    </section>
</template>
