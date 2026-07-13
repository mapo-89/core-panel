<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, markRaw, ref } from 'vue'
import { trans } from 'laravel-vue-i18n'

import TabsRenderer from '@core-panel/components/TabBuilder/TabsRenderer.vue'

import AppLayout from '@core-panel/layouts/AppLayout.vue'
import RouteListTab from '@core-panel/pages/Admin/Developer/components/RouteListTab.vue'
import developer from '@/routes/core-panel/developer'
import type {
    DeveloperRouteTabPayload,
    TabsSchema,
} from '@core-panel/types/core-panel'

const props = defineProps<{
    activeTab: 'api' | 'web' | 'service'
    apiTab?: DeveloperRouteTabPayload | null
    webTab?: DeveloperRouteTabPayload | null
    serviceTab?: DeveloperRouteTabPayload | null
    docsTab?: { docsUrl: string } | null
}>()

const activeTab = ref(props.activeTab)

const tabComponents = {
    RouteListTab: markRaw(RouteListTab),
}

function regenerateApiDocs(): void {
    router.post(
        developer.regenerateApiDocs.url(),
        {},
        {
            preserveScroll: true,
        },
    )
}

const tabSchema = computed<TabsSchema>(() => {
    const tabs: TabsSchema['tabs'] = []

    if (props.apiTab) {
        tabs.push({
            component: 'RouteListTab',
            componentProps: {
                ...props.apiTab,
                emptyMessage: 'page-developer.states.no_api_routes',
                tabLabel: 'page-developer.tabs.api',
            },
            icon: 'sitemap',
            key: 'api',
            label: 'page-developer.tabs.api',
            permission: 'api-routes.view',
        })
    }

    if (props.webTab) {
        tabs.push({
            component: 'RouteListTab',
            componentProps: {
                ...props.webTab,
                emptyMessage: 'page-developer.states.no_web_routes',
                tabLabel: 'page-developer.tabs.web',
            },
            icon: 'globe',
            key: 'web',
            label: 'page-developer.tabs.web',
            permission: 'api-routes.view',
        })
    }

    if (props.serviceTab) {
        tabs.push({
            component: 'RouteListTab',
            componentProps: {
                ...props.serviceTab,
                emptyMessage: 'page-developer.states.no_service_routes',
                tabLabel: 'page-developer.tabs.service',
            },
            icon: 'bolt',
            key: 'service',
            label: 'page-developer.tabs.service',
            permission: 'api-routes.view',
        })
    }

    return {
        panelSurface: true,
        panelSurfaceClass: 'cp-side-tabs__panel-surface--unpadded',
        panelSurfaceVariant: 'card',
        tabs,
    }
})
</script>

<template>
    <AppLayout
        :title="trans('navigation.routes')"
        :subtitle="trans('page-developer.description')"
    >
        <Head :title="trans('navigation.routes')" />

        <template #page-actions>
            <Button
                v-if="docsTab"
                icon="pi pi-refresh"
                outlined
                :label="$t('page-developer.actions.generate_docs')"
                @click="regenerateApiDocs"
            />
            <Button
                v-if="docsTab"
                as="a"
                :href="docsTab.docsUrl"
                icon="pi pi-external-link"
                outlined
                rel="noopener noreferrer"
                target="_blank"
                :label="$t('page-developer.actions.open_docs')"
            />
        </template>

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
