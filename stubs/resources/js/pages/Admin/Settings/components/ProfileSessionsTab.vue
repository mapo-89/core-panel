<script setup lang="ts">
import { computed, ref } from 'vue'

import { router, useForm } from '@inertiajs/vue3'
import AppIcon from '@core-panel/components/AppIcon.vue'
import type { UserSessionRecord } from '@core-panel/types/core-panel'
import profile from '@/routes/profile'

const props = defineProps<{
    browserSessions: UserSessionRecord[]
}>()

const showPasswordDialog = ref(false)
const logoutSuccess = ref(false)
const passwordConfirmForm = useForm({
    password: '',
})

const otherSessionsCount = computed(
    () => props.browserSessions.filter((session) => !session.is_current).length,
)

const canLogoutOtherSessions = computed(() => otherSessionsCount.value > 0)

function formatLastActive(timestamp: number): string {
    return new Date(timestamp * 1000).toLocaleString()
}

function sessionDeviceIcon(userAgent: string | null): 'desktop' | 'smartphone' {
    const agent = userAgent?.toLowerCase() ?? ''

    return /(android|iphone|ipad|ipod|mobile)/.test(agent)
        ? 'smartphone'
        : 'desktop'
}

function sessionBrowserLabel(userAgent: string | null): string | null {
    const agent = userAgent ?? ''

    if (/edg\//i.test(agent)) {
        return 'Edge'
    }

    if (/chrome\//i.test(agent) && !/edg\//i.test(agent)) {
        return 'Chrome'
    }

    if (/firefox\//i.test(agent)) {
        return 'Firefox'
    }

    if (
        /safari\//i.test(agent) &&
        !/chrome\//i.test(agent) &&
        !/chromium\//i.test(agent)
    ) {
        return 'Safari'
    }

    return null
}

function sessionPlatformLabel(userAgent: string | null): string | null {
    const agent = userAgent ?? ''

    if (/windows/i.test(agent)) {
        return 'Windows'
    }

    if (/(iphone|ipad|ipod)/i.test(agent)) {
        return 'iOS'
    }

    if (/android/i.test(agent)) {
        return 'Android'
    }

    if (/mac os x|macintosh/i.test(agent)) {
        return 'macOS'
    }

    if (/linux/i.test(agent)) {
        return 'Linux'
    }

    return null
}

function sessionTitle(session: UserSessionRecord): string | null {
    const parts = [
        sessionPlatformLabel(session.user_agent),
        sessionBrowserLabel(session.user_agent),
    ].filter((value): value is string => Boolean(value && value.trim() !== ''))

    if (parts.length > 0) {
        return parts.join(' — ')
    }

    return session.user_agent
}

function openLogoutOtherSessionsDialog(): void {
    passwordConfirmForm.reset()
    passwordConfirmForm.clearErrors()
    showPasswordDialog.value = true
}

function logoutOtherSessions(): void {
    passwordConfirmForm.post(profile.sessions.destroyOthers.url(), {
        onSuccess: () => {
            showPasswordDialog.value = false
            passwordConfirmForm.reset()
            logoutSuccess.value = true
            window.setTimeout(() => {
                logoutSuccess.value = false
            }, 3000)
            router.reload({
                only: ['browserSessions', 'flash'],
            })
        },
    })
}
</script>

