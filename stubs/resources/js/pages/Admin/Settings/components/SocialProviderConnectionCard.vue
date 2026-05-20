<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { computed, ref } from 'vue'

import githubIcon from '@/assets/icons/github.svg'
import githubWhiteIcon from '@/assets/icons/github-white.svg'
import googleIcon from '@/assets/icons/google.png'
import microsoftIcon from '@/assets/icons/microsoft.svg'
import AppIcon from '@/components/AppIcon.vue'
import socialite from '@/routes/socialite'
import type {
    SocialAccountRecord,
    SocialProviderRecord,
} from '@/types/core-panel'

const props = defineProps<{
    provider: string
    socialAccounts: SocialAccountRecord[]
    socialProviders: SocialProviderRecord[]
}>()

const providerRecord = computed(() =>
    props.socialProviders.find(
        (provider) => provider.provider === props.provider,
    ),
)

const providerAccount = computed(() =>
    props.socialAccounts.find((account) => account.provider === props.provider),
)

const showCard = computed(
    () =>
        providerRecord.value !== undefined ||
        providerAccount.value !== undefined,
)

const isConnected = computed(() => providerAccount.value !== undefined)
const providerLabel = computed(
    () =>
        providerRecord.value?.label ??
        providerAccount.value?.label ??
        props.provider,
)
const providerLastSyncedAt = computed(
    () => providerAccount.value?.connectedAt ?? '—',
)
const disconnectProcessing = ref(false)
const testMailProcessing = ref(false)
const showAccountEmail = computed(() => isConnected.value)
const showMailStatus = computed(() => props.provider === 'microsoft')
const showCalendarStatus = computed(() => props.provider === 'microsoft')
const canSendTestMail = computed(
    () => props.provider === 'microsoft' && isConnected.value,
)
const isGithubProvider = computed(() => props.provider === 'github')
const showProviderLabel = computed(
    () =>
        providerLogo.value === null ||
        props.provider === 'google' ||
        props.provider === 'microsoft',
)
const providerIconName = computed(() => {
    return (
        {
            github: 'code',
            google: 'globe',
        }[props.provider] ?? 'logo'
    )
})
const providerLogo = computed(() => {
    return (
        {
            github: githubIcon,
            google: googleIcon,
            microsoft: microsoftIcon,
        }[props.provider] ?? null
    )
})

const providerHealth = computed(() => ({
    available: isConnected.value,
    message: isConnected.value
        ? providerText('connection_status_connected_message')
        : providerText('connection_status_disconnected_message'),
}))

const providerMailStatus = computed(() => ({
    available: isConnected.value,
    message: isConnected.value
        ? trans('page-settings.microsoft_mail_permission_connected_message')
        : trans('page-settings.microsoft_mail_permission_disconnected_message'),
}))

const providerCalendarStatus = computed(() => ({
    available: isConnected.value,
    message: isConnected.value
        ? trans('page-settings.microsoft_calendar_permission_connected_message')
        : trans(
              'page-settings.microsoft_calendar_permission_disconnected_message',
          ),
}))

function startConnect(): void {
    submitLinkAction(socialite.link.url(props.provider))
}

function disconnect(): void {
    disconnectProcessing.value = true

    router.delete(socialite.unlink.url(props.provider), {
        onFinish: () => {
            disconnectProcessing.value = false
        },
        preserveScroll: true,
    })
}

function sendTestMail(): void {
    if (!canSendTestMail.value) {
        return
    }

    testMailProcessing.value = true

    router.post(
        socialite.testMail.url(props.provider),
        {},
        {
            onFinish: () => {
                testMailProcessing.value = false
            },
            preserveScroll: true,
        },
    )
}

function submitLinkAction(url: string): void {
    const form = document.createElement('form')
    form.method = 'POST'
    form.action = url

    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content')

    if (csrfToken) {
        const tokenInput = document.createElement('input')
        tokenInput.type = 'hidden'
        tokenInput.name = '_token'
        tokenInput.value = csrfToken
        form.append(tokenInput)
    }

    document.body.append(form)
    form.submit()
}

