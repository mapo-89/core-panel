<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import Message from 'primevue/message'

import AppIcon from '@/components/AppIcon.vue'
import userSessionRoutes from '@/routes/core-panel/users/sessions'
import type { UserSessionRecord } from '@/types/core-panel'

const props = defineProps<{
    enabled: boolean
    userId: string
}>()

const loading = ref(false)
const loaded = ref(false)
const sessions = ref<UserSessionRecord[]>([])

const currentSession = computed(
    () => sessions.value.find((session) => session.is_current) ?? null,
)

const otherSessions = computed(() =>
    sessions.value.filter((session) => !session.is_current),
)

function getCsrfToken(): string | undefined {
    const match = document.cookie.match(/(^|;\s*)XSRF-TOKEN=([^;]*)/)

    return match ? decodeURIComponent(match[2]) : undefined
}

async function loadSessions(): Promise<void> {
    if (!props.enabled) {
        return
    }

    loading.value = true
    loaded.value = true

    try {
        const response = await fetch(
            userSessionRoutes.index.url(props.userId),
            {
                headers: {
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            },
        )

        const payload = (await response.json()) as {
            data?: UserSessionRecord[]
        }

        sessions.value = payload.data ?? []
    } finally {
        loading.value = false
    }
}

async function revokeSession(session: UserSessionRecord): Promise<void> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    }
    const xsrfToken = getCsrfToken()

    if (xsrfToken !== undefined) {
        headers['X-XSRF-TOKEN'] = xsrfToken
    }

    await fetch(
        userSessionRoutes.destroy.url({
            user: props.userId,
            session: session.id,
        }),
        {
            credentials: 'same-origin',
            headers,
            method: 'DELETE',
        },
    )

    await loadSessions()
}

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
        sessionBrowserLabel(session.user_agent),
        sessionPlatformLabel(session.user_agent),
    ].filter((value): value is string => Boolean(value && value.trim() !== ''))

    if (parts.length > 0) {
        return parts.join(' on ')
    }

    return session.user_agent
}

onMounted(async () => {
    await loadSessions()
})
</script>

<template>
    <section class="cp-card grid gap-5 p-6">
        <div class="grid gap-1">
            <h2 class="text-lg font-semibold text-[var(--cp-text-primary)]">
                {{ $t('page-settings.sessions_title') }}
            </h2>
            <p class="text-sm text-[var(--cp-text-muted)]">
                {{ $t('page-users.sessions_description') }}
            </p>
        </div>

        <Message
            v-if="!enabled"
            severity="secondary"
            size="small"
            variant="simple"
        >
            {{ $t('page-settings.browser_sessions_unavailable') }}
        </Message>

        <Message
            v-else-if="loading"
            severity="secondary"
            size="small"
            variant="simple"
        >
            {{ $t('page-users.sessions_loading') }}
        </Message>

        <Message
            v-else-if="loaded && sessions.length === 0"
            severity="secondary"
            size="small"
            variant="simple"
        >
            {{ $t('page-users.sessions_empty') }}
        </Message>

        <div v-else class="cp-user-profile__workspace">
            <section
                v-if="currentSession"
                class="cp-user-profile__session-group"
            >
                <span class="cp-user-profile__inline-label">
                    {{ $t('page-settings.browser_sessions_this_device') }}
                </span>

                <article class="cp-user-profile__list-item">
                    <div class="flex items-center gap-3">
                        <div class="cp-user-profile__provider-icon">
                            <AppIcon
                                :name="
                                    sessionDeviceIcon(currentSession.user_agent)
                                "
                                :stroke-width="1.75"
                            />
                        </div>

                        <div class="grid gap-1">
                            <strong
                                class="text-sm text-[var(--cp-text-primary)]"
                            >
                                {{
                                    sessionTitle(currentSession) ??
                                    $t(
                                        'page-settings.browser_sessions_unknown_device',
                                    )
                                }}
                            </strong>
                            <span class="text-xs text-[var(--cp-text-muted)]">
                                {{
                                    currentSession.ip_address ??
                                    $t('common.ui.unknown_ip')
                                }}
                            </span>
                            <span class="text-xs text-[var(--cp-text-muted)]">
                                {{
                                    $t(
                                        'page-settings.browser_sessions_last_active',
                                        {
                                            time: formatLastActive(
                                                currentSession.last_active,
                                            ),
                                        },
                                    )
                                }}
                            </span>
                        </div>
                    </div>

                    <Tag
                        :value="
                            $t('page-settings.browser_sessions_this_device')
                        "
                    />
                </article>
            </section>

            <section
                v-if="otherSessions.length > 0"
                class="cp-user-profile__session-group"
            >
                <span class="cp-user-profile__inline-label">
                    {{ $t('common.auth.browser_sessions') }}
                </span>

                <div class="cp-user-profile__list">
                    <article
                        v-for="session in otherSessions"
                        :key="session.id"
                        class="cp-user-profile__list-item"
                    >
                        <div class="flex items-center gap-3">
                            <div class="cp-user-profile__provider-icon">
                                <AppIcon
                                    :name="
                                        sessionDeviceIcon(session.user_agent)
                                    "
                                    :stroke-width="1.75"
                                />
                            </div>

                            <div class="grid gap-1">
                                <strong
                                    class="text-sm text-[var(--cp-text-primary)]"
                                >
                                    {{
                                        sessionTitle(session) ??
                                        $t(
                                            'page-settings.browser_sessions_unknown_device',
                                        )
                                    }}
                                </strong>
                                <span
                                    class="text-xs text-[var(--cp-text-muted)]"
                                >
                                    {{
                                        session.ip_address ??
                                        $t('common.ui.unknown_ip')
                                    }}
                                </span>
                                <span
                                    class="text-xs text-[var(--cp-text-muted)]"
                                >
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

                        <Button
                            :label="$t('common.ui.remove')"
                            outlined
                            severity="secondary"
                            size="small"
                            @click="revokeSession(session)"
                        />
                    </article>
                </div>
            </section>
        </div>
    </section>
</template>
