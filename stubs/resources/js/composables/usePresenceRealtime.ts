import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    toValue,
    watch,
    type MaybeRefOrGetter,
    type Ref,
} from 'vue'

import presenceRoutes from '@/routes/presence'

type PresenceStatus = 'online' | 'away' | 'offline'

type PresenceEvent = {
    lastSeenAt: number | null
    status?: PresenceStatus | null
    userId: string
}

type PresenceResponse = {
    data?: PresenceEvent[]
    meta?: {
        cursor?: number
    }
}

const cursor = ref(0)
const lastSeenByUserId = ref<Record<string, number | null>>({})
const now = ref(Date.now())
const trackedUserIds = new Set<string>()

let pollAbortController: AbortController | null = null
let clockIntervalId: number | null = null
let isPolling = false
let isStarted = false
let lastInteractionAt = Date.now()
let removeFocusListener: (() => void) | null = null
let removeVisibilityListener: (() => void) | null = null
let removeInteractionListeners: (() => void) | null = null

const ONLINE_WINDOW_MS = 2 * 60 * 1000
const AWAY_WINDOW_MS = 10 * 60 * 1000

function resolvePresenceStatus(
    lastSeenAt: number | null | undefined,
): PresenceStatus {
    if (typeof lastSeenAt !== 'number') {
        return 'offline'
    }

    const elapsed = now.value - lastSeenAt * 1000

    if (elapsed <= ONLINE_WINDOW_MS) {
        return 'online'
    }

    if (elapsed <= AWAY_WINDOW_MS) {
        return 'away'
    }

    return 'offline'
}

function getXsrfToken(): string | null {
    const match = document.cookie.match(/(^|;\s*)XSRF-TOKEN=([^;]*)/)

    return match ? decodeURIComponent(match[2]) : null
}

function isDocumentVisible(): boolean {
    if (typeof document === 'undefined') {
        return true
    }

    return document.visibilityState !== 'hidden'
}

function setTrackedPresence(
    userId: string | null | undefined,
    lastSeenAt: number | null | undefined,
): void {
    if (!userId) {
        return
    }

    if (typeof lastSeenAt !== 'number') {
        return
    }

    const currentValue = lastSeenByUserId.value[userId]

    if (
        currentValue === undefined ||
        currentValue === null ||
        lastSeenAt >= currentValue
    ) {
        lastSeenByUserId.value = {
            ...lastSeenByUserId.value,
            [userId]: lastSeenAt,
        }
    }
}

function applyPresenceResponse(payload: PresenceResponse): void {
    cursor.value = Number(payload.meta?.cursor ?? cursor.value)

    for (const event of payload.data ?? []) {
        setTrackedPresence(event.userId, event.lastSeenAt)
    }
}

async function heartbeat(): Promise<void> {
    if (!isStarted || !isDocumentVisible()) {
        return
    }

    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    }
    const xsrfToken = getXsrfToken()

    if (xsrfToken) {
        headers['X-XSRF-TOKEN'] = xsrfToken
    }

    const response = await fetch(presenceRoutes.heartbeat.url(), {
        credentials: 'same-origin',
        headers,
        method: 'POST',
    })

    if (!response.ok) {
        return
    }

    applyPresenceResponse((await response.json()) as PresenceResponse)
}

async function poll(): Promise<void> {
    if (isPolling || !isStarted) {
        return
    }

    isPolling = true

    try {
        while (isStarted) {
            if (!isDocumentVisible()) {
                await new Promise((resolve) => window.setTimeout(resolve, 1000))

                continue
            }

            const ids = [...trackedUserIds]

            if (ids.length === 0) {
                await new Promise((resolve) => window.setTimeout(resolve, 1000))

                continue
            }

            pollAbortController?.abort()
            pollAbortController = new AbortController()

            const url = new URL(
                presenceRoutes.updates.url(),
                window.location.origin,
            )
            url.searchParams.set('cursor', String(cursor.value))

            for (const id of ids) {
                url.searchParams.append('ids[]', id)
            }

            try {
                const response = await fetch(url.toString(), {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: pollAbortController.signal,
                })

                if (!response.ok) {
                    await new Promise((resolve) =>
                        window.setTimeout(resolve, 1000),
                    )

                    continue
                }

                applyPresenceResponse(
                    (await response.json()) as PresenceResponse,
                )
            } catch (error) {
                if (
                    error instanceof DOMException &&
                    error.name === 'AbortError'
                ) {
                    return
                }

                await new Promise((resolve) => window.setTimeout(resolve, 1000))
            }
        }
    } finally {
        isPolling = false
    }
}

