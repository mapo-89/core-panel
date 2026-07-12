<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed, markRaw, ref } from 'vue'

import { trans } from 'laravel-vue-i18n'

import TabsRenderer from '@core-panel/components/TabBuilder/TabsRenderer.vue'

import { useCan } from '@core-panel/composables/useCan'
import AppLayout from '@core-panel/layouts/AppLayout.vue'
import ActivityLogsTab from '@/pages/Admin/Logs/components/ActivityLogsTab.vue'
import AuthenticationLogsTab from '@/pages/Admin/Logs/components/AuthenticationLogsTab.vue'
import LogFilesTab from '@/pages/Admin/Logs/components/LogFilesTab.vue'
import type {
    ActivityLogRecord,
    AuthenticationLogRecord,
    LogFileRecord,
    TabsSchema,
} from '@core-panel/types/core-panel'

type ActivityTabPayload = {
    filters: {
        date_from: string | null
        date_to: string | null
        event: string | null
        search: string
        subject_type: string | null
        user: string | null
    }
    logs: {
        currentPage: number
        data: ActivityLogRecord[]
        lastPage: number
        perPage: number
        total: number
    }
    options: {
        events: Array<{ label: string; value: string }>
        subjectTypes: Array<{ label: string; value: string }>
        users: Array<{ label: string; value: string }>
    }
}

type AuthenticationTabPayload = {
    filters: {
        date_from: string | null
        date_to: string | null
        guard: string | null
        result: string | null
        search: string
        user: string | null
    }
    logs: {
        currentPage: number
        data: AuthenticationLogRecord[]
        lastPage: number
        perPage: number
        total: number
    }
    options: {
        guards: Array<{ label: string; value: string }>
        results: Array<{ label: string; value: string }>
        users: Array<{ label: string; value: string }>
    }
}

type LogsTabPayload = {
    filters: {
        channel: string | null
        direction: string
        search: string
        state: string | null
        sort: string
    }
    files: {
        currentPage: number
        data: LogFileRecord[]
        lastPage: number
        perPage: number
        total: number
    }
    options: {
        channels: Array<{ label: string; value: string }>
        states: Array<{ label: string; value: string }>
    }
}

const props = defineProps<{
    activeTab: 'activity' | 'authentication' | 'logs'
    activityTab?: ActivityTabPayload | null
    authenticationTab?: AuthenticationTabPayload | null
    logsTab?: LogsTabPayload | null
}>()

const activeTab = ref(props.activeTab)
const { hasRole } = useCan()

const tabComponents = {
    ActivityLogsTab: markRaw(ActivityLogsTab),
    AuthenticationLogsTab: markRaw(AuthenticationLogsTab),
    LogFilesTab: markRaw(LogFilesTab),
}

const tabSchema = computed<TabsSchema>(() => {
    const tabs: TabsSchema['tabs'] = []

    if (props.activityTab) {
        tabs.push({
            component: 'ActivityLogsTab',
            componentProps: props.activityTab,
            icon: 'activity',
            key: 'activity',
            label: 'page-logs.tabs.activity',
            permission: 'activity-logs.view',
        })
    }

    if (props.authenticationTab) {
        tabs.push({
            component: 'AuthenticationLogsTab',
            componentProps: props.authenticationTab,
            icon: 'lock',
            key: 'authentication',
            label: 'page-logs.tabs.authentication',
            permission: 'authentication-logs.view',
        })
    }

    if (props.logsTab && hasRole('super-admin')) {
        tabs.push({
            component: 'LogFilesTab',
            componentProps: props.logsTab,
            icon: 'files',
            key: 'logs',
            label: 'page-logs.tabs.logs',
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
        :title="trans('page-logs.title')"
        :subtitle="trans('page-logs.description')"
    >
        <Head :title="trans('page-logs.title')" />

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