function providerText(
    key:
        | 'account_label'
        | 'connect'
        | 'connect_hint'
        | 'connected'
        | 'connected_hint'
        | 'connection_status_connected_message'
        | 'connection_status_disconnected_message'
        | 'connection_status_label'
        | 'disconnect'
        | 'disconnected_hint'
        | 'last_sync_label'
        | 'reconnect'
        | 'subtitle'
        | 'title',
): string {
    if (props.provider === 'microsoft') {
        return {
            account_label: trans('page-settings.microsoft_account_label'),
            connect: trans('page-settings.microsoft_connect'),
            connect_hint: trans('page-settings.microsoft_connect_hint'),
            connected: trans('page-settings.microsoft_connected'),
            connected_hint: trans('page-settings.microsoft_connected_hint'),
            connection_status_connected_message: trans(
                'page-settings.microsoft_connection_status_connected_message',
            ),
            connection_status_disconnected_message: trans(
                'page-settings.microsoft_connection_status_disconnected_message',
            ),
            connection_status_label: trans(
                'page-settings.microsoft_connection_status_label',
            ),
            disconnect: trans('page-settings.microsoft_disconnect'),
            disconnected_hint: trans(
                'page-settings.microsoft_disconnected_hint',
            ),
            last_sync_label: trans('page-settings.microsoft_last_sync_label'),
            reconnect: trans('page-settings.microsoft_reconnect'),
            subtitle: trans('page-settings.microsoft_subtitle'),
            title: trans('page-settings.microsoft_title'),
        }[key]
    }

    return {
        account_label: trans('page-settings.social_provider_account_label', {
            provider: providerLabel.value,
        }),
        connect: trans('page-settings.social_provider_connect', {
            provider: providerLabel.value,
        }),
        connect_hint: trans('page-settings.social_provider_connect_hint', {
            provider: providerLabel.value,
        }),
        connected: trans('page-settings.social_provider_connected', {
            provider: providerLabel.value,
        }),
        connected_hint: trans('page-settings.social_provider_connected_hint', {
            provider: providerLabel.value,
        }),
        connection_status_connected_message: trans(
            'page-settings.social_provider_connection_status_connected_message',
            {
                provider: providerLabel.value,
            },
        ),
        connection_status_disconnected_message: trans(
            'page-settings.social_provider_connection_status_disconnected_message',
            {
                provider: providerLabel.value,
            },
        ),
        connection_status_label: trans(
            'page-settings.social_provider_connection_status_label',
        ),
        disconnect: trans('page-settings.social_provider_disconnect', {
            provider: providerLabel.value,
        }),
        disconnected_hint: trans('page-settings.not_connected'),
        last_sync_label: trans('page-settings.social_provider_last_sync_label'),
        reconnect: trans('page-settings.social_provider_connect', {
            provider: providerLabel.value,
        }),
        subtitle: trans('page-settings.social_provider_subtitle', {
            provider: providerLabel.value,
        }),
        title: trans('page-settings.social_provider_title', {
            provider: providerLabel.value,
        }),
    }[key]
}
</script>