function startIntervals(): void {
    if (clockIntervalId === null) {
        clockIntervalId = window.setInterval(() => {
            now.value = Date.now()
        }, 15000)
    }
}

function stopIntervals(): void {
    if (clockIntervalId !== null) {
        window.clearInterval(clockIntervalId)
        clockIntervalId = null
    }
}

function registerUser(userId: string): () => void {
    trackedUserIds.add(userId)

    return () => {
        trackedUserIds.delete(userId)
    }
}

export function usePresenceRuntime(
    authUserId: Ref<string | null>,
    authUserLastSeenAt: Ref<number | null>,
): void {
    let unregisterCurrentUser: (() => void) | null = null

    watch(
        [authUserId, authUserLastSeenAt],
        ([userId, lastSeenAt]) => {
            if (!userId) {
                return
            }

            setTrackedPresence(userId, lastSeenAt)
        },
        { immediate: true },
    )

    onMounted(() => {
        if (!authUserId.value) {
            return
        }

        isStarted = true
        startIntervals()

        unregisterCurrentUser = registerUser(authUserId.value)

        const handleFocus = () => {
            lastInteractionAt = Date.now()
            now.value = Date.now()
            void heartbeat()
        }

        const handleVisibilityChange = () => {
            now.value = Date.now()

            if (isDocumentVisible()) {
                void heartbeat()
                void poll()
            }
        }

        const handleInteraction = () => {
            const interactionTimestamp = Date.now()

            if (interactionTimestamp - lastInteractionAt < 15000) {
                return
            }

            lastInteractionAt = interactionTimestamp
            now.value = interactionTimestamp
            void heartbeat()
        }

        window.addEventListener('focus', handleFocus)
        document.addEventListener('visibilitychange', handleVisibilityChange)
        window.addEventListener('pointerdown', handleInteraction, {
            passive: true,
        })
        window.addEventListener('keydown', handleInteraction, { passive: true })
        window.addEventListener('scroll', handleInteraction, { passive: true })
        window.addEventListener('touchstart', handleInteraction, {
            passive: true,
        })
        window.addEventListener('mousemove', handleInteraction, {
            passive: true,
        })

        removeFocusListener = () => {
            window.removeEventListener('focus', handleFocus)
        }
        removeVisibilityListener = () => {
            document.removeEventListener(
                'visibilitychange',
                handleVisibilityChange,
            )
        }
        removeInteractionListeners = () => {
            window.removeEventListener('pointerdown', handleInteraction)
            window.removeEventListener('keydown', handleInteraction)
            window.removeEventListener('scroll', handleInteraction)
            window.removeEventListener('touchstart', handleInteraction)
            window.removeEventListener('mousemove', handleInteraction)
        }

        void heartbeat()
        void poll()
    })

    onBeforeUnmount(() => {
        unregisterCurrentUser?.()
        unregisterCurrentUser = null
        isStarted = false
        removeFocusListener?.()
        removeVisibilityListener?.()
        removeInteractionListeners?.()
        removeFocusListener = null
        removeVisibilityListener = null
        removeInteractionListeners = null
        pollAbortController?.abort()
        pollAbortController = null
        stopIntervals()
    })
}

export function usePresenceStatus(options: {
    fallbackLastSeenAt?: MaybeRefOrGetter<number | null | undefined>
    fallbackStatus?: MaybeRefOrGetter<PresenceStatus | null | undefined>
    userId?: MaybeRefOrGetter<string | null | undefined>
}): Ref<PresenceStatus> {
    let unregister: (() => void) | null = null

    const resolvedStatus = computed<PresenceStatus>(() => {
        const userId = toValue(options.userId)
        const fallbackLastSeenAt = toValue(options.fallbackLastSeenAt)
        const lastSeenAt =
            (userId ? lastSeenByUserId.value[userId] : undefined) ??
            fallbackLastSeenAt

        if (typeof lastSeenAt === 'number') {
            return resolvePresenceStatus(lastSeenAt)
        }

        const fallbackStatus = toValue(options.fallbackStatus)

        if (
            fallbackStatus === 'online' ||
            fallbackStatus === 'away' ||
            fallbackStatus === 'offline'
        ) {
            return fallbackStatus
        }

        return 'offline'
    })

    onMounted(() => {
        const userId = toValue(options.userId)

        if (!userId) {
            return
        }

        setTrackedPresence(userId, toValue(options.fallbackLastSeenAt))
        unregister = registerUser(userId)
    })

    onBeforeUnmount(() => {
        unregister?.()
        unregister = null
    })

    return resolvedStatus
}
