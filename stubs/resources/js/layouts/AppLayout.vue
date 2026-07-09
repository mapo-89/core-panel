<script setup lang="ts">
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
    watchEffect,
} from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { useToast } from 'primevue/usetoast'

import AppToast from '@/components/AppToast.vue'
import SocialAvatarSyncDialog from '@/components/Auth/SocialAvatarSyncDialog.vue'
import { useColorMode } from '@/composables/useColorMode'
import { usePresenceRuntime } from '@/composables/usePresenceRealtime'
import { useRuntimeUiSettings } from '@/composables/useRuntimeUiSettings'
import { useSidebar } from '@/composables/useSidebar'
import AppFooter from '@/layouts/components/AppFooter.vue'
import AppHeader from '@/layouts/components/AppHeader.vue'
import AppPageHeader from '@/layouts/components/AppPageHeader.vue'
import AppSidebar from '@/layouts/components/AppSidebar.vue'

const props = withDefaults(
    defineProps<{
        backUrl?: boolean | string
        subtitle?: string
        title?: string
    }>(),
    {
        backUrl: false,
        subtitle: '',
        title: '',
    },
)

const page = usePage<{
    appName?: string
    auth?: {
        user?: {
            id?: string | null
            presenceLastSeenAt?: number | null
        } | null
    }
    corePanel?: {
        settings?: {
            ui?: {
                dark_mode_default?: boolean
                show_app_footer?: boolean
            }
        }
    }
    flash?: {
        error?: string | null
        info?: string | null
        socialAvatarPrompt?: {
            current_avatar_url?: string | null
            provider?: string
            provider_avatar_url?: string | null
            provider_label?: string | null
            user_id?: string | null
        } | null
        status?: string | null
        success?: string | null
        warning?: string | null
    }
}>()
const toast = useToast()
const sharedAppName = computed(() => page.props.appName ?? 'CorePanel')
const { colorMode, isDarkMode, setColorMode } = useColorMode('system')
const {
    closeMobileSidebar,
    isCollapsed,
    isMobile,
    isMobileOpen,
    toggleSidebar,
} = useSidebar()
useRuntimeUiSettings()
const showAppFooter = computed(
    () => page.props.corePanel?.settings?.ui?.show_app_footer !== false,
)
const authUserId = computed(() => page.props.auth?.user?.id ?? null)
const authUserPresenceLastSeenAt = computed(
    () => page.props.auth?.user?.presenceLastSeenAt ?? null,
)

usePresenceRuntime(authUserId, authUserPresenceLastSeenAt)

watchEffect(() => {
    document.documentElement.dataset.appName = sharedAppName.value
})

function resolveFlashStatus(status: string): string {
    const normalizedStatus = status.trim()

    const translations: Record<string, string> = {
        'password-updated': trans('page-settings.password_updated_status'),
        'profile-information-updated': trans('page-users.users.updated'),
        'recovery-codes-generated': trans(
            'page-settings.recovery_codes_generated_status',
        ),
        'recovery-codes-regenerated': trans(
            'page-settings.recovery_codes_regenerated_status',
        ),
        'two-factor-authentication-confirmed': trans(
            'page-settings.two_factor_confirmed_status',
        ),
        'two-factor-authentication-disabled': trans(
            'page-settings.two_factor_disabled_status',
        ),
        'two-factor-authentication-enabled': trans(
            'page-settings.two_factor_enabled_status',
        ),
        'verification-link-sent': trans(
            'page-auth.verification_link_sent_status',
        ),
    }

    return translations[normalizedStatus] ?? normalizedStatus
}

const flashState = computed(() => ({
    error: page.props.flash?.error ?? null,
    info: page.props.flash?.info ?? null,
    socialAvatarPrompt: page.props.flash?.socialAvatarPrompt ?? null,
    status: page.props.flash?.status ?? null,
    success: page.props.flash?.success ?? null,
    warning: page.props.flash?.warning ?? null,
}))
const lastFlashFingerprint = ref<string | null>(null)
let removeVisitStartListener: (() => void) | null = null

