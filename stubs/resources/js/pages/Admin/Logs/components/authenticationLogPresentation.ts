import { trans } from 'laravel-vue-i18n'

import type { AuthenticationLogRecord } from '@core-panel/types/core-panel'

export type AuthenticationResultTone =
    | 'danger'
    | 'info'
    | 'neutral'
    | 'success'
    | 'warning'

export function formatAuthenticationResultLabel(
    log: Pick<AuthenticationLogRecord, 'authenticationResult'>,
): string {
    return trans(`page-authentication-logs.results.${log.authenticationResult}`)
}

export function resolveAuthenticationResultTone(
    log: Pick<AuthenticationLogRecord, 'authenticationResult'>,
): AuthenticationResultTone {
    return (
        {
            expired: 'warning',
            failed: 'danger',
            logout: 'neutral',
            revoked: 'info',
            successful: 'success',
        } satisfies Record<
            AuthenticationLogRecord['authenticationResult'],
            AuthenticationResultTone
        >
    )[log.authenticationResult]
}

export function formatAuthenticationDeviceLabel(
    log: Pick<AuthenticationLogRecord, 'browser' | 'deviceName' | 'platform'>,
): string {
    if (log.deviceName && !looksLikeUserAgent(log.deviceName)) {
        return log.deviceName
    }

    if (log.browser && log.platform) {
        return trans('page-authentication-logs.device_browser_on_platform', {
            browser: log.browser,
            platform: log.platform,
        })
    }

    if (log.browser) {
        return log.browser
    }

    if (log.platform) {
        return log.platform
    }

    return '—'
}

function looksLikeUserAgent(value: string): boolean {
    const normalized = value.toLowerCase()

    return (
        value.length > 80 ||
        normalized.includes('mozilla/') ||
        normalized.includes('applewebkit/') ||
        normalized.includes('gecko/') ||
        normalized.includes('chrome/') ||
        normalized.includes('safari/') ||
        normalized.includes('firefox/')
    )
}

export function formatAuthenticationMethodLabel(
    log: Pick<AuthenticationLogRecord, 'authMethod' | 'socialProvider'>,
): string {
    if (log.authMethod === 'socialite') {
        if (log.socialProvider) {
            return trans(
                'page-authentication-logs.methods.socialite_provider',
                {
                    provider: formatProviderLabel(log.socialProvider),
                },
            )
        }

        return trans('page-authentication-logs.methods.socialite')
    }

    return trans('page-authentication-logs.methods.form')
}

function formatProviderLabel(provider: string): string {
    return provider
        .split(/[-_]/)
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ')
}
