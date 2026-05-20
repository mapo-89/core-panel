<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'

import {
    formatAuthenticationDeviceLabel,
    formatAuthenticationMethodLabel,
    formatAuthenticationResultLabel,
    resolveAuthenticationResultTone,
} from '@/pages/Admin/Logs/components/authenticationLogPresentation'
import LogBadge from '@/pages/Admin/Logs/components/LogBadge.vue'
import LogUserAvatar from '@/pages/Admin/Logs/components/LogUserAvatar.vue'
import type { AuthenticationLogRecord } from '@/types/core-panel'

defineProps<{
    data: AuthenticationLogRecord
}>()

const emit = defineEmits<{
    cancel: []
}>()

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—'
    }

    return new Date(value).toLocaleString()
}

function formatValue(value: string | null): string {
    if (!value) {
        return '—'
    }

    return value
}
</script>

<template>
    <div class="space-y-5 p-1">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <span
                    class="block text-xs font-medium uppercase text-surface-400 dark:text-surface-500"
                >
                    {{ trans('page-authentication-logs.columns.result') }}
                </span>
                <LogBadge
                    class="mt-1"
                    dot
                    :label="formatAuthenticationResultLabel(data)"
                    :tone="resolveAuthenticationResultTone(data)"
                />
            </div>
            <div>
                <span
                    class="block text-xs font-medium uppercase text-surface-400 dark:text-surface-500"
                >
                    {{ trans('page-authentication-logs.columns.user') }}
                </span>
                <div class="mt-1">
                    <LogUserAvatar
                        :avatar-url="data.userAvatarUrl ?? null"
                        :label="
                            data.userName ??
                            data.userEmail ??
                            data.login ??
                            null
                        "
                        size="sm"
                    />
                </div>
            </div>
            <div>
                <span
                    class="block text-xs font-medium uppercase text-surface-400 dark:text-surface-500"
                >
                    {{ trans('page-authentication-logs.columns.guard') }}
                </span>
                <span
                    class="mt-1 block text-sm text-surface-800 dark:text-surface-200"
                >
                    {{ formatValue(data.guard) }}
                </span>
            </div>
            <div>
                <span
                    class="block text-xs font-medium uppercase text-surface-400 dark:text-surface-500"
                >
                    {{ trans('page-authentication-logs.columns.method') }}
                </span>
                <span
                    class="mt-1 block text-sm text-surface-800 dark:text-surface-200"
                >
                    {{ formatAuthenticationMethodLabel(data) }}
                </span>
            </div>
            <div>
                <span
                    class="block text-xs font-medium uppercase text-surface-400 dark:text-surface-500"
                >
                    {{ trans('page-authentication-logs.columns.ip_address') }}
                </span>
                <span
                    class="mt-1 block text-sm text-surface-800 dark:text-surface-200"
                >
                    {{ formatValue(data.ipAddress) }}
                </span>
            </div>
            <div>
                <span
                    class="block text-xs font-medium uppercase text-surface-400 dark:text-surface-500"
                >
                    {{ trans('page-authentication-logs.columns.device') }}
                </span>
                <span
                    class="mt-1 block text-sm text-surface-800 dark:text-surface-200"
                >
                    {{ formatAuthenticationDeviceLabel(data) }}
                </span>
            </div>
            <div>
                <span
                    class="block text-xs font-medium uppercase text-surface-400 dark:text-surface-500"
                >
                    {{ trans('page-authentication-logs.columns.device_type') }}
                </span>
                <span
                    class="mt-1 block text-sm text-surface-800 dark:text-surface-200"
                >
                    {{ formatValue(data.deviceType) }}
                </span>
            </div>
            <div>
                <span
                    class="block text-xs font-medium uppercase text-surface-400 dark:text-surface-500"
                >
                    {{ trans('page-authentication-logs.columns.browser') }}
                </span>
                <span
                    class="mt-1 block text-sm text-surface-800 dark:text-surface-200"
                >
                    {{ formatValue(data.browser) }}
                </span>
            </div>
            <div>
                <span
                    class="block text-xs font-medium uppercase text-surface-400 dark:text-surface-500"
                >
                    {{ trans('page-authentication-logs.columns.platform') }}
                </span>
                <span
                    class="mt-1 block text-sm text-surface-800 dark:text-surface-200"
                >
                    {{ formatValue(data.platform) }}
                </span>
            </div>
            <div>
                <span
                    class="block text-xs font-medium uppercase text-surface-400 dark:text-surface-500"
                >
                    {{ trans('page-authentication-logs.columns.login_at') }}
                </span>
                <span
                    class="mt-1 block text-sm text-surface-800 dark:text-surface-200"
                >
                    {{ formatDateTime(data.loginAt) }}
                </span>
            </div>
            <div>
                <span
                    class="block text-xs font-medium uppercase text-surface-400 dark:text-surface-500"
                >
                    {{ trans('page-authentication-logs.columns.logout_at') }}
                </span>
                <span
                    class="mt-1 block text-sm text-surface-800 dark:text-surface-200"
                >
                    {{ formatDateTime(data.logoutAt) }}
                </span>
            </div>
            <div>
                <span
                    class="block text-xs font-medium uppercase text-surface-400 dark:text-surface-500"
                >
                    {{
                        trans('page-authentication-logs.columns.last_active_at')
                    }}
                </span>
                <span
                    class="mt-1 block text-sm text-surface-800 dark:text-surface-200"
                >
                    {{ formatDateTime(data.lastActiveAt) }}
                </span>
            </div>
        </div>

        <div>
            <h3
                class="mb-2 text-sm font-semibold text-surface-700 dark:text-surface-300"
            >
                {{ trans('page-authentication-logs.user_agent') }}
            </h3>
            <pre
                class="overflow-auto rounded bg-surface-50 p-3 text-xs text-surface-700 dark:bg-surface-800 dark:text-surface-300"
                >{{ data.userAgent || '—' }}</pre
            >
        </div>

        <div v-if="Object.keys(data.properties ?? {}).length > 0">
            <h3
                class="mb-2 text-sm font-semibold text-surface-700 dark:text-surface-300"
            >
                {{ trans('page-authentication-logs.properties') }}
            </h3>
            <pre
                class="overflow-auto rounded bg-surface-50 p-3 text-xs text-surface-700 dark:bg-surface-800 dark:text-surface-300"
                >{{ JSON.stringify(data.properties, null, 2) }}</pre
            >
        </div>

        <div class="flex justify-end pt-2">
            <Button
                :label="$t('button.close')"
                icon="pi pi-times"
                outlined
                severity="secondary"
                @click="emit('cancel')"
            />
        </div>
    </div>
</template>