onMounted(() => {
    removeVisitStartListener = router.on('start', (event) => {
        const method = String(
            (event as { detail?: { visit?: { method?: string } } }).detail
                ?.visit?.method ?? 'get',
        ).toLowerCase()

        if (method === 'get') {
            return
        }

        lastFlashFingerprint.value = null
    })
})

onBeforeUnmount(() => {
    removeVisitStartListener?.()
    removeVisitStartListener = null
})

watch(
    flashState,
    (flash) => {
        const notifications = [
            flash.success
                ? {
                      detail: flash.success,
                      life: 4000,
                      severity: 'success' as const,
                      summary: trans('common.ui.saved'),
                  }
                : null,
            flash.status
                ? {
                      detail: resolveFlashStatus(flash.status),
                      life: 4000,
                      severity: 'success' as const,
                      summary: trans('common.ui.saved'),
                  }
                : null,
            flash.error
                ? {
                      detail: flash.error,
                      life: 5000,
                      severity: 'error' as const,
                      summary: trans('common.ui.error'),
                  }
                : null,
            flash.warning
                ? {
                      detail: flash.warning,
                      life: 4500,
                      severity: 'warn' as const,
                      summary: trans('common.ui.status'),
                  }
                : null,
            flash.info
                ? {
                      detail: flash.info,
                      life: 4000,
                      severity: 'info' as const,
                      summary: trans('common.ui.status'),
                  }
                : null,
        ].filter((entry): entry is NonNullable<typeof entry> => entry !== null)

        if (notifications.length === 0) {
            lastFlashFingerprint.value = null

            return
        }

        const fingerprint = JSON.stringify(notifications)

        if (lastFlashFingerprint.value === fingerprint) {
            return
        }

        lastFlashFingerprint.value = fingerprint

        notifications.forEach((notification) => {
            toast.add(notification)
        })
    },
    { immediate: true },
)

const socialAvatarPrompt = computed(() => page.props.flash?.socialAvatarPrompt)
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-[var(--cp-surface-canvas)]">
        <AppToast />
        <ConfirmDialog />
        <DynamicDialog />
        <SocialAvatarSyncDialog
            v-if="
                socialAvatarPrompt?.provider &&
                socialAvatarPrompt?.provider_label
            "
            :current-avatar-url="socialAvatarPrompt.current_avatar_url ?? null"
            :provider="socialAvatarPrompt.provider"
            :provider-avatar-url="
                socialAvatarPrompt.provider_avatar_url ?? null
            "
            :provider-label="socialAvatarPrompt.provider_label"
        />

        <AppSidebar
            :collapsed="isCollapsed"
            :is-mobile="isMobile"
            :mobile-open="isMobileOpen"
            @close-mobile="closeMobileSidebar"
        />

        <div
            class="app-main-shell flex min-w-0 flex-1 flex-col overflow-hidden"
            :class="{
                'app-main-shell--expanded': !isMobile && !isCollapsed,
                'app-main-shell--collapsed': !isMobile && isCollapsed,
                'app-main-shell--mobile': isMobile,
            }"
        >
            <AppHeader
                :collapsed="isCollapsed"
                :color-mode="colorMode"
                :is-dark-mode="isDarkMode"
                :is-mobile="isMobile"
                @set-color-mode="setColorMode"
                @toggle-sidebar="toggleSidebar"
            />

            <main
                class="app-main flex min-h-0 w-full flex-1 flex-col overflow-y-auto px-4 pt-[4.5rem] pb-8 md:px-6 lg:px-8"
            >
                <AppPageHeader
                    :back-url="props.backUrl"
                    :subtitle="props.subtitle"
                    :title="props.title"
                >
                    <template #actions>
                        <slot name="page-actions" />
                    </template>
                </AppPageHeader>

                <slot />
            </main>

            <AppFooter v-if="showAppFooter" />
        </div>
    </div>
</template>