<template>
    <section v-if="showCard" class="cp-card grid gap-5 p-6">
        <div class="grid gap-1">
            <h2 class="text-lg font-semibold text-[var(--cp-text-primary)]">
                {{ providerText('title') }}
            </h2>
            <p class="text-sm text-[var(--cp-text-muted)]">
                {{ providerText('subtitle') }}
            </p>
        </div>

        <div
            class="space-y-4 rounded-2xl border border-surface-200/80 bg-surface-50/70 p-4 dark:border-surface-800 dark:bg-surface-950/80"
        >
            <div
                class="rounded-2xl bg-white px-5 py-4 dark:border dark:border-surface-800 dark:bg-surface-900/90"
            >
                <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                    <template v-if="providerLogo">
                        <img
                            v-if="isGithubProvider"
                            :src="githubIcon"
                            :alt="providerLabel"
                            class="h-8 w-auto shrink-0 dark:hidden sm:h-9"
                        />
                        <img
                            v-if="isGithubProvider"
                            :src="githubWhiteIcon"
                            :alt="providerLabel"
                            class="hidden h-8 w-auto shrink-0 dark:block sm:h-9"
                        />
                        <img
                            v-if="!isGithubProvider"
                            :src="providerLogo"
                            :alt="providerLabel"
                            class="shrink-0"
                            :class="
                                props.provider === 'microsoft'
                                    ? 'h-9 w-9 sm:h-10 sm:w-10'
                                    : 'h-8 w-8 sm:h-9 sm:w-9'
                            "
                        />
                    </template>
                    <div v-else class="flex shrink-0 items-center">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-full bg-surface-100 text-surface-700 sm:h-10 sm:w-10 dark:bg-surface-800 dark:text-surface-100"
                        >
                            <AppIcon :name="providerIconName" />
                        </div>
                    </div>

                    <div
                        class="flex min-w-0 flex-1 items-center justify-between gap-3"
                    >
                        <div class="flex min-w-0 flex-wrap items-center gap-3">
                            <span
                                v-if="showProviderLabel"
                                class="text-2xl leading-none font-semibold tracking-tight text-[#737373] dark:text-white sm:text-[2.35rem]"
                            >
                                {{ providerLabel }}
                            </span>
                            <Tag
                                v-if="providerRecord?.isMaster"
                                severity="contrast"
                                :value="
                                    $t(
                                        'page-settings.social_provider_master_badge',
                                    )
                                "
                                class="ml-1 text-[0.65rem]"
                            />
                        </div>
                        <span
                            class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium ring-1"
                            :class="
                                isConnected
                                    ? 'bg-green-500/10 text-green-700 ring-green-500/20 dark:bg-green-500/15 dark:text-green-300'
                                    : 'bg-surface-900/5 text-surface-600 ring-surface-300 dark:bg-surface-800/70 dark:text-surface-200 dark:ring-surface-700'
                            "
                        >
                            <AppIcon :name="isConnected ? 'check' : 'ban'" />
                            {{
                                providerText(
                                    isConnected
                                        ? 'connected'
                                        : 'disconnected_hint',
                                )
                            }}
                        </span>
                    </div>
                </div>
            </div>

            <div v-if="isConnected" class="grid gap-3 md:grid-cols-2">
                <div
                    v-if="showAccountEmail && providerAccount?.providerEmail"
                    class="rounded-xl border border-surface-200/80 bg-white/80 p-3 dark:border-surface-800 dark:bg-surface-950/95"
                >
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.18em] text-surface-500 dark:text-surface-400"
                    >
                        {{ providerText('account_label') }}
                    </p>
                    <p
                        class="mt-1 break-all text-sm font-medium text-surface-900 dark:text-surface-0"
                    >
                        {{ providerAccount.providerEmail }}
                    </p>
                </div>

                <div
                    class="rounded-xl border border-surface-200/80 bg-white/80 p-3 dark:border-surface-800 dark:bg-surface-950/95"
                >
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.18em] text-surface-500 dark:text-surface-400"
                    >
                        {{ providerText('last_sync_label') }}
                    </p>
                    <p
                        class="mt-1 text-sm font-medium text-surface-900 dark:text-surface-0"
                    >
                        {{ providerLastSyncedAt }}
                    </p>
                </div>
            </div>

            <div
                v-if="isConnected"
                class="grid gap-3"
                :class="
                    showMailStatus || showCalendarStatus
                        ? 'lg:grid-cols-3'
                        : 'lg:grid-cols-1'
                "
            >
                <article
                    class="rounded-xl border border-surface-200/80 bg-white/80 p-4 dark:border-surface-800 dark:bg-surface-950/95"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p
                                class="text-sm font-semibold text-surface-900 dark:text-surface-0"
                            >
                                {{ providerText('connection_status_label') }}
                            </p>
                            <p
                                class="mt-2 text-sm text-surface-600 dark:text-surface-200"
                            >
                                {{ providerHealth.message }}
                            </p>
                        </div>
                        <AppIcon
                            :name="
                                providerHealth.available
                                    ? 'circle-check-big'
                                    : 'triangle-alert'
                            "
                            :class="
                                providerHealth.available
                                    ? 'text-green-600 dark:text-green-300'
                                    : 'text-amber-500 dark:text-amber-300'
                            "
                        />
                    </div>
                </article>

                <article
                    v-if="showMailStatus"
                    class="rounded-xl border border-surface-200/80 bg-white/80 p-4 dark:border-surface-800 dark:bg-surface-950/95"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p
                                class="text-sm font-semibold text-surface-900 dark:text-surface-0"
                            >
                                {{
                                    $t(
                                        'page-settings.microsoft_mail_permission_label',
                                    )
                                }}
                            </p>
                            <p
                                class="mt-2 text-sm text-surface-600 dark:text-surface-200"
                            >
                                {{ providerMailStatus.message }}
                            </p>
                        </div>
                        <AppIcon
                            :name="
                                providerMailStatus.available
                                    ? 'circle-check-big'
                                    : 'triangle-alert'
                            "
                            :class="
                                providerMailStatus.available
                                    ? 'text-green-600 dark:text-green-300'
                                    : 'text-rose-500 dark:text-rose-300'
                            "
                        />
                    </div>
                </article>

                <article
                    v-if="showCalendarStatus"
                    class="rounded-xl border border-surface-200/80 bg-white/80 p-4 dark:border-surface-800 dark:bg-surface-950/95"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p
                                class="text-sm font-semibold text-surface-900 dark:text-surface-0"
                            >
                                {{
                                    $t(
                                        'page-settings.microsoft_calendar_permission_label',
                                    )
                                }}
                            </p>
                            <p
                                class="mt-2 text-sm text-surface-600 dark:text-surface-200"
                            >
                                {{ providerCalendarStatus.message }}
                            </p>
                        </div>
                        <AppIcon
                            :name="
                                providerCalendarStatus.available
                                    ? 'circle-check-big'
                                    : 'x'
                            "
                            :class="
                                providerCalendarStatus.available
                                    ? 'text-green-600 dark:text-green-300'
                                    : 'text-rose-500 dark:text-rose-300'
                            "
                        />
                    </div>
                </article>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <Button
                    v-if="!isConnected && providerRecord"
                    class="gap-2"
                    @click="startConnect"
                >
                    <img
                        v-if="props.provider === 'microsoft'"
                        :src="microsoftIcon"
                        alt=""
                        class="h-4 w-4 shrink-0"
                    />
                    <img
                        v-else-if="providerLogo"
                        :src="providerLogo"
                        alt=""
                        class="h-4 w-auto shrink-0"
                    />
                    <AppIcon v-else :name="providerIconName" />
                    <span>{{ providerText('connect') }}</span>
                </Button>
                <template v-else-if="isConnected">
                    <Button
                        v-if="canSendTestMail"
                        class="gap-2"
                        severity="contrast"
                        :loading="testMailProcessing"
                        @click="sendTestMail"
                    >
                        <AppIcon name="email" />
                        <span>{{
                            $t('page-settings.microsoft_send_test_mail')
                        }}</span>
                    </Button>
                    <Button
                        class="gap-2"
                        severity="secondary"
                        @click="startConnect"
                    >
                        <AppIcon name="refresh" />
                        <span>{{ providerText('reconnect') }}</span>
                    </Button>
                    <Button
                        class="gap-2"
                        severity="danger"
                        variant="outlined"
                        :loading="disconnectProcessing"
                        @click="disconnect"
                    >
                        <AppIcon name="x" />
                        <span>{{ providerText('disconnect') }}</span>
                    </Button>
                </template>
            </div>
        </div>
    </section>
</template>
