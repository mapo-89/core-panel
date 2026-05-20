<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { computed, ref } from 'vue'

import socialite from '@/routes/socialite'

type ConflictDecision =
    | 'cancel'
    | 'change_email'
    | 'confirm_link'
    | 'switch_user'
    | 'takeover_connection'

const props = defineProps<{
    context: 'connections' | 'login'
    currentAvatarUrl?: string | null
    currentEmail?: string | null
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

const processing = ref(false)
const avatarDecision = ref<'keep' | 'replace'>('keep')
const actions = computed(() => socialite)
const hasAvatarChoice = computed(
    () => !!props.currentAvatarUrl && !!props.providerAvatarUrl,
)
const hasEmailChoice = computed(
    () =>
        props.decisionType === 'change_email' ||
        props.decisionType === 'switch_user' ||
        props.decisionType === 'takeover_connection',
)

const title = computed(() =>
    trans('page-settings.social_master_conflict_title', {
        provider: props.providerLabel,
    }),
)

const subtitle = computed(() =>
    hasAvatarChoice.value && !hasEmailChoice.value
        ? trans('page-settings.social_master_conflict_avatar_only_subtitle', {
              provider: props.providerLabel,
          })
        : trans('page-settings.social_master_conflict_subtitle', {
              provider: props.providerLabel,
          }),
)

const bodyTitle = computed(() => {
    if (hasAvatarChoice.value && !hasEmailChoice.value) {
        return trans('page-settings.social_master_conflict_avatar_only_title', {
            provider: props.providerLabel,
        })
    }

    switch (props.decisionType) {
        case 'confirm_link':
            return trans(
                'page-settings.social_master_conflict_confirm_link_title',
                {
                    provider: props.providerLabel,
                },
            )
        case 'switch_user':
            return trans(
                'page-settings.social_master_conflict_existing_user_title',
                {
                    provider: props.providerLabel,
                },
            )
        case 'takeover_connection':
            return trans(
                'page-settings.social_master_conflict_takeover_title',
                {
                    provider: props.providerLabel,
                },
            )
        default:
            return trans(
                'page-settings.social_master_conflict_change_email_title',
                {
                    provider: props.providerLabel,
                },
            )
    }
})

const bodyText = computed(() => {
    if (hasAvatarChoice.value && !hasEmailChoice.value) {
        return trans('page-settings.social_master_conflict_avatar_only_text', {
            provider: props.providerLabel,
        })
    }

    switch (props.decisionType) {
        case 'confirm_link':
            return trans(
                'page-settings.social_master_conflict_confirm_link_text',
                {
                    email: props.providerEmail,
                    provider: props.providerLabel,
                },
            )
        case 'switch_user':
            return trans(
                'page-settings.social_master_conflict_existing_user_text',
                {
                    email: props.providerEmail,
                    name: props.existingUser?.fullName ?? props.providerEmail,
                    provider: props.providerLabel,
                },
            )
        case 'takeover_connection':
            return trans('page-settings.social_master_conflict_takeover_text', {
                email: props.providerEmail,
                name: props.existingUser?.fullName ?? props.providerEmail,
                provider: props.providerLabel,
            })
        default:
            return trans(
                'page-settings.social_master_conflict_change_email_text',
                {
                    current: props.currentEmail ?? '',
                    provider: props.providerLabel,
                    providerEmail: props.providerEmail,
                },
            )
    }
})

const actionLabel = computed(() => {
    if (hasAvatarChoice.value && !hasEmailChoice.value) {
        return trans(
            'page-settings.social_master_conflict_avatar_only_action',
            {
                provider: props.providerLabel,
            },
        )
    }

    switch (props.decisionType) {
        case 'confirm_link':
            return trans(
                'page-settings.social_master_conflict_confirm_link_action',
                {
                    provider: props.providerLabel,
                },
            )
        case 'switch_user':
            return trans(
                'page-settings.social_master_conflict_switch_user_action',
                {
                    provider: props.providerLabel,
                },
            )
        case 'takeover_connection':
            return trans(
                'page-settings.social_master_conflict_takeover_action',
                {
                    provider: props.providerLabel,
                },
            )
        default:
            return trans(
                'page-settings.social_master_conflict_change_email_action',
                {
                    provider: props.providerLabel,
                },
            )
    }
})

const panelToneClass = computed(() => {
    switch (props.decisionType) {
        case 'takeover_connection':
            return 'border-rose-300/40 bg-rose-500/8'
        case 'switch_user':
            return 'border-amber-300/40 bg-amber-500/8'
        case 'confirm_link':
            return 'border-emerald-300/40 bg-emerald-500/8'
        default:
            return 'border-sky-300/40 bg-sky-500/8'
    }
})

function submitDecision(decision: ConflictDecision): void {
    processing.value = true

    router.post(
        actions.value.resolveConflict.url(props.provider),
        {
            avatar_decision: hasAvatarChoice.value
                ? avatarDecision.value
                : null,
            decision,
        },
        {
            onFinish: () => {
                processing.value = false
            },
            preserveScroll: true,
        },
    )
}
</script>

<template>
    <Dialog
        :closable="false"
        :draggable="false"
        modal
        :style="{ width: 'min(720px, calc(100vw - 2rem))' }"
        :visible="true"
    >
        <template #header>
            <div class="grid gap-1">
                <h2 class="text-lg font-semibold text-[var(--cp-text-primary)]">
                    {{ title }}
                </h2>
                <p class="text-sm text-[var(--cp-text-muted)]">
                    {{ subtitle }}
                </p>
            </div>
        </template>

        <div class="grid gap-6">
            <div class="grid gap-3" :class="{ 'md:grid-cols-2': currentEmail }">
                <div
                    v-if="currentEmail"
                    class="rounded-[var(--cp-radius-md)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-4"
                >
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cp-text-muted)]"
                    >
                        {{
                            $t(
                                'page-settings.social_master_conflict_current_email',
                            )
                        }}
                    </p>
                    <p
                        class="mt-2 break-all text-sm font-medium text-[var(--cp-text-primary)]"
                    >
                        {{ currentEmail }}
                    </p>
                </div>

                <div
                    class="rounded-[var(--cp-radius-md)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-4"
                >
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cp-text-muted)]"
                    >
                        {{
                            $t(
                                'page-settings.social_master_conflict_provider_email',
                                {
                                    provider: providerLabel,
                                },
                            )
                        }}
                    </p>
                    <p
                        class="mt-2 break-all text-sm font-medium text-[var(--cp-text-primary)]"
                    >
                        {{ providerEmail }}
                    </p>
                </div>
            </div>

            <div
                class="rounded-[var(--cp-radius-md)] border p-5 text-sm text-[var(--cp-text-primary)]"
                :class="panelToneClass"
            >
                <p class="font-semibold">
                    {{ bodyTitle }}
                </p>
                <p class="mt-2 text-[var(--cp-text-muted)]">
                    {{ bodyText }}
                </p>
            </div>

            <div
                v-if="hasAvatarChoice"
                class="grid gap-4 rounded-[var(--cp-radius-md)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-4 md:grid-cols-2"
            >
                <section class="grid gap-3">
                    <div class="grid gap-1">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cp-text-muted)]"
                        >
                            {{
                                $t(
                                    'page-settings.social_avatar_sync_current_label',
                                )
                            }}
                        </p>
                        <p class="text-sm text-[var(--cp-text-muted)]">
                            {{
                                $t('page-settings.social_avatar_sync_keep_hint')
                            }}
                        </p>
                    </div>
                    <div
                        class="flex min-h-44 items-center justify-center rounded-[var(--cp-radius-lg)] border border-dashed border-[var(--cp-surface-border)] bg-[var(--cp-surface-muted)]/60 p-4"
                    >
                        <div
                            class="flex h-32 w-32 items-center justify-center overflow-hidden rounded-full border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)]"
                        >
                            <img
                                v-if="currentAvatarUrl"
                                :src="currentAvatarUrl"
                                :alt="
                                    $t(
                                        'page-settings.social_avatar_sync_current_label',
                                    )
                                "
                                class="h-full w-full object-cover"
                            />
                        </div>
                    </div>
                </section>

                <section class="grid gap-3">
                    <div class="grid gap-1">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cp-text-muted)]"
                        >
                            {{
                                $t(
                                    'page-settings.social_avatar_sync_provider_label',
                                    { provider: providerLabel },
                                )
                            }}
                        </p>
                        <p class="text-sm text-[var(--cp-text-muted)]">
                            {{
                                $t(
                                    'page-settings.social_avatar_sync_replace_hint',
                                    { provider: providerLabel },
                                )
                            }}
                        </p>
                    </div>
                    <div
                        class="flex min-h-44 items-center justify-center rounded-[var(--cp-radius-lg)] border border-dashed border-[var(--cp-surface-border)] bg-[var(--cp-surface-muted)]/60 p-4"
                    >
                        <div
                            class="flex h-32 w-32 items-center justify-center overflow-hidden rounded-full border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)]"
                        >
                            <img
                                v-if="providerAvatarUrl"
                                :src="providerAvatarUrl"
                                :alt="providerLabel"
                                class="h-full w-full object-cover"
                            />
                        </div>
                    </div>
                </section>

                <div class="md:col-span-2">
                    <SelectButton
                        v-model="avatarDecision"
                        :allow-empty="false"
                        class="w-full"
                        :options="[
                            {
                                label: $t(
                                    'page-settings.social_avatar_sync_keep',
                                ),
                                value: 'keep',
                            },
                            {
                                label: $t(
                                    'page-settings.social_avatar_sync_replace',
                                    { provider: providerLabel },
                                ),
                                value: 'replace',
                            },
                        ]"
                        option-label="label"
                        option-value="value"
                    />
                </div>
            </div>

            <div
                v-if="existingUser"
                class="rounded-[var(--cp-radius-md)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-4"
            >
                <p
                    class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cp-text-muted)]"
                >
                    {{ $t('page-settings.social_master_conflict_target_user') }}
                </p>
                <p
                    class="mt-2 text-sm font-medium text-[var(--cp-text-primary)]"
                >
                    {{ existingUser.fullName }}
                </p>
                <p class="text-sm text-[var(--cp-text-muted)]">
                    {{ existingUser.email }}
                </p>
            </div>

            <div class="flex flex-wrap justify-end gap-2">
                <Button
                    :disabled="processing"
                    outlined
                    severity="secondary"
                    @click="submitDecision('cancel')"
                >
                    {{ $t('common.ui.cancel') }}
                </Button>
                <Button
                    :loading="processing"
                    :severity="
                        decisionType === 'takeover_connection'
                            ? 'danger'
                            : undefined
                    "
                    @click="submitDecision(decisionType)"
                >
                    {{ actionLabel }}
                </Button>
            </div>
        </div>
    </Dialog>
</template>
