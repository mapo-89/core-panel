<script setup lang="ts">
import { computed, onMounted, ref, useAttrs, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'

import AppIcon from '../AppIcon.vue'
import FormRenderer from '../FormBuilder/FormRenderer.vue'
import type { FormModel } from '../FormBuilder/types'
import type { TabBuilderTab, TabsSchema } from './types'

type TabsPageProps = {
    auth?: {
        permissions?: string[]
    }
}

const props = withDefaults(
    defineProps<{
        components?: Record<string, unknown>
        layout?: 'horizontal' | 'vertical'
        modelValue?: string
        permissions?: string[]
        reloadOnly?: Record<string, string[]>
        schema: TabsSchema
        syncWithUrl?: boolean
        urlKey?: string
    }>(),
    {
        components: () => ({}),
        layout: 'horizontal',
        modelValue: undefined,
        permissions: () => [],
        reloadOnly: () => ({}),
        syncWithUrl: false,
        urlKey: 'tab',
    },
)

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

const page = usePage<TabsPageProps>()
const attrs = useAttrs()
const loadedTabs = ref<string[]>([])
const formModel = ref<FormModel>({})

const availablePermissions = computed(() =>
    props.permissions.length > 0
        ? props.permissions
        : (page.props.auth?.permissions ?? []),
)

const visibleTabs = computed(() =>
    (props.schema?.tabs ?? []).filter((tab) => {
        if (tab.visible === false) {
            return false
        }

        if (tab.permission === undefined || tab.permission === '') {
            return true
        }

        return availablePermissions.value.includes(tab.permission)
    }),
)

const defaultTab = computed(() => visibleTabs.value[0]?.key ?? '')
const activeTab = ref(props.modelValue ?? defaultTab.value)
const activeVisibleTab = computed(
    () =>
        visibleTabs.value.find((tab) => tab.key === activeTab.value) ??
        visibleTabs.value[0],
)

function resolveLabel(tab: TabBuilderTab): string {
    const locale = document.documentElement.lang || 'en'
    const translated = tab.labelTranslations?.[locale] ?? tab.label ?? tab.key

    return trans(translated)
}

function activateTab(key: string): void {
    const wasLoaded = loadedTabs.value.includes(key)

    activeTab.value = key
    emit('update:modelValue', key)

    if (!wasLoaded) {
        loadedTabs.value.push(key)
    }

    if (props.syncWithUrl) {
        const url = new URL(window.location.href)
        url.searchParams.set(props.urlKey, key)
        window.history.replaceState(window.history.state, '', url.toString())
    }

    const partials = props.reloadOnly[key]

    if (!wasLoaded && partials !== undefined && partials.length > 0) {
        router.reload({
            only: partials,
        })
    }
}

function isLoaded(tab: TabBuilderTab): boolean {
    if (tab.lazy !== true) {
        return true
    }

    return loadedTabs.value.includes(tab.key)
}

onMounted(() => {
    const urlTab = props.syncWithUrl
        ? new URL(window.location.href).searchParams.get(props.urlKey)
        : null

    activeTab.value =
        urlTab && visibleTabs.value.some((tab) => tab.key === urlTab)
            ? urlTab
            : (props.modelValue ?? defaultTab.value)

    if (activeTab.value !== '') {
        loadedTabs.value.push(activeTab.value)
        emit('update:modelValue', activeTab.value)
    }
})

watch(
    () => props.modelValue,
    (value) => {
        if (value !== undefined && value !== '' && value !== activeTab.value) {
            activateTab(value)
        }
    },
)
</script>

<template>
    <div v-if="layout === 'vertical'" v-bind="attrs" class="cp-tabs-vertical">
        <div class="cp-tabs-vertical__sidebar">
            <div class="cp-vtab-card">
                <nav class="cp-vtab-nav">
                    <button
                        v-for="tab in visibleTabs"
                        :key="tab.key"
                        class="cp-vtab"
                        :class="{
                            'cp-vtab--active': activeTab === tab.key,
                        }"
                        type="button"
                        @click="activateTab(tab.key)"
                    >
                        <span v-if="tab.icon" class="cp-vtab__icon-tile">
                            <AppIcon :name="tab.icon" class="cp-vtab__icon" />
                        </span>
                        <span class="cp-vtab__body">
                            <span class="cp-vtab__label">
                                {{ resolveLabel(tab) }}
                            </span>
                        </span>
                        <span v-if="tab.badge" class="cp-vtab__trailing">
                            <Badge :value="tab.badge" />
                        </span>
                    </button>
                </nav>
            </div>
        </div>

        <div class="cp-tabs-vertical__content">
            <div v-if="activeVisibleTab" class="cp-tabs-vertical__panel">
                <div v-if="!isLoaded(activeVisibleTab)" class="grid gap-3">
                    <Skeleton height="2.5rem" />
                    <Skeleton height="2.5rem" />
                    <Skeleton height="8rem" />
                </div>

                <template v-else>
                    <div
                        v-if="schema.panelSurface"
                        :class="[
                            'cp-side-tabs__panel-surface',
                            schema.panelSurfaceVariant === 'flush'
                                ? 'cp-side-tabs__panel-surface--flush'
                                : null,
                            schema.panelSurfaceClass,
                        ]"
                    >
                        <component
                            :is="components[activeVisibleTab.component]"
                            v-if="
                                activeVisibleTab.component &&
                                components[activeVisibleTab.component]
                            "
                            v-bind="activeVisibleTab.componentProps ?? {}"
                        />

                        <FormRenderer
                            v-else-if="activeVisibleTab.schema"
                            :errors="{}"
                            :model-value="formModel"
                            :schema="activeVisibleTab.schema"
                            :wrap-in-form="false"
                            @update:model-value="formModel = $event"
                        />

                        <div
                            v-else
                            class="rounded border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] px-4 py-6 text-sm text-[var(--cp-text-muted)]"
                        >
                            {{ resolveLabel(activeVisibleTab) }}
                        </div>
                    </div>

                    <component
                        :is="components[activeVisibleTab.component]"
                        v-else-if="
                            activeVisibleTab.component &&
                            components[activeVisibleTab.component]
                        "
                        v-bind="activeVisibleTab.componentProps ?? {}"
                    />

                    <FormRenderer
                        v-else-if="activeVisibleTab.schema"
                        :errors="{}"
                        :model-value="formModel"
                        :schema="activeVisibleTab.schema"
                        :wrap-in-form="false"
                        @update:model-value="formModel = $event"
                    />

                    <div
                        v-else
                        class="rounded border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] px-4 py-6 text-sm text-[var(--cp-text-muted)]"
                    >
                        {{ resolveLabel(activeVisibleTab) }}
                    </div>
                </template>
            </div>
        </div>
    </div>

    <Tabs
        v-else
        v-bind="attrs"
        :value="activeTab"
        @update:value="activateTab(String($event))"
    >
        <TabList>
            <Tab v-for="tab in visibleTabs" :key="tab.key" :value="tab.key">
                <div class="flex items-center gap-2">
                    <AppIcon v-if="tab.icon" :name="tab.icon" />
                    <span>{{ resolveLabel(tab) }}</span>
                    <Badge v-if="tab.badge" :value="tab.badge" />
                </div>
            </Tab>
        </TabList>

        <TabPanels>
            <TabPanel
                v-for="tab in visibleTabs"
                :key="tab.key"
                :value="tab.key"
            >
                <div v-if="!isLoaded(tab)" class="grid gap-3">
                    <Skeleton height="2.5rem" />
                    <Skeleton height="2.5rem" />
                    <Skeleton height="8rem" />
                </div>

                <component
                    :is="components[tab.component]"
                    v-bind="tab.componentProps ?? {}"
                    v-else-if="tab.component && components[tab.component]"
                />

                <FormRenderer
                    v-else-if="tab.schema"
                    :errors="{}"
                    :model-value="formModel"
                    :schema="tab.schema"
                    :wrap-in-form="false"
                    @update:model-value="formModel = $event"
                />

                <div
                    v-else
                    class="rounded border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] px-4 py-6 text-sm text-[var(--cp-text-muted)]"
                >
                    {{ resolveLabel(tab) }}
                </div>
            </TabPanel>
        </TabPanels>
    </Tabs>
</template>