<template>
    <section class="cp-card grid gap-5 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="grid gap-1.5">
                <h2 class="text-lg font-semibold text-[var(--cp-text-primary)]">
                    {{ $t('page-settings.sessions_title') }}
                </h2>
                <p class="text-sm font-medium text-[var(--cp-text-muted)]">
                    {{ $t('page-settings.browser_sessions_subtitle') }}
                </p>
            </div>

            <Button
                v-if="canLogoutOtherSessions"
                :label="$t('page-settings.browser_sessions_logout_others')"
                outlined
                severity="secondary"
                size="small"
                type="button"
                @click="openLogoutOtherSessionsDialog"
            />
        </div>

        <p class="text-sm leading-6 text-[var(--cp-text-muted)]">
            {{ $t('page-settings.browser_sessions_description') }}
        </p>

        <Message
            v-if="browserSessions.length === 0"
            severity="secondary"
            size="small"
            variant="simple"
        >
            {{ $t('page-settings.browser_sessions_unavailable') }}
        </Message>

        <Message
            v-else-if="!canLogoutOtherSessions"
            severity="secondary"
            size="small"
            variant="simple"
        >
            {{ $t('page-settings.browser_sessions_only_current') }}
        </Message>

        <div v-if="browserSessions.length > 0" class="grid gap-3">
            <article
                v-for="session in browserSessions"
                :key="session.id"
                class="flex items-center gap-3 rounded-[var(--cp-radius-md)] border border-[var(--cp-surface-border)] px-4 py-3"
            >
                <div class="shrink-0 text-[var(--cp-text-muted)]">
                    <AppIcon
                        :name="sessionDeviceIcon(session.user_agent)"
                        :stroke-width="1.75"
                    />
                </div>

                <div class="min-w-0 flex-1 grid gap-1">
                    <div
                        class="truncate text-sm font-medium text-[var(--cp-text-primary)]"
                    >
                        {{
                            sessionTitle(session) ??
                            $t('page-settings.browser_sessions_unknown_device')
                        }}
                    </div>
                    <div class="text-xs text-[var(--cp-text-muted)]">
                        {{ session.ip_address ?? $t('common.ui.unknown_ip') }}
                        <span v-if="!session.is_current">
                            —
                            {{
                                $t(
                                    'page-settings.browser_sessions_last_active',
                                    {
                                        time: formatLastActive(
                                            session.last_active,
                                        ),
                                    },
                                )
                            }}
                        </span>
                    </div>
                </div>

                <div class="shrink-0">
                    <Tag
                        v-if="session.is_current"
                        :value="
                            $t('page-settings.browser_sessions_this_device')
                        "
                    />
                </div>
            </article>
        </div>

        <div
            v-if="logoutSuccess"
            class="text-sm font-medium text-emerald-600 dark:text-emerald-400"
        >
            {{ $t('page-settings.browser_sessions_done') }}
        </div>

        <Dialog
            v-model:visible="showPasswordDialog"
            :header="$t('page-settings.browser_sessions_logout_others')"
            modal
            :style="{ width: '26rem' }"
        >
            <p class="mb-4 text-sm text-[var(--cp-text-muted)]">
                {{ $t('page-settings.browser_sessions_logout_confirm') }}
            </p>

            <form class="grid gap-4" @submit.prevent="logoutOtherSessions">
                <div class="grid gap-1">
                    <label
                        class="text-sm font-medium text-[var(--cp-text-primary)]"
                        for="logout_other_sessions_password"
                    >
                        {{ $t('page-settings.confirm_password') }}
                    </label>
                    <Password
                        id="logout_other_sessions_password"
                        v-model="passwordConfirmForm.password"
                        autocomplete="current-password"
                        autofocus
                        :feedback="false"
                        :invalid="Boolean(passwordConfirmForm.errors.password)"
                        toggle-mask
                        fluid
                    />
                    <small
                        v-if="passwordConfirmForm.errors.password"
                        class="auth-form__field-error"
                    >
                        {{ passwordConfirmForm.errors.password }}
                    </small>
                </div>

                <div class="flex justify-end gap-2">
                    <Button
                        :label="$t('common.ui.cancel')"
                        outlined
                        severity="secondary"
                        type="button"
                        @click="showPasswordDialog = false"
                    />
                    <Button
                        :disabled="passwordConfirmForm.processing"
                        :loading="passwordConfirmForm.processing"
                        severity="danger"
                        type="submit"
                    >
                        <AppIcon name="logout" />
                        <span>{{
                            $t('page-settings.browser_sessions_logout_others')
                        }}</span>
                    </Button>
                </div>
            </form>
        </Dialog>
    </section>
</template>
