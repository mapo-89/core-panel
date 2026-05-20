<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import {
    computed,
    markRaw,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue'

import TabsRenderer from '@core-panel/components/TabBuilder/TabsRenderer.vue'

import AppLayout from '@/layouts/AppLayout.vue'
import ApiSettingsTab from '@/pages/Admin/Settings/components/ApiSettingsTab.vue'
import AuthSettingsTab from '@/pages/Admin/Settings/components/AuthSettingsTab.vue'
import GeneralSettingsTab from '@/pages/Admin/Settings/components/GeneralSettingsTab.vue'
import UiAppearanceSettingsTab from '@/pages/Admin/Settings/components/UiAppearanceSettingsTab.vue'
import SettingsGroupPanel from '@/pages/Admin/Settings/components/SettingsGroupPanel.vue'
import type {
    ApiTokenManagerPayload,
    SettingGroupRecord,
    TabsSchema,
} from '@/types/core-panel'

const props = withDefaults(
    defineProps<{
        apiTokenManager?: ApiTokenManagerPayload | null
        currentGroup?: string
        groups?: SettingGroupRecord[]
    }>(),
    {
        apiTokenManager: null,
        currentGroup: 'general',
        groups: () => [],
    },
)

const visibleGroupKeys = ['general', 'appearance', 'auth', 'api']

function normalizeRequestedTab(group: string | undefined): string {
    if (group === 'i18n') {
        return 'general'
    }

    if (group === 'ui') {
        return 'appearance'
    }

    if (group && visibleGroupKeys.includes(group)) {
        return group
    }

    return 'general'
}

const activeTab = ref(normalizeRequestedTab(props.currentGroup))
const apiTokenCreateRequest = ref(0)
let cleanupStickyObserver: (() => void) | null = null
const tabComponents = {
    ApiSettingsTab: markRaw(ApiSettingsTab),
    AuthSettingsTab: markRaw(AuthSettingsTab),
    GeneralSettingsTab: markRaw(GeneralSettingsTab),
    SettingsGroupPanel: markRaw(SettingsGroupPanel),
    UiAppearanceSettingsTab: markRaw(UiAppearanceSettingsTab),
}

function requestCreateToken(): void {
    apiTokenCreateRequest.value += 1
}

const generalGroup = computed<SettingGroupRecord | undefined>(() =>
    props.groups.find((group) => group.key === 'general'),
)

const languageGroup = computed<SettingGroupRecord | undefined>(() =>
    props.groups.find((group) => group.key === 'i18n'),
)

const appearanceGroup = computed<SettingGroupRecord | undefined>(() =>
    props.groups.find((group) => group.key === 'appearance'),
)

const authGroup = computed<SettingGroupRecord | undefined>(() =>
    props.groups.find((group) => group.key === 'auth'),
)

const uiGroup = computed<SettingGroupRecord | undefined>(() =>
    props.groups.find((group) => group.key === 'ui'),
)

const tabIcons: Record<string, string> = {
    api: 'key',
    appearance: 'image',
    auth: 'shield',
    files: 'files',
    general: 'settings',
    mail: 'email',
    oauth: 'key',
    security: 'lock',
    storage: 'files',
}

const visibleGroups = computed(() =>
    props.groups.filter(
        (group) =>
            !['i18n', 'ui'].includes(group.key) &&
            visibleGroupKeys.includes(group.key),
    ),
)

const tabSchema = computed<TabsSchema>(() => ({
    tabs: [...visibleGroups.value]
        .sort((left, right) => {
            const order = ['general', 'appearance', 'auth', 'api']
            const leftIndex = order.indexOf(left.key)
            const rightIndex = order.indexOf(right.key)

            if (leftIndex === -1 && rightIndex === -1) {
                return 0
            }

            if (leftIndex === -1) {
                return 1
            }

            if (rightIndex === -1) {
                return -1
            }

            return leftIndex - rightIndex
        })
        .map((group) => ({
            component:
                group.key === 'general'
                    ? 'GeneralSettingsTab'
                    : group.key === 'appearance'
                      ? 'UiAppearanceSettingsTab'
                      : group.key === 'auth'
                        ? 'AuthSettingsTab'
                        : group.key === 'api'
                          ? 'ApiSettingsTab'
                          : 'SettingsGroupPanel',
            componentProps:
                group.key === 'general'
                    ? {
                          generalGroup: generalGroup.value ?? group,
                          languageGroup: languageGroup.value ?? null,
                      }
                    : group.key === 'appearance'
                      ? {
                            appearanceGroup: appearanceGroup.value ?? group,
                            uiGroup: uiGroup.value,
                        }
                      : group.key === 'auth'
                        ? {
                              authGroup: authGroup.value ?? group,
                          }
                        : group.key === 'api'
                          ? {
                                apiTokenManager: props.apiTokenManager,
                                createRequestKey: apiTokenCreateRequest.value,
                                onRequestCreateToken: requestCreateToken,
                            }
                          : { group },
            icon: tabIcons[group.key] ?? 'settings',
            key: group.key,
            label: group.label,
        })),
}))

function syncStickyActionState(): void {
    const scrollContainer = document.querySelector(
        '.app-main',
    ) as HTMLElement | null

    if (!scrollContainer) {
        return
    }
    const scrollContainerRect = scrollContainer.getBoundingClientRect()

    const stickyActions = document.querySelectorAll<HTMLElement>(
        '.cp-settings-general-tab__actions--sticky, .cp-settings-appearance-tab__actions--sticky, .cp-settings-group-panel__actions--sticky',
    )

    stickyActions.forEach((actions) => {
        const sectionBody = actions.closest(
            '.cp-section__body',
        ) as HTMLElement | null

        if (!sectionBody) {
            actions.classList.remove('is-stuck')

            return
        }

        const sectionBodyRect = sectionBody.getBoundingClientRect()
        const actionsRect = actions.getBoundingClientRect()
        const intersectsScrollFrame =
            sectionBodyRect.top < scrollContainerRect.bottom &&
            sectionBodyRect.bottom > scrollContainerRect.top
        const naturalTop = sectionBodyRect.bottom - actionsRect.height
        const isShiftedFromNaturalPosition = actionsRect.top < naturalTop - 1
        const isStuck = intersectsScrollFrame && isShiftedFromNaturalPosition

        actions.classList.toggle('is-stuck', isStuck)
    })
}

function bindStickyActionObserver(): void {
    cleanupStickyObserver?.()

    const scrollContainer = document.querySelector(
        '.app-main',
    ) as HTMLElement | null

    if (!scrollContainer) {
        cleanupStickyObserver = null

        return
    }

    const handleSync = (): void => {
        syncStickyActionState()
    }

    const resizeObserver =
        typeof ResizeObserver === 'undefined'
            ? null
            : new ResizeObserver(() => {
                  syncStickyActionState()
              })

    resizeObserver?.observe(scrollContainer)

    document
        .querySelectorAll<HTMLElement>(
            '.cp-section--sticky-actions .cp-section__body',
        )
        .forEach((sectionBody) => {
            resizeObserver?.observe(sectionBody)
        })

    scrollContainer.addEventListener('scroll', handleSync, { passive: true })
    window.addEventListener('resize', handleSync)
    syncStickyActionState()

    cleanupStickyObserver = () => {
        scrollContainer.removeEventListener('scroll', handleSync)
        window.removeEventListener('resize', handleSync)
        resizeObserver?.disconnect()
    }
}

watch(activeTab, async () => {
    await nextTick()
    bindStickyActionObserver()
})

onMounted(async () => {
    await nextTick()
    bindStickyActionObserver()
})

onBeforeUnmount(() => {
    cleanupStickyObserver?.()
})
</script>

<template>
    <AppLayout
        :title="$t('navigation.settings')"
        :subtitle="$t('page-settings.settings_description')"
    >
        <Head :title="$t('navigation.settings')" />

        <TabsRenderer
            v-model="activeTab"
            class="cp-side-tabs"
            :components="tabComponents"
            layout="vertical"
            :schema="tabSchema"
            sync-with-url
        />
    </AppLayout>
</template>
