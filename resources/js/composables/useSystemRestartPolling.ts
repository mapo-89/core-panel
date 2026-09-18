import { onUnmounted, ref } from 'vue'

const DEFAULT_INTERVAL_MS = 3_000
const DEFAULT_TIMEOUT_MS = 120_000

export function useSystemRestartPolling(
    healthUrl: string,
    options: {
        intervalMs?: number
        isRestartConfirmed?: () => boolean
        onRecovered?: () => void
        timeoutMs?: number
    } = {},
) {
    const intervalMs = options.intervalMs ?? DEFAULT_INTERVAL_MS
    const timeoutMs = options.timeoutMs ?? DEFAULT_TIMEOUT_MS
    const active = ref(false)
    const timedOut = ref(false)
    const unavailable = ref(false)
    let pollTimer: number | null = null
    let timeoutTimer: number | null = null
    let requestController: AbortController | null = null
    let unavailableObserved = false

    function clearTimers(): void {
        if (pollTimer !== null) {
            window.clearTimeout(pollTimer)
            pollTimer = null
        }

        if (timeoutTimer !== null) {
            window.clearTimeout(timeoutTimer)
            timeoutTimer = null
        }
    }

    function stop(): void {
        active.value = false
        clearTimers()
        requestController?.abort()
        requestController = null
    }

    function observeUnavailable(): void {
        if (!active.value) {
            return
        }

        unavailableObserved = true
        unavailable.value = true

        const restartConfirmed = options.isRestartConfirmed?.() ?? true

        if (!restartConfirmed || timeoutTimer !== null) {
            return
        }

        timeoutTimer = window.setTimeout(() => {
            timedOut.value = true
            stop()
        }, timeoutMs)
    }

    async function poll(): Promise<void> {
        if (!active.value) {
            return
        }

        requestController = new AbortController()

        try {
            const response = await fetch(healthUrl, {
                cache: 'no-store',
                headers: { Accept: 'application/json' },
                signal: requestController.signal,
            })
            const restartConfirmed = options.isRestartConfirmed?.() ?? true

            if (
                response.ok &&
                unavailableObserved &&
                restartConfirmed &&
                active.value
            ) {
                stop()
                const onRecovered =
                    options.onRecovered ?? (() => window.location.reload())
                onRecovered()

                return
            }
            if (!response.ok) {
                observeUnavailable()
            }
        } catch {
            observeUnavailable()
            // Restart-related network errors are expected while the runtime is down.
        } finally {
            requestController = null
        }

        if (active.value) {
            pollTimer = window.setTimeout(() => void poll(), intervalMs)
        }
    }

    function start(): void {
        if (active.value) {
            return
        }

        active.value = true
        timedOut.value = false
        unavailable.value = false
        unavailableObserved = false
        void poll()
    }

    onUnmounted(stop)

    return { active, start, stop, timedOut, unavailable }
}
